<?php

namespace App\Http\Controllers;

use App\Http\Resources\VendingMachineResource;
use App\Http\Resources\VendingMachineTempResource;
use App\Mail\VMChannelErrorLogsMail;
use App\Models\VendingMachine;
use App\Models\VendingMachineChannelErrorLog;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;


class VendingMachineController extends Controller
{
    public function index(Request $request)
    {
        return Inertia::render('VendingMachine/Index', [
            'vendingMachines' => VendingMachineResource::collection(
                VendingMachine::query()
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

    public function temp($vendingMachineId)
    {
        $vendingMachine = VendingMachine::findOrFail($vendingMachineId);

        return Inertia::render('VendingMachine/Temp', [
            'vendingMachineObj' => new VendingMachineResource($vendingMachine),
            'vendingMachineTempsObj' => VendingMachineTempResource::collection(
                $vendingMachine->vendingMachineTemps()->whereDate('created_at', '>=', Carbon::now()->subDays(7))->get()
            ),
        ]);
    }

    public function channelErrorLogsEmail()
    {
        $intervalHours = 24;
        $now = Carbon::now();
        $vendingMachineChannelErrorLogs = VendingMachineChannelErrorLog::with([
            'vendingMachineChannel',
            'vendingMachineChannel.vendingMachine',
            'vendingMachineChannelError'
        ])
            ->leftJoin('vending_machine_channels', 'vending_machine_channels.id', '=', 'vending_machine_channel_error_logs.vending_machine_channel_id')
            ->leftJoin('vending_machines', 'vending_machines.id', '=', 'vending_machine_channels.vending_machine_id')
            ->where('vending_machine_channel_error_logs.created_at', '>=', $now->subHours($intervalHours))
            ->orderBy('vending_machines.code')
            ->orderBy('vending_machine_channel_error_logs.created_at')
            ->select('*', 'vending_machine_channel_error_logs.created_at')
            ->get();

        Mail::to([
            'daniel.ma@happyice.com.sg',
            'kent@happyice.com.sg',
            'stephen@happyice.com.sg',
            'brianlee@happyice.com.my'
            ])
            ->send(new VMChannelErrorLogsMail($vendingMachineChannelErrorLogs, $intervalHours));
    }
}
