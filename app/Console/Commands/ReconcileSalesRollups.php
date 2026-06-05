<?php

namespace App\Console\Commands;

use App\Jobs\ProcessGpMetricsDay;
use App\Jobs\StoreVendProductRecords;
use App\Jobs\StoreVendsRecord;
use App\Jobs\Vend\SyncVendTransactionTotalsJson;
use App\Models\Vend;
use App\Models\VendTransaction;
use App\Services\GpMetricsAggregator;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

/**
 * Reconcile the two sales rollups against their single source of truth.
 *
 * `vend_records` (built by StoreVendsRecord) and `gp_metrics` (built by
 * GpMetricsAggregator) are deterministic daily aggregates of `vend_transactions`.
 * They are exact at build time, but drift when a transaction for an
 * already-rolled-up date changes afterward — late gateway settlement, a backdated
 * upload, a cost/revenue backfill, a customer/vend reassignment, or a deletion.
 *
 * For every day in the window this command recomputes the authoritative figure
 * straight from `vend_transactions` and compares it to what each rollup stored.
 * Days that don't tally are healed by re-dispatching the existing build jobs
 * (StoreVendsRecord / ProcessGpMetricsDay) — the build logic itself is untouched.
 *
 * Performance: the per-day figures are gathered with ONE grouped scan per table
 * (four queries total), not four queries per day, so even a wide window is a
 * single indexed range scan of `vend_transactions` (on the transaction_datetime
 * index) plus two tiny reads of the small rollup tables. The only per-day work is
 * the exact gp re-aggregation, and that runs solely for the rare day whose cheap
 * pre-check is already off — so normal nights touch it zero times.
 *
 *   vend_records : compared to an exact replica of the builder's own arithmetic
 *                  (plain SUM of vt.amount with the same success/settled rule and
 *                  the same vends inner-join), so there are no rounding artefacts.
 *
 *   gp_metrics   : a cheap SUM(vt.amount) pre-check first; only days that look off
 *                  are re-confirmed with a like-for-like fresh aggregation (the
 *                  exact buildRawQuery() the nightly job stores). That second pass
 *                  uses the identical formula, so multi-basket cent-rounding can
 *                  never masquerade as drift and trigger an endless heal loop.
 */
class ReconcileSalesRollups extends Command
{
    protected $signature = 'reconcile:sales-rollups
        {--from= : Start date YYYY-MM-DD (defaults to --days back from yesterday)}
        {--to= : End date YYYY-MM-DD (defaults to yesterday)}
        {--days=14 : Trailing days to scan when --from/--to are not supplied}
        {--tolerance=0 : Cents of allowed difference per day before a day counts as drifted}
        {--gp-precheck=50 : Cents the cheap gp pre-check may differ before the exact re-check runs (absorbs multi-basket rounding; perf guard only)}
        {--dry-run : Report drifted days only; do not dispatch any rebuilds}
        {--skip-cascade : Heal only the base rollups; skip the totals-JSON + Site Summary refresh}
        {--chunk=1000 : Chunk size passed to the gp_metrics rebuild}
        {--queue=low : Queue used for dispatched rebuild jobs}';

    protected $description = 'Verify vend_records and gp_metrics tally to vend_transactions per day, and auto-heal drifted days.';

