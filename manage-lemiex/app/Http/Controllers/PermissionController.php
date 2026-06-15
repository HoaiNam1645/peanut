<?php

namespace App\Http\Controllers;

use App\Constants\HttpCode;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PermissionController extends Controller
{
    /**
     * Get all permissions grouped by group
     */
    public function getAll(): JsonResponse
    {
        try {
            $permissions = Permission::orderBy('group')->orderBy('name')->get();
            $grouped = $permissions->groupBy('group');

            return response()->json([
                'code' => HttpCode::SUCCESS,
                'status' => true,
                'message' => 'Permissions retrieved successfully',
                'data' => [
                    'permissions' => $permissions,
                    'grouped' => $grouped,
                    'groups' => $grouped->keys(),
                ]
            ], HttpCode::SUCCESS);
        } catch (\Exception $e) {
            Log::error('Failed to get permissions', ['error' => $e->getMessage()]);
            return response()->json([
                'code' => HttpCode::SERVER_ERROR,
                'status' => false,
                'message' => 'Failed to get permissions: ' . $e->getMessage(),
            ], HttpCode::SERVER_ERROR);
        }
    }

    /**
     * Get all roles with their permissions
     */
    public function getRolesWithPermissions(): JsonResponse
    {
        try {
            $roles = Role::with('permissions')->get();
            $permissions = Permission::orderBy('group')->orderBy('name')->get();
            $grouped = $permissions->groupBy('group');

            // Create permission matrix
            $matrix = [];
            foreach ($roles as $role) {
                $rolePermissions = $role->permissions->pluck('id')->toArray();
                $matrix[$role->id] = [
                    'role' => $role,
                    'permissions' => $rolePermissions,
                ];
            }

            return response()->json([
                'code' => HttpCode::SUCCESS,
                'status' => true,
                'message' => 'Roles with permissions retrieved successfully',
                'data' => [
                    'roles' => $roles,
                    'permissions' => $permissions,
                    'grouped' => $grouped,
                    'groups' => $grouped->keys(),
                    'matrix' => $matrix,
                ]
            ], HttpCode::SUCCESS);
        } catch (\Exception $e) {
            Log::error('Failed to get roles with permissions', ['error' => $e->getMessage()]);
            return response()->json([
                'code' => HttpCode::SERVER_ERROR,
                'status' => false,
                'message' => 'Failed to get roles with permissions: ' . $e->getMessage(),
            ], HttpCode::SERVER_ERROR);
        }
    }

    /**
     * Update permissions for a role
     */
    public function updateRolePermissions(Request $request, int $roleId): JsonResponse
    {
        try {
            $request->validate([
                'permissions' => 'required|array',
                'permissions.*' => 'integer|exists:permissions,id',
            ]);

            $role = Role::findOrFail($roleId);

            DB::beginTransaction();

            // Sync permissions
            $role->syncPermissions($request->permissions);

            DB::commit();

            // Reload role with permissions
            $role->load('permissions');

            Log::info('Updated role permissions', [
                'role_id' => $roleId,
                'role_name' => $role->name,
                'permissions_count' => count($request->permissions),
            ]);

            return response()->json([
                'code' => HttpCode::SUCCESS,
                'status' => true,
                'message' => 'Permissions updated successfully',
                'data' => [
                    'role' => $role,
                    'permissions' => $role->permissions->pluck('id')->toArray(),
                ]
            ], HttpCode::SUCCESS);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to update role permissions', [
                'role_id' => $roleId,
                'error' => $e->getMessage()
            ]);
            return response()->json([
                'code' => HttpCode::SERVER_ERROR,
                'status' => false,
                'message' => 'Failed to update permissions: ' . $e->getMessage(),
            ], HttpCode::SERVER_ERROR);
        }
    }

    /**
     * Create a new permission
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'name' => 'required|string|unique:permissions,name',
                'display_name' => 'required|string',
                'description' => 'nullable|string',
                'group' => 'nullable|string',
                'route' => 'nullable|string',
                'method' => 'nullable|string',
            ]);

            $permission = Permission::create($validated);

            Log::info('Created permission', ['permission' => $permission->name]);

            return response()->json([
                'code' => HttpCode::SUCCESS,
                'status' => true,
                'message' => 'Permission created successfully',
                'data' => $permission
            ], HttpCode::SUCCESS);
        } catch (\Exception $e) {
            Log::error('Failed to create permission', ['error' => $e->getMessage()]);
            return response()->json([
                'code' => HttpCode::SERVER_ERROR,
                'status' => false,
                'message' => 'Failed to create permission: ' . $e->getMessage(),
            ], HttpCode::SERVER_ERROR);
        }
    }

    /**
     * Update a permission
     */
    public function update(Request $request, int $id): JsonResponse
    {
        try {
            $permission = Permission::findOrFail($id);

            $validated = $request->validate([
                'name' => 'sometimes|string|unique:permissions,name,' . $id,
                'display_name' => 'sometimes|string',
                'description' => 'nullable|string',
                'group' => 'nullable|string',
                'route' => 'nullable|string',
                'method' => 'nullable|string',
            ]);

            $permission->update($validated);

            Log::info('Updated permission', ['permission' => $permission->name]);

            return response()->json([
                'code' => HttpCode::SUCCESS,
                'status' => true,
                'message' => 'Permission updated successfully',
                'data' => $permission
            ], HttpCode::SUCCESS);
        } catch (\Exception $e) {
            Log::error('Failed to update permission', ['error' => $e->getMessage()]);
            return response()->json([
                'code' => HttpCode::SERVER_ERROR,
                'status' => false,
                'message' => 'Failed to update permission: ' . $e->getMessage(),
            ], HttpCode::SERVER_ERROR);
        }
    }

    /**
     * Delete a permission
     */
    public function destroy(int $id): JsonResponse
    {
        try {
            $permission = Permission::findOrFail($id);

            if (!$permission->removable) {
                return response()->json([
                    'code' => HttpCode::BAD_REQUEST,
                    'status' => false,
                    'message' => 'This permission cannot be deleted',
                ], HttpCode::BAD_REQUEST);
            }

            // Detach from all roles first
            $permission->roles()->detach();
            $permission->delete();

            Log::info('Deleted permission', ['permission' => $permission->name]);

            return response()->json([
                'code' => HttpCode::SUCCESS,
                'status' => true,
                'message' => 'Permission deleted successfully',
            ], HttpCode::SUCCESS);
        } catch (\Exception $e) {
            Log::error('Failed to delete permission', ['error' => $e->getMessage()]);
            return response()->json([
                'code' => HttpCode::SERVER_ERROR,
                'status' => false,
                'message' => 'Failed to delete permission: ' . $e->getMessage(),
            ], HttpCode::SERVER_ERROR);
        }
    }

    /**
     * Create a new role
     */
    public function storeRole(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'name' => 'required|string|max:50|unique:roles,name|regex:/^[A-Za-z][A-Za-z0-9_]*$/',
                'display_name' => 'required|string|max:100',
                'description' => 'nullable|string|max:500',
                'permission_ids' => 'nullable|array',
                'permission_ids.*' => 'integer|exists:permissions,id',
            ]);

            DB::beginTransaction();

            $role = Role::create([
                'name' => $validated['name'],
                'display_name' => $validated['display_name'],
                'description' => $validated['description'] ?? null,
                'removable' => true,
            ]);

            if (!empty($validated['permission_ids'])) {
                $role->permissions()->sync($validated['permission_ids']);
            }

            DB::commit();

            return response()->json([
                'code' => HttpCode::SUCCESS,
                'status' => true,
                'message' => 'Role created successfully',
                'data' => $role->load('permissions'),
            ], HttpCode::SUCCESS);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'code' => HttpCode::BAD_REQUEST,
                'status' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], HttpCode::BAD_REQUEST);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to create role', ['error' => $e->getMessage()]);
            return response()->json([
                'code' => HttpCode::SERVER_ERROR,
                'status' => false,
                'message' => 'Failed to create role: ' . $e->getMessage(),
            ], HttpCode::SERVER_ERROR);
        }
    }

    /**
     * Update a role's metadata
     */
    public function updateRole(Request $request, int $id): JsonResponse
    {
        try {
            $role = Role::findOrFail($id);

            $validated = $request->validate([
                'name' => 'sometimes|string|max:50|unique:roles,name,' . $id . '|regex:/^[A-Za-z][A-Za-z0-9_]*$/',
                'display_name' => 'sometimes|string|max:100',
                'description' => 'nullable|string|max:500',
            ]);

            // Built-in roles: prevent renaming "name" to keep middleware bypass logic intact
            if (!$role->removable && isset($validated['name']) && $validated['name'] !== $role->name) {
                return response()->json([
                    'code' => HttpCode::BAD_REQUEST,
                    'status' => false,
                    'message' => 'Cannot rename built-in role',
                ], HttpCode::BAD_REQUEST);
            }

            $role->update($validated);

            return response()->json([
                'code' => HttpCode::SUCCESS,
                'status' => true,
                'message' => 'Role updated successfully',
                'data' => $role->fresh(),
            ], HttpCode::SUCCESS);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'code' => HttpCode::BAD_REQUEST,
                'status' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], HttpCode::BAD_REQUEST);
        } catch (\Exception $e) {
            Log::error('Failed to update role', ['error' => $e->getMessage()]);
            return response()->json([
                'code' => HttpCode::SERVER_ERROR,
                'status' => false,
                'message' => 'Failed to update role: ' . $e->getMessage(),
            ], HttpCode::SERVER_ERROR);
        }
    }

    /**
     * Delete a role (only removable ones, and only if no users assigned)
     */
    public function destroyRole(int $id): JsonResponse
    {
        try {
            $role = Role::findOrFail($id);

            if (!$role->removable) {
                return response()->json([
                    'code' => HttpCode::BAD_REQUEST,
                    'status' => false,
                    'message' => 'Cannot delete built-in role',
                ], HttpCode::BAD_REQUEST);
            }

            $userCount = $role->users()->count();
            if ($userCount > 0) {
                return response()->json([
                    'code' => HttpCode::BAD_REQUEST,
                    'status' => false,
                    'message' => "Cannot delete role: {$userCount} user(s) still assigned",
                ], HttpCode::BAD_REQUEST);
            }

            DB::beginTransaction();
            $role->permissions()->detach();
            $role->delete();
            DB::commit();

            return response()->json([
                'code' => HttpCode::SUCCESS,
                'status' => true,
                'message' => 'Role deleted successfully',
            ], HttpCode::SUCCESS);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to delete role', ['error' => $e->getMessage()]);
            return response()->json([
                'code' => HttpCode::SERVER_ERROR,
                'status' => false,
                'message' => 'Failed to delete role: ' . $e->getMessage(),
            ], HttpCode::SERVER_ERROR);
        }
    }

    /**
     * Seed permissions from routes
     */
    public function seedFromRoutes(): JsonResponse
    {
        try {
            $permissions = self::generatePermissionsFromRoutes();
            $created = 0;

            DB::beginTransaction();

            foreach ($permissions as $perm) {
                $existing = Permission::where('name', $perm['name'])->first();
                if (!$existing) {
                    Permission::create($perm);
                    $created++;
                }
            }

            DB::commit();

            return response()->json([
                'code' => HttpCode::SUCCESS,
                'status' => true,
                'message' => "Created {$created} new permissions",
                'data' => [
                    'created' => $created,
                    'total_defined' => count($permissions),
                ]
            ], HttpCode::SUCCESS);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to seed permissions', ['error' => $e->getMessage()]);
            return response()->json([
                'code' => HttpCode::SERVER_ERROR,
                'status' => false,
                'message' => 'Failed to seed permissions: ' . $e->getMessage(),
            ], HttpCode::SERVER_ERROR);
        }
    }

    /**
     * Generate permissions list from API routes
     */
    public static function generatePermissionsFromRoutes(): array
    {
        return [
            // Dashboard
            ['name' => 'dashboard.view', 'display_name' => 'Xem Dashboard', 'group' => 'Dashboard', 'route' => '/dashboard/*', 'method' => 'GET', 'removable' => false],

            // Orders
            ['name' => 'orders.view', 'display_name' => 'Xem danh sách đơn hàng', 'group' => 'Orders', 'route' => '/orders', 'method' => 'GET', 'removable' => false],
            ['name' => 'orders.view.detail', 'display_name' => 'Xem chi tiết đơn hàng', 'group' => 'Orders', 'route' => '/orders/{id}', 'method' => 'GET', 'removable' => false],
            ['name' => 'orders.create', 'display_name' => 'Tạo đơn hàng', 'group' => 'Orders', 'route' => '/orders/create', 'method' => 'POST', 'removable' => false],
            ['name' => 'orders.update', 'display_name' => 'Cập nhật đơn hàng', 'group' => 'Orders', 'route' => '/orders/update', 'method' => 'PUT', 'removable' => false],
            ['name' => 'orders.change_status', 'display_name' => 'Thay đổi trạng thái đơn', 'group' => 'Orders', 'route' => '/orders/change-fulfill-status', 'method' => 'PUT', 'removable' => false],
            ['name' => 'orders.cancel', 'display_name' => 'Hủy đơn hàng', 'group' => 'Orders', 'route' => '/orders/cancel', 'method' => 'POST', 'removable' => false],
            ['name' => 'orders.seller_cancel', 'display_name' => 'Seller hủy đơn', 'group' => 'Orders', 'route' => '/orders/seller-cancel', 'method' => 'POST', 'removable' => false],
            ['name' => 'orders.remake', 'display_name' => 'Remake file/QR', 'group' => 'Orders', 'route' => '/orders/remake/*', 'method' => 'PUT', 'removable' => false],
            ['name' => 'orders.batch_remake', 'display_name' => 'Batch remake QR/Des', 'group' => 'Orders', 'route' => '/orders/batch-remake-*', 'method' => 'POST', 'removable' => false],
            ['name' => 'orders.qc_reject', 'display_name' => 'QC reject item', 'group' => 'Orders', 'route' => '/orders/qc-reject', 'method' => 'PUT', 'removable' => false],
            ['name' => 'orders.buy_label', 'display_name' => 'Mua shipping label', 'group' => 'Orders', 'route' => '/buy-label/*', 'method' => 'POST', 'removable' => false],
            ['name' => 'orders.upload_video', 'display_name' => 'Upload video', 'group' => 'Orders', 'route' => '/orders/upload-video', 'method' => 'POST', 'removable' => false],
            ['name' => 'orders.view_videos', 'display_name' => 'Xem video đơn hàng', 'group' => 'Orders', 'route' => '/orders/videos', 'method' => 'GET', 'removable' => false],
            ['name' => 'orders.export_ids', 'display_name' => 'Export danh sách IDs', 'group' => 'Orders', 'route' => '/orders/ids', 'method' => 'GET', 'removable' => false],
            ['name' => 'orders.export_urls', 'display_name' => 'Export URLs', 'group' => 'Orders', 'route' => '/orders/export-urls', 'method' => 'POST', 'removable' => false],

            // Products
            ['name' => 'products.view', 'display_name' => 'Xem danh sách sản phẩm', 'group' => 'Products', 'route' => '/products', 'method' => 'GET', 'removable' => false],
            ['name' => 'products.create', 'display_name' => 'Tạo sản phẩm', 'group' => 'Products', 'route' => '/products', 'method' => 'POST', 'removable' => false],
            ['name' => 'products.update', 'display_name' => 'Cập nhật sản phẩm', 'group' => 'Products', 'route' => '/products/{id}', 'method' => 'PUT', 'removable' => false],
            ['name' => 'products.import', 'display_name' => 'Import sản phẩm', 'group' => 'Products', 'route' => '/products/import', 'method' => 'POST', 'removable' => false],
            ['name' => 'products.variants', 'display_name' => 'Quản lý variants', 'group' => 'Products', 'route' => '/products/variants/*', 'method' => '*', 'removable' => false],

            // Stock
            ['name' => 'stock.view', 'display_name' => 'Xem tồn kho', 'group' => 'Stock', 'route' => '/stock', 'method' => 'GET', 'removable' => false],
            ['name' => 'stock.update', 'display_name' => 'Cập nhật tồn kho', 'group' => 'Stock', 'route' => '/stock/*', 'method' => 'PUT', 'removable' => false],
            ['name' => 'stock.import', 'display_name' => 'Import tồn kho', 'group' => 'Stock', 'route' => '/stock/imports', 'method' => 'POST', 'removable' => false],
            ['name' => 'stock.export', 'display_name' => 'Export tồn kho', 'group' => 'Stock', 'route' => '/stock/exports', 'method' => 'GET', 'removable' => false],
            ['name' => 'stock.shortage', 'display_name' => 'Xem báo cáo thiếu hàng', 'group' => 'Stock', 'route' => '/stock/shortage/*', 'method' => 'GET', 'removable' => false],
            ['name' => 'stock.audit_logs', 'display_name' => 'Xem lịch sử thay đổi kho', 'group' => 'Stock', 'route' => '/stock/audit-logs', 'method' => 'GET', 'removable' => false],

            // Users
            ['name' => 'users.view', 'display_name' => 'Xem danh sách users', 'group' => 'Users', 'route' => '/users', 'method' => 'GET', 'removable' => false],
            ['name' => 'users.create', 'display_name' => 'Tạo user', 'group' => 'Users', 'route' => '/users', 'method' => 'POST', 'removable' => false],
            ['name' => 'users.update', 'display_name' => 'Cập nhật user', 'group' => 'Users', 'route' => '/users/{id}', 'method' => 'PUT', 'removable' => false],
            ['name' => 'users.delete', 'display_name' => 'Xóa user', 'group' => 'Users', 'route' => '/users/{id}', 'method' => 'DELETE', 'removable' => false],

            // Stores
            ['name' => 'stores.view', 'display_name' => 'Xem danh sách stores', 'group' => 'Stores', 'route' => '/stores', 'method' => 'GET', 'removable' => false],
            ['name' => 'stores.create', 'display_name' => 'Tạo store', 'group' => 'Stores', 'route' => '/stores', 'method' => 'POST', 'removable' => false],
            ['name' => 'stores.update', 'display_name' => 'Cập nhật store', 'group' => 'Stores', 'route' => '/stores/{id}', 'method' => 'PUT', 'removable' => false],

            // Transactions
            ['name' => 'transactions.view', 'display_name' => 'Xem giao dịch', 'group' => 'Transactions', 'route' => '/transactions', 'method' => 'GET', 'removable' => false],
            ['name' => 'transactions.add_fund', 'display_name' => 'Nạp tiền', 'group' => 'Transactions', 'route' => '/transactions/add-fund', 'method' => 'POST', 'removable' => false],
            ['name' => 'transactions.export', 'display_name' => 'Export giao dịch', 'group' => 'Transactions', 'route' => '/transactions/export', 'method' => 'GET', 'removable' => false],
            ['name' => 'transactions.approve_funds', 'display_name' => 'Duyệt yêu cầu nạp/rút tiền', 'group' => 'Transactions', 'route' => '/transactions/{id}/approve', 'method' => 'POST', 'removable' => false],

            // Support Tickets
            ['name' => 'tickets.view', 'display_name' => 'Xem tickets', 'group' => 'Support', 'route' => '/tickets', 'method' => 'GET', 'removable' => false],
            ['name' => 'tickets.create', 'display_name' => 'Tạo ticket', 'group' => 'Support', 'route' => '/tickets', 'method' => 'POST', 'removable' => false],
            ['name' => 'tickets.update_status', 'display_name' => 'Cập nhật trạng thái ticket', 'group' => 'Support', 'route' => '/tickets/{id}/status', 'method' => 'PUT', 'removable' => false],
            ['name' => 'tickets.send_message', 'display_name' => 'Gửi tin nhắn ticket', 'group' => 'Support', 'route' => '/tickets/{id}/messages', 'method' => 'POST', 'removable' => false],

            // Tiers
            ['name' => 'tiers.view', 'display_name' => 'Xem tiers', 'group' => 'Tiers', 'route' => '/tiers', 'method' => 'GET', 'removable' => false],
            ['name' => 'tiers.manage', 'display_name' => 'Quản lý tiers', 'group' => 'Tiers', 'route' => '/tiers/*', 'method' => '*', 'removable' => false],

            // Fulfillment Priorities
            ['name' => 'fulfillment.view', 'display_name' => 'Xem ưu tiên fulfillment', 'group' => 'Fulfillment', 'route' => '/fulfillment-priorities', 'method' => 'GET', 'removable' => false],
            ['name' => 'fulfillment.manage', 'display_name' => 'Quản lý ưu tiên fulfillment', 'group' => 'Fulfillment', 'route' => '/fulfillment-priorities', 'method' => 'PUT', 'removable' => false],

            // Reports
            ['name' => 'reports.view', 'display_name' => 'Xem báo cáo', 'group' => 'Reports', 'route' => '/reports/*', 'method' => 'GET', 'removable' => false],
            ['name' => 'reports.staff', 'display_name' => 'Xem báo cáo nhân viên', 'group' => 'Reports', 'route' => '/reports/staff', 'method' => 'GET', 'removable' => false],

            // Permissions
            ['name' => 'permissions.view', 'display_name' => 'Xem phân quyền', 'group' => 'Permissions', 'route' => '/permissions', 'method' => 'GET', 'removable' => false],
            ['name' => 'permissions.manage', 'display_name' => 'Quản lý phân quyền', 'group' => 'Permissions', 'route' => '/permissions/*', 'method' => '*', 'removable' => false],

            // Attendance
            ['name' => 'attendance.view', 'display_name' => 'Xem chấm công', 'group' => 'Attendance', 'route' => '/attendances', 'method' => 'GET', 'removable' => false],
            ['name' => 'attendance.import', 'display_name' => 'Import chấm công', 'group' => 'Attendance', 'route' => '/attendances/import', 'method' => 'POST', 'removable' => false],

            // Payroll
            ['name' => 'payroll.view', 'display_name' => 'Xem bảng lương', 'group' => 'Payroll', 'route' => '/payroll/*', 'method' => 'GET', 'removable' => false],
            ['name' => 'payroll.manage', 'display_name' => 'Quản lý bảng lương', 'group' => 'Payroll', 'route' => '/payroll/*', 'method' => '*', 'removable' => false],
        ];
    }
}
