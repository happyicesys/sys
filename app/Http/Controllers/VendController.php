<?php

namespace App\Http\Controllers;

use App\Http\Resources\CategoryResource;
use App\Http\Resources\CategoryGroupResource;
use App\Http\Resources\PaymentMethodResource;
use App\Http\Resources\VendResource;
use App\Http\Resources\VendChannelErrorResource;
use App\Http\Resources\VendTransactionResource;
use App\Http\Resources\VendTempResource;
use App\Mail\VendChannelErrorLogsMail;
use App\Models\Category;
use App\Models\CategoryGroup;
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
        $sortKey = $request->sortKey ? $request->sortKey : 'code';
        $sortBy = $request->sortBy ? $request->sortBy : true;
        $className = get_class(new Customer());

        return Inertia::render('Vend/Index', [
            'categories' => CategoryResource::collection(
                Category::where('classname', $className)->orderBy('name')->get()
            ),
            'categoryGroups' => CategoryGroupResource::collection(
                CategoryGroup::where('classname', $className)->orderBy('name')->get()
            ),
            'vends' => VendResource::collection(
                Vend::with([
                    'latestVendBinding.customer',
                    'latestVendBinding.customer.category.categoryGroup',
                    'vendChannels' => function($query) {
                        $query->where('code', '<', 1000)->where('is_active', true);
                    },
                    'vendChannels.vendChannelErrorLogs',
                    'vendChannels.vendChannelErrorLogs.vendChannelError',
                    ])
                    // ->has('vendChannels')
                    ->when($request->code, function($query, $search) {
                        $query->where('code', 'LIKE', "%{$search}%");
                    })
                    ->when($request->serialNum, function($query, $search) {
                        $query->where('serial_num', 'LIKE', "%{$search}%");
                    })
                    ->when($request->customer_code, function($query, $search) {
                        $query->whereHas('latestVendBinding.customer', function($query) use ($search) {
                            $query->where('code', 'LIKE', "%{$search}%");
                        });
                    })
                    ->when($request->customer_name, function($query, $search) {
                        $query->whereHas('latestVendBinding.customer', function($query) use ($search) {
                            $query->where('name', 'LIKE', "%{$search}%");
                        });
                    })
                    ->when($request->categories, function($query, $search) {
                        $query->whereHas('latestVendBinding.customer.category', function($query) use ($search) {
                            $query->whereIn('id', $search);
                        });
                    })
                    ->when($request->categoryGroups, function($query, $search) {
                        $query->whereHas('latestVendBinding.customer.category.categoryGroup', function($query) use ($search) {
                            $query->whereIn('id', $search);
                        });
                    })
                    ->when($request->tempHigherThan, function($query, $search) {
                        if(is_numeric($search)) {
                            $query->where('temp', '>=', $search * 10);
                        }
                    })
                    ->when($request->vend_channel_error_id, function($query, $search) {
                        if($search === 'errors_only') {
                            $query->whereHas('vendChannels.vendChannelErrorLogs', function($query) {
                               $query->where('is_error_cleared', false);
                            });
                        }else if($search !== null) {
                            $query->whereHas('vendChannels.vendChannelErrorLogs', function($query) use ($search) {
                                $query->where('vend_channel_error_id', $search)->where('is_error_cleared', false);
                            });
                        }
                    })
                    ->when($sortKey, function($query, $search) use ($sortBy) {
                        $query->orderBy($search, filter_var($sortBy, FILTER_VALIDATE_BOOLEAN) ? 'asc' : 'desc' );
                    })
                    ->paginate($numberPerPage === 'All' ? 10000 : $numberPerPage)
                    ->withQueryString()
            ),
            'vendChannelErrors' => VendChannelErrorResource::collection(VendChannelError::orderBy('code')->get()),
        ]);
    }

    public function temp(Request $request, $vendId)
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
        return Inertia::render('Vend/Temp', [
            'duration' => $duration,
            'vendObj' => VendResource::make($vend),
            'vendTempsObj' => VendTempResource::collection(
                $vend
                ->vendTemps()
                ->where('vend_temps.created_at', '>=', $startDate)
                ->where('vend_temps.created_at', '<=', $endDate)
                ->get()
            ),
            'startDate' => $startDate->format('D M d Y H:i:s'),
            'endDate' => $endDate->format('D M d Y H:i:s'),
            'startDateString' => $startDate->format('y-m-d H:i'),
            'endDateString' => $endDate->format('y-m-d H:i'),
        ]);
    }

    public function transactionIndex(Request $request)
    {
        // dd($request->all());
        $numberPerPage = $request->numberPerPage ? $request->numberPerPage : 100;
        $sortKey = $request->sortKey ? $request->sortKey : 'transaction_datetime';
        $sortBy = $request->sortBy ? $request->sortBy : false;
        $startDate =  $request->date_from ?? Carbon::today()->startOfMonth()->toDateString();
        $endDate =  $request->date_to ?? Carbon::today()->toDateString();
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
                    ->when($request->codes, function($query, $search) {
                        $query->whereHas('vend', function($query) use ($search) {
                            $query->whereIn('id', $search);
                        });
                    })
                    ->when($request->errors, function($query, $search) {
                        $query->whereHas('vendChannel.vendChannelErrorLogs', function($query) use ($search) {
                            $query->where('vend_channel_error_id', 'IN', $search)->where('is_error_cleared', false);
                        });
                    })
                    ->when($request->paymentMethod, function($query, $search) {
                        $query->where('payment_method_id', $search);
                    })
                    ->when($request->categories, function($query, $search) {
                        $query->whereHas('vend.latestVendBinding.customer.category', function($query) use ($search) {
                            $query->whereIn('id', $search);
                        });
                    })
                    ->when($request->categoryGroups, function($query, $search) {
                        $query->whereHas('vend.latestVendBinding.customer.category.categoryGroup', function($query) use ($search) {
                            $query->whereIn('id', $search);
                        });
                    })
                    ->when($startDate, function($query, $search) {
                        $query->whereDate('transaction_datetime', '>=', $search);
                    })
                    ->when($endDate, function($query, $search) {
                        $query->whereDate('transaction_datetime', '<=', $search);
                    })
                    ->when($sortKey, function($query, $search) use ($sortBy) {
                        $query->orderBy($search, filter_var($sortBy, FILTER_VALIDATE_BOOLEAN) ? 'asc' : 'desc' );
                    })
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
}
