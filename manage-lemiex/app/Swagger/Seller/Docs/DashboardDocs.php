<?php

namespace App\Swagger\Seller\Docs;

/**
 * @OA\Get(
 *     path="/api/dashboard/statistics",
 *     operationId="sellerGetDashboardStatistics",
 *     tags={"Dashboard"},
 *     summary="Thống kê dashboard cho seller",
 *     description="Trả về tổng đơn, doanh thu, sản phẩm, ví... được scope theo seller_id. Yêu cầu dashboard.view.",
 *     security={{"bearerAuth":{}}},
 *     @OA\Parameter(
 *         name="time_range",
 *         in="query",
 *         description="Số ngày tính ngược từ hiện tại (mặc định 30)",
 *         @OA\Schema(type="integer", default=30, example=7)
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="Thành công",
 *         @OA\JsonContent(
 *             @OA\Property(property="code", type="integer", example=200),
 *             @OA\Property(property="status", type="boolean", example=true),
 *             @OA\Property(
 *                 property="data",
 *                 type="object",
 *                 @OA\Property(property="is_seller", type="boolean", example=true),
 *                 @OA\Property(property="time_range", type="integer", example=30),
 *                 @OA\Property(
 *                     property="overview",
 *                     type="object",
 *                     @OA\Property(property="total_orders", type="integer"),
 *                     @OA\Property(property="orders_this_period", type="integer"),
 *                     @OA\Property(property="orders_growth", type="number", format="float"),
 *                     @OA\Property(property="total_revenue", type="number", format="float"),
 *                     @OA\Property(property="revenue_this_period", type="number", format="float"),
 *                     @OA\Property(property="revenue_growth", type="number", format="float"),
 *                     @OA\Property(property="wallet_balance", type="number", format="float")
 *                 ),
 *                 @OA\Property(property="orders_by_payment_status", type="object"),
 *                 @OA\Property(property="orders_by_fulfill_status", type="object"),
 *                 @OA\Property(property="recent_orders", type="array", @OA\Items(type="object")),
 *                 @OA\Property(property="top_products", type="array", @OA\Items(type="object")),
 *                 @OA\Property(property="revenue_chart", type="array", @OA\Items(type="object")),
 *                 @OA\Property(property="order_count_chart", type="array", @OA\Items(type="object"))
 *             )
 *         )
 *     ),
 *     @OA\Response(response=403, description="Không có permission dashboard.view")
 * )
 */
class DashboardDocs
{
    // Class container for Seller Dashboard API documentation
}
