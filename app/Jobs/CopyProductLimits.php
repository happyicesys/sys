<?php

namespace App\Jobs;

use App\Models\ProductLimit;
use App\Models\Product;
use App\Models\VendData;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class CopyProductLimits implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $sourceDate;
    protected $targetDate;

    /**
     * Create a new job instance.
     *
     * @param  Carbon  $sourceDate  The date to copy FROM
     * @param  Carbon  $targetDate  The date to seed TO (the new Day 5)
     */
    public function __construct(Carbon $sourceDate, Carbon $targetDate)
    {
        $this->sourceDate = $sourceDate;
        $this->targetDate = $targetDate;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // 1. Fetch all active inventory products once
        $products = Product::query()
            ->where('is_active', true)
            ->where('is_inventory', true)
            ->get();

        $sourceDateStr = $this->sourceDate->toDateString();
        $targetDateStr = $this->targetDate->toDateString();

        // 2. Optimized Fetch: Use a single query to get all source and current target limits
        $sourceLimits = ProductLimit::where('date', $sourceDateStr)
            ->whereIn('product_id', $products->pluck('id'))
            ->get()
            ->keyBy('product_id');

        $targetLimits = ProductLimit::where('date', $targetDateStr)
            ->whereIn('product_id', $products->pluck('id'))
            ->get()
            ->keyBy('product_id');

        // Snapshot storage
        $snapshot = [];

        // 3. Main Loop
        foreach ($products as $product) {
            $sourceLimit = $sourceLimits->get($product->id);
            $targetLimit = $targetLimits->get($product->id);

            // Record the snapshot BEFORE changes
            $snapshot[$product->code] = [
                'name' => $product->name,
                'qty' => $sourceLimit ? $sourceLimit->qty : 'No',
            ];

            // 4. Respect future manual overrides (Green labels)
            if ($targetLimit && !$targetLimit->is_created_by_system) {
                continue;
            }

            // 5. Apply the "Relay" copy (X+1 from X)
            if ($sourceLimit) {
                ProductLimit::updateOrCreate([
                    'date' => $targetDateStr,
                    'product_id' => $product->id,
                ], [
                    'created_by' => $sourceLimit->created_by,
                    'is_created_by_system' => true,
                    'qty' => $sourceLimit->qty,
                    'setup_date' => $sourceLimit->setup_date,
                ]);
            } else {
                // If previous day was "No", ensure target day stays "No"
                if ($targetLimit) {
                    \App\Models\ProductLimit::where('id', $targetLimit->id)->delete();
                }
            }
        }

        // 6. Log detailed evidence to vend_data in bulk
        VendData::create([
            'type' => 'copy_product_limit_done',
            'vend_code' => 'SYSTEM',
            'value' => [
                'source_date' => $sourceDateStr,
                'target_date' => $targetDateStr,
                'products_processed' => $products->count(),
                'snapshot' => $snapshot,
                'timestamp' => now()->toDateTimeString(),
            ]
        ]);
    }
}
