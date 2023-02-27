<?php

namespace App\Http\Controllers;

// use App\Exports\VendTempExport;
// use App\Exports\VendTransactionExport;
use Rap2hpoutre\FastExcel\FastExcel;
use App\Http\Resources\CategoryResource;
use App\Http\Resources\CategoryGroupResource;
use App\Http\Resources\CountryResource;
use App\Http\Resources\OperatorResource;
use App\Http\Resources\PaymentMethodResource;
use App\Http\Resources\VendResource;
use App\Http\Resources\VendChannelErrorResource;
use App\Http\Resources\VendFanResource;
use App\Http\Resources\VendTransactionResource;
use App\Http\Resources\VendTempResource;
use App\Mail\VendChannelErrorLogsMail;
use App\Models\Category;
use App\Models\CategoryGroup;
use App\Models\Country;
use App\Models\Customer;
use App\Models\Operator;
use App\Models\PaymentMethod;
use App\Models\Vend;
use App\Models\VendChannel;
use App\Models\VendChannelError;
use App\Models\VendChannelErrorLog;
use App\Models\VendData;
use App\Models\VendTemp;
use App\Models\VendTransaction;
use App\Models\PaymentGateway\Midtrans;
use App\Traits\GetUserTimezone;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;
use Spatie\Permission\Models\Role;

// use PhpMqtt\Client\Facades\MQTT;
// use App\Jobs\ProcessVendData;

class VendController extends Controller
{
    use GetUserTimezone;

    public function index(Request $request)
    {
        $numberPerPage = $request->numberPerPage ? $request->numberPerPage : 100;
        $className = get_class(new Customer());

        return Inertia::render('Vend/Index', [
            'categories' => CategoryResource::collection(
                Category::where('classname', $className)->orderBy('name')->get()
            ),
            'categoryGroups' => CategoryGroupResource::collection(
                CategoryGroup::where('classname', $className)->orderBy('name')->get()
            ),
            'constTempError' => VendTemp::TEMPERATURE_ERROR,
            // 'countries' => CountryResource::collection(Country::orderBy('sequence')->orderBy('name')->get()),
            'operatorOptions' => OperatorResource::collection(
                Operator::all()
            ),
            'vends' => VendResource::collection(
                Vend::with([
                    'latestVendBinding',
                    'latestVendBinding.customer',
                    // 'latestVendBinding.customer.addresses',
                    'latestVendBinding.customer.deliveryAddress',
                    'latestVendBinding.customer.category.categoryGroup',
                    'productMapping',
                    // 'vendSevenDaysTransactions',
                    ])
                    ->leftJoin('vend_bindings', function($query) {
                        $query->on('vend_bindings.vend_id', '=', 'vends.id')
                                ->where('is_active', true)
                                ->latest('begin_date')
                                ->limit(1);
                    })
                    ->leftJoin('customers', 'customers.id', '=', 'vend_bindings.customer_id')
                    ->leftJoin('addresses', function($query) {
                        $query->on('addresses.modelable_id', '=', 'customers.id')
                                ->where('addresses.modelable_type', '=', 'App\Models\Customer')
                                ->where('addresses.type', '=', 2)
                                ->limit(1);
                    })
                    ->select('*', 'vends.id', 'vends.code', 'vends.name')
                    ->filterIndex($request)
                    ->orderBy('vends.is_online', 'desc')->orderBy('vends.code', 'asc')
                    ->paginate($numberPerPage === 'All' ? 10000 : $numberPerPage)
                    ->withQueryString()
            ),
            // 'vendOptions' => VendResource::collection(Vend::orderBy('code')->get()),
            'vendChannelErrors' => VendChannelErrorResource::collection(VendChannelError::orderBy('code')->get()),
        ]);
    }

