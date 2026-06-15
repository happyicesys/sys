<?php

namespace Database\Seeders;

use App\Models\VendModel;
use Illuminate\Database\Seeder;

/**
 * VendModelSeeder — seeds the "Smart Vend" vend model.
 *
 * `vend_models` is the hardware-model classifier a Vend points at via `vends.vend_model_id`
 * (used today for filtering, reporting and list sorting — see App\Traits\HasFilter). Adding a
 * "Smart Vend" model is purely additive: it introduces a new row that nothing references until a
 * vend is explicitly assigned it, so the legacy fleet is unaffected.
 *
 * Idempotent on purpose (firstOrCreate keyed on name): safe to re-run against a shared/production
 * database without creating duplicate models.
 */
class VendModelSeeder extends Seeder
{
    public function run(): void
    {
        VendModel::firstOrCreate(
            ['name' => 'Smart Vend'],
            [
                'desc' => 'AI smart freezer — vision-based grab-and-go (sg.mark1.freezer APK)',
                'is_sortable' => true,
            ],
        );
    }
}
