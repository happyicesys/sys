<?php

namespace Database\Seeders;

use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ExportPermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $exportExcelPermission = Permission::updateOrCreate([
            'name' => 'export excel',
            'guard_name' => 'api'
        ]);

        $observerRole = Role::updateOrCreate([
            'name' => 'user_observer',
            'guard_name' => 'api'
        ]);

        // sync with user roles
        $userRole = Role::where('name', 'user')->first();
        foreach($userRole->permissions as $permission) {
            $observerRole->givePermissionTo($permission);
        }

        $observerRole->revokePermissionTo('read transactions');

        $roles = Role::where('id', '!=', $observerRole->id)->get();

        foreach($roles as $role) {
            $role->givePermissionTo($exportExcelPermission);
        }


    }
}
