<?php

namespace App\Http\Controllers;

use App\Http\Resources\VendResource;
use App\Http\Resources\VendTempResource;
use App\Mail\VendChannelErrorLogsMail;
use App\Models\Vend;
use App\Models\VendChannelErrorLog;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;


class VendController extends Controller
{
    public function index(Request $request)
    {
        $numberPerPage = $request->numberPerPage ? $request->numberPerPage : 100;

        return Inertia::render('Vend/Index', [
            'vends' => VendResource::collection(
                Vend::with([
                    'vendChannels',
                    'vendChannels.vendChannelErrorLogs',
                    'vendChannels.vendChannelErrorLogs.vendChannelError',
                    ])
                    ->when($request->code, function($query, $search) {
                        $query->where('code', 'LIKE', "%{$search}%");
                    })
                    ->when($request->serialNum, function($query, $search) {
                        $query->where('serial_num', 'LIKE', "%{$search}%");
                    })
                    ->when($request->name, function($query, $search) {
                        $query->where('name', 'LIKE', "%{$search}%");
                    })
                    ->when($request->tempHigherThan, function($query, $search) {
                        if(is_numeric($search)) {
                            $query->where('temp', '>=', $search * 10);
                        }
                    })
                    ->when($request->sortKey, function($query, $search) use ($request) {
                        $query->orderBy($search, filter_var($request->sortBy, FILTER_VALIDATE_BOOLEAN) ? 'asc' : 'desc' );
                    })
                    ->paginate($numberPerPage === 'All' ? 10000 : $numberPerPage)
                    ->withQueryString()
            ),
            'filters' => $request->only(['code', 'serialNum', 'name', 'temp', 'sortKey', 'sortBy', 'numberPerPage']),
        ]);
    }

    public function temp($vendId, $duration = 1)
    {
        $vend = Vend::findOrFail($vendId);

        $startDate =  Carbon::now()->subDays($duration);
        $endDate =  Carbon::now();

        return Inertia::render('Vend/Temp', [
            'duration' => $duration,
            'startDate' => $startDate->toDateString(),
            'endDate' => $endDate->toDateString(),
            'vendObj' => new VendResource($vend),
            'vendTempsObj' => VendTempResource::collection(
                $vend->vendTemps()->whereDate('created_at', '>=', $startDate)->get()
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
}
