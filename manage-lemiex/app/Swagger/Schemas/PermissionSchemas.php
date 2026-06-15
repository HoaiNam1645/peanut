<?php

namespace App\Swagger\Schemas;

/**
 * @OA\Schema(
 *     schema="Permission",
 *     type="object",
 *     description="Permission đơn lẻ",
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="name", type="string", example="orders.view", description="Mã định danh dạng group.action"),
 *     @OA\Property(property="display_name", type="string", example="Xem danh sách đơn hàng"),
 *     @OA\Property(property="description", type="string", nullable=true),
 *     @OA\Property(property="group", type="string", example="Orders", nullable=true),
 *     @OA\Property(property="route", type="string", example="/orders", nullable=true),
 *     @OA\Property(property="method", type="string", example="GET", nullable=true),
 *     @OA\Property(property="removable", type="boolean", example=false, description="Permission mặc định = false, custom = true"),
 *     @OA\Property(property="created_at", type="string", format="date-time", nullable=true),
 *     @OA\Property(property="updated_at", type="string", format="date-time", nullable=true)
 * )
 *
 * @OA\Schema(
 *     schema="Role",
 *     type="object",
 *     description="Role / Vai trò",
 *     @OA\Property(property="id", type="integer", example=2),
 *     @OA\Property(property="name", type="string", example="Seller", description="Tên hệ thống (không đổi với role built-in)"),
 *     @OA\Property(property="display_name", type="string", example="Người bán"),
 *     @OA\Property(property="description", type="string", nullable=true),
 *     @OA\Property(property="removable", type="boolean", example=false, description="Role mặc định không thể xoá"),
 *     @OA\Property(
 *         property="permissions",
 *         type="array",
 *         @OA\Items(ref="#/components/schemas/Permission"),
 *         description="Chỉ trả về khi load with('permissions')"
 *     )
 * )
 */
class PermissionSchemas
{
}
