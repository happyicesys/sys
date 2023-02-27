<?php

namespace Database\Seeders;

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

        foreach($models as $model) {
            foreach($actions as $action) {
                Permission::create([
                    'name' => $action.' '.$model,
                ]);
            }
        }

        $roles[] = Role::where('name', 'admin')->first();
        $roles[] = Role::where('name', 'driver')->first();
        $roles[] = Role::where('name', 'operator')->first();
        $roles[] = Role::where('name', 'operator_user')->first();
        $roles[] = Role::where('name', 'supervisor')->first();

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
    }
}
