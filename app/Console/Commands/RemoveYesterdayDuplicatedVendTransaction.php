<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Jobs\RemoveDuplicatedVendTransaction;
use Carbon\Carbon;

class RemoveYesterdayDuplicatedVendTransaction extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'remove:today-duplicated-vend-transaction';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Dispatch a job to remove duplicate vend transactions from yesterday, keeping only the latest one';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // Set the date range for yesterday
        $dateFrom = Carbon::today()->startOfDay();
        $dateTo = Carbon::today()->endOfDay();

        // Dispatch the job
        RemoveDuplicatedVendTransaction::dispatch($dateFrom, $dateTo);
    }
}
