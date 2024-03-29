<?php

namespace App\Console\Commands;

use App\Jobs\StoreVendsRecord;
use Illuminate\Console\Command;

class StoreVendRecordsWithDateRange extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'store:vend-records-date-range {from} {to}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Store vend records with date range';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $dateFrom = $this->argument('from');
        $dateTo = $this->argument('to');

        StoreVendsRecord::dispatch($dateFrom, $dateTo);
    }
}
