<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\ProductMapping;

class UnbindProductMappingSelfReferenceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        ProductMapping::withoutGlobalScopes()->whereColumn('id', 'upcoming_product_mapping_id')->update([
            'upcoming_product_mapping_id' => null
        ]);
    }
}

