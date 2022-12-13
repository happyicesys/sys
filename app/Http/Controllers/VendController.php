<?php

namespace App\Http\Controllers;

use App\Exports\VendTransactionExport;
use App\Http\Resources\CategoryResource;
use App\Http\Resources\CategoryGroupResource;
use App\Http\Resources\CountryResource;
use App\Http\Resources\PaymentMethodResource;
use App\Http\Resources\VendResource;
use App\Http\Resources\VendChannelErrorResource;
use App\Http\Resources\VendTransactionResource;
use App\Http\Resources\VendTempResource;
use App\Mail\VendChannelErrorLogsMail;
use App\Models\Category;
use App\Models\CategoryGroup;
use App\Models\Country;
use App\Models\Customer;
use App\Models\PaymentMethod;
use App\Models\Vend;
use App\Models\VendChannelError;
use App\Models\VendChannelErrorLog;
use App\Models\VendTemp;
use App\Models\VendTransaction;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;


class VendController extends Controller
{
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
            'countries' => CountryResource::collection(Country::orderBy('sequence')->orderBy('name')->get()),
            'vends' => VendResource::collection(
                Vend::with([
                    'latestVendBinding',
                    'latestVendBinding.customer',
                    // 'latestVendBinding.customer.addresses',
                    'latestVendBinding.customer.deliveryAddress',
                    'latestVendBinding.customer.category.categoryGroup',
                    'vendSevenDaysTransactions',
                    'vendTodayTransactions',
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
                    ->select('*', 'vends.id', 'vends.code')
                    ->filterIndex($request)
                    ->paginate($numberPerPage === 'All' ? 10000 : $numberPerPage)
                    ->withQueryString()
            ),
            'vendOptions' => VendResource::collection(Vend::orderBy('code')->get()),
            'vendChannelErrors' => VendChannelErrorResource::collection(VendChannelError::orderBy('code')->get()),
        ]);
    }

    public function temp(Request $request, $vendId, $type)
    {
        $duration = 1;
        if($request->duration) {
            $duration = $request->duration;
        }
        // dd($request->all());
        $vend = Vend::with('latestVendBinding.customer')->findOrFail($vendId);
        $startDate =  Carbon::now()->subDays($duration);
        $endDate =  Carbon::now();
        if($request->datetime_from) {
            $startDate = Carbon::parse($request->datetime_from)->setTimezone('Asia/Singapore');
        }
        if($request->datetime_to) {
            $endDate = Carbon::parse($request->datetime_to)->setTimezone('Asia/Singapore');
        }
        if($type == VendTemp::TYPE_CHAMBER) {
            $typeName = 'Temp 1';
            $vendTemps = $vend
            ->vendTemps()
            ->where('vend_temps.created_at', '>=', $startDate)
            ->where('vend_temps.created_at', '<=', $endDate)
            ->get();
        }else if($type == VendTemp::TYPE_EVAPORATOR) {
            $typeName = 'Temp 2';
            $vendTemps = $vend
            ->vendTempsEvaporator()
            ->where('vend_temps.created_at', '>=', $startDate)
            ->where('vend_temps.created_at', '<=', $endDate)
            ->get();
        }

        return Inertia::render('Vend/Temp', [
            'duration' => $duration,
            'type' => [
                'name' => $typeName,
                'value' => $type,
            ],
            'vendObj' => VendResource::make($vend),
            'vendTempsObj' => VendTempResource::collection($vendTemps),
            'startDate' => $startDate->format('D M d Y H:i:s'),
            'endDate' => $endDate->format('D M d Y H:i:s'),
            'startDateString' => $startDate->format('y-m-d H:i'),
            'endDateString' => $endDate->format('y-m-d H:i'),
        ]);
    }

    public function transactionIndex(Request $request)
    {
        $numberPerPage = $request->numberPerPage ? $request->numberPerPage : 100;
        $className = get_class(new Customer());

        return Inertia::render('Vend/Transaction', [
            'categories' => CategoryResource::collection(
                Category::where('classname', $className)->orderBy('name')->get()
            ),
            'categoryGroups' => CategoryGroupResource::collection(
                CategoryGroup::where('classname', $className)->orderBy('name')->get()
            ),
            'vends' => VendResource::collection(Vend::orderBy('code')->get()),
            'vendTransactions' => VendTransactionResource::collection(
                VendTransaction::with([
                    'paymentMethod',
                    'vend',
                    'vend.latestVendBinding.customer.category.categoryGroup',
                    'vendChannel',
                    'vendChannelError',
                    ])
                    ->filterTransactionIndex($request)
                    ->paginate($numberPerPage === 'All' ? 10000 : $numberPerPage)
                    ->withQueryString()
            ),
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
            'vend',
            'vend.latestVendBinding.customer.category.categoryGroup',
            'vendChannel',
            'vendChannelError',
            ])
            ->filterTransactionIndex($request)
            ->get();

        return (new VendTransactionExport(VendTransactionResource::collection($vendTransactions)))->download('Vend_transactions_'.Carbon::now()->toDateTimeString().'.xlsx');
    }
}
