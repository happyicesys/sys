<?php

namespace App\Jobs\Vend;

use App\Models\VendTransaction;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SyncUnitCostJson implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $vendTransaction;
    /**
     * Create a new job instance.
     */
    public function __construct(VendTransaction $vendTransaction)
    {
        $this->vendTransaction = $vendTransaction;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $vendTransaction = $this->vendTransaction;

        $revenue = $vendTransaction->getRevenue();
        $unitCost = $vendTransaction->getUnitCost();
        $grossProfit = $vendTransaction->getGrossProfit();
        $grossProfitMargin = $revenue ? (($grossProfit * 100)/ $revenue) : 0;

        $vendTransaction->update([
            'revenue' => $revenue,
            'gross_profit' => $grossProfit,
            'gross_profit_margin' => $grossProfitMargin,
            'unit_cost' => $unitCost,
        ]);
    }
}