    public function handle(): int
    {
        [$start, $end] = $this->resolveRange();
        $tolerance = max(0, (int) $this->option('tolerance'));
        // The cheap pre-check may legitimately differ from the stored gp total by a
        // few cents of multi-basket redistribution rounding; only differences beyond
        // this allowance are worth the exact (more expensive) re-aggregation. Never
        // smaller than the real drift tolerance.
        $gpPrecheck = max($tolerance, (int) $this->option('gp-precheck'));
        $dryRun = (bool) $this->option('dry-run');
        $chunk = max(1, (int) $this->option('chunk'));
        $queue = (string) $this->option('queue') ?: 'low';

        $this->info(sprintf(
            'Reconciling sales rollups %s..%s (tolerance=%dc, mode=%s)',
            $start->toDateString(),
            $end->toDateString(),
            $tolerance,
            $dryRun ? 'dry-run' : 'auto-heal'
        ));

        // One grouped scan per table — built up front so the day loop is pure
        // in-memory comparison (no per-day queries).
        $vrControl = $this->controlVendRecordsMap($start, $end);
        $vrStored = $this->storedVendRecordsMap($start, $end);
        $gpControl = $this->controlGpMetricsMap($start, $end);
        $gpStored = $this->storedGpMetricsMap($start, $end);

        $rows = [];
        $vrHealDays = [];
        $gpHealDays = [];

        for ($day = $start->copy(); $day->lte($end); $day->addDay()) {
            $d = $day->toDateString();

            // ---- vend_records: exact replica control, no rounding ----
            $vrc = $vrControl[$d] ?? 0;
            $vrs = $vrStored[$d] ?? 0;
            if (abs($vrc - $vrs) > $tolerance) {
                $vrHealDays[] = $d;
                $rows[] = ['vend_records', $d, $this->money($vrc), $this->money($vrs), $this->signed($vrc - $vrs)];
            }

            // ---- gp_metrics: cheap pre-check, confirm suspects like-for-like ----
            $gps = $gpStored[$d] ?? 0;
            if (abs(($gpControl[$d] ?? 0) - $gps) > $gpPrecheck) {
                // Re-confirm with the exact formula the builder stores, so a
                // redistribution rounding gap is not mistaken for real drift.
                $gpf = $this->gpMetricsFreshCents($day);
                if (abs($gpf - $gps) > $tolerance) {
                    $gpHealDays[] = $d;
                    $rows[] = ['gp_metrics', $d, $this->money($gpf), $this->money($gps), $this->signed($gpf - $gps)];
                }
            }
        }

        if (empty($rows)) {
            $this->info('All days tally to vend_transactions. Nothing to heal.');
            return self::SUCCESS;
        }

        $this->table(['Rollup', 'Date', 'Transactions ($)', 'Stored ($)', 'Diff ($)'], $rows);
        $this->warn(sprintf('Drifted: %d vend_records day(s), %d gp_metrics day(s).', count($vrHealDays), count($gpHealDays)));

        if ($dryRun) {
            $this->line('Dry-run: no rebuilds dispatched. Re-run without --dry-run to heal.');
            return self::SUCCESS;
        }

        // Heal the three day-level rollups in lockstep for every drifted day, so
        // every report that reads them (Dashboard, Sales Report, Ops Performance,
        // product report) stays consistent. All idempotent; all on the low queue.
        $healDays = array_values(array_unique(array_merge($vrHealDays, $gpHealDays)));
        foreach ($healDays as $d) {
            StoreVendsRecord::dispatch($d, $d, true)->onQueue($queue);
            ProcessGpMetricsDay::dispatch($d, $chunk)->onQueue($queue);
            StoreVendProductRecords::dispatch($d, $d)->onQueue($queue);
        }

        $this->info(sprintf(
            'Dispatched base-rollup rebuilds for %d day(s) on queue:%s.',
            count($healDays),
            $queue
        ));

        if (! $this->option('skip-cascade')) {
            $this->cascadeDownstream($healDays, $queue);
        }

        return self::SUCCESS;
    }

    /**
     * Resync the cached/derived layers that read the healed days, EXCEPT locked
     * Site Summaries — customer-summary:compute preserves locked rows (no
     * --force-single-row), so frozen historical Site Summaries are never rewritten.
     * Frozen ops machine snapshots are left alone too (their financials already read
     * gp_metrics live). Everything dispatches to the low queue.
     */
    private function cascadeDownstream(array $healDays, string $queue): void
    {
        if (empty($healDays)) {
            return;
        }

        // 1) Refresh cached totals JSON for the vends touched on the healed days
        //    (the job also refreshes each vend's customer; it is unique-coalesced).
        $vendIds = $this->vendIdsTouchedOn($healDays);
        if ($vendIds->isNotEmpty()) {
            Vend::whereIn('id', $vendIds)->get()->each(function ($vend) use ($queue) {
                SyncVendTransactionTotalsJson::dispatch($vend)->onQueue($queue);
            });
        }

        // 2) Recompute unlocked Site (customer) summaries for each affected month.
        //    Locked rows stay frozen (the command skips them by default).
        $months = collect($healDays)->map(fn ($d) => substr($d, 0, 7))->unique()->values();
        foreach ($months as $month) {
            Artisan::call('customer-summary:compute', [
                '--month' => $month,
                '--queue' => $queue,
            ]);
        }

        $this->info(sprintf(
            'Cascade: refreshed totals JSON for %d vend(s); recomputed unlocked Site Summaries for %d month(s).',
            $vendIds->count(),
            $months->count()
        ));
    }

    /**
     * Distinct vend ids that had a transaction on any of the given days. Bounded by
     * a transaction_datetime range (uses the index) before the per-day DATE filter.
     */
    private function vendIdsTouchedOn(array $days)
    {
        $min = min($days) . ' 00:00:00';
        $max = max($days) . ' 23:59:59';

        return DB::table('vend_transactions')
            ->whereBetween('transaction_datetime', [$min, $max])
            ->whereIn(DB::raw('DATE(transaction_datetime)'), $days)
            ->distinct()
            ->pluck('vend_id')
            ->filter()
            ->values();
    }

    /**
     * @return array{0:Carbon,1:Carbon}
     */
    private function resolveRange(): array
    {
        $from = $this->option('from');
        $to = $this->option('to');

        if ($from || $to) {
            $start = $from ? Carbon::parse($from)->startOfDay() : Carbon::yesterday()->startOfDay();
            $end = $to ? Carbon::parse($to)->startOfDay() : Carbon::yesterday()->startOfDay();
            if ($start->gt($end)) {
                [$start, $end] = [$end, $start];
            }

            return [$start, $end];
        }

        $days = max(1, (int) $this->option('days'));
        $end = Carbon::yesterday()->startOfDay();
        $start = $end->copy()->subDays($days - 1);

        return [$start, $end];
    }

