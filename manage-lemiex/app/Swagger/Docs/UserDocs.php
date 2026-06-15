<?php

namespace App\Swagger\Docs;

/**
 * @OA\Get(
 *     path="/api/users",
 *     operationId="getUsers",
 *     tags={"Users"},
 *     summary="Danh sách người dùng",
 *     description="Lấy danh sách tất cả người dùng với phân trang và bộ lọc. Yêu cầu quyền Admin.",
 *     security={{"bearerAuth":{}}},
 *     @OA\Parameter(
 *         name="per_page",
 *         in="query",
 *         description="Số lượng kết quả mỗi trang",
 *         @OA\Schema(type="integer", default=10, example=10)
 *     ),
 *     @OA\Parameter(
 *         name="page",
 *         in="query",
 *         description="Số trang",
 *         @OA\Schema(type="integer", default=1, example=1)
 *     ),
 *     @OA\Parameter(
 *         name="search",
 *         in="query",
 *         description="Tìm kiếm theo email, username, tên",
 *         @OA\Schema(type="string", example="john")
 *     ),
 *     @OA\Parameter(
 *         name="status",
 *         in="query",
 *         description="Lọc theo trạng thái",
 *         @OA\Schema(type="string", enum={"Unconfirmed", "Active", "Banned"}, example="Active")
 *     ),
 *     @OA\Parameter(
 *         name="role_id",
 *         in="query",
 *         description="Lọc theo vai trò (1=Admin, 2=Support, 3=Seller, 4=Staff)",
 *         @OA\Schema(type="integer", example=3)
 *     ),
 *     @OA\Parameter(
 *         name="tier",
 *         in="query",
 *         description="Lọc theo tier (0=Silver, 1=Gold, 2=Platinum, 3=Diamond)",
 *         @OA\Schema(type="integer", example=1)
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="Lấy danh sách thành công",
 *         @OA\JsonContent(ref="#/components/schemas/UserListResponse")
 *     ),
 *     @OA\Response(
 *         response=401,
 *         description="Chưa đăng nhập",
 *         @OA\JsonContent(ref="#/components/schemas/UnauthorizedResponse")
 *     ),
 *     @OA\Response(
 *         response=403,
 *         description="Không có quyền truy cập",
 *         @OA\JsonContent(
 *             @OA\Property(property="code", type="integer", example=403),
 *             @OA\Property(property="status", type="boolean", example=false),
 *             @OA\Property(property="message", type="string", example="Bạn không có quyền truy cập chức năng này")
 *         )
 *     )
 * )
 *
 * @OA\Get(
 *     path="/api/users/roles",
 *     operationId="getRoles",
 *     tags={"Users"},
 *     summary="Danh sách vai trò (Roles)",
 *     description="Lấy danh sách tất cả vai trò để sử dụng trong dropdown khi tạo/sửa người dùng. Yêu cầu quyền Admin.",
 *     security={{"bearerAuth":{}}},
 *     @OA\Response(
 *         response=200,
 *         description="Lấy danh sách roles thành công",
 *         @OA\JsonContent(
 *             @OA\Property(property="code", type="integer", example=200),
 *             @OA\Property(property="status", type="boolean", example=true),
 *             @OA\Property(property="message", type="string", example="Get roles successfully"),
 *             @OA\Property(
 *                 property="data",
 *                 type="array",
 *                 @OA\Items(
 *                     type="object",
 *                     @OA\Property(property="id", type="integer", example=2),
 *                     @OA\Property(property="name", type="string", example="user"),
 *                     @OA\Property(property="display_name", type="string", example="User"),
 *                     @OA\Property(property="description", type="string", example="Regular user role")
 *                 )
 *             )
 *         )
 *     ),
 *     @OA\Response(
 *         response=401,
 *         description="Chưa đăng nhập",
 *         @OA\JsonContent(ref="#/components/schemas/UnauthorizedResponse")
 *     ),
 *     @OA\Response(
 *         response=403,
 *         description="Không có quyền truy cập",
 *         @OA\JsonContent(
 *             @OA\Property(property="code", type="integer", example=403),
 *             @OA\Property(property="status", type="boolean", example=false),
 *             @OA\Property(property="message", type="string", example="Bạn không có quyền truy cập chức năng này")
 *         )
 *     )
 * )
 *
 * @OA\Post(
 *     path="/api/users",
 *     operationId="createUser",
 *     tags={"Users"},
 *     summary="Tạo người dùng mới",
 *     description="Tạo tài khoản người dùng mới. Yêu cầu quyền Admin.",
 *     security={{"bearerAuth":{}}},
 *     @OA\RequestBody(
 *         required=true,
 *         description="Thông tin người dùng mới",
 *         @OA\JsonContent(ref="#/components/schemas/UserCreateRequest")
 *     ),
 *     @OA\Response(
 *         response=201,
 *         description="Tạo thành công",
 *         @OA\JsonContent(
 *             @OA\Property(property="code", type="integer", example=201),
 *             @OA\Property(property="status", type="boolean", example=true),
 *             @OA\Property(property="message", type="string", example="Tạo người dùng thành công"),
 *             @OA\Property(property="data", ref="#/components/schemas/User")
 *         )
 *     ),
 *     @OA\Response(
 *         response=422,
 *         description="Lỗi validation",
 *         @OA\JsonContent(ref="#/components/schemas/ValidationErrorResponse")
 *     ),
 *     @OA\Response(
 *         response=401,
 *         description="Chưa đăng nhập",
 *         @OA\JsonContent(ref="#/components/schemas/UnauthorizedResponse")
 *     ),
 *     @OA\Response(
 *         response=403,
 *         description="Không có quyền truy cập",
 *         @OA\JsonContent(
 *             @OA\Property(property="code", type="integer", example=403),
 *             @OA\Property(property="status", type="boolean", example=false),
 *             @OA\Property(property="message", type="string", example="Bạn không có quyền truy cập chức năng này")
 *         )
 *     )
 * )
 *
 * @OA\Get(
 *     path="/api/users/{id}",
 *     operationId="getUserById",
 *     tags={"Users"},
 *     summary="Chi tiết người dùng",
 *     description="Lấy thông tin chi tiết một người dùng theo ID. Yêu cầu quyền Admin.",
 *     security={{"bearerAuth":{}}},
 *     @OA\Parameter(
 *         name="id",
 *         in="path",
 *         required=true,
 *         description="ID người dùng",
 *         @OA\Schema(type="integer", example=1)
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="Lấy thông tin thành công",
 *         @OA\JsonContent(ref="#/components/schemas/UserDetailResponse")
 *     ),
 *     @OA\Response(
 *         response=404,
 *         description="Không tìm thấy người dùng",
 *         @OA\JsonContent(
 *             @OA\Property(property="code", type="integer", example=404),
 *             @OA\Property(property="status", type="boolean", example=false),
 *             @OA\Property(property="message", type="string", example="Không tìm thấy người dùng")
 *         )
 *     ),
 *     @OA\Response(
 *         response=401,
 *         description="Chưa đăng nhập",
 *         @OA\JsonContent(ref="#/components/schemas/UnauthorizedResponse")
 *     )
 * )
 *
 * @OA\Put(
 *     path="/api/users/{id}",
 *     operationId="updateUser",
 *     tags={"Users"},
 *     summary="Cập nhật người dùng",
 *     description="Cập nhật thông tin người dùng theo ID. Có thể cập nhật mật khẩu bằng cách gửi password và password_confirmation. Để trống nếu không muốn đổi mật khẩu. Yêu cầu quyền Admin.",
 *     security={{"bearerAuth":{}}},
 *     @OA\Parameter(
 *         name="id",
 *         in="path",
 *         required=true,
 *         description="ID người dùng",
 *         @OA\Schema(type="integer", example=1)
 *     ),
 *     @OA\RequestBody(
 *         required=true,
 *         description="Thông tin cần cập nhật (password và password_confirmation là optional)",
 *         @OA\JsonContent(
 *             allOf={
 *                 @OA\Schema(ref="#/components/schemas/UserUpdateRequest"),
 *                 @OA\Schema(
 *                     @OA\Property(property="password", type="string", minLength=6, description="Mật khẩu mới (optional, để trống nếu không đổi)", example="newpassword123"),
 *                     @OA\Property(property="password_confirmation", type="string", description="Xác nhận mật khẩu mới (bắt buộc nếu có password)", example="newpassword123")
 *                 )
 *             }
 *         )
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="Cập nhật thành công",
 *         @OA\JsonContent(
 *             @OA\Property(property="code", type="integer", example=200),
 *             @OA\Property(property="status", type="boolean", example=true),
 *             @OA\Property(property="message", type="string", example="Cập nhật thành công"),
 *             @OA\Property(property="data", ref="#/components/schemas/User")
 *         )
 *     ),
 *     @OA\Response(
 *         response=404,
 *         description="Không tìm thấy người dùng",
 *         @OA\JsonContent(
 *             @OA\Property(property="code", type="integer", example=404),
 *             @OA\Property(property="status", type="boolean", example=false),
 *             @OA\Property(property="message", type="string", example="Không tìm thấy người dùng")
 *         )
 *     ),
 *     @OA\Response(
 *         response=422,
 *         description="Lỗi validation",
 *         @OA\JsonContent(ref="#/components/schemas/ValidationErrorResponse")
 *     ),
 *     @OA\Response(
 *         response=401,
 *         description="Chưa đăng nhập",
 *         @OA\JsonContent(ref="#/components/schemas/UnauthorizedResponse")
 *     )
 * )
 *
 * @OA\Delete(
 *     path="/api/users/{id}",
 *     operationId="deleteUser",
 *     tags={"Users"},
 *     summary="Xóa người dùng",
 *     description="Xóa người dùng theo ID. Yêu cầu quyền Admin. Lưu ý: Không thể xóa tài khoản Admin và không thể xóa chính tài khoản của bạn.",
 *     security={{"bearerAuth":{}}},
 *     @OA\Parameter(
 *         name="id",
 *         in="path",
 *         required=true,
 *         description="ID người dùng cần xóa",
 *         @OA\Schema(type="integer", example=1)
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="Xóa thành công",
 *         @OA\JsonContent(
 *             @OA\Property(property="code", type="integer", example=200),
 *             @OA\Property(property="status", type="boolean", example=true),
 *             @OA\Property(property="message", type="string", example="Xóa người dùng thành công")
 *         )
 *     ),
 *     @OA\Response(
 *         response=404,
 *         description="Không tìm thấy người dùng",
 *         @OA\JsonContent(
 *             @OA\Property(property="code", type="integer", example=404),
 *             @OA\Property(property="status", type="boolean", example=false),
 *             @OA\Property(property="message", type="string", example="Không tìm thấy người dùng")
 *         )
 *     ),
 *     @OA\Response(
 *         response=401,
 *         description="Chưa đăng nhập",
 *         @OA\JsonContent(ref="#/components/schemas/UnauthorizedResponse")
 *     ),
 *     @OA\Response(
 *         response=403,
 *         description="Không có quyền xóa",
 *         @OA\JsonContent(
 *             @OA\Property(property="code", type="integer", example=403),
 *             @OA\Property(property="status", type="boolean", example=false),
 *             @OA\Property(property="message", type="string", example="Không được phép xóa tài khoản Admin / Không thể xóa chính tài khoản của bạn")
 *         )
 *     )
 * )
 */
class UserDocs
{
    // Chứa Swagger documentation cho User endpoints
    // Logic thực tế trong UserController
}
