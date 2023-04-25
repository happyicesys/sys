<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Jobs\Vend\SyncUnitCostJson;
use Illuminate\Console\Command;

class SyncVendTransactionCurrentUnitCost extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sync:vend-transaction-current-unit-cost';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync vend transaction current unit cost';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $products = Product::has('unitCosts')->get();

        foreach($products as $product) {
            if($product->vendTransactions()->exists()) {
                $unitCostId = $product->unitCosts()->where('is_current', true)->first()->id;
                foreach($product->vendTransactions as $vendTransaction) {
                    $vendTransaction->unit_cost_id = $unitCostId;
                    $vendTransaction->save();
                    SyncUnitCostJson::dispatch($vendTransaction);
                }
            }
        }
    }
}
