<?php

namespace App\Services;

use App\Enums\OrderFulfillStatus;
use App\Enums\OrderPaymentStatus;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderItemMeta;
use App\Models\OrderItemWorkflow;
use App\Models\Production;
use App\Models\ProductVariant;
use App\Models\StockAuditLog;
use App\Models\Timeline;
use App\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Service to handle fulfill status changes with complex business logic
 * Based on CHANGE_FULFILL_STATUS_SPEC.md
 */
class OrderFulfillStatusService
{
    protected $orderService;

    public function __construct(OrderService $orderService)
    {
        $this->orderService = $orderService;
    }

    /**
     * Validate if status transition is allowed
     * Admin can bypass all validations but special_handling actions are still executed
     */
    public function validateStatusTransition(Order $order, string $newStatus, $user): array
    {
        $currentStatus = $order->fulfill_status;
        $userRole = $user->role->name ?? null;

        // ABSOLUTE BLOCK: No one can transition TO new_order status
        // This rule applies to ALL roles including Admin
        if ($newStatus === OrderFulfillStatus::NEW_ORDER && $currentStatus !== OrderFulfillStatus::NEW_ORDER) {
            return [
                'allowed' => false,
                'message' => 'Cannot change status back to new_order. This status is only for newly created orders.'
            ];
        }

        // ABSOLUTE BLOCK: No one can transition TO in_stock status
        // This status is only set by the stock allocation system
        if ($newStatus === OrderFulfillStatus::IN_STOCK && $currentStatus !== OrderFulfillStatus::IN_STOCK) {
            return [
                'allowed' => false,
                'message' => 'Cannot change status to in_stock. This status is managed by the stock allocation system.'
            ];
        }

        // SPECIAL RULE: Staff and Admin can always change to return_to_support from ANY status
        if ($newStatus === OrderFulfillStatus::RETURN_TO_SUPPORT && in_array($userRole, ['Admin', 'HR', 'Staff'])) {
            return [
                'allowed' => true,
                'special_handling' => []
            ];
        }

        // SPECIAL RULE: Cancelled status logic
        if ($newStatus === OrderFulfillStatus::CANCELLED) {
            // Seller can ONLY cancel from new_order status (no refund needed as not paid yet)
            if ($userRole === 'Seller') {
                if ($currentStatus === OrderFulfillStatus::NEW_ORDER) {
                    return [
                        'allowed' => true,
                        'special_handling' => ['cancel_productions']
                    ];
                } else {
                    return [
                        'allowed' => false,
                        'message' => 'Seller can only cancel orders with new_order status'
                    ];
                }
            }

            // Admin and Staff can cancel from ANY status, NO refund
            if (in_array($userRole, ['Admin', 'HR', 'Staff'])) {
                return [
                    'allowed' => true,
                    'special_handling' => ['cancel_productions'] // No refund_if_paid
                ];
            }
        }

        // SPECIAL RULE: Cancelled (Refund Shipping) status logic
        if ($newStatus === OrderFulfillStatus::CANCELLED_REFUND_SHIPPING) {
            // Only Admin can perform this action
            if (!in_array($userRole, ['Admin', 'HR'], true)) {
                return [
                    'allowed' => false,
                    'message' => 'Only Admin can change status to Cancelled (Refund Shipping)'
                ];
            }

            // Only allowed if PAID
            if ($order->payment_status !== OrderPaymentStatus::PAID) {
                return [
                    'allowed' => false,
                    'message' => 'Cannot refund shipping: Order is not paid'
                ];
            }

            return [
                'allowed' => true,
                'special_handling' => ['cancel_productions', 'refund_shipping']
            ];
        }

        // SPECIAL RULE: Closed status logic (Full Refund)
        if ($newStatus === OrderFulfillStatus::CLOSED) {
            // Only Admin can perform this action
            if (!in_array($userRole, ['Admin', 'HR'], true)) {
                return [
                    'allowed' => false,
                    'message' => 'Only Admin can change status to Closed'
                ];
            }

            // Only allowed if PAID
            if ($order->payment_status !== OrderPaymentStatus::PAID) {
                return [
                    'allowed' => false,
                    'message' => 'Cannot close and full refund: Order is not paid'
                ];
            }

            return [
                'allowed' => true,
                'special_handling' => ['cancel_productions', 'refund_full']
            ];
        }

        // SPECIAL RULE: On Hold status logic
        if ($newStatus === OrderFulfillStatus::ON_HOLD) {
            // Admin and Staff can hold from ANY status
            if (in_array($userRole, ['Admin', 'HR', 'Staff'])) {
                return [
                    'allowed' => true,
                    'special_handling' => []
                ];
            }

            // Seller Restrictions
            if ($userRole === 'Seller') {
                // Constraint 1: Must NOT be Paid
                if ($order->payment_status === OrderPaymentStatus::PAID) {
                    return [
                        'allowed' => false,
                        'message' => 'Seller cannot hold orders that are already paid'
                    ];
                }

                // Constraint 2: Only from NEW_ORDER or CONFIRM
                if (!in_array($currentStatus, [OrderFulfillStatus::NEW_ORDER, OrderFulfillStatus::CONFIRM])) {
                    return [
                        'allowed' => false,
                        'message' => 'Seller can only hold orders from New Order or Confirm status'
                    ];
                }

                return [
                    'allowed' => true,
                    'special_handling' => []
                ];
            }
        }

        // Get validation result (needed for special_handling even for Admin)
        $validation = $this->getTransitionRules($currentStatus, $newStatus, $order, $userRole);

        // Admin/HR bypasses ALL validations - only keep special_handling
        if (in_array($userRole, ['Admin', 'HR'], true)) {
            return [
                'allowed' => true,
                'special_handling' => $validation['special_handling'] ?? []
            ];
        }

        if (!$validation['allowed']) {
            return [
                'allowed' => false,
                'message' => $validation['message']
            ];
        }

        // Check additional conditions (not for Admin)
        if (isset($validation['conditions'])) {
            $conditionCheck = $this->checkConditions($validation['conditions'], $order);
            if (!$conditionCheck['passed']) {
                return [
                    'allowed' => false,
                    'message' => $conditionCheck['message']
                ];
            }
        }

        return [
            'allowed' => true,
            'special_handling' => $validation['special_handling'] ?? []
        ];
    }

