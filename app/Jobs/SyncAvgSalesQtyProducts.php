<?php

namespace App\Jobs;

use Carbon\Carbon;
use App\Models\Product;
use App\Services\VendTransactionSalesAggregator;
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

            $counts = VendTransactionSalesAggregator::productTotals($startDate, $endDate)
                ->pluck('total_count', 'product_id');

            // Step 2: Loop through products in chunks
            Product::chunk(50, function ($products) use ($counts) {
                foreach ($products as $product) {
                    $count = $counts[$product->id] ?? 0;
                    $avg = $count / 4;

                    $product->update([
                        'avg_seven_days_count' => $avg
                    ]);
                }
            });

        } catch (\Exception $e) {
            Log::error('SyncAvgSalesQtyProducts Job Failed: ' . $e->getMessage());
            $this->fail($e);
        }
    }
}
