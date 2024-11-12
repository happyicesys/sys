<?php

namespace App\Jobs;

use Carbon\Carbon;
use App\Models\Product;
use App\Models\VendTransaction;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SyncAvgSalesQtyProducts implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $date;
    /**
     * Create a new job instance.
     */
    public function __construct($date)
    {
        $this->date = $date;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $products = Product::all();

        // Define the date range: past 28 days
        $startDate = Carbon::parse($this->date)->subDays(28);
        $endDate = Carbon::parse($this->date);

        foreach ($products as $product) {
            // Calculate total quantity sold in the past 28 days for the product
            $totalQuantity = VendTransaction::where('product_id', $product->id)
                ->whereBetween('created_at', [$startDate, $endDate])
                ->count();

            // Calculate the average quantity sold per day over 28 days
            $avgSevenDaysCount = $totalQuantity / 4;

            // Save the calculated average to the product
            $product->avg_seven_days_count = $avgSevenDaysCount;
            $product->save();
        }
    }
}
