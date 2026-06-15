<?php

namespace App\Http\Middleware;

use App\Constants\HttpCode;
use App\Constants\MsgCode;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Facades\JWTAuth;

class JWTAuthMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        try {
            // Lấy token từ cookie hoặc Authorization header
            $token = $request->cookie('token');
            
            // Nếu không có token trong cookie, thử lấy từ Authorization header
            if (!$token) {
                $authHeader = $request->header('Authorization');
                if ($authHeader && str_starts_with($authHeader, 'Bearer ')) {
                    $token = substr($authHeader, 7); // Remove "Bearer " prefix
                }
            }
            
            if (!$token) {
                return response()->json([
                    'code' => HttpCode::UNAUTHORIZED,
                    'status' => false,
                    'msgCode' => MsgCode::UNAUTHORIZED,
                    'message' => 'Bạn chưa đăng nhập'
                ], HttpCode::UNAUTHORIZED);
            }

            // Set token và authenticate
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

            // Set user vào auth guard để có thể dùng $request->user()
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
