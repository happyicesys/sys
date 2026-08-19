<?php

namespace Database\Seeders;

use App\Models\Operator;
use App\Models\VendModel;
use App\Models\VendPrefix;
use Illuminate\Database\Seeder;

/**
 * Idempotent seed for the CityBox provisioning prerequisites (design §8f):
 * the dedicated "Citybox" operator, its vend prefix, and the two hardware
 * models. Safe to re-run — everything is firstOrCreate on a natural key.
 * Bank account / GST for the operator are entered by a human on the Operator
 * page (they are settlement data, not code).
 */
class CityboxOperatorSeeder extends Seeder
{
    public function run(): void
    {
        $operator = Operator::firstOrCreate(
            ['code' => config('citybox.operator_code', 'CB')],
            ['name' => 'Citybox', 'country_id' => Operator::where('code', 'HIPL')->value('country_id') ?? 1, 'timezone' => 'Asia/Singapore'],
        );

        VendPrefix::firstOrCreate(
            ['name' => config('citybox.vend_prefix_name', 'CB')],
            ['desc' => 'CityBox smart chiller', 'operator_id' => $operator->id],
        );

        foreach (config('citybox.device_models', []) as $type => $modelName) {
            VendModel::firstOrCreate(['name' => $modelName], ['desc' => "CityBox smart chiller, type {$type}, 5 layers"]);
        }
    }
}
