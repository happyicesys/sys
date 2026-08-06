<?php

namespace App\Console\Commands;

use App\Jobs\StoreVendProductRecords;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Rebuild vend_product_records for days that never got written.
 *
 * WHY reconcile:range / reconcile:sales-rollups DO NOT COVER THIS
 * ===============================================================
 * Those commands compute control totals for `vend_records` and `gp_metrics`,
 * and dispatch StoreVendProductRecords only as a PASSENGER on days where one of
 * those two drifts. The four gap months below have perfectly healthy
 * vend_records, so reconcile inspects them, finds nothing wrong with what it
 * measures, and moves on — leaving vend_product_records empty forever. That is
 * Concern 3 of the VPR rollup design biting for real: nothing watches VPR's own
 * completeness. This command is that watcher.
 *
 * Measured on live 2026-08-06 — days present in vend_records, absent in VPR:
 *
 *     2025-08   22 missing of 31
 *     2025-11   27 missing of 30
 *     2026-01   28 missing of 31
 *     2026-02   25 missing of 28
 *     ------------------------------
 *     102 days
 *
 * Every other month is complete. Source data is INTACT for all four (checked:
 * vend_transactions has full day coverage and ~90-105k rows in each), so the
 * rebuild has real transactions to aggregate — it is not going to write zeros.
 *
 * WHY DAY AT A TIME, NEVER A RANGE IN ONE JOB
 * ===========================================
 * StoreVendProductRecords holds its ENTIRE merged result set in a PHP array and
 * then issues one `updateOrCreate` — a SELECT plus an INSERT/UPDATE — per row.
 * At ~1,900 rows/day, handing it a 28-day range means ~53k rows in memory and
 * ~106k queries inside a single job, which is how you get an OOM or a queue
 * timeout halfway through with no idea which days landed. One day per job keeps
 * memory flat, makes the work resumable, and means a failure costs you one day.
 *
 * RE-RUNNING IS SAFE
 * ==================
 * The job upserts via updateOrCreate keyed on
 * (vend_id, customer_id, product_id, date), so a day that is rebuilt twice is
 * overwritten, not double-counted. That is what makes --gaps safe to run on a
 * schedule, and safe to re-run after a partial failure.
 *
 * Usage:
 *   php artisan vpr:rebuild --gaps --dry-run     # show what is missing, touch nothing
 *   php artisan vpr:rebuild --gaps               # queue every missing day on 'low'
 *   php artisan vpr:rebuild --gaps --sync        # run inline (no queue worker needed)
 *   php artisan vpr:rebuild --from=2025-11-01 --to=2025-11-30
 */
class RebuildVendProductRecords extends Command
{
    protected $signature = 'vpr:rebuild
                            {--gaps : Rebuild every day that exists in vend_records but is missing from vend_product_records (default when no --from/--to given)}
                            {--from= : Start date YYYY-MM-DD — rebuild this explicit range instead of auto-detecting}
                            {--to= : End date YYYY-MM-DD}
                            {--sync : Run each day inline instead of queueing. Slower and blocking, but needs no queue worker and you see it finish}
                            {--queue=low : Queue to dispatch on. Defaults to low so a 100-day backfill cannot jam the main queue}
                            {--dry-run : List the days that would be rebuilt, then exit without dispatching}
                            {--force : Skip the confirmation prompt (implied when running non-interactively, e.g. from a seeder)}';

    protected $description = 'Rebuild vend_product_records for days missing from the rollup. reconcile:range does not cover these.';

