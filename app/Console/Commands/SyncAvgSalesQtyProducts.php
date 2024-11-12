<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Jobs\SyncAvgSalesQtyProducts as SyncAvgSalesQtyProductsJob;
use Carbon\Carbon;

class SyncAvgSalesQtyProducts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sync:avg-sales-qty-products';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        SyncAvgSalesQtyProductsJob::dispatch(Carbon::today()->toDateString());
    }
}
