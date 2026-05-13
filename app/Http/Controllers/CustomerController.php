<?php

namespace App\Http\Controllers;

use App\Http\Resources\CategoryGroupResource;
use App\Http\Resources\CategoryResource;
use App\Http\Resources\CountryResource;
use App\Http\Resources\CustomerPeriodSummaryResource;
use App\Http\Resources\CustomerResource;
use App\Http\Resources\LocationTypeResource;
use App\Http\Resources\OperatorResource;
use App\Http\Resources\PriceTemplateResource;
use App\Http\Resources\ProfileResource;
use App\Http\Resources\TagResource;
use App\Http\Resources\UserResource;
use App\Http\Resources\VendModelResource;
use App\Http\Resources\VendConfigResource;
use App\Http\Resources\VendPrefixResource;
use App\Http\Resources\ZoneResource;
use App\Jobs\SyncVendCustomerCms;
use App\Jobs\SyncTransactionItemCMS;
use App\Models\Category;
use App\Models\CategoryGroup;
use App\Models\Country;
use App\Models\Customer;
use App\Models\CustomerContractLog;
use App\Models\LocationType;
use App\Models\Operator;
use App\Models\OpsJobItem;
use App\Models\PriceTemplate;
use App\Models\Profile;
use App\Models\SellingPrice;
use App\Models\Tag;
use App\Models\User;
use App\Models\Vend;
use App\Models\VendConfig;
use App\Models\VendModel;
use App\Models\VendPrefix;
use App\Models\Zone;
use App\Services\HistoryService;
use App\Services\MapService;
use App\Services\PerformanceReportContentService;
use App\Services\TagBindingService;
use App\Traits\ExportOptimizationTrait;
use App\Traits\HasFilter;
use App\Traits\SearchAddress;
use Carbon\Carbon;
use DB;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Rap2hpoutre\FastExcel\FastExcel;

class CustomerController extends Controller
{
    use ExportOptimizationTrait, HasFilter, SearchAddress;

    protected $historyService;
    protected $mapService;

    public function __construct(HistoryService $historyService)
    {
        $this->historyService = $historyService;
        $this->mapService = new MapService();
    }

