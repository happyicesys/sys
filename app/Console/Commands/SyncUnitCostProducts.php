<?php

namespace App\Console\Commands;

use App\Models\Product;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class SyncUnitCostProducts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sync:unit-cost-products';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync unit cost products from admin to sys';

    /**
     * Execute the console command.
     */

    public $endPointUrl = 'https://admin.happyice.com.sg/api/items/unitcosts/profile/2';

    public function handle()
    {
        $response = Http::get($this->endPointUrl);
        $obj = $response->collect();

        if($obj and isset($obj['unitcosts'])) {
            foreach($obj['unitcosts'] as $unitcost) {
                if(isset($unitcost['item'])) {
                    $item = $unitcost['item'];
                    // $product = Product::where('code', $item['product_id'])->whereHas('operator', function($query) use ($obj) {
                    //     $query->where('name', $obj['name']);
                    // })->first();
                    $product = Product::where('code', $item['product_id'])->first();
                    if($product) {
                        if($product->unitCosts()->exists()) {
                            $product->unitCosts()->update([
                                'is_current' => false,
                            ]);
                        }
                        $product->unitCosts()->updateOrCreate([
                            'cost' => $unitcost['unit_cost']/ (isset($item['item_uoms'][0]['value']) ? $item['item_uoms'][0]['value'] : 1)  * 100,
                        ], [
                            'date_from' => Carbon::parse($item['updated_at'])->setTimezone($product->operator->timezone)->toDateString(),
                        ]);
                        $currentUnitCost = $product->unitCosts()->whereDate('date_from', '<=', Carbon::today()->setTimezone($product->operator->timezone)->toDateString())->latest('created_at')->first();
                        $currentUnitCost->is_current = true;
                        $currentUnitCost->save();
                    }
                }
            }
        }
    }
}
