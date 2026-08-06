<?php

namespace App\Http\Controllers;

use App\Http\Resources\MonthResource;
use App\Http\Resources\OperatorResource;
use App\Http\Resources\OptionResource;
use App\Http\Resources\VendModelResource;
use App\Http\Resources\VendPrefixResource;
use App\Http\Resources\VendTransactionGraphResource;
use App\Models\Category;
use App\Models\CategoryGroup;
use App\Models\Customer;
use App\Models\LocationType;
use App\Models\Month;
use App\Models\Operator;
use App\Models\Product;
use App\Models\Vend;
use App\Models\VendModel;
use App\Models\VendPrefix;
use App\Models\VendProductRecord;
use App\Models\VendRecord;
use App\Models\VendTransaction;
use App\Support\IndexHint;
use App\Support\ProductAccess;
use App\Services\VendTransactionSalesAggregator;
use App\Traits\GetUserTimezone;
use Carbon\Carbon;
use DB;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;

class DashboardController extends Controller
{
    use GetUserTimezone;

    /**
     * Monthly-sales achievement tiers (thresholds in dollars).
     *
     * This is the single source of truth for the popup's tier styling. The
     * figure and each recent-month row are highlighted with the colour/weight
     * of the highest tier whose threshold the amount meets or exceeds.
     *
     * Set a tier to `null` to disable it — a null tier is omitted from the API
     * response and never applied on the front end. Tiers are evaluated highest
     * first (gold → silver → bronze), so keep thresholds in ascending order.
     */
    /**
     * Roles allowed to see the post-login "This month sales" popup.
     *
     * Single source of truth: the backend gate reads this, and
     * MonthlySalesPopup.vue mirrors the same list to skip a pointless request
     * on every layout remount. The BACKEND is authoritative — the Vue copy is
     * only there to save the round trip, never to enforce.
     */
    public const MONTHLY_SALES_POPUP_ROLES = [
        'superadmin',
        'admin',
        'supervisor',
        'technician',
        'driver',
    ];

    public const MONTHLY_SALES_TIERS = [
        'bronze' => 260000,
        'silver' => 300000,
        'gold' => null,
    ];

    protected $weatherService;

    /**
     * Which pre-aggregated rollup this request reads.
     *
     * false => vend_records          (Dashboard > Performance)
     *          grain: date x machine. NO product dimension, so a
     *          product-restricted viewer sees whole-machine takings.
     *
     * true  => vend_product_records  (Dashboard > Performance (Lite))
     *          grain: date x machine x PRODUCT. VendProductRecord carries
     *          ProductAccessProductColumnScope, so every Eloquent read below is
     *          automatically narrowed to the viewer's "Access Product(s)"
     *          allow-list. This is what makes the page safe for prod_owner.
     *
     * Set once by index()/indexLite() and read by the shared private chart
     * methods, so BOTH pages are driven by ONE set of queries. Do not fork these
     * methods into a second controller: hand-copied read paths are exactly how
     * the Excel exports drifted out of sync with their pages.
     */
    protected bool $lite = false;

    public function __construct(\App\Services\WeatherService $weatherService)
    {
        $this->weatherService = $weatherService;
        // Server-side gates. The sidebar already hides both links, but a hidden
        // link is not a control — prod_owner must not be able to reach the
        // whole-machine page by typing the URL, which is the entire point of the
        // Lite split. Every role that can currently SEE the Performance link
        // already holds `read dashboard-performance` on live, so this closes a
        // hole without taking access away from anyone. superadmin passes via
        // Gate::before. monthlySalesPopup is deliberately NOT gated (it carries
        // its own operator-group check).
        $this->middleware(['permission:read dashboard-performance'])->only('index');
        $this->middleware(['permission:read dashboard-performance-lite'])->only('indexLite');
    }

    /**
     * Dashboard > Performance (Lite) — the product-grained twin of index().
     *
     * Same page, same filters, same charts; every figure is read from
     * vend_product_records instead of vend_records so it can be honestly
     * narrowed to the viewer's products.
     */
    public function indexLite(Request $request)
    {
        $this->lite = true;

        return $this->index($request);
    }

    /**
     * Eloquent query against whichever rollup this request reads.
     *
     * $indexes is the preferred index list PER SOURCE — passed through
     * IndexHint::useFrom, which silently drops the hint when the index is absent
     * instead of hard-failing with SQLSTATE 1176 on a DB whose migrations are
     * behind. On live every index below exists, so the emitted SQL for the
     * vend_records path is byte-identical to what it was before.
     */
    private function recordQuery(array $indexes = [])
    {
        $table = $this->recordTable();
        $query = $this->lite ? VendProductRecord::query() : VendRecord::query();

        $hint = $indexes[$table] ?? null;

        return $hint ? $query->from(IndexHint::useFrom($table, $hint)) : $query;
    }

    /** Physical table behind the current request, for column qualification. */
    private function recordTable(): string
    {
        return $this->lite ? 'vend_product_records' : 'vend_records';
    }

    /**
     * A transient, never-persisted model used only to carry a zero/today row into
     * the day-graph collection. Matched to the active source so the collection
     * handed to VendTransactionGraphResource is homogeneous.
     */
    private function newRecordStub()
    {
        return $this->lite ? new VendProductRecord() : new VendRecord();
    }

    /** Date-range charts: day graph, sales comparison, performers, vend count. */
    private const IDX_BY_DATE = [
        'vend_records' => ['idx_operator_date_vend'],
        'vend_product_records' => ['idx_vpr_operator_date_product'],
    ];

    /** Monthly analytics: the wide covering indexes. */
    private const IDX_BY_MONTH = [
        'vend_records' => ['idx_vr_monthly_sales_covering', 'idx_operator_date_vend'],
        'vend_product_records' => ['idx_vpr_monthly_summary', 'idx_vpr_operator_date_product'],
    ];

