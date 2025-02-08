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
        try {
            $startDate = Carbon::parse($this->date)->subDays(28)->startOfDay();
            $endDate = Carbon::parse($this->date)->endOfDay();

            Product::chunk(20, function ($products) use ($startDate, $endDate) {
                foreach ($products as $product) {
                    $totalQuantity = VendTransaction::where('product_id', $product->id)
                        ->whereBetween('created_at', [$startDate, $endDate])
                        ->count();

                    $avgSevenDaysCount = $totalQuantity / 4;

                    $product->update(['avg_seven_days_count' => $avgSevenDaysCount]);
                }
            });

        } catch (\Exception $e) {
            Log::error('SyncAvgSalesQtyProducts Job Failed: ' . $e->getMessage());
            $this->fail($e);
        }
    }
}
