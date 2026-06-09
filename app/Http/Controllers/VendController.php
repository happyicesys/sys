<?php

namespace App\Http\Controllers;

// use App\Exports\VendTempExport;
use App\Exports\VendTransactionExport;
use App\Jobs\PublishMqtt;
use App\Http\Resources\DCVend\VendResource as DCVendResource;
use App\Http\Resources\DCVend\CustomerResource as DCVendCustomerResource;
use App\Http\Resources\CategoryResource;
use App\Http\Resources\CategoryGroupResource;
use App\Http\Resources\CountryResource;
use App\Http\Resources\CustomerResource;
use App\Http\Resources\DeliveryPlatformResource;
use App\Http\Resources\LocationTypeResource;
use App\Http\Resources\ModemTypeResource;
use App\Http\Resources\ModemUnitResource;
use App\Http\Resources\OperatorResource;
use App\Http\Resources\PaymentGatewayLogResource;
use App\Http\Resources\PaymentGatewayResource;
use App\Http\Resources\PaymentMethodResource;
use App\Http\Resources\ProductMappingResource;
use App\Http\Resources\ProductResource;
use App\Http\Resources\TagResource;
use App\Http\Resources\UserResource;
use App\Http\Resources\VendDBResource;
use App\Http\Resources\VendResource;
use App\Http\Resources\VendChannelResource;
use App\Http\Resources\VendChannelErrorResource;
use App\Http\Resources\VendConfigResource;
use App\Http\Resources\VendContractResource;
use App\Http\Resources\VendFanResource;
use App\Http\Resources\VendModelResource;
use App\Http\Resources\VendPrefixResource;
use App\Http\Resources\VendTransactionResource;
use App\Http\Resources\VendTransactionItemResource;
use App\Http\Resources\VendTempResource;
use App\Http\Resources\ZoneResource;
use App\Jobs\ExportVendTransactionCsv;
use App\Jobs\ExportVendTransactionCsvChunk;
use App\Jobs\SyncVendCustomerCms;
use App\Jobs\Vend\SaveVendChannelsJson;
use App\Mail\VendChannelErrorLogsMail;
use App\Models\Campaign;
use App\Models\CardTerminal;
use App\Models\CampaignItem;
use App\Models\Category;
use App\Models\CategoryGroup;
use App\Models\Country;
use App\Models\Customer;
use App\Models\DeliveryPlatform;
use App\Models\ExportJob;
use App\Models\ExportJobChunk;
use App\Models\LocationType;
use App\Models\ModemType;
use App\Models\ModemUnit;
use App\Models\Operator;
use App\Models\OpsJob;
use App\Models\PaymentMethod;
use App\Models\PaymentGateway;
use App\Models\PaymentGatewayLog;
use App\Models\Product;
use App\Models\ProductMapping;
use App\Models\ProductMappingItem;
use App\Models\SellingPrice;
use App\Models\Tag;
use App\Models\User;
use App\Models\Vend;
use App\Models\VendChannel;
use App\Models\VendChannelError;
use App\Models\VendChannelErrorLog;
use App\Models\VendConfig;
use App\Models\VendContract;
use App\Models\VendData;
use App\Models\VendModel;
use App\Models\VendPrefix;
use App\Models\VendRecord;
use App\Models\VendSnapshot;
use App\Models\VendLog;
use App\Models\VendTemp;
use App\Models\VendTransaction;
use App\Models\VendTransactionItem;
use App\Models\PaymentGateways\Midtrans;
use App\Models\PaymentGateways\Omise;
use App\Models\Zone;
use App\Services\CmsService;
use App\Services\CustomerSummaryAggregator;
use App\Services\HistoryService;
use App\Services\MapService;
use App\Services\MqttService;
use App\Services\PaymentGatewayService;
use App\Services\ProductMappingService;
use App\Services\RunningNumberService;
use App\Services\VendDataService;
use App\Services\VendDispenseService;
use App\Services\VendJobService;
use App\Traits\GetUserTimezone;
use App\Traits\HasFilter;
use Carbon\Carbon;
use DB;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Cache;
use Intervention\Image\Laravel\Facades\Image;
use Inertia\Inertia;
use Maatwebsite\Excel\Facades\Excel;
use Rap2hpoutre\FastExcel\FastExcel;
use Spatie\Permission\Models\Role;


class VendController extends Controller
{
    use GetUserTimezone, HasFilter;

    protected $cmsService;
    protected $historyService;
    protected $mapService;
    protected $mqttService;
    protected $paymentGatewayService;
    protected $productMappingService;
    protected $runningNumberService;
    protected $vendDataService;
    protected $vendDispenseService;
    protected $vendJobService;


    public function __construct(
        CmsService $cmsService,
        HistoryService $historyService,
        MqttService $mqttService,
        PaymentGatewayService $paymentGatewayService,
        RunningNumberService $runningNumberService,
        VendDataService $vendDataService,
        VendDispenseService $vendDispenseService,
        VendJobService $vendJobService
    ) {
        $this->middleware(['permission:read vend-customers'])->only('indexCustomer');
        $this->middleware(['permission:read machine-view'])->only('index');
        $this->middleware(['permission:read machine-view|read vend-customers'])->only('logs');
        $this->middleware(['permission:read transactions'])->only('transactionIndex');
        $this->cmsService = $cmsService;
        $this->historyService = $historyService;
        $this->mapService = new MapService();
        $this->mqttService = $mqttService;
        $this->paymentGatewayService = $paymentGatewayService;
        $this->productMappingService = new ProductMappingService();
        $this->runningNumberService = $runningNumberService;
        $this->vendDataService = $vendDataService;
        $this->vendDispenseService = $vendDispenseService;
        $this->vendJobService = $vendJobService;
    }

