<?php

namespace App\Swagger\Docs;

/**
 * @OA\Get(
 *     path="/api/attendances",
 *     operationId="getAttendances",
 *     tags={"Attendance"},
 *     summary="Danh sách chấm công",
 *     description="Yêu cầu permission attendance.view (HR auto-bypass).",
 *     security={{"bearerAuth":{}}},
 *     @OA\Parameter(name="employee_name", in="query", @OA\Schema(type="string"), description="Lọc theo tên nhân viên"),
 *     @OA\Parameter(name="from", in="query", @OA\Schema(type="string", format="date"), description="Khoảng tuỳ chỉnh - từ ngày"),
 *     @OA\Parameter(name="to", in="query", @OA\Schema(type="string", format="date"), description="Khoảng tuỳ chỉnh - đến ngày"),
 *     @OA\Parameter(name="date", in="query", @OA\Schema(type="string", format="date"), description="Ngày cụ thể"),
 *     @OA\Parameter(name="month", in="query", @OA\Schema(type="string", example="2026-05"), description="Tháng"),
 *     @OA\Response(response=200, description="Thành công"),
 *     @OA\Response(response=403, ref="#/components/responses/Forbidden")
 * )
 *
 * @OA\Get(
 *     path="/api/attendances/logs/{userId}",
 *     operationId="getAttendanceLogs",
 *     tags={"Attendance"},
 *     summary="Chi tiết log chấm công của một nhân viên",
 *     security={{"bearerAuth":{}}},
 *     @OA\Parameter(name="userId", in="path", required=true, @OA\Schema(type="integer")),
 *     @OA\Parameter(name="from", in="query", @OA\Schema(type="string", format="date")),
 *     @OA\Parameter(name="to", in="query", @OA\Schema(type="string", format="date")),
 *     @OA\Response(response=200, description="Thành công")
 * )
 *
 * @OA\Post(
 *     path="/api/attendances/logs/{userId}/complete",
 *     operationId="completeAttendanceLog",
 *     tags={"Attendance"},
 *     summary="Bổ sung log chấm công còn thiếu",
 *     description="Cho phép HR thêm log check-in/check-out thủ công khi máy chấm công bị lỗi. Yêu cầu attendance.import.",
 *     security={{"bearerAuth":{}}},
 *     @OA\Parameter(name="userId", in="path", required=true, @OA\Schema(type="integer")),
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\JsonContent(
 *             required={"date"},
 *             @OA\Property(property="date", type="string", format="date", example="2026-05-10"),
 *             @OA\Property(property="check_in", type="string", format="time", example="08:00:00"),
 *             @OA\Property(property="check_out", type="string", format="time", example="17:30:00"),
 *             @OA\Property(property="note", type="string", example="Máy quẹt thẻ lỗi")
 *         )
 *     ),
 *     @OA\Response(response=200, description="Thành công")
 * )
 *
 * @OA\Post(
 *     path="/api/attendances/import",
 *     operationId="importAttendance",
 *     tags={"Attendance"},
 *     summary="Import file chấm công (.txt)",
 *     description="Upload file export từ máy chấm công, tự parse và lưu vào DB.",
 *     security={{"bearerAuth":{}}},
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\MediaType(
 *             mediaType="multipart/form-data",
 *             @OA\Schema(
 *                 required={"file"},
 *                 @OA\Property(property="file", type="string", format="binary", description="File .txt từ máy chấm công")
 *             )
 *         )
 *     ),
 *     @OA\Response(response=200, description="Import thành công"),
 *     @OA\Response(response=400, description="File không hợp lệ")
 * )
 */
class AttendanceDocs
{
}