    /**
     * Get transition rules based on current and new status
     */
    protected function getTransitionRules(string $from, string $to, Order $order, ?string $userRole): array
    {
        // Check if transitioning TO qc_pass, packed, or shipped (from ANY status)
        // These transitions auto-complete all items for their respective stages
        if ($to === OrderFulfillStatus::QC_PASS) {
            return [
                'allowed' => true,
                'special_handling' => ['auto_complete_stage_qc']
            ];
        }
        if ($to === OrderFulfillStatus::PACKED) {
            return [
                'allowed' => true,
                'special_handling' => ['auto_complete_stage_packing']
            ];
        }
        if ($to === OrderFulfillStatus::SHIPPED) {
            return [
                'allowed' => true,
                'special_handling' => ['auto_complete_stage_shipout', 'map_stock', 'calculate_process_time', 'create_timeline_complete']
            ];
        }

        // FROM NEW_ORDER
        if ($from === OrderFulfillStatus::NEW_ORDER) {
            if ($to === OrderFulfillStatus::CANCELLED) {
                return [
                    'allowed' => true,
                    'special_handling' => ['refund_if_paid', 'cancel_productions']
                ];
            }
            if ($to === OrderFulfillStatus::PRODUCING) {
                return [
                    'allowed' => true,
                    'conditions' => ['payment_status_paid']
                ];
            }
            if ($to === OrderFulfillStatus::CONFIRM) {
                return [
                    'allowed' => true,
                    'conditions' => ['payment_status_paid']
                ];
            }
            if ($to === OrderFulfillStatus::ON_HOLD) {
                return ['allowed' => true];
            }
            if ($to === OrderFulfillStatus::TEST_ORDER) {
                return [
                    'allowed' => false,
                    'message' => 'Status new order no change test order'
                ];
            }
            return ['allowed' => false, 'message' => 'Invalid status transition'];
        }

        // FROM PRODUCING
        if ($from === OrderFulfillStatus::PRODUCING) {
            if ($to === OrderFulfillStatus::CANCELLED) {
                return [
                    'allowed' => $userRole !== 'Staff',
                    'message' => $userRole === 'Staff' ? 'Staff cannot cancel order from producing status' : null,
                    'special_handling' => ['cancel_productions']
                ];
            }
            if ($to === OrderFulfillStatus::ON_HOLD) {
                return ['allowed' => true];
            }
            if ($to === OrderFulfillStatus::RETURN_TO_SUPPORT) {
                return ['allowed' => true];
            }
            if ($to === OrderFulfillStatus::NEW_ORDER) {
                return [
                    'allowed' => false,
                    'message' => 'Status printed no change new order'
                ];
            }
            if ($to === OrderFulfillStatus::TEST_ORDER) {
                return [
                    'allowed' => false,
                    'message' => 'Status new order no change test order'
                ];
            }
            return ['allowed' => false, 'message' => 'Invalid status transition'];
        }

        // FROM CONFIRM
        if ($from === OrderFulfillStatus::CONFIRM) {
            if ($to === OrderFulfillStatus::PRODUCING) {
                return [
                    'allowed' => true,
                    'special_handling' => ['auto_complete_items']
                ];
            }
            if ($to === OrderFulfillStatus::CANCELLED) {
                return [
                    'allowed' => $userRole !== 'Staff',
                    'message' => $userRole === 'Staff' ? 'Staff cannot cancel order from confirm status' : null,
                    'special_handling' => ['cancel_productions']
                ];
            }
            if ($to === OrderFulfillStatus::ON_HOLD) {
                return ['allowed' => true];
            }
            if ($to === OrderFulfillStatus::TEST_ORDER) {
                return [
                    'allowed' => false,
                    'message' => 'Status new order no change test order'
                ];
            }
            return ['allowed' => false, 'message' => 'Invalid status transition'];
        }

        // FROM PENDING_STOCK
        if ($from === OrderFulfillStatus::PENDING_STOCK) {
            if ($to === OrderFulfillStatus::PRODUCING) {
                return ['allowed' => true];
            }
            if ($to === OrderFulfillStatus::CANCELLED) {
                return [
                    'allowed' => $userRole !== 'Staff',
                    'message' => $userRole === 'Staff' ? 'Staff cannot cancel order from pending stock status' : null,
                    'special_handling' => ['cancel_productions']
                ];
            }
            if ($to === OrderFulfillStatus::ON_HOLD) {
                return ['allowed' => true];
            }
            return ['allowed' => false, 'message' => 'Invalid status transition'];
        }

        // FROM ON_HOLD
        if ($from === OrderFulfillStatus::ON_HOLD) {
            if ($to === OrderFulfillStatus::PRODUCING) {
                // Only Admin/Staff can move from On Hold to Producing directly
                return [
                    'allowed' => in_array($userRole, ['Admin', 'HR', 'Staff']),
                    'message' => 'Only Admin/Staff/HR can change status to Producing from On Hold'
                ];
            }
            if ($to === OrderFulfillStatus::CONFIRM) {
                return [
                    'allowed' => in_array($userRole, ['Admin', 'HR', 'Staff', 'Seller']),
                    'message' => 'Cannot change status back to Confirm from On Hold'
                ];
            }
            if ($to === OrderFulfillStatus::CANCELLED) {
                return [
                    'allowed' => $userRole !== 'Staff',
                    'message' => $userRole === 'Staff' ? 'Staff cannot cancel order' : null,
                    'special_handling' => ['refund_if_paid', 'cancel_productions']
                ];
            }
            if ($to === OrderFulfillStatus::RETURN_TO_SUPPORT) {
                return ['allowed' => true];
            }
            return ['allowed' => false, 'message' => 'Invalid status transition'];
        }

        // FROM IN_STOCK, RETURN_TO_SUPPORT - same as PRODUCING
        if (in_array($from, [OrderFulfillStatus::IN_STOCK, OrderFulfillStatus::RETURN_TO_SUPPORT])) {
            return $this->getTransitionRules(OrderFulfillStatus::PRODUCING, $to, $order, $userRole);
        }

        return ['allowed' => false, 'message' => 'Invalid status transition'];
    }

