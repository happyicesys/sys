<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\ProductMapping;
use App\Models\Vend;

class UnbindProductMappingSelfReferenceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // --- 1. Fix ProductMappings pointing to themselves ---
        $pmSelfFixed = ProductMapping::withoutGlobalScopes()
            ->whereColumn('id', 'upcoming_product_mapping_id')
            ->update(['upcoming_product_mapping_id' => null]);

        // --- 2. Fix Vends where upcoming mapping equals their current mapping ---
        $vendSelfFixed = Vend::whereNotNull('upcoming_product_mapping_id')
            ->whereColumn('upcoming_product_mapping_id', 'product_mapping_id')
            ->update(['upcoming_product_mapping_id' => null]);

        // --- 3. Null out upcoming that points to N/A ProductMapping ---
        $naMapping = ProductMapping::withoutGlobalScopes()
            ->where('name', 'N/A')
            ->whereNull('operator_id')
            ->first();

        $pmNaFixed = 0;
        $vendNaFixed = 0;

        if ($naMapping) {
            $pmNaFixed = ProductMapping::withoutGlobalScopes()
                ->where('upcoming_product_mapping_id', $naMapping->id)
                ->update(['upcoming_product_mapping_id' => null]);

            $vendNaFixed = Vend::where('upcoming_product_mapping_id', $naMapping->id)
                ->update(['upcoming_product_mapping_id' => null]);
        }

        $this->command->info("Self-reference fixed  — ProductMappings: {$pmSelfFixed}, Vends: {$vendSelfFixed}");
        $this->command->info("N/A upcoming cleared  — ProductMappings: {$pmNaFixed}, Vends: {$vendNaFixed}");
    }
}

