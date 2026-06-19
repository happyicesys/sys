<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\GpMetric;
use App\Models\Operator;
use App\Models\OpsMachineDailySnapshot;
use App\Models\Vend;
use App\Services\CustomerSummaryAggregator;
use App\Services\OpsMachineDailySnapshotBuilder;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

/**
 * Operations > Ops Performance — Phase 1 only (Key KPI + Machines' Status &
 * Component). Phases 2-5 from the spec are intentionally not built here.
 *
 * Two data domains, both honouring the same filter set:
 *   - Financial / KPI rows -> derived live from gp_metrics (every dimension +
 *     full history, so filters work retroactively).
 *   - Machines' Status & Component -> from the per-machine snapshot
 *     (ops_machine_daily_snapshots), with a live fallback off `vends` for the
 *     anchor day when the nightly job hasn't frozen it yet.
 *
 * Columns are relative to an anchor date (date_to, default yesterday): Avg
 * L7d / L30d, the anchor day + 7 prior, and This Mth / L1m-L3m.
 *
 * The three "higher than" rows are per-machine counts (machines meeting the
 * condition, with % of active machines), per the spec. The two daily ones are
 * computed for every day column (each day evaluated "as of" that day); the
 * monthly one shows under This Mth only. Their definitions:
 *   - L30d sales higher than LastMth: machine's trailing-30d sales vs its full
 *     previous-calendar-month sales.
 *   - Current Mth sales higher than Previous Mth: current MTD vs the same span
 *     of the previous month.
 *   - Avg Daily Sales L30d >= Overall Avg/day: machine's L30d avg/day vs the
 *     fleet-wide mean of all machines' L30d avg/day (the same definition the
 *     CustomerIndex "% of VM, Avg Daily Sales L30D >= Avg/Day" card uses, so
 *     the two readings agree).
 */
class OpsPerformanceController extends Controller
{
    private const DAY_COLUMNS = 8;

    /**
     * Net VendEarning figures, computed the same way the rest of the app does
     * (gross − location fee), used to OVERRIDE the gross-based KPI rows so
     * "VendEarning" on this page means the operator's net share — matching the
     * Operation Dashboard (per machine, L30d) and Site Summary (per site, monthly).
     */
    private int $vendEarn30dCents = 0;

    /** @var array<string,int> monthColumn key => net VendEarning cents (Site Summary) */
    private array $monthlyVendEarnMap = [];

    /** @var array<string,int> snapshot_date => frozen L30d net VendEarning cents */
    private array $vendEarnByDate = [];

    private int $kpiActiveTotal = 0;

    public function index(Request $request)
    {
        return Inertia::render('Vend/OpsPerformance', $this->buildData($request));
    }

    public function export(Request $request)
    {
        $data = $this->buildData($request);
        $name = 'Ops_Performance_' . $data['anchorDate'] . '_' . now()->format('Ymd_His') . '.xlsx';

        return (new \App\Exports\OpsPerformanceExport($data))->download($name);
    }

    /**
     * Single source of truth for the page payload — used by both the Inertia
     * page and the Excel export so they never drift.
     */
    private function buildData(Request $request): array
    {
        $today = Carbon::today();
        $yesterday = $today->copy()->subDay();

        $anchor = $request->date_to ? Carbon::parse($request->date_to)->startOfDay() : $yesterday->copy();
        if ($anchor->gt($yesterday)) {
            $anchor = $yesterday->copy();
        }

        $rangeStart = $request->date_from
            ? Carbon::parse($request->date_from)->startOfDay()
            : $anchor->copy()->subMonthsNoOverflow(4)->startOfMonth();
        if ($rangeStart->gt($anchor)) {
            $rangeStart = $anchor->copy()->subMonthsNoOverflow(4)->startOfMonth();
        }

        // ---- filters ----
        // Operator defaults mirror the Dashboard: when nothing is selected, scope
        // to the user's own operator (HIPL expands to its operator group).
        $operatorIds = $this->ids($request->operators);
        if (empty($operatorIds)) {
            $operatorIds = $this->defaultOperatorIds();
        }

        // Site (customer) status: defaults to Active on first load. An explicit
        // 'all' selection clears the constraint so every site is included.
        [$siteStatusIds, $siteStatusSelected] = $this->resolveSiteStatuses($request->site_statuses);

        $f = [
            'operatorIds' => $operatorIds,
            'locationTypeIds' => $this->ids($request->location_type_ids),
            'vendPrefixIds' => $this->ids($request->vend_prefix_ids),
            'vendModelIds' => $this->ids($request->vend_model_ids),
            'categoryIds' => $this->ids($request->category_ids),
            'vendIds' => $this->resolveVendIds($request->codes),
            'customerIds' => $this->resolveCustomerIds($request->customer),
            'siteStatusIds' => $siteStatusIds,
            // Exclude test rigs from the financial/qty rows so they reconcile
            // with the Transactions page (which also drops testing vends).
            'testingVendIds' => Cache::remember('testing_vend_ids', 3600, fn () => DB::table('vends')->where('is_testing', true)->pluck('id')->all()),
        ];
        $statuses = array_values(array_filter((array) $request->statuses));

        $dayColumns = $this->dayColumns($anchor);
        $monthColumns = $this->monthColumns($anchor);
        $anchorKey = $anchor->toDateString();

        // ---- financials + active counts ----
        $finByDate = $this->financialsByDate($rangeStart, $anchor, $f);
        $activeByDate = $this->activeCountsByDate($rangeStart, $anchor, $f);
        $activeTotal = $this->activeTotal($activeByDate, $anchorKey);

        // ---- per-machine "higher than" counts (spec rows 13/16/17) ----
        $monthStart = $anchor->copy()->startOfMonth();
        $prevMonthStart = $monthStart->copy()->subMonthNoOverflow();
        $prevSameSpanEnd = $prevMonthStart->copy()->addDays($monthStart->diffInDays($anchor));
        $rangeStartStr = $rangeStart->toDateString();

        // The two daily comparison rows are computed for EVERY day column, each
        // day evaluated "as of" that day; the monthly one stays anchor-only.
        $compareSeries = $this->perMachineCompareSeries($f, $dayColumns, $activeByDate, $activeTotal, $rangeStartStr);

        $perMachine = [
            'l30d_vs_lastmth' => $compareSeries['l30d_vs_lastmth'],
            'curr_vs_prev' => $this->perMachineGreater($f, $prevMonthStart->toDateString(), $anchorKey, $monthStart->toDateString(), $anchorKey, $prevMonthStart->toDateString(), $prevSameSpanEnd->toDateString(), $activeTotal),
            'l30d_avg_vs_overall' => $compareSeries['l30d_avg_vs_overall'],
        ];

        // Net VendEarning (gross − location fee), computed from the same blessed
        // sources the rest of the app uses, ready to override the gross-based
        // VendEarning KPI rows inside buildKpis().
        $this->vendEarn30dCents = $this->thirtyDayVendEarningCents($f);
        $this->vendEarnByDate = $this->snapshotVendEarningByDate($f, $dayColumns);
        $this->monthlyVendEarnMap = $this->monthlyVendEarningMap($f, $monthColumns);
        $this->kpiActiveTotal = $activeTotal;

        $kpis = $this->buildKpis($finByDate, $activeByDate, $dayColumns, $monthColumns, $anchorKey, $perMachine);

        // ---- component section ----
        [$component, $componentIsLive] = $this->componentSection($anchor, $f, $statuses);

        return [
            'generatedAt' => now()->toDateTimeString(),
            'anchorDate' => $anchorKey,
            'totalMachines' => $activeTotal,
            'currency' => $this->currency(),
            'componentIsLive' => $componentIsLive,
            'hasData' => ! empty($finByDate) || $component !== null,
            'dayColumns' => $dayColumns,
            'monthColumns' => $monthColumns,
            'kpis' => $kpis,
            'component' => $component,
            'filterOptions' => $this->filterOptions(),
            'filters' => [
                'date_from' => $rangeStart->toDateString(),
                'date_to' => $anchorKey,
                'operators' => $f['operatorIds'],
                'location_type_ids' => $f['locationTypeIds'],
                'vend_prefix_ids' => $f['vendPrefixIds'],
                'vend_model_ids' => $f['vendModelIds'],
                'category_ids' => $f['categoryIds'],
                'statuses' => $statuses,
                'site_statuses' => $siteStatusSelected,
                'codes' => (string) $request->codes,
                'customer' => (string) $request->customer,
            ],
        ];
    }

