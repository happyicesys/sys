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
        // Define the range of dates relative to 23:57.
        // We are preparing for the "flip" at midnight.
        // The brand new Day 5 (relative to 00:00) is Day 6 (relative to 23:57).
        $targetDate = Carbon::today()->addDays(6);
        $sourceDate = Carbon::today()->addDays(5);

        // Standard start log (Very fast)
        \App\Models\VendData::create([
            'type' => 'copy_product_limit_start',
            'vend_code' => 'SYSTEM',
            'value' => [
                'source_date' => $sourceDate->toDateString(),
                'target_date' => $targetDate->toDateString(),
                'timestamp' => now()->toDateTimeString(),
            ]
        ]);

        // Dispatch the background job to the 'low' priority queue
        // This keeps the midnight scheduler thread extremely fast and ensures the copy process
        // runs gracefully without competing for resources during the midnight rush.
        CopyProductLimits::dispatch($sourceDate, $targetDate)->onQueue('low');

        $this->info("Product limits copy job has been dispatched to the 'low' queue.");
    }
}
