<?php

namespace App\Jobs;

use App\Models\Product;
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
        $this->dateFrom = $dateFrom;
        $this->dateTo = $dateTo;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $productLimits = ProductLimit::query()
            ->where('date', Carbon::parse($this->dateFrom)->toDateString())
            ->get();

        if($productLimits) {
            foreach($productLimits as $productLimit) {
                ProductLimit::updateOrCreate([
                    'date' => Carbon::parse($this->dateTo)->toDateString(),
                    'product_id' => $productLimit->product_id
                ],
                [
                    'created_by' => $productLimit->created_by,
                    'is_created_by_system' => true,
                    'qty' => $productLimit->qty,
                    'setup_date' => $productLimit->setup_date,
                ]);
            }
        }
    }
}
