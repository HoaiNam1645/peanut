<?php

namespace App\Services;

use App\Constants\StockConstants;
use App\Models\Product;
use App\Models\Production;
use App\Models\ProductVariant;
use App\Models\StockAuditLog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;

class StockService
{
    /**
     * Get stock summary statistics for a specific product or all products
     * 
     * @param int|null $productId If provided, get summary for specific product only
     */
    public function getSummary(?int $productId = null): array
    {
        try {
            // Build base query
            $baseQuery = ProductVariant::where('active', true);

            // Filter by product if provided
            if ($productId) {
                $baseQuery->where('product_id', $productId);
            }

            // Get variant IDs first (before any selectRaw)
            $variantIds = (clone $baseQuery)->pluck('variant_id')->toArray();

            // Get total stock and low stock items
            $stockSummary = (clone $baseQuery)->selectRaw('
                SUM(stock) as total_stock,
                COUNT(CASE WHEN stock < ? THEN 1 END) as low_stock_items
            ', [StockConstants::LOW_STOCK_THRESHOLD])
                ->first();

            // Get total reserved from productions with status = 'pending'
            $totalReserved = 0;
            if (!empty($variantIds)) {
                $totalReserved = Production::whereIn('product_variant_id', $variantIds)
                    ->where('status', StockConstants::PRODUCTION_STATUS_PENDING)
                    ->sum('quantity');
            }

            $totalStock = (int) ($stockSummary->total_stock ?? 0);
            $reserved = (int) $totalReserved;
            $available = $totalStock - $reserved;

            return [
                'success' => true,
                'data' => [
                    'total_stock' => $totalStock,
                    'reserved' => $reserved,
                    'available' => $available,
                    'low_stock_items' => (int) ($stockSummary->low_stock_items ?? 0),
                ]
            ];
        } catch (Exception $e) {
            Log::error('Failed to get stock summary', [
                'product_id' => $productId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'message' => 'Failed to get stock summary',
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Get products with variants for stock management
     */
    public function getStockList(array $filters = []): array
    {
        try {
            $query = Product::with(['variants' => function ($q) use ($filters) {
                // Apply filters
                if (!empty($filters['variant_id'])) {
                    $q->where('variant_id', 'like', '%' . $filters['variant_id'] . '%');
                }

                if (!empty($filters['sku'])) {
                    $q->where('sku', 'like', '%' . $filters['sku'] . '%');
                }

                if (!empty($filters['style'])) {
                    $q->where('style', $filters['style']);
                }

                if (!empty($filters['color'])) {
                    $q->where('color', $filters['color']);
                }

                if (!empty($filters['size'])) {
                    $q->where('size', $filters['size']);
                }

                if (!empty($filters['stock_level'])) {
                    switch ($filters['stock_level']) {
                        case StockConstants::STOCK_LEVEL_LOW:
                            $q->where('stock', '<', StockConstants::LOW_STOCK_THRESHOLD);
                            break;
                        case StockConstants::STOCK_LEVEL_OUT:
                            $q->where('stock', '<=', StockConstants::OUT_OF_STOCK);
                            break;
                    }
                }

                if (!empty($filters['active_status'])) {
                    $isActive = $filters['active_status'] === StockConstants::ACTIVE_STATUS_ACTIVE;
                    $q->where('active', $isActive);
                }

                $q->orderBy('variant_id');
            }]);

            // Get products and filter those with variants
            $products = $query->get()->filter(function ($product) {
                return $product->variants->count() > 0;
            })->values();

            // Get all variant IDs to fetch reserved quantities in one query
            $variantIds = $products->flatMap(function ($product) {
                return $product->variants->pluck('variant_id');
            })->unique()->toArray();

            // Fetch reserved quantities for all variants at once (avoid N+1)
            $reservedQuantities = Production::whereIn('product_variant_id', $variantIds)
                ->where('status', StockConstants::PRODUCTION_STATUS_PENDING)
                ->groupBy('product_variant_id')
                ->selectRaw('product_variant_id, SUM(quantity) as total_reserved')
                ->pluck('total_reserved', 'product_variant_id')
                ->toArray();

            // Add computed reserved and available to each variant
            foreach ($products as $product) {
                foreach ($product->variants as $variant) {
                    $reserved = (int) ($reservedQuantities[$variant->variant_id] ?? 0);
                    $variant->reserved = $reserved;
                    $variant->available = $variant->stock - $reserved;
                }
            }

            return [
                'success' => true,
                'data' => $products
            ];
        } catch (Exception $e) {
            Log::error('Failed to get stock list', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'message' => 'Failed to get stock list',
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Get filter options (styles, colors, sizes)
     */
    public function getFilterOptions(): array
    {
        try {
            $styles = ProductVariant::distinct()
                ->whereNotNull('style')
                ->pluck('style')
                ->sort()
                ->values();

            $colors = ProductVariant::distinct()
                ->whereNotNull('color')
                ->pluck('color')
                ->sort()
                ->values();

            $sizes = ProductVariant::distinct()
                ->whereNotNull('size')
                ->pluck('size')
                ->sort()
                ->values();

            return [
                'success' => true,
                'data' => [
                    'styles' => $styles,
                    'colors' => $colors,
                    'sizes' => $sizes,
                ]
            ];
        } catch (Exception $e) {
            Log::error('Failed to get filter options', [
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => 'Failed to get filter options',
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Update variant
     */
    public function updateVariant(int $variantId, array $data): array
    {
        DB::beginTransaction();
        try {
            $variant = ProductVariant::findOrFail($variantId);

            // Store old values for audit trail
            $oldValues = [
                'stock' => $variant->stock,
                'sku' => $variant->sku,
                'style' => $variant->style,
                'active' => $variant->active,
            ];

            $changes = [];
            $baseMetadata = [
                'ip' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'timestamp' => now()->toDateTimeString(),
            ];

            // Track SKU changes
            if (isset($data['sku']) && $data['sku'] !== $oldValues['sku']) {
                $changes['sku'] = [
                    'old' => $oldValues['sku'],
                    'new' => $data['sku']
                ];
                $variant->sku = $data['sku'];

                // Log SKU change
                StockAuditLog::create([
                    'product_variant_id' => $variant->variant_id,
                    'user_id' => Auth::id(),
                    'action' => StockConstants::AUDIT_ACTION_UPDATE_SKU,
                    'before_quantity' => null,
                    'after_quantity' => null,
                    'reason' => $data['reason'] ?? 'SKU updated from stock management',
                    'metadata' => json_encode(array_merge($baseMetadata, [
                        'field' => 'sku',
                        'old_value' => $oldValues['sku'],
                        'new_value' => $data['sku'],
                    ])),
                ]);
            }

            // Track Style changes
            if (isset($data['style']) && $data['style'] !== $oldValues['style']) {
                $changes['style'] = [
                    'old' => $oldValues['style'],
                    'new' => $data['style']
                ];
                $variant->style = $data['style'];

                // Log Style change
                StockAuditLog::create([
                    'product_variant_id' => $variant->variant_id,
                    'user_id' => Auth::id(),
                    'action' => StockConstants::AUDIT_ACTION_UPDATE_STYLE,
                    'before_quantity' => null,
                    'after_quantity' => null,
                    'reason' => $data['reason'] ?? 'Style updated from stock management',
                    'metadata' => json_encode(array_merge($baseMetadata, [
                        'field' => 'style',
                        'old_value' => $oldValues['style'],
                        'new_value' => $data['style'],
                    ])),
                ]);
            }

            // Track Active status changes
            if (isset($data['active']) && $data['active'] !== $oldValues['active']) {
                $changes['active'] = [
                    'old' => $oldValues['active'],
                    'new' => $data['active']
                ];
                $variant->active = $data['active'];

                // Log Active status change
                $action = $data['active']
                    ? StockConstants::AUDIT_ACTION_ACTIVATE
                    : StockConstants::AUDIT_ACTION_DEACTIVATE;

                StockAuditLog::create([
                    'product_variant_id' => $variant->variant_id,
                    'user_id' => Auth::id(),
                    'action' => $action,
                    'before_quantity' => null,
                    'after_quantity' => null,
                    'reason' => $data['reason'] ?? ($data['active'] ? 'Variant activated' : 'Variant deactivated'),
                    'metadata' => json_encode(array_merge($baseMetadata, [
                        'field' => 'active',
                        'old_value' => $oldValues['active'],
                        'new_value' => $data['active'],
                    ])),
                ]);
            }

            // Track Stock changes
            if (isset($data['stock']) && $data['stock'] !== $oldValues['stock']) {
                $changes['stock'] = [
                    'old' => $oldValues['stock'],
                    'new' => $data['stock']
                ];
                $variant->stock = $data['stock'];

                // Log stock change
                StockAuditLog::create([
                    'product_variant_id' => $variant->variant_id,
                    'user_id' => Auth::id(),
                    'action' => StockConstants::AUDIT_ACTION_ADJUST,
                    'before_quantity' => $oldValues['stock'],
                    'after_quantity' => $data['stock'],
                    'reason' => $data['reason'] ?? 'Manual stock adjustment from stock management',
                    'metadata' => json_encode(array_merge($baseMetadata, [
                        'field' => 'stock',
                        'difference' => $data['stock'] - $oldValues['stock'],
                    ])),
                ]);
            }

            $variant->save();

            // Log summary if multiple fields changed
            if (count($changes) > 1) {
                Log::info('Multiple variant fields updated', [
                    'variant_id' => $variant->variant_id,
                    'user_id' => Auth::id(),
                    'changes' => $changes,
                ]);
            }

            DB::commit();

            // Add computed reserved and available
            $reserved = Production::where('product_variant_id', $variant->variant_id)
                ->where('status', StockConstants::PRODUCTION_STATUS_PENDING)
                ->sum('quantity');

            $variant->reserved = (int) $reserved;
            $variant->available = $variant->stock - $variant->reserved;

            return [
                'success' => true,
                'data' => $variant
            ];
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Failed to update variant', [
                'variant_id' => $variantId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'message' => 'Failed to update variant',
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Get variant history - Returns last 20 changes only
     */
    public function getVariantHistory(int $variantId): array
    {
        try {
            $variant = ProductVariant::findOrFail($variantId);

            // Get only the last 20 changes
            $history = StockAuditLog::where('product_variant_id', $variant->variant_id)
                ->with('user:id,username,email')
                ->orderBy('created_at', 'desc')
                ->limit(20)
                ->get();

            return [
                'success' => true,
                'data' => $history
            ];
        } catch (Exception $e) {
            Log::error('Failed to get variant history', [
                'variant_id' => $variantId,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => 'Failed to get variant history',
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Bulk update variants
     */
    public function bulkUpdateVariants(array $variantIds, string $action, ?int $stockValue = null, ?string $reason = null): array
    {
        DB::beginTransaction();
        try {
            $variants = ProductVariant::whereIn('id', $variantIds)->get();

            if ($variants->isEmpty()) {
                return [
                    'success' => false,
                    'message' => 'No variants found'
                ];
            }

            $baseMetadata = [
                'ip' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'timestamp' => now()->toDateTimeString(),
                'bulk_action' => true,
            ];

            foreach ($variants as $variant) {
                $oldStock = $variant->stock;

                switch ($action) {
                    case StockConstants::BULK_ACTION_ACTIVATE:
                        if (!$variant->active) {
                            $variant->active = true;

                            // Log activation
                            StockAuditLog::create([
                                'product_variant_id' => $variant->variant_id,
                                'user_id' => Auth::id(),
                                'action' => StockConstants::AUDIT_ACTION_ACTIVATE,
                                'before_quantity' => null,
                                'after_quantity' => null,
                                'reason' => $reason ?? 'Bulk activation from stock management',
                                'metadata' => json_encode(array_merge($baseMetadata, [
                                    'field' => 'active',
                                    'old_value' => false,
                                    'new_value' => true,
                                    'operation' => 'activate',
                                ])),
                            ]);
                        }
                        break;

                    case StockConstants::BULK_ACTION_DEACTIVATE:
                        if ($variant->active) {
                            $variant->active = false;

                            // Log deactivation
                            StockAuditLog::create([
                                'product_variant_id' => $variant->variant_id,
                                'user_id' => Auth::id(),
                                'action' => StockConstants::AUDIT_ACTION_DEACTIVATE,
                                'before_quantity' => null,
                                'after_quantity' => null,
                                'reason' => $reason ?? 'Bulk deactivation from stock management',
                                'metadata' => json_encode(array_merge($baseMetadata, [
                                    'field' => 'active',
                                    'old_value' => true,
                                    'new_value' => false,
                                    'operation' => 'deactivate',
                                ])),
                            ]);
                        }
                        break;

                    case StockConstants::BULK_ACTION_ADD_STOCK:
                        if ($stockValue === null || $stockValue <= 0) {
                            throw new Exception('Stock value must be greater than 0');
                        }

                        $variant->stock += $stockValue;

                        // Log stock change
                        StockAuditLog::create([
                            'product_variant_id' => $variant->variant_id,
                            'user_id' => Auth::id(),
                            'action' => StockConstants::AUDIT_ACTION_INCREASE,
                            'before_quantity' => $oldStock,
                            'after_quantity' => $variant->stock,
                            'reason' => $reason ?? 'Bulk add stock from stock management',
                            'metadata' => json_encode(array_merge($baseMetadata, [
                                'field' => 'stock',
                                'operation' => 'add',
                                'amount_added' => $stockValue,
                                'difference' => $variant->stock - $oldStock,
                            ])),
                        ]);
                        break;

                    case StockConstants::BULK_ACTION_SUBTRACT_STOCK:
                        if ($stockValue === null || $stockValue <= 0) {
                            throw new Exception('Stock value must be greater than 0');
                        }

                        $variant->stock -= $stockValue;
                        if ($variant->stock < 0) {
                            $variant->stock = 0;
                        }

                        // Log stock change
                        StockAuditLog::create([
                            'product_variant_id' => $variant->variant_id,
                            'user_id' => Auth::id(),
                            'action' => StockConstants::AUDIT_ACTION_DECREASE,
                            'before_quantity' => $oldStock,
                            'after_quantity' => $variant->stock,
                            'reason' => $reason ?? 'Bulk subtract stock from stock management',
                            'metadata' => json_encode(array_merge($baseMetadata, [
                                'field' => 'stock',
                                'operation' => 'subtract',
                                'amount_subtracted' => $stockValue,
                                'difference' => $variant->stock - $oldStock,
                                'capped_at_zero' => $oldStock - $stockValue < 0,
                            ])),
                        ]);
                        break;

                    case StockConstants::BULK_ACTION_SET_STOCK:
                        if ($stockValue === null || $stockValue < 0) {
                            throw new Exception('Stock value must be 0 or greater');
                        }

                        $variant->stock = $stockValue;

                        // Log stock change
                        StockAuditLog::create([
                            'product_variant_id' => $variant->variant_id,
                            'user_id' => Auth::id(),
                            'action' => StockConstants::AUDIT_ACTION_ADJUST,
                            'before_quantity' => $oldStock,
                            'after_quantity' => $variant->stock,
                            'reason' => $reason ?? 'Bulk set stock from stock management',
                            'metadata' => json_encode(array_merge($baseMetadata, [
                                'field' => 'stock',
                                'operation' => 'set',
                                'new_value' => $stockValue,
                                'difference' => $variant->stock - $oldStock,
                            ])),
                        ]);
                        break;

                    default:
                        throw new Exception('Invalid bulk action');
                }

                $variant->save();
            }

            DB::commit();

            return [
                'success' => true,
                'data' => [
                    'updated_count' => $variants->count()
                ]
            ];
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Failed to bulk update variants', [
                'variant_ids' => $variantIds,
                'action' => $action,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'message' => 'Failed to bulk update variants',
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Adjust stock (existing method - kept for backward compatibility)
     */
    public function adjustStock(
        string $variantId,
        int $adjustment,
        string $action,
        string $reason,
        array $metadata = []
    ): array {
        try {
            // Get variant with lock
            $variant = ProductVariant::where('variant_id', $variantId)
                ->lockForUpdate()
                ->first();

            if (!$variant) {
                return [
                    'success' => false,
                    'message' => 'Product variant not found'
                ];
            }

            $beforeQuantity = (int) $variant->stock;
            $afterQuantity = $beforeQuantity + $adjustment;

            // Prevent negative stock
            if ($afterQuantity < 0) {
                return [
                    'success' => false,
                    'message' => 'Stock cannot be negative'
                ];
            }

            // Update stock
            $variant->update(['stock' => $afterQuantity]);

            // Create audit log
            StockAuditLog::create([
                'product_variant_id' => $variantId,
                'user_id' => Auth::id() ?? 1,
                'action' => $action,
                'before_quantity' => $beforeQuantity,
                'after_quantity' => $afterQuantity,
                'reason' => $reason,
                'metadata' => $metadata,
            ]);

            Log::info('Stock adjusted', [
                'variant_id' => $variantId,
                'action' => $action,
                'adjustment' => $adjustment,
                'before' => $beforeQuantity,
                'after' => $afterQuantity,
                'reason' => $reason
            ]);

            return [
                'success' => true,
                'message' => 'Stock adjusted successfully',
                'data' => [
                    'variant_id' => $variantId,
                    'before_quantity' => $beforeQuantity,
                    'after_quantity' => $afterQuantity,
                    'adjustment' => $adjustment
                ]
            ];
        } catch (Exception $e) {
            Log::error('Failed to adjust stock', [
                'variant_id' => $variantId,
                'adjustment' => $adjustment,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'message' => 'Failed to adjust stock: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Get audit logs (existing method - kept for backward compatibility)
     */
    public function getAuditLogs(string $variantId, int $limit = 50): array
    {
        try {
            $logs = StockAuditLog::where('product_variant_id', $variantId)
                ->with('user:id,username,email')
                ->orderBy('created_at', 'desc')
                ->limit($limit)
                ->get();

            return [
                'success' => true,
                'data' => $logs
            ];
        } catch (Exception $e) {
            Log::error('Failed to get audit logs', [
                'variant_id' => $variantId,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => 'Failed to get audit logs'
            ];
        }
    }

    public function importStock($request)
    {
        $file = $request->file;
        $stockType = $request->stock_type ?? 'set';

        if (!in_array($stockType, ['add_stock', 'subtract_stock', 'set'])) {
            return [
                'success' => false,
                'message' => 'Invalid stock_type. Must be: add_stock, subtract_stock, or set'
            ];
        }

        $dataFile = $this->realFile($file);
        $header = $dataFile['header'];
        $rows = $dataFile['rows'];

        if (empty($rows)) {
            return [
                'success' => false,
                'message' => 'The file is empty or has no data rows'
            ];
        }

        // Normalize headers for case-insensitive matching
        $headerMap = [];
        foreach ($header as $h) {
            // Remove BOM and normalize
            $cleaned = $this->removeBOM(trim($h));
            $normalized = strtolower($cleaned);
            $headerMap[$normalized] = $cleaned;
        }

        // Check if file has at least one identifier
        $hasVariantId = isset($headerMap['variant_id']) || isset($headerMap['variant id']) || isset($headerMap['variants']);
        $hasSku = isset($headerMap['sku']);
        
        if (!$hasVariantId && !$hasSku) {
            return [
                'success' => false,
                'message' => 'File must have at least one identifier column: variant_id or sku'
            ];
        }

        // Process import
        return $this->processImport($rows, $headerMap, $stockType);
    }

    /**
     * Process import with flexible field handling
     */
    private function processImport(array $rows, array $headerMap, string $stockType): array
    {
        DB::beginTransaction();
        try {
            $successCount = 0;
            $failedCount = 0;
            $errors = [];

            $baseMetadata = [
                'ip' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'timestamp' => now()->toDateTimeString(),
                'import_type' => $stockType,
            ];

            foreach ($rows as $index => $row) {
                try {
                    // Get identifiers (case-insensitive)
                    $variantId = $this->getValueFromRow($row, ['variant_id', 'variant id', 'variants']);
                    $sku = $this->getValueFromRow($row, ['sku']);

                    // Skip if no identifier
                    if (empty($variantId) && empty($sku)) {
                        $errors[] = "Row " . ($index + 2) . ": Missing both variant_id and sku";
                        $failedCount++;
                        continue;
                    }

                    // Find variant
                    $query = ProductVariant::query();

                    if (!empty($variantId) && !empty($sku)) {
                        // Both provided - use both
                        $query->where('variant_id', $variantId)->where('sku', $sku);
                    } elseif (!empty($variantId)) {
                        // Only variant_id
                        $query->where('variant_id', $variantId);
                    } else {
                        // Only sku
                        $query->where('sku', $sku);
                    }

                    $variant = $query->first();

                    if (!$variant) {
                        $identifier = !empty($variantId) ? "variant_id: {$variantId}" : "sku: {$sku}";
                        $errors[] = "Row " . ($index + 2) . ": Variant not found ({$identifier})";
                        $failedCount++;
                        continue;
                    }

                    // Store old values
                    $oldStock = $variant->stock;
                    $changes = [];

                    // Process stock if present
                    $stockValue = $this->getValueFromRow($row, ['stock']);
                    if ($stockValue !== null && $stockValue !== '') {
                        $stockValue = (int) $stockValue;
                        
                        switch ($stockType) {
                            case 'add_stock':
                                $variant->stock += $stockValue;
                                $action = StockConstants::AUDIT_ACTION_INCREASE;
                                break;
                            case 'subtract_stock':
                                $variant->stock -= $stockValue;
                                if ($variant->stock < 0) $variant->stock = 0;
                                $action = StockConstants::AUDIT_ACTION_DECREASE;
                                break;
                            case 'set':
                            default:
                                $variant->stock = $stockValue;
                                $action = StockConstants::AUDIT_ACTION_ADJUST;
                                break;
                        }

                        $changes['stock'] = [
                            'old' => $oldStock,
                            'new' => $variant->stock
                        ];

                        // Log stock change
                        StockAuditLog::create([
                            'product_variant_id' => $variant->variant_id,
                            'user_id' => Auth::id() ?? 1,
                            'action' => $action,
                            'before_quantity' => $oldStock,
                            'after_quantity' => $variant->stock,
                            'reason' => "CSV import - {$stockType}",
                            'metadata' => json_encode(array_merge($baseMetadata, [
                                'row' => $index + 2,
                                'stock_type' => $stockType,
                                'value' => $stockValue,
                            ])),
                        ]);
                    }

                    // Process other fields dynamically
                    $updatableFields = ['style', 'color', 'size', 'product'];
                    foreach ($updatableFields as $field) {
                        $value = $this->getValueFromRow($row, [$field]);
                        if ($value !== null && $value !== '' && $variant->{$field} !== $value) {
                            $changes[$field] = [
                                'old' => $variant->{$field},
                                'new' => $value
                            ];
                            $variant->{$field} = $value;

                            // Log field change
                            StockAuditLog::create([
                                'product_variant_id' => $variant->variant_id,
                                'user_id' => Auth::id() ?? 1,
                                'action' => 'update_' . $field,
                                'before_quantity' => null,
                                'after_quantity' => null,
                                'reason' => "CSV import - field update",
                                'metadata' => json_encode(array_merge($baseMetadata, [
                                    'row' => $index + 2,
                                    'field' => $field,
                                    'old_value' => $changes[$field]['old'],
                                    'new_value' => $value,
                                ])),
                            ]);
                        }
                    }

                    // Save if there are changes
                    if (!empty($changes)) {
                        $variant->save();
                        $successCount++;
                    }

                } catch (Exception $e) {
                    $errors[] = "Row " . ($index + 2) . ": " . $e->getMessage();
                    $failedCount++;
                    Log::error('Import row failed', [
                        'row' => $index + 2,
                        'error' => $e->getMessage()
                    ]);
                }
            }

            DB::commit();

            return [
                'success' => true,
                'message' => "Import completed: {$successCount} succeeded, {$failedCount} failed",
                'data' => [
                    'success_count' => $successCount,
                    'failed_count' => $failedCount,
                    'errors' => $errors
                ]
            ];

        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Import failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'message' => 'Import failed: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Remove BOM (Byte Order Mark) from string
     */
    private function removeBOM(string $text): string
    {
        // Remove UTF-8 BOM
        $bom = pack('H*','EFBBBF');
        $text = preg_replace("/^$bom/", '', $text);
        
        // Remove other common BOMs
        $text = str_replace("\xEF\xBB\xBF", '', $text); // UTF-8
        $text = str_replace("\xFF\xFE", '', $text);     // UTF-16 LE
        $text = str_replace("\xFE\xFF", '', $text);     // UTF-16 BE
        
        return $text;
    }

    /**
     * Get value from row with case-insensitive key matching
     */
    private function getValueFromRow(array $row, array $possibleKeys): ?string
    {
        foreach ($possibleKeys as $key) {
            foreach ($row as $rowKey => $value) {
                $cleanedRowKey = $this->removeBOM(trim($rowKey));
                if (strtolower($cleanedRowKey) === strtolower(trim($key))) {
                    return trim($value);
                }
            }
        }
        return null;
    }

    private function realFile($file)
    {
        $rows = [];
        $header = [];

        if (($handle = fopen($file->getRealPath(), 'r')) !== false) {

            if (($header = fgetcsv($handle, 1000, ',')) !== false) {
                // Remove BOM and trim each header
                $header = array_map(function($h) {
                    return $this->removeBOM(trim($h));
                }, $header);
            }

            while (($data = fgetcsv($handle, 1000, ',')) !== false) {
                $rows[] = array_combine($header, $data);
            }

            fclose($handle);
        }

        return [
            'header' => $header,
            'rows' => $rows,
        ];
    }

    public function detect(array $header): string
    {
        $header = array_map('strtolower', $header);

        // Nếu có "variants" => type = variants
        if (in_array('variants', $header)) {
            return 'variants';
        }

        // Nếu có "sku" => type = sku
        if (in_array('sku', $header)) {
            return 'sku';
        }

        // Tuỳ bạn định nghĩa thêm
        if (in_array('variants', $header) && in_array('sku', $header)) {
            return 'variants_sku';
        }

        // Không match
        return 'unknown';
    }

    /**
     * Export stock data to CSV
     */
    public function exportStock(array $filters = []): array
    {
        try {
            // Get stock data with filters
            $stockResult = $this->getStockList($filters);
            
            if (!$stockResult['success']) {
                return [
                    'success' => false,
                    'message' => 'Failed to get stock data for export'
                ];
            }

            $products = $stockResult['data'];
            
            // Prepare CSV data
            $csvData = [];
            
            // CSV Header
            $csvData[] = [
                'Variant ID',
                'SKU',
                'Product',
                'Style',
                'Color',
                'Size',
                'Stock',
                'Reserved',
                'Available',
                'Status'
            ];

            // CSV Rows
            foreach ($products as $product) {
                foreach ($product->variants as $variant) {
                    $csvData[] = [
                        $variant->variant_id ?? '',
                        $variant->sku ?? '',
                        $product->name ?? '',
                        $variant->style ?? '',
                        $variant->color ?? '',
                        $variant->size ?? '',
                        $variant->stock ?? 0,
                        $variant->reserved ?? 0,
                        $variant->available ?? 0,
                        $variant->active ? 'Active' : 'Inactive'
                    ];
                }
            }

            // Generate CSV content
            $output = fopen('php://temp', 'r+');
            foreach ($csvData as $row) {
                fputcsv($output, $row);
            }
            rewind($output);
            $csvContent = stream_get_contents($output);
            fclose($output);

            return [
                'success' => true,
                'data' => $csvContent,
                'filename' => 'stock_export_' . date('Y-m-d_His') . '.csv'
            ];

        } catch (Exception $e) {
            Log::error('Failed to export stock', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'message' => 'Failed to export stock: ' . $e->getMessage()
            ];
        }
    }
}
