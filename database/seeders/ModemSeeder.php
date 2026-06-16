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

    // Short-name aliases keyed by the modem type name.
    const MODEM_TYPE_ALIASES = [
        'Quectel - EC25-EFA'   => '4G Small Andr',
        'Quectel - EC25-EUXGR' => '4G Big Andr',
        'Air724UGB4'           => 'Square Module',
        'Huawei 3G'            => 'Huawei 3G',
    ];

    /**
     * Detect the short alias for a given modem type name.
     * Falls back to keyword matching so renamed/new rows still resolve.
     */
    public static function detectAlias(string $name): ?string
    {
        if (isset(self::MODEM_TYPE_ALIASES[$name])) {
            return self::MODEM_TYPE_ALIASES[$name];
        }

        $haystack = strtoupper($name);

        if (str_contains($haystack, 'EC25-EFA')) {
            return '4G Small Andr';
        }
        if (str_contains($haystack, 'EC25-EUXGR') || str_contains($haystack, 'EC23-EUXGR')) {
            return '4G Big Andr';
        }
        if (str_contains($haystack, 'AIR724')) {
            return 'Square Module';
        }
        if (str_contains($haystack, 'HUAWEI')) {
            return 'Huawei 3G';
        }

        return null;
    }

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Seed base modem types (idempotent) with detected aliases.
        foreach (self::MODEM_TYPE_MAPPINGS as $id => $name) {
            ModemType::updateOrCreate(
                ['id' => $id],
                [
                    'name' => $name,
                    'alias' => self::detectAlias($name),
                    'is_modem_unit_required' => $id == 3,
                ]
            );
        }

        // Backfill aliases for any existing rows that are still missing one.
        ModemType::whereNull('alias')->get()->each(function (ModemType $modemType) {
            $alias = self::detectAlias($modemType->name);
            if ($alias !== null) {
                $modemType->update(['alias' => $alias]);
            }
        });
    }
}