    public function index(Request $request)
    {
        $this->setDefaultOperators($request);

        // Pre-resolve vend IDs from machine codes once here so every downstream
        // query can use a direct whereIn instead of firing its own subquery against
        // the vends table repeatedly (would otherwise run ~7 identical lookups).
        if ($request->codes) {
            $codesArr = strpos($request->codes, ',') !== false
                ? array_map('trim', explode(',', $request->codes))
                : [$request->codes];
            $resolvedVendIds = \DB::table('vends')
                ->whereIn('code', $codesArr)
                ->pluck('id')
                ->toArray();
            $request->merge(['_resolved_vend_ids' => $resolvedVendIds]);
        }

        $shouldAutoload = $request->boolean('autoload', false);

        $bestPerformerLimit = (int) $request->input('best_performer_limit', $request->input('performer_limit', 20));
        $bestPerformerLimit = max(1, min(50, $bestPerformerLimit));
        $worstPerformerLimit = (int) $request->input('worst_performer_limit', 20);
        $worstPerformerLimit = max(1, min(50, $worstPerformerLimit));

        // Fetch months once — reused by getMonthlyAnalytics() and the Inertia render
        // to avoid firing two identical "select * from months" queries per request.
        $allMonths = Month::all();

        if ($shouldAutoload) {
            $t = microtime(true);
            \Log::info('[Dashboard] start');

            // Cache testing vend IDs for 5 min. VendController::update() busts this
            // key whenever is_testing changes, so staleness is bounded.
            $testingVendIds = Cache::remember('testing_vend_ids', 300, function () {
                return \DB::table('vends')
                    ->where('is_testing', true)
                    ->pluck('id')
                    ->toArray();
            });
            \Log::info('[Dashboard] testingVendIds: ' . round((microtime(true) - $t) * 1000) . 'ms'); $t = microtime(true);

            $dayGraph = $this->getDayGraph($request, $testingVendIds);
            \Log::info('[Dashboard] getDayGraph: ' . round((microtime(true) - $t) * 1000) . 'ms'); $t = microtime(true);

            $productGraph = $this->getProductGraph($request);
            \Log::info('[Dashboard] getProductGraph: ' . round((microtime(true) - $t) * 1000) . 'ms'); $t = microtime(true);

            $bestPerformer = $this->getBestPerformer($request, $bestPerformerLimit, $testingVendIds);
            \Log::info('[Dashboard] getBestPerformer: ' . round((microtime(true) - $t) * 1000) . 'ms'); $t = microtime(true);

            $worstPerformer = $this->getWorstPerformer($request, $worstPerformerLimit, $testingVendIds);
            \Log::info('[Dashboard] getWorstPerformer: ' . round((microtime(true) - $t) * 1000) . 'ms'); $t = microtime(true);

            $vendCount = $this->getVendCount($request, $testingVendIds);
            \Log::info('[Dashboard] getVendCount: ' . round((microtime(true) - $t) * 1000) . 'ms'); $t = microtime(true);

            $monthGraphData = $this->getMonthGraphData($request, $testingVendIds);
            \Log::info('[Dashboard] getMonthGraphData: ' . round((microtime(true) - $t) * 1000) . 'ms'); $t = microtime(true);

            $activeMachineGraphData = $this->getActiveMachineGraphData($request, $testingVendIds);
            \Log::info('[Dashboard] getActiveMachineGraphData: ' . round((microtime(true) - $t) * 1000) . 'ms'); $t = microtime(true);

            $monthlyAnalytics = $this->getMonthlyAnalytics($request, $allMonths);
            \Log::info('[Dashboard] getMonthlyAnalytics: ' . round((microtime(true) - $t) * 1000) . 'ms'); $t = microtime(true);

            $salesComparisonGraphData = $this->getSalesComparisonGraph($request, $testingVendIds);
            \Log::info('[Dashboard] getSalesComparisonGraph: ' . round((microtime(true) - $t) * 1000) . 'ms');
        } else {
            $emptyCollection = collect([]);
            $dayGraph = $emptyCollection;
            $productGraph = $emptyCollection;
            $bestPerformer = $emptyCollection;
            $worstPerformer = $emptyCollection;
            $vendCount = 0;
            $monthGraphData = [];
            $activeMachineGraphData = [];
            $monthlyAnalytics = [];
            $salesComparisonGraphData = [];
        }

        return Inertia::render($this->lite ? 'DashboardLite' : 'Dashboard', array_merge([
            // "Access Product(s)".
            //
            // Dashboard.vue uses this to raise the amber "whole-machine figures"
            // warning: every figure there comes from vend_records, which has no
            // product dimension, so a restricted viewer is seeing whole-machine
            // money. We show it anyway (see routes/web.php) but the page must say
            // so rather than let it read as "their" sales.
            //
            // DashboardLite.vue reads the SAME flag to say the opposite — its
            // figures ARE narrowed to the viewer's products — because it is
            // backed by vend_product_records.
            'productRestricted' => ProductAccess::isRestricted(),
            'activeMachineGraphData' => $activeMachineGraphData,
            'autoLoad' => $shouldAutoload,
            'dayGraphData' => VendTransactionGraphResource::collection($dayGraph),
            'locationTypeOptions' => OptionResource::collection(
                LocationType::toBase()->select('id', 'name')->orderBy('sequence')->get()
            ),
            'monthGraphData' => $monthGraphData,
            'months' => MonthResource::collection($allMonths),
            'monthsByModel' => $monthlyAnalytics,
            'operatorOptions' => OperatorResource::collection(
                Operator::orderBy('name')->get()
            ),
            'productGraphData' => $productGraph,
            'performerGraphData' => VendTransactionGraphResource::collection($bestPerformer),
            'performerLimit' => $bestPerformerLimit,
            'worstPerformerGraphData' => VendTransactionGraphResource::collection($worstPerformer),
            'worstPerformerLimit' => $worstPerformerLimit,
            'vendCount' => $vendCount,
            'vendModelOptions' => VendModelResource::collection(
                VendModel::orderBy('name')->get()
            ),
            'vendPrefixOptions' => VendPrefixResource::collection(
                VendPrefix::orderBy('name')->get()
            ),
            'salesComparisonGraphData' => $salesComparisonGraphData,
            // Only the Lite page gets the product filter dropdowns — the full
            // Performance page has no product dimension to filter on, and this
            // would be dead payload on every one of its requests.
        ], $this->lite ? $this->productFilterOptions() : []));
    }

    /**
     * Dropdown options for the Lite page's product filter row.
     *
     * Built from the Product MASTER, not from a DISTINCT over the 622k-row
     * vend_product_records — 893 products / 27 categories / 6 groups is three
     * trivial queries, where the DISTINCT would be a full scan on every load.
     *
     * No manual product-access filtering here on purpose: Product already
     * carries ProductAccessProductScope (plus OperatorProductFilterScope), so a
     * restricted viewer's dropdown lists only their own SKUs, and the categories
     * below are derived from that already-narrowed set rather than from the full
     * category table — otherwise the filter bar would advertise categories the
     * viewer can never return a row for.
     *
     * Shape is {id, name} to match locationTypeOptions (MultiSelect label="name"),
     * deliberately NOT ProductResource — that ships ~25 fields plus relations per
     * row and would put ~900 of them in the Inertia payload.
     */
    private function productFilterOptions(): array
    {
        $products = Product::query()
            ->orderBy('name')
            ->orderBy('code')
            ->get(['id', 'code', 'name', 'category_id', 'category_group_id']);

        return [
            'productOptions' => $products->map(fn ($product) => [
                'id' => $product->id,
                'name' => trim($product->code . ' - ' . $product->name, ' -'),
            ])->values(),
            'productCategoryOptions' => Category::whereIn(
                'id',
                $products->pluck('category_id')->filter()->unique()->values()
            )->orderBy('name')->get(['id', 'name']),
            'productCategoryGroupOptions' => CategoryGroup::whereIn(
                'id',
                $products->pluck('category_group_id')->filter()->unique()->values()
            )->orderBy('name')->get(['id', 'name']),
        ];
    }

