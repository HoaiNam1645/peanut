<?php

namespace App\Http\Controllers;

use App\Constants\HttpCode;
use App\Constants\ResponseMessage;
use App\Constants\StockConstants;
use App\Http\Requests\ImportStockRequest;
use App\Services\StockService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class StockController extends Controller
{
    protected StockService $stockService;

    public function __construct(StockService $stockService)
    {
        $this->stockService = $stockService;
    }

    /**
     * Get stock summary statistics for a specific product
     */
    public function summary(Request $request): JsonResponse
    {
        try {
            $productId = $request->input('product_id');

            $result = $this->stockService->getSummary($productId);

            if (!$result['success']) {
                return response()->json([
                    'code' => HttpCode::SERVER_ERROR,
                    'status' => false,
                    'message' => ResponseMessage::STOCK_SUMMARY_RETRIEVED_FAILED,
                    'error' => config('app.debug') ? ($result['error'] ?? null) : null
                ], HttpCode::SERVER_ERROR);
            }

            return response()->json([
                'code' => HttpCode::SUCCESS,
                'status' => true,
                'message' => ResponseMessage::STOCK_SUMMARY_RETRIEVED,
                'data' => $result['data']
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'code' => HttpCode::SERVER_ERROR,
                'status' => false,
                'message' => ResponseMessage::STOCK_SUMMARY_RETRIEVED_FAILED,
                'error' => config('app.debug') ? $e->getMessage() : null
            ], HttpCode::SERVER_ERROR);
        }
    }

    /**
     * Get products with variants for stock management
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $filters = [
                'variant_id' => $request->input('variant_id'),
                'sku' => $request->input('sku'),
                'style' => $request->input('style'),
                'color' => $request->input('color'),
                'size' => $request->input('size'),
                'stock_level' => $request->input('stock_level'),
                'active_status' => $request->input('active_status'),
            ];

            $result = $this->stockService->getStockList($filters);

            if (!$result['success']) {
                return response()->json([
                    'code' => HttpCode::SERVER_ERROR,
                    'status' => false,
                    'message' => ResponseMessage::STOCK_LIST_RETRIEVED_FAILED,
                    'error' => config('app.debug') ? ($result['error'] ?? null) : null
                ], HttpCode::SERVER_ERROR);
            }

            return response()->json([
                'code' => HttpCode::SUCCESS,
                'status' => true,
                'message' => ResponseMessage::STOCK_LIST_RETRIEVED,
                'data' => $result['data']
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'code' => HttpCode::SERVER_ERROR,
                'status' => false,
                'message' => ResponseMessage::STOCK_LIST_RETRIEVED_FAILED,
                'error' => config('app.debug') ? $e->getMessage() : null
            ], HttpCode::SERVER_ERROR);
        }
    }

    /**
     * Get filter options (styles, colors, sizes)
     */
    public function filterOptions(): JsonResponse
    {
        try {
            $result = $this->stockService->getFilterOptions();

            if (!$result['success']) {
                return response()->json([
                    'code' => HttpCode::SERVER_ERROR,
                    'status' => false,
                    'message' => ResponseMessage::STOCK_FILTER_OPTIONS_RETRIEVED_FAILED,
                    'error' => config('app.debug') ? ($result['error'] ?? null) : null
                ], HttpCode::SERVER_ERROR);
            }

            return response()->json([
                'code' => HttpCode::SUCCESS,
                'status' => true,
                'message' => ResponseMessage::STOCK_FILTER_OPTIONS_RETRIEVED,
                'data' => $result['data']
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'code' => HttpCode::SERVER_ERROR,
                'status' => false,
                'message' => ResponseMessage::STOCK_FILTER_OPTIONS_RETRIEVED_FAILED,
                'error' => config('app.debug') ? $e->getMessage() : null
            ], HttpCode::SERVER_ERROR);
        }
    }

    /**
     * Update variant
     */
    public function updateVariant(Request $request, int $id): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'sku' => 'nullable|string|max:255',
                'style' => 'nullable|string|max:255',
                'stock' => 'nullable|integer|min:0',
                'active' => 'nullable|boolean',
                'reason' => 'nullable|string|max:500',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'code' => HttpCode::VALIDATION_ERROR,
                    'status' => false,
                    'message' => ResponseMessage::VALIDATION_FAILED,
                    'errors' => $validator->errors()
                ], HttpCode::VALIDATION_ERROR);
            }

            $result = $this->stockService->updateVariant($id, $request->all());

            if (!$result['success']) {
                return response()->json([
                    'code' => HttpCode::SERVER_ERROR,
                    'status' => false,
                    'message' => ResponseMessage::VARIANT_UPDATE_FAILED,
                    'error' => config('app.debug') ? ($result['error'] ?? null) : null
                ], HttpCode::SERVER_ERROR);
            }

            return response()->json([
                'code' => HttpCode::SUCCESS,
                'status' => true,
                'message' => ResponseMessage::VARIANT_UPDATED,
                'data' => $result['data']
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'code' => HttpCode::SERVER_ERROR,
                'status' => false,
                'message' => ResponseMessage::VARIANT_UPDATE_FAILED,
                'error' => config('app.debug') ? $e->getMessage() : null
            ], HttpCode::SERVER_ERROR);
        }
    }

    /**
     * Get variant history - Returns last 20 changes only
     */
    public function history(int $id): JsonResponse
    {
        try {
            $result = $this->stockService->getVariantHistory($id);

            if (!$result['success']) {
                return response()->json([
                    'code' => HttpCode::NOT_FOUND,
                    'status' => false,
                    'message' => ResponseMessage::VARIANT_HISTORY_RETRIEVED_FAILED,
                    'error' => config('app.debug') ? ($result['error'] ?? null) : null
                ], HttpCode::NOT_FOUND);
            }

            return response()->json([
                'code' => HttpCode::SUCCESS,
                'status' => true,
                'message' => ResponseMessage::VARIANT_HISTORY_RETRIEVED,
                'data' => $result['data']
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'code' => HttpCode::SERVER_ERROR,
                'status' => false,
                'message' => ResponseMessage::VARIANT_HISTORY_RETRIEVED_FAILED,
                'error' => config('app.debug') ? $e->getMessage() : null
            ], HttpCode::SERVER_ERROR);
        }
    }

    /**
     * Bulk update variants
     */
    public function bulkUpdate(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'variant_ids' => 'required|array|min:1',
                'variant_ids.*' => 'required|integer|exists:product_variants,id',
                'action' => [
                    'required',
                    'in:' . implode(',', [
                        StockConstants::BULK_ACTION_ACTIVATE,
                        StockConstants::BULK_ACTION_DEACTIVATE,
                        StockConstants::BULK_ACTION_ADD_STOCK,
                        StockConstants::BULK_ACTION_SUBTRACT_STOCK,
                        StockConstants::BULK_ACTION_SET_STOCK,
                    ])
                ],
                'stock_value' => [
                    'required_if:action,' . StockConstants::BULK_ACTION_ADD_STOCK,
                    'required_if:action,' . StockConstants::BULK_ACTION_SUBTRACT_STOCK,
                    'required_if:action,' . StockConstants::BULK_ACTION_SET_STOCK,
                    'integer',
                    'min:0'
                ],
                'reason' => 'nullable|string|max:500',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'code' => HttpCode::VALIDATION_ERROR,
                    'status' => false,
                    'message' => ResponseMessage::VALIDATION_FAILED,
                    'errors' => $validator->errors()
                ], HttpCode::VALIDATION_ERROR);
            }

            $result = $this->stockService->bulkUpdateVariants(
                $request->variant_ids,
                $request->action,
                $request->stock_value,
                $request->reason
            );

            if (!$result['success']) {
                return response()->json([
                    'code' => HttpCode::SERVER_ERROR,
                    'status' => false,
                    'message' => ResponseMessage::VARIANTS_BULK_UPDATE_FAILED,
                    'error' => config('app.debug') ? ($result['error'] ?? null) : null
                ], HttpCode::SERVER_ERROR);
            }

            return response()->json([
                'code' => HttpCode::SUCCESS,
                'status' => true,
                'message' => ResponseMessage::VARIANTS_BULK_UPDATED,
                'data' => $result['data']
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'code' => HttpCode::SERVER_ERROR,
                'status' => false,
                'message' => ResponseMessage::VARIANTS_BULK_UPDATE_FAILED,
                'error' => config('app.debug') ? $e->getMessage() : null
            ], HttpCode::SERVER_ERROR);
        }
    }

    public function importStock(ImportStockRequest $request): JsonResponse
    {
        try {
            $result = $this->stockService->importStock($request);

            if (!$result['success']) {
                return response()->json([
                    'code' => HttpCode::BAD_REQUEST,
                    'status' => false,
                    'message' => $result['message'] ?? ResponseMessage::IMPORT_STOCK_FAILED,
                    'data' => $result['data'] ?? null,
                    'error' => config('app.debug') ? ($result['error'] ?? null) : null
                ], HttpCode::BAD_REQUEST);
            }

            return response()->json([
                'code' => HttpCode::SUCCESS,
                'status' => true,
                'message' => $result['message'] ?? ResponseMessage::UPDATED_SUCCESS,
                'data' => $result['data'] ?? null
            ], HttpCode::SUCCESS);

        } catch (\Exception $e) {
            Log::error('Import stock failed in controller', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'code' => HttpCode::SERVER_ERROR,
                'status' => false,
                'message' => ResponseMessage::IMPORT_STOCK_FAILED,
                'error' => config('app.debug') ? $e->getMessage() : null
            ], HttpCode::SERVER_ERROR);
        }
    }

    public function exportStock(Request $request)
    {
        try {
            $filters = $request->only([
                'variant_id',
                'sku',
                'style',
                'color',
                'size',
                'stock_level',
                'active_status'
            ]);

            $result = $this->stockService->exportStock($filters);

            if (!$result['success']) {
                return response()->json([
                    'code' => HttpCode::BAD_REQUEST,
                    'status' => false,
                    'message' => $result['message'] ?? 'Failed to export stock',
                ], HttpCode::BAD_REQUEST);
            }

            // Return CSV file as download
            return response($result['data'], 200, [
                'Content-Type' => 'text/csv',
                'Content-Disposition' => 'attachment; filename="' . $result['filename'] . '"',
            ]);

        } catch (\Exception $e) {
            Log::error('Export stock failed in controller', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'code' => HttpCode::SERVER_ERROR,
                'status' => false,
                'message' => 'Failed to export stock',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], HttpCode::SERVER_ERROR);
        }
    }
}
