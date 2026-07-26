<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Idempotent: adds `read visitor-history` and grants it to the admin-tier roles.
 * superadmin is granted explicitly as well because the sidebar checks
 * permissions literally (permissions.includes(...)), so a Gate::before
 * super-user would otherwise never see the "Visitor History" link.
 * Mirrored in RolePermissionSyncSeeder so a full re-sync keeps it.
 */
return new class extends Migration
{
    public function up(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $permissions = ['read visitor-history'];
        foreach ($permissions as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        foreach (['superadmin', 'admin'] as $roleName) {
            $role = Role::where('name', $roleName)->where('guard_name', 'web')->first();
            if ($role) {
                $role->givePermissionTo($permissions);
            }
        }

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public function down(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $permission = Permission::where('name', 'read visitor-history')->where('guard_name', 'web')->first();
        if ($permission) {
            $permission->delete();
        }

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }
};
