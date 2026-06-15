<?php

namespace App\Swagger\Docs;

/**
 * @OA\Get(
 *     path="/api/payroll/tiers",
 *     operationId="getPayrollTiers",
 *     tags={"Payroll"},
 *     summary="Danh sách bậc lương",
 *     security={{"bearerAuth":{}}},
 *     @OA\Response(response=200, description="Thành công"),
 *     @OA\Response(response=403, ref="#/components/responses/Forbidden")
 * )
 *
 * @OA\Post(
 *     path="/api/payroll/tiers",
 *     operationId="createPayrollTier",
 *     tags={"Payroll"},
 *     summary="Tạo bậc lương mới",
 *     description="Yêu cầu payroll.manage.",
 *     security={{"bearerAuth":{}}},
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\JsonContent(
 *             required={"name", "base_salary"},
 *             @OA\Property(property="name", type="string", example="Junior Staff"),
 *             @OA\Property(property="base_salary", type="number", format="float", example=8000000),
 *             @OA\Property(property="description", type="string")
 *         )
 *     ),
 *     @OA\Response(response=200, description="Tạo thành công")
 * )
 *
 * @OA\Put(
 *     path="/api/payroll/tiers/{id}",
 *     operationId="updatePayrollTier",
 *     tags={"Payroll"},
 *     summary="Cập nhật bậc lương",
 *     security={{"bearerAuth":{}}},
 *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
 *     @OA\RequestBody(
 *         @OA\JsonContent(
 *             @OA\Property(property="name", type="string"),
 *             @OA\Property(property="base_salary", type="number", format="float"),
 *             @OA\Property(property="description", type="string")
 *         )
 *     ),
 *     @OA\Response(response=200, description="Thành công")
 * )
 *
 * @OA\Delete(
 *     path="/api/payroll/tiers/{id}",
 *     operationId="deletePayrollTier",
 *     tags={"Payroll"},
 *     summary="Xoá bậc lương",
 *     security={{"bearerAuth":{}}},
 *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
 *     @OA\Response(response=200, description="Xoá thành công")
 * )
 *
 * @OA\Get(
 *     path="/api/payroll/employees/{id}/salary-history",
 *     operationId="getEmployeeSalaryHistory",
 *     tags={"Payroll"},
 *     summary="Lịch sử lương của nhân viên",
 *     security={{"bearerAuth":{}}},
 *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer"), description="User ID"),
 *     @OA\Response(response=200, description="Thành công")
 * )
 *
 * @OA\Get(
 *     path="/api/payroll/employees/{id}/current-salary",
 *     operationId="getEmployeeCurrentSalary",
 *     tags={"Payroll"},
 *     summary="Lương hiện tại của nhân viên",
 *     security={{"bearerAuth":{}}},
 *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
 *     @OA\Response(response=200, description="Thành công")
 * )
 *
 * @OA\Get(
 *     path="/api/payroll/employees/{id}/salary-log",
 *     operationId="getEmployeeSalaryLog",
 *     tags={"Payroll"},
 *     summary="Log thay đổi lương",
 *     security={{"bearerAuth":{}}},
 *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
 *     @OA\Response(response=200, description="Thành công")
 * )
 *
 * @OA\Post(
 *     path="/api/payroll/employees/{id}/salary",
 *     operationId="createEmployeeSalary",
 *     tags={"Payroll"},
 *     summary="Tạo mức lương mới cho nhân viên",
 *     security={{"bearerAuth":{}}},
 *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\JsonContent(
 *             required={"tier_id", "effective_date"},
 *             @OA\Property(property="tier_id", type="integer", example=1),
 *             @OA\Property(property="effective_date", type="string", format="date", example="2026-05-01"),
 *             @OA\Property(property="custom_amount", type="number", format="float", nullable=true),
 *             @OA\Property(property="note", type="string")
 *         )
 *     ),
 *     @OA\Response(response=200, description="Tạo thành công")
 * )
 *
 * @OA\Put(
 *     path="/api/payroll/employees/{id}/salary",
 *     operationId="updateEmployeeSalary",
 *     tags={"Payroll"},
 *     summary="Cập nhật mức lương hiện tại",
 *     security={{"bearerAuth":{}}},
 *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
 *     @OA\Response(response=200, description="Thành công")
 * )
 *
 * @OA\Get(
 *     path="/api/payroll/adjustments",
 *     operationId="getPayrollAdjustments",
 *     tags={"Payroll"},
 *     summary="Danh sách điều chỉnh lương (thưởng/phạt)",
 *     security={{"bearerAuth":{}}},
 *     @OA\Parameter(name="user_id", in="query", @OA\Schema(type="integer")),
 *     @OA\Parameter(name="month", in="query", @OA\Schema(type="string", example="2026-05")),
 *     @OA\Response(response=200, description="Thành công")
 * )
 *
 * @OA\Post(
 *     path="/api/payroll/adjustments",
 *     operationId="createPayrollAdjustment",
 *     tags={"Payroll"},
 *     summary="Tạo điều chỉnh lương",
 *     security={{"bearerAuth":{}}},
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\JsonContent(
 *             required={"user_id", "amount", "type", "month"},
 *             @OA\Property(property="user_id", type="integer", example=42),
 *             @OA\Property(property="amount", type="number", format="float", example=500000),
 *             @OA\Property(property="type", type="string", enum={"bonus", "penalty", "allowance", "deduction"}),
 *             @OA\Property(property="month", type="string", example="2026-05"),
 *             @OA\Property(property="note", type="string")
 *         )
 *     ),
 *     @OA\Response(response=200, description="Tạo thành công")
 * )
 *
 * @OA\Put(
 *     path="/api/payroll/adjustments/{id}",
 *     operationId="updatePayrollAdjustment",
 *     tags={"Payroll"},
 *     summary="Cập nhật điều chỉnh lương",
 *     security={{"bearerAuth":{}}},
 *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
 *     @OA\Response(response=200, description="Thành công")
 * )
 *
 * @OA\Delete(
 *     path="/api/payroll/adjustments/{id}",
 *     operationId="deletePayrollAdjustment",
 *     tags={"Payroll"},
 *     summary="Xoá điều chỉnh lương",
 *     security={{"bearerAuth":{}}},
 *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
 *     @OA\Response(response=200, description="Xoá thành công")
 * )
 *
 * @OA\Get(
 *     path="/api/payroll/report",
 *     operationId="getPayrollReport",
 *     tags={"Payroll"},
 *     summary="Báo cáo bảng lương tháng",
 *     description="Tổng hợp lương cuối cùng từ base salary + adjustments + công.",
 *     security={{"bearerAuth":{}}},
 *     @OA\Parameter(name="month", in="query", required=true, @OA\Schema(type="string", example="2026-05")),
 *     @OA\Parameter(name="user_id", in="query", @OA\Schema(type="integer"), description="Lọc theo nhân viên cụ thể"),
 *     @OA\Response(response=200, description="Thành công")
 * )
 *
 * @OA\Post(
 *     path="/api/payroll/net-salary",
 *     operationId="updateNetSalary",
 *     tags={"Payroll"},
 *     summary="Cập nhật lương thực nhận sau khi chốt",
 *     security={{"bearerAuth":{}}},
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\JsonContent(
 *             required={"user_id", "month", "net_salary"},
 *             @OA\Property(property="user_id", type="integer"),
 *             @OA\Property(property="month", type="string", example="2026-05"),
 *             @OA\Property(property="net_salary", type="number", format="float")
 *         )
 *     ),
 *     @OA\Response(response=200, description="Cập nhật thành công")
 * )
 */
class PayrollDocs
{
}