    /**
     * Check conditions for status transition
     */
    protected function checkConditions(array $conditions, Order $order): array
    {
        foreach ($conditions as $condition) {
            if ($condition === 'payment_status_paid') {
                if ($order->payment_status !== OrderPaymentStatus::PAID) {
                    return [
                        'passed' => false,
                        'message' => 'Order must be paid before changing to this status'
                    ];
                }
            } elseif ($condition === 'production_complete') {
                if ($this->checkProduction($order->id)) {
                    return [
                        'passed' => false,
                        'message' => 'Cannot change status - some items not completed'
                    ];
                }
            }
        }

        return ['passed' => true];
    }

    /**
     * Check if all productions are completed (mapped)
     * Returns true if any production is NOT mapped
     * Returns false if all productions are mapped
     */
    protected function checkProduction(int $orderId): bool
    {
        $items = OrderItem::where('order_id', $orderId)->get();

        foreach ($items as $item) {
            $production = $item->productions()->first();

            if (!$production || $production->status !== 'mapped') {
                return true; // Has incomplete production
            }
        }

        return false; // All completed
    }

    /**
     * Auto-complete all items for staff stage when transitioning CONFIRM -> PRODUCING
     * Similar logic to OrderItemService::completeAllPositionsForStage but for all items
     * 
     * This will:
     * - Complete staff stage workflows for all positions of all items
     * - Map stock for each item's production
     * - Update item status to true
     */
    public function autoCompleteAllItems(Order $order): array
    {
        // Meta keys that need to be tracked for item completion (positions)
        $trackableMetaKeys = ['front', 'back', 'sleeve_left', 'sleeve_right', 'neck'];

        $userId = Auth::id();
        $username = Auth::user()->username ?? 'System';
        $itemsCompleted = 0;
        $itemsFailed = [];

        // Get all items for this order
        $items = $order->items()->with(['productions', 'metas'])->get();

        if ($items->isEmpty()) {
            return [
                'success' => false,
                'message' => 'No items found for this order',
                'code' => 400
            ];
        }

        foreach ($items as $orderItem) {
            // Get existing positions for this item
            $existingPositions = OrderItemMeta::where('order_item_id', $orderItem->id)
                ->whereIn('meta_key', $trackableMetaKeys)
                ->pluck('meta_key')
                ->toArray();

            if (empty($existingPositions)) {
                Log::warning('No trackable positions found for item', [
                    'item_id' => $orderItem->id,
                    'order_id' => $order->id
                ]);
                continue;
            }

            // Complete staff stage for all positions
            foreach ($existingPositions as $position) {
                $workflow = OrderItemWorkflow::firstOrCreate(
                    [
                        'order_item_id' => $orderItem->id,
                        'position' => $position,
                        'stage' => 'staff',
                    ],
                    [
                        'completed' => false,
                        'completed_by' => null,
                        'completed_at' => null,
                    ]
                );

                if (!$workflow->completed) {
                    $workflow->update([
                        'completed' => true,
                        'completed_by' => $userId,
                        'completed_at' => now(),
                    ]);
                }
            }

            // Map stock for this item's production
            $production = $orderItem->productions()->first();
            if ($production && $production->status === 'pending') {
                // Lock variant for update
                $variant = ProductVariant::where('variant_id', $production->product_variant_id)
                    ->lockForUpdate()
                    ->first();

                if ($variant) {
                    // Check stock
                    if ($variant->stock < $production->quantity) {
                        $itemsFailed[] = [
                            'item_id' => $orderItem->id,
                            'reason' => "Insufficient stock: need {$production->quantity}, have {$variant->stock}"
                        ];
                        Log::warning('Insufficient stock during auto-complete', [
                            'item_id' => $orderItem->id,
                            'variant_id' => $variant->variant_id,
                            'required' => $production->quantity,
                            'available' => $variant->stock
                        ]);
                        continue;
                    }

                    $beforeStock = $variant->stock;

                    // Deduct stock
                    $variant->stock -= $production->quantity;
                    $variant->save();

                    // Create audit log
                    StockAuditLog::create([
                        'product_variant_id' => $variant->variant_id,
                        'user_id' => $userId,
                        'action' => 'decrease',
                        'before_quantity' => $beforeStock,
                        'after_quantity' => $variant->stock,
                        'reason' => "Auto-mapped for production #{$production->id} (confirm->producing)",
                    ]);

                    // Update production status
                    $production->status = 'mapped';
                    $production->save();
                }
            }

            // Update item status to true
            if (!$orderItem->status) {
                $orderItem->status = true;
                $orderItem->save();
            }

            $itemsCompleted++;

            Log::info('Auto-completed item during confirm->producing', [
                'item_id' => $orderItem->id,
                'order_id' => $order->id,
                'positions' => $existingPositions
            ]);
        }

        // Create timeline entry
        $this->orderService->createTimeline(
            $order,
            'auto_complete_items',
            "{$username} auto-completed {$itemsCompleted} items (confirm->producing)"
        );

        // If some items failed, log but continue (partial success)
        if (!empty($itemsFailed)) {
            Log::warning('Some items failed during auto-complete', [
                'order_id' => $order->id,
                'failed_items' => $itemsFailed
            ]);
        }

        return [
            'success' => true,
            'message' => "Auto-completed {$itemsCompleted} items",
            'data' => [
                'items_completed' => $itemsCompleted,
                'items_failed' => $itemsFailed
            ]
        ];
    }

