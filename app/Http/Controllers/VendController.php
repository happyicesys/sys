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
use App\Http\Resources\PaymentMethodResource;
use App\Http\Resources\ProductResource;
use App\Http\Resources\TagResource;
use App\Http\Resources\UserResource;
use App\Http\Resources\VendDBResource;
use App\Http\Resources\VendResource;
use App\Http\Resources\VendChannelResource;
use App\Http\Resources\VendChannelErrorResource;
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
use App\Models\VendContract;
use App\Models\VendData;
use App\Models\VendModel;
use App\Models\VendPrefix;
use App\Models\VendRecord;
use App\Models\VendSnapshot;
use App\Models\VendTemp;
use App\Models\VendTransaction;
use App\Models\VendTransactionItem;
use App\Models\PaymentGateways\Midtrans;
use App\Models\PaymentGateways\Omise;
use App\Models\Zone;
use App\Services\CmsService;
use App\Services\HistoryService;
use App\Services\MapService;
use App\Services\MqttService;
use App\Services\PaymentGatewayService;
use App\Services\ProductMappingService;
use App\Services\RunningNumberService;
use App\Services\VendDataService;
use App\Services\VendDispenseService;
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
use Imagick;
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


    public function __construct(
        CmsService $cmsService,
        HistoryService $historyService,
        MqttService $mqttService,
        PaymentGatewayService $paymentGatewayService,
        RunningNumberService $runningNumberService,
        VendDataService $vendDataService,
        VendDispenseService $vendDispenseService
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
    }

    public function index(Request $request)
    {
        $request->merge(['visited' => isset($request->visited) ? $request->visited : true]);
        if (!isset($request->is_active)) {
            if (
                auth()->user()->hasRole('superadmin') or
                auth()->user()->hasRole('admin') or
                auth()->user()->hasRole('supervisor') or
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
                $relatedCodes = ['HIPL', 'HIMD', 'LEA', 'DCVIC', 'HIESG', 'IP'];

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

        $vends = Vend::query()
            ->with([
                'deliveryProductMappingVends.deliveryProductMapping.deliveryPlatformOperator.deliveryPlatform',
                // 'vendChannels' => function($query) {
                //     $query->select('*')
                //         ->selectRaw("(SELECT amount FROM selling_prices WHERE
                //             selling_prices.product_id = vend_channels.product_id AND
                //             selling_prices.type = (SELECT server_price_type FROM vends WHERE vends.id = vend_channels.vend_id) LIMIT 1) AS server_amount");
                // },
                // // 'vendChannels.latestOpsJobItemChannel',
                // 'vendChannels.product.thumbnail',
                // 'vendChannels.product.sellingPrices',
                // 'vendChannels.vendChannelErrorLogs' => function($query) {
                //     $query->where('created_at', '>=', Carbon::today()->subDays(29));
                // },
                // 'vendChannels.vendChannelErrorLogs.vendChannelError',
            ])
            ->leftJoin('customers', 'vends.customer_id', '=', 'customers.id')
            ->leftJoin('categories', 'categories.id', '=', 'customers.category_id')
            ->leftJoin('category_groups', 'category_groups.id', '=', 'categories.category_group_id')
            ->leftJoin('modem_types', 'modem_types.id', '=', 'vends.modem_type_id')
            ->leftJoin('modem_units', 'modem_units.id', '=', 'vends.modem_unit_id')
            ->leftJoin('operators', 'operators.id', '=', 'vends.operator_id')
            ->leftJoin('product_mappings', 'product_mappings.id', '=', 'vends.product_mapping_id')
            ->leftJoin('vend_prefixes', 'vend_prefixes.id', '=', 'vends.vend_prefix_id')
            ->leftJoin(DB::raw('
                (
                    SELECT vend_id, SUM(amount * qty) AS total_stock_amount, SUM(amount * capacity) AS total_full_load_amount
                    FROM vend_channels
                    WHERE is_active = true
                    AND capacity > 0
                    GROUP BY vend_id
                ) AS vc
            '), 'vc.vend_id', '=', 'vends.id')
            ->leftJoin(DB::raw('
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
            '), 'vc_cost.vend_id', '=', 'vends.id')
            ->select(
                'vends.id AS id',
                'vends.id AS vend_id',
                'vends.amount_average_day',
                'vends.begin_date',
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
                'vend_prefixes.name AS vend_prefix_name',
                'vc.total_full_load_amount',
                'vc.total_stock_amount',
                'vc_cost.total_stock_cost',
                // 'delivery_platforms.slug AS delivery_platform_slug'
            );
        $vends = $this->filterVendsDB($vends, $request);
        $vends = $this->filterOperatorDB($vends, 'vends');
        $vends = $vends->paginate($request->numberPerPage === 'All' ? 10000 : $request->numberPerPage)
            ->withQueryString();

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
                    return $vend->vend_transaction_totals_json ? $vend->vend_transaction_totals_json['vend_records_thirty_days_amount_average'] : 0;
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
            'nextDeliveryDriverOptions' => Customer::query()
                ->where('cms_invoice_history->next_delivery_driver', '!=', null)
                ->select('cms_invoice_history->next_delivery_driver AS name')
                ->distinct()
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
        if (!isset($request->is_active)) {
            if (
                auth()->user()->hasRole('superadmin') or
                auth()->user()->hasRole('admin') or
                auth()->user()->hasRole('supervisor') or
                auth()->user()->hasRole('driver')
            ) {
                $request->merge(['is_active' => 'true']);
            } else {
                $request->merge(['is_active' => 'all']);
            }
        }

        if (!$request->operators) {
            $userOperator = auth()->user()->operator;

            if ($userOperator && $userOperator->code === 'HIPL') {
                $relatedCodes = ['HIPL', 'HIMD', 'LEA', 'DCVIC', 'HIESG', 'IP'];

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

        $shouldAutoload = $request->boolean('autoload', false);
        $perPage = $request->numberPerPage === 'All' ? 10000 : $request->numberPerPage;
        $perPage = $perPage ?: 50;
        $mapApiKey = $this->mapService->getMapApiKeyByUser(auth()->user());

        if ($shouldAutoload) {
            $vends = Customer::query()
                ->with([
                    'deliveryAddress',
                    'nextInvoiceDriver:id,name,username',
                    'lastOpsJobItem:id,ops_job_id,status,vend_id,customer_id',
                    'lastOpsJobItem.opsJob:id,code,date,delivered_by',
                    'lastOpsJobItem.opsJob.deliveredBy:id,name,username',
                    'lastSecondOpsJobItem:id,ops_job_id,status,vend_id,customer_id',
                    'lastSecondOpsJobItem.opsJob:id,code,date,delivered_by',
                    'lastSecondOpsJobItem.opsJob.deliveredBy:id,name,username',
                    'nextOpsJobItem:id,ops_job_id,status,vend_id,customer_id',
                    'nextOpsJobItem.opsJob:id,code,date,delivered_by',
                    'nextOpsJobItem.opsJob.deliveredBy:id,name,username',
                    'nextOpsJobItem.opsJobItemChannels.vendChannel' => function ($query) {
                        $query->with([
                            'vend:id,server_price_type',
                        ]);
                    },
                    // 'vend.vendChannels' => function($query) {
                    //     $query->select('*')
                    //         ->selectRaw("(SELECT amount FROM selling_prices WHERE
                    //             selling_prices.product_id = vend_channels.product_id AND
                    //             selling_prices.type = (SELECT server_price_type FROM vends WHERE vends.id = vend_channels.vend_id) LIMIT 1) AS server_amount");
                    // },
                    // 'vend.vendChannels.latestOpsJobItemChannel:id,actual_qty,vend_channel_id',
                    // 'vend.vendChannels.product.thumbnail',
                    // 'vend.vendChannels.product.sellingPrices',
                    // 'vend.vendChannels.vendChannelErrorLogs' => function($query) {
                    //     $query->where('created_at', '>=', Carbon::today()->subDays(29));
                    // },
                    // 'vend.vendChannels.vendChannelErrorLogs.vendChannelError',
                    'vend.modemUnit',
                    'vend.deliveryProductMappingVends:id,vend_id,delivery_product_mapping_id',
                    'vend.deliveryProductMappingVends.deliveryProductMapping:id,delivery_platform_operator_id',
                    'vend.deliveryProductMappingVends.deliveryProductMapping.deliveryPlatformOperator:id,delivery_platform_id',
                    'vend.deliveryProductMappingVends.deliveryProductMapping.deliveryPlatformOperator.deliveryPlatform:id,name'
                ])
                ->leftJoin('vends', 'vends.customer_id', '=', 'customers.id')
                ->leftJoin('vend_prefixes', 'vend_prefixes.id', '=', 'vends.vend_prefix_id')
                ->leftJoin('categories', 'categories.id', '=', 'customers.category_id')
                ->leftJoin('category_groups', 'category_groups.id', '=', 'categories.category_group_id')
                ->leftJoin('location_types', 'location_types.id', '=', 'customers.location_type_id')
                ->leftJoin('operators', 'operators.id', '=', 'customers.operator_id')
                ->leftJoin('product_mappings', 'product_mappings.id', '=', 'vends.product_mapping_id')
                ->leftJoin('zones', 'zones.id', '=', 'customers.zone_id')
                ->leftJoin('addresses', function ($query) {
                    $query->on('addresses.modelable_id', '=', 'customers.id')
                        ->where('addresses.modelable_type', '=', 'App\Models\Customer')
                        ->where('addresses.type', '=', 2)
                        ->limit(1);
                })
                ->leftJoin(DB::raw('
                (
                    SELECT vend_id, SUM(amount * qty) AS total_stock_amount, SUM(amount * capacity) AS total_full_load_amount
                    FROM vend_channels
                    WHERE is_active = true
                    AND capacity > 0
                    GROUP BY vend_id
                ) AS vc
            '), 'vc.vend_id', '=', 'vends.id')
                ->leftJoin(DB::raw('
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
            '), 'vc_cost.vend_id', '=', 'vends.id')
                ->leftJoin(DB::raw('
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
                            SELECT id, product_id, qty, date
                            FROM (
                                SELECT id, product_id, qty, date,
                                    ROW_NUMBER() OVER (PARTITION BY product_id ORDER BY id DESC) as rn
                                FROM product_limits
                                WHERE date = CURDATE()
                            ) pl_inner
                            WHERE rn = 1
                        ) AS product_limits ON products.id = product_limits.product_id
                    WHERE
                        products.is_available = true
                    AND vend_channels.is_active = true
                    AND vend_channels.capacity > 0
                    GROUP BY
                        vend_channels.vend_id
                ) AS vc_stock
            '), 'vc_stock.vend_id', '=', 'vends.id')

                ->leftJoin(DB::raw('
                (
                    SELECT oji.customer_id, oji.cash_amount, oji.acc_total_amount, oji.acc_total_count,
                        SUM(oji_c.actual_qty * vc.amount) AS amount,
                        SUM(oji_c.actual_qty) AS count
                    FROM (
                        SELECT
                            id,
                            customer_id,
                            cash_amount,
                            acc_total_amount,
                            acc_total_count,
                            ops_job_id,
                            ROW_NUMBER() OVER (PARTITION BY customer_id ORDER BY created_at DESC) as rn
                        FROM ops_job_items
                        WHERE status >= 3 AND status <> 99
                    ) oji
                    INNER JOIN ops_job_item_channels oji_c ON oji.id = oji_c.ops_job_item_id
                    INNER JOIN vend_channels vc ON oji_c.vend_channel_id = vc.id
                    INNER JOIN ops_jobs oj ON oji.ops_job_id = oj.id
                    WHERE oji.rn = 1 AND oj.date < CURDATE() + INTERVAL 1 DAY
                    GROUP BY oji.customer_id
                ) AS last_ops_jobs
            '), 'last_ops_jobs.customer_id', '=', 'customers.id')
                ->leftJoin(DB::raw('
                (
                    SELECT oji.customer_id, oji.cash_amount, oji.acc_total_amount, oji.acc_total_count,
                        SUM(oji_c.actual_qty * vc.amount) AS amount,
                        SUM(oji_c.actual_qty) AS count
                    FROM (
                        SELECT
                            id,
                            customer_id,
                            cash_amount,
                            acc_total_amount,
                            acc_total_count,
                            ops_job_id,
                            ROW_NUMBER() OVER (PARTITION BY customer_id ORDER BY created_at DESC) as rn
                        FROM ops_job_items
                        WHERE status >= 3 AND status <> 99
                    ) oji
                    INNER JOIN ops_job_item_channels oji_c ON oji.id = oji_c.ops_job_item_id
                    INNER JOIN vend_channels vc ON oji_c.vend_channel_id = vc.id
                    INNER JOIN ops_jobs oj ON oji.ops_job_id = oj.id
                    WHERE oji.rn = 2 AND oj.date < CURDATE() + INTERVAL 1 DAY
                    GROUP BY oji.customer_id
                ) AS last_second_ops_jobs
            '), 'last_second_ops_jobs.customer_id', '=', 'customers.id')
                ->leftJoin(DB::raw('
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
            '), 'next_ops_jobs.customer_id', '=', 'customers.id')
                ->leftJoin(
                    DB::raw('(
                SELECT SUM(ops_job_item_channels.actual_qty) AS qty,
                       SUM(ops_job_item_channels.actual_qty * vend_channels.amount) AS amount,
                       ops_job_items.customer_id
                FROM ops_job_item_channels
                INNER JOIN vend_channels ON ops_job_item_channels.vend_channel_id = vend_channels.id
                INNER JOIN ops_job_items ON ops_job_item_channels.ops_job_item_id = ops_job_items.id
                INNER JOIN ops_jobs ON ops_job_items.ops_job_id = ops_jobs.id
                WHERE ops_job_items.status >= 3
                AND ops_job_items.status <> 99
                AND ops_jobs.date BETWEEN CURDATE() - INTERVAL 29 DAY AND CURDATE()
                GROUP BY ops_job_items.customer_id
            ) AS last_thirty_days_stock_in'),
                    'last_thirty_days_stock_in.customer_id',
                    '=',
                    'customers.id'
                )
                ->select(
                    'customers.id AS id',
                    'vends.id AS vend_id',
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
                    'vends.is_door_open',
                    'vends.is_disposed',
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
                    'customers.frequency_per_week_status',
                    'customers.is_active AS is_active',
                    'customers.is_active AS customer_is_active',
                    'customers.location_type_id',
                    'customers.name',
                    'customers.name AS customer_name',
                    'customers.operator_id',
                    'customers.ops_note',
                    'customers.person_json',
                    'customers.person_id AS person_id',
                    'customers.preferred_visit_days_json',
                    'customers.selling_price_type',
                    'customers.termination_date',
                    'customers.virtual_customer_prefix',
                    'customers.virtual_customer_code',
                    'location_types.name AS location_type_name',
                    'last_ops_jobs.acc_total_amount AS last_ops_job_acc_total_amount',
                    'last_ops_jobs.acc_total_count AS last_ops_job_acc_total_count',
                    'last_ops_jobs.amount AS last_ops_job_amount',
                    'last_ops_jobs.cash_amount AS last_ops_job_cash_amount',
                    'last_ops_jobs.count AS last_ops_job_count',
                    'last_second_ops_jobs.acc_total_amount AS last_second_ops_job_acc_total_amount',
                    'last_second_ops_jobs.acc_total_count AS last_second_ops_job_acc_total_count',
                    'last_second_ops_jobs.amount AS last_second_ops_job_amount',
                    'last_second_ops_jobs.cash_amount AS last_second_ops_job_cash_amount',
                    'last_second_ops_jobs.count AS last_second_ops_job_count',
                    'last_thirty_days_stock_in.amount AS last_thirty_days_stock_in_amount',
                    'last_thirty_days_stock_in.qty AS last_thirty_days_stock_in_qty',
                    'next_ops_jobs.amount AS next_ops_job_amount',
                    'next_ops_jobs.cash_amount AS next_ops_job_cash_amount',
                    'next_ops_jobs.count AS next_ops_job_count',
                    'product_mappings.name AS product_mapping_name',
                    'product_mappings.remarks AS product_mapping_remarks',
                    'operators.code AS operator_code',
                    'operators.name AS operator_name',
                    'addresses.postcode AS postcode',
                    'vend_prefixes.name AS vend_prefix_name',
                    'vc.total_full_load_amount',
                    'vc.total_stock_amount',
                    'vc_cost.total_stock_cost',
                    'vc_stock.actual_stock_in_value',
                    'vc_stock.actual_stock_in_qty',
                    'zones.name AS zone_name',
                    DB::raw('
                    (JSON_UNQUOTE(JSON_EXTRACT(customers.totals_json, "$.vend_records_thirty_days_amount_average")) *30 /100)/
                    (vc.total_full_load_amount / 100) AS thirty_days_over_full_load_ratio
                '),
                    DB::raw('
                    (last_thirty_days_stock_in.amount/100 - (JSON_UNQUOTE(JSON_EXTRACT(customers.totals_json, "$.thirty_days_amount"))/100)) AS thirty_days_stock_in_delta_amount
                '),
                    DB::raw('
                    ((last_thirty_days_stock_in.amount/100 - (JSON_UNQUOTE(JSON_EXTRACT(customers.totals_json, "$.thirty_days_amount"))/100)))/ (last_thirty_days_stock_in.amount/100) * 100  AS thirty_days_stock_in_delta_percent
                '),
                );
            $vends = $this->filterVendsDB($vends, $request);
            $vends = $this->filterOperatorDB($vends, 'customers');

            $vends = $vends->paginate($perPage)
                ->withQueryString();

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
            ];
        }

        // Cache all option queries to reduce database calls
        $driverOptions = UserResource::collection(
            User::whereHas('roles', function ($query) {
                $query->whereIn('name', ['admin', 'driver', 'supervisor', 'technician']);
            })->orderBy('name')->get()
        );

        $products = Product::query()
            ->with(['thumbnail', 'isAvailableUpdatedBy'])
            ->when($request->operators, function ($query, $search) {
                $query->whereIn('operator_id', $search);
            })
            ->select('id', 'code', 'desc', 'name', 'is_available', 'is_available_updated_at', 'is_available_updated_by')
            ->where('is_active', true)
            ->where('is_inventory', true)
            ->orderBy('code')
            ->get();

        // Pre-load all options to avoid N+1 queries
        $deliveryPlatformOptions = DeliveryPlatformResource::collection(
            DeliveryPlatform::orderBy('name')->get()
        );
        $locationTypeOptions = LocationTypeResource::collection(
            LocationType::orderBy('sequence')->get()
        );
        $operatorOptions = OperatorResource::collection(
            Operator::orderBy('name')->get()
        );
        $vendChannelErrors = VendChannelErrorResource::collection(
            VendChannelError::orderBy('code')->get()
        );
        $vendContractOptions = VendContractResource::collection(
            VendContract::orderBy('name')->get()
        );
        $vendModelOptions = VendModelResource::collection(
            VendModel::orderBy('name')->get()
        );
        $vendPrefixOptions = VendPrefixResource::collection(
            VendPrefix::orderBy('name')->get()
        );
        $zoneOptions = ZoneResource::collection(
            Zone::orderBy('name')->get()
        );

        return Inertia::render('Vend/CustomerIndex', [
            'cmsEndpoint' => env('CMS_URL'),
            'constTempError' => VendTemp::TEMPERATURE_ERROR,
            'dayOptions' => Customer::DAYS_MAPPING,
            'deliveryPlatformOptions' => $deliveryPlatformOptions,
            'deviceTypes' => Vend::DEVICE_TYPE_MAPPINGS,
            'driverOptions' => $driverOptions,
            'frequencyPerWeekOptions' => Customer::FREQUENCY_PER_WEEK_STATUSES_MAPPING,
            'indexType' => $request->indexType,
            'autoLoad' => $shouldAutoload,
            'locationTypeOptions' => $locationTypeOptions,
            'mapApiKey' => $mapApiKey,
            'nextDeliveryDriverOptions' => $driverOptions,
            'operatorOptions' => $operatorOptions,
            'productOptions' => ProductResource::collection($products),
            'sellingPriceTypeOptions' => SellingPrice::TYPE_MAPPINGS,
            'totals' => $totals,
            'vends' => VendResource::collection($vends),
            'vendChannelErrors' => $vendChannelErrors,
            'vendContractOptions' => $vendContractOptions,
            'vendModelOptions' => $vendModelOptions,
            'vendPrefixOptions' => $vendPrefixOptions,
            'zoneOptions' => $zoneOptions,
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
        $fid = 1;
        $content = base64_encode(json_encode([
            'Type' => 'TYPESYNCSETTINGSPARAM',
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

        $request->merge(['types' => empty($request->types) ? [1] : $request->types]);
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
                'customers.virtual_customer_prefix',
                'customers.virtual_customer_code',
                DB::raw('CASE WHEN customers.person_id THEN CONCAT(customers.virtual_customer_code, " (", customers.virtual_customer_prefix, ")") ELSE customers.code END AS customer_code'),
                'customers.name AS customer_name',
                'parameter_json',
                'temp',
            )
            ->first();

        $vendTemps = DB::table('vend_temps')
            ->where('vend_id', $vendId)
            // ->whereIn('type', $request->types)
            ->where('value', '!=', VendTemp::TEMPERATURE_ERROR)
            ->where('vend_temps.created_at', '>=', $startDate)
            ->where('vend_temps.created_at', '<=', $endDate)
            ->select(
                'created_at',
                'type',
                'value',
            )
            ->get();

        $fans = [];
        if ($request->fans) {
            $fans = array_merge($fans, $request->fans);
        } else {
            $fans = [];
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
            ->get();

        $vendOptions = DB::table('vends')
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
                                        WHEN error_code_normalized IS NULL THEN NULL
                                        WHEN error_code_normalized = 0 THEN NULL
                                        ELSE 1
                                    END
                                ) as seven_days_error_count'
                            )
                        )
                        ->selectRaw('COUNT(CASE WHEN transaction_datetime >= ? THEN id ELSE NULL END) as three_days_total_count', [Carbon::today()->subDays(2)])
                        ->selectRaw('COUNT(CASE WHEN transaction_datetime >= ? AND error_code_normalized IS NOT NULL AND error_code_normalized != 0 THEN 1 END) as three_days_error_count', [Carbon::today()->subDays(2)]);
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

    public function getVendBannerImage($vendCode)
    {
        $imageArray = [];
        $vend = Vend::where('code', $vendCode)->first();

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

                // dd($vendChannel->product->thumbnail->full_url);
                $thumbnail = new Imagick();
                $thumbnail->readImageBlob(file_get_contents($vendChannel->product->thumbnail->full_url));
                $thumbnail->resizeImage(500, 500, Imagick::FILTER_LANCZOS, 1);
                return response($thumbnail, 200)
                    ->header('Content-Type', 'image/*');

                // $thumbnail = file_get_contents($vendChannel->product->thumbnail->full_url);
                // return response($thumbnail, 200)
                //     ->header('Content-Type', 'image/*');
            }
        }

        // return response()->json([
        //     'url' => null,
        // ], 400);
        return false;
    }

    public function getVendParameters($vendCode)
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

        if ($apkSetting->campaignItems) {
            $campaignItems = $apkSetting->campaignItems;
        }

        $campaignBindings = collect($apkSetting->campaigns)->filter(function ($campaign) {
            return (bool) ($campaign->is_active ?? false);
        });

        $data = [
            ...$apkSetting->settings_parameter_json,
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
                    'promo_type' => $campaign->promo_type,
                    'slug' => $campaign->slug ?? $campaign->name ?? null,
                    'value' => $campaign->value,
                    'start_date' => optional($campaign->start_at)?->toDateString(),
                    'end_date' => optional($campaign->end_at)?->toDateString(),
                    'min_basket_value' => $campaign->min_basket_value,
                    'max_discount_value' => $campaign->max_discount_value,
                ];
            })->values(),
        ];

        return $data;

        // return $vend->apkSettings[0]->settings_parameter_json;
    }

    public function transactionIndex(Request $request)
    {
        if (!$request->operators) {
            if (auth()->user()->operator->code == 'HIPL') {
                $request->merge([
                    'operators' => [
                        auth()->user()->operator_id,
                        Operator::where('code', 'HIMD')->first()?->id,
                        Operator::where('code', 'LEA')->first()?->id,
                        Operator::where('code', 'DCVIC')->first()?->id,
                        Operator::where('code', 'HIESG')->first()?->id,
                        Operator::where('code', 'IP')->first()?->id,
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

        $recordsQuery = (clone $baseQuery)
            ->with([
                'vendTransactionItems.product',
                'vendTransactionItems.vendChannelError',
            ])
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
                DB::raw("
                (
                    SELECT JSON_ARRAYAGG(
                            JSON_OBJECT('id', t.id, 'slug', t.slug, 'name', t.name)
                        )
                    FROM JSON_TABLE(
                            COALESCE(vend_transactions.label_json, '[]'),
                            '$[*]' COLUMNS(
                                tag_id BIGINT PATH '$',
                                tag_name VARCHAR(255) PATH '$'
                            )
                        ) jt
                    JOIN tags t ON (t.id = jt.tag_id OR t.name = jt.tag_name)
                ) AS label_json
                ")
            );

        $records = $recordsQuery
            ->forPage($currentPage, $perPage)
            ->get();

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

        $itemStats = DB::table('vend_transaction_items')
            ->select([
                'vend_transaction_id',
                DB::raw('COUNT(*) as total_items'),
                DB::raw('COUNT(CASE WHEN vend_channel_error_code IN (0,6) OR vend_channel_error_code IS NULL THEN 1 END) as success_items'),
            ])
            ->groupBy('vend_transaction_id');

        $totals = VendTransaction::query()
            ->leftJoin('vend_channel_errors', 'vend_channel_errors.id', '=', 'vend_transactions.vend_channel_error_id')
            ->leftJoin('delivery_platform_orders', 'delivery_platform_orders.vend_transaction_id', '=', 'vend_transactions.id')
            ->join('vends', 'vends.id', '=', 'vend_transactions.vend_id')
            ->leftJoinSub($itemStats, 'item_stats', function ($join) {
                $join->on('vend_transactions.id', '=', 'item_stats.vend_transaction_id');
            })
            ->filterTransactionIndex($request)
            ->where(function ($query) {
                $query->whereNull('vends.is_testing')
                    ->orWhere('vends.is_testing', false);
            })
            ->select([
                DB::raw('CAST(COUNT(CASE
                    WHEN vend_channel_errors.code = 0 OR vend_channel_errors.code = 6 OR vend_channel_errors.code IS NULL OR is_multiple = true
                    THEN 1 ELSE NULL END) AS SIGNED) AS success_count'),

                DB::raw('ROUND(COALESCE(SUM(CASE
                    WHEN vend_channel_errors.code = 0 OR vend_channel_errors.code = 6 OR vend_channel_errors.code IS NULL OR is_multiple = true
                    THEN vend_transactions.amount ELSE 0 END), 0), 2) AS success_amount'),

                DB::raw('COUNT(*) AS total_count'),

                // total_qty: COALESCE to 1 when no item rows
                DB::raw('SUM(COALESCE(item_stats.total_items, 1)) AS total_qty'),

                // success_total_qty: use item_stats OR 1 for single transaction success
                DB::raw('CAST(SUM(CASE
                    WHEN item_stats.success_items IS NOT NULL THEN item_stats.success_items
                    WHEN item_stats.success_items IS NULL AND (vend_channel_errors.code = 0 OR vend_channel_errors.code = 6 OR vend_channel_errors.code IS NULL)
                         AND is_multiple = 0 THEN 1
                    ELSE 0
                END) AS SIGNED) AS success_total_qty'),

                // success_total_qty_rate: percent of success qty out of total qty
                DB::raw('ROUND(SUM(CASE
                    WHEN item_stats.success_items IS NOT NULL THEN item_stats.success_items
                    WHEN item_stats.success_items IS NULL AND (vend_channel_errors.code = 0 OR vend_channel_errors.code = 6 OR vend_channel_errors.code IS NULL)
                         AND is_multiple = 0 THEN 1
                    ELSE 0
                END) * 100.0 / NULLIF(SUM(COALESCE(item_stats.total_items, 1)), 0), 2) AS success_total_qty_rate'),

                DB::raw('ROUND(COUNT(CASE
                    WHEN vend_channel_errors.code = 0 OR vend_channel_errors.code = 6 OR vend_channel_errors.code IS NULL OR is_multiple = true
                    THEN 1 ELSE NULL END) * 100.0 / NULLIF(COUNT(*), 0), 2) AS success_count_rate'),
                DB::raw('CAST(COUNT(CASE
                    WHEN (vend_channel_errors.code = 0 OR vend_channel_errors.code = 6 OR vend_channel_errors.code IS NULL)
                        AND delivery_platform_orders.id IS NOT NULL
                    THEN 1 ELSE NULL END) AS SIGNED) AS delivery_platform_success_count'),

                DB::raw('CAST(ROUND(COALESCE(SUM(CASE
                    WHEN (vend_channel_errors.code = 0 OR vend_channel_errors.code = 6 OR vend_channel_errors.code IS NULL)
                        AND delivery_platform_orders.id IS NOT NULL
                    THEN vend_transactions.amount ELSE 0 END), 0), 2) AS SIGNED) AS delivery_platform_success_amount'),

                DB::raw('CAST(SUM(CASE
                    WHEN is_multiple = 1 AND delivery_platform_orders.id IS NOT NULL
                    THEN 1 ELSE 0 END) AS SIGNED) AS multiple_count_delivery_platform'),

                DB::raw('CAST(SUM(CASE
                    WHEN is_multiple = 1 AND delivery_platform_orders.id IS NULL
                    THEN 1 ELSE 0 END) AS SIGNED) AS multiple_count_machine')

            ])
            ->first();


        $latestExports = ExportJob::with('attachment')
            ->where('user_id', auth()->id())
            ->where('type', 'vend_transaction')
            ->latest()
            ->limit(5)
            ->get();

        // Cache all option queries to reduce database calls
        $categories = CategoryResource::collection(
            Category::where('classname', $className)->orderBy('name')->get()
        );
        $categoryGroups = CategoryGroupResource::collection(
            CategoryGroup::where('classname', $className)->orderBy('name')->get()
        );
        $locationTypeOptions = LocationTypeResource::collection(
            LocationType::orderBy('sequence')->get()
        );
        $operatorOptions = OperatorResource::collection(
            Operator::orderBy('name')->get()
        );
        $paymentMethods = PaymentMethodResource::collection(
            PaymentMethod::orderBy('name')->get()
        );
        $tagOptions = TagResource::collection(
            Tag::orderBy('name')->get()
        );
        $vendChannelErrors = VendChannelErrorResource::collection(
            VendChannelError::orderBy('code')->get()
        );
        $vendContractOptions = VendContractResource::collection(
            VendContract::orderBy('name')->get()
        );
        $vendModelOptions = VendModelResource::collection(
            VendModel::orderBy('name')->get()
        );
        $vendPrefixOptions = VendPrefixResource::collection(
            VendPrefix::orderBy('name')->get()
        );

        return Inertia::render('Vend/Transaction', [
            'categories' => $categories,
            'categoryGroups' => $categoryGroups,
            'latestExports' => $latestExports,
            'locationTypeOptions' => $locationTypeOptions,
            'operatorOptions' => $operatorOptions,
            'paymentMethods' => $paymentMethods,
            'vendTransactions' => VendTransactionResource::collection(
                $vendTransactions
            ),
            'tagOptions' => $tagOptions,
            'totals' => $totals,
            'vendChannelErrors' => $vendChannelErrors,
            'vendContractOptions' => $vendContractOptions,
            'vendModelOptions' => VendModelResource::collection(
                VendModel::orderBy('name')->get()
            ),
            'vendPrefixOptions' => VendPrefixResource::collection(
                VendPrefix::orderBy('name')->get()
            ),
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
                        Operator::where('code', 'DCVIC')->first()?->id,
                        Operator::where('code', 'HIESG')->first()?->id,
                        Operator::where('code', 'IP')->first()?->id,
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

        $baseQuery = VendTransaction::query()
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
            ->when($user->vends()->exists(), function ($query) use ($user) {
                $query->whereIn('vend_transactions.vend_id', $user->vends->pluck('id'));
            })
            ->filterTransactionIndex($request);

        $totalRows = $baseQuery->count();
        $chunkSize = 20000;

        if ($totalRows <= $chunkSize) {
            // ✅ Small export, dispatch direct CSV (no zip)
            ExportVendTransactionCsv::dispatch($job->id, $request->all(), $user->id);
        } else {
            // ✅ Large export, split into chunks
            $totalChunks = ceil($totalRows / $chunkSize);

            for ($i = 0; $i < $totalChunks; $i++) {
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
                    $chunkSize
                );
            }
        }

        return back()->with('message', 'Export started! You can check it later in the export list.');
    }


    public function exportTransactionExcel(Request $request)
    {
        $request->merge(['sortKey' => $request->sortKey ?? 'transaction_datetime']);
        $request->merge(['sortBy' => $request->sortBy ?? false]);

        $timezone = $this->getUserTimezone();
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
                        Operator::where('code', 'DCVIC')->first()?->id,
                        Operator::where('code', 'HIESG')->first()?->id,
                        Operator::where('code', 'IP')->first()?->id,
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

        return Inertia::render('Vend/PaymentGatewayTransaction', [
            'operatorOptions' => $operatorOptions,
            'paymentMethods' => $paymentMethods,
            'paymentGatewayLogs' => PaymentGatewayLogResource::collection(
                $paymentGatewayLogs
            ),
            'totals' => $totals,
        ]);
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
                    ]);
                    break;
                case 'active':
                    $request->merge([
                        'is_active' => true,
                        'is_testing' => false,
                        'is_disposed' => false,
                    ]);
                    break;
                case 'inactive':
                    $request->merge([
                        'is_active' => false,
                        'is_testing' => false,
                        'is_disposed' => false,
                    ]);
                    break;
                case 'disposed':
                    $request->merge([
                        'is_active' => false,
                        'is_testing' => false,
                        'is_disposed' => true,
                    ]);
                    break;
            }
        }

        $vend = Vend::findOrFail($vendID);

        if ($request->product_mapping_id != $vend->product_mapping_id) {
            $request->merge([
                'upcoming_product_mapping_id' => null,
            ]);
            $isProductMappingChanged = true;
        }

        if ($request->modem_type_id == null) {
            $request->merge([
                'modem_unit_id' => null,
            ]);
        }

        $request->validate([
            'lcd_monitor_id' => 'required',
            'menu_frame_id' => 'required',
            'operator_id' => 'required',
            'product_mapping_id' => 'required',
            'vend_config_id' => 'required',
            'vend_model_id' => 'required',
            'vend_prefix_id' => 'required',
        ]);

        $vend->update([
            'name' => $request->name,
            'begin_date' => $request->begin_date,
            'key_id' => $request->key_id,
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
            'is_testing' => $request->is_testing,
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

        // if($request->modem_unit_imei) {
        //     $vend->modemUnit()->update([
        //         'imei' => $request->modem_unit_imei,
        //         'modem_type_id' => $vend->modem_type_id,
        //     ]);
        // }

        if ($isProductMappingChanged and $vend->product_mapping_id) {
            $this->productMappingService->syncChannels($vend->product_mapping_id);
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
            ->where('capacity', '>', 0);
        $vendChannels = $this->filterVendChannelsDB($vendChannels, $request);
        $vendChannels = $this->filterOperatorDB($vendChannels, 'customers');
        $vendChannels = $vendChannels->get();

        // dd($vendChannels);
        return (new FastExcel($this->yieldOneByOne($vendChannels)))->download('Vend_channels_' . Carbon::now()->toDateTimeString() . '.xlsx', function ($vendChannel) {
            return [
                'Machine ID' => isset($vendChannel->vend_code) ? $vendChannel->vend_code : '',
                'Customer Name' => isset($vendChannel->customer_code) ?
                    $vendChannel->customer_code . ' ' . $vendChannel->customer_name :
                    (isset($vendChannel->vend_name) ? $vendChannel->vend_name : ''),
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
                'customers.id AS customer_id',
                DB::raw('CASE WHEN customers.person_id IS NOT NULL THEN CONCAT(customers.virtual_customer_code," (",customers.virtual_customer_prefix,")") ELSE customers.code END AS customer_code'),
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

        $logs = $vend->eventLogs()
            ->select(['id', 'event', 'subject', 'context', 'occurred_at', 'created_at'])
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
        $vend->product_mapping_id = $vend->upcoming_product_mapping_id;
        $vend->upcoming_product_mapping_id = null;
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
}
