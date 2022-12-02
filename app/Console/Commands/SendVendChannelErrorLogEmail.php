<?php

namespace App\Console\Commands;

use App\Mail\VendChannelErrorLogsMail;
use App\Models\Vend;
use App\Models\VendChannelErrorLog;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class SendVendChannelErrorLogEmail extends Command
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
        $vendChannelErrorLogs = VendChannelErrorLog::with([
            'vendChannel',
            'vendChannel.vend',
            'vendChannel.vend.latestVendBinding.customer',
            'vendChannelError'
        ])
            ->leftJoin('vend_channels', 'vend_channels.id', '=', 'vend_channel_error_logs.vend_channel_id')
            ->leftJoin('vends', 'vends.id', '=', 'vend_channels.vend_id')
            ->where('vend_channel_error_logs.created_at', '>=', $now->subHours($intervalHours))
            ->where('is_error_cleared', false)
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
