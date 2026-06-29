<?php

namespace App\Http\Controllers;

use App\Constants\HttpCode;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    /**
     * Get dashboard statistics
     */
    public function getStatistics(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            // Check if user is a seller (Assuming role_id 3 is Seller, or check role name if relationship loaded)
            // You might need to adjust logic based on your specific role implementation
            // Here assuming a simple check. Ideally use $user->hasRole('Seller') if available.
            $isSeller = false;
            if ($user->role_id === 2) {
                $isSeller = true;
            } else {
                // If role relation is loaded or loadable
                $user->load('role');
                if ($user->role && ($user->role->name === 'Seller' || $user->role->slug === 'seller')) {
                    $isSeller = true;
                }
            }

            $timeRange = (int) $request->input('time_range', '30');

            if ($timeRange === 1) {
                // Today: từ 00:00 hôm nay đến hiện tại
                $startDate = now()->startOfDay()->toDateTimeString();
                $endDate = now()->toDateTimeString();
                $previousStartDate = now()->subDay()->startOfDay()->toDateTimeString();
                $previousEndDate = now()->subDay()->toDateTimeString();
            } elseif ($timeRange === 2) {
                // Yesterday: từ 00:00 hôm qua đến 00:00 hôm nay
                $startDate = now()->subDay()->startOfDay()->toDateTimeString();
                $endDate = now()->startOfDay()->toDateTimeString();
                $previousStartDate = now()->subDays(2)->startOfDay()->toDateTimeString();
                $previousEndDate = now()->subDay()->startOfDay()->toDateTimeString();
            } else {
                // N ngày vừa qua tính từ hiện tại
                $startDate = now()->subDays($timeRange)->toDateTimeString();
                $endDate = now()->toDateTimeString();
                $previousStartDate = now()->subDays($timeRange * 2)->toDateTimeString();
                $previousEndDate = $startDate;
            }

            // Orders Statistics
            $orderQuery = Order::query();
            if ($isSeller) {
                $orderQuery->where('seller_id', $user->id);
            }

            $orderStats = $orderQuery->selectRaw('
                COUNT(*) as total_orders,
                SUM(CASE WHEN created_at >= ? AND created_at < ? THEN 1 ELSE 0 END) as orders_this_period,
                SUM(CASE WHEN created_at >= ? AND created_at < ? THEN 1 ELSE 0 END) as orders_previous_period
            ', [$startDate, $endDate, $previousStartDate, $previousEndDate])->first();

            $ordersThisPeriod = $orderStats->orders_this_period;
            $ordersPreviousPeriod = $orderStats->orders_previous_period;
            $totalOrders = $ordersThisPeriod; // User wants big number to reflect selected time range

            $ordersGrowth = $ordersPreviousPeriod > 0
                ? (($ordersThisPeriod - $ordersPreviousPeriod) / $ordersPreviousPeriod) * 100
                : 0;

            // Revenue Statistics
            $revenueQuery = DB::table('order_items')
                ->join('orders', 'order_items.order_id', '=', 'orders.id');

            if ($isSeller) {
                $revenueQuery->where('orders.seller_id', $user->id);
            }

            $revenueStats = $revenueQuery->selectRaw('
                    SUM(order_items.quantity * order_items.price) as total_revenue,
                    SUM(CASE WHEN orders.created_at >= ? AND orders.created_at < ? THEN order_items.quantity * order_items.price ELSE 0 END) as revenue_this_period,
                    SUM(CASE WHEN orders.created_at >= ? AND orders.created_at < ? THEN order_items.quantity * order_items.price ELSE 0 END) as revenue_previous_period
                ', [$startDate, $endDate, $previousStartDate, $previousEndDate])
                ->first();

            $revenueThisPeriod = $revenueStats->revenue_this_period ?? 0;
            $revenuePreviousPeriod = $revenueStats->revenue_previous_period ?? 0;
            $totalRevenue = $revenueThisPeriod; // User wants big number to reflect selected time range

            $revenueGrowth = $revenuePreviousPeriod > 0
                ? (($revenueThisPeriod - $revenuePreviousPeriod) / $revenuePreviousPeriod) * 100
                : 0;

            // Products count - Filtered by time range
            $totalProducts = Product::where('created_at', '>=', $startDate)
                ->where('created_at', '<', $endDate)
                ->count();

            // Variants Statistics - Filtered by time range
            $variantStats = ProductVariant::where('created_at', '>=', $startDate)
                ->where('created_at', '<', $endDate)
                ->selectRaw('
                COUNT(*) as total_variants,
                SUM(CASE WHEN active = 1 THEN 1 ELSE 0 END) as active_variants,
                SUM(stock) as total_stock,
                SUM(CASE WHEN stock > 0 AND stock <= 10 THEN 1 ELSE 0 END) as low_stock_variants
            ')->first();

            $totalVariants = $variantStats->total_variants ?? 0;
            $activeVariants = $variantStats->active_variants;
            $totalStock = $variantStats->total_stock ?? 0;
            $lowStockVariants = $variantStats->low_stock_variants;

            // Users Statistics - Hide for Seller
            if ($isSeller) {
                $totalUsers = 0;
                $newUsersThisPeriod = 0;
            } else {
                $userStats = User::selectRaw('
                    SUM(CASE WHEN created_at >= ? AND created_at < ? THEN 1 ELSE 0 END) as new_users_this_period
                ', [$startDate, $endDate])->first();
                $newUsersThisPeriod = $userStats->new_users_this_period ?? 0;
                $totalUsers = $newUsersThisPeriod; // User wants big number to reflect selected time range
            }

            // Payment Status Distribution
            $ordersByPaymentStatusQuery = Order::select('payment_status', DB::raw('count(*) as count'))
                ->where('created_at', '>=', $startDate)
                ->where('created_at', '<', $endDate);
            if ($isSeller) {
                $ordersByPaymentStatusQuery->where('seller_id', $user->id);
            }
            $ordersByPaymentStatus = $ordersByPaymentStatusQuery
                ->groupBy('payment_status')
                ->get()
                ->pluck('count', 'payment_status');

            // Fulfill Status Distribution
            $ordersByFulfillStatusQuery = Order::select('fulfill_status', DB::raw('count(*) as count'))
                ->whereNotNull('fulfill_status')
                ->where('created_at', '>=', $startDate)
                ->where('created_at', '<', $endDate);
            if ($isSeller) {
                $ordersByFulfillStatusQuery->where('seller_id', $user->id);
            }
            $ordersByFulfillStatus = $ordersByFulfillStatusQuery
                ->groupBy('fulfill_status')
                ->get()
                ->pluck('count', 'fulfill_status');

            // Recent Orders
            $recentOrdersQuery = Order::with(['store', 'items'])
                ->orderBy('created_at', 'desc')
                ->limit(10);
            if ($isSeller) {
                $recentOrdersQuery->where('seller_id', $user->id);
            }
            $recentOrders = $recentOrdersQuery->get()
                ->map(function ($order) {
                    return [
                        'id' => $order->id,
                        'ref_id' => $order->ref_id,
                        'store_name' => $order->store->name ?? 'N/A',
                        'payment_status' => $order->payment_status,
                        'fulfill_status' => $order->fulfill_status,
                        'total_items' => $order->items->count(),
                        'created_at' => $order->created_at,
                    ];
                });

            // Top Products (by order quantity) WITH a per-size breakdown.
            // Group by (product name, variant size) so each ranked product carries a
            // `sizes` list (e.g. S: 12, M: 40, L: 30); aggregate + rank in PHP.
            $sizeExpr = "COALESCE(NULLIF(product_variants.size, ''), '—')";
            $topProductRowsQuery = DB::table('order_items')
                ->join('orders', 'order_items.order_id', '=', 'orders.id')
                ->leftJoin('product_variants', 'order_items.variant_id', '=', 'product_variants.variant_id')
                ->leftJoin('products', 'product_variants.product_id', '=', 'products.id')
                ->select(
                    DB::raw('COALESCE(products.name, order_items.product_name) as product_name'),
                    DB::raw("{$sizeExpr} as size"),
                    DB::raw('SUM(order_items.quantity) as total_quantity')
                )
                ->where('orders.created_at', '>=', $startDate)
                ->where('orders.created_at', '<', $endDate);
            if ($isSeller) {
                $topProductRowsQuery->where('orders.seller_id', $user->id);
            }
            $topProductRows = $topProductRowsQuery
                ->groupBy(DB::raw('COALESCE(products.name, order_items.product_name)'), DB::raw($sizeExpr))
                ->get();

            // Canonical size order so the breakdown reads S, M, L, XL, … (unknown last).
            $sizeOrder = ['XXS' => 0, 'XS' => 1, 'S' => 2, 'M' => 3, 'L' => 4, 'XL' => 5, 'XXL' => 6, '2XL' => 6, '3XL' => 7, '4XL' => 8, '5XL' => 9, '6XL' => 10, '7XL' => 11];
            $grouped = [];
            foreach ($topProductRows as $row) {
                $name = $row->product_name;
                if (! isset($grouped[$name])) {
                    $grouped[$name] = ['product_name' => $name, 'total_quantity' => 0, 'sizes' => []];
                }
                $grouped[$name]['total_quantity'] += (int) $row->total_quantity;
                $grouped[$name]['sizes'][] = ['size' => $row->size, 'quantity' => (int) $row->total_quantity];
            }
            $topProducts = collect($grouped)
                ->map(function ($product) use ($sizeOrder) {
                    usort($product['sizes'], function ($a, $b) use ($sizeOrder) {
                        $oa = $sizeOrder[strtoupper($a['size'])] ?? 99;
                        $ob = $sizeOrder[strtoupper($b['size'])] ?? 99;

                        return $oa <=> $ob ?: strcmp((string) $a['size'], (string) $b['size']);
                    });

                    return $product;
                })
                ->sortByDesc('total_quantity')
                ->take(10)
                ->values()
                ->all();

            // Get top 5 products by total quantity (respecting time range) - Use actual product name

            $topProductNamesQuery = DB::table('order_items')
                ->join('orders', 'order_items.order_id', '=', 'orders.id')
                ->leftJoin('product_variants', 'order_items.variant_id', '=', 'product_variants.variant_id')
                ->leftJoin('products', 'product_variants.product_id', '=', 'products.id')
                ->where('orders.created_at', '>=', $startDate)
                ->where('orders.created_at', '<', $endDate)
                ->select(
                    DB::raw('COALESCE(products.name, order_items.product_name) as product_name'),
                    DB::raw('SUM(order_items.quantity) as total')
                );
            if ($isSeller) {
                $topProductNamesQuery->where('orders.seller_id', $user->id);
            }
            $topProductNames = $topProductNamesQuery
                ->groupBy(DB::raw('COALESCE(products.name, order_items.product_name)'))
                ->orderByDesc('total')
                ->limit(5)
                ->pluck('product_name');

            // Product Sales Chart (respecting time range) - Only for top 5 products, use actual product name
            $productSalesDataQuery = DB::table('order_items')
                ->join('orders', 'order_items.order_id', '=', 'orders.id')
                ->leftJoin('product_variants', 'order_items.variant_id', '=', 'product_variants.variant_id')
                ->leftJoin('products', 'product_variants.product_id', '=', 'products.id')
                ->where('orders.created_at', '>=', $startDate)
                ->where('orders.created_at', '<', $endDate)
                ->whereIn(DB::raw('COALESCE(products.name, order_items.product_name)'), $topProductNames);
            if ($isSeller) {
                $productSalesDataQuery->where('orders.seller_id', $user->id);
            }
            $productSalesData = $productSalesDataQuery
                ->select(
                    DB::raw('COALESCE(products.name, order_items.product_name) as product_name'),
                    DB::raw('DATE(orders.created_at) as date'),
                    DB::raw('SUM(order_items.quantity) as quantity')
                )
                ->groupBy(DB::raw('COALESCE(products.name, order_items.product_name)'), DB::raw('DATE(orders.created_at)'))
                ->orderBy('date', 'asc')
                ->get();

            // Format product sales data for chart
            $productSalesChart = [];
            foreach ($productSalesData as $item) {
                $date = $item->date;
                if (! isset($productSalesChart[$date])) {
                    $productSalesChart[$date] = ['date' => $date];
                }
                $productSalesChart[$date][$item->product_name] = (int) $item->quantity;
            }
            $productSalesChart = array_values($productSalesChart);

            // Daily Revenue Chart (respecting time range) - Total revenue per day by payment status
            $revenueDataQuery = DB::table('order_items')
                ->join('orders', 'order_items.order_id', '=', 'orders.id')
                ->where('orders.created_at', '>=', $startDate)
                ->where('orders.created_at', '<', $endDate);
            if ($isSeller) {
                $revenueDataQuery->where('orders.seller_id', $user->id);
            }
            $revenueData = $revenueDataQuery
                ->select(
                    DB::raw('DATE(orders.created_at) as date'),
                    'orders.payment_status',
                    DB::raw('SUM(order_items.quantity * order_items.price) as revenue')
                )
                ->groupBy(DB::raw('DATE(orders.created_at)'), 'orders.payment_status')
                ->orderBy('date', 'asc')
                ->get();

            // Format revenue data for chart
            $revenueChart = [];
            foreach ($revenueData as $item) {
                $date = $item->date;
                if (! isset($revenueChart[$date])) {
                    $revenueChart[$date] = ['date' => $date];
                }
                $revenueChart[$date][$item->payment_status] = round((float) $item->revenue, 2);
            }
            $revenueChart = array_values($revenueChart);

            // Daily Order Count Chart (respecting time range)
            $orderCountDataQuery = DB::table('orders')
                ->where('created_at', '>=', $startDate)
                ->where('created_at', '<', $endDate);
            if ($isSeller) {
                $orderCountDataQuery->where('seller_id', $user->id);
            }
            $orderCountData = $orderCountDataQuery
                ->select(
                    DB::raw('DATE(created_at) as date'),
                    DB::raw('COUNT(*) as count')
                )
                ->groupBy(DB::raw('DATE(created_at)'))
                ->orderBy('date', 'asc')
                ->get();

            // Format order count data for chart
            $orderCountChart = $orderCountData->map(function ($item) {
                return [
                    'date' => $item->date,
                    'orders' => (int) $item->count,
                ];
            })->toArray();

            // Transaction Chart (respecting time range) - by type
            $transactionDataQuery = DB::table('transactions')
                ->where('created_at', '>=', $startDate)
                ->where('created_at', '<', $endDate)
                ->whereNull('deleted_at');
            if ($isSeller) {
                $transactionDataQuery->where('seller_id', $user->id);
            }
            $transactionData = $transactionDataQuery
                ->select(
                    DB::raw('DATE(created_at) as date'),
                    'type',
                    DB::raw('SUM(ABS(amount)) as total_amount'),
                    DB::raw('COUNT(*) as count')
                )
                ->groupBy(DB::raw('DATE(created_at)'), 'type')
                ->orderBy('date', 'asc')
                ->get();

            // Format transaction data for chart
            $transactionChart = [];
            foreach ($transactionData as $item) {
                $date = $item->date;
                if (! isset($transactionChart[$date])) {
                    $transactionChart[$date] = ['date' => $date];
                }
                $type = $item->type ?? 'other';
                $transactionChart[$date][$type] = round((float) $item->total_amount, 2);
            }
            $transactionChart = array_values($transactionChart);

            // Transaction Summary
            $transactionSummaryQuery = Transaction::query();
            if ($isSeller) {
                $transactionSummaryQuery->where('seller_id', $user->id);
            }
            $filteredTxQuery = (clone $transactionSummaryQuery)
                ->where('created_at', '>=', $startDate)
                ->where('created_at', '<', $endDate);

            $transactionSummary = [
                'total_deposits' => round((clone $filteredTxQuery)->where('type', 'deposit')->sum('amount'), 2),
                'total_withdrawals' => round(abs((clone $filteredTxQuery)->where('type', 'withdrawal')->sum('amount')), 2),
                'total_payments' => round(abs((clone $filteredTxQuery)->where('type', 'payment')->sum('amount')), 2),
                'pending_transactions' => (clone $transactionSummaryQuery)->where('status', 'pending')->count(), // Pending is usually current state
                'transactions_this_period' => $filteredTxQuery->count(),
            ];

            // Wallet Balance for Seller
            $walletBalance = 0;
            if ($isSeller) {
                $user->load('profile');
                $walletBalance = $user->profile->wallet_balance ?? 0;
            }

            // Shop Stats (aggregated by store, within time range)
            $shopStatsQuery = DB::table('orders')
                ->leftJoin('stores', 'orders.store_id', '=', 'stores.id')
                ->select(
                    'stores.id as shop_id',
                    DB::raw("COALESCE(stores.name, 'Unknown') as shop_name"),
                    DB::raw('COUNT(orders.id) as total'),
                    DB::raw("SUM(CASE WHEN orders.payment_status = 'refunded' OR orders.fulfill_status = 'cancelled_refund_shipping' THEN 1 ELSE 0 END) as refund"),
                    DB::raw("SUM(CASE WHEN orders.payment_status = 'paid' THEN 1 ELSE 0 END) as paid"),
                    DB::raw("SUM(CASE WHEN orders.fulfill_status IN ('new_order', 'confirm', 'producing') THEN 1 ELSE 0 END) as processing"),
                    DB::raw("SUM(CASE WHEN orders.fulfill_status = 'on_hold' THEN 1 ELSE 0 END) as on_hold"),
                    DB::raw('COUNT(DISTINCT orders.seller_id) as seller_count'),
                    DB::raw('SUM(CASE WHEN orders.payment_status = \'paid\' THEN orders.total_cost ELSE 0 END) as paid_amount'),
                    DB::raw('SUM(CASE WHEN orders.fulfill_status IN (\'new_order\', \'confirm\', \'producing\') THEN orders.total_cost ELSE 0 END) as processing_amount'),
                    DB::raw('SUM(CASE WHEN orders.fulfill_status = \'on_hold\' THEN orders.total_cost ELSE 0 END) as on_hold_amount')
                )
                ->where('orders.created_at', '>=', $startDate)
                ->where('orders.created_at', '<', $endDate);
            if ($isSeller) {
                $shopStatsQuery->where('orders.seller_id', $user->id);
            }
            $shopStats = $shopStatsQuery
                ->groupBy('stores.id', 'stores.name')
                ->orderBy('total', 'desc')
                ->limit(20)
                ->get()
                ->map(function ($row) {
                    $total = (int) $row->total;
                    $refund = (int) $row->refund;

                    return [
                        'shop_id' => $row->shop_id,
                        'shop_name' => $row->shop_name,
                        'total' => $total,
                        'refund' => $refund,
                        'refund_pct' => $total > 0 ? round(($refund / $total) * 100, 1) : 0,
                        'paid' => (int) $row->paid,
                        'processing' => (int) $row->processing,
                        'on_hold' => (int) $row->on_hold,
                        'seller_count' => (int) $row->seller_count,
                        'paid_amount' => round((float) $row->paid_amount, 2),
                        'processing_amount' => round((float) $row->processing_amount, 2),
                        'on_hold_amount' => round((float) $row->on_hold_amount, 2),
                    ];
                });

            return response()->json([
                'code' => HttpCode::SUCCESS,
                'status' => true,
                'message' => 'Dashboard statistics retrieved successfully',
                'data' => [
                    'overview' => [
                        'total_orders' => $totalOrders,
                        'orders_this_period' => $ordersThisPeriod,
                        'orders_growth' => round($ordersGrowth, 2),
                        'total_revenue' => round($totalRevenue, 2),
                        'revenue_this_period' => round($revenueThisPeriod, 2),
                        'revenue_growth' => round($revenueGrowth, 2),
                        'total_products' => $totalProducts,
                        'total_variants' => $totalVariants,
                        'active_variants' => $activeVariants,
                        'total_stock' => $totalStock,
                        'low_stock_variants' => $isSeller ? 0 : $lowStockVariants, // Hide stock alerts for seller if not relevant
                        'total_users' => $totalUsers,
                        'new_users_this_period' => $newUsersThisPeriod,
                        'wallet_balance' => $walletBalance, // Bonus: send wallet balance
                    ],
                    'orders_by_payment_status' => $ordersByPaymentStatus,
                    'orders_by_fulfill_status' => $ordersByFulfillStatus,
                    'recent_orders' => $recentOrders,
                    'top_products' => $topProducts,
                    'product_sales_chart' => $productSalesChart,
                    'revenue_chart' => $revenueChart,
                    'order_count_chart' => $orderCountChart,
                    'transaction_chart' => $transactionChart,
                    'transaction_summary' => $transactionSummary,
                    'top_product_names' => $topProductNames,
                    'shop_stats' => $shopStats,
                    'time_range' => $timeRange,
                    'is_seller' => $isSeller, // Flag for frontend
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'code' => HttpCode::SERVER_ERROR,
                'status' => false,
                'message' => 'Failed to retrieve dashboard statistics',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], HttpCode::SERVER_ERROR);
        }
    }
}
