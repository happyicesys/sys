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
use App\Models\Category;
use App\Models\CategoryGroup;
use App\Models\Country;
use App\Models\Customer;
use App\Models\CustomerContractLog;
use App\Models\CustomerScheduledContract;
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
use App\Services\CmsPersonPullService;
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
     * (no reseed needed) — we just don't display or sum them.
     *
     * The value is the single, app-wide reporting floor used by EVERY average
     * and accumulated figure (not just Customer Summary). It is sourced from
     * config('reporting.floor_date') so each per-country deployment can set its
     * own genesis date via the REPORTING_FLOOR_DATE env var. Read it through
     * self::summaryFloorDate() — never re-hardcode the date.
     */
    public static function summaryFloorDate(): string
    {
        return config('reporting.floor_date', '2023-01-01');
    }

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
            'status' => $request->status ?: [Customer::STATUS_ACTIVE, Customer::STATUS_REMOVED],
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

        // --- Unread Site-Note tracking (messenger-style badges) ----------
        // Stamp "viewed" (which resets this page's sidebar badge and slides the
        // Unread-button window) ONLY on a genuine page arrival: a full Inertia
        // visit — not a partial reload such as the note-save refresh
        // (router.reload only:['summaries']) — and not an in-page filter search
        // (searched=1) or the Unread view itself (unread=1).
        $authUser = auth()->user();
        $noteService = app(\App\Services\NoteNotificationService::class);
        $isUnreadView = $request->boolean('unread');
        // "@Me Mentioned" view — sites whose Site Note @-mentions this user.
        $isMentionView = $request->boolean('mentioned');
        $isPartialReload = $request->hasHeader('X-Inertia-Partial-Data');
        if ($authUser && !$request->boolean('searched') && !$isPartialReload) {
            $noteService->markViewed($authUser, \App\Services\NoteNotificationService::PAGE_SUMMARY);
        }
        $summaryUnreadSince = $authUser
            ? $noteService->unreadSince($authUser, \App\Services\NoteNotificationService::PAGE_SUMMARY)
            : null;
        $summaryUnreadCount = $authUser
            ? $noteService->customerUnreadCount($authUser, $summaryUnreadSince)
            : 0;
        // Badge count for the "@Me Mentioned" button (sites mentioning the user).
        $summaryMentionCount = $authUser
            ? $noteService->customerMentionedCount($authUser)
            : 0;
        // Default the FIRST page load to the Unread view when the user has
        // unread Site Notes, so they land on what changed since their last
        // visit. Only on a genuine fresh load: an explicit search (searched=1),
        // an explicit unread toggle (unread present in the request, incl.
        // unread=0 "Show All"), or the mention view all opt out. Sorting stays
        // Note Last Updated desc via the override below.
        if (!$request->has('unread')
            && !$request->boolean('searched')
            && !$isMentionView
            && $summaryUnreadCount > 0) {
            $isUnreadView = true;
        }
        // Unread / mention views always list newest-changed notes first,
        // regardless of the column the user had previously sorted by.
        if ($isUnreadView || $isMentionView) {
            $summarySortKey = 'notes_updated_at';
            $summarySortBy = 'false';
        }

        $request->merge([
            'is_binded_vend' => $request->is_binded_vend ? $request->is_binded_vend : 'all',
            'is_cms' => $request->is_cms ? $request->is_cms : 'all',
            // Customer Status — 5-value dropdown (Potential / New / Active /
            // Pending / Inactive). Default to Active so the page opens on the
            // active book, matching the prior binary is_active=true default.
            // Customer::filterIndex resolves `status` via the status_id column
            // (and still honours legacy `is_active` URLs for backward compat).
            'status' => $request->status ?: [Customer::STATUS_ACTIVE, Customer::STATUS_REMOVED],
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
            $currentMonthStart,
            $request->period_from,
            $request->period_to
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
        //
        // NOTE: scopeFilterIndex ALSO has a Contract Attachment? handler, but
        // it's the period-AGNOSTIC "has/has-not ANY contract ever" check meant
        // for the Customer Index. The Summary page instead uses the period-AWARE
        // applyContractAttachmentFilter() below. If both ran, the "No" branch
        // would AND down to "never had ANY contract", silently dropping sites
        // whose only contract predates the period — those then match neither
        // Yes nor No and the counts stop reconciling (Yes + No < All). So we
        // neutralise filterIndex's copy here and let only the period-aware
        // filter apply. (Restored immediately after for applyContractAttachmentFilter.)
        $contractAttachmentInput = $request->input('contract_attachment');
        $request->merge(['contract_attachment' => 'all']);
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
        $request->merge(['contract_attachment' => $contractAttachmentInput]);

        $customerIdsQuery = $this->filterOperator($customerIdsQuery);
        // Summary-only filter: Placement Contract Type. Accepts an array of
        // codes (F, S, R, U, PS, PS+U, PSORU). 'all' (or absent) = no filter.
        $this->applyContractCommissionTypeFilter($customerIdsQuery, $request);
        // Summary-only filter: Contract Attachment? — keep/drop customers
        // based on whether a contract attachment was uploaded in the
        // selected period window or later.
        $this->applyContractAttachmentFilter($customerIdsQuery, $request, $rangeStart);
        $customerIds = $customerIdsQuery->pluck('customers.id')->unique()->values();

        // Unread view → keep only sites whose Site Note was changed by someone
        // else since the user's previous visit (intersect with the already
        // filtered/operator-scoped set so visibility rules still hold).
        if ($isUnreadView && $authUser) {
            $unreadCustomerIds = $noteService->customerUnreadIds($authUser, $summaryUnreadSince);
            $customerIds = $customerIds->intersect($unreadCustomerIds)->values();
        }

        // Mentioned view → keep only sites whose Site Note @-mentions the user
        // (intersect with the already filtered/operator-scoped set).
        if ($isMentionView && $authUser) {
            $mentionedCustomerIds = $noteService->customerMentionedIds($authUser);
            $customerIds = $customerIds->intersect($mentionedCustomerIds)->values();
        }

        // Sort key/direction whitelist + the full ORDER BY decision tree now
        // live in applySummaryOrdering() (shared with the Excel export), called
        // after the listing query is built below.

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
            'customer:id,name,code,company_remark,virtual_customer_code,virtual_customer_prefix,person_id,operator_id,selling_price_type,is_active,status_id,location_type_id,contract_commission_type,contract_commission_value,contract_commission_value2,contract_ps_term,is_external_subsidize,external_subsidize_amount,begin_date,active_date,removed_date,termination_date,report_email,is_report_email_enabled,location_grading_placement,location_grading_access,location_grading_flexibility,contract_until,contract_auto_renewal,contract_notice_period,notes,notes_updated_at,notes_updated_by,loc_fee_remarks,loc_fee_remarks_updated_at,loc_fee_remarks_updated_by',
            // Customer's primary contact (morphOne) — used to render the
            // Billing Company (`company`, the Edit form's "Bill From" field)
            // and Billing Contact Person (`name`) lines stacked under Address
            // on the Summary page.
            'customer.contact:id,modelable_id,modelable_type,name,company',
            // Customer-level note "last edited by" user — drives the
            // tiny audit line under the textarea on Customer Summary.
            'customer.notesUpdatedBy:id,name',
            // "Remarks for Loc Fees" last-edited-by user — drives the audit
            // line under the textarea in the rightmost Summary column.
            'customer.locFeeRemarksUpdatedBy:id,name',
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

        // Row-level filters (replicated / locked / paid / payment-date).
        // Applied to the listing query AND — via this same closure — to the
        // totals query and the count-card customer set below, so the cards
        // stay in lockstep with the table. (Customer-level filters are already
        // baked into $customerIds, so they flow into all three automatically.)
        // Extracted to applySummaryRowFilters() and SHARED with the Excel
        // export (summaryExportExcel) so the .xlsx can never drift from the
        // on-screen rows again — see that method for the filter semantics.
        $applyRowFilters = fn ($q) => $this->applySummaryRowFilters($q, $request);
        $applyRowFilters($summariesQuery);

        // Ordering — extracted to applySummaryOrdering() and shared with the
        // Excel export (summaryExportExcel) so the download matches the
        // on-screen sort exactly. Default sort is Note Last Updated, newest
        // first; multi-month views cluster a customer's months together.
        $this->applySummaryOrdering($summariesQuery, $summarySortKey, $summarySortBy, $isAggregated);

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

        // Flag, per row, whether the SITE has a pending "upcoming term" (a
        // future contract set via "Set Upcoming Term" on the Edit page, still
        // awaiting its effective date) — drives the amber "Upcoming Term" badge
        // in the Site column on the Summary page.
        $this->attachUpcomingTermFlag($summaries->getCollection());

        // Flag rows whose SITE has been re-activated (removed → active again).
        // Their stored figures are multi-interval log-derived; the resource must
        // NOT live-re-derive them with the single active_date/removed_date pair
        // (which only knows the latest interval), so show the stored value.
        $this->attachReactivationFlag($summaries->getCollection());

        // Resolve each row's machine: machine-split rows carry their own vend_id
        // (show that machine + a "New" badge on the swapped-in row); whole-month
        // rows fall back to the site's current vend in the Vue layer.
        $this->attachMachineSplitInfo($summaries->getCollection());

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
        // sales_excl_gst_cents — Total Sales with each row's operator GST
        // stripped out (sales_cents is stored gross of GST). De-grossed per
        // row via the row's operator gst_vat_rate (correlated subquery — no
        // join, so $applyRowFilters column references stay unambiguous).
        // Feeds the "(excl GST ...)" figure on the Total Sales card AND the
        // denominator for the % badges on the Gross Earning / Location Fees /
        // Vend Earnings cards, which are all excl-GST figures — dividing
        // them by GST-inclusive sales understated every percentage.
        $totalsRow = $totalsQuery
            ->selectRaw('
                COALESCE(SUM(sales_cents), 0) AS sales_cents,
                COALESCE(SUM(sales_cents / (1 + COALESCE((SELECT gst_vat_rate FROM operators WHERE operators.id = customer_period_summaries.operator_id), 0) / 100)), 0) AS sales_excl_gst_cents,
                COALESCE(SUM(gross_earning_cents), 0) AS gross_earning_cents,
                COALESCE(SUM(location_fees_cents), 0) AS location_fees_cents,
                COALESCE(SUM(location_earning_cents), 0) AS location_earning_cents,
                MIN(period_start) AS earliest_period_start,
                MAX(period_end) AS latest_period_end
            ')
            ->first();
        // Location Fees / Vend Earnings totals must reflect the SAME to-date
        // proration the current-month rows now show, otherwise the boxes would
        // read higher than the sum of the visible current-month rows until
        // month end. Past/closed months keep their stored values (summed in
        // SQL); the current in-progress month is re-derived live with the
        // to-date cap, mirroring CustomerPeriodSummaryResource's gating exactly
        // (locked / segment / re-activated current-month rows keep their stored
        // value just as the resource does). Sales / Gross are proration-
        // independent, so they keep the plain full SUM from $totalsRow above.
        $nonCurrentQuery = \App\Models\CustomerPeriodSummary::query()
            ->whereIn('customer_id', $customerIds)
            ->whereBetween('year_month', [$rangeStart->toDateString(), $rangeEnd->toDateString()])
            ->where('is_current_month', false);
        $applyRowFilters($nonCurrentQuery);
        $nonCurrentRow = $nonCurrentQuery
            ->selectRaw('
                COALESCE(SUM(location_fees_cents), 0) AS location_fees_cents,
                COALESCE(SUM(location_earning_cents), 0) AS location_earning_cents
            ')
            ->first();
        $locationFeesTotal = (int) ($nonCurrentRow->location_fees_cents ?? 0);
        $locationEarningTotal = (int) ($nonCurrentRow->location_earning_cents ?? 0);

        $currentMonthQuery = \App\Models\CustomerPeriodSummary::query()
            ->whereIn('customer_id', $customerIds)
            ->whereBetween('year_month', [$rangeStart->toDateString(), $rangeEnd->toDateString()])
            ->where('is_current_month', true);
        $applyRowFilters($currentMonthQuery);
        $currentMonthRows = $currentMonthQuery->get([
            'customer_id', 'year_month', 'period_start', 'period_end', 'locked_at', 'contract_log_id', 'vend_id',
            'sales_cents', 'gross_earning_cents', 'location_fees_cents', 'location_earning_cents',
        ]);

        // True if any current-month flat fee was actually shaved by the to-date
        // cap — drives the "to date" caption on the totals cards so it only
        // appears when the current month is in view AND has a flat fee accruing
        // (never on a pure-PS-only or past-months-only view).
        $totalsHasToDateProration = false;

        if ($currentMonthRows->isNotEmpty()) {
            $currentCustomerIds = $currentMonthRows->pluck('customer_id')->filter()->unique()->values()->all();
            // Current contract + operator GST + active window per site — same
            // batched lookup attachAccumulatedVendingEarning uses.
            $currentContractMap = \Illuminate\Support\Facades\DB::table('customers')
                ->leftJoin('operators', 'operators.id', '=', 'customers.operator_id')
                ->whereIn('customers.id', $currentCustomerIds)
                ->select(
                    'customers.id',
                    'customers.contract_commission_type',
                    'customers.contract_commission_value',
                    'customers.contract_commission_value2',
                    'customers.contract_ps_term',
                    'customers.is_external_subsidize',
                    'customers.external_subsidize_amount',
                    'customers.begin_date',
                    'customers.active_date',
                    'customers.removed_date',
                    'operators.gst_vat_rate'
                )
                ->get()
                ->keyBy('id');
            $reactivatedIds = array_flip(\App\Services\CustomerSummaryAggregator::reactivatedCustomerIds());

            foreach ($currentMonthRows as $cr) {
                $c = $currentContractMap->get($cr->customer_id);
                // Frozen path — locked / segment / re-activated rows show their
                // stored value on screen, so the total uses it too.
                $useStored = $cr->locked_at !== null
                    || $cr->contract_log_id !== null
                    || isset($reactivatedIds[(int) $cr->customer_id])
                    || !$c;
                if ($useStored) {
                    $locationFeesTotal += (int) $cr->location_fees_cents;
                    $locationEarningTotal += (int) $cr->location_earning_cents;
                    continue;
                }

                $gstRatePct = (float) ($c->gst_vat_rate ?? 0);
                $toDateAsOf = $cr->period_end ? \Carbon\Carbon::parse($cr->period_end) : null;
                // Machine-split rows (vend_id set) prorate over the segment's
                // own days — see rowFlatDayRatio. These rows are always the
                // current month here (is_current_month = true).
                $isMachineSplit = $cr->vend_id !== null;
                $flatDayRatio = \App\Services\CustomerSummaryAggregator::rowFlatDayRatio(
                    $c->active_date ?? $c->begin_date,
                    $c->removed_date,
                    \Carbon\Carbon::parse($cr->year_month)->startOfMonth(),
                    $toDateAsOf,
                    $isMachineSplit,
                    true,
                    $cr->period_start,
                    $cr->period_end
                );
                $liveFee = \App\Services\CustomerSummaryAggregator::computeLocationFeeCents(
                    $c->contract_commission_type,
                    $c->contract_commission_value !== null ? (float) $c->contract_commission_value : null,
                    $c->contract_commission_value2 !== null ? (float) $c->contract_commission_value2 : null,
                    $c->contract_ps_term !== null ? (float) $c->contract_ps_term : null,
                    (int) $cr->sales_cents,
                    (int) $cr->gross_earning_cents,
                    $gstRatePct,
                    $flatDayRatio
                );
                $liveExt = ($c->is_external_subsidize && $c->external_subsidize_amount !== null)
                    ? (int) round(((float) $c->external_subsidize_amount) * 100)
                    : 0;
                $locationFeesTotal += $liveFee;
                $locationEarningTotal += (int) $cr->gross_earning_cents - ($liveFee - $liveExt);

                // Did the to-date cap actually reduce this row's flat fee?
                if (!$totalsHasToDateProration) {
                    $fullRatio = \App\Services\CustomerSummaryAggregator::rowFlatDayRatio(
                        $c->active_date ?? $c->begin_date,
                        $c->removed_date,
                        \Carbon\Carbon::parse($cr->year_month)->startOfMonth(),
                        null,
                        $isMachineSplit,
                        true,
                        $cr->period_start,
                        $cr->period_end
                    );
                    if ($fullRatio > $flatDayRatio) {
                        $fullFee = \App\Services\CustomerSummaryAggregator::computeLocationFeeCents(
                            $c->contract_commission_type,
                            $c->contract_commission_value !== null ? (float) $c->contract_commission_value : null,
                            $c->contract_commission_value2 !== null ? (float) $c->contract_commission_value2 : null,
                            $c->contract_ps_term !== null ? (float) $c->contract_ps_term : null,
                            (int) $cr->sales_cents,
                            (int) $cr->gross_earning_cents,
                            $gstRatePct,
                            $fullRatio
                        );
                        if ($fullFee !== $liveFee) {
                            $totalsHasToDateProration = true;
                        }
                    }
                }
            }
        }

        $totals = [
            'sales_cents' => (int) ($totalsRow->sales_cents ?? 0),
            'sales_excl_gst_cents' => (int) round((float) ($totalsRow->sales_excl_gst_cents ?? 0)),
            'gross_earning_cents' => (int) ($totalsRow->gross_earning_cents ?? 0),
            'location_fees_cents' => $locationFeesTotal,
            'location_earning_cents' => $locationEarningTotal,
            // Current-month flat fees in these totals are accrued to-date (not
            // billed for the full month). Lets Summary.vue caption the cards.
            'has_to_date_proration' => $totalsHasToDateProration,
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

        // Displayed "Period range" END — use where the FILTERED data actually
        // ends, not a blind end-of-month. The current in-progress month stores
        // period_end capped at the to-date cutoff (yesterday; see
        // CustomerSummaryAggregator::persistMonth), so MAX(period_end) yields
        // e.g. 2026-06-25 for the "Current" report instead of 2026-06-30.
        // Closed months keep their end-of-month period_end, so multi-month
        // ranges still show the right tail. Capped to the resolved end-of-month
        // as a guard, and falls back to it when the filter matches no rows.
        $resolvedRangeEnd = $rangeEnd->copy()->endOfMonth();
        $displayRangeEnd = $totalsRow && $totalsRow->latest_period_end
            ? \Carbon\Carbon::parse($totalsRow->latest_period_end)->min($resolvedRangeEnd)->toDateString()
            : $resolvedRangeEnd->toDateString();

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
        //    a customer counts as "no attachment" when they have NO contract
        //    attachment at all (period-agnostic), matching the red "No Contract"
        //    badge. Any contract file ever uploaded → not counted here.
        $noContractAttachmentCount = Customer::query()
            ->whereIn('id', $displayedCustomerIds)
            ->whereDoesntHave('contracts')
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

        // Outstanding-owed map for the Payment-History button badge: one grouped
        // SUM over the settlement ledger for the displayed sites. Derived (never
        // stored) so it can't drift. customer_id => signed cents (+ve = we owe).
        // Only sites with ledger rows appear; the Vue defaults the rest to 0.
        $settlementBalances = \App\Models\CustomerSettlement::query()
            ->whereIn('customer_id', $displayedCustomerIds)
            ->selectRaw('customer_id, SUM(amount_cents) AS bal')
            ->groupBy('customer_id')
            ->pluck('bal', 'customer_id');

        // Total outstanding we owe across the displayed sites — sum of the
        // per-site balances above (same filtered scope as the count cards).
        // Drives the new "Total Outstanding" totals card.
        $totals['outstanding_cents'] = (int) $settlementBalances->sum();

        // Paid / Waived credit totals across the SAME displayed sites — feed the
        // "Payment to Loc Fees" section beside the outstanding figure. Both
        // entry types post NEGATIVE amount_cents (credits that reduce what we
        // owe), so negate the SUM to surface a positive paid/waived amount.
        $settlementCreditTotals = \App\Models\CustomerSettlement::query()
            ->whereIn('customer_id', $displayedCustomerIds)
            ->whereIn('entry_type', [
                \App\Models\CustomerSettlement::TYPE_PAYMENT,
                \App\Models\CustomerSettlement::TYPE_WAIVER,
            ])
            ->selectRaw(
                'COALESCE(SUM(CASE WHEN entry_type = ? THEN -amount_cents ELSE 0 END), 0) AS paid_cents,
                 COALESCE(SUM(CASE WHEN entry_type = ? THEN -amount_cents ELSE 0 END), 0) AS waived_cents',
                [
                    \App\Models\CustomerSettlement::TYPE_PAYMENT,
                    \App\Models\CustomerSettlement::TYPE_WAIVER,
                ]
            )
            ->first();
        $totals['paid_cents'] = (int) ($settlementCreditTotals->paid_cents ?? 0);
        $totals['waived_cents'] = (int) ($settlementCreditTotals->waived_cents ?? 0);

        // Period-scoped twins of the three settlement figures above — same
        // displayed sites, but restricted to ledger entries whose year_month
        // falls inside the SHOWN period window (the Period Report filter). Lets
        // the "Payment to Loc Fees" panel show an all-time column beside a
        // "shown period only" column. Payments/waivers are stamped with the
        // year_month of the period they settle (see the Paid action), so this
        // attributes them to the right period(s). amount_cents sign: +ve = a
        // charge we owe, -ve = a payment/waiver credit — so the net SUM is the
        // outstanding attributable to the shown period, while the credit legs
        // are negated to surface positive paid/waived amounts.
        $settlementPeriodTotals = \App\Models\CustomerSettlement::query()
            ->whereIn('customer_id', $displayedCustomerIds)
            ->whereBetween('year_month', [$rangeStart->toDateString(), $rangeEnd->toDateString()])
            ->selectRaw(
                'COALESCE(SUM(amount_cents), 0) AS outstanding_cents,
                 COALESCE(SUM(CASE WHEN entry_type = ? THEN -amount_cents ELSE 0 END), 0) AS paid_cents,
                 COALESCE(SUM(CASE WHEN entry_type = ? THEN -amount_cents ELSE 0 END), 0) AS waived_cents',
                [
                    \App\Models\CustomerSettlement::TYPE_PAYMENT,
                    \App\Models\CustomerSettlement::TYPE_WAIVER,
                ]
            )
            ->first();
        $totals['outstanding_period_cents'] = (int) ($settlementPeriodTotals->outstanding_cents ?? 0);
        $totals['paid_period_cents'] = (int) ($settlementPeriodTotals->paid_cents ?? 0);
        $totals['waived_period_cents'] = (int) ($settlementPeriodTotals->waived_cents ?? 0);

        $className = get_class(new Customer());
        $optionsService = app(\App\Services\OptionsService::class);

        return Inertia::render('Customer/Summary', [
            'summaries' => CustomerPeriodSummaryResource::collection($summaries),
            'totals' => $totals,
            // customer_id => outstanding cents (signed; +ve = we owe the site).
            // Powers the live balance badge on the Payment-History button.
            'settlementBalances' => $settlementBalances,
            // Unread Site-Note count for the on-page "Unread" toggle button.
            'unreadCount' => $summaryUnreadCount,
            // Whether the server resolved this load into the Unread view (drives
            // the button's initial "Show All" state — e.g. the fresh-load default).
            'unreadView' => $isUnreadView,
            // Count of sites that @-mention the user, for the "@Me Mentioned" button.
            'mentionCount' => $summaryMentionCount,
            // Same-operator users for the @-mention dropdown in the note cell.
            'mentionableUsers' => $authUser ? $noteService->mentionableUsers($authUser) : [],
            'periodReport' => $request->period_report,
            'periodReportOptions' => $this->periodReportOptions(),
            // Echo the Custom Range bounds back so the month pickers survive a
            // hard reload / deep link (YYYY-MM strings, or null when unused).
            'periodFrom' => $request->period_from,
            'periodTo' => $request->period_to,
            'rangeStart' => $displayRangeStart,
            'rangeEnd' => $displayRangeEnd,
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
     * Site Performance — aggregate matrix report (Site Management ▸ Performance).
     *
     * One set of figures across the FILTERED set of sites, laid out as a matrix:
     *   columns = "Avg L30d" (rolling last 30 days) + the last 8 completed months
     *   rows    = headline totals, Admin & Finance (profile status / month-end
     *             lock / location-fee payment), Placement Contract Type, and the
     *             per-machine distribution bands for Net Location Fees, Vend
     *             Earning and Accumulated Vend Earning.
     *
     * Mirrors the Sites / Summary & Comm filters (Site ID, Machine ID, Site,
     * Status, Tags, Is From CMS, Operator, Machine Prefix, Location Type,
     * Placement Contract Type). Read-only.
     */
    public function performance(Request $request)
    {
        $customerIds = $this->resolvePerformanceCustomerIds($request);
        $matrix = $this->buildPerformanceMatrix($customerIds);

        $className = get_class(new Customer());
        $optionsService = app(\App\Services\OptionsService::class);

        return Inertia::render('Customer/Performance', [
            'columns' => $matrix['columns'],
            'metrics' => $matrix['metrics'],
            // Filter dropdown sources — same shapes as summary()/index() so the
            // three Site Management pages stay in sync.
            'statuses' => [
                ['id' => 'all', 'name' => 'All'],
                ...collect(Customer::STATUSES_MAPPING)->map(fn ($status, $index) => [
                    'id' => $index,
                    'name' => $status,
                ]),
            ],
            'cmsEndpoint' => env('CMS_URL'),
            'locationTypeOptions' => $optionsService->locationTypes(),
            'operatorOptions' => $optionsService->operators(),
            'tags' => $optionsService->tags($className),
            'vendPrefixOptions' => $optionsService->vendPrefixes(),
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
     * Excel export of the Site Performance matrix. Same filter resolution +
     * aggregation as performance(); flattened to one row per metric with a
     * column per period so the download mirrors the on-screen table and the
     * uploaded "Customer Performance" template.
     */
    public function performanceExportExcel(Request $request)
    {
        $customerIds = $this->resolvePerformanceCustomerIds($request);
        $matrix = $this->buildPerformanceMatrix($customerIds);

        $columns = $matrix['columns'];
        $metrics = $matrix['metrics'];

        // Row layout for the export: [section, label, metricKey, format].
        // format: 'int' | 'money' | 'money_pct' (of sales) | 'pct_total' (blank
        // for non-numeric section headers, which carry metricKey = null).
        $layout = $this->performanceRowLayout();

        $rows = collect($layout)->map(function ($r) use ($columns, $metrics) {
            $out = ['Section' => $r['section'], 'Metric' => $r['label']];
            foreach ($columns as $col) {
                $out[$col['label']] = $r['key'] === null
                    ? ''
                    : $this->formatPerformanceCell($metrics, $r['key'], $col['key'], $r['format']);
            }
            return $out;
        });

        return (new FastExcel($rows))->download(
            'SitePerformance' . now()->format('YmdHis') . '.xlsx'
        );
    }

    /**
     * Resolve the filtered Site (Customer) id set for the Performance page.
     * Reuses the Customer Index filter scope + operator scoping + the
     * Placement Contract Type filter, exactly like summary() so all three
     * Site Management pages agree on "which sites".
     */
    protected function resolvePerformanceCustomerIds(Request $request)
    {
        $request->merge([
            'is_binded_vend' => $request->is_binded_vend ?: 'all',
            'is_cms' => $request->is_cms ?: 'all',
            'status' => $request->status ?: [Customer::STATUS_ACTIVE, Customer::STATUS_REMOVED],
            // Performance never orders the customers table — strip any sort keys
            // so filterIndex() can't try to ORDER BY summary columns here.
            'sortKey' => null,
            'sortBy' => null,
        ]);

        // Mirror summary()/CustomerIndex default operator set on a fresh load
        // (the user's own operator, plus HIMD/LEA/HIESG/UL-ST for HIPL admins).
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

        // Same base query as summary() so the two pages resolve an identical
        // site set (leftJoins kept defensively in case filterIndex references
        // the joined tables; duplicates are collapsed by unique() below).
        $query = Customer::query()
            ->select('customers.id')
            ->leftJoin('addresses', function ($q) {
                $q->on('addresses.modelable_id', '=', 'customers.id')
                    ->where('addresses.modelable_type', '=', 'App\\Models\\Customer')
                    ->where('addresses.type', '=', 2)
                    ->limit(1);
            })
            ->leftJoin('vends', 'vends.customer_id', '=', 'customers.id')
            ->filterIndex($request);
        $query = $this->filterOperator($query);
        $this->applyContractCommissionTypeFilter($query, $request);

        return $query->pluck('customers.id')->unique()->values();
    }

    /**
     * Static row layout for the Site Performance matrix — single source of truth
     * for both the Excel export here and (via the same key names) the Vue page.
     * Section header rows carry key = null.
     *
     * @return array<int,array{section:string,label:string,key:?string,format:string}>
     */
    protected function performanceRowLayout(): array
    {
        $b = ['neg' => 'Negative', 'b0_50' => '$0 to $50', 'b51_100' => '$51 to $100',
            'b101_150' => '$101 to $150', 'b151_200' => '$151 to $200',
            'b201_300' => '$201 to $300', 'b301_500' => '$301 to $500', 'b500p' => 'Above $500'];
        $a = ['neg' => 'Negative', 'a0_500' => '$0 to $500', 'a500_1000' => '$500 to $1000',
            'a1000_5000' => '$1000 to $5000', 'a5000_10000' => '$5000 to $10000',
            'a10k_20k' => '$10k to $20k', 'a20k_30k' => '$20k to $30k', 'a30kp' => '$30k above'];

        $rows = [
            ['Headline', 'Total Customer (Active Status)', 'total_customers', 'int'],
            ['Headline', 'Total Sales (Inc GST), $', 'sales_cents', 'money'],
            ['Headline', 'Total Sales (excl GST), $', 'sales_excl_cents', 'money'],
            ['Headline', 'Total Gross Earning, $', 'gross_cents', 'money_pct'],
            ['Headline', 'Total Location Fees, $', 'location_fees_cents', 'money_pct'],
            ['Headline', 'Total VendEarning, $', 'vend_earning_cents', 'money_pct'],

            ['Admin and Finance', 'Customer Profile Status — Total', 'profile_total', 'int'],
            ['Admin and Finance', 'Potential', 'profile_potential', 'int'],
            ['Admin and Finance', 'New', 'profile_new', 'int'],
            ['Admin and Finance', 'Active', 'profile_active', 'int'],
            ['Admin and Finance', 'Pending', 'profile_pending', 'int'],
            ['Admin and Finance', 'Inactive', 'profile_inactive', 'int'],
            ['Admin and Finance', 'Month-End Lock — Done', 'lock_done', 'int'],
            ['Admin and Finance', 'Month-End Lock — Still open', 'lock_open', 'int'],
            ['Admin and Finance', 'Location Fees payment — Paid, qty', 'paid_qty', 'int'],
            ['Admin and Finance', 'Location Fees payment — Paid, $', 'paid_amt_cents', 'money'],
            ['Admin and Finance', 'Location Fees payment — Unpaid, qty', 'unpaid_qty', 'int'],
            ['Admin and Finance', 'Location Fees payment — Unpaid, $', 'unpaid_amt_cents', 'money'],

            ['Placement Contract Type', 'F: Free Placement', 'ct_F', 'int'],
            ['Placement Contract Type', 'S: Subsidized Plan', 'ct_S', 'int'],
            ['Placement Contract Type', 'R: Fix Rental', 'ct_R', 'int'],
            ['Placement Contract Type', 'U: Utility only', 'ct_U', 'int'],
            ['Placement Contract Type', 'R + U', 'ct_RU', 'int'],
            ['Placement Contract Type', 'PS: Profit Sharing', 'ct_PS', 'int'],
            ['Placement Contract Type', 'PS + U', 'ct_PSU', 'int'],
            ['Placement Contract Type', 'PS or U', 'ct_PSORU', 'int'],
            ['Placement Contract Type', 'External Subsidize?', 'ct_ext_sub', 'int'],
            ['Placement Contract Type', 'Contract available? — Yes', 'contract_avail_yes', 'int'],
            ['Placement Contract Type', 'Contract available? — No', 'contract_avail_no', 'int'],
            ['Placement Contract Type', 'Contract End (no Auto Renewal) — Next 15d', 'contract_end_15', 'int'],
            ['Placement Contract Type', 'Contract End (no Auto Renewal) — Next 30d', 'contract_end_30', 'int'],
            ['Placement Contract Type', 'Contract End (no Auto Renewal) — Next 60d', 'contract_end_60', 'int'],

            ['Net Location Fees', 'Avg per machine', 'nlf_avg_per_machine_cents', 'money'],
        ];
        foreach ($b as $k => $label) {
            $rows[] = ['Net Location Fees', $label, 'nlf_' . $k, 'int'];
        }
        $rows[] = ['Vend Earning', 'Avg per machine', 've_avg_per_machine_cents', 'money'];
        foreach ($b as $k => $label) {
            $rows[] = ['Vend Earning', $label, 've_' . $k, 'int'];
        }
        $rows[] = ['Accumulated Vend Earning', 'Avg per machine', 'ave_avg_per_machine_cents', 'money'];
        foreach ($a as $k => $label) {
            $rows[] = ['Accumulated Vend Earning', $label, 'ave_' . $k, 'int'];
        }

        return array_map(fn ($r) => [
            'section' => $r[0], 'label' => $r[1], 'key' => $r[2], 'format' => $r[3],
        ], $rows);
    }

    /**
     * Format a single matrix cell for the Excel export (plain strings).
     */
    protected function formatPerformanceCell(array $metrics, string $key, string $colKey, string $format): string
    {
        $val = $metrics[$key][$colKey] ?? null;
        if ($val === null) {
            return '';
        }
        $money = fn ($cents) => number_format(((float) $cents) / 100, 2);

        switch ($format) {
            case 'money':
                return $money($val);
            case 'money_pct':
                $sales = $metrics['sales_cents'][$colKey] ?? 0;
                $pct = $sales != 0 ? round(($val / $sales) * 100, 1) : null;
                return $money($val) . ($pct !== null ? ' (' . $pct . '%)' : '');
            case 'int':
            default:
                return (string) (int) $val;
        }
    }

    /**
     * Build the Site Performance matrix (columns + metrics) for a set of Site
     * (Customer) ids. Pure read aggregation — touches no shared writer paths.
     *
     * Columns: "Avg L30d" (live rolling 30-day window) + the last 8 completed
     * calendar months (newest first).
     *
     * Per-month money / lock / paid / contract-type figures are reconstructed
     * from the stored customer_period_summaries snapshots (which already hold a
     * per-period contract snapshot). Profile status and contract end-date are
     * not historized, so those reconstruct against the site's CURRENT status /
     * contract_until relative to each month — see inline notes.
     *
     * @return array{columns:array,metrics:array}
     */
    protected function buildPerformanceMatrix($customerIds): array
    {
        $today = Carbon::today();
        $currentMonthStart = $today->copy()->startOfMonth();
        $floor = Carbon::parse(self::summaryFloorDate())->startOfMonth();

        // --- Columns: Avg L30d + last 8 completed months (newest first) -------
        $months = [];
        for ($i = 1; $i <= 8; $i++) {
            $months[] = $currentMonthStart->copy()->subMonthsNoOverflow($i);
        }
        $columns = [['key' => 'l30d', 'label' => 'Avg L30d', 'sub' => 'Last 30 days']];
        foreach ($months as $m) {
            $columns[] = [
                'key' => $m->format('ym'),       // e.g. "2605"
                'label' => $m->format('ym'),
                'sub' => $m->format('Y-m'),
                'period_start' => $m->toDateString(),
            ];
        }
        $colKeys = array_map(fn ($c) => $c['key'], $columns);

        // Metric scaffold — every leaf is colKey => number (0 default).
        $metricKeys = $this->performanceMetricKeys();
        $M = [];
        foreach ($metricKeys as $k) {
            $M[$k] = array_fill_keys($colKeys, 0);
        }

        if ($customerIds->isEmpty()) {
            return ['columns' => $columns, 'metrics' => $M];
        }

        $operatorGst = DB::table('operators')->pluck('gst_vat_rate', 'id')
            ->map(fn ($v) => (float) $v)->all();

        // Current site state (status / contract / subsidy) keyed by id.
        $customers = Customer::query()
            ->whereIn('id', $customerIds)
            ->get([
                'id', 'operator_id', 'status_id',
                'contract_commission_type', 'contract_commission_value',
                'contract_commission_value2', 'contract_ps_term',
                'is_external_subsidize', 'external_subsidize_amount',
                'contract_until', 'contract_auto_renewal',
            ])
            ->keyBy('id');

        // First contract-attachment upload date per site (for "Contract available?").
        $contractFirstUpload = DB::table('attachments')
            ->where('modelable_type', 'App\\Models\\Customer')
            ->where('type', Customer::FILE_TYPE_CONTRACT)
            ->whereIn('modelable_id', $customerIds)
            ->groupBy('modelable_id')
            ->selectRaw('modelable_id, MIN(created_at) AS first_at')
            ->pluck('first_at', 'modelable_id');

        // All summary rows from the floor → current month (full history needed
        // for the Accumulated Vend Earning column). Scoped to the filtered set.
        $rows = \App\Models\CustomerPeriodSummary::query()
            ->whereIn('customer_id', $customerIds)
            ->where('year_month', '>=', $floor->toDateString())
            ->where('year_month', '<=', $currentMonthStart->toDateString())
            ->get([
                'customer_id', 'operator_id', 'year_month',
                'sales_cents', 'gross_earning_cents', 'location_fees_cents',
                'location_earning_cents', 'external_subsidize_cents', 'vend_count',
                'contract_commission_type', 'locked_at', 'paid_at',
            ]);
        $rowsByMonth = $rows->groupBy(fn ($r) => Carbon::parse($r->year_month)->toDateString());
        $rowsByCust = $rows->groupBy('customer_id');

        // Contract type code → metric-key suffix.
        $ctKey = [
            'F' => 'ct_F', 'S' => 'ct_S', 'R' => 'ct_R', 'U' => 'ct_U',
            'R+U' => 'ct_RU', 'PS' => 'ct_PS', 'PS+U' => 'ct_PSU', 'PSORU' => 'ct_PSORU',
        ];

        // ── Per-month columns ────────────────────────────────────────────────
        foreach ($months as $m) {
            $col = $m->format('ym');
            $mr = $rowsByMonth->get($m->toDateString()) ?? collect();
            $monthEnd = $m->copy()->endOfMonth();
            $custIdsThisMonth = $mr->pluck('customer_id')->unique();

            $M['total_customers'][$col] = $custIdsThisMonth->count();
            $M['sales_cents'][$col] = (int) $mr->sum('sales_cents');
            $M['gross_cents'][$col] = (int) $mr->sum('gross_earning_cents');
            $M['location_fees_cents'][$col] = (int) $mr->sum('location_fees_cents');
            $M['vend_earning_cents'][$col] = (int) $mr->sum('location_earning_cents');
            $M['sales_excl_cents'][$col] = (int) $mr->reduce(function ($carry, $r) use ($operatorGst) {
                $gst = $operatorGst[$r->operator_id] ?? 0;
                return $carry + ((int) $r->sales_cents) / (1 + $gst / 100);
            }, 0);

            // Profile status — current status of the sites active that month.
            foreach ($custIdsThisMonth as $cid) {
                $st = optional($customers->get($cid))->status_id;
                $bucket = $this->profileStatusKey($st);
                if ($bucket) {
                    $M[$bucket][$col]++;
                    $M['profile_total'][$col]++;
                }
            }

            // Month-End Lock / Location-Fee payment — per summary row.
            $M['lock_done'][$col] = $mr->filter(fn ($r) => $r->locked_at !== null)->count();
            $M['lock_open'][$col] = $mr->count() - $M['lock_done'][$col];
            $paidRows = $mr->filter(fn ($r) => $r->paid_at !== null);
            $M['paid_qty'][$col] = $paidRows->count();
            $M['paid_amt_cents'][$col] = (int) $paidRows->sum('location_fees_cents');
            $M['unpaid_qty'][$col] = $mr->count() - $paidRows->count();
            $M['unpaid_amt_cents'][$col] = (int) $mr->sum('location_fees_cents') - $M['paid_amt_cents'][$col];

            // Placement Contract Type — distinct sites per type, from the stored
            // per-period contract snapshot (historized on the summary row).
            foreach ($ctKey as $code => $key) {
                $M[$key][$col] = $mr->filter(fn ($r) => $r->contract_commission_type === $code)
                    ->pluck('customer_id')->unique()->count();
            }
            $M['ct_ext_sub'][$col] = $mr->filter(fn ($r) => (int) $r->external_subsidize_cents !== 0)
                ->pluck('customer_id')->unique()->count();

            // Contract available? / Contract End date — evaluated as of month end.
            $this->fillContractSnapshotColumn(
                $M, $col, $custIdsThisMonth, $customers, $contractFirstUpload, $monthEnd
            );

            // Per-machine distributions — aggregate per site for the month first.
            $perCust = [];
            foreach ($mr->groupBy('customer_id') as $cid => $crows) {
                $perCust[$cid] = [
                    'nlf' => (int) $crows->sum(fn ($r) => (int) $r->location_fees_cents - (int) $r->external_subsidize_cents),
                    've' => (int) $crows->sum('location_earning_cents'),
                    'vc' => (int) $crows->max('vend_count'),
                ];
            }
            $this->fillDistribution($M, $col, 'nlf', $perCust, 'nlf', false);
            $this->fillDistribution($M, $col, 've', $perCust, 've', false);

            // Accumulated Vend Earning — lifetime location_earning through this
            // month / current machine count.
            $perCustAcc = [];
            foreach ($rowsByCust as $cid => $crows) {
                $cum = (int) $crows->filter(fn ($r) => Carbon::parse($r->year_month)->lte($m))
                    ->sum('location_earning_cents');
                $vc = (int) $crows->max('vend_count');
                if ($cum === 0 && $vc === 0) {
                    continue;
                }
                $perCustAcc[$cid] = ['acc' => $cum, 'vc' => $vc];
            }
            $this->fillDistribution($M, $col, 'ave', $perCustAcc, 'acc', true);
        }

        // ── Avg L30d column (live rolling 30 days) ────────────────────────────
        $this->fillL30dColumn(
            $M, 'l30d', $customerIds, $customers, $operatorGst,
            $contractFirstUpload, $rowsByCust, $today, $ctKey
        );

        return ['columns' => $columns, 'metrics' => $M];
    }

    /**
     * Every metric leaf key the matrix produces (used to scaffold zero-filled
     * rows so the Vue page can read any cell without undefined checks).
     */
    protected function performanceMetricKeys(): array
    {
        $base = [
            'total_customers', 'sales_cents', 'sales_excl_cents', 'gross_cents',
            'location_fees_cents', 'vend_earning_cents',
            'profile_total', 'profile_potential', 'profile_new', 'profile_active',
            'profile_pending', 'profile_inactive',
            'lock_done', 'lock_open',
            'paid_qty', 'paid_amt_cents', 'unpaid_qty', 'unpaid_amt_cents',
            'ct_F', 'ct_S', 'ct_R', 'ct_U', 'ct_RU', 'ct_PS', 'ct_PSU', 'ct_PSORU', 'ct_ext_sub',
            'contract_avail_yes', 'contract_avail_no',
            'contract_end_15', 'contract_end_30', 'contract_end_60',
        ];
        $bands = ['neg', 'b0_50', 'b51_100', 'b101_150', 'b151_200', 'b201_300', 'b301_500', 'b500p'];
        $accBands = ['neg', 'a0_500', 'a500_1000', 'a1000_5000', 'a5000_10000', 'a10k_20k', 'a20k_30k', 'a30kp'];
        $base[] = 'nlf_avg_per_machine_cents';
        $base[] = 've_avg_per_machine_cents';
        $base[] = 'ave_avg_per_machine_cents';
        foreach ($bands as $b) {
            $base[] = 'nlf_' . $b;
            $base[] = 've_' . $b;
        }
        foreach ($accBands as $b) {
            $base[] = 'ave_' . $b;
        }
        return $base;
    }

    /** Map a status_id to its profile metric key (null if unknown). */
    protected function profileStatusKey($statusId): ?string
    {
        switch ((int) $statusId) {
            case Customer::STATUS_POTENTIAL: return 'profile_potential';
            case Customer::STATUS_NEW:       return 'profile_new';
            case Customer::STATUS_ACTIVE:    return 'profile_active';
            case Customer::STATUS_PENDING:   return 'profile_pending';
            case Customer::STATUS_INACTIVE:  return 'profile_inactive';
            default: return null;
        }
    }

    /**
     * Fill "Contract available?" + "Contract End (no Auto Renewal)" counts for a
     * column, evaluated as of $ref for the given site id set. Uses the site's
     * CURRENT contract_until (not historized) — see method note in
     * buildPerformanceMatrix.
     */
    protected function fillContractSnapshotColumn(&$M, $col, $custIds, $customers, $contractFirstUpload, Carbon $ref): void
    {
        $r15 = $ref->copy()->addDays(15);
        $r30 = $ref->copy()->addDays(30);
        $r60 = $ref->copy()->addDays(60);

        foreach ($custIds as $cid) {
            $c = $customers->get($cid);
            if (!$c) {
                continue;
            }
            $first = $contractFirstUpload[$cid] ?? null;
            if ($first && Carbon::parse($first)->lte($ref->copy()->endOfDay())) {
                $M['contract_avail_yes'][$col]++;
            } else {
                $M['contract_avail_no'][$col]++;
            }

            if ($c->contract_until && !$c->contract_auto_renewal) {
                $until = Carbon::parse($c->contract_until);
                if ($until->gte($ref) && $until->lte($r60)) {
                    $M['contract_end_60'][$col]++;
                    if ($until->lte($r30)) {
                        $M['contract_end_30'][$col]++;
                    }
                    if ($until->lte($r15)) {
                        $M['contract_end_15'][$col]++;
                    }
                }
            }
        }
    }

    /**
     * Bucket a per-site per-machine $ figure into the distribution bands for a
     * column. $perCust is [cid => [...,'vc'=>machines]] and $valKey selects the
     * cent value; $acc switches to the accumulated band thresholds. Also fills
     * the "Avg per machine" row (total value / total machines).
     */
    protected function fillDistribution(&$M, $col, $prefix, array $perCust, string $valKey, bool $acc): void
    {
        $sumVal = 0;
        $sumMachines = 0;
        foreach ($perCust as $row) {
            $vc = (int) ($row['vc'] ?? 0);
            $val = (int) ($row[$valKey] ?? 0);
            $sumVal += $val;
            $sumMachines += $vc;
            if ($vc <= 0) {
                continue;
            }
            $perMachine = $val / $vc;
            $band = $acc ? $this->accBand($perMachine) : $this->feeBand($perMachine);
            $M[$prefix . '_' . $band][$col]++;
        }
        $M[$prefix . '_avg_per_machine_cents'][$col] = $sumMachines > 0
            ? (int) round($sumVal / $sumMachines)
            : 0;
    }

    /** Net-fee / vend-earning per-machine band (cents in → band key). */
    protected function feeBand(float $cents): string
    {
        if ($cents < 0) return 'neg';
        $d = $cents / 100;
        if ($d <= 50) return 'b0_50';
        if ($d <= 100) return 'b51_100';
        if ($d <= 150) return 'b101_150';
        if ($d <= 200) return 'b151_200';
        if ($d <= 300) return 'b201_300';
        if ($d <= 500) return 'b301_500';
        return 'b500p';
    }

    /** Accumulated vend-earning per-machine band (cents in → band key). */
    protected function accBand(float $cents): string
    {
        if ($cents < 0) return 'neg';
        $d = $cents / 100;
        if ($d <= 500) return 'a0_500';
        if ($d <= 1000) return 'a500_1000';
        if ($d <= 5000) return 'a1000_5000';
        if ($d <= 10000) return 'a5000_10000';
        if ($d <= 20000) return 'a10k_20k';
        if ($d <= 30000) return 'a20k_30k';
        return 'a30kp';
    }

    /**
     * Fill the "Avg L30d" column from a live rolling 30-day window. Money rows
     * aggregate vend_transactions / gp_metrics over [today-29, today] and derive
     * Location Fees / Vend Earning from each site's CURRENT contract. Count /
     * contract rows use the current snapshot over the active-in-window sites;
     * Accumulated Vend Earning is lifetime-to-date. Lock / Paid are not
     * meaningful for an in-progress window and stay 0.
     */
    protected function fillL30dColumn(&$M, $col, $customerIds, $customers, array $operatorGst, $contractFirstUpload, $rowsByCust, Carbon $today, array $ctKey): void
    {
        $windowStart = $today->copy()->subDays(29)->startOfDay();
        $windowEnd = $today->copy()->endOfDay();

        $testingVendIds = \Illuminate\Support\Facades\Cache::remember('testing_vend_ids', 3600, fn () =>
            DB::table('vends')->where('is_testing', true)->pluck('id')->map(fn ($v) => (int) $v)->all()
        );

        // Sales per site over the window — mirrors CustomerSummaryAggregator's
        // success_amount filter exactly.
        $salesByCust = DB::table('vend_transactions')
            ->leftJoin('vend_channel_errors', 'vend_channel_errors.id', '=', 'vend_transactions.vend_channel_error_id')
            ->whereIn('vend_transactions.customer_id', $customerIds)
            ->where(function ($q) use ($windowStart, $windowEnd) {
                $q->whereBetween('vend_transactions.transaction_datetime', [$windowStart, $windowEnd])
                  ->orWhere(function ($or) use ($windowStart, $windowEnd) {
                      $or->whereNull('vend_transactions.transaction_datetime')
                         ->whereBetween('vend_transactions.created_at', [$windowStart, $windowEnd]);
                  });
            })
            ->where(function ($q) {
                $q->whereIn('vend_channel_errors.code', [0, 6])
                  ->orWhereNull('vend_channel_errors.code')
                  ->orWhere('vend_transactions.is_multiple', true);
            })
            ->when(!empty($testingVendIds), fn ($q) => $q->whereNotIn('vend_transactions.vend_id', $testingVendIds))
            ->where('vend_transactions.settlement_status', \App\Models\VendTransaction::SETTLEMENT_SETTLED)
            ->groupBy('vend_transactions.customer_id')
            ->selectRaw('vend_transactions.customer_id AS customer_id, SUM(vend_transactions.amount) AS sales_cents')
            ->pluck('sales_cents', 'customer_id');

        // Gross + machine count per site over the window.
        $gpByCust = DB::table('gp_metrics')
            ->whereIn('customer_id', $customerIds)
            ->whereBetween('txn_date', [$windowStart->toDateString(), $windowEnd->toDateString()])
            ->groupBy('customer_id')
            ->selectRaw('customer_id, COALESCE(SUM(gross_profit_cents),0) AS gross_cents, COUNT(DISTINCT vend_id) AS vc')
            ->get()->keyBy('customer_id');

        // Active-in-window site set = union of sales + gp keys.
        $activeIds = collect($salesByCust->keys())
            ->merge($gpByCust->keys())
            ->unique();

        $r15 = $today->copy()->addDays(15);
        $perCustNlf = [];
        $perCustVe = [];
        foreach ($activeIds as $cid) {
            $c = $customers->get($cid);
            if (!$c) {
                continue;
            }
            $sales = (int) round((float) ($salesByCust[$cid] ?? 0));
            $gross = (int) (optional($gpByCust->get($cid))->gross_cents ?? 0);
            $vc = (int) (optional($gpByCust->get($cid))->vc ?? 0);
            $gst = (float) ($operatorGst[$c->operator_id] ?? 0);

            $locFee = \App\Services\CustomerSummaryAggregator::computeLocationFeeCents(
                $c->contract_commission_type,
                $c->contract_commission_value !== null ? (float) $c->contract_commission_value : null,
                $c->contract_commission_value2 !== null ? (float) $c->contract_commission_value2 : null,
                $c->contract_ps_term !== null ? (float) $c->contract_ps_term : null,
                $sales, $gross, $gst
            );
            $ext = ($c->is_external_subsidize && $c->external_subsidize_amount !== null)
                ? (int) round(((float) $c->external_subsidize_amount) * 100) : 0;
            $netLoc = $locFee - $ext;
            $vendEarn = $gross - $netLoc;

            $M['sales_cents'][$col] += $sales;
            $M['sales_excl_cents'][$col] += (int) round($gst > 0 ? $sales / (1 + $gst / 100) : $sales);
            $M['gross_cents'][$col] += $gross;
            $M['location_fees_cents'][$col] += $locFee;
            $M['vend_earning_cents'][$col] += $vendEarn;

            $perCustNlf[$cid] = ['nlf' => $netLoc, 'vc' => $vc];
            $perCustVe[$cid] = ['ve' => $vendEarn, 'vc' => $vc];

            // Profile + contract-type snapshot (current).
            $bucket = $this->profileStatusKey($c->status_id);
            if ($bucket) {
                $M[$bucket][$col]++;
                $M['profile_total'][$col]++;
            }
            $code = $c->contract_commission_type;
            if (isset($ctKey[$code])) {
                $M[$ctKey[$code]][$col]++;
            }
            if ($ext !== 0) {
                $M['ct_ext_sub'][$col]++;
            }
        }
        $M['total_customers'][$col] = $activeIds->count();

        // Contract available? / Contract End — as of today over the active set.
        $this->fillContractSnapshotColumn($M, $col, $activeIds, $customers, $contractFirstUpload, $today);

        // Distributions over the window.
        $this->fillDistribution($M, $col, 'nlf', $perCustNlf, 'nlf', false);
        $this->fillDistribution($M, $col, 've', $perCustVe, 've', false);

        // Accumulated Vend Earning — lifetime to date / current machine count.
        $perCustAcc = [];
        foreach ($rowsByCust as $cid => $crows) {
            $cum = (int) $crows->sum('location_earning_cents');
            $vc = (int) $crows->max('vend_count');
            if ($cum === 0 && $vc === 0) {
                continue;
            }
            $perCustAcc[$cid] = ['acc' => $cum, 'vc' => $vc];
        }
        $this->fillDistribution($M, $col, 'ave', $perCustAcc, 'acc', true);
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
            // last finished month (2 rows). The "Last N Mth Only" variants are
            // the SAME single-month behaviour, just anchored N completed months
            // back (2 = the month two months ago, 3 = three months ago).
            ['id' => 'last_month_only',   'value' => 'Last Month Only'],
            ['id' => 'last_2_month_only', 'value' => 'Last 2 Mth Only'],
            ['id' => 'last_3_month_only', 'value' => 'Last 3 Mth Only'],
            ['id' => 'last_1_month',    'value' => 'Last month'],
            ['id' => 'last_2_months',   'value' => 'Last 2 months'],
            ['id' => 'last_3_months',   'value' => 'Last 3 months'],
            ['id' => 'last_6_months',   'value' => 'Last 6 months'],
            ['id' => 'last_12_months',  'value' => 'Last 12 months'],
            ['id' => 'last_24_months',  'value' => 'Last 24 months'],
            ['id' => 'last_36_months',  'value' => 'Last 36 months'],
            ['id' => 'all',             'value' => 'All'],
            // "Custom Range" — user-picked month bounds (period_from / period_to,
            // YYYY-MM). Behaves like a bounded "All": one row per stored month
            // per Site within the window, clustered by Site. See
            // resolvePeriodReportRange()'s 'custom' branch for the clamping rules.
            ['id' => 'custom',          'value' => 'Custom Range'],
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
     *                     self::summaryFloorDate() — pre-floor rows in the
     *                     table came from the Excel backfill and are
     *                     incomplete); rangeEnd = current month
     *   custom          → user-supplied $from/$to month bounds (YYYY-MM),
     *                     each snapped to first-of-month. Reversed bounds are
     *                     swapped; the start is clamped forward to the
     *                     reporting floor and the end is clamped back to the
     *                     current month. A missing bound falls back to the
     *                     current month so a half-filled range still resolves.
     *   anything else   → falls back to "current"
     *
     * @return array{0:\Carbon\Carbon,1:\Carbon\Carbon}
     */
    protected function resolvePeriodReportRange(
        ?string $id,
        \Carbon\Carbon $currentMonthStart,
        ?string $from = null,
        ?string $to = null
    ): array {
        // "Custom Range" — bounded window from user-picked months. Tolerant of
        // blank/reversed/out-of-range input so a stray value never errors; it
        // just clamps back into the showable window.
        if ($id === 'custom') {
            $parse = function (?string $v) {
                try {
                    return $v ? \Carbon\Carbon::parse($v)->startOfMonth() : null;
                } catch (\Throwable $e) {
                    return null;
                }
            };
            $start = $parse($from) ?: $currentMonthStart->copy();
            $end   = $parse($to)   ?: $currentMonthStart->copy();

            // Reversed range → swap so start <= end.
            if ($start->gt($end)) {
                [$start, $end] = [$end, $start];
            }

            // Clamp start forward to the reporting floor (pre-floor rows are
            // incomplete Excel backfill) and end back to the current month
            // (no future months exist yet). Mirrors the 'all' clamp.
            $floor = \Carbon\Carbon::parse(self::summaryFloorDate())->startOfMonth();
            if ($start->lt($floor)) {
                $start = $floor->copy();
            }
            if ($end->gt($currentMonthStart)) {
                $end = $currentMonthStart->copy();
            }
            // If clamping inverted the bounds (e.g. both before the floor),
            // collapse to a single valid month.
            if ($start->gt($end)) {
                $start = $end->copy();
            }
            return [$start, $end];
        }

        // "Last N Mth Only" — a single-month window anchored N completed months
        // back (excludes the in-progress current month). "Last Month Only" is
        // N=1 (previous month); the 2/3 variants are exactly the same single
        // month behaviour, just two or three months ago (month start to end).
        $onlyMonthsBack = [
            'last_month_only'   => 1,
            'last_2_month_only' => 2,
            'last_3_month_only' => 3,
        ][$id] ?? null;
        if ($onlyMonthsBack !== null) {
            $anchor = $currentMonthStart->copy()->subMonthsNoOverflow($onlyMonthsBack)->startOfMonth();
            return [$anchor, $anchor->copy()];
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
            // Hard floor — see self::summaryFloorDate(). Pre-floor rows exist in
            // the table from the Excel backfill but are incomplete, so we
            // refuse to show or sum them. max() picks the later of the two,
            // i.e. clamps the start forward to the floor when needed.
            $floor = \Carbon\Carbon::parse(self::summaryFloorDate())->startOfMonth();
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
     * single-month windows ('current', 'last_month_only', 'last_2_month_only',
     * 'last_3_month_only') are NOT aggregated so the user-picked sort column
     * stays primary; every other option (multi-month / all) clusters by
     * customer.
     */
    protected function isAggregatedPeriodReport(?string $id): bool
    {
        return $id !== null && !in_array($id, [
            'current',
            'last_month_only',
            'last_2_month_only',
            'last_3_month_only',
        ], true);
    }

    /**
     * Row-level filters for customer_period_summaries queries — shared by the
     * Summary listing/totals/count-cards AND the Excel export so every surface
     * returns the exact same row set.
     *
     *  - Contract changes (same month): 'true'/Changes only → rows in a
     *    segmented month (count > 1); 'false'/No → rows in a single-row
     *    month (count = 1); 'all'/absent → no filter. Correlated count on
     *    the same table; customer_id is indexed so it stays cheap.
     *  - Period Locked? / Location Fee Paid?: 'true' → only matching rows,
     *    'false' → only the opposite, 'all'/absent → no filter.
     *  - Payment Date range — filters on paid_date (the actual payment
     *    date entered in the Paid popup). Either bound may be empty for
     *    an open-ended range; rows with no paid_date drop out whenever a
     *    bound is set (SQL NULL comparison). Malformed values are
     *    ignored rather than erroring.
     */
    protected function applySummaryRowFilters($q, Request $request)
    {
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
        $parseDate = function ($v) {
            try {
                return $v ? \Carbon\Carbon::parse($v)->toDateString() : null;
            } catch (\Throwable $e) {
                return null;
            }
        };
        if ($from = $parseDate($request->paid_date_from)) {
            $q->where('paid_date', '>=', $from);
        }
        if ($to = $parseDate($request->paid_date_to)) {
            $q->where('paid_date', '<=', $to);
        }

        return $q;
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

        // Period-AGNOSTIC by design: the green "Contract" badge shows whenever
        // the site has ANY contract attachment ever uploaded (latest_contract),
        // regardless of when. The filter must mirror that exactly so Yes/No line
        // up with the badge — a site with an older contract file must NOT leak
        // into the "No" results. So we ignore $rangeStart here and match purely
        // on whether any contract attachment exists, identical to the Customer
        // Index scopeFilterIndex handler.
        $wantsContract = filter_var($raw, FILTER_VALIDATE_BOOLEAN);

        if ($wantsContract) {
            $query->whereHas('contracts');
        } else {
            $query->whereDoesntHave('contracts');
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

        // The current in-progress month is normally not lockable — EXCEPT when
        // the site is being Removed within this month. Management has decided
        // the removal and the prorated payment may need to clear in advance, so
        // the row can be settled early even if the Removed Date is a few days
        // out. See summaryRemovedInPeriod().
        if ($summary->is_current_month && !$this->summaryRemovedInPeriod($summary)) {
            return back()->withErrors(['lock' => 'The current month cannot be locked until it is complete.']);
        }
        if ($summary->locked_at !== null) {
            return back()->with('success', 'This period is already locked.');
        }

        $this->applyLockToSummary($summary, $user->id);

        return back()->with('success', 'Period locked.');
    }

    /**
     * Whether the summary row's site has a hard termination date that has
     * fully elapsed (the whole termination day is strictly before today, app
     * TZ). Used to allow locking a current-month row early once the site has
     * terminated. Mirrors the same rule in CustomerPeriodSummaryResource.
     * Expects the customer relation to be loaded; returns false otherwise.
     */
    protected function summaryRemovedInPeriod(\App\Models\CustomerPeriodSummary $summary): bool
    {
        $c = $summary->customer; // eager-loaded by both lock endpoints
        $removed = $c ? $c->removed_date : null;
        if (!$removed || !$summary->year_month) {
            return false;
        }
        $rd = $removed instanceof \Carbon\Carbon
            ? $removed->copy()
            : \Carbon\Carbon::parse($removed);
        $monthStart = \Carbon\Carbon::parse($summary->year_month)->startOfMonth();
        $monthEnd = $monthStart->copy()->endOfMonth();

        // Removed Date lands inside this row's month → the row may be locked
        // early (even if the date is a few days in the future).
        return $rd->between($monthStart, $monthEnd, true);
    }

    /**
     * Freeze + stamp a summary row as Locked. Shared by the single-row lock
     * endpoint and the batch-lock endpoint so both produce IDENTICAL
     * snapshots. Caller is responsible for permission + eligibility guards
     * (not current month, not already locked).
     *
     * Freezes the LIVE values (current contract applied to this month's
     * stored sales/gross) into the stored columns — same derivation the
     * resource uses for unlocked rows, so "what you see is what locks".
     * Expects customer.operator to be eager-loaded for the GST rate.
     */
    protected function applyLockToSummary(\App\Models\CustomerPeriodSummary $summary, int $userId): void
    {
        $c = $summary->customer;
        if ($c) {
            $gstRatePct = ($c->relationLoaded('operator') && $c->operator && $c->operator->gst_vat_rate !== null)
                ? (float) $c->operator->gst_vat_rate
                : 0.0;

            // Prorate flat fees by the active window for this row's month, so
            // locking a removal (or activation) month freezes the SAME prorated
            // figure shown live on screen. Machine-split rows (vend_id set)
            // prorate over the segment's own days so locking a swap month
            // doesn't freeze a full month's flat fee on each segment.
            $flatDayRatio = $summary->year_month
                ? \App\Services\CustomerSummaryAggregator::rowFlatDayRatio(
                    $c->active_date ?? $c->begin_date,
                    $c->removed_date,
                    \Carbon\Carbon::parse($summary->year_month)->startOfMonth(),
                    null,
                    $summary->vend_id !== null,
                    (bool) $summary->is_current_month,
                    $summary->period_start,
                    $summary->period_end
                )
                : 1.0;

            $locFeeCents = \App\Services\CustomerSummaryAggregator::computeLocationFeeCents(
                $c->contract_commission_type,
                $c->contract_commission_value !== null ? (float) $c->contract_commission_value : null,
                $c->contract_commission_value2 !== null ? (float) $c->contract_commission_value2 : null,
                $c->contract_ps_term !== null ? (float) $c->contract_ps_term : null,
                (int) $summary->sales_cents,
                (int) $summary->gross_earning_cents,
                $gstRatePct,
                $flatDayRatio
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
            // Freeze the Ref Price tier (RP) alongside the contract terms so a
            // later customers.selling_price_type change can't rewrite the badge
            // on this settled month. Resource/export fall back to live for rows
            // locked before this column existed.
            $summary->contract_selling_price_type = $c->selling_price_type;
            $summary->location_fees_cents = $locFeeCents;
            $summary->external_subsidize_cents = $extCents;
            $summary->location_earning_cents = $earningCents;
            $summary->location_earning_rate = $rate;
        }

        $summary->locked_at = now();
        $summary->locked_by = $userId;
        $summary->is_locked = true;
        $summary->save();
    }

    /**
     * Unlock a previously-locked Customer Summary row.
     *
     * Restricted to the top-tier roles (superadmin / admin / supervisor) — a
     * HIGHER access level than locking, per product requirement. Clears locked_at /
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
        if (!$user || !$user->hasAnyRole(['superadmin', 'admin', 'supervisor'])) {
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
        $summary->is_locked = false;
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

        // Actual payment date from the Paid popup — optional; empty defaults
        // to today. paid_at stays the audit timestamp of the click itself.
        // is_waived flags the fee as waived rather than paid; when waived the
        // remarks field is mandatory (the reason for the waiver).
        $validated = $request->validate([
            'paid_date' => ['nullable', 'date'],
            'is_waived' => ['nullable', 'boolean'],
            'waived_remarks' => ['nullable', 'string', 'max:1000', 'required_if:is_waived,true,1'],
            // Optional free-text comment — applies to both Paid and Waived.
            // Saved onto the settlement ledger row (remarks) so it shows in
            // Payment History under the entry.
            'comment' => ['nullable', 'string', 'max:1000'],
            // Actual amount paid / waived, in minor units (cents). Posts to the
            // settlement ledger (Payment History) as a credit. 0 / null → no
            // ledger entry (e.g. marking Paid without recording a figure yet).
            'paid_amount_cents' => ['nullable', 'integer', 'min:0'],
        ], [
            'paid_date.date' => 'Payment date must be a valid date.',
            'waived_remarks.required_if' => 'Please enter a remark explaining why this period is waived.',
        ]);

        $isWaived = !empty($validated['is_waived']);
        $paidDate = !empty($validated['paid_date'])
            ? \Carbon\Carbon::parse($validated['paid_date'])->toDateString()
            : now()->toDateString();

        $amountCents = (int) ($validated['paid_amount_cents'] ?? 0);
        $comment = trim((string) ($validated['comment'] ?? ''));

        // Ledger description shown in Payment History. A waiver keeps its
        // mandatory reason; any free-text comment is appended (for a plain
        // payment the comment is the whole remark). Both can be empty → null.
        $ledgerRemarks = trim(implode("\n", array_filter([
            $isWaived ? trim($validated['waived_remarks']) : null,
            $comment !== '' ? $comment : null,
        ]))) ?: null;

        // Atomic: the paid-state flip AND the ledger credit must commit together
        // (or not at all) so the summary's Paid state and the ledger can never
        // desync. A site can be Unpaid → re-Paid, so clear any prior paid-action
        // credit for THIS period first, then post the fresh one — never
        // double-credits. amount stored NEGATIVE (reduces what we owe).
        \Illuminate\Support\Facades\DB::transaction(function () use ($summary, $user, $isWaived, $paidDate, $amountCents, $validated, $ledgerRemarks) {
            $summary->paid_at = now();
            $summary->paid_date = $paidDate;
            $summary->paid_by = $user->id;
            $summary->is_paid = true;
            $summary->is_waived = $isWaived;
            $summary->waived_remarks = $isWaived ? trim($validated['waived_remarks']) : null;
            $summary->save();

            \App\Models\CustomerSettlement::query()
                ->where('customer_period_summary_id', $summary->id)
                ->where('source', \App\Models\CustomerSettlement::SOURCE_PAID_ACTION)
                ->delete();

            if ($amountCents > 0) {
                $monthLabel = $summary->year_month
                    ? \Carbon\Carbon::parse($summary->year_month)->format('M Y')
                    : '';
                $entry = \App\Models\CustomerSettlement::create([
                    'customer_id'  => $summary->customer_id,
                    'operator_id'  => $summary->operator_id,
                    'entry_date'   => $paidDate,
                    'year_month'   => $summary->year_month,
                    'entry_type'   => $isWaived
                        ? \App\Models\CustomerSettlement::TYPE_WAIVER
                        : \App\Models\CustomerSettlement::TYPE_PAYMENT,
                    'amount_cents' => -$amountCents,   // credit — reduces what we owe.
                    'item'         => ($isWaived ? 'Waived' : 'Payment') . ($monthLabel ? ' — ' . $monthLabel : ''),
                    'remarks'      => $ledgerRemarks,
                    'customer_period_summary_id' => $summary->id,
                    'source'       => \App\Models\CustomerSettlement::SOURCE_PAID_ACTION,
                    'created_by'   => $user->id,
                ]);

                // Audit the money action (who recorded the payment / waiver, when).
                $this->logSettlement(
                    $entry,
                    $isWaived
                        ? \App\Models\CustomerSettlementLog::ACTION_WAIVER
                        : \App\Models\CustomerSettlementLog::ACTION_PAYMENT,
                    ($isWaived ? 'Waived' : 'Payment recorded') . ($monthLabel ? ' for ' . $monthLabel : ''),
                    null,
                    $amountCents,
                    \App\Models\CustomerSettlement::SOURCE_PAID_ACTION
                );
            }
        });

        return back()->with('success', $isWaived ? 'Period marked Waived.' : 'Period marked Paid.');
    }

    /**
     * Mark a Paid row as Unpaid (reverses markPaidCustomerPeriodSummary).
     *
     * Same permission tier as Unlock (superadmin / admin / supervisor) — Unpaid
     * reverses a recorded action, so it sits at the higher access tier. Clears
     * paid_at / paid_by and stamps last_unpaid_at / last_unpaid_by so the
     * tooltip can surface "last unpaid by X at Y" on the next Paid cycle.
     */
    public function markUnpaidCustomerPeriodSummary(Request $request, $id)
    {
        $user = auth()->user();
        if (!$user || !$user->hasAnyRole(['superadmin', 'admin', 'supervisor'])) {
            abort(403, 'You do not have permission to mark summary rows Unpaid.');
        }

        $summary = \App\Models\CustomerPeriodSummary::findOrFail($id);

        if ($summary->paid_at === null) {
            return back()->with('success', 'This period is already Unpaid.');
        }

        // Atomic: clear the paid-state AND reverse the ledger credit together so
        // they can't desync. Audit each reversal BEFORE deleting (keeps actor +
        // amount in history); the FK is nullOnDelete so the log survives.
        \Illuminate\Support\Facades\DB::transaction(function () use ($summary, $user) {
            $summary->paid_at = null;
            $summary->paid_date = null;
            $summary->paid_by = null;
            $summary->is_paid = false;
            // Waiving is part of the Paid state — clear it on Unpaid too.
            $summary->is_waived = false;
            $summary->waived_remarks = null;
            $summary->last_unpaid_at = now();
            $summary->last_unpaid_by = $user->id;
            $summary->save();

            $paidEntries = \App\Models\CustomerSettlement::query()
                ->where('customer_period_summary_id', $summary->id)
                ->where('source', \App\Models\CustomerSettlement::SOURCE_PAID_ACTION)
                ->get();
            foreach ($paidEntries as $entry) {
                $this->logSettlement(
                    $entry,
                    \App\Models\CustomerSettlementLog::ACTION_PAYMENT_REVERSED,
                    'Payment reversed (period marked Unpaid)',
                    (int) $entry->amount_cents,
                    null,
                    \App\Models\CustomerSettlement::SOURCE_PAID_ACTION
                );
            }
            \App\Models\CustomerSettlement::query()
                ->where('customer_period_summary_id', $summary->id)
                ->where('source', \App\Models\CustomerSettlement::SOURCE_PAID_ACTION)
                ->delete();
        });

        return back()->with('success', 'Period marked Unpaid.');
    }

    /**
     * Batch Lock — locks every eligible id in one request (Customer Summary
     * batch bar). Per-row eligibility mirrors the single lock endpoint:
     * not the current month + not already locked. Ineligible ids are
     * SKIPPED (not errored) — the UI only sends eligible rows, so skips can
     * only come from a stale tab racing another user. Same permission tier
     * as single Lock ('admin-access customers'). All-or-nothing write via
     * transaction so a mid-batch failure can't leave a half-locked set.
     */
    public function batchLockCustomerPeriodSummaries(Request $request)
    {
        $user = auth()->user();
        if (!$user || !$user->can('admin-access customers')) {
            abort(403, 'You do not have permission to lock summary rows.');
        }

        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1', 'max:500'],
            'ids.*' => ['integer'],
        ]);

        $summaries = \App\Models\CustomerPeriodSummary::with(['customer.operator'])
            ->whereIn('id', $validated['ids'])
            ->get();

        $locked = 0;
        \Illuminate\Support\Facades\DB::transaction(function () use ($summaries, $user, &$locked) {
            foreach ($summaries as $summary) {
                // Skip already-locked rows, and current-month rows UNLESS the
                // site is being Removed within this month — same exception as
                // the single-row lock endpoint.
                if ($summary->locked_at !== null) {
                    continue;
                }
                if ($summary->is_current_month && !$this->summaryRemovedInPeriod($summary)) {
                    continue;
                }
                $this->applyLockToSummary($summary, $user->id);
                $locked++;
            }
        });

        if ($locked === 0) {
            return back()->withErrors(['batch' => 'No selected rows were eligible to lock.']);
        }

        return back()->with('success', sprintf('Locked %d period(s).', $locked));
    }

    /**
     * Batch Mark Paid — marks every eligible id Paid in one request, with a
     * SHARED payment date from the batch popup (empty → today). Per-row
     * eligibility mirrors the single Paid flow + the UI's paid-tracking
     * cutoff: locked + not already paid + not current month + period 2605
     * (2026-05-01) onward. Ineligible ids are skipped. Same permission tier
     * as single Paid ('admin-access customers').
     */
    public function batchMarkPaidCustomerPeriodSummaries(Request $request)
    {
        $user = auth()->user();
        if (!$user || !$user->can('admin-access customers')) {
            abort(403, 'You do not have permission to mark summary rows Paid.');
        }

        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1', 'max:500'],
            'ids.*' => ['integer'],
            'paid_date' => ['nullable', 'date'],
        ], [
            'paid_date.date' => 'Payment date must be a valid date.',
        ]);

        $paidDate = !empty($validated['paid_date'])
            ? \Carbon\Carbon::parse($validated['paid_date'])->toDateString()
            : now()->toDateString();

        $summaries = \App\Models\CustomerPeriodSummary::query()
            ->whereIn('id', $validated['ids'])
            ->get();

        $paid = 0;
        \Illuminate\Support\Facades\DB::transaction(function () use ($summaries, $user, $paidDate, &$paid) {
            $now = now();
            foreach ($summaries as $summary) {
                if ($summary->locked_at === null || $summary->paid_at !== null) {
                    continue;
                }
                if ($summary->is_current_month) {
                    continue;
                }
                // Paid-tracking cutoff — same gate as Summary.vue's
                // isPaidEligiblePeriod (period 2605 onward only).
                if ($summary->year_month && $summary->year_month->toDateString() < '2026-05-01') {
                    continue;
                }
                $summary->paid_at = $now;
                $summary->paid_date = $paidDate;
                $summary->paid_by = $user->id;
                $summary->is_paid = true;
                $summary->save();

                // Record each period's payment in the settlement ledger using
                // its Net Loc Fee (location_fees − external_subsidize) as the
                // amount — same figure the single Paid modal pre-fills. Clear
                // any prior paid-action credit first (re-pay safety), then post.
                \App\Models\CustomerSettlement::query()
                    ->where('customer_period_summary_id', $summary->id)
                    ->where('source', \App\Models\CustomerSettlement::SOURCE_PAID_ACTION)
                    ->delete();

                $netLocFee = (int) $summary->location_fees_cents - (int) $summary->external_subsidize_cents;
                if ($netLocFee > 0) {
                    $monthLabel = $summary->year_month
                        ? \Carbon\Carbon::parse($summary->year_month)->format('M Y')
                        : '';
                    $entry = \App\Models\CustomerSettlement::create([
                        'customer_id'  => $summary->customer_id,
                        'operator_id'  => $summary->operator_id,
                        'entry_date'   => $paidDate,
                        'year_month'   => $summary->year_month,
                        'entry_type'   => \App\Models\CustomerSettlement::TYPE_PAYMENT,
                        'amount_cents' => -$netLocFee,   // credit — reduces what we owe.
                        'item'         => 'Payment' . ($monthLabel ? ' — ' . $monthLabel : ''),
                        'remarks'      => null,
                        'customer_period_summary_id' => $summary->id,
                        'source'       => \App\Models\CustomerSettlement::SOURCE_PAID_ACTION,
                        'created_by'   => $user->id,
                    ]);
                    $this->logSettlement(
                        $entry,
                        \App\Models\CustomerSettlementLog::ACTION_PAYMENT,
                        'Payment recorded' . ($monthLabel ? ' for ' . $monthLabel : '') . ' (batch)',
                        null,
                        $netLocFee,
                        \App\Models\CustomerSettlement::SOURCE_PAID_ACTION
                    );
                }
                $paid++;
            }
        });

        if ($paid === 0) {
            return back()->withErrors(['batch' => 'No selected rows were eligible to mark Paid.']);
        }

        return back()->with('success', sprintf('Marked %d period(s) Paid.', $paid));
    }

    /**
     * Site Summary ▸ batch bar ▸ "Export CIMB" — commission (location fee)
     * payout file for the selected summary rows, in CIMB BizChannel bulk
     * format. Mirrors the refund batch export: same permission gate as
     * Mark Paid, 422 with a readable message on business-rule rejections,
     * NO side effects (re-export freely; Verify Paid stays a manual step
     * after the bank run succeeds).
     */
    public function exportCommissionBankFile(Request $request)
    {
        $user = auth()->user();
        if (!$user || !$user->can('admin-access customers')) {
            abort(403, 'You do not have permission to export commission payments.');
        }

        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1', 'max:500'],
            'ids.*' => ['integer'],
        ]);

        try {
            $res = app(\App\Services\Commission\CommissionCimbExportService::class)
                ->export($validated['ids']);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        // txt for a single operator; zip (one txt per operator) when the
        // selection spans operators — each file debits its own account.
        return response($res['content'], 200, [
            'Content-Type' => $res['mime'],
            'Content-Disposition' => 'attachment; filename="' . $res['filename'] . '"',
            'X-Filename' => $res['filename'],
            'Access-Control-Expose-Headers' => 'Content-Disposition, X-Filename',
        ]);
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
    /**
     * Queue a Summary recompute for every month affected by a change to a
     * site's activation / removal date, so the STORED figures (and the headline
     * totals that SUM them) pick up the new flat-fee proration on the same save
     * rather than waiting for the nightly run.
     *
     * Affected span = earliest changed lifecycle month → current month
     * (inclusive). Recomputing the whole span — not just the new date's month —
     * is what makes a date MOVE correct in both directions: pulling a removal
     * date earlier must clear the now-orphaned later months, and pushing it
     * later must (re)create the months that now qualify. A future-dated change
     * only touches the current month (its proration is still a full month until
     * that month arrives, which the nightly run will then handle).
     *
     * No-op when neither date changed. Bounded to 60 months back so a wildly
     * back-dated value can't enqueue an unbounded backfill; anything older is
     * left to an explicit `customer-summary:compute` backfill.
     */
    protected function dispatchSummaryRecomputeForLifecycleChange(
        $oldActiveDate,
        $newActiveDate,
        $oldRemovedDate,
        $newRemovedDate
    ): void {
        $toDate = function ($v) {
            if (empty($v)) {
                return null;
            }
            try {
                return \Carbon\Carbon::parse($v)->startOfDay();
            } catch (\Throwable $e) {
                return null;
            }
        };

        $oldA = $toDate($oldActiveDate);
        $newA = $toDate($newActiveDate);
        $oldR = $toDate($oldRemovedDate);
        $newR = $toDate($newRemovedDate);

        $activeChanged = optional($oldA)->toDateString() !== optional($newA)->toDateString();
        $removedChanged = optional($oldR)->toDateString() !== optional($newR)->toDateString();
        if (!$activeChanged && !$removedChanged) {
            return;
        }

        // Earliest month touched by whichever date(s) changed.
        $candidates = [];
        if ($activeChanged) {
            $candidates[] = $oldA;
            $candidates[] = $newA;
        }
        if ($removedChanged) {
            $candidates[] = $oldR;
            $candidates[] = $newR;
        }
        $candidates = array_filter($candidates);

        $currentMonth = \Carbon\Carbon::today()->startOfMonth();
        $lower = $currentMonth->copy();
        foreach ($candidates as $d) {
            $m = $d->copy()->startOfMonth();
            if ($m->lt($lower)) {
                $lower = $m;
            }
        }

        // Bound the backfill so a far back-dated value can't flood the queue.
        $floor = $currentMonth->copy()->subMonths(60);
        if ($lower->lt($floor)) {
            $lower = $floor;
        }

        for ($m = $lower->copy(); $m->lte($currentMonth); $m->addMonth()) {
            \App\Jobs\ProcessCustomerSummaryMonth::dispatch($m->toDateString());
        }
    }

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
        // We also clamp at self::summaryFloorDate() so the running sum starts
        // at the floor date — pre-floor rows (reconstructed from
        // Excel) are incomplete and would inflate / distort the lifetime
        // accumulated earning. This must stay in lockstep with the floor
        // applied in resolvePeriodReportRange() so the on-screen rows and
        // their running totals describe the same window.
        $floor = self::summaryFloorDate();
        // Ordered by period_start (NOT year_month) and keyed by period_start so
        // a month split into segments accumulates CONTINUOUSLY: segment 1 (e.g.
        // 1st–19th) shows the running total through the 19th, segment 2 (20th–
        // end) shows that plus its own earning — instead of both segments
        // sharing the whole-month total. period_start is unique per row
        // (customer_id, period_start), so it sequences segments correctly.
        $monthlyRows = \Illuminate\Support\Facades\DB::table('customer_period_summaries')
            ->select('customer_id', 'year_month', 'period_start', 'period_end', 'is_current_month', 'contract_log_id', 'vend_id', 'location_earning_cents', 'sales_cents', 'gross_earning_cents', 'locked_at')
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
                // Active window — drives the same flat-fee proration the row
                // resource applies, so the Accumulate column reconciles to the
                // sum of the per-row Vend Earnings (incl. a prorated removal
                // month).
                'customers.begin_date',
                'customers.active_date',
                'customers.removed_date',
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
        // "Avg Mthly Sales" — cumulative running average of monthly sales_cents
        // per customer, keyed by period_start so each row shows the average
        // up to AND INCLUDING that month. Sales is contract-independent, so we
        // use the stored sales_cents directly (no live re-derivation needed).
        // Past months' sales_cents are frozen, the current (in-progress) month
        // re-aggregates nightly — so each completed month's average naturally
        // stays put, letting the user see month-over-month improvement.
        $avgRunning = [];      // [cid][period_start_key] => avg cents (int)
        $perCustomerSalesSum = [];   // [cid] => cumulative sales_cents
        $perCustomerMonths = [];     // [cid] => [year_month => true] (distinct month count)
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
                    // Prorate flat fees for this row's month (e.g. a removal
                    // month) the same way the row resource does. For the CURRENT
                    // in-progress month also cap at period_end (the data cutoff)
                    // so the flat fee accrues to-date — keeping the Accumulate
                    // column in lockstep with the to-date per-row Vend Earning.
                    $toDateAsOf = ($r->is_current_month && $r->period_end)
                        ? \Carbon\Carbon::parse($r->period_end)
                        : null;
                    $flatDayRatio = \App\Services\CustomerSummaryAggregator::rowFlatDayRatio(
                        $c->active_date ?? $c->begin_date,
                        $c->removed_date,
                        \Carbon\Carbon::parse($r->year_month)->startOfMonth(),
                        $toDateAsOf,
                        $r->vend_id !== null,
                        (bool) $r->is_current_month,
                        $r->period_start,
                        $r->period_end
                    );
                    $liveLocFeeCents = \App\Services\CustomerSummaryAggregator::computeLocationFeeCents(
                        $c->contract_commission_type,
                        $c->contract_commission_value !== null ? (float) $c->contract_commission_value : null,
                        $c->contract_commission_value2 !== null ? (float) $c->contract_commission_value2 : null,
                        $c->contract_ps_term !== null ? (float) $c->contract_ps_term : null,
                        (int) $r->sales_cents,
                        (int) $r->gross_earning_cents,
                        $gstRatePct,
                        $flatDayRatio
                    );
                    $liveExtCents = ($c->is_external_subsidize && $c->external_subsidize_amount !== null)
                        ? (int) round(((float) $c->external_subsidize_amount) * 100)
                        : 0;
                    $effectiveEarning = (int) $r->gross_earning_cents - ($liveLocFeeCents - $liveExtCents);
                }
            }

            $perCustomerSum[$cid] = ($perCustomerSum[$cid] ?? 0) + $effectiveEarning;
            $running[$cid][$key] = $perCustomerSum[$cid];

            // Running average of monthly sales. Count DISTINCT months (year_month)
            // as the denominator so a month split into contract segments still
            // counts as a single month and doesn't deflate the average.
            $ym = \Carbon\Carbon::parse($r->year_month)->toDateString();
            $perCustomerSalesSum[$cid] = ($perCustomerSalesSum[$cid] ?? 0) + (int) $r->sales_cents;
            $perCustomerMonths[$cid][$ym] = true;
            $monthCount = count($perCustomerMonths[$cid]);
            $avgRunning[$cid][$key] = $monthCount > 0
                ? intdiv($perCustomerSalesSum[$cid], $monthCount)
                : 0;
        }

        foreach ($collection as $row) {
            $rowKey = optional($row->period_start)->toDateString();
            $row->accumulate_vending_earning_cents = (int) ($running[$row->customer_id][$rowKey] ?? 0);
            $row->avg_monthly_sales_cents = isset($avgRunning[$row->customer_id][$rowKey])
                ? (int) $avgRunning[$row->customer_id][$rowKey]
                : null;
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
            // Previous month's running Avg Mthly Sales — same formula used for
            // the visible rows (SUM sales / DISTINCT months, floored at the
            // reporting floor). Lets the single-month "Current" view draw the
            // green/red trend arrow on the Avg cell by diffing this month's
            // running average against last month's.
            ->selectRaw(
                '(SELECT COALESCE(SUM(s3.sales_cents), 0)
                            / NULLIF(COUNT(DISTINCT s3.`year_month`), 0)
                    FROM customer_period_summaries s3
                    WHERE s3.customer_id = customer_period_summaries.customer_id
                      AND s3.`year_month` >= ?
                      AND s3.`year_month` <= customer_period_summaries.`year_month`
                  ) AS avg_monthly_sales_cents',
                [self::summaryFloorDate()]
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
                // Active window — prorates the previous month's flat fee (e.g.
                // when the prior month was the removal month) so the trend
                // arrow compares against the same prorated figure shown there.
                'customers.begin_date',
                'customers.active_date',
                'customers.removed_date',
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
                    // Prorate the previous month's flat fee for its own month.
                    $flatDayRatio = \App\Services\CustomerSummaryAggregator::computeActiveDayRatio(
                        $c->active_date ?? $c->begin_date,
                        $c->removed_date,
                        \Carbon\Carbon::parse($prevKey)->startOfMonth()
                    );
                    $locationFeesCents = \App\Services\CustomerSummaryAggregator::computeLocationFeeCents(
                        $c->contract_commission_type,
                        $c->contract_commission_value !== null ? (float) $c->contract_commission_value : null,
                        $c->contract_commission_value2 !== null ? (float) $c->contract_commission_value2 : null,
                        $c->contract_ps_term !== null ? (float) $c->contract_ps_term : null,
                        (int) $p->sales_cents,
                        (int) $p->gross_earning_cents,
                        $gstRatePct,
                        $flatDayRatio
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
                // Running Avg Mthly Sales through the previous month — drives
                // the trend arrow on the current month's Avg cell.
                'avg_monthly_sales_cents' => $p->avg_monthly_sales_cents !== null
                    ? (int) round($p->avg_monthly_sales_cents)
                    : null,
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
     * Flag, per visible row, whether the SITE has a pending "upcoming term" —
     * a future placement contract queued via "Set Upcoming Term" on the Edit
     * page (customer_scheduled_contracts.status = pending) that hasn't reached
     * its effective date yet. Drives the amber "Upcoming Term" badge in the
     * Site column on the Summary page so users can see, at a glance, which
     * sites have a contract change coming.
     *
     * Site-level, so every row of a customer carries the same flag; the Vue
     * side only renders the badge once per site cluster. One batched query.
     */
    protected function attachUpcomingTermFlag($collection): void
    {
        if ($collection->isEmpty()) {
            return;
        }

        $customerIds = $collection->pluck('customer_id')->filter()->unique()->values()->all();

        $pendingByCustomer = empty($customerIds)
            ? []
            : \App\Models\CustomerScheduledContract::query()
                ->whereIn('customer_id', $customerIds)
                ->where('status', \App\Models\CustomerScheduledContract::STATUS_PENDING)
                ->pluck('effective_date', 'customer_id')
                ->all();

        foreach ($collection as $row) {
            $cid = $row->customer_id;
            $eff = ($cid !== null && isset($pendingByCustomer[$cid])) ? $pendingByCustomer[$cid] : null;
            $row->upcoming_term = $eff
                ? ['effective_date' => \Carbon\Carbon::parse($eff)->toDateString()]
                : null;
        }
    }

    /**
     * Flag rows whose SITE has been re-activated (an Active status event after a
     * Removed one). For those the stored summary figures are multi-interval
     * log-derived (CustomerSummaryAggregator), so the Resource must show the
     * STORED value rather than live-re-deriving from the single
     * active_date/removed_date pair (which only remembers the latest interval).
     * One batched query; sets $row->use_stored_proration. Re-activation is rare,
     * so this is cheap and a no-op for virtually every page.
     */
    protected function attachReactivationFlag($collection): void
    {
        if ($collection->isEmpty()) {
            return;
        }
        $reIds = array_flip(\App\Services\CustomerSummaryAggregator::reactivatedCustomerIds());
        foreach ($collection as $row) {
            $row->use_stored_proration = isset($reIds[(int) $row->customer_id]);
        }
    }

    /**
     * Machine binding history for a SITE (customer) — drives the clock-icon
     * popup on the Site Summary. Lists every machine ever bound/unbound to the
     * site, newest first, with the vend_code, prefix, action and timestamp.
     * Keyed on the customer (site), so a vend reused across sites only shows its
     * stints at THIS site.
     */
    public function vendBindings($id)
    {
        $rows = \Illuminate\Support\Facades\DB::table('customer_vend_bindings as b')
            ->leftJoin('vends as v', 'v.id', '=', 'b.vend_id')
            ->leftJoin('vend_prefixes as vp', 'vp.id', '=', 'b.vend_prefix_id')
            ->leftJoin('users as u', 'u.id', '=', 'b.user_id')
            ->where('b.customer_id', $id)
            ->orderByDesc('b.created_at')
            ->get([
                'v.code as vend_code',
                'vp.name as vend_prefix',
                'b.is_binding',
                'b.created_at',
                'u.name as user_name',
            ]);

        return response()->json([
            'data' => $rows->map(fn ($b) => [
                'vend_code' => $b->vend_code,
                'vend_prefix' => $b->vend_prefix,
                'is_binding' => (bool) $b->is_binding,
                'created_at' => $b->created_at ? \Carbon\Carbon::parse($b->created_at)->format('Y-m-d H:i') : null,
                'user' => $b->user_name,
            ])->all(),
        ]);
    }

    /**
     * Per-row machine + freeze flags for the Site Summary.
     *   - machine_vend: ['id','code'] of the machine attached DURING this row's
     *     period — a split row uses its stored vend_id, every other row resolves
     *     it from the append-only binding history at the row's period_end (so a
     *     past row shows its historical machine, frozen). Null only if the site
     *     never had a bound machine.
     *   - is_new_machine: true on a machine-split row whose machine differs from
     *     the previous row of the SAME (customer, year_month) — the "New" badge
     *     goes on the after-change row, never the first machine.
     *   - is_latest_row: the site's MOST-RECENT row (max period_start) = the
     *     "Current" live row. Only this row stays hyperlinked + editable; older
     *     rows are read-only/frozen.
     * Two batched lookups (bindings + max-period); cheap, runs once per page.
     */
    protected function attachMachineSplitInfo($collection): void
    {
        if ($collection->isEmpty()) {
            return;
        }

        $customerIds = $collection->pluck('customer_id')->filter()->unique()->values()->all();
        if (empty($customerIds)) {
            foreach ($collection as $row) {
                $row->machine_vend = null;
                $row->is_new_machine = false;
                $row->is_latest_row = false;
                $row->is_replaced_machine = false;
            }
            return;
        }

        // Absolute latest period per site → the live "Current" row.
        $maxPeriodByCustomer = \App\Models\CustomerPeriodSummary::query()
            ->whereIn('customer_id', $customerIds)
            ->groupBy('customer_id')
            ->selectRaw('customer_id, MAX(period_start) AS mx')
            ->pluck('mx', 'customer_id')
            ->map(fn ($v) => \Carbon\Carbon::parse($v)->toDateString())
            ->all();

        // Append-only bind history (is_binding=true) → the machine in effect on
        // any date (carry-forward of the last bind on/before that date).
        $bindsByCustomer = [];
        \Illuminate\Support\Facades\DB::table('customer_vend_bindings')
            ->whereIn('customer_id', $customerIds)
            ->where('is_binding', true)
            ->orderBy('customer_id')->orderBy('created_at')
            ->get(['customer_id', 'vend_id', 'created_at'])
            ->each(function ($r) use (&$bindsByCustomer) {
                $bindsByCustomer[(int) $r->customer_id][] = [
                    'date' => \Carbon\Carbon::parse($r->created_at)->startOfDay(),
                    'vend' => (int) $r->vend_id,
                ];
            });
        $vendAt = function (int $cid, $date) use ($bindsByCustomer) {
            $found = null;
            foreach ($bindsByCustomer[$cid] ?? [] as $b) {
                if ($b['date']->lte($date)) {
                    $found = $b['vend'];
                } else {
                    break; // ordered asc → no later bind applies
                }
            }
            return $found;
        };

        // Resolve each row's machine id (stored vend_id, else by period_end).
        $resolved = [];
        foreach ($collection as $row) {
            $row->is_replaced_machine = false; // set true in the ordered walk below
            $cid = (int) $row->customer_id;
            $vid = $row->vend_id !== null ? (int) $row->vend_id : null;
            if ($vid === null && $row->period_end) {
                $pe = $row->period_end instanceof \Carbon\Carbon
                    ? $row->period_end->copy()->startOfDay()
                    : \Carbon\Carbon::parse($row->period_end)->startOfDay();
                $vid = $vendAt($cid, $pe);
            }
            $resolved[(int) $row->id] = $vid;
        }

        $allVids = collect($resolved)->filter()->unique()->values()->all();
        // Load code + prefix + model for each resolved machine so the Summary can
        // show the row's historical machine prefix AND model (not just the code).
        $vendsById = empty($allVids)
            ? collect()
            : \App\Models\Vend::query()
                ->with(['vendPrefix:id,name', 'vendModel:id,name'])
                ->whereIn('id', $allVids)
                ->get(['id', 'code', 'vend_prefix_id', 'vend_model_id'])
                ->keyBy('id');

        // Walk in period order so the FIRST machine of a month isn't "New".
        $prevVendByGroup = [];
        $prevRowByGroup = []; // last machine-bearing row per group → marked replaced on swap
        $ordered = $collection->sortBy([
            ['customer_id', 'asc'],
            ['year_month', 'asc'],
            ['period_start', 'asc'],
        ]);
        foreach ($ordered as $row) {
            $cid = (int) $row->customer_id;
            $vid = $resolved[(int) $row->id] ?? null;
            $mv = ($vid !== null && isset($vendsById[$vid])) ? $vendsById[$vid] : null;
            $row->machine_vend = $mv
                ? [
                    'id' => $vid,
                    'code' => $mv->code,
                    'prefix' => optional($mv->vendPrefix)->name,
                    'model' => optional($mv->vendModel)->name,
                ]
                : null;

            $groupKey = $cid . '|' . (optional($row->year_month)->toDateString() ?? (string) $row->year_month);
            $prev = $prevVendByGroup[$groupKey] ?? null;
            $isSwap = ($vid !== null && $prev !== null && $prev !== $vid);
            $row->is_new_machine = $isSwap;
            // When the machine changed within the month, the PRIOR segment of
            // that month ended because its machine was swapped out — flag it so
            // the Summary can red-mark its period_end (the swap boundary), the
            // same styling a removal date gets. The swapped-IN row keeps
            // is_new_machine; the swapped-OUT (previous) row gets this.
            if ($isSwap && isset($prevRowByGroup[$groupKey])) {
                $prevRowByGroup[$groupKey]->is_replaced_machine = true;
            }
            if ($vid !== null) {
                $prevVendByGroup[$groupKey] = $vid;
                $prevRowByGroup[$groupKey] = $row;
            }

            $ps = optional($row->period_start)->toDateString() ?? (string) $row->period_start;
            $row->is_latest_row = isset($maxPeriodByCustomer[$cid]) && $ps === $maxPeriodByCustomer[$cid];
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
     * Apply the Site Summary ORDER BY to a CustomerPeriodSummary query.
     *
     * Extracted from summary() so the on-screen listing AND the Excel export
     * (summaryExportExcel) order rows IDENTICALLY — the export now honours the
     * same sort the page shows (default: Note Last Updated, newest first)
     * instead of the old hardcoded customer_id/year_month order.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  string|null  $rawSortKey   request sortKey (defaults to notes_updated_at)
     * @param  string|null  $rawSortBy    request sortBy ('true' = asc, else desc)
     * @param  bool  $isAggregated  multi-month view → cluster a customer's months
     */
    protected function applySummaryOrdering($query, $rawSortKey, $rawSortBy, bool $isAggregated): void
    {
        $summarySortKey = $rawSortKey ?: 'notes_updated_at';
        $summarySortBy = $rawSortBy ?: 'false';

        // Sortable columns. machine_id / machine_prefix sort by the customer's
        // latest-bound vend (resolved via scalar subqueries below).
        // accumulate_vending_earning sorts by the lifetime running prefix sum
        // of location_earning_cents per customer up to each row's year_month.
        $sortKey = in_array($summarySortKey, [
            'year_month', 'sales_cents', 'gross_earning_cents',
            'location_fees_cents', 'location_earning_cents', 'location_earning_rate',
            'transaction_count', 'job_count', 'customer_id',
            'machine_id', 'machine_prefix',
            'contract_commission_type', 'contract_commission_value',
            'external_subsidize', 'net_loc_fee',
            'accumulate_vending_earning', 'avg_monthly_sales',
            'period_start', 'period_end',
            'customer_name', 'selling_price_type', 'begin_date', 'site_status',
            'contract_attachment', 'location_type', 'contract_until',
            'notes_updated_at', 'loc_fee_remarks_updated_at',
            'gross_earning_rate', 'outstanding',
        ], true) ? $summarySortKey : 'year_month';
        $sortDirection = filter_var($summarySortBy, FILTER_VALIDATE_BOOLEAN) ? 'asc' : 'desc';

        // notes_updated_at is a CUSTOMER-level value (identical across all of a
        // customer's monthly rows), so we can sort by it page-wide and still
        // keep each customer's months contiguous using customer_id purely as a
        // tie-breaker (applied at the end). For every OTHER sort key in a
        // multi-month view the legacy behaviour stands: cluster by customer_id
        // first so the per-customer grouping stays unambiguous.
        $clusterByCustomerFirst = $isAggregated
            && $sortKey !== 'notes_updated_at'
            && $sortKey !== 'loc_fee_remarks_updated_at'
            && $sortKey !== 'outstanding';
        if ($clusterByCustomerFirst) {
            $query->orderBy('customer_id', 'asc');
        }

        // NULLs always sort to the END of the list, regardless of asc/desc.
        // Pattern: `(expr) IS NULL ASC` evaluates 0 for non-null and 1 for
        // null, so non-nulls land first; the user's direction is applied as
        // the secondary order. Bindings (if any) are repeated because each
        // orderByRaw consumes its own copy at SQL-compile time.
        $nullsLastRaw = function (string $expr, string $dir, array $bindings = []) use ($query) {
            $query->orderByRaw("({$expr}) IS NULL ASC", $bindings);
            $query->orderByRaw("({$expr}) {$dir}", $bindings);
        };

        if ($sortKey === 'machine_id') {
            // Normal sort (NOT nulls-last): a site with no machine bound resolves
            // to NULL, which MySQL orders FIRST on ascending and last on
            // descending. So "sort by smallest" surfaces empty-machine-id sites
            // at the top instead of dumping them behind everything.
            $query->orderByRaw(
                '(SELECT v.code FROM vends v
                   WHERE v.customer_id = customer_period_summaries.customer_id
                   ORDER BY v.begin_date DESC, v.created_at DESC LIMIT 1) ' . $sortDirection
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
            $nullsLastRaw('customer_period_summaries.external_subsidize_cents', $sortDirection);
        } elseif ($sortKey === 'net_loc_fee') {
            $nullsLastRaw(
                'customer_period_summaries.location_fees_cents
                  - customer_period_summaries.external_subsidize_cents',
                $sortDirection
            );
        } elseif ($sortKey === 'accumulate_vending_earning') {
            $nullsLastRaw(
                'SELECT COALESCE(SUM(s2.location_earning_cents), 0)
                  FROM customer_period_summaries s2
                  WHERE s2.customer_id = customer_period_summaries.customer_id
                    AND s2.`year_month` <= customer_period_summaries.`year_month`',
                $sortDirection
            );
        } elseif ($sortKey === 'avg_monthly_sales') {
            // Avg Mthly Sales — running average of sales_cents per customer up
            // to & including each row's year_month. Denominator counts DISTINCT
            // months so segment-split months still count as one (matches the
            // PHP computation in attachAccumulatedVendingEarning). Floored at
            // the reporting floor so it sorts on the same window that's
            // displayed; NULLIF yields NULL for customers with no months → last.
            $nullsLastRaw(
                'SELECT COALESCE(SUM(s2.sales_cents), 0)
                          / NULLIF(COUNT(DISTINCT s2.`year_month`), 0)
                  FROM customer_period_summaries s2
                  WHERE s2.customer_id = customer_period_summaries.customer_id
                    AND s2.`year_month` >= \'' . self::summaryFloorDate() . '\'
                    AND s2.`year_month` <= customer_period_summaries.`year_month`',
                $sortDirection
            );
        } elseif ($sortKey === 'customer_name') {
            $nullsLastRaw(
                'SELECT c.name FROM customers c
                  WHERE c.id = customer_period_summaries.customer_id',
                $sortDirection
            );
        } elseif ($sortKey === 'selling_price_type') {
            $nullsLastRaw(
                'SELECT c.selling_price_type FROM customers c
                  WHERE c.id = customer_period_summaries.customer_id',
                $sortDirection
            );
        } elseif ($sortKey === 'begin_date') {
            $nullsLastRaw(
                'SELECT c.begin_date FROM customers c
                  WHERE c.id = customer_period_summaries.customer_id',
                $sortDirection
            );
        } elseif ($sortKey === 'site_status') {
            // Site Status — customers.status_id (lifecycle: 1=Inactive,
            // 2=Active, 3=Pending, 4=New, 5=Potential). Sorting by the raw
            // id groups rows by status; drives the "Site Status" sort item
            // stacked in the Site column header on Customer/Summary.vue.
            $nullsLastRaw(
                'SELECT c.status_id FROM customers c
                  WHERE c.id = customer_period_summaries.customer_id',
                $sortDirection
            );
        } elseif ($sortKey === 'contract_until') {
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
        } elseif ($sortKey === 'loc_fee_remarks_updated_at') {
            // Remarks for Loc Fees — customers.loc_fee_remarks_updated_at.
            // Customer-level (identical across a site's monthly rows), so it
            // sorts page-wide; sites with no remark resolve to NULL and sort
            // to the end via nullsLastRaw.
            $nullsLastRaw(
                'SELECT c.loc_fee_remarks_updated_at FROM customers c
                  WHERE c.id = customer_period_summaries.customer_id',
                $sortDirection
            );
        } elseif ($sortKey === 'location_type') {
            $nullsLastRaw(
                'SELECT lt.name FROM customers c
                  LEFT JOIN location_types lt ON lt.id = c.location_type_id
                  WHERE c.id = customer_period_summaries.customer_id',
                $sortDirection
            );
        } elseif ($sortKey === 'contract_attachment') {
            $nullsLastRaw(
                'SELECT MAX(a.created_at) FROM attachments a
                  WHERE a.modelable_type = ?
                    AND a.modelable_id = customer_period_summaries.customer_id
                    AND a.type = ?',
                $sortDirection,
                ['App\\Models\\Customer', Customer::FILE_TYPE_CONTRACT]
            );
        } elseif ($sortKey === 'outstanding') {
            // Outstanding ($) — per-site settlement balance: SUM of signed
            // amount_cents across the ledger (+ve = we owe, -ve = credit). A
            // customer-level value (identical across the site's monthly rows),
            // so it sorts page-wide like the other customer-level keys. Sites
            // with no ledger resolve to 0 via COALESCE.
            $nullsLastRaw(
                'SELECT COALESCE(SUM(cs.amount_cents), 0)
                  FROM customer_settlements cs
                  WHERE cs.customer_id = customer_period_summaries.customer_id',
                $sortDirection
            );
        } elseif ($sortKey === 'gross_earning_rate') {
            $nullsLastRaw(
                'customer_period_summaries.gross_earning_cents /
                  NULLIF(customer_period_summaries.sales_cents /
                    (1 + COALESCE((SELECT o.gst_vat_rate FROM operators o
                                   WHERE o.id = customer_period_summaries.operator_id), 0) / 100), 0)',
                $sortDirection
            );
        } else {
            // Whitelisted scalar column on customer_period_summaries.
            $nullsLastRaw($sortKey, $sortDirection);
        }

        // Final tie-breaker: customer_id (when the branch above didn't already
        // cluster by it) + year_month DESC so a customer's most recent month
        // sits at the top of their cluster.
        if (!$clusterByCustomerFirst) {
            $query->orderBy('customer_id', 'asc');
        }
        $query->orderBy('year_month', 'desc');
    }

    /**
     * Export the Customer Management > Summary table to .xlsx.
     *
     * Reuses the same filter + period resolution as summary(); streams rows
     * out via FastExcel with a generator so memory stays flat.
     */
    public function summaryExportExcel(Request $request)
    {
        // Capture the on-screen sort BEFORE the merge below nulls it for the
        // customer-filter scope — the export's row ordering reuses the same
        // sort the page shows (default: Note Last Updated, newest first).
        $summarySortKey = $request->sortKey ?: 'notes_updated_at';
        $summarySortBy = $request->sortBy ?: 'false';

        $request->merge([
            'is_binded_vend' => $request->is_binded_vend ? $request->is_binded_vend : 'all',
            'is_cms' => $request->is_cms ? $request->is_cms : 'all',
            // Customer Status — mirror summary(): default to Active and let
            // filterIndex resolve `status` via status_id. The legacy is_active
            // default is intentionally gone; with the new `status` filter,
            // forcing is_active=true would conflict (e.g. status=Inactive AND
            // is_active=true => 0 rows; status=All => only active exported).
            'status' => $request->status ?: [Customer::STATUS_ACTIVE, Customer::STATUS_REMOVED],
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
            $currentMonthStart,
            $request->period_from,
            $request->period_to
        );

        // Resolve qualifying customer IDs through the Customer Index filters.
        // Neutralise scopeFilterIndex's period-agnostic Contract Attachment?
        // handler so ONLY the period-aware applyContractAttachmentFilter() below
        // applies — mirrors summary(); see the note there for why double-applying
        // the "No" branch under-counts.
        $contractAttachmentInput = $request->input('contract_attachment');
        $request->merge(['contract_attachment' => 'all']);
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
        $request->merge(['contract_attachment' => $contractAttachmentInput]);
        $customerIdsQuery = $this->filterOperator($customerIdsQuery);
        // Mirror summary()'s Placement Contract Type filter so the export
        // honours the same dropdown selection.
        $this->applyContractCommissionTypeFilter($customerIdsQuery, $request);
        // Mirror summary()'s Contract Attachment? filter so the export
        // honours the same dropdown selection.
        $this->applyContractAttachmentFilter($customerIdsQuery, $request, $rangeStart);
        $customerIds = $customerIdsQuery->pluck('customers.id')->unique()->values();

        // Mirror summary()'s Unread / "@Me Mentioned" view restriction so
        // exporting while either toggle is on downloads exactly the rows on
        // screen. Read-only: unlike summary(), the export never markViewed()s,
        // so downloading doesn't slide the unread window or clear badges.
        $authUser = auth()->user();
        if ($authUser && ($request->boolean('unread') || $request->boolean('mentioned'))) {
            $noteService = app(\App\Services\NoteNotificationService::class);
            if ($request->boolean('unread')) {
                $unreadSince = $noteService->unreadSince(
                    $authUser,
                    \App\Services\NoteNotificationService::PAGE_SUMMARY
                );
                $customerIds = $customerIds
                    ->intersect($noteService->customerUnreadIds($authUser, $unreadSince))
                    ->values();
            }
            if ($request->boolean('mentioned')) {
                $customerIds = $customerIds
                    ->intersect($noteService->customerMentionedIds($authUser))
                    ->values();
            }
        }

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
            // status_id drives the new "Site Status" column; notes_updated_at
            // drives "Note Last Updated" — both mirror the on-screen table.
            'customer:id,name,company_remark,code,virtual_customer_code,virtual_customer_prefix,operator_id,status_id,selling_price_type,location_type_id,location_grading_placement,location_grading_access,location_grading_flexibility,begin_date,active_date,removed_date,termination_date,contract_commission_type,contract_commission_value,contract_commission_value2,contract_ps_term,is_external_subsidize,external_subsidize_amount,contract_until,contract_auto_renewal,contract_notice_period,notes,notes_updated_at,loc_fee_remarks,loc_fee_remarks_updated_at,loc_fee_remarks_updated_by',
            'customer.operator:id,code,name,gst_vat_rate',
            // Drives the "Remarks Updated By" column for the Site Summary
            // "Remarks for Loc Fees" field (mirrors the on-screen audit line).
            'customer.locFeeRemarksUpdatedBy:id,name',
            'customer.tagBindings.tag:id,name',
            'customer.deliveryAddress',
            'customer.locationType:id,name',
            'customer.vend:id,customer_id,code,vend_prefix_id',
            'customer.vend.vendPrefix:id,name',
            // Drives the Company + Contact Person + Contact Phone columns (morphOne).
            // `company` = the Edit form's "Bill From" billing-company field.
            'customer.contact:id,modelable_id,modelable_type,name,company,phone_num,alt_phone_num',
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
            ->whereBetween('year_month', [$rangeStart->toDateString(), $rangeEnd->toDateString()]);

        // Row-level filters — the SAME set the on-screen table applies
        // (Contract changes same-month / Period Locked? / Location Fee Paid? /
        // Payment Date range), via the shared applySummaryRowFilters() helper.
        // The Locked?/Paid? dropdowns used to be skipped here ("pre-existing
        // behaviour"), which meant filtering Paid = Yes on screen still
        // exported unpaid rows — user-reported bug, fixed 2026-07-02. The
        // export now always matches the visible row set exactly.
        $this->applySummaryRowFilters($query, $request);

        // Order the export rows with the SAME logic the on-screen table uses,
        // so the .xlsx matches what the user sees (default: Note Last Updated,
        // newest first) instead of the old hardcoded customer_id/year_month.
        $isAggregated = $this->isAggregatedPeriodReport($request->period_report);
        $this->applySummaryOrdering($query, $summarySortKey, $summarySortBy, $isAggregated);

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
            ->select('customer_id', 'year_month', 'period_start', 'period_end', 'is_current_month', 'contract_log_id', 'vend_id', 'location_earning_cents', 'sales_cents', 'gross_earning_cents', 'locked_at')
            ->whereIn('customer_id', $customerIds)
            ->where('year_month', '>=', self::summaryFloorDate())
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
                // Active window — prorates flat fees per month (incl. removal
                // month) so the exported Accumulate column matches the screen.
                'customers.begin_date',
                'customers.active_date',
                'customers.removed_date',
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
        // avgSalesMap[customer_id][period_start] = running "Avg Mthly Sales"
        // (cumulative sales_cents / DISTINCT months) through that month —
        // same formula attachAccumulatedVendingEarning() uses for the
        // on-screen column, reusing this loop's ordered rows for free.
        $avgSalesMap = [];
        $avgSalesSum = [];
        $avgSalesMonths = [];
        foreach ($accumRows as $r) {
            $eff = (int) $r->location_earning_cents;
            if ($r->locked_at === null && $r->contract_log_id === null) {
                $c = $accumContractMap->get($r->customer_id);
                if ($c) {
                    $gstRatePct = (float) ($c->gst_vat_rate ?? 0);
                    // Current in-progress month accrues its flat fee to-date
                    // (cap at period_end) so the exported Accumulate column
                    // matches the on-screen one (attachAccumulatedVendingEarning).
                    $toDateAsOf = ($r->is_current_month && $r->period_end)
                        ? \Carbon\Carbon::parse($r->period_end)
                        : null;
                    $flatDayRatio = \App\Services\CustomerSummaryAggregator::rowFlatDayRatio(
                        $c->active_date ?? $c->begin_date,
                        $c->removed_date,
                        \Carbon\Carbon::parse($r->year_month)->startOfMonth(),
                        $toDateAsOf,
                        $r->vend_id !== null,
                        (bool) $r->is_current_month,
                        $r->period_start,
                        $r->period_end
                    );
                    $fee = \App\Services\CustomerSummaryAggregator::computeLocationFeeCents(
                        $c->contract_commission_type,
                        $c->contract_commission_value !== null ? (float) $c->contract_commission_value : null,
                        $c->contract_commission_value2 !== null ? (float) $c->contract_commission_value2 : null,
                        $c->contract_ps_term !== null ? (float) $c->contract_ps_term : null,
                        (int) $r->sales_cents,
                        (int) $r->gross_earning_cents,
                        $gstRatePct,
                        $flatDayRatio
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

            // Running Avg Mthly Sales — count DISTINCT months (year_month) as
            // the denominator so a month split into contract segments still
            // counts once and doesn't deflate the average (mirrors the
            // on-screen attachAccumulatedVendingEarning()).
            $ymKey = \Carbon\Carbon::parse($r->year_month)->toDateString();
            $avgSalesSum[$r->customer_id] = ($avgSalesSum[$r->customer_id] ?? 0) + (int) $r->sales_cents;
            $avgSalesMonths[$r->customer_id][$ymKey] = true;
            $monthCount = count($avgSalesMonths[$r->customer_id]);
            $avgSalesMap[$r->customer_id][$psKey] = $monthCount > 0
                ? intdiv($avgSalesSum[$r->customer_id], $monthCount)
                : 0;
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

        // Per-site Outstanding($) — the same settlement balance the on-screen
        // Action column's "Owe $X" badge shows: SUM of signed amount_cents
        // across the ledger (+ve = we owe the site, -ve = credit). A
        // customer-level figure (identical across a site's monthly rows).
        // Sites with no ledger rows are absent here and export as 0 below.
        $settlementBalanceMap = \App\Models\CustomerSettlement::query()
            ->whereIn('customer_id', $customerIds)
            ->selectRaw('customer_id, SUM(amount_cents) AS bal')
            ->groupBy('customer_id')
            ->pluck('bal', 'customer_id');

        $rowIndex = 0;

        return (new FastExcel($this->exportWithCursor($query)))->download(
            $this->formatExportFilename('CustomersSummary', 'xlsx'),
            function ($row) use (&$rowIndex, $divisor, $currencySymbol, $contractTypeLabels, $formatLocationFeesRate, $accumulateMap, $avgSalesMap, $invoiceSnapshots, $settlementBalanceMap) {
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
                    // RP follows the same lock rule — unlocked rows reflect the
                    // live customer tier; locked rows keep the frozen snapshot.
                    $row->contract_selling_price_type = $customer->selling_price_type;
                    // Prorate flat fees for this row's month (removal / activation
                    // month) — mirrors CustomerPeriodSummaryResource. For the
                    // CURRENT in-progress month also cap at period_end so the
                    // exported flat fee / Vend Earning match the to-date figures
                    // shown on screen.
                    $exportToDateAsOf = ($row->is_current_month && $row->period_end)
                        ? \Carbon\Carbon::parse($row->period_end)
                        : null;
                    $flatDayRatio = $row->year_month
                        ? \App\Services\CustomerSummaryAggregator::computeActiveDayRatio(
                            $customer->active_date ?? $customer->begin_date,
                            $customer->removed_date,
                            \Carbon\Carbon::parse($row->year_month)->startOfMonth(),
                            $exportToDateAsOf
                        )
                        : 1.0;
                    $liveFee = \App\Services\CustomerSummaryAggregator::computeLocationFeeCents(
                        $customer->contract_commission_type,
                        $customer->contract_commission_value !== null ? (float) $customer->contract_commission_value : null,
                        $customer->contract_commission_value2 !== null ? (float) $customer->contract_commission_value2 : null,
                        $customer->contract_ps_term !== null ? (float) $customer->contract_ps_term : null,
                        (int) $row->sales_cents,
                        (int) $row->gross_earning_cents,
                        $gstRatePct,
                        $flatDayRatio
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
                    // Site Status — mirrors the on-screen badge (customers.
                    // status_id resolved to its label); Removed rows also carry
                    // the removal date, same as the badge's "Removed 251031".
                    'Site Status' => (function () use ($customer) {
                        if (!$customer) return null;
                        $label = Customer::STATUSES_MAPPING[$customer->status_id] ?? null;
                        if ($label === 'Removed' && $customer->removed_date) {
                            $label .= ' ' . \Carbon\Carbon::parse($customer->removed_date)->format('ymd');
                        }
                        return $label;
                    })(),
                    // Company = billing company from the morphOne Contact
                    // (`contact.company`, the Edit form's "Bill From" field),
                    // falling back to the legacy CMS-mirrored `company_remark`
                    // for customers never edited in mark1. Mirrors the
                    // Billing Company line on the on-screen Summary table.
                    'Company' => optional($customer?->contact)->company ?: $customer?->company_remark,
                    'Contact Person' => optional($customer?->contact)->name,
                    'Contact Phone' => optional($customer?->contact)->phone_num,
                    'Contact Alt Phone' => optional($customer?->contact)->alt_phone_num,
                    // Lock-aware RP: frozen snapshot for locked rows (set above
                    // for unlocked rows), falling back to the live customer tier
                    // for rows locked before the snapshot column existed.
                    'Ref Price' => ($rpTier = ($row->contract_selling_price_type ?? $customer?->selling_price_type))
                        ? ('RP' . $rpTier) : null,
                    // New: Begin Date + Contract Attachment — the on-screen
                    // Customer cell stacks these below the name.
                    'Begin Date' => $customer?->begin_date ? \Carbon\Carbon::parse($customer->begin_date)->format('ymd') : null,
                    // Avg Mthly Sales — running average of monthly sales up to
                    // and including this row's period, mirroring the on-screen
                    // Site column figure. Keyed by period_start like Accumulate.
                    'Avg Mthly Sales' => (function () use ($row, $avgSalesMap, $divisor) {
                        $psKey = $row->period_start instanceof \Carbon\Carbon
                            ? $row->period_start->toDateString()
                            : \Carbon\Carbon::parse($row->period_start)->toDateString();
                        $cents = $avgSalesMap[$row->customer_id][$psKey] ?? null;
                        return $cents !== null ? round(((int) $cents) / $divisor, 2) : null;
                    })(),
                    'Contract Attachment' => $hasContract ? 'Yes' : 'No',
                    'Address' => $fullAddress,
                    'Period Report (YYMM)' => $row->year_month ? \Carbon\Carbon::parse($row->year_month)->format('ym') : null,
                    'Machine ID' => $vend?->code,
                    // relationLoaded() returns false in cursor() context (see the
                    // Location Type / audit-column comments below), so read through
                    // optional() directly — customer.vend.vendPrefix IS eager-loaded.
                    'Machine Prefix' => optional($vend?->vendPrefix)->name,
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
                    // Header says "Site Tag" / "Site Note" to match the on-screen
                    // column (Customer→Site display rename) — data unchanged.
                    'Site Tag' => $tagNames,
                    // New: Site Note (stacked with Site Tag on screen).
                    'Site Note' => $customer?->notes,
                    // Note Last Updated — customers.notes_updated_at, the same
                    // timestamp the on-screen column (and default sort) uses.
                    'Note Last Updated' => $fmtAuditDate($customer?->notes_updated_at),
                    // Outstanding($) — the Action column's "Owe $X" settlement
                    // balance (per-site, signed: +ve = we owe the site). Header
                    // states the amount only, no "owe" wording. Sites with no
                    // ledger rows resolve to 0.
                    'Outstanding($)' => round(((int) ($settlementBalanceMap[$row->customer_id] ?? 0)) / $divisor, 2),
                    // Lock / Paid / Unlocked / Unpaid audit — the on-screen
                    // Period Verify & Lock column shows all of these; split into
                    // separate columns here so the CSV/Excel is easy to filter.
                    // Note: relationLoaded() returns false in cursor() context
                    // (see Location Type comment above), so we read through
                    // optional() directly — the relations ARE eager-loaded.
                    // Explicit boolean state columns (mirror locked_at /
                    // paid_at; backfilled by BackfillSummaryLockedPaidFlagsSeeder).
                    'Locked?' => $row->is_locked ? 'Yes' : 'No',
                    'Paid?' => $row->is_paid ? 'Yes' : 'No',
                    'Locked At' => $fmtAuditDate($row->locked_at),
                    'Locked By' => optional($row->lockedBy)->name,
                    'Last Unlocked At' => $fmtAuditDate($row->last_unlocked_at),
                    'Last Unlocked By' => optional($row->lastUnlockedBy)->name,
                    'Paid At' => $fmtAuditDate($row->paid_at),
                    // Actual payment date entered in the Paid popup (defaults
                    // to the click date when left empty). Date-only column.
                    'Payment Date' => $row->paid_date ? \Carbon\Carbon::parse($row->paid_date)->format('ymd') : null,
                    'Paid By' => optional($row->paidBy)->name,
                    // Waived state — flags a waived (vs actually paid) location
                    // fee plus the mandatory reason. Money columns unaffected.
                    'Waived?' => $row->is_waived ? 'Yes' : 'No',
                    'Waived Remarks' => $row->waived_remarks,
                    'Last Unpaid At' => $fmtAuditDate($row->last_unpaid_at),
                    'Last Unpaid By' => optional($row->lastUnpaidBy)->name,
                    // New: Email Performance Report audit (modal action; only
                    // populated on locked rows that were emailed).
                    'Report Emailed At' => $fmtAuditDate($row->report_emailed_at),
                    'Report Emailed By' => optional($row->reportEmailedBy)->name,
                    // Remarks for Loc Fees — the on-screen Action column's
                    // free-text box (one per Site, parked on the customer
                    // record). Split value + audit (At / By) to match this
                    // export's existing audit-column style. relationLoaded()
                    // is unreliable in cursor() context, so read through
                    // optional() — locFeeRemarksUpdatedBy IS eager-loaded.
                    'Remarks for Loc Fees' => $customer?->loc_fee_remarks,
                    'Remarks Updated At' => $fmtAuditDate($customer?->loc_fee_remarks_updated_at),
                    'Remarks Updated By' => optional($customer?->locFeeRemarksUpdatedBy)->name,
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

        // Find the summary row(s) whose period overlaps the requested window.
        // We fetch the FULL rows (not a SUM-only projection) because the
        // report service is lock-aware: a locked / segment row must report the
        // per-period contract SNAPSHOT frozen at lock time (commission type /
        // value(s) / PS Term), not the customer's current live contract — so a
        // PS Term later tuned 70%→50% can't rewrite an already-locked month.
        //
        // The representative row (latest month, last segment) carries the
        // snapshot + lock state; its sales_cents is overwritten with the SUM
        // across all matched months so PS math still covers the full window.
        $summaryRows = \App\Models\CustomerPeriodSummary::query()
            ->where('customer_id', $customer->id)
            ->whereBetween('year_month', [
                $periodStart->copy()->startOfMonth()->toDateString(),
                $periodEnd->copy()->startOfMonth()->toDateString(),
            ])
            ->orderBy('year_month')
            ->orderBy('segment_index')
            ->get();

        $summaryRow = $summaryRows->last();
        if ($summaryRow) {
            $summaryRow->sales_cents = (int) $summaryRows->sum('sales_cents');
        }

        $service = new PerformanceReportContentService();
        $content = $service->generate($customer, $periodStart, $periodEnd, $summaryRow);

        return response()->json($content);
    }

    /**
     * Batch sibling of getPerformanceReportContent().
     *
     * Backs the "Export Batch Report Content" button on Customer Summary: the
     * user ticks several rows (machines, possibly across months) and we return
     * the structured Report Content for EACH (customer, period) in one round
     * trip so the frontend can stitch them into a single client-facing email
     * body. Each entry is computed with the exact same lock-aware, GST-aware
     * logic as the single-row endpoint above — we just loop, caching customers
     * so a multi-machine client isn't reloaded per row.
     *
     * Request body:
     *   rows: [{ customer_id, period_start, period_end }, ...]
     *
     * Response:
     *   { rows: [{ customer_id, period_start, period_end, content }, ...] }
     * (preserves request order; rows whose customer no longer exists are
     * skipped rather than erroring the whole batch.)
     */
    public function batchPerformanceReportContent(Request $request)
    {
        $validated = $request->validate([
            'rows'                => 'required|array|min:1|max:500',
            'rows.*.customer_id'  => 'required|integer',
            'rows.*.period_start' => 'required|date',
            'rows.*.period_end'   => 'required|date',
        ]);

        $service = new PerformanceReportContentService();
        $customerCache = [];
        $out = [];

        foreach ($validated['rows'] as $r) {
            $customerId = (int) $r['customer_id'];

            if (!array_key_exists($customerId, $customerCache)) {
                $customerCache[$customerId] = Customer::with('operator:id,gst_vat_rate')->find($customerId);
            }
            $customer = $customerCache[$customerId];
            if (!$customer) {
                continue;
            }

            $periodStart = \Carbon\Carbon::parse($r['period_start']);
            $periodEnd   = \Carbon\Carbon::parse($r['period_end']);
            if ($periodEnd->lt($periodStart)) {
                continue;
            }

            // Same overlap lookup + sales-sum collapse as the single-row path so
            // PS-family math covers the full window and locked/segment rows keep
            // their frozen snapshot terms.
            $summaryRows = \App\Models\CustomerPeriodSummary::query()
                ->where('customer_id', $customer->id)
                ->whereBetween('year_month', [
                    $periodStart->copy()->startOfMonth()->toDateString(),
                    $periodEnd->copy()->startOfMonth()->toDateString(),
                ])
                ->orderBy('year_month')
                ->orderBy('segment_index')
                ->get();

            $summaryRow = $summaryRows->last();
            if ($summaryRow) {
                $summaryRow->sales_cents = (int) $summaryRows->sum('sales_cents');
            }

            $out[] = [
                'customer_id'  => $customer->id,
                'period_start' => $periodStart->toDateString(),
                'period_end'   => $periodEnd->toDateString(),
                'content'      => $service->generate($customer, $periodStart, $periodEnd, $summaryRow),
            ];
        }

        return response()->json(['rows' => $out]);
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
            // Pull-From-CMS picker is retired (CMS deprecation). Prop kept as an
            // empty list so Create.vue's Object.values() mapping stays safe.
            'cmsCustomerOptions' => [],
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
     * "Pull from CMS" (Create/Edit ▸ beside the CMS Linking ID field).
     *
     * On-demand, ONE-WAY CMS → form fetch: returns the CMS person mapped to
     * the Customer form shape as JSON. The Vue page fills the form fields;
     * NOTHING is persisted until the user presses Save, and nothing is ever
     * pushed back to CMS from this path (CMS stays the source of truth).
     * Field map + OneMap-by-postcode address rules live in
     * CmsPersonPullService.
     */
    public function pullCmsPerson(Request $request, CmsPersonPullService $cmsPersonPullService)
    {
        $request->validate([
            'person_id' => ['required', 'integer', 'min:1'],
        ]);

        return response()->json($cmsPersonPullService->pull((int) $request->person_id));
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
     * Persist the customer-level "Remarks for Loc Fees" from the Customer
     * Summary page (rightmost column). Same shape as updateNotes() — the
     * remark + audit stamp live directly on the customer record so the value
     * survives any period/filter combination on the Summary page. This is a
     * standalone field, separate from the general Site Note; no unread or
     * mention tracking is attached.
     */
    public function updateLocFeeRemarks(Request $request, $customerId)
    {
        $customer = Customer::findOrFail($customerId);
        $customer->loc_fee_remarks = $request->loc_fee_remarks;
        $customer->loc_fee_remarks_updated_by = auth()->user()->id;
        $customer->loc_fee_remarks_updated_at = Carbon::now();
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

    /**
     * Settlement ledger ("Payment History") for one site — drives the
     * Payment-History popup on the Site Summary. Returns the full chronological
     * ledger with a DERIVED running balance (cumulative SUM of signed
     * amount_cents), plus the current outstanding total. Read-only.
     *
     * Shape mirrors vendBindings(): the Vue caller reads res.data.data.
     */
    public function getSettlements($id)
    {
        $ledger = $this->buildSettlementLedger($id);

        return response()->json([
            'data'              => $ledger['rows'],
            'outstanding_cents' => $ledger['outstanding_cents'],
            'since_date'        => $ledger['since_date'],
            'site_label'        => $ledger['site_label'],
            'logs'              => $ledger['logs'],
        ]);
    }

    /**
     * Build the settlement ledger for one site: chronological rows with a
     * DERIVED running balance (cumulative SUM of signed amount_cents), plus
     * meta (site label, outstanding, since-date). Shared by the JSON popup
     * endpoint and the PDF / Excel exports so all three stay identical.
     */
    private function buildSettlementLedger($id): array
    {
        $customer = Customer::query()
            ->select(['id', 'name', 'company_remark'])
            ->findOrFail($id);

        // Site ID shown across the Summary page is id + 20000 (see refIdFor()).
        $refId = $customer->id + 20000;

        $rows = \App\Models\CustomerSettlement::query()
            ->where('customer_id', $customer->id)
            ->with(['createdBy:id,name', 'updatedBy:id,name'])
            ->chronological()
            ->get();

        $balance = 0;
        $data = $rows->map(function ($r) use (&$balance) {
            $amount = (int) $r->amount_cents;
            $balance += $amount;

            // Treat as "edited" only when updated_by is set AND the update is
            // distinct from creation (auto reference_no backfill touches updated_at).
            $wasEdited = $r->updated_by !== null;

            return [
                'id'           => $r->id,
                'reference_no' => $r->reference_no,
                'entry_date'   => optional($r->entry_date)->toDateString(),
                'year_month'   => optional($r->year_month)->toDateString(),
                'entry_type'   => $r->entry_type,
                'item'         => $r->item,
                'remarks'      => $r->remarks,
                'amount_cents' => $amount,
                // SOA-style split: DR = charge we owe, CR = payment we make.
                'debit_cents'  => $amount > 0 ? $amount : 0,
                'credit_cents' => $amount < 0 ? -$amount : 0,
                'balance_cents' => $balance,
                'source'       => $r->source,
                'created_by'   => $r->createdBy?->name,
                'created_at'   => optional($r->created_at)->toIso8601String(),
                'edited_by'    => $wasEdited ? $r->updatedBy?->name : null,
                'edited_at'    => $wasEdited ? optional($r->updated_at)->toIso8601String() : null,
            ];
        })->values();

        // Change-history audit trail (newest first) for the "Change history"
        // panel. The audit log is a SECONDARY feature — guard it so a missing
        // table (migration not yet run) or any log-query issue can never blank
        // out the core ledger. Falls back to an empty history.
        $logs = collect();
        if (\Illuminate\Support\Facades\Schema::hasTable('customer_settlement_logs')) {
            $logs = \App\Models\CustomerSettlementLog::query()
                ->where('customer_id', $customer->id)
                ->with('changedBy:id,name')
                ->orderByDesc('id')
                ->limit(100)
                ->get()
                ->map(fn ($l) => [
                    'action'           => $l->action,
                    'reference_no'     => $l->reference_no,
                    'entry_type'       => $l->entry_type,
                    'note'             => $l->note,
                    'old_amount_cents' => $l->old_amount_cents,
                    'new_amount_cents' => $l->new_amount_cents,
                    'by'               => $l->changedBy?->name,
                    'source'           => $l->source,
                    'at'               => optional($l->created_at)->toIso8601String(),
                ])
                ->values();
        }

        $sinceRow = $rows->first();

        return [
            'customer'          => $customer,
            'ref_id'            => $refId,
            'site_label'        => $refId . ' · ' . ($customer->name ?: $customer->company_remark ?: ('#' . $customer->id)),
            'rows'              => $data,
            'outstanding_cents' => $balance,
            'since_date'        => $sinceRow ? optional($sinceRow->entry_date)->toDateString() : null,
            'logs'              => $logs,
        ];
    }

    /**
     * Add a manual entry straight from the Payment-History popup — a Paid or
     * Waived credit, or a free-form Adjustment.
     *
     * STANDALONE ledger row: it changes what we owe the site owner but does NOT
     * touch any period summary's Paid/Waived flags — that remains the job of the
     * per-row Paid button. Provenance is SOURCE_MANUAL so it can later be
     * edited/deleted here (unlike the auto-posted paid-action credits).
     * Balances re-derive automatically.
     *
     * Sign convention:
     *   payment / waiver        → always a CREDIT (stored NEGATIVE).
     *   adjustment + direction  → credit (NEGATIVE, reduces what we owe) or
     *                             debit  (POSITIVE, increases what we owe).
     *
     * Admin-only (same gate as Lock/Paid/edit). Required fields: entry_type
     * (payment|waiver|adjustment), entry_date, description (item), amount.
     * For an adjustment, direction (debit|credit) is required.
     */
    public function storeSettlement(Request $request, $id)
    {
        $user = auth()->user();
        if (!$user || !$user->can('admin-access customers')) {
            abort(403, 'You do not have permission to add settlement entries.');
        }

        $customer = Customer::query()->select(['id', 'operator_id'])->findOrFail($id);

        $validated = $request->validate([
            'entry_type'   => ['required', 'string', 'in:payment,waiver,adjustment'],
            'entry_date'   => ['required', 'date'],
            'item'         => ['required', 'string', 'max:255'],
            // Magnitude in minor units (cents); always > 0. Sign applied below.
            'amount_cents' => ['required', 'integer', 'min:1'],
            // Only meaningful for an adjustment: which way it moves the balance.
            'direction'    => ['nullable', 'string', 'in:debit,credit', 'required_if:entry_type,adjustment'],
            'remarks'      => ['nullable', 'string', 'max:1000'],
        ], [
            'item.required'        => 'Please enter a description for this entry.',
            'amount_cents.min'     => 'Amount must be greater than zero.',
            'direction.required_if' => 'Please choose whether this adjustment is a charge or a credit.',
        ]);

        $type       = $validated['entry_type'];
        $magnitude  = abs((int) $validated['amount_cents']);
        $isAdjustment = $type === \App\Models\CustomerSettlement::TYPE_ADJUSTMENT;

        // payment/waiver are credits. An adjustment is a debit (+) only when
        // explicitly marked so; otherwise it's a credit (−).
        $signed = ($isAdjustment && ($validated['direction'] ?? 'credit') === 'debit')
            ? $magnitude
            : -$magnitude;

        $entry = \App\Models\CustomerSettlement::create([
            'customer_id'  => $customer->id,
            'operator_id'  => $customer->operator_id,
            'entry_date'   => \Carbon\Carbon::parse($validated['entry_date'])->toDateString(),
            'year_month'   => null,   // ad-hoc — not tied to a specific accounting month.
            'entry_type'   => $type,
            'amount_cents' => $signed,
            'item'         => trim($validated['item']),
            'remarks'      => $validated['remarks'] ?? null,
            'customer_period_summary_id' => null,
            'source'       => \App\Models\CustomerSettlement::SOURCE_MANUAL,
            'created_by'   => $user->id,
        ]);

        // Map entry type → audit action. Adjustment has no dedicated action, so
        // it logs as a generic "created".
        $action = [
            \App\Models\CustomerSettlement::TYPE_PAYMENT => \App\Models\CustomerSettlementLog::ACTION_PAYMENT,
            \App\Models\CustomerSettlement::TYPE_WAIVER  => \App\Models\CustomerSettlementLog::ACTION_WAIVER,
        ][$type] ?? \App\Models\CustomerSettlementLog::ACTION_CREATED;

        $this->logSettlement(
            $entry,
            $action,
            $this->settlementTypeLabel($type) . ' added from Payment History',
            null,
            $entry->amount_cents,
            'user'
        );

        $messages = [
            \App\Models\CustomerSettlement::TYPE_PAYMENT    => 'Paid entry added.',
            \App\Models\CustomerSettlement::TYPE_WAIVER     => 'Waived entry added.',
            \App\Models\CustomerSettlement::TYPE_ADJUSTMENT => 'Adjustment added.',
        ];

        return response()->json([
            'message' => $messages[$type] ?? 'Entry added.',
        ]);
    }

    /**
     * Whether a settlement row may be edited/deleted from the popup. Manually
     * owned types (opening_balance, adjustment) always qualify; payment/waiver
     * qualify ONLY when hand-entered (source=manual) so the auto-posted credits
     * from the per-row Paid button stay locked to their period's Paid state.
     */
    private function isManuallyEditableSettlement(\App\Models\CustomerSettlement $settlement): bool
    {
        $alwaysEditable = [
            \App\Models\CustomerSettlement::TYPE_OPENING_BALANCE,
            \App\Models\CustomerSettlement::TYPE_ADJUSTMENT,
        ];
        $manualCreditTypes = [
            \App\Models\CustomerSettlement::TYPE_PAYMENT,
            \App\Models\CustomerSettlement::TYPE_WAIVER,
        ];

        if (in_array($settlement->entry_type, $alwaysEditable, true)) {
            return true;
        }

        return in_array($settlement->entry_type, $manualCreditTypes, true)
            && $settlement->source === \App\Models\CustomerSettlement::SOURCE_MANUAL;
    }

    /**
     * Edit a settlement ledger entry. Restricted to admins and to entry types
     * that are MANUALLY owned — opening_balance (a seeded migration figure),
     * adjustment, and manual payment/waiver added from the popup. Auto-posted
     * rows (location_fee from the monthly cron, payment/waiver from the per-row
     * Paid action) are NOT editable here so the ledger stays reconciled with its
     * source of truth; correct those upstream or via an adjustment entry
     * instead. Balances re-derive automatically.
     */
    public function updateSettlement(Request $request, $id)
    {
        $user = auth()->user();
        if (!$user || !$user->can('admin-access customers')) {
            abort(403, 'You do not have permission to edit settlement entries.');
        }

        $settlement = \App\Models\CustomerSettlement::findOrFail($id);

        if (!$this->isManuallyEditableSettlement($settlement)) {
            return response()->json([
                'message' => 'Only opening balance, adjustment, and manually-added paid/waived entries can be edited. For an auto-posted location fee or the per-row Paid action, add an adjustment instead.',
            ], 422);
        }

        $validated = $request->validate([
            'amount_cents' => ['required', 'integer'],
            'remarks'      => ['nullable', 'string', 'max:1000'],
        ]);

        $oldAmount = (int) $settlement->amount_cents;
        // Preserve the original sign so a credit (payment/waiver/-ve adjustment)
        // stays a credit and a debit stays a debit; only the magnitude is editable.
        $magnitude = abs((int) $validated['amount_cents']);
        $newAmount = $oldAmount < 0 ? -$magnitude : $magnitude;

        $settlement->amount_cents = $newAmount;
        $settlement->remarks = $validated['remarks'] ?? null;
        $settlement->updated_by = $user->id;
        $settlement->save();

        // Audit: record the edit (who, when, before → after).
        $this->logSettlement(
            $settlement,
            \App\Models\CustomerSettlementLog::ACTION_EDITED,
            $this->settlementTypeLabel($settlement->entry_type) . ' edited',
            $oldAmount,
            $newAmount,
            'user'
        );

        return response()->json(['message' => 'Settlement updated.']);
    }

    /**
     * Delete a manually-added settlement entry from the popup. Admin-only and
     * restricted to hand-entered rows (opening_balance, adjustment, or a
     * source=manual payment/waiver) so auto-posted location fees and per-row
     * Paid credits can't be removed here. Logged before deletion; balances
     * re-derive automatically.
     */
    public function deleteSettlement($id)
    {
        $user = auth()->user();
        if (!$user || !$user->can('admin-access customers')) {
            abort(403, 'You do not have permission to delete settlement entries.');
        }

        $settlement = \App\Models\CustomerSettlement::findOrFail($id);

        if (!$this->isManuallyEditableSettlement($settlement)) {
            return response()->json([
                'message' => 'Only manually-added entries can be deleted. Auto-posted location fees and per-row Paid credits cannot be removed here.',
            ], 422);
        }

        // Audit the removal (capture amount before → null) while the row still
        // exists so the log keeps the reference/type.
        $this->logSettlement(
            $settlement,
            \App\Models\CustomerSettlementLog::ACTION_DELETED,
            $this->settlementTypeLabel($settlement->entry_type) . ' deleted from Payment History',
            (int) $settlement->amount_cents,
            null,
            'user'
        );

        $settlement->delete();

        return response()->json(['message' => 'Settlement entry deleted.']);
    }

    /**
     * Append one row to the settlement audit trail (customer_settlement_logs).
     * Called for every money action — payment, waiver, reversal, edit — so the
     * Payment-History "Change history" panel always shows who did what, when.
     */
    private function logSettlement(
        \App\Models\CustomerSettlement $settlement,
        string $action,
        ?string $note = null,
        ?int $oldAmount = null,
        ?int $newAmount = null,
        string $source = 'user'
    ): void {
        \App\Models\CustomerSettlementLog::create([
            'customer_id'            => $settlement->customer_id,
            'customer_settlement_id' => $settlement->id,
            'reference_no'           => $settlement->reference_no,
            'action'                 => $action,
            'entry_type'             => $settlement->entry_type,
            'old_amount_cents'       => $oldAmount,
            'new_amount_cents'       => $newAmount,
            'note'                   => $note,
            'changed_by'             => auth()->id(),
            'source'                 => $source,
        ]);
    }

    /** Human label for a settlement entry type, used in exports. */
    private function settlementTypeLabel(?string $type): string
    {
        return [
            \App\Models\CustomerSettlement::TYPE_OPENING_BALANCE => 'Opening Balance',
            \App\Models\CustomerSettlement::TYPE_LOCATION_FEE    => 'Location Fee',
            \App\Models\CustomerSettlement::TYPE_PAYMENT         => 'Payment',
            \App\Models\CustomerSettlement::TYPE_WAIVER          => 'Waiver',
            \App\Models\CustomerSettlement::TYPE_ADJUSTMENT      => 'Adjustment',
        ][$type] ?? ($type ?: '—');
    }

    /**
     * Export one site's settlement ledger (Payment History) to .xlsx.
     * Money columns are numeric major-currency units so Excel can sum/sort.
     */
    public function settlementsExportExcel($id)
    {
        $ledger = $this->buildSettlementLedger($id);

        $operatorCountry = auth()->user()->operator?->country;
        $divisor = pow(10, $operatorCountry?->currency_exponent ?? 2);

        $rows = collect($ledger['rows'])->map(function ($r) use ($divisor) {
            return [
                'Ref'         => $r['reference_no'],
                'Date'        => $r['entry_date'],
                'Type'        => $this->settlementTypeLabel($r['entry_type']),
                'Description' => $r['item'],
                'Debit'       => $r['debit_cents'] ? round($r['debit_cents'] / $divisor, 2) : null,
                'Credit'      => $r['credit_cents'] ? round($r['credit_cents'] / $divisor, 2) : null,
                'Balance'     => round($r['balance_cents'] / $divisor, 2),
                'Remarks'     => $r['remarks'],
                'Recorded By' => $r['created_by'],
            ];
        });

        // Closing balance row (blank money cells except Balance).
        $rows->push([
            'Ref' => '', 'Date' => '', 'Type' => '',
            'Description' => $ledger['outstanding_cents'] < 0 ? 'CREDIT BALANCE' : 'OUTSTANDING BALANCE',
            'Debit' => null, 'Credit' => null,
            'Balance' => round($ledger['outstanding_cents'] / $divisor, 2),
            'Remarks' => '', 'Recorded By' => '',
        ]);

        $fileName = 'PaymentHistory_' . $ledger['ref_id'] . '_' . now()->format('YmdHis') . '.xlsx';

        return (new FastExcel($rows))->download($fileName);
    }

    /**
     * Printable Statement-of-Account view of one site's settlement ledger.
     * Returns a self-contained HTML page that auto-opens the browser print
     * dialog (Save as PDF) — no server-side PDF dependency required.
     */
    public function settlementsPrintView($id)
    {
        $ledger = $this->buildSettlementLedger($id);

        $operatorCountry = auth()->user()->operator?->country;
        $divisor = pow(10, $operatorCountry?->currency_exponent ?? 2);
        $symbol = $operatorCountry?->currency_symbol ?? '$';

        return response()->view('exports.settlement-statement', [
            'ledger'   => $ledger,
            'divisor'  => $divisor,
            'symbol'   => $symbol,
            'company'  => auth()->user()->operator?->name ?? config('app.name', 'Happy Ice'),
            'typeLabel' => fn ($t) => $this->settlementTypeLabel($t),
            'generatedAt' => now(),
        ]);
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
                // Pending future contract change (one at a time) + who set it.
                'scheduledContract.createdBy:id,name',
                'zone',
                // Site grouping — so Edit.vue can pre-fill the Group name field.
                'customerGroup:id,name,operator_id',
            ])
            ->find($id);

        // Use OptionsService for dropdown options
        $optionsService = app(\App\Services\OptionsService::class);

        // Site status change history (newest first) for the Status History popup.
        $statusHistory = \App\Models\CustomerStatusLog::with('changedBy:id,name')
            ->where('customer_id', $id)
            ->orderByDesc('id')
            ->get()
            ->map(fn ($log) => [
                'id' => $log->id,
                'status_id' => $log->status_id,
                'status_name' => $log->status_name,
                'status_date' => optional($log->status_date)->toDateString(),
                'changed_by' => $log->changedBy ? $log->changedBy->name : null,
                'created_at' => optional($log->created_at)->toDateTimeString(),
            ]);

        // Site grouping — existing groups for this site's operator, fed to the
        // Site Grouping dropdown as { value: name, label: name } with a leading
        // "--- Clear ---" (value '') to ungroup. The save path still binds by
        // customer_group_name (firstOrCreate), so selecting a managed group just
        // resolves to it; groups are created on Operations ▸ Site Grouping.
        $siteGroupNames = \App\Models\CustomerGroup::query()
            ->when($customer && $customer->operator_id, fn ($q) => $q->where('operator_id', $customer->operator_id))
            ->orderBy('name')
            ->pluck('name');

        // Always include the site's CURRENT group so the dropdown can render the
        // existing binding — a group assigned on the Site Grouping page may fall
        // outside this operator-scoped list, which would otherwise leave the
        // field blank even though the site is grouped.
        if ($customer && $customer->customerGroup) {
            $siteGroupNames = $siteGroupNames
                ->push($customer->customerGroup->name)
                ->unique()
                ->sort()
                ->values();
        }

        $siteGroupOptions = collect([['value' => '', 'label' => '--- Clear ---']])
            ->merge($siteGroupNames->map(fn ($n) => ['value' => $n, 'label' => $n]))
            ->values();

        return Inertia::render('Customer/Edit', [
            'cmsEndpoint' => env('CMS_URL'),
            'siteGroupOptions' => $siteGroupOptions,
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
            'statusHistory' => $statusHistory,
            'type' => 'update',
            'zoneOptions' => $optionsService->zones(),
        ]);
    }

    /**
     * Create or replace the single PENDING future contract change for a
     * customer. The values sit dormant until `contract:apply-scheduled` runs
     * on the chosen effective_date (see that command + the
     * customer_scheduled_contracts migration). Only one pending row is allowed
     * at a time, so this upserts: any existing pending row is overwritten.
     *
     * The live contract is NOT touched here — it only changes when the
     * schedule applies on its date.
     */
    public function storeScheduledContract(Request $request, $id)
    {
        $customer = Customer::findOrFail($id);

        $commissionType = $request->input('contract_commission_type');
        $psTypes = ['PS', 'PS+U', 'PSORU'];

        $validated = $request->validate([
            // Must be a real future date — scheduling "today or earlier" makes
            // no sense (use the normal contract fields for an immediate change).
            'effective_date'             => 'required|date|after:today',
            'contract_commission_type'   => 'nullable|in:F,S,R,U,R+U,PS,PS+U,PSORU',
            'contract_commission_value'  => [
                'nullable', 'numeric', 'min:0',
                ...($commissionType && in_array($commissionType, $psTypes, true) ? ['max:100'] : []),
            ],
            'contract_commission_value2' => 'nullable|numeric|min:0',
            'contract_ps_term'           => 'nullable|numeric|min:0|max:100',
            'is_external_subsidize'      => 'nullable|boolean',
            'external_subsidize_amount'  => 'nullable|numeric|min:0',
            'contract_from'              => 'nullable|date',
            'contract_until'             => 'nullable|date',
            'contract_auto_renewal'      => 'nullable|boolean',
            'contract_notice_period'     => 'nullable|string|in:' . implode(',', Customer::NOTICE_PERIOD_OPTIONS),
            'contract_remarks'           => 'nullable|string|max:5000',
        ]);

        // Mirror the live-form rule: never persist a subsidy amount while the
        // toggle is off.
        $isExternalSubsidize = (bool) ($validated['is_external_subsidize'] ?? false);
        $externalSubsidizeAmount = $isExternalSubsidize ? ($validated['external_subsidize_amount'] ?? null) : null;

        $payload = [
            'customer_id'                => $customer->id,
            'effective_date'             => \Carbon\Carbon::parse($validated['effective_date'])->toDateString(),
            'status'                     => CustomerScheduledContract::STATUS_PENDING,
            'applied_at'                 => null,
            'contract_commission_type'   => $validated['contract_commission_type'] ?? null,
            'contract_commission_value'  => $validated['contract_commission_value'] ?? null,
            'contract_commission_value2' => $validated['contract_commission_value2'] ?? null,
            'contract_ps_term'           => $validated['contract_ps_term'] ?? null,
            'is_external_subsidize'      => $isExternalSubsidize,
            'external_subsidize_amount'  => $externalSubsidizeAmount,
            'contract_from'              => !empty($validated['contract_from']) ? \Carbon\Carbon::parse($validated['contract_from'])->toDateString() : null,
            'contract_until'             => !empty($validated['contract_until']) ? \Carbon\Carbon::parse($validated['contract_until'])->toDateString() : null,
            'contract_auto_renewal'      => (bool) ($validated['contract_auto_renewal'] ?? false),
            'contract_notice_period'     => $validated['contract_notice_period'] ?? null,
            'contract_remarks'           => $validated['contract_remarks'] ?? null,
            'updated_by'                 => auth()->id(),
        ];

        // One pending row per customer: reuse the existing one if present so we
        // never stack two schedules, otherwise create it (stamping created_by).
        $existing = CustomerScheduledContract::query()
            ->where('customer_id', $customer->id)
            ->where('status', CustomerScheduledContract::STATUS_PENDING)
            ->latest('id')
            ->first();

        if ($existing) {
            $existing->update($payload);
        } else {
            $payload['created_by'] = auth()->id();
            CustomerScheduledContract::create($payload);
        }

        return redirect()->back();
    }

    /**
     * Cancel the pending future contract change for a customer (keeps the row
     * as a status = cancelled audit trail). No-op if there is none.
     */
    public function cancelScheduledContract(Request $request, $id)
    {
        $customer = Customer::findOrFail($id);

        CustomerScheduledContract::query()
            ->where('customer_id', $customer->id)
            ->where('status', CustomerScheduledContract::STATUS_PENDING)
            ->update([
                'status' => CustomerScheduledContract::STATUS_CANCELLED,
                'updated_by' => auth()->id(),
            ]);

        return redirect()->back();
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

    /**
     * Human-readable label for a customer already holding a CMS Linking ID,
     * used in the duplicate-person_id validation message so the user can see
     * exactly which site the ID is bound to AND jump straight to it.
     *
     * Returns an HTML <a> linking to that site's edit page, labelled with the
     * Site ID# (id + RUNNING_NUMBER_INIT — the same number shown in the form's
     * read-only "Site ID#" box, NOT the CMS virtual_customer_code) and the site
     * name. The customer name is the only user-controlled part, so it's escaped
     * with e() — the rest are integers. The form renders this via v-html, so
     * keeping the markup minimal + escaped here is what makes that safe.
     */
    private function describeBoundCustomer(Customer $customer): string
    {
        $siteId = $customer->id + Customer::RUNNING_NUMBER_INIT;
        $name = $customer->name ?: ($customer->virtual_customer_code ?: ('customer #' . $customer->id));
        $label = 'Site ' . $siteId . ' (' . e($name) . ')';

        return '<a href="/customers/' . $customer->id . '/edit" target="_blank" rel="noopener noreferrer" class="underline font-medium">'
            . $label
            . '</a>';
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
                // so unlinked sites are unaffected. Closure (instead of the plain
                // `unique` rule) so the rejection names the site already holding
                // the ID, e.g. "...already bound to ABC - Foo".
                'person_id' => ['nullable', 'integer', function ($attribute, $value, $fail) {
                    if ($value === null || $value === '') {
                        return;
                    }
                    $existing = Customer::where('person_id', $value)->first();
                    if ($existing) {
                        $fail('The CMS Linking ID ' . $value . ' is already bound to ' . $this->describeBoundCustomer($existing) . '.');
                    }
                }],
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
            $request->validate($rules, [], ['person_id' => 'CMS Linking ID']);

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

            // Begin Date is optional on the form — when left blank, default it to
            // today so every new site has an explicit start date (the active_date
            // seeding below and downstream Summary windows then derive from it).
            if (empty($createData['begin_date'])) {
                $createData['begin_date'] = \Carbon\Carbon::today()->toDateString();
            }

            // New active site: seed active_date so the Summary commission window
            // starts at creation (begin_date if entered, else today). The
            // aggregator also falls back to begin_date, but seeding it keeps the
            // customer record explicit.
            if ($statusId === Customer::STATUS_ACTIVE && empty($createData['active_date'])) {
                $createData['active_date'] = !empty($createData['begin_date'])
                    ? \Carbon\Carbon::parse($createData['begin_date'])->toDateString()
                    : \Carbon\Carbon::today()->toDateString();
            }

            // New site created directly as "Removed": seed removed_date (from the
            // status prompt, else today) so the Summary commission cutoff is set.
            if ($statusId === Customer::STATUS_REMOVED) {
                $createData['removed_date'] = !empty($createData['removed_date'])
                    ? \Carbon\Carbon::parse($createData['removed_date'])->toDateString()
                    : \Carbon\Carbon::today()->toDateString();
            }

            $customer = Customer::create($createData);

            // Seed the Status History with the initial status + its effective
            // date (Active → active_date, Removed → removed_date; none otherwise).
            $initialStatusDate = $statusId === Customer::STATUS_ACTIVE
                ? ($createData['active_date'] ?? null)
                : ($statusId === Customer::STATUS_REMOVED ? ($createData['removed_date'] ?? null) : null);
            \App\Models\CustomerStatusLog::create([
                'customer_id' => $customer->id,
                'status_id' => $statusId,
                'status_date' => $initialStatusDate,
                'changed_by' => auth()->id(),
                'source' => 'user',
            ]);

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

    public function update(Request $request, $id)
    {
        // dd($request->all());
        $customer = Customer::find($id);
        $vend = Vend::find($request->id);

        $requestCustomerArr = $request->customer;

        // Server-managed audit columns — never trust these from the payload.
        // The Edit page builds its form by spreading the serialized Customer
        // model, where the eager-loaded contractDetailUpdatedBy relation
        // overrides the raw contract_detail_updated_by column with an
        // {id, name} OBJECT. Shipping that back stringifies to "Array" → 0
        // on the int column and trips the customers_contract_detail_updated_by
        // users FK (SQLSTATE 23000/1452) on ANY save of a customer that has a
        // prior contract audit stamp — e.g. just changing Refilling Routes.
        // Strip them here; they are re-stamped below when a genuine contract
        // change is detected. Timestamps are likewise model-managed.
        if (is_array($requestCustomerArr)) {
            unset(
                $requestCustomerArr['contract_detail_updated_at'],
                $requestCustomerArr['contract_detail_updated_by'],
                $requestCustomerArr['created_at'],
                $requestCustomerArr['updated_at'],
            );

            // Refilling Routes "--- Clear ---" option posts zone_id = '' — coerce
            // to NULL so the zones FK on the int column isn't fed an empty string.
            if (($requestCustomerArr['zone_id'] ?? null) === '') {
                $requestCustomerArr['zone_id'] = null;
            }

            // Site grouping — resolve the typed Group name into a
            // customer_group_id, find-or-creating the group scoped to this
            // site's (possibly just-changed) operator. A blank name unbinds the
            // site (NULL). customer_group_name is a UI-only field, never a
            // column, so it must not reach the mass-assign below. Only the Site
            // edit form sends this key; other save paths (saveVend/submit) post
            // a flat payload and never enter this branch, so grouping is left
            // untouched there.
            if (array_key_exists('customer_group_name', $requestCustomerArr)) {
                $groupName = trim((string) $requestCustomerArr['customer_group_name']);
                unset($requestCustomerArr['customer_group_name']);
                if ($groupName === '') {
                    $requestCustomerArr['customer_group_id'] = null;
                } else {
                    $groupOperatorId = $requestCustomerArr['operator_id'] ?? ($customer->operator_id ?? null);
                    $group = \App\Models\CustomerGroup::firstOrCreate(
                        ['operator_id' => $groupOperatorId, 'name' => $groupName],
                        ['created_by' => auth()->id()]
                    );
                    $requestCustomerArr['customer_group_id'] = $group->id;
                }
            }

            $request->merge(['customer' => $requestCustomerArr]);
        }
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

        // ── Site lifecycle status-change handling ──────────────────────────
        // When the status actually changes, capture the effective date for the
        // new status and remember it so a customer_status_logs row is appended
        // after the save:
        //   Active   → Active Date (from the prompt; defaults today). Opens a
        //              new active interval, so removed_date is cleared.
        //   Removed  → Removed Date (from the prompt; defaults today). Commission
        //              stops after this date (removal month prorated).
        //   Inactive → auto-stamps termination_date (record-only Inactive Date;
        //              not user-settable, does NOT gate the calc).
        //   Potential/New → no date.
        $statusActuallyChanged = $customer && (int) $customer->status_id !== $statusId;
        // Also treat a changed Active/Removed effective date (even when the
        // status label stays the same — e.g. correcting a Removed Date on a
        // site that is already Removed) as a loggable event.
        $dateOnlyChanged = false;
        if ($customer && !$statusActuallyChanged) {
            $toDate = fn ($v) => !empty($v) ? \Carbon\Carbon::parse($v)->toDateString() : null;
            if ($statusId === Customer::STATUS_ACTIVE) {
                $dateOnlyChanged = $toDate($requestCustomerArr['active_date'] ?? null) !== $toDate($customer->active_date);
            } elseif ($statusId === Customer::STATUS_REMOVED) {
                $dateOnlyChanged = $toDate($requestCustomerArr['removed_date'] ?? null) !== $toDate($customer->removed_date);
            }
        }
        $statusChanged = $statusActuallyChanged || $dateOnlyChanged;
        $statusLogDate = null;
        if ($statusChanged) {
            if ($statusId === Customer::STATUS_ACTIVE) {
                $activeDate = !empty($requestCustomerArr['active_date'])
                    ? \Carbon\Carbon::parse($requestCustomerArr['active_date'])->toDateString()
                    : \Carbon\Carbon::today()->toDateString();
                $requestCustomerArr['active_date'] = $activeDate;
                // Only reopen the interval (clear removed_date) on a real
                // transition INTO Active — not when merely correcting the date.
                if ($statusActuallyChanged) {
                    $requestCustomerArr['removed_date'] = null;
                }
                $statusLogDate = $activeDate;
            } elseif ($statusId === Customer::STATUS_REMOVED) {
                $removedDate = !empty($requestCustomerArr['removed_date'])
                    ? \Carbon\Carbon::parse($requestCustomerArr['removed_date'])->toDateString()
                    : \Carbon\Carbon::today()->toDateString();
                $requestCustomerArr['removed_date'] = $removedDate;
                $statusLogDate = $removedDate;
            } elseif ($statusId === Customer::STATUS_INACTIVE) {
                $requestCustomerArr['termination_date'] = now()->toDateTimeString();
                $statusLogDate = \Carbon\Carbon::today()->toDateString();
            }
            $request->merge(['customer' => $requestCustomerArr]);
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
                // Site lifecycle dates entered via the status-change prompt.
                'customer.active_date'                     => 'nullable|date',
                'customer.removed_date'                    => 'nullable|date',
                // Termination Date — hard end for the site (ignores notice
                // period). Drives prorated profit-sharing cutoff in the Summary
                // aggregator; nullable so un-terminated sites are unaffected.
                'customer.termination_date'                => 'nullable|date',
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
                // (nullable), so unlinked sites are unaffected. Closure (instead
                // of the plain `unique` rule) so the rejection names the site
                // already holding the ID, e.g. "...already bound to ABC - Foo".
                'customer.person_id'                       => ['nullable', 'integer', function ($attribute, $value, $fail) use ($customer) {
                    if ($value === null || $value === '') {
                        return;
                    }
                    $existing = Customer::where('person_id', $value)
                        ->where('id', '!=', $customer->id)
                        ->first();
                    if ($existing) {
                        $fail('The CMS Linking ID ' . $value . ' is already bound to ' . $this->describeBoundCustomer($existing) . '.');
                    }
                }],
            ];

            // Friendly attribute name so a unique/integer rejection reads
            // "CMS Linking ID has already been taken" instead of the raw
            // "customer.person id" dot-path.
            $request->validate($contractRules, [], ['customer.person_id' => 'CMS Linking ID']);

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

            // Capture the pre-update lifecycle dates so we can recompute the
            // affected month span below if they change (flat-fee proration).
            $oldActiveDate = $customer->active_date;
            $oldRemovedDate = $customer->removed_date;

            $customer->update($request->customer);

            // Append a Status History row whenever the site status changed (date
            // captured above). Append-only audit; powers the Status History popup.
            if ($statusChanged) {
                \App\Models\CustomerStatusLog::create([
                    'customer_id' => $customer->id,
                    'status_id' => $statusId,
                    'status_date' => $statusLogDate,
                    'changed_by' => auth()->id(),
                    'source' => 'user',
                ]);
            }

            // Activation / removal date drives flat-fee proration in the Summary
            // aggregator. When either changed, recompute the affected month span
            // NOW (queued) so the stored figures — and the headline totals that
            // SUM them — reflect the new proration immediately, instead of
            // waiting for the nightly run. The per-row display already re-derives
            // live; this keeps the totals boxes in lockstep on the same save.
            $this->dispatchSummaryRecomputeForLifecycleChange(
                $oldActiveDate,
                $customer->active_date,
                $oldRemovedDate,
                $customer->removed_date
            );

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
            'status' => $request->status ?: [Customer::STATUS_ACTIVE, Customer::STATUS_REMOVED],
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
