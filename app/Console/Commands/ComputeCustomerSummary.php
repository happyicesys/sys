<?php

namespace App\Console\Commands;

use App\Jobs\ProcessCustomerSummaryMonth;
use App\Jobs\ProcessGpMetricsDay;
use App\Jobs\ProcessGpMetricsRange;
use App\Services\CustomerSummaryAggregator;
use Carbon\Carbon;
use Illuminate\Bus\PendingDispatch;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Bus;

/**
 * Compute / re-compute customer_period_summaries.
 *
 * Defaults to "the month containing yesterday" — the same window the nightly
 * scheduler runs. Pass --from / --to (or --month) to backfill a range.
 *
 * Examples:
 *   php artisan customer-summary:compute                          # nightly default
 *   php artisan customer-summary:compute --month=2026-04          # one specific month
 *   php artisan customer-summary:compute --from=2025-06 --to=2026-04 --sync
 *   php artisan customer-summary:compute --since-begin-date --sync   # full backfill
 *
 * "Settle all" — rebuild upstream gp_metrics first, then summaries:
 *   php artisan customer-summary:compute --since-begin-date --with-gp-metrics
 *   php artisan customer-summary:compute --from=2025-06 --to=2026-04 --with-gp-metrics
 */
class ComputeCustomerSummary extends Command
{
    protected $signature = 'customer-summary:compute
        {--month= : Process a specific month (YYYY-MM or YYYY-MM-DD)}
        {--from= : Range start month (YYYY-MM or YYYY-MM-DD)}
        {--to= : End month (YYYY-MM or YYYY-MM-DD)}
        {--since-begin-date : Process every month from min(customers.begin_date) to current}
        {--as-of= : Cap the aggregation at this date (YYYY-MM-DD); default = yesterday}
        {--sync : Run synchronously instead of queueing}
        {--queue= : Override queue name (low|default|high). Defaults: backfill=low, nightly=default}
        {--with-gp-metrics : Also rebuild gp_metrics for the range BEFORE the summary (chained per month)}
        {--gp-chunk=1000 : Chunk size for gp_metrics inserts when --with-gp-metrics is used}';

    protected $description = 'Aggregate customer_period_summaries from gp_metrics + customer contract details';

    public function handle(): int
    {
        $asOf = $this->option('as-of') ? Carbon::parse($this->option('as-of')) : Carbon::today()->subDay();

        [$rangeStart, $rangeEnd, $isBackfill] = $this->determineRange();
        if (!$rangeStart || !$rangeEnd) {
            $this->error('No range determined. Specify --month, --from/--to, or --since-begin-date.');
            return self::FAILURE;
        }
        if ($rangeStart->gt($rangeEnd)) {
            $this->error('Invalid range: from > to.');
            return self::FAILURE;
        }

        // Backfill ranges (any explicit window) go on the "low" queue so they
        // don't compete with realtime/default jobs. The nightly run (no range
        // options) stays on "default" so the morning view is fresh.
        // Explicit --queue= always wins.
        $queue = $this->option('queue')
            ?: ($isBackfill ? 'low' : 'default');

        $withGpMetrics = (bool) $this->option('with-gp-metrics');
        $gpChunk = (int) ($this->option('gp-chunk') ?: 1000);

        $this->info(sprintf(
            'Customer Summary aggregation: %s → %s (as_of=%s, sync=%s, queue=%s, backfill=%s, with_gp_metrics=%s)',
            $rangeStart->format('Y-m'),
            $rangeEnd->format('Y-m'),
            $asOf->toDateString(),
            $this->option('sync') ? 'yes' : 'no',
            $queue,
            $isBackfill ? 'yes' : 'no',
            $withGpMetrics ? 'yes' : 'no'
        ));

        // --with-gp-metrics + --sync: run everything inline. Days within a
        // month process before that month's summary; months are sequential.
        if ($withGpMetrics && $this->option('sync')) {
            $cursor = $rangeStart->copy();
            while ($cursor->lte($rangeEnd)) {
                $monthStart = $cursor->copy()->startOfMonth();
                $monthEnd = $cursor->copy()->endOfMonth()->startOfDay();
                // Cap at as-of for the in-progress month.
                if ($monthEnd->gt($asOf)) {
                    $monthEnd = $asOf->copy();
                }
                $day = $monthStart->copy();
                while ($day->lte($monthEnd)) {
                    $this->info(' - sync gp_metrics ' . $day->toDateString());
                    \App\Services\GpMetricsAggregator::persistDay($day->copy(), $gpChunk);
                    $day->addDay();
                }
                $this->info(' - sync customer_summary ' . $cursor->format('Y-m'));
                CustomerSummaryAggregator::persistMonth($cursor->copy(), $asOf);
                $cursor->addMonthNoOverflow();
            }
            $this->info('Done.');
            return self::SUCCESS;
        }

        $cursor = $rangeStart->copy();
        while ($cursor->lte($rangeEnd)) {
            if ($this->option('sync')) {
                $this->info(' - sync ' . $cursor->format('Y-m'));
                CustomerSummaryAggregator::persistMonth($cursor->copy(), $asOf);
            } elseif ($withGpMetrics) {
                $this->dispatchMonthChain($cursor->copy(), $asOf, $queue, $gpChunk);
            } else {
                $this->info(' - queue:' . $queue . ' ' . $cursor->format('Y-m'));
                ProcessCustomerSummaryMonth::dispatch(
                    $cursor->copy()->startOfMonth()->toDateString(),
                    $asOf->toDateString()
                )->onQueue($queue);
            }
            $cursor->addMonthNoOverflow();
        }

        $this->info('Done.');
        return self::SUCCESS;
    }