    /**
     * Lightweight JSON for the post-login "This month sales" popup.
     *
     * Only the HIPL operator group (HIPL + HIMD + LEA + HIESG + UL-ST) is eligible.
     * The figure mirrors the dashboard's sales total exactly: SUM(total_amount)
     * from vend_records for every day from the 1st up to *yesterday*, plus today's
     * live vend_transactions (success error codes 0/6/NULL, amount > 0). vend_records
     * are T-1 daily aggregates, so combining "records up to yesterday" + "today's
     * transactions" avoids any double count and stays fast (no full-month scan of
     * vend_transactions). Amounts are stored in cents, so the total is divided by 100.
     */
    public function monthlySalesPopup(Request $request)
    {
        // ── SECURITY GATE ───────────────────────────────────────────────
        // This is the ONLY authorization boundary that matters. The figure is
        // group-level sales and must never reach a non-HIPL operator. The
        // route already requires auth ('auth' middleware), and here we resolve
        // the operator code straight from the authenticated user's own
        // operator_id (DB-backed, server-side — cannot be spoofed by the
        // client). Anyone whose operator is not exactly "HIPL" gets an
        // identical, data-free {show:false} response: no amount, no leak.
        $user = $request->user();
        $operatorCode = $user
            ? Operator::whereKey($user->operator_id)->value('code')
            : null;

        if ($operatorCode !== 'HIPL') {
            return response()->json(['show' => false]);
        }

        // ── SECOND GATE: role ───────────────────────────────────────────
        // Operator alone was not enough. Every role in the HIPL group saw this,
        // including prod_owner — a product owner bound to a single machine was
        // being shown GROUP-WIDE monthly sales plus the last three months'
        // totals. The figure is company performance and is only meant for the
        // roles below.
        //
        // Checked server-side off the user's own role rows, same as the operator
        // check above, and returns the identical data-free {show:false} so a
        // caller cannot tell which gate rejected them.
        if (! $user->hasAnyRole(self::MONTHLY_SALES_POPUP_ROLES)) {
            return response()->json(['show' => false]);
        }

        // Show once per *login* session. The session is regenerated on login
        // (AuthenticatedSessionController), so this flag resets on every fresh
        // login and survives in-session page navigation. Using the server session
        // instead of browser sessionStorage means logout→login re-shows it.
        //
        // IMPORTANT: the flag is set ONLY when the user explicitly dismisses the
        // popup (the client pings ?dismiss=1 on close), NOT on the auto-fetch.
        // The Authenticated layout is a non-persistent Inertia layout, so it
        // remounts on every navigation and re-fetches this endpoint. If we set
        // the flag on fetch, an early remount right after login would flip the
        // gate to "shown" before the user ever interacted with it, causing the
        // popup to flash for ~1s and then never reappear. Deferring the flag to
        // dismissal makes the popup persist across remounts until the user
        // actually closes it.
        if ($request->boolean('dismiss')) {
            $request->session()->put('monthly_sales_popup_shown', true);
            return response()->json(['show' => false]);
        }

        // A manual refresh (?refresh=1) bypasses the once-per-session gate so the
        // user can re-pull the live figure on demand. It does NOT touch the
        // session flag, so the auto-show-once behaviour is unaffected.
        if (! $request->boolean('refresh')) {
            if ($request->session()->get('monthly_sales_popup_shown')) {
                return response()->json(['show' => false]);
            }
        }

        $operatorIds = Operator::whereIn('code', ['HIPL', 'HIMD', 'LEA', 'HIESG', 'UL-ST'])
            ->pluck('id')
            ->all();

        $tz = $this->getUserTimezone();
        $now = Carbon::now($tz);
        $monthStart = $now->copy()->startOfMonth()->startOfDay();
        // App timezone is authoritative for day boundaries (DB stores local time).
        $appTzNow = Carbon::now(config('app.timezone'));
        $todayStart = $appTzNow->copy()->startOfDay();
        $todayEnd = $appTzNow->copy()->endOfDay();

        $testingVendIds = Cache::remember('testing_vend_ids', 300, function () {
            return \DB::table('vends')->where('is_testing', true)->pluck('id')->toArray();
        });

        // Past days of this month (1st .. end of yesterday) from the daily aggregates.
        $recordsAmount = (float) VendRecord::query()
            ->whereIn('operator_id', $operatorIds)
            ->whereBetween('date', [$monthStart, $todayStart->copy()->subSecond()])
            ->when($testingVendIds, fn ($q) => $q->whereNotIn('vend_id', $testingVendIds))
            ->sum('total_amount');

        // Today, live from vend_transactions (same success filter the dashboard uses).
        $todayAmount = (float) VendTransaction::query()
            ->leftJoin('vend_channel_errors', 'vend_channel_errors.id', '=', 'vend_transactions.vend_channel_error_id')
            ->whereIn('vend_transactions.operator_id', $operatorIds)
            ->whereBetween('vend_transactions.transaction_datetime', [$todayStart, $todayEnd])
            ->where('vend_transactions.amount', '>', 0)
            ->where(function ($query) {
                $query->whereIn('vend_channel_errors.code', [0, 6])
                    ->orWhereNull('vend_channel_errors.code');
            })
            ->when($testingVendIds, fn ($q) => $q->whereNotIn('vend_transactions.vend_id', $testingVendIds))
            ->sum('vend_transactions.amount');

        $total = round(($recordsAmount + $todayAmount) / 100, 2);

        // ── LAST COMPLETE MONTHS ────────────────────────────────────────
        // Show the 3 most recent *complete* months under the live figure,
        // each with a month-over-month arrow. We compute 4 trailing months
        // so the earliest displayed month also has a prior month to compare
        // against. Complete months are fully captured by the daily
        // aggregates (vend_records), so no live transaction scan is needed.
        $monthlyTotals = [];
        for ($i = 1; $i <= 4; $i++) {
            $mStart = $monthStart->copy()->subMonths($i);
            $mEnd = $mStart->copy()->endOfMonth()->endOfDay();

            $amount = round(((float) VendRecord::query()
                ->whereIn('operator_id', $operatorIds)
                ->whereBetween('date', [$mStart, $mEnd])
                ->when($testingVendIds, fn ($q) => $q->whereNotIn('vend_id', $testingVendIds))
                ->sum('total_amount')) / 100, 2);

            $monthlyTotals[$i] = [
                'date' => $mStart,
                'amount' => $amount,
            ];
        }

        // Build the 3 displayed months (most recent first) with a trend
        // direction vs the immediately preceding month ('up'|'down'|'flat').
        $lastMonths = [];
        for ($i = 1; $i <= 3; $i++) {
            $prev = $monthlyTotals[$i + 1]['amount'];
            $curr = $monthlyTotals[$i]['amount'];
            $trend = $curr > $prev ? 'up' : ($curr < $prev ? 'down' : 'flat');
            $pct = $prev > 0 ? round((($curr - $prev) / $prev) * 100, 1) : null;

            $lastMonths[] = [
                'label' => $monthlyTotals[$i]['date']->format('M y'),
                'amount' => $curr,
                'trend' => $trend,
                'change_pct' => $pct,
            ];
        }

        // Only expose enabled tiers (drop any with a null threshold).
        $tiers = array_filter(self::MONTHLY_SALES_TIERS, fn ($t) => $t !== null);

        return response()->json([
            'show' => true,
            'amount' => $total,
            'currency' => '$',
            'as_of' => $now->format('d M Y H:i'),
            'last_months' => $lastMonths,
            'tiers' => (object) $tiers,
        ]);
    }

