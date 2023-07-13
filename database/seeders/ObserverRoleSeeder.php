<?php

namespace Database\Seeders;

use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;


class ObserverRoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $readDashboard = Permission::updateOrCreate([
            'name' => 'read dashboard',
            'guard_name' => 'api'
        ]);

        Role::create([
            'name' => 'vc_observer',
            'guard_name' => 'api',
        ]);

        $roles = Role::all();

        foreach($roles as $role) {
            $role->givePermissionTo($readDashboard);
        }




    }
}
