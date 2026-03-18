<?php

namespace Database\Seeders;

use App\Models\Operator;
use App\Models\ProductMapping;
use App\Models\VendConfig;
use App\Models\VendPrefix;
use Illuminate\Database\Seeder;

class AddNAOptionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // $operators = Operator::all();

        // foreach ($operators as $operator) {
        //     // Add N/A to VendConfig
        //     VendConfig::updateOrCreate([
        //         'name' => 'N/A',
        //         'operator_id' => $operator->id,
        //     ], [
        //         'desc' => 'Not Applicable',
        //         'is_active' => true,
        //     ]);

        //     // Add N/A to VendPrefix
        //     VendPrefix::updateOrCreate([
        //         'name' => 'N/A',
        //         'operator_id' => $operator->id,
        //     ], [
        //         'desc' => 'Not Applicable',
        //     ]);

        //     // Add N/A to ProductMapping
        //     ProductMapping::updateOrCreate([
        //         'name' => 'N/A',
        //         'operator_id' => $operator->id,
        //     ], [
        //         'remarks' => 'Not Applicable',
        //         'is_active' => true,
        //     ]);
        // }

        $productMapping = ProductMapping::withoutGlobalScopes()->updateOrCreate([
            'name' => 'N/A',
            'operator_id' => null,
        ], [
            'remarks' => 'Not Applicable',
            'is_active' => true,
        ]);

        $vendPrefix = VendPrefix::withoutGlobalScopes()->updateOrCreate([
            'name' => 'N/A',
            'operator_id' => null,
        ], [
            'desc' => 'Not Applicable',
        ]);

        $vendConfig = VendConfig::updateOrCreate([
            'name' => 'N/A',
            'operator_id' => null,
        ], [
            'desc' => 'Not Applicable',
            'is_active' => true,
        ]);

        // Link them together
        $vendConfig->vendPrefixes()->syncWithoutDetaching([$vendPrefix->id]);
        $vendPrefix->productMappings()->syncWithoutDetaching([$productMapping->id]);
    }
}