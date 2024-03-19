<?php

namespace App\Console\Commands;

use App\Models\Vend;
use App\Jobs\StoreVendsRecord;
use Illuminate\Console\Command;

class StorePreviousDayVendRecords extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'store:previous-day-vend-records';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Store previous day vend records';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // store yesterday sales into vend records table
        StoreVendsRecord::dispatch();
    }
}
