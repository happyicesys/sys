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
                'mcp-tokens',
                ['read', 'manage'],
                ['superadmin', 'admin']
            ],
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
                // 2026-07-23 sheet sync v2 (struck-through cells = disabled): - driver
                // (Ops Dashboard Full-Filter Yes struck) and - operator_3pl (limited-filter
                // cell struck; round-1 addition reverted)
                ['superadmin', 'admin', 'supervisor', 'observer_transactions', 'technician', 'operator_admin', 'operator_supervisor', 'operator_driver', 'franchisee', 'licensee']
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
                // 2026-07-23 sheet sync v2: - operator_admin, operator_supervisor (both Yes
                // struck; round-1 operator_supervisor addition reverted)
                ['superadmin', 'admin', 'supervisor', 'technician']
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
                // 2026-07-23 sheet sync: - operator_admin, operator_supervisor (HappyIce staff only)
                ['superadmin', 'admin', 'supervisor', 'technician']
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
                // 2026-07-23 sheet sync v2: HappyIce staff only - operator_admin/
                // operator_supervisor/operator_driver Yes struck through, operator_3pl blank
                ['superadmin', 'admin', 'supervisor', 'technician', 'driver']
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
                // 2026-07-23 sheet sync: - operator_admin, operator_supervisor (Machine Settings = HappyIce staff only).
                // NOTE: also hides Smart Freezer Settings menu item for operators (shares 'read machine-settings').
                ['superadmin', 'admin', 'supervisor', 'technician']
            ],

            [
                'machine-settings',
                ['export', 'create', 'delete', 'admin-access'],
                // 2026-07-23 sheet sync: - operator_admin, operator_supervisor
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
                // 2026-07-23 sheet sync: - driver (UI Setting row has no Driver)
                ['superadmin', 'admin', 'supervisor', 'technician', 'operator_admin', 'operator_supervisor']
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
                // 2026-07-23 sheet sync: - technician; v2: - operator_admin, operator_supervisor (Yes struck)
                ['superadmin', 'admin', 'supervisor']
            ],

            [
                'customers',
                ['read', 'export', 'create', 'update', 'delete'],
                ['superadmin', 'admin', 'supervisor', 'technician', 'operator_admin', 'operator_supervisor']
            ],

            [
                'customers',
                ['admin-access'],
                // 2026-07-23 sheet sync: - operator_admin, operator_supervisor (Site Settlement = staff only)
                ['superadmin', 'admin', 'supervisor', 'technician']
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
                // 2026-07-23 sheet sync v2: - operator_admin, operator_supervisor (Yes struck)
                ['superadmin', 'admin', 'supervisor']
            ],

            [
                'product-categories',
                ['create', 'update', 'delete', 'export', 'admin-access'],
                ['superadmin', 'admin', 'supervisor']
            ],

            [
                'product-subcategories',
                ['read'],
                // 2026-07-23 sheet sync v2: - operator_admin, operator_supervisor (Yes struck)
                ['superadmin', 'admin', 'supervisor']
            ],

            [
                'product-subcategories',
                ['create', 'update', 'delete', 'export', 'admin-access'],
                ['superadmin', 'admin', 'supervisor']
            ],

            [
                'product-campaign-labels',
                ['read', 'create', 'update', 'delete', 'export', 'admin-access'],
                // 2026-07-23 sheet sync v2: - operator_admin, operator_supervisor (Product
                // Labels + Machine Campaigns rows struck). Also hides Campaign Management menu.
                ['superadmin', 'admin', 'supervisor']
            ],

            [
                'data-settings',
                ['read', 'create', 'update', 'delete', 'export', 'admin-access'],
                // 2026-07-23 sheet sync v2: - operator_admin, operator_supervisor (whole
                // Data Management section struck through for the operator roles)
                ['superadmin', 'admin', 'supervisor', 'technician', 'hid_user']
            ],

            [
                'card-terminals',
                ['read', 'create', 'update', 'delete', 'export', 'admin-access'],
                // 2026-07-23 sheet sync v2: - operator_admin, operator_supervisor (Yes struck)
                ['superadmin', 'admin', 'supervisor', 'technician']
            ],

            [
                'machine-stickers',
                ['read', 'create', 'update', 'delete', 'export', 'admin-access'],
                // 2026-07-23 sheet sync v2: - operator_admin, operator_supervisor (page not in
                // sheet, but whole Data Management section is disabled for operator roles)
                ['superadmin', 'admin', 'supervisor', 'technician']
            ],

            [
                'location-types',
                ['read', 'create', 'update', 'delete', 'export', 'admin-access'],
                // 2026-07-23 sheet sync v2: - operator_admin, operator_supervisor (Yes struck)
                ['superadmin', 'admin', 'supervisor']
            ],

            [
                'banks',
                ['read', 'create', 'update', 'delete', 'export', 'admin-access'],
                // 2026-07-23 sheet sync v2: - operator_admin, operator_supervisor (page not in
                // sheet, but whole Data Management section is disabled for operator roles)
                ['superadmin', 'admin', 'supervisor']
            ],

            [
                'hid-cards',
                ['read', 'create', 'update', 'delete', 'admin-access'],
                // 2026-07-23 sheet sync v2: - operator_admin, operator_supervisor (both Yes
                // struck; round-1 operator_supervisor addition reverted); hid_user keeps access
                ['superadmin', 'admin', 'supervisor', 'technician', 'hid_user']
            ],

            [
                'vend-models',
                ['read', 'create', 'update', 'delete', 'export', 'admin-access'],
                // 2026-07-23 sheet sync v2: - operator_admin, operator_supervisor (Yes struck)
                ['superadmin', 'admin', 'supervisor', 'technician']
            ],

            [
                'modem-models',
                ['read', 'create', 'update', 'delete', 'export', 'admin-access'],
                // 2026-07-23 sheet sync v2: - operator_admin, operator_supervisor (Yes struck)
                ['superadmin', 'admin', 'supervisor']
            ],

            // 2026-07-23 sheet sync v2: Modem IMEI appears twice in the sheet - the
            // Machine Mgmt row adds technician read; the Data Mgmt row's operator grants
            // are struck through (disabled), so operators get nothing here.
            // Write actions stay superadmin/admin/supervisor.
            [
                'modem-imei',
                ['read'],
                ['superadmin', 'admin', 'supervisor', 'technician']
            ],

            [
                'modem-imei',
                ['create', 'update', 'delete', 'export', 'admin-access'],
                ['superadmin', 'admin', 'supervisor']
            ],

            [
                'keys',
                ['read', 'create', 'update', 'delete', 'export', 'admin-access'],
                // 2026-07-23 sheet sync v2: - operator_admin, operator_supervisor (Yes struck)
                ['superadmin', 'admin', 'supervisor', 'technician']
            ],

            [
                'serial-numbers',
                ['read'],
                // 2026-07-23 sheet sync v2: - operator_admin, operator_supervisor (Yes struck)
                ['superadmin', 'admin', 'supervisor', 'technician']
            ],

            [
                'serial-numbers',
                ['create', 'update', 'delete', 'export', 'admin-access'],
                ['superadmin', 'admin', 'supervisor', 'technician']
            ],

            [
                'telcos',
                ['read', 'create', 'update', 'delete', 'export', 'admin-access'],
                // 2026-07-23 sheet sync v2: - operator_admin, operator_supervisor (Yes struck)
                ['superadmin', 'admin', 'supervisor', 'technician']
            ],

            [
                'simcards',
                ['read', 'create', 'update', 'delete', 'export', 'admin-access'],
                // 2026-07-23 sheet sync v2: - operator_admin, operator_supervisor (Yes struck)
                ['superadmin', 'admin', 'supervisor', 'technician']
            ],

            [
                'zones',
                ['read', 'create', 'update', 'delete', 'export', 'admin-access'],
                // 2026-07-23 sheet sync v2: - operator_admin, operator_supervisor (Yes struck)
                ['superadmin', 'admin', 'supervisor']
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
                // 2026-07-23 sheet sync v2: - operator_admin, operator_supervisor (Yes struck);
                // Delivery Campaign row is NOT struck so delivery-platform-campaigns keeps them
                ['superadmin', 'admin', 'supervisor']
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
                // 2026-07-23 sheet sync: - operator_admin (Admin > Operators = superadmin/admin only)
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
                // 2026-07-23 sheet sync: - technician, driver (staff use "Tutorial (with CMS)" / resource-centers instead)
                ['superadmin', 'admin', 'supervisor', 'operator_admin', 'operator_supervisor', 'operator_driver', 'operator_3pl', 'franchisee']
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
            // 2026_07_03_120001_drop_approve_refunds_permission.php (removed the
            // 'approve refunds' ability, moved supervisors onto 'verify refunds') and
            // 2026_07_11_120000_grant_supervisor_full_refund_access.php (gave
            // supervisor create/update/payout for full parity with admin on the
            // Refund Requests + Refund Settlement pages, e.g. the Re-match box and the
            // settlement actions). Listed here too because this seeder truncates ALL
            // permissions and rebuilds only what it lists — without this block,
            // re-running it would delete refund access. superadmin is granted
            // explicitly so the sidebar (literal permission check) shows it.
            // supervisor now mirrors admin: read/create/update/verify/payout.
            [
                'refunds',
                ['read'],
                ['superadmin', 'admin', 'supervisor', 'operator']
            ],
            [
                'refunds',
                ['create', 'update', 'payout'],
                ['superadmin', 'admin', 'supervisor']
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