    public function index(Request $request)
    {
        $request->merge(['visited' => isset($request->visited) ? $request->visited : true]);
        if (!isset($request->is_active)) {
            if (
                auth()->user()->hasRole('superadmin') or
                auth()->user()->hasRole('admin') or
                auth()->user()->hasRole('supervisor') or
                auth()->user()->hasRole('observer_transactions') or
                auth()->user()->hasRole('driver')
            ) {
                $request->merge(['is_active' => 'true']);
            } else {
                $request->merge(['is_active' => 'all']);
            }
        }

        if (auth()->user()->is_production_status_only) {
            $request->merge([
                'status' => 'factory'
            ]);
        } else {
            $request->merge([
                'status' => $request->status != null ? $request->status : 'active'
            ]);
        }


        if (!$request->operators) {
            $userOperator = auth()->user()->operator;

            if ($userOperator && $userOperator->code === 'HIPL') {
                $relatedCodes = ['HIPL', 'HIMD', 'LEA', 'HIESG', 'UL-ST'];

                $operatorIds = Operator::whereIn('code', $relatedCodes)
                    ->pluck('id')
                    ->filter()
                    ->values()
                    ->toArray();

                $request->merge(['operators' => $operatorIds]);
            } else {
                $request->merge(['operators' => [$userOperator?->id]]);
            }
        }

        $request->merge([
            'indexType' => 'vends',
            'numberPerPage' => isset($request->numberPerPage) ? $request->numberPerPage : 50,
            'sortKey' => isset($request->sortKey) ? $request->sortKey : 'balance_percent',
            'sortBy' => isset($request->sortBy) ? $request->sortBy : true,
        ]);
        $className = get_class(new Customer());

        $sortKey = $request->sortKey;
        $needsVc = in_array($sortKey, ['thirty_days_over_full_load_ratio', 'total_stock_amount', 'total_full_load_amount']);
        $needsVcCost = in_array($sortKey, ['total_stock_cost']);



        $vends = Vend::query()
            ->with([
                'cardTerminal',
                'customer:id,name,code,person_id',
                'customer.deliveryAddress',
                'modemType',
                'modemUnit',
                'productMapping:id,name',
                'deliveryProductMappingVends.deliveryProductMapping.deliveryPlatformOperator.deliveryPlatform:id,name'
            ]);

        $vends->leftJoin('customers', 'vends.customer_id', '=', 'customers.id')
            ->leftJoin('categories', 'categories.id', '=', 'customers.category_id')
            ->leftJoin('category_groups', 'category_groups.id', '=', 'categories.category_group_id')
            ->leftJoin('location_types', 'location_types.id', '=', 'customers.location_type_id')
            ->leftJoin('operators', 'operators.id', '=', 'vends.operator_id')
            ->leftJoin('product_mappings', 'product_mappings.id', '=', 'vends.product_mapping_id')
            ->leftJoin('zones', 'zones.id', '=', 'customers.zone_id')
            ->leftJoin('addresses', function ($query) {
                $query->on('addresses.modelable_id', '=', 'customers.id')
                    ->where('addresses.modelable_type', '=', 'App\Models\Customer')
                    ->where('addresses.type', '=', 2);
            })
            ->leftJoin('vend_configs', 'vend_configs.id', '=', 'vends.vend_config_id')
            ->leftJoin('vend_prefixes', 'vend_prefixes.id', '=', 'vends.vend_prefix_id')
            ->leftJoin('modem_types', 'modem_types.id', '=', 'vends.modem_type_id')
            ->leftJoin('modem_units', 'modem_units.id', '=', 'vends.modem_unit_id');

        $vends = $this->filterVendsDB($vends, $request);
        $vends = $this->filterOperatorDB($vends);

        // Subquery Joins for sorting columns
        $needsVc = in_array($request->sortKey, ['total_full_load_amount', 'total_stock_amount', 'thirty_days_over_full_load_ratio']);
        $needsVcCost = in_array($request->sortKey, ['total_stock_cost']);
        $needsVcStock = in_array($request->sortKey, ['actual_stock_in_value', 'actual_stock_in_qty']);
        $needsLastOpsJobs = in_array($request->sortKey, ['last_ops_job_acc_total_amount', 'last_ops_job_acc_total_count', 'last_ops_job_amount', 'last_ops_job_cash_amount', 'last_ops_job_count']);
        $needsLastSecondOpsJobs = in_array($request->sortKey, ['last_second_ops_job_acc_total_amount', 'last_second_ops_job_acc_total_count', 'last_second_ops_job_amount', 'last_second_ops_job_cash_amount', 'last_second_ops_job_count']);
        $needsNextOpsJobs = in_array($request->sortKey, ['next_ops_job_amount', 'next_ops_job_cash_amount', 'next_ops_job_count']);
        $needsLastThirtyDaysStockIn = in_array($request->sortKey, ['last_thirty_days_stock_in_amount', 'last_thirty_days_stock_in_qty', 'thirty_days_stock_in_delta_amount', 'thirty_days_stock_in_delta_percent']);

        $total = (clone $vends)->count();

        // Apply conditional joins for data retrieval
        $vends->when($needsVc, function ($query) {
            $query->leftJoin(DB::raw('
                (
                    SELECT vend_id, SUM(amount * qty) AS total_stock_amount, SUM(amount * capacity) AS total_full_load_amount
                    FROM vend_channels
                    WHERE is_active = true
                    AND capacity > 0
                    GROUP BY vend_id
                ) AS vc
            '), 'vc.vend_id', '=', 'vends.id');
        })
            ->when($needsVcCost, function ($query) {
                $query->leftJoin(DB::raw('
                (
                    SELECT
                        vend_channels.vend_id,
                        SUM(vend_channels.qty * unit_costs.cost) AS total_stock_cost
                    FROM
                        vend_channels
                    INNER JOIN
                        products ON vend_channels.product_id = products.id
                    INNER JOIN
                        unit_costs ON products.id = unit_costs.product_id
                    WHERE
                        unit_costs.is_current = true
                    AND vend_channels.is_active = true
                    AND vend_channels.capacity > 0
                    GROUP BY
                        vend_channels.vend_id
                ) AS vc_cost
            '), 'vc_cost.vend_id', '=', 'vends.id');
            });

        $selectColumns = [
            'vends.id AS id',
            'vends.id AS vend_id',
            'vends.t1_lowest_48h',
            'vends.amount_average_day',
            'vends.begin_date',
            'vends.card_terminal_id',
            'vends.code',
            'vends.acb_vmc_pa_json',
            'vends.apk_ver_json',
            'vends.balance_percent',
            'vends.serial_num',
            'vends.temp',
            'vends.temp_updated_at',
            'vends.termination_date',
            'vends.coin_amount',
            'vends.firmware_ver',
            'vends.is_active AS vend_is_active',
            'vends.is_door_open',
            'vends.is_disposed',
            'vends.is_sold',
            'vends.is_mqtt',
            'vends.is_mqtt_active',
            'vends.is_online',
            'vends.is_sensor_normal',
            'vends.is_temp_active',
            'vends.is_temp_error',
            'vends.is_testing',
            'vends.label_name',
            'vends.lcd_monitor_id',
            'vends.last_updated_at',
            'vends.modem_type_id',
            'vends.modem_unit_id',
            'vends.mqtt_last_updated_at',
            'vends.operator_id',
            'vends.out_of_stock_sku_percent',
            'vends.parameter_json',
            'vends.product_mapping_id',
            'vends.private_key',
            'vends.is_fan_enabled',
            'vends.termination_date',
            'vends.vend_channel_totals_json',
            'vends.vend_channel_error_logs_json',
            'vends.vend_channels_json',
            'vends.vend_transaction_totals_json',
            'vends.vend_type_id',
            'vends.virtual_vend_records_thirty_days_amount_average',
            'vends.is_active AS is_active',
            'customers.id AS customer_id',
            'customers.code AS customer_code',
            'customers.is_active AS customer_is_active',
            'customers.name AS customer_name',
            'customers.person_json',
            'customers.person_id AS person_id',
            'customers.selling_price_type',
            'customers.virtual_customer_prefix',
            'customers.virtual_customer_code',
            'modem_types.name AS modem_type_name',
            'modem_types.is_resetable AS modem_type_is_resettable',
            'modem_units.imei AS modem_unit_imei',
            'modem_units.is_online AS modem_unit_is_online',
            'modem_units.last_updated_at AS modem_unit_last_updated_at',
            'product_mappings.name AS product_mapping_name',
            'product_mappings.remarks AS product_mapping_remarks',
            'operators.code AS operator_code',
            'operators.name AS operator_name',
            'vend_configs.name AS vend_config_name',
            'vends.vend_vend_config_version',
            'vends.upcoming_product_mapping_id',
            'vend_prefixes.name AS vend_prefix_name',
            'customers.totals_json AS customers_totals_json',
            // 'delivery_platforms.slug AS delivery_platform_slug'
        ];

        if ($needsVc) {
            $selectColumns[] = 'vc.total_full_load_amount';
            $selectColumns[] = 'vc.total_stock_amount';
        }

        if ($needsVcCost) {
            $selectColumns[] = 'vc_cost.total_stock_cost';
        }

        $vends->select($selectColumns);

        $page = Paginator::resolveCurrentPage() ?: 1;
        $perPage = $request->numberPerPage === 'All' ? 10000 : ($request->numberPerPage ?: 50);

        $items = $vends->forPage($page, $perPage)->get();

        $vends = new LengthAwarePaginator($items, $total, $perPage, $page, [
            'path' => Paginator::resolveCurrentPath(),
            'query' => $request->query(),
        ]);

        if (!$needsVc || !$needsVcCost) {
            $types = [];
            if (!$needsVc)
                $types[] = 'vc';
            if (!$needsVcCost)
                $types[] = 'vc_cost';
            $this->loadAggregates($vends->getCollection(), $types);
        }

        $totals = [
            // 'thirtyDays' => collect((clone $vends)
            //                 ->items())
            //                 ->sum(function($vend) {
            //                     return $vend->vend_transaction_totals_json ? json_decode($vend->vend_transaction_totals_json)->thirty_days_amount : 0;
            //                 })/100,
            // 'thirthyDaysAvg' => collect((clone $vends)
            //                 ->items())
            //                 ->sum(function($vend) {
            //                     return $vend->vend_transaction_totals_json ? json_decode($vend->vend_transaction_totals_json)->vend_records_thirty_days_amount_average : 0;
            //                 })/100,
            'thirtyDays' => collect((clone $vends)
                ->items())
                ->sum(function ($vend) {
                    return $vend->vend_transaction_totals_json ? $vend->vend_transaction_totals_json['thirty_days_amount'] : 0;
                }) / 100,
            'thirthyDaysAvg' => collect((clone $vends)
                ->items())
                ->sum(function ($vend) {
                    $customerTotals = is_string($vend->customers_totals_json)
                        ? json_decode($vend->customers_totals_json, true)
                        : $vend->customers_totals_json;
                    return $customerTotals ? ($customerTotals['vend_records_thirty_days_amount_average'] ?? 0) : 0;
                }) / 100,
        ];

        return Inertia::render('Vend/Index', [
            'cmsEndpoint' => env('CMS_URL'),
            'constTempError' => VendTemp::TEMPERATURE_ERROR,
            'deliveryPlatformOptions' => DeliveryPlatformResource::collection(
                DeliveryPlatform::orderBy('name')->get()
            ),
            'deviceTypes' => Vend::DEVICE_TYPE_MAPPINGS,
            'indexType' => $request->indexType,
            'lcdMonitorOptions' => Vend::LCD_MONITOR_MAPPINGS,
            'locationTypeOptions' => LocationTypeResource::collection(
                LocationType::orderBy('sequence')->get()
            ),
            'modemTypeOptions' => ModemTypeResource::collection(
                ModemType::orderBy('id')->get()
            ),
            'modemUnitOptions' => ModemUnitResource::collection(
                ModemUnit::orderBy('imei')->get()
            ),
            'nextDeliveryDriverOptions' => DB::table('customers')
                ->where('cms_invoice_history->next_delivery_driver', '!=', null)
                ->selectRaw('DISTINCT cms_invoice_history->>"$.next_delivery_driver" AS name')
                ->get(),
            'operatorOptions' => OperatorResource::collection(
                Operator::with('logo')->orderBy('name')->get()
            ),
            'productOptions' => ProductResource::collection(
                Product::query()
                    ->with('thumbnail')
                    ->select('id', 'code', 'desc', 'name', 'is_available')
                    ->where('is_active', true)
                    ->where('is_inventory', true)
                    ->orderBy('code')
                    ->get()
            ),
            'sellingPriceTypeOptions' => SellingPrice::TYPE_MAPPINGS,
            'totals' => $totals,
            'vends' => VendResource::collection($vends),
            'vendChannelErrors' => VendChannelErrorResource::collection(VendChannelError::orderBy('code')->get()),
            'vendConfigOptions' => VendConfigResource::collection(
                VendConfig::orderBy('name')->get()
            ),
            'vendModelOptions' => VendModelResource::collection(
                VendModel::orderBy('name')->get()
            ),
            'vendPrefixOptions' => VendPrefixResource::collection(
                VendPrefix::orderBy('name')->get()
            ),
        ]);
    }

    public function indexCustomer(Request $request)
    {
        $request->merge(['visited' => isset($request->visited) ? $request->visited : true]);

        // NB: || not `or` — `or` has lower precedence than `=` and would
        // only assign the FIRST hasRole() result.
        $isAdminish = auth()->user()->hasRole('superadmin')
            || auth()->user()->hasRole('admin')
            || auth()->user()->hasRole('supervisor')
            || auth()->user()->hasRole('observer_transactions')
            || auth()->user()->hasRole('driver');

        if ($request->indexType === 'customers') {
            // 5-value Customer Status (matches Customer::STATUSES_MAPPING),
            // replacing the binary "Customer Active?" filter on this page.
            // Defaults to Active for admin-ish roles, else "All" — mirrors the
            // prior is_active default. Then we push customer_status into the
            // request's `status` slot so Customer::filterIndex's existing
            // status_id branch picks it up, and neutralise is_active so the
            // legacy boolean filter doesn't conflict (`is_active=true` AND
            // `status=1` would yield zero rows).
            if ($request->customer_status === null || $request->customer_status === '') {
                $request->merge(['customer_status' => $isAdminish ? Customer::STATUS_ACTIVE : 'all']);
            }
            $request->merge([
                'status' => $request->customer_status,
                'is_active' => 'all',
            ]);
        } else if (!isset($request->is_active)) {
            // Vends list — keep the existing binary is_active default.
            if ($isAdminish) {
                $request->merge(['is_active' => 'true']);
            } else {
                $request->merge(['is_active' => 'all']);
            }
        }

        if (!$request->operators) {
            $userOperator = auth()->user()->operator;

            if ($userOperator && $userOperator->code === 'HIPL') {
                $relatedCodes = ['HIPL', 'HIMD', 'LEA', 'HIESG', 'UL-ST'];

                $operatorIds = Operator::whereIn('code', $relatedCodes)
                    ->pluck('id')
                    ->filter()
                    ->values()
                    ->toArray();

                $request->merge(['operators' => $operatorIds]);
            } else {
                $request->merge(['operators' => [$userOperator?->id]]);
            }
        }

        $request->merge([
            'indexType' => 'customers',
            'numberPerPage' => isset($request->numberPerPage) ? $request->numberPerPage : 50,
            'sortKey' => isset($request->sortKey) ? $request->sortKey : 'balance_percent',
            'sortBy' => isset($request->sortBy) ? $request->sortBy : true,
            'productAvailableDate' => isset($request->productFilters['productAvailableDate']) ? Carbon::parse($request->productFilters['productAvailableDate'])->toDateString() : Carbon::today()->addDay()->toDateString()
        ]);

        $className = get_class(new Customer());

        $sortKey = $request->sortKey;
        $needsVc = in_array($sortKey, ['thirty_days_over_full_load_ratio', 'total_stock_amount', 'total_full_load_amount']);
        $needsVcCost = in_array($sortKey, ['total_stock_cost']);
        $needsVcStock = in_array($sortKey, ['actual_stock_in_value', 'actual_stock_in_qty']);
        $needsLastOpsJobs = in_array($sortKey, ['last_ops_job_acc_total_amount', 'last_ops_job_acc_total_count', 'last_ops_job_amount', 'last_ops_job_cash_amount', 'last_ops_job_count']);
        $needsLastSecondOpsJobs = in_array($sortKey, ['last_second_ops_job_acc_total_amount', 'last_second_ops_job_acc_total_count', 'last_second_ops_job_amount', 'last_second_ops_job_cash_amount', 'last_second_ops_job_count']);
        $needsNextOpsJobs = in_array($sortKey, ['next_ops_job_amount', 'next_ops_job_cash_amount', 'next_ops_job_count']);
        $needsLastThirtyDaysStockIn = in_array($sortKey, ['last_thirty_days_stock_in_amount', 'last_thirty_days_stock_in_qty', 'thirty_days_stock_in_delta_amount', 'thirty_days_stock_in_delta_percent', 'last_thirty_days_jobs_done_count']);
        // Sort hooks for the Contract Type column's Accumulated VendEarning and
        // L30d VendEarning rows. Both are normally computed in PHP after the
        // page is fetched (see the post-query loops further down). When the
        // user sorts by them we expose a matching SQL alias on the SELECT so
        // filterVendsDB() can ORDER BY the alias across the full result set.
        $needsAccumulatedVendingEarning = in_array($sortKey, ['accumulate_vending_earning_cents']);
        $needsThirtyDaysVendingEarning = in_array($sortKey, ['thirty_days_vending_earning_cents']);
        // External Subsidize / Net Loc Fee sort. Both normally computed in the
        // Vue cell (Ext Sub from the customer's current contract; Net Loc Fee =
        // Location Fees − Ext Sub). When the user sorts by them we expose
        // matching SQL aliases (external_subsidize / net_loc_fee) so the ORDER
        // BY applies across the full result set, not just the current page.
        $needsExternalSubsidize = in_array($sortKey, ['external_subsidize']);
        $needsNetLocFee = in_array($sortKey, ['net_loc_fee']);
        // PWRON 1d/2d/3d sort. Counts are normally attached in the post-query
        // PHP loop further down (see "PWRON 1d / 2d / 3d counts per vend").
        // When the user sorts by them we expose matching SQL aliases on the
        // SELECT via a single conditional-aggregation leftJoin against
        // vend_daily_stats so filterVendsDB() can ORDER BY across the full
        // result set (otherwise sort would only work within the current page).
        $needsPwron = in_array($sortKey, ['pwron_1d_count', 'pwron_2d_count', 'pwron_3d_count']);
        // "# of No Found in Txn" 1d/2d/3d sort — same pattern as PWRON. The
        // metric ('nofound_txn') is written by LogNofoundTxnIfStillMissing
        // (5-min delayed after a PG payment is approved) and decremented by
        // VendTransactionService when the matching vend_transactions row
        // finally lands. Both metrics share the same vend_daily_stats table
        // and the same three-date window, so we can reuse the date variables.
        $needsNofoundTxn = in_array($sortKey, ['nofound_txn_1d_count', 'nofound_txn_2d_count', 'nofound_txn_3d_count']);
        // Dates are app-TZ — mirrors the post-query loop and matches how
        // IncrementVendDailyStat buckets writes. Computed up here so both the
        // sort-leftJoin and the post-query enrichment use the same values.
        $pwronDate1d = Carbon::today()->toDateString();
        $pwronDate2d = Carbon::today()->subDay()->toDateString();
        $pwronDate3d = Carbon::today()->subDays(2)->toDateString();

        $shouldAutoload = $request->boolean('autoload', false);
        $perPage = $request->numberPerPage === 'All' ? 10000 : $request->numberPerPage;
        $perPage = $perPage ?: 50;
        $mapApiKey = $this->mapService->getMapApiKeyByUser(auth()->user());

        // Pre-Search aggregate cards ("Last 30 days" + "Current") — computed
        // across ALL rows matching the current filters (NOT capped by
        // numberPerPage) on the initial, non-autoload page load. Null on the
        // Search/autoload path so the frontend falls back to its existing
        // page-scoped totals/currentStats once the table is loaded.
        $initialStats = null;

        if ($shouldAutoload) {
            $vends = Customer::query()
                ->with([
                    'deliveryAddress',
                    // Customer Tag chips + last-edited-by audit line for the
                    // new "Customer Tag / Note" column. Mirrors the eager-load
                    // already used by the Customer Summary page so the same
                    // VendResource fields are populated here.
                    'tagBindings',
                    'tagBindings.tag:id,name,slug,classname',
                    'notesUpdatedBy:id,name',
                    // Audit user for the inline Ops Note edit (same Customer
                    // row, same pattern as notesUpdatedBy).
                    'opsNoteUpdatedBy:id,name',
                    'lastOpsJobItem:id,ops_job_id,status,vend_id,customer_id,stock_action_type,frozen_at,frozen_snapshot',
                    'lastOpsJobItem.opsJob:id,code,date,delivered_by',
                    'lastOpsJobItem.opsJob.deliveredBy:id,name,username',
                    'lastOpsJobItem.vend:id,upcoming_product_mapping_id,product_mapping_id',
                    'lastOpsJobItem.vend.productMapping:id,upcoming_product_mapping_id,name',
                    'lastOpsJobItem.vend.upcomingProductMapping:id,name',
                    'lastOpsJobItem.vend.productMapping.upcomingProductMapping:id,name',
                    'lastSecondOpsJobItem:id,ops_job_id,status,vend_id,customer_id,stock_action_type',
                    'lastSecondOpsJobItem.opsJob:id,code,date,delivered_by',
                    'lastSecondOpsJobItem.opsJob.deliveredBy:id,name,username',
                    'lastSecondOpsJobItem.vend:id,upcoming_product_mapping_id,product_mapping_id',
                    'lastSecondOpsJobItem.vend.productMapping:id,upcoming_product_mapping_id,name',
                    'lastSecondOpsJobItem.vend.upcomingProductMapping:id,name',
                    'lastSecondOpsJobItem.vend.productMapping.upcomingProductMapping:id,name',
                    'nextOpsJobItem:id,ops_job_id,status,vend_id,customer_id,remarks,sequence,stock_action_type',
                    'nextOpsJobItem.opsJob:id,code,date,delivered_by',
                    'nextOpsJobItem.opsJob.deliveredBy:id,name,username',
                    'nextOpsJobItem.vend:id,upcoming_product_mapping_id,product_mapping_id',
                    'nextOpsJobItem.vend.productMapping:id,upcoming_product_mapping_id,name',
                    'nextOpsJobItem.vend.upcomingProductMapping:id,name',
                    'nextOpsJobItem.vend.productMapping.upcomingProductMapping:id,name',
                    'vend.modemUnit',
                    // Modem type — needed for the rich "Modem" block in the
                    // Machine Status column (type name + Reset button gate).
                    'vend.modemUnit.modemType:id,name,is_resetable',
                    'vend.productMapping:id,upcoming_product_mapping_id,name',
                    // Upcoming new mapping for the machine — drives the "New"
                    // badge next to the product mapping name in the machine
                    // column on Vend/CustomerIndex. Mirrors the eager loads the
                    // Ops Job columns already use: an upcoming mapping can sit
                    // either directly on the vend (vend.upcomingProductMapping)
                    // or on its current mapping (productMapping.upcomingProductMapping).
                    'vend.upcomingProductMapping:id,name',
                    'vend.productMapping.upcomingProductMapping:id,name',
                    'vend.deliveryProductMappingVends:id,vend_id,delivery_product_mapping_id',
                    'vend.deliveryProductMappingVends.deliveryProductMapping:id,delivery_platform_operator_id',
                    'vend.deliveryProductMappingVends.deliveryProductMapping.deliveryPlatformOperator:id,delivery_platform_id',
                    'vend.deliveryProductMappingVends.deliveryProductMapping.deliveryPlatformOperator.deliveryPlatform:id,name',
                    // Campaign(s) bound to the machine via its APK settings
                    // (Vend -> apkSettings -> campaigns). Drives the new
                    // "Campaign" badge under the Ref Price line on
                    // Vend/CustomerIndex. Only active campaigns are surfaced
                    // (filtered in VendResource).
                    'vend.apkSettings:id,name',
                    'vend.apkSettings.campaigns:id,name,is_active,start_at,end_at'
                ]);

        // Conditional Joins for performance
        // Restore unconditional joins for required select columns
        $vends->leftJoin('vends', 'vends.customer_id', '=', 'customers.id')
            ->leftJoin('categories', 'categories.id', '=', 'customers.category_id')
            ->leftJoin('category_groups', 'category_groups.id', '=', 'categories.category_group_id')
            ->leftJoin('location_types', 'location_types.id', '=', 'customers.location_type_id')
            ->leftJoin('operators', 'operators.id', '=', 'customers.operator_id')
            ->leftJoin('product_mappings', 'product_mappings.id', '=', 'vends.product_mapping_id')
            ->leftJoin('zones', 'zones.id', '=', 'customers.zone_id')
            ->leftJoin('addresses', function ($query) {
                $query->on('addresses.modelable_id', '=', 'customers.id')
                    ->where('addresses.modelable_type', '=', 'App\Models\Customer')
                    ->where('addresses.type', '=', 2);
            })
            ->leftJoin('vend_configs', 'vend_configs.id', '=', 'vends.vend_config_id')
            ->leftJoin('vend_prefixes', 'vend_prefixes.id', '=', 'vends.vend_prefix_id')
            // Card terminal type (Nayax / Nets / Nets-Auresys / PAX / MLS). Joined so we
            // can SELECT card_terminals.name AS card_terminal_name and expose
            // it on the Customer Index "Card Terminal" badge / filter without
            // an N+1 lazy load.
            ->leftJoin('card_terminals', 'card_terminals.id', '=', 'vends.card_terminal_id');

        $vends = $this->filterVendsDB($vends, $request);
        $vends = $this->filterOperatorDB($vends, 'customers');

        $countQuery = clone $vends;
        $total = $countQuery->count();

        // Pre-fetch operator customer IDs once (~1 ms, indexed) so the ROW_NUMBER
        // sort subqueries can drive into ops_job_items with an explicit IN list.
        // An INNER JOIN to customers lets MySQL pick the wrong join order (full
        // ops_job_items scan → join → filter) — an IN list forces BKA probes via
        // idx_oji_cust_created (customer_id, created_at) and eliminates the ambiguity.
        $sortOperatorIds = collect((array)$request->operators)->filter()->map(fn($v) => (int)$v)->all();
        $sortCustomerIn = '0'; // safe default — matches nothing
        if (($needsLastOpsJobs || $needsLastSecondOpsJobs || $needsLastThirtyDaysStockIn) && $sortOperatorIds) {
            $scopedIds = DB::table('customers')
                ->whereIn('operator_id', $sortOperatorIds)
                ->pluck('id')
                ->map(fn($v) => (int)$v)
                ->all();
            if ($scopedIds) {
                $sortCustomerIn = implode(',', $scopedIds);
            }
        }

            $vends->when($needsVc, function ($query) {
                $query->leftJoin(DB::raw('
                (
                    SELECT vend_id, SUM(amount * qty) AS total_stock_amount, SUM(amount * capacity) AS total_full_load_amount
                    FROM vend_channels
                    WHERE is_active = true
                    AND capacity > 0
                    GROUP BY vend_id
                ) AS vc
            '), 'vc.vend_id', '=', 'vends.id');
            })
                ->when($needsVcCost, function ($query) {
                    // Removed the products bridge join — vend_channels.product_id links
                    // directly to unit_costs.product_id (saves one PK lookup per row).
                    $query->leftJoin(DB::raw('
                (
                    SELECT
                        vend_channels.vend_id,
                        SUM(vend_channels.qty * unit_costs.cost) AS total_stock_cost
                    FROM
                        vend_channels
                    INNER JOIN
                        unit_costs ON vend_channels.product_id = unit_costs.product_id
                    WHERE
                        unit_costs.is_current = true
                    AND vend_channels.is_active = true
                    AND vend_channels.capacity > 0
                    GROUP BY
                        vend_channels.vend_id
                ) AS vc_cost
            '), 'vc_cost.vend_id', '=', 'vends.id');
                })
                ->when($needsVcStock, function ($query) use ($request) {
                    // Replaced ROW_NUMBER() OVER (PARTITION BY product_id ORDER BY id DESC) with
                    // MAX(id) GROUP BY — reads entirely from the covering index on product_limits
                    // (date, product_id, id) without materialising all rows first.
                    $query->leftJoin(DB::raw('
                (
                    SELECT
                        vend_channels.vend_id,
                        SUM(
                            vend_channels.amount *
                            GREATEST(
                                CASE
                                    WHEN product_limits.id AND product_limits.qty < vend_channels.capacity THEN
                                        (product_limits.qty - vend_channels.qty)
                                    ELSE
                                        (vend_channels.capacity - vend_channels.qty)
                                END,
                                0
                            )
                        ) AS actual_stock_in_value,
                        SUM(
                            GREATEST(
                                CASE
                                    WHEN product_limits.id AND product_limits.qty < vend_channels.capacity THEN
                                        (product_limits.qty - vend_channels.qty)
                                    ELSE
                                        (vend_channels.capacity - vend_channels.qty)
                                END,
                                0
                            )
                        ) AS actual_stock_in_qty
                    FROM
                        vend_channels
                    INNER JOIN
                        products ON vend_channels.product_id = products.id
                    LEFT JOIN (
                            SELECT pl.product_id, pl.qty, pl.id
                            FROM product_limits AS pl
                            INNER JOIN (
                                SELECT product_id, MAX(id) AS max_id
                                FROM product_limits
                                WHERE `date` = "' . $request->productAvailableDate . '"
                                GROUP BY product_id
                            ) AS latest_pl ON pl.id = latest_pl.max_id
                        ) AS product_limits ON products.id = product_limits.product_id
                    WHERE
                        products.is_available = true
                    AND vend_channels.is_active = true
                    AND vend_channels.capacity > 0
                    GROUP BY
                        vend_channels.vend_id
                ) AS vc_stock
            '), 'vc_stock.vend_id', '=', 'vends.id');
                })
                ->when($needsLastOpsJobs, function ($query) use ($sortCustomerIn) {
                    // Pre-fetched operator customer IDs are injected as a hard IN list so MySQL
                    // always drives into ops_job_items via idx_oji_cust_created (customer_id,
                    // created_at) using BKA — no join-order ambiguity, no full table scan.
                    $query->leftJoin(DB::raw("(
                SELECT oji.customer_id, oji.cash_amount, oji.acc_total_amount, oji.acc_total_count,
                    SUM(ojic.actual_qty * vc.amount) AS amount,
                    SUM(ojic.actual_qty) AS count
                FROM (
                    SELECT oji_inner.id, oji_inner.customer_id, oji_inner.cash_amount,
                           oji_inner.acc_total_amount, oji_inner.acc_total_count, oji_inner.ops_job_id,
                           ROW_NUMBER() OVER (PARTITION BY oji_inner.customer_id ORDER BY oji_inner.created_at DESC) AS rn
                    FROM ops_job_items oji_inner
                    WHERE oji_inner.customer_id IN ({$sortCustomerIn})
                    AND oji_inner.status >= 3 AND oji_inner.status <> 99
                ) oji
                INNER JOIN ops_job_item_channels ojic ON oji.id = ojic.ops_job_item_id
                INNER JOIN vend_channels vc ON ojic.vend_channel_id = vc.id
                INNER JOIN ops_jobs oj ON oji.ops_job_id = oj.id
                WHERE oji.rn = 1 AND oj.date < CURDATE() + INTERVAL 1 DAY
                GROUP BY oji.customer_id
            ) AS last_ops_jobs"), 'last_ops_jobs.customer_id', '=', 'customers.id');
                })
                ->when($needsLastSecondOpsJobs, function ($query) use ($sortCustomerIn) {
                    // Same IN-list approach as last_ops_jobs, rn = 2.
                    $query->leftJoin(DB::raw("(
                SELECT oji.customer_id, oji.cash_amount, oji.acc_total_amount, oji.acc_total_count,
                    SUM(ojic.actual_qty * vc.amount) AS amount,
                    SUM(ojic.actual_qty) AS count
                FROM (
                    SELECT oji_inner.id, oji_inner.customer_id, oji_inner.cash_amount,
                           oji_inner.acc_total_amount, oji_inner.acc_total_count, oji_inner.ops_job_id,
                           ROW_NUMBER() OVER (PARTITION BY oji_inner.customer_id ORDER BY oji_inner.created_at DESC) AS rn
                    FROM ops_job_items oji_inner
                    WHERE oji_inner.customer_id IN ({$sortCustomerIn})
                    AND oji_inner.status >= 3 AND oji_inner.status <> 99
                ) oji
                INNER JOIN ops_job_item_channels ojic ON oji.id = ojic.ops_job_item_id
                INNER JOIN vend_channels vc ON ojic.vend_channel_id = vc.id
                INNER JOIN ops_jobs oj ON oji.ops_job_id = oj.id
                WHERE oji.rn = 2 AND oj.date < CURDATE() + INTERVAL 1 DAY
                GROUP BY oji.customer_id
            ) AS last_second_ops_jobs"), 'last_second_ops_jobs.customer_id', '=', 'customers.id');
                })
                ->when($needsNextOpsJobs, function ($query) {
                    $query->leftJoin(DB::raw('
                (
                    SELECT oji.customer_id, oji.cash_amount,
                        SUM(ojic.picked_qty * vc.amount) AS amount,
                        SUM(ojic.picked_qty) AS count
                    FROM ops_job_items oji
                    INNER JOIN (
                        SELECT customer_id, MAX(created_at) AS min_created_at
                        FROM ops_job_items
                        WHERE status < 3
                        GROUP BY customer_id
                    ) next_job ON next_job.customer_id = oji.customer_id AND oji.created_at = next_job.min_created_at
                    INNER JOIN ops_job_item_channels ojic ON oji.id = ojic.ops_job_item_id
                    INNER JOIN vend_channels vc ON ojic.vend_channel_id = vc.id
                    INNER JOIN ops_jobs oj ON oji.ops_job_id = oj.id
                    WHERE oji.status < 3 AND oj.date >= CURDATE()
                    GROUP BY oji.customer_id
                    ) AS next_ops_jobs
            '), 'next_ops_jobs.customer_id', '=', 'customers.id');
                })
                ->when($needsLastThirtyDaysStockIn, function ($query) use ($sortCustomerIn) {
                    // Drive from ops_jobs (date filter via idx_oj_date first) then filter
                    // ops_job_items to the operator's customers via the pre-fetched IN list.
                    $query->leftJoin(
                        DB::raw("(
                SELECT SUM(ojic.actual_qty) AS qty,
                       SUM(ojic.actual_qty * vc.amount) AS amount,
                       COUNT(DISTINCT oji.id) AS jobs_done_count,
                       oji.customer_id
                FROM ops_jobs oj
                INNER JOIN ops_job_items oji ON oji.ops_job_id = oj.id
                    AND oji.customer_id IN ({$sortCustomerIn})
                    AND oji.status >= 3 AND oji.status <> 99
                INNER JOIN ops_job_item_channels ojic ON ojic.ops_job_item_id = oji.id
                INNER JOIN vend_channels vc ON ojic.vend_channel_id = vc.id
                WHERE oj.date BETWEEN CURDATE() - INTERVAL 29 DAY AND CURDATE()
                GROUP BY oji.customer_id
            ) AS last_thirty_days_stock_in"),
                        'last_thirty_days_stock_in.customer_id',
                        '=',
                        'customers.id'
                    );
                })
                ->when($needsAccumulatedVendingEarning, function ($query) {
                    // Lifetime-to-date Vending Earning per customer for sorting.
                    // Mirrors the post-query SUM(location_earning_cents) loop below
                    // (and CustomerController::attachAccumulatedVendingEarning) so
                    // the sort order matches the value rendered in the column.
                    // Hits the (customer_id, year_month) unique index on
                    // customer_period_summaries — cheap even at site scale.
                    $through = \Carbon\Carbon::now()->startOfMonth()->toDateString();
                    // Floor — keep in lockstep with
                    // CustomerController::SUMMARY_FLOOR_DATE so this matches the
                    // Customer Summary page. Pre-floor rows are incomplete Excel
                    // backfill and excluded from the lifetime sum.
                    $floor = \App\Http\Controllers\CustomerController::SUMMARY_FLOOR_DATE;
                    $query->leftJoin(DB::raw("(
                SELECT customer_id, SUM(location_earning_cents) AS accumulate_vending_earning_cents
                FROM customer_period_summaries
                WHERE `year_month` >= '{$floor}' AND `year_month` <= '{$through}'
                GROUP BY customer_id
            ) AS accum_ve"), 'accum_ve.customer_id', '=', 'customers.id');
                })
                ->when($needsPwron, function ($query) use ($pwronDate1d, $pwronDate2d, $pwronDate3d) {
                    // Single conditional-aggregation subquery over vend_daily_stats —
                    // hits the (vend_id, date, metric) unique index once for all
                    // three dates instead of a separate join per column. Aliases
                    // match the field names used by the post-query enrichment
                    // loop and the resource, so the SELECT below + filterVendsDB
                    // ORDER BY work without any extra mapping.
                    $query->leftJoin(DB::raw("(
                SELECT vend_id,
                    SUM(CASE WHEN `date` = '{$pwronDate1d}' THEN count ELSE 0 END) AS pwron_1d_count,
                    SUM(CASE WHEN `date` = '{$pwronDate2d}' THEN count ELSE 0 END) AS pwron_2d_count,
                    SUM(CASE WHEN `date` = '{$pwronDate3d}' THEN count ELSE 0 END) AS pwron_3d_count
                FROM vend_daily_stats
                WHERE metric = 'pwron'
                AND `date` IN ('{$pwronDate1d}', '{$pwronDate2d}', '{$pwronDate3d}')
                GROUP BY vend_id
            ) AS vds_pwron"), 'vds_pwron.vend_id', '=', 'vends.id');
                })
                ->when($needsNofoundTxn, function ($query) use ($pwronDate1d, $pwronDate2d, $pwronDate3d) {
                    // Same shape as the PWRON join above, different metric. Reuses
                    // the same three-date window so the screen's three columns line
                    // up across both rows. Aliased as vds_nofound to avoid colliding
                    // with vds_pwron when both sorts are needed (only one runs at
                    // a time today, but cheap insurance).
                    $query->leftJoin(DB::raw("(
                SELECT vend_id,
                    SUM(CASE WHEN `date` = '{$pwronDate1d}' THEN count ELSE 0 END) AS nofound_txn_1d_count,
                    SUM(CASE WHEN `date` = '{$pwronDate2d}' THEN count ELSE 0 END) AS nofound_txn_2d_count,
                    SUM(CASE WHEN `date` = '{$pwronDate3d}' THEN count ELSE 0 END) AS nofound_txn_3d_count
                FROM vend_daily_stats
                WHERE metric = 'nofound_txn'
                AND `date` IN ('{$pwronDate1d}', '{$pwronDate2d}', '{$pwronDate3d}')
                GROUP BY vend_id
            ) AS vds_nofound"), 'vds_nofound.vend_id', '=', 'vends.id');
                });
            $selectColumns = [
                'customers.id AS id',
                'vends.id AS vend_id',
                'vends.t1_lowest_48h',
                'vends.amount_average_day',
                'vends.code',
                'vends.acb_vmc_pa_json',
                'vends.apk_ver_json',
                'vends.balance_percent',
                'vends.serial_num',
                DB::raw("CASE WHEN customers.is_active THEN vends.temp ELSE customers.snap_vend_status_json->>'$.t1' END AS temp"),
                'vends.temp_updated_at',
                'vends.coin_amount',
                'vends.firmware_ver',
                'vends.is_active AS vend_is_active',
                'vends.is_door_open',
                'vends.is_disposed',
                'vends.is_sold',
                'vends.is_mqtt',
                'vends.is_mqtt_active',
                'vends.is_online',
                'vends.is_sensor_normal',
                'vends.is_temp_active',
                'vends.is_temp_error',
                'vends.is_testing',
                DB::raw('DATE(customers.last_invoice_date) AS last_invoice_date'),
                DB::raw('DATE(customers.next_invoice_date) AS next_invoice_date'),
                'customers.next_invoice_driver_id',
                'vends.label_name',
                'vends.last_updated_at',
                'vends.mqtt_last_updated_at',
                'vends.out_of_stock_sku_percent',
                DB::raw('CASE WHEN customers.is_active THEN vends.parameter_json ELSE customers.snap_parameter_json END AS parameter_json'),
                'vends.product_mapping_id',
                'vends.private_key',
                'vends.is_fan_enabled',
                'vends.vend_channel_totals_json',
                DB::raw('CASE WHEN customers.is_active THEN vends.vend_channel_error_logs_json ELSE customers.snap_vend_channel_error_logs_json END AS vend_channel_error_logs_json'),
                'customers.totals_json AS vend_transaction_totals_json',
                'vends.vend_type_id',
                'vends.vend_channels_json',
                'vends.virtual_vend_records_thirty_days_amount_average',
                'customers.id AS customer_id',
                DB::raw("customers.account_manager_json->>'$.name' AS account_manager_name"),
                'customers.begin_date',
                'customers.cms_invoice_history',
                'customers.code AS customer_code',
                // Contract fields — required for Last 30d Vending Earning total
                // (Vending Earning = Gross Earning - Location Fees, where Location
                // Fees are derived from the customer's contract type/values).
                'customers.contract_commission_type',
                'customers.contract_commission_value',
                'customers.contract_commission_value2',
                'customers.contract_ps_term',
                // External Subsidize — drives the "Ext Subsidize" + "Net Loc Fee"
                // lines in the Contract Type column on Vend/CustomerIndex.
                'customers.is_external_subsidize',
                'customers.external_subsidize_amount',
                'customers.frequency_per_week_status',
                'customers.is_active AS is_active',
                'customers.is_active AS customer_is_active',
                'customers.location_type_id',
                'customers.name',
                'customers.name AS customer_name',
                'customers.operator_id',
                'customers.ops_note',
                // Audit pair powers the "edited by X at T" line under the Ops
                // Note textarea on Vend/CustomerIndex. Lives next to ops_note
                // for SELECT readability; see Customer Note for the matching
                // notes_updated_at / notes_updated_by setup.
                'customers.ops_note_updated_at',
                'customers.ops_note_updated_by',
                'customers.person_json',
                'customers.person_id AS person_id',
                'customers.preferred_visit_days_json',
                'customers.selling_price_type',
                'customers.termination_date',
                'customers.contract_until',
                'customers.contract_auto_renewal',
                // Notice Period — string column (e.g. "1 mth", "2 wks"). Shown
                // under Contract End Date on Vend/CustomerIndex's Lifetime Sales
                // cluster so the contract summary reads end-date + notice in one
                // glance, matching the Customer Summary layout.
                'customers.contract_notice_period',
                'customers.virtual_customer_prefix',
                'customers.virtual_customer_code',
                'customers.location_grading_placement',
                'customers.location_grading_access',
                'customers.location_grading_flexibility',
                // Customer Tag + Note column (mirrors Customer Summary).
                // Notes are stored on the customer record so they persist
                // across filter changes here just like they do on Summary.
                'customers.notes',
                'customers.notes_updated_at',
                'customers.notes_updated_by',
                'location_types.name AS location_type_name',
                'product_mappings.name AS product_mapping_name',
                'product_mappings.remarks AS product_mapping_remarks',
                'operators.code AS operator_code',
                'operators.name AS operator_name',
                'addresses.postcode AS postcode',
                'vend_configs.name AS vend_config_name',
                'vend_prefixes.name AS vend_prefix_name',
                'zones.name AS zone_name',
                // Card terminal (user-defined) — drives the "Card Terminal"
                // badge on the customer index page.
                'vends.card_terminal_id',
                'card_terminals.name AS card_terminal_name',
            ];

            if ($needsVc) {
                $selectColumns[] = 'vc.total_full_load_amount';
                $selectColumns[] = 'vc.total_stock_amount';
                $selectColumns[] = DB::raw('
                    (JSON_UNQUOTE(JSON_EXTRACT(customers.totals_json, "$.vend_records_thirty_days_amount_average")) *30 /100)/
                    (vc.total_full_load_amount / 100) AS thirty_days_over_full_load_ratio
                ');
            }

            if ($needsVcCost) {
                $selectColumns[] = 'vc_cost.total_stock_cost';
            }

            if ($needsVcStock) {
                $selectColumns[] = 'vc_stock.actual_stock_in_value';
                $selectColumns[] = 'vc_stock.actual_stock_in_qty';
            }

            if ($needsLastOpsJobs) {
                $selectColumns[] = 'last_ops_jobs.acc_total_amount AS last_ops_job_acc_total_amount';
                $selectColumns[] = 'last_ops_jobs.acc_total_count AS last_ops_job_acc_total_count';
                $selectColumns[] = 'last_ops_jobs.amount AS last_ops_job_amount';
                $selectColumns[] = 'last_ops_jobs.cash_amount AS last_ops_job_cash_amount';
                $selectColumns[] = 'last_ops_jobs.count AS last_ops_job_count';
            }

            if ($needsLastSecondOpsJobs) {
                $selectColumns[] = 'last_second_ops_jobs.acc_total_amount AS last_second_ops_job_acc_total_amount';
                $selectColumns[] = 'last_second_ops_jobs.acc_total_count AS last_second_ops_job_acc_total_count';
                $selectColumns[] = 'last_second_ops_jobs.amount AS last_second_ops_job_amount';
                $selectColumns[] = 'last_second_ops_jobs.cash_amount AS last_second_ops_job_cash_amount';
                $selectColumns[] = 'last_second_ops_jobs.count AS last_second_ops_job_count';
            }

            if ($needsNextOpsJobs) {
                $selectColumns[] = 'next_ops_jobs.amount AS next_ops_job_amount';
                $selectColumns[] = 'next_ops_jobs.cash_amount AS next_ops_job_cash_amount';
                $selectColumns[] = 'next_ops_jobs.count AS next_ops_job_count';
            }

            if ($needsLastThirtyDaysStockIn) {
                $selectColumns[] = 'last_thirty_days_stock_in.amount AS last_thirty_days_stock_in_amount';
                $selectColumns[] = 'last_thirty_days_stock_in.qty AS last_thirty_days_stock_in_qty';
                // # of Job Done L30d — count of completed ops_job_items for this
                // customer in the trailing 30 days. Shares the same subquery as the
                // stock-in sums so the sortable value matches the rendered count.
                $selectColumns[] = 'last_thirty_days_stock_in.jobs_done_count AS last_thirty_days_jobs_done_count';
                $selectColumns[] = DB::raw('
                    (last_thirty_days_stock_in.amount/100 - (JSON_UNQUOTE(JSON_EXTRACT(customers.totals_json, "$.thirty_days_amount"))/100)) AS thirty_days_stock_in_delta_amount
                ');
                $selectColumns[] = DB::raw('
                    ((last_thirty_days_stock_in.amount/100 - (JSON_UNQUOTE(JSON_EXTRACT(customers.totals_json, "$.thirty_days_amount"))/100)))/ (last_thirty_days_stock_in.amount/100) * 100  AS thirty_days_stock_in_delta_percent
                ');
            }

            if ($needsAccumulatedVendingEarning) {
                // Expose the joined subquery's SUM under the same name the
                // PHP post-loop writes back onto each row — filterVendsDB()
                // will ORDER BY this alias for the requested direction.
                $selectColumns[] = DB::raw('COALESCE(accum_ve.accumulate_vending_earning_cents, 0) AS accumulate_vending_earning_cents');
            }

            if ($needsPwron) {
                // Aliases match the post-query enrichment + VendResource fields.
                // COALESCE because vends with no pwron stats today/yesterday
                // should sort as 0, not NULL (matches the post-loop's ?? 0).
                // The PHP enrichment loop further down will overwrite these
                // SQL-derived values with the same numbers so the rendered
                // counts stay in sync — sort here is purely for ORDER BY.
                $selectColumns[] = DB::raw('COALESCE(vds_pwron.pwron_1d_count, 0) AS pwron_1d_count');
                $selectColumns[] = DB::raw('COALESCE(vds_pwron.pwron_2d_count, 0) AS pwron_2d_count');
                $selectColumns[] = DB::raw('COALESCE(vds_pwron.pwron_3d_count, 0) AS pwron_3d_count');
            }

            if ($needsNofoundTxn) {
                // Same pattern as the PWRON block above — see comment there.
                $selectColumns[] = DB::raw('COALESCE(vds_nofound.nofound_txn_1d_count, 0) AS nofound_txn_1d_count');
                $selectColumns[] = DB::raw('COALESCE(vds_nofound.nofound_txn_2d_count, 0) AS nofound_txn_2d_count');
                $selectColumns[] = DB::raw('COALESCE(vds_nofound.nofound_txn_3d_count, 0) AS nofound_txn_3d_count');
            }

            if ($needsThirtyDaysVendingEarning) {
                // L30d Vending Earning = L30d Gross Earning - NET Location Fees,
                // where NET Location Fees = Location Fees - External Subsidize.
                // Location Fees vary by contract_commission_type — mirrors
                // CustomerSummaryAggregator::computeLocationFeeCents so the
                // sort order matches the value rendered in the cell. External
                // Subsidize is a flat monthly amount, treated the same way as
                // the flat monthly rental (added straight back, not prorated).
                //   F      → fee 0
                //   S      → fee = -value*100  (subsidy = income to operator)
                //   R / U  → fee = value*100   (flat rent / utility)
                //   R+U    → fee = value*100 + value2*100  (flat rent + utility)
                //   PS     → fee = (sales_incl_gst / (1+gst%)) * ps_term% * value%
                //   PS+U   → PS + value2*100
                //   PSORU  → MAX(PS, value2*100)
                // PS-family bails out to 0 when sales <= 0 (matches PHP).
                $selectColumns[] = DB::raw('(
                    CAST(JSON_UNQUOTE(JSON_EXTRACT(customers.totals_json, "$.thirty_days_gross_profit")) AS DECIMAL(20,4))
                    - (CASE customers.contract_commission_type
                        WHEN \'F\' THEN 0
                        WHEN \'S\' THEN -ROUND(COALESCE(customers.contract_commission_value, 0) * 100)
                        WHEN \'R\' THEN ROUND(COALESCE(customers.contract_commission_value, 0) * 100)
                        WHEN \'U\' THEN ROUND(COALESCE(customers.contract_commission_value, 0) * 100)
                        WHEN \'R+U\' THEN ROUND(COALESCE(customers.contract_commission_value, 0) * 100)
                            + ROUND(COALESCE(customers.contract_commission_value2, 0) * 100)
                        WHEN \'PS\' THEN
                            CASE WHEN CAST(JSON_UNQUOTE(JSON_EXTRACT(customers.totals_json, "$.thirty_days_amount")) AS DECIMAL(20,4)) <= 0 THEN 0
                            ELSE ROUND(
                                (CAST(JSON_UNQUOTE(JSON_EXTRACT(customers.totals_json, "$.thirty_days_amount")) AS DECIMAL(20,4)) / (1 + COALESCE(operators.gst_vat_rate, 0)/100))
                                * COALESCE(customers.contract_ps_term, 0)/100
                                * COALESCE(customers.contract_commission_value, 0)/100
                            ) END
                        WHEN \'PS+U\' THEN
                            CASE WHEN CAST(JSON_UNQUOTE(JSON_EXTRACT(customers.totals_json, "$.thirty_days_amount")) AS DECIMAL(20,4)) <= 0 THEN 0
                            ELSE ROUND(
                                (CAST(JSON_UNQUOTE(JSON_EXTRACT(customers.totals_json, "$.thirty_days_amount")) AS DECIMAL(20,4)) / (1 + COALESCE(operators.gst_vat_rate, 0)/100))
                                * COALESCE(customers.contract_ps_term, 0)/100
                                * COALESCE(customers.contract_commission_value, 0)/100
                            ) END
                            + ROUND(COALESCE(customers.contract_commission_value2, 0) * 100)
                        WHEN \'PSORU\' THEN GREATEST(
                            CASE WHEN CAST(JSON_UNQUOTE(JSON_EXTRACT(customers.totals_json, "$.thirty_days_amount")) AS DECIMAL(20,4)) <= 0 THEN 0
                            ELSE ROUND(
                                (CAST(JSON_UNQUOTE(JSON_EXTRACT(customers.totals_json, "$.thirty_days_amount")) AS DECIMAL(20,4)) / (1 + COALESCE(operators.gst_vat_rate, 0)/100))
                                * COALESCE(customers.contract_ps_term, 0)/100
                                * COALESCE(customers.contract_commission_value, 0)/100
                            ) END,
                            ROUND(COALESCE(customers.contract_commission_value2, 0) * 100)
                        )
                        ELSE 0
                    END)
                    + (CASE WHEN customers.is_external_subsidize = 1
                            THEN ROUND(COALESCE(customers.external_subsidize_amount, 0) * 100)
                            ELSE 0 END)
                ) AS thirty_days_vending_earning_cents');
            }

            if ($needsExternalSubsidize || $needsNetLocFee) {
                // External Subsidize in cents — gated by the is_external_subsidize
                // toggle; external_subsidize_amount is stored in dollars.
                $extSubCaseSql = '(CASE WHEN customers.is_external_subsidize = 1
                        THEN ROUND(COALESCE(customers.external_subsidize_amount, 0) * 100)
                        ELSE 0 END)';

                if ($needsExternalSubsidize) {
                    $selectColumns[] = DB::raw($extSubCaseSql . ' AS external_subsidize');
                }

                if ($needsNetLocFee) {
                    // Location Fees CASE — mirrors the block embedded in
                    // thirty_days_vending_earning_cents above (which mirrors
                    // CustomerSummaryAggregator::computeLocationFeeCents), so the
                    // sort order matches the value rendered in the cell:
                    //   F → 0; S → -value*100; R/U → value*100; R+U → value*100 + value2*100;
                    //   PS → de-grossed sales * ps_term% * value%; PS+U → PS + value2*100;
                    //   PSORU → MAX(PS, value2*100). PS-family bails to 0 when sales <= 0.
                    $locationFeeCaseSql = '(CASE customers.contract_commission_type
                            WHEN \'F\' THEN 0
                            WHEN \'S\' THEN -ROUND(COALESCE(customers.contract_commission_value, 0) * 100)
                            WHEN \'R\' THEN ROUND(COALESCE(customers.contract_commission_value, 0) * 100)
                            WHEN \'U\' THEN ROUND(COALESCE(customers.contract_commission_value, 0) * 100)
                            WHEN \'R+U\' THEN ROUND(COALESCE(customers.contract_commission_value, 0) * 100)
                                + ROUND(COALESCE(customers.contract_commission_value2, 0) * 100)
                            WHEN \'PS\' THEN
                                CASE WHEN CAST(JSON_UNQUOTE(JSON_EXTRACT(customers.totals_json, "$.thirty_days_amount")) AS DECIMAL(20,4)) <= 0 THEN 0
                                ELSE ROUND(
                                    (CAST(JSON_UNQUOTE(JSON_EXTRACT(customers.totals_json, "$.thirty_days_amount")) AS DECIMAL(20,4)) / (1 + COALESCE(operators.gst_vat_rate, 0)/100))
                                    * COALESCE(customers.contract_ps_term, 0)/100
                                    * COALESCE(customers.contract_commission_value, 0)/100
                                ) END
                            WHEN \'PS+U\' THEN
                                CASE WHEN CAST(JSON_UNQUOTE(JSON_EXTRACT(customers.totals_json, "$.thirty_days_amount")) AS DECIMAL(20,4)) <= 0 THEN 0
                                ELSE ROUND(
                                    (CAST(JSON_UNQUOTE(JSON_EXTRACT(customers.totals_json, "$.thirty_days_amount")) AS DECIMAL(20,4)) / (1 + COALESCE(operators.gst_vat_rate, 0)/100))
                                    * COALESCE(customers.contract_ps_term, 0)/100
                                    * COALESCE(customers.contract_commission_value, 0)/100
                                ) END
                                + ROUND(COALESCE(customers.contract_commission_value2, 0) * 100)
                            WHEN \'PSORU\' THEN GREATEST(
                                CASE WHEN CAST(JSON_UNQUOTE(JSON_EXTRACT(customers.totals_json, "$.thirty_days_amount")) AS DECIMAL(20,4)) <= 0 THEN 0
                                ELSE ROUND(
                                    (CAST(JSON_UNQUOTE(JSON_EXTRACT(customers.totals_json, "$.thirty_days_amount")) AS DECIMAL(20,4)) / (1 + COALESCE(operators.gst_vat_rate, 0)/100))
                                    * COALESCE(customers.contract_ps_term, 0)/100
                                    * COALESCE(customers.contract_commission_value, 0)/100
                                ) END,
                                ROUND(COALESCE(customers.contract_commission_value2, 0) * 100)
                            )
                            ELSE 0
                        END)';
                    $selectColumns[] = DB::raw('(' . $locationFeeCaseSql . ' - ' . $extSubCaseSql . ') AS net_loc_fee');
                }
            }

            $vends->select($selectColumns);

            $page = Paginator::resolveCurrentPage() ?: 1;
            $perPage = $request->numberPerPage === 'All' ? 10000 : ($request->numberPerPage ?: 50);

            $items = $vends->forPage($page, $perPage)->get();

            $vends = new LengthAwarePaginator($items, $total, $perPage, $page, [
                'path' => Paginator::resolveCurrentPath(),
                'query' => $request->query(),
            ]);

            if (!$needsVc || !$needsVcCost || !$needsVcStock || !$needsLastOpsJobs || !$needsLastSecondOpsJobs || !$needsNextOpsJobs || !$needsLastThirtyDaysStockIn) {
                $types = [];
                if (!$needsVc)
                    $types[] = 'vc';
                if (!$needsVcCost)
                    $types[] = 'vc_cost';
                if (!$needsVcStock)
                    $types[] = 'vc_stock';
                if (!$needsLastOpsJobs)
                    $types[] = 'last_ops_jobs';
                if (!$needsLastSecondOpsJobs)
                    $types[] = 'last_second_ops_jobs';
                if (!$needsNextOpsJobs)
                    $types[] = 'next_ops_jobs';
                if (!$needsLastThirtyDaysStockIn)
                    $types[] = 'last_thirty_days_stock_in';

                $this->loadAggregates($vends->getCollection(), $types);
            }

            // Per-vend Location Fees + L30d Vending Earning.
            // Reuses CustomerSummaryAggregator::computeLocationFeeCents so the
            // math matches the Customer Summary page and the totals row above.
            // Pure in-PHP O(per_page) loop — no extra SQL.
            //
            // PS-family math also needs the operator GST rate to de-gross the
            // INCL-GST sales basis before applying PS Term (mirrors the
            // Performance Report Preview popup). Pre-loaded once as a small
            // {operator_id => gst_vat_rate%} lookup so the closures below
            // can resolve it in O(1) without N+1 queries.
            $vendOperatorGstRates = DB::table('operators')
                ->pluck('gst_vat_rate', 'id')
                ->map(fn ($v) => (float) $v)
                ->all();
            foreach ($vends->items() as $vend) {
                $totalsJson = $vend->vend_transaction_totals_json;
                if (!$totalsJson) {
                    $vend->location_fees_cents = null;
                    $vend->thirty_days_vending_earning_cents = null;
                    continue;
                }
                $salesCents = (int) ($totalsJson['thirty_days_amount'] ?? 0);
                $grossEarningCents = (int) ($totalsJson['thirty_days_gross_profit'] ?? 0);
                $gstRatePct = (float) ($vendOperatorGstRates[$vend->operator_id] ?? 0);
                $locationFeeCents = CustomerSummaryAggregator::computeLocationFeeCents(
                    $vend->contract_commission_type,
                    $vend->contract_commission_value !== null ? (float) $vend->contract_commission_value : null,
                    $vend->contract_commission_value2 !== null ? (float) $vend->contract_commission_value2 : null,
                    $vend->contract_ps_term !== null ? (float) $vend->contract_ps_term : null,
                    $salesCents,
                    $grossEarningCents,
                    $gstRatePct
                );
                // External Subsidize (flat monthly amount, cents) reduces the
                // operator's effective location cost, so Vend Earning is NET of
                // it: Vend Earning = Gross − (Location Fees − External Subsidize).
                $extSubCents = ($vend->is_external_subsidize && $vend->external_subsidize_amount !== null)
                    ? (int) round(((float) $vend->external_subsidize_amount) * 100)
                    : 0;
                $vend->location_fees_cents = $locationFeeCents;
                $vend->thirty_days_vending_earning_cents = $grossEarningCents - ($locationFeeCents - $extSubCents);
            }

            // Accumulated (lifetime-to-date) Vending Earning per customer.
            // EFFECTIVE sum of vend earning from customer_period_summaries up to
            // and including the current month: locked months (and segment rows)
            // keep their frozen stored location_earning_cents; UNLOCKED whole-
            // month rows are re-derived LIVE from the customer's current
            // contract. This mirrors CustomerController::attachAccumulatedVendingEarning
            // EXACTLY, so the Ops Dashboard matches the Customer Summary page the
            // moment a contract is edited — a plain SUM(location_earning_cents)
            // lagged behind until the unlocked months were re-snapshotted.
            $customerIds = collect($vends->items())
                ->pluck('customer_id')
                ->filter()
                ->unique()
                ->values()
                ->all();
            if (!empty($customerIds)) {
                $through = Carbon::now()->startOfMonth()->toDateString();
                // Floor — keep in lockstep with CustomerController::SUMMARY_FLOOR_DATE
                // so the displayed Accumulated VendEarning matches the Customer
                // Summary page (and the sort subquery above).
                $floor = \App\Http\Controllers\CustomerController::SUMMARY_FLOOR_DATE;

                // Every monthly row for these customers up to the current month.
                $monthlyRows = DB::table('customer_period_summaries')
                    ->select('customer_id', 'contract_log_id', 'locked_at', 'location_earning_cents', 'sales_cents', 'gross_earning_cents')
                    ->whereIn('customer_id', $customerIds)
                    ->where('year_month', '>=', $floor)
                    ->where('year_month', '<=', $through)
                    ->get();

                // Per-customer current contract + operator GST — used to
                // re-derive the LIVE vend earning for unlocked whole-month rows.
                $contractMap = DB::table('customers')
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

                $accumSums = [];
                foreach ($monthlyRows as $r) {
                    $cid = $r->customer_id;
                    $effectiveEarning = (int) $r->location_earning_cents; // locked / segment / fallback
                    // Segment rows (contract_log_id set) keep their stored
                    // per-segment earning; only unlocked WHOLE-month rows
                    // re-derive live from the customer's current contract.
                    if ($r->locked_at === null && $r->contract_log_id === null) {
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
                    $accumSums[$cid] = ($accumSums[$cid] ?? 0) + $effectiveEarning;
                }

                foreach ($vends->items() as $vend) {
                    $vend->accumulate_vending_earning_cents = (int) ($accumSums[$vend->customer_id] ?? 0);
                }
            } else {
                foreach ($vends->items() as $vend) {
                    $vend->accumulate_vending_earning_cents = 0;
                }
            }

            // PWRON 1d / 2d / 3d + No-Found-in-Txn 1d / 2d / 3d counts per vend —
            // drives the two stacked blocks at the bottom of the Error column on
            // Vend/CustomerIndex.
            //
            // 1d = today, 2d = yesterday, 3d = day before yesterday (3d is the
            // baseline; 1d/2d are colored relative to the next-longer window —
            // see CustomerIndex.vue). Date strings are taken in the app
            // timezone, which matches how IncrementVendDailyStat buckets writes
            // (see VendDataService::processVendData PWRON branch — also uses
            // Carbon::now()->toDateString()), so 1d-aligned data lines up.
            //
            // Single batched query against vend_daily_stats keyed on
            // (vend_id, date, metric) — hits the unique index. We pull BOTH
            // metrics in one round-trip and bucket by metric in PHP, instead
            // of issuing two separate queries.
            $vendIds = collect($vends->items())
                ->pluck('vend_id')
                ->filter()
                ->unique()
                ->values()
                ->all();
            // $pwronDate1d/2d/3d are computed earlier in this method so the
            // sort-leftJoin and this enrichment share the exact same dates.
            $pwronByVend = [];
            $nofoundByVend = [];
            if (!empty($vendIds)) {
                $statsRows = DB::table('vend_daily_stats')
                    ->whereIn('vend_id', $vendIds)
                    ->whereIn('metric', ['pwron', 'nofound_txn'])
                    ->whereIn('date', [$pwronDate1d, $pwronDate2d, $pwronDate3d])
                    ->get(['vend_id', 'date', 'metric', 'count']);
                foreach ($statsRows as $row) {
                    $vid = (int) $row->vend_id;
                    // DB date may come back as 'Y-m-d' or 'Y-m-d 00:00:00'
                    // depending on driver — normalize to a 'Y-m-d' key.
                    $dateKey = substr((string) $row->date, 0, 10);
                    if ($row->metric === 'pwron') {
                        $pwronByVend[$vid][$dateKey] = (int) $row->count;
                    } elseif ($row->metric === 'nofound_txn') {
                        $nofoundByVend[$vid][$dateKey] = (int) $row->count;
                    }
                }
            }
            foreach ($vends->items() as $vend) {
                $vid = (int) ($vend->vend_id ?? 0);
                $vend->pwron_1d_count = (int) ($pwronByVend[$vid][$pwronDate1d] ?? 0);
                $vend->pwron_2d_count = (int) ($pwronByVend[$vid][$pwronDate2d] ?? 0);
                $vend->pwron_3d_count = (int) ($pwronByVend[$vid][$pwronDate3d] ?? 0);
                $vend->nofound_txn_1d_count = (int) ($nofoundByVend[$vid][$pwronDate1d] ?? 0);
                $vend->nofound_txn_2d_count = (int) ($nofoundByVend[$vid][$pwronDate2d] ?? 0);
                $vend->nofound_txn_3d_count = (int) ($nofoundByVend[$vid][$pwronDate3d] ?? 0);
            }

            $totals = [
                'mapApiKey' => $mapApiKey,
                'thirtyDays' => collect($vends->items())
                    ->sum(function ($vend) {
                        return $vend->vend_transaction_totals_json ? $vend->vend_transaction_totals_json['thirty_days_amount'] : 0;
                    }) / 100,
                'thirthyDaysAvg' => collect($vends->items())
                    ->sum(function ($vend) {
                        return $vend->vend_transaction_totals_json ? $vend->vend_transaction_totals_json['vend_records_thirty_days_amount_average'] : 0;
                    }) / 100,
                'thirthyDaysStockIn' => collect($vends->items())
                    ->sum(function ($vend) {
                        return $vend->last_thirty_days_stock_in_amount ? $vend->last_thirty_days_stock_in_amount : 0;
                    }) / 100,
                // Last 30d Gross Earning (excl GST) — sum of per-customer
                // thirty_days_gross_profit (mirrors Customer Summary's
                // `gross_earning_cents`, which is also revenue - unit_cost).
                'thirtyDaysGrossEarning' => collect($vends->items())
                    ->sum(function ($vend) {
                        return $vend->vend_transaction_totals_json
                            ? ($vend->vend_transaction_totals_json['thirty_days_gross_profit'] ?? 0)
                            : 0;
                    }) / 100,
                // Last 30d Vending Earning — Gross Earning - Location Fees per
                // customer, then summed. Reuses the same Location Fee formula
                // the Customer Summary page uses (CustomerSummaryAggregator).
                'thirtyDaysVendingEarning' => collect($vends->items())
                    ->sum(function ($vend) use ($vendOperatorGstRates) {
                        $totalsJson = $vend->vend_transaction_totals_json;
                        if (!$totalsJson) {
                            return 0;
                        }
                        // Use thirty_days_amount (INCL-GST, sums vend_records.total_amount),
                        // NOT thirty_days_revenue (excl-GST). This matches the Customer
                        // Summary page's sales_cents which now sources from
                        // gp_metrics.amount_cents (also incl-GST), so the PS-family
                        // location fee math agrees on both screens.
                        // gross_earning_cents stays excl-GST because gross_profit is
                        // revenue − unit_cost by definition (the column label even
                        // says "Gross Earning (excl GST)"); only the sales basis
                        // for the fee formula needs to be incl-GST.
                        $salesCents = (int) ($totalsJson['thirty_days_amount'] ?? 0);
                        $grossEarningCents = (int) ($totalsJson['thirty_days_gross_profit'] ?? 0);
                        $gstRatePct = (float) ($vendOperatorGstRates[$vend->operator_id] ?? 0);
                        $locationFeeCents = CustomerSummaryAggregator::computeLocationFeeCents(
                            $vend->contract_commission_type,
                            $vend->contract_commission_value !== null ? (float) $vend->contract_commission_value : null,
                            $vend->contract_commission_value2 !== null ? (float) $vend->contract_commission_value2 : null,
                            $vend->contract_ps_term !== null ? (float) $vend->contract_ps_term : null,
                            $salesCents,
                            $grossEarningCents,
                            $gstRatePct
                        );
                        // NET of External Subsidize (flat monthly amount), same
                        // as the per-row L30d Vend Earning above.
                        $extSubCents = ($vend->is_external_subsidize && $vend->external_subsidize_amount !== null)
                            ? (int) round(((float) $vend->external_subsidize_amount) * 100)
                            : 0;
                        return $grossEarningCents - ($locationFeeCents - $extSubCents);
                    }) / 100,
                // Last 30d Location Fees (LocEarning) — sum of per-customer
                // location_fees_cents already computed in the per-vend loop
                // above. Mirrors the Customer/Site Summary "Total Location
                // Fees" card (gross location fee, before External Subsidize).
                'thirtyDaysLocationFees' => collect($vends->items())
                    ->sum(function ($vend) {
                        return $vend->location_fees_cents ?? 0;
                    }) / 100,
                // Last 30d # of Job Done — sum of completed ops_job_items per
                // customer (last_thirty_days_jobs_done_count), attached for
                // every row via the stock-in join/loadAggregates path. Integer
                // count, not currency.
                'thirtyDaysJobsDone' => collect($vends->items())
                    ->sum(function ($vend) {
                        return (int) ($vend->last_thirty_days_jobs_done_count ?? 0);
                    }),
            ];
        } else {
            $vends = new LengthAwarePaginator([], 0, $perPage, 1, [
                'path' => Paginator::resolveCurrentPath(),
                'pageName' => 'page',
            ]);

            $totals = [
                'mapApiKey' => $mapApiKey,
                'thirtyDays' => 0,
                'thirthyDaysAvg' => 0,
                'thirthyDaysStockIn' => 0,
                'thirtyDaysGrossEarning' => 0,
                'thirtyDaysVendingEarning' => 0,
                'thirtyDaysLocationFees' => 0,
                'thirtyDaysJobsDone' => 0,
            ];

            // Initial page load (no Search yet): compute the two aggregate
            // cards over the FULL filtered result set via a slim query —
            // the heavy table itself still only loads on Search.
            //
            // Mirror the Search form's untouched Machine Status default
            // ('Active' — CustomerIndex.vue statusOptions[2]) so the
            // pre-Search cards equal what the first Search returns. Skipped
            // for codes/channel_codes deep-links: the frontend resets Status
            // to All for those and auto-triggers a Search immediately.
            if (!$request->filled('status') && !$request->filled('codes') && !$request->filled('channel_codes')) {
                $request->merge(['status' => 'active']);
            }
            $initialStats = $this->computeCustomerIndexCardStats($request);
        }

        // Cache static dropdown options — same pattern as transactionIndex (line ~2034).
        // These rarely change; cache for 24h. Products and drivers get shorter TTLs.
        $ttl        = 86400; // 24 h — truly static lookups
        $driverTtl  = 1800;  // 30 min — users can be added/modified
        $productTtl = 300;   // 5 min  — is_available toggles more often

        $deliveryPlatformOptions = Cache::remember('delivery_platform_options', $ttl, fn() =>
            DeliveryPlatformResource::collection(DeliveryPlatform::orderBy('name')->get())->resolve()
        );

        $locationTypeOptions = Cache::remember('location_type_options', $ttl, fn() =>
            LocationTypeResource::collection(LocationType::orderBy('sequence')->get())->resolve()
        );

        $operatorOptions = Cache::remember('operator_options_' . auth()->user()->operator_id, $ttl, fn() =>
            OperatorResource::collection(Operator::orderBy('name')->get())->resolve()
        );

        $vendChannelErrors = Cache::remember('vend_channel_errors', $ttl, fn() =>
            VendChannelErrorResource::collection(VendChannelError::orderBy('code')->get())->resolve()
        );

        $vendConfigOptions = Cache::remember('vend_config_options', $ttl, fn() =>
            VendConfigResource::collection(VendConfig::orderBy('name')->get())->resolve()
        );

        $vendContractOptions = Cache::remember('vend_contract_options', $ttl, fn() =>
            VendContractResource::collection(VendContract::orderBy('name')->get())->resolve()
        );

        $vendModelOptions = Cache::remember('vend_model_options', $ttl, fn() =>
            VendModelResource::collection(VendModel::orderBy('name')->get())->resolve()
        );

        // Customer View filter: only list prefixes that still have at least
        // one Active machine — prefixes whose machines are all
        // inactive/testing should not clutter the dropdown.
        $vendPrefixOptions = Cache::remember('vend_prefix_options_active_' . auth()->user()->operator_id, $ttl, fn() =>
            VendPrefixResource::collection(
                VendPrefix::hasActiveVends()->orderBy('name')->get()
            )->resolve()
        );

        $zoneOptions = Cache::remember('zone_options', $ttl, fn() =>
            ZoneResource::collection(Zone::orderBy('name')->get())->resolve()
        );

        $productMappingOptions = Cache::remember('product_mapping_options', $ttl, fn() =>
            ProductMappingResource::collection(ProductMapping::orderBy('name')->get())->resolve()
        );

        // Drivers: cache per-site (not operator-scoped) with a shorter TTL
        $driverOptions = Cache::remember('customer_driver_options', $driverTtl, fn() =>
            UserResource::collection(User::with('roles')->orderBy('name')->get())->resolve()
        );

        // Products: operator-scoped (is_available can toggle), short TTL
        $operatorIds = array_values(array_filter((array) $request->operators));
        sort($operatorIds);
        $productCacheKey = 'customer_product_options_' . implode('_', $operatorIds);
        $productOptions = Cache::remember($productCacheKey, $productTtl, function () use ($request) {
            return ProductResource::collection(
                Product::query()
                    ->with(['thumbnail', 'isAvailableUpdatedBy'])
                    ->when($request->operators, fn($q, $ops) => $q->whereIn('operator_id', $ops))
                    ->select('id', 'code', 'desc', 'name', 'is_available', 'is_available_updated_at', 'is_available_updated_by')
                    ->where('is_active', true)
                    ->where('is_inventory', true)
                    ->orderBy('code')
                    ->get()
            )->resolve();
        });

        return Inertia::render('Vend/CustomerIndex', [
            // Card terminal options (Nayax / Nets / Nets-Auresys / PAX / MLS). Drives the
            // "Card Terminal" filter dropdown. Sourced from the static const
            // so the dropdown stays consistent even if a vend hasn't been
            // bound to any card terminal yet.
            'cardTerminalOptions' => Vend::CARD_TERMINALS,
            'cmsEndpoint' => env('CMS_URL'),
            'constTempError' => VendTemp::TEMPERATURE_ERROR,
            'dayOptions' => Customer::DAYS_MAPPING,
            'deliveryPlatformOptions' => ['data' => $deliveryPlatformOptions],
            'deviceTypes' => Vend::DEVICE_TYPE_MAPPINGS,
            'driverOptions' => ['data' => $driverOptions],
            'frequencyPerWeekOptions' => Customer::FREQUENCY_PER_WEEK_STATUSES_MAPPING,
            // 5-value Customer Status dropdown options for the customers view
            // (Potential / New / Active / Pending / Inactive + "All" sentinel).
            // Same shape as CustomerController::index()'s `statuses` prop.
            'customerStatuses' => [
                ['id' => 'all', 'name' => 'All'],
                ...collect(Customer::STATUSES_MAPPING)->map(fn ($status, $index) => [
                    'id' => $index,
                    'name' => $status,
                ]),
            ],
            'indexType' => $request->indexType,
            'autoLoad' => $shouldAutoload,
            // Pre-Search card stats over ALL filtered rows (null once a
            // Search/autoload has populated the table).
            'initialStats' => $initialStats,
            'locationTypeOptions' => ['data' => $locationTypeOptions],
            'mapApiKey' => $mapApiKey,
            'nextDeliveryDriverOptions' => ['data' => $driverOptions],
            'operatorOptions' => ['data' => $operatorOptions],
            'productOptions' => ['data' => $productOptions],
            'sellingPriceTypeOptions' => SellingPrice::TYPE_MAPPINGS,
            'totals' => $totals,
            'vends' => VendResource::collection($vends),
            'vendChannelErrors' => ['data' => $vendChannelErrors],
            'productMappingOptions' => ['data' => $productMappingOptions],
            'vendConfigOptions' => ['data' => $vendConfigOptions],
            'vendContractOptions' => ['data' => $vendContractOptions],
            'vendModelOptions' => ['data' => $vendModelOptions],
            'vendPrefixOptions' => ['data' => $vendPrefixOptions],
            'zoneOptions' => ['data' => $zoneOptions],
        ]);
    }

    public function deleteLatestExportTransaction($id)
    {
        $exportJob = ExportJob::findOrFail($id);

        if ($exportJob->attachment) {
            Storage::disk('digitaloceanspaces')->delete($exportJob->attachment->local_url);
            $exportJob->attachment->delete();
        }

        $exportJob->delete();

        return redirect()->back();
    }

    public function searchVendCode($vendCode)
    {
        $vends = Vend::query()
            ->with(['operator', 'customer'])
            ->where('vends.code', 'LIKE', "{$vendCode}%")
            ->get();

        return $vends;
    }

    public function restartAPK($id)
    {
        $vend = Vend::findOrFail($id);
        $fid = 1;
        $content = base64_encode(json_encode([
            'Type' => 'REBOOTANDROID',
            'time' => Carbon::now()->timestamp,
            'action' => '',
            'mid' => $vend->code,
        ]));
        $contentLength = strlen($content);
        $key = $vend && $vend->private_key ? $vend->private_key : '123456789110138A';
        $md5 = md5($fid . ',' . $contentLength . ',' . $content . $key);

        PublishMqtt::dispatch('CM' . $vend->code, $fid . ',' . $contentLength . ',' . $content . ',' . $md5)->onQueue('high');
        // $this->mqttService->publish('CM'.$vend->code, $fid.','.$contentLength.','.$content.','.$md5);

        return redirect()->back();
    }

    public function restartVMC($id)
    {
        $vend = Vend::findOrFail($id);
        $fid = 1;
        $content = base64_encode(json_encode([
            'Type' => 'RESET',
            'time' => Carbon::now()->timestamp,
            'action' => '',
            'mid' => $vend->code,
        ]));
        $contentLength = strlen($content);
        $key = $vend && $vend->private_key ? $vend->private_key : '123456789110138A';
        $md5 = md5($fid . ',' . $contentLength . ',' . $content . $key);

        PublishMqtt::dispatch('CM' . $vend->code, $fid . ',' . $contentLength . ',' . $content . ',' . $md5)->onQueue('high');
        // $this->mqttService->publish('CM'.$vend->code, $fid.','.$contentLength.','.$content.','.$md5);

        return redirect()->back();
    }

    public function syncApkSettings($id)
    {
        $vend = Vend::findOrFail($id);

        $payload = [
            'Type' => 'TYPESYNCSETTINGSPARAM',
            'time' => Carbon::now()->timestamp,
            'action' => '',
            'mid' => $vend->code,
        ];

        $this->vendJobService->dispatch($vend, 'TYPESYNCSETTINGSPARAM', $payload, function ($payload, $vend) {
            $fid = 1;
            $content = base64_encode(json_encode($payload));
            $contentLength = strlen($content);
            $key = $vend && $vend->private_key ? $vend->private_key : '123456789110138A';
            $md5 = md5($fid . ',' . $contentLength . ',' . $content . $key);

            return $fid . ',' . $contentLength . ',' . $content . ',' . $md5;
        });

        return redirect()->back();
    }

    public function syncVendChannels($id)
    {
        $vend = Vend::findOrFail($id);
        $fid = 1;
        $content = base64_encode(json_encode([
            'Type' => 'TYPESYNCAPICHANNELSLOTLIST',
            'time' => Carbon::now()->timestamp,
            'action' => '',
            'mid' => $vend->code,
        ]));
        $contentLength = strlen($content);
        $key = $vend && $vend->private_key ? $vend->private_key : '123456789110138A';
        $md5 = md5($fid . ',' . $contentLength . ',' . $content . $key);

        PublishMqtt::dispatch('CM' . $vend->code, $fid . ',' . $contentLength . ',' . $content . ',' . $md5)->onQueue('high');

        return redirect()->back();
    }

    public function triggerLogUpload(Request $request, $id)
    {
        $request->merge([
            'trigger_log_date' => isset($request->trigger_log_date) ? $request->trigger_log_date : Carbon::today()->toDateString(),
        ]);

        $vend = Vend::findOrFail($id);
        $fid = 1;
        $content = base64_encode(json_encode([
            'Type' => 'UPDATELOG',
            'time' => Carbon::now()->timestamp,
            'date' => $request->trigger_log_date,
            'action' => '',
            'mid' => $vend->code,
        ]));
        $contentLength = strlen($content);
        $key = $vend && $vend->private_key ? $vend->private_key : '123456789110138A';
        $md5 = md5($fid . ',' . $contentLength . ',' . $content . $key);

        PublishMqtt::dispatch('CM' . $vend->code, $fid . ',' . $contentLength . ',' . $content . ',' . $md5)->onQueue('high');
        // $this->mqttService->publish('CM'.$vend->code, $fid.','.$contentLength.','.$content.','.$md5);

        return redirect()->back();
    }

    public function temp(Request $request, $vendId, $type)
    {
        // dd($request->all());
        $duration = 2;
        if ($request->duration) {
            $duration = $request->duration;
        }

        $startDate = $request->durationType == 'day' || !$request->durationType ? Carbon::now()->setTimezone($this->getUserTimezone())->subDays($duration) : Carbon::now()->setTimezone($this->getUserTimezone())->subHours($duration);
        $endDate = Carbon::now()->setTimezone($this->getUserTimezone());
        if ($request->datetime_from) {
            $startDate = Carbon::parse($request->datetime_from)->setTimezone($this->getUserTimezone());
        }
        if ($request->datetime_to) {
            $endDate = Carbon::parse($request->datetime_to)->setTimezone($this->getUserTimezone());
        }

        $request->merge(['types' => empty($request->types) ? [1, 2] : $request->types]);
        // $request->types = empty($request->types) ? [1, 2] : $request->types;

        // dd($request->types);
        $typeName = 'Temp ' . $type;

        $vend = DB::table('vends')
            ->leftJoin('customers', 'customers.id', '=', 'vends.customer_id')
            ->where('vends.id', $vendId)
            ->select(
                'vends.id',
                'vends.code',
                'vends.name',
                'vends.last_updated_at',
                'vends.mqtt_last_updated_at',
                'vends.is_active',
                'vends.is_mqtt',
                'vends.is_mqtt_active',
                'vends.is_online',
                'vends.is_testing',
                'vends.is_testing',
                'vends.is_fan_enabled',
                'customers.id AS customer_id',
                'customers.virtual_customer_prefix',
                'customers.virtual_customer_code',
                DB::raw('CASE WHEN customers.person_id THEN CONCAT(IFNULL(customers.virtual_customer_code, \'\'), " (", IFNULL(customers.virtual_customer_prefix, \'\'), ")") ELSE customers.code END AS customer_code'),
                'customers.name AS customer_name',
                'parameter_json',
                'temp',
                'vend_prefixes.name AS vend_prefix_name',
            )
            ->leftJoin('vend_prefixes', 'vend_prefixes.id', '=', 'vends.vend_prefix_id')
            ->first();

        $vendTemps = DB::table('vend_temps')
            ->where('vend_id', $vendId)
            ->whereIn('type', $request->types)
            ->where('value', '!=', VendTemp::TEMPERATURE_ERROR)
            ->where('vend_temps.created_at', '>=', $startDate)
            ->where('vend_temps.created_at', '<=', $endDate)
            ->select(
                'created_at',
                'type',
                'value',
            )
            ->orderBy('created_at', 'asc')
            ->get();

        $fans = [];
        if ($request->fans) {
            $fans = array_merge($fans, $request->fans);
        } else {
            $fans = [1];
        }

        $vendFans = DB::table('vend_fans')
            ->where('vend_id', $vendId)
            ->whereIn('type', $fans)
            ->where('vend_fans.created_at', '>=', $startDate)
            ->where('vend_fans.created_at', '<=', $endDate)
            ->select(
                'created_at',
                'type',
                'value',
            )
            ->orderBy('created_at', 'asc')
            ->get();

        $vendAlertLogs = VendLog::where('vend_id', $vendId)
            ->whereIn('event', ['machine_health_alert', 'machine_health_alert_dismissed'])
            ->where('occurred_at', '>=', $startDate)
            ->where('occurred_at', '<=', $endDate)
            ->select('id', 'event', 'subject', 'context', 'occurred_at')
            ->orderBy('occurred_at', 'asc')
            ->get()
            ->map(function ($log) {
                return [
                    'event' => $log->event,
                    'subject' => $log->subject,
                    'alert_type' => $log->context['alert_type'] ?? (($log->context['type'] ?? null) === 'connectivity' ? 'connectivity' : null),
                    'bucket' => $log->context['bucket'] ?? null,
                    'severity' => $log->context['severity'] ?? null,
                    'occurred_at' => $log->occurred_at->toIso8601String(),
                    'context' => $log->context,
                ];
            });

        $vendOptions = Cache::remember('vend_options_temp_index', 3600, function () {
            return DB::table('vends')
                ->leftJoin('customers', 'customers.id', '=', 'vends.customer_id')
                ->select(
                    'vends.id',
                    'vends.code',
                    'customers.name as customer_name',
                    'customers.virtual_customer_prefix',
                    'customers.virtual_customer_code',
                    DB::raw('CASE WHEN customers.id IS NOT NULL THEN customers.id + ' . Customer::RUNNING_NUMBER_INIT . ' END AS customer_ref_id')
                )
                ->orderBy('vends.code')
                ->get()
                ->map(function ($vendOption) {
                    return [
                        'id' => $vendOption->id,
                        'code' => $vendOption->code,
                        'customer_name' => $vendOption->customer_name,
                        'customer_ref_id' => $vendOption->customer_ref_id,
                        'virtual_customer_prefix' => $vendOption->virtual_customer_prefix,
                        'virtual_customer_code' => $vendOption->virtual_customer_code,
                    ];
                });
        });

        return Inertia::render('Vend/Temp', [
            'duration' => $duration,
            'type' => [
                'name' => $typeName,
                'value' => $type,
            ],
            'types' => $request->types,
            'fans' => $fans,
            'vendObj' => VendDBResource::make($vend),
            'vendTempsObj' => VendTempResource::collection($vendTemps),
            'vendFansObj' => VendFanResource::collection($vendFans),
            'vendAlertLogsObj' => $vendAlertLogs,
            'startDate' => $startDate->format('D M d Y H:i:s'),
            'endDate' => $endDate->format('D M d Y H:i:s'),
            'startDateString' => $startDate->format('y-m-d H:i'),
            'endDateString' => $endDate->format('y-m-d H:i'),
            'tempError' => VendTemp::TEMPERATURE_ERROR,
            'vendOptions' => $vendOptions,
        ]);
    }

    public function exportTempExcel(Request $request, $vendId, $type)
    {
        $duration = 3;
        if ($request->duration) {
            $duration = $request->duration;
        }
        $vend = DB::table('vends')
            ->leftJoin('customers', 'customers.id', '=', 'vends.customer_id')
            ->where('vends.id', $vendId)
            ->select(
                'vends.id',
                'vends.code',
                'vends.name',
                'customers.code AS customer_code',
                'customers.name AS customer_name',
                'parameter_json',
                'temp',

            )
            ->first();

        $startDate = $request->durationType == 'day' || !$request->durationType ? Carbon::now()->setTimezone($this->getUserTimezone())->subDays($duration) : Carbon::now()->setTimezone($this->getUserTimezone())->subHours($duration);
        $endDate = Carbon::now()->setTimezone($this->getUserTimezone());
        if ($request->datetime_from) {
            $startDate = Carbon::parse($request->datetime_from)->setTimezone($this->getUserTimezone());
        }
        if ($request->datetime_to) {
            $endDate = Carbon::parse($request->datetime_to)->setTimezone($this->getUserTimezone());
        }
        $request->types = empty($request->types) ? [1] : $request->types;

        $typeName = 'Temp ' . $type;

        $vendTemps = DB::table('vend_temps')
            ->leftJoin('vends', 'vends.id', '=', 'vend_temps.vend_id')
            ->where('vend_id', $vendId)
            ->whereIn('type', $request->types)
            ->where('value', '!=', VendTemp::TEMPERATURE_ERROR)
            ->where('vend_temps.created_at', '>=', $startDate)
            ->where('vend_temps.created_at', '<=', $endDate)
            ->select(
                'vends.code AS vend_code',
                'vend_temps.created_at',
                'type',
                'value',
            )
            ->get();

        return (new FastExcel($vendTemps))->download('Vend_Temps_' . Carbon::now()->toDateTimeString() . '.xlsx', function ($vendTemp) {
            return [
                'Machine ID' => $vendTemp->vend_code,
                'Date Time' => Carbon::parse($vendTemp->created_at)->toDateTimeString(),
                'Temp' => $vendTemp->value / 10,
                'Type' => 'T' . $vendTemp->type,
            ];
        });
    }

    public function getChannelsErrorRate($id)
    {
        $vendChannels = VendChannel::query()
            ->with([
                'vendTransactions' => function ($query) {
                    $query
                        ->where('transaction_datetime', '>=', Carbon::today()->subDays(6))
                        ->groupBy('vend_channel_id')
                        ->select(
                            'id',
                            'vend_channel_id',
                            DB::raw(
                                'COUNT(id) as seven_days_total_count'
                            ),
                            DB::raw(
                                'COUNT(
                                    CASE
                                        WHEN vend_channel_error_id IS NULL THEN NULL
                                        WHEN vend_channel_error_id IN (1, 5) THEN NULL
                                        ELSE 1
                                    END
                                ) as seven_days_error_count'
                            )
                        )
                        ->selectRaw('COUNT(CASE WHEN transaction_datetime >= ? THEN id ELSE NULL END) as two_days_total_count', [Carbon::today()->subDays(1)])
                        ->selectRaw('COUNT(CASE WHEN transaction_datetime >= ? AND vend_channel_error_id IS NOT NULL AND vend_channel_error_id NOT IN (1, 5) THEN 1 END) as two_days_error_count', [Carbon::today()->subDays(1)]);
                },
            ])
            ->where('vend_id', $id)
            ->where('is_active', true)
            ->select(
                'id'
            )
            ->get();
        return $vendChannels;
    }


    public function getAllDCVends(Request $request)
    {
        if (!$request->operatorCode) {
            throw new \Exception('Operator code is required');
        }

        // Real-time data - no caching (operators need immediate visibility of vend assignments)
        $customers = Customer::query()
            ->with([
                'deliveryAddress',
                'photos',
                'vend' => function ($query) use ($request) {
                    $query
                        ->with([
                            'vendChannels',
                            'vendChannels.product.thumbnail',
                        ]);
                },
            ])
            ->leftJoin('vends', 'vends.customer_id', '=', 'customers.id')
            ->whereHas('operator', function ($query) use ($request) {
                $query->where('code', $request->operatorCode)
                    ->where('is_dcvend', true);
            })
            ->where('customers.is_active', true)
            ->orderByRaw("CASE WHEN vends.id IS NOT NULL THEN 0 ELSE 1 END") // Sort by customers with vends first
            ->orderBy('vends.code') // Sort by vend code
            ->select('customers.id', 'customers.name', 'is_restricted_access') // Ensure only customer columns are selected
            ->distinct()
            ->get();

        return DCVendCustomerResource::collection($customers);

        // return DCVendResource::collection($vends);
    }

    // public function getVendAllChannelThumbnails($vendCode)
    // {
    //     $vendChannels = VendChannel::query()
    //         ->with([
    //             'product.thumbnail',
    //             'product.category',
    //             'product.tagBindings.tag',
    //             'vend.productMapping',
    //         ])
    //         ->whereHas('vend', fn($q) => $q->where('code', $vendCode))
    //         ->where('is_active', true)
    //         ->where('capacity', '>', 0)
    //         ->orderBy('code', 'asc')
    //         ->get();

    //     if ($vendChannels->isEmpty()) {
    //         return response()->json([], 200);
    //     }

    //     $productMappingItems = ProductMappingItem::where('product_mapping_id', $vendChannels->first()->vend->product_mapping_id ?? null)
    //         ->whereIn('channel_code', $vendChannels->pluck('code')->map(fn($c) => (int)$c))
    //         ->get()
    //         ->keyBy('channel_code');

    //     $dataArr = [];
    //     foreach ($vendChannels as $index => $vendChannel) {
    //         $productMappingItem = $productMappingItems->get((int)$vendChannel->code);

    //         $serverPrice = null;
    //         if ($productMappingItem && $vendChannel->vend->server_price_type) {
    //             $sellingPrice = SellingPrice::where('type', $vendChannel->vend->server_price_type)
    //                 ->where('product_id', $productMappingItem->product_id)
    //                 ->first();
    //             $serverPrice = $sellingPrice?->amount;
    //         }

    //         $data = [
    //             'vend_code' => $vendChannel->vend->code,
    //             'channel_code' => $vendChannel->code,
    //             'product_id' => $vendChannel->product?->id,
    //             'product_code' => $vendChannel->product?->code,
    //             'product_name' => $vendChannel->product?->name,
    //             'product_sub_category' => $vendChannel->product?->category?->name,
    //             'thumbnail' => $vendChannel->product?->thumbnail?->full_url,
    //             'server_price' => $serverPrice,
    //             'labels' => $vendChannel->product?->tagBindings->map(fn($tb) => ['name' => $tb->tag?->name])->toArray() ?? [],
    //         ];

    //         if ($vendChannel->product?->translated_names_json) {
    //             foreach ($vendChannel->product->translated_names_json as $lang => $value) {
    //                 $data['product_name_' . $value['id']] = $value['name'];
    //             }
    //         }

    //         $dataArr[] = $data;
    //     }

    //     return response()->json($dataArr, 200);
    // }
    public function getVendAllChannelThumbnails($vendCode)
    {
        // Real-time data - no caching (product mappings and channel quantities need to be current)
        $vend = Vend::with([
            'vendChannels.product.thumbnail',
            'vendChannels.product.category',
            'vendChannels.product.tagBindings.tag',
            'productMapping.productMappingItems',
        ])->where('code', $vendCode)->first();

        if (!$vend || $vend->vendChannels->isEmpty()) {
            return response()->json([], 200);
        }

        $productMappingItems = $vend->productMapping?->productMappingItems->keyBy('channel_code');

        if (!$productMappingItems) {
            return response()->json([], 200);
        }

        $serverPriceType = $vend->server_price_type;

        // Get product IDs from mapping items
        $productIds = $productMappingItems->pluck('product_id')->unique()->toArray();

        // Get relevant selling prices if needed
        $sellingPrices = [];
        if ($serverPriceType) {
            $sellingPrices = SellingPrice::where('type', $serverPriceType)
                ->whereIn('product_id', $productIds)
                ->get()
                ->keyBy('product_id');
        }

        // Sort vendChannels by productMappingItem.sequence, then code
        $sortedVendChannels = $vend->vendChannels->sort(function ($a, $b) use ($productMappingItems) {
            $seqA = $productMappingItems->get((int) $a->code)?->sequence;
            $seqB = $productMappingItems->get((int) $b->code)?->sequence;

            $hasSeqA = $seqA !== null;
            $hasSeqB = $seqB !== null;

            if ($hasSeqA && !$hasSeqB)
                return -1;
            if (!$hasSeqA && $hasSeqB)
                return 1;

            if ($seqA !== $seqB)
                return $seqA <=> $seqB;

            return (int) $a->code <=> (int) $b->code;
        })->values();

        $dataArr = [];

        foreach ($sortedVendChannels as $vendChannel) {
            $product = $vendChannel->product;
            $productMappingItem = $productMappingItems->get((int) $vendChannel->code);
            $serverPrice = $productMappingItem ? ($sellingPrices[$productMappingItem->product_id]->amount ?? null) : null;

            $data = [
                'vend_code' => $vend->code,
                'channel_code' => $vendChannel->code,
                'product_id' => $product?->id,
                'product_code' => $product?->code,
                'product_name' => $product?->name,
                'product_desc' => $product?->desc,
                'product_is_halal' => $product?->is_halal,
                'product_is_healthier_choice' => $product?->is_healthier_choice,
                'product_nutri_grade' => $product?->nutri_grade,
                'product_sub_category' => $product?->category?->name,
                'product_volumn_weight' => $product?->measurement_value,
                'sequence' => $productMappingItem?->sequence,
                'thumbnail' => $product?->thumbnail?->full_url,
                'server_price' => $serverPrice,
                'labels' => $product?->tagBindings->map(fn($tb) => [
                    'id' => $tb->tag?->id,
                    'name' => $tb->tag?->name
                ])->toArray() ?? [],
            ];

            if ($product?->translated_names_json) {
                foreach ($product->translated_names_json as $lang => $value) {
                    $data['product_name_' . $value['id']] = $value['name'];
                }
            }

            $dataArr[] = $data;
        }

        return response()->json($dataArr, 200);
    }

    /**
     * Returns the configured menu (product mapping) for a vend.
     *
     * Unlike getVendAllChannelThumbnails(), this is built from the product
     * mapping items directly, so it reflects the intended planogram regardless
     * of physical channel state (is_active / capacity / qty are NOT considered).
     */
    public function getVendMenu($vendCode)
    {
        // Real-time data - no caching (product mappings need to be current)
        $vend = Vend::with([
            'productMapping.productMappingItems.product.thumbnail',
            'productMapping.productMappingItems.product.category',
            'productMapping.productMappingItems.product.tagBindings.tag',
        ])->where('code', $vendCode)->first();

        if (!$vend) {
            return response()->json([], 200);
        }

        $productMappingItems = $vend->productMapping?->productMappingItems;

        if (!$productMappingItems || $productMappingItems->isEmpty()) {
            return response()->json([], 200);
        }

        $serverPriceType = $vend->server_price_type;

        // Get product IDs from mapping items
        $productIds = $productMappingItems->pluck('product_id')->unique()->toArray();

        // Get relevant selling prices if needed (same logic as thumbnails)
        $sellingPrices = [];
        if ($serverPriceType) {
            $sellingPrices = SellingPrice::where('type', $serverPriceType)
                ->whereIn('product_id', $productIds)
                ->get()
                ->keyBy('product_id');
        }

        // Sort mapping items by sequence (nulls last), then channel_code
        $sortedItems = $productMappingItems->sort(function ($a, $b) {
            $hasSeqA = $a->sequence !== null;
            $hasSeqB = $b->sequence !== null;

            if ($hasSeqA && !$hasSeqB)
                return -1;
            if (!$hasSeqA && $hasSeqB)
                return 1;

            if ($a->sequence !== $b->sequence)
                return $a->sequence <=> $b->sequence;

            return (int) $a->channel_code <=> (int) $b->channel_code;
        })->values();

        $dataArr = [];

        foreach ($sortedItems as $item) {
            $product = $item->product;
            $serverPrice = $sellingPrices[$item->product_id]->amount ?? null;

            $data = [
                'vend_code' => $vend->code,
                'channel_code' => $item->channel_code,
                'product_id' => $product?->id,
                'product_code' => $product?->code,
                'product_name' => $product?->name,
                'product_desc' => $product?->desc,
                'product_is_halal' => $product?->is_halal,
                'product_is_healthier_choice' => $product?->is_healthier_choice,
                'product_nutri_grade' => $product?->nutri_grade,
                'product_sub_category' => $product?->category?->name,
                'product_volumn_weight' => $product?->measurement_value,
                'sequence' => $item->sequence,
                'thumbnail' => $product?->thumbnail?->full_url,
                'server_price' => $serverPrice,
                'labels' => $product?->tagBindings->map(fn($tb) => [
                    'id' => $tb->tag?->id,
                    'name' => $tb->tag?->name
                ])->toArray() ?? [],
            ];

            if ($product?->translated_names_json) {
                foreach ($product->translated_names_json as $lang => $value) {
                    $data['product_name_' . $value['id']] = $value['name'];
                }
            }

            $dataArr[] = $data;
        }

        return response()->json($dataArr, 200);
    }

    public function getVendBannerImage($vendCode)
    {
        $imageArray = [];
        // Eager load apkSettings and images to avoid N+1 queries
        $vend = Vend::with(['apkSettings.images'])
            ->where('code', $vendCode)
            ->first();

        if ($vend && $vend->apkSettings->isNotEmpty()) { // Ensure vend exists and apkSettings is not empty
            $apkSetting = $vend->apkSettings->first(); // Use first() to avoid undefined index error

            if (!empty($apkSetting->images)) {
                foreach ($apkSetting->images as $image) {
                    $imageArray[] = [
                        'name' => $image->name,
                        'ext' => pathinfo($image->full_url, PATHINFO_EXTENSION),
                        'url' => $image->full_url,
                    ];
                }
            }
        }

        return response([
            'pictures' => $imageArray,
        ], 200);
    }

    public function getVendBannerVideo($vendCode)
    {
        $videoArray = [];
        $vend = Vend::where('code', $vendCode)->first();

        if ($vend && $vend->apkSettings->isNotEmpty()) {
            $apkSetting = $vend->apkSettings->first();

            if (!empty($apkSetting->videos)) {
                foreach ($apkSetting->videos as $video) {
                    $videoArray[] = [
                        'name' => $video->name,
                        'ext' => pathinfo($video->full_url, PATHINFO_EXTENSION),
                        'url' => $video->full_url,
                    ];
                }
            }
        }

        return response([
            'videos' => $videoArray,
        ], 200);
    }

    public function getVendCampaignImage($vendCode)
    {
        $imageArray = [];
        $vend = Vend::where('code', $vendCode)->first();

        if ($vend && $vend->apkSettings->isNotEmpty()) {
            $apkSetting = $vend->apkSettings->first();

            if (!empty($apkSetting->campaignImages)) {
                foreach ($apkSetting->campaignImages as $image) {
                    $imageArray[] = [
                        'name' => $image->name,
                        'ext' => pathinfo($image->full_url, PATHINFO_EXTENSION),
                        'url' => $image->full_url,
                    ];
                }
            }
        }

        return response([
            'pictures' => $imageArray,
        ], 200);
    }

    public function getVendCampaignVideo($vendCode)
    {
        $videoArray = [];
        $vend = Vend::where('code', $vendCode)->first();

        if ($vend && $vend->apkSettings->isNotEmpty()) {
            $apkSetting = $vend->apkSettings->first();

            if (!empty($apkSetting->campaignVideos)) {
                foreach ($apkSetting->campaignVideos as $video) {
                    $videoArray[] = [
                        'name' => $video->name,
                        'ext' => pathinfo($video->full_url, PATHINFO_EXTENSION),
                        'url' => $video->full_url,
                    ];
                }
            }
        }

        return response([
            'videos' => $videoArray,
        ], 200);
    }


    public function getVendChannelThumnail($vendCode, $vendChannelCode)
    {
        $vendChannel = VendChannel::query()
            ->with('product.thumbnail')
            ->whereHas('vend', function ($query) use ($vendCode) {
                $query->where('code', $vendCode);
            })
            ->where('code', $vendChannelCode)
            ->first();

        if ($vendChannel) {
            if ($vendChannel->product && $vendChannel->product->thumbnail) {

                $thumbnail = Image::read(file_get_contents($vendChannel->product->thumbnail->full_url));
                $thumbnail->resize(300, 300);
                return response($thumbnail->toJpeg(), 200)
                    ->header('Content-Type', 'image/jpeg');
            }
        }

        // return response()->json([
        //     'url' => null,
        // ], 400);
        return false;
    }

    public function getVendParameters($vendCode, $apkVer = null)
    {
        // Real-time data - no caching (campaign settings need to be current)
        $campaignItems = [];
        $vend = Vend::where('code', $vendCode)->firstOrFail();
        $apkSetting = $vend->apkSettings()
            ->with([
                'campaignItems.tagBindings.tag',
                'campaigns.labelsX',
                'campaigns.labelsY',
            ])
            ->first();

        if (!$apkSetting) {
            abort(response([
                'error_code' => 400,
                'error_message' => 'Parameters not found',
            ], 400));
        }

        $isGrabEnabled = $vend->is_enable_grab_collection;

        if ($apkSetting->campaignItems) {
            $campaignItems = $apkSetting->campaignItems;
        }

        $campaignBindings = collect($apkSetting->campaigns)->filter(function ($campaign) {
            return (bool) ($campaign->is_active ?? false);
        });

        $settingsParams = $apkSetting->settings_parameter_json ?? [];
        if (!is_array($settingsParams)) {
            $settingsParams = (array) $settingsParams;
        }

        $data = [
            ...$settingsParams,
            'isGrabEnabled' => $isGrabEnabled ? "true" : "false",
            'companyUrl' => $settingsParams['company_url'] ?? null,
            'companyAddress' => $settingsParams['company_address'] ?? null,
            'companyName' => $settingsParams['companyName'] ?? null,
            'refundUrl' => $settingsParams['refund_url'] ?? null,
            'promoLabelItems' => $campaignItems->map(function ($campaignItem) {
                return [
                    'id' => isset($campaignItem->tagBindings[0]) ? $campaignItem->tagBindings[0]->tag->id : null,
                    'label' => isset($campaignItem->tagBindings[0]) ? $campaignItem->tagBindings[0]->tag->name : null,
                    'bundle_qty' => $campaignItem->qty,
                    'promo_type' => CampaignItem::PROMO_TYPE_MAPPINGS[$campaignItem->promo_type],
                    'value' => $campaignItem->value,
                ];
            }),
            'campaigns' => $campaignBindings->map(function ($campaign) {
                return [
                    'bundle_qty' => $campaign->bundle_qty,
                    'id' => $campaign->id,
                    'label' => $campaign->name ?? $campaign->slug ?? (string) $campaign->id,
                    'labels_x' => collect($campaign->labelsX)->pluck('id')->values()->all(),
                    'labels_y' => collect($campaign->labelsY)->pluck('id')->values()->all(),
                    'promo_type' => $campaign->promo_type === 'Item' ? Campaign::TYPE_ITEM : $campaign->promo_type,
                    'slug' => $campaign->slug ?? $campaign->name ?? null,
                    'value' => $campaign->value,
                    'start_date' => optional($campaign->start_at)?->toDateString(),
                    'end_date' => optional($campaign->end_at)?->toDateString(),
                    'min_basket_value' => $campaign->min_basket_value,
                    'max_discount_value' => $campaign->max_discount_value,
                ];
            })->values(),
        ];

        if (!$apkVer && $vend->apk_ver_json && isset($vend->apk_ver_json['apkver'])) {
            $apkVer = $vend->apk_ver_json['apkver'];
        }

        if ($apkVer && $apkVer >= 213) {
            unset($data['promoLabelItems']);
        }

        return $data;

        // return $vend->apkSettings[0]->settings_parameter_json;
    }

    public function transactionIndex(Request $request)
    {
        if (!$request->has('operators')) {
            if (auth()->user()->operator->code == 'HIPL') {
                $request->merge([
                    'operators' => [
                        auth()->user()->operator_id,
                        Operator::where('code', 'HIMD')->first()?->id,
                        Operator::where('code', 'LEA')->first()?->id,
                        Operator::where('code', 'HIESG')->first()?->id,
                        Operator::where('code', 'UL-ST')->first()?->id,
                    ]
                ]);
            } else {
                $request->merge(['operators' => [auth()->user()->operator_id]]);
            }
        }
        $request->merge(['sortKey' => $request->sortKey ? $request->sortKey : 'transaction_datetime']);
        $request->merge(['sortBy' => $request->sortBy ? $request->sortBy : false]);
        $request->merge(['visited' => isset($request->visited) ? $request->visited : true]);

        $request->date_from = $request->date_from ? Carbon::parse($request->date_from)->setTimezone($this->getUserTimezone())->startOfDay() : Carbon::today()->setTimezone($this->getUserTimezone())->startOfDay();
        $request->date_to = $request->date_to ? Carbon::parse($request->date_to)->setTimezone($this->getUserTimezone())->endOfDay() : Carbon::today()->setTimezone($this->getUserTimezone())->endOfDay();
        $numberPerPage = $request->numberPerPage ? $request->numberPerPage : 50;
        $className = get_class(new Customer());

        // dd($request->all());

        $perPage = $numberPerPage === 'All' ? 10000 : $numberPerPage;
        $currentPage = LengthAwarePaginator::resolveCurrentPage();

        $baseQuery = VendTransaction::query()
            ->filterTransactionIndex($request);

        $totalTransactions = (clone $baseQuery)->count();

        // Deferred join: get the page's transaction IDs from the lightweight base
        // query first (operator + date filter + ORDER BY + LIMIT, no extra joins),
        // then fetch only those rows with the full join set.
        //
        // Without this, MySQL applies all 8 joins to every matching row before
        // limiting: O(all_rows × joins) → ~21 s for 2 700 rows.
        // With deferred join: ID fetch is O(all_rows via index), join fetch is
        // O(page_size × joins) → target <500 ms total.
        $pageIds = (clone $baseQuery)
            ->forPage($currentPage, $perPage)
            ->pluck('vend_transactions.id')
            ->all();

        if (empty($pageIds)) {
            $records = collect();
        } else {
            // Re-apply the same ORDER BY as the ID-fetch step.
            // FIELD(id, v1, v2, ...) was previously used here but degrades to
            // O(n²) comparisons when page size is large ("All" = 10 000 rows).
            // Since step B joins all the same tables, applying the same sort key
            // produces identical ordering with O(n log n) cost instead.
            //
            // NOTE: sort key must be table-qualified here because the join set
            // introduces columns with the same name (e.g. vend_channels.amount).
            $sortKey = $request->sortKey ?: 'transaction_datetime';
            // Map bare column names to their qualified form to avoid ambiguity.
            $qualifiedSortKey = match (true) {
                str_contains($sortKey, '.') || str_contains($sortKey, '->') => $sortKey,
                default => 'vend_transactions.' . $sortKey,
            };
            $sortDir = filter_var($request->sortBy, FILTER_VALIDATE_BOOLEAN) ? 'asc' : 'desc';

            $records = VendTransaction::query()
                ->with([
                    'vendTransactionItems.product',
                    'vendTransactionItems.vendChannelError',
                ])
                ->whereIn('vend_transactions.id', $pageIds)
                ->leftJoin('customers', 'customers.id', '=', 'vend_transactions.customer_id')
                ->leftJoin('operators', 'operators.id', '=', 'vend_transactions.operator_id')
                ->leftJoin('payment_methods', 'payment_methods.id', '=', 'vend_transactions.payment_method_id')
                ->leftJoin('products', 'products.id', '=', 'vend_transactions.product_id')
                ->leftJoin('vend_channels', 'vend_channels.id', '=', 'vend_transactions.vend_channel_id')
                ->leftJoin('vend_channel_errors', 'vend_channel_errors.id', '=', 'vend_transactions.vend_channel_error_id')
                ->join('vends', 'vends.id', '=', 'vend_transactions.vend_id')
                ->leftJoin('vend_contracts', 'vend_contracts.id', '=', 'vends.vend_contract_id')
                ->leftJoin('vend_prefixes', 'vend_prefixes.id', '=', 'vend_transactions.vend_prefix_id')
                ->select(
                    'vend_transactions.id',
                    'vend_transactions.order_id',
                    'vend_transactions.transaction_datetime',
                    'vends.code AS vend_code',
                    'vend_prefixes.name AS vend_prefix_name',
                    'customers.code AS customer_code',
                    'customers.name AS customer_name',
                    'customers.person_id',
                    'customers.virtual_customer_prefix',
                    'customers.virtual_customer_code',
                    'operators.code AS operator_code',
                    'vend_transactions.vend_channel_code',
                    'products.code AS product_code',
                    'products.name AS product_name',
                    'vend_channels.amount AS vend_channel_amount',
                    'vend_channels.amount2 AS vend_channel_amount2',
                    'vend_transactions.amount',
                    'payment_methods.name AS payment_method_name',
                    'vend_transactions.cashless_mfg',
                    'vend_channel_errors.desc AS vend_channel_error_desc',
                    'vend_channel_errors.code AS vend_channel_error_code',
                    'vend_contracts.name AS vend_contract_name',
                    'vend_transactions.interface_type',
                    'vend_transactions.is_multiple',
                    'vend_transactions.is_refunded',
                    'vend_transactions.is_payment_received',
                    'vend_transactions.items_json',
                    'vend_transactions.meta_json',
                    'vend_transactions.vend_transaction_json',
                    'vend_transactions.label_json'
                )
                ->orderBy($qualifiedSortKey, $sortDir)
                ->get();
        }

        // Resolve labels in PHP to avoid expensive JSON_TABLE join per row
        $tagIds = $records->pluck('label_json')->flatten()->unique()->filter();
        $tagMap = Tag::whereIn('id', $tagIds)->orWhereIn('name', $tagIds)->get(['id', 'name', 'slug'])->keyBy('id');

        foreach ($records as $record) {
            $record->raw_label_json = $record->label_json;
            $record->label_json = collect($record->label_json)->map(function ($t) use ($tagMap) {
                $tag = $tagMap->get($t) ?? $tagMap->firstWhere('name', $t);
                return $tag ? ['id' => $tag->id, 'slug' => $tag->slug, 'name' => $tag->name] : $t;
            })->toArray();
        }

        $vendTransactions = new LengthAwarePaginator(
            $records,
            $totalTransactions,
            $perPage,
            $currentPage,
            [
                'path' => $request->url(),
                'query' => $request->query(),
            ]
        );

        // Pre-fetch testing vend IDs once (cached 1h) so the totals + item-totals
        // queries can filter with a cheap whereNotIn instead of an INNER JOIN on vends.
        // The JOIN forced MySQL to scan vends for every matching transaction row (~21k ops).
        $testingVendIds = Cache::remember('testing_vend_ids', 3600, fn() =>
            DB::table('vends')->where('is_testing', true)->pluck('id')->map(fn($v) => (int)$v)->all()
        );

        // Optimize: Split totals calculation into two queries to avoid expensive subquery join
        $totals = VendTransaction::query()
            ->leftJoin('payment_methods', 'payment_methods.id', '=', 'vend_transactions.payment_method_id')
            ->leftJoin('vend_channel_errors', 'vend_channel_errors.id', '=', 'vend_transactions.vend_channel_error_id')
            ->leftJoin('delivery_platform_orders', 'delivery_platform_orders.vend_transaction_id', '=', 'vend_transactions.id')
            ->filterTransactionIndex($request, true)
            ->when(!empty($testingVendIds), fn($q) => $q->whereNotIn('vend_transactions.vend_id', $testingVendIds))
            // Unified transactions: exclude in-flight (PENDING) and voided
            // (REFUNDED) gateway rows from the sales card. Legacy + all non-gateway
            // rows are SETTLED (column default), so this is a no-op for them.
            ->where('vend_transactions.settlement_status', VendTransaction::SETTLEMENT_SETTLED)
            ->select([
                DB::raw('CAST(COUNT(CASE
                    WHEN vend_channel_errors.code = 0 OR vend_channel_errors.code = 6 OR vend_channel_errors.code IS NULL OR is_multiple = true
                    THEN 1 ELSE NULL END) AS SIGNED) AS success_count'),

                DB::raw('CAST(COUNT(CASE
                    WHEN vend_channel_errors.code = 0 OR vend_channel_errors.code = 6 OR vend_channel_errors.code IS NULL OR is_multiple = true
                    THEN 1 ELSE NULL END) AS SIGNED) AS success_payment_count'),

                DB::raw('COUNT(*) AS total_transaction_count'),

                DB::raw('ROUND(COUNT(CASE
                    WHEN vend_channel_errors.code = 0 OR vend_channel_errors.code = 6 OR vend_channel_errors.code IS NULL OR is_multiple = true
                    THEN 1 ELSE NULL END) * 100.0 / NULLIF(COUNT(*), 0), 2) AS success_payment_rate'),

                DB::raw('ROUND(COALESCE(SUM(CASE
                    WHEN vend_channel_errors.code = 0 OR vend_channel_errors.code = 6 OR vend_channel_errors.code IS NULL OR is_multiple = true
                    THEN vend_transactions.amount ELSE 0 END), 0), 2) AS success_amount'),

                DB::raw('ROUND(COALESCE(SUM(CASE
                    WHEN (vend_channel_errors.code = 0 OR vend_channel_errors.code = 6 OR vend_channel_errors.code IS NULL OR is_multiple = true)
                        AND delivery_platform_orders.id IS NULL
                        AND payment_methods.code = 0
                    THEN vend_transactions.amount ELSE 0 END), 0), 2) AS cash_amount'),

                DB::raw('ROUND(COALESCE(SUM(CASE
                    WHEN (vend_channel_errors.code = 0 OR vend_channel_errors.code = 6 OR vend_channel_errors.code IS NULL OR is_multiple = true)
                        AND delivery_platform_orders.id IS NULL
                        AND payment_methods.payment_gateway_id IS NULL
                        AND payment_methods.code > 0
                    THEN vend_transactions.amount ELSE 0 END), 0), 2) AS cashless_terminal_amount'),

                DB::raw('ROUND(COALESCE(SUM(CASE
                    WHEN (vend_channel_errors.code = 0 OR vend_channel_errors.code = 6 OR vend_channel_errors.code IS NULL OR is_multiple = true)
                        AND delivery_platform_orders.id IS NULL
                        AND payment_methods.payment_gateway_id IS NOT NULL
                    THEN vend_transactions.amount ELSE 0 END), 0), 2) AS qr_payment_amount'),

                DB::raw('CAST(COUNT(CASE
                    WHEN (vend_channel_errors.code = 0 OR vend_channel_errors.code = 6 OR vend_channel_errors.code IS NULL OR is_multiple = true)
                        AND delivery_platform_orders.id IS NULL
                        AND payment_methods.code = 0
                    THEN 1 ELSE NULL END) AS SIGNED) AS cash_count'),

                DB::raw('CAST(COUNT(CASE
                    WHEN (vend_channel_errors.code = 0 OR vend_channel_errors.code = 6 OR vend_channel_errors.code IS NULL OR is_multiple = true)
                        AND delivery_platform_orders.id IS NULL
                        AND payment_methods.payment_gateway_id IS NULL
                        AND payment_methods.code > 0
                    THEN 1 ELSE NULL END) AS SIGNED) AS cashless_terminal_count'),

                DB::raw('CAST(COUNT(CASE
                    WHEN (vend_channel_errors.code = 0 OR vend_channel_errors.code = 6 OR vend_channel_errors.code IS NULL OR is_multiple = true)
                        AND delivery_platform_orders.id IS NULL
                        AND payment_methods.payment_gateway_id IS NOT NULL
                    THEN 1 ELSE NULL END) AS SIGNED) AS qr_payment_count'),

                DB::raw('COUNT(*) AS total_count'),

                // Count of single items (where is_multiple = 0), excluding error codes #4 and #5
                DB::raw('CAST(SUM(CASE WHEN is_multiple = 0 AND (vend_channel_errors.code IS NULL OR vend_channel_errors.code NOT IN (4, 5)) THEN 1 ELSE 0 END) AS SIGNED) as single_qty'),
                // Count of successful single items
                DB::raw('CAST(SUM(CASE
                    WHEN is_multiple = 0 AND (vend_channel_errors.code = 0 OR vend_channel_errors.code = 6 OR vend_channel_errors.code IS NULL)
                    THEN 1 ELSE 0 END) AS SIGNED) as success_single_qty'),

                DB::raw('ROUND(COUNT(CASE
                    WHEN vend_channel_errors.code = 0 OR vend_channel_errors.code = 6 OR vend_channel_errors.code IS NULL OR is_multiple = true
                    THEN 1 ELSE NULL END) * 100.0 / NULLIF(COUNT(*), 0), 2) AS success_count_rate'),

                DB::raw('CAST(COUNT(CASE
                    WHEN (vend_channel_errors.code = 0 OR vend_channel_errors.code = 6 OR vend_channel_errors.code IS NULL OR is_multiple = true)
                        AND delivery_platform_orders.id IS NOT NULL
                    THEN 1 ELSE NULL END) AS SIGNED) AS delivery_platform_success_count'),

                DB::raw('ROUND(COALESCE(SUM(CASE
                    WHEN (vend_channel_errors.code = 0 OR vend_channel_errors.code = 6 OR vend_channel_errors.code IS NULL OR is_multiple = true)
                        AND delivery_platform_orders.id IS NOT NULL
                    THEN vend_transactions.amount ELSE 0 END), 0), 2) AS delivery_platform_success_amount'),

