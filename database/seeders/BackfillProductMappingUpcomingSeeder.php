<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class BackfillProductMappingUpcomingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $productMappings = \App\Models\ProductMapping::with(['upcomingProductMappings', 'vends'])->get();

        foreach ($productMappings as $productMapping) {
            $upcomingMapping = $productMapping->upcomingProductMappings->first();

            if ($upcomingMapping) {
                // Set the direct column to match the first upcoming mapping from the pivot
                $productMapping->upcoming_product_mapping_id = $upcomingMapping->id;
                $productMapping->save();

                // Cascade to all Vends bound to this ProductMapping
                foreach ($productMapping->vends as $vend) {
                    $vend->upcoming_product_mapping_id = $upcomingMapping->id;
                    $vend->save();
                }
            }
        }
    }
}
