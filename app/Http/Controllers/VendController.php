<?php

namespace App\Http\Controllers;

// use App\Exports\VendTempExport;
// use App\Exports\VendTransactionExport;
use Rap2hpoutre\FastExcel\FastExcel;
use App\Http\Resources\CategoryResource;
use App\Http\Resources\CategoryGroupResource;
use App\Http\Resources\CountryResource;
use App\Http\Resources\LocationTypeResource;
use App\Http\Resources\OperatorResource;
use App\Http\Resources\PaymentMethodResource;
use App\Http\Resources\ProductResource;
use App\Http\Resources\VendDBResource;
use App\Http\Resources\VendResource;
use App\Http\Resources\VendChannelErrorResource;
use App\Http\Resources\VendFanResource;
use App\Http\Resources\VendTransactionDBResource;
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
use App\Models\Vend;
use App\Models\VendBinding;
use App\Models\VendChannel;
use App\Models\VendChannelError;
use App\Models\VendChannelErrorLog;
use App\Models\VendData;
use App\Models\VendSnapshot;
use App\Models\VendTemp;
use App\Models\VendTransaction;
use App\Models\PaymentGateways\Midtrans;
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
use Inertia\Inertia;
use Spatie\Permission\Models\Role;
use App\Models\PaymentGateways\Omise;


// use PhpMqtt\Client\Facades\MQTT;

class VendController extends Controller
{
    use GetUserTimezone, HasFilter;

    protected $mqttService;
    protected $paymentGatewayService;
    protected $runningNumberService;
    protected $vendDataService;
    protected $vendDispenseService;


