<?php

namespace App\Jobs;

use App\Constants\OrderStatus;
use App\Constants\SellerConstants;
use App\Models\Order;
use App\Models\Timeline;
use App\Models\Transaction;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;

class PayOrderJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $timeout = 60;
    public $backoff = [10, 30, 60]; // Retry after 10s, 30s, 60s

    protected $orderId;

    public function __construct(int $orderId)
    {
        $this->orderId = $orderId;
    }

    /**
     * Get unique ID for job (prevent duplicate processing)
     */
    public function uniqueId(): string
    {
        return "pay-order-{$this->orderId}";
    }

    /**
     * Execute the job
     */
    public function handle(): void
    {
        try {
            // Load order with seller
            $order = Order::with(['seller.profile'])->find($this->orderId);

            if (!$order) {
                Log::error('PayOrderJob: Order not found', ['order_id' => $this->orderId]);
                return;
            }

            // STEP 2.2: Check idempotency - Already paid?
            if ($order->payment_status === OrderStatus::PAYMENT_PAID) {
                Log::info('PayOrderJob: Order already paid', [
                    'order_id' => $this->orderId,
                    'ref_id' => $order->ref_id
                ]);
                return;
            }

            $seller = $order->seller;
            if (!$seller || !$seller->profile) {
                Log::error('PayOrderJob: Seller or profile not found', [
                    'order_id' => $this->orderId,
                    'seller_id' => $order->seller_id
                ]);
                return;
            }

            // STEP 2.3: Check seller has production enabled
            if (!$seller->profile->production) {
                Log::info('PayOrderJob: Seller does not have production enabled', [
                    'order_id' => $this->orderId,
                    'seller_id' => $seller->id
                ]);
                return;
            }

            // STEP 2.4: Check order status is valid
            if (in_array($order->fulfill_status, [OrderStatus::CANCELLED, OrderStatus::TEST_ORDER, OrderStatus::ON_HOLD, OrderStatus::CLOSED])) {
                Log::info('PayOrderJob: Order status not eligible for payment', [
                    'order_id' => $this->orderId,
                    'status' => $order->fulfill_status
                ]);
                return;
            }

            // STEP 2.4.1: Check if order has all required files (PES, DST, JSON)
            $missingFiles = $this->checkMissingFiles($order);
            if (!empty($missingFiles)) {
                Log::info('PayOrderJob: Order missing required files', [
                    'order_id' => $this->orderId,
                    'missing_files' => $missingFiles
                ]);
                return;
            }

            // STEP 2.5: Check credit availability
            $walletBalance = (float) $seller->profile->wallet_balance;
            $maxDebit = (float) $seller->profile->max_debit;
            $totalCost = (float) $order->total_cost;

            // Skip if total cost is zero or negative
            if ($totalCost <= 0) {
                Log::info('PayOrderJob: Order total cost is zero or negative', [
                    'order_id' => $this->orderId,
                    'total_cost' => $totalCost
                ]);
                return;
            }

            $newBalance = $walletBalance - $totalCost;

            // Check if seller has unlimited debt privilege (special accounts)
            $hasUnlimitedDebt = SellerConstants::canHaveUnlimitedDebt($seller->username);

            // Allow payment if: has enough credit OR has unlimited debt privilege
            $canPay = $hasUnlimitedDebt || ($newBalance >= -$maxDebit);

            if (!$canPay) {
                $shortage = abs($newBalance + $maxDebit);
                Log::warning('PayOrderJob: Insufficient credit', [
                    'order_id' => $this->orderId,
                    'seller_id' => $seller->id,
                    'username' => $seller->username,
                    'wallet_balance' => $walletBalance,
                    'max_debit' => $maxDebit,
                    'total_cost' => $totalCost,
                    'new_balance' => $newBalance,
                    'shortage' => $shortage,
                    'has_unlimited_debt' => $hasUnlimitedDebt
                ]);
                return;
            }

            // Log if using unlimited debt privilege
            if ($hasUnlimitedDebt && $newBalance < -$maxDebit) {
                Log::info('PayOrderJob: Using unlimited debt privilege', [
                    'order_id' => $this->orderId,
                    'username' => $seller->username,
                    'new_balance' => $newBalance,
                    'normal_max_debit' => $maxDebit
                ]);
            }

            // STEP 3: Process payment
            $this->processPayment($order, $seller, $totalCost, $newBalance);
        } catch (Exception $e) {
            Log::error('PayOrderJob: Failed to process payment', [
                'order_id' => $this->orderId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            throw $e; // Re-throw to trigger retry
        }
    }

    /**
     * Check if order is missing required files (PES, DST, JSON)
     * Returns array of missing file types, empty array if all files present
     */
    protected function checkMissingFiles(Order $order): array
    {
        $missingFiles = [];

        // Load order items with metas
        $order->load('items.metas');

        foreach ($order->items as $item) {
            $metas = $item->metas;

            if (!$metas || $metas->isEmpty()) {
                continue; // No metas at all, might be print order
            }

            // Get base keys (front, back, sleeve_left, sleeve_right, neck)
            // These are the PES file entries
            $baseKeys = $metas->whereIn('meta_key', ['front', 'back', 'sleeve_left', 'sleeve_right', 'neck'])
                ->pluck('meta_key')
                ->toArray();

            // If no design files at all, skip this item (might be print order)
            if (empty($baseKeys)) {
                continue;
            }

            // For each base key, check if ALL 3 files exist: PES, DST, JSON
            foreach ($baseKeys as $baseKey) {
                $pesEntry = $metas->where('meta_key', $baseKey)->first();
                $hasPes = $pesEntry && !empty($pesEntry->meta_value);

                $dstEntry = $metas->where('meta_key', $baseKey . '_dst')->first();
                $hasDst = $dstEntry && !empty($dstEntry->meta_value);

                $jsonEntry = $metas->where('meta_key', $baseKey . '_json')->first();
                $hasJson = $jsonEntry && !empty($jsonEntry->meta_value);

                // Must have ALL 3: PES, DST, and JSON
                if ($hasPes && !$hasDst) {
                    $missingFiles[] = "Item#{$item->id}:{$baseKey}_dst";
                }
                if ($hasPes && !$hasJson) {
                    $missingFiles[] = "Item#{$item->id}:{$baseKey}_json";
                }
                // Also check if PES itself is missing when DST or JSON exists (edge case)
                if (!$hasPes && ($hasDst || $hasJson)) {
                    $missingFiles[] = "Item#{$item->id}:{$baseKey}_pes";
                }
            }
        }

        return $missingFiles;
    }

    /**
     * Process payment with transaction safety
     */
    protected function processPayment(Order $order, $seller, float $totalCost, float $newBalance): void
    {
        DB::beginTransaction();

        try {
            // STEP 3.4: Create transaction record
            $transaction = Transaction::create([
                'seller_id' => $seller->id,
                'order_id' => $order->id,
                'amount' => -$totalCost, // Negative because deducting
                'remaining_balance' => $newBalance,
                'type' => 'payment',
                'status' => 'approved',
                'note' => "Pay for order ID {$order->id} (Ref: {$order->ref_id})",
            ]);

            // STEP 3.5: Update priority flag - REMOVED

            // STEP 3.6: Create timeline entry
            $timelineAction = match ($order->fulfill_status) {
                OrderStatus::NEW_ORDER => 'pay order new',
                default => "pay order {$order->fulfill_status}",
            };

            Timeline::create([
                'object' => 'order',
                'object_id' => $order->id,
                'owner_id' => null, // System action
                'action' => $timelineAction,
                'note' => "Auto-payment processed. Amount: $" . number_format($totalCost, 2) . ". New balance: $" . number_format($newBalance, 2),
            ]);

            // STEP 3.7: Update order
            $order->update([
                'payment_status' => OrderStatus::PAYMENT_PAID,
                'paid_cost' => $totalCost,
            ]);

            // STEP 3.8: Update seller wallet
            $seller->profile->update([
                'wallet_balance' => $newBalance,
            ]);

            // STEP 3.9: Commit transaction
            DB::commit();

            Log::info('PayOrderJob: Payment processed successfully', [
                'order_id' => $order->id,
                'ref_id' => $order->ref_id,
                'seller_id' => $seller->id,
                'amount' => $totalCost,
                'old_balance' => $seller->profile->wallet_balance + $totalCost,
                'new_balance' => $newBalance,
                'transaction_id' => $transaction->id,
            ]);
        } catch (Exception $e) {
            DB::rollBack();

            Log::error('PayOrderJob: Transaction failed, rolled back', [
                'order_id' => $order->id,
                'error' => $e->getMessage()
            ]);

            throw $e;
        }
    }

    /**
     * Handle job failure
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('PayOrderJob: Job failed permanently', [
            'order_id' => $this->orderId,
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString()
        ]);
    }
}