    public function handle(): int
    {
        $days = $this->option('from') || $this->option('to')
            ? $this->explicitRange()
            : $this->missingDays();

        if ($days === null) {
            return self::FAILURE;
        }

        if (empty($days)) {
            $this->info('vend_product_records has no missing days. Nothing to rebuild.');

            return self::SUCCESS;
        }

        $count = count($days);
        $this->info("{$count} day(s) to rebuild: {$days[0]} .. {$days[$count - 1]}");
        $this->line('  <fg=gray>~' . number_format($count * 1900) . ' rows, each an upsert. Re-running is safe (updateOrCreate).</>');

        if ($this->option('dry-run')) {
            $this->newLine();
            foreach (array_chunk($days, 8) as $chunk) {
                $this->line('  ' . implode('  ', $chunk));
            }
            $this->newLine();
            $this->comment('Dry run — nothing dispatched.');

            return self::SUCCESS;
        }

        // isInteractive() is false under a seeder or a cron, where a prompt would
        // silently take the default and look like a hang.
        if (! $this->option('force') && $this->input->isInteractive()
            && ! $this->confirm("Rebuild {$count} day(s)?", true)) {
            $this->comment('Aborted.');

            return self::SUCCESS;
        }

        $sync = (bool) $this->option('sync');
        $queue = (string) $this->option('queue');

        $this->newLine();
        $this->line($sync
            ? '<fg=cyan>Running inline (--sync). This will take a while.</>'
            : "<fg=cyan>Dispatching one job per day onto the '{$queue}' queue.</>");

        $bar = $this->output->createProgressBar($count);
        $bar->start();

        $failed = [];

        foreach ($days as $day) {
            try {
                if ($sync) {
                    StoreVendProductRecords::dispatchSync($day, $day);
                } else {
                    StoreVendProductRecords::dispatch($day, $day)->onQueue($queue);
                }
            } catch (\Throwable $e) {
                // One bad day must not abort the other 101. Collect and report.
                $failed[$day] = $e->getMessage();
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        foreach ($failed as $day => $message) {
            $this->error("  {$day}: {$message}");
        }

        if ($sync) {
            // Only meaningful for --sync: with a queue the work has not run yet.
            $stillMissing = $this->missingDays() ?? [];
            $remaining = array_values(array_intersect($stillMissing, $days));

            if ($remaining) {
                $this->warn(count($remaining) . ' day(s) produced no rows and are still missing:');
                $this->line('  ' . implode('  ', array_slice($remaining, 0, 20)));
                $this->line('  <fg=gray>A day with vend_records but no product-attributable transactions will always look like this. Check vend_transactions for that date before re-running.</>');
            } else {
                $this->info('All requested days now have rows in vend_product_records.');
            }
        } else {
            $this->info("Queued {$count} day(s) on '{$queue}'. Re-run with --gaps --dry-run once the queue drains to confirm.");
        }

        return empty($failed) ? self::SUCCESS : self::FAILURE;
    }

    /**
     * Days present in vend_records but absent from vend_product_records.
     *
     * Two cheap DISTINCT plucks and an array_diff, rather than a LEFT JOIN
     * against a derived DISTINCT over 622k rows. Both columns are indexed, so
     * each side is a loose index scan returning a few hundred dates.
     */
    private function missingDays(): ?array
    {
        // Floor at the first day VPR ever covered. vend_records reaches further
        // back than the rollup was ever designed to, and silently manufacturing
        // history nobody asked for is not this command's job — use explicit
        // --from/--to if you genuinely want to extend the rollup backwards.
        $floor = DB::table('vend_product_records')->min('date');

        if ($floor === null) {
            $this->error('vend_product_records is empty — refusing to guess a start date. Use --from and --to explicitly.');

            return null;
        }

        $floor = substr((string) $floor, 0, 10);

        $have = DB::table('vend_product_records')
            ->distinct()
            ->pluck('date')
            ->map(fn ($d) => substr((string) $d, 0, 10))
            ->all();

        $want = DB::table('vend_records')
            ->where('date', '>=', $floor)
            ->distinct()
            ->pluck('date')
            ->map(fn ($d) => substr((string) $d, 0, 10))
            ->all();

        $missing = array_values(array_diff($want, $have));
        sort($missing);

        return $missing;
    }

    private function explicitRange(): ?array
    {
        $from = $this->option('from');
        $to = $this->option('to');

        if (! $from || ! $to) {
            $this->error('--from and --to must be given together. Use --gaps to auto-detect instead.');

            return null;
        }

        try {
            $start = Carbon::parse($from)->startOfDay();
            $end = Carbon::parse($to)->startOfDay();
        } catch (\Throwable $e) {
            $this->error('Could not parse --from/--to. Use YYYY-MM-DD.');

            return null;
        }

        if ($start->gt($end)) {
            $this->error('--from cannot be after --to.');

            return null;
        }

        return collect(CarbonPeriod::create($start, $end))
            ->map(fn ($d) => $d->toDateString())
            ->all();
    }
}
