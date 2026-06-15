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

class AdminMiddleware
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

            // Load role relationship nếu chưa có
            if (!$user->relationLoaded('role')) {
                $user->load('role');
            }

            // Kiểm tra role phải là ADMIN hoặc HR
            if (!in_array($user->role_id, [UserRole::ADMIN, UserRole::HR], true)) {
                return response()->json([
                    'code' => HttpCode::FORBIDDEN,
                    'status' => false,
                    'msgCode' => MsgCode::FORBIDDEN,
                    'message' => 'Bạn không có quyền truy cập. Chỉ ADMIN hoặc HR mới được phép.'
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
