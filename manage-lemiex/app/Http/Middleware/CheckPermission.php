<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Tymon\JWTAuth\Facades\JWTAuth;

class CheckPermission
{
    /**
     * Handle an incoming request.
     * 
     * Usage in routes:
     *   ->middleware('permission:orders.view')
     *   ->middleware('permission:orders.view,orders.create')  // OR logic
     */
    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        try {
            // Prefer user already attached by jwt.auth middleware (supports cookie + header)
            $user = auth('jwt')->user();

            // Fallback for routes that don't pre-run jwt.auth: try to parse from header/query
            if (!$user) {
                try {
                    $user = JWTAuth::parseToken()->authenticate();
                } catch (\Exception $e) {
                    $user = null;
                }
            }

            if (!$user) {
                return response()->json([
                    'code' => 401,
                    'status' => false,
                    'message' => 'Unauthorized',
                ], 401);
            }

            // Load role with permissions
            $user->load('role.permissions');

            // Admin and HR have all permissions
            if ($user->role && in_array($user->role->name, ['Admin', 'HR'], true)) {
                return $next($request);
            }

            // Check if user has any of the required permissions
            if (!$user->role || !$this->hasAnyPermission($user->role, $permissions)) {
                return response()->json([
                    'code' => 403,
                    'status' => false,
                    'message' => 'Bạn không có quyền thực hiện hành động này',
                    'required_permissions' => $permissions,
                ], 403);
            }

            return $next($request);
        } catch (\Exception $e) {
            return response()->json([
                'code' => 401,
                'status' => false,
                'message' => 'Unauthorized: ' . $e->getMessage(),
            ], 401);
        }
    }

    /**
     * Check if role has any of the given permissions
     */
    private function hasAnyPermission($role, array $permissions): bool
    {
        $rolePermissions = $role->permissions->pluck('name')->toArray();

        foreach ($permissions as $permission) {
            if (in_array($permission, $rolePermissions)) {
                return true;
            }
        }

        return false;
    }
}
