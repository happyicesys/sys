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

    /**
     * Hard floor for Customer Summary reporting. Monthly Customer Period
     * Summaries before this date were reconstructed from imported Excel and
     * are incomplete, so the "Accumulate Vending Earning" column would be
     * misleading if it summed across that boundary. We clamp both the visible
     * window ("All" Period Report) and the lifetime running sum to this date.
     *
     * Pre-floor rows are intentionally left in `customer_period_summaries`
     * (no reseed needed) — we just don't display or sum them. If the floor
     * ever needs to change, update this constant only.
     */
    const SUMMARY_FLOOR_DATE = '2023-01-01';

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
            // Customer Status filter (replaces is_active). Default to Active so
            // the list opens on active customers, matching prior behaviour.
            'status' => $request->status ? $request->status : Customer::STATUS_ACTIVE,
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
        // Default sort: Note Last Updated, latest → oldest — matches the Vue
        // side's initial filter so the column header indicator and the actual
        // server order agree on first page load. sortBy 'false' = desc per the
        // filter_var() conversion further below; customers whose Site Note was
        // never edited resolve to NULL and sort to the end (nullsLastRaw).
        $summarySortKey = $request->sortKey ?: 'notes_updated_at';
        $summarySortBy = $request->sortBy ?: 'false';

        $request->merge([
            'is_binded_vend' => $request->is_binded_vend ? $request->is_binded_vend : 'all',
            'is_cms' => $request->is_cms ? $request->is_cms : 'all',
            // Customer Status — 5-value dropdown (Potential / New / Active /
            // Pending / Inactive). Default to Active so the page opens on the
            // active book, matching the prior binary is_active=true default.
            // Customer::filterIndex resolves `status` via the status_id column
            // (and still honours legacy `is_active` URLs for backward compat).
            'status' => $request->status ?: Customer::STATUS_ACTIVE,
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

        // Initial-load default for the Operator filter — mirror Summary.vue's
        // onMounted default (the user's own operator, plus HIMD/LEA/HIESG/UL-ST
        // for HIPL admins) so the FIRST server render already reflects the
        // operator chips the form shows. Without this the initial render uses
        // no operator filter (all operators) and the count visibly jumps when
        // the form's first Search applies the default set.
        //
        // Applied ONLY on a fresh load (no `searched` flag). An explicit search
        // sends searched=1, so deselecting every operator to "see all" is still
        // honoured instead of snapping back to this default.
        if (!$request->boolean('searched') && empty($request->operators)) {
            $user = auth()->user();
            if ($user && $user->operator_id) {
                $defaultOperatorIds = [(int) $user->operator_id];
                $userOperator = \App\Models\Operator::find($user->operator_id);
                if ($userOperator && $userOperator->code === 'HIPL') {
                    $defaultOperatorIds = array_merge(
                        $defaultOperatorIds,
                        \App\Models\Operator::whereIn('code', ['HIMD', 'LEA', 'HIESG', 'UL-ST'])
                            ->pluck('id')->map(fn ($v) => (int) $v)->all()
                    );
                }
                $request->merge(['operators' => array_values(array_unique($defaultOperatorIds))]);
            }
        }

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
        // Summary-only filter: Contract Attachment? — keep/drop customers
        // based on whether a contract attachment was uploaded in the
        // selected period window or later.
        $this->applyContractAttachmentFilter($customerIdsQuery, $request, $rangeStart);
        $customerIds = $customerIdsQuery->pluck('customers.id')->unique()->values();

        // Sortable columns. machine_id / machine_prefix sort by the customer's
        // latest-bound vend (resolved via scalar subqueries below).
        // accumulate_vending_earning sorts by the lifetime running prefix sum
        // of location_earning_cents per customer up to each row's year_month —
        // computed via a correlated subquery (see orderByRaw below).
        $sortKey = in_array($summarySortKey, [
            'year_month', 'sales_cents', 'gross_earning_cents',
            'location_fees_cents', 'location_earning_cents', 'location_earning_rate',
            'transaction_count', 'job_count', 'customer_id',
            'machine_id', 'machine_prefix',
            'contract_commission_type', 'contract_commission_value',
            'external_subsidize', 'net_loc_fee',
            'accumulate_vending_earning',
            // Real columns on customer_period_summaries — sortable directly.
            'period_start', 'period_end',
            // Customer-table fields — resolved via correlated subqueries below.
            'customer_name', 'selling_price_type', 'begin_date',
            'contract_attachment', 'location_type', 'contract_until',
            // Site Note last-updated timestamp — customers.notes_updated_at,
            // resolved via correlated subquery below.
            'notes_updated_at',
            // Computed Gross Earning rate (excl-GST) — see orderByRaw below.
            'gross_earning_rate',
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
            'customer:id,name,code,company_remark,virtual_customer_code,virtual_customer_prefix,person_id,operator_id,selling_price_type,is_active,location_type_id,contract_commission_type,contract_commission_value,contract_commission_value2,contract_ps_term,is_external_subsidize,external_subsidize_amount,begin_date,termination_date,report_email,is_report_email_enabled,location_grading_placement,location_grading_access,location_grading_flexibility,contract_until,contract_auto_renewal,contract_notice_period,notes,notes_updated_at,notes_updated_by',
            // Customer's primary contact (morphOne) — used to render the
            // Contact Person line stacked under Address on the Summary page.
            'customer.contact:id,modelable_id,modelable_type,name',
            // Customer-level note "last edited by" user — drives the
            // tiny audit line under the textarea on Customer Summary.
            'customer.notesUpdatedBy:id,name',
            // User who locked the row (if any) — "Locked by X" tooltip.
            'lockedBy:id,name',
            // "Email Performance Report" audit — drives the "Last sent by X
            // at Y" line in the Report Content modal.
            'reportEmailedBy:id,name',
            // User who marked the row Paid — "Paid by X" tooltip.
            'paidBy:id,name',
            // Reverse-action audit relations — used by the tooltip on the
            // Period Verify & Lock cell so the next Lock/Paid cycle can
            // show who last unpaid / unlocked. Light (id+name) selects.
            'lastUnpaidBy:id,name',
            'lastUnlockedBy:id,name',
            // gst_vat_rate is pulled here so the Sales column can render the
            // excl-GST sub-line right under the incl-GST main value.
            'customer.operator:id,code,name,gst_vat_rate',
            'customer.tagBindings.tag:id,name',
            'customer.deliveryAddress',
            'customer.locationType:id,name',
            'customer.vend:id,customer_id,code,vend_prefix_id',
            'customer.vend.vendPrefix:id,name',
            // All vends bound to the customer — used to expand the "+N more"
            // hint into a line-broken list (ascending) in the Vend ID column.
            'customer.vends:id,customer_id,code,vend_prefix_id',
            'customer.vends.vendPrefix:id,name',
            // Delivery-platform bindings on the customer's vends — used to
            // render small platform badges (e.g. green "Grab" pill) next to
            // the customer name in the Customer column on Summary. Mirrors
            // the chain eager-loaded in VendController::indexCustomer().
            'customer.vends.deliveryProductMappingVends:id,vend_id,delivery_product_mapping_id',
            'customer.vends.deliveryProductMappingVends.deliveryProductMapping:id,delivery_platform_operator_id',
            'customer.vends.deliveryProductMappingVends.deliveryProductMapping.deliveryPlatformOperator:id,delivery_platform_id',
            'customer.vends.deliveryProductMappingVends.deliveryProductMapping.deliveryPlatformOperator.deliveryPlatform:id,name',
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

        // Row-level filters (replicated / locked / paid). Applied to the
        // listing query AND — via this same closure — to the totals query and
        // the count-card customer set below, so the cards stay in lockstep with
        // the table. (Customer-level filters are already baked into
        // $customerIds, so they flow into all three automatically.)
        //
        //  - Replicated: keep only rows in a (customer_id, year_month) bucket
        //    holding more than one row (a segmented month). Correlated count on
        //    the same table; customer_id is indexed so it stays cheap.
        //  - Period Locked / Location Fee Paid: 'true' → only matching rows,
        //    'false' → only the opposite, 'all'/absent → no filter.
        $applyRowFilters = function ($q) use ($request) {
            // Contract changes (same month): 'true'/Changes only → rows in a
            // segmented month (count > 1); 'false'/No → rows in a single-row
            // month (count = 1); 'all'/absent → no filter.
            if (in_array($request->replicated_only, ['true', 'false'], true)) {
                $op = $request->replicated_only === 'true' ? '>' : '=';
                $q->whereRaw(
                    '(SELECT COUNT(*) FROM customer_period_summaries s_rep
                      WHERE s_rep.customer_id = customer_period_summaries.customer_id
                        AND s_rep.`year_month` = customer_period_summaries.`year_month`) ' . $op . ' 1'
                );
            }
            if (in_array($request->period_locked, ['true', 'false'], true)) {
                $request->period_locked === 'true'
                    ? $q->whereNotNull('locked_at')
                    : $q->whereNull('locked_at');
            }
            if (in_array($request->location_fee_paid, ['true', 'false'], true)) {
                $request->location_fee_paid === 'true'
                    ? $q->whereNotNull('paid_at')
                    : $q->whereNull('paid_at');
            }
        };
        $applyRowFilters($summariesQuery);

        // For machine_id / machine_prefix sorting, attach a scalar subquery
        // that picks the latest-bound vend per customer (mirrors the
        // Customer::vend() relation: latest by begin_date, then created_at).
        // notes_updated_at is a CUSTOMER-level value (identical across all of a
        // customer's monthly rows), so we can sort by it page-wide and still
        // keep each customer's months contiguous using customer_id purely as a
        // tie-breaker (applied after the sortKey block below). The Vue side's
        // first-row-per-customer / striping logic is adjacency-based
        // (isFirstRowForCustomer compares the previous row), so contiguity —
        // not global customer_id order — is all it needs. For every OTHER sort
        // key the legacy behaviour stands: cluster by customer_id first.
        $clusterByCustomerFirst = $isAggregated && $sortKey !== 'notes_updated_at';
        if ($clusterByCustomerFirst) {
            // Multi-month view: cluster a customer's months together so the
            // "Accumulate / Customer Tag" first-row-per-customer rendering
            // on the Vue side stays unambiguous, regardless of what column
            // the user sorted by. customer_id is primary; the user-picked
            // sortKey becomes secondary within each customer's group.
            $summariesQuery->orderBy('customer_id', 'asc');
        }

        // NULLs always sort to the END of the list, regardless of asc/desc.
        // Pattern: `(expr) IS NULL ASC` evaluates 0 for non-null and 1 for
        // null, so non-nulls land first; the user's direction is applied as
        // the secondary order. Bindings (if any) are repeated because each
        // orderByRaw consumes its own copy at SQL-compile time.
        $nullsLastRaw = function (string $expr, string $dir, array $bindings = []) use ($summariesQuery) {
            $summariesQuery->orderByRaw("({$expr}) IS NULL ASC", $bindings);
            $summariesQuery->orderByRaw("({$expr}) {$dir}", $bindings);
        };

        if ($sortKey === 'machine_id') {
            $nullsLastRaw(
                'SELECT v.code FROM vends v
                  WHERE v.customer_id = customer_period_summaries.customer_id
                  ORDER BY v.begin_date DESC, v.created_at DESC LIMIT 1',
                $sortDirection
            );
        } elseif ($sortKey === 'machine_prefix') {
            $nullsLastRaw(
                'SELECT vp.name FROM vends v
                  LEFT JOIN vend_prefixes vp ON vp.id = v.vend_prefix_id
                  WHERE v.customer_id = customer_period_summaries.customer_id
                  ORDER BY v.begin_date DESC, v.created_at DESC LIMIT 1',
                $sortDirection
            );
        } elseif ($sortKey === 'external_subsidize') {
            // External Subsidize — per-period snapshot stored on the summary
            // row (locked history), already in cents.
            $nullsLastRaw('customer_period_summaries.external_subsidize_cents', $sortDirection);
        } elseif ($sortKey === 'net_loc_fee') {
            // Net Loc Fee = Location Fees − External Subsidize, both in cents
            // and both stored on the summary row.
            $nullsLastRaw(
                'customer_period_summaries.location_fees_cents
                  - customer_period_summaries.external_subsidize_cents',
                $sortDirection
            );
        } elseif ($sortKey === 'accumulate_vending_earning') {
            // Lifetime running sum of location_earning_cents for THIS row's
            // customer up to and including THIS row's year_month. Same value
            // that attachAccumulatedVendingEarning() later attaches as
            // $row->accumulate_vending_earning_cents — we just need it visible
            // to MySQL during ORDER BY so the page-window selection is
            // accumulate-aware.
            //
            // Efficient on the existing (customer_id, year_month) unique
            // index: each subquery is an index range scan, no full table
            // scan.
            $nullsLastRaw(
                'SELECT COALESCE(SUM(s2.location_earning_cents), 0)
                  FROM customer_period_summaries s2
                  WHERE s2.customer_id = customer_period_summaries.customer_id
                    AND s2.`year_month` <= customer_period_summaries.`year_month`',
                $sortDirection
            );
        } elseif ($sortKey === 'customer_name') {
            // Customer name lives on the customers table; correlate by id.
            $nullsLastRaw(
                'SELECT c.name FROM customers c
                  WHERE c.id = customer_period_summaries.customer_id',
                $sortDirection
            );
        } elseif ($sortKey === 'selling_price_type') {
            // Ref Price (RP{selling_price_type}) — customers.selling_price_type.
            $nullsLastRaw(
                'SELECT c.selling_price_type FROM customers c
                  WHERE c.id = customer_period_summaries.customer_id',
                $sortDirection
            );
        } elseif ($sortKey === 'begin_date') {
            // Begin Date — customers.begin_date.
            $nullsLastRaw(
                'SELECT c.begin_date FROM customers c
                  WHERE c.id = customer_period_summaries.customer_id',
                $sortDirection
            );
        } elseif ($sortKey === 'contract_until') {
            // Contract End Date — customers.contract_until.
            $nullsLastRaw(
                'SELECT c.contract_until FROM customers c
                  WHERE c.id = customer_period_summaries.customer_id',
                $sortDirection
            );
        } elseif ($sortKey === 'notes_updated_at') {
            // Note Last Updated — customers.notes_updated_at. Customers whose
            // Site Note has never been edited resolve to NULL and sort to the
            // end (via nullsLastRaw), so recently-noted sites cluster at top.
            $nullsLastRaw(
                'SELECT c.notes_updated_at FROM customers c
                  WHERE c.id = customer_period_summaries.customer_id',
                $sortDirection
            );
        } elseif ($sortKey === 'location_type') {
            // Location Type name — customers.location_type_id → location_types.name.
            $nullsLastRaw(
                'SELECT lt.name FROM customers c
                  LEFT JOIN location_types lt ON lt.id = c.location_type_id
                  WHERE c.id = customer_period_summaries.customer_id',
                $sortDirection
            );
        } elseif ($sortKey === 'contract_attachment') {
            // Contract Attachment — sort by the latest contract upload date
            // (most recent FILE_TYPE_CONTRACT attachment). Customers with no
            // contract resolve to NULL and sort to the end (via nullsLastRaw).
            $nullsLastRaw(
                'SELECT MAX(a.created_at) FROM attachments a
                  WHERE a.modelable_type = ?
                    AND a.modelable_id = customer_period_summaries.customer_id
                    AND a.type = ?',
                $sortDirection,
                ['App\\Models\\Customer', Customer::FILE_TYPE_CONTRACT]
            );
        } elseif ($sortKey === 'gross_earning_rate') {
            // Gross Earning rate (excl-GST) — mirrors the Vue grossEarningRate():
            //   gross_earning_cents / (sales_cents / (1 + operator.gst%/100))
            // operator_id is stored on the summary row, so we correlate to the
            // operators table for the GST rate (0 when none configured).
            $nullsLastRaw(
                'customer_period_summaries.gross_earning_cents /
                  NULLIF(customer_period_summaries.sales_cents /
                    (1 + COALESCE((SELECT o.gst_vat_rate FROM operators o
                                   WHERE o.id = customer_period_summaries.operator_id), 0) / 100), 0)',
                $sortDirection
            );
        } else {
            // Whitelisted scalar column on customer_period_summaries (e.g.
            // year_month / sales_cents / …). Most are NOT NULL by schema, but
            // route through nullsLastRaw for consistency.
            $nullsLastRaw($sortKey, $sortDirection);
        }

        // Final tie-breaker: customer_id (in any case the branch above didn't
        // already apply it — single-month "current" view, or the multi-month
        // notes_updated_at sort where customer_id is the tie-breaker that keeps
        // each customer's months contiguous) + year_month DESC so a customer's
        // most recent month sits at the top of their cluster.
        if (!$clusterByCustomerFirst) {
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

        // Attach the resolved PREVIOUS-month snapshot to each current-month
        // row, so the Vue side can render month-over-month trend arrows even
        // in the single-month "Current" view (where last month isn't itself a
        // visible row to compare against). No-op for non-current rows — in the
        // multi-month views the previous month is already a visible row and
        // the arrows resolve from that.
        $this->attachPreviousMonthSummary($summaries->getCollection());

        // Flag, per row, which contract terms changed versus the customer's
        // immediately-preceding period — drives the tiny "New" badge beside
        // Placement Contract Type / Contract End Date / Auto Renewal / Notice
        // Period on the Summary page.
        $this->attachContractChangeFlags($summaries->getCollection());

        // Aggregate totals — summed across the FULL filtered set (not just
        // the paginated rows visible on this page) so the 4 boxes above the
        // table (Total Sales / Gross Earning / Location Fees / Vend Earnings)
        // reflect every row matching the current filters. Same WHERE clause
        // as the listing query so the numbers stay in lockstep with the
        // table. Cents-typed; the Vue side runs them through formatMoney().
        $totalsQuery = \App\Models\CustomerPeriodSummary::query()
            ->whereIn('customer_id', $customerIds)
            ->whereBetween('year_month', [$rangeStart->toDateString(), $rangeEnd->toDateString()]);
        $applyRowFilters($totalsQuery);
        $totalsRow = $totalsQuery
            ->selectRaw('
                COALESCE(SUM(sales_cents), 0) AS sales_cents,
                COALESCE(SUM(gross_earning_cents), 0) AS gross_earning_cents,
                COALESCE(SUM(location_fees_cents), 0) AS location_fees_cents,
                COALESCE(SUM(location_earning_cents), 0) AS location_earning_cents,
                MIN(period_start) AS earliest_period_start
            ')
            ->first();
        $totals = [
            'sales_cents' => (int) ($totalsRow->sales_cents ?? 0),
            'gross_earning_cents' => (int) ($totalsRow->gross_earning_cents ?? 0),
            'location_fees_cents' => (int) ($totalsRow->location_fees_cents ?? 0),
            'location_earning_cents' => (int) ($totalsRow->location_earning_cents ?? 0),
        ];

        // Displayed "Period range" — collapse to where the FILTERED data
        // actually starts. So e.g. picking "All" but with a filter whose
        // earliest data is 2023-11 shows the range starting at 2023-11-01,
        // not at the global floor (2023-01-01). The range END is left at the
        // resolved end (end-of-current-month for "All") per user request.
        // Falls back to the resolved range when the filter matches no rows.
        $displayRangeStart = $totalsRow && $totalsRow->earliest_period_start
            ? \Carbon\Carbon::parse($totalsRow->earliest_period_start)->toDateString()
            : $rangeStart->toDateString();

        // Distinct-customer aggregate counts scoped to the SAME customer set
        // the on-screen table actually shows — i.e. filtered customers that
        // ALSO have at least one summary row in the resolved period window.
        // Drives the two count cards ("# without Contract Attachment" /
        // "# To Be Expired in 30ds") in the totals row on Customer/Summary.vue.
        //
        // Without the period-window scope the counts include customers that
        // match the filters but have no row in the selected period (e.g.
        // brand-new or terminated customers under "Last Month Only"), so the
        // counts drift higher than the row total visible in the table.
        $displayedCustomerIdsQuery = \App\Models\CustomerPeriodSummary::query()
            ->whereIn('customer_id', $customerIds)
            ->whereBetween('year_month', [$rangeStart->toDateString(), $rangeEnd->toDateString()]);
        $applyRowFilters($displayedCustomerIdsQuery);
        $displayedCustomerIds = $displayedCustomerIdsQuery
            ->select('customer_id')
            ->distinct()
            ->pluck('customer_id');

        // 1) No-contract-attachment count — mirrors applyContractAttachmentFilter:
        //    a customer counts as "no attachment" when they have no contract
        //    attachment uploaded on/after the first day of the filtered
        //    period's starting month (so older legacy attachments don't
        //    inflate the count for the current reporting window).
        $attachmentThreshold = $rangeStart->copy()->startOfMonth()->toDateString();
        $noContractAttachmentCount = Customer::query()
            ->whereIn('id', $displayedCustomerIds)
            ->whereDoesntHave('contracts', function ($q) use ($attachmentThreshold) {
                $q->where('attachments.created_at', '>=', $attachmentThreshold);
            })
            ->count();

        // 2) "To Be Expired in 30ds" — customers whose contract_until is
        //    between today and 30 calendar days from today (inclusive on
        //    both ends, upcoming-only — already-expired contracts are
        //    excluded). App TZ is operator TZ on this per-country deployment,
        //    so Carbon::today() is correct without explicit conversion.
        //
        //    Auto-renewing contracts are EXCLUDED — a contract that renews
        //    itself isn't really "expiring", so only non-auto-renewal
        //    contracts (false OR not set) count toward this card.
        $today = \Carbon\Carbon::today();
        $expiringIn30dCount = Customer::query()
            ->whereIn('id', $displayedCustomerIds)
            ->whereNotNull('contract_until')
            ->whereBetween('contract_until', [
                $today->toDateString(),
                $today->copy()->addDays(30)->toDateString(),
            ])
            ->where(function ($q) {
                $q->where('contract_auto_renewal', false)
                  ->orWhereNull('contract_auto_renewal');
            })
            ->count();

        $totals['no_contract_attachment_count'] = (int) $noContractAttachmentCount;
        $totals['expiring_in_30d_count'] = (int) $expiringIn30dCount;

        $className = get_class(new Customer());
        $optionsService = app(\App\Services\OptionsService::class);

        return Inertia::render('Customer/Summary', [
            'summaries' => CustomerPeriodSummaryResource::collection($summaries),
            'totals' => $totals,
            'periodReport' => $request->period_report,
            'periodReportOptions' => $this->periodReportOptions(),
            'rangeStart' => $displayRangeStart,
            'rangeEnd' => $rangeEnd->copy()->endOfMonth()->toDateString(),
            // 5-value Customer Status dropdown options — same shape as
            // CustomerController::index() so both pages stay in sync.
            'statuses' => [
                ['id' => 'all', 'name' => 'All'],
                ...collect(Customer::STATUSES_MAPPING)->map(fn ($status, $index) => [
                    'id' => $index,
                    'name' => $status,
                ]),
            ],
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
                ['id' => 'R+U',   'value' => 'R + U'],
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
            // "Last Month Only" → just the previous completed month (1 row per
            // customer). Distinct from "Last month" which shows current + the
            // last finished month (2 rows).
            ['id' => 'last_month_only', 'value' => 'Last Month Only'],
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
     *   all             → rangeStart = earliest known year_month (clamped to
     *                     self::SUMMARY_FLOOR_DATE — pre-floor rows in the
     *                     table came from the Excel backfill and are
     *                     incomplete); rangeEnd = current month
     *   anything else   → falls back to "current"
     *
     * @return array{0:\Carbon\Carbon,1:\Carbon\Carbon}
     */
    protected function resolvePeriodReportRange(?string $id, \Carbon\Carbon $currentMonthStart): array
    {
        // "Last Month Only" — a single-month window for the previous completed
        // month (excludes the in-progress current month).
        if ($id === 'last_month_only') {
            $lastMonth = $currentMonthStart->copy()->subMonthNoOverflow()->startOfMonth();
            return [$lastMonth, $lastMonth->copy()];
        }

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
            // Hard floor — see self::SUMMARY_FLOOR_DATE. Pre-floor rows exist in
            // the table from the Excel backfill but are incomplete, so we
            // refuse to show or sum them. max() picks the later of the two,
            // i.e. clamps the start forward to the floor when needed.
            $floor = \Carbon\Carbon::parse(self::SUMMARY_FLOOR_DATE)->startOfMonth();
            if ($rangeStart->lt($floor)) {
                $rangeStart = $floor;
            }
            return [$rangeStart, $currentMonthStart->copy()];
        }

        // 'current' (and any unknown value) — single-month window for the
        // in-progress month.
        return [$currentMonthStart->copy(), $currentMonthStart->copy()];
    }

    /**
     * Whether the supplied period_report id should produce ONE aggregated row
     * per customer (true) instead of one row per stored month (false). The
     * single-month windows ('current', 'last_month_only') are NOT aggregated
     * so the user-picked sort column stays primary; every other option
     * (multi-month / all) clusters by customer.
     */
    protected function isAggregatedPeriodReport(?string $id): bool
    {
        return $id !== null && !in_array($id, ['current', 'last_month_only'], true);
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

        $allowed = ['F', 'S', 'R', 'U', 'R+U', 'PS', 'PS+U', 'PSORU'];
        $codes = array_values(array_intersect($values, $allowed));
        if (empty($codes)) {
            return;
        }

        $query->whereIn('customers.contract_commission_type', $codes);
    }

    /**
     * Apply the Summary page's "Contract Attachment?" filter to a
     * customers-table query.
     *
     * Semantics (per spec): show customers that DID ('true' / Yes) or did
     * NOT ('false' / No) have ANY contract attachment uploaded during the
     * selected Period Report window OR LATER — i.e. an attachment of type
     * FILE_TYPE_CONTRACT whose created_at is on/after the period's starting
     * month ($rangeStart). The value 'all' (or absent) is treated as "no
     * filter" so the default selection still returns every customer.
     *
     * Uses the existing Customer::contracts() relation (already scoped to
     * type = FILE_TYPE_CONTRACT) so the type rule stays in one place.
     */
    protected function applyContractAttachmentFilter($query, Request $request, \Carbon\Carbon $rangeStart): void
    {
        $raw = $request->input('contract_attachment');
        if ($raw === null || $raw === '' || $raw === 'all') {
            return;
        }

        // Threshold = first day of the period's starting month. Attachments
        // uploaded on/after this count as "that period or onwards".
        $threshold = $rangeStart->copy()->startOfMonth()->toDateString();

        $wantsContract = filter_var($raw, FILTER_VALIDATE_BOOLEAN);

        $scopeUploadedSince = function ($q) use ($threshold) {
            $q->where('attachments.created_at', '>=', $threshold);
        };

        if ($wantsContract) {
            $query->whereHas('contracts', $scopeUploadedSince);
        } else {
            $query->whereDoesntHave('contracts', $scopeUploadedSince);
        }
    }

    /**
     * Lock a Customer Summary row (action-triggered).
     *
     * Snapshots the row's CURRENT live figures + contract details into the
     * stored columns and stamps locked_at / locked_by. After this the resource
     * renders the frozen snapshot instead of re-deriving live. Only a
     * COMPLETED month can be locked (the in-progress current month has no Lock
     * button and is rejected here defensively). Requires 'admin-access customers'.
     */
    public function lockCustomerPeriodSummary(Request $request, $id)
    {
        $user = auth()->user();
        if (!$user || !$user->can('admin-access customers')) {
            abort(403, 'You do not have permission to lock summary rows.');
        }

        $summary = \App\Models\CustomerPeriodSummary::with(['customer.operator'])->findOrFail($id);

        if ($summary->is_current_month) {
            return back()->withErrors(['lock' => 'The current month cannot be locked until it is complete.']);
        }
        if ($summary->locked_at !== null) {
            return back()->with('success', 'This period is already locked.');
        }

        // Freeze the LIVE values (current contract applied to this month's
        // stored sales/gross) into the stored columns — same derivation the
        // resource uses for unlocked rows, so "what you see is what locks".
        $c = $summary->customer;
        if ($c) {
            $gstRatePct = ($c->relationLoaded('operator') && $c->operator && $c->operator->gst_vat_rate !== null)
                ? (float) $c->operator->gst_vat_rate
                : 0.0;

            $locFeeCents = \App\Services\CustomerSummaryAggregator::computeLocationFeeCents(
                $c->contract_commission_type,
                $c->contract_commission_value !== null ? (float) $c->contract_commission_value : null,
                $c->contract_commission_value2 !== null ? (float) $c->contract_commission_value2 : null,
                $c->contract_ps_term !== null ? (float) $c->contract_ps_term : null,
                (int) $summary->sales_cents,
                (int) $summary->gross_earning_cents,
                $gstRatePct
            );
            $extCents = ($c->is_external_subsidize && $c->external_subsidize_amount !== null)
                ? (int) round(((float) $c->external_subsidize_amount) * 100)
                : 0;
            $earningCents = (int) $summary->gross_earning_cents - ($locFeeCents - $extCents);
            $rate = (int) $summary->sales_cents > 0
                ? round($earningCents / (int) $summary->sales_cents, 4)
                : 0;

            $summary->contract_commission_type = $c->contract_commission_type;
            $summary->contract_commission_value = $c->contract_commission_value;
            $summary->contract_commission_value2 = $c->contract_commission_value2;
            $summary->contract_ps_term = $c->contract_ps_term;
            $summary->location_fees_cents = $locFeeCents;
            $summary->external_subsidize_cents = $extCents;
            $summary->location_earning_cents = $earningCents;
            $summary->location_earning_rate = $rate;
        }

        $summary->locked_at = now();
        $summary->locked_by = $user->id;
        $summary->save();

        return back()->with('success', 'Period locked.');
    }

    /**
     * Unlock a previously-locked Customer Summary row.
     *
     * Restricted to the top-tier roles (superadmin / admin) — a HIGHER access
     * level than locking, per product requirement. Clears locked_at /
     * locked_by so the row reverts to live re-derivation. The frozen stored
     * columns stay as-is but are no longer authoritative (the resource
     * recomputes live while unlocked).
     *
     * Blocked when the row is currently Paid — the Paid state implies the
     * locked snapshot has already been actioned on, so the user must
     * explicitly Unpaid first (audit trail discipline). last_unlocked_at /
     * last_unlocked_by are stamped on every successful unlock so the next
     * Lock cycle's tooltip can show the prior Unlock history.
     */
    public function unlockCustomerPeriodSummary(Request $request, $id)
    {
        $user = auth()->user();
        if (!$user || !$user->hasAnyRole(['superadmin', 'admin'])) {
            abort(403, 'You do not have permission to unlock summary rows.');
        }

        $summary = \App\Models\CustomerPeriodSummary::findOrFail($id);

        // Hard guard: a Paid row must be Unpaid first. This matches the UI
        // (Unlock button is disabled while paid) so the only way to hit this
        // branch is a stale tab / direct POST.
        if ($summary->paid_at !== null) {
            return back()->withErrors(['unlock' => 'This period is marked Paid — Unpaid it first before unlocking.']);
        }

        $summary->locked_at = null;
        $summary->locked_by = null;
        $summary->last_unlocked_at = now();
        $summary->last_unlocked_by = $user->id;
        $summary->save();

        return back()->with('success', 'Period unlocked.');
    }

    /**
     * Mark a locked Customer Summary row as Paid.
     *
     * Same permission as Lock ('admin-access customers') — Paid is a forward
     * action that anyone who can Lock should also be able to apply. Requires
     * the row to be locked first (paid only makes sense on top of a frozen
     * snapshot) and not already paid. Sets paid_at / paid_by; does NOT touch
     * the lock fields.
     */
    public function markPaidCustomerPeriodSummary(Request $request, $id)
    {
        $user = auth()->user();
        if (!$user || !$user->can('admin-access customers')) {
            abort(403, 'You do not have permission to mark summary rows Paid.');
        }

        $summary = \App\Models\CustomerPeriodSummary::findOrFail($id);

        if ($summary->locked_at === null) {
            return back()->withErrors(['paid' => 'Lock the period first before marking it Paid.']);
        }
        if ($summary->paid_at !== null) {
            return back()->with('success', 'This period is already marked Paid.');
        }

        $summary->paid_at = now();
        $summary->paid_by = $user->id;
        $summary->save();

        return back()->with('success', 'Period marked Paid.');
    }

    /**
     * Mark a Paid row as Unpaid (reverses markPaidCustomerPeriodSummary).
     *
     * Same permission tier as Unlock (superadmin / admin) — Unpaid reverses
     * a recorded action, so it sits at the higher access tier. Clears
     * paid_at / paid_by and stamps last_unpaid_at / last_unpaid_by so the
     * tooltip can surface "last unpaid by X at Y" on the next Paid cycle.
     */
    public function markUnpaidCustomerPeriodSummary(Request $request, $id)
    {
        $user = auth()->user();
        if (!$user || !$user->hasAnyRole(['superadmin', 'admin'])) {
            abort(403, 'You do not have permission to mark summary rows Unpaid.');
        }

        $summary = \App\Models\CustomerPeriodSummary::findOrFail($id);

        if ($summary->paid_at === null) {
            return back()->with('success', 'This period is already Unpaid.');
        }

        $summary->paid_at = null;
        $summary->paid_by = null;
        $summary->last_unpaid_at = now();
        $summary->last_unpaid_by = $user->id;
        $summary->save();

        return back()->with('success', 'Period marked Unpaid.');
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
        //
        // We also clamp at self::SUMMARY_FLOOR_DATE so the running sum starts
        // at the floor date — pre-floor rows (reconstructed from
        // Excel) are incomplete and would inflate / distort the lifetime
        // accumulated earning. This must stay in lockstep with the floor
        // applied in resolvePeriodReportRange() so the on-screen rows and
        // their running totals describe the same window.
        $floor = self::SUMMARY_FLOOR_DATE;
        // Ordered by period_start (NOT year_month) and keyed by period_start so
        // a month split into segments accumulates CONTINUOUSLY: segment 1 (e.g.
        // 1st–19th) shows the running total through the 19th, segment 2 (20th–
        // end) shows that plus its own earning — instead of both segments
        // sharing the whole-month total. period_start is unique per row
        // (customer_id, period_start), so it sequences segments correctly.
        $monthlyRows = \Illuminate\Support\Facades\DB::table('customer_period_summaries')
            ->select('customer_id', 'year_month', 'period_start', 'contract_log_id', 'location_earning_cents', 'sales_cents', 'gross_earning_cents', 'locked_at')
            ->whereIn('customer_id', $customerIds)
            ->where('year_month', '>=', $floor)
            ->where('year_month', '<=', $through)
            ->orderBy('customer_id')
            ->orderBy('period_start')
            ->get();

        // Per-customer current contract + operator GST — used to recompute the
        // LIVE vend earning for UNLOCKED months (locked months keep their
        // frozen stored location_earning_cents). One batched query, O(1) lookup.
        $contractMap = \Illuminate\Support\Facades\DB::table('customers')
            ->leftJoin('operators', 'operators.id', '=', 'customers.operator_id')
            ->whereIn('customers.id', $customerIds)
            ->select(
                'customers.id',
                'customers.contract_commission_type',
                'customers.contract_commission_value',
                'customers.contract_commission_value2',
                'customers.contract_ps_term',
                'customers.is_external_subsidize',
                'customers.external_subsidize_amount',
                'operators.gst_vat_rate'
            )
            ->get()
            ->keyBy('id');

        // running[$customer_id][$yearMonthYmd] = cumulative EFFECTIVE vend
        // earning through and including that month. "Effective" = the frozen
        // stored value for locked months, or the live re-derivation for
        // unlocked months — keeping the Accumulate column in lockstep with the
        // per-row Vend Earning shown (which the resource derives the same way).
        $running = [];
        $perCustomerSum = [];
        foreach ($monthlyRows as $r) {
            $cid = $r->customer_id;
            // year_month is a DATE column; raw DB::table returns it as a
            // string (e.g. "2024-03-01" or "2024-03-01 00:00:00"). Carbon
            // normalises both to YYYY-MM-DD so it lines up with the row's
            // own ->year_month->toDateString() below.
            $key = \Carbon\Carbon::parse($r->period_start)->toDateString();

            $effectiveEarning = (int) $r->location_earning_cents; // locked / segment / fallback
            // Segment rows (contract_log_id set) keep their stored per-segment
            // earning — they were computed from that segment's own contract, so
            // re-deriving live with the customer's current contract would be
            // wrong. Only unlocked WHOLE-month rows re-derive live.
            if ($r->locked_at === null && $r->contract_log_id === null) {
                // Unlocked → recompute live from the customer's current contract.
                $c = $contractMap->get($cid);
                if ($c) {
                    $gstRatePct = (float) ($c->gst_vat_rate ?? 0);
                    $liveLocFeeCents = \App\Services\CustomerSummaryAggregator::computeLocationFeeCents(
                        $c->contract_commission_type,
                        $c->contract_commission_value !== null ? (float) $c->contract_commission_value : null,
                        $c->contract_commission_value2 !== null ? (float) $c->contract_commission_value2 : null,
                        $c->contract_ps_term !== null ? (float) $c->contract_ps_term : null,
                        (int) $r->sales_cents,
                        (int) $r->gross_earning_cents,
                        $gstRatePct
                    );
                    $liveExtCents = ($c->is_external_subsidize && $c->external_subsidize_amount !== null)
                        ? (int) round(((float) $c->external_subsidize_amount) * 100)
                        : 0;
                    $effectiveEarning = (int) $r->gross_earning_cents - ($liveLocFeeCents - $liveExtCents);
                }
            }

            $perCustomerSum[$cid] = ($perCustomerSum[$cid] ?? 0) + $effectiveEarning;
            $running[$cid][$key] = $perCustomerSum[$cid];
        }

        foreach ($collection as $row) {
            $rowKey = optional($row->period_start)->toDateString();
            $row->accumulate_vending_earning_cents = (int) ($running[$row->customer_id][$rowKey] ?? 0);
        }
    }

    /**
     * Attach the resolved PREVIOUS-month snapshot to each CURRENT-month row.
     *
     * Drives the month-over-month trend arrows for the current (in-progress)
     * month. In the single-month "Current" view there is no previous-month row
     * on screen to diff against, so without this the latest month shows no
     * arrows at all. We resolve last month's figures the SAME way the row
     * itself is resolved (CustomerPeriodSummaryResource): locked months keep
     * their frozen snapshot; unlocked months are re-derived LIVE from the
     * customer's current contract — mirroring attachAccumulatedVendingEarning()
     * so the comparison is apples-to-apples with what's rendered elsewhere.
     *
     * Only current-month rows get this. The multi-month "Last N months" / "All"
     * views already show the previous month as its own row, and the Vue side
     * diffs against that visible row directly (so the fallback never fires).
     *
     * Cost: two batched queries per page (previous-month rows + the per-customer
     * contract/GST map). Comparison is incomplete-month vs full prior month, so
     * the arrows will typically point down early in the month and climb toward
     * parity as the month progresses — which is the intended signal.
     */
    protected function attachPreviousMonthSummary($collection): void
    {
        if ($collection->isEmpty()) {
            return;
        }

        // (customer_id, previous-month-first-of-month) we need, one per
        // current-month row. Keyed maps keep the later lookups O(1).
        $prevDateByCustomer = [];   // customer_id => 'Y-m-01' (previous month)
        foreach ($collection as $row) {
            if (!$row->is_current_month || $row->customer_id === null || !$row->year_month) {
                continue;
            }
            $prevDateByCustomer[$row->customer_id] = $row->year_month
                ->copy()->startOfMonth()->subMonth()->toDateString();
        }
        if (empty($prevDateByCustomer)) {
            return;
        }

        $customerIds = array_keys($prevDateByCustomer);
        $prevDates = array_values(array_unique($prevDateByCustomer));

        // All candidate previous-month rows in one batched query; filter to the
        // exact (customer, month) pair in-memory via the keyed map below.
        $prevRows = \Illuminate\Support\Facades\DB::table('customer_period_summaries')
            ->select(
                'customer_id', 'year_month', 'sales_cents', 'gross_earning_cents',
                'location_fees_cents', 'external_subsidize_cents',
                'location_earning_cents', 'transaction_count', 'locked_at'
            )
            ->whereIn('customer_id', $customerIds)
            ->whereIn('year_month', $prevDates)
            ->get()
            ->keyBy(fn ($r) => $r->customer_id . '|' . \Carbon\Carbon::parse($r->year_month)->toDateString());

        // Current contract + operator GST per customer — used to re-derive the
        // LIVE figures for unlocked previous months. Same source/shape as
        // attachAccumulatedVendingEarning().
        $contractMap = \Illuminate\Support\Facades\DB::table('customers')
            ->leftJoin('operators', 'operators.id', '=', 'customers.operator_id')
            ->whereIn('customers.id', $customerIds)
            ->select(
                'customers.id',
                'customers.contract_commission_type',
                'customers.contract_commission_value',
                'customers.contract_commission_value2',
                'customers.contract_ps_term',
                'customers.is_external_subsidize',
                'customers.external_subsidize_amount',
                'operators.gst_vat_rate'
            )
            ->get()
            ->keyBy('id');

        foreach ($collection as $row) {
            if (!$row->is_current_month || $row->customer_id === null || !$row->year_month) {
                $row->previous_month_summary = null;
                continue;
            }

            $prevKey = $prevDateByCustomer[$row->customer_id] ?? null;
            $p = $prevKey ? $prevRows->get($row->customer_id . '|' . $prevKey) : null;
            if (!$p) {
                $row->previous_month_summary = null;
                continue;
            }

            $locationFeesCents = (int) $p->location_fees_cents;
            $externalSubsidizeCents = (int) $p->external_subsidize_cents;
            $locationEarningCents = (int) $p->location_earning_cents;

            if ($p->locked_at === null) {
                $c = $contractMap->get($row->customer_id);
                if ($c) {
                    $gstRatePct = (float) ($c->gst_vat_rate ?? 0);
                    $locationFeesCents = \App\Services\CustomerSummaryAggregator::computeLocationFeeCents(
                        $c->contract_commission_type,
                        $c->contract_commission_value !== null ? (float) $c->contract_commission_value : null,
                        $c->contract_commission_value2 !== null ? (float) $c->contract_commission_value2 : null,
                        $c->contract_ps_term !== null ? (float) $c->contract_ps_term : null,
                        (int) $p->sales_cents,
                        (int) $p->gross_earning_cents,
                        $gstRatePct
                    );
                    $externalSubsidizeCents = ($c->is_external_subsidize && $c->external_subsidize_amount !== null)
                        ? (int) round(((float) $c->external_subsidize_amount) * 100)
                        : 0;
                    $locationEarningCents = (int) $p->gross_earning_cents - ($locationFeesCents - $externalSubsidizeCents);
                }
            }

            $row->previous_month_summary = [
                'year_month' => $prevKey,
                'sales_cents' => (int) $p->sales_cents,
                'gross_earning_cents' => (int) $p->gross_earning_cents,
                'location_fees_cents' => $locationFeesCents,
                'external_subsidize_cents' => $externalSubsidizeCents,
                'location_earning_cents' => $locationEarningCents,
                'transaction_count' => (int) $p->transaction_count,
            ];
        }
    }

    /**
     * Flag, per visible row, which contract terms changed during that period
     * versus the previous period — drives the tiny "New" badge beside Placement
     * Contract Type / External Subsidize on the Customer Summary page.
     *
     * The badge must track WHAT IS ON SCREEN. Each row's displayed Placement
     * Contract Detail is lock-aware (mirrors CustomerPeriodSummaryResource):
     *   - a LOCKED row, or a SEGMENT row (contract_log_id set), shows its
     *     frozen per-period snapshot, and
     *   - an unlocked whole-month row shows the customer's LIVE contract.
     * So we resolve the as-displayed contract for each row and compare it to
     * the customer's immediately-preceding period (also resolved as-displayed).
     * The badge then lands exactly where the value visibly changes.
     *
     * This is why we do NOT key off customer_contract_logs effective dates: a
     * contract edit is stamped at the wall-clock edit time, but it visually
     * takes effect from the START of the oldest still-unlocked period (every
     * unlocked period re-derives live). Resolving by edit time put the badge on
     * the wrong period (the current month) and missed the locked→unlocked
     * boundary where the number actually changes.
     *
     * No preceding period (the very first period on record for the customer)
     * → no baseline → no badge, so a brand-new customer isn't flagged.
     *
     * Cost: one batched query per page pulling the visible customers' period
     * summaries (light columns), grouped + ordered so a linear scan finds each
     * row's previous period regardless of pagination.
     */
    protected function attachContractChangeFlags($collection): void
    {
        $default = [
            'placement_type' => false,
            'external_subsidize' => false,
            'contract_until' => false,
            'auto_renewal' => false,
            'notice_period' => false,
        ];

        if ($collection->isEmpty()) {
            return;
        }

        $customerIds = $collection->pluck('customer_id')->filter()->unique()->values()->all();
        if (empty($customerIds)) {
            foreach ($collection as $row) {
                $row->contract_diff = $default;
            }
            return;
        }

        // null / '' / 0 all mean "no value" for these commission fields, so
        // they compare EQUAL — a value2 of null vs 0 must NOT light a phantom
        // "New" badge. A genuine change (0 → 80) is still detected.
        $num = fn ($v) => ($v === null || $v === '') ? '0.00' : number_format((float) $v, 2, '.', '');
        $str = fn ($v) => ($v === null || $v === '') ? null : (string) $v;

        // Live (current) contract terms per customer, taken from the loaded
        // customer relation — the same source the Resource uses to re-derive
        // unlocked rows. Keyed by customer_id.
        $liveByCustomer = [];
        foreach ($collection as $row) {
            $cid = $row->customer_id;
            if ($cid === null || isset($liveByCustomer[$cid])) {
                continue;
            }
            if ($row->relationLoaded('customer') && $row->customer) {
                $c = $row->customer;
                $liveByCustomer[$cid] = [
                    'type' => $c->contract_commission_type,
                    'val'  => $c->contract_commission_value,
                    'val2' => $c->contract_commission_value2,
                    'ps'   => $c->contract_ps_term,
                    'sub'  => ($c->is_external_subsidize && $c->external_subsidize_amount !== null)
                        ? (float) $c->external_subsidize_amount : null,
                ];
            }
        }

        // The as-displayed contract terms for a summary row: locked rows and
        // segment rows show their frozen snapshot; unlocked whole-month rows
        // show the customer's live contract (falling back to the snapshot when
        // the live terms aren't available).
        $effective = function ($s) use ($liveByCustomer) {
            $isLocked  = $s->locked_at !== null;
            $isSegment = $s->contract_log_id !== null;

            if (!$isLocked && !$isSegment && isset($liveByCustomer[$s->customer_id])) {
                return $liveByCustomer[$s->customer_id];
            }

            return [
                'type' => $s->contract_commission_type,
                'val'  => $s->contract_commission_value,
                'val2' => $s->contract_commission_value2,
                'ps'   => $s->contract_ps_term,
                // Snapshot external subsidize is stored in cents on the row.
                'sub'  => $s->external_subsidize_cents !== null
                    ? ((int) $s->external_subsidize_cents) / 100.0 : null,
            ];
        };

        // Every period summary for the visible customers, ordered so a customer's
        // rows (including same-month segments) read oldest → newest. Lets us find
        // each row's immediately-preceding period even when pagination cut it off.
        $summariesByCustomer = \App\Models\CustomerPeriodSummary::query()
            ->whereIn('customer_id', $customerIds)
            ->orderBy('customer_id')
            ->orderBy('period_start')
            ->orderBy('period_end')
            ->orderBy('id')
            ->get([
                'id', 'customer_id', 'period_start', 'period_end',
                'locked_at', 'contract_log_id',
                'contract_commission_type', 'contract_commission_value',
                'contract_commission_value2', 'contract_ps_term',
                'external_subsidize_cents',
            ])
            ->groupBy('customer_id');

        foreach ($collection as $row) {
            $cid = $row->customer_id;
            $list = $summariesByCustomer->get($cid);

            if ($cid === null || $list === null || !$row->period_start) {
                $row->contract_diff = $default;
                continue;
            }

            // The entry immediately before this row in period order. The list
            // is ordered, so the last entry seen before this row's id is the
            // previous period (or previous segment within a split month).
            $prev = null;
            foreach ($list as $s) {
                if ((int) $s->id === (int) $row->id) {
                    break;
                }
                $prev = $s;
            }

            // No preceding period → no baseline → first period isn't flagged.
            if ($prev === null) {
                $row->contract_diff = $default;
                continue;
            }

            $cur     = $effective($row);
            $prevEff = $effective($prev);

            $placementChanged =
                $str($cur['type']) !== $str($prevEff['type'])
                || $num($cur['val'])  !== $num($prevEff['val'])
                || $num($cur['val2']) !== $num($prevEff['val2'])
                || $num($cur['ps'])   !== $num($prevEff['ps']);

            $row->contract_diff = array_merge($default, [
                'placement_type' => $placementChanged,
                // External Subsidize is shown per row, so a genuine change
                // (e.g. none → $90) is visible on screen and flagged "New".
                'external_subsidize' => $num($cur['sub']) !== $num($prevEff['sub']),
                // End date / auto-renewal / notice period render as the
                // customer's LIVE value on every row and never change a figure
                // in the row, so diffing them would surface a "New" badge next
                // to an identical-looking value. Left unflagged on purpose.
            ]);
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
            // Customer Status — mirror summary(): default to Active and let
            // filterIndex resolve `status` via status_id. The legacy is_active
            // default is intentionally gone; with the new `status` filter,
            // forcing is_active=true would conflict (e.g. status=Inactive AND
            // is_active=true => 0 rows; status=All => only active exported).
            'status' => $request->status ?: Customer::STATUS_ACTIVE,
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
        // Mirror summary()'s Contract Attachment? filter so the export
        // honours the same dropdown selection.
        $this->applyContractAttachmentFilter($customerIdsQuery, $request, $rangeStart);
        $customerIds = $customerIdsQuery->pluck('customers.id')->unique()->values();

        // Mirror summary()'s per-month grain in the export — one row per
        // (customer, year_month) regardless of period_report. The user
        // can collapse / pivot in Excel themselves if they want a
        // roll-up.
        $eagerLoads = [
            // Column-selection eager load — must include every column the
            // export references below. Contract + subsidy fields and operator
            // GST are needed so the export can re-derive UNLOCKED rows live,
            // exactly like the on-screen resource (CustomerPeriodSummaryResource).
            // contract_until / contract_auto_renewal / contract_notice_period
            // and `notes` were added so the export now carries the stacked
            // "Contract End Date / Auto Renewal / Notice Period" + Customer
            // Note columns the on-screen page shows.
            'customer:id,name,company_remark,code,virtual_customer_code,virtual_customer_prefix,operator_id,selling_price_type,location_type_id,location_grading_placement,location_grading_access,location_grading_flexibility,begin_date,termination_date,contract_commission_type,contract_commission_value,contract_commission_value2,contract_ps_term,is_external_subsidize,external_subsidize_amount,contract_until,contract_auto_renewal,contract_notice_period,notes',
            'customer.operator:id,code,name,gst_vat_rate',
            'customer.tagBindings.tag:id,name',
            'customer.deliveryAddress',
            'customer.locationType:id,name',
            'customer.vend:id,customer_id,code,vend_prefix_id',
            'customer.vend.vendPrefix:id,name',
            // Drives the Contact Person + Contact Phone columns (morphOne).
            'customer.contact:id,modelable_id,modelable_type,name,phone_num,alt_phone_num',
            // Drives the "Contract Attachment" Yes/No column — we only need to
            // know whether the customer has at least one FILE_TYPE_CONTRACT
            // attachment, so this is just the id list (very cheap).
            'customer.contracts:id,modelable_id,modelable_type',
            // Period-summary audit relations — drive the Locked/Unlocked/
            // Paid/Unpaid/Report-Emailed "by" columns. All filtered to id+name
            // so we don't drag whole user rows into memory.
            'lockedBy:id,name',
            'lastUnlockedBy:id,name',
            'paidBy:id,name',
            'lastUnpaidBy:id,name',
            'reportEmailedBy:id,name',
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
            'R+U'   => 'Fix Rental + Utility',
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
                    $sep = $type === 'PSORU' ? ' or $' : ' + $';
                    $base .= $sep . rtrim(rtrim(number_format((float) $val2, 2, '.', ''), '0'), '.');
                }
                if ($psTerm !== null) {
                    $base .= ' (PS Term ' . rtrim(rtrim(number_format((float) $psTerm, 2, '.', ''), '0'), '.') . '%)';
                }
                return $base;
            }
            if ($type === 'R+U') {
                // Flat Fix Rental + Utility, both dollar amounts.
                $rental  = $val !== null ? '$' . number_format((float) $val, 2) : '$0.00';
                $utility = $val2 !== null ? '$' . number_format((float) $val2, 2) : '$0.00';
                return $rental . ' + ' . $utility;
            }
            return $val !== null ? '$' . number_format((float) $val, 2) : '';
        };

        // Pre-fetch lifetime "Accumulate Vending Earning" per customer so the
        // streamed exporter can resolve it via O(1) map lookup instead of
        // running a per-row aggregate query. Vending Earning =
        // gross_earning_cents - location_fees_cents (already pre-computed and
        // stored as location_earning_cents on each monthly summary row).
        $accumThrough = $rangeEnd->copy()->startOfMonth()->toDateString();
        // Floor matches attachAccumulatedVendingEarning() — pre-floor rows are
        // incomplete and excluded from the lifetime sum so the export agrees
        // with what's rendered on screen.
        //
        // Lock-aware: each month contributes its EFFECTIVE vend earning —
        // the frozen stored value for locked months, or the live re-derivation
        // (current contract applied to that month's stored sales/gross) for
        // unlocked months. Mirrors attachAccumulatedVendingEarning() so the
        // export's Accumulate column matches the on-screen figure.
        $accumRows = \Illuminate\Support\Facades\DB::table('customer_period_summaries')
            ->select('customer_id', 'year_month', 'period_start', 'contract_log_id', 'location_earning_cents', 'sales_cents', 'gross_earning_cents', 'locked_at')
            ->whereIn('customer_id', $customerIds)
            ->where('year_month', '>=', self::SUMMARY_FLOOR_DATE)
            ->where('year_month', '<=', $accumThrough)
            ->orderBy('customer_id')
            ->orderBy('period_start')
            ->get();
        $accumContractMap = \Illuminate\Support\Facades\DB::table('customers')
            ->leftJoin('operators', 'operators.id', '=', 'customers.operator_id')
            ->whereIn('customers.id', $customerIds)
            ->select(
                'customers.id',
                'customers.contract_commission_type',
                'customers.contract_commission_value',
                'customers.contract_commission_value2',
                'customers.contract_ps_term',
                'customers.is_external_subsidize',
                'customers.external_subsidize_amount',
                'operators.gst_vat_rate'
            )
            ->get()
            ->keyBy('id');
        // accumulateMap[customer_id][YYYY-MM-DD] = RUNNING cumulative effective
        // vend earning through that month — so each exported row shows the
        // per-month running figure exactly like the web table, not a single
        // lifetime total repeated on every row. Rows are ordered ascending by
        // (customer, year_month) above so one linear pass builds the prefix sum.
        // Keyed by period_start (NOT year_month) so a segmented month accumulates
        // continuously across its segments rather than both sharing the month
        // total. Segment rows (contract_log_id set) keep their stored earning.
        $accumulateMap = [];
        $accumRunning = [];
        foreach ($accumRows as $r) {
            $eff = (int) $r->location_earning_cents;
            if ($r->locked_at === null && $r->contract_log_id === null) {
                $c = $accumContractMap->get($r->customer_id);
                if ($c) {
                    $gstRatePct = (float) ($c->gst_vat_rate ?? 0);
                    $fee = \App\Services\CustomerSummaryAggregator::computeLocationFeeCents(
                        $c->contract_commission_type,
                        $c->contract_commission_value !== null ? (float) $c->contract_commission_value : null,
                        $c->contract_commission_value2 !== null ? (float) $c->contract_commission_value2 : null,
                        $c->contract_ps_term !== null ? (float) $c->contract_ps_term : null,
                        (int) $r->sales_cents,
                        (int) $r->gross_earning_cents,
                        $gstRatePct
                    );
                    $ext = ($c->is_external_subsidize && $c->external_subsidize_amount !== null)
                        ? (int) round(((float) $c->external_subsidize_amount) * 100)
                        : 0;
                    $eff = (int) $r->gross_earning_cents - ($fee - $ext);
                }
            }
            $accumRunning[$r->customer_id] = ($accumRunning[$r->customer_id] ?? 0) + $eff;
            $psKey = \Carbon\Carbon::parse($r->period_start)->toDateString();
            $accumulateMap[$r->customer_id][$psKey] = $accumRunning[$r->customer_id];
        }

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

                // Lock-aware live re-derivation — mirrors
                // CustomerPeriodSummaryResource: an UNLOCKED row reflects the
                // customer's CURRENT contract (contract details + Location
                // Fees + Vend Earning re-derived from the row's stored
                // sales/gross), while a LOCKED row keeps its frozen snapshot.
                // Runs AFTER the invoice-snapshot override so the live recompute
                // uses the same sales/gross base the on-screen page does.
                if ($row->locked_at === null && $customer) {
                    $gstRatePct = optional($customer->operator)->gst_vat_rate !== null
                        ? (float) $customer->operator->gst_vat_rate
                        : 0.0;
                    $row->contract_commission_type = $customer->contract_commission_type;
                    $row->contract_commission_value = $customer->contract_commission_value;
                    $row->contract_commission_value2 = $customer->contract_commission_value2;
                    $row->contract_ps_term = $customer->contract_ps_term;
                    $liveFee = \App\Services\CustomerSummaryAggregator::computeLocationFeeCents(
                        $customer->contract_commission_type,
                        $customer->contract_commission_value !== null ? (float) $customer->contract_commission_value : null,
                        $customer->contract_commission_value2 !== null ? (float) $customer->contract_commission_value2 : null,
                        $customer->contract_ps_term !== null ? (float) $customer->contract_ps_term : null,
                        (int) $row->sales_cents,
                        (int) $row->gross_earning_cents,
                        $gstRatePct
                    );
                    $liveExt = ($customer->is_external_subsidize && $customer->external_subsidize_amount !== null)
                        ? (int) round(((float) $customer->external_subsidize_amount) * 100)
                        : 0;
                    $row->location_fees_cents = $liveFee;
                    // Stamp the live External Subsidize back on the row so the
                    // new dedicated "External Subsidize" / "Net Loc Fee" columns
                    // below render the same value as the on-screen page (which
                    // also lives-derives this for unlocked rows).
                    $row->external_subsidize_cents = $liveExt;
                    $row->location_earning_cents = (int) $row->gross_earning_cents - ($liveFee - $liveExt);
                    $row->location_earning_rate = (int) $row->sales_cents > 0
                        ? round($row->location_earning_cents / (int) $row->sales_cents, 4)
                        : 0;
                }

                // Sales — split the on-screen stacked "Sales ($) / (w/ GST) /
                // (excl GST)" cell into two columns. The excl-GST figure is
                // the de-grossed sales used for PS-family math; matches the
                // primary line shown on the page.
                $gstRatePct = optional(optional($customer)->operator)->gst_vat_rate !== null
                    ? (float) $customer->operator->gst_vat_rate
                    : 0.0;
                $salesInclGstAmt = round(((int) $row->sales_cents) / $divisor, 2);
                $salesExclGstAmt = $gstRatePct > 0
                    ? round(((int) $row->sales_cents) / $divisor / (1 + $gstRatePct / 100), 2)
                    : $salesInclGstAmt;
                // Gross Earning Rate — mirrors Summary.vue's grossEarningRate():
                // gross_earning_cents / sales_excl_gst_cents.
                $salesExclGstCents = $gstRatePct > 0
                    ? ((int) $row->sales_cents) / (1 + $gstRatePct / 100)
                    : (int) $row->sales_cents;
                $grossEarningRatePct = $salesExclGstCents > 0
                    ? round(((int) $row->gross_earning_cents) / $salesExclGstCents * 100, 2)
                    : null;

                // Location Fees / External Subsidize / Net Loc Fee — three
                // dedicated columns to mirror the on-screen stacked cell.
                $extSubAmt = round(((int) ($row->external_subsidize_cents ?? 0)) / $divisor, 2);
                $netLocFeeAmt = round((((int) $row->location_fees_cents) - ((int) ($row->external_subsidize_cents ?? 0))) / $divisor, 2);

                // Contract Attachment Y/N — same gate the on-screen badge uses
                // (any FILE_TYPE_CONTRACT attachment present → Yes). We rely on
                // the eager load above; relationLoaded() returns false inside
                // cursor() context (existing quirk — see the Location Type
                // comment below), so check the collection directly.
                $hasContract = $customer
                    && $customer->contracts !== null
                    && $customer->contracts->isNotEmpty();

                $fmtAuditDate = fn ($v) => $v ? \Carbon\Carbon::parse($v)->format('Y-m-d H:i') : '';

                return [
                    '#' => $rowIndex,
                    'Customer ID' => $customer ? ($customer->id + Customer::RUNNING_NUMBER_INIT) : null,
                    'Customer Name' => $customer?->name,
                    // Company (CMS-mirrored `company_remark`) and Contact Person
                    // (morphOne Contact relation). These exist on the customer
                    // record but aren't shown in the on-screen Summary table;
                    // useful in the export for offline contact lookup.
                    'Company' => $customer?->company_remark,
                    'Contact Person' => optional($customer?->contact)->name,
                    'Contact Phone' => optional($customer?->contact)->phone_num,
                    'Contact Alt Phone' => optional($customer?->contact)->alt_phone_num,
                    'Ref Price' => $customer?->selling_price_type ? ('RP' . $customer->selling_price_type) : null,
                    // New: Begin Date + Contract Attachment — the on-screen
                    // Customer cell stacks these below the name.
                    'Begin Date' => $customer?->begin_date ? \Carbon\Carbon::parse($customer->begin_date)->format('ymd') : null,
                    'Contract Attachment' => $hasContract ? 'Yes' : 'No',
                    'Address' => $fullAddress,
                    'Period Report (YYMM)' => $row->year_month ? \Carbon\Carbon::parse($row->year_month)->format('ym') : null,
                    'Machine ID' => $vend?->code,
                    'Machine Prefix' => $vend && $vend->relationLoaded('vendPrefix') ? optional($vend->vendPrefix)->name : null,
                    'Period Start Date' => $row->period_start ? (\Carbon\Carbon::parse($row->period_start))->format('ymd') : null,
                    'Period End Date' => $row->period_end ? (\Carbon\Carbon::parse($row->period_end))->format('ymd') : null,
                    // Sales — split into incl-GST and excl-GST to mirror the
                    // on-screen stacked cell.
                    'Sales ($) (incl GST)' => $salesInclGstAmt,
                    'Sales ($) (excl GST)' => $salesExclGstAmt,
                    // Gross Earning — split into value + Rate (vs sales-excl-GST).
                    'Gross Earning (excl GST)' => round(((int) $row->gross_earning_cents) / $divisor, 2),
                    'Gross Earning Rate %' => $grossEarningRatePct,
                    '# of Job' => (int) $row->job_count,
                    'Placement Contract Type' => $contractTypeLabels[$row->contract_commission_type] ?? $row->contract_commission_type,
                    'Location Fees Rate' => $formatLocationFeesRate($row),
                    // Location Fees / External Subsidize / Net Loc Fee — split
                    // out of the on-screen stacked cell.
                    'Location Fees' => round(((int) $row->location_fees_cents) / $divisor, 2),
                    'External Subsidize' => $extSubAmt,
                    'Net Loc Fee' => $netLocFeeAmt,
                    'Vending Earning' => round(((int) $row->location_earning_cents) / $divisor, 2),
                    'Vending Earning Rate %' => round(((float) $row->location_earning_rate) * 100, 2),
                    'Accumulate Vending Earning' => round(((int) ($accumulateMap[$row->customer_id][
                        $row->period_start instanceof \Carbon\Carbon
                            ? $row->period_start->toDateString()
                            : \Carbon\Carbon::parse($row->period_start)->toDateString()
                    ] ?? 0)) / $divisor, 2),
                    // Location Grading: matches Summary.vue's "P, A, F" tooltip
                    // format — "{placement}, {access}, {flexibility}", each
                    // letter or '-' when blank. Hidden entirely if all three
                    // are null so the column doesn't carry meaningless "-, -, -"
                    // rows.
                    'Location Grading' => $this->formatLocationGradingForExport($customer),
                    'Location Type' => optional($customer?->locationType)->name,
                    // New: Contract terms column on screen is three stacked
                    // sub-fields — split here.
                    'Contract End Date' => $customer?->contract_until ? \Carbon\Carbon::parse($customer->contract_until)->format('ymd') : null,
                    'Auto Renewal' => $customer ? ($customer->contract_auto_renewal ? 'Yes' : 'No') : '',
                    'Notice Period' => $customer?->contract_notice_period,
                    'Customer Tag' => $tagNames,
                    // New: Customer Note (stacked with Customer Tag on screen).
                    'Customer Note' => $customer?->notes,
                    // Lock / Paid / Unlocked / Unpaid audit — the on-screen
                    // Period Verify & Lock column shows all of these; split into
                    // separate columns here so the CSV/Excel is easy to filter.
                    // Note: relationLoaded() returns false in cursor() context
                    // (see Location Type comment above), so we read through
                    // optional() directly — the relations ARE eager-loaded.
                    'Locked At' => $fmtAuditDate($row->locked_at),
                    'Locked By' => optional($row->lockedBy)->name,
                    'Last Unlocked At' => $fmtAuditDate($row->last_unlocked_at),
                    'Last Unlocked By' => optional($row->lastUnlockedBy)->name,
                    'Paid At' => $fmtAuditDate($row->paid_at),
                    'Paid By' => optional($row->paidBy)->name,
                    'Last Unpaid At' => $fmtAuditDate($row->last_unpaid_at),
                    'Last Unpaid By' => optional($row->lastUnpaidBy)->name,
                    // New: Email Performance Report audit (modal action; only
                    // populated on locked rows that were emailed).
                    'Report Emailed At' => $fmtAuditDate($row->report_emailed_at),
                    'Report Emailed By' => optional($row->reportEmailedBy)->name,
                    'CMS Txn #' => $cmsTxnId,
                ];
            }
        );
    }

    /**
     * Format a customer's three Location Grading picks for the Summary export.
     * Mirrors Summary.vue's render style: "{placement}, {access}, {flexibility}"
     * with '-' for blanks. Returns null when all three are null so empty rows
     * don't render a meaningless "-, -, -" string.
     *
     * @param  \App\Models\Customer|null  $customer
     * @return string|null
     */
    protected function formatLocationGradingForExport($customer): ?string
    {
        if (!$customer) return null;
        $p = $customer->location_grading_placement;
        $a = $customer->location_grading_access;
        $f = $customer->location_grading_flexibility;
        if ($p === null && $a === null && $f === null) {
            return null;
        }
        return ($p ?: '-') . ', ' . ($a ?: '-') . ', ' . ($f ?: '-');
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

        // Returns JSON for AJAX callers (the Vue page now uses axios so the
        // request survives the mailto handoff — see onModalEmailClicked) and
        // falls back to a redirect for any browser-form caller.
        $wantsJson = $request->wantsJson() || $request->ajax();
        $fail = function (string $message, int $status) use ($wantsJson) {
            if ($wantsJson) {
                return response()->json(['message' => $message], $status);
            }
            return redirect()->back()->withErrors(['send_performance_report' => $message]);
        };

        if (!$customer->is_report_email_enabled || empty($customer->report_email)) {
            return $fail('This customer has not opted-in to performance report emails. Enable it from the customer edit page first.', 422);
        }

        // The Vue side now composes the actual email via `mailto:` (the
        // operator's own mail client sends it), so we don't dispatch a Mailable
        // here anymore. This endpoint's job is purely to stamp the audit so the
        // team has a record of who clicked send for which (customer, period).
        //
        // Guards: row must exist for the (customer, period_start, period_end)
        // triple AND must be locked — audited mailing is a post-lock action.
        // Direct column comparison so the new (customer_id, period_start)
        // unique index resolves this as a primary-key-like lookup. Using
        // whereDate() wraps the column in DATE(...) and disqualifies the
        // index.
        $summary = \App\Models\CustomerPeriodSummary::query()
            ->where('customer_id', $customer->id)
            ->where('period_start', $request->period_start)
            ->where('period_end', $request->period_end)
            ->first();

        if (!$summary) {
            return $fail('Could not find the matching summary row for that period.', 404);
        }

        if ($summary->locked_at === null) {
            return $fail('Only locked periods can be marked as emailed — lock the row first.', 422);
        }

        $summary->forceFill([
            'report_emailed_at' => now(),
            'report_emailed_by' => auth()->id(),
        ])->save();

        $message = "Recorded email send for {$customer->report_email} ({$request->period_start} → {$request->period_end}).";

        if ($wantsJson) {
            $user = auth()->user();
            return response()->json([
                'message' => $message,
                'report_emailed_at' => optional($summary->report_emailed_at)->toDateTimeString(),
                'report_emailed_by_user' => $user ? ['id' => $user->id, 'name' => $user->name] : null,
            ]);
        }

        return redirect()->back()->with('success', $message);
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

        // Eager-load operator so CustomerInvoiceService can read
        // operator->gst_vat_rate for PS-family GST de-grossing without an
        // extra lazy query.
        $customer = Customer::with('operator:id,gst_vat_rate')->findOrFail($id);

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
            // Eager-load operator so CustomerInvoiceService can read
            // operator->gst_vat_rate for PS-family GST de-grossing without
            // an extra lazy query per bulk entry.
            $customer = Customer::with('operator:id,gst_vat_rate')->find($entry['customer_id']);
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
            'bankOptions' => $optionsService->banks(),
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

    /**
     * Persist a customer-level free-text note from the Customer Summary
     * page (Customer Tag column). Mirrors ProductController::updateRemarks
     * — the note + audit stamp live directly on the customer record so
     * the value survives any period/filter combination on the Summary
     * page. Returning a fresh customer payload (with the notesUpdatedBy
     * user) lets the Vue page refresh the "last updated by" line without
     * a full router reload.
     */
    public function updateNotes(Request $request, $customerId)
    {
        $customer = Customer::findOrFail($customerId);
        $customer->notes = $request->notes;
        $customer->notes_updated_by = auth()->user()->id;
        $customer->notes_updated_at = Carbon::now();
        $customer->save();

        return redirect()->back();
    }

    /**
     * Inline-update the customer's Ops Note (refilling/operations free-text
     * field shown in the "Refilling Routes" column on Vend/CustomerIndex).
     * Same shape as updateNotes() — write the value plus a (by, at) audit
     * pair, no return payload, the caller does a partial reload.
     */
    public function updateOpsNote(Request $request, $customerId)
    {
        $customer = Customer::findOrFail($customerId);
        $customer->ops_note = $request->ops_note;
        $customer->ops_note_updated_by = auth()->user()->id;
        $customer->ops_note_updated_at = Carbon::now();
        $customer->save();

        return redirect()->back();
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
                'bank',
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
            // Notice Period dropdown — see Customer::NOTICE_PERIOD_OPTIONS.
            'noticePeriodOptions' => Customer::NOTICE_PERIOD_OPTIONS,
            'operatorOptions' => $optionsService->operators(),
            'bankOptions' => $optionsService->banks(),
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

    /**
     * Create/refresh the billing address row (addresses.type = 1) for a
     * customer. When $same is true we mirror the delivery payload into the
     * billing row (so anything reading billingAddress always has a valid
     * address); otherwise we persist the dedicated billing payload. No-ops
     * when the chosen source has no postcode.
     *
     * @param array|null $deliveryAddr
     * @param array|null $billingAddr
     */
    private function syncBillingAddress(Customer $customer, bool $same, $deliveryAddr, $billingAddr): void
    {
        $fields = ['postcode', 'unit_num', 'block_num', 'building', 'street_name', 'country_id', 'latitude', 'longitude'];
        $src = $same ? $deliveryAddr : $billingAddr;

        if (!is_array($src) || empty($src['postcode'])) {
            return;
        }

        $customer->billingAddress()->updateOrCreate(
            ['type' => Customer::ADDRESS_TYPE_BILLING],
            collect($src)->only($fields)->toArray()
        );
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
            $billingSame = filter_var($request->is_billing_same_as_delivery ?? true, FILTER_VALIDATE_BOOLEAN);

            $rules = [
                'name' => 'required',
                // Site-level contact (stored on customers table). Phone is plain
                // text — no country code (single-country localized deployment).
                'site_contact_person' => 'nullable|string|max:191',
                'site_phone_number' => 'nullable|string|max:50|regex:/^[0-9+\-\s()]+$/',
                'site_alt_phone_number' => 'nullable|string|max:50|regex:/^[0-9+\-\s()]+$/',
                // Free-text remarks for the delivery address.
                'address_remarks' => 'nullable|string|max:5000',
                // CMS Linking ID — manually entered CMS person id. Must be
                // unique so two sites can't link to the same CMS person, which
                // would double-target API invoicing. Empty → null (nullable),
                // so unlinked sites are unaffected.
                'person_id' => 'nullable|integer|unique:customers,person_id',
            ];
            if (!$billingSame) {
                // "Billing Address same as Delivery" is unchecked → the billing
                // fields are shown and must be filled.
                $rules['billing_address.postcode'] = 'required';
                $rules['billing_address.country_id'] = 'required';
            }
            // Bank Details — required together: if any bank field is filled,
            // all three must be provided.
            if ($request->bank_id || $request->bank_account_name || $request->bank_account_number) {
                $rules['bank_id'] = 'required|integer';
                $rules['bank_account_name'] = 'required|string|max:191';
                $rules['bank_account_number'] = 'required|string|max:191';
            }
            $request->validate($rules);

            // Status drives the (now-derived) is_active mirror, same as update().
            // The create form sends an integer status_id (defaults to Potential);
            // fall back to Active when absent/invalid so the NOT NULL status_id
            // column always gets a valid value, and keep is_active in sync so
            // legacy infra reading the boolean stays correct.
            $createData = $request->all();
            $statusId = (isset($createData['status_id']) && $createData['status_id'] !== '' && $createData['status_id'] !== null)
                ? (int) $createData['status_id']
                : Customer::STATUS_ACTIVE;
            if (!array_key_exists($statusId, Customer::STATUSES_MAPPING)) {
                $statusId = Customer::STATUS_ACTIVE;
            }
            $createData['status_id'] = $statusId;
            $createData['is_active'] = ($statusId === Customer::STATUS_ACTIVE);

            $customer = Customer::create($createData);

            if ($request->contact and isset($request->contact['name']) and $request->contact['name']) {
                $customer->contact()->updateOrCreate($request->contact);
            }

            if ($request->address and isset($request->address['postcode']) and $request->address['postcode']) {
                $customer->deliveryAddress()->updateOrCreate([
                    'type' => Customer::ADDRESS_TYPE_DELIVERY,
                ], $request->address);
            }

            $this->syncBillingAddress($customer, $billingSame, $request->address, $request->billing_address);
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
        // Status drives the (now-derived) is_active mirror. The form sends an
        // integer status_id; default to Active when absent/invalid so the
        // NOT NULL customers.status_id column always receives a valid value.
        $statusId = (isset($requestCustomerArr['status_id']) && $requestCustomerArr['status_id'] !== '' && $requestCustomerArr['status_id'] !== null)
            ? (int) $requestCustomerArr['status_id']
            : Customer::STATUS_ACTIVE;
        if (!array_key_exists($statusId, Customer::STATUSES_MAPPING)) {
            $statusId = Customer::STATUS_ACTIVE;
        }
        $requestCustomerArr['status_id'] = $statusId;
        // Keep the legacy boolean in sync: only "Active" maps to is_active=true.
        $requestCustomerArr['is_active'] = ($statusId === Customer::STATUS_ACTIVE);

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
            $twoValueTypes = ['PS+U', 'PSORU', 'R+U'];

            $contractRules = [
                'customer.contract_commission_type'        => 'nullable|in:F,S,R,U,R+U,PS,PS+U,PSORU',
                'customer.contract_commission_value'       => [
                    'nullable',
                    'numeric',
                    'min:0',
                    ...($commissionType && in_array($commissionType, $psTypes) ? ['max:100'] : []),
                ],
                'customer.contract_commission_value2'      => 'nullable|numeric|min:0',
                'customer.contract_ps_term'                => 'nullable|numeric|min:0|max:100',
                // External Subsidize — toggle + optional dollar amount.
                'customer.is_external_subsidize'           => 'nullable|boolean',
                'customer.external_subsidize_amount'       => 'nullable|numeric|min:0',
                'customer.contract_from'                   => 'nullable|date',
                'customer.contract_until'                  => 'nullable|date',
                'customer.contract_auto_renewal'           => 'nullable|boolean',
                'customer.contract_notice_period'          => 'nullable|string|in:' . implode(',', Customer::NOTICE_PERIOD_OPTIONS),
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
                // "Payment To" tracking (sys-only) — who Location Fees are
                // paid to, and whether that payee is GST registered.
                'customer.payment_to'                      => 'nullable|string|max:191',
                'customer.is_gst_registered'               => 'nullable|boolean',
                // Site-level contact (stored on customers table). Phone is plain
                // text — no country code (single-country localized deployment).
                'customer.site_contact_person'             => 'nullable|string|max:191',
                'customer.site_phone_number'               => 'nullable|string|max:50|regex:/^[0-9+\-\s()]+$/',
                'customer.site_alt_phone_number'           => 'nullable|string|max:50|regex:/^[0-9+\-\s()]+$/',
                // Free-text remarks for the delivery address.
                'customer.address_remarks'                 => 'nullable|string|max:5000',
                // CMS Linking ID — unique across customers (ignoring this row),
                // so two sites can't link to the same CMS person. Empty → null
                // (nullable), so unlinked sites are unaffected.
                'customer.person_id'                       => 'nullable|integer|unique:customers,person_id,' . $customer->id,
            ];

            $request->validate($contractRules);

            // Billing address required only when "same as delivery" is off.
            if (!filter_var($requestCustomerArr['is_billing_same_as_delivery'] ?? true, FILTER_VALIDATE_BOOLEAN)) {
                $request->validate([
                    'customer.billing_address.postcode' => 'required',
                    'customer.billing_address.country_id' => 'required',
                ]);
            }

            // Bank Details — required together (nested under customer.* on update).
            if (!empty($requestCustomerArr['bank_id']) || !empty($requestCustomerArr['bank_account_name']) || !empty($requestCustomerArr['bank_account_number'])) {
                $request->validate([
                    'customer.bank_id' => 'required|integer',
                    'customer.bank_account_name' => 'required|string|max:191',
                    'customer.bank_account_number' => 'required|string|max:191',
                ]);
            }

            // Defensive: never let the DB hold "enabled = true" with a NULL/
            // empty email. The Vue side already enforces this on the form,
            // but if someone POSTs directly we still want clean data so the
            // Summary page's button-visibility flag is meaningful.
            if (empty($requestCustomerArr['report_email'])) {
                $requestCustomerArr['is_report_email_enabled'] = false;
                $request->merge(['customer' => $requestCustomerArr]);
            }

            // External Subsidize — when the toggle is off, never persist a
            // stray amount. Mirrors the Vue-side clear so direct POSTs also
            // store clean data.
            if (empty($requestCustomerArr['is_external_subsidize'])) {
                $requestCustomerArr['is_external_subsidize'] = false;
                $requestCustomerArr['external_subsidize_amount'] = null;
                $request->merge(['customer' => $requestCustomerArr]);
            }

            // Detect if any contract detail field changed → log audit
            $contractFields = [
                'contract_commission_type', 'contract_commission_value', 'contract_commission_value2',
                'contract_ps_term', 'is_external_subsidize', 'external_subsidize_amount',
                'contract_from', 'contract_until', 'contract_auto_renewal',
                'contract_notice_period', 'contract_remarks',
            ];
            $contractChanged = false;

            // Field groups for type-aware comparison. A naive (string) compare
            // produced FALSE POSITIVES — e.g. a bool stored as true ("1") vs a
            // form value of "true", or a decimal stored "9.00" vs an incoming
            // "9", or a date "2027-12-27" vs an ISO "2027-12-27T00:00:00". Those
            // spurious diffs wrote a new customer_contract_logs version on every
            // re-save, which in turn split the month into segments and lit the
            // "New" badge even though nothing actually changed. Normalise each
            // field by its real type before comparing so only genuine edits log.
            $boolFields = ['is_external_subsidize', 'contract_auto_renewal'];
            $numFields  = ['contract_commission_value', 'contract_commission_value2', 'contract_ps_term', 'external_subsidize_amount'];
            $dateFields = ['contract_from', 'contract_until'];

            $normBool = fn ($v) => filter_var($v, FILTER_VALIDATE_BOOLEAN);
            $normNum  = function ($v) {
                if ($v === null || $v === '') {
                    return null;
                }
                return number_format((float) $v, 4, '.', '');
            };
            $normDate = function ($v) {
                if ($v === null || $v === '') {
                    return null;
                }
                try {
                    return \Carbon\Carbon::parse($v)->toDateString();
                } catch (\Throwable $e) {
                    return (string) $v;
                }
            };
            $normStr = fn ($v) => $v === null ? '' : trim((string) $v);

            foreach ($contractFields as $field) {
                $incoming = $requestCustomerArr[$field] ?? null;
                $existing = $customer->{$field};

                if (in_array($field, $boolFields, true)) {
                    $changed = $normBool($incoming) !== $normBool($existing);
                } elseif (in_array($field, $numFields, true)) {
                    $changed = $normNum($incoming) !== $normNum($existing);
                } elseif (in_array($field, $dateFields, true)) {
                    $changed = $normDate($incoming) !== $normDate($existing);
                } else {
                    $changed = $normStr($incoming) !== $normStr($existing);
                }

                if ($changed) {
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
                    'is_external_subsidize' => (bool) $customer->is_external_subsidize,
                    'external_subsidize_amount' => $customer->external_subsidize_amount,
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

            $this->syncBillingAddress(
                $customer,
                filter_var($requestCustomerArr['is_billing_same_as_delivery'] ?? true, FILTER_VALIDATE_BOOLEAN),
                $request->customer['address'] ?? null,
                $request->customer['billing_address'] ?? null
            );

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
            // Mirror index()'s Customer Status filter so the export honours the
            // same status selection sent from the Customer Index page.
            'status' => $request->status ? $request->status : Customer::STATUS_ACTIVE,
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
            'R+U'   => 'Fix Rental + Utility',
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
                    'R+U'           => 'Fix Rental + Utility Amt',
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
                    'Status'                        => Customer::STATUSES_MAPPING[$customer->status_id] ?? '—',
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
                    'Notice Period'                 => $customer->contract_notice_period,
                    'Contract Remarks'              => $customer->contract_remarks,
                ];
            }
        );
    }
}
