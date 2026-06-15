<?php

namespace App\Swagger\Docs;

/**
 * ==================== BUY LABEL API DOCUMENTATION ====================
 *
 * @OA\Post(
 *     path="/api/buy-label/single",
 *     operationId="buyLabelSingle",
 *     tags={"Buy Label"},
 *     summary="Mua label cho một đơn hàng",
 *     description="Mua shipping label từ ShipEngine cho một đơn hàng cụ thể. Phí sẽ được trừ từ ví seller.",
 *     security={{"bearerAuth":{}}},
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\JsonContent(
 *             required={"order_id"},
 *             @OA\Property(property="order_id", type="integer", example=123, description="ID đơn hàng cần mua label")
 *         )
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="Mua label thành công",
 *         @OA\JsonContent(
 *             @OA\Property(property="code", type="integer", example=200),
 *             @OA\Property(property="status", type="boolean", example=true),
 *             @OA\Property(property="message", type="string", example="Label purchased successfully"),
 *             @OA\Property(property="data", type="object",
 *                 @OA\Property(property="order_id", type="integer", example=123),
 *                 @OA\Property(property="label_url", type="string", example="https://shipengine.com/labels/123.pdf"),
 *                 @OA\Property(property="tracking_number", type="string", example="1Z999AA10123456784"),
 *                 @OA\Property(property="carrier", type="string", example="USPS"),
 *                 @OA\Property(property="cost", type="number", format="float", example=5.50)
 *             )
 *         )
 *     ),
 *     @OA\Response(
 *         response=400,
 *         description="Không thể mua label",
 *         @OA\JsonContent(
 *             @OA\Property(property="code", type="integer", example=400),
 *             @OA\Property(property="status", type="boolean", example=false),
 *             @OA\Property(property="message", type="string", example="Order is not eligible for buying label"),
 *             @OA\Property(property="errors", type="object")
 *         )
 *     ),
 *     @OA\Response(response=401, description="Chưa xác thực"),
 *     @OA\Response(response=402, description="Số dư ví không đủ"),
 *     @OA\Response(response=404, description="Không tìm thấy đơn hàng")
 * )
 *
 * @OA\Post(
 *     path="/api/buy-label/batch",
 *     operationId="buyLabelBatch",
 *     tags={"Buy Label"},
 *     summary="Mua label hàng loạt",
 *     description="Mua shipping labels cho nhiều đơn hàng cùng lúc. Job sẽ được thực hiện async.",
 *     security={{"bearerAuth":{}}},
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\JsonContent(
 *             required={"order_ids"},
 *             @OA\Property(property="order_ids", type="array", @OA\Items(type="integer"), example={123, 124, 125}, description="Danh sách ID đơn hàng")
 *         )
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="Batch job đã được tạo",
 *         @OA\JsonContent(
 *             @OA\Property(property="code", type="integer", example=200),
 *             @OA\Property(property="status", type="boolean", example=true),
 *             @OA\Property(property="message", type="string", example="Batch buy label job dispatched"),
 *             @OA\Property(property="data", type="object",
 *                 @OA\Property(property="total_orders", type="integer", example=3),
 *                 @OA\Property(property="eligible_orders", type="integer", example=3),
 *                 @OA\Property(property="estimated_cost", type="number", format="float", example=16.50),
 *                 @OA\Property(property="job_id", type="string", example="batch-123456")
 *             )
 *         )
 *     ),
 *     @OA\Response(response=400, description="Không có đơn hàng hợp lệ"),
 *     @OA\Response(response=402, description="Số dư ví không đủ cho tất cả đơn hàng")
 * )
 *
 * @OA\Post(
 *     path="/api/buy-label/check-eligible",
 *     operationId="checkEligibleOrders",
 *     tags={"Buy Label"},
 *     summary="Kiểm tra đơn hàng có thể mua label",
 *     description="Kiểm tra xem các đơn hàng có đủ điều kiện để mua shipping label không",
 *     security={{"bearerAuth":{}}},
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\JsonContent(
 *             required={"order_ids"},
 *             @OA\Property(property="order_ids", type="array", @OA\Items(type="integer"), example={123, 124, 125})
 *         )
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="Kết quả kiểm tra",
 *         @OA\JsonContent(
 *             @OA\Property(property="code", type="integer", example=200),
 *             @OA\Property(property="status", type="boolean", example=true),
 *             @OA\Property(property="data", type="object",
 *                 @OA\Property(property="eligible", type="array", @OA\Items(type="object",
 *                     @OA\Property(property="order_id", type="integer", example=123),
 *                     @OA\Property(property="estimated_cost", type="number", example=5.50)
 *                 )),
 *                 @OA\Property(property="ineligible", type="array", @OA\Items(type="object",
 *                     @OA\Property(property="order_id", type="integer", example=124),
 *                     @OA\Property(property="reason", type="string", example="Order already has label")
 *                 )),
 *                 @OA\Property(property="total_eligible", type="integer", example=2),
 *                 @OA\Property(property="total_estimated_cost", type="number", example=11.00),
 *                 @OA\Property(property="wallet_balance", type="number", example=100.00),
 *                 @OA\Property(property="can_proceed", type="boolean", example=true)
 *             )
 *         )
 *     ),
 *     @OA\Response(response=400, description="Validation lỗi")
 * )
 */
class BuyLabelDocs
{
    // Buy Label API Documentation
}
