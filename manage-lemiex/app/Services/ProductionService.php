<?php

namespace App\Services;

use App\Models\Production;
use App\Models\ProductVariant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;

class ProductionService
{
    protected $stockService;

    public function __construct(StockService $stockService)
    {
        $this->stockService = $stockService;
    }

    public function mapStock(Production $production): array
    {
        try {
            // Step 3.1: Validate production status
            if (!in_array($production->status, ['pending', 'pickup'])) {
                Log::warning('Attempted to map non-pending production', [
                    'production_id' => $production->id,
                    'status' => $production->status
                ]);

                return [
                    'success' => false,
                    'message' => 'Production status must be pending or pickup'
                ];
            }

            // Step 3.2: Lock product variant (FOR UPDATE)
            $variant = ProductVariant::where('variant_id', $production->product_variant_id)
                ->lockForUpdate()
                ->first();

            if (!$variant) {
                return [
                    'success' => false,
                    'message' => 'Product variant not found'
                ];
            }

            // Step 3.3: Check stock availability
            $requiredQuantity = (int) $production->quantity;
            $currentStock = (int) $variant->stock;

            if ($currentStock < $requiredQuantity) {
                Log::warning('Insufficient stock for production', [
                    'production_id' => $production->id,
                    'variant_id' => $variant->variant_id,
                    'required' => $requiredQuantity,
                    'available' => $currentStock,
                    'shortage' => $requiredQuantity - $currentStock
                ]);

                return [
                    'success' => false,
                    'message' => "Insufficient stock. Required: {$requiredQuantity}, Available: {$currentStock}"
                ];
            }

            // Step 3.4: Deduct stock
            $adjustResult = $this->stockService->adjustStock(
                $variant->variant_id,
                -$requiredQuantity,
                'decrease',
                "Mapped for production #{$production->id}",
                [
                    'production_id' => $production->id,
                    'order_item_id' => $production->order_item_id
                ]
            );

            if (!$adjustResult['success']) {
                return [
                    'success' => false,
                    'message' => $adjustResult['message'] ?? 'Failed to adjust stock'
                ];
            }

            // Step 3.6: Update production status
            $production->update(['status' => 'mapped']);

            Log::info('Stock mapped for production', [
                'production_id' => $production->id,
                'variant_id' => $variant->variant_id,
                'quantity' => $requiredQuantity,
                'old_stock' => $currentStock,
                'new_stock' => $currentStock - $requiredQuantity
            ]);

            return [
                'success' => true,
                'message' => 'Stock mapped successfully',
                'data' => [
                    'production_id' => $production->id,
                    'variant_id' => $variant->variant_id,
                    'quantity_mapped' => $requiredQuantity,
                    'old_stock' => $currentStock,
                    'new_stock' => $currentStock - $requiredQuantity
                ]
            ];
        } catch (Exception $e) {
            Log::error('Failed to map stock for production', [
                'production_id' => $production->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'message' => 'Failed to map stock: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Unmap stock - reverse the mapStock operation
     * Used when QC rejects an item
     */
    public function unmapStock(Production $production): array
    {
        try {
            // Step 1: Validate production status (must be 'mapped')
            if ($production->status !== 'mapped') {
                Log::warning('Attempted to unmap non-mapped production', [
                    'production_id' => $production->id,
                    'status' => $production->status
                ]);

                return [
                    'success' => false,
                    'message' => 'Production status must be mapped to unmap'
                ];
            }

            // Step 2: Get product variant
            $variant = ProductVariant::where('variant_id', $production->product_variant_id)
                ->lockForUpdate()
                ->first();

            if (!$variant) {
                return [
                    'success' => false,
                    'message' => 'Product variant not found'
                ];
            }

            // Step 3: Add back stock
            $quantity = (int) $production->quantity;
            $currentStock = (int) $variant->stock;

            $adjustResult = $this->stockService->adjustStock(
                $variant->variant_id,
                $quantity,
                'increase',
                "Unmapped from production #{$production->id} (QC rejected)",
                [
                    'production_id' => $production->id,
                    'order_item_id' => $production->order_item_id
                ]
            );

            if (!$adjustResult['success']) {
                return [
                    'success' => false,
                    'message' => $adjustResult['message'] ?? 'Failed to restore stock'
                ];
            }

            // Step 4: Reset production status to pending
            $production->update(['status' => 'pending']);

            Log::info('Stock unmapped for production (QC rejected)', [
                'production_id' => $production->id,
                'variant_id' => $variant->variant_id,
                'quantity' => $quantity,
                'old_stock' => $currentStock,
                'new_stock' => $currentStock + $quantity
            ]);

            return [
                'success' => true,
                'message' => 'Stock unmapped successfully',
                'data' => [
                    'production_id' => $production->id,
                    'variant_id' => $variant->variant_id,
                    'quantity_restored' => $quantity,
                    'old_stock' => $currentStock,
                    'new_stock' => $currentStock + $quantity
                ]
            ];
        } catch (Exception $e) {
            Log::error('Failed to unmap stock for production', [
                'production_id' => $production->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'message' => 'Failed to unmap stock: ' . $e->getMessage()
            ];
        }
    }
}