    /**
     * Auto-complete all items for a specific workflow stage
     * Used when Admin manually changes order to qc_pass, packed, or shipped
     * 
     * @param Order $order
     * @param string $stage (qc, packing, shipout)
     * @return array
     */
    public function autoCompleteAllItemsForStage(Order $order, string $stage): array
    {
        // Meta keys that need to be tracked for item completion (positions)
        $trackableMetaKeys = ['front', 'back', 'sleeve_left', 'sleeve_right', 'neck'];

        $userId = Auth::id();
        $username = Auth::user()->username ?? 'System';
        $itemsCompleted = 0;
        $itemsFailed = [];

        // Get all items for this order
        $items = $order->items()->with(['productions', 'metas'])->get();

        if ($items->isEmpty()) {
            return [
                'success' => false,
                'message' => 'No items found for this order',
                'code' => 400
            ];
        }

        // Stage hierarchy
        $stageOrder = ['staff', 'qc', 'packing', 'shipout'];
        $currentStageIndex = array_search($stage, $stageOrder);

        if ($currentStageIndex === false) {
            return [
                'success' => false,
                'message' => "Invalid stage: {$stage}",
                'code' => 400
            ];
        }

        foreach ($items as $orderItem) {
            // Get existing positions for this item
            $existingPositions = OrderItemMeta::where('order_item_id', $orderItem->id)
                ->whereIn('meta_key', $trackableMetaKeys)
                ->pluck('meta_key')
                ->toArray();

            if (empty($existingPositions)) {
                Log::warning('No trackable positions found for item', [
                    'item_id' => $orderItem->id,
                    'order_id' => $order->id
                ]);
                continue;
            }

            // Complete ALL previous stages + current stage for ALL positions
            foreach ($existingPositions as $position) {
                for ($i = 0; $i <= $currentStageIndex; $i++) {
                    $stg = $stageOrder[$i];

                    $workflow = OrderItemWorkflow::firstOrCreate(
                        [
                            'order_item_id' => $orderItem->id,
                            'position' => $position,
                            'stage' => $stg,
                        ],
                        [
                            'completed' => false,
                            'completed_by' => null,
                            'completed_at' => null,
                        ]
                    );

                    if (!$workflow->completed) {
                        $workflow->update([
                            'completed' => true,
                            'completed_by' => $userId,
                            'completed_at' => now(),
                        ]);
                    }
                }
            }

            // Map stock for this item's production if staff stage is being completed
            if (!$orderItem->status) {
                $production = $orderItem->productions()->first();
                if ($production && $production->status === 'pending') {
                    // Lock variant for update
                    $variant = ProductVariant::where('variant_id', $production->product_variant_id)
                        ->lockForUpdate()
                        ->first();

                    if ($variant) {
                        // Check stock
                        if ($variant->stock < $production->quantity) {
                            $itemsFailed[] = [
                                'item_id' => $orderItem->id,
                                'reason' => "Insufficient stock: need {$production->quantity}, have {$variant->stock}"
                            ];
                            Log::warning('Insufficient stock during auto-complete stage', [
                                'item_id' => $orderItem->id,
                                'variant_id' => $variant->variant_id,
                                'required' => $production->quantity,
                                'available' => $variant->stock
                            ]);
                            continue;
                        }

                        $beforeStock = $variant->stock;

                        // Deduct stock
                        $variant->stock -= $production->quantity;
                        $variant->save();

                        // Create audit log
                        StockAuditLog::create([
                            'product_variant_id' => $variant->variant_id,
                            'user_id' => $userId,
                            'action' => 'decrease',
                            'before_quantity' => $beforeStock,
                            'after_quantity' => $variant->stock,
                            'reason' => "Auto-mapped for production #{$production->id} (status change to {$stage})",
                        ]);

                        // Update production status
                        $production->status = 'mapped';
                        $production->save();
                    }
                }

                // Update item status to true
                $orderItem->status = true;
                $orderItem->save();
            }

            $itemsCompleted++;

            Log::info('Auto-completed item for stage', [
                'item_id' => $orderItem->id,
                'order_id' => $order->id,
                'stage' => $stage,
                'positions' => $existingPositions
            ]);
        }

        // Create timeline entry
        $stageLabels = [
            'qc' => 'QC',
            'packing' => 'Packing',
            'shipout' => 'Shipout'
        ];
        $stageLabel = $stageLabels[$stage] ?? $stage;

        $this->orderService->createTimeline(
            $order,
            "auto_complete_{$stage}",
            "{$username} auto-completed {$stageLabel} stage for {$itemsCompleted} items"
        );

        // If some items failed, log but continue (partial success)
        if (!empty($itemsFailed)) {
            Log::warning('Some items failed during auto-complete stage', [
                'order_id' => $order->id,
                'stage' => $stage,
                'failed_items' => $itemsFailed
            ]);
        }

        return [
            'success' => true,
            'message' => "Auto-completed {$stageLabel} stage for {$itemsCompleted} items",
            'data' => [
                'stage' => $stage,
                'items_completed' => $itemsCompleted,
                'items_failed' => $itemsFailed
            ]
        ];
    }