    /**
     * Per-month dispatcher for the --with-gp-metrics flag.
     *
     *   Bus::chain([
     *      ProcessGpMetricsRange(month_start → month_end),   // 1 job, processes all days inline
     *      ProcessCustomerSummaryMonth(month),                // 1 job, depends on the range above
     *   ])
     *
     * Why one range job per month instead of N day jobs:
     *   - Each month uses ONE worker + ONE MySQL connection for the whole
     *     range, instead of N short-lived workers each opening a new
     *     connection. Way lighter on the connection pool — critical given
     *     the recent SQLSTATE 1040 incident.
     *   - Far fewer Redis round-trips (1 dispatch vs ~30). Less queue
     *     bookkeeping, fewer "pending → reserved → completed" cycles.
     *   - Larger insert chunks per call inside the same execution context.
     *   - Months still run in PARALLEL via separate chains, capped by the
     *     low-queue's maxProcesses (currently 4).
     *
     * For an N-month backfill on 4 low workers: roughly N/4 "waves", each
     * wave = the time it takes one worker to grind through one month.
     */
    protected function dispatchMonthChain(Carbon $month, Carbon $asOf, string $queue, int $gpChunk): void
    {
        $monthStart = $month->copy()->startOfMonth();
        $monthEnd = $month->copy()->endOfMonth()->startOfDay();
        if ($monthEnd->gt($asOf)) {
            $monthEnd = $asOf->copy();
        }

        $rangeJob = new ProcessGpMetricsRange(
            $monthStart->toDateString(),
            $monthEnd->toDateString(),
            $gpChunk
        );

        $summaryJob = new ProcessCustomerSummaryMonth(
            $monthStart->toDateString(),
            $asOf->toDateString()
        );

        $dayCount = $monthStart->diffInDays($monthEnd) + 1;

        $this->info(sprintf(
            ' - queue:%s chain %s (range %d days → summary)',
            $queue,
            $month->format('Y-m'),
            $dayCount
        ));

        Bus::chain([$rangeJob, $summaryJob])
            ->onQueue($queue)
            ->dispatch();
    }

    /**
     * @return array{0:?\Carbon\Carbon,1:?\Carbon\Carbon,2:bool}  start, end, isBackfill
     */
    protected function determineRange(): array
    {
        if ($this->option('month')) {
            $m = Carbon::parse($this->option('month'))->startOfMonth();
            return [$m, $m->copy(), true];
        }

        if ($this->option('since-begin-date')) {
            $earliest = \App\Models\Customer::query()
                ->withoutGlobalScopes()
                ->whereNotNull('begin_date')
                ->min('begin_date');
            if (!$earliest) {
                return [null, null, true];
            }
            return [
                Carbon::parse($earliest)->startOfMonth(),
                Carbon::today()->startOfMonth(),
                true,
            ];
        }

        if ($this->option('from') || $this->option('to')) {
            $from = $this->option('from') ? Carbon::parse($this->option('from'))->startOfMonth() : Carbon::today()->startOfMonth();
            $to = $this->option('to') ? Carbon::parse($this->option('to'))->startOfMonth() : Carbon::today()->startOfMonth();
            return [$from, $to, true];
        }

        // Default: the month containing yesterday (matches the nightly schedule).
        // Plus if today == 1st, also re-stamp the just-finished previous month.
        $yesterday = Carbon::today()->subDay();
        $start = $yesterday->copy()->startOfMonth();
        $end = Carbon::today()->startOfMonth();
        return [$start, $end, false]; // nightly default → not a backfill
    }
}
