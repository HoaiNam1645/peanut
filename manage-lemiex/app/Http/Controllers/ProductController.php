<?php

namespace App\Http\Controllers;

use App\Constants\HttpCode;
use App\Constants\ProductConstants;
use App\Constants\ResponseMessage;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\StockAuditLog;
use App\Services\ProductService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class ProductController extends Controller
{
    protected $productService;

    public function __construct(ProductService $productService)
    {
        $this->productService = $productService;
    }

    /**
     * Create a new product with variants and prices
     */
    public function store(Request $request): JsonResponse
    {
        try {
            // Validation rules
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
                'style' => 'nullable|string|max:255',
                'status' => 'boolean',
                'category_type' => 'nullable|in:embroidery,print,wood',
                'mockup' => 'nullable|url|max:500',
                'template_url' => 'nullable|url|max:1000',
                'brand' => 'nullable|string|max:255',
                'warehouse_name' => 'nullable|string|max:255',

                'variants' => 'nullable|array',
                'variants.*.variant_id' => 'required_with:variants|string|max:255|unique:product_variants,variant_id',
                'variants.*.sku' => 'nullable|string|max:255',
                'variants.*.style' => 'nullable|string|max:255',
                'variants.*.color' => 'nullable|string|max:100',
                'variants.*.size' => 'nullable|string|max:50',
                'variants.*.stock' => 'nullable|integer|min:0',
                'variants.*.active' => 'nullable|boolean',
                'variants.*.weight' => 'nullable|integer|min:0',
                'variants.*.length' => 'nullable|integer|min:0',
                'variants.*.width' => 'nullable|integer|min:0',
                'variants.*.height' => 'nullable|integer|min:0',
                'variants.*.supplier_price' => 'nullable|numeric|min:0',
                'variants.*.chest_inch' => 'nullable|numeric|min:0',
                'variants.*.chest_cm' => 'nullable|numeric|min:0',
                'variants.*.length_inch' => 'nullable|numeric|min:0',
                'variants.*.length_cm' => 'nullable|numeric|min:0',
                'variants.*.neck_inch' => 'nullable|numeric|min:0',
                'variants.*.neck_cm' => 'nullable|numeric|min:0',

                'variants.*.prices' => 'nullable|array',
                'variants.*.prices.*.tier_id' => 'required|integer|exists:tiers,tier_id',
                'variants.*.prices.*.type' => 'required|in:base_cost,seller_shipping,priority_shipping,shipping_cost',
                'variants.*.prices.*.price' => 'required|numeric|min:0',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'code' => HttpCode::BAD_REQUEST,
                    'status' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], HttpCode::BAD_REQUEST);
            }

            // Create product using service
            $product = $this->productService->createProduct($request->all());

            return response()->json([
                'code' => HttpCode::SUCCESS,
                'status' => true,
                'message' => 'Product created successfully',
                'data' => $product
            ], HttpCode::SUCCESS);
        } catch (\Exception $e) {
            return response()->json([
                'code' => HttpCode::SERVER_ERROR,
                'status' => false,
                'message' => 'Failed to create product',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], HttpCode::SERVER_ERROR);
        }
    }

    /**
     * Get filter options for product creation
     */
    public function getFilterOptions(): JsonResponse
    {
        try {
            $options = $this->productService->getFilterOptions();

            return response()->json([
                'code' => HttpCode::SUCCESS,
                'status' => true,
                'message' => 'Filter options retrieved successfully',
                'data' => $options
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
     * Get tiers and price types for product creation
     */
    public function getMetadata(): JsonResponse
    {
        try {
            $data = [
                'tiers' => $this->productService->getTiers(),
                'price_types' => $this->productService->getPriceTypes(),
            ];

            return response()->json([
                'code' => HttpCode::SUCCESS,
                'status' => true,
                'message' => 'Metadata retrieved successfully',
                'data' => $data
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'code' => HttpCode::SERVER_ERROR,
                'status' => false,
                'message' => 'Failed to retrieve metadata',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], HttpCode::SERVER_ERROR);
        }
    }

    /**
     * Update product information only
     */
    public function updateProduct(Request $request, $id): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
                'style' => 'nullable|string|max:255',
                'status' => 'boolean',
                'category_type' => 'nullable|in:embroidery,print,wood',
                'mockup' => 'nullable|url|max:500',
                'template_url' => 'nullable|url|max:1000',
                'brand' => 'nullable|string|max:255',
                'warehouse_name' => 'nullable|string|max:255',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'code' => HttpCode::BAD_REQUEST,
                    'status' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], HttpCode::BAD_REQUEST);
            }

            $product = Product::find($id);

            if (!$product) {
                return response()->json([
                    'code' => HttpCode::NOT_FOUND,
                    'status' => false,
                    'message' => 'Product not found'
                ], HttpCode::NOT_FOUND);
            }

            $product->update($request->all());

            return response()->json([
                'code' => HttpCode::SUCCESS,
                'status' => true,
                'message' => 'Product updated successfully',
                'data' => $product
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'code' => HttpCode::SERVER_ERROR,
                'status' => false,
                'message' => 'Failed to update product',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], HttpCode::SERVER_ERROR);
        }
    }

    /**
     * Update variant information
     */
    public function updateVariant(Request $request, $id): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'variant_id' => 'required|string|max:255|unique:product_variants,variant_id,' . $id,
                'sku' => 'nullable|string|max:255',
                'style' => 'nullable|string|max:255',
                'color' => 'nullable|string|max:100',
                'size' => 'nullable|string|max:50',
                'stock' => 'nullable|integer|min:0',
                'active' => 'nullable|boolean',
                'weight' => 'nullable|integer|min:0',
                'length' => 'nullable|integer|min:0',
                'width' => 'nullable|integer|min:0',
                'height' => 'nullable|integer|min:0',
                'supplier_price' => 'nullable|numeric|min:0',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'code' => HttpCode::BAD_REQUEST,
                    'status' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], HttpCode::BAD_REQUEST);
            }

            $variant = ProductVariant::find($id);

            if (!$variant) {
                return response()->json([
                    'code' => HttpCode::NOT_FOUND,
                    'status' => false,
                    'message' => 'Variant not found'
                ], HttpCode::NOT_FOUND);
            }

            // Store old values for audit log
            $oldValues = $variant->toArray();
            $oldStock = $variant->stock;

            $variant->update($request->all());

            // Log changes to audit log
            $changes = [];
            $fieldsToTrack = ['sku', 'style', 'color', 'size', 'stock', 'active', 'supplier_price'];

            foreach ($fieldsToTrack as $field) {
                if ($request->has($field) && $oldValues[$field] != $variant->$field) {
                    $changes[$field] = [
                        'old' => $oldValues[$field],
                        'new' => $variant->$field
                    ];
                }
            }

            // If stock changed, create audit log
            if (isset($changes['stock'])) {
                $newStock = $variant->stock;
                $action = $newStock > $oldStock ? 'increase' : ($newStock < $oldStock ? 'decrease' : 'adjust');

                StockAuditLog::create([
                    'product_variant_id' => $variant->variant_id,
                    'user_id' => Auth::id() ?? 1,
                    'action' => $action,
                    'before_quantity' => $oldStock,
                    'after_quantity' => $newStock,
                    'reason' => 'Manual update via Variant Detail page',
                    'metadata' => [
                        'source' => 'variant_detail_page',
                        'all_changes' => $changes,
                        'ip' => $request->ip()
                    ]
                ]);
            } elseif (!empty($changes)) {
                // Log other field changes (SKU, style, active, etc.)
                $action = 'update_' . array_key_first($changes);
                if (isset($changes['active'])) {
                    $action = $variant->active ? 'activate' : 'deactivate';
                } elseif (isset($changes['sku'])) {
                    $action = 'update_sku';
                } elseif (isset($changes['style'])) {
                    $action = 'update_style';
                }

                StockAuditLog::create([
                    'product_variant_id' => $variant->variant_id,
                    'user_id' => Auth::id() ?? 1,
                    'action' => $action,
                    'before_quantity' => $oldStock,
                    'after_quantity' => $variant->stock,
                    'reason' => 'Field update via Variant Detail page',
                    'metadata' => [
                        'source' => 'variant_detail_page',
                        'changes' => $changes,
                        'ip' => $request->ip()
                    ]
                ]);
            }

            return response()->json([
                'code' => HttpCode::SUCCESS,
                'status' => true,
                'message' => 'Variant updated successfully',
                'data' => $variant
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'code' => HttpCode::SERVER_ERROR,
                'status' => false,
                'message' => 'Failed to update variant',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], HttpCode::SERVER_ERROR);
        }
    }

    /**
     * Update variant tier pricing
     */
    public function updateVariantPricing(Request $request, $variantId): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'prices' => 'required|array',
                'prices.*.tier_id' => 'required|integer|exists:tiers,tier_id',
                'prices.*.type' => 'required|in:base_cost,seller_shipping,priority_shipping,shipping_cost',
                'prices.*.price' => 'required|numeric|min:0',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'code' => HttpCode::BAD_REQUEST,
                    'status' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], HttpCode::BAD_REQUEST);
            }

            // Find variant by variant_id (string)
            $variant = ProductVariant::where('variant_id', $variantId)->first();

            if (!$variant) {
                return response()->json([
                    'code' => HttpCode::NOT_FOUND,
                    'status' => false,
                    'message' => 'Variant not found'
                ], HttpCode::NOT_FOUND);
            }

            // Update prices using service
            $result = $this->productService->updateVariantPricing($variantId, $request->prices);

            if (!$result['success']) {
                return response()->json([
                    'code' => HttpCode::SERVER_ERROR,
                    'status' => false,
                    'message' => $result['message'] ?? 'Failed to update pricing'
                ], HttpCode::SERVER_ERROR);
            }

            return response()->json([
                'code' => HttpCode::SUCCESS,
                'status' => true,
                'message' => 'Tier pricing updated successfully',
                'data' => $result['data'] ?? null
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'code' => HttpCode::SERVER_ERROR,
                'status' => false,
                'message' => 'Failed to update tier pricing',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], HttpCode::SERVER_ERROR);
        }
    }

    /**
     * Get all products (defaults to embroidery category for backward compatibility)
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = Product::query();

            // Filter by status
            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            // Search by name
            if ($request->has('search')) {
                $query->where('name', 'like', '%' . $request->search . '%');
            }

            $category = $request->input('category', 'wood');
            $query->where('category_type', $category);

            $products = $query->orderBy('name')->get();

            return response()->json([
                'code' => HttpCode::SUCCESS,
                'status' => true,
                'message' => ResponseMessage::PRODUCTS_RETRIEVED,
                'data' => $products
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'code' => HttpCode::SERVER_ERROR,
                'status' => false,
                'message' => ResponseMessage::PRODUCTS_RETRIEVED_FAILED,
                'error' => config('app.debug') ? $e->getMessage() : null
            ], HttpCode::SERVER_ERROR);
        }
    }

    /**
     * Get print products only
     */
    public function getPrintProducts(Request $request): JsonResponse
    {
        try {
            $query = Product::query();

            // Only print products
            $query->where('category_type', 'print');

            // Filter by status
            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            // Search by name
            if ($request->has('search')) {
                $query->where('name', 'like', '%' . $request->search . '%');
            }

            $products = $query->orderBy('name')->get();

            return response()->json([
                'code' => HttpCode::SUCCESS,
                'status' => true,
                'message' => ResponseMessage::PRODUCTS_RETRIEVED,
                'data' => $products
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'code' => HttpCode::SERVER_ERROR,
                'status' => false,
                'message' => ResponseMessage::PRODUCTS_RETRIEVED_FAILED,
                'error' => config('app.debug') ? $e->getMessage() : null
            ], HttpCode::SERVER_ERROR);
        }
    }

    /**
     * Get product variants with search and filters (original API)
     */
    public function getVariants(Request $request): JsonResponse
    {
        try {
            $query = ProductVariant::with(['product', 'priceVariants']);

            // Filter by variant_id (partial match)
            if ($request->filled('variant_id')) {
                $query->where('variant_id', 'like', '%' . $request->variant_id . '%');
            }

            // Filter by sku (partial match)
            if ($request->filled('sku')) {
                $query->where('sku', 'like', '%' . $request->sku . '%');
            }

            // Filter by style (partial match)
            if ($request->filled('style')) {
                $query->where('style', 'like', '%' . $request->style . '%');
            }

            // Filter by color (partial match) - renamed to color_search to avoid conflict
            if ($request->filled('color_search')) {
                $query->where('color', 'like', '%' . $request->color_search . '%');
            }

            // Filter by product name (partial match)
            if ($request->filled('product_name')) {
                $productName = $request->product_name;
                $query->whereHas('product', function ($productQuery) use ($productName) {
                    $productQuery->where('name', 'like', '%' . $productName . '%');
                });
            }

            // Filter by product_id (exact match)
            if ($request->has('product_id')) {
                $query->where('product_id', $request->product_id);
            }

            // Filter by color (exact match)
            if ($request->has('color') && !$request->filled('color_search')) {
                $query->where('color', $request->color);
            }

            // Filter by size (exact match)
            if ($request->has('size')) {
                $query->where('size', $request->size);
            }

            // Filter by active status
            if ($request->has('active')) {
                // Convert string "true"/"false" to boolean
                $active = filter_var($request->active, FILTER_VALIDATE_BOOLEAN);
                $query->where('active', $active);
            } else {
                // Default: only active variants
                $query->where('active', ProductConstants::DEFAULT_ACTIVE_STATUS);
            }

            // Filter by stock availability
            if ($request->has('in_stock') && $request->in_stock) {
                $query->where('stock', '>', 0);
            }

            // Pagination
            $perPage = $request->input('per_page', ProductConstants::DEFAULT_PER_PAGE);
            $variants = $query->orderBy('product_id')
                ->orderBy('color')
                ->orderBy('size')
                ->paginate($perPage);

            return response()->json([
                'code' => HttpCode::SUCCESS,
                'status' => true,
                'message' => ResponseMessage::PRODUCT_VARIANTS_RETRIEVED,
                'data' => $variants
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'code' => HttpCode::SERVER_ERROR,
                'status' => false,
                'message' => ResponseMessage::PRODUCT_VARIANTS_RETRIEVED_FAILED,
                'error' => config('app.debug') ? $e->getMessage() : null
            ], HttpCode::SERVER_ERROR);
        }
    }

    /**
     * Get products with variants grouped (for management page)
     */
    public function getProductsWithVariants(Request $request): JsonResponse
    {
        try {
            $query = Product::with(['variants' => function ($q) {
                $q->select('id', 'product_id', 'variant_id', 'sku', 'style', 'color', 'size', 'stock', 'active', 'supplier_price', 'weight', 'length', 'width', 'height');
            }]);

            // Search by product name, brand, or style
            if ($request->filled('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', '%' . $search . '%')
                        ->orWhere('brand', 'like', '%' . $search . '%')
                        ->orWhere('style', 'like', '%' . $search . '%');
                });
            }

            // Filter by style
            if ($request->filled('style')) {
                $query->where('style', 'like', '%' . $request->style . '%');
            }

            // Filter by brand
            if ($request->filled('brand')) {
                $query->where('brand', 'like', '%' . $request->brand . '%');
            }

            // Filter by status
            if ($request->filled('status')) {
                $active = $request->status === '1' || $request->status === 'true' || $request->status === true;
                $query->where('status', $active);
            }

            // Filter by category_type (embroidery/print)
            if ($request->filled('category')) {
                $query->where('category_type', $request->category);
            }

            // Only get products that have variants
            $query->has('variants');

            // Sorting
            $sortBy = $request->input('sort_by', 'created_at');
            $sortOrder = $request->input('sort_order', 'desc');

            // Validate sort fields
            $allowedSortFields = ['created_at', 'name', 'brand', 'style'];
            if (!in_array($sortBy, $allowedSortFields)) {
                $sortBy = 'created_at';
            }

            $sortOrder = strtolower($sortOrder) === 'asc' ? 'asc' : 'desc';

            // Pagination
            $perPage = $request->input('per_page', ProductConstants::DEFAULT_PER_PAGE);
            $products = $query->orderBy($sortBy, $sortOrder)
                ->paginate($perPage);

            // Transform data to include aggregated variant info
            $products->getCollection()->transform(function ($product) {
                $variants = $product->variants;

                // Get unique colors and sizes
                $colors = $variants->pluck('color')->unique()->values();
                $sizes = $variants->pluck('size')->unique()->values();

                // Calculate total stock
                $totalStock = $variants->sum('stock');
                $activeVariants = $variants->where('active', true)->count();
                $totalVariants = $variants->count();

                // Get price range
                $prices = $variants->pluck('supplier_price')->filter();
                $minPrice = $prices->min();
                $maxPrice = $prices->max();

                return [
                    'id' => $product->id,
                    'name' => $product->name,
                    'style' => $product->style,
                    'brand' => $product->brand,
                    'mockup' => $product->mockup,
                    'template_url' => $product->template_url,
                    'warehouse_name' => $product->warehouse_name,
                    'status' => $product->status,
                    'created_at' => $product->created_at,
                    'colors' => $colors,
                    'sizes' => $sizes,
                    'total_stock' => $totalStock,
                    'active_variants' => $activeVariants,
                    'total_variants' => $totalVariants,
                    'price_range' => [
                        'min' => $minPrice,
                        'max' => $maxPrice
                    ],
                    'variants' => $variants
                ];
            });

            return response()->json([
                'code' => HttpCode::SUCCESS,
                'status' => true,
                'message' => ResponseMessage::PRODUCT_VARIANTS_RETRIEVED,
                'data' => $products
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'code' => HttpCode::SERVER_ERROR,
                'status' => false,
                'message' => ResponseMessage::PRODUCT_VARIANTS_RETRIEVED_FAILED,
                'error' => config('app.debug') ? $e->getMessage() : null
            ], HttpCode::SERVER_ERROR);
        }
    }

    /**
     * Get single variant by variant_id
     */
    public function getVariantById($variantId): JsonResponse
    {
        try {
            $variant = ProductVariant::with(['product', 'priceVariants'])
                ->where('variant_id', $variantId)
                ->first();

            if (!$variant) {
                return response()->json([
                    'code' => HttpCode::NOT_FOUND,
                    'status' => false,
                    'message' => ResponseMessage::PRODUCT_VARIANT_NOT_FOUND
                ], HttpCode::NOT_FOUND);
            }

            // Format response with useful info
            $data = [
                'variant_id' => $variant->variant_id,
                'sku' => $variant->sku,
                'product_name' => $variant->product->name ?? 'Unknown',
                'full_name' => implode(' - ', array_filter([
                    $variant->product->name ?? '',
                    $variant->color ?: null,
                    $variant->size ?: null,
                ])),
                'style' => $variant->style,
                'color' => $variant->color,
                'size' => $variant->size,
                'stock' => $variant->stock,
                'active' => $variant->active,
                'supplier_price' => $variant->supplier_price,
                'pricing' => $variant->priceVariants->groupBy('tier_id')->map(function ($prices) {
                    return $prices->pluck('price', 'type');
                }),
            ];

            return response()->json([
                'code' => HttpCode::SUCCESS,
                'status' => true,
                'message' => ResponseMessage::PRODUCT_VARIANT_RETRIEVED,
                'data' => $data
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'code' => HttpCode::SERVER_ERROR,
                'status' => false,
                'message' => ResponseMessage::PRODUCT_VARIANT_RETRIEVED_FAILED,
                'error' => config('app.debug') ? $e->getMessage() : null
            ], HttpCode::SERVER_ERROR);
        }
    }

    /**
     * Get available styles for wood products
     */
    public function getStyles(Request $request): JsonResponse
    {
        try {
            $query = ProductVariant::select('product_variants.style')
                ->distinct()
                ->join('products', 'products.id', '=', 'product_variants.product_id')
                ->whereNotNull('product_variants.style')
                ->where('product_variants.style', '!=', '')
                ->where('product_variants.active', ProductConstants::DEFAULT_ACTIVE_STATUS)
                ->where('products.category_type', 'wood');

            if ($request->has('product_id')) {
                $query->where('product_variants.product_id', $request->product_id);
            }

            $styles = $query->orderBy('product_variants.style')->pluck('style');

            return response()->json([
                'code' => HttpCode::SUCCESS,
                'status' => true,
                'data' => $styles
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'code' => HttpCode::SERVER_ERROR,
                'status' => false,
                'message' => 'Failed to retrieve styles',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], HttpCode::SERVER_ERROR);
        }
    }

    /**
     * Get available colors for wood products
     */
    public function getColors(Request $request): JsonResponse
    {
        try {
            $query = ProductVariant::select('product_variants.color')
                ->distinct()
                ->join('products', 'products.id', '=', 'product_variants.product_id')
                ->whereNotNull('product_variants.color')
                ->where('product_variants.active', ProductConstants::DEFAULT_ACTIVE_STATUS)
                ->where('products.category_type', 'wood');

            if ($request->has('product_id')) {
                $query->where('product_variants.product_id', $request->product_id);
            }

            if ($request->has('style')) {
                $query->where('product_variants.style', $request->style);
            }

            $colors = $query->orderBy('product_variants.color')->pluck('color');

            return response()->json([
                'code' => HttpCode::SUCCESS,
                'status' => true,
                'data' => $colors
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'code' => HttpCode::SERVER_ERROR,
                'status' => false,
                'message' => ResponseMessage::COLORS_RETRIEVED_FAILED,
                'error' => config('app.debug') ? $e->getMessage() : null
            ], HttpCode::SERVER_ERROR);
        }
    }

    /**
     * Get available sizes for wood products
     */
    public function getSizes(Request $request): JsonResponse
    {
        try {
            $query = ProductVariant::select('product_variants.size')
                ->distinct()
                ->join('products', 'products.id', '=', 'product_variants.product_id')
                ->whereNotNull('product_variants.size')
                ->where('product_variants.active', ProductConstants::DEFAULT_ACTIVE_STATUS)
                ->where('products.category_type', 'wood');

            if ($request->has('product_id')) {
                $query->where('product_variants.product_id', $request->product_id);
            }

            if ($request->has('style')) {
                $query->where('product_variants.style', $request->style);
            }

            if ($request->has('color')) {
                $query->where('product_variants.color', $request->color);
            }

            $sizeOrder = implode("', '", ProductConstants::SIZE_ORDER);
            $sizes = $query->orderByRaw("FIELD(product_variants.size, '{$sizeOrder}')")
                ->pluck('size');

            return response()->json([
                'code' => HttpCode::SUCCESS,
                'status' => true,
                'data' => $sizes
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'code' => HttpCode::SERVER_ERROR,
                'status' => false,
                'message' => ResponseMessage::SIZES_RETRIEVED_FAILED,
                'error' => config('app.debug') ? $e->getMessage() : null
            ], HttpCode::SERVER_ERROR);
        }
    }

    /**
     * Get distinct brands for wood products (cascade filter step)
     */
    public function getBrands(Request $request): JsonResponse
    {
        try {
            $query = ProductVariant::select('products.brand')
                ->distinct()
                ->join('products', 'products.id', '=', 'product_variants.product_id')
                ->whereNotNull('products.brand')
                ->where('products.brand', '!=', '')
                ->where('product_variants.active', ProductConstants::DEFAULT_ACTIVE_STATUS)
                ->where('products.category_type', 'wood');

            if ($request->filled('style')) {
                $query->where('product_variants.style', $request->style);
            }
            if ($request->filled('size')) {
                $query->where('product_variants.size', $request->size);
            }
            if ($request->filled('product_id')) {
                $query->where('product_variants.product_id', $request->product_id);
            }

            $brands = $query->orderBy('products.brand')->pluck('brand');

            return response()->json([
                'code' => HttpCode::SUCCESS,
                'status' => true,
                'data' => $brands
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'code' => HttpCode::SERVER_ERROR,
                'status' => false,
                'message' => 'Failed to retrieve brands',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], HttpCode::SERVER_ERROR);
        }
    }

    /**
     * Get simplified products list for wood (cascade filter step — bước "chọn Brand/Product")
     */
    public function getProductsSimple(Request $request): JsonResponse
    {
        try {
            $query = Product::select('products.id', 'products.name', 'products.brand', 'products.style', 'products.mockup', 'products.template_url')
                ->distinct()
                ->join('product_variants', 'product_variants.product_id', '=', 'products.id')
                ->where('product_variants.active', ProductConstants::DEFAULT_ACTIVE_STATUS)
                ->where('products.category_type', 'wood');

            if ($request->filled('style')) {
                $query->where('product_variants.style', $request->style);
            }
            if ($request->filled('size')) {
                $query->where('product_variants.size', $request->size);
            }
            if ($request->filled('brand')) {
                $query->where('products.brand', $request->brand);
            }

            $products = $query->orderBy('products.name')->get();

            return response()->json([
                'code' => HttpCode::SUCCESS,
                'status' => true,
                'data' => $products
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'code' => HttpCode::SERVER_ERROR,
                'status' => false,
                'message' => 'Failed to retrieve products',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], HttpCode::SERVER_ERROR);
        }
    }

    /**
     * Get product detail with all variants
     */
    public function getProductDetail($id): JsonResponse
    {
        try {
            $product = Product::with(['variants' => function ($query) {
                $query->orderBy('color')->orderBy('size');
            }, 'variants.priceVariants'])->find($id);

            if (!$product) {
                return response()->json([
                    'code' => HttpCode::NOT_FOUND,
                    'status' => false,
                    'message' => 'Product not found'
                ], HttpCode::NOT_FOUND);
            }

            // Calculate aggregated data
            $variants = $product->variants;

            $colors = $variants->pluck('color')->unique()->values();
            $sizes = $variants->pluck('size')->unique()->values();
            $totalStock = $variants->sum('stock');
            $activeVariants = $variants->where('active', true)->count();
            $totalVariants = $variants->count();

            $prices = $variants->pluck('supplier_price')->filter();
            $minPrice = $prices->min();
            $maxPrice = $prices->max();

            // Tier names mapping
            $tierNames = \App\Models\Tier::query()
                ->orderBy('tier_id')
                ->pluck('name', 'tier_id')
                ->toArray();

            // Transform variants with tier pricing
            $variantsData = $variants->map(function ($variant) use ($tierNames) {
                $variantArray = $variant->toArray();

                // Group pricing by tier
                $pricing = [];
                foreach ($tierNames as $tierId => $tierName) {
                    $tierPrices = $variant->priceVariants
                        ->where('tier_id', $tierId)
                        ->pluck('price', 'type')
                        ->toArray();

                    if (!empty($tierPrices)) {
                        $pricing[$tierName] = $tierPrices;
                    }
                }

                $variantArray['tier_pricing'] = $pricing;
                unset($variantArray['price_variants']); // Remove raw data

                return $variantArray;
            });

            $data = [
                'product' => [
                    'id' => $product->id,
                    'name' => $product->name,
                    'style' => $product->style,
                    'brand' => $product->brand,
                    'mockup' => $product->mockup,
                    'template_url' => $product->template_url,
                    'warehouse_name' => $product->warehouse_name,
                    'status' => $product->status,
                    'created_at' => $product->created_at,
                    'updated_at' => $product->updated_at,
                ],
                'summary' => [
                    'colors' => $colors,
                    'sizes' => $sizes,
                    'total_stock' => $totalStock,
                    'active_variants' => $activeVariants,
                    'total_variants' => $totalVariants,
                    'price_range' => [
                        'min' => $minPrice,
                        'max' => $maxPrice
                    ]
                ],
                'tiers' => $tierNames,
                'variants' => $variantsData
            ];

            return response()->json([
                'code' => HttpCode::SUCCESS,
                'status' => true,
                'message' => 'Product detail retrieved successfully',
                'data' => $data
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'code' => HttpCode::SERVER_ERROR,
                'status' => false,
                'message' => 'Failed to retrieve product detail',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], HttpCode::SERVER_ERROR);
        }
    }

    /**
     * Update variant stock (add or subtract)
     */
    public function updateStock(Request $request): JsonResponse
    {
        try {
            // Validate request
            $request->validate([
                'type' => 'required|in:add_stock,sub_stock',
                'name' => 'required|integer', // product_id
                'color' => 'required|string',
                'size' => 'required|string',
                'stock' => 'required|integer|min:1'
            ]);

            // Find the variant
            $variant = ProductVariant::where('product_id', $request->name)
                ->where('color', $request->color)
                ->where('size', $request->size)
                ->first();

            if (!$variant) {
                return response()->json([
                    'code' => HttpCode::NOT_FOUND,
                    'status' => false,
                    'message' => 'Variant not found with the specified product, color, and size'
                ], HttpCode::NOT_FOUND);
            }

            $oldStock = $variant->stock;
            $changeAmount = $request->stock;

            // Update stock based on type
            if ($request->type === 'add_stock') {
                $variant->stock += $changeAmount;
                $action = 'added';
                $auditAction = 'increase';
            } else { // sub_stock
                // Check if we have enough stock to subtract
                if ($variant->stock < $changeAmount) {
                    return response()->json([
                        'code' => HttpCode::BAD_REQUEST,
                        'status' => false,
                        'message' => "Insufficient stock. Current stock: {$variant->stock}, requested to subtract: {$changeAmount}"
                    ], HttpCode::BAD_REQUEST);
                }
                $variant->stock -= $changeAmount;
                $action = 'subtracted';
                $auditAction = 'decrease';
            }

            $variant->save();

            // Log stock change to audit log
            StockAuditLog::create([
                'product_variant_id' => $variant->variant_id,
                'user_id' => Auth::id() ?? 1,
                'action' => $auditAction,
                'before_quantity' => $oldStock,
                'after_quantity' => $variant->stock,
                'reason' => "Manual stock {$action} via Product Variants page",
                'metadata' => [
                    'change_amount' => $changeAmount,
                    'product_id' => $variant->product_id,
                    'color' => $variant->color,
                    'size' => $variant->size,
                    'source' => 'product_variants_page'
                ]
            ]);

            return response()->json([
                'code' => HttpCode::SUCCESS,
                'status' => true,
                'message' => "Stock {$action} successfully",
                'data' => [
                    'variant_id' => $variant->variant_id,
                    'product_id' => $variant->product_id,
                    'color' => $variant->color,
                    'size' => $variant->size,
                    'old_stock' => $oldStock,
                    'change_amount' => $changeAmount,
                    'new_stock' => $variant->stock,
                    'action' => $action
                ]
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'code' => HttpCode::BAD_REQUEST,
                'status' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], HttpCode::BAD_REQUEST);
        } catch (\Exception $e) {
            return response()->json([
                'code' => HttpCode::SERVER_ERROR,
                'status' => false,
                'message' => 'Failed to update stock',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], HttpCode::SERVER_ERROR);
        }
    }

    /**
     * Download CSV template for product import
     */
    public function downloadImportTemplate(): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $csv = $this->productService->generateCSVTemplate();

        return response()->streamDownload(function () use ($csv) {
            echo $csv;
        }, 'product_import_template.csv', [
            'Content-Type' => 'text/csv',
        ]);
    }

    /**
     * Export current products/variants/prices in import CSV format
     */
    public function exportImportData(): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $csv = $this->productService->generateCurrentDataExportCSV();

        return response()->streamDownload(function () use ($csv) {
            echo $csv;
        }, 'product_import_export.csv', [
            'Content-Type' => 'text/csv',
        ]);
    }

    /**
     * Preview CSV import (validate without importing)
     */
    public function previewImport(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'file' => 'required|file|mimes:csv,txt|max:10240',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'code' => HttpCode::BAD_REQUEST,
                'status' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], HttpCode::BAD_REQUEST);
        }

        $file = $request->file('file');
        $parsed = $this->productService->parseCSV($file->getPathname());

        if (!$parsed['success']) {
            return response()->json([
                'code' => HttpCode::BAD_REQUEST,
                'status' => false,
                'message' => $parsed['error'] ?? 'Failed to parse CSV'
            ], HttpCode::BAD_REQUEST);
        }

        $parsedData = $parsed['data'];

        if (!empty($parsedData['errors'])) {
            return response()->json([
                'code' => HttpCode::BAD_REQUEST,
                'status' => false,
                'message' => 'CSV validation errors found',
                'errors' => $parsedData['errors'],
                'total_rows' => $parsedData['total_rows']
            ], HttpCode::BAD_REQUEST);
        }

        $preview = $this->productService->previewImport($parsedData['rows']);

        if (!$preview['success']) {
            return response()->json([
                'code' => HttpCode::SERVER_ERROR,
                'status' => false,
                'message' => $preview['error'] ?? 'Failed to preview import'
            ], HttpCode::SERVER_ERROR);
        }

        return response()->json([
            'code' => HttpCode::SUCCESS,
            'status' => true,
            'message' => 'CSV preview generated successfully',
            'data' => $preview['data']
        ]);
    }

    /**
     * Import products from CSV file
     */
    public function importCSV(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'file' => 'required|file|mimes:csv,txt|max:10240',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'code' => HttpCode::BAD_REQUEST,
                'status' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], HttpCode::BAD_REQUEST);
        }

        $file = $request->file('file');
        $parsed = $this->productService->parseCSV($file->getPathname());

        if (!$parsed['success']) {
            return response()->json([
                'code' => HttpCode::BAD_REQUEST,
                'status' => false,
                'message' => $parsed['error'] ?? 'Failed to parse CSV'
            ], HttpCode::BAD_REQUEST);
        }

        $parsedData = $parsed['data'];

        if (!empty($parsedData['errors'])) {
            return response()->json([
                'code' => HttpCode::BAD_REQUEST,
                'status' => false,
                'message' => 'CSV validation errors found',
                'errors' => $parsedData['errors'],
                'total_rows' => $parsedData['total_rows']
            ], HttpCode::BAD_REQUEST);
        }

        if (empty($parsedData['rows'])) {
            return response()->json([
                'code' => HttpCode::BAD_REQUEST,
                'status' => false,
                'message' => 'No valid rows found in CSV'
            ], HttpCode::BAD_REQUEST);
        }

        $results = $this->productService->importProducts($parsedData['rows']);

        if (!$results['success']) {
            return response()->json([
                'code' => HttpCode::SERVER_ERROR,
                'status' => false,
                'message' => $results['error'] ?? 'Failed to import products'
            ], HttpCode::SERVER_ERROR);
        }

        return response()->json([
            'code' => HttpCode::SUCCESS,
            'status' => true,
            'message' => 'Products imported successfully',
            'data' => $results['data']
        ]);
    }

    /**
     * Get all variants for embroidery products with simple format for dropdown/selection
     * Query params: style, color, size, search, per_page, page
     */
    public function getAllVariantsSimple(Request $request): JsonResponse
    {
        try {
            // Select only needed columns for better performance
            $query = ProductVariant::select([
                'product_variants.id',
                'product_variants.product_id',
                'product_variants.variant_id',
                'product_variants.sku',
                'product_variants.style',
                'product_variants.color',
                'product_variants.size'
            ])
                ->join('products', 'products.id', '=', 'product_variants.product_id')
                ->addSelect('products.name as product_name', 'products.brand as product_brand')
                ->where('product_variants.active', true)
                ->where('products.category_type', 'wood');

            // Quick search (exact match) across multiple fields
            if ($request->filled('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('product_variants.style', $search)
                        ->orWhere('product_variants.sku', $search)
                        ->orWhere('product_variants.variant_id', $search)
                        ->orWhere('products.name', $search);
                });
            }

            // Filter by product_id (exact match)
            if ($request->filled('product_id')) {
                $query->where('product_variants.product_id', $request->product_id);
            }

            // Filter by brand (exact match qua products.brand)
            if ($request->filled('brand')) {
                $query->where('products.brand', $request->brand);
            }

            // Filter by style (exact match)
            if ($request->filled('style')) {
                $query->where('product_variants.style', $request->style);
            }

            // Filter by color (exact match) — wood luôn null nhưng giữ để backward compat
            if ($request->filled('color')) {
                $query->where('product_variants.color', $request->color);
            }

            // Filter by size (exact match)
            if ($request->filled('size')) {
                $query->where('product_variants.size', $request->size);
            }

            // Pagination - default 50, max 200
            $perPage = min($request->input('per_page', 50), 200);

            $variants = $query->orderBy('products.name')
                ->orderBy('product_variants.size')
                ->paginate($perPage);

            // Transform data
            $data = $variants->getCollection()->map(function ($variant) {
                return [
                    'id' => $variant->id,
                    'name' => $variant->product_name,
                    'brand' => $variant->product_brand,
                    'variant_id' => $variant->variant_id,
                    'sku' => $variant->sku,
                    'style' => $variant->style,
                    'color' => $variant->color,
                    'size' => $variant->size,
                ];
            });

            return response()->json([
                'status' => 'success',
                'data' => $data,
                'pagination' => [
                    'current_page' => $variants->currentPage(),
                    'per_page' => $variants->perPage(),
                    'total' => $variants->total(),
                    'last_page' => $variants->lastPage(),
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve variants',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], HttpCode::SERVER_ERROR);
        }
    }

    // ==================== PRINT PRODUCT APIs ====================

    /**
     * Get available styles for print products only
     */
    public function getPrintStyles(Request $request): JsonResponse
    {
        try {
            $resolvedStyle = 'COALESCE(product_variants.style, products.style)';

            $query = ProductVariant::selectRaw("{$resolvedStyle} as style")
                ->distinct()
                ->join('products', 'products.id', '=', 'product_variants.product_id')
                ->where('product_variants.active', ProductConstants::DEFAULT_ACTIVE_STATUS)
                ->where('products.category_type', 'print')
                ->whereRaw("{$resolvedStyle} IS NOT NULL")
                ->whereRaw("{$resolvedStyle} != ''");

            if ($request->has('product_id')) {
                $query->where('product_variants.product_id', $request->product_id);
            }

            $styles = $query->orderBy('style')->pluck('style');

            return response()->json([
                'code' => HttpCode::SUCCESS,
                'status' => true,
                'data' => $styles
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'code' => HttpCode::SERVER_ERROR,
                'status' => false,
                'message' => 'Failed to retrieve print styles',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], HttpCode::SERVER_ERROR);
        }
    }

    /**
     * Get available colors for print products only
     */
    public function getPrintColors(Request $request): JsonResponse
    {
        try {
            $resolvedStyle = 'COALESCE(product_variants.style, products.style)';

            $query = ProductVariant::select('product_variants.color')
                ->distinct()
                ->join('products', 'products.id', '=', 'product_variants.product_id')
                ->whereNotNull('product_variants.color')
                ->where('product_variants.active', ProductConstants::DEFAULT_ACTIVE_STATUS)
                ->where('products.category_type', 'print');

            if ($request->has('product_id')) {
                $query->where('product_variants.product_id', $request->product_id);
            }

            if ($request->has('style')) {
                $query->whereRaw("{$resolvedStyle} = ?", [$request->style]);
            }

            $colors = $query->orderBy('product_variants.color')->pluck('color');

            return response()->json([
                'code' => HttpCode::SUCCESS,
                'status' => true,
                'data' => $colors
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'code' => HttpCode::SERVER_ERROR,
                'status' => false,
                'message' => 'Failed to retrieve print colors',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], HttpCode::SERVER_ERROR);
        }
    }

    /**
     * Get available sizes for print products only
     */
    public function getPrintSizes(Request $request): JsonResponse
    {
        try {
            $resolvedStyle = 'COALESCE(product_variants.style, products.style)';

            $query = ProductVariant::select('product_variants.size')
                ->distinct()
                ->join('products', 'products.id', '=', 'product_variants.product_id')
                ->whereNotNull('product_variants.size')
                ->where('product_variants.active', ProductConstants::DEFAULT_ACTIVE_STATUS)
                ->where('products.category_type', 'print');

            if ($request->has('product_id')) {
                $query->where('product_variants.product_id', $request->product_id);
            }

            if ($request->has('style')) {
                $query->whereRaw("{$resolvedStyle} = ?", [$request->style]);
            }

            if ($request->has('color')) {
                $query->where('product_variants.color', $request->color);
            }

            $sizeOrder = implode("', '", ProductConstants::SIZE_ORDER);
            $sizes = $query->orderByRaw("FIELD(product_variants.size, '{$sizeOrder}')")
                ->pluck('size');

            return response()->json([
                'code' => HttpCode::SUCCESS,
                'status' => true,
                'data' => $sizes
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'code' => HttpCode::SERVER_ERROR,
                'status' => false,
                'message' => 'Failed to retrieve print sizes',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], HttpCode::SERVER_ERROR);
        }
    }

    /**
     * Get all variants for print products with simple format for dropdown/selection
     * Query params: style, color, size, search, per_page, page
     */
    public function getAllPrintVariantsSimple(Request $request): JsonResponse
    {
        try {
            $resolvedStyle = 'COALESCE(product_variants.style, products.style)';

            $query = ProductVariant::select([
                'product_variants.id',
                'product_variants.product_id',
                'product_variants.variant_id',
                'product_variants.color',
                'product_variants.size'
            ])
                ->join('products', 'products.id', '=', 'product_variants.product_id')
                ->selectRaw("{$resolvedStyle} as style")
                ->addSelect('products.name as product_name')
                ->where('product_variants.active', true)
                ->where('products.category_type', 'print');

            // Quick search across style, color, name (exact match)
            if ($request->filled('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search, $resolvedStyle) {
                    $q->whereRaw("{$resolvedStyle} = ?", [$search])
                        ->orWhere('product_variants.color', $search)
                        ->orWhere('products.name', $search);
                });
            }

            // Filter by style (exact match)
            if ($request->filled('style')) {
                $query->whereRaw("{$resolvedStyle} = ?", [$request->style]);
            }

            // Filter by color (exact match)
            if ($request->filled('color')) {
                $query->where('product_variants.color', $request->color);
            }

            // Filter by size (exact match)
            if ($request->filled('size')) {
                $query->where('product_variants.size', $request->size);
            }

            // Pagination - default 50, max 200
            $perPage = min($request->input('per_page', 50), 200);

            $variants = $query->orderBy('products.name')
                ->orderBy('product_variants.color')
                ->orderBy('product_variants.size')
                ->paginate($perPage);

            // Transform data
            $data = $variants->getCollection()->map(function ($variant) {
                return [
                    'id' => $variant->id,
                    'name' => $variant->product_name,
                    'variant_id' => $variant->variant_id,
                    'style' => $variant->style,
                    'color' => $variant->color,
                    'size' => $variant->size,
                ];
            });

            return response()->json([
                'status' => 'success',
                'data' => $data,
                'pagination' => [
                    'current_page' => $variants->currentPage(),
                    'per_page' => $variants->perPage(),
                    'total' => $variants->total(),
                    'last_page' => $variants->lastPage(),
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve print variants',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], HttpCode::SERVER_ERROR);
        }
    }
}
