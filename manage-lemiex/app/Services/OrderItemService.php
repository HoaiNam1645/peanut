<?php

namespace App\Services;

use App\Models\OrderItem;
use App\Models\OrderItemMeta;
use App\Models\OrderItemWorkflow;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;

class OrderItemService
{
    protected $productionService;
    protected $orderService;

    // Trackable workflow positions across embroidery and print orders.
    const TRACKABLE_POSITIONS = ['front', 'back', 'sleeve_left', 'sleeve_right', 'neck', 'wrap'];

    public function __construct(
        ProductionService $productionService,
        OrderService $orderService
    ) {
        $this->productionService = $productionService;
        $this->orderService = $orderService;
    }


    /**
     * Change workflow status for any stage (staff, qc, packing, shipout)
     * Auto-completes previous stages if needed
     * Updates order status based on stage completion
     */
    public function changeWorkflowStatus(int $itemId, string $position, string $stage, bool $status): array
    {
        try {
            DB::beginTransaction();

            // Validate stage
            $validStages = ['staff', 'qc', 'packing', 'shipout'];
            if (!in_array($stage, $validStages)) {
                DB::rollBack();
                return [
                    'success' => false,
                    'message' => "Invalid stage '{$stage}'",
                    'code' => 400
                ];
            }

            // Step 1: Load order item with relations
            $orderItem = OrderItem::with(['order', 'productions', 'metas'])
                ->find($itemId);

            if (!$orderItem) {
                DB::rollBack();
                return [
                    'success' => false,
                    'message' => 'Order item not found',
                    'code' => 404
                ];
            }

            $order = $orderItem->order;

            if (!$order) {
                DB::rollBack();
                return [
                    'success' => false,
                    'message' => 'Order not found',
                    'code' => 404
                ];
            }

            // Block workflow on cancelled orders
            // Block workflow on cancelled orders
            $cancelledStatuses = ['cancelled', 'cancelled_refund_shipping'];
            if (in_array($order->fulfill_status, $cancelledStatuses)) {
                DB::rollBack();
                return [
                    'success' => false,
                    'message' => 'Cannot process workflow - order is cancelled',
                    'code' => 400
                ];
            }

            // Step 2: Validate position
            if (!in_array($position, self::TRACKABLE_POSITIONS)) {
                DB::rollBack();
                return [
                    'success' => false,
                    'message' => "Position '{$position}' is not trackable",
                    'code' => 400
                ];
            }

            $user = Auth::user();
            $userId = $user ? $user->id : null;
            $username = $user ? $user->username : 'System';
            $itemName = $orderItem->product_name ?? "Item #{$itemId}";

            // Step 3: Auto-complete previous stages if needed
            $stageOrder = ['staff', 'qc', 'packing', 'shipout'];
            $currentStageIndex = array_search($stage, $stageOrder);

            for ($i = 0; $i < $currentStageIndex; $i++) {
                $prevStage = $stageOrder[$i];
                $prevWorkflow = OrderItemWorkflow::firstOrCreate(
                    [
                        'order_item_id' => $itemId,
                        'position' => $position,
                        'stage' => $prevStage,
                    ],
                    [
                        'completed' => false,
                        'completed_by' => null,
                        'completed_at' => null,
                    ]
                );

                if (!$prevWorkflow->completed) {
                    $prevWorkflow->update([
                        'completed' => true,
                        'completed_by' => $userId,
                        'completed_at' => now(),
                    ]);

                    Log::info("Auto-completed previous stage", [
                        'item_id' => $itemId,
                        'position' => $position,
                        'stage' => $prevStage,
                        'auto_completed_by' => $stage
                    ]);
                }
            }

            // Step 4: Find or create workflow for current stage
            $workflow = OrderItemWorkflow::firstOrCreate(
                [
                    'order_item_id' => $itemId,
                    'position' => $position,
                    'stage' => $stage,
                ],
                [
                    'completed' => false,
                    'completed_by' => null,
                    'completed_at' => null,
                ]
            );

            // Step 4.5: Prevent unchecking completed workflow (use qcRejectItem API instead)
            if ($workflow->completed && !$status) {
                DB::rollBack();
                return [
                    'success' => false,
                    'message' => 'Cannot uncheck completed workflow. Use QC reject API instead.',
                    'code' => 400
                ];
            }

            // Step 5: Update workflow status (normal flow - completing)
            $workflow->update([
                'completed' => $status,
                'completed_by' => $status ? $userId : null,
                'completed_at' => $status ? now() : null,
            ]);

            Log::info('Order item workflow status updated', [
                'item_id' => $itemId,
                'position' => $position,
                'stage' => $stage,
                'completed' => $status
            ]);

            // Step 6: Get all positions for this item
            $existingPositions = $this->getTrackablePositionsForItem($orderItem);

            $totalPositions = count($existingPositions);

            // Step 7: Check stage completion and update order status accordingly
            $orderStatusChanged = false;
            $itemStatusChanged = false;

            // Count completed positions for current stage
            $completedForStage = OrderItemWorkflow::where('order_item_id', $itemId)
                ->where('stage', $stage)
                ->whereIn('position', $existingPositions)
                ->where('completed', true)
                ->count();

            $allPositionsCompleted = $totalPositions > 0 && $completedForStage === $totalPositions;

            // Handle stage-specific logic
            switch ($stage) {
                case 'staff':
                    // When all staff positions done → item status = true
                    if ($allPositionsCompleted && !$orderItem->status) {
                        $production = $orderItem->productions()->first();
                        if ($production) {
                            $mapResult = $this->productionService->mapStock($production);
                            if (!$mapResult['success']) {
                                DB::rollBack();
                                return [
                                    'success' => false,
                                    'message' => $mapResult['message'] ?? 'Failed to map stock',
                                    'code' => 400
                                ];
                            }
                        }
                        $orderItem->update(['status' => true]);
                        $itemStatusChanged = true;
                    }

                    // Update order to producing immediately when any staff workflow is touched/completed
                    // User request: "only tick 1 face then change to producing immediately"
                    if (!in_array($order->fulfill_status, ['producing', 'qc_pass', 'packed', 'shipped'])) {
                        $order->update(['fulfill_status' => 'producing']);
                        $orderStatusChanged = true;
                    }
                    break;

                case 'qc':
                    // When all QC positions done for ALL items → order → qc_pass
                    $allItemsQcPassed = $this->checkAllItemsStageCompleted($order->id, 'qc');
                    if ($allItemsQcPassed && !in_array($order->fulfill_status, ['qc_pass', 'packed', 'shipped'])) {
                        $order->update(['fulfill_status' => 'qc_pass']);
                        $orderStatusChanged = true;

                        Log::info('Order status auto-updated to QC_PASS', [
                            'order_id' => $order->id
                        ]);
                    }
                    break;

                case 'packing':
                    // When all packing positions done for ALL items → order → packed
                    $allItemsPacked = $this->checkAllItemsStageCompleted($order->id, 'packing');
                    if ($allItemsPacked && !in_array($order->fulfill_status, ['packed', 'shipped'])) {
                        $order->update(['fulfill_status' => 'packed']);
                        $orderStatusChanged = true;

                        Log::info('Order status auto-updated to PACKED', [
                            'order_id' => $order->id
                        ]);
                    }
                    break;

                case 'shipout':
                    // When all shipout positions done for ALL items → order → shipped
                    $allItemsShipped = $this->checkAllItemsStageCompleted($order->id, 'shipout');
                    if ($allItemsShipped && $order->fulfill_status !== 'shipped') {
                        $order->update(['fulfill_status' => 'shipped']);
                        $orderStatusChanged = true;

                        Log::info('Order status auto-updated to SHIPPED', [
                            'order_id' => $order->id
                        ]);
                    }
                    break;
            }

            // Step 8: Create timeline
            $stageLabels = [
                'staff' => 'Production',
                'qc' => 'QC',
                'packing' => 'Packing',
                'shipout' => 'Shipout'
            ];
            $stageLabel = $stageLabels[$stage] ?? $stage;
            $statusText = $status ? 'completed' : 'not completed';

            $this->orderService->createTimeline(
                $order,
                'workflow_status_changed',
                "{$username} marked {$itemName} [{$position}] {$stageLabel} as {$statusText}"
            );

            if ($orderStatusChanged) {
                $this->orderService->createTimeline(
                    $order,
                    'status_changed',
                    "{$username} auto-updated order to {$order->fulfill_status}"
                );
            }

            DB::commit();

            // Step 9: Clear cache
            $this->clearTrackOrderCache($order->id);

            return [
                'success' => true,
                'message' => "{$stageLabel} status updated successfully",
                'data' => [
                    'item_id' => $itemId,
                    'position' => $position,
                    'stage' => $stage,
                    'workflow_completed' => $status,
                    'item_status' => $orderItem->fresh()->status,
                    'item_status_changed' => $itemStatusChanged,
                    'total_positions' => $totalPositions,
                    'completed_positions' => $completedForStage,
                    'order_id' => $order->id,
                    'order_status' => $order->fresh()->fulfill_status,
                    'order_status_changed' => $orderStatusChanged,
                ]
            ];
        } catch (Exception $e) {
            DB::rollBack();

            Log::error('Failed to change workflow status', [
                'item_id' => $itemId,
                'position' => $position,
                'stage' => $stage,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'message' => 'Failed to change workflow status: ' . $e->getMessage(),
                'code' => 500
            ];
        }
    }

    /**
     * Check if all items in an order have completed a specific stage for all positions
     */
    private function checkAllItemsStageCompleted(int $orderId, string $stage): bool
    {
        $items = OrderItem::where('order_id', $orderId)->get();

        foreach ($items as $item) {
            $positions = $this->getTrackablePositionsForItem($item);

            if (empty($positions)) {
                continue; // No trackable positions for this item
            }

            $completedCount = OrderItemWorkflow::where('order_item_id', $item->id)
                ->where('stage', $stage)
                ->whereIn('position', $positions)
                ->where('completed', true)
                ->count();

            if ($completedCount < count($positions)) {
                return false; // Not all positions completed for this item
            }
        }

        return true;
    }


    /**
     * Clear track order cache for an order
     * Cache keys match URL format: track_{order_id}?stt={stt}&item_id={item_id}&item_stt={item_stt}
     */
    private function clearTrackOrderCache(int $orderId): void
    {
        try {
            // Get all items for this order with their quantities
            $items = OrderItem::where('order_id', $orderId)
                ->select('id', 'quantity')
                ->get();

            $clearedKeys = [];

            $trackPrefixes = ["track_{$orderId}", "track_v2_{$orderId}"];
            foreach ($trackPrefixes as $baseKey) {
                Cache::forget($baseKey);
                $clearedKeys[] = $baseKey;
            }

            // Track cumulative stt and item_stt (page index)
            $cumulativeStt = 0;
            $itemStt = 0;

            foreach ($items as $item) {
                $itemStt++;
                $itemId = $item->id;
                $quantity = $item->quantity ?? 1;

                foreach ($trackPrefixes as $prefix) {
                    Cache::forget("{$prefix}?item_stt={$itemStt}");
                    Cache::forget("{$prefix}?item_id={$itemId}");
                    Cache::forget("{$prefix}?item_id={$itemId}&item_stt={$itemStt}");
                }

                // Clear cache for each quantity of this item
                for ($q = 1; $q <= $quantity; $q++) {
                    $cumulativeStt++;

                    foreach ($trackPrefixes as $prefix) {
                        $combinations = [
                            "{$prefix}?stt={$cumulativeStt}",
                            "{$prefix}?stt={$cumulativeStt}&item_id={$itemId}",
                            "{$prefix}?stt={$cumulativeStt}&item_stt={$itemStt}",
                            "{$prefix}?stt={$cumulativeStt}&item_id={$itemId}&item_stt={$itemStt}",
                        ];

                        foreach ($combinations as $key) {
                            Cache::forget($key);
                            $clearedKeys[] = $key;
                        }
                    }
                }
            }

            Log::info('Cleared track order cache', [
                'order_id' => $orderId,
                'keys_count' => count($clearedKeys)
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to clear track order cache', [
                'order_id' => $orderId,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Complete ALL positions for a stage at once (used by QC/Packing/Shipout scanner)
     * Auto-completes previous stages if needed
     * Updates item status and order fulfill_status accordingly
     */
    public function completeAllPositionsForStage(int $itemId, string $stage, bool $status): array
    {
        try {
            DB::beginTransaction();

            // Validate stage
            $validStages = ['qc', 'packing', 'shipout'];
            if (!in_array($stage, $validStages)) {
                DB::rollBack();
                return [
                    'success' => false,
                    'message' => "Stage must be one of: qc, packing, shipout",
                    'code' => 400
                ];
            }

            // Load order item
            $orderItem = OrderItem::with(['order', 'productions', 'metas'])->find($itemId);

            if (!$orderItem) {
                DB::rollBack();
                return [
                    'success' => false,
                    'message' => 'Order item not found',
                    'code' => 404
                ];
            }

            $order = $orderItem->order;
            if (!$order) {
                DB::rollBack();
                return [
                    'success' => false,
                    'message' => 'Order not found',
                    'code' => 404
                ];
            }

            // Block workflow on cancelled orders
            $cancelledStatuses = ['cancelled', 'cancelled_refund_shipping'];
            if (in_array($order->fulfill_status, $cancelledStatuses)) {
                DB::rollBack();
                return [
                    'success' => false,
                    'message' => 'Cannot process workflow - order is cancelled',
                    'code' => 400
                ];
            }

            // Validate order status for packing and shipout stages
            // QC auto-completes staff stage, so no validation needed for QC
            // Packing requires QC to be passed first
            if ($stage === 'packing' && $order->fulfill_status !== 'qc_pass') {
                DB::rollBack();
                return [
                    'success' => false,
                    'message' => 'Order must pass QC before packing. Current status: ' . $order->fulfill_status,
                    'code' => 400
                ];
            }

            // Shipout requires order to be packed first
            // TEMPORARILY DISABLED: Role packing not used currently
            // Uncomment below to re-enable this validation
            // if ($stage === 'shipout' && $order->fulfill_status !== 'packed') {
            //     DB::rollBack();
            //     return [
            //         'success' => false,
            //         'message' => 'Order must be packed before shipout. Current status: ' . $order->fulfill_status,
            //         'code' => 400
            //     ];
            // }

            // Get existing positions for this item
            $existingPositions = $this->getTrackablePositionsForItem($orderItem);

            if (empty($existingPositions)) {
                DB::rollBack();
                return [
                    'success' => false,
                    'message' => 'No trackable positions found for this item',
                    'code' => 400
                ];
            }

            $user = Auth::user();
            $userId = $user ? $user->id : null;
            $username = $user ? $user->username : 'System';
            $itemName = $orderItem->product_name ?? "Item #{$itemId}";

            // Auto-complete ALL previous stages for ALL positions
            $stageOrder = ['staff', 'qc', 'packing', 'shipout'];
            $currentStageIndex = array_search($stage, $stageOrder);

            foreach ($existingPositions as $position) {
                // Complete previous stages
                for ($i = 0; $i <= $currentStageIndex; $i++) {
                    $stg = $stageOrder[$i];

                    $workflow = OrderItemWorkflow::firstOrCreate(
                        [
                            'order_item_id' => $itemId,
                            'position' => $position,
                            'stage' => $stg,
                        ],
                        [
                            'completed' => false,
                            'completed_by' => null,
                            'completed_at' => null,
                        ]
                    );

                    if (!$workflow->completed && $status) {
                        $workflow->update([
                            'completed' => true,
                            'completed_by' => $userId,
                            'completed_at' => now(),
                        ]);
                    }
                }
            }

            Log::info("Completed all positions for stage: {$stage}", [
                'item_id' => $itemId,
                'order_id' => $order->id,
                'stage' => $stage,
                'positions' => $existingPositions
            ]);

            // Update item status = true (if staff stage is complete, need to map stock)
            if (!$orderItem->status && $status) {
                // Map stock if not already done
                $production = $orderItem->productions()->first();
                if ($production && $production->status === 'pending') {
                    $mapResult = $this->productionService->mapStock($production);
                    if (!$mapResult['success']) {
                        Log::warning('Failed to map stock during stage completion', [
                            'production_id' => $production->id,
                            'error' => $mapResult['message']
                        ]);
                        // Continue anyway
                    }
                }

                $orderItem->update(['status' => true]);
            }

            // Check if ALL items in order have completed this stage before updating order status
            $allItemsCompleted = $this->checkAllItemsStageCompleted($order->id, $stage);
            $orderStatusChanged = false;
            $newFulfillStatus = $order->fulfill_status;

            if ($allItemsCompleted) {
                $newFulfillStatus = match ($stage) {
                    'qc' => 'qc_pass',
                    'packing' => 'packed',
                    'shipout' => 'shipped',
                    default => $order->fulfill_status
                };

                if ($order->fulfill_status !== $newFulfillStatus) {
                    $order->update(['fulfill_status' => $newFulfillStatus]);
                    $orderStatusChanged = true;

                    Log::info("Order status updated to {$newFulfillStatus} (all items completed)", [
                        'order_id' => $order->id,
                        'stage' => $stage
                    ]);
                }
            } else {
                Log::info("Item {$stage} completed, but waiting for other items", [
                    'order_id' => $order->id,
                    'item_id' => $itemId,
                    'stage' => $stage
                ]);
            }

            // Create timeline
            $this->orderService->createTimeline(
                $order,
                "{$stage}_complete",
                "{$username} completed {$stage} stage for {$itemName} (all positions)" .
                    ($orderStatusChanged ? " - Order status updated to {$newFulfillStatus}" : "")
            );

            DB::commit();
            $this->clearTrackOrderCache($order->id);

            return [
                'success' => true,
                'message' => ucfirst($stage) . ' completed for all positions' .
                    ($allItemsCompleted ? " - Order updated to {$newFulfillStatus}" : " - Waiting for other items"),
                'data' => [
                    'item_id' => $itemId,
                    'stage' => $stage,
                    'positions_completed' => $existingPositions,
                    'item_status' => true,
                    'order_id' => $order->id,
                    'order_status' => $order->fresh()->fulfill_status,
                    'order_status_changed' => $orderStatusChanged,
                    'all_items_completed' => $allItemsCompleted,
                ]
            ];
        } catch (Exception $e) {
            DB::rollBack();

            Log::error('Failed to complete all positions for stage', [
                'item_id' => $itemId,
                'stage' => $stage,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'message' => 'Failed to complete stage: ' . $e->getMessage(),
                'code' => 500
            ];
        }
    }

    /**
     * QC Reject Item - Reset all workflows, unmap stock, return order to support
     * Called by QC app when rejecting an item
     */
    public function qcRejectItem(int $itemId): array
    {
        try {
            DB::beginTransaction();

            // Load order item
            $orderItem = OrderItem::with(['order', 'productions'])->find($itemId);

            if (!$orderItem) {
                DB::rollBack();
                return [
                    'success' => false,
                    'message' => 'Order item not found',
                    'code' => 404
                ];
            }

            $order = $orderItem->order;
            if (!$order) {
                DB::rollBack();
                return [
                    'success' => false,
                    'message' => 'Order not found',
                    'code' => 404
                ];
            }

            $user = Auth::user();
            $username = $user ? $user->username : 'System';
            $itemName = $orderItem->product_name ?? "Item #{$itemId}";

            // Reset all workflows for this item
            OrderItemWorkflow::where('order_item_id', $itemId)->update([
                'completed' => false,
                'completed_by' => null,
                'completed_at' => null,
            ]);

            Log::info('QC rejected item - all workflows reset', [
                'item_id' => $itemId,
                'order_id' => $order->id
            ]);

            // Unmap stock (revert stock deduction)
            $production = $orderItem->productions()->first();
            if ($production && $production->status === 'mapped') {
                $unmapResult = $this->productionService->unmapStock($production);
                if (!$unmapResult['success']) {
                    Log::warning('Failed to unmap stock during QC rejection', [
                        'production_id' => $production->id,
                        'error' => $unmapResult['message']
                    ]);
                    // Continue anyway
                }
            }

            // Reset order item status
            $orderItem->update(['status' => false]);

            // Update order status to return_to_support
            $order->update(['fulfill_status' => 'return_to_support']);

            // Create timeline
            $this->orderService->createTimeline(
                $order,
                'qc_rejected',
                "{$username} (QC) rejected {$itemName} - Order returned to support"
            );

            DB::commit();
            $this->clearTrackOrderCache($order->id);

            return [
                'success' => true,
                'message' => 'Item rejected - order returned to support',
                'data' => [
                    'item_id' => $itemId,
                    'item_status' => false,
                    'order_id' => $order->id,
                    'order_status' => 'return_to_support',
                ]
            ];
        } catch (Exception $e) {
            DB::rollBack();

            Log::error('Failed to reject item', [
                'item_id' => $itemId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'message' => 'Failed to reject item: ' . $e->getMessage(),
                'code' => 500
            ];
        }
    }

    private function getTrackablePositionsForItem(OrderItem $orderItem): array
    {
        $metas = $orderItem->relationLoaded('metas')
            ? $orderItem->metas
            : $orderItem->metas()->get();

        return $metas
            ->map(fn($meta) => $this->extractTrackablePosition($meta->meta_key))
            ->filter()
            ->unique()
            ->values()
            ->toArray();
    }

    private function extractTrackablePosition(string $metaKey): ?string
    {
        if (in_array($metaKey, self::TRACKABLE_POSITIONS)) {
            return $metaKey;
        }

        if (in_array($metaKey, ['front_image', 'wrap_image'])) {
            return str_replace('_image', '', $metaKey);
        }

        // Print/design file metas (front_pdf / back_json / sleeve_left_dst / neck_emb …) →
        // position prefix. Must match trackOrder's extraction so QC/packing/shipout see the
        // same positions the track page shows (else "No trackable positions found").
        if (preg_match('/^(front|back|sleeve_left|sleeve_right|neck|wrap)_(pdf|json|dst|emb)$/', $metaKey, $matches)) {
            return $matches[1];
        }

        return null;
    }
}
