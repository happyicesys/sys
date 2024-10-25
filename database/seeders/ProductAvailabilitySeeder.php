<?php

namespace Database\Seeders;

use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ProductAvailabilitySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {

        $productPermission = Permission::where('name', 'read products')->first();

        $productListPermission = Permission::updateOrCreate([
            'name' => 'read product-lists',
            'guard_name' => 'web'
        ]);

        $availabilityPermission = Permission::updateOrCreate([
            'name' => 'read product-availability',
            'guard_name' => 'web'
        ]);

        $adminAvailabilityPermission = Permission::updateOrCreate([
            'name' => 'admin-access product-availability',
            'guard_name' => 'web'
        ]);


        $opsRoles = Role::whereIn('name', ['superadmin', 'admin', 'supervisor', 'operator_admin', 'driver', 'technician'])->get();

        $adminRoles = Role::whereIn('name', ['superadmin', 'admin', 'supervisor', 'operator_admin'])->get();

        if($opsRoles) {
            foreach($opsRoles as $role) {
                $role->givePermissionTo($productPermission);
                $role->givePermissionTo($availabilityPermission);
            }
        }

        if($adminRoles) {
            foreach($adminRoles as $role) {
                $role->givePermissionTo($productListPermission);
                $role->givePermissionTo($adminAvailabilityPermission);
            }
        }
    }
}