    /**
     * Map stock for order (deduct real stock)
     * 
     * ⚠️ IMPORTANT: This function is ONLY called when PRODUCING -> SHIPPED
     * It's COMMENTED in all other transitions (NEW_ORDER -> PRODUCING, etc.)
     * 
     * Returns true if successful, false if insufficient stock
     */
    public function mapStock(int $orderId): bool
    {
        $items = OrderItem::where('order_id', $orderId)->get();

        foreach ($items as $item) {
            $production = $item->productions()->first();

            if (!$production) {
                continue;
            }

            // If already mapped, skip (success)
            if ($production->status === 'mapped') {
                continue;
            }

            // Validate production status (must be pending, pickup, or canceled)
            // Allow 'canceled' to enable Admin to restore/ship accidentally cancelled orders
            if (!in_array($production->status, ['pending', 'pickup', 'canceled'])) {
                Log::warning('Production status not valid for mapping', [
                    'production_id' => $production->id,
                    'status' => $production->status
                ]);
                return false;
            }

            // Lock variant for update
            $variant = ProductVariant::where('variant_id', $production->product_variant_id)
                ->lockForUpdate()
                ->first();

            if (!$variant) {
                Log::error('Product variant not found', [
                    'variant_id' => $production->product_variant_id
                ]);
                return false;
            }

            // Check stock
            if ($variant->stock < $production->quantity) {
                Log::warning('Insufficient stock', [
                    'variant_id' => $variant->variant_id,
                    'required' => $production->quantity,
                    'available' => $variant->stock
                ]);
                return false;
            }

            $beforeStock = $variant->stock;

            // Deduct stock
            $variant->stock -= $production->quantity;
            $variant->save();

            // Create audit log
            StockAuditLog::create([
                'product_variant_id' => $variant->variant_id,
                'user_id' => Auth::id(),
                'action' => 'decrease',
                'before_quantity' => $beforeStock,
                'after_quantity' => $variant->stock,
                'reason' => "Mapped for production #{$production->id}",
            ]);

            // Update production status
            $production->status = 'mapped';
            $production->save();

            Log::info('Stock mapped successfully', [
                'production_id' => $production->id,
                'variant_id' => $variant->variant_id,
                'quantity' => $production->quantity,
                'remaining_stock' => $variant->stock
            ]);
        }

        return true;
    }

