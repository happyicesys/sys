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
use Illuminate\Support\Facades\Storage;

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
        {--gp-chunk=1000 : Chunk size for gp_metrics inserts when --with-gp-metrics is used}
        {--chunk= : Dispatch at most N months per invocation, save progress, resume on next run. Default 200.}
        {--reset : Wipe the saved progress watermark for this range (start over from rangeStart).}
        {--force-single-row : One-off backfill mode. Skips mid-month contract segmentation, IGNORES locked rows (overwrites them), and stamps segmentation_overridden=true on every inserted row so future nightly runs leave them merged. --sync only.}';

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
        $forceSingleRow = (bool) $this->option('force-single-row');

        // --force-single-row is destructive (it wipes locked rows in range) and
        // only applies through the sync paths below. Refuse to silently no-op
        // when paired with the queued path.
        if ($forceSingleRow && !$this->option('sync')) {
            $this->error('--force-single-row requires --sync (the queued path does not support it).');
            return self::FAILURE;
        }

        $this->info(sprintf(
            'Customer Summary aggregation: %s → %s (as_of=%s, sync=%s, queue=%s, backfill=%s, with_gp_metrics=%s, force_single_row=%s)',
            $rangeStart->format('Y-m'),
            $rangeEnd->format('Y-m'),
            $asOf->toDateString(),
            $this->option('sync') ? 'yes' : 'no',
            $queue,
            $isBackfill ? 'yes' : 'no',
            $withGpMetrics ? 'yes' : 'no',
            $forceSingleRow ? 'yes' : 'no'
        ));

        // Force-single-row blows away locked rows for every customer in every
        // month of the range. Surface this loudly and ask for confirmation,
        // unless --no-interaction is passed.
        if ($forceSingleRow) {
            $this->warn(sprintf(
                '⚠  --force-single-row will WIPE every customer_period_summaries row from %s to %s (INCLUDING locked rows) and rebuild them from each customer\'s CURRENT contract values.',
                $rangeStart->format('Y-m'),
                $rangeEnd->format('Y-m')
            ));
            if (!$this->confirm('Continue?', false)) {
                $this->comment('Aborted.');
                return self::SUCCESS;
            }
        }

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
                CustomerSummaryAggregator::persistMonth($cursor->copy(), $asOf, $forceSingleRow);
                $cursor->addMonthNoOverflow();
            }
            $this->info('Done.');
            return self::SUCCESS;
        }

        // ─────────────────────────────────────────────────────────────
        // Synchronous mode: process months inline, no queue.
        // ─────────────────────────────────────────────────────────────
        if ($this->option('sync')) {
            $cursor = $rangeStart->copy();
            while ($cursor->lte($rangeEnd)) {
                $this->info(' - sync ' . $cursor->format('Y-m'));
                CustomerSummaryAggregator::persistMonth($cursor->copy(), $asOf, $forceSingleRow);
                $cursor->addMonthNoOverflow();
            }
            $this->info('Done.');
            return self::SUCCESS;
        }

        // ─────────────────────────────────────────────────────────────
        // Queued mode: chunked, resumable dispatch.
        //
        // Each invocation dispatches at most --chunk=N months (default 200)
        // and persists a watermark in storage/app/customer-summary-compute-progress.json.
        // Running the same command again picks up from where the previous run
        // left off, so a multi-thousand-month backfill can be paced by the
        // operator: dispatch a chunk → watch the queue drain → run again →
        // dispatch the next chunk → etc.
        //
        // The watermark is keyed by the resolved range so different ranges
        // (e.g. --since-begin-date vs --from=2025-06 --to=2025-12) keep
        // independent progress. Pass --reset to wipe the watermark and
        // start over from rangeStart.
        // ─────────────────────────────────────────────────────────────

        $chunkSize = (int) ($this->option('chunk') ?: 200);
        if ($chunkSize < 1) {
            $this->error('--chunk must be >= 1.');
            return self::FAILURE;
        }

        $progressKey = $this->progressKey($rangeStart, $rangeEnd, $withGpMetrics);
        if ($this->option('reset')) {
            $this->forgetProgress($progressKey);
            $this->info("Watermark cleared for range {$rangeStart->format('Y-m')} → {$rangeEnd->format('Y-m')}.");
        }

        $progress = $this->readProgress($progressKey);
        // Resume cursor: either the saved watermark, or rangeStart on a fresh run.
        $cursor = $progress['next_month'] ?? null
            ? Carbon::parse($progress['next_month'])->startOfMonth()
            : $rangeStart->copy();

        if ($cursor->lt($rangeStart)) {
            // Watermark is older than current rangeStart (e.g. range narrowed).
            // Trust the explicit rangeStart.
            $cursor = $rangeStart->copy();
        }

        if ($cursor->gt($rangeEnd)) {
            $this->info('Already past range end — nothing to dispatch. Pass --reset to restart from ' . $rangeStart->format('Y-m') . '.');
            return self::SUCCESS;
        }

        // Total months across the whole range, just for the progress display.
        $totalMonths = $rangeStart->copy()->diffInMonths($rangeEnd->copy()) + 1;
        // Months remaining = from cursor to rangeEnd inclusive.
        $remaining = $cursor->copy()->diffInMonths($rangeEnd->copy()) + 1;
        $dispatchCount = min($chunkSize, $remaining);

        $dispatchedMonths = [];
        for ($i = 0; $i < $dispatchCount; $i++) {
            $monthStart = $cursor->copy()->startOfMonth();
            $monthEnd = $cursor->copy()->endOfMonth()->startOfDay();
            if ($monthEnd->gt($asOf)) {
                $monthEnd = $asOf->copy();
            }

            if ($withGpMetrics) {
                // Range → summary as a Bus::chain, so the gp_metrics rebuild
                // for the month finishes before its summary recomputes.
                Bus::chain([
                    new ProcessGpMetricsRange(
                        $monthStart->toDateString(),
                        $monthEnd->toDateString(),
                        $gpChunk
                    ),
                    new ProcessCustomerSummaryMonth(
                        $monthStart->toDateString(),
                        $asOf->toDateString()
                    ),
                ])->onQueue($queue)->dispatch();
            } else {
                ProcessCustomerSummaryMonth::dispatch(
                    $monthStart->toDateString(),
                    $asOf->toDateString()
                )->onQueue($queue);
            }

            $dispatchedMonths[] = $cursor->format('Y-m');
            $cursor->addMonthNoOverflow();
        }

        // Advance watermark. If we just finished the range, mark complete.
        $nextMonth = $cursor->lte($rangeEnd) ? $cursor->format('Y-m-d') : null;
        $this->writeProgress($progressKey, [
            'range_start' => $rangeStart->toDateString(),
            'range_end' => $rangeEnd->toDateString(),
            'next_month' => $nextMonth,
            'total_months' => $totalMonths,
            'dispatched_so_far' => ($progress['dispatched_so_far'] ?? 0) + count($dispatchedMonths),
            'updated_at' => Carbon::now()->toIso8601String(),
            'with_gp_metrics' => $withGpMetrics,
        ]);

        $dispatchedTotal = ($progress['dispatched_so_far'] ?? 0) + count($dispatchedMonths);
        $this->info(sprintf(
            'Dispatched %d month(s) on queue=%s: %s → %s. Progress: %d/%d.',
            count($dispatchedMonths),
            $queue,
            $dispatchedMonths[0] ?? '-',
            end($dispatchedMonths) ?: '-',
            $dispatchedTotal,
            $totalMonths
        ));

        if ($nextMonth === null) {
            $this->info('All months in range dispatched. Watermark cleared.');
            $this->forgetProgress($progressKey);
        } else {
            $this->info(sprintf(
                'Next month to dispatch: %s. Re-run the same command to dispatch the next %d months.',
                Carbon::parse($nextMonth)->format('Y-m'),
                $chunkSize
            ));
        }

        return self::SUCCESS;
    }

    /**
     * Stable scope key for the watermark file. Different ranges keep
     * independent progress.
     */
    protected function progressKey(Carbon $rangeStart, Carbon $rangeEnd, bool $withGpMetrics): string
    {
        return sprintf(
            '%s_%s%s',
            $rangeStart->format('Y-m'),
            $rangeEnd->format('Y-m'),
            $withGpMetrics ? '_gp' : ''
        );
    }

    /**
     * Watermark file path. Uses Laravel's "local" filesystem (storage/app/)
     * so it persists across deploys but stays out of the public asset tree.
     */
    protected function progressFile(): string
    {
        return 'customer-summary-compute-progress.json';
    }

    /**
     * Read the persisted watermark for a scope. Returns [] when unset.
     *
     * @return array<string, mixed>
     */
    protected function readProgress(string $scopeKey): array
    {
        $path = $this->progressFile();
        if (!Storage::disk('local')->exists($path)) {
            return [];
        }
        $raw = Storage::disk('local')->get($path);
        $all = json_decode($raw, true);
        if (!is_array($all) || !isset($all[$scopeKey]) || !is_array($all[$scopeKey])) {
            return [];
        }
        return $all[$scopeKey];
    }

    protected function writeProgress(string $scopeKey, array $data): void
    {
        $path = $this->progressFile();
        $all = [];
        if (Storage::disk('local')->exists($path)) {
            $decoded = json_decode(Storage::disk('local')->get($path), true);
            if (is_array($decoded)) {
                $all = $decoded;
            }
        }
        $all[$scopeKey] = $data;
        Storage::disk('local')->put($path, json_encode($all, JSON_PRETTY_PRINT));
    }

    protected function forgetProgress(string $scopeKey): void
    {
        $path = $this->progressFile();
        if (!Storage::disk('local')->exists($path)) {
            return;
        }
        $decoded = json_decode(Storage::disk('local')->get($path), true);
        if (!is_array($decoded)) {
            return;
        }
        unset($decoded[$scopeKey]);
        if (empty($decoded)) {
            Storage::disk('local')->delete($path);
        } else {
            Storage::disk('local')->put($path, json_encode($decoded, JSON_PRETTY_PRINT));
        }
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
            // Clamp the genesis backfill forward to the app-wide reporting floor
            // so we never generate (incomplete) pre-genesis summary rows. The
            // Summary display already hides anything before the floor — see
            // CustomerController::summaryFloorDate().
            $start = Carbon::parse($earliest)->startOfMonth();
            $floor = Carbon::parse(\App\Http\Controllers\CustomerController::summaryFloorDate())->startOfMonth();
            if ($start->lt($floor)) {
                $start = $floor;
            }
            return [
                $start,
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
