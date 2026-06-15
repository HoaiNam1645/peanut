<?php

namespace App\Swagger\Schemas;

/**
 * @OA\Schema(
 *     schema="UserCreateRequest",
 *     type="object",
 *     required={"email", "username", "password", "password_confirmation", "role_id", "status"},
 *     @OA\Property(
 *         property="email",
 *         type="string",
 *         format="email",
 *         example="user@example.com",
 *         description="Địa chỉ email"
 *     ),
 *     @OA\Property(
 *         property="username",
 *         type="string",
 *         example="username123",
 *         description="Tên đăng nhập"
 *     ),
 *     @OA\Property(
 *         property="password",
 *         type="string",
 *         format="password",
 *         example="password123",
 *         description="Mật khẩu"
 *     ),
 *     @OA\Property(
 *         property="password_confirmation",
 *         type="string",
 *         format="password",
 *         example="password123",
 *         description="Xác nhận mật khẩu"
 *     ),
 *     @OA\Property(
 *         property="role_id",
 *         type="integer",
 *         example=3,
 *         description="ID vai trò: 1=Admin, 2=Support, 3=Seller, 4=Staff"
 *     ),
 *     @OA\Property(
 *         property="status",
 *         type="string",
 *         enum={"Unconfirmed", "Active", "Banned"},
 *         example="Active",
 *         description="Trạng thái tài khoản"
 *     ),
 *     @OA\Property(
 *         property="first_name",
 *         type="string",
 *         example="John",
 *         description="Tên"
 *     ),
 *     @OA\Property(
 *         property="last_name",
 *         type="string",
 *         example="Doe",
 *         description="Họ"
 *     ),
 *     @OA\Property(
 *         property="phone",
 *         type="string",
 *         example="0123456789",
 *         description="Số điện thoại"
 *     ),
 *     @OA\Property(
 *         property="address",
 *         type="string",
 *         example="123 Đường ABC",
 *         description="Địa chỉ"
 *     ),
 *     @OA\Property(
 *         property="birthday",
 *         type="string",
 *         format="date",
 *         example="1990-01-01",
 *         description="Ngày sinh (YYYY-MM-DD)"
 *     ),
 *     @OA\Property(
 *         property="webhook_url",
 *         type="string",
 *         format="url",
 *         example="https://example.com/webhook",
 *         description="URL webhook"
 *     ),
 *     @OA\Property(
 *         property="telegram_id",
 *         type="string",
 *         example="123456789",
 *         description="ID Telegram"
 *     ),
 *     @OA\Property(
 *         property="tier_id",
 *         type="integer",
 *         enum={0, 1, 2, 3},
 *         example=1,
 *         description="Tier ID: 0=Silver, 1=Gold, 2=Platinum, 3=Diamond"
 *     ),
 *     @OA\Property(
 *         property="private_seller",
 *         type="integer",
 *         enum={0, 1, 2, 3},
 *         example=1,
 *         description="Tier ID (Alias cho tier_id, legacy support)"
 *     ),
 *     @OA\Property(
 *         property="max_debit",
 *         type="number",
 *         format="float",
 *         example=1000,
 *         description="Số tiền nợ tối đa"
 *     ),
 *     @OA\Property(
 *         property="max_date_debit",
 *         type="integer",
 *         example=30,
 *         description="Số ngày nợ tối đa"
 *     ),
 *     @OA\Property(
 *         property="min_date_debit",
 *         type="integer",
 *         example=7,
 *         description="Số ngày nợ tối thiểu"
 *     )
 * )
 *
 * @OA\Schema(
 *     schema="UserUpdateRequest",
 *     type="object",
 *     @OA\Property(property="email", type="string", format="email", example="user@example.com", description="Địa chỉ email"),
 *     @OA\Property(property="username", type="string", example="username123", description="Tên đăng nhập"),
 *     @OA\Property(property="role_id", type="integer", example=3, description="ID vai trò"),
 *     @OA\Property(property="status", type="string", enum={"Unconfirmed", "Active", "Banned"}, example="Active", description="Trạng thái"),
 *     @OA\Property(property="first_name", type="string", example="John", description="Tên"),
 *     @OA\Property(property="last_name", type="string", example="Doe", description="Họ"),
 *     @OA\Property(property="phone", type="string", example="0123456789", description="Số điện thoại"),
 *     @OA\Property(property="tier_id", type="integer", enum={0, 1, 2, 3}, example=1, description="Tier ID: 0=Silver, 1=Gold..."),
 *     @OA\Property(property="private_seller", type="integer", enum={0, 1, 2, 3}, example=1, description="Tier ID (Legacy alias)"),
 *     @OA\Property(property="max_debit", type="number", format="float", example=1000, description="Số tiền nợ tối đa")
 * )
 *
 * @OA\Schema(
 *     schema="User",
 *     type="object",
 *     @OA\Property(property="id", type="integer", example=1, description="ID người dùng"),
 *     @OA\Property(property="email", type="string", example="user@example.com", description="Email"),
 *     @OA\Property(property="username", type="string", example="username123", description="Tên đăng nhập"),
 *     @OA\Property(property="first_name", type="string", example="John", description="Tên"),
 *     @OA\Property(property="last_name", type="string", example="Doe", description="Họ"),
 *     @OA\Property(property="phone", type="string", nullable=true, example="0123456789", description="Số điện thoại"),
 *     @OA\Property(property="status", type="string", enum={"Unconfirmed", "Active", "Banned"}, example="Active", description="Trạng thái"),
 *     @OA\Property(property="created_at", type="string", format="date-time", example="2024-01-01T00:00:00Z", description="Ngày tạo"),
 *     @OA\Property(property="updated_at", type="string", format="date-time", example="2024-01-01T00:00:00Z", description="Ngày cập nhật"),
 *     @OA\Property(
 *         property="role",
 *         type="object",
 *         description="Thông tin vai trò",
 *         @OA\Property(property="id", type="integer", example=3),
 *         @OA\Property(property="name", type="string", example="Seller")
 *     ),
 *     @OA\Property(
 *         property="profile",
 *         type="object",
 *         nullable=true,
 *         description="Thông tin hồ sơ",
 *         @OA\Property(property="balance", type="number", format="float", example=1250.50, description="Số dư"),
 *         @OA\Property(property="avatar", type="string", nullable=true, example="https://example.com/avatar.jpg", description="Ảnh đại diện"),
 *         @OA\Property(property="private_seller", type="integer", example=1, description="Tier ID của User"),
 *         @OA\Property(property="max_debit", type="number", format="float", example=1000, description="Nợ tối đa"),
 *         @OA\Property(
 *             property="tier",
 *             type="object",
 *             nullable=true,
 *             description="Thông tin tier",
 *             @OA\Property(property="tier_id", type="integer", example=1),
 *             @OA\Property(property="name", type="string", example="Gold")
 *         )
 *     )
 * )
 *
 * @OA\Schema(
 *     schema="UserListResponse",
 *     type="object",
 *     @OA\Property(property="code", type="integer", example=200),
 *     @OA\Property(property="status", type="boolean", example=true),
 *     @OA\Property(property="message", type="string", example="Lấy danh sách thành công"),
 *     @OA\Property(
 *         property="data",
 *         type="object",
 *         @OA\Property(
 *             property="data",
 *             type="array",
 *             @OA\Items(ref="#/components/schemas/User")
 *         ),
 *         @OA\Property(property="current_page", type="integer", example=1),
 *         @OA\Property(property="per_page", type="integer", example=10),
 *         @OA\Property(property="total", type="integer", example=50),
 *         @OA\Property(property="last_page", type="integer", example=5)
 *     )
 * )
 *
 * @OA\Schema(
 *     schema="UserDetailResponse",
 *     type="object",
 *     @OA\Property(property="code", type="integer", example=200),
 *     @OA\Property(property="status", type="boolean", example=true),
 *     @OA\Property(property="message", type="string", example="Lấy thông tin thành công"),
 *     @OA\Property(
 *         property="data",
 *         ref="#/components/schemas/User"
 *     )
 * )
 */
class UserSchemas
{
    // Chứa các schema definitions cho User endpoints
}
