<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * RETIRED 2026-08-10 — deliberately a no-op.
 *
 * RolePermissionSyncSeeder is the single source of truth for every role and
 * permission; it already creates and grants
 * 'read/export dashboard-performance-lite' (superadmin, admin, prod_owner).
 *
 * This seeder's old behaviour additionally REVOKED
 * 'read/export dashboard-performance' from prod_owner — which now directly
 * contradicts the 2026-08-09 sheet sync that grants the full Performance page
 * to prod_owner in RolePermissionSyncSeeder. Running it after the sync seeder
 * would undo that grant.
 *
 * Kept as a stub (rather than deleted) so anything still calling
 *
 *     php artisan db:seed --class=DashboardPerformanceLitePermissionSeeder
 *
 * prints a pointer instead of doing damage. (VendProductRecordBackfillSeeder's
 * docblock references this class only as historical setup context — the
 * backfill itself does not depend on it.)
 */
class DashboardPerformanceLitePermissionSeeder extends Seeder
{
    public function run(): void
    {
        $this->command?->warn(
            'DashboardPerformanceLitePermissionSeeder is retired (2026-08-10) and does nothing. '
            . 'Roles and permissions sync ONLY via RolePermissionSyncSeeder — amend its tuples and run: '
            . 'php artisan db:seed --class=RolePermissionSyncSeeder'
        );
    }
}
