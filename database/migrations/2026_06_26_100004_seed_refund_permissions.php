<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    /**
     * Idempotent: adds refund permissions and assigns them to existing roles.
     * superadmin is granted them explicitly too — although AuthServiceProvider's
     * Gate::before authorises superadmin for every ability, the sidebar nav checks
     * permissions literally (permissions.includes(...)), so without the explicit
     * grant the Refund Requests link stays hidden for superadmin users.
     */
    public function up(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $permissions = [
            'read refunds',
            'create refunds',
            'update refunds',
            'verify refunds',
            'approve refunds',
            'payout refunds',
        ];

        foreach ($permissions as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        $map = [
            'superadmin' => ['read refunds', 'create refunds', 'update refunds', 'verify refunds', 'approve refunds', 'payout refunds'],
            'operator'   => ['read refunds', 'verify refunds'],
            'admin'      => ['read refunds', 'create refunds', 'update refunds', 'verify refunds', 'payout refunds'],
            'supervisor' => ['read refunds', 'approve refunds'],
        ];

        foreach ($map as $roleName => $perms) {
            $role = Role::where('name', $roleName)->where('guard_name', 'web')->first();
            if ($role) {
                $role->givePermissionTo($perms);
            }
        }

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public function down(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        foreach (['read refunds', 'create refunds', 'update refunds', 'verify refunds', 'approve refunds', 'payout refunds'] as $name) {
            $permission = Permission::where('name', $name)->where('guard_name', 'web')->first();
            if ($permission) {
                $permission->delete();
            }
        }

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }
};
