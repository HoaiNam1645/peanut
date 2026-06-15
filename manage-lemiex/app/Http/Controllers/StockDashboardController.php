<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\ProductVariant;
use App\Models\StockAuditLog;
use App\Models\ReportStock;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StockDashboardController extends Controller
{
    /**
     * Get summary KPI stats.
     */
    public function getSummary(Request $request): JsonResponse
    {
        // Optimization: Single query to calculate all KPIs using conditional aggregation
        // This avoids 4 separate queries and table scans
        $stats = ProductVariant::where('active', true)
            ->selectRaw('
                SUM(stock) as total_items,
                SUM(stock * supplier_price) as total_value,
                COUNT(CASE WHEN stock <= 10 AND stock > 0 THEN 1 END) as low_stock_count,
                COUNT(CASE WHEN stock <= 0 THEN 1 END) as out_of_stock_count
            ')
            ->first();

        return response()->json([
            'success' => true,
            'data' => [
                'total_items' => (int) ($stats->total_items ?? 0),
                'total_value' => round($stats->total_value ?? 0, 2),
                'low_stock_count' => (int) $stats->low_stock_count,
                'out_of_stock_count' => (int) $stats->out_of_stock_count,
            ]
        ]);
    }

    /**
     * Get chart analytics data.
     */
    public function getAnalytics(Request $request): JsonResponse
    {

        // 1. Stock History (ReportStock)
        $history = ReportStock::withSum('items as total_stock', 'stock')
            ->orderBy('report_date', 'asc') // Get oldest first
            // Logic fix: We want the LATEST 30 days. So desc then sort.
            ->orderBy('report_date', 'desc')
            ->take(30)
            ->get()
            ->sortBy('report_date')
            ->values()
            ->map(function ($report) {
                return [
                    'date' => $report->report_date->format('Y-m-d'),
                    'total_stock' => (int) ($report->total_stock ?? 0),
                ];
            });

        // Ensure we always have data for the chart (at least Today)
        $historyData = $history->toArray();
        $today = now()->format('Y-m-d');
        $currentStock = ProductVariant::where('active', true)->sum('stock');

        // Check if today is present
        $hasToday = collect($historyData)->contains('date', $today);

        if (!$hasToday) {
            $historyData[] = [
                'date' => $today,
                'total_stock' => (int) $currentStock
            ];
        }

        // If we only have 1 point (Today), add Yesterday as a starting point (flat or 0)
        // This ensures the chart renders a line/area.
        if (count($historyData) < 2) {
            array_unshift($historyData, [
                'date' => now()->subDay()->format('Y-m-d'),
                'total_stock' => count($historyData) > 0 ? $historyData[0]['total_stock'] : 0
            ]);
        }

        $history = array_values($historyData); // Re-index

        // 2. Category Distribution
        // Group by style using a join on products table
        $distribution = ProductVariant::where('active', true)
            ->join('products', 'product_variants.product_id', '=', 'products.id')
            ->select('products.style', DB::raw('SUM(product_variants.stock) as total_qty'))
            ->groupBy('products.style')
            ->orderByDesc('total_qty')
            ->get()
            ->map(function ($item) {
                return [
                    'name' => $item->style ?: 'Unknown',
                    'value' => (int) $item->total_qty
                ];
            });

        return response()->json([
            'success' => true,
            'data' => [
                'history' => $history,
                'distribution' => $distribution
            ]
        ]);
    }

    /**
     * Get recent collected activities.
     */
    public function getActivities(Request $request): JsonResponse
    {
        // Eager load productVariant and its product to avoid N+1
        $logs = StockAuditLog::with(['user', 'productVariant.product'])
            ->latest()
            ->take(10)
            ->get()
            ->map(function ($log) {
                $variantData = $log->productVariant;

                $variantName = $variantData && $variantData->product
                    ? $variantData->product->name . ' (' . ($variantData->color ?? '-') . '-' . ($variantData->size ?? '-') . ')'
                    : 'Deleted Variant';

                $change = $log->after_quantity - $log->before_quantity;

                return [
                    'id' => $log->id,
                    'user' => $log->user ? $log->user->name : 'System',
                    'action' => $log->action,
                    'variant' => $variantName,
                    'sku' => $variantData ? $variantData->sku : 'N/A',
                    'quantity_change' => $change,
                    'new_stock' => $log->after_quantity,
                    'created_at' => $log->created_at->format('Y-m-d H:i:s'),
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $logs
        ]);
    }
}
