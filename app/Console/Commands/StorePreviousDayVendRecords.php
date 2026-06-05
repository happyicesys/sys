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
        SyncAvgSalesQtyProducts::dispatch($yesterday->toDateString());

        // Re-roll the day-level rollups over a trailing 3 days (was yesterday only)
        // so late-arriving or edited transactions for the last couple of days are
        // picked up, mirroring gp:compute-metrics' 3-day self-heal. Both jobs upsert,
        // so re-rolling a settled day is idempotent. vend_records and
        // vend_product_records stay in lockstep. The reconcile:sales-rollups command
        // covers drift older than this window.
        for ($i = 1; $i <= 3; $i++) {
            $day = Carbon::today()->subDays($i)->toDateString();
            StoreVendsRecord::dispatch($day, $day, true);
            StoreVendProductRecords::dispatch($day, $day);
        }
    }
}
