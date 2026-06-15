<?php

namespace App\Swagger\Docs;

/**
 * ==================== STOCK API DOCUMENTATION ====================
 *
 * @OA\Get(
 *     path="/api/stock/summary",
 *     operationId="getStockSummary",
 *     tags={"Stock"},
 *     summary="Thống kê tồn kho",
 *     description="Lấy thống kê tổng quan về tồn kho của một sản phẩm cụ thể",
 *     security={{"bearerAuth":{}}},
 *     @OA\Parameter(name="product_id", in="query", required=true, description="ID sản phẩm", @OA\Schema(type="integer")),
 *     @OA\Response(
 *         response=200,
 *         description="Thành công",
 *         @OA\JsonContent(
 *             @OA\Property(property="code", type="integer", example=200),
 *             @OA\Property(property="status", type="boolean", example=true),
 *             @OA\Property(property="message", type="string", example="Stock summary retrieved successfully"),
 *             @OA\Property(property="data", type="object",
 *                 @OA\Property(property="total_variants", type="integer", example=50),
 *                 @OA\Property(property="total_stock", type="integer", example=1000),
 *                 @OA\Property(property="low_stock_count", type="integer", example=5),
 *                 @OA\Property(property="out_of_stock_count", type="integer", example=2)
 *             )
 *         )
 *     ),
 *     @OA\Response(response=401, description="Chưa xác thực", @OA\JsonContent(ref="#/components/schemas/UnauthorizedResponse"))
 * )
 *
 * @OA\Get(
 *     path="/api/stock",
 *     operationId="getStock",
 *     tags={"Stock"},
 *     summary="Danh sách tồn kho",
 *     description="Lấy danh sách variants với thông tin tồn kho, hỗ trợ phân trang và bộ lọc",
 *     security={{"bearerAuth":{}}},
 *     @OA\Parameter(name="variant_id", in="query", description="Lọc theo variant ID", @OA\Schema(type="integer")),
 *     @OA\Parameter(name="sku", in="query", description="Tìm theo SKU", @OA\Schema(type="string")),
 *     @OA\Parameter(name="style", in="query", description="Lọc theo style", @OA\Schema(type="string")),
 *     @OA\Parameter(name="color", in="query", description="Lọc theo màu", @OA\Schema(type="string")),
 *     @OA\Parameter(name="size", in="query", description="Lọc theo size", @OA\Schema(type="string")),
 *     @OA\Parameter(name="stock_level", in="query", description="Lọc theo mức tồn kho", @OA\Schema(type="string", enum={"in_stock", "low_stock", "out_of_stock"})),
 *     @OA\Parameter(name="active_status", in="query", description="Lọc theo trạng thái active", @OA\Schema(type="string", enum={"active", "inactive"})),
 *     @OA\Response(
 *         response=200,
 *         description="Thành công",
 *         @OA\JsonContent(
 *             @OA\Property(property="code", type="integer", example=200),
 *             @OA\Property(property="status", type="boolean", example=true),
 *             @OA\Property(property="data", type="object",
 *                 @OA\Property(property="items", type="array", @OA\Items(type="object")),
 *                 @OA\Property(property="pagination", type="object")
 *             )
 *         )
 *     ),
 *     @OA\Response(response=401, description="Chưa xác thực")
 * )
 *
 * @OA\Get(
 *     path="/api/stock/filter-options",
 *     operationId="getStockFilterOptions",
 *     tags={"Stock"},
 *     summary="Tùy chọn bộ lọc",
 *     description="Lấy danh sách các giá trị có thể lọc (styles, colors, sizes)",
 *     security={{"bearerAuth":{}}},
 *     @OA\Response(
 *         response=200,
 *         description="Thành công",
 *         @OA\JsonContent(
 *             @OA\Property(property="code", type="integer", example=200),
 *             @OA\Property(property="status", type="boolean", example=true),
 *             @OA\Property(property="data", type="object",
 *                 @OA\Property(property="styles", type="array", @OA\Items(type="string", example="Gildan 5000")),
 *                 @OA\Property(property="colors", type="array", @OA\Items(type="string", example="Black")),
 *                 @OA\Property(property="sizes", type="array", @OA\Items(type="string", example="M"))
 *             )
 *         )
 *     ),
 *     @OA\Response(response=401, description="Chưa xác thực")
 * )
 *
 * @OA\Put(
 *     path="/api/stock/variants/{id}",
 *     operationId="updateStockVariant",
 *     tags={"Stock"},
 *     summary="Cập nhật tồn kho variant",
 *     description="Cập nhật số lượng tồn kho cho một variant cụ thể",
 *     security={{"bearerAuth":{}}},
 *     @OA\Parameter(name="id", in="path", required=true, description="ID của variant", @OA\Schema(type="integer")),
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\JsonContent(
 *             @OA\Property(property="quantity", type="integer", example=100, description="Số lượng tồn kho mới"),
 *             @OA\Property(property="note", type="string", example="Nhập hàng mới", description="Ghi chú thay đổi")
 *         )
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="Cập nhật thành công",
 *         @OA\JsonContent(
 *             @OA\Property(property="code", type="integer", example=200),
 *             @OA\Property(property="status", type="boolean", example=true),
 *             @OA\Property(property="message", type="string", example="Variant updated successfully")
 *         )
 *     ),
 *     @OA\Response(response=404, description="Không tìm thấy variant")
 * )
 *
 * @OA\Get(
 *     path="/api/stock/variants/{id}/history",
 *     operationId="getStockVariantHistory",
 *     tags={"Stock"},
 *     summary="Lịch sử thay đổi tồn kho",
 *     description="Lấy 20 thay đổi gần nhất của một variant",
 *     security={{"bearerAuth":{}}},
 *     @OA\Parameter(name="id", in="path", required=true, description="ID của variant", @OA\Schema(type="integer")),
 *     @OA\Response(
 *         response=200,
 *         description="Thành công",
 *         @OA\JsonContent(
 *             @OA\Property(property="code", type="integer", example=200),
 *             @OA\Property(property="status", type="boolean", example=true),
 *             @OA\Property(property="data", type="array", @OA\Items(type="object",
 *                 @OA\Property(property="id", type="integer"),
 *                 @OA\Property(property="old_quantity", type="integer"),
 *                 @OA\Property(property="new_quantity", type="integer"),
 *                 @OA\Property(property="change_type", type="string"),
 *                 @OA\Property(property="note", type="string"),
 *                 @OA\Property(property="created_at", type="string", format="date-time")
 *             ))
 *         )
 *     ),
 *     @OA\Response(response=404, description="Không tìm thấy variant")
 * )
 *
 * @OA\Post(
 *     path="/api/stock/bulk-update",
 *     operationId="bulkUpdateStock",
 *     tags={"Stock"},
 *     summary="Cập nhật hàng loạt",
 *     description="Cập nhật tồn kho cho nhiều variants cùng lúc",
 *     security={{"bearerAuth":{}}},
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\JsonContent(
 *             @OA\Property(property="updates", type="array", @OA\Items(type="object",
 *                 @OA\Property(property="variant_id", type="integer", example=1),
 *                 @OA\Property(property="quantity", type="integer", example=50)
 *             )),
 *             @OA\Property(property="note", type="string", example="Bulk update từ nhập kho")
 *         )
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="Cập nhật thành công",
 *         @OA\JsonContent(
 *             @OA\Property(property="code", type="integer", example=200),
 *             @OA\Property(property="status", type="boolean", example=true),
 *             @OA\Property(property="message", type="string", example="Bulk update completed"),
 *             @OA\Property(property="data", type="object",
 *                 @OA\Property(property="updated_count", type="integer", example=10),
 *                 @OA\Property(property="failed_count", type="integer", example=0)
 *             )
 *         )
 *     ),
 *     @OA\Response(response=400, description="Validation lỗi")
 * )
 *
 * @OA\Post(
 *     path="/api/stock/imports",
 *     operationId="importStock",
 *     tags={"Stock"},
 *     summary="Import tồn kho từ file",
 *     description="Import dữ liệu tồn kho từ file CSV/Excel",
 *     security={{"bearerAuth":{}}},
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\MediaType(
 *             mediaType="multipart/form-data",
 *             @OA\Schema(
 *                 @OA\Property(property="file", type="string", format="binary", description="File CSV/Excel")
 *             )
 *         )
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="Import thành công",
 *         @OA\JsonContent(
 *             @OA\Property(property="code", type="integer", example=200),
 *             @OA\Property(property="status", type="boolean", example=true),
 *             @OA\Property(property="message", type="string", example="Stock imported successfully"),
 *             @OA\Property(property="data", type="object",
 *                 @OA\Property(property="imported", type="integer", example=100),
 *                 @OA\Property(property="failed", type="integer", example=2)
 *             )
 *         )
 *     ),
 *     @OA\Response(response=400, description="File không hợp lệ")
 * )
 *
 * @OA\Get(
 *     path="/api/stock/exports",
 *     operationId="exportStock",
 *     tags={"Stock"},
 *     summary="Export tồn kho",
 *     description="Xuất dữ liệu tồn kho ra file CSV",
 *     security={{"bearerAuth":{}}},
 *     @OA\Parameter(name="style", in="query", description="Lọc theo style", @OA\Schema(type="string")),
 *     @OA\Parameter(name="color", in="query", description="Lọc theo màu", @OA\Schema(type="string")),
 *     @OA\Parameter(name="size", in="query", description="Lọc theo size", @OA\Schema(type="string")),
 *     @OA\Response(
 *         response=200,
 *         description="Export thành công",
 *         @OA\JsonContent(
 *             @OA\Property(property="code", type="integer", example=200),
 *             @OA\Property(property="status", type="boolean", example=true),
 *             @OA\Property(property="data", type="object",
 *                 @OA\Property(property="csv", type="string", description="CSV content"),
 *                 @OA\Property(property="filename", type="string", example="stock_export_2024-01-01.csv")
 *             )
 *         )
 *     )
 * )
 *
 * ==================== STOCK AUDIT LOGS ====================
 *
 * @OA\Get(
 *     path="/api/stock/audit-logs",
 *     operationId="getStockAuditLogs",
 *     tags={"Stock - Audit Logs"},
 *     summary="Lấy danh sách audit logs",
 *     description="Lấy lịch sử thay đổi stock với phân trang và bộ lọc. Hiển thị tất cả các thay đổi stock (increase, decrease, adjust, map, restore, manual) với thông tin chi tiết về variant, product, user thực hiện.",
 *     security={{"bearerAuth":{}}},
 *     @OA\Parameter(name="page", in="query", description="Số trang (mặc định: 1)", @OA\Schema(type="integer", default=1)),
 *     @OA\Parameter(name="per_page", in="query", description="Số lượng/trang (mặc định: 20)", @OA\Schema(type="integer", default=20)),
 *     @OA\Parameter(name="variant_id", in="query", description="Lọc theo Product Variant ID", @OA\Schema(type="integer")),
 *     @OA\Parameter(name="action", in="query", description="Lọc theo loại action", @OA\Schema(type="string", enum={"increase", "decrease", "adjust", "map", "restore", "manual"})),
 *     @OA\Parameter(name="user_id", in="query", description="Lọc theo User ID thực hiện", @OA\Schema(type="integer")),
 *     @OA\Parameter(name="style", in="query", description="Lọc theo style sản phẩm", @OA\Schema(type="string")),
 *     @OA\Parameter(name="color", in="query", description="Lọc theo màu sắc variant", @OA\Schema(type="string")),
 *     @OA\Parameter(name="size", in="query", description="Lọc theo size variant", @OA\Schema(type="string")),
 *     @OA\Parameter(name="order_id", in="query", description="Lọc theo Order ID liên quan", @OA\Schema(type="integer")),
 *     @OA\Parameter(name="date_from", in="query", description="Từ ngày (YYYY-MM-DD)", @OA\Schema(type="string", format="date")),
 *     @OA\Parameter(name="date_to", in="query", description="Đến ngày (YYYY-MM-DD)", @OA\Schema(type="string", format="date")),
 *     @OA\Response(
 *         response=200,
 *         description="Lấy danh sách audit logs thành công",
 *         @OA\JsonContent(
 *             @OA\Property(property="code", type="integer", example=200),
 *             @OA\Property(property="status", type="boolean", example=true),
 *             @OA\Property(property="message", type="string", example="Audit logs retrieved successfully"),
 *             @OA\Property(property="data", type="object",
 *                 @OA\Property(property="logs", type="array", @OA\Items(type="object",
 *                     @OA\Property(property="id", type="integer", example=1),
 *                     @OA\Property(property="variant_id", type="integer", example=123),
 *                     @OA\Property(property="variant", type="object", nullable=true,
 *                         @OA\Property(property="color", type="string", example="Black"),
 *                         @OA\Property(property="size", type="string", example="L"),
 *                         @OA\Property(property="sku", type="string", example="GIL-18000-BLK-L")
 *                     ),
 *                     @OA\Property(property="product", type="object", nullable=true,
 *                         @OA\Property(property="id", type="integer", example=1),
 *                         @OA\Property(property="name", type="string", example="Gildan 18000"),
 *                         @OA\Property(property="brand", type="string", example="Gildan"),
 *                         @OA\Property(property="style", type="string", example="18000")
 *                     ),
 *                     @OA\Property(property="user", type="object", nullable=true,
 *                         @OA\Property(property="id", type="integer", example=1),
 *                         @OA\Property(property="username", type="string", example="admin"),
 *                         @OA\Property(property="email", type="string", example="admin@example.com")
 *                     ),
 *                     @OA\Property(property="action", type="string", example="decrease"),
 *                     @OA\Property(property="before_quantity", type="integer", example=100),
 *                     @OA\Property(property="after_quantity", type="integer", example=99),
 *                     @OA\Property(property="change", type="integer", example=-1),
 *                     @OA\Property(property="reason", type="string", nullable=true, example="Order #65 - Item mapped"),
 *                     @OA\Property(property="metadata", type="object", nullable=true),
 *                     @OA\Property(property="created_at", type="string", format="date-time")
 *                 )),
 *                 @OA\Property(property="pagination", type="object",
 *                     @OA\Property(property="current_page", type="integer", example=1),
 *                     @OA\Property(property="last_page", type="integer", example=10),
 *                     @OA\Property(property="per_page", type="integer", example=20),
 *                     @OA\Property(property="total", type="integer", example=200),
 *                     @OA\Property(property="from", type="integer", example=1),
 *                     @OA\Property(property="to", type="integer", example=20)
 *                 )
 *             )
 *         )
 *     ),
 *     @OA\Response(response=401, description="Chưa đăng nhập"),
 *     @OA\Response(response=500, description="Server error")
 * )
 *
 * @OA\Get(
 *     path="/api/stock/audit-logs/filter-options",
 *     operationId="getAuditLogFilterOptions",
 *     tags={"Stock - Audit Logs"},
 *     summary="Lấy các tùy chọn filter",
 *     description="Lấy danh sách các giá trị có thể chọn cho bộ lọc audit logs: styles, colors, sizes, actions.",
 *     security={{"bearerAuth":{}}},
 *     @OA\Response(
 *         response=200,
 *         description="Lấy filter options thành công",
 *         @OA\JsonContent(
 *             @OA\Property(property="code", type="integer", example=200),
 *             @OA\Property(property="status", type="boolean", example=true),
 *             @OA\Property(property="data", type="object",
 *                 @OA\Property(property="styles", type="array", @OA\Items(type="string")),
 *                 @OA\Property(property="colors", type="array", @OA\Items(type="string")),
 *                 @OA\Property(property="sizes", type="array", @OA\Items(type="string")),
 *                 @OA\Property(property="actions", type="array", @OA\Items(type="string"), example={"increase", "decrease", "adjust", "map", "restore", "manual"})
 *             )
 *         )
 *     ),
 *     @OA\Response(response=401, description="Chưa đăng nhập"),
 *     @OA\Response(response=500, description="Server error")
 * )
 *
 * @OA\Get(
 *     path="/api/stock/audit-logs/check-variant",
 *     operationId="checkVariantProductions",
 *     tags={"Stock - Audit Logs"},
 *     summary="Kiểm tra productions của variant",
 *     description="Kiểm tra danh sách các productions (đơn hàng) liên quan đến một product variant.",
 *     security={{"bearerAuth":{}}},
 *     @OA\Parameter(name="variant_id", in="query", required=true, description="Product Variant ID cần kiểm tra", @OA\Schema(type="integer")),
 *     @OA\Response(
 *         response=200,
 *         description="Lấy danh sách productions thành công",
 *         @OA\JsonContent(
 *             @OA\Property(property="code", type="integer", example=200),
 *             @OA\Property(property="status", type="boolean", example=true),
 *             @OA\Property(property="data", type="object",
 *                 @OA\Property(property="variant_id", type="integer", example=123),
 *                 @OA\Property(property="productions", type="array", @OA\Items(type="object",
 *                     @OA\Property(property="production_id", type="integer", example=1),
 *                     @OA\Property(property="status", type="string", example="mapped"),
 *                     @OA\Property(property="quantity", type="integer", example=1),
 *                     @OA\Property(property="order_id", type="integer", example=65),
 *                     @OA\Property(property="order_ref", type="string", example="REF-12345")
 *                 ))
 *             )
 *         )
 *     ),
 *     @OA\Response(response=400, description="Thiếu variant_id",
 *         @OA\JsonContent(
 *             @OA\Property(property="code", type="integer", example=400),
 *             @OA\Property(property="status", type="boolean", example=false),
 *             @OA\Property(property="message", type="string", example="Variant ID is required")
 *         )
 *     ),
 *     @OA\Response(response=401, description="Chưa đăng nhập"),
 *     @OA\Response(response=500, description="Server error")
 * )
 */
class StockDocs
{
    // Stock and Stock Audit Logs API Documentation
}
