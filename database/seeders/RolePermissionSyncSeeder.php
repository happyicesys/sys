<?php

namespace Database\Seeders;

use DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class RolePermissionSyncSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // unbind all the permissions first
        $roles = Role::all();

        foreach($roles as $role) {
            $role->syncPermissions([]);
        }

        // Disable foreign key checks
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');



        // Delete all permissions
        Permission::truncate();

        // Re-enable foreign key checks
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');
        // Define permissions and link them to roles based on the data from the file
        $permissionsData = [
            ['dashboard',
            ['read', 'export'],
            ['superadmin', 'admin', 'supervisor', 'operator_admin', 'operator_viewer', 'licensee']],

            ['dashboard',
            ['admin-access'],
            ['superadmin', 'admin', 'supervisor']],

            ['vends',
            ['read', 'export'],
            ['superadmin', 'admin', 'supervisor', 'technician', 'driver', 'operator_admin', 'operator_viewer', 'operator_3pl', 'franchisee', 'licensee']],

            ['vends',
            ['update'],
            ['superadmin', 'admin', 'supervisor', 'operator_admin']],

            ['vends',
            ['admin-access'],
            ['superadmin', 'admin', 'supervisor']],

            ['vend-customers',
            ['read', 'export'],
            ['superadmin', 'admin', 'supervisor', 'technician', 'driver', 'operator_admin', 'operator_viewer', 'operator_3pl', 'franchisee', 'licensee']],

            ['vend-contracts',
            ['read', 'export'],
            ['superadmin', 'admin', 'supervisor', 'technician', 'operator_admin']],

            ['vend-customers',
            ['admin-access'],
            ['superadmin', 'admin', 'supervisor', 'technician', 'driver', 'operator_admin', 'operator_viewer']],

            ['vend-machines',
            ['read', 'export'],
            ['superadmin', 'admin', 'supervisor', 'technician']],

            ['vend-machines',
            ['admin-access'],
            ['superadmin', 'admin', 'supervisor', 'technician']],

            ['transactions',
            ['read', 'export'],
            ['superadmin', 'admin', 'supervisor', 'technician', 'operator_admin', 'operator_viewer', 'franchisee', 'licensee']],

            ['transactions',
            ['admin-access'],
            ['superadmin', 'admin', 'supervisor', 'technician', 'operator_admin', 'operator_viewer']],

            ['operations',
            ['read', 'export', 'create', 'update', 'delete', 'admin-access'],
            ['superadmin', 'admin', 'supervisor', 'technician', 'driver', 'operator_admin', 'operator_viewer', 'operator_3pl']],

            ['vend-settings',
            ['read', 'export', 'create', 'update', 'delete', 'admin-access'],
            ['superadmin', 'admin', 'supervisor', 'technician', 'operator_admin']],

            ['machine-view',
            ['read', 'export'],
            ['superadmin', 'admin', 'supervisor', 'technician']],

            ['machine-settings',
            ['read', 'export'],
            ['superadmin', 'admin', 'supervisor', 'technician']],

            ['apk-settings',
            ['read', 'export'],
            ['superadmin', 'admin', 'supervisor', 'technician', 'driver', 'operator_admin', 'operator_viewer']],

            ['vend-configs',
            ['read', 'create', 'update', 'delete', 'admin-access'],
            ['superadmin', 'admin', 'supervisor', 'technician']],

            ['vend-prefixes',
            ['read', 'create', 'update', 'delete', 'admin-access'],
            ['superadmin', 'admin', 'supervisor', 'technician']],

            ['cashless-terminals',
            ['read', 'create', 'update', 'delete', 'admin-access'],
            ['superadmin', 'admin', 'supervisor', 'technician']],

            ['customers',
            ['read', 'export', 'create', 'update', 'delete'],
            ['superadmin', 'admin', 'supervisor', 'technician', 'operator_admin', 'operator_viewer']],

            ['customers',
            ['admin-access'],
            ['superadmin', 'admin', 'supervisor', 'technician', 'operator_admin', 'operator_viewer']],

            ['products',
            ['read', 'create', 'update', 'delete', 'export', 'admin-access'],
            ['superadmin', 'admin', 'supervisor', 'technician', 'operator_admin', 'operator_viewer']],

            ['product-mappings',
            ['read', 'create', 'update', 'delete', 'export', 'admin-access'],
            ['superadmin', 'admin', 'supervisor', 'technician', 'operator_admin', 'operator_viewer']],

            ['product-availability',
            ['read', 'create', 'update', 'delete', 'export', 'admin-access'],
            ['superadmin', 'admin', 'supervisor', 'operator_admin', 'operator_viewer']],

            ['product-categories',
            ['read', 'create', 'update', 'delete', 'export', 'admin-access'],
            ['superadmin', 'admin', 'supervisor', 'operator_admin', 'operator_viewer']],

            ['product-subcategories',
            ['read', 'create', 'update', 'delete', 'export', 'admin-access'],
            ['superadmin', 'admin', 'supervisor', 'operator_admin', 'operator_viewer']],

            ['product-campaign-labels',
            ['read', 'create', 'update', 'delete', 'export', 'admin-access'],
            ['superadmin', 'admin', 'supervisor', 'operator_admin', 'operator_viewer']],

            ['data-settings',
            ['read', 'create', 'update', 'delete', 'export', 'admin-access'],
            ['superadmin', 'admin', 'supervisor', 'technician', 'opeartor_admin', 'operator_viewer']],

            ['cashless-providers',
            ['read', 'create', 'update', 'delete', 'export', 'admin-access'],
            ['superadmin', 'admin', 'supervisor', 'technician', 'opeartor_admin', 'operator_viewer']],

            ['location-types',
            ['read', 'create', 'update', 'delete', 'export', 'admin-access'],
            ['superadmin', 'admin', 'supervisor', 'opeartor_admin', 'operator_viewer']],

            ['vend-models',
            ['read', 'create', 'update', 'delete', 'export', 'admin-access'],
            ['superadmin', 'admin', 'supervisor', 'technician']],

            ['modem-models',
            ['read', 'create', 'update', 'delete', 'export', 'admin-access'],
            ['superadmin', 'admin', 'supervisor']],

            ['modem-imei',
            ['read', 'create', 'update', 'delete', 'export', 'admin-access'],
            ['superadmin', 'admin', 'supervisor']],

            ['keys',
            ['read', 'create', 'update', 'delete', 'export', 'admin-access'],
            ['superadmin', 'admin', 'supervisor', 'technician', 'operator_admin', 'operator_viewer']],

            ['serial-numbers',
            ['read', 'create', 'update', 'delete', 'export', 'admin-access'],
            ['superadmin', 'admin', 'supervisor', 'technician']],

            ['telcos',
            ['read', 'create', 'update', 'delete', 'export', 'admin-access'],
            ['superadmin', 'admin', 'supervisor', 'technician', 'operator_admin', 'operator_viewer']],

            ['simcards',
            ['read', 'create', 'update', 'delete', 'export', 'admin-access'],
            ['superadmin', 'admin', 'supervisor', 'technician', 'operator_admin', 'operator_viewer']],

            ['zones',
            ['read', 'create', 'update', 'delete', 'export', 'admin-access'],
            ['superadmin', 'admin', 'supervisor', 'opeartor_admin', 'operator_viewer']],

            ['delivery-platforms',
            ['read', 'export'],
            ['superadmin', 'admin', 'supervisor', 'technician', 'operator_admin', 'operator_viewer']],

            ['delivery-platform-orders',
            ['read', 'export', 'update'],
            ['superadmin', 'admin', 'supervisor', 'technician', 'operator_admin', 'operator_viewer']],

            ['delivery-platform-orders',
            ['admin-access'],
            ['superadmin', 'admin', 'supervisor', 'technician']],

            ['delivery-platform-vends',
            ['read', 'export', 'update'],
            ['superadmin', 'admin', 'supervisor', 'technician', 'operator_admin', 'operator_viewer']],

            ['delivery-platform-vends',
            ['admin-access'],
            ['superadmin', 'admin', 'supervisor', 'technician']],

            ['delivery-platform-product-mappings',
            ['read', 'export', 'create', 'update', 'delete'],
            ['superadmin', 'admin', 'supervisor', 'operator_admin', 'operator_viewer']],

            ['delivery-platform-product-mappings',
            ['admin-access'],
            ['superadmin', 'admin', 'supervisor']],

            ['delivery-platform-campaigns',
            ['read', 'export', 'create', 'update', 'delete'],
            ['superadmin', 'admin', 'supervisor', 'operator_admin', 'operator_viewer']],

            ['delivery-platform-campaigns',
            ['admin-access'],
            ['superadmin', 'admin', 'supervisor']],

            ['reports',
            ['read', 'export', 'admin-access'],
            ['superadmin', 'admin', 'supervisor', 'operator_admin', 'operator_viewer']],

            ['admins',
            ['read', 'export'],
            ['superadmin', 'admin', 'operator_admin']],

            ['operators',
            ['read', 'create', 'update', 'delete', 'export', 'admin-access'],
            ['superadmin', 'admin']],

            ['users',
            ['read', 'create', 'update', 'delete', 'export'],
            ['superadmin', 'admin', 'operator_admin']],

            ['users',
            ['admin-access'],
            ['superadmin', 'admin', 'operator_admin']],

            ['vouchers',
            ['read', 'create', 'update', 'delete', 'export'],
            ['superadmin', 'admin', 'supervisor', 'operator_admin']],

            ['vouchers',
            ['admin-access'],
            ['superadmin', 'admin', 'operator_admin']],

            ['resource-centers',
            ['read', 'export'],
            ['superadmin', 'admin', 'supervisor', 'technician', 'driver', 'operator_admin', 'operator_viewer', 'operator_3pl', 'franchisee', 'licensee']],

            ['resource-center-operators',
            ['read', 'export'],
            ['superadmin', 'admin', 'supervisor', 'technician', 'driver', 'operator_admin', 'operator_viewer', 'operator_3pl', 'franchisee', 'licensee']],

            ['resource-center-operators',
            ['admin-access'],
            ['superadmin', 'admin', 'supervisor']],

            ['resource-center-technicians',
            ['read', 'export'],
            ['superadmin', 'admin', 'supervisor', 'technician']],

            ['resource-center-technicians',
            ['admin-access'],
            ['superadmin', 'admin', 'supervisor']]
        ];

        // Create permissions and assign to roles
        foreach ($permissionsData as $data) {
            foreach ($data[1] as $action) {
                $permissionName = "{$action} {$data[0]}";
                $permission = Permission::create(['name' => $permissionName, 'guard_name' => 'web']);

                foreach ($data[2] as $roleName) {
                    $role = Role::where('name', $roleName)->first();
                    if ($role) {
                        $role->givePermissionTo($permission);
                    }
                }
            }
        }
    }
}
