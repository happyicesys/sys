<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    /**
     * Give the supervisor role full parity with admin on the Refund Requests and
     * Refund Settlement pages. Previously supervisor had only 'read refunds' +
     * 'verify refunds', so:
     *   - the "Re-match / Match transaction" box (bind a ticket to an Order ID)
     *     was hidden and its POST /refunds/{ticket}/match|clear-match routes 403'd
     *     — both are gated on 'update refunds';
     *   - every Refund Settlement action (close/reopen, export CIMB/XLSX, mark-done,
     *     return-to-pool, …) 403'd — all gated on 'payout refunds'.
     *
     * Grant supervisor the missing abilities (create/update/payout) so it matches
     * admin's refund grant (read/create/update/verify/payout). Idempotent —
     * givePermissionTo is a no-op if the role already holds the permission.
     */
    public function up(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Ensure the abilities exist (they normally do from the earlier refund
        // permission migration; firstOrCreate keeps this self-contained).
        foreach (['create refunds', 'update refunds', 'payout refunds'] as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        $supervisor = Role::where('name', 'supervisor')->where('guard_name', 'web')->first();
        if ($supervisor) {
            $supervisor->givePermissionTo(['create refunds', 'update refunds', 'payout refunds']);
        }

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }

    /**
     * Revert supervisor back to the prior read + verify only footprint. The
     * permissions themselves are left intact (admin/superadmin still use them);
     * only the supervisor grant is withdrawn.
     */
    public function down(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $supervisor = Role::where('name', 'supervisor')->where('guard_name', 'web')->first();
        if ($supervisor) {
            $supervisor->revokePermissionTo(['create refunds', 'update refunds', 'payout refunds']);
        }

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }
};
