<?php

namespace App\Swagger\Docs;

/**
 * ==================== DASHBOARD API DOCUMENTATION ====================
 *
 * @OA\Get(
 *     path="/api/dashboard/statistics",
 *     operationId="getDashboardStatistics",
 *     tags={"Dashboard"},
 *     summary="Thống kê Dashboard",
 *     description="Lấy các thống kê tổng quan cho dashboard. Admin thấy toàn bộ, Seller chỉ thấy dữ liệu của mình.",
 *     security={{"bearerAuth":{}}},
 *     @OA\Parameter(
 *         name="time_range",
 *         in="query",
 *         description="Số ngày thống kê (mặc định 30 ngày)",
 *         @OA\Schema(type="integer", default=30, example=30)
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="Thành công",
 *         @OA\JsonContent(
 *             @OA\Property(property="code", type="integer", example=200),
 *             @OA\Property(property="status", type="boolean", example=true),
 *             @OA\Property(property="message", type="string", example="Statistics retrieved successfully"),
 *             @OA\Property(property="data", type="object",
 *                 @OA\Property(property="orders", type="object",
 *                     @OA\Property(property="total", type="integer", example=1500),
 *                     @OA\Property(property="new_order", type="integer", example=50),
 *                     @OA\Property(property="producing", type="integer", example=200),
 *                     @OA\Property(property="shipped", type="integer", example=1200),
 *                     @OA\Property(property="cancelled", type="integer", example=50)
 *                 ),
 *                 @OA\Property(property="revenue", type="object",
 *                     @OA\Property(property="total", type="number", format="float", example=50000.00),
 *                     @OA\Property(property="print_cost", type="number", format="float", example=20000.00),
 *                     @OA\Property(property="shipping_cost", type="number", format="float", example=5000.00),
 *                     @OA\Property(property="profit", type="number", format="float", example=25000.00)
 *                 ),
 *                 @OA\Property(property="products", type="object",
 *                     @OA\Property(property="total", type="integer", example=100),
 *                     @OA\Property(property="total_variants", type="integer", example=500),
 *                     @OA\Property(property="low_stock", type="integer", example=10),
 *                     @OA\Property(property="out_of_stock", type="integer", example=5)
 *                 ),
 *                 @OA\Property(property="users", type="object",
 *                     @OA\Property(property="total_sellers", type="integer", example=50),
 *                     @OA\Property(property="active_sellers", type="integer", example=40)
 *                 ),
 *                 @OA\Property(property="chart_data", type="object",
 *                     @OA\Property(property="labels", type="array", @OA\Items(type="string")),
 *                     @OA\Property(property="orders", type="array", @OA\Items(type="integer")),
 *                     @OA\Property(property="revenue", type="array", @OA\Items(type="number"))
 *                 )
 *             )
 *         )
 *     ),
 *     @OA\Response(
 *         response=401,
 *         description="Chưa xác thực",
 *         @OA\JsonContent(ref="#/components/schemas/UnauthorizedResponse")
 *     )
 * )
 */
class DashboardDocs
{
    // Dashboard API Documentation
}
