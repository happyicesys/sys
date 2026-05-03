<?php

namespace App\Console\Commands;

use App\Models\Vend;
use App\Jobs\RemoveEmptyOpsJob;
use App\Jobs\RemoveOddTransactions;
use App\Jobs\StoreVendProductRecords;
use App\Jobs\StoreVendsRecord;
use App\Jobs\SyncAvgSalesQtyProducts;
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
        // store yesterday sales into vend records table
        $yesterday = Carbon::yesterday();
        RemoveOddTransactions::dispatch($yesterday->toDateString(), $yesterday->toDateString());
        RemoveEmptyOpsJob::dispatch($yesterday->toDateString());
        StoreVendsRecord::dispatch($yesterday->toDateString(), $yesterday->toDateString(), true);
        StoreVendProductRecords::dispatch($yesterday->toDateString(), $yesterday->toDateString());
        SyncAvgSalesQtyProducts::dispatch($yesterday->toDateString());
    }
}
