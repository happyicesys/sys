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
    protected $description = 'Copy the product limit from yesterday';

    /**
     * Execute the console command.
     */
    public function handle()
    {

        $from = Carbon::yesterday()->toDateString();
        $to = Carbon::today()->toDateString();

        CopyProductLimits::dispatch($from, $to);
    }
}
