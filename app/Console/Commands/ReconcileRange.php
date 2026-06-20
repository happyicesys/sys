<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

/**
 * Operator-friendly wrapper around `reconcile:sales-rollups` for a date range.
 *
 * Reconciles every vend_transactions-derived rollup (vend_records, gp_metrics,
 * vend_product_records) month by month, then cascades the totals_json resync +
 * unlocked Site-Summary recompute — so the machine page, Site Summary and Sales
 * Transactions page tally for the covered range.
 *
 * Defaults to 1 Jan of the current year → yesterday, so the common case is just:
 *   php artisan reconcile:range
 *
 * Explicit range:
 *   php artisan reconcile:range --from=2026-06-01 --to=2026-06-19
 *   php artisan reconcile:range --from=2025-01-01 --to=2025-12-31 --dry-run
 *
 * A queue worker must be running — heals dispatch to the 'low' queue:
 *   php artisan queue:work --queue=low
 */
class ReconcileRange extends Command
{
    protected $signature = 'reconcile:range
        {--from= : Start date YYYY-MM-DD (default: 1 Jan of the current year)}
        {--to= : End date YYYY-MM-DD (default: yesterday; never scans past yesterday)}
        {--dry-run : Report drifted days only; write nothing}
        {--queue=low : Queue used for dispatched heal jobs}';

    protected $description = 'Reconcile all vend_transactions-derived rollups over a date range (default: this year → yesterday), month by month.';

    public function handle(): int
    {
        // Never reconcile past yesterday — today is still in flight.
        $yesterday = Carbon::yesterday()->startOfDay();

        $from = $this->option('from')
            ? Carbon::parse($this->option('from'))->startOfDay()
            : Carbon::today()->startOfYear();

        $to = $this->option('to')
            ? Carbon::parse($this->option('to'))->startOfDay()
            : $yesterday->copy();

        if ($to->gt($yesterday)) {
            $this->warn(sprintf('Capping --to at yesterday (%s); today is still in flight.', $yesterday->toDateString()));
            $to = $yesterday->copy();
        }

        if ($from->gt($to)) {
            $this->error(sprintf('--from (%s) is after --to (%s) — nothing to do.', $from->toDateString(), $to->toDateString()));
            return self::FAILURE;
        }

        $dry = (bool) $this->option('dry-run');
        $queue = (string) $this->option('queue');

        $this->info(sprintf(
            'reconcile:range %s → %s%s (heals dispatch to "%s" queue)',
            $from->toDateString(),
            $to->toDateString(),
            $dry ? ' [DRY-RUN — nothing written]' : '',
            $queue
        ));

        // One pass per calendar month: visible progress + each pass is a single
        // grouped scan per rollup table.
        $cursor = $from->copy()->startOfMonth();
        while ($cursor->lte($to)) {
            $monthStart = $cursor->gt($from) ? $cursor->copy() : $from->copy();
            $monthEnd = $cursor->copy()->endOfMonth();
            if ($monthEnd->gt($to)) {
                $monthEnd = $to->copy();
            }

            $params = [
                '--from' => $monthStart->toDateString(),
                '--to' => $monthEnd->toDateString(),
                '--queue' => $queue,
            ];
            if ($dry) {
                $params['--dry-run'] = true;
            }

            // Stream the sub-command's own per-day drift report to this output.
            Artisan::call('reconcile:sales-rollups', $params, $this->getOutput());

            $cursor->addMonthNoOverflow()->startOfMonth();
        }

        $this->info($dry
            ? 'Dry-run complete. Re-run without --dry-run to heal.'
            : 'Done. Let the low queue drain, then verify with customer-summary:validate-sales.');

        return self::SUCCESS;
    }
}