    public function temp(Request $request, $vendId, $type)
    {
        $duration = 3;
        if($request->duration) {
            $duration = $request->duration;
        }
        $vend = Vend::with('latestVendBinding.customer')->findOrFail($vendId);
        // dd($duration);
        $startDate =  $request->durationType == 'day' || !$request->durationType ? Carbon::now()->setTimezone($this->getUserTimezone())->subDays($duration) : Carbon::now()->setTimezone($this->getUserTimezone())->subHours($duration);
        $endDate =  Carbon::now()->setTimezone($this->getUserTimezone());
        if($request->datetime_from) {
            $startDate = Carbon::parse($request->datetime_from)->setTimezone($this->getUserTimezone());
        }
        if($request->datetime_to) {
            $endDate = Carbon::parse($request->datetime_to)->setTimezone($this->getUserTimezone());
        }
        $types = [$type];
        if($request->types) {
            $types = array_merge($types, $request->types);
        }

        $typeName = 'Temp '.$type;

        $vendTemps = $vend
        ->vendTemps()
        ->whereIn('type', $types)
        ->where('value', '!=', VendTemp::TEMPERATURE_ERROR)
        ->where('vend_temps.created_at', '>=', $startDate)
        ->where('vend_temps.created_at', '<=', $endDate)
        ->get();

        $fans = [];
        if($request->fans) {
            $fans = array_merge($fans, $request->fans);
        }else {
            $fans = [];
        }
        // dd($fans);
        $vendFans = $vend
            ->vendFans()
            ->whereIn('type', $fans)
            ->where('vend_fans.created_at', '>=', $startDate)
            ->where('vend_fans.created_at', '<=', $endDate)
            ->get();

        // dd($vendTemps->toArray(), $vendFans->toArray());
        return Inertia::render('Vend/Temp', [
            'duration' => $duration,
            'type' => [
                'name' => $typeName,
                'value' => $type,
            ],
            'types' => $types,
            'fans' => $fans,
            'vendObj' => VendResource::make($vend),
            'vendTempsObj' => VendTempResource::collection($vendTemps),
            'vendFansObj' => VendFanResource::collection($vendFans),
            'startDate' => $startDate->format('D M d Y H:i:s'),
            'endDate' => $endDate->format('D M d Y H:i:s'),
            'startDateString' => $startDate->format('y-m-d H:i'),
            'endDateString' => $endDate->format('y-m-d H:i'),
        ]);
    }

    public function exportTempExcel(Request $request, $vendId, $type)
    {
        $duration = 3;
        if($request->duration) {
            $duration = $request->duration;
        }
        // dd($request->all());
        $vend = Vend::with('latestVendBinding.customer')->findOrFail($vendId);
        // dd($duration);
        $startDate =  $request->durationType == 'day' || !$request->durationType ? Carbon::now()->setTimezone($this->getUserTimezone())->subDays($duration) : Carbon::now()->setTimezone($this->getUserTimezone())->subHours($duration);
        $endDate =  Carbon::now()->setTimezone($this->getUserTimezone());
        if($request->datetime_from) {
            $startDate = Carbon::parse($request->datetime_from)->setTimezone($this->getUserTimezone());
        }
        if($request->datetime_to) {
            $endDate = Carbon::parse($request->datetime_to)->setTimezone($this->getUserTimezone());
        }
        $types = [$type];
        if($request->types) {
            $types = array_merge($types, $request->types);
        }

        $typeName = 'Temp '.$type;

        $vendTemps = $vend
        ->vendTemps()
        ->whereIn('type', $types)
        ->where('value', '!=', VendTemp::TEMPERATURE_ERROR)
        ->where('vend_temps.created_at', '>=', $startDate)
        ->where('vend_temps.created_at', '<=', $endDate)
        ->get();

        return (new FastExcel($vendTemps))->download('Vend_Temps_'.Carbon::now()->toDateTimeString().'.xlsx', function ($vendTemp) {
            return [
                'Vend ID' => $vendTemp->vend->code,
                'Date Time' => Carbon::parse($vendTemp->created_at)->toDateTimeString(),
                'Temp' => $vendTemp->value/ 10,
                'Type' => 'T'.$vendTemp->type,
            ];
        });

        // return (new VendTempExport(VendTempResource::collection($vendTemps)))->download('Vend_temps_'.Carbon::now()->toDateTimeString().'.xlsx');
    }