    public function index(Request $request)
    {
        $request->merge([
            'is_binded_vend' => $request->is_binded_vend ? $request->is_binded_vend : 'all',
            'is_cms' => $request->is_cms ? $request->is_cms : 'all',
            'is_active' => $request->is_active ? $request->is_active : 'true',
            'numberPerPage' => $request->numberPerPage ? $request->numberPerPage : 100,
            'sortKey' => $request->sortKey ? $request->sortKey : 'customers.id',
            'sortBy' => $request->sortBy ? $request->sortBy : 'false',
        ]);
        // if(!$request->operators) {
        //     if(auth()->user()->operator->code == 'HIPL') {
        //         $request->merge(['operators' => [auth()->user()->operator_id, Operator::where('code', 'HIMD')->first() ? Operator::where('code', 'HIMD')->first()->id : null]]);
        //     }else {
        //         $request->merge(['operators' => ['all']]);
        //     }
        // }
        $className = get_class(new Customer());

        $customers = Customer::with([
            'deliveryAddress',
            'tagBindings',
            'vend.productMapping:id,name',
            'vend.vendPrefix',
        ])
            ->leftJoin('addresses', function ($query) {
                $query->on('addresses.modelable_id', '=', 'customers.id')
                    ->where('addresses.modelable_type', '=', 'App\\Models\\Customer')
                    ->where('addresses.type', '=', 2)
                    ->limit(1);
            })
            ->leftJoin('operators', 'customers.operator_id', '=', 'operators.id')
            ->leftJoin('vends', 'vends.customer_id', '=', 'customers.id')
            ->leftJoin('product_mappings', 'product_mappings.id', '=', 'vends.product_mapping_id')
            ->leftJoin('zones', 'zones.id', '=', 'customers.zone_id')
            ->leftJoin(DB::raw('
                (
                    SELECT vend_id, SUM(amount * qty) AS total_stock_amount, SUM(amount * capacity) AS total_full_load_amount
                    FROM vend_channels
                    WHERE is_active = true
                    AND capacity > 0
                    GROUP BY vend_id
                ) AS vc
            '), 'vc.vend_id', '=', 'vends.id')
            ->select(
                'addresses.postcode as postcode',
                'customers.*',
                'customers.id',
                'customers.begin_date as begin_date',
                'customers.frequency_per_week_status',
                'customers.operator_id',
                'customers.preferred_visit_days_json',
                'customers.zone_id',
                'operators.code as operator_code',
                'operators.name as operator_name',
                'product_mappings.name AS product_mapping_name',
                'vc.total_full_load_amount',
                'vends.code as vend_code',
                'zones.name as zone_name',
                DB::raw('
                    (JSON_UNQUOTE(JSON_EXTRACT(customers.totals_json, "$.vend_records_thirty_days_amount_average")) *30 /100)/
                    (vc.total_full_load_amount / 100) AS thirty_days_over_full_load_ratio
                ')
            )
            ->filterIndex($request);

        $customers = $this->filterOperator($customers);

        $customers = $customers
            ->paginate(
                $request->numberPerPage === 'All' ?
                10000 :
                $request->numberPerPage
            )
            ->withQueryString();

        // Use OptionsService to load all dropdown options
        $optionsService = app(\App\Services\OptionsService::class);

        return Inertia::render('Customer/Index', [
            'customers' => CustomerResource::collection(
                $customers
            ),
            'categories' => $optionsService->categories($className),
            'categoryGroups' => $optionsService->categoryGroups($className),
            'cmsEndpoint' => env('CMS_URL'),
            'days' => Customer::DAYS_MAPPING,
            'frequencyPerWeekOptions' => Customer::FREQUENCY_PER_WEEK_STATUSES_MAPPING,
            'locationTypeOptions' => $optionsService->locationTypes(),
            'mapApiKey' => $this->mapService->getMapApiKeyByUser(auth()->user()),
            'operatorOptions' => $optionsService->operators(),

            'profiles' => $optionsService->profiles(),
            'sellingPriceTypeOptions' => SellingPrice::TYPE_MAPPINGS,
            'statuses' => [
                [
                    'id' => 'all',
                    'name' => 'All',
                ],
                ...collect(Customer::STATUSES_MAPPING)->map(function ($status, $index) {
                    return [
                        'id' => $index,
                        'name' => $status,
                    ];
                })
            ],
            'tags' => $optionsService->tags($className),
            'users' => $optionsService->users(),
            'vendConfigOptions' => VendConfigResource::collection(
                VendConfig::orderBy('name')->get()
            ),
            'vendModelOptions' => $optionsService->vendModels(),
            'vendPrefixOptions' => $optionsService->vendPrefixes(),
            'zoneOptions' => $optionsService->zones(),
        ]);
    }

    /**
     * Customer Management > Summary subtab.
     *
     * Reads pre-aggregated rows from customer_period_summaries (refreshed
     * nightly by ProcessCustomerSummaryMonth) and applies the same
     * customer-level filters as the Customer Index page, plus a Period Report
     * filter — one of:
     *   current
     *   last_1_month, last_2_months, last_3_months, last_6_months,
     *   last_12_months, last_24_months, last_36_months
     *   all
     */
    public function summary(Request $request)
    {
        // Capture the summary-page sort fields BEFORE merging defaults — these
        // belong to customer_period_summaries (year_month, sales_cents, ...).
        // We must NOT let them flow into Customer::filterIndex(), which would
        // try to ORDER BY those columns on the customers table.
        $summarySortKey = $request->sortKey ?: 'year_month';
        $summarySortBy = $request->sortBy ?: 'false';

        $request->merge([
            'is_binded_vend' => $request->is_binded_vend ? $request->is_binded_vend : 'all',
            'is_cms' => $request->is_cms ? $request->is_cms : 'all',
            'is_active' => $request->is_active ? $request->is_active : 'true',
            'numberPerPage' => $request->numberPerPage ? $request->numberPerPage : 100,
            'period_report' => $request->period_report ?: 'current',
            // Strip sortKey/sortBy so filterIndex doesn't apply them to the
            // customers table. We'll apply them to the summaries query below.
            'sortKey' => null,
            'sortBy' => null,
        ]);

        // Resolve period range based on period_report option.
        // Current         : just the current calendar month (one row per customer)
        // Last N month(s) : current (in-progress) month + the N most recent
        //                   finished months, so the latest (possibly
        //                   incomplete) month sits at the top of each
        //                   customer's group.
        // All             : earliest known month → current month inclusive
        $today = \Carbon\Carbon::today();
        $currentMonthStart = $today->copy()->startOfMonth();

        [$rangeStart, $rangeEnd] = $this->resolvePeriodReportRange(
            $request->period_report,
            $currentMonthStart
        );

        // Reuse the Customer Index filter scope to determine which customers
        // qualify, then join the pre-aggregated summary rows for the period.
        $customerIdsQuery = Customer::query()
            ->select('customers.id')
            ->leftJoin('addresses', function ($q) {
                $q->on('addresses.modelable_id', '=', 'customers.id')
                    ->where('addresses.modelable_type', '=', 'App\\Models\\Customer')
                    ->where('addresses.type', '=', 2)
                    ->limit(1);
            })
            ->leftJoin('vends', 'vends.customer_id', '=', 'customers.id')
            ->filterIndex($request);

        $customerIdsQuery = $this->filterOperator($customerIdsQuery);
        // Summary-only filter: Placement Contract Type. Accepts an array of
        // codes (F, S, R, U, PS, PS+U, PSORU). 'all' (or absent) = no filter.
        $this->applyContractCommissionTypeFilter($customerIdsQuery, $request);
        $customerIds = $customerIdsQuery->pluck('customers.id')->unique()->values();

        // Sortable columns. machine_id / machine_prefix sort by the customer's
        // latest-bound vend (resolved via scalar subqueries below).
        $sortKey = in_array($summarySortKey, [
            'year_month', 'sales_cents', 'gross_earning_cents',
            'location_fees_cents', 'location_earning_cents', 'location_earning_rate',
            'transaction_count', 'customer_id',
            'machine_id', 'machine_prefix',
            'contract_commission_type', 'contract_commission_value',
        ], true) ? $summarySortKey : 'year_month';
        $sortDirection = filter_var($summarySortBy, FILTER_VALIDATE_BOOLEAN) ? 'asc' : 'desc';

        // ALWAYS render one row per (customer, year_month) — even when the
        // user picked a multi-month "Last N months" / "All" filter.
        //
        // We used to SUM into a single roll-up row per customer for those
        // filters, but that's incompatible with the per-month invoice +
        // snapshot-lock design: each month gets its own CMS transaction,
        // its own locked numbers, its own re-create. Returning a single
        // SUM'd row would have to either hide all of that or arbitrarily
        // pick one month's invoice to represent the lot.
        //
        // Multi-month is now a "show me a row per month" experience.
        // isAggregated is still tracked so we know to force customer
        // clustering on the sort.
        $isAggregated = $this->isAggregatedPeriodReport($request->period_report);

        $eagerLoads = [
            'customer:id,name,code,virtual_customer_code,virtual_customer_prefix,person_id,operator_id,selling_price_type,is_active,location_type_id,contract_commission_type,contract_commission_value,contract_commission_value2,contract_ps_term,begin_date,termination_date,report_email,is_report_email_enabled,location_grading_placement,location_grading_access,location_grading_flexibility,contract_until,contract_auto_renewal,contract_notice_period',
            'customer.operator:id,code,name',
            'customer.tagBindings.tag:id,name',
            'customer.deliveryAddress',
            'customer.locationType:id,name',
            'customer.vend:id,customer_id,code,vend_prefix_id',
            'customer.vend.vendPrefix:id,name',
            // All vends bound to the customer — used to expand the "+N more"
            // hint into a line-broken list (ascending) in the Vend ID column.
            'customer.vends:id,customer_id,code,vend_prefix_id',
            'customer.vends.vendPrefix:id,name',
            // Contract attachments — drives the "Contract Attachment"
            // hyperlink in the Customer column on the Summary page. The
            // relation already orders DESC by created_at (latest() in
            // Customer::contracts), so the first row is the most recent.
            'customer.contracts',
        ];

        $summariesQuery = \App\Models\CustomerPeriodSummary::query()
            ->with($eagerLoads)
            ->whereIn('customer_id', $customerIds)
            ->whereBetween('year_month', [$rangeStart->toDateString(), $rangeEnd->toDateString()]);

        // For machine_id / machine_prefix sorting, attach a scalar subquery
        // that picks the latest-bound vend per customer (mirrors the
        // Customer::vend() relation: latest by begin_date, then created_at).
        if ($isAggregated) {
            // Multi-month view: cluster a customer's months together so the
            // "Accumulate / Customer Tag" first-row-per-customer rendering
            // on the Vue side stays unambiguous, regardless of what column
            // the user sorted by. customer_id is primary; the user-picked
            // sortKey becomes secondary within each customer's group.
            $summariesQuery->orderBy('customer_id', 'asc');
        }

        if ($sortKey === 'machine_id') {
            $summariesQuery->orderByRaw(
                '(SELECT v.code FROM vends v
                  WHERE v.customer_id = customer_period_summaries.customer_id
                  ORDER BY v.begin_date DESC, v.created_at DESC LIMIT 1) ' . $sortDirection
            );
        } elseif ($sortKey === 'machine_prefix') {
            $summariesQuery->orderByRaw(
                '(SELECT vp.name FROM vends v
                  LEFT JOIN vend_prefixes vp ON vp.id = v.vend_prefix_id
                  WHERE v.customer_id = customer_period_summaries.customer_id
                  ORDER BY v.begin_date DESC, v.created_at DESC LIMIT 1) ' . $sortDirection
            );
        } else {
            $summariesQuery->orderBy($sortKey, $sortDirection);
        }

        // Final tie-breaker: customer_id (in case the multi-month branch
        // above didn't already apply it, e.g. single-month "current" view)
        // + year_month DESC so a customer's most recent month sits at the
        // top of their cluster.
        if (!$isAggregated) {
            $summariesQuery->orderBy('customer_id', 'asc');
        }
        $summariesQuery->orderBy('year_month', 'desc');

        $summaries = $summariesQuery;

        $summaries = $summaries
            ->paginate(
                $request->numberPerPage === 'All' ? 10000 : (int) $request->numberPerPage
            )
            ->withQueryString();

        // (clampAggregatedPeriodBounds is no longer needed — multi-month
        // period reports now return one row per (customer, year_month),
        // so period_start / period_end on each row already reflect the
        // stored month's bounds exactly.)

        // "Accumulate Vending Earning" — lifetime sum of location_earning_cents
        // (= gross_earning - location_fees) for each customer up to and
        // including the latest month visible on this view ($rangeEnd). One
        // batched query per page keeps it cheap.
        $this->attachAccumulatedVendingEarning($summaries->getCollection(), $rangeEnd);

        // Attach the latest API Invoice (if any) for each visible row's
        // (customer, period_start, period_end) triple — drives the
        // "API Rpt" badge and the per-row Create-button visibility on the
        // Customer Summary page.
        $this->attachExistingInvoice($summaries->getCollection());

        // Aggregate totals — summed across the FULL filtered set (not just
        // the paginated rows visible on this page) so the 4 boxes above the
        // table (Total Sales / Gross Earning / Location Fees / Vend Earnings)
        // reflect every row matching the current filters. Same WHERE clause
        // as the listing query so the numbers stay in lockstep with the
        // table. Cents-typed; the Vue side runs them through formatMoney().
        $totalsRow = \App\Models\CustomerPeriodSummary::query()
            ->whereIn('customer_id', $customerIds)
            ->whereBetween('year_month', [$rangeStart->toDateString(), $rangeEnd->toDateString()])
            ->selectRaw('
                COALESCE(SUM(sales_cents), 0) AS sales_cents,
                COALESCE(SUM(gross_earning_cents), 0) AS gross_earning_cents,
                COALESCE(SUM(location_fees_cents), 0) AS location_fees_cents,
                COALESCE(SUM(location_earning_cents), 0) AS location_earning_cents
            ')
            ->first();
        $totals = [
            'sales_cents' => (int) ($totalsRow->sales_cents ?? 0),
            'gross_earning_cents' => (int) ($totalsRow->gross_earning_cents ?? 0),
            'location_fees_cents' => (int) ($totalsRow->location_fees_cents ?? 0),
            'location_earning_cents' => (int) ($totalsRow->location_earning_cents ?? 0),
        ];

        $className = get_class(new Customer());
        $optionsService = app(\App\Services\OptionsService::class);

        return Inertia::render('Customer/Summary', [
            'summaries' => CustomerPeriodSummaryResource::collection($summaries),
            'totals' => $totals,
            'periodReport' => $request->period_report,
            'periodReportOptions' => $this->periodReportOptions(),
            'rangeStart' => $rangeStart->toDateString(),
            'rangeEnd' => $rangeEnd->copy()->endOfMonth()->toDateString(),
            'cmsEndpoint' => env('CMS_URL'),
            'locationTypeOptions' => $optionsService->locationTypes(),
            'mapApiKey' => $this->mapService->getMapApiKeyByUser(auth()->user()),
            'operatorOptions' => $optionsService->operators(),
            'tags' => $optionsService->tags($className),
            'vendPrefixOptions' => $optionsService->vendPrefixes(),
            // Placement Contract Type options for the new filter dropdown.
            // Order matches Customer/Edit.vue's commissionTypeOptions so the
            // labels stay consistent across the app.
            'contractCommissionTypeOptions' => [
                ['id' => 'F',     'value' => 'Free Placement'],
                ['id' => 'S',     'value' => 'Subsidized Plan'],
                ['id' => 'R',     'value' => 'Fix Rental'],
                ['id' => 'U',     'value' => 'Utility only'],
                ['id' => 'PS',    'value' => 'PS'],
                ['id' => 'PS+U',  'value' => 'PS + U'],
                ['id' => 'PSORU', 'value' => 'PS OR U'],
            ],
        ]);
    }

    /**
     * For aggregated Summary rows ("Last 12 months" / "All"), replace the
     * stored MIN(period_start) / MAX(period_end) with the actual reporting
     * window. Clamp by the customer's begin_date / termination_date so:
     *   - a customer who started mid-window shows their begin_date
     *   - a customer who terminated mid-window shows their termination_date
     */
    protected function clampAggregatedPeriodBounds($collection, \Carbon\Carbon $rangeStart, \Carbon\Carbon $rangeEnd): void
    {
        $rangeEndEffective = $rangeEnd->copy()->endOfMonth()->startOfDay();

        foreach ($collection as $row) {
            $cust = $row->customer ?? null;

            $start = $rangeStart->copy();
            if ($cust && $cust->begin_date) {
                $beginDate = $cust->begin_date instanceof \Carbon\Carbon
                    ? $cust->begin_date->copy()
                    : \Carbon\Carbon::parse($cust->begin_date);
                if ($beginDate->gt($start)) {
                    $start = $beginDate;
                }
            }

            $end = $rangeEndEffective->copy();
            if ($cust && $cust->termination_date) {
                $termDate = $cust->termination_date instanceof \Carbon\Carbon
                    ? $cust->termination_date->copy()
                    : \Carbon\Carbon::parse($cust->termination_date);
                if ($termDate->lt($end)) {
                    $end = $termDate;
                }
            }

            $row->period_start = $start->toDateString();
            $row->period_end = $end->toDateString();
        }
    }

    /**
     * Period Report options for the Summary page dropdown. Single source of
     * truth for the labels/ids used by both the on-screen filter and the
     * controller's period range resolver.
     *
     * @return array<int,array{id:string,value:string}>
     */
    protected function periodReportOptions(): array
    {
        return [
            ['id' => 'current',         'value' => 'Current'],
            ['id' => 'last_1_month',    'value' => 'Last month'],
            ['id' => 'last_2_months',   'value' => 'Last 2 months'],
            ['id' => 'last_3_months',   'value' => 'Last 3 months'],
            ['id' => 'last_6_months',   'value' => 'Last 6 months'],
            ['id' => 'last_12_months',  'value' => 'Last 12 months'],
            ['id' => 'last_24_months',  'value' => 'Last 24 months'],
            ['id' => 'last_36_months',  'value' => 'Last 36 months'],
            ['id' => 'all',             'value' => 'All'],
        ];
    }

    /**
     * Map a `last_N_month(s)` id to its month count. Returns null for ids that
     * aren't of that shape (e.g. `current`, `all`).
     */
    protected function periodReportMonthsBack(?string $id): ?int
    {
        $map = [
            'last_1_month'   => 1,
            'last_2_months'  => 2,
            'last_3_months'  => 3,
            'last_6_months'  => 6,
            'last_12_months' => 12,
            'last_24_months' => 24,
            'last_36_months' => 36,
        ];
        return $map[$id] ?? null;
    }

    /**
     * Resolve the [rangeStart, rangeEnd] anchor months for a given
     * period_report id. Both bounds are first-of-month dates.
     *
     *   current         → rangeStart = rangeEnd = current month start
     *   last_N_month(s) → rangeEnd = current (in-progress) month;
     *                     rangeStart = current month - N months. Yields
     *                     N+1 month rows so the latest (possibly
     *                     incomplete) month appears at the top of each
     *                     customer's group.
     *   all             → rangeStart = earliest known year_month; rangeEnd = current month
     *   anything else   → falls back to "current"
     *
     * @return array{0:\Carbon\Carbon,1:\Carbon\Carbon}
     */
    protected function resolvePeriodReportRange(?string $id, \Carbon\Carbon $currentMonthStart): array
    {
        $monthsBack = $this->periodReportMonthsBack($id);
        if ($monthsBack !== null) {
            // Include the current (still-in-progress) month at the top of
            // each customer's group, then walk back N finished months.
            $rangeEnd = $currentMonthStart->copy();                              // current month (incomplete)
            $rangeStart = $rangeEnd->copy()->subMonthsNoOverflow($monthsBack);   // N months back from current
            return [$rangeStart->startOfMonth(), $rangeEnd->startOfMonth()];
        }

        if ($id === 'all') {
            $earliest = \Illuminate\Support\Facades\DB::table('customer_period_summaries')->min('year_month');
            $rangeStart = $earliest
                ? \Carbon\Carbon::parse($earliest)->startOfMonth()
                : $currentMonthStart->copy();
            return [$rangeStart, $currentMonthStart->copy()];
        }

        // 'current' (and any unknown value) — single-month window for the
        // in-progress month.
        return [$currentMonthStart->copy(), $currentMonthStart->copy()];
    }

    /**
     * Whether the supplied period_report id should produce ONE aggregated row
     * per customer (true) instead of one row per stored month (false). Every
     * option except 'current' aggregates.
     */
    protected function isAggregatedPeriodReport(?string $id): bool
    {
        return $id !== null && $id !== 'current';
    }

    /**
     * Apply the Summary page's "Placement Contract Type" filter to a
     * customers-table query. Accepts the request param `contract_commission_types`
     * as either an array of codes (F, S, R, U, PS, PS+U, PSORU) or a single
     * scalar; the value 'all' (or an array containing it) is treated as
     * "no filter" so the default selection still returns every customer.
     *
     * Whitelists incoming codes against the canonical set so callers can't
     * inject arbitrary values.
     */
    protected function applyContractCommissionTypeFilter($query, Request $request): void
    {
        $raw = $request->input('contract_commission_types');
        if ($raw === null || $raw === '' || $raw === 'all') {
            return;
        }
        $values = is_array($raw) ? $raw : [$raw];
        if (in_array('all', $values, true)) {
            return;
        }

        $allowed = ['F', 'S', 'R', 'U', 'PS', 'PS+U', 'PSORU'];
        $codes = array_values(array_intersect($values, $allowed));
        if (empty($codes)) {
            return;
        }

        $query->whereIn('customers.contract_commission_type', $codes);
    }

    /**
     * Attach "Accumulate Vend Earning" to each summary row — now computed
     * PER ROW as the running prefix sum through that row's own year_month
     * (instead of a single lifetime-to-date figure per customer).
     *
     * Vending Earning = Gross Earning - Location Fees, already pre-computed
     * per-month and stored on customer_period_summaries.location_earning_cents
     * (see CustomerSummaryAggregator::persistMonth()).
     *
     * So:
     *   row for 2024-03 → SUM(location_earning_cents) over all months ≤ 2024-03
     *   row for 2024-02 → SUM(location_earning_cents) over all months ≤ 2024-02
     *   row for 2024-01 → SUM(location_earning_cents) over all months ≤ 2024-01
     *
     * The most recent visible month's row inherits the current month's
     * still-accumulating numbers (CustomerSummaryAggregator keeps the
     * current month's row fresh to "as_of_date"), so the top row stays
     * "up to date" without any extra work here.
     *
     * Cost: one batched query per page pulling every (customer_id, year_month,
     * location_earning_cents) tuple up to the page's latest visible month.
     * We sort ascending in-memory and build the prefix sum once.
     * Customers without any matching monthly rows fall back to 0 so the
     * UI can format consistently.
     *
     * NOTE: $accumulateThrough is retained as an upper bound so we don't
     * sum months *after* the visible window (e.g. future "All" filter
     * shouldn't pull months that haven't been aggregated yet). For per-row
     * lookup we still key by the row's own year_month.
     */
    protected function attachAccumulatedVendingEarning($collection, \Carbon\Carbon $accumulateThrough): void
    {
        if ($collection->isEmpty()) {
            return;
        }

        $customerIds = $collection->pluck('customer_id')->filter()->unique()->values()->all();
        if (empty($customerIds)) {
            return;
        }

        $through = $accumulateThrough->copy()->startOfMonth()->toDateString();

        // Pull every monthly row for these customers up to the page's
        // latest visible month, ordered ascending so a single linear pass
        // builds the prefix sum keyed by (customer_id, year_month).
        $monthlyRows = \Illuminate\Support\Facades\DB::table('customer_period_summaries')
            ->select('customer_id', 'year_month', 'location_earning_cents')
            ->whereIn('customer_id', $customerIds)
            ->where('year_month', '<=', $through)
            ->orderBy('customer_id')
            ->orderBy('year_month')
            ->get();

        // running[$customer_id][$yearMonthYmd] = cumulative location_earning_cents
        // through and including that month.
        $running = [];
        $perCustomerSum = [];
        foreach ($monthlyRows as $r) {
            $cid = $r->customer_id;
            // year_month is a DATE column; raw DB::table returns it as a
            // string (e.g. "2024-03-01" or "2024-03-01 00:00:00"). Carbon
            // normalises both to YYYY-MM-DD so it lines up with the row's
            // own ->year_month->toDateString() below.
            $key = \Carbon\Carbon::parse($r->year_month)->toDateString();
            $perCustomerSum[$cid] = ($perCustomerSum[$cid] ?? 0) + (int) $r->location_earning_cents;
            $running[$cid][$key] = $perCustomerSum[$cid];
        }

        foreach ($collection as $row) {
            $rowYm = optional($row->year_month)->toDateString();
            $row->accumulate_vending_earning_cents = (int) ($running[$row->customer_id][$rowYm] ?? 0);
        }
    }

    /**
     * Look up the latest customer_period_summary_invoices row for each
     * (customer, period_start, period_end) triple visible on the page,
     * and attach a compact summary onto the row so the Vue page can:
     *   - render the "API Rpt" badge with a transaction id
     *   - decide whether the per-row Create-button should fire a confirm
     *
     * One batched query per page keeps this cheap. We only consider rows
     * that successfully resolved a CMS transaction (cms_transaction_id IS
     * NOT NULL) — in-flight rows that haven't yet succeeded shouldn't
     * gate the button.
     */
    protected function attachExistingInvoice($collection): void
    {
        if ($collection->isEmpty()) {
            return;
        }

        // Build the (customer_id, period_start, period_end) tuples we need
        // to look up. Convert Carbon dates to ISO strings for the WHERE.
        $tuples = [];
        foreach ($collection as $row) {
            $cid = $row->customer_id;
            $ps = $row->period_start instanceof \Carbon\Carbon
                ? $row->period_start->toDateString()
                : (string) $row->period_start;
            $pe = $row->period_end instanceof \Carbon\Carbon
                ? $row->period_end->toDateString()
                : (string) $row->period_end;
            $tuples[$cid][$ps . '|' . $pe] = true;
        }

        if (empty($tuples)) {
            return;
        }

        $customerIds = array_keys($tuples);

        // Pull the most recent (by id desc) invoice per (customer, period)
        // tuple in a single query, then index by composite key for O(1)
        // lookup back into the collection. Using a window function would be
        // tidier on MySQL 8+, but the DB version isn't guaranteed app-wide
        // — so we fall back to a per-customer max(id) groupby join.
        $latestIds = \App\Models\CustomerPeriodSummaryInvoice::query()
            ->whereIn('customer_id', $customerIds)
            ->whereNotNull('cms_transaction_id')
            ->selectRaw('MAX(id) AS id')
            ->groupBy('customer_id', 'period_start', 'period_end')
            ->pluck('id');

        if ($latestIds->isEmpty()) {
            return;
        }

        $invoices = \App\Models\CustomerPeriodSummaryInvoice::query()
            ->whereIn('id', $latestIds)
            ->get([
                'id', 'customer_id', 'period_start', 'period_end',
                'cms_transaction_id', 'cms_transaction_at',
                'total_amount_cents', 'summary_snapshot',
            ])
            ->keyBy(function ($inv) {
                return $inv->customer_id
                    . '|' . $inv->period_start->toDateString()
                    . '|' . $inv->period_end->toDateString();
            });

        foreach ($collection as $row) {
            $ps = $row->period_start instanceof \Carbon\Carbon
                ? $row->period_start->toDateString()
                : (string) $row->period_start;
            $pe = $row->period_end instanceof \Carbon\Carbon
                ? $row->period_end->toDateString()
                : (string) $row->period_end;
            $key = $row->customer_id . '|' . $ps . '|' . $pe;
            if (isset($invoices[$key])) {
                $inv = $invoices[$key];
                $row->existing_invoice = [
                    'id' => $inv->id,
                    'cms_transaction_id' => $inv->cms_transaction_id,
                    'cms_transaction_at' => optional($inv->cms_transaction_at)->toIso8601String(),
                    'total_amount_cents' => (int) ($inv->total_amount_cents ?? 0),
                ];

                // Snapshot override: once an invoice is on file for this
                // (customer, month), the Customer Summary page must render
                // the FROZEN values that were sent to CMS — even if a
                // later backfill changed the live customer_period_summaries
                // row. Sorts/filters still run on the live DB columns
                // (we only mutate the in-memory model for serialization).
                $snap = $inv->summary_snapshot;
                if (is_array($snap)) {
                    if (array_key_exists('sales_cents', $snap)) {
                        $row->sales_cents = (int) $snap['sales_cents'];
                    }
                    if (array_key_exists('gross_earning_cents', $snap)) {
                        $row->gross_earning_cents = (int) $snap['gross_earning_cents'];
                    }
                    if (array_key_exists('location_fees_cents', $snap)) {
                        $row->location_fees_cents = (int) $snap['location_fees_cents'];
                    }
                    if (array_key_exists('location_earning_cents', $snap)) {
                        $row->location_earning_cents = (int) $snap['location_earning_cents'];
                    }
                    if (array_key_exists('location_earning_rate', $snap)) {
                        $row->location_earning_rate = (float) $snap['location_earning_rate'];
                    }
                    if (array_key_exists('contract_commission_type', $snap)) {
                        $row->contract_commission_type = $snap['contract_commission_type'];
                    }
                    if (array_key_exists('contract_commission_value', $snap)) {
                        $row->contract_commission_value = $snap['contract_commission_value'];
                    }
                    if (array_key_exists('contract_commission_value2', $snap)) {
                        $row->contract_commission_value2 = $snap['contract_commission_value2'];
                    }
                    if (array_key_exists('contract_ps_term', $snap)) {
                        $row->contract_ps_term = $snap['contract_ps_term'];
                    }
                }
            } else {
                $row->existing_invoice = null;
            }
        }
    }

    /**
     * Export the Customer Management > Summary table to .xlsx.
     *
     * Reuses the same filter + period resolution as summary(); streams rows
     * out via FastExcel with a generator so memory stays flat.
     */
    public function summaryExportExcel(Request $request)
    {
        $request->merge([
            'is_binded_vend' => $request->is_binded_vend ? $request->is_binded_vend : 'all',
            'is_cms' => $request->is_cms ? $request->is_cms : 'all',
            'is_active' => $request->is_active ? $request->is_active : 'true',
            'period_report' => $request->period_report ?: 'current',
            // sortKey/sortBy don't apply to the Customer filter scope here.
            'sortKey' => null,
            'sortBy' => null,
        ]);

        // Period range — same logic as summary().
        $today = \Carbon\Carbon::today();
        $currentMonthStart = $today->copy()->startOfMonth();

        [$rangeStart, $rangeEnd] = $this->resolvePeriodReportRange(
            $request->period_report,
            $currentMonthStart
        );

        // Resolve qualifying customer IDs through the Customer Index filters.
        $customerIdsQuery = Customer::query()
            ->select('customers.id')
            ->leftJoin('addresses', function ($q) {
                $q->on('addresses.modelable_id', '=', 'customers.id')
                    ->where('addresses.modelable_type', '=', 'App\\Models\\Customer')
                    ->where('addresses.type', '=', 2)
                    ->limit(1);
            })
            ->leftJoin('vends', 'vends.customer_id', '=', 'customers.id')
            ->filterIndex($request);
        $customerIdsQuery = $this->filterOperator($customerIdsQuery);
        // Mirror summary()'s Placement Contract Type filter so the export
        // honours the same dropdown selection.
        $this->applyContractCommissionTypeFilter($customerIdsQuery, $request);
        $customerIds = $customerIdsQuery->pluck('customers.id')->unique()->values();

        // Mirror summary()'s per-month grain in the export — one row per
        // (customer, year_month) regardless of period_report. The user
        // can collapse / pivot in Excel themselves if they want a
        // roll-up.
        $eagerLoads = [
            'customer:id,name,code,virtual_customer_code,virtual_customer_prefix,operator_id,selling_price_type,location_type_id,begin_date,termination_date',
            'customer.operator:id,code,name',
            'customer.tagBindings.tag:id,name',
            'customer.deliveryAddress',
            'customer.locationType:id,name',
            'customer.vend:id,customer_id,code,vend_prefix_id',
            'customer.vend.vendPrefix:id,name',
        ];

        $query = \App\Models\CustomerPeriodSummary::query()
            ->with($eagerLoads)
            ->whereIn('customer_id', $customerIds)
            ->whereBetween('year_month', [$rangeStart->toDateString(), $rangeEnd->toDateString()])
            ->orderBy('customer_id', 'asc')
            ->orderBy('year_month', 'desc');

        $operatorCountry = auth()->user()->operator->country ?? null;
        $divisor = pow(10, $operatorCountry?->currency_exponent ?? 2);
        $currencySymbol = $operatorCountry?->currency_symbol ?? '$';

        $contractTypeLabels = [
            'F'     => 'Free Placement',
            'S'     => 'Subsidized Plan',
            'R'     => 'Fix Rental',
            'U'     => 'Utility Only',
            'PS'    => 'Profit Sharing Only',
            'PS+U'  => 'PS + Utility',
            'PSORU' => 'PS OR Utility (whichever higher)',
        ];

        $formatLocationFeesRate = function ($row) {
            $type = $row->contract_commission_type;
            if (!$type) return '';
            $val = $row->contract_commission_value;
            $val2 = $row->contract_commission_value2;
            $psTerm = $row->contract_ps_term;
            if (in_array($type, ['PS', 'PS+U', 'PSORU'], true)) {
                $base = $val !== null ? rtrim(rtrim(number_format((float) $val, 2, '.', ''), '0'), '.') . '%' : '';
                if (in_array($type, ['PS+U', 'PSORU'], true) && $val2 !== null) {
                    $base .= ' + $' . rtrim(rtrim(number_format((float) $val2, 2, '.', ''), '0'), '.');
                }
                if ($psTerm !== null) {
                    $base .= ' (PS Term ' . rtrim(rtrim(number_format((float) $psTerm, 2, '.', ''), '0'), '.') . '%)';
                }
                return $base;
            }
            return $val !== null ? '$' . number_format((float) $val, 2) : '';
        };

        // Pre-fetch lifetime "Accumulate Vending Earning" per customer so the
        // streamed exporter can resolve it via O(1) map lookup instead of
        // running a per-row aggregate query. Vending Earning =
        // gross_earning_cents - location_fees_cents (already pre-computed and
        // stored as location_earning_cents on each monthly summary row).
        $accumThrough = $rangeEnd->copy()->startOfMonth()->toDateString();
        $accumulateMap = \Illuminate\Support\Facades\DB::table('customer_period_summaries')
            ->selectRaw('customer_id, SUM(location_earning_cents) AS accum')
            ->whereIn('customer_id', $customerIds)
            ->where('year_month', '<=', $accumThrough)
            ->groupBy('customer_id')
            ->pluck('accum', 'customer_id');

        // Pre-fetch the latest API Invoice snapshot per (customer, period)
        // tuple so locked rows export their FROZEN values — same rule the
        // on-screen view follows. One indexed query covers the page.
        $latestInvoiceIds = \App\Models\CustomerPeriodSummaryInvoice::query()
            ->whereIn('customer_id', $customerIds)
            ->whereNotNull('cms_transaction_id')
            ->selectRaw('MAX(id) AS id')
            ->groupBy('customer_id', 'period_start', 'period_end')
            ->pluck('id');
        $invoiceSnapshots = \App\Models\CustomerPeriodSummaryInvoice::query()
            ->whereIn('id', $latestInvoiceIds)
            ->get(['customer_id', 'period_start', 'period_end', 'cms_transaction_id', 'summary_snapshot'])
            ->keyBy(function ($inv) {
                return $inv->customer_id
                    . '|' . $inv->period_start->toDateString()
                    . '|' . $inv->period_end->toDateString();
            });

        $rowIndex = 0;

        return (new FastExcel($this->exportWithCursor($query)))->download(
            $this->formatExportFilename('CustomersSummary', 'xlsx'),
            function ($row) use (&$rowIndex, $divisor, $currencySymbol, $contractTypeLabels, $formatLocationFeesRate, $accumulateMap, $invoiceSnapshots) {
                $rowIndex++;
                $customer = $row->customer;
                $address = $customer?->deliveryAddress;
                $vend = $customer?->vend;
                $tagNames = $customer
                    ? $customer->tagBindings->map(fn ($tb) => optional($tb->tag)->name)->filter()->implode(', ')
                    : '';

                // Build full_address the same way AddressResource does.
                $fullAddress = '';
                if ($address) {
                    $parts = [];
                    if ($address->block_num)   $parts[] = 'Blk ' . ucwords(strtolower($address->block_num));
                    if ($address->unit_num)    $parts[] = '#' . $address->unit_num;
                    if ($address->building)    $parts[] = ucwords(strtolower($address->building));
                    if ($address->street_name) $parts[] = ucwords(strtolower($address->street_name));
                    $joined = implode(', ', $parts);
                    $fullAddress = $joined ? ($joined . ($address->postcode ? ', ' . $address->postcode : '')) : ($address->postcode ?? '');
                }

                // Snapshot override: if an API Invoice was created for this
                // (customer, month), replay its frozen values so the export
                // matches what was actually billed in CMS.
                $ps = $row->period_start instanceof \Carbon\Carbon
                    ? $row->period_start->toDateString()
                    : (string) $row->period_start;
                $pe = $row->period_end instanceof \Carbon\Carbon
                    ? $row->period_end->toDateString()
                    : (string) $row->period_end;
                $invKey = $row->customer_id . '|' . $ps . '|' . $pe;
                $cmsTxnId = null;
                if (isset($invoiceSnapshots[$invKey])) {
                    $inv = $invoiceSnapshots[$invKey];
                    $cmsTxnId = $inv->cms_transaction_id;
                    $snap = $inv->summary_snapshot;
                    if (is_array($snap)) {
                        foreach (['sales_cents', 'gross_earning_cents', 'location_fees_cents',
                                  'location_earning_cents', 'location_earning_rate',
                                  'contract_commission_type', 'contract_commission_value',
                                  'contract_commission_value2', 'contract_ps_term'] as $f) {
                            if (array_key_exists($f, $snap)) {
                                $row->{$f} = $snap[$f];
                            }
                        }
                    }
                }

                return [
                    '#' => $rowIndex,
                    'Customer ID' => $customer ? ($customer->id + Customer::RUNNING_NUMBER_INIT) : null,
                    'Customer Name' => $customer?->name,
                    'Ref Price' => $customer?->selling_price_type ? ('RP' . $customer->selling_price_type) : null,
                    'Address' => $fullAddress,
                    'Period Report (YYMM)' => $row->year_month ? \Carbon\Carbon::parse($row->year_month)->format('ym') : null,
                    'Machine ID' => $vend?->code,
                    'Machine Prefix' => $vend && $vend->relationLoaded('vendPrefix') ? optional($vend->vendPrefix)->name : null,
                    'Period Start Date' => $row->period_start ? (\Carbon\Carbon::parse($row->period_start))->format('ymd') : null,
                    'Period End Date' => $row->period_end ? (\Carbon\Carbon::parse($row->period_end))->format('ymd') : null,
                    // Sales now sources gp_metrics.amount_cents (INCL-GST) to
                    // match the Transactions page's "Total Sales" column.
                    // Gross Earning stays revenue − unit_cost (excl-GST), per
                    // its explicit column label.
                    'Sales ($) (incl GST)' => round(((int) $row->sales_cents) / $divisor, 2),
                    'Gross Earning (excl GST)' => round(((int) $row->gross_earning_cents) / $divisor, 2),
                    'Placement Contract Type' => $contractTypeLabels[$row->contract_commission_type] ?? $row->contract_commission_type,
                    'Location Fees Rate' => $formatLocationFeesRate($row),
                    'Location Fees' => round(((int) $row->location_fees_cents) / $divisor, 2),
                    'Vending Earning' => round(((int) $row->location_earning_cents) / $divisor, 2),
                    'Vending Earning Rate %' => round(((float) $row->location_earning_rate) * 100, 2),
                    'Accumulate Vending Earning' => round(((int) ($accumulateMap[$row->customer_id] ?? 0)) / $divisor, 2),
                    'Location Grading' => null,          // placeholder — same as the on-screen "—"
                    'Location Type' => $customer && $customer->relationLoaded('locationType') ? optional($customer->locationType)->name : null,
                    'Customer Tag' => $tagNames,
                    'CMS Txn #' => $cmsTxnId,
                ];
            }
        );
    }

    /**
     * Performance Report email — stub endpoint for the "Email" button on
     * Customer Summary > Action. Validates the request and confirms the
     * intent; the actual queued send (Mailable + dispatch) will be wired
     * in a follow-up so this exists primarily to give the frontend a real
     * URL to POST to without 404'ing.
     *
     * Contract once the send is wired:
     *   - body must contain period_start (Y-m-d) and period_end (Y-m-d)
     *   - customer must have is_report_email_enabled = true and a valid
     *     report_email; we re-check both on the server to defend against
     *     stale UI state (e.g. user toggled off in another tab).
     *   - dispatches a queued job that pulls the per-customer per-period
     *     summary row and emails an HTML report (format TBD by user).
     */
    public function sendPerformanceReport(Request $request, $id)
    {
        $request->validate([
            'period_start' => 'required|date',
            'period_end'   => 'required|date|after_or_equal:period_start',
        ]);

        $customer = Customer::findOrFail($id);

        if (!$customer->is_report_email_enabled || empty($customer->report_email)) {
            return redirect()->back()->withErrors([
                'send_performance_report' => 'This customer has not opted-in to performance report emails. Enable it from the customer edit page first.',
            ]);
        }

        // TODO: dispatch Mailable on the queue, e.g.
        //   PerformanceReportMail::dispatch($customer, $request->period_start, $request->period_end)
        //       ->onQueue('mail');
        // For now we only confirm the request was accepted so the UI flow
        // can be exercised end-to-end.

        return redirect()->back()->with(
            'success',
            "Performance report queued for {$customer->report_email} ({$request->period_start} → {$request->period_end}). [send pipeline pending wiring]"
        );
    }

    /**
     * Returns the structured Performance Report content for a single customer
     * over a period — used by the "Report Content" preview modal on Customer
     * Summary AND (later) by the queued email body builder. Both surfaces
     * call this endpoint so they stay in lockstep.
     *
     * Looks up the matching customer_period_summary row to pull Sales for
     * PS-family math; gracefully falls back to 0 sales if the row hasn't
     * been aggregated yet (preview still renders, just shows $0.00).
     */
    public function getPerformanceReportContent(Request $request, $id)
    {
        $request->validate([
            'period_start' => 'required|date',
            'period_end'   => 'required|date|after_or_equal:period_start',
        ]);

        // Eager-load the operator so PerformanceReportContentService can read
        // operator->gst_vat_rate for the PS-family GST de-grossing without an
        // extra lazy query per request.
        $customer = Customer::with('operator:id,gst_vat_rate')->findOrFail($id);

        $periodStart = \Carbon\Carbon::parse($request->period_start);
        $periodEnd   = \Carbon\Carbon::parse($request->period_end);

        // Find the summary row whose period overlaps the requested window.
        // For a single-month period this is exact; for aggregated periods
        // we sum sales across the matching months so PS math is correct.
        $summaryRow = \App\Models\CustomerPeriodSummary::query()
            ->where('customer_id', $customer->id)
            ->whereBetween('year_month', [
                $periodStart->copy()->startOfMonth()->toDateString(),
                $periodEnd->copy()->startOfMonth()->toDateString(),
            ])
            ->selectRaw('MIN(id) AS id, customer_id, SUM(sales_cents) AS sales_cents')
            ->groupBy('customer_id')
            ->first();

        $service = new PerformanceReportContentService();
        $content = $service->generate($customer, $periodStart, $periodEnd, $summaryRow);

        return response()->json($content);
    }

    /**
     * Customer Management > Summary > Action ▸ "Create API Invoice".
     *
     * Mirrors the spirit of OpsJobController::syncCmsInvoices() but for
     * the Customer Period Summary flow:
     *   - Builds a CMS invoice payload using hardcoded item codes per
     *     contract_commission_type (PS=055, U=V01, R=60, PS+U=both,
     *     PSORU=whichever-higher).
     *   - Creates a customer_period_summary_invoices row up front so the
     *     UI can show an "in flight" indicator.
     *   - Dispatches SyncCustomerInvoiceCMS to POST to /api/transactions/deals
     *     and persist the returned transaction_id back onto that row.
     *
     * Re-creation is allowed (per product decision) — the UI shows a
     * confirm dialog when an invoice already exists for the same
     * (customer + period). The server records every invocation so the
     * audit trail is preserved; only the latest row drives the badge.
     *
     * Body:
     *   period_start (Y-m-d) — required
     *   period_end   (Y-m-d) — required, >= period_start
     *   force        (bool)  — optional, must be 1/true to re-create when
     *                          a non-failed invoice already exists for
     *                          the same period.
     */
    public function syncCmsInvoice(Request $request, $id)
    {
        $request->validate([
            'period_start' => 'required|date',
            'period_end'   => 'required|date|after_or_equal:period_start',
            'force'        => 'nullable|boolean',
        ]);

        $customer = Customer::findOrFail($id);

        $service = app(\App\Services\CustomerInvoiceService::class);

        if (!$service->isInvoiceable($customer)) {
            return redirect()->back()->withErrors([
                'sync_cms_invoice' => 'This customer cannot be invoiced via API: missing CMS person_id, non-invoiceable contract type (F/S), or incomplete contract values.',
            ]);
        }

        if (empty(config('app.cms_url'))) {
            return redirect()->back()->withErrors([
                'sync_cms_invoice' => 'CMS endpoint is not configured (CMS_URL).',
            ]);
        }

        $periodStart = \Carbon\Carbon::parse($request->period_start)->toDateString();
        $periodEnd   = \Carbon\Carbon::parse($request->period_end)->toDateString();

        $existing = \App\Models\CustomerPeriodSummaryInvoice::query()
            ->where('customer_id', $customer->id)
            ->whereDate('period_start', $periodStart)
            ->whereDate('period_end', $periodEnd)
            ->whereNotNull('cms_transaction_id')
            ->orderByDesc('id')
            ->first();

        if ($existing && !$request->boolean('force')) {
            return redirect()->back()->withErrors([
                'sync_cms_invoice' => "Invoice already exists for this period (transaction #{$existing->cms_transaction_id}). Pass force=1 to re-create.",
            ]);
        }

        // Fetch the FULL stored row for the month — needed both for the
        // CMS payload (sales drives PS math) AND for the snapshot we
        // freeze on the invoice. SUM(sales_cents) is sufficient if the
        // period spans more than one stored month, but our per-month
        // grain means it's always a single row anyway.
        $summaryRow = \App\Models\CustomerPeriodSummary::query()
            ->where('customer_id', $customer->id)
            ->whereBetween('year_month', [
                \Carbon\Carbon::parse($periodStart)->startOfMonth()->toDateString(),
                \Carbon\Carbon::parse($periodEnd)->startOfMonth()->toDateString(),
            ])
            ->orderBy('year_month', 'asc')
            ->first();

        // Freeze the snapshot at creation time. attachExistingInvoice()
        // replays this onto the Customer Summary page so any later
        // backfill can't make the page show numbers that disagree with
        // what was actually invoiced.
        $snapshot = $service->buildSnapshot(
            $customer,
            \Carbon\Carbon::parse($periodStart),
            \Carbon\Carbon::parse($periodEnd),
            $summaryRow
        );

        $invoice = \App\Models\CustomerPeriodSummaryInvoice::create([
            'customer_id' => $customer->id,
            'period_start' => $periodStart,
            'period_end' => $periodEnd,
            'contract_commission_type' => $customer->contract_commission_type,
            'summary_snapshot' => $snapshot,
            'created_by' => auth()->id(),
        ]);

        \App\Jobs\SyncCustomerInvoiceCMS::dispatch(
            $invoice->id,
            $customer->id,
            $summaryRow ? $summaryRow->id : null,
            $periodStart,
            $periodEnd,
            auth()->id(),
        );

        return redirect()->back()->with(
            'success',
            "API Invoice queued for {$customer->name} ({$periodStart} → {$periodEnd})."
        );
    }

    /**
     * Bulk variant of syncCmsInvoice — accepts an array of
     * [{customer_id, period_start, period_end, force?}, ...]. Each entry
     * is dispatched independently; failures don't block the rest.
     *
     * Returns a small summary { queued, skipped, errors[] } via flash so
     * the UI can show a single toast covering the whole batch.
     */
    public function syncCmsInvoicesBulk(Request $request)
    {
        $request->validate([
            'items' => 'required|array|min:1',
            'items.*.customer_id' => 'required|integer',
            'items.*.period_start' => 'required|date',
            'items.*.period_end' => 'required|date|after_or_equal:items.*.period_start',
            'items.*.force' => 'nullable|boolean',
        ]);

        if (empty(config('app.cms_url'))) {
            return redirect()->back()->withErrors([
                'sync_cms_invoice' => 'CMS endpoint is not configured (CMS_URL).',
            ]);
        }

        $service = app(\App\Services\CustomerInvoiceService::class);

        $queued = 0;
        $skipped = 0;
        $errors = [];

        foreach ($request->input('items') as $entry) {
            $customer = Customer::find($entry['customer_id']);
            if (!$customer) {
                $errors[] = "Customer #{$entry['customer_id']} not found.";
                $skipped++;
                continue;
            }
            if (!$service->isInvoiceable($customer)) {
                $errors[] = "Customer #{$customer->id} ({$customer->name}) is not invoiceable.";
                $skipped++;
                continue;
            }

            $periodStart = \Carbon\Carbon::parse($entry['period_start'])->toDateString();
            $periodEnd   = \Carbon\Carbon::parse($entry['period_end'])->toDateString();
            $force = !empty($entry['force']);

            $existing = \App\Models\CustomerPeriodSummaryInvoice::query()
                ->where('customer_id', $customer->id)
                ->whereDate('period_start', $periodStart)
                ->whereDate('period_end', $periodEnd)
                ->whereNotNull('cms_transaction_id')
                ->orderByDesc('id')
                ->first();

            if ($existing && !$force) {
                $errors[] = "{$customer->name}: already invoiced (#{$existing->cms_transaction_id}).";
                $skipped++;
                continue;
            }

            $summaryRow = \App\Models\CustomerPeriodSummary::query()
                ->where('customer_id', $customer->id)
                ->whereBetween('year_month', [
                    \Carbon\Carbon::parse($periodStart)->startOfMonth()->toDateString(),
                    \Carbon\Carbon::parse($periodEnd)->startOfMonth()->toDateString(),
                ])
                ->orderBy('year_month', 'asc')
                ->first();

            $snapshot = $service->buildSnapshot(
                $customer,
                \Carbon\Carbon::parse($periodStart),
                \Carbon\Carbon::parse($periodEnd),
                $summaryRow
            );

            $invoice = \App\Models\CustomerPeriodSummaryInvoice::create([
                'customer_id' => $customer->id,
                'period_start' => $periodStart,
                'period_end' => $periodEnd,
                'contract_commission_type' => $customer->contract_commission_type,
                'summary_snapshot' => $snapshot,
                'created_by' => auth()->id(),
            ]);

            \App\Jobs\SyncCustomerInvoiceCMS::dispatch(
                $invoice->id,
                $customer->id,
                $summaryRow ? $summaryRow->id : null,
                $periodStart,
                $periodEnd,
                auth()->id(),
            );

            $queued++;
        }

        return redirect()->back()->with('success', sprintf(
            'API Invoices: %d queued, %d skipped.%s',
            $queued,
            $skipped,
            $errors ? ' ' . implode(' ', $errors) : ''
        ));
    }

    public function bindVend(Request $request, $id)
    {
        $customer = Customer::find($id);
        $vend = Vend::find($request->vendID);

        if ($customer and $vend) {
            $vend->customer_id = $customer->id;
            $vend->save();
            SyncVendCustomerCms::dispatchSync($customer->person_id, $vend->id);
        }

        return redirect()->back();
    }

    public function create()
    {
        // Use OptionsService for dropdown options
        $optionsService = app(\App\Services\OptionsService::class);

        return Inertia::render('Customer/Create', [
            'cmsCustomerOptions' => env('CMS_URL') ? (Http::get(env('CMS_URL') . '/api/vends/unbind')->collect() ?
                Http::get(env('CMS_URL') . '/api/vends/unbind')->collect()->whereNotIn('id', Customer::select('person_id')->pluck('person_id'))->all() :
                []) : [],
            'countries' => $optionsService->countries(),
            'customer' => new Customer(),
            'operatorOptions' => $optionsService->operators(),
            'vendOptions' => Vend::query()
                ->select('id', 'code', 'customer_id')
                ->where('customer_id', null)
                ->orderBy('code')
                ->get(),
            'cmsEndpoint' => env('CMS_URL'),
            'sellingPriceTypeOptions' => collect(SellingPrice::TYPE_MAPPINGS),
            'type' => 'create',
        ]);
    }

    public function delete($id)
    {
        $customer = Customer::find($id);
        $customer->delete();

        return redirect()->route('customers');
    }

    public function disconnectCms($id)
    {
        $customer = Customer::find($id);
        $customer->person_id = null;
        $customer->save();

        return redirect()->route('customers.edit', [$id]);
    }

    public function edit(Request $request, $id)
    {
        $customerInit = Customer::findOrFail($id);

        if ($request->selling_price_type) {
            $type = $request->selling_price_type;
        } else {
            $type = $customerInit->selling_price_type;
        }

        $customer = Customer::query()
            ->with([
                'attachments',
                'contracts',
                'billingAddress',
                'category',
                'category.categoryGroup',
                'contact',
                'customerVendBindings.vend:id,code,customer_id',
                'customerVendBindings.vendPrefix',
                'deliveryAddress',
                'firstTransaction',
                'photos',
                'profile',
                'status',
                // Eager-load tag relation so the multiselect on Customer/Edit
                // can preselect bound tags by name + id.
                'tagBindings.tag',
                'vend:id,code,customer_id,product_mapping_id',
                'vend.productMapping.attachments' => function ($query) use ($type) {
                    // $query->when($type, function ($query, $type) {
                    //     $query->where('type', $type);
                    // });
                    $query->where('type', $type);
                },
                'vend.vendChannels:id,amount,amount2,code,vend_id,product_id',
                'vend.vendChannels.product:id,name,code,desc',
                'vend.vendChannels.product.sellingPrices' => function ($query) use ($type) {
                    // $query->when($type, function ($query, $type) {
                    //     $query->where('type', $type);
                    // });
                    $query->where('type', $type);
                },
                'vend.vendChannels.product.thumbnail',
                'contractDetailUpdatedBy:id,name',
                'zone',
            ])
            ->find($id);

        // Use OptionsService for dropdown options
        $optionsService = app(\App\Services\OptionsService::class);

        return Inertia::render('Customer/Edit', [
            'cmsEndpoint' => env('CMS_URL'),
            'countries' => $optionsService->countries(),
            'customerTagOptions' => $optionsService->tags(Customer::class),
            'days' => Customer::DAYS_MAPPING,
            'frequencyPerWeekOptions' => Customer::FREQUENCY_PER_WEEK_STATUSES_MAPPING,
            // Drives the "Location Grading" section in Customer/Edit.vue
            // (3 radio groups). Keep the data source in the model so the
            // rubric stays in one place.
            'locationGradingCategories' => Customer::LOCATION_GRADING_CATEGORIES,
            'locationTypeOptions' => $optionsService->locationTypes(),
            'operatorOptions' => $optionsService->operators(),
            'sellingPriceTypeOptions' => collect(SellingPrice::TYPE_MAPPINGS),
            'vendOptions' => Vend::query()
                ->select('id', 'code', 'customer_id')
                ->where('customer_id', null)
                ->orderBy('code')
                ->get(),
            'customer' => $customer,
            'type' => 'update',
            'zoneOptions' => $optionsService->zones(),
        ]);
    }

    public function getMap(Request $request)
    {
        $input = collect($request->all());

        $customers = Customer::query()
            ->with([
                'contact',
                'vend:id,code,customer_id',
                'deliveryAddress'
            ])
            ->whereIn('id', $input->pluck('customer_id'))
            ->get()
            ->sortBy(function ($customer) use ($input) {
                return $input->firstWhere('customer_id', $customer->id)['sequence'];
            })->values(); // Resetting the keys of the collection


        return CustomerResource::collection($customers);
    }

    // retrieve all or single vendcodes from sys.happyice
    public function getCustomersByPersonID($personID = null)
    {
        $customers = Customer::query()
            ->with(['vends'])
            ->when($personID, fn($query, $input) => $query->where('person_id', $input))
            ->get();

        SyncVendCustomerCms::dispatch($personID, null);
        return $customers;
    }

    // public function migrate(Request $request)
    // {
    //     $value = $request->all();
    //     SyncVendCustomerCms::dispatch(null, $value['id']);
    // }

    public function search(Request $request)
    {
        $search = $request->search;
        $customers = Customer::query()
            ->with([
                'operator:id,name',
                'vend:id,code,customer_id'
            ])
            ->has('vend')
            ->where(function ($query) use ($search) {
                $query->where('name', 'LIKE', '%' . $search . '%')
                    ->orWhere('virtual_customer_code', 'LIKE', '%' . $search . '%')
                    ->orWhere('virtual_customer_prefix', 'LIKE', '%' . $search . '%')
                    ->orWhereHas('vend', function ($query) use ($search) {
                        $query->where('code', 'LIKE', '%' . $search . '%');
                    });
            })
            ->whereNull('operator_id')
            ->get();

        return $customers;
    }

    public function store(Request $request)
    {
        $request->validate([
            'operator_id' => 'required',
        ]);

        if ($request->is_existing) {
            $request->validate([
                'cms_customer_id' => 'required',
            ]);
            SyncVendCustomerCms::dispatchSync($request->cms_customer_id, null);

            $customer = Customer::where('person_id', $request->cms_customer_id)->first();

            if ($request->operator_id) {
                $customer->update([
                    'operator_id' => $request->operator_id,
                    'selling_price_type' => $request->selling_price_type,
                ]);
            }
        } else {
            $request->validate([
                'name' => 'required',
            ]);
            $customer = Customer::create($request->all());

            if ($request->contact and isset($request->contact['name']) and $request->contact['name']) {
                $customer->contact()->updateOrCreate($request->contact);
            }

            if ($request->address and isset($request->address['postcode']) and $request->address['postcode']) {
                $customer->deliveryAddress()->updateOrCreate([
                    'type' => Customer::ADDRESS_TYPE_DELIVERY,
                ], $request->address);
            }
        }

        return redirect()->route('customers.edit', [$customer->id]);
    }

    public function syncFromCms(Request $request)
    {
        $response = Http::get(env('CMS_URL') . '/api/people');
        $people = $response->collect();

        if ($people) {
            foreach ($people as $person) {
                $customer = Customer::where('person_id', $person['id'])->first();

                if ($customer) {
                    $customer->update([
                        'cms_customer' => $person,
                    ]);
                }
            }
        }

        return true;
    }

    public function syncCmsInvoiceItems(Request $request)
    {
        $customers = Customer::query()
            ->whereIn('id', $request->customerIDs)
            ->get();

        if ($customers) {
            foreach ($customers as $customer) {
                SyncTransactionItemCMS::dispatch($customer->id)->onQueue('default');
                // SyncTransactionItemCMS::dispatchSync($customer->id);
            }
        }
    }

    public function syncNextDeliveryDate($people = [])
    {
        if (!$people) {
            // get all people from cms
            $response = Http::get(env('CMS_URL') . '/api/people/last-invoice-date');
            $people = $response->collect();
        }

        if (empty($people)) {
            return true;
        }

        // Batch load all customers by person_id
        $personIds = collect($people)->pluck('id')->toArray();
        $customers = Customer::whereIn('person_id', $personIds)
            ->get()
            ->keyBy('person_id');

        // Batch load all ops job items by cms_transaction_id
        $transactionIds = collect($people)
            ->pluck('next_transaction_id')
            ->filter()
            ->toArray();
        $opsJobItems = OpsJobItem::whereIn('cms_transaction_id', $transactionIds)
            ->get()
            ->keyBy('cms_transaction_id');

        // Prepare bulk updates
        $now = now();

        foreach ($people as $person) {
            $customer = $customers->get($person['id']);

            if ($customer) {
                $customer->update([
                    'cms_invoice_history' => $person,
                    'last_invoice_date' => $person['last_delivery_date'],
                    'next_invoice_date' => $person['next_delivery_date'],
                    'updated_at' => $now,
                ]);
            }

            if ($person['next_transaction_id'] && $person['next_transaction_sequence']) {
                $opsJobItem = $opsJobItems->get($person['next_transaction_id']);

                if ($opsJobItem) {
                    $opsJobItem->update([
                        'sequence' => $person['next_transaction_sequence'],
                        'updated_at' => $now,
                    ]);
                }
            }
        }

        return true;
    }

    public function update(Request $request, $id)
    {
        // dd($request->all());
        $customer = Customer::find($id);
        $vend = Vend::find($request->id);

        $requestCustomerArr = $request->customer;
        if (isset($requestCustomerArr['is_active']) and $requestCustomerArr['is_active'] === 'true') {
            $requestCustomerArr['is_active'] = true;
        } else {
            $requestCustomerArr['is_active'] = false;
        }

        if (isset($requestCustomerArr['is_restricted_access']) and $requestCustomerArr['is_restricted_access'] === 'true') {
            $requestCustomerArr['is_restricted_access'] = true;
        } else {
            $requestCustomerArr['is_restricted_access'] = false;
        }

        $request->merge([
            'customer' => $requestCustomerArr,
        ]);

        if (!$customer) {
            if ($request->is_existing) {
                $request->validate([
                    'customer_id' => 'required',
                ]);
                $customer = Customer::where('id', $request->customer_id)->first();
            } else {
                $request->validate([
                    'customer.operator_id' => 'required',
                    'customer.name' => 'required',
                ]);
                $customer = Customer::create($request->customer);

                if ($request->customer['contact'] && isset($request->customer['contact']['name']) && $request->customer['contact']['name']) {
                    $customer->contact()->updateOrCreate($request->customer['contact']);
                }

                if ($request->customer['address'] && isset($request->customer['address']['postcode']) && $request->customer['address']['postcode']) {
                    $customer->deliveryAddress()->updateOrCreate([
                        'type' => Customer::ADDRESS_TYPE_DELIVERY,
                    ], $request->customer['address']);
                }
            }
            $isMovement = false;
            if (!$vend->customer_id && $customer->id) {
                $isMovement = true;
            }
            $vend->customer_id = $customer->id;
            $vend->save();

            if ($isMovement) {
                $this->historyService->syncVendCustomerMovement($vend, $customer, true);
            }
        } else {
            // dd('here1111', $request->all());

            // Contract detail conditional validation (all nullable, validate when filled)
            $commissionType = $requestCustomerArr['contract_commission_type'] ?? null;
            $psTypes = ['PS', 'PS+U', 'PSORU'];
            $twoValueTypes = ['PS+U', 'PSORU'];

            $contractRules = [
                'customer.contract_commission_type'        => 'nullable|in:F,S,R,U,PS,PS+U,PSORU',
                'customer.contract_commission_value'       => [
                    'nullable',
                    'numeric',
                    'min:0',
                    ...($commissionType && in_array($commissionType, $psTypes) ? ['max:100'] : []),
                ],
                'customer.contract_commission_value2'      => 'nullable|numeric|min:0',
                'customer.contract_ps_term'                => 'nullable|numeric|min:0|max:100',
                'customer.contract_from'                   => 'nullable|date',
                'customer.contract_until'                  => 'nullable|date',
                'customer.contract_auto_renewal'           => 'nullable|boolean',
                'customer.contract_notice_period'          => 'nullable|integer|min:0',
                'customer.contract_remarks'                => 'nullable|string|max:5000',
                // Performance Report Email opt-in (see migration
                // 2026_05_09_000000_add_report_email_to_customers).
                'customer.report_email'                    => 'nullable|email|max:191',
                'customer.is_report_email_enabled'         => 'nullable|boolean',
                // Location Grading — A/B/C per category, nullable. See
                // Customer::LOCATION_GRADING_CATEGORIES for the rubric.
                'customer.location_grading_placement'      => 'nullable|in:A,B,C',
                'customer.location_grading_access'         => 'nullable|in:A,B,C',
                'customer.location_grading_flexibility'    => 'nullable|in:A,B,C',
            ];

            $request->validate($contractRules);

            // Defensive: never let the DB hold "enabled = true" with a NULL/
            // empty email. The Vue side already enforces this on the form,
            // but if someone POSTs directly we still want clean data so the
            // Summary page's button-visibility flag is meaningful.
            if (empty($requestCustomerArr['report_email'])) {
                $requestCustomerArr['is_report_email_enabled'] = false;
                $request->merge(['customer' => $requestCustomerArr]);
            }

            // Detect if any contract detail field changed → log audit
            $contractFields = [
                'contract_commission_type', 'contract_commission_value', 'contract_commission_value2',
                'contract_ps_term', 'contract_from', 'contract_until', 'contract_auto_renewal',
                'contract_notice_period', 'contract_remarks',
            ];
            $contractChanged = false;
            foreach ($contractFields as $field) {
                $incoming = $requestCustomerArr[$field] ?? null;
                $existing = $customer->{$field};
                // normalise for comparison
                if ($existing instanceof \Carbon\Carbon) {
                    $existing = $existing->toDateString();
                }
                if ((string) $incoming !== (string) $existing) {
                    $contractChanged = true;
                    break;
                }
            }
            if ($contractChanged) {
                $requestCustomerArr['contract_detail_updated_at'] = now();
                $requestCustomerArr['contract_detail_updated_by'] = auth()->id();
                $request->merge(['customer' => $requestCustomerArr]);
            }

            $customer->update($request->customer);

            // Append a row to customer_contract_logs whenever any contract field
            // changes, so the Summary page (and future segmented reporting) can
            // resolve which contract was active for any historical period.
            // Stamps the previously-active row's effective_to to "now" so the
            // history is contiguous.
            if ($contractChanged) {
                $now = now();
                CustomerContractLog::query()
                    ->where('customer_id', $customer->id)
                    ->whereNull('effective_to')
                    ->update(['effective_to' => $now]);

                CustomerContractLog::query()->create([
                    'customer_id' => $customer->id,
                    'effective_from' => $now,
                    'effective_to' => null,
                    'contract_commission_type' => $customer->contract_commission_type,
                    'contract_commission_value' => $customer->contract_commission_value,
                    'contract_commission_value2' => $customer->contract_commission_value2,
                    'contract_ps_term' => $customer->contract_ps_term,
                    'contract_from' => $customer->contract_from,
                    'contract_until' => $customer->contract_until,
                    'contract_auto_renewal' => (bool) $customer->contract_auto_renewal,
                    'contract_notice_period' => $customer->contract_notice_period,
                    'contract_remarks' => $customer->contract_remarks,
                    'changed_by' => auth()->id(),
                    'source' => 'user',
                ]);
            }

            if ($request->customer['contact'] && isset($request->customer['contact']['name'])) {
                if ($customer->contact) {
                    $customer->contact->update($request->customer['contact']);
                } else {
                    $customer->contact()->create($request->customer['contact']);
                }
            }

            if ($request->customer['address'] && isset($request->customer['address']['country_id'])) {
                $customer->deliveryAddress()->updateOrCreate([
                    'type' => Customer::ADDRESS_TYPE_DELIVERY,
                ], $request->customer['address']);
            }

            if ($request->customer and isset($request->customer['vend_id']) and $request->customer['vend_id']) {
                $vend = Vend::find($request->customer['vend_id']);

                $isMovement = false;
                if (!$vend->customer_id && $customer->id) {
                    $isMovement = true;
                }
                $vend->customer_id = $customer->id;
                $vend->save();

                if ($isMovement) {
                    $this->historyService->syncVendCustomerMovement($vend, $customer, true);
                }
            }
        }

        if ($customer->deliveryAddress) {
            if ((!$customer->deliveryAddress->latitude or !$customer->deliveryAddress->longitude) and $customer->deliveryAddress->country->code == 'SG') {
                $location = $this->getAddressResult($customer->deliveryAddress->postcode);

                if ($location) {
                    $customer->deliveryAddress->update([
                        'latitude' => $location['latitude'],
                        'longitude' => $location['longitude'],
                    ]);
                }
            }
        }

        if ($customer->vend) {
            $customer->vend->update([
                'operator_id' => $customer->operator_id,
            ]);
        }

        if ($customer and $customer->person_id and $vend) {
            SyncVendCustomerCms::dispatchSync($customer->person_id, $vend->id);
        }

        // Sync customer tags (Customer-scoped Tag rows from /tags?classname=
        // App\Models\Customer). The Vue side sends tag_ids at the top level so
        // it can't collide with the customer payload's mass-assignment.
        if ($customer && $request->has('tag_ids')) {
            $tagIds = collect($request->input('tag_ids', []))
                ->filter(fn ($v) => is_numeric($v))
                ->map(fn ($v) => (int) $v)
                ->unique()
                ->values()
                ->all();
            TagBindingService::sync($customer, $tagIds);
        }

        return redirect()->back();
    }

    public function uploadAttachment(Request $request, $id)
    {
        $customer = Customer::findOrFail($id);

        if ($request->files) {
            $files = $request->file('files');
            $dir = 'sys/customers';
            $storedPath = $files->storePublicly($dir);
            $fileName = basename($storedPath);
            $url = Storage::url($storedPath);
            $customer->attachments()->create([
                'type' => 1,
                'full_url' => $url,
                'local_url' => $dir . '/' . $fileName,
            ]);
        }
        return true;
    }

    public function uploadPhoto(Request $request, $id)
    {
        $customer = Customer::findOrFail($id);

        if ($request->files) {
            $files = $request->file('files');
            $dir = 'sys/customers';
            $storedPath = $files->storePublicly($dir);
            $fileName = basename($storedPath);
            $url = Storage::url($storedPath);
            $customer->photos()->create([
                'type' => 2,
                'full_url' => $url,
                'local_url' => $dir . '/' . $fileName,
            ]);
        }
        return true;
    }

    public function uploadContract(Request $request, $id)
    {
        $customer = Customer::findOrFail($id);

        if ($request->files) {
            $files = $request->file('files');
            $dir = 'sys/customers/contracts';
            $storedPath = $files->storePublicly($dir);
            $fileName = pathinfo($files->getClientOriginalName(), PATHINFO_FILENAME);
            $url = Storage::url($storedPath);
            $customer->contracts()->create([
                'name' => $fileName,
                'type' => Customer::FILE_TYPE_CONTRACT,
                'full_url' => $url,
                'local_url' => $dir . '/' . basename($storedPath),
            ]);
        }
        return true;
    }

    public function exportExcel(Request $request)
    {
        $request->merge([
            'is_binded_vend' => $request->is_binded_vend ? $request->is_binded_vend : 'all',
            'is_cms' => $request->is_cms ? $request->is_cms : 'all',
            'is_active' => $request->is_active ? $request->is_active : 'true',
            'sortKey' => $request->sortKey ? $request->sortKey : 'customers.id',
            'sortBy' => $request->sortBy ? $request->sortBy : 'false',
        ]);

        $operatorCountry = auth()->user()->operator->country;
        $divisor = pow(10, $operatorCountry->currency_exponent ?? 2);

        $query = Customer::query()
            ->with([
                'deliveryAddress',
                'tagBindings',
                'vend.vendPrefix',
            ])
            ->leftJoin('addresses', function ($join) {
                $join->on('addresses.modelable_id', '=', 'customers.id')
                    ->where('addresses.modelable_type', '=', 'App\\Models\\Customer')
                    ->where('addresses.type', '=', 2)
                    ->limit(1);
            })
            ->leftJoin('operators', 'customers.operator_id', '=', 'operators.id')
            ->leftJoin('vends', 'vends.customer_id', '=', 'customers.id')
            ->leftJoin('zones', 'zones.id', '=', 'customers.zone_id')
            ->leftJoin(DB::raw('
                (
                    SELECT vend_id, SUM(amount * capacity) AS total_full_load_amount
                    FROM vend_channels
                    WHERE is_active = true
                    AND capacity > 0
                    GROUP BY vend_id
                ) AS vc
            '), 'vc.vend_id', '=', 'vends.id')
            ->select(
                'addresses.postcode as postcode',
                'customers.*',
                'customers.id',
                'customers.begin_date as begin_date',
                'customers.frequency_per_week_status',
                'customers.operator_id',
                'customers.zone_id',
                'operators.code as operator_code',
                'vends.code as vend_code',
                'zones.name as zone_name',
                'vc.total_full_load_amount',
                DB::raw('
                    (JSON_UNQUOTE(JSON_EXTRACT(customers.totals_json, "$.vend_records_thirty_days_amount_average")) * 30 / 100) /
                    (vc.total_full_load_amount / 100) AS thirty_days_over_full_load_ratio
                ')
            )
            ->filterIndex($request);

        $query = $this->filterOperator($query);

        $commissionTypeLabels = [
            'F'     => 'Free Placement',
            'S'     => 'Subsidized Plan',
            'R'     => 'Fix Rental',
            'U'     => 'Utility Only',
            'PS'    => 'Profit Sharing Only',
            'PS+U'  => 'PS + Utility',
            'PSORU' => 'PS OR Utility (whichever higher)',
        ];

        return (new FastExcel($this->exportWithCursor($query)))->download(
            $this->formatExportFilename('Customers', 'xlsx'),
            function ($customer) use ($divisor, $commissionTypeLabels) {
                $totals = $customer->totals_json ?? [];

                $lifetimeSales      = isset($totals['vend_records_amount_latest'])
                    ? round($totals['vend_records_amount_latest'] / $divisor, 2) : null;
                $avgSalesDay        = isset($totals['vend_records_amount_average_day'])
                    ? round($totals['vend_records_amount_average_day'] / $divisor, 2) : null;
                $avgDailySales30d   = isset($totals['vend_records_thirty_days_amount_average'])
                    ? round($totals['vend_records_thirty_days_amount_average'] / $divisor, 2) : null;
                $sales30d           = isset($totals['vend_records_thirty_days_amount'])
                    ? round($totals['vend_records_thirty_days_amount'] / $divisor, 2) : null;
                $grossMargin30d     = isset($totals['thirty_days_gross_profit'])
                    ? round($totals['thirty_days_gross_profit'] / $divisor, 2) : null;
                $fullLoadValue      = isset($customer->total_full_load_amount)
                    ? round($customer->total_full_load_amount / 100, 2) : null;
                $ratio30dOverFullLoad = isset($customer->thirty_days_over_full_load_ratio)
                    ? round($customer->thirty_days_over_full_load_ratio, 4) : null;

                $contractType = $customer->contract_commission_type;
                $contractTypeLabel = $contractType
                    ? ($contractType . ': ' . ($commissionTypeLabels[$contractType] ?? $contractType))
                    : null;

                // Label the value column based on type for clarity
                $contractValueLabel = match($contractType) {
                    'S'             => 'Subsidized Amt',
                    'R'             => 'Fix Rental Amt',
                    'U'             => 'Utility Amt',
                    'PS', 'PS+U', 'PSORU' => 'Commission (%)',
                    default         => null,
                };

                return [
                    // ── Identity ──────────────────────────────────────────────
                    'Customer ID'                   => $customer->id + 20000,
                    'Customer Name'                 => $customer->name,
                    'Machine ID'                    => $customer->vend_code,
                    'Machine Prefix'                => $customer->vend?->vendPrefix?->name,
                    'Delivery Address'              => $customer->deliveryAddress?->full_address,
                    'Postcode'                      => $customer->postcode,
                    'Tags'                          => $customer->tagBindings?->pluck('name')->implode(', '),
                    'Refilling Route'               => $customer->zone_name,
                    'Status'                        => $customer->is_active ? 'Active' : 'Not Active',
                    'Operator'                      => $customer->operator_code,
                    'Ref Price Type'                => 'RP' . $customer->selling_price_type,
                    'Begin Date'                    => $customer->begin_date
                                                        ? Carbon::parse($customer->begin_date)->format('Y-m-d') : null,

                    // ── Sales & Performance ───────────────────────────────────
                    'Lifetime Sales'                => $lifetimeSales,
                    'Avg Sales/Day'                 => $avgSalesDay,
                    'AvgDailySales (Last30d)'       => $avgDailySales30d,
                    'Sales (Last30d)'               => $sales30d,
                    'Gross Margin (Last30d)'        => $grossMargin30d,
                    'Full Load Value'               => $fullLoadValue,
                    'Avg30dSales / Full Load'       => $ratio30dOverFullLoad,

                    // ── Contract ──────────────────────────────────────────────
                    'Contract Type'                 => $contractTypeLabel,
                    'Contract Value Label'          => $contractValueLabel,
                    'Contract Value'                => $customer->contract_commission_value,
                    'Contract Value 2'              => $customer->contract_commission_value2,
                    'PS Term (%)'                   => $customer->contract_ps_term,
                    'Contract From'                 => $customer->contract_from
                                                        ? Carbon::parse($customer->contract_from)->format('Y-m-d') : null,
                    'Contract Until'                => $customer->contract_until
                                                        ? Carbon::parse($customer->contract_until)->format('Y-m-d') : null,
                    'Auto Renewal'                  => $customer->contract_auto_renewal ? 'Yes' : 'No',
                    'Notice Period (months)'        => $customer->contract_notice_period,
                    'Contract Remarks'              => $customer->contract_remarks,
                ];
            }
        );
    }
}
