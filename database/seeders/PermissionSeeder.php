<?php

namespace Database\Seeders;

use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class PermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $actions = ['create', 'read', 'update', 'delete', 'admin-access'];
        $models = [
            'vends',
            'transactions',
            'products',
            'product-mappings',
            'operators',
            'resource-centers',
            'users',
        ];

        foreach($actions as $action) {
            foreach($models as $model) {
                Permission::create([
                    'name' => $action . ' ' . $model,
                    'guard_name' => 'api',
                ]);
            }
        }

        // $roles[] = Role::where('name', 'superadmin')->first();
        // $roles[] = Role::where('name', 'admin')->first();
        // $roles[] = Role::where('name', 'driver')->first();
        // $roles[] = Role::where('name', 'operator')->first();
        // $roles[] = Role::where('name', 'operator_user')->first();
        // $roles[] = Role::where('name', 'supervisor')->first();
        // $roles[] = Role::where('name', 'user')->first();

        $permissions = Permission::all();

        foreach(Role::all() as $role){
            foreach($permissions as $permission) {
                $role->givePermissionTo($permission);
            }
        }

        $operatorRoles[] = Role::where('name', 'operator')->first();
        $operatorRoles[] = Role::where('name', 'operator_user')->first();

        foreach($operatorRoles as $operatorRole) {
            $operatorRole->revokePermissionTo('admin-access vends');
            $operatorRole->revokePermissionTo('admin-access transactions');
            $operatorRole->revokePermissionTo('admin-access products');
            $operatorRole->revokePermissionTo('admin-access product-mappings');
            $operatorRole->revokePermissionTo('admin-access operators');
            $operatorRole->revokePermissionTo('admin-access resource-centers');
            $operatorRole->revokePermissionTo('admin-access users');
        }

        $admin = Role::where('name' , 'admin')->first();
        $users = User::all();
        foreach($users as $user) {
            $user->assignRole($admin);
        }

        $superadmin = Role::where('name', 'superadmin')->first();
        $user = User::where('email', 'leehongjie91@gmail.com')->first();
        $user->assignRole($superadmin);

        $normalUserRoles = Role::whereIn('name', ['user', 'operator_user'])->get();
        foreach($normalUserRoles as $normalUserRole) {
            foreach($models as $model) {
                foreach($actions as $action) {
                    if($action == 'create' or $action == 'update' or $action == 'delete') {
                        $normalUserRole->revokePermissionTo($action . ' ' . $model);
                    }
                }
            }
        }

    }
}
