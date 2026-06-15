<?php

namespace App\Console\Commands;

use App\Constants\OrderStatus;
use App\Constants\OrderItemStatus;
use App\Models\Order;
use App\Models\ProductVariant;
use App\Services\OrderService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class StockAllocationCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'stock:allocate
                            {--dry-run : Run without making changes}
                            {--detail : Show detailed output}';

    /**
     * The console command description.
     */
    protected $description = 'Allocate stock to pending orders using FIFO principle';

    /**
     * Statistics for reporting
     */
    protected $stats = [
        'total_orders_processed' => 0,
        'new_to_confirm' => 0,
        'pending_to_in_stock' => 0,
        'confirm_to_pending' => 0,
        'no_change' => 0,
        'errors' => 0,
    ];

    /**
     * Order service for timeline
     */
    protected $orderService;

    /**
     * Constructor
     */
    public function __construct(OrderService $orderService)
    {
        parent::__construct();
        $this->orderService = $orderService;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $startTime = microtime(true);
        $this->info('🚀 Starting Stock Allocation Command...');
        $this->info('Time: ' . now()->toDateTimeString());
        $this->newLine();

        try {
            // STEP 1: IDENTIFICATION - Get eligible orders
            $this->info('📋 STEP 1: Identifying eligible orders...');
            $orders = $this->getEligibleOrders();
            $this->info("Found {$orders->count()} orders to process");
            $this->newLine();

            if ($orders->isEmpty()) {
                $this->info('✅ No orders to process. Exiting.');
                return 0;
            }

            // STEP 2: SNAPSHOT - Create stock snapshot
            $this->info('📸 STEP 2: Creating stock snapshot...');
            $stockSnapshot = $this->createStockSnapshot();
            $this->info('Snapshot created for ' . count($stockSnapshot) . ' variants');
            $this->newLine();

            // STEP 3: GROUPING - Group items by variant
            $this->info('📦 STEP 3: Grouping items by variant...');
            $groupedItems = $this->groupItemsByVariant($orders);
            $this->info('Grouped into ' . count($groupedItems) . ' variant groups');
            $this->newLine();

            // STEP 4: SIMULATION - Simulate stock allocation
            $this->info('🎯 STEP 4: Simulating FIFO stock allocation...');
            $allocationResults = $this->simulateAllocation($groupedItems, $stockSnapshot);
            $this->newLine();

            // STEP 5: EVALUATION - Determine new order statuses
            $this->info('⚖️  STEP 5: Evaluating order statuses...');
            $statusChanges = $this->evaluateOrderStatuses($orders, $allocationResults);
            $this->info('Status changes determined for ' . count($statusChanges) . ' orders');
            $this->newLine();

            // STEP 6: PERSISTENCE - Save changes to database
            if ($this->option('dry-run')) {
                $this->warn('🔍 DRY RUN MODE - No changes will be saved');
                $this->displayStatusChanges($statusChanges);
            } else {
                $this->info('💾 STEP 6: Persisting changes to database...');
                $this->persistChanges($statusChanges);
                $this->info('Changes saved successfully');

                // Always update pending_demand after allocation (even if no status changes)
                $this->updatePendingDemand();
            }
            $this->newLine();

            // STEP 7: REPORTING - Display results
            $this->info('📊 STEP 7: Generating report...');
            $this->displayReport($startTime);

            $this->newLine();
            $this->info('✅ Stock Allocation Command completed successfully!');

            return 0;
        } catch (\Exception $e) {
            $this->error('❌ Error: ' . $e->getMessage());
            Log::error('Stock Allocation Command failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return 1;
        }
    }

    /**
     * STEP 1: Get eligible orders for stock allocation
     * 
     * Criteria:
     * - Payment status = 'paid'
     * - Fulfill status in: new_order, pending_stock, confirm, in_stock
     *   (Re-check confirm/in_stock to prevent race conditions when stock depleted by other orders)
     * - Must have all required files (PES, DST, JSON) for each design
     * - Sorted by created_at ASC (FIFO)
     */
    protected function getEligibleOrders()
    {
        $orders = Order::with(['items' => function ($query) {
            $query->where('status', OrderItemStatus::UNPROCESSED)
                ->with('metas')
                ->orderBy('id', 'asc');
        }])
            ->whereIn('fulfill_status', OrderStatus::getEligibleForAllocation()) // Now includes CONFIRM and IN_STOCK
            ->where('payment_status', OrderStatus::PAYMENT_PAID)
            ->where('created_at', '<=', now()->subMinutes(40)) // Only process orders created > 40 mins ago
            ->orderBy('created_at', 'asc')
            ->orderBy('id', 'asc')
            ->get();

        // Filter out orders with missing files
        $eligibleOrders = $orders->filter(function ($order) {
            $missingFiles = $this->checkMissingFiles($order);

            if (!empty($missingFiles)) {
                if ($this->option('detail')) {
                    $this->line("Order #{$order->id} - Skipped (missing files: " . implode(', ', array_slice($missingFiles, 0, 3)) . (count($missingFiles) > 3 ? '...' : '') . ")");
                }

                Log::info('StockAllocation: Order skipped due to missing files', [
                    'order_id' => $order->id,
                    'ref_id' => $order->ref_id,
                    'missing_files' => $missingFiles
                ]);

                return false;
            }

            return true;
        });

        if ($orders->count() !== $eligibleOrders->count()) {
            $skipped = $orders->count() - $eligibleOrders->count();
            $this->warn("Skipped {$skipped} orders due to missing files (PES/DST/JSON)");
        }

        return $eligibleOrders;
    }

    /**
     * Check if order is missing required files (PES, DST, JSON)
     * Returns array of missing file types, empty array if all files present
     */
    protected function checkMissingFiles(Order $order): array
    {
        $missingFiles = [];

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

            // For each base key, check if PES has value AND DST and JSON exist
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
     * STEP 2: Create stock snapshot
     * 
     * Returns: ['variant_id' => stock_quantity]
     */
    protected function createStockSnapshot(): array
    {
        $snapshot = [];

        $variants = ProductVariant::select('variant_id', 'stock')
            ->where('active', true)
            ->get();

        foreach ($variants as $variant) {
            $snapshot[$variant->variant_id] = (int) $variant->stock;
        }

        if ($this->option('detail')) {
            $this->line('Stock snapshot sample:');
            $sample = array_slice($snapshot, 0, 5, true);
            foreach ($sample as $variantId => $stock) {
                $this->line("  - {$variantId}: {$stock}");
            }
            if (count($snapshot) > 5) {
                $this->line('  ... and ' . (count($snapshot) - 5) . ' more');
            }
        }

        return $snapshot;
    }

    /**
     * STEP 3: Group items by variant
     * 
     * Returns: ['variant_id' => [items sorted by FIFO]]
     */
    protected function groupItemsByVariant($orders): array
    {
        $grouped = [];

        foreach ($orders as $order) {
            foreach ($order->items as $item) {
                if (!OrderItemStatus::isUnprocessed($item->status)) {
                    continue; // Skip processed items
                }

                $variantId = $item->variant_id;

                if (!isset($grouped[$variantId])) {
                    $grouped[$variantId] = [];
                }

                $grouped[$variantId][] = [
                    'order_id' => $order->id,
                    'item_id' => $item->id,
                    'variant_id' => $variantId,
                    'quantity' => (int) $item->quantity,
                    'created_at' => $order->created_at,
                    'order_ref' => $order->ref_id,
                ];
            }
        }

        // Sort items within each group by FIFO
        foreach ($grouped as $variantId => &$items) {
            usort($items, function ($a, $b) {
                $timeCompare = $a['created_at'] <=> $b['created_at'];
                if ($timeCompare !== 0) {
                    return $timeCompare;
                }
                return $a['order_id'] <=> $b['order_id'];
            });
        }

        return $grouped;
    }

    /**
     * STEP 4: Simulate FIFO stock allocation
     * 
     * Returns: ['order_id' => ['item_id' => true/false]]
     */
    protected function simulateAllocation(array $groupedItems, array $stockSnapshot): array
    {
        $allocationResults = [];
        $workingStock = $stockSnapshot;

        foreach ($groupedItems as $variantId => $items) {
            $currentStock = $workingStock[$variantId] ?? 0;

            if ($this->option('detail')) {
                $this->line("Processing variant {$variantId} (stock: {$currentStock})");
            }

            foreach ($items as $item) {
                $orderId = $item['order_id'];
                $itemId = $item['item_id'];
                $quantity = $item['quantity'];

                // Initialize order allocation if not exists
                if (!isset($allocationResults[$orderId])) {
                    $allocationResults[$orderId] = [];
                }

                // Check if we can allocate
                if ($currentStock >= $quantity) {
                    // ALLOCATE
                    $allocationResults[$orderId][$itemId] = true;
                    $currentStock -= $quantity;

                    if ($this->option('detail')) {
                        $this->line("  ✓ ALLOCATE Order #{$orderId}, Item #{$itemId}, Qty: {$quantity}, Remaining: {$currentStock}");
                    }
                } else {
                    // PENDING
                    $allocationResults[$orderId][$itemId] = false;
                    $currentStock -= $quantity; // Allow negative

                    if ($this->option('detail')) {
                        $shortage = $quantity - ($currentStock + $quantity);
                        $this->line("  ✗ PENDING Order #{$orderId}, Item #{$itemId}, Qty: {$quantity}, Shortage: {$shortage}");
                    }
                }
            }

            // Update working stock
            $workingStock[$variantId] = $currentStock;
        }

        return $allocationResults;
    }

    /**
     * STEP 5: Evaluate new order statuses based on allocation results
     * 
     * Returns: ['order_id' => ['old_status' => ..., 'new_status' => ...]]
     */
    protected function evaluateOrderStatuses($orders, array $allocationResults): array
    {
        $statusChanges = [];

        foreach ($orders as $order) {
            $orderId = $order->id;
            $currentStatus = $order->fulfill_status;

            // Check if all items are allocated
            $allAllocated = true;
            $itemResults = $allocationResults[$orderId] ?? [];

            foreach ($order->items as $item) {
                if (!OrderItemStatus::isUnprocessed($item->status)) {
                    continue; // Skip processed items
                }

                if (!isset($itemResults[$item->id]) || $itemResults[$item->id] === false) {
                    $allAllocated = false;
                    break;
                }
            }

            // Determine new status based on business rules
            $newStatus = $allAllocated
                ? OrderStatus::getNextStatusWhenAllocated($currentStatus)
                : OrderStatus::getNextStatusWhenPending($currentStatus);

            if ($newStatus !== $currentStatus) {
                $statusChanges[$orderId] = [
                    'order' => $order,
                    'old_status' => $currentStatus,
                    'new_status' => $newStatus,
                    'all_allocated' => $allAllocated,
                ];

                $this->stats['total_orders_processed']++;

                // Track specific transitions
                if ($currentStatus === OrderStatus::NEW_ORDER && $newStatus === OrderStatus::CONFIRM) {
                    $this->stats['new_to_confirm']++;
                } elseif ($currentStatus === OrderStatus::PENDING_STOCK && $newStatus === OrderStatus::IN_STOCK) {
                    $this->stats['pending_to_in_stock']++;
                } elseif (in_array($currentStatus, [OrderStatus::NEW_ORDER, OrderStatus::CONFIRM, OrderStatus::IN_STOCK]) && $newStatus === OrderStatus::PENDING_STOCK) {
                    // Now includes: new_order→pending, confirm→pending, AND in_stock→pending (race condition fix)
                    $this->stats['confirm_to_pending']++;
                }
            } else {
                $this->stats['no_change']++;
            }
        }

        return $statusChanges;
    }



    /**
     * STEP 6: Persist status changes to database
     */
    protected function persistChanges(array $statusChanges): void
    {
        if (empty($statusChanges)) {
            $this->info('No changes to persist');
            return;
        }

        DB::beginTransaction();

        try {
            foreach ($statusChanges as $orderId => $change) {
                $order = $change['order'];
                $oldStatus = $change['old_status'];
                $newStatus = $change['new_status'];
                $allAllocated = $change['all_allocated'];

                // Update order status
                $order->update(['fulfill_status' => $newStatus]);

                // Create timeline entry
                $statusText = strtoupper(str_replace('_', ' ', $newStatus));
                $reason = $allAllocated ? 'all items allocated' : 'insufficient stock';

                $this->orderService->createTimeline(
                    $order,
                    'stock_allocation',
                    "System auto-updated order to {$statusText} ({$reason})"
                );

                // Log the change
                Log::info('Stock allocation status change', [
                    'order_id' => $orderId,
                    'ref_id' => $order->ref_id,
                    'old_status' => $oldStatus,
                    'new_status' => $newStatus,
                    'all_allocated' => $allAllocated,
                ]);
            }

            DB::commit();

            $this->info('Successfully updated ' . count($statusChanges) . ' orders');
        } catch (\Exception $e) {
            DB::rollBack();
            $this->stats['errors']++;
            throw $e;
        }
    }

    /**
     * Update pending_demand column for all product variants
     * 
     * Calculates total quantity needed from orders with fulfill_status = 'pending_stock'
     * and updates the pending_demand column in product_variants table.
     */
    protected function updatePendingDemand(): void
    {
        $this->info('📦 Updating pending_demand for variants...');

        try {
            // Calculate pending demand per variant from pending_stock orders
            $pendingDemands = DB::table('orders')
                ->join('order_items', 'orders.id', '=', 'order_items.order_id')
                ->where('orders.fulfill_status', OrderStatus::PENDING_STOCK)
                ->where('orders.payment_status', OrderStatus::PAYMENT_PAID)
                ->where('order_items.status', OrderItemStatus::UNPROCESSED)
                ->groupBy('order_items.variant_id')
                ->select(
                    'order_items.variant_id',
                    DB::raw('SUM(order_items.quantity) as total_demand')
                )
                ->get()
                ->keyBy('variant_id');

            // Reset all pending_demand to 0 first
            ProductVariant::query()->update(['pending_demand' => 0]);

            // Update pending_demand for variants with pending orders
            foreach ($pendingDemands as $variantId => $demand) {
                $totalDemand = is_object($demand) ? $demand->total_demand : $demand;

                ProductVariant::where('variant_id', $variantId)
                    ->update(['pending_demand' => (int) $totalDemand]);
            }

            $this->info('Updated pending_demand for ' . count($pendingDemands) . ' variants');

            Log::info('Pending demand updated', [
                'variants_updated' => count($pendingDemands),
                'total_pending_quantity' => $pendingDemands->sum('total_demand'),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to update pending_demand', [
                'error' => $e->getMessage(),
            ]);
            $this->warn('Failed to update pending_demand: ' . $e->getMessage());
        }
    }

    /**
     * Display status changes (for dry-run mode)
     */
    protected function displayStatusChanges(array $statusChanges): void
    {
        if (empty($statusChanges)) {
            $this->info('No status changes would be made');
            return;
        }

        $this->table(
            ['Order ID', 'Ref ID', 'Old Status', 'New Status', 'All Allocated'],
            collect($statusChanges)->map(function ($change) {
                return [
                    $change['order']->id,
                    $change['order']->ref_id,
                    $change['old_status'],
                    $change['new_status'],
                    $change['all_allocated'] ? 'Yes' : 'No',
                ];
            })->toArray()
        );
    }

    /**
     * STEP 7: Display execution report
     */
    protected function displayReport(float $startTime): void
    {
        $executionTime = round(microtime(true) - $startTime, 2);

        $this->newLine();
        $this->info('═══════════════════════════════════════════');
        $this->info('           EXECUTION REPORT');
        $this->info('═══════════════════════════════════════════');
        $this->newLine();

        $this->line("⏱️  Execution Time: {$executionTime}s");
        $this->line("📊 Total Orders Processed: {$this->stats['total_orders_processed']}");
        $this->line("📈 Status Changes:");
        $this->line("   • NEW_ORDER/PRIORITY/EXPRESS → CONFIRM: {$this->stats['new_to_confirm']}");
        $this->line("   • PENDING_STOCK → IN_STOCK: {$this->stats['pending_to_in_stock']}");
        $this->line("   • Any → PENDING_STOCK: {$this->stats['confirm_to_pending']}");
        $this->line("   • No Change: {$this->stats['no_change']}");

        if ($this->stats['errors'] > 0) {
            $this->line("❌ Errors: {$this->stats['errors']}");
        }

        $this->newLine();
        $this->info('═══════════════════════════════════════════');
    }
}