    public function transactionIndex(Request $request)
    {
        $numberPerPage = $request->numberPerPage ? $request->numberPerPage : 100;
        $className = get_class(new Customer());
        $vendTransactions =
                VendTransaction::with([
                    'paymentMethod',
                    'product',
                    'vend',
                    'vend.latestVendBinding.customer.category.categoryGroup',
                    'vendChannel',
                    'vendChannelError',
                    ])
                    ->filterTransactionIndex($request);

        $vendTransactionsTotal = clone $vendTransactions;
        $vendTransactionsCount = clone $vendTransactions;
        $vendTransactionsTotal = $vendTransactionsTotal->isSuccessful()->sum('amount');
        $vendTransactionsCount = $vendTransactionsCount->isSuccessful()->count();

        return Inertia::render('Vend/Transaction', [
            'categories' => CategoryResource::collection(
                Category::where('classname', $className)->orderBy('name')->get()
            ),
            'categoryGroups' => CategoryGroupResource::collection(
                CategoryGroup::where('classname', $className)->orderBy('name')->get()
            ),
            'operatorOptions' => OperatorResource::collection(
                Operator::all()
            ),
            // 'vends' => VendResource::collection(Vend::orderBy('code')->get()),
            'vendTransactions' => VendTransactionResource::collection(
                $vendTransactions
                ->paginate($numberPerPage === 'All' ? 10000 : $numberPerPage)
                ->withQueryString()
            ),
            'vendTransactionsTotal' => $vendTransactionsTotal,
            'vendTransactionsCount' => $vendTransactionsCount,
            'vendChannelErrors' => VendChannelErrorResource::collection(VendChannelError::orderBy('code')->get()),
            'paymentMethods' => PaymentMethodResource::collection(PaymentMethod::orderBy('name')->get()),

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
        $vendTransactions = VendTransaction::with([
            'paymentMethod',
            'vend.latestVendBinding.customer',
            'product',
            ])
            ->filterTransactionIndex($request)
            ->get();

        // return (new VendTransactionExport(VendTransactionResource::collection($vendTransactions)))->download('Vend_transactions_'.Carbon::now()->toDateTimeString().'.xlsx');

        // return (new FastExcel($vendTransactions))->download('Vend_transactions_'.Carbon::now()->toDateTimeString().'.xlsx', function ($vendTransaction) {
        //     return [
        //         'Order ID' => $vendTransaction->order_id,
        //         'Transaction Datetime' => Carbon::parse($vendTransaction->transaction_datetime)->toDateTimeString(),
        //         'Vend ID' => $vendTransaction->vend->code,
        //         'Customer Name' => $vendTransaction->vend->latestVendBinding ?
        //                             $vendTransaction->vend->latestVendBinding->customer->code.''.$vendTransaction->vend->latestVendBinding->customer->name :
        //                             $vendTransaction->vend->name,
        //         'Channel' => $vendTransaction->vendChannel->code,
        //         'Product Code' => $vendTransaction->product ?
        //                         $vendTransaction->product->code :
        //                         '',
        //         'Product Name' => $vendTransaction->product ?
        //                         $vendTransaction->product->name :
        //                         '',
        //         'Amount' => $vendTransaction->amount/ 100,
        //         'Payment Method' => $vendTransaction->paymentMethod->name,
        //         'Error' => $vendTransaction->vendChannelError ? $vendTransaction->vendChannelError->code : ''
        //     ];
        // });

        return (new FastExcel($this->yieldOneByOne($vendTransactions)))->download('Vend_transactions_'.Carbon::now()->toDateTimeString().'.xlsx', function ($vendTransaction) {
            return [
                'Order ID' => $vendTransaction->order_id,
                'Transaction Datetime' => Carbon::parse($vendTransaction->transaction_datetime)->toDateTimeString(),
                'Vend ID' => $vendTransaction->vend->code,
                'Customer Name' => $vendTransaction->vend->latestVendBinding ?
                                    $vendTransaction->vend->latestVendBinding->customer->code.''.$vendTransaction->vend->latestVendBinding->customer->name :
                                    $vendTransaction->vend->name,
                'Channel' => $vendTransaction->vend_transaction_json['SId'],
                'Product Code' => $vendTransaction->product ?
                                $vendTransaction->product->code :
                                '',
                'Product Name' => $vendTransaction->product ?
                                $vendTransaction->product->name :
                                '',
                'Amount' => $vendTransaction->amount/ 100,
                'Payment Method' => $vendTransaction->paymentMethod ? $vendTransaction->paymentMethod->name : '',
                'Error' => $vendTransaction->vend_transaction_json['SErr'] ? $vendTransaction->vend_transaction_json['SErr'] : 0,
            ];
        });
    }

    private function yieldOneByOne($items) {
        foreach($items as $item) {
            yield $item;
        }
    }

    public function update(Request $request, $vendId)
    {
        $request->validate([
            'serial_num' => 'nullable|numeric',
            'private_key' => 'nullable',
        ]);

        $vend = Vend::findOrFail($vendId);

        $vend->update([
            'name' => $request->name,
            'serial_num' => $request->serial_num,
            'private_key' => $request->private_key,
        ]);

        return redirect()->route('vends');
    }

    public function unbindCustomer($vendId)
    {
        $vend = Vend::findOrFail($vendId);

        $vend->latestVendBinding->delete();

        return redirect()->route('vends');
    }

    public function exportChannelExcel(Request $request)
    {
        // dd($request->all());
        $vendChannels = VendChannel::with([
            'vend.latestVendBinding.customer.deliveryAddress',
            'vend.latestVendBinding.customer.category.categoryGroup',
            'product',
            ])
            ->leftJoin('vends', 'vends.id', '=', 'vend_channels.vend_id')
            ->leftJoin('vend_bindings', function($query) {
                $query->on('vend_bindings.vend_id', '=', 'vends.id')
                        ->where('vend_bindings.is_active', true)
                        ->latest('begin_date')
                        ->limit(1);
            })
            ->leftJoin('customers', 'customers.id', '=', 'vend_bindings.customer_id')
            ->leftJoin('addresses', function($query) {
                $query->on('addresses.modelable_id', '=', 'customers.id')
                        ->where('addresses.modelable_type', '=', 'App\Models\Customer')
                        ->where('addresses.type', '=', 2)
                        ->limit(1);
            })
            ->select('*', 'vends.id', 'vends.code AS vend_code', 'vend_channels.code AS vend_channel_code', 'vends.name')
            ->when($request->channel_codes, function($query, $search) {
                if(strpos($search, ',') !== false) {
                    $search = explode(',', $search);
                }else {
                    $search = [$search];
                }
            })
            ->where('capacity', '>', 0)
            ->filterIndex($request)
            ->get();
            // die($vendChannels);

        return (new FastExcel($this->yieldOneByOne($vendChannels)))->download('Vend_channels_'.Carbon::now()->toDateTimeString().'.xlsx', function ($vendChannel) {
            return [
                'Vend ID' => $vendChannel->vend_code,
                'Customer Name' => $vendChannel->vend->latestVendBinding ?
                                    $vendChannel->vend->latestVendBinding->customer->code.' '.$vendChannel->vend->latestVendBinding->customer->name :
                                    $vendChannel->vend->name,
                'Channel' => $vendChannel->vend_channel_code,
                'Product Code' => $vendChannel->product ?
                                $vendChannel->product->code :
                                '',
                'Product Name' => $vendChannel->product ?
                                $vendChannel->product->name :
                                '',
                'Qty' => $vendChannel->qty,
                'Capacity' => $vendChannel->capacity,
                'Price' => $vendChannel->amount/ 100,
                'Balance Percent(%)' => $vendChannel->capacity ? round($vendChannel->qty/ $vendChannel->capacity * 100) : '',
            ];
        });
        // return true;
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
