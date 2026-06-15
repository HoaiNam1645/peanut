<?php

namespace App\Swagger\Docs;

/**
 * @OA\Post(
 *     path="/api/orders/cancel-refund-shipping",
 *     operationId="cancelWithShippingRefund",
 *     tags={"Orders"},
 *     summary="Hủy đơn và hoàn tiền ship",
 *     description="Cho phép hủy đơn hàng (khi chưa shipped) và hoàn lại phí ship vào ví seller. Đồng thời hủy các production liên quan và cập nhật lại nhu cầu tồn kho (pending_demand) nếu cần.",
 *     security={{"bearerAuth":{}}},
 *     @OA\RequestBody(
 *         required=true,
 *         description="Thông tin hủy đơn",
 *         @OA\JsonContent(
 *             required={"order_id"},
 *             @OA\Property(property="order_id", type="integer", example=123, description="ID đơn hàng cần hủy"),
 *             @OA\Property(property="note", type="string", example="Khách đổi ý", description="Ghi chú hủy đơn (tùy chọn)")
 *         )
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="Hủy đơn thành công",
 *         @OA\JsonContent(
 *             type="object",
 *             @OA\Property(property="code", type="integer", example=200),
 *             @OA\Property(property="status", type="boolean", example=true),
 *             @OA\Property(property="message", type="string", example="Order cancelled successfully with $5.99 shipping refund"),
 *             @OA\Property(
 *                 property="data",
 *                 type="object",
 *                 @OA\Property(property="id", type="integer", example=123),
 *                 @OA\Property(property="fulfill_status", type="string", example="cancelled_refund_shipping"),
 *                 @OA\Property(property="shipping_refunded", type="number", format="float", example=5.99),
 *                 @OA\Property(property="refund_fee", type="number", format="float", example=5.99),
 *                 @OA\Property(property="updated_at", type="string", format="date-time")
 *             )
 *         )
 *     ),
 *     @OA\Response(
 *         response=400,
 *         description="Lỗi request (Đã ship hoặc đã hủy)",
 *         @OA\JsonContent(
 *             type="object",
 *             @OA\Property(property="code", type="integer", example=400),
 *             @OA\Property(property="status", type="boolean", example=false),
 *             @OA\Property(property="message", type="string", example="Cannot cancel order that has already been shipped"),
 *             @OA\Property(property="data", type="null")
 *         )
 *     ),
 *     @OA\Response(
 *         response=403,
 *         description="Không có quyền (Staff restrictions)",
 *         @OA\JsonContent(
 *             type="object",
 *             @OA\Property(property="code", type="integer", example=403),
 *             @OA\Property(property="status", type="boolean", example=false),
 *             @OA\Property(property="message", type="string", example="Staff cannot cancel order from this status"),
 *             @OA\Property(property="data", type="null")
 *         )
 *     ),
 *     @OA\Response(
 *         response=404,
 *         description="Không tìm thấy đơn hàng",
 *         @OA\JsonContent(ref="#/components/schemas/NotFoundResponse")
 *     ),
 *     @OA\Response(
 *         response=500,
 *         description="Lỗi server",
 *         @OA\JsonContent(ref="#/components/schemas/ServerErrorResponse")
 *     )
 * )
 */
class CancelRefundShippingDocs {}
