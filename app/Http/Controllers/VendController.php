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
        return Inertia::render('Vend/Index', [
            'vends' => VendResource::collection(
                Vend::query()
                                ->when($request->code, function($query, $search) {
                                    $query->where('code', 'LIKE', "%{$search}%");
                                })
                                ->when($request->serial_num, function($query, $search) {
                                    $query->where('serial_num', 'LIKE', "%{$search}%");
                                })
                                ->when($request->name, function($query, $search) {
                                    $query->where('name', 'LIKE', "%{$search}%");
                                })
                                ->orderBy('code')
                                ->paginate(100)
                                ->withQueryString()
                            ),
            'filters' => $request->only(['code', 'serial_num', 'name']),
        ]);
    }

    public function temp($vendId, $duration = 3)
    {
        $vend = Vend::findOrFail($vendId);

        return Inertia::render('Vend/Temp', [
            'duration' => $duration,
            'vendObj' => new VendResource($vend),
            'vendTempsObj' => VendTempResource::collection(
                $vend->vendTemps()->whereDate('created_at', '>=', Carbon::now()->subDays($duration))->get()
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
