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
                ['superadmin', 'admin', 'supervisor', 'observer', 'observer_transactions', 'technician', 'operator_admin', 'operator_supervisor', 'licensee', 'hid_user']
            ],

            [
                'dashboard',
                ['admin-access'],
                ['superadmin', 'admin', 'supervisor', 'operator_admin']
            ],

            [
                'dashboard-performance',
                ['read', 'export'],
                ['superadmin', 'admin', 'supervisor', 'observer', 'observer_transactions', 'operator_admin', 'operator_supervisor', 'licensee', 'hid_user']
            ],

            [
                'dashboard-machine-health',
                ['read', 'export'],
                ['superadmin', 'admin', 'supervisor', 'technician', 'operator_admin', 'operator_supervisor']
            ],

            [
                'vends',
                ['read', 'export'],
                ['superadmin', 'admin', 'supervisor', 'observer_transactions', 'technician', 'driver', 'operator_admin', 'operator_supervisor', 'operator_driver', 'franchisee', 'licensee']
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
                ['superadmin', 'admin', 'supervisor', 'observer_transactions', 'technician', 'driver', 'operator_admin', 'operator_supervisor', 'operator_driver', 'operator_3pl', 'franchisee', 'licensee']
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
                ['superadmin', 'admin', 'supervisor', 'observer_transactions', 'technician', 'operator_admin', 'operator_supervisor', 'franchisee', 'licensee', 'hid_user']
            ],

            [
                'transactions',
                ['admin-access'],
                ['superadmin', 'admin', 'supervisor', 'technician', 'operator_admin', 'operator_supervisor']
            ],

            [
                'transactions-sales',
                ['read', 'export'],
                ['superadmin', 'admin', 'supervisor', 'observer_transactions', 'technician', 'operator_admin', 'operator_supervisor', 'franchisee', 'licensee', 'hid_user']
            ],

            [
                'transactions-sales',
                ['admin-access'],
                ['superadmin', 'admin', 'supervisor', 'technician', 'operator_admin', 'operator_supervisor']
            ],

            [
                'transactions-payment-gateway',
                ['read', 'export'],
                ['superadmin', 'admin', 'supervisor', 'technician', 'operator_admin', 'operator_supervisor']
            ],

            [
                'operations',
                ['read', 'export', 'create', 'update', 'delete', 'admin-access'],
                ['superadmin', 'admin', 'supervisor', 'technician', 'driver', 'operator_admin', 'operator_supervisor', 'operator_driver', 'operator_3pl']
            ],

            [
                'operation-jobs',
                ['read', 'export', 'create', 'update', 'delete', 'admin-access'],
                ['superadmin', 'admin', 'supervisor', 'technician', 'driver', 'operator_admin', 'operator_supervisor', 'operator_driver', 'operator_3pl']
            ],

            [
                'operation-job-summaries',
                ['read', 'export', 'admin-access'],
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
                ['read', 'update'],
                ['superadmin', 'admin', 'supervisor', 'technician', 'operator_admin', 'operator_supervisor']
            ],

            [
                'machine-settings',
                ['export', 'create', 'delete', 'admin-access'],
                ['superadmin', 'admin', 'supervisor', 'technician', 'operator_admin', 'operator_supervisor']
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
                ['read', 'create', 'update', 'delete', 'admin-access'],
                ['superadmin', 'admin', 'supervisor', 'technician', 'operator_admin', 'operator_supervisor']
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
                ['read'],
                ['superadmin', 'admin', 'supervisor', 'operator_admin', 'operator_supervisor']
            ],

            [
                'product-categories',
                ['create', 'update', 'delete', 'export', 'admin-access'],
                ['superadmin', 'admin', 'supervisor']
            ],

            [
                'product-subcategories',
                ['read'],
                ['superadmin', 'admin', 'supervisor', 'operator_admin', 'operator_supervisor']
            ],

            [
                'product-subcategories',
                ['create', 'update', 'delete', 'export', 'admin-access'],
                ['superadmin', 'admin', 'supervisor']
            ],

            [
                'product-campaign-labels',
                ['read', 'create', 'update', 'delete', 'export', 'admin-access'],
                ['superadmin', 'admin', 'supervisor', 'operator_admin', 'operator_supervisor']
            ],

            [
                'data-settings',
                ['read', 'create', 'update', 'delete', 'export', 'admin-access'],
                ['superadmin', 'admin', 'supervisor', 'technician', 'operator_admin', 'operator_supervisor', 'hid_user']
            ],

            [
                'card-terminals',
                ['read', 'create', 'update', 'delete', 'export', 'admin-access'],
                ['superadmin', 'admin', 'supervisor', 'technician', 'operator_admin', 'operator_supervisor']
            ],

            [
                'location-types',
                ['read', 'create', 'update', 'delete', 'export', 'admin-access'],
                ['superadmin', 'admin', 'supervisor', 'operator_admin', 'operator_supervisor']
            ],

            [
                'banks',
                ['read', 'create', 'update', 'delete', 'export', 'admin-access'],
                ['superadmin', 'admin', 'supervisor', 'operator_admin', 'operator_supervisor']
            ],

            [
                'hid-cards',
                ['read', 'create', 'update', 'delete', 'admin-access'],
                ['superadmin', 'admin', 'supervisor', 'technician', 'operator_admin', 'hid_user']
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
                ['superadmin', 'admin', 'supervisor']
            ],

            [
                'keys',
                ['read', 'create', 'update', 'delete', 'export', 'admin-access'],
                ['superadmin', 'admin', 'supervisor', 'technician', 'operator_admin', 'operator_supervisor']
            ],

            [
                'serial-numbers',
                ['read'],
                ['superadmin', 'admin', 'supervisor', 'technician', 'operator_admin', 'operator_supervisor']
            ],

            [
                'serial-numbers',
                ['create', 'update', 'delete', 'export', 'admin-access'],
                ['superadmin', 'admin', 'supervisor', 'technician']
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
                ['superadmin', 'admin', 'supervisor', 'operator_admin', 'operator_supervisor']
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
                ['read', 'export', 'create', 'update', 'delete', 'admin-access'],
                ['superadmin', 'admin', 'supervisor', 'operator_admin', 'operator_supervisor']
            ],

            [
                'delivery-platform-campaigns',
                ['read', 'export', 'create', 'update', 'delete', 'admin-access'],
                ['superadmin', 'admin', 'supervisor', 'operator_admin', 'operator_supervisor']
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
                ['create', 'delete', 'export', 'admin-access'],
                ['superadmin', 'admin']
            ],

            [
                'operators',
                ['read', 'update'],
                ['superadmin', 'admin', 'operator_admin']
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
                ['superadmin', 'admin', 'supervisor', 'operator_admin', 'operator_supervisor']
            ],

            [
                'vouchers',
                ['admin-access'],
                ['superadmin', 'admin', 'operator_admin']
            ],

            [
                'resource-centers',
                ['read', 'export'],
                ['superadmin', 'admin', 'supervisor', 'technician', 'driver']
            ],

            [
                'resource-center-operators',
                ['read', 'export', 'admin-access'],
                ['superadmin', 'admin', 'supervisor']
            ],

            [
                'resource-center-technicians',
                ['read', 'export', 'admin-access'],
                ['superadmin', 'admin', 'supervisor', 'technician']
            ],

            [
                'resource-center-drivers',
                ['read', 'export', 'admin-access'],
                ['superadmin', 'admin', 'supervisor', 'technician', 'driver']
            ],

            [
                'tutorials',
                ['read', 'export'],
                ['superadmin', 'admin', 'supervisor', 'technician', 'driver', 'operator_admin', 'operator_supervisor', 'operator_driver', 'operator_3pl', 'franchisee']
            ],

            [
                'tutorials-operators',
                ['read', 'export', 'admin-access'],
                ['superadmin', 'admin', 'supervisor', 'operator_admin', 'operator_supervisor']
            ],

            [
                'tutorials-technicians',
                ['read', 'export', 'admin-access'],
                ['superadmin', 'admin', 'supervisor', 'operator_admin', 'operator_supervisor']
            ],

            [
                'tutorials-drivers',
                ['read', 'export', 'admin-access'],
                ['superadmin', 'admin', 'supervisor', 'operator_admin', 'operator_supervisor', 'operator_driver', 'operator_3pl', 'franchisee']
            ],

            // Refund Requests module. Source of truth = migration
            // 2026_06_26_100004_seed_refund_permissions.php, as amended by
            // 2026_07_03_120001_drop_approve_refunds_permission.php (which removed the
            // 'approve refunds' ability and moved supervisors onto 'verify refunds').
            // Listed here too because this seeder truncates ALL permissions and
            // rebuilds only what it lists — without this block, re-running it would
            // delete refund access (and previously it also resurrected the retired
            // 'approve refunds' permission and dropped supervisor's verify grant).
            // superadmin is granted explicitly so the sidebar (literal permission
            // check) shows it. NOTE: supervisor deliberately has read + verify only;
            // its access to the ticket "Overwritten" Final Refund Amount control is
            // granted by ROLE on the route (role_or_permission:supervisor|update
            // refunds), NOT by the broad 'update refunds' permission.
            [
                'refunds',
                ['read'],
                ['superadmin', 'admin', 'supervisor', 'operator']
            ],
            [
                'refunds',
                ['create', 'update', 'payout'],
                ['superadmin', 'admin']
            ],
            [
                'refunds',
                ['verify'],
                ['superadmin', 'admin', 'supervisor', 'operator']
            ],

            // Operator Groups (payout groups) module. Source of truth = migration
            // 2026_07_06_120000_seed_operator_group_permissions.php. Listed here too
            // because this seeder truncates ALL permissions and rebuilds only what it
            // lists — without this block, re-running it would delete the operator-group
            // permissions and 403 the /operator-groups page for admin (superadmin still
            // sees it via Gate::before, but admin would lose access). Admin-only CRUD.
            [
                'operator-groups',
                ['read', 'manage'],
                ['superadmin', 'admin']
            ],
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
