<?php

namespace App\Console\Commands;

use App\Constants\OrderStatus;
use App\Jobs\PayOrderJob;
use App\Models\Order;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class AutoPayOrdersCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'orders:auto-pay
                            {--limit=100 : Maximum number of orders to process}
                            {--dry-run : Run without dispatching jobs}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Automatically process payments for pending orders';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $limit = (int) $this->option('limit');
        $dryRun = $this->option('dry-run');

        $this->info("Starting auto-payment process...");
        $this->info("Limit: {$limit} orders");
        $this->info("Mode: " . ($dryRun ? 'DRY RUN' : 'LIVE'));

        try {
            // Find orders that need payment
            $orders = $this->findPendingOrders($limit);

            if ($orders->isEmpty()) {
                $this->info('No pending orders found for payment.');
                return self::SUCCESS;
            }

            $this->info("Found {$orders->count()} orders to process");

            $processed = 0;
            $skipped = 0;
            $dispatched = 0;

            foreach ($orders as $order) {
                $result = $this->processOrder($order, $dryRun);

                if ($result === 'dispatched') {
                    $dispatched++;
                } elseif ($result === 'skipped') {
                    $skipped++;
                }

                $processed++;

                // Progress indicator
                if ($processed % 10 === 0) {
                    $this->info("Progress: {$processed}/{$orders->count()}");
                }
            }

            // Summary
            $this->newLine();
            $this->info('=== Summary ===');
            $this->info("Total processed: {$processed}");
            $this->info("Jobs dispatched: {$dispatched}");
            $this->info("Skipped: {$skipped}");

            Log::info('AutoPayOrdersCommand completed', [
                'processed' => $processed,
                'dispatched' => $dispatched,
                'skipped' => $skipped,
                'dry_run' => $dryRun
            ]);

            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error('Failed to process auto-payment: ' . $e->getMessage());

            Log::error('AutoPayOrdersCommand failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return self::FAILURE;
        }
    }

    /**
     * Find orders that need payment
     */
    protected function findPendingOrders(int $limit)
    {
        return Order::query()
            ->with(['seller.profile'])
            ->where('payment_status', OrderStatus::PAYMENT_PENDING)
            ->whereNotIn('fulfill_status', [
                // OrderStatus::NEW_ORDER,
                OrderStatus::PRODUCING,
                OrderStatus::SHIPPED,
                OrderStatus::CANCELLED,
                OrderStatus::CANCELLED_REFUND_SHIPPING,
                OrderStatus::TEST_ORDER,
                OrderStatus::RETURN_TO_SUPPORT,
                OrderStatus::CLOSED,
            ])
            ->where('created_at', '<=', now()->subMinutes(30))
            ->where('total_cost', '>', 0)
            ->orderBy('created_at', 'asc')
            ->limit($limit)
            ->get();
    }

    /**
     * Process individual order
     */
    protected function processOrder(Order $order, bool $dryRun): string
    {
        // Check if seller has production enabled
        if (!$order->seller || !$order->seller->profile || !$order->seller->profile->production) {
            $this->line("  Order #{$order->id} - Skipped (seller not production)");
            return 'skipped';
        }

        // Check if order has all required files (PES, DST, JSON)
        $missingFiles = $this->checkMissingFiles($order);
        if (!empty($missingFiles)) {
            $this->line("  Order #{$order->id} - Skipped (missing files: " . implode(', ', $missingFiles) . ")");
            return 'skipped';
        }

        // Check credit availability
        $walletBalance = (float) $order->seller->profile->wallet_balance;
        $maxDebit = (float) $order->seller->profile->max_debit;
        $totalCost = (float) $order->total_cost;
        $newBalance = $walletBalance - $totalCost;

        // Check if seller has unlimited debt privilege (special accounts)
        $hasUnlimitedDebt = \App\Constants\SellerConstants::canHaveUnlimitedDebt($order->seller->username);

        // Allow payment if: has enough credit OR has unlimited debt privilege
        $canPay = $hasUnlimitedDebt || ($newBalance >= -$maxDebit);

        if (!$canPay) {
            $shortage = abs($newBalance + $maxDebit);
            $this->line("  Order #{$order->id} - Skipped (insufficient credit, shortage: $" . number_format($shortage, 2) . ")");
            return 'skipped';
        }

        // Dispatch job
        if (!$dryRun) {
            PayOrderJob::dispatch($order->id);
        }

        $this->line("  Order #{$order->id} - " . ($dryRun ? 'Would dispatch' : 'Dispatched') . " (cost: $" . number_format($totalCost, 2) . ")");
        return 'dispatched';
    }

    /**
     * Check if order is missing required files (PES, DST, JSON)
     * Returns array of missing file types, empty array if all files present
     * 
     * Logic:
     * - If order has _emb files → must have corresponding PES, DST, JSON to be paid
     * - If order has PES, DST, JSON (no emb) → can be paid
     * - If order has nothing (print order) → can be paid
     */
    protected function checkMissingFiles(Order $order): array
    {
        $missingFiles = [];

        // Load order items with metas
        $order->load('items.metas');

        // Positions to check
        $positions = ['front', 'back', 'sleeve_left', 'sleeve_right', 'neck'];

        foreach ($order->items as $item) {
            $metas = $item->metas;

            if (!$metas || $metas->isEmpty()) {
                continue; // No metas at all, might be print order
            }

            foreach ($positions as $position) {
                // Check if EMB file exists for this position
                $embEntry = $metas->where('meta_key', $position . '_emb')->first();
                $hasEmb = $embEntry && !empty($embEntry->meta_value);

                // Check if PES file exists for this position
                $pesEntry = $metas->where('meta_key', $position)->first();
                $hasPes = $pesEntry && !empty($pesEntry->meta_value);

                // Check if DST file exists
                $dstEntry = $metas->where('meta_key', $position . '_dst')->first();
                $hasDst = $dstEntry && !empty($dstEntry->meta_value);

                // Check if JSON file exists
                $jsonEntry = $metas->where('meta_key', $position . '_json')->first();
                $hasJson = $jsonEntry && !empty($jsonEntry->meta_value);

                // KEY LOGIC: If has EMB but no PES/DST/JSON → block payment
                if ($hasEmb && !$hasPes) {
                    $missingFiles[] = "Item#{$item->id}:{$position}_pes (has emb, waiting for pes)";
                }

                // If has PES, must have DST and JSON
                if ($hasPes) {
                    if (!$hasDst) {
                        $missingFiles[] = "Item#{$item->id}:{$position}_dst";
                    }
                    if (!$hasJson) {
                        $missingFiles[] = "Item#{$item->id}:{$position}_json";
                    }
                }

                // Edge case: has DST or JSON but no PES
                if (!$hasPes && ($hasDst || $hasJson)) {
                    $missingFiles[] = "Item#{$item->id}:{$position}_pes";
                }
            }
        }

        return $missingFiles;
    }
}
