<?php

namespace App\Console\Commands;

use App\Mail\VMChannelErrorLogsMail;
use App\Models\VendingMachine;
use App\Models\VendingMachineChannelErrorLog;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class SendVendingMachineChannelErrorLogEmail extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'send:channel-error-logs-email';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send Vending Machine Error Logs Email';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
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
