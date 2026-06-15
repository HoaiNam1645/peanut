<?php

namespace App\Swagger\Seller\Docs;

/**
 * @OA\Post(
 *     path="/api/auth/login",
 *     summary="Đăng nhập",
 *     description="Đăng nhập vào hệ thống và nhận JWT token",
 *     operationId="sellerLogin",
 *     tags={"Auth"},
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\JsonContent(
 *             required={"email", "password"},
 *             @OA\Property(property="email", type="string", format="email", example="seller@example.com", description="Email đăng nhập"),
 *             @OA\Property(property="password", type="string", format="password", example="password123", description="Mật khẩu")
 *         )
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="Đăng nhập thành công",
 *         @OA\JsonContent(
 *             @OA\Property(property="code", type="integer", example=200),
 *             @OA\Property(property="status", type="boolean", example=true),
 *             @OA\Property(property="message", type="string", example="Login successful"),
 *             @OA\Property(
 *                 property="data",
 *                 type="object",
 *                 @OA\Property(property="access_token", type="string", example="eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9..."),
 *                 @OA\Property(property="token_type", type="string", example="bearer"),
 *                 @OA\Property(property="expires_in", type="integer", example=3600, description="Thời gian hết hạn (giây)"),
 *                 @OA\Property(
 *                     property="user",
 *                     type="object",
 *                     @OA\Property(property="id", type="integer", example=1),
 *                     @OA\Property(property="username", type="string", example="seller_shop"),
 *                     @OA\Property(property="email", type="string", example="seller@example.com"),
 *                     @OA\Property(property="role", type="string", example="Seller"),
 *                     @OA\Property(property="tier_id", type="integer", example=1, description="Cấp bậc seller: 0=Silver, 1=Gold, 2=Platinum, 3=Diamond")
 *                 )
 *             )
 *         )
 *     ),
 *     @OA\Response(
 *         response=401,
 *         description="Thông tin đăng nhập không chính xác",
 *         @OA\JsonContent(
 *             @OA\Property(property="code", type="integer", example=401),
 *             @OA\Property(property="status", type="boolean", example=false),
 *             @OA\Property(property="message", type="string", example="Invalid credentials")
 *         )
 *     ),
 *     @OA\Response(
 *         response=422,
 *         description="Validation error",
 *         @OA\JsonContent(
 *             @OA\Property(property="code", type="integer", example=422),
 *             @OA\Property(property="status", type="boolean", example=false),
 *             @OA\Property(property="message", type="string", example="Validation failed"),
 *             @OA\Property(property="errors", type="object",
 *                 @OA\Property(property="email", type="array", @OA\Items(type="string", example="The email field is required.")),
 *                 @OA\Property(property="password", type="array", @OA\Items(type="string", example="The password field is required."))
 *             )
 *         )
 *     )
 * )
 *
 * @OA\Post(
 *     path="/api/auth/logout",
 *     summary="Đăng xuất",
 *     description="Đăng xuất và vô hiệu hóa JWT token hiện tại",
 *     operationId="sellerLogout",
 *     tags={"Auth"},
 *     security={{"bearerAuth": {}}},
 *     @OA\Response(
 *         response=200,
 *         description="Đăng xuất thành công",
 *         @OA\JsonContent(
 *             @OA\Property(property="code", type="integer", example=200),
 *             @OA\Property(property="status", type="boolean", example=true),
 *             @OA\Property(property="message", type="string", example="Successfully logged out")
 *         )
 *     ),
 *     @OA\Response(
 *         response=401,
 *         description="Chưa đăng nhập hoặc token không hợp lệ",
 *         @OA\JsonContent(
 *             @OA\Property(property="code", type="integer", example=401),
 *             @OA\Property(property="status", type="boolean", example=false),
 *             @OA\Property(property="message", type="string", example="Unauthenticated")
 *         )
 *     )
 * )
 *
 * @OA\Get(
 *     path="/api/auth/me",
 *     summary="Lấy thông tin user hiện tại",
 *     description="Lấy thông tin của user đang đăng nhập",
 *     operationId="sellerMe",
 *     tags={"Auth"},
 *     security={{"bearerAuth": {}}},
 *     @OA\Response(
 *         response=200,
 *         description="Thành công",
 *         @OA\JsonContent(
 *             @OA\Property(property="code", type="integer", example=200),
 *             @OA\Property(property="status", type="boolean", example=true),
 *             @OA\Property(
 *                 property="data",
 *                 type="object",
 *                 @OA\Property(property="id", type="integer", example=1),
 *                 @OA\Property(property="username", type="string", example="seller_shop"),
 *                 @OA\Property(property="email", type="string", example="seller@example.com"),
 *                 @OA\Property(property="role", type="string", example="Seller"),
 *                 @OA\Property(property="tier_id", type="integer", example=1),
 *                 @OA\Property(property="balance", type="number", example=150.50, description="Số dư ví"),
 *                 @OA\Property(property="created_at", type="string", format="date-time", example="2026-01-01T00:00:00Z")
 *             )
 *         )
 *     ),
 *     @OA\Response(
 *         response=401,
 *         description="Chưa đăng nhập",
 *         @OA\JsonContent(
 *             @OA\Property(property="code", type="integer", example=401),
 *             @OA\Property(property="status", type="boolean", example=false),
 *             @OA\Property(property="message", type="string", example="Unauthenticated")
 *         )
 *     )
 * )
 */
class AuthDocs
{
    // Class container for Seller Auth API documentation
}