    private function currency(): array
    {
        $country = optional(optional(auth()->user())->operator)->country;

        return [
            'symbol' => $country->currency_symbol ?? '',
            'exponent' => (int) ($country->currency_exponent ?? 2),
            'hidden' => (bool) ($country->is_currency_exponent_hidden ?? false),
        ];
    }

    // ===================== filters =====================

    private function ids($value): array
    {
        return array_values(array_filter(array_map('intval', (array) $value), fn ($v) => $v > 0));
    }

    private function resolveVendIds($codes): array
    {
        $list = collect(explode(',', (string) $codes))->map(fn ($c) => trim($c))->filter()->all();
        if (empty($list)) {
            return [];
        }
        $ids = DB::table('vends')->whereIn('code', $list)->pluck('id')->all();
        return empty($ids) ? [-1] : $ids;
    }

    private function resolveCustomerIds($search): array
    {
        $search = trim((string) $search);
        if ($search === '') {
            return [];
        }
        $ids = DB::table('customers')
            ->where(function ($q) use ($search) {
                $q->where('virtual_customer_code', 'LIKE', "{$search}%")
                    ->orWhere('code', 'LIKE', "{$search}%")
                    ->orWhere('name', 'LIKE', "%{$search}%");
            })
            ->pluck('id')->all();
        return empty($ids) ? [-1] : $ids;
    }

    private function filterOptions(): array
    {
        $opts = fn ($table) => DB::table($table)->select('id', 'name')->orderBy('name')->get();

        return [
            'operators' => $opts('operators'),
            'locationTypes' => DB::table('location_types')->select('id', 'name')->orderBy('sequence')->get(),
            'vendPrefixes' => $opts('vend_prefixes'),
            'vendModels' => $opts('vend_models'),
            'categories' => $opts('categories'),
            'statuses' => [
                ['id' => 'active', 'name' => 'Active'],
                ['id' => 'testing', 'name' => 'Testing'],
                ['id' => 'inactive', 'name' => 'Inactive'],
            ],
            'siteStatuses' => array_merge(
                [['id' => 'all', 'name' => 'All']],
                collect(Customer::STATUSES_MAPPING)
                    ->map(fn ($name, $id) => ['id' => (int) $id, 'name' => $name])
                    ->values()
                    ->all()
            ),
        ];
    }

    /**
     * Operator default mirrors DashboardController::setDefaultOperators — the
     * user's own operator, with HIPL expanded to its operator group.
     */
    private function defaultOperatorIds(): array
    {
        $user = auth()->user();

        if ($user->operator && $user->operator->code === 'HIPL') {
            $map = Operator::whereIn('code', ['HIMD', 'LEA', 'HIESG', 'UL-ST'])->pluck('id', 'code');

            return array_values(array_filter([
                $user->operator_id,
                $map->get('HIMD'),
                $map->get('LEA'),
                $map->get('HIESG'),
                $map->get('UL-ST'),
            ]));
        }

        return array_values(array_filter([$user->operator_id]));
    }

