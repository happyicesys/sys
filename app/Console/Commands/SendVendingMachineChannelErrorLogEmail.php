<?php

namespace App\Console\Commands;

use App\Mail\VendingMachineChannelErrorLogs;
use App\Models\VendingMachine;
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
        $vendingMachines = VendingMachine::query()
            ->with([
                'vendingMachineChannels',
                'vendingMachineChannels.vendingMachineChannelErrorLogs' => function($query) use ($intervalHours, $now) {
                    $query->where('created_at', '>=', $now->subHours($intervalHours));
                },
                'vendingMachineChannels.vendingMachineChannelErrorLogs.vendingMachineChannelError'
                ])
            ->whereHas('vendingMachineChannels.vendingMachineChannelErrorLogs', function($query) use ($intervalHours, $now) {
                $query->where('created_at', '>=', $now->subHours($intervalHours));
            })
            ->orderBy('code')
            ->get();



        Mail::to(['daniel.ma@happyice.com.sg', 'kent@happyice.com.sg', 'stephen@happyice.com.sg'])
            ->send(new VendingMachineChannelErrorLogs($vendingMachines, $intervalHours));
    }
}