    private function getSalesComparisonGraph(Request $request, array $testingVendIds)
    {
        if ($request->month_year) {
            $baseDate = Carbon::createFromFormat('Y-m', $request->month_year)->setTimezone($this->getUserTimezone())->startOfMonth();
        } else {
            $baseDate = Carbon::today()->setTimezone($this->getUserTimezone())->startOfMonth();
        }

        $today = Carbon::today()->setTimezone($this->getUserTimezone());
        $yearsBack = max(2, min(3, (int) ($request->years_back ?? 2)));

        // Define the 6 base periods (2-year view)
        $periods = [
            'current_month' => $baseDate->copy(),
            'prev_month' => $baseDate->copy()->subMonth(),
            'next_month' => $baseDate->copy()->addMonth(),
            'last_year_same_month' => $baseDate->copy()->subYear(),
            'last_year_prev_month' => $baseDate->copy()->subYear()->subMonth(),
            'last_year_next_month' => $baseDate->copy()->subYear()->addMonth(),
        ];

        // Add 3-year periods when requested
        if ($yearsBack >= 3) {
            $periods['two_years_ago_same_month'] = $baseDate->copy()->subYears(2);
            $periods['two_years_ago_prev_month']  = $baseDate->copy()->subYears(2)->subMonth();
            $periods['two_years_ago_next_month']  = $baseDate->copy()->subYears(2)->addMonth();
        }

        // Filter out future "next month" - REMOVED to always show 3 months
        // $includeNextMonth = !$periods['next_month']->isFuture();
        // if (!$includeNextMonth) {
        //     unset($periods['next_month']);
        // }

        $cacheKey = $this->makeCacheKey('sales_comparison_graph', $request);
        $results = Cache::remember($cacheKey, 300, function () use ($request, $testingVendIds, $periods) {
            $query = $this->recordQuery(self::IDX_BY_DATE)
                ->filterIndex($request)
                ->whereNotIn('vend_id', $testingVendIds)
                ->select(
                    DB::raw('SUM(total_amount) as amount'),
                    DB::raw('DAY(date) as day'),
                    DB::raw('MONTH(date) as month'),
                    DB::raw('YEAR(date) as year')
                )
                ->groupBy('year', 'month', 'day');

            // Build where clause for all periods.
            // Use whereBetween per period (start/end of month) instead of whereYear()/whereMonth()
            // because YEAR(date) and MONTH(date) are function calls on the column — MySQL cannot
            // use any index on `date` when a function wraps it. whereBetween generates a plain
            // range predicate (date >= X AND date <= Y) that the date index can seek directly.
            $query->where(function ($q) use ($periods) {
                foreach ($periods as $key => $date) {
                    $q->orWhereBetween('date', [
                        $date->copy()->startOfMonth()->startOfDay(),
                        $date->copy()->endOfMonth()->endOfDay(),
                    ]);
                }
            });

            return $query->get();
        });

        // Initialize structure
        // Weather service disabled — was causing 5-10 s of latency (6 calls × ~1-2 s each).
        // Re-enable by restoring getDailyWeatherForRange() calls per period.
        $data = [];
        foreach ($periods as $key => $date) {
            $daysInMonth = $date->daysInMonth;
            $data[$key] = [
                'label' => $date->format('M Y'),
                'data' => [],
                'year' => $date->year,
                'month' => $date->month,
                'weather_icons' => [],
            ];

            // Initialize days based on actual days in month
            for ($day = 1; $day <= $daysInMonth; $day++) {
                // Check if this date is in the future
                $checkDate = Carbon::create($date->year, $date->month, $day)->setTimezone($this->getUserTimezone());

                // Use null for future dates (Chart.js won't draw lines to null values)
                // Use 0 for past/today dates with no data
                if ($checkDate->isFuture()) {
                    $data[$key]['data'][$day] = null;
                } else {
                    $data[$key]['data'][$day] = 0;
                }
                $data[$key]['weather_icons'][$day] = null; // weather disabled
            }
        }

        // Fill data from query results
        foreach ($results as $row) {
            foreach ($data as $key => &$periodData) {
                if ($row->year == $periodData['year'] && $row->month == $periodData['month']) {
                    // Only set data if the day exists in the initialized array (sanity check)
                    if (isset($periodData['data'][$row->day])) {
                        $periodData['data'][$row->day] = (float) $row->amount / 100;
                    }
                }
            }
        }

        // Re-index data to be 0-indexed arrays for Chart.js
        foreach ($data as &$periodData) {
            $periodData['data'] = array_values($periodData['data']);
            $periodData['weather_icons'] = array_values($periodData['weather_icons']);
        }

        return $data;
    }

    private function setDefaultOperators(Request $request)
    {
        if (!$request->operators || (is_array($request->operators) && in_array('all', $request->operators))) {
            if (auth()->user()->operator->code == 'HIPL') {
                // Single query instead of 4 separate first() calls.
                $operatorMap = Operator::whereIn('code', ['HIMD', 'LEA', 'HIESG', 'UL-ST'])
                    ->pluck('id', 'code');
                $request->merge([
                    'operators' => [
                        auth()->user()->operator_id,
                        $operatorMap->get('HIMD'),
                        $operatorMap->get('LEA'),
                        $operatorMap->get('HIESG'),
                        $operatorMap->get('UL-ST'),
                    ]
                ]);
            } else {
                $request->merge(['operators' => [auth()->user()->operator_id]]);
            }
        }
    }

    private function getDayGraph(Request $request, array $testingVendIds)
    {
        if ($request->month_year) {
            $baseDate = Carbon::createFromFormat('Y-m', $request->month_year)->setTimezone($this->getUserTimezone());
            $day_date_from = $baseDate->copy()->startOfMonth();
            $day_date_to = $baseDate->copy()->endOfMonth();
        } else {
            $day_date_from = $request->day_date_from ? Carbon::parse($request->day_date_from)->setTimezone($this->getUserTimezone()) : Carbon::today()->startOfMonth()->setTimezone($this->getUserTimezone());
            $day_date_to = $request->day_date_to ? Carbon::parse($request->day_date_to)->setTimezone($this->getUserTimezone()) : Carbon::today()->endOfMonth()->setTimezone($this->getUserTimezone());
        }

        $dayGraph = $this->recordQuery(self::IDX_BY_DATE)
            ->whereBetween('date', [$day_date_from->copy()->subMonth()->startOfDay(), $day_date_to->copy()->endOfDay()])
            ->filterIndex($request)
            ->whereNotIn('vend_id', $testingVendIds);

        // dd($dayGraph->get()->toArray());
        $dayGraph->groupBy('date')
            ->select(
                DB::raw('MONTH(date) as month'),
                DB::raw('DATE_FORMAT(date, "%M %Y") as month_name'),
                DB::raw('DATE(date) as date'),
                DB::raw('DAY(date) as day'),
                DB::raw('SUM(total_amount) as amount'),
                DB::raw('SUM(total_count) as count')
            );

        $dayGraph = $dayGraph->orderBy('date', 'asc')->get();

        $today = Carbon::today()->setTimezone($this->getUserTimezone());
        if ($today->between($day_date_from->copy()->subMonth()->startOfDay(), $day_date_to->copy()->endOfDay())) {
            // Ensure we use application timezone boundaries to match DB storage
            $startOfTodayUTC = $today->copy()->setTimezone(config('app.timezone'))->startOfDay();
            $endOfTodayUTC = $today->copy()->setTimezone(config('app.timezone'))->endOfDay();

            // Today is not in either rollup yet (both are T-1), so it is topped up
            // live from vend_transactions. No lite branch needed: VendTransaction
            // already carries ProductAccessTransactionScope, so this leg is
            // narrowed to the viewer's products on BOTH pages.
            $todayTransactions = VendTransaction::query()
                ->filterTransactionIndex($request)
                ->leftJoin('vend_channel_errors', 'vend_channel_errors.id', '=', 'vend_transactions.vend_channel_error_id')
                ->where(function ($query) {
                    $query->where('vend_channel_errors.code', 0)
                        ->orWhere('vend_channel_errors.code', 6)
                        ->orWhereNull('vend_channel_errors.code');
                })
                ->whereBetween('transaction_datetime', [$startOfTodayUTC, $endOfTodayUTC])
                ->where('amount', '>', 0)
                ->whereNotIn('vend_id', $testingVendIds)
                ->select(
                    DB::raw('ROUND(SUM(amount), 2) as amount'),
                    DB::raw('SUM(success_qty) as count')
                )
                ->first();

            if ($todayTransactions) {
                // Check if today already exists in dayGraph (unlikely from VendRecord but good to check)
                $existingTodayIndex = $dayGraph->search(function ($item) use ($today) {
                    return $item->day == $today->day && $item->month == $today->month;
                });

                if ($existingTodayIndex !== false) {
                    // If exists (maybe partial VendRecord?), replace or add? Usually VendsRecords are T-1.
                    // Let's assume real-time takes precedence or we sum?
                    // For safety, let's override with real-time if VendRecord is empty, or sum if partial.
                    // But simpler is to assume VendRecord doesn't have Today yet.
                    $dayGraph[$existingTodayIndex]->amount = $todayTransactions->amount ?? 0;
                    $dayGraph[$existingTodayIndex]->count = $todayTransactions->count ?? 0;
                } else {
                    $newEntry = $this->newRecordStub();
                    $newEntry->month = $today->month;
                    $newEntry->month_name = $today->format('F Y'); // "January 2026"
                    $newEntry->date = $today->copy(); // Keep as Carbon object or string matching others
                    $newEntry->day = $today->day;
                    $newEntry->amount = $todayTransactions->amount ?? 0;
                    $newEntry->count = $todayTransactions->count ?? 0;
                    $dayGraph->push($newEntry);
                }
            }
        }

        $dayGraph = $this->fillEmptyDates($dayGraph, $day_date_from->copy()->subMonth(), $day_date_to);

        // Weather service disabled — was causing 5-10 s delay per call.
        // Re-enable by restoring the getDailyWeatherForRange() call below.
        foreach ($dayGraph as $day) {
            $day->weather_icon = null;
        }

        return $dayGraph;
    }

