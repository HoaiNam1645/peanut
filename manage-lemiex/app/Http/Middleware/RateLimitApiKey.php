<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class RateLimitApiKey
{
    /**
     * Handle an incoming request.
     * Rate limit: 60 requests per minute per API key
     */
    public function handle(Request $request, Closure $next): Response
    {
        $apiKey = $request->header('X-API-Key') ?? $request->input('api_key');

        if (!$apiKey) {
            return response()->json([
                'code' => 401,
                'status' => false,
                'message' => 'API key is required'
            ], 401);
        }

        // Check if API key is locked
        $lockKey = "api_key_locked:{$apiKey}";
        if (Cache::has($lockKey)) {
            $ttl = Cache::get($lockKey);
            return response()->json([
                'code' => 429,
                'status' => false,
                'message' => 'API key is temporarily locked due to multiple failed attempts',
                'retry_after' => $ttl
            ], 429);
        }

        // Rate limiting
        $key = "rate_limit:order_create:{$apiKey}";
        $limit = 60; // requests per minute
        $decay = 60; // seconds

        $attempts = Cache::get($key, 0);

        if ($attempts >= $limit) {
            $retryAfter = Cache::get($key . ':timer', $decay);
            
            return response()->json([
                'code' => 429,
                'status' => false,
                'message' => 'Too many requests. Please try again later.'
            ], 429)->withHeaders([
                'X-RateLimit-Limit' => $limit,
                'X-RateLimit-Remaining' => 0,
                'Retry-After' => $retryAfter
            ]);
        }

        // Increment counter
        if ($attempts === 0) {
            Cache::put($key, 1, $decay);
            Cache::put($key . ':timer', $decay, $decay);
        } else {
            Cache::increment($key);
        }

        $remaining = $limit - ($attempts + 1);

        $response = $next($request);

        return $response->withHeaders([
            'X-RateLimit-Limit' => $limit,
            'X-RateLimit-Remaining' => max(0, $remaining)
        ]);
    }
}
