<?php

namespace Database\Seeders;

use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use DB;

class LogisticsRoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // remove the excessive roles
        $removeRoles = ['user', 'user_observer', 'vc_observer', 'vc_observer_loose'];

        $removeRoleUsers = User::with('roles')->get()->filter(
            fn ($user) => $user->roles->whereIn('name', $removeRoles)->toArray()
        );

        foreach($removeRoleUsers as $removeRoleUser) {
            $removeRoleUser->syncRoles([]);
            $removeRoleUser->assignRole('admin');
        }

        Role::whereIn('name', $removeRoles)->delete();

        // rename 'vending_machine_and_transaction', 'vending_machine_only' to franchisee
        $franchisee = Role::where('name', 'vending_machine_and_transaction')->first();
        if($franchisee) {
            $franchisee->name = 'franchisee';
            $franchisee->save();
        }

        $vendOnlyRole = Role::where('name', 'vending_machine_only')->first();
        if($vendOnlyRole) {
            $vendOnlyUsers = User::role('vending_machine_only')->get();

            if($vendOnlyUsers->count() > 0) {
                $vendOnlyUsers->each(function($user) {
                    $user->syncRoles(['franchisee']);
                });
            }
        }


        Role::where('name', 'vending_machine_only')->delete();

        Role::updateOrCreate([
            'name' => 'technician',
            'guard_name' => 'web',
        ]);

        $operator = Role::where('name', 'operator')->first();
        if($operator) {
            $operator->name = 'operator_admin';
            $operator->save();
        }

        $operator = Role::where('name', 'operator_user')->first();
        if($operator) {
            $operator->name = 'operator_viewer';
            $operator->save();
        }

        Role::updateOrCreate([
            'name' => 'operator_3pl',
            'guard_name' => 'web',
        ]);


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
            ['superadmin', 'admin', 'supervisor', 'operator_admin', 'operator_viewer']],

            ['vends',
            ['read', 'export'],
            ['superadmin', 'admin', 'supervisor', 'technician', 'driver', 'operator_admin', 'operator_viewer', 'operator_3pl', 'franchisee']],

            ['vend-customers',
            ['read', 'export'],
            ['superadmin', 'admin', 'supervisor', 'technician', 'driver', 'operator_admin', 'operator_viewer', 'operator_3pl', 'franchisee']],

            ['vend-customers',
            ['admin-access'],
            ['superadmin', 'admin', 'supervisor', 'technician', 'driver']],

            ['vend-machines',
            ['read', 'export'],
            ['superadmin', 'admin', 'supervisor', 'technician']],

            ['transactions',
            ['read', 'export'],
            ['superadmin', 'admin', 'supervisor', 'technician', 'operator_admin', 'operator_viewer', 'franchisee']],

            ['transactions',
            ['admin-access'],
            ['superadmin', 'admin', 'supervisor', 'technician']],

            ['operations',
            ['read', 'export', 'create', 'update', 'delete', 'admin-access'],
            ['superadmin', 'admin', 'supervisor', 'technician', 'driver']],

            ['vend-settings',
            ['read', 'export', 'create', 'update', 'delete', 'admin-access'],
            ['superadmin', 'admin', 'supervisor', 'technician']],

            ['customers',
            ['read', 'export', 'create', 'update', 'delete'],
            ['superadmin', 'admin', 'supervisor', 'technician', 'operator_admin', 'operator_viewer']],

            ['customers',
            ['admin-access'],
            ['superadmin', 'admin', 'supervisor', 'technician']],

            ['products',
            ['read', 'create', 'update', 'delete', 'export', 'admin-access'],
            ['superadmin', 'admin', 'supervisor', 'technician', 'operator_admin', 'operator_viewer']],

            ['product-mappings',
            ['read', 'create', 'update', 'delete', 'export', 'admin-access'],
            ['superadmin', 'admin', 'supervisor', 'technician', 'operator_admin', 'operator_viewer']],

            ['data-settings',
            ['read', 'create', 'update', 'delete', 'export', 'admin-access'],
            ['superadmin', 'admin', 'supervisor', 'technician']],

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
            ['superadmin', 'admin', 'supervisor']],

            ['admins',
            ['read', 'export'],
            ['superadmin', 'admin', 'operator_admin']],

            ['operators',
            ['read', 'create', 'update', 'delete', 'export', 'admin-access'],
            ['superadmin', 'admin']],

            ['users',
            ['read', 'create', 'update', 'delete', 'export'],
            ['superadmin', 'admin', 'operator_admin']],

            ['resource-centers',
            ['read', 'export'],
            ['superadmin', 'admin', 'supervisor', 'technician', 'driver', 'operator_admin', 'operator_viewer', 'operator_3pl', 'franchisee']],

            ['resource-center-operators',
            ['read', 'export'],
            ['superadmin', 'admin', 'supervisor', 'technician', 'driver', 'operator_admin', 'operator_viewer', 'operator_3pl', 'franchisee']],

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
