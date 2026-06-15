<?php

namespace App\Swagger\Docs;

/**
 * @OA\Post(
 *     path="/api/auth/login",
 *     operationId="authLogin",
 *     tags={"Auth"},
 *     summary="Đăng nhập",
 *     description="Xác thực người dùng và trả về JWT token",
 *     @OA\RequestBody(
 *         required=true,
 *         description="Thông tin đăng nhập",
 *         @OA\JsonContent(ref="#/components/schemas/LoginRequest")
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="Đăng nhập thành công",
 *         @OA\JsonContent(ref="#/components/schemas/LoginResponse")
 *     ),
 *     @OA\Response(
 *         response=401,
 *         description="Thông tin đăng nhập không hợp lệ",
 *         @OA\JsonContent(
 *             @OA\Property(property="code", type="integer", example=401),
 *             @OA\Property(property="status", type="boolean", example=false),
 *             @OA\Property(property="message", type="string", example="Email hoặc mật khẩu không đúng")
 *         )
 *     ),
 *     @OA\Response(
 *         response=422,
 *         description="Lỗi validation",
 *         @OA\JsonContent(ref="#/components/schemas/ValidationErrorResponse")
 *     )
 * )
 *
 * @OA\Post(
 *     path="/api/auth/logout",
 *     operationId="authLogout",
 *     tags={"Auth"},
 *     summary="Đăng xuất",
 *     description="Hủy token JWT hiện tại",
 *     security={{"bearerAuth":{}}},
 *     @OA\Response(
 *         response=200,
 *         description="Đăng xuất thành công",
 *         @OA\JsonContent(ref="#/components/schemas/LogoutResponse")
 *     ),
 *     @OA\Response(
 *         response=401,
 *         description="Chưa đăng nhập",
 *         @OA\JsonContent(ref="#/components/schemas/UnauthorizedResponse")
 *     )
 * )
 *
 * @OA\Get(
 *     path="/api/auth/me",
 *     operationId="authMe",
 *     tags={"Auth"},
 *     summary="Thông tin người dùng hiện tại",
 *     description="Lấy thông tin hồ sơ của người dùng đang đăng nhập",
 *     security={{"bearerAuth":{}}},
 *     @OA\Response(
 *         response=200,
 *         description="Lấy thông tin thành công",
 *         @OA\JsonContent(ref="#/components/schemas/MeResponse")
 *     ),
 *     @OA\Response(
 *         response=401,
 *         description="Chưa đăng nhập",
 *         @OA\JsonContent(ref="#/components/schemas/UnauthorizedResponse")
 *     )
 * )
 */
class AuthDocs
{
    // Chứa Swagger documentation cho Auth endpoints
    // Logic thực tế trong AuthController
}
