<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;

/**
 * Thin db:seed entry point for the `reconcile:range` command — clears backlog
 * that predates the nightly/weekly reconcile windows (e.g. a vend_records hole
 * left by a late gateway settlement) so the machine page, Site Summary and
 * Sales Transactions page tally.
 *
 * Prefer the command directly — it takes --from/--to (a seeder cannot):
 *   php artisan reconcile:range                              # this year → yesterday
 *   php artisan reconcile:range --from=2026-06-01 --to=2026-06-19
 *
 * This seeder simply delegates to it for those who prefer db:seed:
 *   php artisan db:seed --class=ReconcileSalesRollupsSeeder              # this year → yesterday
 *   RECONCILE_FROM=2025-01-01 RECONCILE_TO=2025-12-31 \
 *     php artisan db:seed --class=ReconcileSalesRollupsSeeder            # custom range
 *
 * A queue worker must be running — heals dispatch to the 'low' queue:
 *   php artisan queue:work --queue=low
 */
class ReconcileSalesRollupsSeeder extends Seeder
{
    public function run(): void
    {
        // Defaults (this year → yesterday) live in the command; only forward an
        // override when the env vars are set.
        $opts = [];
        if (env('RECONCILE_FROM')) {
            $opts['--from'] = env('RECONCILE_FROM');
        }
        if (env('RECONCILE_TO')) {
            $opts['--to'] = env('RECONCILE_TO');
        }

        Artisan::call('reconcile:range', $opts, $this->command?->getOutput());
    }
}
