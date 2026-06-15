<?php

namespace App\Swagger\Docs;

/**
 * @OA\Get(
 *     path="/api/permissions",
 *     operationId="getPermissions",
 *     tags={"Permissions"},
 *     summary="Danh sách tất cả permissions",
 *     description="Lấy toàn bộ permissions trong hệ thống, có nhóm sẵn theo group. Yêu cầu quyền permissions.view (Admin/HR auto-bypass).",
 *     security={{"bearerAuth":{}}},
 *     @OA\Response(
 *         response=200,
 *         description="Thành công",
 *         @OA\JsonContent(
 *             @OA\Property(property="code", type="integer", example=200),
 *             @OA\Property(property="status", type="boolean", example=true),
 *             @OA\Property(property="data", type="object",
 *                 @OA\Property(property="permissions", type="array", @OA\Items(ref="#/components/schemas/Permission")),
 *                 @OA\Property(property="grouped", type="object", description="Permissions nhóm theo group name"),
 *                 @OA\Property(property="groups", type="array", @OA\Items(type="string"))
 *             )
 *         )
 *     ),
 *     @OA\Response(response=403, description="Không có quyền truy cập")
 * )
 *
 * @OA\Get(
 *     path="/api/permissions/matrix",
 *     operationId="getPermissionMatrix",
 *     tags={"Permissions"},
 *     summary="Ma trận role × permission",
 *     description="Lấy ma trận role và permissions để hiển thị UI quản lý phân quyền.",
 *     security={{"bearerAuth":{}}},
 *     @OA\Response(
 *         response=200,
 *         description="Thành công",
 *         @OA\JsonContent(
 *             @OA\Property(property="code", type="integer", example=200),
 *             @OA\Property(property="status", type="boolean", example=true),
 *             @OA\Property(property="data", type="object",
 *                 @OA\Property(property="roles", type="array", @OA\Items(ref="#/components/schemas/Role")),
 *                 @OA\Property(property="permissions", type="array", @OA\Items(ref="#/components/schemas/Permission")),
 *                 @OA\Property(property="grouped", type="object", description="Permissions nhóm theo group"),
 *                 @OA\Property(property="matrix", type="object", description="Map roleId → { permissions: [id, ...] }")
 *             )
 *         )
 *     )
 * )
 *
 * @OA\Post(
 *     path="/api/permissions",
 *     operationId="createPermission",
 *     tags={"Permissions"},
 *     summary="Tạo permission mới",
 *     description="Tạo permission mới. Yêu cầu quyền permissions.manage.",
 *     security={{"bearerAuth":{}}},
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\JsonContent(
 *             required={"name", "display_name"},
 *             @OA\Property(property="name", type="string", example="orders.archive", description="Mã định danh dạng group.action"),
 *             @OA\Property(property="display_name", type="string", example="Lưu trữ đơn hàng"),
 *             @OA\Property(property="description", type="string", example="Cho phép lưu trữ đơn hàng cũ"),
 *             @OA\Property(property="group", type="string", example="Orders"),
 *             @OA\Property(property="route", type="string", example="/orders/archive"),
 *             @OA\Property(property="method", type="string", example="POST")
 *         )
 *     ),
 *     @OA\Response(response=200, description="Tạo thành công"),
 *     @OA\Response(response=400, description="Validation lỗi"),
 *     @OA\Response(response=403, description="Không có quyền")
 * )
 *
 * @OA\Put(
 *     path="/api/permissions/{id}",
 *     operationId="updatePermission",
 *     tags={"Permissions"},
 *     summary="Cập nhật permission",
 *     security={{"bearerAuth":{}}},
 *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
 *     @OA\RequestBody(
 *         @OA\JsonContent(
 *             @OA\Property(property="display_name", type="string", example="Cập nhật tên hiển thị"),
 *             @OA\Property(property="description", type="string"),
 *             @OA\Property(property="group", type="string")
 *         )
 *     ),
 *     @OA\Response(response=200, description="Thành công"),
 *     @OA\Response(response=404, description="Permission không tồn tại")
 * )
 *
 * @OA\Delete(
 *     path="/api/permissions/{id}",
 *     operationId="deletePermission",
 *     tags={"Permissions"},
 *     summary="Xoá permission (chỉ với permission removable=true)",
 *     security={{"bearerAuth":{}}},
 *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
 *     @OA\Response(response=200, description="Xoá thành công"),
 *     @OA\Response(response=400, description="Không thể xoá permission mặc định")
 * )
 *
 * @OA\Put(
 *     path="/api/permissions/roles/{roleId}",
 *     operationId="updateRolePermissions",
 *     tags={"Permissions"},
 *     summary="Cập nhật danh sách permissions của role",
 *     description="Sync toàn bộ permissions cho role (sẽ thay thế danh sách hiện có).",
 *     security={{"bearerAuth":{}}},
 *     @OA\Parameter(name="roleId", in="path", required=true, @OA\Schema(type="integer")),
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\JsonContent(
 *             required={"permissions"},
 *             @OA\Property(
 *                 property="permissions",
 *                 type="array",
 *                 @OA\Items(type="integer"),
 *                 example={1, 2, 3, 5, 8}
 *             )
 *         )
 *     ),
 *     @OA\Response(response=200, description="Cập nhật thành công"),
 *     @OA\Response(response=403, description="Không có quyền permissions.manage")
 * )
 *
 * @OA\Post(
 *     path="/api/permissions/roles",
 *     operationId="createRole",
 *     tags={"Permissions"},
 *     summary="Tạo role mới",
 *     description="Tạo role custom. Role mới mặc định removable=true. Yêu cầu permissions.manage.",
 *     security={{"bearerAuth":{}}},
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\JsonContent(
 *             required={"name", "display_name"},
 *             @OA\Property(property="name", type="string", example="Manager", description="Chỉ chữ/số/underscore, bắt đầu bằng chữ"),
 *             @OA\Property(property="display_name", type="string", example="Quản lý cấp cao"),
 *             @OA\Property(property="description", type="string", example="Có quyền xem báo cáo"),
 *             @OA\Property(property="permission_ids", type="array", @OA\Items(type="integer"), description="IDs permission gán ngay khi tạo (tuỳ chọn)")
 *         )
 *     ),
 *     @OA\Response(response=200, description="Tạo role thành công"),
 *     @OA\Response(response=400, description="Tên role đã tồn tại hoặc sai format")
 * )
 *
 * @OA\Put(
 *     path="/api/permissions/roles/{id}/info",
 *     operationId="updateRoleInfo",
 *     tags={"Permissions"},
 *     summary="Cập nhật thông tin role (không đổi permissions)",
 *     description="Đổi display_name/description của role. Role built-in không thể đổi name.",
 *     security={{"bearerAuth":{}}},
 *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
 *     @OA\RequestBody(
 *         @OA\JsonContent(
 *             @OA\Property(property="display_name", type="string"),
 *             @OA\Property(property="description", type="string"),
 *             @OA\Property(property="name", type="string", description="Chỉ áp dụng cho role removable=true")
 *         )
 *     ),
 *     @OA\Response(response=200, description="Cập nhật thành công"),
 *     @OA\Response(response=400, description="Không thể đổi name của role built-in")
 * )
 *
 * @OA\Delete(
 *     path="/api/permissions/roles/{id}",
 *     operationId="deleteRole",
 *     tags={"Permissions"},
 *     summary="Xoá role",
 *     description="Chỉ xoá được role custom (removable=true) và không có user nào đang assigned.",
 *     security={{"bearerAuth":{}}},
 *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
 *     @OA\Response(response=200, description="Xoá thành công"),
 *     @OA\Response(response=400, description="Role mặc định hoặc còn user đang dùng")
 * )
 *
 * @OA\Post(
 *     path="/api/permissions/seed",
 *     operationId="seedPermissions",
 *     tags={"Permissions"},
 *     summary="Sync permissions từ codebase",
 *     description="Tạo các permissions chưa tồn tại trong DB dựa trên danh sách hardcode trong PermissionController.",
 *     security={{"bearerAuth":{}}},
 *     @OA\Response(
 *         response=200,
 *         description="Sync thành công",
 *         @OA\JsonContent(
 *             @OA\Property(property="status", type="boolean", example=true),
 *             @OA\Property(property="data", type="object",
 *                 @OA\Property(property="created", type="integer", example=5),
 *                 @OA\Property(property="already_exists", type="integer", example=66),
 *                 @OA\Property(property="total_defined", type="integer", example=71)
 *             )
 *         )
 *     )
 * )
 */
class PermissionDocs
{
}
