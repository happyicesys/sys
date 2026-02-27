<?php

namespace App\Console\Commands;

use App\Jobs\CopyProductLimits;
use Carbon\Carbon;
use Illuminate\Console\Command;

class CopyProductLimitFromYesterday extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'copy:product-limit-from-yesterday';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Copy the product limit from yesterday over the next five days';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // Define the range of dates
        $from = Carbon::today();
        $to = Carbon::today()->addDays(5);

        // Dispatch the job with Carbon instances
        CopyProductLimits::dispatch($from, $to);

        $this->info("Product limits from yesterday have been queued for copying over the next five days.");
    }
}
