<?php

namespace App\Console\Commands;

use App\Jobs\SyncActiveVendRecord;
use Illuminate\Console\Command;

class SyncActiveVendRecordByDateRange extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sync:active-vend-record-by-date-range {dateFrom} {dateTo}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync active vend record by date range';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $dateFrom = $this->argument('dateFrom');
        $dateTo = $this->argument('dateTo');

        SyncActiveVendRecord::dispatch($dateFrom, $dateTo);
    }
}
