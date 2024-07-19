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
use App\Http\Resources\OperatorResource;
use App\Http\Resources\PaymentMethodResource;
use App\Http\Resources\ProductResource;
use App\Http\Resources\VendDBResource;
use App\Http\Resources\VendResource;
use App\Http\Resources\VendChannelResource;
use App\Http\Resources\VendChannelErrorResource;
use App\Http\Resources\VendFanResource;
use App\Http\Resources\VendPrefixResource;
use App\Http\Resources\VendTransactionResource;
use App\Http\Resources\VendTempResource;
use App\Jobs\SyncVendCustomerCms;
use App\Jobs\Vend\SaveVendChannelsJson;
use App\Mail\VendChannelErrorLogsMail;
use App\Models\Category;
use App\Models\CategoryGroup;
use App\Models\Country;
use App\Models\Customer;
use App\Models\LocationType;
use App\Models\Operator;
use App\Models\PaymentMethod;
use App\Models\PaymentGatewayLog;
use App\Models\Product;
use App\Models\SellingPrice;
use App\Models\Vend;
use App\Models\VendChannel;
use App\Models\VendChannelError;
use App\Models\VendChannelErrorLog;
use App\Models\VendData;
use App\Models\VendPrefix;
use App\Models\VendRecord;
use App\Models\VendSnapshot;
use App\Models\VendTemp;
use App\Models\VendTransaction;
use App\Models\PaymentGateways\Midtrans;
use App\Models\PaymentGateways\Omise;
use App\Services\HistoryService;
use App\Services\MqttService;
use App\Services\PaymentGatewayService;
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

    protected $historyService;
    protected $mqttService;
    protected $paymentGatewayService;
    protected $runningNumberService;
    protected $vendDataService;
    protected $vendDispenseService;


    public function __construct(
        HistoryService $historyService,
        MqttService $mqttService,
        PaymentGatewayService $paymentGatewayService,
        RunningNumberService $runningNumberService,
        VendDataService $vendDataService,
        VendDispenseService $vendDispenseService
    )
    {
        $this->middleware(['permission:read vends'])->only(['index', 'indexCustomer']);
        $this->middleware(['permission:read transactions'])->only('transactionIndex');
        $this->historyService = $historyService;
        $this->mqttService = $mqttService;
        $this->paymentGatewayService = $paymentGatewayService;
        $this->runningNumberService = $runningNumberService;
        $this->vendDataService = $vendDataService;
        $this->vendDispenseService = $vendDispenseService;
    }

    public function index(Request $request)
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
                $request->merge(['operators' => [auth()->user()->operator_id, Operator::where('code', 'HIMD')->first() ? Operator::where('code', 'HIMD')->first()->id : null]]);
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
                'vendChannels',
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
                'vends.is_temp_error',
                'vends.is_testing',
                'vends.last_updated_at',
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
            'categories' => CategoryResource::collection(
                Category::where('classname', $className)->orderBy('name')->get()
            ),
            'categoryGroups' => CategoryGroupResource::collection(
                CategoryGroup::where('classname', $className)->orderBy('name')->get()
            ),
            'cmsEndpoint' => env('CMS_URL'),
            'constTempError' => VendTemp::TEMPERATURE_ERROR,
            'deviceTypes' => Vend::DEVICE_TYPE_MAPPINGS,
            'indexType' => $request->indexType,
            'locationTypeOptions' => LocationTypeResource::collection(
                LocationType::orderBy('sequence')->get()
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
                $request->merge(['operators' => [auth()->user()->operator_id, Operator::where('code', 'HIMD')->first() ? Operator::where('code', 'HIMD')->first()->id : null]]);
            }
        }
        $request->merge([
            'indexType' => 'customers',
            'numberPerPage' => isset($request->numberPerPage) ? $request->numberPerPage : 50,
            'sortKey' => isset($request->sortKey) ? $request->sortKey : 'balance_percent',
            'sortBy' => isset($request->sortBy) ? $request->sortBy : true,
        ]);
        $className = get_class(new Customer());

        $vends = Customer::query()
            ->with([
                'vend.vendChannels',
                'vend.vendChannels.product.thumbnail',
                'vend.vendChannels.product.sellingPrices',
                'vend.vendChannels.vendChannelErrorLogs' => function($query) {
                    $query->where('created_at', '>=', Carbon::today()->subDays(29));
                },
                'vend.vendChannels.vendChannelErrorLogs.vendChannelError',
            ])
            ->leftJoin('vends', 'vends.customer_id', '=', 'customers.id')
            ->leftJoin('vend_prefixes', 'vend_prefixes.id', '=', 'vends.vend_prefix_id')
            ->leftJoin('categories', 'categories.id', '=', 'customers.category_id')
            ->leftJoin('category_groups', 'category_groups.id', '=', 'categories.category_group_id')
            ->leftJoin('location_types', 'location_types.id', '=', 'customers.location_type_id')
            ->leftJoin('operators', 'operators.id', '=', 'customers.operator_id')
            ->leftJoin('product_mappings', 'product_mappings.id', '=', 'vends.product_mapping_id')
            ->leftJoin('addresses', function($query) {
                $query->on('addresses.modelable_id', '=', 'customers.id')
                        ->where('addresses.modelable_type', '=', 'App\Models\Customer')
                        ->where('addresses.type', '=', 2)
                        ->limit(1);
            })
            ->leftJoin(DB::raw('
                (
                    SELECT vend_id, SUM(amount * qty) AS total_stock_amount
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
                    SUM(vend_channels.amount * (vend_channels.capacity - vend_channels.qty)) AS actual_stock_in_value,
                    SUM(vend_channels.capacity - vend_channels.qty) AS actual_stock_in_qty
                FROM
                    vend_channels
                INNER JOIN
                    products ON vend_channels.product_id = products.id
                WHERE
                    products.is_available = true
                AND vend_channels.is_active = true
                AND vend_channels.capacity > 0
                GROUP BY
                    vend_channels.vend_id
            ) AS vc_stock
        '), 'vc_stock.vend_id', '=', 'vends.id')
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
                'vends.is_temp_error',
                'vends.is_testing',
                DB::raw('DATE(customers.last_invoice_date) AS last_invoice_date'),
                DB::raw('DATE(customers.next_invoice_date) AS next_invoice_date'),
                'vends.last_updated_at',
                'vends.mqtt_last_updated_at',
                'vends.out_of_stock_sku_percent',
                DB::raw('CASE WHEN customers.is_active THEN vends.parameter_json ELSE customers.snap_parameter_json END AS parameter_json'),
                'vends.product_mapping_id',
                'vends.private_key',
                // DB::raw('CASE WHEN customers.is_active THEN vends.vend_channels_json ELSE customers.snap_vend_channels_json END AS vend_channels_json'),
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
                'customers.is_active AS is_active',
                'customers.is_active AS customer_is_active',
                'customers.location_type_id',
                'customers.name AS customer_name',
                'customers.operator_id',
                'customers.person_json',
                'customers.person_id AS person_id',
                'customers.selling_price_type',
                'customers.termination_date',
                'customers.virtual_customer_prefix',
                'customers.virtual_customer_code',
                'location_types.name AS location_type_name',
                'product_mappings.name AS product_mapping_name',
                'product_mappings.remarks AS product_mapping_remarks',
                'operators.code AS operator_code',
                'operators.name AS operator_name',
                'addresses.postcode AS postcode',
                'vend_prefixes.name AS vend_prefix_name',
                'vc.total_stock_amount',
                'vc_cost.total_stock_cost',
                'vc_stock.actual_stock_in_value',
                'vc_stock.actual_stock_in_qty'
            );
        $vends = $this->filterVendsDB($vends, $request);
        $vends = $this->filterOperatorDB($vends, 'customers');

        $vends = $vends->paginate($request->numberPerPage === 'All' ? 10000 : $request->numberPerPage)
            ->withQueryString();


        $totals = [
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

        return Inertia::render('Vend/CustomerIndex', [
            'categories' => CategoryResource::collection(
                Category::where('classname', $className)->orderBy('name')->get()
            ),
            'categoryGroups' => CategoryGroupResource::collection(
                CategoryGroup::where('classname', $className)->orderBy('name')->get()
            ),
            'cmsEndpoint' => env('CMS_URL'),
            'constTempError' => VendTemp::TEMPERATURE_ERROR,
            'deviceTypes' => Vend::DEVICE_TYPE_MAPPINGS,
            'indexType' => $request->indexType,
            'locationTypeOptions' => LocationTypeResource::collection(
                LocationType::orderBy('sequence')->get()
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
            'vendPrefixOptions' => VendPrefixResource::collection(
                VendPrefix::orderBy('name')->get()
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
        ->with('product.thumbnail')
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

    public function transactionIndex(Request $request)
    {
        if(!$request->operators) {
            if(auth()->user()->operator->code == 'HIPL') {
                $request->merge(['operators' => [auth()->user()->operator_id, Operator::where('code', 'HIMD')->first() ? Operator::where('code', 'HIMD')->first()->id : null]]);
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
                'vend_transactions.is_multiple',
                'vend_transactions.is_refunded',
                'vend_transaction_items_json',
                'vend_transactions.is_payment_received',
                'vend_transactions.items_json',
            )->paginate($numberPerPage === 'All' ? 10000 : $numberPerPage)
            ->withQueryString();

        $totals = VendTransaction::query()
            ->leftJoin('vend_channel_errors', 'vend_channel_errors.id', '=', 'vend_transactions.vend_channel_error_id')
            ->join('vends', 'vends.id', '=', 'vend_transactions.vend_id')
            ->filterTransactionIndex($request)
            ->whereNotIn('vend_transactions.vend_id', function($query) {
                $query->select('id')
                    ->from('vends')
                    ->where('is_testing', true);
            })
            ->where(function($query) {
                $query
                    ->where('vend_channel_errors.code', 0)
                    ->orWhere('vend_channel_errors.code', 6)
                    ->orWhere('vend_channel_errors.code', null)
                    ->orWhere('is_multiple', true);
            })
            ->select(
                DB::raw('ROUND(COALESCE(SUM(vend_transactions.amount), 0), 2) AS amount'),
                DB::raw('COUNT(*) AS count')
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
                'vend_id' => $vendTransaction->vend_code ? $vendTransaction->vend_code : '',
                'machine_prefix' => $vendTransaction->vend_prefix_name ?
                                    $vendTransaction->vend_prefix_name :
                                    '',
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
                'sales_before_gst' => $vendTransaction->revenue/ 100,
                'unit_cost' => $vendTransaction->cost ?
                                $vendTransaction->cost/100 :
                                '',
                'payment_method' => $vendTransaction->payment_method_name,
                'error_code' => $vendTransaction->vend_channel_error_code,
                'location_type' => $vendTransaction->location_type_name,
                'operator' => $vendTransaction->operator_code,
                'is_successful' => $vendTransaction->vend_channel_error_code ? ($vendTransaction->vend_channel_error_code == 0 || $vendTransaction->vend_channel_error_code == 6 ? 'Successful' : "Unsuccessful") : 'Successful',
                'is_refunded' => $vendTransaction->is_refunded ? 'Yes' : '',
            ];

            if($vendTransaction->vendTransactionItems) {

                foreach($vendTransaction->vendTransactionItems as $vendTransactionItem) {
                    $data[] = [
                        'order_id' => '',
                        'transaction_datetime' => '',
                        'vend_id' => '',
                        'machine_prefix' => '',
                        'customer_code' => '',
                        'customer_name' => '',
                        'channel' => $vendTransactionItem->vend_channel_code,
                        'product_code' => $vendTransactionItem->product ? $vendTransactionItem->product->code : '',
                        'product_name' => $vendTransactionItem->product ? $vendTransactionItem->product->name : '',
                        'price_type' => '',
                        'amount' => '',
                        'sales_before_gst' => '',
                        'unit_cost' => $vendTransactionItem->unitCost ?
                                        $vendTransactionItem->unitCost->cost :
                                        '',
                        'payment_method' => '',
                        'error_code' => $vendTransactionItem->vendChannelError ? $vendTransactionItem->vendChannelError->code : '',
                        'location_type' => '',
                        'operator' => '',
                        'is_successful' => $vendTransactionItem->vendChannelError ? ($vendTransactionItem->vendChannelError->code == 0 || $vendTransactionItem->vendChannelError->code == 6 ? 'Successful' : "Unsuccessful") : 'Successful',
                        'is_refunded' => '',
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
        $dataArr = [];
        $input = collect($request->all());
        $items = VendChannel::query()
            ->with([
                'product:id,code,name,desc',
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
        }

        $vend->update([
            'name' => $request->name,
            'begin_date' => $request->begin_date,
            'key_id' => $request->key_id,
            'cashless_terminal_id' => $request->cashless_terminal_id,
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

        if ($request->hasFile('files')) {
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
