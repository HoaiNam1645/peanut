<?php

namespace App\Http\Controllers;

use App\Constants\HttpCode;
use App\Constants\OrderStatus;
use App\Constants\OrderItemStatus;
use App\Models\ProductVariant;
use App\Models\Order;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ShortageReportController extends Controller
{
    /**
     * Get shortage report - ORDER-CENTRIC approach
     * 
     * Returns all pending_stock orders with their shortage variants.
     * This is more intuitive for operations team to track and resolve.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $perPage = $request->input('per_page', 50);
            $sortBy = $request->input('sort_by', 'seller_username'); // Default sort by seller
            $sortOrder = $request->input('sort_order', 'asc'); // Default asc for text
            $dateFrom = $request->input('date_from');
            $dateTo = $request->input('date_to');

            // 1. Get ALL active item demands (FIFO sorted) to simulate state
            // We need CONFIRM and IN_STOCK items too because they reserve stock
            $activeItemsQuery = DB::table('order_items')
                ->join('orders', 'order_items.order_id', '=', 'orders.id')
                ->whereIn('orders.fulfill_status', [
                    OrderStatus::NEW_ORDER,
                    OrderStatus::CONFIRM,
                    OrderStatus::IN_STOCK,
                    OrderStatus::PENDING_STOCK
                ])
                ->where('orders.payment_status', OrderStatus::PAYMENT_PAID)
                ->where('order_items.status', OrderItemStatus::UNPROCESSED)
                ->select([
                    'orders.id as order_id',
                    'orders.ref_id',
                    'orders.created_at',
                    'orders.fulfill_status',
                    'order_items.id as item_id',
                    'order_items.variant_id',
                    'order_items.quantity'
                ])
                ->orderBy('orders.created_at', 'asc') // FIFO
                ->orderBy('orders.id', 'asc');

            $allItems = $activeItemsQuery->get();

            // 2. Get stock snapshot for involved variants
            $variantIds = $allItems->pluck('variant_id')->unique();
            $stocks = ProductVariant::whereIn('variant_id', $variantIds)
                ->pluck('stock', 'variant_id')
                ->toArray();

            // 3. Simulate FIFO Allocation
            $orderShortages = []; // [order_id => [shortage_variants...]]
            $workingStock = $stocks;

            foreach ($allItems as $item) {
                $vid = $item->variant_id;
                $qty = (int) $item->quantity;
                $currentStock = $workingStock[$vid] ?? 0;

                if ($currentStock >= $qty) {
                    // Allocated
                    $workingStock[$vid] = $currentStock - $qty;
                } else {
                    // Shortage
                    // Only record shortage if this is a PENDING_STOCK order (or eligible)
                    // We technically only care about reporting shortage for pending orders

                    // Logic: Even if order is CONFIRM, if we run out of stock in simulation, 
                    // it means we have a problem. But for this report, we focus on PENDING_STOCK.

                    if ($item->fulfill_status === OrderStatus::PENDING_STOCK) {
                        if (!isset($orderShortages[$item->order_id])) {
                            $orderShortages[$item->order_id] = [];
                        }

                        // Check if variant already added to shortage list of this order
                        $exists = false;
                        foreach ($orderShortages[$item->order_id] as &$existing) {
                            if ($existing['variant_id'] === $vid) {
                                $existing['shortage'] += ($qty - $currentStock); // Add to shortage
                                $existing['quantity_needed'] += $qty;
                                $existing['pending_demand'] += $qty;
                                $exists = true;
                                break;
                            }
                        }
                        unset($existing);

                        if (!$exists) {
                            $orderShortages[$item->order_id][] = [
                                'variant_id' => $vid,
                                'stock' => $stocks[$vid] ?? 0, // Original stock
                                'shortage' => $qty - $currentStock, // Actual shortage for this item
                                'quantity_needed' => $qty,
                                'pending_demand' => $qty // Set demand for this order
                            ];
                        }
                    }

                    // Consume remaining stock (if any)
                    $workingStock[$vid] = max(0, $currentStock - $qty);
                }
            }

            // 4. Fetch full order details for PENDING_STOCK orders only
            // Build query for pending_stock orders matching filters
            $query = Order::with([
                'seller:id,username',
                'items' => function ($q) {
                    $q->where('status', OrderItemStatus::UNPROCESSED)
                        ->with(['metas', 'productVariant:id,variant_id,style,color,size,stock']);
                }
            ])
                ->where('fulfill_status', OrderStatus::PENDING_STOCK)
                ->where('payment_status', OrderStatus::PAYMENT_PAID);

            // Apply filters
            if ($dateFrom) {
                $query->where('created_at', '>=', $dateFrom);
            }
            if ($dateTo) {
                $query->where('created_at', '<=', $dateTo . ' 23:59:59');
            }
            if ($request->filled('order_id')) {
                $oid = $request->input('order_id');
                $query->where(function ($q) use ($oid) {
                    $q->where('id', 'like', "%{$oid}%")
                        ->orWhere('ref_id', 'like', "%{$oid}%");
                });
            }
            if ($request->filled('variant_id')) {
                $vid = $request->input('variant_id');
                $query->whereHas('items', function ($q) use ($vid) {
                    $q->where('variant_id', 'like', "%{$vid}%");
                });
            }

            $pendingOrders = $query->get();

            if ($pendingOrders->isEmpty()) {
                return $this->emptyResponse($perPage);
            }

            // 5. Transform Orders merging Simulation Results
            $transformedOrders = $pendingOrders->map(function ($order) use ($orderShortages) {
                return $this->transformOrderWithSimulation($order, $orderShortages[$order->id] ?? []);
            })->filter();

            // Apply filters based on pending reason
            if ($request->filled('pending_reason')) {
                $reason = $request->input('pending_reason');
                $transformedOrders = $transformedOrders->filter(function ($order) use ($reason) {
                    return $order['pending_reason'] === $reason;
                });
            }

            // Sort logic (same as before)
            if ($sortBy === 'days_pending') {
                $transformedOrders = $sortOrder === 'desc'
                    ? $transformedOrders->sortByDesc('days_pending')->values()
                    : $transformedOrders->sortBy('days_pending')->values();
            } elseif ($sortBy === 'created_at') {
                $transformedOrders = $sortOrder === 'desc'
                    ? $transformedOrders->sortByDesc('created_at')->values()
                    : $transformedOrders->sortBy('created_at')->values();
            } elseif ($sortBy === 'shortage') {
                $transformedOrders = $sortOrder === 'desc'
                    ? $transformedOrders->sortByDesc('total_shortage')->values()
                    : $transformedOrders->sortBy('total_shortage')->values();
            } elseif ($sortBy === 'seller_username') {
                $transformedOrders = $sortOrder === 'desc'
                    ? $transformedOrders->sortByDesc('seller_username')->values()
                    : $transformedOrders->sortBy('seller_username')->values();
            }

            // Summary calculation
            $totalPending = $transformedOrders->count();
            $ordersWithShortage = $transformedOrders->where('pending_reason', 'shortage')->count();
            $totalShortage = $transformedOrders->sum('total_shortage');
            $allShortageVariants = $transformedOrders->flatMap(function ($order) {
                return collect($order['shortage_variants'] ?? [])->pluck('variant_id');
            })->unique()->count();

            // Pagination
            $lastPage = max(1, ceil($totalPending / $perPage));
            $currentPage = min($request->input('page', 1), $lastPage);
            $offset = ($currentPage - 1) * $perPage;
            $paginatedOrders = $transformedOrders->slice($offset, $perPage)->values();

            return response()->json([
                'code' => HttpCode::SUCCESS,
                'status' => true,
                'message' => 'Shortage report retrieved successfully',
                'data' => [
                    'orders' => $paginatedOrders,
                    'summary' => [
                        'total_pending_orders' => $totalPending,
                        'orders_with_shortage' => $ordersWithShortage,
                        'total_variants_shortage' => $allShortageVariants,
                        'total_quantity_shortage' => $totalShortage,
                    ],
                    'pagination' => [
                        'current_page' => (int) $currentPage,
                        'last_page' => (int) $lastPage,
                        'per_page' => (int) $perPage,
                        'total' => $totalPending,
                    ]
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to get shortage report', ['error' => $e->getMessage()]);
            return response()->json([
                'code' => HttpCode::SERVER_ERROR,
                'status' => false,
                'message' => 'Failed to retrieve shortage report',
                'error' => $e->getMessage()
            ], HttpCode::SERVER_ERROR);
        }
    }

    /**
     * Get shortage report - VARIANT-CENTRIC approach
     * 
     * Returns all shortage variants with their affected orders.
     * This is useful for stock/purchasing team to see which products need restocking.
     */
    public function indexByVariant(Request $request): JsonResponse
    {
        try {
            $perPage = $request->input('per_page', 50);
            $sortBy = $request->input('sort_by', 'shortage'); // Default sort by shortage
            $sortOrder = $request->input('sort_order', 'desc');
            $dateFrom = $request->input('date_from');
            $dateTo = $request->input('date_to');

            // 1. Get ALL active item demands (FIFO sorted) to simulate state
            $activeItemsQuery = DB::table('order_items')
                ->join('orders', 'order_items.order_id', '=', 'orders.id')
                ->whereIn('orders.fulfill_status', [
                    OrderStatus::NEW_ORDER,
                    OrderStatus::CONFIRM,
                    OrderStatus::IN_STOCK,
                    OrderStatus::PENDING_STOCK
                ])
                ->where('orders.payment_status', OrderStatus::PAYMENT_PAID)
                ->where('order_items.status', OrderItemStatus::UNPROCESSED)
                ->select([
                    'orders.id as order_id',
                    'orders.ref_id',
                    'orders.created_at',
                    'orders.fulfill_status',
                    'orders.seller_id',
                    'order_items.id as item_id',
                    'order_items.variant_id',
                    'order_items.quantity'
                ])
                ->orderBy('orders.created_at', 'asc') // FIFO
                ->orderBy('orders.id', 'asc');

            // Apply date filters if provided
            if ($dateFrom) {
                $activeItemsQuery->where('orders.created_at', '>=', $dateFrom);
            }
            if ($dateTo) {
                $activeItemsQuery->where('orders.created_at', '<=', $dateTo . ' 23:59:59');
            }

            $allItems = $activeItemsQuery->get();

            // 2. Get stock snapshot for involved variants
            $variantIds = $allItems->pluck('variant_id')->unique();
            $variants = ProductVariant::whereIn('variant_id', $variantIds)
                ->get()
                ->keyBy('variant_id');

            $stocks = $variants->mapWithKeys(fn($v) => [$v->variant_id => (int) $v->stock])->toArray();

            // 3. Get seller info
            $sellerIds = $allItems->pluck('seller_id')->unique();
            $sellers = DB::table('users')
                ->whereIn('id', $sellerIds)
                ->pluck('username', 'id')
                ->toArray();

            // 4. Simulate FIFO Allocation and track shortage by variant
            $variantShortages = []; // [variant_id => ['orders' => [...], 'total_shortage' => X, ...]]
            $workingStock = $stocks;

            foreach ($allItems as $item) {
                $vid = $item->variant_id;
                $qty = (int) $item->quantity;
                $currentStock = $workingStock[$vid] ?? 0;

                if ($currentStock >= $qty) {
                    // Fully allocated
                    $workingStock[$vid] = $currentStock - $qty;
                } else {
                    // Shortage detected
                    if ($item->fulfill_status === OrderStatus::PENDING_STOCK) {
                        $shortageQty = $qty - max(0, $currentStock);

                        if (!isset($variantShortages[$vid])) {
                            $variant = $variants[$vid] ?? null;
                            $variantShortages[$vid] = [
                                'variant_id' => $vid,
                                'style' => $variant->style ?? 'N/A',
                                'color' => $variant->color ?? 'N/A',
                                'size' => $variant->size ?? 'N/A',
                                'stock' => $stocks[$vid] ?? 0,
                                'total_demand' => 0,
                                'total_shortage' => 0,
                                'orders_count' => 0,
                                'orders' => [],
                            ];
                        }

                        // Add order to variant's affected orders list
                        $orderExists = false;
                        foreach ($variantShortages[$vid]['orders'] as &$existingOrder) {
                            if ($existingOrder['order_id'] === $item->order_id) {
                                $existingOrder['quantity'] += $qty;
                                $existingOrder['shortage'] += $shortageQty;
                                $orderExists = true;
                                break;
                            }
                        }
                        unset($existingOrder);

                        if (!$orderExists) {
                            $variantShortages[$vid]['orders'][] = [
                                'order_id' => $item->order_id,
                                'ref_id' => $item->ref_id,
                                'seller' => $sellers[$item->seller_id] ?? 'N/A',
                                'quantity' => $qty,
                                'shortage' => $shortageQty,
                                'created_at' => $item->created_at,
                                'days_pending' => Carbon::parse($item->created_at)->diffInDays(Carbon::now()),
                            ];
                            $variantShortages[$vid]['orders_count']++;
                        }

                        $variantShortages[$vid]['total_demand'] += $qty;
                        $variantShortages[$vid]['total_shortage'] += $shortageQty;
                    }

                    // Consume remaining stock
                    $workingStock[$vid] = max(0, $currentStock - $qty);
                }
            }

            // 5. Convert to array and filter
            $variantsList = collect(array_values($variantShortages));

            // Apply variant_id filter
            if ($request->filled('variant_id')) {
                $vid = $request->input('variant_id');
                $variantsList = $variantsList->filter(function ($v) use ($vid) {
                    return stripos($v['variant_id'], $vid) !== false;
                });
            }

            // Apply style filter
            if ($request->filled('style')) {
                $style = $request->input('style');
                $variantsList = $variantsList->filter(function ($v) use ($style) {
                    return stripos($v['style'], $style) !== false;
                });
            }

            // Sort
            if ($sortBy === 'shortage') {
                $variantsList = $sortOrder === 'desc'
                    ? $variantsList->sortByDesc('total_shortage')->values()
                    : $variantsList->sortBy('total_shortage')->values();
            } elseif ($sortBy === 'orders_count') {
                $variantsList = $sortOrder === 'desc'
                    ? $variantsList->sortByDesc('orders_count')->values()
                    : $variantsList->sortBy('orders_count')->values();
            } elseif ($sortBy === 'variant_id') {
                $variantsList = $sortOrder === 'desc'
                    ? $variantsList->sortByDesc('variant_id')->values()
                    : $variantsList->sortBy('variant_id')->values();
            } elseif ($sortBy === 'demand') {
                $variantsList = $sortOrder === 'desc'
                    ? $variantsList->sortByDesc('total_demand')->values()
                    : $variantsList->sortBy('total_demand')->values();
            }

            // Summary
            $totalVariants = $variantsList->count();
            $totalShortage = $variantsList->sum('total_shortage');
            $totalOrdersAffected = $variantsList->sum('orders_count');

            // Pagination
            $lastPage = max(1, ceil($totalVariants / $perPage));
            $currentPage = min($request->input('page', 1), $lastPage);
            $offset = ($currentPage - 1) * $perPage;
            $paginatedVariants = $variantsList->slice($offset, $perPage)->values();

            return response()->json([
                'code' => HttpCode::SUCCESS,
                'status' => true,
                'message' => 'Shortage report by variant retrieved successfully',
                'data' => [
                    'variants' => $paginatedVariants,
                    'summary' => [
                        'total_variants' => $totalVariants,
                        'total_shortage' => $totalShortage,
                        'total_orders_affected' => $totalOrdersAffected,
                    ],
                    'pagination' => [
                        'current_page' => (int) $currentPage,
                        'last_page' => (int) $lastPage,
                        'per_page' => (int) $perPage,
                        'total' => $totalVariants,
                    ]
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to get shortage report by variant', ['error' => $e->getMessage()]);
            return response()->json([
                'code' => HttpCode::SERVER_ERROR,
                'status' => false,
                'message' => 'Failed to retrieve shortage report by variant',
                'error' => $e->getMessage()
            ], HttpCode::SERVER_ERROR);
        }
    }

    /**
     * Get pending demand map for all variants
     */
    protected function getPendingDemandMap($dateFrom = null, $dateTo = null): array
    {
        $query = DB::table('orders')
            ->join('order_items', 'orders.id', '=', 'order_items.order_id')
            ->where('orders.fulfill_status', OrderStatus::PENDING_STOCK)
            ->where('orders.payment_status', OrderStatus::PAYMENT_PAID)
            ->where('order_items.status', OrderItemStatus::UNPROCESSED);

        if ($dateFrom) {
            $query->where('orders.created_at', '>=', $dateFrom);
        }
        if ($dateTo) {
            $query->where('orders.created_at', '<=', $dateTo . ' 23:59:59');
        }

        return $query->groupBy('order_items.variant_id')
            ->select([
                'order_items.variant_id',
                DB::raw('SUM(order_items.quantity) as pending_demand')
            ])
            ->get()
            ->keyBy('variant_id')
            ->map(fn($item) => (int) $item->pending_demand)
            ->toArray();
    }

    /**
     * Transform order with shortage information
     */
    protected function transformOrderWithShortage($order, array $pendingDemandMap): ?array
    {
        $shortageVariants = [];
        $missingFiles = [];
        $totalShortage = 0;

        foreach ($order->items as $item) {
            $variant = $item->productVariant;

            // Check for missing files
            $itemMissingFiles = $this->checkMissingFilesForItem($item);
            if (!empty($itemMissingFiles)) {
                $missingFiles = array_merge($missingFiles, $itemMissingFiles);
            }

            // Check for shortage
            if ($variant) {
                $variantId = $variant->variant_id;
                $pendingDemand = $pendingDemandMap[$variantId] ?? 0;
                $stock = (int) $variant->stock;
                $shortage = max(0, $pendingDemand - $stock);

                if ($shortage > 0) {
                    $shortageVariants[] = [
                        'id' => $variant->id,
                        'variant_id' => $variantId,
                        'style' => $variant->style,
                        'color' => $variant->color,
                        'size' => $variant->size,
                        'stock' => $stock,
                        'pending_demand' => $pendingDemand,
                        'shortage' => $shortage,
                        'quantity_needed' => (int) $item->quantity,
                    ];
                    $totalShortage += $shortage;
                }
            }
        }

        // Determine pending reason
        // Note: All pending_stock orders are already >40 mins old
        $pendingReason = 'unknown';

        if (!empty($shortageVariants)) {
            // Has variants with shortage
            $pendingReason = 'shortage';
        } elseif (!empty($missingFiles)) {
            // Has missing files
            $pendingReason = 'missing_files';
        } elseif ($order->items->isEmpty()) {
            // Order has no items (edge case)
            $pendingReason = 'no_items';
        } else {
            // Order has stock and files, but still pending
            // This means stock was recently added and needs reallocation
            $pendingReason = 'awaiting_allocation';
        }

        $daysPending = Carbon::parse($order->created_at)->diffInDays(Carbon::now());

        return [
            'id' => $order->id,
            'ref_id' => $order->ref_id,
            'seller_username' => $order->seller->username ?? 'N/A',
            'created_at' => $order->created_at->toIso8601String(),
            'days_pending' => (int) $daysPending,
            'total_items' => $order->items->count(),
            'shortage_variants' => $shortageVariants,
            'total_shortage' => $totalShortage,
            'missing_files' => $missingFiles,
            'pending_reason' => $pendingReason,
        ];
    }

    /**
     * Check missing files for an order item
     */
    protected function checkMissingFilesForItem($item): array
    {
        $missingFiles = [];
        $metas = $item->metas;

        if (!$metas || $metas->isEmpty()) {
            return $missingFiles;
        }

        // Get base keys (front, back, sleeve_left, sleeve_right, neck)
        $baseKeys = $metas->whereIn('meta_key', ['front', 'back', 'sleeve_left', 'sleeve_right', 'neck'])
            ->pluck('meta_key')
            ->toArray();

        if (empty($baseKeys)) {
            return $missingFiles; // Print order, no embroidery files
        }

        foreach ($baseKeys as $baseKey) {
            $pesEntry = $metas->where('meta_key', $baseKey)->first();
            $hasPes = $pesEntry && !empty($pesEntry->meta_value);

            $dstEntry = $metas->where('meta_key', $baseKey . '_dst')->first();
            $hasDst = $dstEntry && !empty($dstEntry->meta_value);

            $jsonEntry = $metas->where('meta_key', $baseKey . '_json')->first();
            $hasJson = $jsonEntry && !empty($jsonEntry->meta_value);

            if ($hasPes && !$hasDst) {
                $missingFiles[] = "{$baseKey}_dst";
            }
            if ($hasPes && !$hasJson) {
                $missingFiles[] = "{$baseKey}_json";
            }
            if (!$hasPes && ($hasDst || $hasJson)) {
                $missingFiles[] = "{$baseKey}_pes";
            }
        }

        return $missingFiles;
    }

    /**
     * Get shortage variants for a specific order
     */
    public function getShortageVariants(Request $request, int $orderId): JsonResponse
    {
        try {
            $order = Order::with([
                'seller:id,username',
                'items' => function ($q) {
                    $q->where('status', OrderItemStatus::UNPROCESSED)
                        ->with(['metas', 'productVariant:id,variant_id,style,color,size,stock']);
                }
            ])
                ->where('fulfill_status', OrderStatus::PENDING_STOCK)
                ->where('payment_status', OrderStatus::PAYMENT_PAID)
                ->findOrFail($orderId);

            // Get pending demand map
            $pendingDemandMap = $this->getPendingDemandMap();

            // Transform order
            $orderData = $this->transformOrderWithShortage($order, $pendingDemandMap);

            return response()->json([
                'code' => HttpCode::SUCCESS,
                'status' => true,
                'message' => 'Shortage variants retrieved successfully',
                'data' => $orderData
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to get shortage variants', [
                'order_id' => $orderId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'code' => HttpCode::SERVER_ERROR,
                'status' => false,
                'message' => 'Failed to retrieve shortage variants',
                'error' => $e->getMessage()
            ], HttpCode::SERVER_ERROR);
        }
    }

    /**
     * Export shortage report to CSV (Order-centric)
     */
    public function export(Request $request): JsonResponse
    {
        try {
            $dateFrom = $request->input('date_from');
            $dateTo = $request->input('date_to');

            // Get all pending orders
            $query = Order::with([
                'seller:id,username',
                'items' => function ($q) {
                    $q->where('status', OrderItemStatus::UNPROCESSED)
                        ->with('productVariant:id,variant_id,style,color,size,stock');
                }
            ])
                ->where('fulfill_status', OrderStatus::PENDING_STOCK)
                ->where('payment_status', OrderStatus::PAYMENT_PAID);

            if ($dateFrom) {
                $query->where('created_at', '>=', $dateFrom);
            }
            if ($dateTo) {
                $query->where('created_at', '<=', $dateTo . ' 23:59:59');
            }

            $orders = $query->orderBy('created_at', 'asc')->get();

            // Get pending demand map
            $pendingDemandMap = $this->getPendingDemandMap($dateFrom, $dateTo);

            // Generate CSV
            $csv = "Order ID,Ref ID,Seller,Days Pending,Pending Reason,Variant ID,Style,Color,Size,Stock,Pending Demand,Shortage\n";

            foreach ($orders as $order) {
                $orderData = $this->transformOrderWithShortage($order, $pendingDemandMap);

                if ($orderData['pending_reason'] === 'shortage') {
                    foreach ($orderData['shortage_variants'] as $variant) {
                        $csv .= sprintf(
                            "%d,%s,%s,%d,%s,%s,%s,%s,%s,%d,%d,%d\n",
                            $orderData['id'],
                            $orderData['ref_id'],
                            str_replace(',', ';', $orderData['seller_username']),
                            $orderData['days_pending'],
                            $orderData['pending_reason'],
                            $variant['variant_id'],
                            str_replace(',', ';', $variant['style'] ?? ''),
                            str_replace(',', ';', $variant['color'] ?? ''),
                            $variant['size'] ?? '',
                            $variant['stock'],
                            $variant['pending_demand'],
                            $variant['shortage']
                        );
                    }
                } else {
                    // Order with missing files or other reason
                    $csv .= sprintf(
                        "%d,%s,%s,%d,%s,,,,,,\n",
                        $orderData['id'],
                        $orderData['ref_id'],
                        str_replace(',', ';', $orderData['seller_username']),
                        $orderData['days_pending'],
                        $orderData['pending_reason']
                    );
                }
            }

            $filename = 'shortage_report_' . date('Y-m-d_His') . '.csv';

            return response()->json([
                'code' => HttpCode::SUCCESS,
                'status' => true,
                'message' => 'Export generated successfully',
                'data' => [
                    'csv' => $csv,
                    'filename' => $filename,
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to export shortage report', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'code' => HttpCode::SERVER_ERROR,
                'status' => false,
                'message' => 'Failed to export shortage report',
                'error' => $e->getMessage()
            ], HttpCode::SERVER_ERROR);
        }
    }

    /**
     * Get filter options for shortage report
     */
    public function getFilterOptions(): JsonResponse
    {
        try {
            // Get unique sellers with pending orders
            $sellers = Order::where('fulfill_status', OrderStatus::PENDING_STOCK)
                ->where('payment_status', OrderStatus::PAYMENT_PAID)
                ->with('seller:id,username')
                ->get()
                ->pluck('seller.username')
                ->filter()
                ->unique()
                ->values();

            // Pending reasons
            $pendingReasons = [
                ['value' => 'shortage', 'label' => 'Stock Shortage'],
                ['value' => 'missing_files', 'label' => 'Missing Files'],
                ['value' => 'unknown', 'label' => 'Unknown'],
            ];

            return response()->json([
                'code' => HttpCode::SUCCESS,
                'status' => true,
                'message' => 'Filter options retrieved successfully',
                'data' => [
                    'sellers' => $sellers,
                    'pending_reasons' => $pendingReasons,
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'code' => HttpCode::SERVER_ERROR,
                'status' => false,
                'message' => 'Failed to get filter options',
                'error' => $e->getMessage()
            ], HttpCode::SERVER_ERROR);
        }
    }
    /**
     * Helper to return empty response
     */
    protected function emptyResponse($perPage): JsonResponse
    {
        return response()->json([
            'code' => HttpCode::SUCCESS,
            'status' => true,
            'message' => 'Shortage report retrieved successfully',
            'data' => [
                'orders' => [],
                'summary' => [
                    'total_pending_orders' => 0,
                    'orders_with_shortage' => 0,
                    'orders_missing_files' => 0,
                    'total_quantity_shortage' => 0,
                ],
                'pagination' => [
                    'current_page' => 1,
                    'last_page' => 1,
                    'per_page' => $perPage,
                    'total' => 0,
                ]
            ]
        ]);
    }

    /**
     * Transform order using Simulation Results
     */
    protected function transformOrderWithSimulation($order, array $simulatedShortages): ?array
    {
        $shortageVariants = $simulatedShortages;
        $missingFiles = [];
        $totalShortage = 0;

        foreach ($shortageVariants as $variant) {
            $totalShortage += $variant['shortage'];
        }

        // Still check for missing files
        foreach ($order->items as $item) {
            $itemMissingFiles = $this->checkMissingFilesForItem($item);
            if (!empty($itemMissingFiles)) {
                $missingFiles = array_merge($missingFiles, $itemMissingFiles);
            }
        }

        // Determine pending reason
        $pendingReason = 'unknown';

        if (!empty($shortageVariants)) {
            $pendingReason = 'shortage';
        } elseif (!empty($missingFiles)) {
            $pendingReason = 'missing_files';
        } elseif ($order->items->isEmpty()) {
            $pendingReason = 'no_items';
        } else {
            // New Logic: If simulate shortage is empty, AND missing files empty
            // it means we HAVE stock allocated in simulation.
            // So this is truly Awaiting Allocation.
            $pendingReason = 'awaiting_allocation';
        }

        $daysPending = Carbon::parse($order->created_at)->diffInDays(Carbon::now());

        // Enrich shortage variants with extra data (style/color/size) from order items
        if (!empty($shortageVariants)) {
            foreach ($shortageVariants as &$sVariant) {
                // Find matching item to get details
                $matchingItem = $order->items->firstWhere('variant_id', $sVariant['variant_id']);
                if ($matchingItem && $matchingItem->productVariant) {
                    $sVariant['style'] = $matchingItem->productVariant->style;
                    $sVariant['color'] = $matchingItem->productVariant->color;
                    $sVariant['size'] = $matchingItem->productVariant->size;

                    // Add real pending demand from simulation if needed, or keep 0
                    // Ideally we should calculate global demand too, but shortage is enough
                }
            }
        }

        return [
            'id' => $order->id,
            'ref_id' => $order->ref_id,
            'seller_username' => $order->seller->username ?? 'N/A',
            'created_at' => $order->created_at->toIso8601String(),
            'days_pending' => (int) $daysPending,
            'total_items' => $order->items->count(),
            'shortage_variants' => $shortageVariants,
            'total_shortage' => $totalShortage,
            'missing_files' => $missingFiles,
            'pending_reason' => $pendingReason,
        ];
    }
}
