<?php

namespace App\Console\Commands;

use App\Enums\OrderFulfillStatus;
use App\Enums\OrderPaymentStatus;
use App\Jobs\BuyLabelShipEngine;
use App\Models\Order;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class BuyLabelCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:buy-label
                            {--limit=100 : Maximum number of orders to process}
                            {--delay=10 : Delay in seconds between jobs}
                            {--sync : Run jobs synchronously (for debugging)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Auto buy shipping labels for eligible orders via ShipEngine';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $limit = (int) $this->option('limit');
        $delay = (int) $this->option('delay');
        $sync = (bool) $this->option('sync');

        $this->info("Starting Buy Label Cron Job");
        $this->info("Limit: {$limit}, Delay: {$delay}s, Sync: " . ($sync ? 'Yes' : 'No'));

        Log::info("=== Start Buy Label Cron ===", [
            'limit' => $limit,
            'delay' => $delay,
            'timestamp' => now()->toDateTimeString(),
        ]);

        try {
            // Query orders that need labels
            $orders = Order::select('orders.id', 'orders.seller_id', 'orders.ref_id', 'orders.created_at')
                ->join('users', 'users.id', '=', 'orders.seller_id')
                ->join('user_profiles', 'user_profiles.user_id', '=', 'users.id')
                ->where('user_profiles.production', 1) // Seller has production permission
                ->where('orders.payment_status', OrderPaymentStatus::PAID) // Paid orders
                ->whereNotIn('orders.fulfill_status', [
                    OrderFulfillStatus::ON_HOLD,
                    OrderFulfillStatus::SHIPPED,
                    OrderFulfillStatus::RETURN_TO_SUPPORT,
                    OrderFulfillStatus::CANCELLED,
                    OrderFulfillStatus::CLOSED,
                ])
                ->where(function ($q) {
                    $q->whereNull('orders.shipping_label')
                        ->orWhere('orders.shipping_label', '');
                })
                ->where(function ($q) {
                    $q->whereNull('orders.tracking_id')
                        ->orWhere('orders.tracking_id', '');
                })
                ->where('orders.address_1', '!=', '') // Has shipping address
                ->where('orders.created_at', '>=', Carbon::now()->subDays(7)) // Not older than 7 days
                ->where('orders.created_at', '<=', Carbon::now()->subHours(1)) // At least 1 hour old
                ->orderBy('orders.id', 'ASC')
                ->limit($limit)
                ->get();

            $count = $orders->count();

            if ($count === 0) {
                $this->info("No orders found that need labels");
                Log::info("No eligible orders found");
                return self::SUCCESS;
            }

            $this->info("Found {$count} orders to process");
            Log::info("Found eligible orders", [
                'count' => $count,
                'order_ids' => $orders->pluck('id')->toArray(),
            ]);

            // Dispatch jobs for each order
            $dispatched = 0;
            foreach ($orders as $order) {
                try {
                    if ($sync) {
                        $this->info("Processing Order #{$order->id} (Sync)...");
                        BuyLabelShipEngine::dispatchSync($order->id, $order->seller_id);
                        $this->info("Processed Order #{$order->id} successfully.");
                    } else {
                        BuyLabelShipEngine::dispatch($order->id, $order->seller_id)
                            ->delay(now()->addSeconds($delay * $dispatched));

                        $this->info("Dispatched job for Order #{$order->id} (Ref: {$order->ref_id})");
                    }

                    Log::info("Job " . ($sync ? "processed" : "dispatched"), [
                        'order_id' => $order->id,
                        'ref_id' => $order->ref_id,
                        'seller_id' => $order->seller_id,
                        'delay_seconds' => $sync ? 0 : ($delay * $dispatched),
                    ]);

                    $dispatched++;
                } catch (\Exception $e) {
                    $this->error("Failed to dispatch job for Order #{$order->id}: {$e->getMessage()}");

                    Log::error("Failed to dispatch job", [
                        'order_id' => $order->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            $this->info("Successfully dispatched {$dispatched} jobs");
            Log::info("=== Buy Label Cron Completed ===", [
                'total_found' => $count,
                'total_dispatched' => $dispatched,
            ]);

            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error("Cron job failed: {$e->getMessage()}");

            Log::error("Cron job exception", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return self::FAILURE;
        }
    }
}