    /**
     * Unmap stock for order (restore stock back to inventory)
     * Called when Admin reverts from SHIPPED to another status
     * 
     * Returns true if successful
     */
    public function unmapStock(int $orderId): bool
    {
        $items = OrderItem::where('order_id', $orderId)->get();

        foreach ($items as $item) {
            $production = $item->productions()->first();

            if (!$production) {
                continue;
            }

            // Only unmap if currently mapped
            if ($production->status !== 'mapped') {
                continue;
            }

            // Lock variant for update
            $variant = ProductVariant::where('variant_id', $production->product_variant_id)
                ->lockForUpdate()
                ->first();

            if (!$variant) {
                Log::error('Product variant not found for unmapping', [
                    'variant_id' => $production->product_variant_id
                ]);
                continue;
            }

            $beforeStock = $variant->stock;

            // Restore stock
            $variant->stock += $production->quantity;
            $variant->save();

            // Create audit log
            StockAuditLog::create([
                'product_variant_id' => $variant->variant_id,
                'user_id' => Auth::id(),
                'action' => 'increase',
                'before_quantity' => $beforeStock,
                'after_quantity' => $variant->stock,
                'reason' => "Unmapped from production #{$production->id} (status reverted from shipped)",
            ]);

            // Reset production status to pending
            $production->status = 'pending';
            $production->save();

            Log::info('Stock unmapped successfully', [
                'production_id' => $production->id,
                'variant_id' => $variant->variant_id,
                'quantity' => $production->quantity,
                'new_stock' => $variant->stock
            ]);
        }

        return true;
    }

    /**
     * Refund order if paid
     */
    /**
     * Refund order full amount
     */
    public function refundFull(Order $order): void
    {
        // Reuse existing logic
        $this->refundOrder($order);
    }

    /**
     * Refund shipping cost only
     * Used for CANCELLED_REFUND_SHIPPING
     */
    public function refundShipping(Order $order): void
    {
        if ($order->payment_status !== OrderPaymentStatus::PAID) {
            return;
        }

        // Check if shipping cost > 0
        // Use pricing relationship or direct column depending on structure
        // Order model has shipping_cost column directly populated usually
        $shippingCost = $order->shipping_cost ?? 0;

        if ($shippingCost <= 0) {
            return;
        }

        $seller = $order->seller;
        $profile = $seller->profile;

        if (!$profile) {
            return;
        }

        // Refund shipping amount
        $profile->wallet_balance += $shippingCost;
        $profile->save();

        // Create transaction
        Transaction::create([
            'order_id' => $order->id,
            'seller_id' => $seller->id,
            'amount' => $shippingCost,
            'remaining_balance' => $profile->wallet_balance,
            'type' => 'refund',
            'status' => 'approved',
            'note' => "Refund shipping for order ID {$order->id}"
        ]);

        Log::info('Order shipping refunded', [
            'order_id' => $order->id,
            'amount' => $shippingCost,
            'seller_id' => $seller->id
        ]);
    }

    /**
     * Refund order if paid (Legacy / Full Refund)
     */
    public function refundOrder(Order $order): void
    {
        if ($order->payment_status !== OrderPaymentStatus::PAID) {
            return;
        }

        $seller = $order->seller;
        $profile = $seller->profile;

        if (!$profile) {
            return;
        }

        // Refund paid amount
        $profile->wallet_balance += $order->paid_cost;
        $profile->save();

        // Create transaction
        Transaction::create([
            'order_id' => $order->id,
            'seller_id' => $seller->id,
            'amount' => $order->paid_cost,
            'remaining_balance' => $profile->wallet_balance,
            'type' => 'refund',
            'status' => 'approved',
            'note' => "Full refund for order ID {$order->id}"
        ]);

        Log::info('Order refunded (full)', [
            'order_id' => $order->id,
            'amount' => $order->paid_cost,
            'seller_id' => $seller->id
        ]);
    }