    public function __construct(
        MqttService $mqttService,
        PaymentGatewayService $paymentGatewayService,
        RunningNumberService $runningNumberService,
        VendDataService $vendDataService,
        VendDispenseService $vendDispenseService
    )
    {
        $this->middleware(['permission:read vends'])->only('index');
        $this->middleware(['permission:read transactions'])->only('transactionIndex');
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
        $request->merge(['numberPerPage' => isset($request->numberPerPage) ? $request->numberPerPage : 50]);
        $request->merge(['sortKey' => isset($request->sortKey) ? $request->sortKey : 'balance_percent']);
        $request->merge(['sortBy' => isset($request->sortBy) ? $request->sortBy : true]);
        $className = get_class(new Customer());

        $vends = DB::table('vends')
            ->leftJoinSub(
                VendBinding::query()
                    ->select('vend_id', 'customer_id', DB::raw('MAX(begin_date) as begin_date'))
                    ->where('is_active', true)
                    ->groupBy('vend_id'),
                'vend_bindings',
                function ($join) {
                    $join->on('vend_bindings.vend_id', '=', 'vends.id');
                }
            )
            // ->leftJoin('vend_bindings', function($query) {
            //     $query->on('vend_bindings.vend_id', '=', 'vends.id')
            //             // ->where('vend_bindings.is_active', true)
            //             ->latest('begin_date')
            //             ->limit(1);
            // })
            ->leftJoin('customers', 'customers.id', '=', 'vend_bindings.customer_id')
            ->leftJoin('categories', 'categories.id', '=', 'customers.category_id')
            ->leftJoin('category_groups', 'category_groups.id', '=', 'categories.category_group_id')
            ->leftJoin('location_types', 'location_types.id', '=', 'customers.location_type_id')
            ->leftJoin('operator_vend', function($query) {
                $query->on('operator_vend.vend_id', '=', 'vends.id')
                        ->latest('operator_vend.begin_date')
                        ->limit(1);
            })
            ->leftJoin('operators', 'operators.id', '=', 'operator_vend.operator_id')
            ->leftJoin('product_mappings', 'product_mappings.id', '=', 'vends.product_mapping_id')
            ->leftJoin('addresses', function($query) {
                $query->on('addresses.modelable_id', '=', 'customers.id')
                        ->where('addresses.modelable_type', '=', 'App\Models\Customer')
                        ->where('addresses.type', '=', 2)
                        ->limit(1);
            })
            ->select(
                'operator_vend.operator_id',
                'vends.id',
                'vends.amount_average_day',
                'vends.begin_date',
                'vends.code',
                'vends.name',
                'vends.apk_ver_json',
                'vends.balance_percent',
                'vends.serial_num',
                'vends.name',
                'vends.temp',
                'vends.temp_updated_at',
                'vends.termination_date',
                'vends.coin_amount',
                'vends.firmware_ver',
                'vends.is_door_open',
                'vends.is_mqtt',
                'vends.is_mqtt_active',
                'vends.is_online',
                'vends.is_sensor_normal',
                'vends.is_temp_error',
                DB::raw('DATE(customers.last_invoice_date) AS last_invoice_date'),
                DB::raw('DATE(customers.next_invoice_date) AS next_invoice_date'),
                'vends.last_updated_at',
                'vends.mqtt_last_updated_at',
                'vends.out_of_stock_sku_percent',
                'vends.parameter_json',
                'vends.product_mapping_id',
                'vends.private_key',
                'vends.vend_channels_json',
                'vends.vend_channel_totals_json',
                'vends.vend_channel_error_logs_json',
                'vends.vend_transaction_totals_json',
                'vends.vend_type_id',
                'vends.virtual_vend_records_thirty_days_amount_average',
                'vends.is_active',
                'customers.cms_invoice_history',
                'customers.code AS customer_code',
                'customers.customer_json',
                'customers.name AS customer_name',
                'customers.person_id AS customer_person_id',
                'customers.location_type_id',
                'location_types.name AS location_type_name',
                'product_mappings.name AS product_mapping_name',
                'product_mappings.remarks AS product_mapping_remarks',
                'operators.name AS operator_name',
                'addresses.postcode AS postcode',
            );
        $vends = $this->filterVendsDB($vends, $request);
        $vends = $this->filterOperatorDB($vends);

        $vends = $vends->paginate($request->numberPerPage === 'All' ? 10000 : $request->numberPerPage)
            ->withQueryString();

        $totals = [
            'thirtyDays' => collect((clone $vends)
                            ->items())
                            ->sum(function($vend) {
                                return $vend->vend_transaction_totals_json ? json_decode($vend->vend_transaction_totals_json)->thirty_days_amount : 0;
                            })/100,
            'thirthyDaysAvg' => collect((clone $vends)
                            ->items())
                            ->sum(function($vend) {
                                return $vend->vend_transaction_totals_json ? json_decode($vend->vend_transaction_totals_json)->vend_records_thirty_days_amount_average : 0;
                            })/100,
        ];

        return Inertia::render('Vend/Index', [
            'categories' => CategoryResource::collection(
                Category::where('classname', $className)->orderBy('name')->get()
            ),
            'categoryGroups' => CategoryGroupResource::collection(
                CategoryGroup::where('classname', $className)->orderBy('name')->get()
            ),
            'constTempError' => VendTemp::TEMPERATURE_ERROR,
            'locationTypeOptions' => LocationTypeResource::collection(
                LocationType::orderBy('sequence')->get()
            ),
            'operatorOptions' => OperatorResource::collection(
                Operator::orderBy('name')->get()
            ),
            'productOptions' => ProductResource::collection(
                Product::query()
                    ->select('id', 'code', 'desc', 'name')
                    ->where('is_active', true)
                    ->where('is_inventory', true)
                    ->orderBy('code')
                    ->get()
            ),
            'totals' => $totals,
            'vends' => VendDBResource::collection($vends),
            'vendChannelErrors' => VendChannelErrorResource::collection(VendChannelError::orderBy('code')->get()),
        ]);
    }

    public function searchVendCode($vendCode)
    {
        $vends = Vend::query()
            ->leftJoin('operator_vend', function($query) {
                $query->on('operator_vend.vend_id', '=', 'vends.id')
                        ->where('operator_vend.is_main', true)
                        ->latest('operator_vend.begin_date')
                        ->limit(1);
            })
            ->leftJoin('operators', 'operators.id', '=', 'operator_vend.operator_id')
            ->where('vends.code', 'LIKE', "%{$vendCode}%")
            ->select(
                'vends.id',
                'vends.code AS vend_code',
                'operators.code AS operator_code',
                'operators.name AS operator_name'
            )
            ->get();

        return $vends;
    }

