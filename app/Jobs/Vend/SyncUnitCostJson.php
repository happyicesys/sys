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

    public function __construct(VendTransaction $vendTransaction)
    {
        $this->vendTransaction = $vendTransaction;
    }

    public function handle(): void
    {
        $vt = $this->vendTransaction;

        $revenue = $vt->getRevenue();
        $unitCost = $vt->getUnitCost();
        $grossProfit = $revenue - $unitCost;
        $grossProfitMargin = $revenue ? (($grossProfit * 100) / $revenue) : 0;

        $vt->update([
            'revenue' => $revenue,
            'gross_profit' => $grossProfit,
            'gross_profit_margin' => $grossProfitMargin,
            'unit_cost' => $unitCost,
        ]);
    }
}
