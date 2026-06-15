<?php

namespace App\Swagger\Schemas;

/**
 * @OA\Schema(
 *     schema="LoginRequest",
 *     type="object",
 *     required={"email", "password"},
 *     @OA\Property(
 *         property="email",
 *         type="string",
 *         format="email",
 *         example="seller.gold@example.com",
 *         description="Địa chỉ email đăng nhập"
 *     ),
 *     @OA\Property(
 *         property="password",
 *         type="string",
 *         format="password",
 *         example="password",
 *         description="Mật khẩu"
 *     )
 * )
 *
 * @OA\Schema(
 *     schema="LoginResponse",
 *     type="object",
 *     @OA\Property(property="code", type="integer", example=200),
 *     @OA\Property(property="status", type="boolean", example=true),
 *     @OA\Property(property="message", type="string", example="Đăng nhập thành công"),
 *     @OA\Property(
 *         property="data",
 *         type="object",
 *         @OA\Property(property="access_token", type="string", example="eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...", description="JWT token"),
 *         @OA\Property(property="token_type", type="string", example="bearer", description="Loại token"),
 *         @OA\Property(property="expires_in", type="integer", example=3600, description="Thời gian hết hạn (giây)"),
 *         @OA\Property(
 *             property="user",
 *             ref="#/components/schemas/UserProfile",
 *             description="Thông tin người dùng"
 *         )
 *     )
 * )
 *
 * @OA\Schema(
 *     schema="UserProfile",
 *     type="object",
 *     @OA\Property(property="id", type="integer", example=1, description="ID người dùng"),
 *     @OA\Property(property="email", type="string", example="seller.gold@example.com", description="Email"),
 *     @OA\Property(property="username", type="string", example="seller_gold", description="Tên đăng nhập"),
 *     @OA\Property(property="first_name", type="string", example="John", description="Tên"),
 *     @OA\Property(property="last_name", type="string", example="Doe", description="Họ"),
 *     @OA\Property(property="phone", type="string", nullable=true, example="0123456789", description="Số điện thoại"),
 *     @OA\Property(property="status", type="string", enum={"Unconfirmed", "Active", "Banned"}, example="Active", description="Trạng thái"),
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
 *         description="Hồ sơ người dùng",
 *         @OA\Property(property="balance", type="number", format="float", example=1250.50, description="Số dư ví"),
 *         @OA\Property(property="avatar", type="string", nullable=true, example="https://example.com/avatar.jpg", description="Ảnh đại diện"),
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
 *     schema="MeResponse",
 *     type="object",
 *     @OA\Property(property="code", type="integer", example=200),
 *     @OA\Property(property="status", type="boolean", example=true),
 *     @OA\Property(property="message", type="string", example="Lấy thông tin thành công"),
 *     @OA\Property(
 *         property="data",
 *         ref="#/components/schemas/UserProfile"
 *     )
 * )
 *
 * @OA\Schema(
 *     schema="LogoutResponse",
 *     type="object",
 *     @OA\Property(property="code", type="integer", example=200),
 *     @OA\Property(property="status", type="boolean", example=true),
 *     @OA\Property(property="message", type="string", example="Đăng xuất thành công")
 * )
 */
class AuthSchemas
{
    // Chứa các schema definitions cho Auth endpoints
}