    public function restart($id)
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

        $this->mqttService->publish('CM'.$vend->code, $fid.','.$contentLength.','.$content.','.$md5);

        return true;
    }

    public function temp(Request $request, $vendId, $type)
    {
        $duration = 3;
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

        $request->types = empty($request->types) ? [1] : $request->types;

        $typeName = 'Temp '.$type;

        $vend = DB::table('vends')
            ->leftJoin('vend_bindings', function($query) {
                $query->on('vend_bindings.vend_id', '=', 'vends.id')
                        ->where('vend_bindings.is_active', true)
                        ->latest('begin_date')
                        ->limit(1);
            })
            ->leftJoin('customers', 'customers.id', '=', 'vend_bindings.customer_id')
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
            ->leftJoin('vend_bindings', function($query) {
                $query->on('vend_bindings.vend_id', '=', 'vends.id')
                        ->where('vend_bindings.is_active', true)
                        ->latest('begin_date')
                        ->limit(1);
            })
            ->leftJoin('customers', 'customers.id', '=', 'vend_bindings.customer_id')
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

    public function transactionIndex(Request $request)
    {
        $request->merge(['sortKey' => $request->sortKey ? $request->sortKey : 'transaction_datetime']);
        $request->merge(['sortBy' => $request->sortBy ? $request->sortBy : false]);
        $request->merge(['visited' => isset($request->visited) ? $request->visited : true]);
        $request->merge(['is_binded_customer' => isset($request->is_binded_customer) ? $request->is_binded_customer : 'all']);
        $request->date_from =  $request->date_from ? Carbon::parse($request->date_from)->setTimezone($this->getUserTimezone())->startOfDay() : Carbon::today()->setTimezone($this->getUserTimezone())->startOfDay();
        $request->date_to =  $request->date_to ? Carbon::parse($request->date_to)->setTimezone($this->getUserTimezone())->endOfDay() : Carbon::today()->setTimezone($this->getUserTimezone())->endOfDay();
        $numberPerPage = $request->numberPerPage ? $request->numberPerPage : 50;
        $className = get_class(new Customer());

        $vendTransactions = VendTransaction::query()
            ->with([
                'vend:id,code,name',
                'customer:id,code,name,customer_json',
                'operator:id,code,name',
                'paymentMethod:id,code,name',
                'product:id,code,name',
                'vendChannelError:id,desc',
            ])
            ->filterTransactionIndex($request)
            ->when($request->sortKey, function($query, $search) use ($request) {
                $query->orderBy($search, filter_var($request->sortBy, FILTER_VALIDATE_BOOLEAN) ? 'asc' : 'desc' );
            })
            ->paginate($numberPerPage === 'All' ? 10000 : $numberPerPage)
            ->withQueryString();

        // $totals = VendTransaction::query()
        //     ->filterTransactionIndex($request)
        //     ->whereIn('error_code_normalized', [0, 6])
        //     ->select(

        //         DB::raw('ROUND(COALESCE(SUM(vend_transactions.amount)/ 100, 0), 2) AS amount'),
        //         DB::raw('COUNT(*) AS count')
        //     )
        //     ->first();

        $totals = [
            'amount' => VendTransaction::query()
                ->filterTransactionIndex($request)
                ->where(function($query) {
                    $query->where('error_code_normalized', 0)
                        ->orWhere('error_code_normalized', 6)
                        ->orWhere('is_multiple', true);
                })
                ->sum('vend_transactions.amount'),
            'count' => VendTransaction::query()
                ->filterTransactionIndex($request)
                // ->whereIn('error_code_normalized', [0, 6])
                ->where(function($query) {
                    $query->where('error_code_normalized', 0)
                        ->orWhere('error_code_normalized', 6)
                        ->orWhere('is_multiple', true);
                })
                ->count()
        ];

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
                Operator::all()
            ),
            'paymentMethods' => PaymentMethodResource::collection(PaymentMethod::orderBy('name')->get()),
            'vendTransactions' => VendTransactionResource::collection(
                $vendTransactions
            ),
            'totals' => $totals,
            'vendChannelErrors' => VendChannelErrorResource::collection(VendChannelError::orderBy('code')->get()),
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
        $request->merge(['is_binded_customer' => isset($request->is_binded_customer) ? $request->is_binded_customer : 'all']);
        $request->date_from =  $request->date_from ? Carbon::parse($request->date_from)->setTimezone($this->getUserTimezone())->startOfDay() : Carbon::today()->setTimezone($this->getUserTimezone())->startOfDay();
        $request->date_to =  $request->date_to ? Carbon::parse($request->date_to)->setTimezone($this->getUserTimezone())->endOfDay() : Carbon::today()->setTimezone($this->getUserTimezone())->endOfDay();

        $vendTransactions = VendTransaction::query()
        ->with([
            'vend:id,code,name',
            'customer:id,code,name',
            'operator:id,code,name',
            'paymentMethod:id,code,name',
            'product:id,code,name',
            'vendChannelError:id,desc',
        ])
        ->filterTransactionIndex($request)
        ->get();

        // dd($vendTransactions->toArray());
        return (new FastExcel($this->yieldOneByOne($vendTransactions)))->download('Vend_transactions_'.Carbon::now()->toDateTimeString().'.xlsx', function ($vendTransaction) {
            return [
                'Order ID' => $vendTransaction->order_id,
                'Transaction Datetime' => Carbon::parse($vendTransaction->transaction_datetime)->toDateTimeString(),
                'Vend ID' => $vendTransaction->vend->code,
                'Customer Name' => $vendTransaction->customer_json && isset($vendTransaction->customer_json['code']) ?
                                        $vendTransaction->customer_json['code'].' '.$vendTransaction->customer_json['name'] : (
                                        $vendTransaction->vend_json && isset($vendTransaction->vend_json['latest_vend_binding']) ?
                                        $vendTransaction->vend_json['latest_vend_binding']['customer']['code'].' '.$vendTransaction->vend_json['latest_vend_binding']['customer']['name'] : $vendTransaction->vend_name
                                    ),
                'Channel' => $vendTransaction->vend_channel_code,
                // 'Product Code' => $vendTransaction->product ?
                //                 $vendTransaction->product->code :
                //                 '',
                'Product Code' => $vendTransaction->product_json ?
                                $vendTransaction->product_json['code'] :
                                ($vendTransaction->product ? $vendTransaction->product->code : ''),
                // 'Product Name' => $vendTransaction->product ?
                //                 $vendTransaction->product->name :
                //                 '',
                'Product Name' => $vendTransaction->product_json ?
                                $vendTransaction->product_json['name'] :
                                ($vendTransaction->product ? $vendTransaction->product->name : ''),
                'Amount' => $vendTransaction->amount/ 100,
                'Sales (before GST)' => $vendTransaction->revenue/ 100,
                'Unit Cost' => $vendTransaction->unit_cost ?
                                $vendTransaction->unit_cost/ 100 :
                                '',
                'Payment Method' => $vendTransaction->paymentMethod ? $vendTransaction->paymentMethod->name : '',
                'Error Code' => $vendTransaction->vend_transaction_json &&
                            isset($vendTransaction->vend_transaction_json['SErr']) ?
                            $vendTransaction->vend_transaction_json['SErr'] :
                            $vendTransaction->vend_channel_error_code,
                'Location Type' => $vendTransaction->location_type_json ?
                                $vendTransaction->location_type_json['name'] :
                                '',
            ];
        });
    }

    public function exportVendSnapshotExcel($vendSnapshotId)
    {
        $vendSnapshot = VendSnapshot::findOrFail($vendSnapshotId);

        return (new FastExcel($this->yieldOneByOne($vendTransactions)))->download('Vend_transactions_'.Carbon::now()->toDateTimeString().'.xlsx', function ($vendTransaction) {
            return [
                'Order ID' => $vendTransaction->order_id,
                'Transaction Datetime' => Carbon::parse($vendTransaction->transaction_datetime)->toDateTimeString(),
                'Vend ID' => $vendTransaction->vend->code,
                'Customer Name' => $vendTransaction->customer_json && isset($vendTransaction->customer_json['code']) ?
                                    $vendTransaction->customer_json['code'].' '.$vendTransaction->customer_json['name'] : (
                                        $vendTransaction->vend_json && isset($vendTransaction->vend_json['latest_vend_binding']) ?
                                        $vendTransaction->vend_json['latest_vend_binding']['customer']['code'].' '.$vendTransaction->vend_json['latest_vend_binding']['customer']['name'] : $vendTransaction->vend->name
                                    ),
                'Channel' => $vendTransaction->vend_transaction_json &&
                            $vendTransaction->vend_transaction_json['SId'] ?
                            $vendTransaction->vend_transaction_json['SId'] :
                            $vendTransaction->vendChannel->code,
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

        if($request->customer_id) {
            SyncVendCustomerCms::dispatchSync($vend->id, $request->customer_id);
        }

        if($request->operator_id) {
            $vend->operators()->sync([$request->operator_id]);
        }else {
            $vend->operators()->sync([auth()->user()->operator_id]);
        }

        return redirect()->route('settings');
    }

    public function update(Request $request, $vendId)
    {
        // dd($request->all());
        $request->validate([
            'private_key' => 'nullable',
        ]);

        $vend = Vend::findOrFail($vendId);

        $vend->update([
            'name' => $request->name,
            'begin_date' => $request->begin_date,
            'private_key' => $request->private_key,
            'termination_date' => $request->termination_date,
        ]);

        if($request->customer_id) {
            SyncVendCustomerCms::dispatchSync($vend->id, $request->customer_id);
        }else {
            if($vend->vendBindings()->exists()) {
                foreach($vend->vendBindings as $vendBinding) {
                    if($vendBinding->is_active) {
                        $vendBinding->update([
                            'is_active' => false,
                            'termination_date' => Carbon::now()->toDateString(),
                        ]);
                    }
                }
            }
        }

        if($request->operator_id) {
            $vend->operators()->sync([$request->operator_id]);
        }

        return redirect()->route('settings');
    }

    public function unbindCustomer($vendId)
    {
        $vend = Vend::findOrFail($vendId);

        $vend->latestVendBinding->update([
            'is_active' => false,
            'termination_date' => Carbon::now()->toDateString(),
        ]);

        // callback to cms to unbind vendcode
        if($vend->latestVendBinding->customer && $vend->latestVendBinding->customer->person_id) {
            Http::get(env('CMS_URL') . '/api/person/' . $vend->latestVendBinding->customer->person_id . '/detach-vendcode');
        }

        // return redirect()->route('vends');
        return redirect()->route('settings.edit', [$vendId, 'update']);
    }

    public function exportChannelExcel(Request $request)
    {
        $vendChannels = DB::table('vend_channels')
            ->leftJoin('products', 'products.id', '=', 'vend_channels.product_id')
            ->leftJoin('vends', 'vends.id', '=', 'vend_channels.vend_id')
            ->leftJoin('operator_vend', function($query) {
                $query->on('operator_vend.vend_id', '=', 'vends.id')
                        ->latest('operator_vend.begin_date')
                        ->limit(1);
            })
            ->leftJoin('vend_bindings', function($query) {
                $query->on('vend_bindings.vend_id', '=', 'vends.id')
                        ->where('vend_bindings.is_active', true)
                        ->latest('begin_date')
                        ->limit(1);
            })
            ->leftJoin('customers', 'customers.id', '=', 'vend_bindings.customer_id')
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
        $vendChannels = $this->filterOperatorDB($vendChannels);
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
                'latestVendBinding.customer:id,code,name',
                'logs',
            ])
            ->find($id);

        return Inertia::render('Vend/Edit', [
            'type' => 'update',
            'vend' => VendResource::make($vend),
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

          $this->mqttService->publish('CM'.$vendChannel->vend->code, $fid.','.$contentLength.','.$content.','.$md5);

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
