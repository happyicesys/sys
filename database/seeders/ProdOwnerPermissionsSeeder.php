<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Finish prod_owner's 2026-08-05 sheet-sync grants — production-safe.
 *
 * WHY THIS IS NEEDED
 * ==================
 * The permission sheet's "Prod Owner" column grants four things: Dashboard
 * (Lite), Dashboard > Performance, All Transaction (limited filter) and
 * Tutorial > Management. Those rows live in RolePermissionSyncSeeder, which has
 * never been run on production — so on live (checked 2026-08-06) prod_owner
 * holds exactly FOUR permissions:
 *
 *     read/export vend-customers-lite        <- Operations > Dashboard (Lite)
 *     read/export dashboard-performance-lite <- added by DashboardPerformanceLitePermissionSeeder
 *
 * and nothing else. That is precisely why a prod_owner sees ONE sidebar item.
 * The Transactions and Tutorial permissions below all EXIST in the permissions
 * table already; prod_owner simply was never granted them.
 *
 * DashboardPerformanceLitePermissionSeeder did not cover these on purpose — it
 * was scoped to the one permission the Lite page needed, which is what made it
 * safe to run on a live box. This seeder is the same shape for the rest.
 *
 * WHY NOT JUST RUN RolePermissionSyncSeeder
 * =========================================
 * Because it is a FULL RESYNC: syncPermissions([]) on every role, then
 * Permission::truncate(). On production that 403s everyone mid-run, drops every
 * direct model_has_permissions grant without rebuilding it, and applies every
 * other unreleased edit sitting in that file — all to fix one role. This seeder
 * touches prod_owner and nothing else, and is idempotent.
 *
 * WHAT IT GRANTS (role: prod_owner only)
 * ======================================
 *   read/export transactions            Transactions section
 *   read/export transactions-sales      Transactions > All Transactions
 *   read/export tutorials               Tutorial
 *   read/export tutorials-operators     Tutorial > "Management to know"
 *
 * DELIBERATELY NOT GRANTED — these are the "limited" in the sheet's
 * "All Transaction (LIMITED filter)", and granting them would quietly widen the
 * role beyond what the sheet asked for:
 *
 *   admin-access transactions        ~10 extra filter controls on Transaction.vue
 *   read transactions-daily-summary  the sheet gives prod_owner All Transactions
 *                                    and NOT Daily Summary
 *   read dashboard-performance       the whole-machine page; prod_owner gets the
 *                                    product-narrowed Lite page instead
 *
 * Run:
 *   php artisan db:seed --class=ProdOwnerPermissionsSeeder
 */
class ProdOwnerPermissionsSeeder extends Seeder
{
    private const ROLE = 'prod_owner';

    private const GRANT = [
        'read transactions',
        'export transactions',
        'read transactions-sales',
        'export transactions-sales',
        'read tutorials',
        'export tutorials',
        'read tutorials-operators',
        'export tutorials-operators',
    ];

    public function run(): void
    {
        $role = Role::where('name', self::ROLE)->where('guard_name', 'web')->first();

        if (! $role) {
            $this->command?->warn("Role '" . self::ROLE . "' does not exist — run ProdOwnerRoleSeeder first. Nothing changed.");

            return;
        }

        // Forget BEFORE granting: givePermissionTo() resolves names against the
        // cached map, and a stale cache throws PermissionDoesNotExist for a row
        // that is sitting right there in the table.
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $granted = [];
        $missing = [];

        foreach (self::GRANT as $name) {
            // firstOrCreate would paper over a typo by inventing a permission
            // nothing checks — a silent no-op that looks like success. Every name
            // above was verified present on live, so absence means the name is
            // wrong or the box is behind: say so instead.
            $permission = Permission::where('name', $name)->where('guard_name', 'web')->first();

            if (! $permission) {
                $missing[] = $name;

                continue;
            }

            if (! $role->hasPermissionTo($permission)) {
                $role->givePermissionTo($permission);
                $granted[] = $name;
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach ($granted as $name) {
            $this->command?->info("  + prod_owner: {$name}");
        }

        if ($granted === []) {
            $this->command?->info('  prod_owner already held every permission in this set — nothing to do.');
        }

        foreach ($missing as $name) {
            $this->command?->warn("  ! permission row not found, skipped: {$name}");
        }

        $this->command?->info('prod_owner permissions synced. Users must log out and back in for the sidebar to refresh.');
    }
}