    /**
     * Resolve the Site (customer) status filter.
     *
     * @return array{0: int[], 1: (int|string)[]}  [statusIds applied, selection echoed to UI]
     *   - Missing/empty selection  -> default to Active + Removed.
     *   - Explicit 'all'           -> no constraint (every site).
     *   - Specific status ids      -> those statuses.
     */
    private function resolveSiteStatuses($raw): array
    {
        $raw = array_values(array_filter((array) $raw, fn ($v) => $v !== null && $v !== ''));

        if (empty($raw)) {
            return [[Customer::STATUS_ACTIVE, Customer::STATUS_REMOVED], [Customer::STATUS_ACTIVE, Customer::STATUS_REMOVED]];
        }

        if (in_array('all', $raw, true)) {
            return [[], ['all']];
        }

        $ids = array_values(array_filter(array_map('intval', $raw), fn ($v) => $v > 0));

        if (empty($ids)) {
            return [[Customer::STATUS_ACTIVE, Customer::STATUS_REMOVED], [Customer::STATUS_ACTIVE, Customer::STATUS_REMOVED]];
        }

        return [$ids, $ids];
    }

    /** Apply the dimension filters to a gp_metrics query. */
    private function applyGpFilters($q, array $f): void
    {
        $q->when($f['operatorIds'], fn ($q) => $q->whereIn('operator_id', $f['operatorIds']))
            ->when($f['locationTypeIds'], fn ($q) => $q->whereIn('customer_location_type_id', $f['locationTypeIds']))
            ->when($f['vendPrefixIds'], fn ($q) => $q->whereIn('vend_prefix_id', $f['vendPrefixIds']))
            ->when($f['vendModelIds'], fn ($q) => $q->whereIn('vend_model_id', $f['vendModelIds']))
            ->when($f['categoryIds'], fn ($q) => $q->whereIn('category_id', $f['categoryIds']))
            ->when($f['vendIds'], fn ($q) => $q->whereIn('vend_id', $f['vendIds']))
            ->when($f['customerIds'], fn ($q) => $q->whereIn('customer_id', $f['customerIds']))
            ->when($f['siteStatusIds'] ?? [], fn ($q) => $q->whereIn('customer_id', $this->siteStatusCustomerSub($f['siteStatusIds'])))
            ->when(! empty($f['testingVendIds']), fn ($q) => $q->whereNotIn('vend_id', $f['testingVendIds']));
    }

    /**
     * Sub-query of customer ids whose current status is in the given set. Used to
     * scope the Site Status filter on tables that carry a customer_id column.
     */
    private function siteStatusCustomerSub(array $statusIds)
    {
        return DB::table('customers')->select('id')->whereIn('status_id', $statusIds);
    }

    /** Apply the dimension filters to the per-machine snapshot table. */
    private function applySnapshotFilters($q, array $f): void
    {
        $q->when($f['operatorIds'], fn ($q) => $q->whereIn('operator_id', $f['operatorIds']))
            ->when($f['locationTypeIds'], fn ($q) => $q->whereIn('location_type_id', $f['locationTypeIds']))
            ->when($f['vendPrefixIds'], fn ($q) => $q->whereIn('vend_prefix_id', $f['vendPrefixIds']))
            ->when($f['vendModelIds'], fn ($q) => $q->whereIn('vend_model_id', $f['vendModelIds']))
            ->when($f['categoryIds'], fn ($q) => $q->whereIn('category_id', $f['categoryIds']))
            ->when($f['vendIds'], fn ($q) => $q->whereIn('vend_id', $f['vendIds']))
            ->when($f['customerIds'], fn ($q) => $q->whereIn('customer_id', $f['customerIds']))
            ->when($f['siteStatusIds'] ?? [], fn ($q) => $q->whereIn('customer_id', $this->siteStatusCustomerSub($f['siteStatusIds'])));
    }

    /** Apply the dimension filters to the live currentMachineQuery (vends). */
    private function applyLiveFilters($q, array $f): void
    {
        $q->when($f['operatorIds'], fn ($x) => $x->whereIn('vends.operator_id', $f['operatorIds']))
            ->when($f['locationTypeIds'], fn ($x) => $x->whereIn('customers.location_type_id', $f['locationTypeIds']))
            ->when($f['vendPrefixIds'], fn ($x) => $x->whereIn('vends.vend_prefix_id', $f['vendPrefixIds']))
            ->when($f['vendModelIds'], fn ($x) => $x->whereIn('vends.vend_model_id', $f['vendModelIds']))
            ->when($f['categoryIds'], fn ($x) => $x->whereIn('customers.category_id', $f['categoryIds']))
            ->when($f['vendIds'], fn ($x) => $x->whereIn('vends.id', $f['vendIds']))
            ->when($f['customerIds'], fn ($x) => $x->whereIn('vends.customer_id', $f['customerIds']))
            ->when($f['siteStatusIds'] ?? [], fn ($x) => $x->whereIn('customers.status_id', $f['siteStatusIds']));
    }

    // ===================== financials =====================

