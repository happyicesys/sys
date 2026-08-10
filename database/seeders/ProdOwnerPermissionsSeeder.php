<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * RETIRED 2026-08-10 — deliberately a no-op.
 *
 * RolePermissionSyncSeeder is the single source of truth for every role and
 * permission. This seeder's old behaviour (a full resync of prod_owner to a
 * fixed list of 12 grants) would silently STRIP the grants the 2026-08-09
 * sheet sync added there ('read/export dashboard' and
 * 'read/export dashboard-performance'), so running it after the sync seeder
 * actively corrupts the role.
 *
 * Kept as a stub (rather than deleted) so any deploy script, runbook or shell
 * history that still calls
 *
 *     php artisan db:seed --class=ProdOwnerPermissionsSeeder
 *
 * prints a pointer instead of doing damage. To change prod_owner's access,
 * amend the tuples in RolePermissionSyncSeeder and run that.
 */
class ProdOwnerPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        $this->command?->warn(
            'ProdOwnerPermissionsSeeder is retired (2026-08-10) and does nothing. '
            . 'Roles and permissions sync ONLY via RolePermissionSyncSeeder — amend its tuples and run: '
            . 'php artisan db:seed --class=RolePermissionSyncSeeder'
        );
    }
}
