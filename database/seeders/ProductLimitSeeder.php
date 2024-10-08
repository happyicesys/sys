<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\ProductLimit;
use Carbon\Carbon;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ProductLimitSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $products = Product::all();

        foreach($products as $product) {
            $maxOpsJobPickLimitJson = $product->max_ops_job_pick_limit_json;
            if(!$maxOpsJobPickLimitJson || empty($maxOpsJobPickLimitJson)) {
                continue;
            }

            foreach ($maxOpsJobPickLimitJson as $date => $value) {
                if(Carbon::parse($date)->isPast()) {
                    continue;
                }
                ProductLimit::updateOrCreate([
                    'product_id' => $product->id,
                    'date' => Carbon::parse($date),
                    'qty' => $value,
                    'setup_date' => Carbon::parse($date),
                ]);
            }
        }
    }
}
