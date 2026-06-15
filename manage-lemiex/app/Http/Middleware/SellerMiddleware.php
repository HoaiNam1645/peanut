<?php

namespace App\Http\Middleware;

use App\Constants\HttpCode;
use App\Constants\MsgCode;
use App\Enums\UserRole;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Facades\JWTAuth;

class SellerMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        try {
            // Lấy token từ cookie
            $token = $request->cookie('token');
            
            if (!$token) {
                return response()->json([
                    'code' => HttpCode::UNAUTHORIZED,
                    'status' => false,
                    'msgCode' => MsgCode::UNAUTHORIZED,
                    'message' => 'Bạn chưa đăng nhập'
                ], HttpCode::UNAUTHORIZED);
            }

            JWTAuth::setToken($token);
            $user = JWTAuth::authenticate();

            if (!$user) {
                return response()->json([
                    'code' => HttpCode::UNAUTHORIZED,
                    'status' => false,
                    'msgCode' => MsgCode::UNAUTHORIZED,
                    'message' => 'Token không hợp lệ'
                ], HttpCode::UNAUTHORIZED);
            }

            // Kiểm tra role phải là SELLER hoặc ADMIN (Admin có thể truy cập mọi thứ)
            $allowedRoles = [UserRole::SELLER, UserRole::ADMIN];

            if (!$user->role || !in_array($user->role->id, $allowedRoles)) {
                return response()->json([
                    'code' => HttpCode::FORBIDDEN,
                    'status' => false,
                    'msgCode' => MsgCode::FORBIDDEN,
                    'message' => 'Bạn không có quyền truy cập. Chỉ SELLER hoặc ADMIN mới được phép.'
                ], HttpCode::FORBIDDEN);
            }

            auth('jwt')->setUser($user);
            return $next($request);

        } catch (JWTException $e) {
            return response()->json([
                'code' => HttpCode::UNAUTHORIZED,
                'status' => false,
                'msgCode' => MsgCode::UNAUTHORIZED,
                'message' => 'Token không hợp lệ hoặc đã hết hạn'
            ], HttpCode::UNAUTHORIZED);
        }
    }
}
