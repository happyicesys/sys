<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class CustomerPermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $actions = ['create', 'read', 'update', 'delete', 'admin-access'];
        $models = [
            'customers',
        ];

        foreach($actions as $action) {
            foreach($models as $model) {
                Permission::create([
                    'name' => $action . ' ' . $model,
                    'guard_name' => 'web',
                ]);
            }
        }

        $permissions = Permission::all();

        foreach(Role::all() as $role){
            foreach($permissions as $permission) {
                $role->givePermissionTo($permission);
            }
        }
    }
}
