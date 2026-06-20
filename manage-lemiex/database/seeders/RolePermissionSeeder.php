<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class RolePermissionSeeder extends Seeder
{
    /**
     * Default permissions per role.
     *
     * Note: Admin and HR auto-bypass via CheckPermission middleware,
     * so they don't strictly need permissions assigned, but we still
     * grant '*' for completeness if ever the bypass logic is removed.
     */
    public function rolePermissionMap(): array
    {
        return [
            'Admin' => ['*'], // bypass via middleware

            'HR' => ['*'], // bypass via middleware

            'Seller' => [
                'dashboard.view',
                'orders.view',
                'orders.view.detail',
                'orders.create',
                // Sellers may edit their own orders (designs, shipping, address).
                // UpdateOrderLabelShipRequest further restricts them to orders in
                // new_order / on_hold status only.
                'orders.update',
                'orders.seller_cancel',
                'orders.export_ids',
                'products.view',
                'products.variants',
                'stores.view',
                'stores.create',
                'stores.update',
                'tickets.view',
                'tickets.create',
                'tickets.send_message',
                'transactions.view',
                'transactions.add_fund',
                'transactions.export',
                'tiers.view',
            ],

            'Staff' => [
                'dashboard.view',
                'orders.view',
                'orders.view.detail',
                'orders.update',
                'orders.change_status',
                'orders.qc_reject',
                'orders.remake',
                'orders.batch_remake',
                'orders.buy_label',
                'orders.upload_video',
                'orders.view_videos',
                'orders.export_ids',
                'orders.export_urls',
                'stock.view',
                'stock.update',
                'stock.import',
                'stock.export',
                'stock.shortage',
                'stock.audit_logs',
                'attendance.view',
            ],

            'Support' => [
                'dashboard.view',
                'orders.view',
                'orders.view.detail',
                'orders.buy_label',
                'tickets.view',
                'tickets.create',
                'tickets.update_status',
                'tickets.send_message',
            ],

            'Designer' => [
                'dashboard.view',
                'orders.view',
                'orders.view.detail',
                'orders.change_status',
                'orders.upload_video',
                'orders.view_videos',
                'orders.remake',
                'orders.batch_remake',
            ],

            'Finance' => [
                'dashboard.view',
                'transactions.view',
                'transactions.add_fund',
                'transactions.export',
                'transactions.approve_funds',
                'reports.view',
                'reports.staff',
                'orders.view',
                'orders.view.detail',
                'payroll.view',
            ],

            'QC' => [
                'dashboard.view',
                'orders.view',
                'orders.view.detail',
                'orders.change_status',
                'orders.qc_reject',
            ],

            'Packing' => [
                'dashboard.view',
                'orders.view',
                'orders.view.detail',
                'orders.change_status',
            ],

            'Shipout' => [
                'dashboard.view',
                'orders.view',
                'orders.view.detail',
                'orders.change_status',
            ],

            'Supplier' => [
                'dashboard.view',
                'stock.view',
                'stock.update',
            ],
        ];
    }

    public function run(): void
    {
        $allPermissionIds = Permission::pluck('id', 'name')->toArray();

        foreach ($this->rolePermissionMap() as $roleName => $permissionNames) {
            $role = Role::where('name', $roleName)->first();
            if (!$role) continue;

            $ids = in_array('*', $permissionNames, true)
                ? array_values($allPermissionIds)
                : array_values(array_filter(array_map(
                    fn($name) => $allPermissionIds[$name] ?? null,
                    $permissionNames
                )));

            $role->permissions()->sync($ids);
        }
    }
}
