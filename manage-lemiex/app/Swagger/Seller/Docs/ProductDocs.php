<?php

namespace App\Swagger\Seller\Docs;

/**
 * ==================== PRINT PRODUCT — FLOW CHỌN VARIANT ====================
 *
 * Luồng chọn variant cho tạo đơn (giống màn tạo đơn): Product → Size → Variant.
 *   • Step 1: GET /api/products                              — chọn product (id, name)
 *   • Step 2: GET /api/sizes?product_id={id}                 — các size của product đã chọn
 *   • Step 3: GET /api/products/variants?product_id={id}&size={size}  — resolve ra variant (lấy variant_id)
 *   • Step 4: GET /api/products/variants/{variantId}         — chi tiết variant đã chọn
 *
 * 4 endpoint dưới được gom trong tag "Product - Print" theo đúng thứ tự 4 bước.
 *
 * ==================== CATALOG ====================
 * @OA\Get(
 *     path="/api/products",
 *     operationId="sellerGetProducts",
 *     tags={"Product - Print", "Catalog"},
 *     summary="Step 1 — Danh sách product (chọn product)",
 *     description="Bước 1 của flow chọn variant: trả về toàn bộ products (KHÔNG phân trang) đã sort theo tên — lấy id + name để đổ vào dropdown Product. Mặc định category='wood'. Yêu cầu products.view.",
 *     security={{"bearerAuth":{}}},
 *     @OA\Parameter(name="status", in="query", description="Lọc theo status (active boolean)", @OA\Schema(type="boolean")),
 *     @OA\Parameter(name="search", in="query", description="LIKE match theo name", @OA\Schema(type="string")),
 *     @OA\Parameter(name="category", in="query", description="category_type", @OA\Schema(type="string", enum={"embroidery", "print", "wood"}, default="wood")),
 *     @OA\Response(
 *         response=200,
 *         description="Thành công",
 *         @OA\JsonContent(
 *             @OA\Property(property="code", type="integer", example=200),
 *             @OA\Property(property="status", type="boolean", example=true),
 *             @OA\Property(property="data", type="array", @OA\Items(
 *                 @OA\Property(property="id", type="integer"),
 *                 @OA\Property(property="name", type="string"),
 *                 @OA\Property(property="style", type="string"),
 *                 @OA\Property(property="brand", type="string"),
 *                 @OA\Property(property="category_type", type="string"),
 *                 @OA\Property(property="status", type="boolean"),
 *                 @OA\Property(property="mockup", type="string"),
 *                 @OA\Property(property="template_url", type="string"),
 *                 @OA\Property(property="warehouse_name", type="string")
 *             ))
 *         )
 *     )
 * )
 *
 * @OA\Get(
 *     path="/api/sizes",
 *     operationId="getProductSizes",
 *     tags={"Product - Print"},
 *     summary="Step 2 — Size theo product",
 *     description="Bước 2 của flow: trả về danh sách distinct size của các variant active thuộc product đã chọn ở step 1 (vd: S, M, L, XL, 2XL...). Truyền product_id để lấy đúng size của product đó.",
 *     @OA\Parameter(name="product_id", in="query", required=true, description="ID product đã chọn ở step 1", @OA\Schema(type="integer", example=4)),
 *     @OA\Response(
 *         response=200,
 *         description="Thành công",
 *         @OA\JsonContent(
 *             @OA\Property(property="code", type="integer", example=200),
 *             @OA\Property(property="status", type="boolean", example=true),
 *             @OA\Property(property="data", type="array", @OA\Items(type="string", example="2XL"))
 *         )
 *     ),
 *     @OA\Response(response=500, description="Server error")
 * )
 *
 * @OA\Get(
 *     path="/api/products/{id}",
 *     operationId="sellerGetProductDetail",
 *     tags={"Catalog"},
 *     summary="Chi tiết sản phẩm kèm variants + tier pricing",
 *     description="Trả về product + danh sách variants (sort theo color, size) với tier_pricing đã group theo tên tier. Kèm aggregated info: colors, sizes, total_stock, active_variants, min/max price.",
 *     security={{"bearerAuth":{}}},
 *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
 *     @OA\Response(
 *         response=200,
 *         description="Thành công",
 *         @OA\JsonContent(
 *             @OA\Property(property="data", type="object",
 *                 @OA\Property(property="product", type="object",
 *                     @OA\Property(property="id", type="integer"),
 *                     @OA\Property(property="name", type="string"),
 *                     @OA\Property(property="style", type="string"),
 *                     @OA\Property(property="brand", type="string"),
 *                     @OA\Property(property="mockup", type="string"),
 *                     @OA\Property(property="template_url", type="string"),
 *                     @OA\Property(property="status", type="boolean"),
 *                     @OA\Property(property="category_type", type="string"),
 *                     @OA\Property(property="warehouse_name", type="string")
 *                 ),
 *                 @OA\Property(property="variants", type="array", @OA\Items(type="object",
 *                     @OA\Property(property="variant_id", type="string"),
 *                     @OA\Property(property="sku", type="string"),
 *                     @OA\Property(property="color", type="string"),
 *                     @OA\Property(property="size", type="string"),
 *                     @OA\Property(property="stock", type="integer"),
 *                     @OA\Property(property="active", type="boolean"),
 *                     @OA\Property(property="supplier_price", type="number"),
 *                     @OA\Property(property="tier_pricing", type="object", description="Map: { 'Tier Name': { 'base_cost': 5.00, 'shipping_cost': 5.99, ... } }")
 *                 )),
 *                 @OA\Property(property="aggregated", type="object",
 *                     @OA\Property(property="colors", type="array", @OA\Items(type="string")),
 *                     @OA\Property(property="sizes", type="array", @OA\Items(type="string")),
 *                     @OA\Property(property="total_stock", type="integer"),
 *                     @OA\Property(property="active_variants", type="integer"),
 *                     @OA\Property(property="total_variants", type="integer"),
 *                     @OA\Property(property="min_price", type="number"),
 *                     @OA\Property(property="max_price", type="number")
 *                 )
 *             )
 *         )
 *     ),
 *     @OA\Response(response=404, description="Product not found")
 * )
 *
 * @OA\Get(
 *     path="/api/products/variants",
 *     operationId="sellerGetProductVariants",
 *     tags={"Product - Print", "Catalog"},
 *     summary="Step 3 — Resolve variant theo product + size",
 *     description="Bước 3 của flow: lọc variants theo product_id + size (dùng per_page=1) để ra đúng 1 variant, rồi lấy variant_id từ kết quả để gọi step 4. Mặc định chỉ trả active=true. Sort theo product_id → color → size. Yêu cầu products.variants.",
 *     security={{"bearerAuth":{}}},
 *     @OA\Parameter(name="per_page", in="query", @OA\Schema(type="integer", default=20)),
 *     @OA\Parameter(name="variant_id", in="query", description="LIKE match", @OA\Schema(type="string")),
 *     @OA\Parameter(name="sku", in="query", description="LIKE match", @OA\Schema(type="string")),
 *     @OA\Parameter(name="style", in="query", description="LIKE match", @OA\Schema(type="string")),
 *     @OA\Parameter(name="color_search", in="query", description="LIKE match theo color (dùng thay cho 'color' khi cần partial match)", @OA\Schema(type="string")),
 *     @OA\Parameter(name="color", in="query", description="Exact match color (bị ignore nếu có color_search)", @OA\Schema(type="string")),
 *     @OA\Parameter(name="size", in="query", description="Exact match size", @OA\Schema(type="string")),
 *     @OA\Parameter(name="product_name", in="query", description="LIKE match qua relation product.name", @OA\Schema(type="string")),
 *     @OA\Parameter(name="product_id", in="query", description="Exact match", @OA\Schema(type="integer")),
 *     @OA\Parameter(name="active", in="query", description="String 'true'/'false'; mặc định chỉ trả active=true", @OA\Schema(type="boolean")),
 *     @OA\Parameter(name="in_stock", in="query", description="true ⇒ lọc stock > 0", @OA\Schema(type="boolean")),
 *     @OA\Response(
 *         response=200,
 *         description="Thành công",
 *         @OA\JsonContent(
 *             @OA\Property(property="data", type="object", description="Laravel paginator: data[], current_page, per_page, total, last_page, next_page_url, prev_page_url...")
 *         )
 *     )
 * )
 *
 * @OA\Get(
 *     path="/api/products/variants/{variantId}",
 *     operationId="sellerGetVariantDetail",
 *     tags={"Product - Print", "Catalog"},
 *     summary="Step 4 — Chi tiết variant",
 *     description="Bước 4 của flow: lookup bằng variant_id string (vd 'PJ0005'), KHÔNG phải primary key. Trả về full_name, product_name + tier pricing đã group theo tier_id. Dùng để hiển thị variant đã chọn.",
 *     security={{"bearerAuth":{}}},
 *     @OA\Parameter(name="variantId", in="path", required=true, @OA\Schema(type="string"), description="Variant code (vd: PJ0005, G5000-BLK-S)"),
 *     @OA\Response(
 *         response=200,
 *         description="Thành công",
 *         @OA\JsonContent(
 *             @OA\Property(property="data", type="object",
 *                 @OA\Property(property="variant_id", type="string"),
 *                 @OA\Property(property="sku", type="string"),
 *                 @OA\Property(property="product_name", type="string"),
 *                 @OA\Property(property="full_name", type="string", description="Ghép tên: 'Product - Color - Size'"),
 *                 @OA\Property(property="style", type="string"),
 *                 @OA\Property(property="color", type="string"),
 *                 @OA\Property(property="size", type="string"),
 *                 @OA\Property(property="stock", type="integer"),
 *                 @OA\Property(property="active", type="boolean"),
 *                 @OA\Property(property="supplier_price", type="number"),
 *                 @OA\Property(property="pricing", type="object", description="Map: { 0: { 'base_cost': 5.00, ... }, 1: { ... } } — key là tier_id")
 *             )
 *         )
 *     ),
 *     @OA\Response(response=404, description="Variant không tồn tại")
 * )
 *
 * @OA\Get(
 *     path="/api/products/with-variants",
 *     operationId="sellerGetProductsWithVariants",
 *     tags={"Catalog"},
 *     summary="Products kèm variants (paginated, chỉ products có variants)",
 *     description="Chỉ trả về products có ít nhất 1 variant. Mỗi product có aggregated info (colors, sizes, total_stock, min/max price).",
 *     security={{"bearerAuth":{}}},
 *     @OA\Parameter(name="per_page", in="query", @OA\Schema(type="integer", default=20)),
 *     @OA\Parameter(name="search", in="query", description="LIKE theo name, brand, style", @OA\Schema(type="string")),
 *     @OA\Parameter(name="style", in="query", @OA\Schema(type="string")),
 *     @OA\Parameter(name="brand", in="query", @OA\Schema(type="string")),
 *     @OA\Parameter(name="status", in="query", @OA\Schema(type="boolean")),
 *     @OA\Parameter(name="category", in="query", @OA\Schema(type="string", enum={"embroidery", "print", "wood"})),
 *     @OA\Parameter(name="sort_by", in="query", @OA\Schema(type="string", enum={"created_at", "name", "brand", "style"}, default="created_at")),
 *     @OA\Parameter(name="sort_order", in="query", @OA\Schema(type="string", enum={"asc", "desc"}, default="desc")),
 *     @OA\Response(response=200, description="Thành công")
 * )
 *
 * @OA\Get(
 *     path="/api/products/filter-options",
 *     operationId="sellerGetProductFilterOptions",
 *     tags={"Catalog"},
 *     summary="Options cho filter (styles, brands, colors, sizes, categories...)",
 *     description="Trả về danh sách distinct values dùng để build dropdown filter.",
 *     security={{"bearerAuth":{}}},
 *     @OA\Response(response=200, description="Thành công")
 * )
 *
 * @OA\Get(
 *     path="/api/products/metadata",
 *     operationId="sellerGetProductMetadata",
 *     tags={"Catalog"},
 *     summary="Metadata: tiers + price_types",
 *     description="Trả về { tiers: [...], price_types: ['base_cost', 'seller_shipping', 'priority_shipping', 'shipping_cost'] }.",
 *     security={{"bearerAuth":{}}},
 *     @OA\Response(response=200, description="Thành công")
 * )
 *
 * ==================== CREATE PRODUCT ====================
 * @OA\Post(
 *     path="/api/products",
 *     operationId="sellerCreateProduct",
 *     tags={"Catalog"},
 *     summary="Tạo sản phẩm mới (kèm variants + tier pricing)",
 *     description="Tạo product in kèm tối thiểu 0+ variants. Mỗi variant có thể đính kèm tier pricing. Yêu cầu permission products.create.",
 *     security={{"bearerAuth":{}}},
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\JsonContent(
 *             required={"name"},
 *             @OA\Property(property="name", type="string", maxLength=255, example="Áo thun in - Premium"),
 *             @OA\Property(property="style", type="string", maxLength=255, example="Rectangle", description="Style mặc định cho product (variant có thể override)"),
 *             @OA\Property(property="brand", type="string", maxLength=255, example="Wecat Print"),
 *             @OA\Property(property="category_type", type="string", enum={"embroidery", "print", "wood"}, default="wood"),
 *             @OA\Property(property="status", type="boolean", default=true),
 *             @OA\Property(property="mockup", type="string", format="url", maxLength=500, example="https://..."),
 *             @OA\Property(property="template_url", type="string", format="url", maxLength=1000, example="https://example.com/template-link"),
 *             @OA\Property(property="warehouse_name", type="string", maxLength=255, example="Main Warehouse"),
 *             @OA\Property(
 *                 property="variants",
 *                 type="array",
 *                 description="Danh sách variants (tuỳ chọn)",
 *                 @OA\Items(type="object",
 *                     required={"variant_id"},
 *                     @OA\Property(property="variant_id", type="string", maxLength=255, example="PRINT-BOX-S-NAT", description="Phải UNIQUE"),
 *                     @OA\Property(property="sku", type="string", maxLength=255, example="SKU-BOX-S-NAT"),
 *                     @OA\Property(property="style", type="string", maxLength=255, example="Box-S"),
 *                     @OA\Property(property="color", type="string", maxLength=100, nullable=true, description="Có thể null tuỳ loại sản phẩm"),
 *                     @OA\Property(property="size", type="string", maxLength=50, example="S"),
 *                     @OA\Property(property="stock", type="integer", minimum=0, example=100),
 *                     @OA\Property(property="active", type="boolean", default=true),
 *                     @OA\Property(property="weight", type="integer", minimum=0, example=200, description="grams"),
 *                     @OA\Property(property="length", type="integer", minimum=0, example=15, description="cm"),
 *                     @OA\Property(property="width", type="integer", minimum=0, example=10),
 *                     @OA\Property(property="height", type="integer", minimum=0, example=1),
 *                     @OA\Property(property="supplier_price", type="number", format="float", minimum=0, example=3.50),
 *                     @OA\Property(
 *                         property="prices",
 *                         type="array",
 *                         description="Tier pricing — mỗi (tier_id × type) là 1 row",
 *                         @OA\Items(type="object",
 *                             required={"tier_id", "type", "price"},
 *                             @OA\Property(property="tier_id", type="integer", description="exists in tiers.tier_id (0=Silver, 1=Gold, 2=Platinum, 3=Diamond)"),
 *                             @OA\Property(property="type", type="string", enum={"base_cost", "seller_shipping", "priority_shipping", "shipping_cost"}),
 *                             @OA\Property(property="price", type="number", format="float", minimum=0, example=5.00)
 *                         )
 *                     )
 *                 )
 *             )
 *         )
 *     ),
 *     @OA\Response(response=200, description="Product tạo thành công"),
 *     @OA\Response(response=400, description="Validation lỗi"),
 *     @OA\Response(response=403, description="Không có permission products.create")
 * )
 *
 * ==================== IMPORT PRODUCTS ====================
 * @OA\Get(
 *     path="/api/products/import/template",
 *     operationId="sellerDownloadImportTemplate",
 *     tags={"Catalog"},
 *     summary="Tải template CSV để import sản phẩm",
 *     description="Tải file CSV mẫu có sẵn header + 3 dòng dữ liệu mẫu cho sản phẩm in. Yêu cầu permission products.import.",
 *     security={{"bearerAuth":{}}},
 *     @OA\Response(
 *         response=200,
 *         description="File CSV",
 *         @OA\MediaType(mediaType="text/csv")
 *     )
 * )
 *
 * @OA\Get(
 *     path="/api/products/import/export",
 *     operationId="sellerExportImportData",
 *     tags={"Catalog"},
 *     summary="Export toàn bộ products+variants hiện có ra CSV (cùng format import)",
 *     description="Trả về file CSV chứa data hiện tại, có thể chỉnh sửa rồi import lại để update bulk. Yêu cầu products.import.",
 *     security={{"bearerAuth":{}}},
 *     @OA\Response(
 *         response=200,
 *         description="File CSV",
 *         @OA\MediaType(mediaType="text/csv")
 *     )
 * )
 *
 * @OA\Post(
 *     path="/api/products/import/preview",
 *     operationId="sellerPreviewImport",
 *     tags={"Catalog"},
 *     summary="Preview file CSV trước khi import (không lưu DB)",
 *     description="Upload file CSV, parse và validate, trả về preview gồm: số product/variant sẽ tạo mới, sẽ update, errors nếu có. Yêu cầu products.import.",
 *     security={{"bearerAuth":{}}},
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\MediaType(
 *             mediaType="multipart/form-data",
 *             @OA\Schema(
 *                 required={"file"},
 *                 @OA\Property(property="file", type="string", format="binary", description="File .csv (max 10MB)")
 *             )
 *         )
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="Preview thành công",
 *         @OA\JsonContent(
 *             @OA\Property(property="status", type="boolean", example=true),
 *             @OA\Property(property="data", type="object",
 *                 @OA\Property(property="total_products", type="integer", example=5),
 *                 @OA\Property(property="total_variants", type="integer", example=20),
 *                 @OA\Property(property="new_products", type="integer", example=2),
 *                 @OA\Property(property="existing_products", type="integer", example=3),
 *                 @OA\Property(property="products", type="array", @OA\Items(type="object"))
 *             )
 *         )
 *     ),
 *     @OA\Response(response=400, description="CSV format lỗi hoặc validation errors")
 * )
 *
 * @OA\Post(
 *     path="/api/products/import",
 *     operationId="sellerImportProducts",
 *     tags={"Catalog"},
 *     summary="Import sản phẩm từ file CSV vào DB",
 *     description="Chính thức import file CSV vào DB. Wrapped trong transaction — nếu lỗi sẽ rollback toàn bộ. Yêu cầu products.import.",
 *     security={{"bearerAuth":{}}},
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\MediaType(
 *             mediaType="multipart/form-data",
 *             @OA\Schema(
 *                 required={"file"},
 *                 @OA\Property(property="file", type="string", format="binary", description="File .csv (max 10MB). Khuyến nghị preview trước.")
 *             )
 *         )
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="Import thành công",
 *         @OA\JsonContent(
 *             @OA\Property(property="status", type="boolean", example=true),
 *             @OA\Property(property="data", type="object",
 *                 @OA\Property(property="imported", type="integer", example=20),
 *                 @OA\Property(property="failed", type="integer", example=0)
 *             )
 *         )
 *     ),
 *     @OA\Response(response=400, description="CSV format hoặc data lỗi"),
 *     @OA\Response(response=500, description="Transaction failed, đã rollback")
 * )
 */
class ProductDocs
{
    // Class container for Seller Product API documentation
}