    /**
     * Re-charge order when un-cancelling
     * Called when Admin restores a cancelled order that was previously refunded
     */
    public function rechargeOrder(Order $order): void
    {
        // Only recharge if order was paid
        if ($order->payment_status !== OrderPaymentStatus::PAID) {
            return;
        }

        // Only recharge if there's paid_cost to charge
        if ($order->paid_cost <= 0) {
            return;
        }

        $seller = $order->seller;
        $profile = $seller->profile;

        if (!$profile) {
            return;
        }

        // Deduct from wallet (charge back)
        $profile->wallet_balance -= $order->paid_cost;
        $profile->save();

        // Create transaction
        Transaction::create([
            'order_id' => $order->id,
            'seller_id' => $seller->id,
            'amount' => -$order->paid_cost,
            'remaining_balance' => $profile->wallet_balance,
            'type' => 'payment',
            'status' => 'approved',
            'note' => "Re-charge for restored order ID {$order->id}"
        ]);

        Log::info('Order re-charged (un-cancelled)', [
            'order_id' => $order->id,
            'amount' => $order->paid_cost,
            'seller_id' => $seller->id
        ]);
    }

    /**
     * Cancel all productions for order
     */
    public function cancelProductions(Order $order): void
    {
        $items = $order->items;

        foreach ($items as $item) {
            $productions = $item->productions;

            foreach ($productions as $production) {
                $production->status = 'canceled';
                $production->save();

                Log::info('Production canceled', [
                    'production_id' => $production->id,
                    'order_id' => $order->id
                ]);
            }
        }
    }

    /**
     * Calculate and set process time (in hours)
     * Only called when changing to SHIPPED status
     */
    public function calculateProcessTime(Order $order): void
    {
        $order->process_time = $order->created_at->diffInHours(Carbon::now());
        $order->save();
    }

    /**
     * Create timeline entry
     */
    public function createTimeline(Order $order, string $action, string $note): void
    {
        Timeline::create([
            'object' => 'order',
            'object_id' => $order->id,
            'owner_id' => Auth::id(),
            'action' => $action,
            'note' => $note
        ]);
    }

