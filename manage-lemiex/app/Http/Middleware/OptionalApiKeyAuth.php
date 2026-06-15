<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Tymon\JWTAuth\Facades\JWTAuth;

class OptionalApiKeyAuth
{
    public function handle(Request $request, Closure $next)
    {
        $apiKey = $request->query('api_key');

        if ($apiKey) {
            $user = User::where('api_key', $apiKey)->first();
            
            if ($user && $user->role_id == 1) {
                \Illuminate\Support\Facades\Auth::setUser($user);
                return $next($request);
            }

            return response()->json([
                'status' => false,
                'message' => 'Invalid API key or insufficient permissions'
            ], 401);
        }

        try {
            $user = JWTAuth::parseToken()->authenticate();
            if ($user) {
                return $next($request);
            }
        } catch (\Exception $e) {
        }

        return response()->json([
            'status' => false,
            'message' => 'Unauthorized. Please provide valid JWT token or admin API key.'
        ], 401);
    }
}
