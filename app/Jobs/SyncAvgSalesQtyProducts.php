<?php

namespace App\Jobs;

use Carbon\Carbon;
use App\Models\Product;
use App\Models\VendTransaction;
use App\Models\VendTransactionItem;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

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
        $startDate = Carbon::parse($this->date)->subDays(28)->startOfDay();
        $endDate = Carbon::parse($this->date)->endOfDay();

        foreach($products as $product) {
            // Calculate total quantity sold in the past 28 days for the product
            $totalQuantity = VendTransaction::where('product_id', $product->id)
                ->whereBetween('created_at', [$startDate, $endDate])
                ->count();

            // Calculate the average quantity sold per day over 28 days
            $avgSevenDaysCount = $totalQuantity / 4;

            if($product->id == 776 or $product->id == 777 or $product->id == 778) {
                Log::info('Product ID: ' . $product->id . ' | Total Quantity: ' . $totalQuantity . ' | Avg 7 Days Count: ' . $avgSevenDaysCount);
            }

            // Save the calculated average to the product
            $product->avg_seven_days_count = $avgSevenDaysCount;
            $product->save();
        }
    }
}
