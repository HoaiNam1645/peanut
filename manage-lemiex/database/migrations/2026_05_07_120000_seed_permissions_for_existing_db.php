<?php

use App\Http\Controllers\PermissionController;
use App\Models\Permission;
use App\Models\Role;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Seeds permissions and role-permission defaults for existing databases.
 *
 * For fresh installs, DatabaseSeeder also seeds these — this migration
 * ensures permissions are available without re-running the full seeder.
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('permissions') || !Schema::hasTable('roles')) {
            return;
        }

        // Seed permissions table
        foreach (PermissionController::generatePermissionsFromRoutes() as $perm) {
            Permission::updateOrCreate(
                ['name' => $perm['name']],
                [
                    'display_name' => $perm['display_name'],
                    'group' => $perm['group'] ?? null,
                    'route' => $perm['route'] ?? null,
                    'method' => $perm['method'] ?? null,
                    'removable' => $perm['removable'] ?? true,
                ]
            );
        }

        // Apply default role permissions, preserving any existing permissions
        $allPermissionIds = Permission::pluck('id', 'name')->toArray();
        $map = (new RolePermissionSeeder())->rolePermissionMap();

        foreach ($map as $roleName => $permissionNames) {
            $role = Role::where('name', $roleName)->first();
            if (!$role) continue;

            $ids = in_array('*', $permissionNames, true)
                ? array_values($allPermissionIds)
                : array_values(array_filter(array_map(
                    fn($name) => $allPermissionIds[$name] ?? null,
                    $permissionNames
                )));

            // Attach without detaching existing permissions (e.g. legacy page.* perms)
            $role->permissions()->syncWithoutDetaching($ids);
        }
    }

    public function down(): void
    {
        // No-op: permissions are managed via UI; rolling back risks lockout
    }
};
