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

        $roles[] = Role::where('name', 'superadmin')->first();
        $roles[] = Role::where('name', 'admin')->first();
        $roles[] = Role::where('name', 'driver')->first();
        $roles[] = Role::where('name', 'operator')->first();
        $roles[] = Role::where('name', 'operator_user')->first();
        $roles[] = Role::where('name', 'supervisor')->first();
        $roles[] = Role::where('name', 'user')->first();

        $permissions = Permission::all();

        foreach($roles as $role){
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

        $superadmin = Role::where('name', 'superadmin')->first();
        $user = User::where('email', 'leehongjie91@gmail.com')->first();
        $user->assignRole($superadmin);

        $normalUserRoles[] = Role::where('name', 'user')->first();
        $normalUserRoles[] = Role::where('name', 'operator_user')->first();
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