    private function fillEmptyDates($dayGraph, $startDate, $endDate)
    {
        // Build an O(1) lookup keyed by "month-day" so we don't scan the entire
        // collection for every date in the range (was O(n²), now O(n)).
        $existingKeys = [];
        foreach ($dayGraph as $graphDayValue) {
            $existingKeys[$graphDayValue->month . '-' . $graphDayValue->day] = true;
        }

        $currentDate = $startDate->copy();
        while ($currentDate->lte($endDate)) {
            $key = $currentDate->month . '-' . $currentDate->day;
            if (!isset($existingKeys[$key])) {
                $newModel = $this->newRecordStub();
                $newModel->amount = 0;
                $newModel->count = 0;
                $newModel->date = $currentDate->copy()->startOfDay();
                $newModel->day = $currentDate->day;
                $newModel->month = $currentDate->month;
                $newModel->month_name = $currentDate->format('F Y');
                $dayGraph->push($newModel);
                $existingKeys[$key] = true; // prevent duplicate inserts
            }

            $currentDate->addDay();
        }

        return $dayGraph->sortBy('date');
    }

    /**
     * Top-10 products, last 7 days.
     *
     * Deliberately NOT switched to vend_product_records on the Lite page. This
     * is the one chart whose grain is ALREADY the product, it reads live
     * vend_transactions so it includes today (both rollups are T-1), and
     * VendTransaction's ProductAccessTransactionScope already narrows it to the
     * viewer's products. Pointing it at the rollup would lose today and gain
     * nothing.
     */
    private function getProductGraph(Request $request)
    {
        $seven_days_date_from = Carbon::today()->subDays(6)->setTimezone($this->getUserTimezone());
        $seven_days_date_to = Carbon::today()->setTimezone($this->getUserTimezone());

        $salesQuery = VendTransactionSalesAggregator::productTotals(
            $seven_days_date_from,
            $seven_days_date_to,
            function ($query) use ($request) {
                $query->filterTransactionIndex($request)
                    ->whereNotIn('vend_transactions.vend_id', function ($subQuery) {
                        $subQuery->select('id')->from('vends')->where('is_testing', true);
                    });
            }
        );

        $topProducts = $salesQuery
            ->orderByDesc('total_count')
            ->limit(10)
            ->get();

        if ($topProducts->isEmpty()) {
            return collect();
        }

        $products = Product::query()
            ->select('id', 'code', 'name')
            ->whereIn('id', $topProducts->pluck('product_id'))
            ->get()
            ->keyBy('id');

        return $topProducts->map(function ($row) use ($products) {
            $product = $products->get($row->product_id);

            return [
                'amount' => (int) $row->total_amount / 100,
                'count' => (int) $row->total_count,
                'product' => $product ? [
                    'id' => $product->id,
                    'code' => $product->code,
                    'name' => $product->name,
                ] : null,
            ];
        });
    }

    private function getBestPerformer(Request $request, int $limit, array $testingVendIds)
    {
        return $this->performerQuery($request, $testingVendIds)
            ->orderBy('amount', 'desc')
            ->limit($limit)
            ->get();
    }

    private function getWorstPerformer(Request $request, int $limit, array $testingVendIds)
    {
        return $this->performerQuery($request, $testingVendIds)
            ->orderBy('amount', 'asc')
            ->limit($limit)
            ->get();
    }

    /**
     * Shared best/worst performer query — the two differed only in sort
     * direction, so they are one builder now and cannot drift apart.
     *
     * On the Lite page the grouping is still per MACHINE; the product scope is
     * applied by VendProductRecord's global scope before the SUM, so the ranking
     * answers "which machines sell MY products best", not "which machines sell
     * most overall".
     */
    private function performerQuery(Request $request, array $testingVendIds)
    {
        $table = $this->recordTable();

        return $this->recordQuery(self::IDX_BY_DATE)
            ->with(['customer:id,code,name,virtual_customer_prefix,virtual_customer_code', 'vend:id,code,name,customer_id,vend_prefix_id', 'vend.customer:id,code,name,virtual_customer_prefix,virtual_customer_code', 'vend.vendPrefix:id,name'])
            ->filterIndex($request)
            ->whereBetween('date', [Carbon::today()->copy()->subDays(29)->startOfDay(), Carbon::today()->endOfDay()])
            ->whereNotIn('vend_id', $testingVendIds)
            ->groupBy($table . '.vend_id')
            ->select(
                $table . '.id',
                $table . '.customer_id',
                $table . '.vend_id',
                DB::raw("SUM({$table}.total_amount) as amount"),
                DB::raw("SUM({$table}.total_count) as count")
            );
    }

    private function getVendCount(Request $request, array $testingVendIds)
    {
        $cacheKey = $this->makeCacheKey('vend_count', $request);
        $lite = $this->lite;
        $table = $this->recordTable();

        return Cache::remember($cacheKey, 300, function () use ($request, $testingVendIds, $lite, $table) {
            // whereBetween instead of whereDate() — whereDate() wraps the column in DATE()
            // which disables index seeks. whereBetween generates a plain range MySQL can index.
            $query = $this->recordQuery(self::IDX_BY_DATE)
                ->filterIndex($request)
                ->whereBetween('date', [Carbon::yesterday()->startOfDay(), Carbon::yesterday()->endOfDay()])
                ->whereNotIn('vend_id', $testingVendIds);

            // vend_records holds ~one row per machine per day, so a plain count()
            // IS the machine count. vend_product_records holds one row per machine
            // PER PRODUCT, so the same count() would report ~4x too many machines —
            // it must count distinct machines instead.
            return $lite
                ? $query->distinct()->count($table . '.vend_id')
                : $query->count();
        });
    }

    private function getMonthGraphData(Request $request, array $testingVendIds)
    {
        $yearsBack = max(2, min(3, (int) ($request->years_back ?? 2)));

        if ($request->month_year) {
            $baseDate = Carbon::createFromFormat('Y-m', $request->month_year);
            $thisYear = $baseDate->copy()->endOfYear();
            $lastYear = $baseDate->copy()->subYears($yearsBack - 1)->startOfYear();
            $compareYear = $baseDate->year;
            $compareMonth = $baseDate->month;
        } else {
            $thisYear = Carbon::today()->endOfYear();
            $lastYear = Carbon::today()->subYears($yearsBack - 1)->startOfYear();
            $compareYear = Carbon::today()->year;
            $compareMonth = Carbon::today()->month;
        }

        $cacheKey = $this->makeCacheKey('month_graph', $request);
        $monthGraph = Cache::remember($cacheKey, 300, function () use ($request, $testingVendIds, $lastYear, $thisYear) {
            // After migration 2026_04_18_200000 runs, idx_vr_monthly_summary
            // (operator_id, year, month, vend_id, total_amount, total_count) is a
            // covering index — MySQL resolves the entire query with zero heap reads.
            // No USE INDEX hint needed: MySQL's optimizer prefers the covering index
            // automatically, and falls back to idx_operator_year_month safely before
            // the migration runs (avoids full-table-scan from a missing-index hint).
            // Lite reads the same shape off vend_product_records, whose
            // idx_vpr_monthly_summary (operator_id, year, month, product_id,
            // vend_id, total_amount, total_count) covers it the same way.
            return $this->recordQuery()
                ->whereBetween('year', [$lastYear->year, $thisYear->year])
                ->filterIndex($request)
                ->whereNotIn('vend_id', $testingVendIds)
                ->groupBy('year', 'month')
                // month_name is intentionally NOT selected: the consumer below
                // recomputes it via Carbon (line ~730) and never reads it off this
                // result. Dropping MONTHNAME(date) removes the only reference to
                // `date`, so idx_vr_monthly_summary now covers the query fully
                // (zero heap reads). Output is unchanged.
                ->select(
                    DB::raw('month'),
                    DB::raw('year'),
                    DB::raw('SUM(total_amount) as amount'),
                    DB::raw('SUM(total_count) as count')
                )
                ->orderBy('month', 'asc')
                ->get();
        });

        $monthsArrInit = [];
        foreach (range($lastYear->year, $thisYear->year) as $year) {
            for ($i = 1; $i <= 12; $i++) {
                if ($year == $compareYear && $i > $compareMonth) {
                    continue;
                }
                $monthsArrInit[$year][$i] = [
                    'month' => $i,
                    'month_name' => Carbon::createFromDate($year, $i, 1)->format('F'),
                    'year' => $year,
                    'amount' => 0,
                    'count' => 0,
                ];
            }
        }

        foreach ($monthGraph as $month) {
            $monthsArrInit[$month->year][$month->month]['amount'] = $month->amount / 100;
            $monthsArrInit[$month->year][$month->month]['count'] = $month->count;
        }

        return collect($monthsArrInit);
    }

