<?php

namespace App\Http\Controllers;

use App\Constants\HttpCode;
use App\Models\StockAuditLog;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StockAuditLogController extends Controller
{
    /**
     * Get stock audit logs with filters and pagination
     */
    public function getAuditLogs(Request $request): JsonResponse
    {
        try {
            $perPage = $request->input('per_page', 20);
            $page = $request->input('page', 1);

            // Build query with eager loading
            $query = StockAuditLog::with([
                'user:id,username,email',
                'productVariant.product:id,name,brand,style'
            ]);

            // Filter by variant_id
            if ($request->filled('variant_id')) {
                $query->where('product_variant_id', $request->variant_id);
            }

            // Filter by action
            if ($request->filled('action')) {
                $query->where('action', $request->action);
            }

            // Filter by user_id
            if ($request->filled('user_id')) {
                $query->where('user_id', $request->user_id);
            }

            // Filter by style (join with products)
            if ($request->filled('style')) {
                $query->whereHas('productVariant.product', function ($q) use ($request) {
                    $q->where('style', $request->style);
                });
            }

            // Filter by color
            if ($request->filled('color')) {
                $query->whereHas('productVariant', function ($q) use ($request) {
                    $q->where('color', $request->color);
                });
            }

            // Filter by size
            if ($request->filled('size')) {
                $query->whereHas('productVariant', function ($q) use ($request) {
                    $q->where('size', $request->size);
                });
            }

            // Filter by order_id (join through productions)
            if ($request->filled('order_id')) {
                $query->where(function ($q) use ($request) {
                    // Check in metadata first
                    $q->where('metadata->order_id', $request->order_id)
                      // Or join through productions
                      ->orWhereHas('productVariant.productions.orderItem.order', function ($subQ) use ($request) {
                          $subQ->where('id', $request->order_id);
                      });
                });
            }

            // Filter by date range
            if ($request->filled('date_from')) {
                $query->where('created_at', '>=', $request->date_from);
            }

            if ($request->filled('date_to')) {
                $query->where('created_at', '<=', $request->date_to . ' 23:59:59');
            }

            // Sort by created_at DESC (newest first)
            $query->orderBy('created_at', 'desc');

            // Paginate
            $logs = $query->paginate($perPage, ['*'], 'page', $page);

            // Format response
            $formattedLogs = $logs->map(function ($log) {
                $variant = $log->productVariant;
                $product = $variant ? $variant->product : null;

                return [
                    'id' => $log->id,
                    'variant_id' => $log->product_variant_id,
                    'variant' => $variant ? [
                        'color' => $variant->color,
                        'size' => $variant->size,
                        'sku' => $variant->sku,
                    ] : null,
                    'product' => $product ? [
                        'id' => $product->id,
                        'name' => $product->name,
                        'brand' => $product->brand,
                        'style' => $product->style,
                    ] : null,
                    'user' => $log->user ? [
                        'id' => $log->user->id,
                        'username' => $log->user->username,
                        'email' => $log->user->email,
                    ] : null,
                    'action' => $log->action,
                    'before_quantity' => $log->before_quantity,
                    'after_quantity' => $log->after_quantity,
                    'change' => $log->after_quantity !== null && $log->before_quantity !== null 
                        ? $log->after_quantity - $log->before_quantity 
                        : null,
                    'reason' => $log->reason,
                    'metadata' => $log->metadata,
                    'created_at' => $log->created_at,
                ];
            });

            return response()->json([
                'code' => HttpCode::SUCCESS,
                'status' => true,
                'message' => 'Audit logs retrieved successfully',
                'data' => [
                    'logs' => $formattedLogs,
                    'pagination' => [
                        'current_page' => $logs->currentPage(),
                        'last_page' => $logs->lastPage(),
                        'per_page' => $logs->perPage(),
                        'total' => $logs->total(),
                        'from' => $logs->firstItem(),
                        'to' => $logs->lastItem(),
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'code' => HttpCode::SERVER_ERROR,
                'status' => false,
                'message' => 'Failed to retrieve audit logs',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], HttpCode::SERVER_ERROR);
        }
    }

    /**
     * Get filter options (styles, colors, sizes)
     */
    public function getFilterOptions(): JsonResponse
    {
        try {
            $styles = Product::distinct()->pluck('style')->filter()->sort()->values();
            $colors = ProductVariant::distinct()->pluck('color')->filter()->sort()->values();
            $sizes = ProductVariant::distinct()->pluck('size')->filter()->sort()->values();

            return response()->json([
                'code' => HttpCode::SUCCESS,
                'status' => true,
                'data' => [
                    'styles' => $styles,
                    'colors' => $colors,
                    'sizes' => $sizes,
                    'actions' => [
                        'increase',
                        'decrease',
                        'adjust',
                        'map',
                        'restore',
                        'manual'
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'code' => HttpCode::SERVER_ERROR,
                'status' => false,
                'message' => 'Failed to retrieve filter options',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], HttpCode::SERVER_ERROR);
        }
    }

    /**
     * Check variant productions
     */
    public function checkVariantProductions(Request $request): JsonResponse
    {
        try {
            $variantId = $request->input('variant_id');

            if (!$variantId) {
                return response()->json([
                    'code' => HttpCode::BAD_REQUEST,
                    'status' => false,
                    'message' => 'Variant ID is required'
                ], HttpCode::BAD_REQUEST);
            }

            $productions = DB::table('productions')
                ->join('order_items', 'productions.order_item_id', '=', 'order_items.id')
                ->join('orders', 'order_items.order_id', '=', 'orders.id')
                ->where('productions.product_variant_id', $variantId)
                ->select(
                    'productions.id as production_id',
                    'productions.status',
                    'productions.quantity',
                    'orders.id as order_id',
                    'orders.ref_id as order_ref'
                )
                ->orderBy('productions.created_at', 'desc')
                ->get();

            return response()->json([
                'code' => HttpCode::SUCCESS,
                'status' => true,
                'data' => [
                    'variant_id' => $variantId,
                    'productions' => $productions
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'code' => HttpCode::SERVER_ERROR,
                'status' => false,
                'message' => 'Failed to check variant productions',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], HttpCode::SERVER_ERROR);
        }
    }
}
