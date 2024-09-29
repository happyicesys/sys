<?php

namespace Database\Seeders;

use App\Models\ModemType;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ModemSeeder extends Seeder
{

    const MODEM_TYPE_MAPPINGS = [
        1 => 'Quectel - EC25-EFA',
        2 => 'Quectel - EC25-EUXGR',
        3 => 'Air724UGB4',
        4 => 'Huawei 3G',
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $modemTypes = collect(self::MODEM_TYPE_MAPPINGS)->map(function ($name, $id) {
            return \App\Models\ModemType::create([
                'id' => $id,
                'name' => $name,
                'is_modem_unit_required' => $id == 3 ? true : false,
            ]);
        });
    }
}