    /**
     * Hand-applied twin of OperatorVendRecordScope, for the ONE raw DB::table()
     * builder on this page.
     *
     * Kept deliberately faithful to that scope, leg for leg, so the two cannot
     * drift:
     *   - operator_id, skipped for the HappyIce operator (id 1) exactly as the
     *     scope skips it;
     *   - the user's bound machines (user_vend), when they have any;
     *   - and the customer_ids of those machines, which the scope also adds so a
     *     site-grained count cannot spill past the bound machines' sites.
     *
     * Inert when nobody is logged in (queue/cron) and inert for a user with no
     * bound machines — which is every unrestricted user, so the emitted SQL is
     * unchanged for them.
     */
    private function applyVendAccessToRawQuery($query, string $table)
    {
        if (! auth()->check()) {
            return $query;
        }

        $user = auth()->user();

        $operatorId = $user->operator_id;

        if ($operatorId && (int) $operatorId !== 1) {
            $query->where($table . '.operator_id', $operatorId);
        }

        $vendIds = $user->vends ? $user->vends->pluck('id')->all() : [];

        if (empty($vendIds)) {
            return $query;
        }

        $query->whereIn($table . '.vend_id', $vendIds);

        $customerIds = Vend::whereIn('id', $vendIds)->get()->pluck('customer_id')->filter()->all();

        if (! empty($customerIds)) {
            $query->whereIn($table . '.customer_id', $customerIds);
        }

        return $query;
    }

    /**
     * Earliest month ('YYYY-MM') this viewer can have data for, or null.
     *
     * Two independent floors, whichever is later:
     *   1. the first row that exists in the active rollup at all — vend_records
     *      reaches back to 2022, vend_product_records only to 2025-05-27;
     *   2. the viewer's own "Transaction Access From" cut-off.
     *
     * Cached for a day: MIN(date) is an index seek, but this runs on every
     * dashboard load and the answer only moves when the rollup is backfilled.
     * Keyed by table so the two pages cannot serve each other's floor.
     */
    private function rollupDataFloor(): ?string
    {
        $table = $this->recordTable();

        $sourceMin = Cache::remember(
            'rollup_min_date_' . $table,
            86400,
            fn () => DB::table($table)->min('date')
        );

        $sourceMonth = $sourceMin ? substr((string) $sourceMin, 0, 7) : null;
        $viewerFrom = \App\Support\TransactionAccess::current();
        $viewerMonth = $viewerFrom ? substr($viewerFrom, 0, 7) : null;

        if ($sourceMonth === null) {
            return $viewerMonth;
        }

        if ($viewerMonth === null) {
            return $sourceMonth;
        }

        return $sourceMonth >= $viewerMonth ? $sourceMonth : $viewerMonth;
    }

