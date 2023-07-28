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
            'name' => 'superadmin',
            'guard_name' => 'api',
        ]);

        Role::create([
            'name' => 'admin',
            'guard_name' => 'api',
        ]);

        Role::create([
            'name' => 'supervisor',
            'guard_name' => 'api',
        ]);

        Role::create([
            'name' => 'driver',
            'guard_name' => 'api',
        ]);

        Role::create([
            'name' => 'user',
            'guard_name' => 'api',
        ]);
    }
}
