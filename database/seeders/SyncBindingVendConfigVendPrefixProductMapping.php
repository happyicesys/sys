<?php

namespace Database\Seeders;

use App\Models\Vend;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class SyncBindingVendConfigVendPrefixProductMapping extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $vends = Vend::all();
        foreach($vends as $vend) {
            if($vend->productMapping && $vend->vendPrefix) {
                // check whether vendPrefix is in productMapping, if not, unmap the product_mapping_id of vend
                $productMapping = $vend->productMapping;
                $vendPrefixes = $productMapping->vendPrefixes; // Convert Collection to array
                if(!in_array($vend->vendPrefix->id, $vendPrefixes->pluck('id')->toArray())) {
                    $vend->product_mapping_id = null;
                    $vend->save();
                }
            }
        }
    }
}