                DB::raw('CAST(SUM(CASE
                    WHEN is_multiple = 1 AND delivery_platform_orders.id IS NOT NULL
                    THEN 1 ELSE 0 END) AS SIGNED) AS multiple_count_delivery_platform'),

                DB::raw('CAST(SUM(CASE
                    WHEN is_multiple = 1 AND delivery_platform_orders.id IS NULL
                    THEN 1 ELSE 0 END) AS SIGNED) AS multiple_count_machine')

            ])
            ->first();

        // Calculate item totals for multiple transactions
        $itemTotals = VendTransaction::query()
            ->filterTransactionIndex($request, true)
            ->where('is_multiple', true)
            ->when(!empty($testingVendIds), fn($q) => $q->whereNotIn('vend_transactions.vend_id', $testingVendIds))
            ->where('vend_transactions.settlement_status', VendTransaction::SETTLEMENT_SETTLED)
            ->leftJoin('vend_transaction_items', 'vend_transactions.id', '=', 'vend_transaction_items.vend_transaction_id')
            ->select([
                DB::raw('COUNT(CASE WHEN vend_transaction_items.id IS NOT NULL AND (vend_transaction_items.vend_channel_error_code IS NULL OR vend_transaction_items.vend_channel_error_code NOT IN (4, 5)) THEN 1 END) as total_items'),
                DB::raw('COUNT(CASE WHEN vend_transaction_items.id IS NOT NULL AND (vend_transaction_items.vend_channel_error_code IN (0,6) OR vend_transaction_items.vend_channel_error_code IS NULL) THEN 1 END) as success_items')
            ])
            ->first();

        // Merge results
        $totals->total_qty = $totals->single_qty + $itemTotals->total_items;
        $totals->success_total_qty = $totals->success_single_qty + $itemTotals->success_items;
        $totals->success_total_qty_rate = $totals->total_qty > 0 ? round($totals->success_total_qty * 100 / $totals->total_qty, 2) : 0;

        // ── Dispensed-but-unreported QR/PayNow revenue ──────────────────────
        // Some PayNow payments are approved (money received) and the machine
        // dispenses the item (is_dispensed = true), but the machine never
        // reports the completed transaction back via TRADE in VendDataService,
        // so no vend_transaction row is created. These sales are real but
        // invisible to the vend_transactions-based totals above. Product
        // decision: treat them as successful QR sales — add their amount to
        // both the QR Payment Gateway line and the Total Sales headline.
        //
        // Conditions mirror the /vends/payment-gateway-transactions filters:
        //   is_dispensed = true, no linked vend_transaction, status = APPROVE
        //   (status 2 = approved and NOT refunded(98)/declined(99)).
        // Scope matches this card: same operators + date window (+ machine /
        // customer when filtered) and the same testing-machine exclusion.
        // Only counted from PaymentGatewayLog::UNREPORTED_GATEWAY_CUTOFF onward
        // (default true) so periods before that date stay aligned with prior
        // accounting exports. Filters mirrored inside the scope so this headline
        // and the CSV export count exactly the same rows.
        $unreportedGatewayAmount = PaymentGatewayLog::query()
            ->unreportedDispensed($request, $testingVendIds)
            ->sum('payment_gateway_logs.amount');

        // vend_transactions.amount is in minor units (cents); gateway log
        // amounts are in major units — scale up before merging.
        $currencyExponent = auth()->user()->operator?->country?->currency_exponent ?? 2;
        $unreportedGatewayMinor = (int) round(((float) $unreportedGatewayAmount) * pow(10, $currencyExponent));

        $totals->qr_payment_amount = (float) $totals->qr_payment_amount + $unreportedGatewayMinor;
        $totals->success_amount    = (float) $totals->success_amount + $unreportedGatewayMinor;
        // ────────────────────────────────────────────────────────────────────


        $latestExports = ExportJob::with('attachment')
            ->where('user_id', auth()->id())
            ->where('type', 'vend_transaction')
            ->latest()
            ->limit(5)
            ->get();

        // Cache metadata queries to reduce database load
        $ttl = 86400; // 24 hours
        $categories = Cache::remember('categories_' . $className, $ttl, fn() => CategoryResource::collection(
            Category::where('classname', $className)->orderBy('name')->get()
        )->resolve());

        $categoryGroups = Cache::remember('category_groups_' . $className, $ttl, fn() => CategoryGroupResource::collection(
            CategoryGroup::where('classname', $className)->orderBy('name')->get()
        )->resolve());

        $locationTypeOptions = Cache::remember('location_type_options', $ttl, fn() => LocationTypeResource::collection(
            LocationType::orderBy('sequence')->get()
        )->resolve());

        $operatorOptions = Cache::remember('operator_options_' . auth()->user()->operator_id, $ttl, fn() => OperatorResource::collection(
            Operator::orderBy('name')->get()
        )->resolve());

        $paymentMethods = Cache::remember('payment_methods', $ttl, fn() => PaymentMethodResource::collection(
            PaymentMethod::orderBy('name')->get()
        )->resolve());

        $tagOptions = Cache::remember('tag_options', $ttl, fn() => TagResource::collection(
            Tag::orderBy('name')->get()
        )->resolve());

        $vendChannelErrors = Cache::remember('vend_channel_errors', $ttl, fn() => VendChannelErrorResource::collection(
            VendChannelError::orderBy('code')->get()
        )->resolve());

        $vendContractOptions = Cache::remember('vend_contract_options', $ttl, fn() => VendContractResource::collection(
            VendContract::orderBy('name')->get()
        )->resolve());

        $vendModelOptions = Cache::remember('vend_model_options', $ttl, fn() => VendModelResource::collection(
            VendModel::orderBy('name')->get()
        )->resolve());

        // Transaction filter: only list prefixes that still have at least
        // one Active machine — prefixes whose machines are all
        // inactive/testing should not clutter the dropdown.
        $vendPrefixOptions = Cache::remember('vend_prefix_options_active_' . auth()->user()->operator_id, $ttl, fn() => VendPrefixResource::collection(
            VendPrefix::hasActiveVends()->orderBy('name')->get()
        )->resolve());

        // Canonical card-terminal names (Nayax / Nets / Nets-Auresys / PAX /
        // MLS). The frontend uses these to synthesize per-terminal
        // "Credit Card (<name>)" entries inside the Payment Method dropdown
        // — the standalone Card Terminal filter was retired on 2026-05-16.
        $cardTerminalOptions = Cache::remember('card_terminal_options', $ttl, fn() =>
            CardTerminal::orderBy('name')->pluck('name')->values()
        );

        return Inertia::render('Vend/Transaction', [
            'cardTerminalOptions' => ['data' => $cardTerminalOptions],
            'categories' => ['data' => $categories],
            'categoryGroups' => ['data' => $categoryGroups],
            'latestExports' => $latestExports,
            'locationTypeOptions' => ['data' => $locationTypeOptions],
            'operatorOptions' => ['data' => $operatorOptions],
            'paymentMethods' => ['data' => $paymentMethods],
            'vendTransactions' => VendTransactionResource::collection(
                $vendTransactions
            ),
            'tagOptions' => ['data' => $tagOptions],
            'totals' => $totals,
            'vendChannelErrors' => ['data' => $vendChannelErrors],
            'vendContractOptions' => ['data' => $vendContractOptions],
            'vendModelOptions' => ['data' => $vendModelOptions],
            'vendPrefixOptions' => ['data' => $vendPrefixOptions],
        ]);
    }

    public function channelErrorLogsEmail()
    {
        $intervalHours = 24;
        $now = Carbon::now();
        $vendChannelErrorLogs = VendChannelErrorLog::with([
            'vendChannel',
            'vendChannel.vend',
            'vendChannelError'
        ])
            ->leftJoin('vend_channels', 'vend_channels.id', '=', 'vend_channel_error_logs.vend_channel_id')
            ->leftJoin('vends', 'vends.id', '=', 'vend_channels.vend_id')
            ->where('vend_channel_error_logs.created_at', '>=', $now->subHours($intervalHours))
            ->orderBy('vends.code')
            ->orderBy('vend_channel_error_logs.created_at')
            ->select('*', 'vend_channel_error_logs.created_at')
            ->get();

        Mail::to([
            'daniel.ma@happyice.com.sg',
            'kent@happyice.com.sg',
            'stephen@happyice.com.sg',
            'brianlee@happyice.com.my',
            'technician1@happyice.com.sg',
        ])
            ->send(new VendChannelErrorLogsMail($vendChannelErrorLogs, $intervalHours));
    }

    public function exportPaymentGatewayTransactionExcel(Request $request)
    {
        if (!$request->operators) {
            if (auth()->user()->operator->code == 'HIPL') {
                $request->merge([
                    'operators' => [
                        auth()->user()->operator_id,
                        Operator::where('code', 'HIMD')->first()?->id,
                        Operator::where('code', 'LEA')->first()?->id,
                        Operator::where('code', 'HIESG')->first()?->id,
                        Operator::where('code', 'UL-ST')->first()?->id,
                    ]
                ]);
            } else {
                $request->merge(['operators' => [auth()->user()->operator_id]]);
            }
        }
        $request->merge(['sortKey' => $request->sortKey ? $request->sortKey : 'approved_at']);
        $request->merge(['sortBy' => $request->sortBy ? $request->sortBy : false]);
        $request->merge([
            'date_from' => $request->date_from ? Carbon::parse($request->date_from)->setTimezone($this->getUserTimezone())->startOfDay() : Carbon::today()->setTimezone($this->getUserTimezone())->startOfDay(),
            'date_to' => $request->date_to ? Carbon::parse($request->date_to)->setTimezone($this->getUserTimezone())->endOfDay() : Carbon::today()->setTimezone($this->getUserTimezone())->endOfDay(),
        ]);

        $paymentGatewayLogs = PaymentGatewayLog::query()
            ->with([
                'operatorPaymentGateway.operator',
                'vend:id,code,customer_id,name,label_name,is_active,vend_prefix_id',
                'vend.customer',
                'vend.vendPrefix',
                'vendTransaction.vendChannelError',
            ])
            ->filterIndex($request)
            ->where('status', '>=', PaymentGatewayLog::STATUS_APPROVE)
            ->get();


        return (new FastExcel($this->yieldOneByOne($paymentGatewayLogs)))->download('Payment_Gateway_Transactions_' . Carbon::now()->toDateTimeString() . '.xlsx', function ($paymentGatewayLog) {
            return [
                'Ref ID' => $paymentGatewayLog->ref_id,
                'Paid At' => Carbon::parse($paymentGatewayLog->approved_at)->toDateTimeString(),
                'Order ID' => $paymentGatewayLog->order_id,
                'Dispensed?' => $paymentGatewayLog->is_dispensed ? 'Yes' : 'No',
                'Found in Transactions?' => $paymentGatewayLog->vendTransaction ? 'Yes' : 'No',
                'Machine ID' => $paymentGatewayLog->vend_code,
                'Machine Prefix' => $paymentGatewayLog->vend?->vendPrefix?->name,
                'Customer ID' => $paymentGatewayLog->vend?->customer?->virtual_customer_code,
                'Customer Name' => $paymentGatewayLog->vend?->customer?->name,
                'Operator' => $paymentGatewayLog->operatorPaymentGateway?->operator?->name,
                'Amount' => $paymentGatewayLog->amount,
                'Payment Method' => $paymentGatewayLog->method,
                'Refunded?' => $paymentGatewayLog->status == '98' ? 'Yes' : 'No',
                'Error(s)' => $paymentGatewayLog->vendTransaction?->vendChannelError?->desc,
                'QR Ref ID' => $paymentGatewayLog->qr_ref_id,
            ];
        });
    }

    public function exportTransactionCsv(Request $request)
    {
        $filenameBase = 'vend_transactions_' . now()->format('Ymd_His');
        $user = auth()->user();

        $job = ExportJob::create([
            'user_id' => $user->id,
            'type' => 'vend_transaction',
            'status' => 'pending',
            'filename' => $filenameBase,
        ]);
        // dd($request->all());

        // Lightweight ID-only query — same filters, no heavy SELECT columns.
        // Ordering by vend_transactions.id (PK) is index-only and avoids the
        // slow transaction_datetime sort on a large range.
        $userVendIds = $user->vends()->exists() ? $user->vends->pluck('id') : null;

        $idQuery = VendTransaction::query()
            ->select('vend_transactions.id')
            ->leftJoin('customers', 'customers.id', '=', 'vend_transactions.customer_id')
            ->leftJoin('location_types', 'location_types.id', '=', 'customers.location_type_id')
            ->join('vends', 'vends.id', '=', 'vend_transactions.vend_id')
            ->leftJoin('operators', 'operators.id', '=', 'vend_transactions.operator_id')
            ->leftJoin('payment_methods', 'payment_methods.id', '=', 'vend_transactions.payment_method_id')
            ->leftJoin('products', 'products.id', '=', 'vend_transactions.product_id')
            ->leftJoin('unit_costs', 'unit_costs.id', '=', 'vend_transactions.unit_cost_id')
            ->leftJoin('vend_channels', 'vend_channels.id', '=', 'vend_transactions.vend_channel_id')
            ->leftJoin('vend_channel_errors', 'vend_channel_errors.id', '=', 'vend_transactions.vend_channel_error_id')
            ->leftJoin('vend_prefixes', 'vend_prefixes.id', '=', 'vends.vend_prefix_id')
            ->when($userVendIds !== null, function ($query) use ($userVendIds) {
                $query->whereIn('vend_transactions.vend_id', $userVendIds);
            })
            ->filterTransactionIndex($request, true) // skip sort — we impose our own
            ->orderBy('vend_transactions.id');

        // Collect IDs in order to determine per-chunk keyset boundaries.
        // For 2-week windows this is well within memory limits (a few MB of ints).
        $allIds = $idQuery->pluck('vend_transactions.id');
        $totalRows = $allIds->count();
        $chunkSize = 20000;

        if ($totalRows <= $chunkSize) {
            // ✅ Small export — dispatch single job (already uses cursor-safe chunk())
            ExportVendTransactionCsv::dispatch($job->id, $request->all(), $user->id);
        } else {
            // ✅ Large export — split by ID boundaries so each job uses whereBetween,
            //    never OFFSET. This avoids the MySQL scan-and-discard problem AND the
            //    bug where Laravel's chunk() internally overrides skip/take via forPage().
            $chunks = $allIds->chunk($chunkSize);

            foreach ($chunks as $i => $chunkIds) {
                ExportJobChunk::create([
                    'export_job_id' => $job->id,
                    'chunk_index' => $i,
                    'status' => 'pending',
                ]);

                ExportVendTransactionCsvChunk::dispatch(
                    $job->id,
                    $request->all(),
                    $user->id,
                    $i,
                    $chunkSize,
                    $chunkIds->first(), // minId — keyset lower bound
                    $chunkIds->last(),  // maxId — keyset upper bound
                );
            }
        }

        return back()->with('message', 'Export started! You can check it later in the export list.');
    }


    public function exportTransactionExcel(Request $request)
    {
        $request->merge(['sortKey' => $request->sortKey ?? 'transaction_datetime']);
        $request->merge(['sortBy' => $request->sortBy ?? false]);

        $timezone = config('app.timezone');
        $request->date_from = $request->date_from
            ? Carbon::parse($request->date_from)->setTimezone($timezone)->startOfDay()
            : Carbon::today()->setTimezone($timezone)->startOfDay();

        $request->date_to = $request->date_to
            ? Carbon::parse($request->date_to)->setTimezone($timezone)->endOfDay()
            : Carbon::today()->setTimezone($timezone)->endOfDay();

        $data = [];

        VendTransaction::query()
            ->with([
                'vendTransactionItems.vendChannel:id,code,amount',
                'vendTransactionItems.product:id,code,name',
                'vendTransactionItems.unitCost:id,cost',
                'vendTransactionItems.vendChannelError:id,code,desc',
            ])
            ->leftJoin('customers', 'customers.id', '=', 'vend_transactions.customer_id')
            ->join('vends', 'vends.id', '=', 'vend_transactions.vend_id')
            ->leftJoin('location_types', 'location_types.id', '=', 'customers.location_type_id')
            ->leftJoin('operators', 'operators.id', '=', 'vend_transactions.operator_id')
            ->leftJoin('payment_methods', 'payment_methods.id', '=', 'vend_transactions.payment_method_id')
            ->leftJoin('products', 'products.id', '=', 'vend_transactions.product_id')
            ->leftJoin('unit_costs', 'unit_costs.id', '=', 'vend_transactions.unit_cost_id')
            ->leftJoin('vend_channels', 'vend_channels.id', '=', 'vend_transactions.vend_channel_id')
            ->leftJoin('vend_channel_errors', 'vend_channel_errors.id', '=', 'vend_transactions.vend_channel_error_id')
            ->leftJoin('vend_prefixes', 'vend_prefixes.id', '=', 'vends.vend_prefix_id')
            ->filterTransactionIndex($request)
            ->select([
                'vend_transactions.*',
                'vends.code AS vend_code',
                'vends.name AS vend_name',
                'vend_prefixes.name AS vend_prefix_name',
                'customers.id AS customer_id',
                'customers.code AS customer_code',
                'customers.name AS customer_name',
                'customers.person_id',
                'customers.virtual_customer_code',
                'location_types.name AS location_type_name',
                'operators.code AS operator_code',
                'products.code AS product_code',
                'products.name AS product_name',
                'payment_methods.name AS payment_method_name',
                'unit_costs.cost',
                'vend_channels.amount AS vend_channel_amount',
                'vend_channels.amount2 AS vend_channel_amount2',
                'vend_channel_errors.desc AS vend_channel_error_desc',
                'vend_channel_errors.code AS vend_channel_error_code',
                DB::raw('vend_transactions.label_json AS label_ids_json'),
            ])
            ->chunk(500, function ($transactions) use (&$data) {
                // 1) Collect all tag IDs used in this chunk
                $tagIds = $transactions->pluck('label_ids_json')
                    ->filter()
                    ->flatMap(function ($val) {
                    if (is_array($val))
                        return $val;
                    $arr = json_decode($val, true);
                    return is_array($arr) ? $arr : [];
                })
                    ->unique()
                    ->values();

                // 2) Fetch once, key by id
                $tagMap = Tag::whereIn('id', $tagIds)
                    ->get(['id', 'name', 'slug'])
                    ->keyBy('id');

                foreach ($transactions as $txn) {
                    // normalize txn's label IDs
                    $ids = is_array($txn->label_ids_json)
                        ? $txn->label_ids_json
                        : (json_decode($txn->label_ids_json, true) ?: []);

                    // 3) Build label string (use name -> slug -> id)
                    $labelStr = collect($ids)->map(function ($id) use ($tagMap) {
                        $t = $tagMap->get($id);
                        return $t->name ?? $t->slug ?? (string) $id;
                    })->implode(', ');

                    $txn_json = is_array($txn->vend_transaction_json) ? $txn->vend_transaction_json : json_decode($txn->vend_transaction_json, true);
                    $main_amount = $txn->amount / 100;

                    $multipleBreakdown = $txn->is_multiple
                        ? ($txn->amount - $txn->vendTransactionItems->sum(fn($item) => $item->vendChannel?->amount ?? 0)) / 100
                        : $main_amount;

                    // 4) Put labels into the main row (keep item rows empty or repeat, your choice)
                    $data[] = [
                        'order_id' => $txn->order_id,
                        'transaction_datetime' => \Carbon\Carbon::parse($txn->transaction_datetime)->toDateTimeString(),
                        'machine_id' => $txn->vend_code ?? '',
                        'machine_prefix' => $txn->vend_prefix_name ?? '',
                        'customer_id' => $txn->customer_id + 20000,
                        'customer_code' => $txn->person_id ? $txn->virtual_customer_code : '',
                        'customer_name' => $txn->customer_name,
                        'channel' => $txn->vend_channel_code ?? '',
                        'product_code' => $txn->product_code,
                        'product_name' => $txn->product_name,
                        'price_type' => $txn->vend_channel_amount == $txn->amount ? 'P1' : ($txn->vend_channel_amount2 == $txn->amount ? 'P2' : ''),
                        'amount' => $main_amount,
                        'amount_breakdown' => $multipleBreakdown,
                        'unit_cost' => $txn->cost ? $txn->cost / 100 : '',
                        'payment_method' => $txn->payment_method_name,
                        'error_code' => $txn->vend_channel_error_code,
                        'location_type' => $txn->location_type_name,
                        'operator' => $txn->operator_code,
                        'is_successful' => in_array($txn->vend_channel_error_code, [null, 0, 6]) ? 'Successful' : 'Unsuccessful',
                        'is_refunded' => $txn->is_refunded ? 'Yes' : '',
                        'is_multiple' => $txn->is_multiple ? 'Yes' : 'No',
                        'multiple_qty' => $txn->is_multiple ? $txn->vendTransactionItems->count() : 1,
                        'txn_src' => $txn->interface_type,
                        'member_id' => $txn_json['dcvend_user_id'] ?? '',
                        'hid_card_id' => $txn->meta_json['hid_card_id'] ?? '',
                        'voucher' => isset($txn->meta_json['vouchers']) ? $txn->meta_json['vouchers'][0]['code'] : '',
                        'labels' => $labelStr, // 👈 add this column
                    ];

                    foreach ($txn->vendTransactionItems as $item) {
                        $data[] = [
                            'order_id' => $txn->order_id,
                            'transaction_datetime' => \Carbon\Carbon::parse($txn->transaction_datetime)->toDateTimeString(),
                            'machine_id' => $txn->vend_code ?? '',
                            'machine_prefix' => $txn->vend_prefix_name ?? '',
                            'customer_id' => $txn->customer_id + 20000,
                            'customer_code' => $txn->person_id ? $txn->virtual_customer_code : '',
                            'customer_name' => $txn->customer_name,
                            'channel' => (int) $item->vend_channel_code,
                            'product_code' => $item->product->code ?? '',
                            'product_name' => $item->product->name ?? '',
                            'price_type' => 'P1',
                            'amount' => '',
                            'amount_breakdown' => $item->vendChannel ? $item->vendChannel->amount / 100 : '',
                            'unit_cost' => $item->unitCost ? $item->unitCost->cost : '',
                            'payment_method' => $txn->payment_method_name,
                            'error_code' => $item->vendChannelError->code ?? '',
                            'location_type' => $txn->location_type_name,
                            'operator' => $txn->operator_code,
                            'is_successful' => in_array($item->vendChannelError->code ?? null, [null, 0, 6]) ? 'Successful' : 'Unsuccessful',
                            'is_refunded' => '',
                            'is_multiple' => $txn->is_multiple ? 'Yes' : 'No',
                            'multiple_qty' => 0,
                            'txn_src' => $txn->interface_type,
                            'member_id' => $txn_json['dcvend_user_id'] ?? '',
                            'labels' => '', // or $labelStr if you want to repeat per item row
                        ];
                    }
                }
            });

        return (new FastExcel(collect($data)))
            ->download('Vend_transactions_' . now()->format('Ymd_His') . '.xlsx');
    }


    public function exportVendSnapshotExcel($vendSnapshotId)
    {
        $vendSnapshot = VendSnapshot::findOrFail($vendSnapshotId);
        $vendTransactions = $vendSnapshot->vendTransactions;

        return (new FastExcel($this->yieldOneByOne($vendTransactions)))->download('Vend_transactions_' . Carbon::now()->toDateTimeString() . '.xlsx', function ($vendTransaction) {
            return [
                'Order ID' => $vendTransaction->order_id,
                'Transaction Datetime' => Carbon::parse($vendTransaction->transaction_datetime)->toDateTimeString(),
                'Machine ID' => $vendTransaction->vend->code,
                'Customer ID' => $vendTransaction->customer ? $vendTransaction->customer->id + 20000 : '',
                'Customer Name' => $vendTransaction->customer_id ? $vendTransaction->customer->name : '',
                'Channel' => $vendTransaction->vend_channel_code,
                'Product Code' => $vendTransaction->product()->exists() ?
                    $vendTransaction->product->code :
                    '',
                'Product Name' => $vendTransaction->product()->exists() ?
                    $vendTransaction->product->name :
                    '',
                'Amount' => $vendTransaction->amount / 100,
                'Sales (before GST)' => $vendTransaction->revenue / 100,
                'Unit Cost' => $vendTransaction->unitCost()->exists() ?
                    $vendTransaction->unitCost->cost / 100 :
                    '',
                'Payment Method' => $vendTransaction->paymentMethod ? $vendTransaction->paymentMethod->name : '',
                'Error' => $vendTransaction->vend_transaction_json &&
                    $vendTransaction->vend_transaction_json['SErr'] ?
                    $vendTransaction->vend_transaction_json['SErr'] :
                    ($vendTransaction->vendChannelError && $vendTransaction->vendChannelError->code ?? ''),
            ];
        });
    }

    public function latestExports()
    {
        return ExportJob::with('attachment')
            ->where('user_id', auth()->id())
            ->where('type', 'vend_transaction')
            ->latest()
            ->limit(5)
            ->get();
    }


    private function yieldOneByOne($items)
    {
        foreach ($items as $item) {
            yield $item;
        }
    }

    public function create(Request $request)
    {
        $request->validate([
            'code' => 'required|unique:vends',
        ]);

        $vend = Vend::create([
            'begin_date' => $request->begin_date,
            'code' => $request->code,
            'name' => $request->name,
            'private_key' => $request->private_key,
            'settings_parameter_json' => [
                "buy1free1X" => 0,
                "buy1free1Y" => 0,
                "buy2free1X" => 1,
                "buy2free1Y" => 0,
                "bundleEndDate" => null,
                "bundleStartDate" => null,
                "dcvendFreePlanPromoValue" => 15,
                "dcvendGoldPlanPromoValue" => 30,
                "dcvendPlatinumPlanPromoValue" => 30,
                "enableBuy1Free1" => "false",
                "enableBuy2Free1" => "false",
                "promoBannerKind" => "video",
                "promoHeaderText" => null,
                "buy1free1EndDate" => null,
                "buy2free1EndDate" => null,
                "enableDiscount01" => "true",
                "enableDiscount02" => "false",
                "enableDiscount03" => "false",
                "promoRunningText" => null,
                "discountPercent01" => 1,
                "discountPercent02" => 1,
                "discountPercent03" => 1,
                "headerTextEndDate" => null,
                "buy1free1StartDate" => null,
                "buy2free1StartDate" => null,
                "runningTextEndDate" => null,
                "disableP1P2CrossGrp" => "false",
                "headerTextStartDate" => null,
                "enableBundleDiscount" => "false",
                "runningTextStartDate" => null,
                "enablePromoHeaderText" => "false",
                "enablePromoRunningText" => "false",
                "enableHeaderTextRunning" => "false"
            ]
        ]);

        // if($request->customer_id) {
        //     SyncVendCustomerCms::dispatchSync($vend->id, $request->customer_id);
        // }

        if ($request->operator_id) {
            $vend->operator_id = $request->operator_id;
        } else {
            $vend->operator_id = auth()->user()->operator_id;
        }
        $vend->save();

        return redirect()->route('settings');
    }

    public function paymentGatewayTransactionIndex(Request $request)
    {
        if (!$request->operators) {
            if (auth()->user()->operator->code == 'HIPL') {
                $request->merge([
                    'operators' => [
                        auth()->user()->operator_id,
                        Operator::where('code', 'HIMD')->first()?->id,
                        Operator::where('code', 'LEA')->first()?->id,
                        Operator::where('code', 'HIESG')->first()?->id,
                        Operator::where('code', 'UL-ST')->first()?->id,
                    ]
                ]);
            } else {
                $request->merge(['operators' => [auth()->user()->operator_id]]);
            }
        }
        $request->merge(['sortKey' => $request->sortKey ? $request->sortKey : 'approved_at']);
        $request->merge(['sortBy' => $request->sortBy ? $request->sortBy : false]);
        $request->merge(['visited' => isset($request->visited) ? $request->visited : true]);
        $request->merge([
            'date_from' => $request->date_from ? Carbon::parse($request->date_from)->setTimezone($this->getUserTimezone())->startOfDay() : Carbon::today()->setTimezone($this->getUserTimezone())->startOfDay(),
            'date_to' => $request->date_to ? Carbon::parse($request->date_to)->setTimezone($this->getUserTimezone())->endOfDay() : Carbon::today()->setTimezone($this->getUserTimezone())->endOfDay(),
        ]);
        $request->merge(['numberPerPage' => $request->numberPerPage ? $request->numberPerPage : 50]);

        // dd($request->all());

        $paymentGatewayLogs = PaymentGatewayLog::query()
            ->with([
                'operatorPaymentGateway.operator',
                'vend:id,code,customer_id,name,label_name,is_active,vend_prefix_id',
                'vend.customer',
                'vend.vendPrefix',
                'vendTransaction.vendChannelError',
            ])
            ->filterIndex($request)
            ->where('status', '>=', PaymentGatewayLog::STATUS_APPROVE)
            ->paginate($request->numberPerPage === 'All' ? 10000 : $request->numberPerPage)
            ->withQueryString();

        $totals = PaymentGatewayLog::query()
            ->with([
                'operatorPaymentGateway.operator',
                'vend:id,code,customer_id,name,label_name,is_active,vend_prefix_id',
                'vend.customer',
                'vend.vendPrefix',
                'vendTransaction',
            ])
            ->filterIndex($request)
            ->where('status', '>=', PaymentGatewayLog::STATUS_APPROVE)
            ->leftJoin('vend_transactions', 'vend_transactions.payment_gateway_log_id', '=', 'payment_gateway_logs.id')
            ->select(
                DB::raw('CAST(COUNT(CASE
                    WHEN status = 2
                    THEN 1 ELSE NULL END) AS SIGNED) AS paid_count'),
                DB::raw('CAST(COUNT(CASE
                    WHEN status = 98
                    THEN 1 ELSE NULL END) AS SIGNED) AS refund_count'),
                DB::raw('CAST(ROUND(COALESCE(SUM(CASE
                    WHEN status = 2
                    THEN payment_gateway_logs.amount ELSE 0 END), 0), 2) AS SIGNED) AS paid_amount'),
                DB::raw('CAST(ROUND(COALESCE(SUM(CASE
                    WHEN status = 98
                    THEN payment_gateway_logs.amount ELSE 0 END), 0), 2) AS SIGNED) AS refund_amount'),
                // DB::raw('CAST(COUNT(vend_transactions.id) AS SIGNED) AS dispense_count')
                DB::raw('CAST(COUNT(CASE
                    WHEN is_dispensed = 1
                    THEN 1 ELSE NULL END) AS SIGNED) AS dispense_count')
            )
            ->first();

        // Cache option queries to reduce database calls
        $operatorOptions = OperatorResource::collection(
            Operator::orderBy('name')->get()
        );
        $paymentMethods = PaymentMethodResource::collection(
            PaymentMethod::orderBy('name')->get()
        );
        $paymentGatewayOptions = PaymentGatewayResource::collection(
            PaymentGateway::with(['country'])
                ->orderBy('name')
                ->get()
        );

        return Inertia::render('Vend/PaymentGatewayTransaction', [
            'operatorOptions' => $operatorOptions,
            'paymentMethods' => $paymentMethods,
            'paymentGatewayOptions' => $paymentGatewayOptions,
            'paymentGatewayLogs' => PaymentGatewayLogResource::collection(
                $paymentGatewayLogs
            ),
            'totals' => $totals,
        ]);
    }

    /**
     * Daily Summary of vend_transactions.
     *
     * Groups vend_transactions by date (transaction_datetime) and payment_method.
     * Reuses the same scopeFilterTransactionIndex filters as the Sales Transactions
     * page, but the page itself only surfaces a subset of filters (Machine ID,
     * Channel ID, From/To, Payment Method, Card Terminal, Payment Received).
     *
     * NOTE: per-country deployments — DB datetimes are already stored in the
     * operator's timezone, so DATE(transaction_datetime) is the correct group key.
     */
    public function dailySummaryIndex(Request $request)
    {
        if (!$request->has('operators')) {
            if (auth()->user()->operator->code == 'HIPL') {
                $request->merge([
                    'operators' => [
                        auth()->user()->operator_id,
                        Operator::where('code', 'HIMD')->first()?->id,
                        Operator::where('code', 'LEA')->first()?->id,
                        Operator::where('code', 'HIESG')->first()?->id,
                        Operator::where('code', 'UL-ST')->first()?->id,
                    ]
                ]);
            } else {
                $request->merge(['operators' => [auth()->user()->operator_id]]);
            }
        }
        $request->merge(['visited' => isset($request->visited) ? $request->visited : true]);

        $request->date_from = $request->date_from ? Carbon::parse($request->date_from)->setTimezone($this->getUserTimezone())->startOfDay() : Carbon::today()->setTimezone($this->getUserTimezone())->startOfDay();
        $request->date_to = $request->date_to ? Carbon::parse($request->date_to)->setTimezone($this->getUserTimezone())->endOfDay() : Carbon::today()->setTimezone($this->getUserTimezone())->endOfDay();

        $numberPerPage = $request->numberPerPage ? $request->numberPerPage : 50;
        $perPage = $numberPerPage === 'All' ? 10000 : $numberPerPage;
        $currentPage = LengthAwarePaginator::resolveCurrentPage();

        // Sort key + direction. Default: newest date first.
        $sortKey = $request->sortKey ?: 'transaction_date';
        $allowedSortKeys = [
            'transaction_date',
            'payment_method_name',
            'total_transaction_count',
            'success_count',
            'total_amount',
            'success_amount',
        ];
        if (!in_array($sortKey, $allowedSortKeys, true)) {
            $sortKey = 'transaction_date';
        }
        $sortDir = filter_var($request->sortBy, FILTER_VALIDATE_BOOLEAN) ? 'asc' : 'desc';

        // Pre-fetch testing vend IDs once (cached 1h) so the aggregates exclude
        // testing machines — same source as the Sales Transactions totals query,
        // keeping the two pages' figures in sync.
        $testingVendIds = Cache::remember('testing_vend_ids', 3600, fn() =>
            DB::table('vends')->where('is_testing', true)->pluck('id')->map(fn($v) => (int)$v)->all()
        );

        // Base query (shared between paginated rows + totals + count).
        // skipSort = true because filterTransactionIndex doesn't drive sort here.
        $makeBaseQuery = function () use ($request, $testingVendIds) {
            return VendTransaction::query()
                ->leftJoin('payment_methods', 'payment_methods.id', '=', 'vend_transactions.payment_method_id')
                ->leftJoin('vend_channel_errors', 'vend_channel_errors.id', '=', 'vend_transactions.vend_channel_error_id')
                ->filterTransactionIndex($request, true)
                // Exclude testing machines so totals match the Sales Transactions page.
                ->when(!empty($testingVendIds), fn($q) => $q->whereNotIn('vend_transactions.vend_id', $testingVendIds))
                // Unified transactions: exclude PENDING/REFUNDED gateway rows from
                // the daily summary aggregates (no-op for legacy/non-gateway rows).
                ->where('vend_transactions.settlement_status', VendTransaction::SETTLEMENT_SETTLED);
        };

        // Count distinct (date, payment_method_id) groups for pagination.
        $totalGroups = (int) $makeBaseQuery()
            ->select(DB::raw('COUNT(DISTINCT DATE(vend_transactions.transaction_datetime), vend_transactions.payment_method_id) AS cnt'))
            ->value('cnt');

        $records = $makeBaseQuery()
            ->select([
                DB::raw('DATE(vend_transactions.transaction_datetime) AS transaction_date'),
                'vend_transactions.payment_method_id',
                DB::raw('MAX(payment_methods.name) AS payment_method_name'),
                DB::raw('COUNT(*) AS total_transaction_count'),

                DB::raw('CAST(COUNT(CASE
                    WHEN vend_channel_errors.code = 0 OR vend_channel_errors.code = 6 OR vend_channel_errors.code IS NULL OR vend_transactions.is_multiple = true
                    THEN 1 ELSE NULL END) AS SIGNED) AS success_count'),

                DB::raw('ROUND(COALESCE(SUM(vend_transactions.amount), 0), 2) AS total_amount'),

                DB::raw('ROUND(COALESCE(SUM(CASE
                    WHEN vend_channel_errors.code = 0 OR vend_channel_errors.code = 6 OR vend_channel_errors.code IS NULL OR vend_transactions.is_multiple = true
                    THEN vend_transactions.amount ELSE 0 END), 0), 2) AS success_amount'),

                DB::raw('CAST(COUNT(CASE
                    WHEN vend_transactions.is_refunded = 1
                    THEN 1 ELSE NULL END) AS SIGNED) AS refunded_count'),

                DB::raw('ROUND(COALESCE(SUM(CASE
                    WHEN vend_transactions.is_refunded = 1
                    THEN vend_transactions.amount ELSE 0 END), 0), 2) AS refunded_amount'),
            ])
            ->groupBy('transaction_date', 'vend_transactions.payment_method_id')
            ->orderBy($sortKey, $sortDir)
            // tie-breaker so the same group order is stable across pages
            ->orderBy('vend_transactions.payment_method_id')
            ->forPage($currentPage, $perPage)
            ->get();

        $paginator = new LengthAwarePaginator(
            $records,
            $totalGroups,
            $perPage,
            $currentPage,
            [
                'path' => $request->url(),
                'query' => $request->query(),
            ]
        );

        // Shape the response to mirror the API-resource paginator format the
        // shared Paginator.vue / page meta widgets expect (data + links + meta).
        $dailySummary = [
            'data' => $records,
            'links' => [
                'first' => $paginator->url(1),
                'last' => $paginator->url($paginator->lastPage()),
                'prev' => $paginator->previousPageUrl(),
                'next' => $paginator->nextPageUrl(),
            ],
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'from' => $paginator->firstItem(),
                'last_page' => $paginator->lastPage(),
                'links' => $paginator->linkCollection()->toArray(),
                'path' => $paginator->path(),
                'per_page' => $paginator->perPage(),
                'to' => $paginator->lastItem(),
                'total' => $paginator->total(),
            ],
        ];

        // Aggregate totals across all matching rows (regardless of grouping).
        $totals = $makeBaseQuery()
            ->select([
                DB::raw('COUNT(*) AS total_transaction_count'),
                DB::raw('CAST(COUNT(CASE
                    WHEN vend_channel_errors.code = 0 OR vend_channel_errors.code = 6 OR vend_channel_errors.code IS NULL OR vend_transactions.is_multiple = true
                    THEN 1 ELSE NULL END) AS SIGNED) AS success_count'),
                DB::raw('ROUND(COALESCE(SUM(vend_transactions.amount), 0), 2) AS total_amount'),
                DB::raw('ROUND(COALESCE(SUM(CASE
                    WHEN vend_channel_errors.code = 0 OR vend_channel_errors.code = 6 OR vend_channel_errors.code IS NULL OR vend_transactions.is_multiple = true
                    THEN vend_transactions.amount ELSE 0 END), 0), 2) AS success_amount'),
                DB::raw('CAST(COUNT(CASE
                    WHEN vend_transactions.is_refunded = 1
                    THEN 1 ELSE NULL END) AS SIGNED) AS refunded_count'),
                DB::raw('ROUND(COALESCE(SUM(CASE
                    WHEN vend_transactions.is_refunded = 1
                    THEN vend_transactions.amount ELSE 0 END), 0), 2) AS refunded_amount'),
            ])
            ->first();

        $ttl = 86400;
        $paymentMethods = Cache::remember('payment_methods', $ttl, fn() => PaymentMethodResource::collection(
            PaymentMethod::orderBy('name')->get()
        )->resolve());

        $operatorOptions = Cache::remember('operator_options_' . auth()->user()->operator_id, $ttl, fn() => OperatorResource::collection(
            Operator::orderBy('name')->get()
        )->resolve());

        // Canonical card-terminal names — see indexTransaction() above for
        // why this powers the merged Payment Method dropdown.
        $cardTerminalOptions = Cache::remember('card_terminal_options', $ttl, fn() =>
            CardTerminal::orderBy('name')->pluck('name')->values()
        );

        return Inertia::render('Vend/DailySummary', [
            'cardTerminalOptions' => ['data' => $cardTerminalOptions],
            'paymentMethods' => ['data' => $paymentMethods],
            'operatorOptions' => ['data' => $operatorOptions],
            'dailySummary' => $dailySummary,
            'totals' => $totals,
        ]);
    }

    /**
     * Stream the Daily Summary table as a CSV file.
     * Re-runs the same aggregation as dailySummaryIndex but without pagination.
     */
    public function exportDailySummaryCsv(Request $request)
    {
        if (!$request->has('operators')) {
            if (auth()->user()->operator->code == 'HIPL') {
                $request->merge([
                    'operators' => [
                        auth()->user()->operator_id,
                        Operator::where('code', 'HIMD')->first()?->id,
                        Operator::where('code', 'LEA')->first()?->id,
                        Operator::where('code', 'HIESG')->first()?->id,
                        Operator::where('code', 'UL-ST')->first()?->id,
                    ]
                ]);
            } else {
                $request->merge(['operators' => [auth()->user()->operator_id]]);
            }
        }
        $request->merge(['visited' => isset($request->visited) ? $request->visited : true]);
        $request->date_from = $request->date_from ? Carbon::parse($request->date_from)->setTimezone($this->getUserTimezone())->startOfDay() : Carbon::today()->setTimezone($this->getUserTimezone())->startOfDay();
        $request->date_to = $request->date_to ? Carbon::parse($request->date_to)->setTimezone($this->getUserTimezone())->endOfDay() : Carbon::today()->setTimezone($this->getUserTimezone())->endOfDay();

        // Keep the export in sync with the on-screen table (and the Sales
        // Transactions page): exclude testing machines and PENDING/REFUNDED
        // gateway rows.
        $testingVendIds = Cache::remember('testing_vend_ids', 3600, fn() =>
            DB::table('vends')->where('is_testing', true)->pluck('id')->map(fn($v) => (int)$v)->all()
        );

        $rows = VendTransaction::query()
            ->leftJoin('payment_methods', 'payment_methods.id', '=', 'vend_transactions.payment_method_id')
            ->leftJoin('vend_channel_errors', 'vend_channel_errors.id', '=', 'vend_transactions.vend_channel_error_id')
            ->filterTransactionIndex($request, true)
            ->when(!empty($testingVendIds), fn($q) => $q->whereNotIn('vend_transactions.vend_id', $testingVendIds))
            ->where('vend_transactions.settlement_status', VendTransaction::SETTLEMENT_SETTLED)
            ->select([
                DB::raw('DATE(vend_transactions.transaction_datetime) AS transaction_date'),
                'vend_transactions.payment_method_id',
                DB::raw('MAX(payment_methods.name) AS payment_method_name'),
                DB::raw('COUNT(*) AS total_transaction_count'),
                DB::raw('CAST(COUNT(CASE
                    WHEN vend_channel_errors.code = 0 OR vend_channel_errors.code = 6 OR vend_channel_errors.code IS NULL OR vend_transactions.is_multiple = true
                    THEN 1 ELSE NULL END) AS SIGNED) AS success_count'),
                DB::raw('ROUND(COALESCE(SUM(vend_transactions.amount), 0), 2) AS total_amount'),
                DB::raw('ROUND(COALESCE(SUM(CASE
                    WHEN vend_channel_errors.code = 0 OR vend_channel_errors.code = 6 OR vend_channel_errors.code IS NULL OR vend_transactions.is_multiple = true
                    THEN vend_transactions.amount ELSE 0 END), 0), 2) AS success_amount'),
                DB::raw('CAST(COUNT(CASE
                    WHEN vend_transactions.is_refunded = 1
                    THEN 1 ELSE NULL END) AS SIGNED) AS refunded_count'),
                DB::raw('ROUND(COALESCE(SUM(CASE
                    WHEN vend_transactions.is_refunded = 1
                    THEN vend_transactions.amount ELSE 0 END), 0), 2) AS refunded_amount'),
            ])
            ->groupBy('transaction_date', 'vend_transactions.payment_method_id')
            ->orderByDesc('transaction_date')
            ->orderBy('vend_transactions.payment_method_id')
            ->get();

        $country = auth()->user()->operator?->country;
        $currencyExponent = $country?->currency_exponent ?? 2;
        $divisor = pow(10, $currencyExponent);

        $filename = 'Daily_Summary_' . now()->format('Ymd_His') . '.csv';

        return (new FastExcel($this->yieldOneByOne($rows)))->download($filename, function ($row) use ($divisor, $currencyExponent) {
            $total = (int) $row->total_transaction_count;
            $success = (int) $row->success_count;
            $rate = $total > 0 ? round(($success / $total) * 100, 2) : 0.0;
            return [
                'Date' => $row->transaction_date,
                'Payment Method' => $row->payment_method_name ?? '—',
                'Total Transactions' => $total,
                'Successful Transactions' => $success,
                'Success Rate (%)' => $rate,
                'Total Amount' => round(((float) $row->total_amount) / $divisor, $currencyExponent),
                'Successful Amount' => round(((float) $row->success_amount) / $divisor, $currencyExponent),
                'Refunded Count' => (int) $row->refunded_count,
                'Refunded Amount' => round(((float) $row->refunded_amount) / $divisor, $currencyExponent),
            ];
        });
    }

    public function pickLists(Request $request)
    {
        // dd($request->all());
        $dataArr = [];
        $input = collect($request->all());
        $items = VendChannel::query()
            ->with([
                'product:id,code,name,desc,is_available',
                'product.thumbnail:id,full_url,attachments.modelable_id,attachments.modelable_type',
            ])
            ->leftJoin('products', 'products.id', '=', 'vend_channels.product_id')
            ->where('vend_channels.is_active', true)
            ->whereIn('vend_id', $input->pluck('vend_id')->toArray())
            ->select(
                'product_id',
                DB::raw('CAST(SUM(capacity - qty) AS UNSIGNED) as topup_qty'),
            )
            ->groupBy('product_id')
            ->orderBy('products.code')
            ->having('topup_qty', '>', 0)
            ->get();

        $dataArr = [
            'items' => $items->toArray(),
            'vends' => $input->map(function ($item) {
                return [
                    'code' => $item['code'] ?? null,
                    'customer_id' => $item['customer_id'] ?? null,
                    'customer_code' => $item['customer_code'] ?? null,
                    'customer_name' => $item['customer_name'] ?? null,
                    'person_id' => $item['person_id'] ?? null,
                ];
            }),
        ];

        return $dataArr;
    }

    public function update(Request $request, $vendID)
    {
        $isProductMappingChanged = false;
        // status assignment
        if ($request->status) {
            $status = $request->status;
            switch ($status) {
                case 'factory':
                    $request->merge([
                        'is_active' => false,
                        'is_testing' => true,
                        'is_disposed' => false,
                        'is_sold' => false,
                    ]);
                    break;
                case 'active':
                    $request->merge([
                        'is_active' => true,
                        'is_testing' => false,
                        'is_disposed' => false,
                        'is_sold' => false,
                    ]);
                    break;
                case 'inactive':
                    $request->merge([
                        'is_active' => false,
                        'is_testing' => false,
                        'is_disposed' => false,
                        'is_sold' => false,
                    ]);
                    break;
                case 'disposed':
                    $request->merge([
                        'is_active' => false,
                        'is_testing' => false,
                        'is_disposed' => true,
                        'is_sold' => false,
                    ]);
                    break;
                case 'sold':
                    $request->merge([
                        'is_active' => false,
                        'is_testing' => false,
                        'is_disposed' => false,
                        'is_sold' => true,
                    ]);
                    break;
            }
        }

        $vend = Vend::findOrFail($vendID);

        if ($request->product_mapping_id != $vend->product_mapping_id) {
            $newProductMapping = ProductMapping::find($request->product_mapping_id);
            $newUpcomingId = $newProductMapping ? $newProductMapping->upcoming_product_mapping_id : null;
            // Guard: upcoming must never equal the new product_mapping_id (Gap 1 fix)
            if ($newUpcomingId == $request->product_mapping_id) {
                $newUpcomingId = null;
            }
            $request->merge([
                'upcoming_product_mapping_id' => $newUpcomingId,
            ]);
            $isProductMappingChanged = true;
        }

        // Guard: upcoming must never equal the current product_mapping_id (Gap 2 fix —
        // covers the case where product_mapping_id did NOT change but upcoming was sent raw)
        if (
            $request->upcoming_product_mapping_id &&
            $request->upcoming_product_mapping_id == $request->product_mapping_id
        ) {
            $request->merge(['upcoming_product_mapping_id' => null]);
        }

        if ($request->modem_type_id == null) {
            $request->merge([
                'modem_unit_id' => null,
            ]);
        }

        $isNA = false;
        if ($request->vend_config_id) {
            $config = VendConfig::find($request->vend_config_id);
            if ($config && $config->name === 'N/A') {
                $isNA = true;
            }
        }

        $request->validate([
            'lcd_monitor_id' => 'required',
            'menu_frame_id' => 'required',
            'operator_id' => 'required',
            'product_mapping_id' => $isNA ? 'nullable' : 'required',
            // 'vend_config_id' => 'required',
            'vend_model_id' => 'required',
            'vend_prefix_id' => $isNA ? 'nullable' : 'required',
        ]);

        $vend->update([
            'name' => $request->name,
            'begin_date' => $request->begin_date,
            'key_id' => $request->key_id,
            'card_terminal_id' => $request->card_terminal_id,
            'cashless_terminal_id' => $request->cashless_terminal_id,
            'claw_machine_board_id' => $request->claw_machine_board_id,
            'claw_machine_body_id' => $request->claw_machine_body_id,
            'label_name' => $request->label_name,
            'lcd_monitor_id' => $request->lcd_monitor_id,
            'led_matrix_panel_id' => $request->led_matrix_panel_id,
            'menu_frame_id' => $request->menu_frame_id,
            'modem_type_id' => $request->modem_type_id,
            'modem_unit_id' => $request->modem_unit_id,
            'is_active' => $request->is_active,
            'is_disposed' => $request->is_disposed,
            'is_sold' => $request->is_sold,
            'is_testing' => $request->is_testing,
            'is_fan_enabled' => $request->is_fan_enabled === 'true' || $request->is_fan_enabled === true,
            // 'is_using_server_price' => $request->is_using_server_price,
            'product_mapping_id' => $request->product_mapping_id,
            'serial_num' => $request->serial_num,
            'server_price_type' => $request->server_price_type,
            'simcard_id' => $request->simcard_id,
            'termination_date' => $request->termination_date,
            'upcoming_product_mapping_id' => $request->upcoming_product_mapping_id,
            'vend_config_id' => $request->vend_config_id,
            'vend_contract_id' => $request->vend_contract_id,
            'vend_model_id' => $request->vend_model_id,
            'vend_prefix_id' => $request->vend_prefix_id,
            'vend_serial_number_id' => $request->vend_serial_number_id,
            'vend_vend_config_version' => $request->vend_vend_config_version,
        ]);

        // Bust dashboard vend-ID caches whenever a vend is saved, so that changes
        // to is_testing or customer_id are reflected within the next page load
        // rather than waiting for the 5-minute TTL to expire.
        Cache::forget('testing_vend_ids');
        Cache::forget('exclude_vend_ids_for_active_machine');

        // if($request->modem_unit_imei) {
        //     $vend->modemUnit()->update([
        //         'imei' => $request->modem_unit_imei,
        //         'modem_type_id' => $vend->modem_type_id,
        //     ]);
        // }

        if ($isProductMappingChanged and $vend->product_mapping_id) {
            $vend->binded_at = Carbon::now();
            $vend->save();
            $this->productMappingService->syncChannels($vend->product_mapping_id);
        } else if ($isProductMappingChanged and !$vend->product_mapping_id) {
            $vend->binded_at = null;
            $vend->save();
        }

        if ($request->operator_id != $vend->operator_id) {
            $vend->update([
                'operator_id' => $request->operator_id,
            ]);

            if ($vend->customer) {
                $vend->customer->update([
                    'operator_id' => $request->operator_id,
                ]);
            }
        }

        return redirect()->back();
    }

    public function unbindCustomer($vendID, $returnUrl = null)
    {
        $vend = Vend::findOrFail($vendID);
        $customerID = $vend->customer_id;

        $vend->customer->update([
            // 'is_active' => false,
            'termination_date' => Carbon::now()->toDateString(),
            'snap_parameter_json' => $vend->parameter_json,
            'snap_vend_channels_json' => $vend->vend_channels_json,
            'snap_vend_channel_error_logs_json' => $vend->vend_channel_error_logs_json,
            'snap_vend_status_json' => [
                'coin_count' => $vend->parameter_json && isset($vend->parameter_json['CoinCnt']) ? $vend->parameter_json['CoinCnt'] / 100 : null,
                'is_door_open' => $vend->parameter_json && isset($vend->parameter_json['door']) ? ($vend->parameter_json['door'] == 'open' ? true : false) : false,
                'is_mqtt' => $vend->is_mqtt,
                'is_mqtt_active' => $vend->is_mqtt_active,
                'mqtt_last_updated_at' => $vend->mqtt_last_updated_at,
                'is_online' => $vend->is_online,
                'is_sensor' => $vend->parameter_json && isset($vend->parameter_json['Sensor']) ? ($vend->parameter_json['Sensor'] % 2 == 0 ? true : false) : false,
                'fan_speed' => $vend->parameter_json && isset($vend->parameter_json['fan']) ? $vend->parameter_json['fan'] : null,
                'is_temp_error' => $vend->is_temp_error,
                'last_updated_at' => $vend->last_updated_at,
                't1' => $vend->temp,
                'temp_updated_at' => $vend->temp_updated_at,
                't2' => $vend->parameter_json && isset($vend->parameter_json['t2']) ? $vend->parameter_json['t2'] : null,
                't3' => $vend->parameter_json && isset($vend->parameter_json['t3']) ? $vend->parameter_json['t3'] : null,
                't4' => $vend->parameter_json && isset($vend->parameter_json['t4']) ? $vend->parameter_json['t4'] : null,
                'firmware_ver' => $vend->parameter_json && isset($vend->parameter_json['Ver']) ? $vend->parameter_json['Ver'] : null,
                'apk_ver' => $vend->apk_ver_json && isset($vend->apk_ver_json['apkver']) ? $vend->apk_ver_json['apkver'] : null,
                'apk_ver_build_time' => $vend->apk_ver_json && isset($vend->apk_ver_json['buildtime']) ? $vend->apk_ver_json['buildtime'] : null,
                'location_type_name' => $vend->customer && $vend->customer->locationType ? $vend->customer->locationType->name : null,
                'account_manager_name' => $vend->customer->account_manager_json && isset($vend->customer->account_manager_json['name']) ? $vend->customer->account_manager_json['name'] : null,
            ]
        ]);

        $this->historyService->syncVendCustomerMovement($vend, $vend->customer, false);

        // callback to cms to unbind vendcode
        if ($vend->customer && $vend->customer->person_id) {
            Http::get(env('CMS_URL') . '/api/person/' . $vend->customer->person_id . '/detach-vendcode');
        }

        $vend->customer_id = null;
        $vend->save();

        if ($returnUrl == 'vends') {
            return redirect()->route('vends.edit', [$vendID]);
        } else if ($returnUrl == 'settings') {
            return redirect()->route('settings.edit', [$vendID]);
        } else if ($returnUrl == 'customers') {
            return redirect()->route('customers.edit', [$customerID]);
        } else {
            return redirect()->back();
        }
    }

    public function unbindCustomerDeactivate($vendID, $returnUrl = null)
    {
        $vend = Vend::findOrFail($vendID);
        $customerID = $vend->customer_id;

        $vend->customer->update([
            'is_active' => false,
            'termination_date' => Carbon::now()->toDateString(),
            'snap_parameter_json' => $vend->parameter_json,
            'snap_vend_channels_json' => $vend->vend_channels_json,
            'snap_vend_channel_error_logs_json' => $vend->vend_channel_error_logs_json,
            'snap_vend_status_json' => [
                'coin_count' => $vend->parameter_json && isset($vend->parameter_json['CoinCnt']) ? $vend->parameter_json['CoinCnt'] / 100 : null,
                'is_door_open' => $vend->parameter_json && isset($vend->parameter_json['door']) ? ($vend->parameter_json['door'] == 'open' ? true : false) : false,
                'is_mqtt' => $vend->is_mqtt,
                'is_mqtt_active' => $vend->is_mqtt_active,
                'mqtt_last_updated_at' => $vend->mqtt_last_updated_at,
                'is_online' => $vend->is_online,
                'is_sensor' => $vend->parameter_json && isset($vend->parameter_json['Sensor']) ? ($vend->parameter_json['Sensor'] % 2 == 0 ? true : false) : false,
                'fan_speed' => $vend->parameter_json && isset($vend->parameter_json['fan']) ? $vend->parameter_json['fan'] : null,
                'is_temp_error' => $vend->is_temp_error,
                'last_updated_at' => $vend->last_updated_at,
                't1' => $vend->temp,
                'temp_updated_at' => $vend->temp_updated_at,
                't2' => $vend->parameter_json && isset($vend->parameter_json['t2']) ? $vend->parameter_json['t2'] : null,
                't3' => $vend->parameter_json && isset($vend->parameter_json['t3']) ? $vend->parameter_json['t3'] : null,
                't4' => $vend->parameter_json && isset($vend->parameter_json['t4']) ? $vend->parameter_json['t4'] : null,
                'firmware_ver' => $vend->parameter_json && isset($vend->parameter_json['Ver']) ? $vend->parameter_json['Ver'] : null,
                'apk_ver' => $vend->apk_ver_json && isset($vend->apk_ver_json['apkver']) ? $vend->apk_ver_json['apkver'] : null,
                'apk_ver_build_time' => $vend->apk_ver_json && isset($vend->apk_ver_json['buildtime']) ? $vend->apk_ver_json['buildtime'] : null,
                'location_type_name' => $vend->customer && $vend->customer->locationType ? $vend->customer->locationType->name : null,
                'account_manager_name' => $vend->customer->account_manager_json && isset($vend->customer->account_manager_json['name']) ? $vend->customer->account_manager_json['name'] : null,
            ]
        ]);

        $this->historyService->syncVendCustomerMovement($vend, $vend->customer, false);

        // callback to cms to unbind vendcode
        if ($vend->customer && $vend->customer->person_id) {
            Http::get(env('CMS_URL') . '/api/person/' . $vend->customer->person_id . '/detach-vendcode');
        }

        $vend->customer_id = null;
        $vend->save();

        if ($returnUrl == 'vends') {
            return redirect()->route('vends.edit', [$vendID]);
        } else if ($returnUrl == 'settings') {
            return redirect()->route('settings.edit', [$vendID]);
        } else if ($returnUrl == 'customers') {
            return redirect()->route('customers.edit', [$customerID]);
        } else {
            return redirect()->back();
        }
    }

    public function exportChannelExcel(Request $request)
    {
        $vendChannels = DB::table('vend_channels')
            ->leftJoin('products', 'products.id', '=', 'vend_channels.product_id')
            ->leftJoin('vends', 'vends.id', '=', 'vend_channels.vend_id')
            ->leftJoin('customers', 'customers.id', '=', 'vends.customer_id')
            ->leftJoin('operators', 'operators.id', '=', 'customers.operator_id')
            ->leftJoin('categories', 'categories.id', '=', 'customers.category_id')
            ->leftJoin('category_groups', 'category_groups.id', '=', 'categories.category_group_id')
            ->select(
                'customers.code AS customer_code',
                'customers.name AS customer_name',
                'products.code AS product_code',
                'products.name AS product_name',
                'vend_channels.code AS channel_code',
                'vend_channels.amount',
                'vend_channels.capacity',
                'vend_channels.qty',
                'vends.code AS vend_code',
                'vends.name AS vend_name',
            )
            ->where('capacity', '>', 0)
            ->whereNotNull('vends.customer_id');
        $vendChannels = $this->filterVendChannelsDB($vendChannels, $request);
        $vendChannels = $this->filterOperatorDB($vendChannels, 'vends');
        // Apply operators array filter from the UI (filterOperatorDB only enforces user's own operator restriction)
        if ($request->operators) {
            $operators = is_array($request->operators) ? $request->operators : [$request->operators];
            $operators = array_filter($operators);
            if (!empty($operators) && !in_array('all', $operators)) {
                $vendChannels = $vendChannels->whereIn('vends.operator_id', $operators);
            }
        }
        $vendChannels = $vendChannels->get();

        // dd($vendChannels);
        return (new FastExcel($this->yieldOneByOne($vendChannels)))->download('Vend_channels_' . Carbon::now()->toDateTimeString() . '.xlsx', function ($vendChannel) {
            return [
                'Machine ID' => isset($vendChannel->vend_code) ? $vendChannel->vend_code : '',
                'Customer Name' => $vendChannel->customer_name
                    ? (($vendChannel->customer_code ? $vendChannel->customer_code . ' ' : '') . $vendChannel->customer_name)
                    : ($vendChannel->vend_name ?? ''),
                'Channel' => isset($vendChannel->channel_code) ? $vendChannel->channel_code : '',
                'Product Code' => isset($vendChannel->product_code) ?
                    $vendChannel->product_code :
                    '',
                'Product Name' => isset($vendChannel->product_name) ?
                    $vendChannel->product_name :
                    '',
                'Qty' => isset($vendChannel->qty) ? $vendChannel->qty : '',
                'Capacity' => isset($vendChannel->capacity) ? $vendChannel->capacity : '',
                'Price' => isset($vendChannel->amount) ? $vendChannel->amount / 100 : 0,
                'Balance Percent(%)' => isset($vendChannel->capacity) && $vendChannel->capacity > 0 ? round($vendChannel->qty / $vendChannel->capacity * 100) : 0,
            ];
        });
    }


    public function edit(Request $request, $id)
    {
        $vend = Vend::query()
            ->with([
                'customer',
                'customer.deliveryAddress',
                'customer.contact',
                'logs',
                // modemUnit + modemType:is_resetable are needed by the
                // "Reset Modem" button on the Advance Control page.
                'modemUnit',
                'modemUnit.modemType:id,name,is_resetable',
            ])
            ->leftJoin('customers', 'customers.id', '=', 'vends.customer_id')
            ->leftJoin('location_types', 'location_types.id', '=', 'customers.location_type_id')
            ->leftJoin('operators', 'operators.id', '=', 'vends.operator_id')
            ->leftJoin('product_mappings', 'product_mappings.id', '=', 'vends.product_mapping_id')
            ->leftJoin('addresses', function ($query) {
                $query->on('addresses.modelable_id', '=', 'customers.id')
                    ->where('addresses.modelable_type', '=', 'App\Models\Customer')
                    ->where('addresses.type', '=', 2)
                    ->limit(1);
            })
            ->where('vends.id', $id)
            ->select(
                'vends.id',
                'vends.code',
                // FK required by the eager-loaded `modemUnit` belongsTo
                // relationship — omitting it would leave modemUnit null.
                'vends.modem_unit_id',
                'customers.id AS customer_id',
                DB::raw('CASE WHEN customers.person_id IS NOT NULL THEN CONCAT(IFNULL(customers.virtual_customer_code, \'\')," (",IFNULL(customers.virtual_customer_prefix, \'\'),")") ELSE customers.code END AS customer_code'),
                'customers.name AS customer_name',
                'customers.person_id',
                'vends.begin_date',
                'vends.termination_date',
                DB::raw('CASE WHEN vends.is_testing THEN true ELSE false END AS is_testing'),
                DB::raw('CASE WHEN vends.is_active THEN true ELSE false END AS is_active'),
            )
            ->first();

        return Inertia::render('Vend/Edit', [
            'adminCustomerOptions' => CustomerResource::collection(
                Customer::query()
                    ->select(
                        'id',
                        'code',
                        'name',
                        'is_active',
                        'person_id',
                        'person_json',
                        'virtual_customer_code',
                        'virtual_customer_prefix',
                        'operator_id'
                    )
                    ->doesntHave('vend')
                    ->orderBy('created_at', 'desc')
                    ->get()
            ),
            'countries' => CountryResource::collection(Country::orderBy('sequence')->orderBy('name')->get()),
            'operatorOptions' => OperatorResource::collection(
                Operator::all()
            ),
            'type' => 'update',
            'vend' => $vend,
        ]);
    }

    public function logs(Request $request, Vend $vend)
    {
        $perPage = (int) $request->input('per_page', 10);

        // Ensure real-time connectivity/temp mathematical alerts are forcefully written
        // to the database log before fetching the response so the UI and history align
        if ($vend->is_active && !$vend->is_testing) {
            (new \App\Jobs\DetectTempTrends($vend->id, true))->handle();
        }

        // Vend logs are only retained for 90 days. Constrain the query so the
        // pagination/total reflects the retention policy even if the daily
        // prune command (delete:vend-log) hasn't run yet.
        $retentionCutoff = Carbon::now()->subDays(90);

        $logs = $vend->eventLogs()
            ->select(['id', 'event', 'subject', 'context', 'occurred_at', 'created_at'])
            ->where(function ($query) use ($retentionCutoff) {
                $query->where('occurred_at', '>=', $retentionCutoff)
                    ->orWhere(function ($q) use ($retentionCutoff) {
                        $q->whereNull('occurred_at')
                            ->where('created_at', '>=', $retentionCutoff);
                    });
            })
            ->orderByDesc('occurred_at')
            ->paginate($perPage > 0 ? $perPage : 10);

        return response()->json([
            'data' => $logs->items(),
            'meta' => [
                'current_page' => $logs->currentPage(),
                'per_page' => $logs->perPage(),
                'has_more_pages' => $logs->hasMorePages(),
                'next_page' => $logs->currentPage() + 1,
                'total' => $logs->total(),
            ],
        ]);
    }

    public function editProducts(Request $request, $vendId)
    {
        $vend = Vend::findOrFail($vendId);
        $channels = $request->channels;

        foreach ($channels as $channel) {
            if ($channel['product_id'] === $channel['edited_product_id']) {
                continue;
            } else {
                $vendChannel = VendChannel::findOrFail($channel['id']);
                $vendChannel->update([
                    'product_id' => $channel['edited_product_id'],
                ]);
            }
        }
        SaveVendChannelsJson::dispatch($vend->id)->onQueue('default');
    }

    public function dispenseProduct(Request $request)
    {
        $channelId = $request->channel_id;
        $vendChannel = VendChannel::findOrFail($channelId);

        $orderId = $this->runningNumberService->getVendOrderID($vendChannel->vend);

        $result = $this->vendDispenseService->getSingleParam([
            'orderId' => $orderId,
            'amount' => 0,
            'vendCode' => $vendChannel->vend->code,
            'productCode' => 0,
            'productName' => '',
            'channelCode' => $vendChannel->code,
            'paymentMethod' => 11,
        ]);

        $paymentGatewayLog = PaymentGatewayLog::create([
            'order_id' => $orderId,
            'amount' => 0,
            'vend_channel_code' => $vendChannel->code,
            'vend_channel_id' => $vendChannel->id,
            'vend_code' => $vendChannel->vend->code,
            'vend_id' => $vendChannel->vend->id,
            'status' => 2,
        ]);

        $dataArr = [
            'fid' => $vendChannel->id,
            'result' => $result,
            'key' => $vendChannel->vend && $vendChannel->vend->private_key ? $vendChannel->vend->private_key : '123456789110138A',
        ];

        $this->vendDispenseService->dispense($paymentGatewayLog->id, 'CM' . $vendChannel->vend->code, $dataArr);

        return true;
    }

    public function replaceProductMapping($id)
    {
        $vend = Vend::findOrFail($id);

        $newMappingId = $vend->upcoming_product_mapping_id;
        $vend->product_mapping_id = $newMappingId;

        $newProductMapping = ProductMapping::find($newMappingId);
        $newUpcomingId = $newProductMapping ? $newProductMapping->upcoming_product_mapping_id : null;
        // Guard: upcoming must never equal the newly-set product_mapping_id (Gap 3 fix)
        if ($newUpcomingId == $newMappingId) {
            $newUpcomingId = null;
        }
        $vend->upcoming_product_mapping_id = $newUpcomingId;
        $vend->binded_at = Carbon::now();
        
        $vend->save();

        return redirect()->back();
    }

    public function promoteUpcomingProductMapping(Request $request, $id)
    {
        $validated = $request->validate([
            'upcoming_product_mapping_id' => ['required', 'integer', 'exists:product_mappings,id'],
        ]);

        $vend = Vend::with('vendPrefix')->findOrFail($id);
        $vend->product_mapping_id = $validated['upcoming_product_mapping_id'];
        $vend->upcoming_product_mapping_id = null;
        $vend->binded_at = Carbon::now();
        $vend->save();

        if ($vend->vendPrefix) {
            $vend->vendPrefix
                ->productMappings()
                ->syncWithoutDetaching([$validated['upcoming_product_mapping_id']]);
        }

        return redirect()->back();
    }

    public function updateDCVendsCountries($operatorCode)
    {

        $vends = Vend::whereHas('operator', function ($query) use ($operatorCode) {
            $query->where('code', $operatorCode);
        })
            ->get();

        foreach ($vends as $vend) {
            $fid = 1;
            $content = base64_encode(json_encode([
                'Type' => 'TYPEUPDATECOUNTRYCODE',
                'time' => Carbon::now()->timestamp,
                'action' => '',
                'mid' => $vend->code,
            ]));
            $contentLength = strlen($content);
            $key = $vend && $vend->private_key ? $vend->private_key : '123456789110138A';
            $md5 = md5($fid . ',' . $contentLength . ',' . $content . $key);

            // dd('CM'.$vend->code, $fid.','.$contentLength.','.$content.','.$md5);

            PublishMqtt::dispatch('CM' . $vend->code, $fid . ',' . $contentLength . ',' . $content . ',' . $md5)->onQueue('high');
        }
    }

    public function uploadAttachment(Request $request, $id)
    {
        $vend = Vend::findOrFail($id);

        if ($request->files) {
            $files = $request->file('files');
            $dir = 'sys/vends';
            $storedPath = $files->storePublicly($dir);
            $fileName = basename($storedPath);
            $url = Storage::url($storedPath);
            $vend->attachments()->create([
                'type' => 1,
                'full_url' => $url,
                'local_url' => $dir . '/' . $fileName,
            ]);
        }
        return true;
    }

    private function processVendTempTiming($vendTemps)
    {
        if ($vendTemps) {
            for ($i = 0; $i < count($vendTemps); $i++) {
                if ($i > 0) {
                    $past = Carbon::parse($vendTemps[$i - 1]['created_at']);
                    $current = Carbon::parse($vendTemps[$i]['created_at']);
                    $temPast = null;
                    $temCurrent = null;
                    if ($past->diffInMinutes($current) >= 10) {
                        $temPast = $past;
                        $temCurrent = $temPast->copy()->addMinutes(10);
                        while ($temCurrent->diffInMinutes($current) >= 10) {
                            $vendTemps->push([
                                'value' => 'NaN',
                                'created_at' => $temCurrent->copy()->jsonSerialize()
                            ]);
                            $temPast = $temCurrent;
                            $temCurrent = $temCurrent->copy()->addMinutes(10);
                        }
                    }
                    if ($i == count($vendTemps) - 1 and $current->diffInMinutes(Carbon::now()) >= 10) {
                        $temCurrent = $current;
                        while ($temCurrent->diffInMinutes(Carbon::now()) >= 10) {
                            $vendTemps->push([
                                'value' => 'NaN',
                                'created_at' => $temCurrent->copy()->jsonSerialize()
                            ]);
                            $temCurrent = $temCurrent->copy()->addMinutes(10);
                        }
                    }
                }
            }
        }
    }
    /**
     * Pre-Search aggregate cards for Vend/CustomerIndex ("Last 30 days" +
     * "Current"), computed across ALL rows matching the current filters —
     * deliberately NOT capped by numberPerPage. Used on the initial page load
     * (autoload=false) so the operator sees fleet-level numbers before
     * clicking Search; the heavy table query stays Search-only.
     *
     * The math mirrors, 1:1, what the page shows after a Search:
     *  - the backend $totals block in indexCustomer() for the L30d card, and
     *  - CustomerIndex.vue's `currentStats` computed for the Current card
     *    (same per-metric denominators, same fleet-relative green baseline).
     * Keep all three in sync when changing any of them.
     *
     * Only the slim set of columns the card math needs is selected (no eager
     * loads, no VendResource) — see the OpsJob/Edit QUIC lesson: never ship
     * heavy payloads for ancillary UI.
     */
    private function computeCustomerIndexCardStats(Request $request): array
    {
        // Same unconditional joins as the main indexCustomer query so every
        // filterVendsDB/filterOperatorDB branch finds the tables it expects.
        $query = Customer::query()
            ->leftJoin('vends', 'vends.customer_id', '=', 'customers.id')
            ->leftJoin('categories', 'categories.id', '=', 'customers.category_id')
            ->leftJoin('category_groups', 'category_groups.id', '=', 'categories.category_group_id')
            ->leftJoin('location_types', 'location_types.id', '=', 'customers.location_type_id')
            ->leftJoin('operators', 'operators.id', '=', 'customers.operator_id')
            ->leftJoin('product_mappings', 'product_mappings.id', '=', 'vends.product_mapping_id')
            ->leftJoin('zones', 'zones.id', '=', 'customers.zone_id')
            ->leftJoin('addresses', function ($query) {
                $query->on('addresses.modelable_id', '=', 'customers.id')
                    ->where('addresses.modelable_type', '=', 'App\Models\Customer')
                    ->where('addresses.type', '=', 2);
            })
            ->leftJoin('vend_configs', 'vend_configs.id', '=', 'vends.vend_config_id')
            ->leftJoin('vend_prefixes', 'vend_prefixes.id', '=', 'vends.vend_prefix_id')
            ->leftJoin('card_terminals', 'card_terminals.id', '=', 'vends.card_terminal_id');

        $query = $this->filterVendsDB($query, $request);
        $query = $this->filterOperatorDB($query, 'customers');

        // reorder() drops the ORDER BY filterVendsDB added — sorting is
        // irrelevant for aggregation and some sort keys reference SELECT
        // aliases this slim query doesn't expose.
        $rows = $query->reorder()
            ->select([
                'customers.id AS id',
                'customers.id AS customer_id',
                'vends.id AS vend_id',
                'customers.totals_json AS vend_transaction_totals_json',
                'customers.operator_id',
                'customers.contract_commission_type',
                'customers.contract_commission_value',
                'customers.contract_commission_value2',
                'customers.contract_ps_term',
                'customers.is_external_subsidize',
                'customers.external_subsidize_amount',
                'vends.balance_percent',
                'vends.out_of_stock_sku_percent',
                'vends.virtual_vend_records_thirty_days_amount_average',
                // Presence flag only — the full channel JSON is heavy and the
                // card math just needs "does this machine report stock data".
                DB::raw('(vends.vend_channel_totals_json IS NOT NULL) AS has_channel_totals'),
            ])
            ->get();

        // Batched per-collection aggregates (same helpers the Search path
        // uses): Refillable Value + L30d stock-in / # of job done.
        $this->loadAggregates($rows, ['vc_stock', 'last_thirty_days_stock_in']);

        // "# of Job, next day" — replicate Customer::nextOpsJobItem() (oldest
        // pending item whose job is dated >= today) per customer in one query,
        // then keep those whose next job lands tomorrow. Counted per ROW below
        // (a multi-machine customer contributes one row per machine, matching
        // the frontend's per-row currentStats loop).
        $tomorrow = Carbon::tomorrow()->toDateString();
        $customerIds = $rows->pluck('customer_id')->filter()->unique()->values()->all();
        $nextDayCustomerIds = [];
        if (!empty($customerIds)) {
            $placeholders = implode(',', array_fill(0, count($customerIds), '?'));
            $nextDayRows = DB::select("
                SELECT t.customer_id FROM (
                    SELECT oji.customer_id, oj.date,
                           ROW_NUMBER() OVER (PARTITION BY oji.customer_id ORDER BY oji.created_at ASC) AS rn
                    FROM ops_job_items oji
                    INNER JOIN ops_jobs oj ON oj.id = oji.ops_job_id AND oj.date >= CURDATE()
                    WHERE oji.customer_id IN ($placeholders)
                      AND oji.status < 3 AND oji.status <> 99
                ) AS t
                WHERE t.rn = 1 AND DATE(t.date) = ?
            ", array_merge($customerIds, [$tomorrow]));
            $nextDayCustomerIds = array_flip(array_map(fn ($r) => (int) $r->customer_id, $nextDayRows));
        }

        // Operator GST lookup for the PS-family location-fee math (same
        // pre-load the Search path uses).
        $gstRates = DB::table('operators')
            ->pluck('gst_vat_rate', 'id')
            ->map(fn ($v) => (float) $v)
            ->all();

        // Pass 1 — fleet-wide "Overall Avg/day" baseline for the green % card
        // (mean of L30D avg daily sales over VMs that report an avg/day).
        // Cents on both sides here vs dollars on the frontend — the >=
        // comparison is scale-invariant so parity holds.
        $baselineSum = 0.0;
        $baselineN = 0;
        foreach ($rows as $row) {
            $json = $row->vend_transaction_totals_json;
            if (is_array($json) && array_key_exists('vend_records_amount_average_day', $json)) {
                $baselineSum += (float) ($row->virtual_vend_records_thirty_days_amount_average ?? 0);
                $baselineN++;
            }
        }
        $overallAvgDaily = $baselineN > 0 ? $baselineSum / $baselineN : 0.0;

        // Pass 2 — everything else in a single per-row loop.
        $n = $rows->count();
        $sumThirtyDays = 0;          // cents
        $sumStockIn = 0.0;           // cents
        $sumVendEarning = 0;         // cents
        $sumLocationFees = 0;        // cents
        $sumJobsDone = 0;
        $stockTotal = 0; $sumQtyBal = 0.0; $sumSkuBal = 0.0;
        $errTotal = 0; $sumErr = 0.0;
        $greenTotal = 0; $greenCount = 0;
        $salesUpTotal = 0; $salesUpCount = 0;
        $refill150 = 0; $refill200 = 0; $refill250 = 0; $refill350 = 0; $refill450 = 0;
        $nextDayJobCount = 0;

        foreach ($rows as $row) {
            $json = $row->vend_transaction_totals_json;
            if (is_array($json)) {
                // L30d Sales / VendEarning / LocEarning — identical formulas
                // to the Search path's $totals block (incl-GST sales basis,
                // fee net of External Subsidize for VendEarning, gross fee
                // for LocEarning).
                $salesCents = (int) ($json['thirty_days_amount'] ?? 0);
                $grossEarningCents = (int) ($json['thirty_days_gross_profit'] ?? 0);
                $gstRatePct = (float) ($gstRates[$row->operator_id] ?? 0);
                $locationFeeCents = CustomerSummaryAggregator::computeLocationFeeCents(
                    $row->contract_commission_type,
                    $row->contract_commission_value !== null ? (float) $row->contract_commission_value : null,
                    $row->contract_commission_value2 !== null ? (float) $row->contract_commission_value2 : null,
                    $row->contract_ps_term !== null ? (float) $row->contract_ps_term : null,
                    $salesCents,
                    $grossEarningCents,
                    $gstRatePct
                );
                $extSubCents = ($row->is_external_subsidize && $row->external_subsidize_amount !== null)
                    ? (int) round(((float) $row->external_subsidize_amount) * 100)
                    : 0;
                $sumThirtyDays += $salesCents;
                $sumVendEarning += $grossEarningCents - ($locationFeeCents - $extSubCents);
                $sumLocationFees += $locationFeeCents;

                // % of VM, Sales L30D > LMth
                if (array_key_exists('thirty_days_amount', $json) && array_key_exists('last_mth_amount', $json)) {
                    $salesUpTotal++;
                    if ((float) ($json['thirty_days_amount'] ?? 0) > (float) ($json['last_mth_amount'] ?? 0)) {
                        $salesUpCount++;
                    }
                }
                // Today Error rate — only over machines that report one.
                if (array_key_exists('one_day_error_rate', $json)) {
                    $errTotal++;
                    $sumErr += (float) ($json['one_day_error_rate'] ?? 0);
                }
                // % of VM at/above the fleet Overall Avg/day baseline.
                if (array_key_exists('vend_records_amount_average_day', $json)) {
                    $greenTotal++;
                    if ((float) ($row->virtual_vend_records_thirty_days_amount_average ?? 0) >= $overallAvgDaily) {
                        $greenCount++;
                    }
                }
            }

            $sumStockIn += (float) ($row->last_thirty_days_stock_in_amount ?? 0);
            $sumJobsDone += (int) ($row->last_thirty_days_jobs_done_count ?? 0);

            // Stock Qty/SKU Bal — only over machines with channel stock data.
            if ($row->has_channel_totals) {
                $stockTotal++;
                $sumQtyBal += (float) ($row->balance_percent ?? 0);
                $sumSkuBal += 100 - (float) ($row->out_of_stock_sku_percent ?? 0);
            }

            // Refillable Value thresholds — /100 to currency units, matching
            // VendResource's serialisation the frontend compares against.
            $refillVal = ((float) ($row->actual_stock_in_value ?? 0)) / 100;
            if ($refillVal > 150) $refill150++;
            if ($refillVal > 200) $refill200++;
            if ($refillVal > 250) $refill250++;
            if ($refillVal > 350) $refill350++;
            if ($refillVal > 450) $refill450++;

            if (isset($nextDayCustomerIds[(int) $row->customer_id])) {
                $nextDayJobCount++;
            }
        }

        return [
            // All-rows machine count — drives the (Avg / VM) figures.
            'vmCount' => $n,
            // Same keys/units (currency units, not cents) as the Search
            // path's `totals` prop so the template can swap sources directly.
            'totals' => [
                'thirtyDays' => $sumThirtyDays / 100,
                'thirthyDaysStockIn' => $sumStockIn / 100,
                'thirtyDaysVendingEarning' => $sumVendEarning / 100,
                'thirtyDaysLocationFees' => $sumLocationFees / 100,
                'thirtyDaysJobsDone' => $sumJobsDone,
            ],
            // Same shape as CustomerIndex.vue's `currentStats` computed.
            'current' => [
                'total' => $n,
                'stockTotal' => $stockTotal,
                'errTotal' => $errTotal,
                'stockQtyBal' => $stockTotal > 0 ? $sumQtyBal / $stockTotal : 0,
                'stockSkuBal' => $stockTotal > 0 ? $sumSkuBal / $stockTotal : 0,
                'todayError' => $errTotal > 0 ? $sumErr / $errTotal : 0,
                'greenCount' => $greenCount,
                'greenTotal' => $greenTotal,
                'greenPct' => $greenTotal > 0 ? ($greenCount / $greenTotal) * 100 : 0,
                'refillableOver150' => $refill150,
                'refillableOver200' => $refill200,
                'refillableOver250' => $refill250,
                'refillableOver350' => $refill350,
                'refillableOver450' => $refill450,
                'salesUpCount' => $salesUpCount,
                'salesUpTotal' => $salesUpTotal,
                'salesUpPct' => $salesUpTotal > 0 ? ($salesUpCount / $salesUpTotal) * 100 : 0,
                'nextDayJobCount' => $nextDayJobCount,
            ],
        ];
    }

    private function loadAggregates($items, $types = ['vc', 'vc_cost'])
    {
        $vendIds = $items->map(function ($item) {
            return $item->vend_id ?? $item->id;
        })->filter()->unique()->toArray();

        $customerIds = $items->map(function ($item) {
            return $item->customer_id ?? $item->id;
        })->filter()->unique()->toArray();

        if (empty($vendIds) && empty($customerIds)) {
            return $items;
        }

        if (in_array('vc', $types) && !empty($vendIds)) {
            $vcData = DB::table('vend_channels')
                ->select('vend_id', DB::raw('SUM(amount * qty) as total_stock_amount'), DB::raw('SUM(amount * capacity) as total_full_load_amount'))
                ->whereIn('vend_id', $vendIds)
                ->where('is_active', true)
                ->where('capacity', '>', 0)
                ->groupBy('vend_id')
                ->get()
                ->keyBy('vend_id');

            foreach ($items as $item) {
                $vid = $item->vend_id ?? $item->id;
                if (isset($vcData[$vid])) {
                    $item->total_stock_amount = $vcData[$vid]->total_stock_amount;
                    $item->total_full_load_amount = $vcData[$vid]->total_full_load_amount;
                } else {
                    $item->total_stock_amount = 0;
                    $item->total_full_load_amount = 0;
                }
            }
        }

        if (in_array('t1_lowest', $types) && !empty($vendIds)) {
            $t1LowestData = DB::table('vend_temps')
                ->select('vend_id', DB::raw('MIN(value) as t1_lowest_48h'))
                ->whereIn('vend_id', $vendIds)
                ->where('type', 1)
                ->where('value', '!=', 32767)
                ->where('created_at', '>=', DB::raw('NOW() - INTERVAL 48 HOUR'))
                ->groupBy('vend_id')
                ->get()
                ->keyBy('vend_id');

            foreach ($items as $item) {
                $vid = $item->vend_id ?? $item->id;
                $item->t1_lowest_48h = isset($t1LowestData[$vid])
                    ? $t1LowestData[$vid]->t1_lowest_48h
                    : null;
            }
        }

        if (in_array('vc_cost', $types) && !empty($vendIds)) {
            // products join removed — vend_channels.product_id links directly to
            // unit_costs.product_id with no filtering on products itself, so the
            // intermediate join was pure overhead (an extra PK lookup per row).
            $vcCostData = DB::table('vend_channels')
                ->join('unit_costs', 'vend_channels.product_id', '=', 'unit_costs.product_id')
                ->select('vend_channels.vend_id', DB::raw('SUM(vend_channels.qty * unit_costs.cost) as total_stock_cost'))
                ->where('unit_costs.is_current', true)
                ->where('vend_channels.is_active', true)
                ->where('vend_channels.capacity', '>', 0)
                ->whereIn('vend_channels.vend_id', $vendIds)
                ->groupBy('vend_channels.vend_id')
                ->get()
                ->keyBy('vend_id');

            foreach ($items as $item) {
                $vid = $item->vend_id ?? $item->id;
                if (isset($vcCostData[$vid])) {
                    $item->total_stock_cost = $vcCostData[$vid]->total_stock_cost;
                } else {
                    $item->total_stock_cost = 0;
                }
            }
        }

        if (in_array('vc_stock', $types) && !empty($vendIds)) {
            $vcStockData = DB::table('vend_channels')
                ->join('products', 'vend_channels.product_id', '=', 'products.id')
                ->leftJoinSub(function ($query) {
                    // Replace ROW_NUMBER() window function with a MAX(id) GROUP BY approach.
                    // The idx_date_product_created index covers (date, product_id) so the
                    // MAX(id) aggregation reads entirely from the index without heap access
                    // (InnoDB stores the PK in every secondary index leaf node). The old
                    // ROW_NUMBER() approach materialised all rows for tomorrow, ran a filesort
                    // within each product_id partition, then filtered to rn = 1 — all
                    // avoidable for a simple "latest record per group" operation.
                    $query->select('pl.product_id', 'pl.qty', 'pl.id')
                        ->from('product_limits as pl')
                        ->join(
                            DB::raw('(SELECT product_id, MAX(id) AS max_id FROM product_limits WHERE `date` = CURDATE() + INTERVAL 1 DAY GROUP BY product_id) AS latest_pl'),
                            'pl.id', '=', DB::raw('latest_pl.max_id')
                        );
                }, 'product_limits', 'products.id', '=', 'product_limits.product_id')
                ->select(
                    'vend_channels.vend_id',
                    DB::raw('SUM(vend_channels.amount * GREATEST(CASE WHEN product_limits.id AND product_limits.qty < vend_channels.capacity THEN (product_limits.qty - vend_channels.qty) ELSE (vend_channels.capacity - vend_channels.qty) END, 0)) AS actual_stock_in_value'),
                    DB::raw('SUM(GREATEST(CASE WHEN product_limits.id AND product_limits.qty < vend_channels.capacity THEN (product_limits.qty - vend_channels.qty) ELSE (vend_channels.capacity - vend_channels.qty) END, 0)) AS actual_stock_in_qty')
                )
                ->where('products.is_available', true)
                ->where('vend_channels.is_active', true)
                ->where('vend_channels.capacity', '>', 0)
                ->whereIn('vend_channels.vend_id', $vendIds)
                ->groupBy('vend_channels.vend_id')
                ->get()
                ->keyBy('vend_id');

            foreach ($items as $item) {
                $vid = $item->vend_id ?? $item->id;
                if (isset($vcStockData[$vid])) {
                    $item->actual_stock_in_value = $vcStockData[$vid]->actual_stock_in_value;
                    $item->actual_stock_in_qty = $vcStockData[$vid]->actual_stock_in_qty;
                } else {
                    $item->actual_stock_in_value = 0;
                    $item->actual_stock_in_qty = 0;
                }
            }
        }

        if ((in_array('last_ops_jobs', $types) || in_array('last_second_ops_jobs', $types)) && !empty($customerIds)) {
            $placeholders = implode(',', array_fill(0, count($customerIds), '?'));
            // ROW_NUMBER() on a pre-filtered customer set replaces the CROSS JOIN LATERAL approach.
            //
            // The old LATERAL query used an outer "SELECT DISTINCT customer_id FROM ops_job_items
            // WHERE customer_id IN (?)" + per-customer correlated subqueries with UNION ALL. Despite
            // idx_oji_cust_created covering the lookups, MySQL's LATERAL execution overhead per
            // customer (correlated subquery setup + UNION ALL materialisation) was ~180ms/customer,
            // totalling 9s for 50 customers.
            //
            // The new approach: the inner ROW_NUMBER() subquery fetches only rows belonging to the
            // 50 known customer IDs (idx_oji_cust_created seeks to each customer_id, backward-scans
            // created_at to rank completed jobs). The outer filter rn <= 2 keeps at most 100 rows
            // total, making the subsequent channel joins trivial.
            $data = DB::select("
                SELECT
                    base.customer_id,
                    base.cash_amount,
                    base.acc_total_amount,
                    base.acc_total_count,
                    base.rn,
                    SUM(ojic.actual_qty * vc.amount) AS amount,
                    SUM(ojic.actual_qty) AS count
                FROM (
                    SELECT oji.id, oji.customer_id, oji.cash_amount, oji.acc_total_amount,
                           oji.acc_total_count, oji.ops_job_id,
                           ROW_NUMBER() OVER (PARTITION BY oji.customer_id ORDER BY oji.created_at DESC) AS rn
                    FROM ops_job_items oji
                    WHERE oji.customer_id IN ($placeholders)
                    AND oji.status >= 3 AND oji.status <> 99
                ) AS base
                INNER JOIN ops_job_item_channels ojic ON base.id = ojic.ops_job_item_id
                INNER JOIN vend_channels vc ON ojic.vend_channel_id = vc.id
                INNER JOIN ops_jobs oj ON base.ops_job_id = oj.id
                WHERE base.rn <= 2 AND oj.date < CURDATE() + INTERVAL 1 DAY
                GROUP BY base.customer_id, base.rn
            ", $customerIds);

            $groupedData = collect($data)->groupBy('customer_id');

            foreach ($items as $item) {
                $cid = $item->customer_id ?? $item->id;
                $customerData = $groupedData->get($cid);

                // Last Ops Jobs
                if (in_array('last_ops_jobs', $types)) {
                    $row = $customerData?->firstWhere('rn', 1);
                    if ($row) {
                        $item->last_ops_job_acc_total_amount = $row->acc_total_amount;
                        $item->last_ops_job_acc_total_count = $row->acc_total_count;
                        $item->last_ops_job_amount = $row->amount;
                        $item->last_ops_job_cash_amount = $row->cash_amount;
                        $item->last_ops_job_count = $row->count;
                    } else {
                        $item->last_ops_job_acc_total_amount = null;
                        $item->last_ops_job_acc_total_count = null;
                        $item->last_ops_job_amount = null;
                        $item->last_ops_job_cash_amount = null;
                        $item->last_ops_job_count = null;
                    }
                }

                // Last Second Ops Jobs
                if (in_array('last_second_ops_jobs', $types)) {
                    $row = $customerData?->firstWhere('rn', 2);
                    if ($row) {
                        $item->last_second_ops_job_acc_total_amount = $row->acc_total_amount;
                        $item->last_second_ops_job_acc_total_count = $row->acc_total_count;
                        $item->last_second_ops_job_amount = $row->amount;
                        $item->last_second_ops_job_cash_amount = $row->cash_amount;
                        $item->last_second_ops_job_count = $row->count;
                    } else {
                        $item->last_second_ops_job_acc_total_amount = null;
                        $item->last_second_ops_job_acc_total_count = null;
                        $item->last_second_ops_job_amount = null;
                        $item->last_second_ops_job_cash_amount = null;
                        $item->last_second_ops_job_count = null;
                    }
                }
            }
        }


        if (in_array('next_ops_jobs', $types) && !empty($customerIds)) {
            $placeholders = implode(',', array_fill(0, count($customerIds), '?'));
            $data = DB::select("
                SELECT oji.customer_id, oji.cash_amount,
                    SUM(ojic.picked_qty * vc.amount) AS amount,
                    SUM(ojic.picked_qty) AS count
                FROM ops_job_items oji
                INNER JOIN (
                    SELECT oji_inner.customer_id, MIN(oj_inner.date) AS min_date
                    FROM ops_job_items oji_inner
                    INNER JOIN ops_jobs oj_inner ON oji_inner.ops_job_id = oj_inner.id
                    WHERE oji_inner.status < 3
                    AND oj_inner.date >= CURDATE()
                    AND oji_inner.customer_id IN ($placeholders)
                    GROUP BY oji_inner.customer_id
                ) next_job ON next_job.customer_id = oji.customer_id
                INNER JOIN ops_jobs oj ON oji.ops_job_id = oj.id AND oj.date = next_job.min_date
                INNER JOIN ops_job_item_channels ojic ON oji.id = ojic.ops_job_item_id
                INNER JOIN vend_channels vc ON ojic.vend_channel_id = vc.id
                WHERE oji.status < 3
                AND oji.customer_id IN ($placeholders)
                GROUP BY oji.customer_id
            ", array_merge($customerIds, $customerIds));

            $keyedData = collect($data)->keyBy('customer_id');
            foreach ($items as $item) {
                $cid = $item->customer_id ?? $item->id;
                if (isset($keyedData[$cid])) {
                    $row = $keyedData[$cid];
                    $item->next_ops_job_amount = $row->amount;
                    $item->next_ops_job_cash_amount = $row->cash_amount;
                    $item->next_ops_job_count = $row->count;
                } else {
                    $item->next_ops_job_amount = null;
                    $item->next_ops_job_cash_amount = null;
                    $item->next_ops_job_count = null;
                }
            }
        }

        if (in_array('last_thirty_days_stock_in', $types) && !empty($customerIds)) {
            // Drive the join from ops_jobs (idx_oj_date narrows to the 30-day window first),
            // then join outward to ops_job_items → channels. Previously the query started from
            // ops_job_item_channels and the date filter couldn't reduce rows until late in the
            // join chain. The customer_id IN filter is applied on ops_job_items where
            // idx_oji_cust_status_job can satisfy the status + ops_job_id join key.
            $data = DB::table('ops_jobs')
                ->join('ops_job_items', function ($join) use ($customerIds) {
                    $join->on('ops_job_items.ops_job_id', '=', 'ops_jobs.id')
                        ->where('ops_job_items.status', '>=', 3)
                        ->where('ops_job_items.status', '<>', 99)
                        ->whereIn('ops_job_items.customer_id', $customerIds);
                })
                ->join('ops_job_item_channels', 'ops_job_item_channels.ops_job_item_id', '=', 'ops_job_items.id')
                ->join('vend_channels', 'ops_job_item_channels.vend_channel_id', '=', 'vend_channels.id')
                ->select(
                    'ops_job_items.customer_id',
                    DB::raw('SUM(ops_job_item_channels.actual_qty) AS qty'),
                    DB::raw('SUM(ops_job_item_channels.actual_qty * vend_channels.amount) AS amount'),
                    // # of Job Done L30d — DISTINCT because the channel join fans out
                    // one row per channel; we count completed ops_job_items, not rows.
                    DB::raw('COUNT(DISTINCT ops_job_items.id) AS jobs_done_count')
                )
                ->whereBetween('ops_jobs.date', [DB::raw('CURDATE() - INTERVAL 29 DAY'), DB::raw('CURDATE()')])
                ->groupBy('ops_job_items.customer_id')
                ->get()
                ->keyBy('customer_id');

            foreach ($items as $item) {
                $cid = $item->customer_id ?? $item->id;
                if (isset($data[$cid])) {
                    $item->last_thirty_days_stock_in_amount = $data[$cid]->amount;
                    $item->last_thirty_days_stock_in_qty = $data[$cid]->qty;
                    $item->last_thirty_days_jobs_done_count = $data[$cid]->jobs_done_count;
                } else {
                    $item->last_thirty_days_stock_in_amount = 0;
                    $item->last_thirty_days_stock_in_qty = 0;
                    $item->last_thirty_days_jobs_done_count = 0;
                }
            }
        }

        // Calculate derived fields for indexCustomer
        foreach ($items as $item) {
            if (isset($item->vend_transaction_totals_json)) {
                $totals = is_string($item->vend_transaction_totals_json)
                    ? json_decode($item->vend_transaction_totals_json, true)
                    : $item->vend_transaction_totals_json;

                // thirty_days_over_full_load_ratio
                if (!isset($item->thirty_days_over_full_load_ratio)) {
                    if (isset($item->total_full_load_amount) && $item->total_full_load_amount > 0) {
                        $avg = $totals['vend_records_thirty_days_amount_average'] ?? 0;
                        $item->thirty_days_over_full_load_ratio = ($avg * 30 / 100) / ($item->total_full_load_amount / 100);
                    } else {
                        $item->thirty_days_over_full_load_ratio = null;
                    }
                }

                // thirty_days_stock_in_delta_amount and percent
                if (!isset($item->thirty_days_stock_in_delta_amount) && isset($item->last_thirty_days_stock_in_amount)) {
                    $thirtyDaysAmount = $totals['thirty_days_amount'] ?? 0;
                    $stockInAmount = $item->last_thirty_days_stock_in_amount;

                    $item->thirty_days_stock_in_delta_amount = ($stockInAmount / 100) - ($thirtyDaysAmount / 100);

                    if ($stockInAmount > 0) {
                        $item->thirty_days_stock_in_delta_percent = ($item->thirty_days_stock_in_delta_amount) / ($stockInAmount / 100) * 100;
                    } else {
                        $item->thirty_days_stock_in_delta_percent = null;
                    }
                }
            }
        }

        return $items;
    }
}