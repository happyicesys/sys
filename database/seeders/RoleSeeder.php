<?php

namespace Database\Seeders;

use Spatie\Permission\Models\Role;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        Role::create([
            'name' => 'Superadmin',
            'guard_name' => 'superadmin',
        ]);

        Role::create([
            'name' => 'Admin',
            'guard_name' => 'admin',
        ]);

        Role::create([
            'name' => 'Supervisor',
            'guard_name' => 'supervisor',
        ]);

        Role::create([
            'name' => 'Account',
            'guard_name' => 'account',
        ]);

        Role::create([
            'name' => 'Account & Admin',
            'guard_name' => 'account_admin',
        ]);

        Role::create([
            'name' => 'Driver',
            'guard_name' => 'driver',
        ]);

        Role::create([
            'name' => 'Driver Supervisor',
            'guard_name' => 'driver_supervisor',
        ]);

        Role::create([
            'name' => 'Technician',
            'guard_name' => 'technician',
        ]);

        Role::create([
            'name' => 'Merchandiser',
            'guard_name' => 'merchandiser',
        ]);
    }
}
