<?php

namespace Database\Seeders;

use App\Http\Controllers\PermissionController;
use App\Models\Permission;
use Illuminate\Database\Seeder;

class PermissionSeeder extends Seeder
{
    public function run(): void
    {
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
    }
}