    private function financialsByDate($rangeStart, $anchor, array $f): array
    {
        $q = GpMetric::query()
            ->whereBetween('txn_date', [$rangeStart->toDateString(), $anchor->toDateString()]);
        $this->applyGpFilters($q, $f);

        $rows = $q->groupBy('txn_date')
            ->selectRaw('txn_date,
                COALESCE(SUM(amount_cents),0) AS amount_cents,
                COALESCE(SUM(revenue_cents),0) AS revenue_cents,
                COALESCE(SUM(gross_profit_cents),0) AS gross_profit_cents,
                COALESCE(SUM(unit_cost_cents),0) AS unit_cost_cents,
                COALESCE(SUM(sale_count),0) AS sale_count,
                COALESCE(SUM(sale_count),0) - COALESCE(SUM(error_count_4_5),0) AS purchased_qty,
                COALESCE(SUM(transaction_count),0) AS transaction_count')
            ->get();

        $out = [];
        foreach ($rows as $r) {
            $out[Carbon::parse($r->txn_date)->toDateString()] = $r;
        }
        return $out;
    }

    private function activeCountsByDate($rangeStart, $anchor, array $f): array
    {
        $q = OpsMachineDailySnapshot::query()
            ->whereBetween('snapshot_date', [$rangeStart->toDateString(), $anchor->toDateString()])
            ->where('is_active', 1);
        $this->applySnapshotFilters($q, $f);

        $rows = $q->groupBy('snapshot_date')->selectRaw('snapshot_date, COUNT(*) AS c')->get();

        $out = [];
        foreach ($rows as $r) {
            $out[Carbon::parse($r->snapshot_date)->toDateString()] = (int) $r->c;
        }
        return $out;
    }

    private function activeTotal(array $activeByDate, string $anchorKey): int
    {
        if (isset($activeByDate[$anchorKey])) {
            return (int) $activeByDate[$anchorKey];
        }
        if (empty($activeByDate)) {
            return 0;
        }
        ksort($activeByDate);
        return (int) end($activeByDate);
    }

    /**
     * Per-machine count of machines where window-A beats window-B, plus the %
     * of active machines. Returns "N (P%)" or null when there's no data.
     */
    private function perMachineGreater(array $f, string $scanStart, string $scanEnd, string $aStart, string $aEnd, string $bStart, string $bEnd, int $activeTotal, bool $avg = false, ?int $aDays = null, ?int $bDays = null): ?string
    {
        $q = GpMetric::query()->whereBetween('txn_date', [$scanStart, $scanEnd]);
        $this->applyGpFilters($q, $f);

        // Dates originate from Carbon (safe to inline); avoids binding-order issues.
        $rows = $q->groupBy('vend_id')
            ->selectRaw("vend_id,
                SUM(CASE WHEN txn_date BETWEEN '{$aStart}' AND '{$aEnd}' THEN amount_cents ELSE 0 END) AS a,
                SUM(CASE WHEN txn_date BETWEEN '{$bStart}' AND '{$bEnd}' THEN amount_cents ELSE 0 END) AS b")
            ->get();

        if ($rows->isEmpty()) {
            return null;
        }

        $count = 0;
        foreach ($rows as $r) {
            $a = (float) $r->a;
            $b = (float) $r->b;
            if ($avg) {
                $a = $aDays > 0 ? $a / $aDays : 0;
                $b = $bDays > 0 ? $b / $bDays : 0;
            }
            if ($a > $b) {
                $count++;
            }
        }

        $pct = $activeTotal > 0 ? (int) round($count / $activeTotal * 100) : 0;
        return $count . ' (' . $pct . '%)';
    }

    /**
     * Per-day series for the two daily per-machine comparison rows, one value
     * per day column. Each day D is evaluated "as of D":
     *   - l30d_vs_lastmth: trailing-30d sales ending D vs the full calendar
     *     month before D's month.
     *   - l30d_avg_vs_overall: machines whose trailing-30d avg/day ending D is
     *     >= the fleet-wide mean of all machines' trailing-30d avg/day as of D
     *     (mirrors the CustomerIndex green-% card; the /30 scaling cancels on
     *     both sides, so 30-day sums are compared directly).
     * One gp_metrics scan covers every window; per-vend daily sums are then
     * windowed in PHP. The % denominator is that day's active-machine count
     * (snapshot), falling back to the anchor total when no snapshot exists.
     *
     * @return array{l30d_vs_lastmth: array<string,string>, l30d_avg_vs_overall: array<string,string>}
     */
    private function perMachineCompareSeries(array $f, array $dayColumns, array $activeByDate, int $activeTotal, string $rangeStartStr): array
    {
        $windows = [];
        $scanStart = $rangeStartStr;
        foreach ($dayColumns as $col) {
            $d = $col['date'];
            $day = Carbon::parse($d);
            $pmStart = $day->copy()->startOfMonth()->subMonthNoOverflow();
            $windows[$d] = [
                'l30Start' => $day->copy()->subDays(29)->toDateString(),
                'pmStart' => $pmStart->toDateString(),
                'pmEnd' => $pmStart->copy()->endOfMonth()->toDateString(),
            ];
            $scanStart = min($scanStart, $windows[$d]['l30Start'], $windows[$d]['pmStart']);
        }
        $scanEnd = $dayColumns[0]['date'];

        $q = GpMetric::query()->whereBetween('txn_date', [$scanStart, $scanEnd]);
        $this->applyGpFilters($q, $f);
        $rows = $q->groupBy('vend_id', 'txn_date')
            ->selectRaw('vend_id, txn_date, COALESCE(SUM(amount_cents),0) AS amt')
            ->get();

        $byVend = [];
        foreach ($rows as $r) {
            $byVend[$r->vend_id][substr((string) $r->txn_date, 0, 10)] = (float) $r->amt;
        }

        $out = ['l30d_vs_lastmth' => [], 'l30d_avg_vs_overall' => []];
        if (empty($byVend)) {
            return $out;
        }

        foreach ($windows as $d => $w) {
            $cVsLastMth = 0;
            $l30Values = [];
            foreach ($byVend as $amts) {
                $l30 = $this->sumDateWindow($amts, $w['l30Start'], $d);
                if ($l30 > $this->sumDateWindow($amts, $w['pmStart'], $w['pmEnd'])) {
                    $cVsLastMth++;
                }
                $l30Values[] = $l30;
            }
            // Fleet-wide "Overall Avg/day" baseline = mean of each machine's
            // L30d sales as of D (the /30 scaling cancels on both sides, so we
            // compare 30-day sums directly). Counting machines at/above this
            // mirrors VendController's CustomerIndex "% of VM, Avg Daily Sales
            // L30D >= Avg/Day" card, so the two readings agree.
            $cVsOverall = 0;
            if (!empty($l30Values)) {
                $baseline = array_sum($l30Values) / count($l30Values);
                foreach ($l30Values as $v) {
                    if ($v >= $baseline) {
                        $cVsOverall++;
                    }
                }
            }
            $act = (int) ($activeByDate[$d] ?? $activeTotal);
            $pct = fn (int $c) => $act > 0 ? (int) round($c / $act * 100) : 0;
            $out['l30d_vs_lastmth'][$d] = $cVsLastMth . ' (' . $pct($cVsLastMth) . '%)';
            $out['l30d_avg_vs_overall'][$d] = $cVsOverall . ' (' . $pct($cVsOverall) . '%)';
        }

        return $out;
    }

    /** Sum a per-vend [date => amount] map over an inclusive date window. */
    private function sumDateWindow(array $amts, string $start, string $end): float
    {
        $sum = 0.0;
        foreach ($amts as $date => $amt) {
            if ($date >= $start && $date <= $end) {
                $sum += $amt;
            }
        }
        return $sum;
    }

    // ===================== component section =====================

    /**
     * @return array{0: ?array, 1: bool}
     */
    private function componentSection($anchor, array $f, array $statuses): array
    {
        $latestDate = OpsMachineDailySnapshot::query()
            ->where('snapshot_date', '<=', $anchor->toDateString())
            ->max('snapshot_date');

        if ($latestDate) {
            $componentDate = Carbon::parse($latestDate)->toDateString();
            $q = OpsMachineDailySnapshot::query()->where('snapshot_date', $componentDate);
            $this->applySnapshotFilters($q, $f);
            $this->applyStatus($q, $statuses);
            $isLive = $componentDate < $anchor->toDateString();
            $collection = $q->get();
        } else {
            $componentDate = $anchor->toDateString();
            $q = OpsMachineDailySnapshotBuilder::currentMachineQuery();
            $this->applyLiveFilters($q, $f);
            $this->applyLiveStatus($q, $statuses);
            $isLive = true;
            $collection = $q->get();
        }

        if ($collection->isEmpty()) {
            return [null, $isLive];
        }

        return [array_merge(['snapshotDate' => $componentDate], $this->aggregateComponents($collection)), $isLive];
    }

    private function applyStatus($query, array $statuses): void
    {
        if (empty($statuses)) {
            $query->where('is_active', 1);
            return;
        }
        $query->where(function ($w) use ($statuses) {
            if (in_array('active', $statuses)) {
                $w->orWhere('is_active', 1);
            }
            if (in_array('testing', $statuses)) {
                $w->orWhere('is_testing', 1);
            }
            if (in_array('inactive', $statuses)) {
                $w->orWhere(fn ($x) => $x->where('is_active', 0)->where('is_testing', 0));
            }
        });
    }

    private function applyLiveStatus($query, array $statuses): void
    {
        if (empty($statuses)) {
            $query->where('vends.is_active', 1);
            return;
        }
        $query->where(function ($w) use ($statuses) {
            if (in_array('active', $statuses)) {
                $w->orWhere('vends.is_active', 1);
            }
            if (in_array('testing', $statuses)) {
                $w->orWhere('vends.is_testing', 1);
            }
            if (in_array('inactive', $statuses)) {
                $w->orWhere(fn ($x) => $x->where('vends.is_active', 0)->where('vends.is_testing', 0));
            }
        });
    }

    private function aggregateComponents($rows): array
    {
        $lcd = [];
        $bill = ['yes' => 0, 'no' => 0];
        $coin = ['yes' => 0, 'no' => 0];
        $cardYes = 0;
        $cardNo = 0;
        $cardBreakdown = [];
        $firmware = [];
        $apk = [];
        $acb = [];
        $totalActive = 0;

        foreach ($rows as $r) {
            if ((int) $r->is_active === 1) {
                $totalActive++;
            }

            $lcdLabel = Vend::LCD_MONITOR_SHORT_MAPPINGS[$r->lcd_monitor_id] ?? ($r->lcd_monitor_id === null ? 'N/A' : (string) $r->lcd_monitor_id);
            $lcd[$lcdLabel] = ($lcd[$lcdLabel] ?? 0) + 1;

            // Yes = acceptor present (status code reported, 1=Inactive or 3=Active).
            $this->tallyYesNo($bill, $r->bill_stat);
            $this->tallyYesNo($coin, $r->coin_stat);

            if ($r->card_terminal_id !== null) {
                $cardYes++;
                $name = $r->card_terminal_name ?: 'Unknown';
                $cardBreakdown[$name] = ($cardBreakdown[$name] ?? 0) + 1;
            } else {
                $cardNo++;
            }

            $this->tallyKey($firmware, $r->firmware_ver);
            $this->tallyKey($apk, $r->apk_ver);
            $this->tallyKey($acb, $r->acb_rev);
        }

        $lcdRows = $this->mapToRows($lcd);
        $lcdTotal = array_sum(array_map(fn ($r) => $r['label'] === 'N/A' ? 0 : $r['count'], $lcdRows));

        // Ordered, presentation-ready groups. Both the page and the Excel export
        // render from THIS list, so adding a category here flows to both.
        $groups = [
            ['key' => 'lcd', 'label' => 'LCD', 'lead' => 'Total with LCD', 'leadValue' => $lcdTotal, 'rows' => $lcdRows],
            ['key' => 'bill', 'label' => 'Bill Acceptor', 'rows' => [['label' => 'Yes', 'count' => $bill['yes']], ['label' => 'No', 'count' => $bill['no']]]],
            ['key' => 'coin', 'label' => 'Coin Acceptor', 'rows' => [['label' => 'Yes', 'count' => $coin['yes']], ['label' => 'No', 'count' => $coin['no']]]],
            ['key' => 'card', 'label' => 'Card Terminal', 'rows' => [['label' => 'Yes', 'count' => $cardYes], ['label' => 'No', 'count' => $cardNo]]],
            ['key' => 'cardType', 'label' => 'Card Terminal (type)', 'rows' => $this->mapToRows($cardBreakdown)],
            ['key' => 'firmware', 'label' => 'VMC Firmware Ver', 'rows' => $this->mapToRows($firmware)],
            ['key' => 'apk', 'label' => 'Android APK Ver', 'rows' => $this->mapToRows($apk)],
            ['key' => 'acb', 'label' => 'ACB Rev', 'rows' => $this->mapToRows($acb)],
        ];

        return [
            'total' => $rows->count(),
            'totalActive' => $totalActive,
            'groups' => $groups,
        ];
    }

    /** Convert a {label: count} map to an ordered rows array (count desc). */
    private function mapToRows(array $map, bool $sortDesc = true): array
    {
        $rows = [];
        foreach ($map as $label => $count) {
            $rows[] = ['label' => ($label === '' ? 'Unknown' : (string) $label), 'count' => (int) $count];
        }
        if ($sortDesc) {
            usort($rows, fn ($a, $b) => $b['count'] <=> $a['count']);
        }
        return $rows;
    }

    private function tallyYesNo(array &$bucket, $stat): void
    {
        // Present (1=Inactive or 3=Active) => Yes; missing/other => No.
        if ((string) $stat === '3' || (string) $stat === '1') {
            $bucket['yes']++;
        } else {
            $bucket['no']++;
        }
    }

    private function tallyKey(array &$bucket, $key): void
    {
        $k = ($key === null || $key === '') ? 'Unknown' : (string) $key;
        $bucket[$k] = ($bucket[$k] ?? 0) + 1;
    }

    // ===================== column scaffolding =====================

    private function dayColumns(Carbon $anchor): array
    {
        $cols = [];
        for ($i = 0; $i < self::DAY_COLUMNS; $i++) {
            $d = $anchor->copy()->subDays($i);
            $cols[] = [
                'key' => $d->toDateString(),
                'date' => $d->toDateString(),
                'label' => $i === 0 ? 'Yesterday' : $d->format('D'),
            ];
        }
        return $cols;
    }

    private function monthColumns(Carbon $anchor): array
    {
        $cols = [];
        $labels = ['This Mth', 'L1m', 'L2m', 'L3m'];
        foreach ($labels as $i => $label) {
            $monthStart = $anchor->copy()->startOfMonth()->subMonthsNoOverflow($i);
            $monthEnd = $i === 0 ? $anchor->copy() : $monthStart->copy()->endOfMonth();
            $cols[] = [
                'key' => $i === 0 ? 'this_mth' : 'l' . $i . 'm',
                'label' => $label,
                'start' => $monthStart->toDateString(),
                'end' => $monthEnd->toDateString(),
            ];
        }
        return $cols;
    }

    // ===================== KPI pivot =====================

    private function buildKpis(array $fin, array $active, array $dayColumns, array $monthColumns, string $anchorKey, array $perMachine): array
    {
        // id, label, format, agg, scope, compareKey
        $defs = [
            ['total_active_machines', 'Total Active Machines', 'int', 'state', 'both', null],
            ['amount_cents', 'Transaction $', 'money', 'sum', 'both', null],
            ['purchased_qty', '# of qty sold (Transaction)', 'int', 'sum', 'both', null],
            ['gross_margin_pct', 'Total Gross Margin, %', 'percent', 'ratio', 'both', null],
            ['gross_profit_cents', 'Total Gross Margin, $', 'money', 'sum', 'both', null],
            ['vend_earning_per_vm', 'Avg per vm, L30d VendEarning, $', 'money', 'per_vm', 'both', null],
            ['l30d_sales_vs_lastmth', 'L30d sales higher than LastMth', 'text', 'compare', 'daily', 'l30d_vs_lastmth'],
            ['l30d_vendearning', 'L30d VendEarning, $', 'money', 'trailing30_gp', 'daily', null],
            ['monthly_vendearning', 'Monthly VendEarning, $', 'money', 'month_gp', 'monthly', null],
            ['current_mth_vs_prev', 'Current Mth sales higher than Previous Mth', 'text', 'compare', 'monthly', 'curr_vs_prev'],
            ['avg_daily_l30d_vs_overall', 'Avg Daily Sales, L30D >= Overall Avg/day', 'text', 'compare', 'daily', 'l30d_avg_vs_overall'],
        ];

        $rows = [];
        foreach ($defs as [$id, $label, $format, $agg, $scope, $compareKey]) {
            $daily = [];
            $monthly = [];

            if ($scope === 'both' || $scope === 'daily') {
                if ($agg === 'compare') {
                    // Per-day series: one "N (P%)" per day column.
                    $series = $perMachine[$compareKey] ?? [];
                    foreach ($dayColumns as $col) {
                        $daily[$col['key']] = $series[$col['key']] ?? null;
                    }
                } elseif ($agg === 'trailing30_gp') {
                    foreach ($dayColumns as $col) {
                        $daily[$col['key']] = $this->sumFin($fin, $this->trailingDates($col['date'], 30), 'gross_profit_cents');
                    }
                } else {
                    $daily['avg_l7d'] = $this->aggregate($fin, $active, $this->lastNDates($dayColumns, 7), $id, $agg, true);
                    $daily['avg_l30d'] = $this->aggregate($fin, $active, $this->trailingDates($dayColumns[0]['date'], 30), $id, $agg, true);
                    foreach ($dayColumns as $col) {
                        $daily[$col['key']] = $this->dayValue($fin, $active, $col['date'], $id, $agg);
                    }
                }
            }

            if ($scope === 'both' || $scope === 'monthly') {
                if ($agg === 'compare') {
                    $monthly['this_mth'] = $perMachine[$compareKey] ?? null;
                } elseif ($agg === 'month_gp') {
                    foreach ($monthColumns as $col) {
                        $monthly[$col['key']] = $this->sumFin($fin, $this->datesBetween($col['start'], $col['end']), 'gross_profit_cents');
                    }
                } else {
                    foreach ($monthColumns as $col) {
                        $monthly[$col['key']] = $this->aggregate($fin, $active, $this->datesBetween($col['start'], $col['end']), $id, $agg, false);
                    }
                }
            }

            $rows[] = compact('id', 'label', 'format', 'daily', 'monthly');
        }

        // VendEarning = operator's NET share (gross − location fee). The generic
        // pass above filled these rows with GROSS profit; replace them with the
        // net figures so they tie out to the Operation Dashboard (L30d, per
        // machine) and Site Summary (monthly, per site). "Total Gross Margin, $"
        // is intentionally left as gross.
        // Per-day L30d VendEarning comes from the frozen daily snapshot
        // (l30d_vend_earning_cents) so each day shows its real value. The anchor
        // day isn't frozen until the 03:00 job runs, so it falls back to the live
        // figure. Monthly stays from the Site Summary rollup.
        $anchorEarn = $this->vendEarn30dCents;
        $earningFor = function (string $date) use ($anchorKey, $anchorEarn) {
            return $this->vendEarnByDate[$date] ?? ($date === $anchorKey ? $anchorEarn : null);
        };
        $perVmAnchor = $this->kpiActiveTotal > 0 ? (int) round($anchorEarn / $this->kpiActiveTotal) : null;

        foreach ($rows as &$row) {
            if ($row['id'] === 'l30d_vendearning') {
                foreach ($dayColumns as $col) {
                    $row['daily'][$col['key']] = $earningFor($col['key']);
                }
            } elseif ($row['id'] === 'monthly_vendearning') {
                foreach ($monthColumns as $col) {
                    $row['monthly'][$col['key']] = $this->monthlyVendEarnMap[$col['key']] ?? null;
                }
            } elseif ($row['id'] === 'vend_earning_per_vm') {
                // L30d figure is a single 30-day window → no 7-day variant.
                $row['daily']['avg_l7d'] = null;
                $row['daily']['avg_l30d'] = $perVmAnchor;
                foreach ($dayColumns as $col) {
                    $earn = $earningFor($col['key']);
                    $act = $active[$col['key']] ?? $this->kpiActiveTotal;
                    $row['daily'][$col['key']] = ($earn !== null && $act > 0)
                        ? (int) round($earn / $act)
                        : null;
                }
                foreach ($monthColumns as $col) {
                    $m = $this->monthlyVendEarnMap[$col['key']] ?? null;
                    $row['monthly'][$col['key']] = ($m !== null && $this->kpiActiveTotal > 0)
                        ? (int) round($m / $this->kpiActiveTotal)
                        : null;
                }
            }
        }
        unset($row);

        return $rows;
    }

    /**
     * Total NET VendEarning over the last 30 days for the machines in scope — the
     * exact per-machine computation the Operation Dashboard uses
     * (vends.vend_transaction_totals_json's 30-day figures, minus the site location
     * fee via CustomerSummaryAggregator::computeLocationFeeCents, net of external
     * subsidy), summed in PHP over the filtered machines. Bypasses the Vend model's
     * operator global scope (DB::table) — operator scoping comes from $f instead.
     */
    private function thirtyDayVendEarningCents(array $f): int
    {
        $rows = DB::table('vends')
            ->leftJoin('customers', 'customers.id', '=', 'vends.customer_id')
            ->leftJoin('operators', 'operators.id', '=', 'vends.operator_id')
            ->whereNotNull('vends.vend_transaction_totals_json')
            ->when($f['operatorIds'], fn ($q) => $q->whereIn('vends.operator_id', $f['operatorIds']))
            ->when($f['locationTypeIds'], fn ($q) => $q->whereIn('customers.location_type_id', $f['locationTypeIds']))
            ->when($f['vendPrefixIds'], fn ($q) => $q->whereIn('vends.vend_prefix_id', $f['vendPrefixIds']))
            ->when($f['vendModelIds'], fn ($q) => $q->whereIn('vends.vend_model_id', $f['vendModelIds']))
            ->when($f['categoryIds'], fn ($q) => $q->whereIn('customers.category_id', $f['categoryIds']))
            ->when($f['vendIds'], fn ($q) => $q->whereIn('vends.id', $f['vendIds']))
            ->when($f['customerIds'], fn ($q) => $q->whereIn('vends.customer_id', $f['customerIds']))
            ->when($f['siteStatusIds'] ?? [], fn ($q) => $q->whereIn('customers.status_id', $f['siteStatusIds']))
            ->select(
                'vends.vend_transaction_totals_json as totals_json',
                'customers.contract_commission_type as ctype',
                'customers.contract_commission_value as cval',
                'customers.contract_commission_value2 as cval2',
                'customers.contract_ps_term as ps_term',
                'customers.is_external_subsidize as ext_sub',
                'customers.external_subsidize_amount as ext_amt',
                'operators.gst_vat_rate as gst'
            )
            ->get();

        $total = 0;
        foreach ($rows as $r) {
            $tj = is_array($r->totals_json) ? $r->totals_json : json_decode((string) $r->totals_json, true);
            if (! is_array($tj)) {
                continue;
            }

            $salesCents = (int) ($tj['thirty_days_amount'] ?? 0);
            $grossCents = (int) ($tj['thirty_days_gross_profit'] ?? 0);
            if ($salesCents === 0 && $grossCents === 0) {
                continue;
            }

            $fee = CustomerSummaryAggregator::computeLocationFeeCents(
                $r->ctype,
                $r->cval !== null ? (float) $r->cval : null,
                $r->cval2 !== null ? (float) $r->cval2 : null,
                $r->ps_term !== null ? (float) $r->ps_term : null,
                $salesCents,
                $grossCents,
                (float) ($r->gst ?? 0)
            );

            $extSub = ($r->ext_sub && $r->ext_amt !== null)
                ? (int) round(((float) $r->ext_amt) * 100)
                : 0;

            // VendEarning = Gross − (Location Fee − External Subsidy).
            $total += $grossCents - ($fee - $extSub);
        }

        return $total;
    }

    /**
     * Frozen per-day L30d net VendEarning from the machine snapshot, summed over
     * the machines in scope for each day column. Days the nightly job has frozen
     * (or the seeder has filled) return a real value; others are absent and the
     * caller falls back to the live figure for the anchor / blanks the rest.
     * Machine-status is intentionally NOT applied (financials, not component).
     *
     * @param  array<int,array<string,mixed>>  $dayColumns
     * @return array<string,int>
     */
    private function snapshotVendEarningByDate(array $f, array $dayColumns): array
    {
        $dates = array_map(fn ($c) => $c['key'], $dayColumns);
        if (empty($dates)) {
            return [];
        }

        $q = OpsMachineDailySnapshot::query()
            ->whereIn('snapshot_date', $dates)
            ->whereNotNull('l30d_vend_earning_cents');
        $this->applySnapshotFilters($q, $f);

        $rows = $q->groupBy('snapshot_date')
            ->selectRaw('snapshot_date, SUM(l30d_vend_earning_cents) AS e')
            ->get();

        $out = [];
        foreach ($rows as $r) {
            $out[Carbon::parse($r->snapshot_date)->toDateString()] = (int) $r->e;
        }

        return $out;
    }

    /**
     * Net VendEarning per month for the sites in scope, taken straight from the
     * Site Summary rollup (customer_period_summaries.location_earning_cents, which
     * already stores gross_earning − location_fee per site/month). Keyed by month-
     * column key. Site-grained, so machine-level filters (prefix/model/machine id/
     * machine status) do NOT narrow it — only the site-level filters apply.
     *
     * @param  array<int,array<string,mixed>>  $monthColumns
     * @return array<string,int>
     */
    private function monthlyVendEarningMap(array $f, array $monthColumns): array
    {
        $map = [];

        foreach ($monthColumns as $col) {
            $yearMonth = Carbon::parse($col['start'])->startOfMonth()->toDateString();

            $map[$col['key']] = (int) DB::table('customer_period_summaries as cps')
                ->leftJoin('customers as c', 'c.id', '=', 'cps.customer_id')
                ->where('cps.year_month', $yearMonth)
                ->when($f['operatorIds'], fn ($q) => $q->whereIn('cps.operator_id', $f['operatorIds']))
                ->when($f['locationTypeIds'], fn ($q) => $q->whereIn('c.location_type_id', $f['locationTypeIds']))
                ->when($f['categoryIds'], fn ($q) => $q->whereIn('c.category_id', $f['categoryIds']))
                ->when($f['customerIds'], fn ($q) => $q->whereIn('cps.customer_id', $f['customerIds']))
                ->when($f['siteStatusIds'] ?? [], fn ($q) => $q->whereIn('c.status_id', $f['siteStatusIds']))
                ->sum('cps.location_earning_cents');
        }

        return $map;
    }

    private function dayValue(array $fin, array $active, string $date, string $id, string $agg)
    {
        switch ($agg) {
            case 'state':
                return $active[$date] ?? null;
            case 'ratio':
                $r = $fin[$date] ?? null;
                return $r ? $this->marginPct((int) $r->gross_profit_cents, (int) $r->revenue_cents) : null;
            case 'per_vm':
                return $this->aggregate($fin, $active, $this->trailingDates($date, 30), $id, $agg, false);
            default: // sum
                $r = $fin[$date] ?? null;
                return $r ? (int) $r->{$id} : null;
        }
    }

    private function aggregate(array $fin, array $active, array $dates, string $id, string $agg, bool $isAverage)
    {
        switch ($agg) {
            case 'state':
                $vals = [];
                foreach ($dates as $d) {
                    if (isset($active[$d])) {
                        $vals[] = $active[$d];
                    }
                }
                if (empty($vals)) {
                    return null;
                }
                return $isAverage ? (int) round(array_sum($vals) / count($vals)) : (int) end($vals);

            case 'ratio':
                $gp = $this->sumFin($fin, $dates, 'gross_profit_cents');
                $rev = $this->sumFin($fin, $dates, 'revenue_cents');
                return $rev === null ? null : $this->marginPct((int) $gp, (int) $rev);

            case 'per_vm':
                $gp = $this->sumFin($fin, $dates, 'gross_profit_cents');
                $act = $this->latestActive($active, $dates);
                return ($gp === null || ! $act) ? null : (int) round($gp / $act);

            default: // sum
                $vals = [];
                foreach ($dates as $d) {
                    if (isset($fin[$d])) {
                        $vals[] = (int) $fin[$d]->{$id};
                    }
                }
                if (empty($vals)) {
                    return null;
                }
                return $isAverage ? (int) round(array_sum($vals) / count($vals)) : array_sum($vals);
        }
    }

    private function sumFin(array $fin, array $dates, string $field): ?int
    {
        $found = false;
        $sum = 0;
        foreach ($dates as $d) {
            if (isset($fin[$d])) {
                $found = true;
                $sum += (int) $fin[$d]->{$field};
            }
        }
        return $found ? $sum : null;
    }

    private function latestActive(array $active, array $dates): int
    {
        $val = 0;
        foreach ($dates as $d) {
            if (isset($active[$d])) {
                $val = $active[$d];
            }
        }
        return (int) $val;
    }

    private function marginPct(int $gp, int $rev): ?float
    {
        return $rev <= 0 ? null : round($gp / $rev * 100, 2);
    }

    private function datesBetween(string $start, string $end): array
    {
        if ($start > $end) {
            return [];
        }
        $out = [];
        foreach (CarbonPeriod::create(Carbon::parse($start), Carbon::parse($end)) as $d) {
            $out[] = $d->toDateString();
        }
        return $out;
    }

    private function trailingDates(string $endDate, int $days): array
    {
        $end = Carbon::parse($endDate);
        return $this->datesBetween($end->copy()->subDays($days - 1)->toDateString(), $end->toDateString());
    }

    private function lastNDates(array $dayColumns, int $n): array
    {
        return array_map(fn ($c) => $c['date'], array_slice($dayColumns, 0, $n));
    }
}
