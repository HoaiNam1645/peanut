<?php

namespace App\Swagger\Docs;

/**
 * ==================== ORDER API DOCUMENTATION ====================
 * 
 * @OA\Post(
 *     path="/api/update-label",
 *     operationId="webhookUpdateLabel",
 *     tags={"Orders"},
 *     summary="Webhook cập nhật label",
 *     description="Webhook từ bên ngoài để cập nhật tracking và label cho đơn hàng. Không cần authentication.",
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\JsonContent(
 *             required={"order_id"},
 *             @OA\Property(property="order_id", type="integer", example=123, description="ID đơn hàng"),
 *             @OA\Property(property="tracking_id", type="string", example="1Z999AA10123456784", description="Mã tracking"),
 *             @OA\Property(property="tracking_link", type="string", format="url", example="https://tracking.example.com/1Z999AA10123456784"),
 *             @OA\Property(property="label_url", type="string", format="url", example="https://example.com/label.pdf")
 *         )
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="Cập nhật thành công",
 *         @OA\JsonContent(ref="#/components/schemas/ApiResponse")
 *     ),
 *     @OA\Response(response=400, description="Dữ liệu không hợp lệ", @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse"))
 * )
 *
 * @OA\Get(
 *     path="/api/orders",
 *     operationId="getOrders",
 *     tags={"Orders"},
 *     summary="Danh sách đơn hàng",
 *     description="Lấy danh sách đơn hàng với phân trang và bộ lọc. Seller chỉ thấy đơn của mình, Admin/Staff thấy tất cả.",
 *     security={{"bearerAuth":{}}},
 *     @OA\Parameter(name="per_page", in="query", description="Số lượng/trang (mặc định: 20)", @OA\Schema(type="integer", default=20)),
 *     @OA\Parameter(name="page", in="query", description="Số trang", @OA\Schema(type="integer", default=1)),
 *     @OA\Parameter(name="ref_id", in="query", description="Tìm theo mã tham chiếu", @OA\Schema(type="string")),
 *     @OA\Parameter(name="seller_ref", in="query", description="Tìm theo mã tham chiếu seller", @OA\Schema(type="string")),
 *     @OA\Parameter(name="order_stt", in="query", description="Tìm theo STT đơn (nhiều ID cách nhau bởi dấu cách/phẩy)", @OA\Schema(type="string")),
 *     @OA\Parameter(name="category", in="query", description="Lọc theo loại danh mục (embroidery/print)", @OA\Schema(type="string", enum={"embroidery", "print"})),
 *     @OA\Parameter(name="order_type", in="query", description="Lọc theo loại đơn hàng cụ thể (Shirt, Tumbler...)", @OA\Schema(type="string")),
 *     @OA\Parameter(name="search", in="query", description="Tìm chung (ref_id, seller_ref, order_stt, tracking_id)", @OA\Schema(type="string")),
 *     @OA\Parameter(
 *         name="fulfill_status", 
 *         in="query", 
 *         description="Lọc theo trạng thái fulfillment", 
 *         @OA\Schema(type="string", enum={"new_order", "on_hold", "producing", "qc_pass", "packed", "shipped", "delivered", "cancelled"})
 *     ),
 *     @OA\Parameter(name="payment_status", in="query", description="Lọc theo trạng thái thanh toán", @OA\Schema(type="string", enum={"pending", "paid", "failed"})),
 *     @OA\Parameter(name="seller_id", in="query", description="Lọc theo seller (chỉ Admin/Staff)", @OA\Schema(type="integer")),
 *     @OA\Parameter(name="store_id", in="query", description="Lọc theo store", @OA\Schema(type="integer")),
 *     @OA\Parameter(name="date_from", in="query", description="Từ ngày (YYYY-MM-DD)", @OA\Schema(type="string", format="date")),
 *     @OA\Parameter(name="date_to", in="query", description="Đến ngày (YYYY-MM-DD)", @OA\Schema(type="string", format="date")),
 *     @OA\Parameter(name="sort_by", in="query", description="Sắp xếp theo", @OA\Schema(type="string", default="created_at")),
 *     @OA\Parameter(name="sort_order", in="query", description="Thứ tự sắp xếp", @OA\Schema(type="string", enum={"asc", "desc"}, default="desc")),
 *     @OA\Response(
 *         response=200,
 *         description="Lấy danh sách thành công",
 *         @OA\JsonContent(ref="#/components/schemas/OrderListResponse")
 *     ),
 *     @OA\Response(response=401, description="Chưa đăng nhập", @OA\JsonContent(ref="#/components/schemas/UnauthorizedResponse"))
 * )
 *
 * @OA\Get(
 *     path="/api/orders/ids",
 *     operationId="getOrderIds",
 *     tags={"Orders"},
 *     summary="Lấy danh sách Order ID",
 *     description="Lấy danh sách toàn bộ ID đơn hàng thỏa mãn bộ lọc (dùng cho export hoặc bulk actions).",
 *     security={{"bearerAuth":{}}},
 *     @OA\Parameter(name="ref_id", in="query", description="Tìm theo mã tham chiếu", @OA\Schema(type="string")),
 *     @OA\Parameter(name="seller_ref", in="query", description="Tìm theo mã tham chiếu seller", @OA\Schema(type="string")),
 *     @OA\Parameter(name="order_stt", in="query", description="Tìm theo STT đơn (nhiều ID cách nhau bởi dấu cách/phẩy)", @OA\Schema(type="string")),
 *     @OA\Parameter(
 *         name="fulfill_status", 
 *         in="query", 
 *         description="Lọc theo trạng thái fulfillment", 
 *         @OA\Schema(type="string", enum={"new_order", "on_hold", "producing", "qc_pass", "packed", "shipped", "delivered", "cancelled"})
 *     ),
 *     @OA\Parameter(name="payment_status", in="query", description="Lọc theo trạng thái thanh toán", @OA\Schema(type="string", enum={"pending", "paid", "failed"})),
 *     @OA\Parameter(name="seller_id", in="query", description="Lọc theo seller (chỉ Admin/Staff)", @OA\Schema(type="integer")),
 *     @OA\Parameter(name="store_id", in="query", description="Lọc theo store", @OA\Schema(type="integer")),
 *     @OA\Parameter(name="date_from", in="query", description="Từ ngày (YYYY-MM-DD)", @OA\Schema(type="string", format="date")),
 *     @OA\Parameter(name="date_to", in="query", description="Đến ngày (YYYY-MM-DD)", @OA\Schema(type="string", format="date")),
 *     @OA\Response(
 *         response=200,
 *         description="Thành công",
 *         @OA\JsonContent(
 *             type="object",
 *             @OA\Property(property="code", type="integer", example=200),
 *             @OA\Property(property="success", type="boolean", example=true),
 *             @OA\Property(property="data", type="array", @OA\Items(type="integer", example=123)),
 *             @OA\Property(property="count", type="integer", example=50),
 *             @OA\Property(property="message", type="string", example="Order IDs retrieved successfully")
 *         )
 *     ),
 *     @OA\Response(response=401, description="Chưa đăng nhập", @OA\JsonContent(ref="#/components/schemas/UnauthorizedResponse"))
 * )
 *
 * @OA\Post(
 *     path="/api/orders/export-urls",
 *     operationId="exportOrderUrls",
 *     tags={"Orders"},
 *     summary="Export URLs (PES, EMB, QR)",
 *     description="Lấy danh sách URL file design (PES, EMB) và QR code cho danh sách đơn hàng đã chọn.",
 *     security={{"bearerAuth":{}}},
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\JsonContent(
 *             required={"ids"},
 *             @OA\Property(property="ids", type="array", @OA\Items(type="integer"), example={1, 2, 3}, description="Danh sách Order ID cần lấy URL")
 *         )
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="Thành công",
 *         @OA\JsonContent(
 *             type="object",
 *             @OA\Property(property="code", type="integer", example=200),
 *             @OA\Property(property="status", type="boolean", example=true),
 *             @OA\Property(property="data", type="array",
 *                 @OA\Items(
 *                     type="object",
 *                     @OA\Property(property="order_id", type="integer", example=123),
 *                     @OA\Property(property="items", type="array",
 *                         @OA\Items(
 *                             type="object",
 *                             @OA\Property(property="pes_url", type="string", format="url", example="https://storage.example.com/file.pes", description="Link file PES (nếu có)"),
 *                             @OA\Property(property="qr_url", type="string", format="url", example="https://storage.example.com/qr.png", description="Link QR code (lấy cái đầu tiên nếu có nhiều)"),
 *                             @OA\Property(property="emb_url", type="string", format="url", example="https://storage.example.com/file.emb", description="Link file EMB (nếu có)")
 *                         )
 *                     )
 *                 )
 *             )
 *         )
 *     ),
 *     @OA\Response(response=400, description="Dữ liệu không hợp lệ (thiếu ids)", @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse")),
 *     @OA\Response(response=401, description="Chưa đăng nhập", @OA\JsonContent(ref="#/components/schemas/UnauthorizedResponse"))
 * )
 *
 * @OA\Post(
 *     path="/api/orders/create",
 *     operationId="createOrder",
 *     tags={"Orders"},
 *     summary="Tạo đơn hàng",
 *     description="Tạo đơn hàng mới. Hỗ trợ 3 loại: NO_DESIGN, LABEL_SHIP, SELLER_SHIP. Yêu cầu quyền Seller. Chọn example từ dropdown để xem body mẫu.",
 *     security={{"bearerAuth":{}}},
 *     @OA\RequestBody(
 *         required=true,
 *         description="Thông tin đơn hàng - Chọn example phù hợp với loại đơn",
 *         @OA\JsonContent(
 *             @OA\Examples(
 *                 example="NO_DESIGN",
 *                 summary="Đơn NO_DESIGN - Không có design riêng, cần địa chỉ",
 *                 value={
 *                     "api_key": "shpat_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxx",
 *                     "order_type": "NO_DESIGN",
 *                     "ref_id": "ORDER-12345",
 *                     "seller_ref": "SELLER-REF-123",
 *                     "order_status": "pending",
 *                     "shipping_method": "standard",
 *                     "shipping_service": "USPS",
 *                     "shipping_label": null,
 *                     "fulfillment_priority": "normal",
 *                     "note": "Ghi chú đơn hàng",
 *                     "address": {
 *                         "name": "John Doe",
 *                         "phone": "1234567890",
 *                         "street1": "123 Main St",
 *                         "street2": "Apt 4B",
 *                         "city": "New York",
 *                         "state": "NY",
 *                         "zip": "10001",
 *                         "country": "US"
 *                     },
 *                     "line_items": {
 *                         {
 *                             "variant_id": "VARIANT-001",
 *                             "product_name": "T-Shirt Classic",
 *                             "quantity": 2,
 *                             "mockup": "https://example.com/mockup.jpg",
 *                             "mockup_back": "https://example.com/mockup-back.jpg",
 *                             "print_files": null
 *                         }
 *                     }
 *                 }
 *             ),
 *             @OA\Examples(
 *                 example="LABEL_SHIP",
 *                 summary="Đơn LABEL_SHIP - Có sẵn nhãn vận chuyển, KHÔNG cần địa chỉ",
 *                 value={
 *                     "api_key": "shpat_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxx",
 *                     "order_type": "LABEL_SHIP",
 *                     "ref_id": "ORDER-12345",
 *                     "seller_ref": "SELLER-REF-123",
 *                     "order_status": "new_order",
 *                     "product_type": "Tumbler",
 *                     "shipping_method": "standard",
 *                     "shipping_service": "USPS",
 *                     "shipping_label": "https://example.com/label.pdf",
 *                     "fulfillment_priority": "normal",
 *                     "note": "Ghi chú đơn hàng",
 *                     "address": null,
 *                     "line_items": {
 *                         {
 *                             "variant_id": "VARIANT-001",
 *                             "product_name": "T-Shirt Classic",
 *                             "quantity": 2,
 *                             "mockup": "https://example.com/mockup.jpg",
 *                             "mockup_back": "https://example.com/mockup-back.jpg",
 *                             "print_files": {
 *                                 {"key": "front", "url": "https://example.com/front.dst", "url_emb": null, "url_pes": "https://example.com/front.pes", "embroidery_type": null},
 *                                 {"key": "back", "url": "https://example.com/back.dst", "url_emb": null, "url_pes": "https://example.com/back.pes", "embroidery_type": null}
 *                             }
 *                         }
 *                     }
 *                 }
 *             ),
 *             @OA\Examples(
 *                 example="SELLER_SHIP",
 *                 summary="Don SELLER_SHIP - Seller tu ship, can dia chi + print_files",
 *                 value={
 *                     "api_key": "shpat_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxx",
 *                     "order_type": "SELLER_SHIP",
 *                     "ref_id": "ORDER-12345",
 *                     "seller_ref": "SELLER-REF-123",
 *                     "order_status": "new_order",
 *                     "product_type": "Shirt",
 *                     "shipping_method": "standard",
 *                     "shipping_service": "USPS",
 *                     "shipping_label": null,
 *                     "fulfillment_priority": "normal",
 *                     "note": "Ghi chu don hang",
 *                     "address": {
 *                         "name": "John Doe",
 *                         "phone": "1234567890",
 *                         "street1": "123 Main St",
 *                         "street2": "Apt 4B",
 *                         "city": "New York",
 *                         "state": "NY",
 *                         "zip": "10001",
 *                         "country": "US"
 *                     },
 *                     "line_items": {
 *                         {
 *                             "variant_id": "VARIANT-001",
 *                             "product_name": "T-Shirt Classic",
 *                             "quantity": 2,
 *                             "mockup": "https://example.com/mockup.jpg",
 *                             "mockup_back": "https://example.com/mockup-back.jpg",
 *                             "print_files": {
 *                                 {"key": "front", "url": "https://example.com/front.dst", "url_emb": "https://example.com/front.emb", "url_pes": "https://example.com/front.pes", "embroidery_type": "standard"},
 *                                 {"key": "back", "url": "https://example.com/back.dst", "url_emb": null, "url_pes": "https://example.com/back.pes", "embroidery_type": "metallic"}
 *                             }
 *                         }
 *                     }
 *                 }
 *             )
 *         )
 *     ),
 *     @OA\Response(
 *         response=201,
 *         description="Tạo đơn thành công",
 *         @OA\JsonContent(
 *             @OA\Property(property="code", type="integer", example=201),
 *             @OA\Property(property="status", type="boolean", example=true),
 *             @OA\Property(property="message", type="string", example="Tạo đơn hàng thành công"),
 *             @OA\Property(property="data", type="object",
 *                 @OA\Property(property="order_id", type="integer", example=123),
 *                 @OA\Property(property="ref_id", type="string", example="ORDER-12345"),
 *                 @OA\Property(property="total_cost", type="number", example=50.00)
 *             )
 *         )
 *     ),
 *     @OA\Response(response=400, description="Dữ liệu không hợp lệ", @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse")),
 *     @OA\Response(response=401, description="Chưa đăng nhập", @OA\JsonContent(ref="#/components/schemas/UnauthorizedResponse")),
 *     @OA\Response(response=403, description="Không có quyền tạo đơn", @OA\JsonContent(ref="#/components/schemas/ForbiddenResponse"))
 * )
 *
 * @OA\Put(
 *     path="/api/orders/update",
 *     operationId="updateOrder",
 *     tags={"Orders"},
 *     summary="Cập nhật đơn hàng",
 *     description="Cập nhật thông tin đơn hàng. Seller chỉ có thể cập nhật đơn ở trạng thái new_order hoặc on_hold. Admin/Staff có thể cập nhật mọi trạng thái. Chọn example từ dropdown.",
 *     security={{"bearerAuth":{}}},
 *     @OA\RequestBody(
 *         required=true,
 *         description="Chọn loại đơn để xem body mẫu",
 *         @OA\JsonContent(
 *             @OA\Examples(
 *                 example="LABEL_SHIP",
 *                 summary="Cập nhật đơn LABEL_SHIP",
 *                 value={
 *                     "id": 123,
 *                     "order_type": "label_ship",
 *                     "api_key": "shpat_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxx",
 *                     "ref_id": "ORDER-12345",
 *                     "seller_ref": "SELLER-REF-123",
 *                     "order_status": "new_order",
 *                     "product_type": "Tumbler",
 *                     "shipping_method": "standard",
 *                     "shipping_service": "USPS",
 *                     "shipping_label": "https://example.com/new-label.pdf",
 *                     "note": "Cập nhật ghi chú",
 *                     "line_items": {
 *                         {
 *                             "variant_id": "VARIANT-001",
 *                             "product_name": "T-Shirt Classic",
 *                             "quantity": 2,
 *                             "mockup": "https://example.com/mockup.jpg",
 *                             "print_files": {
 *                                 {"key": "front", "url": "https://example.com/front.dst"}
 *                             }
 *                         }
 *                     }
 *                 }
 *             ),
 *             @OA\Examples(
 *                 example="SELLER_SHIP",
 *                 summary="Cập nhật đơn SELLER_SHIP",
 *                 value={
 *                     "id": 123,
 *                     "order_type": "seller_ship",
 *                     "api_key": "shpat_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxx",
 *                     "ref_id": "ORDER-12345",
 *                     "seller_ref": "SELLER-REF-123",
 *                     "order_status": "new_order",
 *                     "shipping_method": "standard",
 *                     "shipping_service": "USPS",
 *                     "note": "Cập nhật ghi chú",
 *                     "address": {
 *                         "name": "John Doe",
 *                         "phone": "1234567890",
 *                         "street1": "456 New St",
 *                         "street2": "Suite 100",
 *                         "city": "Los Angeles",
 *                         "state": "CA",
 *                         "zip": "90001",
 *                         "country": "US"
 *                     },
 *                     "line_items": {
 *                         {
 *                             "variant_id": "VARIANT-001",
 *                             "product_name": "T-Shirt Classic",
 *                             "quantity": 2,
 *                             "mockup": "https://example.com/mockup.jpg",
 *                             "print_files": {
 *                                 {"key": "front", "url": "https://example.com/front.dst"}
 *                             }
 *                         }
 *                     }
 *                 }
 *             )
 *         )
 *     ),
 *     @OA\Response(response=200, description="Cập nhật thành công", @OA\JsonContent(ref="#/components/schemas/ApiResponse")),
 *     @OA\Response(response=400, description="Dữ liệu không hợp lệ", @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse")),
 *     @OA\Response(response=401, description="Chưa đăng nhập", @OA\JsonContent(ref="#/components/schemas/UnauthorizedResponse")),
 *     @OA\Response(response=404, description="Không tìm thấy đơn hàng", @OA\JsonContent(ref="#/components/schemas/NotFoundResponse"))
 * )
 *
 * @OA\Get(
 *     path="/api/orders/fulfill-statuses",
 *     operationId="getFulfillStatuses",
 *     tags={"Orders"},
 *     summary="Danh sách trạng thái fulfillment",
 *     description="Lấy tất cả các trạng thái fulfillment có thể có",
 *     security={{"bearerAuth":{}}},
 *     @OA\Response(
 *         response=200,
 *         description="Thành công",
 *         @OA\JsonContent(
 *             @OA\Property(property="code", type="integer", example=200),
 *             @OA\Property(property="status", type="boolean", example=true),
 *             @OA\Property(property="message", type="string", example="Lấy danh sách trạng thái thành công"),
 *             @OA\Property(
 *                 property="data",
 *                 type="array",
 *                 @OA\Items(ref="#/components/schemas/FulfillStatusItem")
 *             )
 *         )
 *     ),
 *     @OA\Response(response=401, description="Chưa đăng nhập", @OA\JsonContent(ref="#/components/schemas/UnauthorizedResponse"))
 * )
 *
 * @OA\Put(
 *     path="/api/orders/change-fulfill-status",
 *     operationId="changeFulfillStatus",
 *     tags={"Orders"},
 *     summary="Đổi trạng thái fulfillment",
 *     description="Thay đổi trạng thái fulfillment của đơn hàng. Yêu cầu quyền Staff. Chọn example để xem các trạng thái phổ biến.",
 *     security={{"bearerAuth":{}}},
 *     @OA\RequestBody(
 *         required=true,
 *         description="Chọn trạng thái mới từ dropdown",
 *         @OA\JsonContent(
 *             @OA\Examples(
 *                 example="to_producing",
 *                 summary="Chuyển sang Đang sản xuất",
 *                 value={"order_id": 123, "fulfill_status": "producing"}
 *             ),
 *             @OA\Examples(
 *                 example="to_qc_pass",
 *                 summary="Chuyển sang QC Pass",
 *                 value={"order_id": 123, "fulfill_status": "qc_pass"}
 *             ),
 *             @OA\Examples(
 *                 example="to_packed",
 *                 summary="Chuyển sang Đã đóng gói",
 *                 value={"order_id": 123, "fulfill_status": "packed"}
 *             ),
 *             @OA\Examples(
 *                 example="to_shipped",
 *                 summary="Chuyển sang Đã giao hàng",
 *                 value={"order_id": 123, "fulfill_status": "shipped"}
 *             ),
 *             @OA\Examples(
 *                 example="to_delivered",
 *                 summary="Chuyển sang Đã nhận hàng",
 *                 value={"order_id": 123, "fulfill_status": "delivered"}
 *             ),
 *             @OA\Examples(
 *                 example="to_cancelled",
 *                 summary="Hủy đơn hàng",
 *                 value={"order_id": 123, "fulfill_status": "cancelled"}
 *             ),
 *             @OA\Examples(
 *                 example="to_on_hold",
 *                 summary="Tạm giữ đơn",
 *                 value={"order_id": 123, "fulfill_status": "on_hold"}
 *             )
 *         )
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="Đổi trạng thái thành công",
 *         @OA\JsonContent(
 *             @OA\Property(property="code", type="integer", example=200),
 *             @OA\Property(property="status", type="boolean", example=true),
 *             @OA\Property(property="message", type="string", example="Đổi trạng thái thành công"),
 *             @OA\Property(property="data", type="object",
 *                 @OA\Property(property="order_id", type="integer"),
 *                 @OA\Property(property="old_status", type="string"),
 *                 @OA\Property(property="new_status", type="string")
 *             )
 *         )
 *     ),
 *     @OA\Response(response=400, description="Không thể đổi trạng thái", @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse")),
 *     @OA\Response(response=401, description="Chưa đăng nhập", @OA\JsonContent(ref="#/components/schemas/UnauthorizedResponse")),
 *     @OA\Response(response=403, description="Không có quyền", @OA\JsonContent(ref="#/components/schemas/ForbiddenResponse"))
 * )
 *
 * @OA\Put(
 *     path="/api/orders/change-status-items",
 *     operationId="changeItemStatus",
 *     tags={"Orders"},
 *     summary="Đổi trạng thái item",
 *     description="Thay đổi trạng thái workflow của một item theo vị trí in. Stage được xác định tự động theo vai trò người dùng: Staff → staff, QC → qc, Packing → packing, etc.",
 *     security={{"bearerAuth":{}}},
 *     @OA\RequestBody(
 *         required=true,
 *         description="Chọn vị trí in từ dropdown",
 *         @OA\JsonContent(
 *             @OA\Examples(
 *                 example="front_done",
 *                 summary="Hoàn thành mặt trước (Front)",
 *                 value={"item_id": 123, "meta_key": "front", "status": true}
 *             ),
 *             @OA\Examples(
 *                 example="back_done",
 *                 summary="Hoàn thành mặt sau (Back)",
 *                 value={"item_id": 123, "meta_key": "back", "status": true}
 *             ),
 *             @OA\Examples(
 *                 example="sleeve_left_done",
 *                 summary="Hoàn thành tay trái (Sleeve Left)",
 *                 value={"item_id": 123, "meta_key": "sleeve_left", "status": true}
 *             ),
 *             @OA\Examples(
 *                 example="sleeve_right_done",
 *                 summary="Hoàn thành tay phải (Sleeve Right)",
 *                 value={"item_id": 123, "meta_key": "sleeve_right", "status": true}
 *             ),
 *             @OA\Examples(
 *                 example="neck_done",
 *                 summary="Hoàn thành cổ áo (Neck)",
 *                 value={"item_id": 123, "meta_key": "neck", "status": true}
 *             ),
 *             @OA\Examples(
 *                 example="undo_front",
 *                 summary="Hoàn tác mặt trước",
 *                 value={"item_id": 123, "meta_key": "front", "status": false}
 *             )
 *         )
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="Cập nhật thành công",
 *         @OA\JsonContent(
 *             @OA\Property(property="code", type="integer", example=200),
 *             @OA\Property(property="status", type="boolean", example=true),
 *             @OA\Property(property="message", type="string", example="Cập nhật trạng thái item thành công")
 *         )
 *     ),
 *     @OA\Response(response=400, description="Dữ liệu không hợp lệ", @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse")),
 *     @OA\Response(response=401, description="Chưa đăng nhập", @OA\JsonContent(ref="#/components/schemas/UnauthorizedResponse"))
 * )
 *
 * @OA\Put(
 *     path="/api/orders/qc-reject",
 *     operationId="qcRejectItem",
 *     tags={"Orders"},
 *     summary="QC Reject item",
 *     description="QC reject một item - reset tất cả workflows, unmap stock, trả về support. Chỉ QC hoặc Admin mới có quyền.",
 *     security={{"bearerAuth":{}}},
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\JsonContent(ref="#/components/schemas/QcRejectRequest")
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="Reject thành công",
 *         @OA\JsonContent(
 *             @OA\Property(property="code", type="integer", example=200),
 *             @OA\Property(property="status", type="boolean", example=true),
 *             @OA\Property(property="message", type="string", example="Item đã được reject và gửi về support")
 *         )
 *     ),
 *     @OA\Response(response=403, description="Chỉ QC mới có quyền reject", @OA\JsonContent(ref="#/components/schemas/ForbiddenResponse")),
 *     @OA\Response(response=404, description="Không tìm thấy item", @OA\JsonContent(ref="#/components/schemas/NotFoundResponse"))
 * )
 *
 * @OA\Post(
 *     path="/api/orders/post-label",
 *     operationId="postLabel",
 *     tags={"Orders"},
 *     summary="Post label để convert",
 *     description="Gửi label đến conversion service để xử lý",
 *     security={{"bearerAuth":{}}},
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\JsonContent(
 *             required={"id"},
 *             @OA\Property(property="id", type="integer", example=123, description="ID đơn hàng")
 *         )
 *     ),
 *     @OA\Response(response=200, description="Gửi thành công", @OA\JsonContent(ref="#/components/schemas/ApiResponse")),
 *     @OA\Response(response=400, description="Lỗi", @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse"))
 * )
 *
 * @OA\Get(
 *     path="/api/orders/update-label",
 *     operationId="getUpdateLabel",
 *     tags={"Orders"},
 *     summary="Cập nhật label từ external service",
 *     description="API để cập nhật label và tracking từ dịch vụ bên ngoài. Sử dụng API key để xác thực.",
 *     security={{"bearerAuth":{}}},
 *     @OA\Parameter(name="order_id", in="query", required=true, description="ID đơn hàng", @OA\Schema(type="integer")),
 *     @OA\Parameter(name="tracking_id", in="query", description="Mã tracking", @OA\Schema(type="string")),
 *     @OA\Parameter(name="tracking_link", in="query", description="Link tracking", @OA\Schema(type="string", format="url")),
 *     @OA\Parameter(name="label_url", in="query", description="URL của shipping label", @OA\Schema(type="string", format="url")),
 *     @OA\Response(
 *         response=200,
 *         description="Cập nhật thành công",
 *         @OA\JsonContent(
 *             @OA\Property(property="code", type="integer", example=200),
 *             @OA\Property(property="status", type="boolean", example=true),
 *             @OA\Property(property="message", type="string", example="Label updated successfully")
 *         )
 *     ),
 *     @OA\Response(response=400, description="Dữ liệu không hợp lệ"),
 *     @OA\Response(response=404, description="Không tìm thấy đơn hàng")
 * )
 *
 * @OA\Get(
 *     path="/api/orders/track/{orderId}",
 *     operationId="trackOrder",
 *     tags={"Orders"},
 *     summary="Tracking đơn hàng",
 *     description="Lấy thông tin tracking cho trang scan QR. Có thể lọc theo item cụ thể bằng stt hoặc item_id.",
 *     security={{"bearerAuth":{}}},
 *     @OA\Parameter(name="orderId", in="path", required=true, description="ID đơn hàng", @OA\Schema(type="integer")),
 *     @OA\Parameter(name="stt", in="query", description="Số thứ tự item trong đơn", @OA\Schema(type="integer")),
 *     @OA\Parameter(name="item_id", in="query", description="ID item cụ thể", @OA\Schema(type="integer")),
 *     @OA\Response(
 *         response=200,
 *         description="Thành công",
 *         @OA\JsonContent(
 *             @OA\Property(property="success", type="boolean", example=true),
 *             @OA\Property(property="data", type="object",
 *                 @OA\Property(property="order", type="object", description="Thông tin đơn hàng"),
 *                 @OA\Property(property="items", type="array", @OA\Items(type="object",
 *                     @OA\Property(property="id", type="integer"),
 *                     @OA\Property(property="quantity", type="integer"),
 *                     @OA\Property(property="mockup", type="string", nullable=true),
 *                     @OA\Property(property="mockup_back", type="string", nullable=true),
 *                     @OA\Property(property="product", type="object",
 *                         @OA\Property(property="variant_id", type="string"),
 *                         @OA\Property(property="product_name", type="string"),
 *                         @OA\Property(property="size", type="string"),
 *                         @OA\Property(property="color", type="string"),
 *                         @OA\Property(property="style", type="string"),
 *                         @OA\Property(property="color_image", type="string", nullable=true, description="URL ảnh mockup product theo màu")
 *                     ),
 *                     @OA\Property(property="designs", type="array", @OA\Items(type="object",
 *                         @OA\Property(property="position", type="string", example="front"),
 *                         @OA\Property(property="pdf_url", type="string", nullable=true, description="URL ảnh preview PDF của design"),
 *                         @OA\Property(property="json_url", type="string", nullable=true),
 *                         @OA\Property(property="status", type="integer", example=1, description="Staff workflow status"),
 *                         @OA\Property(property="qc_status", type="integer", example=0),
 *                         @OA\Property(property="packing_status", type="integer", example=0),
 *                         @OA\Property(property="shipout_status", type="integer", example=0),
 *                         @OA\Property(property="stitch_count", type="integer", nullable=true),
 *                         @OA\Property(property="width_mm", type="number", nullable=true),
 *                         @OA\Property(property="height_mm", type="number", nullable=true),
 *                         @OA\Property(property="color_count", type="integer", nullable=true),
 *                         @OA\Property(property="needle_assignment", type="object", nullable=true),
 *                         @OA\Property(property="colors", type="array", @OA\Items(type="object"), nullable=true)
 *                     ))
 *                 )),
 *                 @OA\Property(property="current_item", type="object", nullable=true, description="Item được chọn (nếu có stt hoặc item_id)"),
 *                 @OA\Property(property="total_quantity", type="integer")
 *             )
 *         )
 *     ),
 *     @OA\Response(response=404, description="Không tìm thấy đơn hàng", @OA\JsonContent(ref="#/components/schemas/NotFoundResponse"))
 * )
 *
 * @OA\Get(
 *     path="/api/orders/{id}",
 *     operationId="getOrderById",
 *     tags={"Orders"},
 *     summary="Chi tiết đơn hàng",
 *     description="Lấy thông tin chi tiết đầy đủ của một đơn hàng. Seller chỉ xem được đơn của mình.",
 *     security={{"bearerAuth":{}}},
 *     @OA\Parameter(name="id", in="path", required=true, description="ID đơn hàng", @OA\Schema(type="integer")),
 *     @OA\Response(
 *         response=200,
 *         description="Thành công",
 *         @OA\JsonContent(
 *             @OA\Property(property="code", type="integer", example=200),
 *             @OA\Property(property="status", type="boolean", example=true),
 *             @OA\Property(property="data", ref="#/components/schemas/Order")
 *         )
 *     ),
 *     @OA\Response(response=403, description="Không có quyền xem đơn này", @OA\JsonContent(ref="#/components/schemas/ForbiddenResponse")),
 *     @OA\Response(response=404, description="Không tìm thấy đơn hàng", @OA\JsonContent(ref="#/components/schemas/NotFoundResponse"))
 * )
 *
 * @OA\Get(
 *     path="/api/orders/{id}/timeline",
 *     operationId="getOrderTimeline",
 *     tags={"Orders"},
 *     summary="Timeline đơn hàng",
 *     description="Lấy lịch sử các sự kiện của đơn hàng",
 *     security={{"bearerAuth":{}}},
 *     @OA\Parameter(name="id", in="path", required=true, description="ID đơn hàng", @OA\Schema(type="integer")),
 *     @OA\Response(
 *         response=200,
 *         description="Thành công",
 *         @OA\JsonContent(
 *             @OA\Property(property="code", type="integer", example=200),
 *             @OA\Property(property="status", type="boolean", example=true),
 *             @OA\Property(property="data", type="array", @OA\Items(
 *                 type="object",
 *                 @OA\Property(property="event", type="string", example="status_changed"),
 *                 @OA\Property(property="description", type="string", example="Trạng thái đổi từ new_order sang producing"),
 *                 @OA\Property(property="user", type="string", nullable=true),
 *                 @OA\Property(property="created_at", type="string", format="date-time")
 *             ))
 *         )
 *     ),
 *     @OA\Response(response=404, description="Không tìm thấy đơn hàng", @OA\JsonContent(ref="#/components/schemas/NotFoundResponse"))
 * )
 *
 * @OA\Get(
 *     path="/api/orders/{id}/qr-codes",
 *     operationId="getOrderQRCodes",
 *     tags={"Orders"},
 *     summary="QR Codes của đơn hàng",
 *     description="Lấy danh sách QR codes cho tất cả items trong đơn",
 *     security={{"bearerAuth":{}}},
 *     @OA\Parameter(name="id", in="path", required=true, description="ID đơn hàng", @OA\Schema(type="integer")),
 *     @OA\Response(
 *         response=200,
 *         description="Thành công",
 *         @OA\JsonContent(
 *             @OA\Property(property="code", type="integer", example=200),
 *             @OA\Property(property="status", type="boolean", example=true),
 *             @OA\Property(property="data", type="array", @OA\Items(
 *                 type="object",
 *                 @OA\Property(property="item_id", type="integer"),
 *                 @OA\Property(property="stt", type="integer"),
 *                 @OA\Property(property="qr_url", type="string"),
 *                 @OA\Property(property="track_url", type="string")
 *             ))
 *         )
 *     ),
 *     @OA\Response(response=404, description="Không tìm thấy đơn hàng", @OA\JsonContent(ref="#/components/schemas/NotFoundResponse"))
 * )
 *
 * @OA\Put(
 *     path="/api/orders/remake/file",
 *     operationId="remakeFile",
 *     tags={"Orders"},
 *     summary="Remake PES files",
 *     description="Tạo lại PES files cho các order_item_metas bị lỗi trong quá trình tạo đơn",
 *     security={{"bearerAuth":{}}},
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\JsonContent(ref="#/components/schemas/RemakeFileRequest")
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="Remake thành công",
 *         @OA\JsonContent(
 *             @OA\Property(property="code", type="integer", example=200),
 *             @OA\Property(property="status", type="boolean", example=true),
 *             @OA\Property(property="message", type="string", example="Remake thành công"),
 *             @OA\Property(property="data", type="object",
 *                 @OA\Property(property="order_id", type="integer", example=123),
 *                 @OA\Property(property="processed_metas", type="integer", example=3),
 *                 @OA\Property(property="extra_fee", type="number", example=5.00),
 *                 @OA\Property(property="refund_fee", type="number", example=2.00),
 *                 @OA\Property(property="total_cost", type="number", example=50.00)
 *             )
 *         )
 *     ),
 *     @OA\Response(response=400, description="Không tìm thấy design files hoặc PES files", @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse"))
 * )
 *
 * @OA\Put(
 *     path="/api/orders/remake/qr",
 *     operationId="remakeQr",
 *     tags={"Orders"},
 *     summary="Remake QR Codes",
 *     description="Tạo lại QR codes cho các order items. Tất cả items phải thuộc cùng một đơn hàng.",
 *     security={{"bearerAuth":{}}},
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\JsonContent(ref="#/components/schemas/RemakeQrRequest")
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="Remake thành công",
 *         @OA\JsonContent(
 *             @OA\Property(property="code", type="integer", example=200),
 *             @OA\Property(property="status", type="boolean", example=true),
 *             @OA\Property(property="message", type="string", example="Remake QR thành công"),
 *             @OA\Property(property="data", type="array", @OA\Items(
 *                 type="object",
 *                 @OA\Property(property="item_id", type="integer"),
 *                 @OA\Property(property="qr_url", type="string")
 *             ))
 *         )
 *     ),
 *     @OA\Response(response=400, description="Items không cùng một đơn hàng", @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse"))
 * )
 *
 * @OA\Get(
 *     path="/api/proxy/shipping-label",
 *     operationId="proxyShippingLabel",
 *     tags={"Orders"},
 *     summary="Proxy shipping label",
 *     description="Proxy endpoint để tải shipping label, tránh vấn đề CORS",
 *     @OA\Parameter(name="url", in="query", required=true, description="URL của shipping label", @OA\Schema(type="string", format="url")),
 *     @OA\Response(response=200, description="PDF file content", @OA\MediaType(mediaType="application/pdf")),
 *     @OA\Response(response=400, description="URL không hợp lệ", @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse"))
 * )
 *
 * @OA\Post(
 *     path="/api/orders/upload-video",
 *     operationId="uploadOrderVideo",
 *     tags={"Orders"},
 *     summary="Upload video cho order item",
 *     description="Upload file video cho một order item cụ thể. Videos được lưu trữ trên Backblaze B2 trong thư mục 'video_order'. Có thể upload nhiều videos cho cùng một order item - chúng được lưu dưới dạng JSON array trong column 'pdf' của bảng order_items.",
 *     security={{"bearerAuth":{}}},
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\MediaType(
 *             mediaType="multipart/form-data",
 *             @OA\Schema(
 *                 required={"order_id", "order_item_id", "video"},
 *                 @OA\Property(property="order_id", type="integer", description="ID của order", example=19),
 *                 @OA\Property(property="order_item_id", type="integer", description="ID của order item", example=18),
 *                 @OA\Property(property="video", type="string", format="binary", description="File video (mp4, mov, avi, webm). Tối đa: 100MB")
 *             )
 *         )
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="Upload thành công",
 *         @OA\JsonContent(
 *             @OA\Property(property="code", type="integer", example=200),
 *             @OA\Property(property="status", type="boolean", example=true),
 *             @OA\Property(property="message", type="string", example="Video uploaded successfully"),
 *             @OA\Property(property="data", type="object",
 *                 @OA\Property(property="order_id", type="integer", example=19),
 *                 @OA\Property(property="order_item_id", type="integer", example=18),
 *                 @OA\Property(property="video_url", type="string", example="https://s3.us-east-005.backblazeb2.com/Lemiex-Fulfillment/video_order/19_18_20231231150838.mp4"),
 *                 @OA\Property(property="filename", type="string", example="19_18_20231231150838.mp4")
 *             )
 *         )
 *     ),
 *     @OA\Response(
 *         response=400,
 *         description="Validation failed",
 *         @OA\JsonContent(
 *             @OA\Property(property="code", type="integer", example=400),
 *             @OA\Property(property="status", type="boolean", example=false),
 *             @OA\Property(property="message", type="string", example="Validation failed"),
 *             @OA\Property(property="errors", type="object",
 *                 @OA\Property(property="order_id", type="array", @OA\Items(type="string", example="The order id field is required.")),
 *                 @OA\Property(property="order_item_id", type="array", @OA\Items(type="string", example="The order item id field is required.")),
 *                 @OA\Property(property="video", type="array", @OA\Items(type="string", example="The video field is required."))
 *             )
 *         )
 *     ),
 *     @OA\Response(response=500, description="Server error", @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse"))
 * )
 *
 * @OA\Get(
 *     path="/api/orders/videos/{orderItemId}",
 *     operationId="getOrderVideos",
 *     tags={"Orders"},
 *     summary="Lấy danh sách videos của order item",
 *     description="Lấy danh sách các videos đã upload cho một order item cụ thể từ column 'pdf' trong bảng order_items.",
 *     security={{"bearerAuth":{}}},
 *     @OA\Parameter(name="orderItemId", in="path", required=true, description="ID của order item", @OA\Schema(type="integer", example=63)),
 *     @OA\Response(
 *         response=200,
 *         description="Lấy danh sách videos thành công",
 *         @OA\JsonContent(
 *             @OA\Property(property="code", type="integer", example=200),
 *             @OA\Property(property="status", type="boolean", example=true),
 *             @OA\Property(property="message", type="string", example="Videos retrieved successfully"),
 *             @OA\Property(property="data", type="object",
 *                 @OA\Property(property="order_item_id", type="integer", example=63),
 *                 @OA\Property(property="order_id", type="integer", example=65),
 *                 @OA\Property(property="order_stt", type="string", example="ORD-001"),
 *                 @OA\Property(property="product_name", type="string", example="Gildan 64000 - Black - L"),
 *                 @OA\Property(property="videos", type="array", @OA\Items(type="object",
 *                     @OA\Property(property="url", type="string", example="https://s3.us-east-005.backblazeb2.com/Lemiex-Fulfillment/video_order/65_63_20231231150838.mp4"),
 *                     @OA\Property(property="filename", type="string", example="65_63_20231231150838.mp4"),
 *                     @OA\Property(property="uploaded_at", type="string", example="2023-12-31T15:08:38+00:00")
 *                 )),
 *                 @OA\Property(property="video_count", type="integer", example=2)
 *             )
 *         )
 *     ),
 *     @OA\Response(response=404, description="Order item không tồn tại", @OA\JsonContent(ref="#/components/schemas/NotFoundResponse")),
 *     @OA\Response(response=500, description="Server error", @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse"))
 * )
 *
 * @OA\Get(
 *     path="/api/orders/videos",
 *     operationId="getAllVideos",
 *     tags={"Orders"},
 *     summary="Lấy danh sách tất cả videos",
 *     description="Lấy danh sách tất cả order items có videos với phân trang và bộ lọc. Dùng cho trang Video Gallery để hiển thị tất cả production videos. Videos được nhóm theo order_id và order_item_id.",
 *     security={{"bearerAuth":{}}},
 *     @OA\Parameter(name="page", in="query", description="Số trang (mặc định: 1)", @OA\Schema(type="integer", default=1)),
 *     @OA\Parameter(name="per_page", in="query", description="Số lượng/trang (mặc định: 20, tối đa: 100)", @OA\Schema(type="integer", default=20, maximum=100)),
 *     @OA\Parameter(name="order_id", in="query", description="Lọc theo Order ID", @OA\Schema(type="integer", example=65)),
 *     @OA\Parameter(name="order_item_id", in="query", description="Lọc theo Order Item ID", @OA\Schema(type="integer", example=63)),
 *     @OA\Response(
 *         response=200,
 *         description="Lấy danh sách videos thành công",
 *         @OA\JsonContent(
 *             @OA\Property(property="code", type="integer", example=200),
 *             @OA\Property(property="status", type="boolean", example=true),
 *             @OA\Property(property="message", type="string", example="Videos retrieved successfully"),
 *             @OA\Property(property="data", type="array", @OA\Items(type="object",
 *                 @OA\Property(property="order_item_id", type="integer", example=63, description="ID của order item"),
 *                 @OA\Property(property="order_id", type="integer", example=65, description="ID của order"),
 *                 @OA\Property(property="order_stt", type="string", example="ORD-001", description="Số thứ tự đơn hàng"),
 *                 @OA\Property(property="ref_id", type="string", example="REF-12345", description="Mã tham chiếu"),
 *                 @OA\Property(property="product_name", type="string", example="Gildan 64000 - Black - L", description="Tên sản phẩm"),
 *                 @OA\Property(property="color", type="string", example="Black", nullable=true, description="Màu sắc"),
 *                 @OA\Property(property="size", type="string", example="L", nullable=true, description="Kích thước"),
 *                 @OA\Property(property="videos", type="array", @OA\Items(type="object",
 *                     @OA\Property(property="url", type="string", example="https://s3.us-east-005.backblazeb2.com/Lemiex-Fulfillment/video_order/65_63_20251231083722.mp4"),
 *                     @OA\Property(property="filename", type="string", example="65_63_20251231083722.mp4"),
 *                     @OA\Property(property="uploaded_at", type="string", format="date-time", example="2025-12-31T08:37:27+00:00")
 *                 )),
 *                 @OA\Property(property="video_count", type="integer", example=2, description="Số lượng videos"),
 *                 @OA\Property(property="created_at", type="string", format="date-time", example="2025-12-31 08:30:00")
 *             )),
 *             @OA\Property(property="pagination", type="object",
 *                 @OA\Property(property="current_page", type="integer", example=1),
 *                 @OA\Property(property="per_page", type="integer", example=20),
 *                 @OA\Property(property="total", type="integer", example=150),
 *                 @OA\Property(property="last_page", type="integer", example=8)
 *             )
 *         )
 *     ),
 *     @OA\Response(response=401, description="Chưa đăng nhập", @OA\JsonContent(ref="#/components/schemas/UnauthorizedResponse")),
 *     @OA\Response(response=500, description="Server error", @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse"))
 * )
 *
 * ==================== TUMBLER ORDER API ====================
 *
 * @OA\Post(
 *     path="/api/orders/tumbler/label-ship",
 *     operationId="createTumblerLabelShipOrder",
 *     tags={"Orders - Tumbler"},
 *     summary="Tạo đơn Tumbler với Label Ship",
 *     description="Tạo đơn hàng Tumbler/Mug với nhãn vận chuyển TikTok có sẵn. Khác với embroidery: chỉ chấp nhận file ảnh (PNG/JPG), không có PES/EMB.",
 *     @OA\RequestBody(
 *         required=true,
 *         description="Thông tin đơn hàng Tumbler Label Ship",
 *         @OA\JsonContent(
 *             required={"api_key", "ref_id", "order_status", "shipping_method", "shipping_label", "line_items"},
 *             @OA\Property(property="api_key", type="string", example="shpat_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxx", description="API Key của store"),
 *             @OA\Property(property="ref_id", type="string", example="TUMBLER-12345", description="Mã tham chiếu đơn hàng"),
 *             @OA\Property(property="seller_ref", type="string", example="SELLER-REF-123", nullable=true, description="Mã tham chiếu seller"),
 *             @OA\Property(property="order_status", type="string", enum={"new_order", "test_order", "priority"}, example="new_order"),
 *             @OA\Property(property="shipping_method", type="string", enum={"standard", "express", "priority"}, example="standard"),
 *             @OA\Property(property="shipping_service", type="string", enum={"USPS", "FedEx", "UPS"}, example="USPS", nullable=true),
 *             @OA\Property(property="shipping_label", type="string", format="url", example="https://open-fs.tiktokshops.us/label/12345.pdf", description="URL của TikTok shipping label"),
 *             @OA\Property(property="fulfillment_priority", type="string", enum={"normal", "priority"}, example="normal", nullable=true),
 *             @OA\Property(property="note", type="string", example="Ghi chú đơn hàng", nullable=true),
 *             @OA\Property(property="product_type", type="string", example="Tumbler", nullable=true, description="Mặc định là Tumbler"),
 *             @OA\Property(property="line_items", type="array", description="Danh sách sản phẩm",
 *                 @OA\Items(
 *                     type="object",
 *                     required={"variant_id", "product_name", "quantity", "mockup", "print_files"},
 *                     @OA\Property(property="variant_id", type="string", example="TUMBLER-16OZ-001", description="ID variant sản phẩm"),
 *                     @OA\Property(property="product_name", type="string", example="Tumbler 16oz - Custom Design"),
 *                     @OA\Property(property="quantity", type="integer", example=2, minimum=1),
 *                     @OA\Property(property="mockup", type="string", format="url", example="https://example.com/mockup.png", description="URL ảnh mockup (bắt buộc)"),
 *                     @OA\Property(property="print_files", type="array", description="Files thiết kế (chỉ PNG/JPG)",
 *                         @OA\Items(
 *                             type="object",
 *                             required={"key", "url"},
 *                             @OA\Property(property="key", type="string", enum={"front"}, example="front", description="Vị trí in: front"),
 *                             @OA\Property(property="url", type="string", format="url", example="https://example.com/design.png", description="URL file thiết kế PNG/JPG")
 *                         )
 *                     )
 *                 )
 *             )
 *         )
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="Tạo đơn thành công",
 *         @OA\JsonContent(
 *             @OA\Property(property="code", type="integer", example=200),
 *             @OA\Property(property="status", type="boolean", example=true),
 *             @OA\Property(property="message", type="string", example="Success"),
 *             @OA\Property(property="data", type="object",
 *                 @OA\Property(property="order_id", type="integer", example=123)
 *             )
 *         )
 *     ),
 *     @OA\Response(response=400, description="Dữ liệu không hợp lệ", @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse")),
 *     @OA\Response(response=401, description="API key không hợp lệ hoặc store đã bị khóa", @OA\JsonContent(ref="#/components/schemas/UnauthorizedResponse")),
 *     @OA\Response(response=409, description="Đơn hàng đã tồn tại (trùng ref_id)", @OA\JsonContent(
 *         @OA\Property(property="code", type="integer", example=409),
 *         @OA\Property(property="status", type="boolean", example=false),
 *         @OA\Property(property="message", type="string", example="Order already exists"),
 *         @OA\Property(property="data", type="object",
 *             @OA\Property(property="order_id", type="integer", example=100),
 *             @OA\Property(property="ref_id", type="string", example="TUMBLER-12345"),
 *             @OA\Property(property="fulfill_status", type="string", example="new_order")
 *         )
 *     )),
 *     @OA\Response(response=422, description="Validation lỗi", @OA\JsonContent(
 *         @OA\Property(property="code", type="integer", example=422),
 *         @OA\Property(property="status", type="boolean", example=false),
 *         @OA\Property(property="message", type="string", example="Validation failed"),
 *         @OA\Property(property="errors", type="object")
 *     )),
 *     @OA\Response(response=500, description="Server error", @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse"))
 * )
 *
 * @OA\Post(
 *     path="/api/orders/tumbler/seller-ship",
 *     operationId="createTumblerSellerShipOrder",
 *     tags={"Orders - Tumbler"},
 *     summary="Tạo đơn Tumbler với Seller Ship",
 *     description="Tạo đơn hàng Tumbler/Mug với địa chỉ giao hàng (seller tự ship). Hệ thống sẽ mua label và xử lý vận chuyển. Khác với embroidery: chỉ chấp nhận file ảnh (PNG/JPG), không có PES/EMB.",
 *     @OA\RequestBody(
 *         required=true,
 *         description="Thông tin đơn hàng Tumbler Seller Ship",
 *         @OA\JsonContent(
 *             required={"api_key", "ref_id", "order_status", "shipping_method", "address", "line_items"},
 *             @OA\Property(property="api_key", type="string", example="shpat_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxx", description="API Key của store"),
 *             @OA\Property(property="ref_id", type="string", example="TUMBLER-12345", description="Mã tham chiếu đơn hàng"),
 *             @OA\Property(property="seller_ref", type="string", example="SELLER-REF-123", nullable=true, description="Mã tham chiếu seller"),
 *             @OA\Property(property="order_status", type="string", enum={"new_order", "test_order", "priority"}, example="new_order"),
 *             @OA\Property(property="shipping_method", type="string", enum={"standard", "express", "priority"}, example="standard"),
 *             @OA\Property(property="shipping_service", type="string", enum={"USPS", "FedEx", "UPS"}, example="USPS", nullable=true),
 *             @OA\Property(property="fulfillment_priority", type="string", enum={"normal", "priority"}, example="normal", nullable=true),
 *             @OA\Property(property="note", type="string", example="Ghi chú đơn hàng", nullable=true),
 *             @OA\Property(property="product_type", type="string", example="Tumbler", nullable=true, description="Mặc định là Tumbler"),
 *             @OA\Property(property="address", type="object", description="Địa chỉ giao hàng (bắt buộc)",
 *                 required={"name", "street1", "city", "state", "zip", "country"},
 *                 @OA\Property(property="name", type="string", example="John Doe", description="Tên người nhận"),
 *                 @OA\Property(property="phone", type="string", example="+1234567890", nullable=true),
 *                 @OA\Property(property="street1", type="string", example="123 Main Street"),
 *                 @OA\Property(property="street2", type="string", example="Apt 4B", nullable=true),
 *                 @OA\Property(property="city", type="string", example="New York"),
 *                 @OA\Property(property="state", type="string", example="NY"),
 *                 @OA\Property(property="zip", type="string", example="10001"),
 *                 @OA\Property(property="country", type="string", example="US", description="Mã quốc gia 2 ký tự")
 *             ),
 *             @OA\Property(property="line_items", type="array", description="Danh sách sản phẩm",
 *                 @OA\Items(
 *                     type="object",
 *                     required={"variant_id", "product_name", "quantity", "mockup", "print_files"},
 *                     @OA\Property(property="variant_id", type="string", example="TUMBLER-16OZ-001", description="ID variant sản phẩm"),
 *                     @OA\Property(property="product_name", type="string", example="Tumbler 16oz - Custom Design"),
 *                     @OA\Property(property="quantity", type="integer", example=2, minimum=1),
 *                     @OA\Property(property="mockup", type="string", format="url", example="https://example.com/mockup.png", description="URL ảnh mockup (bắt buộc)"),
 *                     @OA\Property(property="print_files", type="array", description="Files thiết kế (chỉ PNG/JPG)",
 *                         @OA\Items(
 *                             type="object",
 *                             required={"key", "url"},
 *                             @OA\Property(property="key", type="string", enum={"front"}, example="front", description="Vị trí in: front"),
 *                             @OA\Property(property="url", type="string", format="url", example="https://example.com/design.png", description="URL file thiết kế PNG/JPG")
 *                         )
 *                     )
 *                 )
 *             )
 *         )
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="Tạo đơn thành công",
 *         @OA\JsonContent(
 *             @OA\Property(property="code", type="integer", example=200),
 *             @OA\Property(property="status", type="boolean", example=true),
 *             @OA\Property(property="message", type="string", example="Success"),
 *             @OA\Property(property="data", type="object",
 *                 @OA\Property(property="order_id", type="integer", example=123)
 *             )
 *         )
 *     ),
 *     @OA\Response(response=400, description="Dữ liệu không hợp lệ", @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse")),
 *     @OA\Response(response=401, description="API key không hợp lệ hoặc store đã bị khóa", @OA\JsonContent(ref="#/components/schemas/UnauthorizedResponse")),
 *     @OA\Response(response=409, description="Đơn hàng đã tồn tại (trùng ref_id)", @OA\JsonContent(
 *         @OA\Property(property="code", type="integer", example=409),
 *         @OA\Property(property="status", type="boolean", example=false),
 *         @OA\Property(property="message", type="string", example="Order already exists"),
 *         @OA\Property(property="data", type="object",
 *             @OA\Property(property="order_id", type="integer", example=100),
 *             @OA\Property(property="ref_id", type="string", example="TUMBLER-12345"),
 *             @OA\Property(property="fulfill_status", type="string", example="new_order")
 *         )
 *     )),
 *     @OA\Response(response=422, description="Validation lỗi", @OA\JsonContent(
 *         @OA\Property(property="code", type="integer", example=422),
 *         @OA\Property(property="status", type="boolean", example=false),
 *         @OA\Property(property="message", type="string", example="Validation failed"),
 *         @OA\Property(property="errors", type="object")
 *     )),
 *     @OA\Response(response=500, description="Server error", @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse"))
 * )
 *
 * @OA\Get(
 *     path="/api/orders/process-order",
 *     operationId="getProcessOrderStatus",
 *     tags={"Orders"},
 *     summary="Lấy trạng thái workflow của nhiều đơn hàng",
 *     description="Lấy trạng thái process (staff, qc, packing, shipout) cho danh sách đơn hàng. Dùng để hiển thị tiến độ sản xuất.",
 *     security={{"bearerAuth":{}}},
 *     @OA\Parameter(
 *         name="ids",
 *         in="query",
 *         required=true,
 *         description="Danh sách Order IDs cách nhau bằng dấu phẩy (tối đa 100 đơn)",
 *         @OA\Schema(type="string", example="25,35,56,78")
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="Thành công",
 *         @OA\JsonContent(
 *             @OA\Property(property="code", type="integer", example=200),
 *             @OA\Property(property="success", type="boolean", example=true),
 *             @OA\Property(property="message", type="string", example="Process orders status fetched successfully"),
 *             @OA\Property(property="data", type="array",
 *                 @OA\Items(
 *                     type="object",
 *                     @OA\Property(property="order_id", type="integer", example=25),
 *                     @OA\Property(property="flow", type="object",
 *                         @OA\Property(property="status", type="integer", example=1, description="Staff stage: 1=complete, 0=pending"),
 *                         @OA\Property(property="qc_status", type="integer", example=1, description="QC stage: 1=complete, 0=pending"),
 *                         @OA\Property(property="packing_status", type="integer", example=0, description="Packing stage: 1=complete, 0=pending"),
 *                         @OA\Property(property="shipout_status", type="integer", example=0, description="Shipout stage: 1=complete, 0=pending")
 *                     ),
 *                     @OA\Property(property="current_step", type="string", example="packing_status", description="Stage hiện tại đang chờ hoàn thành"),
 *                     @OA\Property(property="completed", type="boolean", example=false, description="True nếu tất cả stages đều complete")
 *                 )
 *             )
 *         )
 *     ),
 *     @OA\Response(response=400, description="Thiếu IDs hoặc quá 100 đơn", @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse")),
 *     @OA\Response(response=401, description="Chưa đăng nhập", @OA\JsonContent(ref="#/components/schemas/UnauthorizedResponse"))
 * )
 */
class OrderDocs
{
    // Chứa Swagger documentation cho Order endpoints
    // Logic thực tế trong OrderController, OrderFulfillStatusController, OrderItemController
}
