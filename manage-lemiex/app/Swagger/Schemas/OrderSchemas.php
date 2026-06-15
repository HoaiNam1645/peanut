<?php

namespace App\Swagger\Schemas;

/**
 * ==================== ORDER SCHEMAS ====================
 * 
 * @OA\Schema(
 *     schema="OrderItem",
 *     type="object",
 *     description="Thông tin sản phẩm trong đơn hàng",
 *     @OA\Property(property="id", type="integer", example=1, description="ID item"),
 *     @OA\Property(property="variant_id", type="string", example="VARIANT-001", description="Mã variant sản phẩm"),
 *     @OA\Property(property="product_name", type="string", example="T-Shirt Classic", description="Tên sản phẩm"),
 *     @OA\Property(property="quantity", type="integer", example=2, description="Số lượng"),
 *     @OA\Property(property="price", type="number", format="float", example=15.99, description="Giá"),
 *     @OA\Property(property="mockup", type="string", nullable=true, example="https://example.com/mockup.jpg", description="URL mockup mặt trước"),
 *     @OA\Property(property="mockup_back", type="string", nullable=true, example="https://example.com/mockup-back.jpg", description="URL mockup mặt sau"),
 *     @OA\Property(property="status", type="string", example="processing", description="Trạng thái item"),
 *     @OA\Property(property="sides", type="string", example="front,back", description="Các mặt in"),
 *     @OA\Property(property="qr_codes", type="array", @OA\Items(type="string"), description="Danh sách URL các QR codes của item", nullable=true),
 *     @OA\Property(property="merge_images", type="array", @OA\Items(type="string"), description="Danh sách URL các merge images (cho Tumbler/Print)", nullable=true),
 *     @OA\Property(
 *         property="variant",
 *         type="object",
 *         nullable=true,
 *         description="Thông tin chi tiết variant",
 *         @OA\Property(property="style", type="string", example="Gildan 5000", description="Kiểu sản phẩm"),
 *         @OA\Property(property="color", type="string", example="Black", description="Màu sắc"),
 *         @OA\Property(property="size", type="string", example="L", description="Kích cỡ"),
 *         @OA\Property(property="sku", type="string", example="GIL5000-BLK-L", description="SKU variant")
 *     )
 * )
 *
 * @OA\Schema(
 *     schema="OrderTracking",
 *     type="object",
 *     description="Thông tin tracking vận chuyển",
 *     @OA\Property(property="tracking_id", type="string", example="1Z999AA10123456784", description="Mã tracking"),
 *     @OA\Property(property="status", type="string", example="in_transit", description="Trạng thái vận chuyển"),
 *     @OA\Property(property="service", type="string", example="USPS", description="Dịch vụ vận chuyển"),
 *     @OA\Property(property="method", type="string", example="standard", description="Phương thức vận chuyển"),
 *     @OA\Property(property="total_day", type="integer", example=5, description="Số ngày dự kiến"),
 *     @OA\Property(property="update_time", type="string", format="date-time", description="Thời gian cập nhật cuối")
 * )
 *
 * @OA\Schema(
 *     schema="Order",
 *     type="object",
 *     description="Thông tin đơn hàng",
 *     @OA\Property(property="id", type="integer", example=123, description="ID đơn hàng"),
 *     @OA\Property(property="ref_id", type="string", example="ORDER-12345", description="Mã tham chiếu"),
 *     @OA\Property(property="seller_ref", type="string", nullable=true, example="SELLER-REF-123", description="Mã tham chiếu seller"),
 *     @OA\Property(property="order_stt", type="integer", example=1, description="Số thứ tự đơn hàng"),
 *     @OA\Property(property="order_type", type="string", enum={"label_ship", "seller_ship", "no_design"}, description="Loại đơn hàng"),
 *     @OA\Property(property="fulfill_status", type="string", enum={"new_order", "on_hold", "producing", "qc_pass", "packed", "shipped", "delivered", "cancelled"}, description="Trạng thái fulfillment"),
 *     @OA\Property(property="payment_status", type="string", enum={"pending", "paid", "failed"}, description="Trạng thái thanh toán"),
 *     @OA\Property(property="shipping_method", type="string", enum={"standard", "express", "priority"}, description="Phương thức vận chuyển"),
 *     @OA\Property(property="shipping_service", type="string", enum={"USPS", "FedEx", "UPS"}, description="Dịch vụ vận chuyển"),
 *     @OA\Property(property="shipping_label", type="string", nullable=true, description="URL nhãn vận chuyển"),
 *     @OA\Property(property="tracking_id", type="string", nullable=true, description="Mã tracking"),
 *     @OA\Property(property="tracking_link", type="string", nullable=true, description="Link tracking"),
 *     @OA\Property(property="total_cost", type="number", format="float", example=50.00, description="Tổng chi phí"),
 *     @OA\Property(property="print_cost", type="number", format="float", example=30.00, description="Chi phí in"),
 *     @OA\Property(property="shipping_cost", type="number", format="float", example=10.00, description="Phí vận chuyển"),
 *     @OA\Property(property="extra_fee", type="number", format="float", example=5.00, description="Phụ phí (stitch cao)"),
 *     @OA\Property(property="refund_fee", type="number", format="float", example=2.00, description="Phí hoàn trả (chỉ có 1 mặt)"),
 *     @OA\Property(property="embroidery_fee", type="number", format="float", example=1.50, description="Phí thêu đặc biệt (metallic, glow, puff)"),
 *     @OA\Property(property="note", type="string", nullable=true, description="Ghi chú"),
 *     @OA\Property(property="items_count", type="integer", example=3, description="Số lượng sản phẩm"),
 *     @OA\Property(property="total_quantity", type="integer", example=5, description="Tổng số lượng"),
 *     @OA\Property(property="created_at", type="string", format="date-time", description="Ngày tạo"),
 *     @OA\Property(property="updated_at", type="string", format="date-time", description="Ngày cập nhật"),
 *     @OA\Property(
 *         property="items",
 *         type="array",
 *         @OA\Items(ref="#/components/schemas/OrderItem"),
 *         description="Danh sách sản phẩm"
 *     ),
 *     @OA\Property(property="tracking", ref="#/components/schemas/OrderTracking", nullable=true, description="Thông tin tracking")
 * )
 *
 * @OA\Schema(
 *     schema="OrderListResponse",
 *     type="object",
 *     @OA\Property(property="code", type="integer", example=200),
 *     @OA\Property(property="success", type="boolean", example=true),
 *     @OA\Property(property="message", type="string", example="Lấy danh sách đơn hàng thành công"),
 *     @OA\Property(
 *         property="data",
 *         type="object",
 *         @OA\Property(
 *             property="orders",
 *             type="array",
 *             @OA\Items(ref="#/components/schemas/Order")
 *         ),
 *         @OA\Property(
 *             property="pagination",
 *             type="object",
 *             @OA\Property(property="current_page", type="integer", example=1),
 *             @OA\Property(property="per_page", type="integer", example=20),
 *             @OA\Property(property="total", type="integer", example=100),
 *             @OA\Property(property="last_page", type="integer", example=5),
 *             @OA\Property(property="from", type="integer", example=1),
 *             @OA\Property(property="to", type="integer", example=20)
 *         )
 *     )
 * )
 *
 * @OA\Schema(
 *     schema="CreateOrderNoDesignRequest",
 *     type="object",
 *     required={"api_key", "order_type", "ref_id", "order_status", "shipping_method", "address", "line_items"},
 *     description="Request tạo đơn NO_DESIGN",
 *     @OA\Property(property="api_key", type="string", example="shpat_xxxxxx", description="API key của store"),
 *     @OA\Property(property="order_type", type="string", enum={"NO_DESIGN"}, example="NO_DESIGN"),
 *     @OA\Property(property="ref_id", type="string", example="ORDER-12345", description="Mã tham chiếu đơn hàng"),
 *     @OA\Property(property="seller_ref", type="string", nullable=true, example="SELLER-REF-123"),
 *     @OA\Property(property="order_status", type="string", enum={"pending", "new_order", "priority"}, example="pending"),
 *     @OA\Property(property="shipping_method", type="string", enum={"standard", "express", "priority"}, example="standard"),
 *     @OA\Property(property="shipping_service", type="string", enum={"USPS", "FedEx", "UPS"}, example="USPS"),
 *     @OA\Property(property="note", type="string", nullable=true),
 *     @OA\Property(
 *         property="address",
 *         type="object",
 *         required={"name", "street1", "city", "state", "zip", "country"},
 *         @OA\Property(property="name", type="string", example="John Doe"),
 *         @OA\Property(property="phone", type="string", example="1234567890"),
 *         @OA\Property(property="street1", type="string", example="123 Main St"),
 *         @OA\Property(property="street2", type="string", nullable=true, example="Apt 4B"),
 *         @OA\Property(property="city", type="string", example="New York"),
 *         @OA\Property(property="state", type="string", example="NY"),
 *         @OA\Property(property="zip", type="string", example="10001"),
 *         @OA\Property(property="country", type="string", example="US", description="Mã quốc gia 2 ký tự")
 *     ),
 *     @OA\Property(
 *         property="line_items",
 *         type="array",
 *         @OA\Items(
 *             type="object",
 *             required={"variant_id", "quantity"},
 *             @OA\Property(property="variant_id", type="string", example="VARIANT-001"),
 *             @OA\Property(property="quantity", type="integer", example=2),
 *             @OA\Property(property="mockup", type="string", format="url", nullable=true),
 *             @OA\Property(property="mockup_back", type="string", format="url", nullable=true),
 *             @OA\Property(property="design_front", type="string", format="url", nullable=true),
 *             @OA\Property(property="design_back", type="string", format="url", nullable=true)
 *         )
 *     )
 * )
 *
 * @OA\Schema(
 *     schema="CreateOrderLabelShipRequest",
 *     type="object",
 *     required={"api_key", "order_type", "ref_id", "order_status", "shipping_method", "shipping_label", "line_items"},
 *     description="Request tạo đơn LABEL_SHIP",
 *     @OA\Property(property="api_key", type="string", example="shpat_xxxxxx"),
 *     @OA\Property(property="order_type", type="string", enum={"LABEL_SHIP"}, example="LABEL_SHIP"),
 *     @OA\Property(property="ref_id", type="string", example="ORDER-12345"),
 *     @OA\Property(property="seller_ref", type="string", nullable=true),
 *     @OA\Property(property="product_type", type="string", nullable=true, example="Shirt", description="Product type (Shirt, Tumbler, etc.)"),
 *     @OA\Property(property="order_status", type="string", enum={"pending", "new_order", "priority"}),
 *     @OA\Property(property="shipping_method", type="string", enum={"standard", "express", "priority"}),
 *     @OA\Property(property="shipping_service", type="string", enum={"USPS", "FedEx", "UPS"}),
 *     @OA\Property(property="shipping_label", type="string", format="url", example="https://example.com/label.pdf", description="URL nhãn vận chuyển"),
 *     @OA\Property(property="fulfillment_priority", type="string", enum={"normal", "priority"}, description="Fulfillment priority: normal (free) or priority (extra charge)"),
 *     @OA\Property(property="note", type="string", nullable=true),
 *     @OA\Property(property="line_items", type="array", @OA\Items(type="object"))
 * )
 *
 * @OA\Schema(
 *     schema="CreateOrderSellerShipRequest",
 *     type="object",
 *     required={"api_key", "order_type", "ref_id", "order_status", "shipping_method", "address", "line_items"},
 *     description="Request tạo đơn SELLER_SHIP (có địa chỉ, cần design files)",
 *     @OA\Property(property="api_key", type="string", example="shpat_xxxxxx"),
 *     @OA\Property(property="order_type", type="string", enum={"SELLER_SHIP"}, example="SELLER_SHIP"),
 *     @OA\Property(property="ref_id", type="string", example="ORDER-12345"),
 *     @OA\Property(property="seller_ref", type="string", nullable=true),
 *     @OA\Property(property="order_status", type="string", enum={"new_order", "test_order", "priority"}),
 *     @OA\Property(property="shipping_method", type="string", enum={"standard", "express", "priority"}),
 *     @OA\Property(property="shipping_service", type="string", enum={"USPS", "FedEx", "UPS"}),
 *     @OA\Property(property="fulfillment_priority", type="string", enum={"normal", "priority"}, description="Fulfillment priority: normal (free) or priority (extra charge)"),
 *     @OA\Property(property="note", type="string", nullable=true),
 *     @OA\Property(
 *         property="address",
 *         type="object",
 *         required={"name", "street1", "city", "state", "zip", "country"},
 *         @OA\Property(property="name", type="string", example="John Doe"),
 *         @OA\Property(property="phone", type="string"),
 *         @OA\Property(property="street1", type="string"),
 *         @OA\Property(property="street2", type="string", nullable=true),
 *         @OA\Property(property="city", type="string"),
 *         @OA\Property(property="state", type="string"),
 *         @OA\Property(property="zip", type="string"),
 *         @OA\Property(property="country", type="string", example="US")
 *     ),
 *     @OA\Property(
 *         property="line_items",
 *         type="array",
 *         @OA\Items(
 *             type="object",
 *             required={"variant_id", "product_name", "quantity", "print_files"},
 *             @OA\Property(property="variant_id", type="string"),
 *             @OA\Property(property="product_name", type="string"),
 *             @OA\Property(property="quantity", type="integer"),
 *             @OA\Property(property="mockup", type="string", format="url", nullable=true),
 *             @OA\Property(property="mockup_back", type="string", format="url", nullable=true),
 *             @OA\Property(
 *                 property="print_files",
 *                 type="array",
 *                 @OA\Items(
 *                     type="object",
 *                     @OA\Property(property="key", type="string", enum={"front", "back", "sleeve_left", "sleeve_right", "neck"}),
 *                     @OA\Property(property="url", type="string", format="url", description="URL file .dst"),
 *                     @OA\Property(property="url_emb", type="string", format="url", nullable=true),
 *                     @OA\Property(property="url_pes", type="string", format="url", nullable=true),
 *                     @OA\Property(property="embroidery_type", type="string", enum={"standard", "metallic", "glow", "puff"}, nullable=true, description="Loại thêu (standard không tính phí thêm)")
 *                 )
 *             )
 *         )
 *     )
 * )
 *
 * @OA\Schema(
 *     schema="ChangeFulfillStatusRequest",
 *     type="object",
 *     required={"order_id", "fulfill_status"},
 *     @OA\Property(property="order_id", type="integer", example=123, description="ID đơn hàng"),
 *     @OA\Property(
 *         property="fulfill_status", 
 *         type="string", 
 *         enum={"new_order", "on_hold", "producing", "qc_pass", "packed", "shipped", "delivered", "cancelled"},
 *         example="producing",
 *         description="Trạng thái mới"
 *     )
 * )
 *
 * @OA\Schema(
 *     schema="ChangeItemStatusRequest",
 *     type="object",
 *     required={"item_id", "meta_key", "status"},
 *     @OA\Property(property="item_id", type="integer", example=123, description="ID item"),
 *     @OA\Property(property="meta_key", type="string", enum={"front", "back", "sleeve_left", "sleeve_right", "neck"}, example="front", description="Vị trí in"),
 *     @OA\Property(property="status", type="boolean", example=true, description="Trạng thái hoàn thành")
 * )
 *
 * @OA\Schema(
 *     schema="QcRejectRequest",
 *     type="object",
 *     required={"item_id"},
 *     @OA\Property(property="item_id", type="integer", example=123, description="ID item cần reject")
 * )
 *
 * @OA\Schema(
 *     schema="RemakeFileRequest",
 *     type="object",
 *     required={"order_item_meta_ids"},
 *     @OA\Property(
 *         property="order_item_meta_ids", 
 *         type="array", 
 *         @OA\Items(type="integer"),
 *         example={1, 2, 3},
 *         description="Danh sách ID của order_item_metas cần remake"
 *     )
 * )
 *
 * @OA\Schema(
 *     schema="RemakeQrRequest",
 *     type="object",
 *     required={"order_item_ids"},
 *     @OA\Property(
 *         property="order_item_ids", 
 *         type="array", 
 *         @OA\Items(type="integer"),
 *         example={1, 2, 3},
 *         description="Danh sách ID của order_items cần remake QR (phải cùng 1 đơn)"
 *     )
 * )
 *
 * @OA\Schema(
 *     schema="FulfillStatusItem",
 *     type="object",
 *     @OA\Property(property="value", type="string", example="producing"),
 *     @OA\Property(property="label", type="string", example="Đang sản xuất")
 * )
 */
class OrderSchemas
{
    // Chứa các schema definitions cho Order endpoints
}
