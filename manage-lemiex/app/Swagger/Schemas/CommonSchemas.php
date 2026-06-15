<?php

namespace App\Swagger\Schemas;

/**
 * @OA\Schema(
 *     schema="ApiResponse",
 *     type="object",
 *     description="Response thành công cơ bản",
 *     @OA\Property(property="code", type="integer", example=200, description="Mã trạng thái HTTP"),
 *     @OA\Property(property="status", type="boolean", example=true, description="Trạng thái thành công"),
 *     @OA\Property(property="message", type="string", example="Thành công", description="Thông báo")
 * )
 *
 * @OA\Schema(
 *     schema="ApiErrorResponse",
 *     type="object",
 *     description="Response lỗi cơ bản",
 *     @OA\Property(property="code", type="integer", example=400, description="Mã lỗi HTTP"),
 *     @OA\Property(property="status", type="boolean", example=false, description="Trạng thái thất bại"),
 *     @OA\Property(property="message", type="string", example="Có lỗi xảy ra", description="Thông báo lỗi"),
 *     @OA\Property(property="errors", type="object", nullable=true, description="Chi tiết lỗi")
 * )
 *
 * @OA\Schema(
 *     schema="UnauthorizedResponse",
 *     type="object",
 *     description="Response khi chưa đăng nhập hoặc token hết hạn",
 *     @OA\Property(property="code", type="integer", example=401),
 *     @OA\Property(property="status", type="boolean", example=false),
 *     @OA\Property(property="message", type="string", example="Chưa đăng nhập hoặc phiên đã hết hạn")
 * )
 *
 * @OA\Schema(
 *     schema="ForbiddenResponse",
 *     type="object",
 *     description="Response khi không có quyền truy cập",
 *     @OA\Property(property="code", type="integer", example=403),
 *     @OA\Property(property="status", type="boolean", example=false),
 *     @OA\Property(property="message", type="string", example="Bạn không có quyền truy cập chức năng này")
 * )
 *
 * @OA\Schema(
 *     schema="NotFoundResponse",
 *     type="object",
 *     description="Response khi không tìm thấy dữ liệu",
 *     @OA\Property(property="code", type="integer", example=404),
 *     @OA\Property(property="status", type="boolean", example=false),
 *     @OA\Property(property="message", type="string", example="Không tìm thấy dữ liệu")
 * )
 *
 * @OA\Schema(
 *     schema="ValidationErrorResponse",
 *     type="object",
 *     description="Response khi dữ liệu không hợp lệ",
 *     @OA\Property(property="code", type="integer", example=422),
 *     @OA\Property(property="status", type="boolean", example=false),
 *     @OA\Property(property="message", type="string", example="Dữ liệu không hợp lệ"),
 *     @OA\Property(
 *         property="errors",
 *         type="object",
 *         description="Danh sách lỗi theo từng field",
 *         @OA\AdditionalProperties(
 *             type="array",
 *             @OA\Items(type="string")
 *         )
 *     )
 * )
 *
 * @OA\Schema(
 *     schema="ServerErrorResponse",
 *     type="object",
 *     description="Response lỗi server",
 *     @OA\Property(property="code", type="integer", example=500),
 *     @OA\Property(property="status", type="boolean", example=false),
 *     @OA\Property(property="message", type="string", example="Lỗi hệ thống, vui lòng thử lại sau"),
 *     @OA\Property(property="error", type="string", nullable=true, description="Chi tiết lỗi (chỉ hiển thị ở môi trường dev)")
 * )
 */
class CommonSchemas
{
    // Chứa các schema definitions chung cho API responses
}
