<?php

namespace App\Http\Controllers;

// use App\Exports\VendTempExport;
use App\Exports\VendTransactionExport;
use App\Jobs\PublishMqtt;
use App\Http\Resources\CategoryResource;
use App\Http\Resources\CategoryGroupResource;
use App\Http\Resources\CountryResource;
use App\Http\Resources\CustomerResource;
use App\Http\Resources\LocationTypeResource;
use App\Http\Resources\ModemTypeResource;
use App\Http\Resources\ModemUnitResource;
use App\Http\Resources\OperatorResource;
use App\Http\Resources\PaymentMethodResource;
use App\Http\Resources\ProductResource;
use App\Http\Resources\UserResource;
use App\Http\Resources\VendDBResource;
use App\Http\Resources\VendResource;
use App\Http\Resources\VendChannelResource;
use App\Http\Resources\VendChannelErrorResource;
use App\Http\Resources\VendFanResource;
use App\Http\Resources\VendModelResource;
use App\Http\Resources\VendPrefixResource;
use App\Http\Resources\VendTransactionResource;
use App\Http\Resources\VendTransactionItemResource;
use App\Http\Resources\VendTempResource;
use App\Http\Resources\ZoneResource;
use App\Jobs\SyncVendCustomerCms;
use App\Jobs\Vend\SaveVendChannelsJson;
use App\Mail\VendChannelErrorLogsMail;
use App\Models\Category;
use App\Models\CategoryGroup;
use App\Models\Country;
use App\Models\Customer;
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
use App\Models\User;
use App\Models\Vend;
use App\Models\VendChannel;
use App\Models\VendChannelError;
use App\Models\VendChannelErrorLog;
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
use Illuminate\Support\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
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
    )
    {
        $this->middleware(['permission:read vend-customers'])->only('indexCustomer');
        $this->middleware(['permission:read vend-machines'])->only('index');
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
//         $debugData = Customer::query()
//         ->leftJoin('vends', 'vends.customer_id', '=', 'customers.id')
//         ->leftJoin(DB::raw('
//         (
//             SELECT
//                 vend_channels.vend_id,
//                 vend_channels.id,
//                 vend_channels.code,
//                 products.name,
//                 product_limits.qty as product_limit_qty,
//                 vend_channels.capacity,
//                 vend_channels.qty as vend_channel_qty,
//                 CASE
//                     WHEN product_limits.id AND product_limits.qty < vend_channels.capacity THEN
//                         (product_limits.qty - vend_channels.qty)
//                     ELSE
//                         (vend_channels.capacity - vend_channels.qty)
//                 END AS stock_in_qty
//             FROM
//                 vend_channels
//             INNER JOIN
//                 products ON vend_channels.product_id = products.id
//             LEFT JOIN (
//                     SELECT id, product_id, qty, date
//                     FROM product_limits AS pl
//                     WHERE pl.date = CURDATE()
//                     AND pl.id = (
//                         SELECT id
//                         FROM product_limits
//                         WHERE product_id = pl.product_id
//                         AND date = pl.date
//                         ORDER BY id DESC
//                         LIMIT 1
//                     )
//                 ) AS product_limits ON products.id = product_limits.product_id
//             WHERE
//                 products.is_available = true
//             AND vend_channels.is_active = true
//             AND vend_channels.capacity > 0
//             ORDER BY vend_channels.code
//         ) AS vc_stock
//     '), 'vc_stock.vend_id', '=', 'vends.id')
//         ->select(
//             'customers.id AS id',
//             'vends.id AS vend_id',
//             'vc_stock.code',
//             'vc_stock.name',
//             'vc_stock.product_limit_qty',
//             'vc_stock.capacity',
//             'vc_stock.vend_channel_qty',
//             'vc_stock.stock_in_qty',
//         )
//         ->where('vends.code', 2638)

//     ->get();

// dd($debugData->toArray());

        $request->merge(['visited' => isset($request->visited) ? $request->visited : true]);
        if(!isset($request->is_active)) {
            if(
                auth()->user()->hasRole('superadmin') or
                auth()->user()->hasRole('admin') or
                auth()->user()->hasRole('supervisor') or
                auth()->user()->hasRole('driver')
            ) {
                $request->merge(['is_active' => 'true']);
            }else {
                $request->merge(['is_active' => 'all']);
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
                'vendChannels' => function($query) {
                    $query->select('*')
                        ->selectRaw("(SELECT server_amount FROM product_mapping_items WHERE
                            product_mapping_items.product_id = vend_channels.product_id AND
                            product_mapping_items.product_mapping_id = (SELECT product_mapping_id FROM vends WHERE vends.id = vend_channels.vend_id) LIMIT 1) AS server_amount");
                },
                'vendChannels.latestOpsJobItemChannel',
                'vendChannels.product.thumbnail',
                'vendChannels.product.sellingPrices',
                'vendChannels.vendChannelErrorLogs' => function($query) {
                    $query->where('created_at', '>=', Carbon::today()->subDays(29));
                },
                'vendChannels.vendChannelErrorLogs.vendChannelError',
            ])
            ->leftJoin('customers', 'vends.customer_id', '=', 'customers.id')
            ->leftJoin('categories', 'categories.id', '=', 'customers.category_id')
            ->leftJoin('category_groups', 'category_groups.id', '=', 'categories.category_group_id')
            ->leftJoin('modem_types', 'modem_types.id', '=', 'vends.modem_type_id')
            ->leftJoin('modem_units', 'modem_units.id', '=', 'vends.modem_unit_id')
            ->leftJoin('operators', 'operators.id', '=', 'vends.operator_id')
            ->leftJoin('product_mappings', 'product_mappings.id', '=', 'vends.product_mapping_id')
            ->leftJoin('vend_prefixes', 'vend_prefixes.id', '=', 'vends.vend_prefix_id')
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
                // 'vends.vend_channels_json',
                'vends.vend_channel_totals_json',
                'vends.vend_channel_error_logs_json',
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
                            ->sum(function($vend) {
                                return $vend->vend_transaction_totals_json ? $vend->vend_transaction_totals_json['thirty_days_amount'] : 0;
                            })/100,
            'thirthyDaysAvg' => collect((clone $vends)
                            ->items())
                            ->sum(function($vend) {
                                return $vend->vend_transaction_totals_json ? $vend->vend_transaction_totals_json['vend_records_thirty_days_amount_average'] : 0;
                            })/100,
        ];

        return Inertia::render('Vend/Index', [
            'cmsEndpoint' => env('CMS_URL'),
            'constTempError' => VendTemp::TEMPERATURE_ERROR,
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
                Operator::orderBy('name')->get()
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
        if(!isset($request->is_active)) {
            if(
                auth()->user()->hasRole('superadmin') or
                auth()->user()->hasRole('admin') or
                auth()->user()->hasRole('supervisor') or
                auth()->user()->hasRole('driver')
            ) {
                $request->merge(['is_active' => 'true']);
            }else {
                $request->merge(['is_active' => 'all']);
            }
        }

        if(!$request->operators) {
            if(auth()->user()->operator->code == 'HIPL') {
                $request->merge(['operators' => [
                    auth()->user()->operator_id, Operator::where('code', 'HIMD')->first()?->id,
                    auth()->user()->operator_id, Operator::where('code', 'LEA')->first()?->id,
                ]]);
            }else {
                $request->merge(['operators' => [auth()->user()->operator_id]]);
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
                'nextOpsJobItem.opsJobItemChannels.vendChannel',
                'vend.vendChannels' => function($query) {
                    $query->select('*')
                        ->selectRaw("(SELECT server_amount FROM product_mapping_items WHERE
                            product_mapping_items.product_id = vend_channels.product_id AND
                            product_mapping_items.product_mapping_id = (SELECT product_mapping_id FROM vends WHERE vends.id = vend_channels.vend_id) LIMIT 1) AS server_amount");
                },
                'vend.vendChannels.latestOpsJobItemChannel',
                'vend.vendChannels.product.thumbnail',
                'vend.vendChannels.product.sellingPrices',
                'vend.vendChannels.vendChannelErrorLogs' => function($query) {
                    $query->where('created_at', '>=', Carbon::today()->subDays(29));
                },
                'vend.vendChannels.vendChannelErrorLogs.vendChannelError',
                'vend.modemUnit'
            ])
            ->leftJoin('vends', 'vends.customer_id', '=', 'customers.id')
            ->leftJoin('vend_prefixes', 'vend_prefixes.id', '=', 'vends.vend_prefix_id')
            ->leftJoin('categories', 'categories.id', '=', 'customers.category_id')
            ->leftJoin('category_groups', 'category_groups.id', '=', 'categories.category_group_id')
            ->leftJoin('location_types', 'location_types.id', '=', 'customers.location_type_id')
            ->leftJoin('operators', 'operators.id', '=', 'customers.operator_id')
            ->leftJoin('product_mappings', 'product_mappings.id', '=', 'vends.product_mapping_id')
            ->leftJoin('zones', 'zones.id', '=', 'customers.zone_id')
            ->leftJoin('addresses', function($query) {
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
                            FROM product_limits AS pl
                            WHERE pl.date = CURDATE()
                            AND pl.id = (
                                SELECT id
                                FROM product_limits
                                WHERE product_id = pl.product_id
                                AND date = pl.date
                                ORDER BY id DESC
                                LIMIT 1
                            )
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
                    SELECT
                        last_ops_jobs_inner.customer_id,
                        last_ops_jobs_inner.acc_total_amount,
                        last_ops_jobs_inner.acc_total_count,
                        last_ops_jobs_inner.amount,
                        last_ops_jobs_inner.cash_amount,
                        last_ops_jobs_inner.count
                    FROM
                    (
                        SELECT
                            ops_job_items.customer_id,
                            ops_job_items.cash_amount,
                            ops_job_items.acc_total_amount,
                            ops_job_items.acc_total_count,
                            SUM(ops_job_item_channels.actual_qty * vend_channels.amount) AS amount,
                            SUM(ops_job_item_channels.actual_qty) AS count,
                            ROW_NUMBER() OVER (PARTITION BY ops_job_items.customer_id ORDER BY ops_job_items.created_at DESC) AS rn
                        FROM
                            ops_job_item_channels
                        INNER JOIN
                            vend_channels ON ops_job_item_channels.vend_channel_id = vend_channels.id
                        INNER JOIN
                            ops_job_items ON ops_job_item_channels.ops_job_item_id = ops_job_items.id
                        INNER JOIN
                            ops_jobs ON ops_job_items.ops_job_id = ops_jobs.id
                        WHERE
                            ops_job_items.status >= 3
                            AND ops_job_items.status <> 99
                            AND ops_jobs.date < CURDATE() + INTERVAL 1 DAY
                        GROUP BY
                            ops_job_items.customer_id, ops_job_items.created_at
                    ) AS last_ops_jobs_inner
                    WHERE last_ops_jobs_inner.rn = 1
                ) AS last_ops_jobs
            '), 'last_ops_jobs.customer_id', '=', 'customers.id')
            ->leftJoin(DB::raw('
                (
                    SELECT
                        last_second_ops_jobs_inner.customer_id,
                        last_second_ops_jobs_inner.acc_total_amount,
                        last_second_ops_jobs_inner.acc_total_count,
                        last_second_ops_jobs_inner.amount,
                        last_second_ops_jobs_inner.cash_amount,
                        last_second_ops_jobs_inner.count
                    FROM
                    (
                        SELECT
                            ops_job_items.customer_id,
                            ops_job_items.cash_amount,
                            ops_job_items.acc_total_amount,
                            ops_job_items.acc_total_count,
                            SUM(ops_job_item_channels.actual_qty * vend_channels.amount) AS amount,
                            SUM(ops_job_item_channels.actual_qty) AS count,
                            ROW_NUMBER() OVER (PARTITION BY ops_job_items.customer_id ORDER BY ops_job_items.created_at DESC) AS rn
                        FROM
                            ops_job_item_channels
                        INNER JOIN
                            vend_channels ON ops_job_item_channels.vend_channel_id = vend_channels.id
                        INNER JOIN
                            ops_job_items ON ops_job_item_channels.ops_job_item_id = ops_job_items.id
                        INNER JOIN
                            ops_jobs ON ops_job_items.ops_job_id = ops_jobs.id
                        WHERE
                            ops_job_items.status >= 3
                            AND ops_job_items.status <> 99
                            AND ops_jobs.date < CURDATE() + INTERVAL 1 DAY
                        GROUP BY
                            ops_job_items.customer_id, ops_job_items.created_at
                    ) AS last_second_ops_jobs_inner
                    WHERE last_second_ops_jobs_inner.rn = 2 -- This selects the second-to-last row
                ) AS last_second_ops_jobs
            '), 'last_second_ops_jobs.customer_id', '=', 'customers.id')
            ->leftJoin(DB::raw('
                (
                    SELECT
                        next_ops_jobs_inner.customer_id,
                        next_ops_jobs_inner.amount,
                        next_ops_jobs_inner.cash_amount,
                        next_ops_jobs_inner.count
                    FROM
                    (
                        SELECT
                            ops_job_items.customer_id,
                            ops_job_items.cash_amount,
                            SUM(ops_job_item_channels.picked_qty * vend_channels.amount) AS amount,
                            SUM(ops_job_item_channels.picked_qty) AS count,
                            ROW_NUMBER() OVER (PARTITION BY ops_job_items.customer_id ORDER BY ops_job_items.created_at ASC) AS rn
                        FROM
                            ops_job_item_channels
                        INNER JOIN
                            vend_channels ON ops_job_item_channels.vend_channel_id = vend_channels.id
                        INNER JOIN
                            ops_job_items ON ops_job_item_channels.ops_job_item_id = ops_job_items.id
                        INNER JOIN
                            ops_jobs ON ops_job_items.ops_job_id = ops_jobs.id
                        WHERE
                            ops_job_items.status < 3
                            AND ops_jobs.date >= CURDATE()
                        GROUP BY
                            ops_job_items.customer_id, ops_job_items.created_at
                    ) AS next_ops_jobs_inner
                    WHERE next_ops_jobs_inner.rn = 1
                ) AS next_ops_jobs
            '), 'next_ops_jobs.customer_id', '=', 'customers.id')
            ->leftJoin(DB::raw('(
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
            'last_thirty_days_stock_in.customer_id', '=', 'customers.id')
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

        $vends = $vends->paginate($request->numberPerPage === 'All' ? 10000 : $request->numberPerPage)
            ->withQueryString();

        $totals = [
            'mapApiKey' => $this->mapService->getMapApiKeyByUser(auth()->user()),
            'thirtyDays' => collect((clone $vends)
                            ->items())
                            ->sum(function($vend) {
                                return $vend->vend_transaction_totals_json ? $vend->vend_transaction_totals_json['thirty_days_amount'] : 0;
                            })/100,
            'thirthyDaysAvg' => collect((clone $vends)
                            ->items())
                            ->sum(function($vend) {
                                return $vend->vend_transaction_totals_json ? $vend->vend_transaction_totals_json['vend_records_thirty_days_amount_average'] : 0;
                            })/100,
            'thirthyDaysStockIn' => collect((clone $vends)
                            ->items())
                            ->sum(function($vend) {
                                return $vend->last_thirty_days_stock_in_amount ? $vend->last_thirty_days_stock_in_amount : 0;
                            })/100,
        ];

        return Inertia::render('Vend/CustomerIndex', [
            'cmsEndpoint' => env('CMS_URL'),
            'constTempError' => VendTemp::TEMPERATURE_ERROR,
            'dayOptions' => Customer::DAYS_MAPPING,
            'deviceTypes' => Vend::DEVICE_TYPE_MAPPINGS,
            'driverOptions' => UserResource::collection(
                User::whereHas('roles', function($query) use ($request) {
                    $query
                        ->whereIn('name', ['admin', 'driver', 'supervisor', 'technician']);
                })->orderBy('name')->get()
            ),
            'frequencyPerWeekOptions' => Customer::FREQUENCY_PER_WEEK_STATUSES_MAPPING,
            'indexType' => $request->indexType,
            'locationTypeOptions' => LocationTypeResource::collection(
                LocationType::orderBy('sequence')->get()
            ),
            'mapApiKey' => $this->mapService->getMapApiKeyByUser(auth()->user()),
            'nextDeliveryDriverOptions' => UserResource::collection(
                User::whereHas('roles', function($query) use ($request) {
                    $query
                        ->whereIn('name', ['admin', 'driver', 'supervisor', 'technician']);
                })->orderBy('name')->get()
            ),
            'operatorOptions' => OperatorResource::collection(
                Operator::orderBy('name')->get()
            ),
            'productOptions' => ProductResource::collection(
                Product::query()
                    ->with(['thumbnail', 'isAvailableUpdatedBy'])
                    ->when($request->operators, function($query, $search) {
                        $query->whereIn('operator_id', $search);
                    })
                    ->select('id', 'code', 'desc', 'name', 'is_available', 'is_available_updated_at', 'is_available_updated_by')
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
            'zoneOptions' => ZoneResource::collection(
                Zone::orderBy('name')->get()
            ),
        ]);
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
        $md5 = md5($fid.','.$contentLength.','.$content.$key);

        PublishMqtt::dispatch('CM'.$vend->code, $fid.','.$contentLength.','.$content.','.$md5)->onQueue('high');
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
        $md5 = md5($fid.','.$contentLength.','.$content.$key);

        PublishMqtt::dispatch('CM'.$vend->code, $fid.','.$contentLength.','.$content.','.$md5)->onQueue('high');
        // $this->mqttService->publish('CM'.$vend->code, $fid.','.$contentLength.','.$content.','.$md5);

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
        $md5 = md5($fid.','.$contentLength.','.$content.$key);

        PublishMqtt::dispatch('CM'.$vend->code, $fid.','.$contentLength.','.$content.','.$md5)->onQueue('high');

        return redirect()->back();
    }

    public function triggerLogUpload($id)
    {
        $vend = Vend::findOrFail($id);
        $fid = 1;
        $content = base64_encode(json_encode([
            'Type' => 'UPDATELOG',
            'time' => Carbon::now()->timestamp,
            'action' => '',
            'mid' => $vend->code,
        ]));
        $contentLength = strlen($content);
        $key = $vend && $vend->private_key ? $vend->private_key : '123456789110138A';
        $md5 = md5($fid.','.$contentLength.','.$content.$key);

        PublishMqtt::dispatch('CM'.$vend->code, $fid.','.$contentLength.','.$content.','.$md5)->onQueue('high');
        // $this->mqttService->publish('CM'.$vend->code, $fid.','.$contentLength.','.$content.','.$md5);

        return redirect()->back();
    }

    public function temp(Request $request, $vendId, $type)
    {
        // dd($request->all());
        $duration = 2;
        if($request->duration) {
            $duration = $request->duration;
        }

        $startDate =  $request->durationType == 'day' || !$request->durationType ? Carbon::now()->setTimezone($this->getUserTimezone())->subDays($duration) : Carbon::now()->setTimezone($this->getUserTimezone())->subHours($duration);
        $endDate =  Carbon::now()->setTimezone($this->getUserTimezone());
        if($request->datetime_from) {
            $startDate = Carbon::parse($request->datetime_from)->setTimezone($this->getUserTimezone());
        }
        if($request->datetime_to) {
            $endDate = Carbon::parse($request->datetime_to)->setTimezone($this->getUserTimezone());
        }

        $request->merge(['types' => empty($request->types) ? [1] : $request->types]);
        // $request->types = empty($request->types) ? [1, 2] : $request->types;

        // dd($request->types);
        $typeName = 'Temp '.$type;

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
        if($request->fans) {
            $fans = array_merge($fans, $request->fans);
        }else {
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
        ]);
    }

    public function exportTempExcel(Request $request, $vendId, $type)
    {
        $duration = 3;
        if($request->duration) {
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

        $startDate =  $request->durationType == 'day' || !$request->durationType ? Carbon::now()->setTimezone($this->getUserTimezone())->subDays($duration) : Carbon::now()->setTimezone($this->getUserTimezone())->subHours($duration);
        $endDate =  Carbon::now()->setTimezone($this->getUserTimezone());
        if($request->datetime_from) {
            $startDate = Carbon::parse($request->datetime_from)->setTimezone($this->getUserTimezone());
        }
        if($request->datetime_to) {
            $endDate = Carbon::parse($request->datetime_to)->setTimezone($this->getUserTimezone());
        }
        $request->types = empty($request->types) ? [1] : $request->types;

        $typeName = 'Temp '.$type;

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

        return (new FastExcel($vendTemps))->download('Vend_Temps_'.Carbon::now()->toDateTimeString().'.xlsx', function ($vendTemp) {
            return [
                'Vend ID' => $vendTemp->vend_code,
                'Date Time' => Carbon::parse($vendTemp->created_at)->toDateTimeString(),
                'Temp' => $vendTemp->value/ 10,
                'Type' => 'T'.$vendTemp->type,
            ];
        });
    }

    public function getChannelsErrorRate($id)
    {
        $vendChannels = VendChannel::query()
            ->with([
                'vendTransactions' => function($query) {
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

    public function getVendAllChannelThumnails($vendCode)
    {
        $vendChannels = VendChannel::query()
        ->with([
            'product.thumbnail',
            'vend.productMapping',
        ])
        ->whereHas('vend', function($query) use ($vendCode) {
            $query->where('code', $vendCode);
        })
        ->where('is_active', true)
        ->where('capacity', '>', 0)
        ->orderBy('code', 'asc')
        ->get();

        if($vendChannels) {
            $dataArr = [];
            foreach($vendChannels as $vendChannelIndex => $vendChannel) {
                $dataArr[$vendChannelIndex] = [
                    'vend_code' => $vendChannel->vend->code,
                    'channel_code' => $vendChannel->code,
                    'product_id' => null,
                    'product_code' => null,
                    'product_name' => null,
                    'thumbnail' => null,
                    'server_price' => $vendChannel->vend->product_mapping_id ? (int)(ProductMappingItem::where('product_mapping_id', $vendChannel->vend->product_mapping_id)->where('channel_code', (int)$vendChannel->code)->first()?->server_amount * 100) : null,
                ];
                if($vendChannel->product) {
                    $dataArr[$vendChannelIndex] = [
                        ...$dataArr[$vendChannelIndex],
                        'product_id' => $vendChannel->product->id,
                        'product_code' => $vendChannel->product->code,
                        'product_name' => $vendChannel->product->name,
                    ];
                    if($vendChannel->product->thumbnail) {
                        $dataArr[$vendChannelIndex] = [
                            ...$dataArr[$vendChannelIndex],
                            'thumbnail' => $vendChannel->product->thumbnail->full_url,
                        ];
                    }
                    if($vendChannel->product->translated_names_json) {
                        foreach($vendChannel->product->translated_names_json as $lang => $value) {
                            $dataArr[$vendChannelIndex] = [
                                ...$dataArr[$vendChannelIndex],
                                'product_name_'.$value['id'] => $value['name'],
                            ];
                        }
                    }

                }
            }
            return response()->json($dataArr, 200);
        }

        return false;
    }

    public function getVendBannerImage($vendCode)
    {
        // $vend = Vend::where('code', $vendCode)->first();

        // if($vend && $vend->banner_image) {
        // $image = file_get_contents($vend->banner_image->full_url);
        return response([
            'pictures' => [
                [
                    'name' => 'defaultpicture',
                    'ext' => 'jpg',
                    'url' => "https://happyice-space.sgp1.digitaloceanspaces.com/sys/vends/banner-images/defaultpicture.jpg",
                ]
            ],
        ], 200);
        // }

        return false;
    }

    public function getVendBannerVideo($vendCode)
    {
        // $vend = Vend::where('code', $vendCode)->first();

        // if($vend && $vend->banner_video) {
            // $video = file_get_contents($vend->banner_video->full_url);
        return response([
            'videos' => [
                [
                    'name' => 'defaultvideo',
                    'ext' => 'mp4',
                    'url' => "https://happyice-space.sgp1.digitaloceanspaces.com/sys/vends/banner-videos/defaultvideo.mp4"
                ]
            ],
        ], 200);
        // }

        return false;
    }

    public function getVendChannelThumnail($vendCode, $vendChannelCode)
    {
        $vendChannel = VendChannel::query()
            ->with('product.thumbnail')
            ->whereHas('vend', function($query) use ($vendCode) {
                $query->where('code', $vendCode);
            })
            ->where('code', $vendChannelCode)
            ->first();

        if($vendChannel) {
            if($vendChannel->product && $vendChannel->product->thumbnail) {

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

    public function getVendParameters($vendCode) {
        $vend = Vend::where('code', $vendCode)->firstOrFail();

        if(!$vend->settings_parameter_json) {
            abort(response([
                'error_code' => 400,
                'error_message' => 'Parameters not found',
            ], 400));
        }

        return $vend->settings_parameter_json;
    }

    public function transactionIndex(Request $request)
    {
        if(!$request->operators) {
            if(auth()->user()->operator->code == 'HIPL') {
                $request->merge(['operators' => [auth()->user()->operator_id, Operator::where('code', 'HIMD')->first() ? Operator::where('code', 'HIMD')->first()->id : null]]);
            }else {
                $request->merge(['operators' => [auth()->user()->operator_id]]);
            }
        }
        $request->merge(['sortKey' => $request->sortKey ? $request->sortKey : 'transaction_datetime']);
        $request->merge(['sortBy' => $request->sortBy ? $request->sortBy : false]);
        $request->merge(['visited' => isset($request->visited) ? $request->visited : true]);

        $request->date_from =  $request->date_from ? Carbon::parse($request->date_from)->setTimezone($this->getUserTimezone())->startOfDay() : Carbon::today()->setTimezone($this->getUserTimezone())->startOfDay();
        $request->date_to =  $request->date_to ? Carbon::parse($request->date_to)->setTimezone($this->getUserTimezone())->endOfDay() : Carbon::today()->setTimezone($this->getUserTimezone())->endOfDay();
        $numberPerPage = $request->numberPerPage ? $request->numberPerPage : 50;
        $className = get_class(new Customer());

        $vendTransactions = VendTransaction::query()
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
            ->leftJoin('vend_prefixes', 'vend_prefixes.id', '=', 'vends.vend_prefix_id')
            ->filterTransactionIndex($request)
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
                'vend_transactions.interface_type',
                'vend_transactions.is_multiple',
                'vend_transactions.is_refunded',
                'vend_transaction_items_json',
                'vend_transactions.is_payment_received',
                'vend_transactions.items_json',
            )->paginate($numberPerPage === 'All' ? 10000 : $numberPerPage)
            ->withQueryString();

            $totals = VendTransaction::query()
            ->leftJoin('vend_channel_errors', 'vend_channel_errors.id', '=', 'vend_transactions.vend_channel_error_id')
            ->leftJoin('delivery_platform_orders', 'delivery_platform_orders.vend_transaction_id', '=', 'vend_transactions.id')
            ->join('vends', 'vends.id', '=', 'vend_transactions.vend_id')
            ->filterTransactionIndex($request)
            ->whereNotIn('vend_transactions.vend_id', function($query) {
                $query->select('id')
                    ->from('vends')
                    ->where('is_testing', true);
            })
            ->select(
                DB::raw('CAST(ROUND(COALESCE(SUM(vend_transactions.amount), 0), 2) AS SIGNED) AS amount'),
                DB::raw('CAST(COUNT(*) AS SIGNED) AS count'),
                DB::raw('CAST(SUM(CASE WHEN is_multiple = 1 AND delivery_platform_orders.id IS NOT NULL THEN 1 ELSE 0 END) AS SIGNED) AS multiple_count_delivery_platform'),
                DB::raw('CAST(SUM(CASE WHEN is_multiple = 1 AND delivery_platform_orders.id IS NULL THEN 1 ELSE 0 END) AS SIGNED) AS multiple_count_machine'),
                DB::raw('CAST(SUM(CASE
                                    WHEN vend_transactions.is_multiple = 1
                                    THEN (SELECT COUNT(*) FROM vend_transaction_items WHERE vend_transaction_items.vend_transaction_id = vend_transactions.id)
                                    ELSE 1
                                END) AS SIGNED) AS total_qty'),
                DB::raw('CAST(ROUND(COALESCE(SUM(CASE
                                    WHEN vend_channel_errors.code = 0 OR vend_channel_errors.code = 6 OR vend_channel_errors.code IS NULL OR is_multiple = true
                                    THEN vend_transactions.amount ELSE 0 END), 0), 2) AS SIGNED) AS success_amount'),
                DB::raw('CAST(COUNT(CASE
                                    WHEN vend_channel_errors.code = 0 OR vend_channel_errors.code = 6 OR vend_channel_errors.code IS NULL OR is_multiple = true
                                    THEN 1 ELSE NULL END) AS SIGNED) AS success_count'),
                DB::raw('CAST(SUM(CASE
                                    WHEN vend_transactions.is_multiple = 1
                                    THEN (SELECT COUNT(*) FROM vend_transaction_items WHERE vend_transaction_items.vend_transaction_id = vend_transactions.id AND (vend_channel_error_code = 0 OR vend_channel_error_code = 6 OR vend_channel_error_code IS NULL))
                                    WHEN vend_transactions.is_multiple = 0 AND (vend_channel_errors.code = 0 OR vend_channel_errors.code = 6 OR vend_channel_errors.code IS NULL)
                                    THEN 1
                                    ELSE 0
                                    END) AS SIGNED) AS success_total_qty'),
                DB::raw('ROUND(COALESCE(SUM(CASE
                                    WHEN vend_transactions.is_multiple = 1
                                    THEN (SELECT COUNT(*) FROM vend_transaction_items WHERE vend_transaction_items.vend_transaction_id = vend_transactions.id AND (vend_channel_error_code = 0 OR vend_channel_error_code = 6 OR vend_channel_error_code IS NULL))
                                    WHEN vend_transactions.is_multiple = 0 AND (vend_channel_errors.code = 0 OR vend_channel_errors.code = 6 OR vend_channel_errors.code IS NULL)
                                    THEN 1
                                    ELSE 0
                                    END), 0) * 100.0 / NULLIF(SUM(CASE
                                    WHEN vend_transactions.is_multiple = 1
                                    THEN (SELECT COUNT(*) FROM vend_transaction_items WHERE vend_transaction_items.vend_transaction_id = vend_transactions.id)
                                    ELSE 1
                                    END), 0), 2) AS success_total_qty_rate'),
                DB::raw('ROUND(COUNT(CASE
                                    WHEN vend_channel_errors.code = 0 OR vend_channel_errors.code = 6 OR vend_channel_errors.code IS NULL OR is_multiple = true
                                    THEN 1 ELSE NULL END) * 100.0 / NULLIF(COUNT(*), 0), 2) AS success_count_rate')
            )
            ->first();





        return Inertia::render('Vend/Transaction', [
            'categories' => CategoryResource::collection(
                Category::where('classname', $className)->orderBy('name')->get()
            ),
            'categoryGroups' => CategoryGroupResource::collection(
                CategoryGroup::where('classname', $className)->orderBy('name')->get()
            ),
            'locationTypeOptions' => LocationTypeResource::collection(
                LocationType::orderBy('sequence')->get()
            ),
            'operatorOptions' => OperatorResource::collection(
                Operator::orderBy('name')->get()
            ),
            'paymentMethods' => PaymentMethodResource::collection(PaymentMethod::orderBy('name')->get()),
            'vendTransactions' => VendTransactionResource::collection(
                $vendTransactions
            ),
            'totals' => $totals,
            'vendChannelErrors' => VendChannelErrorResource::collection(VendChannelError::orderBy('code')->get()),
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

    public function exportTransactionExcel(Request $request)
    {
        $request->merge(['sortKey' => $request->sortKey ? $request->sortKey : 'transaction_datetime']);
        $request->merge(['sortBy' => $request->sortBy ? $request->sortBy : false]);
        $request->date_from =  $request->date_from ? Carbon::parse($request->date_from)->setTimezone($this->getUserTimezone())->startOfDay() : Carbon::today()->setTimezone($this->getUserTimezone())->startOfDay();
        $request->date_to =  $request->date_to ? Carbon::parse($request->date_to)->setTimezone($this->getUserTimezone())->endOfDay() : Carbon::today()->setTimezone($this->getUserTimezone())->endOfDay();

        $vendTransactions = VendTransaction::query()
        ->with([
            'vendTransactionItems.product',
            'vendTransactionItems.vendChannelError',
            'vendTransactionItems.unitCost',
        ])
        ->leftJoin('customers', 'customers.id', '=', 'vend_transactions.customer_id')
        ->leftJoin('location_types', 'location_types.id', '=', 'customers.location_type_id')
        ->leftJoin('operators', 'operators.id', '=', 'vend_transactions.operator_id')
        ->leftJoin('payment_methods', 'payment_methods.id', '=', 'vend_transactions.payment_method_id')
        ->leftJoin('products', 'products.id', '=', 'vend_transactions.product_id')
        ->leftJoin('unit_costs', 'unit_costs.id', '=', 'vend_transactions.unit_cost_id')
        ->leftJoin('vend_channels', 'vend_channels.id', '=', 'vend_transactions.vend_channel_id')
        ->leftJoin('vend_channel_errors', 'vend_channel_errors.id', '=', 'vend_transactions.vend_channel_error_id')
        ->join('vends', 'vends.id', '=', 'vend_transactions.vend_id')
        ->leftJoin('vend_prefixes', 'vend_prefixes.id', '=', 'vends.vend_prefix_id')
        ->filterTransactionIndex($request)
        ->select(
            'vend_transactions.id',
            'vend_transactions.order_id',
            'vend_transactions.transaction_datetime',
            'vends.code AS vend_code',
            'vends.name AS vend_name',
            'vend_prefixes.name AS vend_prefix_name',
            'customers.id AS customer_id',
            'customers.code AS customer_code',
            'customers.name AS customer_name',
            'customers.person_id',
            'customers.virtual_customer_prefix',
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
            'vend_transactions.amount',
            'vend_transactions.interface_type',
            'vend_transactions.is_multiple',
            'vend_transactions.is_refunded',
            'vend_transactions.revenue',
            'vend_transactions.is_payment_received',
            'vend_transactions.items_json',
            'vend_transactions.vend_channel_code'
        )
        ->get();

        $data = [];
        foreach($vendTransactions as $vendTransaction) {
            $data[] = [
                'order_id' => $vendTransaction->order_id,
                'transaction_datetime' => Carbon::parse($vendTransaction->transaction_datetime)->toDateTimeString(),
                'machine_id' => $vendTransaction->vend_code ? $vendTransaction->vend_code : '',
                'machine_prefix' => $vendTransaction->vend_prefix_name ?
                                    $vendTransaction->vend_prefix_name :
                                    '',
                'customer_id' => $vendTransaction->customer_id + 20000,
                'customer_code' => $vendTransaction->person_id ?
                                    $vendTransaction->virtual_customer_code :
                                    '',
                'customer_name' => $vendTransaction->customer_name,
                'channel' => $vendTransaction->vend_channel_code ? $vendTransaction->vend_channel_code : '',
                'product_code' => $vendTransaction->product_code,
                'product_name' => $vendTransaction->product_name,
                'price_type' => $vendTransaction->vend_channel_amount ==  $vendTransaction->amount ?
                                'P1' :
                                ($vendTransaction->vend_channel_amount2 ==  $vendTransaction->amount ? 'P2' : '' ),
                'amount' => $vendTransaction->amount/ 100,
                'amount_breakdown' => $vendTransaction->is_multiple ? ($vendTransaction->amount - VendTransactionItem::withSum('vendChannel', 'amount')->whereIn('id', $vendTransaction->vendTransactionItems->pluck('id'))->get()->sum('vend_channel_sum_amount'))/ 100 : $vendTransaction->amount/ 100,
                // 'sales_before_gst' => $vendTransaction->revenue/ 100,
                'unit_cost' => $vendTransaction->cost ?
                                $vendTransaction->cost/100 :
                                '',
                'payment_method' => $vendTransaction->payment_method_name,
                'error_code' => $vendTransaction->vend_channel_error_code,
                'location_type' => $vendTransaction->location_type_name,
                'operator' => $vendTransaction->operator_code,
                'is_successful' => $vendTransaction->vend_channel_error_code ? ($vendTransaction->vend_channel_error_code == 0 || $vendTransaction->vend_channel_error_code == 6 ? 'Successful' : "Unsuccessful") : 'Successful',
                'is_refunded' => $vendTransaction->is_refunded ? 'Yes' : '',
                'is_multiple' => $vendTransaction->is_multiple ? 'Yes' : 'No',
                'multiple_qty' => $vendTransaction->is_multiple ? $vendTransaction->vendTransactionItems->count() : 1,
                'txn_src' => $vendTransaction->interface_type,
            ];

            if($vendTransaction->vendTransactionItems) {
                $vendTransactionItems = $vendTransaction
                    ->vendTransactionItems()
                    ->with([
                        'vendChannel',
                        'vendTransaction',
                        'vendTransaction.vend',
                        'vendTransaction.vend.vendPrefix',
                        'vendTransaction.customer.locationType',
                        'vendTransaction.operator',
                        'product',
                        'unitCost',
                        'vendChannelError',
                    ])
                    ->get();
                foreach($vendTransaction->vendTransactionItems as $vendTransactionItem) {
                    $data[] = [
                        'order_id' => $vendTransactionItem->vendTransaction->order_id,
                        'transaction_datetime' => Carbon::parse($vendTransactionItem->vendTransaction->transaction_datetime)->toDateTimeString(),
                        'vend_id' => $vendTransactionItem->vendTransaction->vend->code,
                        'machine_prefix' => $vendTransactionItem->vendTransaction->vend && $vendTransactionItem->vendTransaction->vend->vendPrefix ? $vendTransactionItem->vendTransaction->vend->vendPrefix->name : '',
                        'customer_id' => $vendTransactionItem->vendTransaction->customer ? $vendTransactionItem->vendTransaction->customer->id + 20000 : '',
                        'customer_code' => $vendTransactionItem->vendTransaction->customer && $vendTransactionItem->vendTransaction->customer->person_id ? $vendTransactionItem->vendTransaction->customer->virtual_customer_code : '',
                        'customer_name' => $vendTransactionItem->vendTransaction->customer ? $vendTransactionItem->vendTransaction->customer->name : '',
                        'channel' => (int)$vendTransactionItem->vend_channel_code,
                        'product_code' => $vendTransactionItem->product ? $vendTransactionItem->product->code : '',
                        'product_name' => $vendTransactionItem->product ? $vendTransactionItem->product->name : '',
                        'price_type' => 'P1',
                        'amount' => '',
                        'amount_breakdown' => $vendTransactionItem->vendChannel ? $vendTransactionItem->vendChannel->amount/100 : '',
                        // 'sales_before_gst' => '',
                        'unit_cost' => $vendTransactionItem->unitCost ?
                                        $vendTransactionItem->unitCost->cost :
                                        '',
                        'payment_method' => $vendTransaction->payment_method_name,
                        'error_code' => $vendTransactionItem->vendChannelError ? $vendTransactionItem->vendChannelError->code : '',
                        'location_type' => $vendTransactionItem->vendTransaction->customer && $vendTransactionItem->vendTransaction->customer->locationType ? $vendTransactionItem->vendTransaction->customer->locationType->name : '',
                        'operator' => $vendTransactionItem->vendTransaction->operator ? $vendTransactionItem->vendTransaction->operator->code : '',
                        'is_successful' => $vendTransactionItem->vendChannelError ? ($vendTransactionItem->vendChannelError->code == 0 || $vendTransactionItem->vendChannelError->code == 6 ? 'Successful' : "Unsuccessful") : 'Successful',
                        'is_refunded' => '',
                        'is_multiple' => $vendTransactionItem->vendTransaction->is_multiple ? 'Yes' : 'No',
                        'multiple_qty' => 0,
                        'txn_src' => $vendTransactionItem->vendTransaction->interface_type,
                    ];
                }
            }
        }
        return (new FastExcel($this->yieldOneByOne($data)))->download('Vend_transactions_'.Carbon::now()->toDateTimeString().'.xlsx');
    }

    public function exportVendSnapshotExcel($vendSnapshotId)
    {
        $vendSnapshot = VendSnapshot::findOrFail($vendSnapshotId);

        return (new FastExcel($this->yieldOneByOne($vendTransactions)))->download('Vend_transactions_'.Carbon::now()->toDateTimeString().'.xlsx', function ($vendTransaction) {
            return [
                'Order ID' => $vendTransaction->order_id,
                'Transaction Datetime' => Carbon::parse($vendTransaction->transaction_datetime)->toDateTimeString(),
                'Vend ID' => $vendTransaction->vend->code,
                'Customer ID' => $vendTransaction->customer ? $vendTransaction->customer->id + 20000 : '',
                'Customer Name' => $vendTransaction->customer_id ? $vendTransaction->customer->name : '',
                'Channel' => $vendTransaction->vend_channel_code,
                'Product Code' => $vendTransaction->product()->exists() ?
                                $vendTransaction->product->code :
                                '',
                'Product Name' => $vendTransaction->product()->exists() ?
                                $vendTransaction->product->name :
                                '',
                'Amount' => $vendTransaction->amount/ 100,
                'Sales (before GST)' => $vendTransaction->revenue/ 100,
                'Unit Cost' => $vendTransaction->unitCost()->exists() ?
                                $vendTransaction->unitCost->cost/ 100 :
                                '',
                'Payment Method' => $vendTransaction->paymentMethod ? $vendTransaction->paymentMethod->name : '',
                'Error' => $vendTransaction->vend_transaction_json &&
                            $vendTransaction->vend_transaction_json['SErr'] ?
                            $vendTransaction->vend_transaction_json['SErr'] :
                            ($vendTransaction->vendChannelError && $vendTransaction->vendChannelError->code ?? ''),
            ];
        });
    }

    private function yieldOneByOne($items) {
        foreach($items as $item) {
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

        if($request->operator_id) {
            $vend->operator_id = $request->operator_id;
        }else {
            $vend->operator_id = auth()->user()->operator_id;
        }
        $vend->save();

        return redirect()->route('settings');
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
        if($request->status) {
            $status = $request->status;
            switch($status) {
                case 'factory':
                    $request->merge([
                        'is_active' => false,
                        'is_testing' => true,
                    ]);
                    break;
                case 'active':
                    $request->merge([
                        'is_active' => true,
                        'is_testing' => false,
                    ]);
                    break;
                case 'inactive':
                    $request->merge([
                        'is_active' => false,
                        'is_testing' => false,
                    ]);
                    break;
            }
        }

        $vend = Vend::findOrFail($vendID);

        if($request->product_mapping_id != $vend->product_mapping_id) {
            $request->merge([
                'upcoming_product_mapping_id' => null,
            ]);
            $isProductMappingChanged = true;
        }
        $vend->update([
            'name' => $request->name,
            'begin_date' => $request->begin_date,
            'key_id' => $request->key_id,
            'cashless_terminal_id' => $request->cashless_terminal_id,
            'claw_machine_board_id' => $request->claw_machine_board_id,
            'claw_machine_body_id' => $request->claw_machine_body_id,
            'label_name' => $request->label_name,
            'lcd_monitor_id' => $request->lcd_monitor_id,
            'menu_frame_id' => $request->menu_frame_id,
            'modem_type_id' => $request->modem_type_id,
            'modem_unit_id' => $request->modem_unit_id,
            'is_active' => $request->is_active,
            'is_testing' => $request->is_testing,
            'product_mapping_id' => $request->product_mapping_id,
            'serial_num' => $request->serial_num,
            'simcard_id' => $request->simcard_id,
            'termination_date' => $request->termination_date,
            'upcoming_product_mapping_id' => $request->upcoming_product_mapping_id,
            'vend_config_id' => $request->vend_config_id,
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

        if($isProductMappingChanged and $vend->product_mapping_id) {
            $this->productMappingService->syncChannels(ProductMapping::find($vend->product_mapping_id));
        }

        if($request->operator_id != $vend->operator_id) {
            $vend->update([
                'operator_id' => $request->operator_id,
            ]);

            if($vend->customer) {
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
                'coin_count' => $vend->parameter_json && isset($vend->parameter_json['CoinCnt']) ? $vend->parameter_json['CoinCnt']/ 100 : null,
                'is_door_open' => $vend->parameter_json && isset($vend->parameter_json['door']) ? ($vend->parameter_json['door'] == 'open' ? true : false) : false,
                'is_mqtt' => $vend->is_mqtt,
                'is_mqtt_active' => $vend->is_mqtt_active,
                'mqtt_last_updated_at' => $vend->mqtt_last_updated_at,
                'is_online' => $vend->is_online,
                'is_sensor' => $vend->parameter_json && isset($vend->parameter_json['Sensor']) ? ($vend->parameter_json['Sensor'] % 2 == 0 ?  true : false) : false,
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
        if($vend->customer && $vend->customer->person_id) {
            Http::get(env('CMS_URL') . '/api/person/' . $vend->customer->person_id . '/detach-vendcode');
        }

        $vend->customer_id = null;
        $vend->save();

        if($returnUrl == 'vends') {
            return redirect()->route('vends.edit', [$vendID]);
        }else if ($returnUrl == 'settings') {
            return redirect()->route('settings.edit', [$vendID]);
        }else if ($returnUrl == 'customers') {
            return redirect()->route('customers.edit', [$customerID]);
        }else {
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
                'coin_count' => $vend->parameter_json && isset($vend->parameter_json['CoinCnt']) ? $vend->parameter_json['CoinCnt']/ 100 : null,
                'is_door_open' => $vend->parameter_json && isset($vend->parameter_json['door']) ? ($vend->parameter_json['door'] == 'open' ? true : false) : false,
                'is_mqtt' => $vend->is_mqtt,
                'is_mqtt_active' => $vend->is_mqtt_active,
                'mqtt_last_updated_at' => $vend->mqtt_last_updated_at,
                'is_online' => $vend->is_online,
                'is_sensor' => $vend->parameter_json && isset($vend->parameter_json['Sensor']) ? ($vend->parameter_json['Sensor'] % 2 == 0 ?  true : false) : false,
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
        if($vend->customer && $vend->customer->person_id) {
            Http::get(env('CMS_URL') . '/api/person/' . $vend->customer->person_id . '/detach-vendcode');
        }

        $vend->customer_id = null;
        $vend->save();

        if($returnUrl == 'vends') {
            return redirect()->route('vends.edit', [$vendID]);
        }else if ($returnUrl == 'settings') {
            return redirect()->route('settings.edit', [$vendID]);
        }else if ($returnUrl == 'customers') {
            return redirect()->route('customers.edit', [$customerID]);
        }else {
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
        return (new FastExcel($this->yieldOneByOne($vendChannels)))->download('Vend_channels_'.Carbon::now()->toDateTimeString().'.xlsx', function ($vendChannel) {
            return [
                'Vend ID' => isset($vendChannel->vend_code) ? $vendChannel->vend_code : '',
                'Customer Name' => isset($vendChannel->customer_code) ?
                                    $vendChannel->customer_code.' '.$vendChannel->customer_name :
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
                'Price' => isset($vendChannel->amount) ? $vendChannel->amount/ 100 : 0,
                'Balance Percent(%)' => isset($vendChannel->capacity) && $vendChannel->capacity > 0 ? round($vendChannel->qty/ $vendChannel->capacity * 100) : 0,
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
                ->leftJoin('addresses', function($query) {
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

    public function editProducts(Request $request, $vendId)
    {
        $vend = Vend::findOrFail($vendId);
        $channels = $request->channels;

        foreach($channels as $channel) {
            if($channel['product_id'] === $channel['edited_product_id']) {
                continue;
            }else {
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
            'productCode' =>  0,
            'productName' => '',
            'channelCode' => $vendChannel->code,
            'paymentMethod' => 10,
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

          $fid = $vendChannel->id;
          $content = base64_encode(json_encode($result));
          $contentLength = strlen($content);
          $key = $vendChannel->vend && $vendChannel->vend->private_key ? $vendChannel->vend->private_key : '123456789110138A';
          $md5 = md5($fid.','.$contentLength.','.$content.$key);

          PublishMqtt::dispatch('CM'.$vendChannel->vend->code, $fid.','.$contentLength.','.$content.','.$md5)->onQueue('high');
        //   $this->mqttService->publish('CM'.$vendChannel->vend->code, $fid.','.$contentLength.','.$content.','.$md5);

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
        if($vendTemps) {
            for($i=0; $i<count($vendTemps); $i++) {
                if($i > 0) {
                    $past = Carbon::parse($vendTemps[$i - 1]['created_at']);
                    $current = Carbon::parse($vendTemps[$i]['created_at']);
                    $temPast = null;
                    $temCurrent = null;
                    if($past->diffInMinutes($current) >= 10) {
                        $temPast = $past;
                        $temCurrent = $temPast->copy()->addMinutes(10);
                        while($temCurrent->diffInMinutes($current) >= 10) {
                            $vendTemps->push([
                                'value' => 'NaN',
                                'created_at' => $temCurrent->copy()->jsonSerialize()
                            ]);
                            $temPast = $temCurrent;
                            $temCurrent = $temCurrent->copy()->addMinutes(10);
                        }
                    }
                    if($i == count($vendTemps) - 1 and $current->diffInMinutes(Carbon::now()) >= 10) {
                        $temCurrent = $current;
                        while($temCurrent->diffInMinutes(Carbon::now()) >= 10) {
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
