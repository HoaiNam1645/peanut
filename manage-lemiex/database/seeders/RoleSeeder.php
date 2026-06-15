<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            ['id' => 1, 'name' => 'Admin', 'display_name' => 'Admin', 'description' => 'System Administrator', 'removable' => false],
            ['id' => 2, 'name' => 'Seller', 'display_name' => 'Seller', 'description' => 'Seller Account', 'removable' => false],
            ['id' => 3, 'name' => 'Staff', 'display_name' => 'Staff', 'description' => 'Staff Member', 'removable' => false],
            ['id' => 4, 'name' => 'Supplier', 'display_name' => 'Supplier', 'description' => 'Supplier Account', 'removable' => false],
            ['id' => 5, 'name' => 'Support', 'display_name' => 'Support', 'description' => 'Support Team', 'removable' => false],
            ['id' => 6, 'name' => 'Designer', 'display_name' => 'Designer', 'description' => 'Designer', 'removable' => false],
            ['id' => 7, 'name' => 'Finance', 'display_name' => 'Finance', 'description' => 'Finance Team', 'removable' => false],
            ['id' => 8, 'name' => 'QC', 'display_name' => 'QC', 'description' => 'Quality Control Team', 'removable' => false],
            ['id' => 9, 'name' => 'Packing', 'display_name' => 'Packing', 'description' => 'Packing Team', 'removable' => false],
            ['id' => 10, 'name' => 'Shipout', 'display_name' => 'Shipout', 'description' => 'Shipping Team', 'removable' => false],
            ['id' => 11, 'name' => 'HR', 'display_name' => 'HR', 'description' => 'Human Resources Team', 'removable' => false],
        ];

        foreach ($roles as $role) {
            Role::create($role);
        }
    }
}
