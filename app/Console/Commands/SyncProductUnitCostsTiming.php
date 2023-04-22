<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class SyncProductUnitCostsTiming extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sync:product-unit-costs-timing';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync product unit costs timing every 12am';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $products = Product::has('unitCosts')->get();

        foreach($products as $product) {
            if($product->unitCosts()->exists()) {
                $product->unitCosts()->update([
                    'is_current' => false,
                ]);
            }
            $currentUnitCost = $product->unitCosts()->whereDate('date_from', '<=', Carbon::today()->setTimezone($product->operator->timezone)->toDateString())->latest('created_at')->first();
            $currentUnitCost->is_current = true;
            $currentUnitCost->save();
        }
    }
}
