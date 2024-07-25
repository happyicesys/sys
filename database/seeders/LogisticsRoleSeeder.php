<?php

namespace Database\Seeders;

use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class LogisticsRoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // remove the excessive roles
        $removeRoles = ['user', 'user_observer', 'vc_observer', 'vc_observer_loose'];


        $role = Role::create([
            'name' => 'logistics',
            'guard_name' => 'web',
        ]);

        $permissions = Permission::all();

        $role->syncPermissions($permissions);
    }
}
