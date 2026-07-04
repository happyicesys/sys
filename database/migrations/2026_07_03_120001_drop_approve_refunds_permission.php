<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    /**
     * Manager-approval level removed. On existing databases: hand the supervisor
     * role the new authorising ability ('verify refunds') so it keeps an
     * actionable refund role, then drop the now-unused 'approve refunds'
     * permission (its role assignments cascade away with it). Idempotent.
     */
    public function up(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Ensure the replacement ability exists, then grant it to supervisors.
        $verify = Permission::firstOrCreate(['name' => 'verify refunds', 'guard_name' => 'web']);
        $supervisor = Role::where('name', 'supervisor')->where('guard_name', 'web')->first();
        if ($supervisor) {
            $supervisor->givePermissionTo($verify);
        }

        // Drop the retired permission (removes it from every role).
        $approve = Permission::where('name', 'approve refunds')->where('guard_name', 'web')->first();
        if ($approve) {
            $approve->delete();
        }

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Recreate the permission and restore the original supervisor grant.
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $approve = Permission::firstOrCreate(['name' => 'approve refunds', 'guard_name' => 'web']);
        foreach (['superadmin', 'supervisor'] as $roleName) {
            $role = Role::where('name', $roleName)->where('guard_name', 'web')->first();
            if ($role) {
                $role->givePermissionTo($approve);
            }
        }

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }
};
