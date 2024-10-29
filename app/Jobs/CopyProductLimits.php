<?php

namespace App\Jobs;

use App\Models\ProductLimit;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class CopyProductLimits implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $dateFrom;
    protected $dateTo;

    /**
     * Create a new job instance.
     */
    public function __construct($dateFrom, $dateTo)
    {
        $this->dateFrom = Carbon::parse($dateFrom);
        $this->dateTo = Carbon::parse($dateTo);
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // Iterate over each day from $dateFrom to $dateTo
        for ($date = $this->dateFrom->copy(); $date->lte($this->dateTo); $date->addDay()) {
            // Get the previous day's product limits
            $previousDate = $date->copy();
            $previousProductLimits = ProductLimit::query()
                ->where('date', $previousDate->toDateString())
                ->get();

            // Copy each previous day's product limit to the current date
            foreach ($previousProductLimits as $productLimit) {
                // Skip if the limit already exists for the target date and product_id
                if (ProductLimit::where('date', $date->toDateString())
                                ->where('product_id', $productLimit->product_id)
                                ->exists()) {
                    continue;
                }

                // Create or update with the previous day's qty
                ProductLimit::updateOrCreate([
                    'date' => $date->toDateString(),
                    'product_id' => $productLimit->product_id,
                ], [
                    'created_by' => $productLimit->created_by,
                    'is_created_by_system' => true,
                    'qty' => $productLimit->qty, // Use qty from the previous day
                    'setup_date' => $productLimit->setup_date,
                ]);
            }
        }
    }
}
