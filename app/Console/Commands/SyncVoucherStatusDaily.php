<?php

namespace App\Console\Commands;

use App\Jobs\SyncVoucherStatus;
use Illuminate\Console\Command;

class SyncVoucherStatusDaily extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sync:voucher-status-daily';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync voucher status daily';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        SyncVoucherStatus::dispatch();
    }
}