    private function getActiveMachineGraphData(Request $request, array $testingVendIds)
    {
        $yearsBack = max(2, min(3, (int) ($request->years_back ?? 2)));

        if ($request->month_year) {
            $baseDate = Carbon::createFromFormat('Y-m', $request->month_year);
            $thisYear = $baseDate->copy()->endOfYear();
            $lastYear = $baseDate->copy()->subYears($yearsBack - 1)->startOfYear();
            $compareYear = $baseDate->year;
            $compareMonth = $baseDate->month;
        } else {
            $thisYear = Carbon::today()->endOfYear();
            $lastYear = Carbon::today()->subYears($yearsBack - 1)->startOfYear();
            $compareYear = Carbon::today()->year;
            $compareMonth = Carbon::today()->month;
        }

        // "No data" and "zero machines" are NOT the same thing, and seeding every
        // month with 0 conflated them.
        //
        // vend_product_records starts 2025-05-27, so on the Lite page Jan-Apr 2025
        // have no rows at all — and the chart drew them as a line crashing to
        // zero, which reads as "the business operated no machines". vend_records
        // says those months actually had 413-437 sites. The same lie appears for
        // any month before a viewer's "Transaction Access From" cut-off.
        //
        // Emitting null instead makes Chart.js break the line (spanGaps defaults
        // to false), which is the honest rendering: we have nothing to say about
        // those months. sumData() in the Vue coerces null to 0, so the legend
        // total is unaffected either way.
        //
        // Months AFTER the floor keep their 0 — that is a real "nothing matched
        // your filters", which the user SHOULD see.
        $dataFloor = $this->rollupDataFloor();

        $activeMonths = [];
        foreach (range($lastYear->year, $thisYear->year) as $year) {
            for ($i = 1; $i <= 12; $i++) {
                if ($year == $compareYear && $i > $compareMonth) {
                    continue;
                }
                $activeMonths[$year][$i] = [
                    'month' => $i,
                    'month_name' => Carbon::createFromDate($year, $i, 1)->format('F'),
                    'year' => $year,
                    'count' => ($dataFloor !== null && sprintf('%04d-%02d', $year, $i) < $dataFloor)
                        ? null
                        : 0,
                ];
            }
        }

        // History must NOT be reshaped by a machine's CURRENT binding. Previously
        // this excluded any vend whose live customer_id IS NULL, which dropped a
        // machine that was bound+active in the past but is unbound today from
        // EVERY historical month — making this count line inconsistent with the
        // Sales-by-Month $ bars (which keep those rows). We now exclude only
        // testing machines (same population the sales chart uses); whether a vend
        // counts in a given month is decided purely by the frozen vend_records
        // rows for that month.
        $excludeVendIds = $testingVendIds;

        $cacheKey = $this->makeCacheKey('active_machine_graph', $request);
        $lite = $this->lite;
        $table = $this->recordTable();
        $activeMachineGraph = Cache::remember($cacheKey, 300, function () use ($request, $excludeVendIds, $lastYear, $thisYear, $lite, $table) {
            // Chart is "... Vending Machines (Site) in operation" — a SITE count.
            // Count DISTINCT customer_id, NOT vend_id: a physical machine that is
            // re-provisioned when moved to another site gets a new vends.id, so
            // COUNT(DISTINCT vend_id) would double-count that one unit as 2. Keying
            // on customer_id counts each site once (NULL customer_id rows — unbound
            // machines — are ignored by COUNT DISTINCT, matching "in operation").
            // whereNotIn('vend_id', ...) still strips testing machines' rows.
            // Note: idx_vr_monthly_summary covers vend_id, not customer_id, so this
            // COUNT DISTINCT may fall back to idx_operator_year_month — flag to brian
            // if an idx on (year, month, customer_id) is wanted for scan cost.
            //
            // DANGER: this is a raw DB::table() builder, so NO Eloquent global
            // scope runs on it — including ProductAccessProductColumnScope. On the
            // Lite page the restriction therefore has to be applied by hand below,
            // or the "machines in operation" line would count the WHOLE fleet for a
            // viewer who is only allowed to see their own SKUs. Every other query
            // on this page goes through Eloquent and is scoped automatically; this
            // one is the exception. Keep the ProductAccess call attached if you
            // ever touch this query.
            //
            return DB::table($table)
                ->selectRaw('year, month, COUNT(DISTINCT customer_id) as count')
                ->when($lite, fn($q) => ProductAccess::applyToColumn($q, $table . '.product_id'))
                // Same DANGER applies to "Transaction Access From": no Eloquent
                // scope reaches this builder, so TransactionAccessScope on
                // VendRecord/VendProductRecord does nothing here. Applied to BOTH
                // sources (not just lite) because both carry the date column and
                // both back this line. Keep this call attached too.
                ->tap(fn($q) => \App\Support\TransactionAccess::applyToColumn($q, $table . '.date'))
                // ...and the SAME is true of the machine allow-list. This was the
                // real leak: a user bound to a single machine saw the whole
                // fleet's site count on this line (350-385 instead of 1), because
                // OperatorVendRecordScope is an ELOQUENT scope and this is a raw
                // builder. Every other query on this page goes through
                // recordQuery() and is scoped automatically; this one is not.
                ->tap(fn($q) => $this->applyVendAccessToRawQuery($q, $table))
                ->whereBetween('year', [$lastYear->year, $thisYear->year])
                ->whereNotIn('vend_id', $excludeVendIds)
                ->when($request->operators, fn($q) => $q->whereIn('operator_id', $request->operators))
                ->when($request->codes && $request->has('_resolved_vend_ids'),
                    fn($q) => $q->whereIn('vend_id', $request->input('_resolved_vend_ids', [])))
                ->when($request->vendModels && !in_array('all', $request->vendModels),
                    fn($q) => $q->whereIn('vend_model_id', $request->vendModels))
                ->when($request->vendPrefixes && !in_array('all', $request->vendPrefixes),
                    fn($q) => $q->whereIn('vend_prefix_id', $request->vendPrefixes))
                ->when($request->locationType && $request->locationType !== 'all',
                    fn($q) => $q->where('location_type_id', $request->locationType))
                ->when($request->customer, fn($q) => $q->whereIn('customer_id', function ($subQ) use ($request) {
                    $subQ->select('id')
                        ->from('customers')
                        ->where('virtual_customer_prefix', 'LIKE', "{$request->customer}%")
                        ->orWhere('virtual_customer_code', 'LIKE', "{$request->customer}%")
                        ->orWhere('name', 'LIKE', "%{$request->customer}%");
                }))
                ->when($request->categories, fn($q) => $q->whereIn('customer_id', function ($subQ) use ($request) {
                    $subQ->select('id')->from('customers')->whereIn('category_id', $request->categories);
                }))
                ->when($request->categoryGroups, fn($q) => $q->whereIn('customer_id', function ($subQ) use ($request) {
                    $subQ->select('id')->from('customers')->whereIn('category_id', function ($catQ) use ($request) {
                        $catQ->select('id')->from('categories')->whereIn('category_group_id', $request->categoryGroups);
                    });
                }))
                ->when($request->is_binded_customer && $request->is_binded_customer !== 'all', function ($q) use ($request, $table) {
                    // Frozen historical binding (the rollup row's own customer_id),
                    // not the live vends.customer_id — see scopeFilterIndex for
                    // rationale.
                    if ($request->is_binded_customer === 'true') {
                        $q->whereNotNull($table . '.customer_id');
                    } else {
                        $q->whereNull($table . '.customer_id');
                    }
                })
                ->groupBy('year', 'month')
                ->orderBy('year', 'asc')
                ->orderBy('month', 'asc')
                ->get();
        }); // end Cache::remember for active_machine_graph

        foreach ($activeMachineGraph as $activeMachine) {
            $activeMonths[$activeMachine->year][$activeMachine->month]['count'] = $activeMachine->count;
        }

        return $activeMonths;
    }


    private function getMonthlyAnalytics(Request $request, $allMonths = null)
    {
        if ($request->month_year) {
            $baseDate = Carbon::createFromFormat('Y-m', $request->month_year);
            $monthlyDateFrom = $baseDate->copy()->startOfYear()->startOfDay();
            $monthlyDateTo = $baseDate->copy()->endOfYear()->endOfDay();
            $currentMonthNumber = $baseDate->month;
        } else {
            $monthlyDateFrom = Carbon::today()->startOfYear()->startOfDay();
            $monthlyDateTo = Carbon::today()->endOfYear()->endOfDay();
            $currentMonthNumber = Carbon::today()->month;
        }

        $request->merge([
            'monthlyDateFrom' => $monthlyDateFrom,
            'monthlyDateTo' => $monthlyDateTo,
            'monthlyTypeName' => $request->monthlyTypeName ?? 'location-type'
        ]);

        // Cache the expensive full-year double-join query for 5 minutes.
        // Use ->format() on the Carbon dates so microseconds don't break the key.
        // $this->lite is part of the key: Performance and Performance (Lite) run
        // the SAME method for the SAME user with the SAME filters but read
        // different tables, so without it the two pages would serve each other's
        // cached figures.
        $cacheKey = 'monthly_analytics_' . ($this->lite ? 'lite_' : '') . auth()->id() . '_' . md5(json_encode([
            $monthlyDateFrom->format('Y-m-d'),
            $monthlyDateTo->format('Y-m-d'),
            $request->monthlyTypeName,
            $request->operators,
            $request->locationType,
            $request->vendPrefixes,
            $request->customer,
        ]));

        $modelName = $this->getModelName($request->monthlyTypeName);
        $items = Cache::remember($cacheKey, 300, function () use ($request, $modelName) {
            return $this->getMonthlySalesQuery($request, $modelName)->get();
        });

        $monthsByModel = [];
        // keyBy() gives O(1) lookup — replaces the O(items × 12) nested foreach.
        // Use the pre-fetched $allMonths if available (avoids a duplicate DB query).
        $months = ($allMonths ?? Month::all())->keyBy('number');

        foreach ($items as $item) {
            $month = $months->get($item->month);
            if (!$month) {
                continue;
            }
            $entry = [
                'current' => $currentMonthNumber == $item->month,
                'month_short_name' => $month->short_name,
                'amount' => $item->amount ? $item->amount / 100 : 0,
                'vend_count' => $item->count ?? 0,
                'average' => $item->average ? $item->average / 100 : 0,
            ];
            if ($item->id) {
                $monthsByModel[$item->name][$item->month] = $entry;
            } else {
                $monthsByModel['Undefined'][$item->month] = $entry;
            }
        }

        return collect($monthsByModel)->sortKeys();
    }


    /**
     * Build a stable, user-scoped cache key from the active request filters.
     * Extra scalar values (e.g. a date range) can be passed in $extra.
     */
    private function makeCacheKey(string $name, Request $request, array $extra = []): string
    {
        // $this->lite MUST be in the key. Performance and Performance (Lite)
        // share these private methods, so the same user with the same filters
        // produces the same key on both pages — and would be served the other
        // page's (differently-sourced) numbers out of cache.
        return $name . '_' . ($this->lite ? 'lite_' : '') . auth()->id() . '_' . md5(json_encode(array_merge([
            $request->operators,
            $request->customer,
            $request->codes,
            $request->vendModels,
            $request->vendPrefixes,
            $request->locationType,
            $request->month_year,
            $request->years_back,
        ], $extra)));
    }

    private function getModelName($monthlyTypeName)
    {
        switch ($monthlyTypeName) {
            // case 'category':
            //     return 'categories';
            case 'location-type':
                return 'location_types';
            case 'operator':
                return 'operators';
            default:
                return 'location_types';
        }
    }

