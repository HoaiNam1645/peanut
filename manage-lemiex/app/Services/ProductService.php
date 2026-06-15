<?php

namespace App\Services;

use App\Models\Tier;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ProductPriceVariant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProductService
{
    /**
     * Create a new product with variants and prices
     */
    public function createProduct(array $data): Product
    {
        return DB::transaction(function () use ($data) {
            // Create the product
            $product = Product::create([
                'name' => $data['name'],
                'style' => $data['style'] ?? null,
                'status' => $data['status'] ?? true,
                'category_type' => $data['category_type'] ?? 'embroidery',
                'mockup' => $data['mockup'] ?? null,
                'template_url' => $data['template_url'] ?? null,
                'brand' => $data['brand'] ?? null,
                'warehouse_name' => $data['warehouse_name'] ?? null,
            ]);

            Log::info('Product created', [
                'product_id' => $product->id,
                'name' => $product->name
            ]);

            // Create variants if provided
            if (isset($data['variants']) && is_array($data['variants'])) {
                foreach ($data['variants'] as $variantData) {
                    $this->createVariant($product, $variantData);
                }
            }

            // Load relationships for response
            $product->load(['variants.priceVariants']);

            return $product;
        });
    }

    /**
     * Create a variant for a product
     */
    private function createVariant(Product $product, array $variantData): ProductVariant
    {
        $variant = ProductVariant::create([
            'product_id' => $product->id,
            'variant_id' => $variantData['variant_id'],
            'sku' => $variantData['sku'] ?? null,
            'style' => $variantData['style'] ?? $product->style,
            'color' => $variantData['color'] ?? null,
            'size' => $variantData['size'] ?? null,
            'stock' => $variantData['stock'] ?? 0,
            'active' => $variantData['active'] ?? true,
            'weight' => $variantData['weight'] ?? null,
            'length' => $variantData['length'] ?? null,
            'width' => $variantData['width'] ?? null,
            'height' => $variantData['height'] ?? null,
            'supplier_price' => $variantData['supplier_price'] ?? null,
        ]);

        Log::info('Variant created', [
            'variant_id' => $variant->variant_id,
            'product_id' => $product->id
        ]);

        // Create price variants if provided
        if (isset($variantData['prices']) && is_array($variantData['prices'])) {
            foreach ($variantData['prices'] as $priceData) {
                $this->createPriceVariant($variant, $priceData);
            }
        }

        return $variant;
    }

    /**
     * Create a price variant
     */
    private function createPriceVariant(ProductVariant $variant, array $priceData): ProductPriceVariant
    {
        $priceVariant = ProductPriceVariant::create([
            'product_variant_id' => $variant->variant_id,
            'tier_id' => $priceData['tier_id'],
            'type' => $priceData['type'],
            'price' => $priceData['price'],
        ]);

        Log::info('Price variant created', [
            'variant_id' => $variant->variant_id,
            'tier_id' => $priceData['tier_id'],
            'type' => $priceData['type']
        ]);

        return $priceVariant;
    }

    /**
     * Get filter options for products
     */
    public function getFilterOptions(): array
    {
        return [
            'brands' => Product::select('brand')
                ->distinct()
                ->whereNotNull('brand')
                ->orderBy('brand')
                ->pluck('brand'),
            'styles' => Product::select('style')
                ->distinct()
                ->whereNotNull('style')
                ->orderBy('style')
                ->pluck('style'),
            'colors' => ProductVariant::select('color')
                ->distinct()
                ->whereNotNull('color')
                ->orderBy('color')
                ->pluck('color'),
            'sizes' => ProductVariant::select('size')
                ->distinct()
                ->whereNotNull('size')
                ->orderByRaw("FIELD(size, 'XS', 'S', 'M', 'L', 'XL', '2XL', '3XL', '4XL', '5XL')")
                ->pluck('size'),
        ];
    }

    /**
     * Get tiers for pricing
     */
    public function getTiers(): array
    {
        return Tier::query()
            ->orderBy('tier_id')
            ->get(['tier_id', 'name'])
            ->map(fn (Tier $tier) => [
                'id' => $tier->tier_id,
                'name' => $tier->name,
            ])
            ->values()
            ->toArray();
    }

    /**
     * Get price types
     */
    public function getPriceTypes(): array
    {
        return [
            'base_cost',
            'seller_shipping',
            'priority_shipping',
            'shipping_cost',
        ];
    }

    /**
     * Update variant tier pricing
     */
    public function updateVariantPricing(string $variantId, array $prices): array
    {
        try {
            DB::beginTransaction();

            // Delete existing prices for this variant
            ProductPriceVariant::where('product_variant_id', $variantId)->delete();

            // Insert new prices
            $createdPrices = [];
            foreach ($prices as $priceData) {
                $price = ProductPriceVariant::create([
                    'product_variant_id' => $variantId,
                    'tier_id' => $priceData['tier_id'],
                    'type' => $priceData['type'],
                    'price' => $priceData['price'],
                ]);
                $createdPrices[] = $price;
            }

            DB::commit();

            Log::info('Variant pricing updated', [
                'variant_id' => $variantId,
                'prices_count' => count($createdPrices)
            ]);

            return [
                'success' => true,
                'data' => [
                    'variant_id' => $variantId,
                    'prices' => $createdPrices
                ]
            ];
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Failed to update variant pricing', [
                'variant_id' => $variantId,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => 'Failed to update pricing: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Parse and validate CSV file for import
     */
    public function parseCSV(string $filePath): array
    {
        try {
            $handle = fopen($filePath, 'r');
            if (!$handle) {
                return [
                    'success' => false,
                    'error' => 'Cannot open CSV file'
                ];
            }

            $header = fgetcsv($handle);
            if (!$header) {
                fclose($handle);
                return [
                    'success' => false,
                    'error' => 'CSV file is empty or invalid'
                ];
            }

            // Normalize header
            $header = array_map(fn($col) => strtolower(trim($col)), $header);

            $requiredColumns = ['product_name', 'variant_id'];
            $missingColumns = array_diff($requiredColumns, $header);
            if (!empty($missingColumns)) {
                fclose($handle);
                return [
                    'success' => false,
                    'error' => 'Missing required columns: ' . implode(', ', $missingColumns)
                ];
            }

            $rows = [];
            $lineNumber = 1;
            $errors = [];

            while (($row = fgetcsv($handle)) !== false) {
                $lineNumber++;
                if (empty(array_filter($row))) continue;

                $data = [];
                foreach ($header as $index => $column) {
                    $data[$column] = isset($row[$index]) ? trim($row[$index]) : '';
                }

                $rowErrors = $this->validateImportRow($data, $lineNumber);
                if (!empty($rowErrors)) {
                    $errors = array_merge($errors, $rowErrors);
                    continue;
                }

                $rows[] = $data;
            }

            fclose($handle);

            return [
                'success' => true,
                'data' => [
                    'rows' => $rows,
                    'errors' => $errors,
                    'total_rows' => count($rows),
                    'header' => $header
                ]
            ];
        } catch (\Exception $e) {
            Log::error('Failed to parse CSV', ['error' => $e->getMessage()]);
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Validate a single import row
     */
    private function validateImportRow(array $data, int $lineNumber): array
    {
        $errors = [];

        if (empty($data['product_name'])) {
            $errors[] = "Line {$lineNumber}: product_name is required";
        }

        if (empty($data['variant_id'])) {
            $errors[] = "Line {$lineNumber}: variant_id is required";
        }

        $numericFields = ['stock', 'weight', 'length', 'width', 'height', 'supplier_price'];
        foreach ($numericFields as $field) {
            if (isset($data[$field]) && $data[$field] !== '' && !is_numeric($data[$field])) {
                $errors[] = "Line {$lineNumber}: {$field} must be numeric";
            }
        }

        return $errors;
    }

    /**
     * Import products from parsed CSV data
     */
    public function importProducts(array $rows): array
    {
        try {
            $results = [
                'products_created' => 0,
                'products_updated' => 0,
                'variants_created' => 0,
                'prices_created' => 0,
            ];

            $groupedProducts = $this->groupRowsByProduct($rows);

            DB::beginTransaction();

            foreach ($groupedProducts as $productData) {
                $result = $this->processImportProduct($productData);
                $results['products_created'] += $result['product_created'] ? 1 : 0;
                $results['products_updated'] += $result['product_updated'] ? 1 : 0;
                $results['variants_created'] += $result['variants_created'];
                $results['prices_created'] += $result['prices_created'];
            }

            DB::commit();
            Log::info('Product import completed', $results);

            return [
                'success' => true,
                'data' => $results
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Product import failed', ['error' => $e->getMessage()]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Group CSV rows by product
     */
    private function groupRowsByProduct(array $rows): array
    {
        $grouped = [];

        foreach ($rows as $row) {
            $productKey = md5(
                ($row['product_name'] ?? '') . '|' .
                    ($row['product_style'] ?? '') . '|' .
                    ($row['product_brand'] ?? '')
            );

            if (!isset($grouped[$productKey])) {
                $grouped[$productKey] = [
                    'product_info' => [
                        'name' => $row['product_name'],
                        'style' => $row['product_style'] ?? null,
                        'brand' => $row['product_brand'] ?? null,
                        'category_type' => $row['category_type'] ?? 'embroidery',
                        'warehouse_name' => $row['warehouse_name'] ?? null,
                        'mockup' => $row['mockup_url'] ?? null,
                        'template_url' => $row['template_url'] ?? null,
                        'status' => isset($row['product_status']) ? (bool)$row['product_status'] : true,
                    ],
                    'variants' => []
                ];
            }

            $grouped[$productKey]['variants'][] = $row;
        }

        return $grouped;
    }

    /**
     * Process a single product with its variants for import
     */
    private function processImportProduct(array $productData): array
    {
        $result = [
            'product_created' => false,
            'product_updated' => false,
            'variants_created' => 0,
            'prices_created' => 0,
        ];

        $product = Product::where('name', $productData['product_info']['name'])
            ->where('style', $productData['product_info']['style'])
            ->where('brand', $productData['product_info']['brand'])
            ->first();

        if (!$product) {
            $product = Product::create($productData['product_info']);
            $result['product_created'] = true;
        } else {
            $product->update($productData['product_info']);
            $result['product_updated'] = true;
        }

        foreach ($productData['variants'] as $variantRow) {
            $variantResult = $this->createImportVariant($product, $variantRow);
            $result['variants_created'] += $variantResult['variant_created'] ? 1 : 0;
            $result['prices_created'] += $variantResult['prices_created'];
        }

        return $result;
    }

    /**
     * Create a variant from CSV row
     */
    private function createImportVariant(Product $product, array $row): array
    {
        $result = ['variant_created' => false, 'prices_created' => 0];

        $variant = ProductVariant::where('variant_id', $row['variant_id'])->first();

        $stockFromCsv = isset($row['stock']) && $row['stock'] !== '' ? (int)$row['stock'] : 0;

        $variantData = [
            'product_id' => $product->id,
            'variant_id' => $row['variant_id'],
            'sku' => $row['sku'] ?? null,
            'style' => $row['variant_style'] ?? $row['product_style'] ?? $product->style,
            'color' => (isset($row['color']) && $row['color'] !== '') ? $row['color'] : null,
            'size' => $row['size'] ?? null,
            'active' => isset($row['active']) ? (bool)$row['active'] : true,
            'weight' => isset($row['weight']) && $row['weight'] !== '' ? (int)$row['weight'] : null,
            'length' => isset($row['length']) && $row['length'] !== '' ? (int)$row['length'] : null,
            'width' => isset($row['width']) && $row['width'] !== '' ? (int)$row['width'] : null,
            'height' => isset($row['height']) && $row['height'] !== '' ? (int)$row['height'] : null,
            'supplier_price' => isset($row['supplier_price']) && $row['supplier_price'] !== '' ? (float)$row['supplier_price'] : null,
        ];

        if (!$variant) {
            // For newly created variants, initialize stock from CSV.
            $variantData['stock'] = $stockFromCsv;
            $variant = ProductVariant::create($variantData);
            $result['variant_created'] = true;
        } else {
            // For existing variants, keep current stock unchanged during CSV import.
            $variant->update($variantData);
        }

        $result['prices_created'] = $this->createImportPriceVariants($variant, $row);

        return $result;
    }

    /**
     * Create price variants from CSV row
     */
    private function createImportPriceVariants(ProductVariant $variant, array $row): int
    {
        $pricesCreated = 0;
        $tiers = array_column($this->getTiers(), 'id');
        $priceTypes = $this->getPriceTypes();

        foreach ($tiers as $tierId) {
            foreach ($priceTypes as $priceType) {
                $columnName = "tier_{$tierId}_{$priceType}";

                if (isset($row[$columnName]) && $row[$columnName] !== '' && is_numeric($row[$columnName])) {
                    ProductPriceVariant::updateOrCreate(
                        [
                            'product_variant_id' => $variant->variant_id,
                            'tier_id' => $tierId,
                            'type' => $priceType,
                        ],
                        [
                            'price' => (float)$row[$columnName],
                        ]
                    );
                    $pricesCreated++;
                }
            }
        }

        return $pricesCreated;
    }

    /**
     * Preview import without actually importing
     */
    public function previewImport(array $rows): array
    {
        try {
            $grouped = $this->groupRowsByProduct($rows);

            $preview = [
                'total_products' => count($grouped),
                'total_variants' => count($rows),
                'new_products' => 0,
                'existing_products' => 0,
                'products' => []
            ];

            foreach ($grouped as $productData) {
                $existingProduct = Product::where('name', $productData['product_info']['name'])
                    ->where('style', $productData['product_info']['style'])
                    ->where('brand', $productData['product_info']['brand'])
                    ->first();

                $isNew = !$existingProduct;
                $preview[$isNew ? 'new_products' : 'existing_products']++;

                $preview['products'][] = [
                    'name' => $productData['product_info']['name'],
                    'style' => $productData['product_info']['style'],
                    'brand' => $productData['product_info']['brand'],
                    'is_new' => $isNew,
                    'variants_count' => count($productData['variants']),
                    'variants' => array_map(fn($v) => [
                        'variant_id' => $v['variant_id'],
                        'color' => $v['color'] ?? null,
                        'size' => $v['size'] ?? null,
                        'stock' => $v['stock'] ?? 0,
                    ], array_slice($productData['variants'], 0, 5))
                ];
            }

            return [
                'success' => true,
                'data' => $preview
            ];
        } catch (\Exception $e) {
            Log::error('Failed to preview import', ['error' => $e->getMessage()]);
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Generate CSV template content
     */
    public function generateCSVTemplate(): string
    {
        $tierIds = array_column($this->getTiers(), 'id');
        $headers = [
            'product_name',
            'product_style',
            'product_brand',
            'category_type',
            'warehouse_name',
            'mockup_url',
            'template_url',
            'product_status',
            'variant_id',
            'variant_style',
            'sku',
            'color',
            'size',
            'stock',
            'active',
            'weight',
            'length',
            'width',
            'height',
            'supplier_price',
        ];

        // Add price columns for all tiers and all types
        $priceTypes = $this->getPriceTypes();
        foreach ($tierIds as $tierId) {
            foreach ($priceTypes as $priceType) {
                $headers[] = "tier_{$tierId}_{$priceType}";
            }
        }

        // Sample data rows — wood laser engraving products
        // Prices per tier: base_cost, seller_shipping, priority_shipping, shipping_cost
        $tierPrices = [
            // Tier 0 (Silver)
            ['5.00', '4.99', '7.99', '5.99'],
            // Tier 1 (Gold)
            ['4.80', '4.50', '7.50', '5.50'],
            // Tier 2 (Platinum)
            ['4.60', '4.00', '7.00', '5.00'],
            // Tier 3 (Diamond)
            ['4.40', '3.50', '6.50', '4.50'],
        ];

        $baseRow = static fn(string $variantId, string $sku, string $size, string $stock) => [
            'Wood Sign Rectangle',     // product_name
            'Rectangle',               // product_style
            'Wecat Wood',              // product_brand
            'wood',                    // category_type
            'Main Warehouse',          // warehouse_name
            '',                        // mockup_url
            '',                        // template_url
            '1',                       // product_status
            $variantId,                // variant_id
            'Rectangle',               // variant_style
            $sku,                      // sku
            '',                        // color (always empty for wood)
            $size,                     // size
            $stock,                    // stock
            '1',                       // active
            '200',                     // weight (grams)
            '15',                      // length (cm)
            '10',                      // width (cm)
            '1',                       // height (cm)
            '3.50',                    // supplier_price
        ];

        $sampleRows = [
            array_merge($baseRow('WOOD-RECT-4X6', 'WD-R-4X6', '4x6', '100'), ...array_slice($tierPrices, 0, count($tierIds))),
            array_merge($baseRow('WOOD-RECT-6X8', 'WD-R-6X8', '6x8', '80'),  ...array_slice($tierPrices, 0, count($tierIds))),
            array_merge($baseRow('WOOD-RECT-8X10', 'WD-R-8X10', '8x10', '50'), ...array_slice($tierPrices, 0, count($tierIds))),
        ];

        $output = fopen('php://temp', 'r+');
        fputcsv($output, $headers);
        foreach ($sampleRows as $row) {
            fputcsv($output, $row);
        }
        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);

        return $csv;
    }

    /**
     * Export current products and variant pricing in the same CSV format as import
     */
    public function generateCurrentDataExportCSV(): string
    {
        $tierIds = array_column($this->getTiers(), 'id');
        $priceTypes = $this->getPriceTypes();

        $headers = [
            'product_name',
            'product_style',
            'product_brand',
            'category_type',
            'warehouse_name',
            'mockup_url',
            'template_url',
            'product_status',
            'variant_id',
            'variant_style',
            'sku',
            'color',
            'size',
            'stock',
            'active',
            'weight',
            'length',
            'width',
            'height',
            'supplier_price',
        ];

        foreach ($tierIds as $tierId) {
            foreach ($priceTypes as $priceType) {
                $headers[] = "tier_{$tierId}_{$priceType}";
            }
        }

        $variants = ProductVariant::query()
            ->with(['product', 'priceVariants'])
            ->orderBy('product_id')
            ->orderBy('variant_id')
            ->get();

        $output = fopen('php://temp', 'r+');
        fputcsv($output, $headers);

        foreach ($variants as $variant) {
            $product = $variant->product;
            if (!$product) {
                continue;
            }

            $row = [
                $product->name,
                $product->style,
                $product->brand,
                $product->category_type,
                $product->warehouse_name,
                $product->mockup,
                $product->template_url,
                $product->status ? '1' : '0',
                $variant->variant_id,
                $variant->style,
                $variant->sku,
                $variant->color,
                $variant->size,
                $variant->stock,
                $variant->active ? '1' : '0',
                $variant->weight,
                $variant->length,
                $variant->width,
                $variant->height,
                $variant->supplier_price,
            ];

            $priceMap = $variant->priceVariants
                ->mapWithKeys(fn (ProductPriceVariant $price) => [
                    "{$price->tier_id}_{$price->type}" => $price->price,
                ]);

            foreach ($tierIds as $tierId) {
                foreach ($priceTypes as $priceType) {
                    $row[] = $priceMap->get("{$tierId}_{$priceType}", '');
                }
            }

            fputcsv($output, $row);
        }

        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);

        return $csv;
    }
}
