<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Dashboard > Performance (Lite) — production-safe permission rollout.
 *
 * WHY THIS EXISTS RATHER THAN JUST RUNNING RolePermissionSyncSeeder
 * ================================================================
 * RolePermissionSyncSeeder is a FULL RESYNC: it calls syncPermissions([]) on
 * every role and then `Permission::truncate()`. On production that means
 *
 *   - every role is stripped of every permission for the duration of the run,
 *     so anyone mid-request in that window gets a 403;
 *   - `model_has_permissions` (permissions granted DIRECTLY to a user rather
 *     than through a role) is emptied by the truncate's cascade and never
 *     rebuilt, because that seeder only knows about roles;
 *   - it also applies every other unreleased edit sitting in that file.
 *
 * None of that is acceptable just to add one page. This seeder is additive and
 * idempotent: it touches exactly the four permission rows below and nothing
 * else, so it is safe to run on a live box at any time, and safe to re-run.
 *
 * RolePermissionSyncSeeder has been updated to match, so a later full resync
 * lands on the same end state — this seeder just gets you there without the
 * outage.
 *
 * WHAT IT DOES
 * ============
 *   + read/export dashboard-performance-lite  ->  superadmin, admin, prod_owner
 *   - read/export dashboard-performance       ->  REVOKED from prod_owner
 *
 * The revoke is the point of the whole change, not an afterthought. The full
 * Performance page reads vend_records, whose grain is date x machine with no
 * product dimension, so it can only ever show a product owner whole-machine
 * money. Performance (Lite) reads vend_product_records and is narrowed to their
 * own SKUs. Leaving prod_owner on both would defeat the split — and because
 * DashboardController now gates index() server-side, the revoke is what
 * actually closes the URL, not just the sidebar link.
 *
 * Run:
 *   php artisan db:seed --class=ProdOwnerRoleSeeder                     # if the role is missing
 *   php artisan db:seed --class=DashboardPerformanceLitePermissionSeeder
 *
 * ProdOwnerRoleSeeder must run first on any box where prod_owner does not
 * exist yet, or the grant below is skipped with a warning (never a crash).
 */
class DashboardPerformanceLitePermissionSeeder extends Seeder
{
    /** Roles that get the new Lite page. */
    private const GRANT_LITE_TO = ['superadmin', 'admin', 'prod_owner'];

    /** Roles that must LOSE the whole-machine Performance page. */
    private const REVOKE_FULL_FROM = ['prod_owner'];

    private const LITE_PERMISSIONS = [
        'read dashboard-performance-lite',
        'export dashboard-performance-lite',
    ];

    private const FULL_PERMISSIONS = [
        'read dashboard-performance',
        'export dashboard-performance',
    ];

    public function run(): void
    {
        // firstOrCreate, not create: re-running must not throw on the
        // permissions.name unique index.
        foreach (self::LITE_PERMISSIONS as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        // Spatie caches the whole permission map. Forget it BEFORE granting, or
        // givePermissionTo() resolves the new rows against a stale cache and
        // throws PermissionDoesNotExist.
        $this->forgetCache();

        foreach (self::GRANT_LITE_TO as $roleName) {
            $role = Role::where('name', $roleName)->where('guard_name', 'web')->first();

            if (! $role) {
                // Mirrors RolePermissionSyncSeeder's `if ($role)` guard: a box
                // that has not run ProdOwnerRoleSeeder yet should get a warning,
                // not a failed deploy.
                $this->command?->warn("  skipped: role '{$roleName}' does not exist on this database.");
                continue;
            }

            $role->givePermissionTo(self::LITE_PERMISSIONS);
            $this->command?->info("  + {$roleName}: " . implode(', ', self::LITE_PERMISSIONS));
        }

        foreach (self::REVOKE_FULL_FROM as $roleName) {
            $role = Role::where('name', $roleName)->where('guard_name', 'web')->first();

            if (! $role) {
                continue;
            }

            foreach (self::FULL_PERMISSIONS as $name) {
                // revokePermissionTo() on a permission the role never held is a
                // no-op, but it still resolves the name — and would throw if the
                // permission row itself is absent. Guard on existence so this
                // stays safe on a fresh/partial database.
                if (! Permission::where('name', $name)->where('guard_name', 'web')->exists()) {
                    continue;
                }

                if ($role->hasPermissionTo($name)) {
                    $role->revokePermissionTo($name);
                    $this->command?->info("  - {$roleName}: {$name}");
                }
            }
        }

        // And again afterwards, so the very next request sees the new map.
        $this->forgetCache();

        $this->command?->info('Dashboard > Performance (Lite) permissions synced.');
    }

    private function forgetCache(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
