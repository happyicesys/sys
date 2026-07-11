<?php

namespace Database\Seeders;

use App\Models\ProductMapping;
use App\Models\VendPrefix;
use Illuminate\Database\Seeder;

/**
 * Ensures a global "N/A" option exists for Product Mapping and Machine
 * Prefix on the machine Setting/Edit form.
 *
 * Context: the Setting/Edit dropdowns are already wired to surface an
 * "N/A" choice — SettingController::edit() lists mappings with
 * operator_id IS NULL (ordering N/A first) and prefixes via
 * `orWhere name = 'N/A'`. The only gap was that the two backing rows were
 * never seeded (VendConfig already has its N/A row, which is why "Setting
 * Chart = N/A" works but the other two did not offer N/A).
 *
 * Both rows are created global (operator_id = null) so they appear for
 * every operator — the OperatorProductMappingScope / OperatorIDFilterScope
 * both admit operator_id IS NULL rows. Idempotent: re-running only touches
 * these two rows and never removes anything.
 *
 * Run: php artisan db:seed --class=AddNaMappingPrefixSeeder
 */
class AddNaMappingPrefixSeeder extends Seeder
{
    public function run(): void
    {
        // Product Mapping "N/A" — global, active so it passes the
        // `operator_id IS NULL AND is_active = 1` dropdown filter.
        ProductMapping::withoutGlobalScopes()->updateOrCreate(
            [
                'name' => 'N/A',
                'operator_id' => null,
            ],
            [
                'remarks' => 'Not Applicable',
                'is_active' => true,
            ]
        );

        // Machine Prefix "N/A" — global; the dropdown matches it by name.
        VendPrefix::withoutGlobalScopes()->updateOrCreate(
            [
                'name' => 'N/A',
                'operator_id' => null,
            ],
            [
                'desc' => 'Not Applicable',
            ]
        );
    }
}
