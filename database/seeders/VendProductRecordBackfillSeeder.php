<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;

/**
 * Backfill the ~102 days missing from vend_product_records.
 *
 * This is a thin wrapper around `vpr:rebuild --gaps`. The real logic — gap
 * detection, day-at-a-time dispatch, the resume/verify pass — lives in
 * App\Console\Commands\RebuildVendProductRecords, because a backfill needs
 * arguments (which range? queue or inline? dry run first?) and seeders take
 * none. Read that class before running this one; its docblock explains why
 * reconcile:range never heals these days.
 *
 * This seeder exists so the fix ships the same way as the permission change:
 *
 *     php artisan db:seed --class=DashboardPerformanceLitePermissionSeeder
 *     php artisan db:seed --class=VendProductRecordBackfillSeeder
 *
 * WHAT IT DOES
 * ============
 * Queues one StoreVendProductRecords job per missing day onto the `low` queue.
 * It does NOT run them inline: ~102 days x ~1,900 rows, each row an upsert, is
 * roughly 390k queries. Blocking a deploy on that is how a release times out.
 * Queueing on `low` (the house convention — same as backfill:multiple-txn-gp)
 * keeps it off the main queue and lets it drain in the background.
 *
 * If the box has no queue worker, run the command directly with --sync instead:
 *
 *     php artisan vpr:rebuild --gaps --sync
 *
 * SAFE TO RE-RUN
 * ==============
 * The job upserts on (vend_id, customer_id, product_id, date), so a day
 * rebuilt twice is overwritten, never double-counted. And --gaps only picks up
 * days that are still missing, so a second run after a partial drain does the
 * remainder and nothing else. Running it when nothing is missing is a no-op.
 *
 * CHECK FIRST, IDEALLY
 * ====================
 *     php artisan vpr:rebuild --gaps --dry-run
 *
 * That prints the exact day list without dispatching anything. Worth doing on
 * production before letting a seeder queue 100+ jobs.
 */
class VendProductRecordBackfillSeeder extends Seeder
{
    public function run(): void
    {
        $this->command?->info('Backfilling vend_product_records for days missing from the rollup...');
        $this->command?->newLine();

        // Route through the command so the seeder and a manual run cannot drift
        // apart — there is exactly one implementation of "which days are missing".
        // --force because a seeder runs non-interactively and a confirm() prompt
        // here would take the default without ever showing itself.
        $exit = $this->command
            ? $this->command->call('vpr:rebuild', ['--gaps' => true, '--force' => true])
            : Artisan::call('vpr:rebuild', ['--gaps' => true, '--force' => true]);

        if ($exit !== 0) {
            $this->command?->warn('vpr:rebuild reported errors — see the output above. Re-running this seeder is safe.');

            return;
        }

        $this->command?->newLine();
        $this->command?->info('Jobs queued. Once the low queue drains, confirm with:');
        $this->command?->line('  php artisan vpr:rebuild --gaps --dry-run');
    }
}