    /**
     * Change fulfill status of an order with full business logic
     * Based on CHANGE_FULFILL_STATUS_SPEC.md
     */
    public function changeFulfillStatus(int $orderId, string $newStatus): array
    {
        try {
            DB::beginTransaction();

            // Step 1: Load order with relations
            $order = Order::with(['seller.profile', 'items.productions'])
                ->find($orderId);

            if (!$order) {
                DB::rollBack();
                return [
                    'success' => false,
                    'message' => 'Order not found',
                    'code' => 404
                ];
            }

            $user = Auth::user();
            $currentStatus = $order->fulfill_status;

            // Step 2: Validate status transition
            $validation = $this->validateStatusTransition($order, $newStatus, $user);

            if (!$validation['allowed']) {
                DB::rollBack();
                return [
                    'success' => false,
                    'message' => $validation['message'],
                    'code' => 400
                ];
            }

            // Step 3: Handle special processing BEFORE status change
            $specialHandling = $validation['special_handling'] ?? [];

            // MANDATORY: Must have shipping_label and tracking_id to change to SHIPPED
            // This applies to ALL roles including Admin
            if ($newStatus === OrderFulfillStatus::SHIPPED) {
                if (empty($order->shipping_label) || empty($order->tracking_id)) {
                    DB::rollBack();
                    return [
                        'success' => false,
                        'message' => 'Cannot change to shipped - order must have shipping_label and tracking_id',
                        'code' => 400
                    ];
                }
            }

            // Auto-complete all items when transitioning from CONFIRM to PRODUCING
            if (in_array('auto_complete_items', $specialHandling)) {
                $autoCompleteResult = $this->autoCompleteAllItems($order);
                if (!$autoCompleteResult['success']) {
                    DB::rollBack();
                    return $autoCompleteResult;
                }
            }

            // Auto-complete QC stage for all items when transitioning TO qc_pass
            if (in_array('auto_complete_stage_qc', $specialHandling)) {
                $autoCompleteResult = $this->autoCompleteAllItemsForStage($order, 'qc');
                if (!$autoCompleteResult['success']) {
                    DB::rollBack();
                    return $autoCompleteResult;
                }
            }

            // Auto-complete Packing stage for all items when transitioning TO packed
            if (in_array('auto_complete_stage_packing', $specialHandling)) {
                $autoCompleteResult = $this->autoCompleteAllItemsForStage($order, 'packing');
                if (!$autoCompleteResult['success']) {
                    DB::rollBack();
                    return $autoCompleteResult;
                }
            }

            // Auto-complete Shipout stage for all items when transitioning TO shipped
            if (in_array('auto_complete_stage_shipout', $specialHandling)) {
                $autoCompleteResult = $this->autoCompleteAllItemsForStage($order, 'shipout');
                if (!$autoCompleteResult['success']) {
                    DB::rollBack();
                    return $autoCompleteResult;
                }
            }

            // Map stock when transitioning to SHIPPED
            // - Always map stock except when coming from new_order (new_order should go through confirm/in_stock first)
            // - If already in special_handling, it will be handled; otherwise, add it for non-new_order transitions
            $shouldMapStock = $newStatus === OrderFulfillStatus::SHIPPED
                && $currentStatus !== OrderFulfillStatus::NEW_ORDER;

            if (in_array('map_stock', $specialHandling) || $shouldMapStock) {
                $mapResult = $this->mapStock($order->id);
                if (!$mapResult) {
                    DB::rollBack();
                    return [
                        'success' => false,
                        'message' => 'Cannot ship - insufficient stock or production not ready',
                        'code' => 400
                    ];
                }
            }

            // Unmap stock when transitioning FROM SHIPPED to any other status
            // This allows Admin to revert shipped orders and restore stock
            if ($currentStatus === OrderFulfillStatus::SHIPPED && $newStatus !== OrderFulfillStatus::SHIPPED) {
                $this->unmapStock($order->id);
            }

            // Re-charge when transitioning FROM CLOSED to any other status
            // CLOSED status triggers refund_full, so we need to recharge when un-closing
            // Note: CANCELLED status does NOT trigger refund, so no recharge needed for un-cancelling
            if ($currentStatus === OrderFulfillStatus::CLOSED && $newStatus !== OrderFulfillStatus::CLOSED) {
                $this->rechargeOrder($order);
            }

            // Step 4: Update order status
            $order->fulfill_status = $newStatus;

            // Calculate process time if shipping
            if (in_array('calculate_process_time', $specialHandling)) {
                $this->calculateProcessTime($order);
            }

            $order->save();

            Log::info('Order fulfill status changed', [
                'order_id' => $orderId,
                'from_status' => $currentStatus,
                'to_status' => $newStatus,
                'user_id' => $user->id
            ]);

            // Step 5: Handle special processing AFTER status change

            // Refund if needed (Existing Logic)
            if (in_array('refund_if_paid', $specialHandling)) {
                $this->refundOrder($order);
            }

            // Refund full if needed (New Logic for Closed)
            if (in_array('refund_full', $specialHandling)) {
                $this->refundFull($order);
            }

            // Refund shipping if needed (New Logic for Cancelled Refund Shipping)
            if (in_array('refund_shipping', $specialHandling)) {
                $this->refundShipping($order);
            }

            // Cancel productions if needed
            if (in_array('cancel_productions', $specialHandling)) {
                $this->cancelProductions($order);
            }

            // Step 6: Create timeline entries
            $username = $user->username ?? 'System';
            $statusName = str_replace('_', ' ', $newStatus);

            if ($newStatus === OrderFulfillStatus::SHIPPED && $currentStatus === OrderFulfillStatus::PRODUCING) {
                $this->createTimeline(
                    $order,
                    'complete order',
                    "{$username} complete order {$order->id}"
                );
            } elseif ($newStatus === OrderFulfillStatus::RETURN_TO_SUPPORT) {
                $count = Timeline::where('object', 'order')
                    ->where('object_id', $order->id)
                    ->where('action', 'return to support')
                    ->count() + 1;

                $this->createTimeline(
                    $order,
                    'return to support',
                    "{$username} change return_to_support order {$order->id} for the {$count} time"
                );
            } else {
                $this->createTimeline(
                    $order,
                    "{$statusName} order",
                    "{$username} change {$statusName} order {$order->id}"
                );
            }

            DB::commit();

            $order->refresh();

            return [
                'success' => true,
                'message' => 'Fulfill status changed successfully',
                'data' => [
                    'id' => $order->id,
                    'fulfill_status' => $order->fulfill_status,
                    'payment_status' => $order->payment_status,
                    'total_cost' => $order->total_cost,
                    'paid_cost' => $order->paid_cost,
                    'priority' => $order->priority ?? 0,
                    'process_time' => $order->process_time,
                    'updated_at' => $order->updated_at,
                ]
            ];
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Failed to change fulfill status', [
                'order_id' => $orderId,
                'new_status' => $newStatus,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'message' => 'Failed to change fulfill status: ' . $e->getMessage(),
                'code' => 500
            ];
        }
    }
}