    /**
     * Authoritative vend_records sales per day, replicating StoreVendsRecord's own
     * arithmetic exactly: amount>0, SETTLED, (multi OR non-error single), inner-
     * joined to vends (orphan-vend rows are excluded by the builder too). One
     * grouped, index-ranged scan; bypasses model global scopes (no auth in console).
     *
     * @return array<string,int> ['Y-m-d' => cents]
     */
    private function controlVendRecordsMap(Carbon $start, Carbon $end): array
    {
        return DB::table('vend_transactions as vt')
            ->join('vends as v', 'vt.vend_id', '=', 'v.id')
            ->leftJoin('vend_channel_errors as vce', 'vt.vend_channel_error_id', '=', 'vce.id')
            ->whereBetween('vt.transaction_datetime', [$start->copy()->startOfDay(), $end->copy()->endOfDay()])
            ->where('vt.amount', '>', 0)
            ->where('vt.settlement_status', VendTransaction::SETTLEMENT_SETTLED)
            ->where(function ($q) {
                $q->where('vt.is_multiple', true)
                    ->orWhereNull('vt.vend_channel_error_id')
                    ->orWhereIn('vce.code', [0, 6]);
            })
            ->groupBy(DB::raw('DATE(vt.transaction_datetime)'))
            ->selectRaw('DATE(vt.transaction_datetime) as d, SUM(vt.amount) as c')
            ->pluck('c', 'd')
            ->map(fn ($v) => (int) $v)
            ->all();
    }

    /**
     * @return array<string,int> ['Y-m-d' => cents]
     */
    private function storedVendRecordsMap(Carbon $start, Carbon $end): array
    {
        return DB::table('vend_records')
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->groupBy(DB::raw('DATE(date)'))
            ->selectRaw('DATE(date) as d, SUM(total_amount) as c')
            ->pluck('c', 'd')
            ->map(fn ($v) => (int) $v)
            ->all();
    }

    /**
     * Cheap gp_metrics pre-check control per day. Mirrors the success/settled rule
     * and the builder's date attribution (transaction_datetime, falling back to
     * created_at when null). No vends join — gp_metrics keeps orphan-vend rows.
     * One grouped scan.
     *
     * @return array<string,int> ['Y-m-d' => cents]
     */
    private function controlGpMetricsMap(Carbon $start, Carbon $end): array
    {
        $s = $start->copy()->startOfDay();
        $e = $end->copy()->endOfDay();

        return DB::table('vend_transactions as vt')
            ->leftJoin('vend_channel_errors as vce', 'vt.vend_channel_error_id', '=', 'vce.id')
            ->where('vt.amount', '>', 0)
            ->where('vt.settlement_status', VendTransaction::SETTLEMENT_SETTLED)
            ->where(function ($q) {
                $q->where('vt.is_multiple', true)
                    ->orWhereNull('vt.vend_channel_error_id')
                    ->orWhereIn('vce.code', [0, 6]);
            })
            ->where(function ($q) use ($s, $e) {
                $q->whereBetween('vt.transaction_datetime', [$s, $e])
                    ->orWhere(function ($or) use ($s, $e) {
                        $or->whereNull('vt.transaction_datetime')
                            ->whereBetween('vt.created_at', [$s, $e]);
                    });
            })
            ->groupBy(DB::raw('DATE(COALESCE(vt.transaction_datetime, vt.created_at))'))
            ->selectRaw('DATE(COALESCE(vt.transaction_datetime, vt.created_at)) as d, SUM(vt.amount) as c')
            ->pluck('c', 'd')
            ->map(fn ($v) => (int) $v)
            ->all();
    }

    /**
     * @return array<string,int> ['Y-m-d' => cents]
     */
    private function storedGpMetricsMap(Carbon $start, Carbon $end): array
    {
        return DB::table('gp_metrics')
            ->whereBetween('txn_date', [$start->toDateString(), $end->toDateString()])
            ->groupBy(DB::raw('DATE(txn_date)'))
            ->selectRaw('DATE(txn_date) as d, SUM(amount_cents) as c')
            ->pluck('c', 'd')
            ->map(fn ($v) => (int) $v)
            ->all();
    }

    /**
     * Like-for-like confirmation for a single suspect day: the exact aggregation
     * the nightly job persists, summed in SQL. Equal to the stored total unless the
     * underlying transactions changed since the last build. Runs rarely.
     */
    private function gpMetricsFreshCents(Carbon $day): int
    {
        $sub = GpMetricsAggregator::buildRawQuery($day->copy()->startOfDay(), $day->copy()->endOfDay());

        return (int) DB::query()->fromSub($sub, 'metrics')->sum('amount_cents');
    }

    private function money(int $cents): string
    {
        return number_format($cents / 100, 2);
    }

    private function signed(int $cents): string
    {
        return ($cents >= 0 ? '+' : '') . number_format($cents / 100, 2);
    }
}
