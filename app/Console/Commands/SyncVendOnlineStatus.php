<?php

namespace App\Console\Commands;

use App\Jobs\SyncOnlineStatus;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class SyncVendOnlineStatus extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sync:vend-online-status';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Run scheduler check online status (more than 5 mins last updated time becomes offline)';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        SyncOnlineStatus::dispatch()->onQueue('default');
    }
}