    // private function getMonthlySalesQuery($request, $className)
    // {
    //     $vendRecords = VendRecord::query()
    //         ->leftJoin('vends', 'vend_records.vend_id', '=', 'vends.id')
    //         ->leftJoin('customers', 'customers.id', '=', 'vend_records.customer_id')
    //         ->leftJoin('location_types', 'vend_records.location_type_id', '=', 'location_types.id')
    //         ->leftJoin('operators', 'operators.id', '=', 'vend_records.operator_id')
    //         ->whereBetween('vend_records.date', [Carbon::parse($request->monthlyDateFrom), Carbon::parse($request->monthlyDateTo)])
    //         ->filterIndex($request)
    //         ->whereNotIn('vend_records.vend_id', function ($query) {
    //             $query->select('id')->from('vends')->where('is_testing', true);
    //         })
    //         ->select('vend_records.date', DB::raw('COUNT(DISTINCT(vend_records.vend_id)) as count'));

    //     switch ($className) {
    //         case 'location_types':
    //             $vendRecords->selectRaw('location_types.id as id');
    //             break;
    //         case 'operators':
    //             $vendRecords->selectRaw('operators.id as id');
    //             break;
    //     }

    //     $vendRecords = $vendRecords->groupBy('id', 'vend_records.date');

    //     $query = VendRecord::query()
    //         ->leftJoin('vends', 'vend_records.vend_id', '=', 'vends.id')
    //         ->leftJoin('customers', 'customers.id', '=', 'vend_records.customer_id')
    //         ->leftJoin('location_types', 'vend_records.location_type_id', '=', 'location_types.id')
    //         // ->leftJoin('categories', 'categories.id', '=', 'customers.category_id')
    //         // ->leftJoin('category_groups', 'category_groups.id', '=', 'categories.category_group_id')
    //         ->leftJoin('operators', 'operators.id', '=', 'vend_records.operator_id')
    //         ->leftJoinSub($vendRecords, 'x', function ($join) use ($className) {
    //             switch ($className) {
    //                 case 'location_types':
    //                     $join->on('location_types.id', '=', 'x.id');
    //                     break;
    //                 case 'operators':
    //                     $join->on('operators.id', '=', 'x.id');
    //                     break;
    //             }
    //             $join->on('vend_records.date', '=', 'x.date');
    //         })
    //         ->whereBetween('vend_records.date', [Carbon::parse($request->monthlyDateFrom), Carbon::parse($request->monthlyDateTo)])
    //         ->filterIndex($request)
    //         ->whereNotIn('vend_records.vend_id', function ($query) {
    //             $query->select('id')->from('vends')->where('is_testing', true);
    //         });

    //     switch ($className) {
    //         case 'location_types':
    //             $query->selectRaw('location_types.id as id')->selectRaw('location_types.name as name');
    //             break;
    //         case 'operators':
    //             $query->selectRaw('operators.id as id')->selectRaw('operators.name as name');
    //             break;
    //     }

    //     $query
    //         // ->selectRaw('SUM(vend_records.total_count) AS count')
    //         ->selectRaw('SUM(vend_records.total_amount) AS amount')
    //         ->selectRaw('COUNT(DISTINCT(vend_records.vend_id)) AS vend_count')
    //         ->selectRaw('AVG(vend_records.total_amount) AS average')
    //         ->selectRaw('vend_records.month')
    //         ->selectRaw('ROUND(AVG(x.count), 2) AS count')
    //         ->groupBy('id', 'vend_records.month')
    //         ->orderBy('name', 'asc');

    //     return $query;
    // }

    private function getMonthlySalesQuery($request, $className)
    {
        $dateFrom = Carbon::parse($request->monthlyDateFrom);
        $dateTo = Carbon::parse($request->monthlyDateTo);

        $table = $this->recordTable();

        // Subquery: daily active vend count per id (location_type_id/operator) & date
        $dailyActive = $this->recordQuery(self::IDX_BY_MONTH)
            // Dedicated covering index for monthly-sales (see migration
            // 2026_07_01_020000). Other dashboard queries keep idx_operator_date_vend.
            ->selectRaw("{$table}.location_type_id as location_type_id")
            ->selectRaw("{$table}.operator_id as operator_id")
            ->selectRaw("{$table}.date as date")
            ->selectRaw("COUNT(DISTINCT {$table}.vend_id) as daily_active_count")
            ->leftJoin('vends as v2', function ($join) use ($table) {
                $join->on($table . '.vend_id', '=', 'v2.id')
                    ->where('v2.is_testing', true);
            })
            ->whereBetween($table . '.date', [$dateFrom, $dateTo])
            ->whereNull('v2.id') // replaces NOT IN for efficiency
            ->when($request->operators, fn($q) => $q->whereIn($table . '.operator_id', $request->operators))
            ->groupBy($table . '.date');

        if ($className === 'location_types') {
            $dailyActive->groupBy($table . '.location_type_id');
        } elseif ($className === 'operators') {
            $dailyActive->groupBy($table . '.operator_id');
        }

        $query = $this->recordQuery(self::IDX_BY_MONTH)
            // Dedicated covering index for monthly-sales (see migration
            // 2026_07_01_020000). Other dashboard queries keep idx_operator_date_vend.
            ->selectRaw("{$table}.month")
            ->selectRaw("SUM({$table}.total_amount) as amount")
            ->selectRaw("COUNT(DISTINCT {$table}.vend_id) as vend_count")
            ->leftJoin('vends as v2', function ($join) use ($table) {
                $join->on($table . '.vend_id', '=', 'v2.id')
                    ->where('v2.is_testing', true);
            })
            ->leftJoinSub($dailyActive, 'daily_active', function ($join) use ($className, $table) {
                $join->on($table . '.date', '=', 'daily_active.date');
                if ($className === 'location_types') {
                    $join->on($table . '.location_type_id', '=', 'daily_active.location_type_id');
                } elseif ($className === 'operators') {
                    $join->on($table . '.operator_id', '=', 'daily_active.operator_id');
                }
            })
            ->whereBetween($table . '.date', [$dateFrom, $dateTo])
            ->whereNull('v2.id') // replaces NOT IN
            ->when($request->operators, fn($q) => $q->whereIn($table . '.operator_id', $request->operators));

        // "Average" must mean the same thing on both pages, or the Lite figure is
        // not comparable to the one prod_owner sees quoted elsewhere.
        //
        // On vend_records a row IS a machine-day, so AVG(total_amount) is already
        // "average takings per machine per day". On vend_product_records a row is
        // a machine-day-PRODUCT, so the same AVG() would silently switch the
        // denominator to product-rows and report a much smaller number that looks
        // like a drop rather than a different metric. Dividing by
        // COUNT(DISTINCT vend_id, date) restores the machine-day denominator, so
        // Lite reads "average takings per machine per day, for my products".
        $query->selectRaw($this->lite
            ? "SUM({$table}.total_amount) / NULLIF(COUNT(DISTINCT {$table}.vend_id, {$table}.date), 0) as average"
            : "AVG({$table}.total_amount) as average");

        if ($className === 'location_types') {
            $query->leftJoin('location_types', $table . '.location_type_id', '=', 'location_types.id')
                ->selectRaw('location_types.id as id')
                ->selectRaw('location_types.name as name')
                ->groupBy('location_types.id', $table . '.month')
                ->orderBy('location_types.name', 'asc');
        } elseif ($className === 'operators') {
            $query->leftJoin('operators', $table . '.operator_id', '=', 'operators.id')
                ->selectRaw('operators.id as id')
                ->selectRaw('operators.name as name')
                ->groupBy('operators.id', $table . '.month')
                ->orderBy('operators.name', 'asc');
        }

        $query->selectRaw('ROUND(AVG(daily_active.daily_active_count), 2) as average_active_count');

        return $query;
    }

}
