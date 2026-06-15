<?php

namespace App\Swagger\Docs;

/**
 * ==================== PRODUCT API DOCUMENTATION ====================
 * 
 * @OA\Get(
 *     path="/api/products",
 *     operationId="getProducts",
 *     tags={"Products"},
 *     summary="Danh sách sản phẩm (mặc định: Embroidery)",
 *     description="Lấy danh sách sản phẩm. **Mặc định trả về sản phẩm thêu (embroidery)** để tương thích với khách hàng hiện tại. Dùng param `category=print` để lấy sản phẩm in.",
 *     security={{"bearerAuth":{}}},
 *     @OA\Parameter(name="status", in="query", description="Lọc theo trạng thái (true/false)", @OA\Schema(type="boolean")),
 *     @OA\Parameter(name="category", in="query", description="Lọc theo category_type (mặc định: embroidery)", @OA\Schema(type="string", enum={"embroidery", "print"}, default="embroidery")),
 *     @OA\Parameter(name="search", in="query", description="Tìm theo tên sản phẩm", @OA\Schema(type="string")),
 *     @OA\Response(
 *         response=200,
 *         description="Thành công",
 *         @OA\JsonContent(
 *             @OA\Property(property="code", type="integer", example=200),
 *             @OA\Property(property="status", type="boolean", example=true),
 *             @OA\Property(property="message", type="string", example="Lấy danh sách sản phẩm thành công"),
 *             @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/Product"))
 *         )
 *     ),
 *     @OA\Response(response=401, description="Chưa đăng nhập", @OA\JsonContent(ref="#/components/schemas/UnauthorizedResponse"))
 * )
 *
 * @OA\Get(
 *     path="/api/products/print",
 *     operationId="getPrintProducts",
 *     tags={"Products"},
 *     summary="Danh sách sản phẩm In (Print)",
 *     description="Lấy danh sách sản phẩm thuộc danh mục In (Print) như Tumbler, v.v.",
 *     security={{"bearerAuth":{}}},
 *     @OA\Parameter(name="status", in="query", description="Lọc theo trạng thái (true/false)", @OA\Schema(type="boolean")),
 *     @OA\Parameter(name="search", in="query", description="Tìm theo tên sản phẩm", @OA\Schema(type="string")),
 *     @OA\Response(
 *         response=200,
 *         description="Thành công",
 *         @OA\JsonContent(
 *             @OA\Property(property="code", type="integer", example=200),
 *             @OA\Property(property="status", type="boolean", example=true),
 *             @OA\Property(property="message", type="string", example="Lấy danh sách sản phẩm thành công"),
 *             @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/Product"))
 *         )
 *     ),
 *     @OA\Response(response=401, description="Chưa đăng nhập", @OA\JsonContent(ref="#/components/schemas/UnauthorizedResponse"))
 * )
 *
 * @OA\Post(
 *     path="/api/products",
 *     operationId="createProduct",
 *     tags={"Products"},
 *     summary="Tạo sản phẩm mới",
 *     description="Tạo sản phẩm mới với variants và tier pricing (optional)",
 *     security={{"bearerAuth":{}}},
 *     @OA\RequestBody(
 *         required=true,
 *         description="Chọn example từ dropdown",
 *         @OA\JsonContent(
 *             @OA\Examples(
 *                 example="simple_product",
 *                 summary="Sản phẩm đơn giản (không có variants)",
 *                 value={
 *                     "name": "Gildan 64000",
 *                     "style": "64000",
 *                     "brand": "Gildan",
 *                     "status": true,
 *                     "mockup": "https://example.com/mockup.jpg",
 *                     "template_url": "https://example.com/template-link",
 *                     "warehouse_name": "US Warehouse",
 *                     "category_type": "print"
 *                 }
 *             ),
 *             @OA\Examples(
 *                 example="product_with_variants",
 *                 summary="Sản phẩm với variants và pricing",
 *                 value={
 *                     "name": "Gildan 64000",
 *                     "style": "64000",
 *                     "brand": "Gildan",
 *                     "status": true,
 *                     "category_type": "print",
 *                     "variants": {
 *                         {
 *                             "variant_id": "GILDAN-64000-BLACK-L",
 *                             "sku": "SKU-001",
 *                             "style": "64000",
 *                             "color": "Black",
 *                             "size": "L",
 *                             "stock": 100,
 *                             "active": true,
 *                             "supplier_price": 5.99,
 *                             "prices": {
 *                                 {"tier_id": 0, "type": "base_cost", "price": 8.99},
 *                                 {"tier_id": 1, "type": "base_cost", "price": 7.99},
 *                                 {"tier_id": 0, "type": "front", "price": 2.00},
 *                                 {"tier_id": 1, "type": "front", "price": 1.50}
 *                             }
 *                         }
 *                     }
 *                 }
 *             )
 *         )
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="Tạo thành công",
 *         @OA\JsonContent(
 *             @OA\Property(property="code", type="integer", example=200),
 *             @OA\Property(property="status", type="boolean", example=true),
 *             @OA\Property(property="message", type="string", example="Tạo sản phẩm thành công"),
 *             @OA\Property(property="data", ref="#/components/schemas/Product")
 *         )
 *     ),
 *     @OA\Response(response=400, description="Validation lỗi", @OA\JsonContent(ref="#/components/schemas/ValidationErrorResponse")),
 *     @OA\Response(response=401, description="Chưa đăng nhập", @OA\JsonContent(ref="#/components/schemas/UnauthorizedResponse"))
 * )
 *
 * @OA\Get(
 *     path="/api/products/filter-options",
 *     operationId="getProductFilterOptions",
 *     tags={"Products"},
 *     summary="Options cho filter",
 *     description="Lấy danh sách colors, sizes, brands để hiển thị trong dropdown filter",
 *     security={{"bearerAuth":{}}},
 *     @OA\Response(
 *         response=200,
 *         description="Thành công",
 *         @OA\JsonContent(
 *             @OA\Property(property="code", type="integer", example=200),
 *             @OA\Property(property="status", type="boolean", example=true),
 *             @OA\Property(property="data", type="object",
 *                 @OA\Property(property="colors", type="array", @OA\Items(type="string", example="Black")),
 *                 @OA\Property(property="sizes", type="array", @OA\Items(type="string", example="L")),
 *                 @OA\Property(property="brands", type="array", @OA\Items(type="string", example="Gildan"))
 *             )
 *         )
 *     ),
 *     @OA\Response(response=401, description="Chưa đăng nhập", @OA\JsonContent(ref="#/components/schemas/UnauthorizedResponse"))
 * )
 *
 * @OA\Get(
 *     path="/api/products/metadata",
 *     operationId="getProductMetadata",
 *     tags={"Products"},
 *     summary="Metadata sản phẩm",
 *     description="Lấy metadata cho form tạo/sửa sản phẩm (tier IDs, price types, etc.)",
 *     security={{"bearerAuth":{}}},
 *     @OA\Response(
 *         response=200,
 *         description="Thành công",
 *         @OA\JsonContent(
 *             @OA\Property(property="code", type="integer", example=200),
 *             @OA\Property(property="status", type="boolean", example=true),
 *             @OA\Property(property="data", type="object",
 *                 @OA\Property(property="tiers", type="object", example={"0": "Silver", "1": "Gold", "2": "Platinum", "3": "Diamond"}),
 *                 @OA\Property(property="price_types", type="array", @OA\Items(type="string", example="base_cost"))
 *             )
 *         )
 *     ),
 *     @OA\Response(response=401, description="Chưa đăng nhập", @OA\JsonContent(ref="#/components/schemas/UnauthorizedResponse"))
 * )
 *
 * @OA\Get(
 *     path="/api/products/variants",
 *     operationId="getVariants",
 *     tags={"Products"},
 *     summary="Danh sách variants",
 *     description="Lấy danh sách variants với phân trang và bộ lọc riêng biệt",
 *     security={{"bearerAuth":{}}},
 *     @OA\Parameter(name="per_page", in="query", description="Số lượng/trang", @OA\Schema(type="integer", default=20)),
 *     @OA\Parameter(name="category", in="query", description="Lọc theo category_type (embroidery/print)", @OA\Schema(type="string", enum={"embroidery", "print"})),
 *     @OA\Parameter(name="variant_id", in="query", description="Tìm theo variant_id (partial match)", @OA\Schema(type="string", example="GILDAN-64000")),
 *     @OA\Parameter(name="sku", in="query", description="Tìm theo SKU (partial match)", @OA\Schema(type="string", example="SKU-001")),
 *     @OA\Parameter(name="style", in="query", description="Tìm theo style (partial match)", @OA\Schema(type="string", example="64000")),
 *     @OA\Parameter(name="color_search", in="query", description="Tìm theo màu (partial match)", @OA\Schema(type="string", example="Black")),
 *     @OA\Parameter(name="product_name", in="query", description="Tìm theo tên sản phẩm (partial match)", @OA\Schema(type="string", example="Gildan")),
 *     @OA\Parameter(name="product_id", in="query", description="Lọc theo product ID (exact match)", @OA\Schema(type="integer")),
 *     @OA\Parameter(name="color", in="query", description="Lọc theo màu (exact match)", @OA\Schema(type="string")),
 *     @OA\Parameter(name="size", in="query", description="Lọc theo size (exact match)", @OA\Schema(type="string")),
 *     @OA\Parameter(name="active", in="query", description="Lọc theo trạng thái (default: true)", @OA\Schema(type="boolean", default=true)),
 *     @OA\Parameter(name="in_stock", in="query", description="Chỉ hiện còn hàng (stock > 0)", @OA\Schema(type="boolean")),
 *     @OA\Response(
 *         response=200,
 *         description="Thành công",
 *         @OA\JsonContent(
 *             @OA\Property(property="code", type="integer", example=200),
 *             @OA\Property(property="status", type="boolean", example=true),
 *             @OA\Property(property="data", type="object",
 *                 @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/ProductVariant")),
 *                 @OA\Property(property="current_page", type="integer"),
 *                 @OA\Property(property="per_page", type="integer"),
 *                 @OA\Property(property="total", type="integer")
 *             )
 *         )
 *     ),
 *     @OA\Response(response=401, description="Chưa đăng nhập", @OA\JsonContent(ref="#/components/schemas/UnauthorizedResponse"))
 * )
 *
 * @OA\Put(
 *     path="/api/products/variants/{id}",
 *     operationId="updateVariant",
 *     tags={"Products"},
 *     summary="Cập nhật variant",
 *     description="Cập nhật thông tin variant. Tự động tạo audit log khi thay đổi stock.",
 *     security={{"bearerAuth":{}}},
 *     @OA\Parameter(name="id", in="path", required=true, description="ID variant (số)", @OA\Schema(type="integer")),
 *     @OA\RequestBody(
 *         required=true,
 *         description="Chọn example từ dropdown",
 *         @OA\JsonContent(
 *             @OA\Examples(
 *                 example="update_stock",
 *                 summary="Cập nhật tồn kho",
 *                 value={"variant_id": "GILDAN-64000-BLACK-L", "stock": 150}
 *             ),
 *             @OA\Examples(
 *                 example="deactivate",
 *                 summary="Tắt variant",
 *                 value={"variant_id": "GILDAN-64000-BLACK-L", "active": false}
 *             ),
 *             @OA\Examples(
 *                 example="update_full",
 *                 summary="Cập nhật đầy đủ",
 *                 value={
 *                     "variant_id": "GILDAN-64000-BLACK-L",
 *                     "sku": "SKU-001-NEW",
 *                     "style": "64000",
 *                     "color": "Black",
 *                     "size": "L",
 *                     "stock": 200,
 *                     "active": true,
 *                     "weight": 200,
 *                     "supplier_price": 6.50
 *                 }
 *             )
 *         )
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="Cập nhật thành công",
 *         @OA\JsonContent(
 *             @OA\Property(property="code", type="integer", example=200),
 *             @OA\Property(property="status", type="boolean", example=true),
 *             @OA\Property(property="message", type="string", example="Cập nhật variant thành công"),
 *             @OA\Property(property="data", ref="#/components/schemas/ProductVariant")
 *         )
 *     ),
 *     @OA\Response(response=400, description="Validation lỗi", @OA\JsonContent(ref="#/components/schemas/ValidationErrorResponse")),
 *     @OA\Response(response=404, description="Không tìm thấy variant", @OA\JsonContent(ref="#/components/schemas/NotFoundResponse"))
 * )
 *
 * @OA\Put(
 *     path="/api/products/variants/{variantId}/pricing",
 *     operationId="updateVariantPricing",
 *     tags={"Products"},
 *     summary="Cập nhật giá tier",
 *     description="Cập nhật tier pricing cho variant",
 *     security={{"bearerAuth":{}}},
 *     @OA\Parameter(name="variantId", in="path", required=true, description="Variant ID (string)", @OA\Schema(type="string")),
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\JsonContent(
 *             @OA\Examples(
 *                 example="update_all_tiers",
 *                 summary="Cập nhật giá tất cả tiers",
 *                 value={
 *                     "prices": {
 *                         {"tier_id": 0, "type": "base_cost", "price": 8.99},
 *                         {"tier_id": 1, "type": "base_cost", "price": 7.99},
 *                         {"tier_id": 2, "type": "base_cost", "price": 6.99},
 *                         {"tier_id": 3, "type": "base_cost", "price": 5.99},
 *                         {"tier_id": 0, "type": "front", "price": 2.50},
 *                         {"tier_id": 1, "type": "front", "price": 2.00},
 *                         {"tier_id": 2, "type": "front", "price": 1.75},
 *                         {"tier_id": 3, "type": "front", "price": 1.50}
 *                     }
 *                 }
 *             )
 *         )
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="Cập nhật thành công",
 *         @OA\JsonContent(ref="#/components/schemas/ApiResponse")
 *     ),
 *     @OA\Response(response=400, description="Validation lỗi", @OA\JsonContent(ref="#/components/schemas/ValidationErrorResponse")),
 *     @OA\Response(response=404, description="Không tìm thấy variant", @OA\JsonContent(ref="#/components/schemas/NotFoundResponse"))
 * )
 *
 * @OA\Get(
 *     path="/api/products/variants/{variantId}",
 *     operationId="getVariantById",
 *     tags={"Products"},
 *     summary="Chi tiết variant",
 *     description="Lấy thông tin chi tiết một variant theo variant_id",
 *     security={{"bearerAuth":{}}},
 *     @OA\Parameter(name="variantId", in="path", required=true, description="Variant ID (string)", @OA\Schema(type="string")),
 *     @OA\Response(
 *         response=200,
 *         description="Thành công",
 *         @OA\JsonContent(
 *             @OA\Property(property="code", type="integer", example=200),
 *             @OA\Property(property="status", type="boolean", example=true),
 *             @OA\Property(property="data", type="object",
 *                 @OA\Property(property="variant_id", type="string"),
 *                 @OA\Property(property="sku", type="string"),
 *                 @OA\Property(property="product_name", type="string"),
 *                 @OA\Property(property="full_name", type="string", example="Gildan 64000 - Black - L"),
 *                 @OA\Property(property="color", type="string"),
 *                 @OA\Property(property="size", type="string"),
 *                 @OA\Property(property="stock", type="integer"),
 *                 @OA\Property(property="active", type="boolean"),
 *                 @OA\Property(property="supplier_price", type="number"),
 *                 @OA\Property(property="pricing", type="object", description="Tier pricing grouped by tier_id")
 *             )
 *         )
 *     ),
 *     @OA\Response(response=404, description="Không tìm thấy variant", @OA\JsonContent(ref="#/components/schemas/NotFoundResponse"))
 * )
 *
 * @OA\Get(
 *     path="/api/products/with-variants",
 *     operationId="getProductsWithVariants",
 *     tags={"Products"},
 *     summary="Sản phẩm với variants",
 *     description="Lấy danh sách sản phẩm kèm theo variants",
 *     security={{"bearerAuth":{}}},
 *     @OA\Parameter(name="category", in="query", description="Lọc theo category_type (embroidery/print)", @OA\Schema(type="string", enum={"embroidery", "print"})),
 *     @OA\Response(
 *         response=200,
 *         description="Thành công",
 *         @OA\JsonContent(
 *             @OA\Property(property="code", type="integer", example=200),
 *             @OA\Property(property="status", type="boolean", example=true),
 *             @OA\Property(property="data", type="array", @OA\Items(type="object"))
 *         )
 *     ),
 *     @OA\Response(response=401, description="Chưa đăng nhập", @OA\JsonContent(ref="#/components/schemas/UnauthorizedResponse"))
 * )
 *
 * @OA\Get(
 *     path="/api/styles",
 *     operationId="getProductStyles",
 *     tags={"Products - Thêu (Embroidery)"},
 *     summary="Danh sách styles (Thêu)",
 *     description="Lấy danh sách styles của sản phẩm **thêu (Embroidery)**. Sử dụng `/api/print/styles` cho sản phẩm in.",
 *     @OA\Parameter(name="product_id", in="query", description="Lọc theo product ID", @OA\Schema(type="integer")),
 *     @OA\Response(
 *         response=200,
 *         description="Thành công",
 *         @OA\JsonContent(
 *             @OA\Property(property="code", type="integer", example=200),
 *             @OA\Property(property="status", type="boolean", example=true),
 *             @OA\Property(property="data", type="array", @OA\Items(type="string", example="T-Shirt"))
 *         )
 *     )
 * )
 *
 * @OA\Get(
 *     path="/api/colors",
 *     operationId="getProductColors",
 *     tags={"Products - Thêu (Embroidery)"},
 *     summary="Danh sách màu sắc (Thêu)",
 *     description="Lấy danh sách màu sắc của sản phẩm **thêu (Embroidery)**. Sử dụng `/api/print/colors` cho sản phẩm in.",
 *     @OA\Parameter(name="product_id", in="query", description="Lọc theo product ID", @OA\Schema(type="integer")),
 *     @OA\Parameter(name="style", in="query", description="Lọc theo style (exact match)", @OA\Schema(type="string")),
 *     @OA\Response(
 *         response=200,
 *         description="Thành công",
 *         @OA\JsonContent(
 *             @OA\Property(property="code", type="integer", example=200),
 *             @OA\Property(property="status", type="boolean", example=true),
 *             @OA\Property(property="data", type="array", @OA\Items(type="string", example="Black"))
 *         )
 *     )
 * )
 *
 * @OA\Get(
 *     path="/api/sizes",
 *     operationId="getProductSizes",
 *     tags={"Products - Thêu (Embroidery)"},
 *     summary="Danh sách size (Thêu)",
 *     description="Lấy danh sách size của sản phẩm **thêu (Embroidery)**. Sử dụng `/api/print/sizes` cho sản phẩm in.",
 *     @OA\Parameter(name="product_id", in="query", description="Lọc theo product ID", @OA\Schema(type="integer")),
 *     @OA\Parameter(name="style", in="query", description="Lọc theo style (exact match)", @OA\Schema(type="string")),
 *     @OA\Parameter(name="color", in="query", description="Lọc theo color (exact match)", @OA\Schema(type="string")),
 *     @OA\Response(
 *         response=200,
 *         description="Thành công",
 *         @OA\JsonContent(
 *             @OA\Property(property="code", type="integer", example=200),
 *             @OA\Property(property="status", type="boolean", example=true),
 *             @OA\Property(property="data", type="array", @OA\Items(type="string", example="L"))
 *         )
 *     )
 * )
 *
 * @OA\Post(
 *     path="/api/products/updatestock",
 *     operationId="updateProductStock",
 *     tags={"Products"},
 *     summary="Cập nhật tồn kho",
 *     description="Cập nhật tồn kho cho variant (legacy endpoint)",
 *     security={{"bearerAuth":{}}},
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\JsonContent(
 *             required={"variant_id", "stock"},
 *             @OA\Property(property="variant_id", type="string", example="GILDAN-64000-BLACK-L"),
 *             @OA\Property(property="stock", type="integer", example=100)
 *         )
 *     ),
 *     @OA\Response(response=200, description="Cập nhật thành công", @OA\JsonContent(ref="#/components/schemas/ApiResponse")),
 *     @OA\Response(response=400, description="Validation lỗi", @OA\JsonContent(ref="#/components/schemas/ValidationErrorResponse"))
 * )
 *
 * @OA\Get(
 *     path="/api/products/import/template",
 *     operationId="downloadImportTemplate",
 *     tags={"Products"},
 *     summary="Tải template import",
 *     description="Tải file CSV template để import sản phẩm/variants",
 *     security={{"bearerAuth":{}}},
 *     @OA\Response(response=200, description="CSV file", @OA\MediaType(mediaType="text/csv")),
 *     @OA\Response(response=401, description="Chưa đăng nhập", @OA\JsonContent(ref="#/components/schemas/UnauthorizedResponse"))
 * )
 *
 * @OA\Post(
 *     path="/api/products/import/preview",
 *     operationId="previewImport",
 *     tags={"Products"},
 *     summary="Preview import CSV",
 *     description="Preview dữ liệu từ file CSV trước khi import thực sự",
 *     security={{"bearerAuth":{}}},
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\MediaType(
 *             mediaType="multipart/form-data",
 *             @OA\Schema(
 *                 required={"file"},
 *                 @OA\Property(property="file", type="string", format="binary", description="File CSV")
 *             )
 *         )
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="Preview thành công",
 *         @OA\JsonContent(
 *             @OA\Property(property="code", type="integer", example=200),
 *             @OA\Property(property="status", type="boolean", example=true),
 *             @OA\Property(property="data", type="object",
 *                 @OA\Property(property="total_rows", type="integer"),
 *                 @OA\Property(property="valid_rows", type="integer"),
 *                 @OA\Property(property="invalid_rows", type="integer"),
 *                 @OA\Property(property="preview", type="array", @OA\Items(type="object"))
 *             )
 *         )
 *     ),
 *     @OA\Response(response=400, description="File không hợp lệ", @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse"))
 * )
 *
 * @OA\Post(
 *     path="/api/products/import",
 *     operationId="importCSV",
 *     tags={"Products"},
 *     summary="Import CSV",
 *     description="Import sản phẩm/variants từ file CSV",
 *     security={{"bearerAuth":{}}},
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\MediaType(
 *             mediaType="multipart/form-data",
 *             @OA\Schema(
 *                 required={"file"},
 *                 @OA\Property(property="file", type="string", format="binary", description="File CSV")
 *             )
 *         )
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="Import thành công",
 *         @OA\JsonContent(
 *             @OA\Property(property="code", type="integer", example=200),
 *             @OA\Property(property="status", type="boolean", example=true),
 *             @OA\Property(property="message", type="string", example="Import thành công 50 records"),
 *             @OA\Property(property="data", type="object",
 *                 @OA\Property(property="imported", type="integer"),
 *                 @OA\Property(property="failed", type="integer"),
 *                 @OA\Property(property="errors", type="array", @OA\Items(type="object"))
 *             )
 *         )
 *     ),
 *     @OA\Response(response=400, description="Import lỗi", @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse"))
 * )
 *
 * @OA\Put(
 *     path="/api/products/{id}",
 *     operationId="updateProduct",
 *     tags={"Products"},
 *     summary="Cập nhật sản phẩm",
 *     description="Cập nhật thông tin sản phẩm (không bao gồm variants)",
 *     security={{"bearerAuth":{}}},
 *     @OA\Parameter(name="id", in="path", required=true, description="ID sản phẩm", @OA\Schema(type="integer")),
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\JsonContent(ref="#/components/schemas/ProductUpdateRequest")
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="Cập nhật thành công",
 *         @OA\JsonContent(
 *             @OA\Property(property="code", type="integer", example=200),
 *             @OA\Property(property="status", type="boolean", example=true),
 *             @OA\Property(property="message", type="string", example="Cập nhật sản phẩm thành công"),
 *             @OA\Property(property="data", ref="#/components/schemas/Product")
 *         )
 *     ),
 *     @OA\Response(response=400, description="Validation lỗi", @OA\JsonContent(ref="#/components/schemas/ValidationErrorResponse")),
 *     @OA\Response(response=404, description="Không tìm thấy sản phẩm", @OA\JsonContent(ref="#/components/schemas/NotFoundResponse"))
 * )
 *
 * @OA\Get(
 *     path="/api/products/{id}",
 *     operationId="getProductDetail",
 *     tags={"Products"},
 *     summary="Chi tiết sản phẩm",
 *     description="Lấy thông tin chi tiết sản phẩm với tất cả variants và tier pricing",
 *     security={{"bearerAuth":{}}},
 *     @OA\Parameter(name="id", in="path", required=true, description="ID sản phẩm", @OA\Schema(type="integer")),
 *     @OA\Response(
 *         response=200,
 *         description="Thành công",
 *         @OA\JsonContent(
 *             @OA\Property(property="code", type="integer", example=200),
 *             @OA\Property(property="status", type="boolean", example=true),
 *             @OA\Property(property="data", type="object",
 *                 @OA\Property(property="product", ref="#/components/schemas/Product"),
 *                 @OA\Property(property="summary", type="object",
 *                     @OA\Property(property="colors", type="array", @OA\Items(type="string")),
 *                     @OA\Property(property="sizes", type="array", @OA\Items(type="string")),
 *                     @OA\Property(property="total_stock", type="integer"),
 *                     @OA\Property(property="active_variants", type="integer"),
 *                     @OA\Property(property="total_variants", type="integer"),
 *                     @OA\Property(property="price_range", type="object",
 *                         @OA\Property(property="min", type="number"),
 *                         @OA\Property(property="max", type="number")
 *                     )
 *                 ),
 *                 @OA\Property(property="tiers", type="object"),
 *                 @OA\Property(property="variants", type="array", @OA\Items(type="object"))
 *             )
 *         )
 *     ),
 *     @OA\Response(response=404, description="Không tìm thấy sản phẩm", @OA\JsonContent(ref="#/components/schemas/NotFoundResponse"))
 * )
 *
 * @OA\Get(
 *     path="/api/all-variants",
 *     operationId="getAllVariantsSimple",
 *     tags={"Products - Thêu (Embroidery)"},
 *     summary="Lấy tất cả variants (Thêu)",
 *     description="Lấy danh sách variants của sản phẩm **thêu (Embroidery)** với format đơn giản cho dropdown/selection. Sử dụng `/api/print/all-variants` cho sản phẩm in.",
 *     @OA\Parameter(name="style", in="query", description="Lọc theo style (exact match)", @OA\Schema(type="string", example="T-Shirt")),
 *     @OA\Parameter(name="color", in="query", description="Lọc theo màu (exact match)", @OA\Schema(type="string", example="Black")),
 *     @OA\Parameter(name="size", in="query", description="Lọc theo size (exact match)", @OA\Schema(type="string", example="L")),
 *     @OA\Parameter(name="search", in="query", description="Tìm kiếm chung", @OA\Schema(type="string")),
 *     @OA\Parameter(name="per_page", in="query", description="Số lượng/trang (mặc định 50, tối đa 200)", @OA\Schema(type="integer", default=50)),
 *     @OA\Response(
 *         response=200,
 *         description="Thành công",
 *         @OA\JsonContent(
 *             @OA\Property(property="status", type="string", example="success"),
 *             @OA\Property(property="data", type="array", @OA\Items(type="object",
 *                 @OA\Property(property="id", type="integer", example=4),
 *                 @OA\Property(property="name", type="string", example="T-shirt comfort colors 1717cc"),
 *                 @OA\Property(property="variant_id", type="string", example="CC1717-BLACK-L"),
 *                 @OA\Property(property="style", type="string", example="Comfort"),
 *                 @OA\Property(property="color", type="string", example="Black"),
 *                 @OA\Property(property="size", type="string", example="L")
 *             )),
 *             @OA\Property(property="pagination", type="object",
 *                 @OA\Property(property="current_page", type="integer"),
 *                 @OA\Property(property="per_page", type="integer"),
 *                 @OA\Property(property="total", type="integer"),
 *                 @OA\Property(property="last_page", type="integer")
 *             )
 *         )
 *     ),
 *     @OA\Response(response=500, description="Lỗi server", @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse"))
 * )
 *
 * ==================== PRINT PRODUCT APIs ====================
 *
 * @OA\Get(
 *     path="/api/print/styles",
 *     operationId="getPrintStyles",
 *     tags={"Products - Print"},
 *     summary="Danh sách styles (Print)",
 *     description="Lấy danh sách styles của sản phẩm In (Print) như Tumbler",
 *     @OA\Parameter(name="product_id", in="query", description="Lọc theo product ID", @OA\Schema(type="integer")),
 *     @OA\Response(
 *         response=200,
 *         description="Thành công",
 *         @OA\JsonContent(
 *             @OA\Property(property="code", type="integer", example=200),
 *             @OA\Property(property="status", type="boolean", example=true),
 *             @OA\Property(property="data", type="array", @OA\Items(type="string", example="Tumbler"))
 *         )
 *     )
 * )
 *
 * @OA\Get(
 *     path="/api/print/colors",
 *     operationId="getPrintColors",
 *     tags={"Products - Print"},
 *     summary="Danh sách màu sắc (Print)",
 *     description="Lấy danh sách màu sắc của sản phẩm In (Print)",
 *     @OA\Parameter(name="product_id", in="query", description="Lọc theo product ID", @OA\Schema(type="integer")),
 *     @OA\Parameter(name="style", in="query", description="Lọc theo style (exact match)", @OA\Schema(type="string")),
 *     @OA\Response(
 *         response=200,
 *         description="Thành công",
 *         @OA\JsonContent(
 *             @OA\Property(property="code", type="integer", example=200),
 *             @OA\Property(property="status", type="boolean", example=true),
 *             @OA\Property(property="data", type="array", @OA\Items(type="string", example="Black"))
 *         )
 *     )
 * )
 *
 * @OA\Get(
 *     path="/api/print/sizes",
 *     operationId="getPrintSizes",
 *     tags={"Products - Print"},
 *     summary="Danh sách size (Print)",
 *     description="Lấy danh sách size của sản phẩm In (Print)",
 *     @OA\Parameter(name="product_id", in="query", description="Lọc theo product ID", @OA\Schema(type="integer")),
 *     @OA\Parameter(name="style", in="query", description="Lọc theo style (exact match)", @OA\Schema(type="string")),
 *     @OA\Parameter(name="color", in="query", description="Lọc theo color (exact match)", @OA\Schema(type="string")),
 *     @OA\Response(
 *         response=200,
 *         description="Thành công",
 *         @OA\JsonContent(
 *             @OA\Property(property="code", type="integer", example=200),
 *             @OA\Property(property="status", type="boolean", example=true),
 *             @OA\Property(property="data", type="array", @OA\Items(type="string", example="20oz"))
 *         )
 *     )
 * )
 *
 * @OA\Get(
 *     path="/api/print/all-variants",
 *     operationId="getAllPrintVariantsSimple",
 *     tags={"Products - Print"},
 *     summary="Lấy tất cả variants Print",
 *     description="Lấy danh sách variants của sản phẩm In (Print) với format đơn giản cho dropdown/selection.",
 *     @OA\Parameter(name="style", in="query", description="Lọc theo style (exact match)", @OA\Schema(type="string", example="Tumbler")),
 *     @OA\Parameter(name="color", in="query", description="Lọc theo màu (exact match)", @OA\Schema(type="string", example="Black")),
 *     @OA\Parameter(name="size", in="query", description="Lọc theo size (exact match)", @OA\Schema(type="string", example="20oz")),
 *     @OA\Parameter(name="search", in="query", description="Tìm kiếm nhanh", @OA\Schema(type="string")),
 *     @OA\Parameter(name="per_page", in="query", description="Số lượng/trang (max 200)", @OA\Schema(type="integer", default=50)),
 *     @OA\Response(
 *         response=200,
 *         description="Thành công",
 *         @OA\JsonContent(
 *             @OA\Property(property="status", type="string", example="success"),
 *             @OA\Property(property="data", type="array", @OA\Items(type="object",
 *                 @OA\Property(property="id", type="integer", example=4),
 *                 @OA\Property(property="name", type="string", example="Tumbler 20oz"),
 *                 @OA\Property(property="variant_id", type="string", example="TUMBLER-20OZ-BLACK"),
 *                 @OA\Property(property="style", type="string", example="Tumbler"),
 *                 @OA\Property(property="color", type="string", example="Black"),
 *                 @OA\Property(property="size", type="string", example="20oz")
 *             )),
 *             @OA\Property(property="pagination", type="object",
 *                 @OA\Property(property="current_page", type="integer"),
 *                 @OA\Property(property="per_page", type="integer"),
 *                 @OA\Property(property="total", type="integer"),
 *                 @OA\Property(property="last_page", type="integer")
 *             )
 *         )
 *     ),
 *     @OA\Response(response=500, description="Server error", @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse"))
 * )
 */
class ProductDocs
{
    // Chứa Swagger documentation cho Product endpoints
    // Logic thực tế trong ProductController
}
