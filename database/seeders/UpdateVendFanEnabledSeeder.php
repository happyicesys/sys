<?php

namespace Database\Seeders;

use App\Models\Vend;
use App\Models\VendModel;
use Illuminate\Database\Seeder;

class UpdateVendFanEnabledSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $modelNames = [
            'DDVM',
            'DVM',
            'DVM + Claw',
            'eDVM',
            'FVM'
        ];

        $modelIds = VendModel::whereIn('name', $modelNames)->pluck('id');

        if ($modelIds->isNotEmpty()) {
            $count = Vend::whereIn('vend_model_id', $modelIds)->update(['is_fan_enabled' => false]);
            $this->command->info("Updated {$count} vends across " . $modelIds->count() . " matching models to have is_fan_enabled as false.");
        } else {
            $this->command->warn('No matching vend models found for the names: ' . implode(', ', $modelNames));
        }
    }
}
