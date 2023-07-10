<?php

namespace App\Console\Commands;

use App\Jobs\StoreVendsRecord;
use Carbon\Carbon;
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
        $yesterday = Carbon::yesterday()->toDateString();
        StoreVendsRecord::dispatch($yesterday, $yesterday);
    }
}
