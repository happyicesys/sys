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

        foreach ($roles as $role) {
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
            [
                'dashboard',
                ['read', 'export'],
                ['superadmin', 'admin', 'supervisor', 'observer', 'operator_admin', 'operator_supervisor', 'licensee', 'hid_user']
            ],

            [
                'dashboard',
                ['admin-access'],
                ['superadmin', 'admin', 'supervisor', 'operator_admin']
            ],

            [
                'dashboard-performance',
                ['read', 'export'],
                ['superadmin', 'admin', 'supervisor', 'observer', 'operator_admin', 'operator_supervisor', 'licensee', 'hid_user']
            ],

            [
                'dashboard-machine-health',
                ['read', 'export'],
                ['superadmin', 'admin', 'supervisor', 'operator_admin', 'operator_supervisor']
            ],

            [
                'vends',
                ['read', 'export'],
                ['superadmin', 'admin', 'supervisor', 'technician', 'driver', 'operator_admin', 'operator_supervisor', 'operator_driver', 'franchisee', 'licensee']
            ],

            [
                'vends',
                ['update'],
                ['superadmin', 'admin', 'supervisor', 'operator_admin']
            ],

            [
                'vends',
                ['admin-access'],
                ['superadmin', 'admin', 'supervisor']
            ],

            [
                'vend-customers',
                ['read', 'export'],
                ['superadmin', 'admin', 'supervisor', 'technician', 'driver', 'operator_admin', 'operator_supervisor', 'operator_driver', 'operator_3pl', 'franchisee', 'licensee']
            ],

            [
                'vend-contracts',
                ['read', 'export'],
                ['superadmin', 'admin', 'supervisor', 'technician', 'operator_admin']
            ],

            [
                'vend-customers',
                ['admin-access'],
                ['superadmin', 'admin', 'supervisor', 'technician', 'driver', 'operator_admin', 'operator_supervisor']
            ],

            [
                'vend-machines',
                ['read', 'export'],
                ['superadmin', 'admin', 'supervisor', 'technician']
            ],

            [
                'vend-machines',
                ['admin-access'],
                ['superadmin', 'admin', 'supervisor', 'technician']
            ],

            [
                'transactions',
                ['read', 'export'],
                ['superadmin', 'admin', 'supervisor', 'technician', 'operator_admin', 'operator_supervisor', 'franchisee', 'licensee', 'hid_user']
            ],

            [
                'transactions',
                ['admin-access'],
                ['superadmin', 'admin', 'supervisor', 'technician', 'operator_admin', 'operator_supervisor']
            ],

            [
                'transactions-sales',
                ['read', 'export'],
                ['superadmin', 'admin', 'supervisor', 'technician', 'operator_admin', 'operator_supervisor', 'franchisee', 'licensee', 'hid_user']
            ],

            [
                'transactions-sales',
                ['admin-access'],
                ['superadmin', 'admin', 'supervisor', 'technician', 'operator_admin', 'operator_supervisor']
            ],

            [
                'transactions-payment-gateway',
                ['read', 'export'],
                ['superadmin', 'admin', 'supervisor', 'technician']
            ],

            [
                'operations',
                ['read', 'export', 'create', 'update', 'delete', 'admin-access'],
                ['superadmin', 'admin', 'supervisor', 'technician', 'driver', 'operator_admin', 'operator_supervisor', 'operator_driver', 'operator_3pl']
            ],

            [
                'vend-settings',
                ['read', 'export', 'create', 'update', 'delete', 'admin-access'],
                ['superadmin', 'admin', 'supervisor', 'technician', 'operator_admin', 'operator_supervisor', 'production_jb']
            ],

            [
                'machine-view',
                ['read', 'export'],
                ['superadmin', 'admin', 'supervisor', 'technician', 'production_jb', 'operator_admin', 'operator_supervisor']
            ],

            [
                'machine-settings',
                ['read', 'export'],
                ['superadmin', 'admin', 'supervisor', 'technician']
            ],

            [
                'machine-alert-parameters',
                ['read', 'export'],
                ['superadmin', 'admin', 'supervisor', 'technician']
            ],

            [
                'apk-settings',
                ['read', 'export'],
                ['superadmin', 'admin', 'supervisor', 'technician', 'driver', 'operator_admin', 'operator_supervisor']
            ],

            [
                'vend-configs',
                ['read', 'create', 'update', 'delete', 'admin-access'],
                ['superadmin', 'admin', 'supervisor', 'technician']
            ],

            [
                'vend-prefixes',
                ['read', 'create', 'update', 'delete', 'admin-access'],
                ['superadmin', 'admin', 'supervisor', 'technician']
            ],

            [
                'cashless-terminals',
                ['read', 'create', 'update', 'delete', 'admin-access', 'operator_admin', 'operator_supervisor'],
                ['superadmin', 'admin', 'supervisor', 'technician']
            ],

            [
                'customers',
                ['read', 'export', 'create', 'update', 'delete'],
                ['superadmin', 'admin', 'supervisor', 'technician', 'operator_admin', 'operator_supervisor']
            ],

            [
                'customers',
                ['admin-access'],
                ['superadmin', 'admin', 'supervisor', 'technician', 'operator_admin', 'operator_supervisor']
            ],

            [
                'products',
                ['read', 'create', 'update', 'delete', 'export', 'admin-access'],
                ['superadmin', 'admin', 'supervisor', 'technician', 'operator_admin', 'operator_supervisor']
            ],

            [
                'product-mappings',
                ['read', 'create', 'update', 'delete', 'export', 'admin-access'],
                ['superadmin', 'admin', 'supervisor', 'technician', 'operator_admin', 'operator_supervisor']
            ],

            [
                'product-availability',
                ['read', 'create', 'update', 'delete', 'export', 'admin-access'],
                ['superadmin', 'admin', 'supervisor', 'operator_admin', 'operator_supervisor']
            ],

            [
                'product-categories',
                ['read', 'create', 'update', 'delete', 'export', 'admin-access'],
                ['superadmin', 'admin', 'supervisor', 'operator_admin', 'operator_supervisor']
            ],

            [
                'product-subcategories',
                ['read', 'create', 'update', 'delete', 'export', 'admin-access'],
                ['superadmin', 'admin', 'supervisor', 'operator_admin', 'operator_supervisor']
            ],

            [
                'product-campaign-labels',
                ['read', 'create', 'update', 'delete', 'export', 'admin-access'],
                ['superadmin', 'admin', 'supervisor', 'operator_admin', 'operator_supervisor']
            ],

            [
                'data-settings',
                ['read', 'create', 'update', 'delete', 'export', 'admin-access'],
                ['superadmin', 'admin', 'supervisor', 'technician', 'opeartor_admin', 'operator_supervisor', 'hid_user']
            ],

            [
                'cashless-providers',
                ['read', 'create', 'update', 'delete', 'export', 'admin-access'],
                ['superadmin', 'admin', 'supervisor', 'technician', 'opeartor_admin', 'operator_supervisor']
            ],

            [
                'location-types',
                ['read', 'create', 'update', 'delete', 'export', 'admin-access'],
                ['superadmin', 'admin', 'supervisor', 'opeartor_admin', 'operator_supervisor']
            ],

            [
                'hid-cards',
                ['read', 'create', 'update', 'delete', 'admin-access'],
                ['superadmin', 'admin', 'supervisor', 'technician', 'opeartor_admin', 'hid_user']
            ],

            [
                'vend-models',
                ['read', 'create', 'update', 'delete', 'export', 'admin-access'],
                ['superadmin', 'admin', 'supervisor', 'technician', 'operator_admin', 'operator_supervisor']
            ],

            [
                'modem-models',
                ['read', 'create', 'update', 'delete', 'export', 'admin-access'],
                ['superadmin', 'admin', 'supervisor', 'operator_admin', 'operator_supervisor']
            ],

            [
                'modem-imei',
                ['read', 'create', 'update', 'delete', 'export', 'admin-access'],
                ['superadmin', 'admin', 'supervisor', 'operator_admin', 'operator_supervisor']
            ],

            [
                'keys',
                ['read', 'create', 'update', 'delete', 'export', 'admin-access'],
                ['superadmin', 'admin', 'supervisor', 'technician', 'operator_admin', 'operator_supervisor']
            ],

            [
                'serial-numbers',
                ['read', 'create', 'update', 'delete', 'export', 'admin-access'],
                ['superadmin', 'admin', 'supervisor', 'technician', 'operator_admin', 'operator_supervisor']
            ],

            [
                'telcos',
                ['read', 'create', 'update', 'delete', 'export', 'admin-access'],
                ['superadmin', 'admin', 'supervisor', 'technician', 'operator_admin', 'operator_supervisor']
            ],

            [
                'simcards',
                ['read', 'create', 'update', 'delete', 'export', 'admin-access'],
                ['superadmin', 'admin', 'supervisor', 'technician', 'operator_admin', 'operator_supervisor']
            ],

            [
                'zones',
                ['read', 'create', 'update', 'delete', 'export', 'admin-access'],
                ['superadmin', 'admin', 'supervisor', 'opeartor_admin', 'operator_supervisor']
            ],

            [
                'delivery-platforms',
                ['read', 'export'],
                ['superadmin', 'admin', 'supervisor', 'technician', 'operator_admin', 'operator_supervisor']
            ],

            [
                'delivery-platform-orders',
                ['read', 'export', 'update'],
                ['superadmin', 'admin', 'supervisor', 'technician', 'operator_admin', 'operator_supervisor']
            ],

            [
                'delivery-platform-orders',
                ['admin-access'],
                ['superadmin', 'admin', 'supervisor', 'technician']
            ],

            [
                'delivery-platform-vends',
                ['read', 'export', 'update'],
                ['superadmin', 'admin', 'supervisor', 'technician', 'operator_admin', 'operator_supervisor']
            ],

            [
                'delivery-platform-vends',
                ['admin-access'],
                ['superadmin', 'admin', 'supervisor', 'technician']
            ],

            [
                'delivery-platform-product-mappings',
                ['read', 'export', 'create', 'update', 'delete'],
                ['superadmin', 'admin', 'supervisor', 'operator_admin', 'operator_supervisor']
            ],

            [
                'delivery-platform-product-mappings',
                ['admin-access'],
                ['superadmin', 'admin', 'supervisor']
            ],

            [
                'delivery-platform-campaigns',
                ['read', 'export', 'create', 'update', 'delete'],
                ['superadmin', 'admin', 'supervisor', 'operator_admin', 'operator_supervisor']
            ],

            [
                'delivery-platform-campaigns',
                ['admin-access'],
                ['superadmin', 'admin', 'supervisor']
            ],

            [
                'reports',
                ['read', 'export', 'admin-access'],
                ['superadmin', 'admin', 'supervisor', 'operator_admin', 'operator_supervisor']
            ],

            [
                'admins',
                ['read', 'export'],
                ['superadmin', 'admin', 'operator_admin']
            ],

            [
                'operators',
                ['read', 'create', 'update', 'delete', 'export', 'admin-access'],
                ['superadmin', 'admin']
            ],

            [
                'users',
                ['read', 'create', 'update', 'delete', 'export'],
                ['superadmin', 'admin', 'operator_admin']
            ],

            [
                'users',
                ['admin-access'],
                ['superadmin', 'admin', 'operator_admin']
            ],

            [
                'vouchers',
                ['read', 'create', 'update', 'delete', 'export'],
                ['superadmin', 'admin', 'supervisor', 'operator_admin']
            ],

            [
                'vouchers',
                ['admin-access'],
                ['superadmin', 'admin', 'operator_admin']
            ],

            [
                'resource-centers',
                ['read', 'export'],
                ['superadmin', 'admin', 'supervisor', 'technician', 'driver', 'operator_admin', 'operator_supervisor', 'operator_3pl', 'franchisee']
            ],

            [
                'resource-center-operators',
                ['read', 'export'],
                ['superadmin', 'admin', 'supervisor', 'operator_admin', 'operator_supervisor']
            ],

            [
                'resource-center-operators',
                ['admin-access'],
                ['superadmin', 'admin', 'supervisor']
            ],

            [
                'resource-center-technicians',
                ['read', 'export'],
                ['superadmin', 'admin', 'supervisor', 'technician', 'operator_admin', 'operator_supervisor']
            ],

            [
                'resource-center-technicians',
                ['admin-access'],
                ['superadmin', 'admin', 'supervisor']
            ],

            [
                'resource-center-drivers',
                ['read', 'export'],
                ['superadmin', 'admin', 'supervisor', 'technician', 'driver', 'operator_admin', 'operator_supervisor', 'operator_driver', 'operator_3pl', 'franchisee']
            ],

            [
                'resource-center-drivers',
                ['admin-access'],
                ['superadmin', 'admin', 'supervisor']
            ]
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
