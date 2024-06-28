<?php

namespace Database\Seeders;

use App\Models\Vend;
use App\Models\VendPrefix;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class SyncProductMappingToVendPrefix extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $vends = Vend::all();

        foreach($vends as $vend) {
            if($vend->vend_prefix_id && $vend->product_mapping_id) {
                $vendPrefix = VendPrefix::findOrFail($vend->vend_prefix_id);
                $vendPrefix->productMappings()->sync($vend->product_mapping_id);
            }
        }
    }
}
