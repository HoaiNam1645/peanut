<?php

namespace App\Swagger\Docs;

/**
 * @OA\Get(
 *     path="/api/reports/staff",
 *     operationId="getStaffReport",
 *     tags={"Reports"},
 *     summary="Báo cáo hiệu suất nhân viên",
 *     description="Thống kê số đơn/items đã xử lý theo nhân viên trong khoảng thời gian. Yêu cầu reports.staff.",
 *     security={{"bearerAuth":{}}},
 *     @OA\Parameter(name="date_from", in="query", required=true, @OA\Schema(type="string", format="date")),
 *     @OA\Parameter(name="date_to", in="query", required=true, @OA\Schema(type="string", format="date")),
 *     @OA\Parameter(name="staff_id", in="query", @OA\Schema(type="integer"), description="Lọc theo 1 nhân viên cụ thể"),
 *     @OA\Response(response=200, description="Thành công"),
 *     @OA\Response(response=403, ref="#/components/responses/Forbidden")
 * )
 *
 * @OA\Get(
 *     path="/api/reports/staff-list",
 *     operationId="getStaffList",
 *     tags={"Reports"},
 *     summary="Danh sách nhân viên có hoạt động",
 *     security={{"bearerAuth":{}}},
 *     @OA\Response(response=200, description="Thành công")
 * )
 *
 * @OA\Get(
 *     path="/api/stock/shortage",
 *     operationId="getShortageReport",
 *     tags={"Reports"},
 *     summary="Báo cáo thiếu hàng theo đơn",
 *     description="Yêu cầu stock.shortage.",
 *     security={{"bearerAuth":{}}},
 *     @OA\Parameter(name="from", in="query", @OA\Schema(type="string", format="date")),
 *     @OA\Parameter(name="to", in="query", @OA\Schema(type="string", format="date")),
 *     @OA\Parameter(name="store_id", in="query", @OA\Schema(type="integer")),
 *     @OA\Response(response=200, description="Thành công")
 * )
 *
 * @OA\Get(
 *     path="/api/stock/shortage/by-variant",
 *     operationId="getShortageByVariant",
 *     tags={"Reports"},
 *     summary="Báo cáo thiếu hàng theo variant",
 *     security={{"bearerAuth":{}}},
 *     @OA\Response(response=200, description="Thành công")
 * )
 *
 * @OA\Get(
 *     path="/api/stock/shortage/export",
 *     operationId="exportShortageReport",
 *     tags={"Reports"},
 *     summary="Export báo cáo thiếu hàng (CSV)",
 *     security={{"bearerAuth":{}}},
 *     @OA\Response(
 *         response=200,
 *         description="File CSV",
 *         @OA\MediaType(mediaType="text/csv")
 *     )
 * )
 *
 * @OA\Get(
 *     path="/api/stock/shortage/orders/{orderId}",
 *     operationId="getShortageVariantsByOrder",
 *     tags={"Reports"},
 *     summary="Variants thiếu hàng của 1 đơn cụ thể",
 *     security={{"bearerAuth":{}}},
 *     @OA\Parameter(name="orderId", in="path", required=true, @OA\Schema(type="integer")),
 *     @OA\Response(response=200, description="Thành công")
 * )
 *
 * @OA\Get(
 *     path="/api/stock/dashboard/summary",
 *     operationId="getStockDashboardSummary",
 *     tags={"Reports"},
 *     summary="Tóm tắt dashboard kho",
 *     security={{"bearerAuth":{}}},
 *     @OA\Response(response=200, description="Thành công")
 * )
 *
 * @OA\Get(
 *     path="/api/stock/dashboard/analytics",
 *     operationId="getStockDashboardAnalytics",
 *     tags={"Reports"},
 *     summary="Analytics kho",
 *     security={{"bearerAuth":{}}},
 *     @OA\Response(response=200, description="Thành công")
 * )
 *
 * @OA\Get(
 *     path="/api/stock/dashboard/activities",
 *     operationId="getStockDashboardActivities",
 *     tags={"Reports"},
 *     summary="Hoạt động gần đây của kho",
 *     security={{"bearerAuth":{}}},
 *     @OA\Response(response=200, description="Thành công")
 * )
 */
class ReportsDocs
{
}
