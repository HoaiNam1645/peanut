<?php

namespace App\Swagger\Seller\Docs;

/**
 * ==================== CREATE ORDER ====================
 * @OA\Post(
 *     path="/api/orders/create",
 *     operationId="createOrder",
 *     tags={"Create Order"},
 *     summary="Tạo đơn hàng",
 *     description="Tạo đơn hàng mới. Hỗ trợ 2 loại: label_ship, seller_ship. Yêu cầu quyền Seller. Chọn example từ dropdown để xem body mẫu.",
 *     @OA\RequestBody(
 *         required=true,
 *         description="Thông tin đơn hàng - Chọn example phù hợp với loại đơn",
 *         @OA\JsonContent(
 *             @OA\Examples(
 *                 example="LABEL_SHIP",
 *                 summary="Đơn LABEL_SHIP (in) - Có sẵn nhãn vận chuyển; NÊN kèm địa chỉ người nhận (state là mã bang 2 ký tự, vd NY) để tạo vận chuyển + thông quan",
 *                 value={
 *                     "api_key": "shpat_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxx",
 *                     "order_type": "label_ship",
 *                     "product_type": "Print",
 *                     "ref_id": "ORDER-12345",
 *                     "seller_ref": "SHOP-ORDER-12345",
 *                     "order_status": "new_order",
 *                     "shipping_method": "standard",
 *                     "shipping_service": "USPS",
 *                     "shipping_label": "https://example.com/label.pdf",
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
 *                             "variant_id": "PRINT-BOX-S-NAT",
 *                             "product_name": "Áo thun in - S",
 *                             "quantity": 1,
 *                             "mockup": "https://example.com/mockup.jpg",
 *                             "print_files": {
 *                                 {"key": "front", "url": "https://example.com/design.pdf"}
 *                             }
 *                         }
 *                     }
 *                 }
 *             ),
 *             @OA\Examples(
 *                 example="SELLER_SHIP",
 *                 summary="Đơn SELLER_SHIP (in) - Seller tự ship, cần địa chỉ + design",
 *                 value={
 *                     "api_key": "shpat_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxx",
 *                     "order_type": "seller_ship",
 *                     "product_type": "Print",
 *                     "ref_id": "ORDER-12345",
 *                     "seller_ref": "SHOP-ORDER-12345",
 *                     "order_status": "new_order",
 *                     "shipping_method": "standard",
 *                     "shipping_service": "USPS",
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
 *                             "variant_id": "PRINT-BOX-S-NAT",
 *                             "product_name": "Áo thun in - S",
 *                             "quantity": 1,
 *                             "mockup": "https://example.com/mockup.jpg",
 *                             "print_files": {
 *                                 {"key": "front", "url": "https://example.com/design.pdf"}
 *                             }
 *                         }
 *                     }
 *                 }
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
 *                 @OA\Property(property="order_id", type="integer", example=12345)
 *             )
 *         )
 *     ),
 *     @OA\Response(
 *         response=409,
 *         description="Đơn hàng đã tồn tại (ref_id trùng)",
 *         @OA\JsonContent(
 *             @OA\Property(property="code", type="integer", example=409),
 *             @OA\Property(property="status", type="boolean", example=false),
 *             @OA\Property(property="message", type="string", example="Order already exists"),
 *             @OA\Property(property="data", type="object",
 *                 @OA\Property(property="order_id", type="integer", example=12345),
 *                 @OA\Property(property="ref_id", type="string", example="ORDER-12345"),
 *                 @OA\Property(property="fulfill_status", type="string", example="new_order")
 *             )
 *         )
 *     ),
 *     @OA\Response(
 *         response=422,
 *         description="Validation error",
 *         @OA\JsonContent(
 *             @OA\Property(property="code", type="integer", example=422),
 *             @OA\Property(property="status", type="boolean", example=false),
 *             @OA\Property(property="message", type="string", example="Validation failed"),
 *             @OA\Property(property="errors", type="object")
 *         )
 *     )
 * )
 *
 * ==================== UPDATE ORDER ====================
 * @OA\Put(
 *     path="/api/orders/update",
 *     operationId="updateOrder",
 *     tags={"Update Order"},
 *     summary="Cập nhật đơn hàng",
 *     description="Cập nhật đơn hàng đã tạo. Hỗ trợ 2 loại: label_ship, seller_ship. Yêu cầu quyền orders.update.
 *
 * - Body cần gửi nguyên vẹn snapshot đơn hiện tại; backend tự diff và chỉ áp các trường thay đổi.
 * - `id` + `ref_id` phải khớp với đơn trong DB (nếu lệch sẽ trả 422).
 * - `order_status`: bắt buộc gửi nhưng KHÔNG được là `producing` hoặc `closed` (đơn ở 2 trạng thái này không cho update).
 * - Seller chỉ update được đơn khi `fulfill_status` là `new_order` hoặc `on_hold`. Admin/Staff không bị giới hạn này.
 * - Enum cho phép: `shipping_method` ∈ {standard, express, priority}; `shipping_service` ∈ {USPS, FedEx, UPS}; `print_files.*.key` ∈ {front, back, sleeve_left, sleeve_right, special_design, neck, wrap}.
 * - `address` bắt buộc cho `seller_ship`, **tùy chọn** cho `label_ship` (nên gửi để tạo vận chuyển/thông quan; `state` là mã bang 2 ký tự); `shipping_label` bắt buộc cho `label_ship`.
 * - Nếu không có gì thay đổi sau diff, response 200 với message `No changes detected` và không touch DB.
 *
 * Chọn example từ dropdown để xem body mẫu.",
 *     @OA\RequestBody(
 *         required=true,
 *         description="Snapshot đầy đủ của đơn cần cập nhật (id + ref_id phải khớp với đơn trong DB)",
 *         @OA\JsonContent(
 *             @OA\Examples(
 *                 example="LABEL_SHIP",
 *                 summary="Cập nhật đơn LABEL_SHIP - Có sẵn nhãn; có thể kèm địa chỉ người nhận (state là mã bang 2 ký tự)",
 *                 value={
 *                     "id": 12345,
 *                     "api_key": "shpat_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxx",
 *                     "order_type": "label_ship",
 *                     "ref_id": "ORDER-12345",
 *                     "seller_ref": "SHOP-ORDER-12345",
 *                     "order_status": "new_order",
 *                     "shipping_method": "standard",
 *                     "shipping_service": "USPS",
 *                     "shipping_label": "https://example.com/label.pdf",
 *                     "note": "Cập nhật ghi chú đơn hàng",
 *                     "address": {
 *                         "name": "John Doe",
 *                         "phone": "1234567890",
 *                         "street1": "456 New Address St",
 *                         "street2": "Suite 100",
 *                         "city": "Los Angeles",
 *                         "state": "CA",
 *                         "zip": "90001",
 *                         "country": "US"
 *                     },
 *                     "line_items": {
 *                         {
 *                             "variant_id": "PRINT-BOX-S-NAT",
 *                             "product_name": "Áo thun in - S",
 *                             "quantity": 2,
 *                             "mockup": "https://example.com/mockup.jpg",
 *                             "print_files": {
 *                                 {"key": "front", "url": "https://example.com/design-v2.pdf"}
 *                             }
 *                         }
 *                     }
 *                 }
 *             ),
 *             @OA\Examples(
 *                 example="SELLER_SHIP",
 *                 summary="Cập nhật đơn SELLER_SHIP - Seller tự ship, có địa chỉ + design",
 *                 value={
 *                     "id": 12345,
 *                     "api_key": "shpat_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxx",
 *                     "order_type": "seller_ship",
 *                     "ref_id": "ORDER-12345",
 *                     "seller_ref": "SHOP-ORDER-12345",
 *                     "order_status": "new_order",
 *                     "shipping_method": "standard",
 *                     "shipping_service": "USPS",
 *                     "note": "Cập nhật ghi chú đơn hàng",
 *                     "address": {
 *                         "name": "John Doe",
 *                         "phone": "1234567890",
 *                         "street1": "456 New Address St",
 *                         "street2": "Suite 100",
 *                         "city": "Los Angeles",
 *                         "state": "CA",
 *                         "zip": "90001",
 *                         "country": "US"
 *                     },
 *                     "line_items": {
 *                         {
 *                             "variant_id": "PRINT-BOX-S-NAT",
 *                             "product_name": "Áo thun in - S",
 *                             "quantity": 1,
 *                             "mockup": "https://example.com/mockup.jpg",
 *                             "print_files": {
 *                                 {"key": "front", "url": "https://example.com/design-v2.pdf"}
 *                             }
 *                         }
 *                     }
 *                 }
 *             )
 *         )
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="Cập nhật đơn thành công. Khi không có field nào thay đổi, message = 'No changes detected' và data chỉ chứa order_id.",
 *         @OA\JsonContent(
 *             @OA\Property(property="code", type="integer", example=200),
 *             @OA\Property(property="status", type="boolean", example=true),
 *             @OA\Property(property="message", type="string", example="Order updated", description="'Order updated' khi có thay đổi, 'No changes detected' khi diff rỗng"),
 *             @OA\Property(property="data", type="object",
 *                 @OA\Property(property="order_id", type="integer", example=12345),
 *                 @OA\Property(property="changed_fields", type="array", @OA\Items(type="string"), example={"shipping_method","note","line_items"}, description="Chỉ có khi có thay đổi")
 *             )
 *         )
 *     ),
 *     @OA\Response(
 *         response=403,
 *         description="Không có quyền orders.update hoặc không đủ điều kiện sửa đơn (vd seller chỉ sửa được đơn new_order/on_hold)",
 *         @OA\JsonContent(
 *             @OA\Property(property="code", type="integer", example=403),
 *             @OA\Property(property="status", type="boolean", example=false),
 *             @OA\Property(property="message", type="string", example="Bạn không có quyền thực hiện hành động này")
 *         )
 *     ),
 *     @OA\Response(
 *         response=404,
 *         description="Không tìm thấy đơn",
 *         @OA\JsonContent(
 *             @OA\Property(property="code", type="integer", example=404),
 *             @OA\Property(property="status", type="boolean", example=false),
 *             @OA\Property(property="message", type="string", example="Order not found")
 *         )
 *     ),
 *     @OA\Response(
 *         response=422,
 *         description="Validation error (vd: order_status không hợp lệ, ref_id không khớp, đơn đã ở trạng thái không cho sửa)",
 *         @OA\JsonContent(
 *             @OA\Property(property="code", type="integer", example=422),
 *             @OA\Property(property="status", type="boolean", example=false),
 *             @OA\Property(property="message", type="string", example="Validation failed"),
 *             @OA\Property(property="errors", type="object",
 *                 example={
 *                     "order_status": {"The selected order status is invalid."}
 *                 }
 *             )
 *         )
 *     )
 * )
 *
 * ==================== GET ORDER DETAIL ====================
 * @OA\Get(
 *     path="/api/orders/{id}",
 *     operationId="getOrderDetail",
 *     tags={"Track Order"},
 *     summary="Chi tiết đơn hàng",
 *     description="Lấy thông tin chi tiết đầy đủ của một đơn hàng bao gồm sản phẩm, địa chỉ, chi phí và trạng thái",
 *     security={{"bearerAuth": {}}},
 *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer"), description="Order ID"),
 *     @OA\Response(
 *         response=200,
 *         description="Thành công",
 *         @OA\JsonContent(
 *             @OA\Property(property="code", type="integer", example=200),
 *             @OA\Property(property="success", type="boolean", example=true),
 *             @OA\Property(
 *                 property="data",
 *                 type="object",
 *                 @OA\Property(property="id", type="integer", example=12345),
 *                 @OA\Property(property="ref_id", type="string", example="ORDER-12345"),
 *                 @OA\Property(property="order_stt", type="string", example="01_07 12.345"),
 *                 @OA\Property(property="fulfill_status", type="string", example="producing", description="Trạng thái: new_order, producing, qc_pass, shipped, cancelled..."),
 *                 @OA\Property(property="payment_status", type="string", example="paid"),
 *                 @OA\Property(property="order_type", type="string", example="Shirt"),
 *                 @OA\Property(property="shipping_method", type="string", example="standard"),
 *                 @OA\Property(property="tracking_id", type="string", example="9400111899223456789012"),
 *                 @OA\Property(property="tracking_link", type="string", example="https://tools.usps.com/go/TrackConfirmAction?tLabels=xxx"),
 *                 @OA\Property(property="print_cost", type="number", example=15.50),
 *                 @OA\Property(property="shipping_cost", type="number", example=0.50),
 *                 @OA\Property(property="extra_fee", type="number", example=0.00),
 *                 @OA\Property(property="refund_fee", type="number", example=0.00),
 *                 @OA\Property(property="total_cost", type="number", example=16.00),
 *                 @OA\Property(property="first_name", type="string", example="John"),
 *                 @OA\Property(property="last_name", type="string", example="Doe"),
 *                 @OA\Property(property="address_1", type="string", example="123 Main St"),
 *                 @OA\Property(property="city", type="string", example="New York"),
 *                 @OA\Property(property="state", type="string", example="NY"),
 *                 @OA\Property(property="postcode", type="string", example="10001"),
 *                 @OA\Property(property="country", type="string", example="US"),
 *                 @OA\Property(property="created_at", type="string", format="date-time"),
 *                 @OA\Property(
 *                     property="items",
 *                     type="array",
 *                     description="Danh sách sản phẩm trong đơn",
 *                     @OA\Items(
 *                         @OA\Property(property="id", type="integer", example=1001),
 *                         @OA\Property(property="variant_id", type="string", example="GD-1800-Black-L"),
 *                         @OA\Property(property="product_name", type="string", example="Custom T-Shirt"),
 *                         @OA\Property(property="quantity", type="integer", example=2),
 *                         @OA\Property(property="price", type="number", example=7.50),
 *                         @OA\Property(property="mockup", type="string", example="https://example.com/mockup-front.png"),
 *                         @OA\Property(property="mockup_back", type="string", nullable=true, example="https://example.com/mockup-back.png"),
 *                         @OA\Property(property="qr_codes", type="array", @OA\Items(type="string"), nullable=true),
 *                         @OA\Property(property="merge_images", type="array", @OA\Items(type="string"), nullable=true),
 *                         @OA\Property(
 *                             property="variant",
 *                             type="object",
 *                             nullable=true,
 *                             description="Thông tin chi tiết variant",
 *                             @OA\Property(property="style", type="string", example="Gildan 5000"),
 *                             @OA\Property(property="color", type="string", example="Black"),
 *                             @OA\Property(property="size", type="string", example="L"),
 *                             @OA\Property(property="sku", type="string", example="GIL5000-BLK-L")
 *                         )
 *                     )
 *                 )
 *             )
 *         )
 *     ),
 *     @OA\Response(
 *         response=404,
 *         description="Đơn hàng không tìm thấy",
 *         @OA\JsonContent(
 *             @OA\Property(property="code", type="integer", example=404),
 *             @OA\Property(property="status", type="boolean", example=false),
 *             @OA\Property(property="message", type="string", example="Order not found")
 *         )
 *     ),
 *     @OA\Response(
 *         response=403,
 *         description="Không có quyền truy cập đơn hàng này",
 *         @OA\JsonContent(
 *             @OA\Property(property="code", type="integer", example=403),
 *             @OA\Property(property="status", type="boolean", example=false),
 *             @OA\Property(property="message", type="string", example="You do not have permission to view this order")
 *         )
 *     )
 * )
 *
 * ==================== SELLER CANCEL ORDER ====================
 * @OA\Post(
 *     path="/api/orders/seller-cancel",
 *     operationId="sellerCancelOrder",
 *     tags={"Cancel Order"},
 *     summary="Seller huỷ đơn hàng (public — auth bằng api_key)",
 *     description="Cho phép Seller huỷ đơn hàng họ đã tạo nhầm. KHÔNG cần JWT, auth bằng `api_key` của store (giống `/api/orders/create`). Điều kiện huỷ: đơn ở trạng thái 'new_order' VÀ chưa thanh toán VÀ thuộc về seller sở hữu api_key.",
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\JsonContent(
 *             required={"api_key", "order_id"},
 *             @OA\Property(property="api_key", type="string", example="shpat_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx", description="API key của store (phải tồn tại trong stores.api_key)"),
 *             @OA\Property(property="order_id", type="integer", example=12345, description="ID đơn hàng (phải tồn tại trong orders.id)"),
 *             @OA\Property(property="reason", type="string", maxLength=500, example="Tạo nhầm đơn", description="Lý do huỷ, tối đa 500 ký tự (không bắt buộc, default 'Seller cancelled')")
 *         )
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="Huỷ đơn thành công",
 *         @OA\JsonContent(
 *             @OA\Property(property="code", type="integer", example=200),
 *             @OA\Property(property="status", type="boolean", example=true),
 *             @OA\Property(property="message", type="string", example="Order cancelled successfully"),
 *             @OA\Property(property="data", type="object",
 *                 @OA\Property(property="id", type="integer", example=12345),
 *                 @OA\Property(property="fulfill_status", type="string", example="cancelled"),
 *                 @OA\Property(property="updated_at", type="string", format="date-time")
 *             )
 *         )
 *     ),
 *     @OA\Response(
 *         response=400,
 *         description="Không thể huỷ — đơn đã thanh toán hoặc không còn ở trạng thái new_order",
 *         @OA\JsonContent(
 *             @OA\Property(property="code", type="integer", example=400),
 *             @OA\Property(property="status", type="boolean", example=false),
 *             @OA\Property(property="message", type="string", example="Cannot cancel paid orders. Please contact support for assistance.")
 *         )
 *     ),
 *     @OA\Response(response=401, description="api_key sai hoặc store không tồn tại"),
 *     @OA\Response(response=403, description="Account không phải Seller, hoặc đơn không thuộc seller này (You can only cancel your own orders)"),
 *     @OA\Response(response=404, description="Đơn hàng không tìm thấy"),
 *     @OA\Response(response=422, description="Validation failed (thiếu api_key/order_id hoặc sai format)")
 * )
 *
 * ==================== LIST ORDERS ====================
 * @OA\Get(
 *     path="/api/orders",
 *     operationId="sellerListOrders",
 *     tags={"Track Order"},
 *     summary="Danh sách đơn hàng của seller",
 *     description="Trả về list orders phân trang. Seller bị auto-scope theo seller_id (chỉ thấy đơn của mình). Hỗ trợ nhiều filter và sort. Lưu ý: với Seller, filter status 'confirm' tự gộp cả 'pending_stock'; 'producing' gộp 'in_stock/producing/qc_pass'; 'shipped' gộp 'packed/shipped'.",
 *     security={{"bearerAuth":{}}},
 *     @OA\Parameter(name="per_page", in="query", @OA\Schema(type="integer", default=20)),
 *     @OA\Parameter(name="page", in="query", @OA\Schema(type="integer", default=1)),
 *     @OA\Parameter(name="search", in="query", description="Search across ref_id, seller_ref, order_stt, tracking_id", @OA\Schema(type="string")),
 *     @OA\Parameter(name="ref_id", in="query", description="LIKE match theo ref_id", @OA\Schema(type="string")),
 *     @OA\Parameter(name="seller_ref", in="query", @OA\Schema(type="string")),
 *     @OA\Parameter(name="tracking_number", in="query", @OA\Schema(type="string")),
 *     @OA\Parameter(name="order_id", in="query", description="Exact match. Có thể nhiều IDs phân cách bằng dấu phẩy/khoảng trắng", @OA\Schema(type="string", example="46,56")),
 *     @OA\Parameter(name="payment_status", in="query", description="Có thể truyền nhiều (array hoặc CSV)", @OA\Schema(type="string", enum={"paid", "unpaid", "refunded"})),
 *     @OA\Parameter(name="fulfill_status", in="query", description="Có thể truyền nhiều (array hoặc CSV)", @OA\Schema(type="string", enum={"new_order", "confirm", "in_stock", "producing", "qc_pass", "packed", "shipped", "delivered", "cancelled", "on_hold"})),
 *     @OA\Parameter(name="exclude_status", in="query", description="Bỏ qua các fulfill_status (CSV)", @OA\Schema(type="string", example="cancelled,delivered")),
 *     @OA\Parameter(name="processing_status", in="query", @OA\Schema(type="string")),
 *     @OA\Parameter(name="store_id", in="query", @OA\Schema(type="integer")),
 *     @OA\Parameter(name="date_from", in="query", @OA\Schema(type="string", format="date")),
 *     @OA\Parameter(name="date_to", in="query", @OA\Schema(type="string", format="date")),
 *     @OA\Parameter(name="cost_min", in="query", @OA\Schema(type="number", format="float")),
 *     @OA\Parameter(name="cost_max", in="query", @OA\Schema(type="number", format="float")),
 *     @OA\Parameter(name="product_name", in="query", @OA\Schema(type="string")),
 *     @OA\Parameter(name="style", in="query", @OA\Schema(type="string")),
 *     @OA\Parameter(name="color", in="query", @OA\Schema(type="string")),
 *     @OA\Parameter(name="size", in="query", @OA\Schema(type="string")),
 *     @OA\Parameter(name="variant_id", in="query", @OA\Schema(type="string")),
 *     @OA\Parameter(name="sort_by", in="query", @OA\Schema(type="string", default="created_at")),
 *     @OA\Parameter(name="sort_order", in="query", @OA\Schema(type="string", enum={"asc", "desc"}, default="desc")),
 *     @OA\Response(
 *         response=200,
 *         description="Thành công",
 *         @OA\JsonContent(
 *             @OA\Property(property="code", type="integer", example=200),
 *             @OA\Property(property="success", type="boolean", example=true),
 *             @OA\Property(property="data", type="object",
 *                 @OA\Property(property="orders", type="array", @OA\Items(type="object")),
 *                 @OA\Property(property="pagination", type="object",
 *                     @OA\Property(property="current_page", type="integer"),
 *                     @OA\Property(property="per_page", type="integer"),
 *                     @OA\Property(property="total", type="integer"),
 *                     @OA\Property(property="last_page", type="integer"),
 *                     @OA\Property(property="from", type="integer"),
 *                     @OA\Property(property="to", type="integer")
 *                 ),
 *                 @OA\Property(property="summary", type="object",
 *                     @OA\Property(property="total_amount_all", type="number", description="Sum total_cost loại trừ new_order"),
 *                     @OA\Property(property="total_amount_page", type="number")
 *                 )
 *             )
 *         )
 *     ),
 *     @OA\Response(response=403, description="Không có permission orders.view")
 * )
 *
 * @OA\Get(
 *     path="/api/orders/ids",
 *     operationId="sellerExportOrderIds",
 *     tags={"Track Order"},
 *     summary="Lấy mảng ID đơn theo filter",
 *     description="Trả về mảng order IDs khớp filter (không phân trang). Dùng cho bulk action / export. Cùng filter signature với GET /orders. Yêu cầu orders.export_ids.",
 *     security={{"bearerAuth":{}}},
 *     @OA\Parameter(name="payment_status", in="query", @OA\Schema(type="string")),
 *     @OA\Parameter(name="fulfill_status", in="query", @OA\Schema(type="string")),
 *     @OA\Parameter(name="date_from", in="query", @OA\Schema(type="string", format="date")),
 *     @OA\Parameter(name="date_to", in="query", @OA\Schema(type="string", format="date")),
 *     @OA\Parameter(name="store_id", in="query", @OA\Schema(type="integer")),
 *     @OA\Parameter(name="search", in="query", @OA\Schema(type="string")),
 *     @OA\Response(
 *         response=200,
 *         description="Thành công",
 *         @OA\JsonContent(
 *             @OA\Property(property="code", type="integer", example=200),
 *             @OA\Property(property="success", type="boolean", example=true),
 *             @OA\Property(property="data", type="array", @OA\Items(type="integer"), example={46, 47, 48}),
 *             @OA\Property(property="count", type="integer", example=3)
 *         )
 *     )
 * )
 *
 * @OA\Get(
 *     path="/api/orders/{id}/timeline",
 *     operationId="sellerGetOrderTimeline",
 *     tags={"Track Order"},
 *     summary="Timeline thay đổi của đơn",
 *     description="Lịch sử event của order (object='order', object_id=id) sort theo created_at desc. Seller bị check ownership.",
 *     security={{"bearerAuth":{}}},
 *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
 *     @OA\Response(
 *         response=200,
 *         description="Thành công",
 *         @OA\JsonContent(
 *             @OA\Property(property="data", type="object",
 *                 @OA\Property(property="order_id", type="integer"),
 *                 @OA\Property(property="order_stt", type="string"),
 *                 @OA\Property(property="timeline", type="array", @OA\Items(
 *                     @OA\Property(property="id", type="integer"),
 *                     @OA\Property(property="action", type="string"),
 *                     @OA\Property(property="note", type="string"),
 *                     @OA\Property(property="created_at", type="string", format="date-time"),
 *                     @OA\Property(property="created_at_formatted", type="string")
 *                 ))
 *             )
 *         )
 *     ),
 *     @OA\Response(response=403, description="Seller chỉ xem được timeline đơn của chính mình"),
 *     @OA\Response(response=404, description="Order không tồn tại")
 * )
 *
 * @OA\Get(
 *     path="/api/orders/{id}/qr-codes",
 *     operationId="sellerGetOrderQRCodes",
 *     tags={"Track Order"},
 *     summary="QR codes và design files của line items",
 *     description="Trả về danh sách QR code + URL design files (PES/EMB/DST/JSON) per line_item per position.",
 *     security={{"bearerAuth":{}}},
 *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
 *     @OA\Response(response=200, description="Thành công")
 * )
 *
 * @OA\Get(
 *     path="/api/orders/track/{orderId}",
 *     operationId="sellerTrackOrder",
 *     tags={"Track Order"},
 *     summary="Track đơn (response cache 10 phút)",
 *     description="Trả về dữ liệu tracking gọn cho UI public tracking page (kèm carrier status, designs by position, workflows by position+stage). Result được cache 10 phút theo orderId + query params.",
 *     security={{"bearerAuth":{}}},
 *     @OA\Parameter(name="orderId", in="path", required=true, @OA\Schema(type="integer")),
 *     @OA\Parameter(name="stt", in="query", description="Số thứ tự item trong đơn (1-based, theo quantity)", @OA\Schema(type="integer")),
 *     @OA\Parameter(name="item_id", in="query", description="ID order_item cụ thể (ưu tiên hơn stt)", @OA\Schema(type="integer")),
 *     @OA\Parameter(name="item_stt", in="query", description="STT item cụ thể trong nhóm", @OA\Schema(type="integer")),
 *     @OA\Response(response=200, description="Thành công")
 * )
 *
 * @OA\Get(
 *     path="/api/orders/fulfill-statuses",
 *     operationId="sellerGetFulfillStatuses",
 *     tags={"Track Order"},
 *     summary="Danh sách fulfill statuses kèm count",
 *     description="Trả về list status với label + count đơn của user. Với Seller: 'pending_stock' được gộp vào 'confirm' và bị loại khỏi list.",
 *     security={{"bearerAuth":{}}},
 *     @OA\Response(
 *         response=200,
 *         description="Thành công",
 *         @OA\JsonContent(
 *             @OA\Property(property="data", type="array", @OA\Items(
 *                 @OA\Property(property="value", type="string", example="confirm"),
 *                 @OA\Property(property="label", type="string", example="Confirmed"),
 *                 @OA\Property(property="count", type="integer", example=12),
 *                 @OA\Property(property="display_label", type="string", example="Confirmed (12)")
 *             ))
 *         )
 *     )
 * )
 */
class OrderDocs
{
    // Class container for Seller Order API documentation
}
