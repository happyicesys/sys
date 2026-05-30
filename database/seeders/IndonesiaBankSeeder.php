<?php

namespace Database\Seeders;

use App\Models\Bank;
use App\Models\Country;
use Illuminate\Database\Seeder;

/**
 * Seeds the major Indonesia banks into the `banks` table.
 *
 * Per-country deployment: run this on the ID instance with
 *   php artisan db:seed --class=IndonesiaBankSeeder
 *
 * Idempotent — updateOrCreate keyed on (name, country_id) so re-running
 * never duplicates rows. country_id is resolved from the ID Country row
 * (code = 'ID'); if that row is absent the banks are still seeded with a
 * null country_id (the customer dropdown shows all active banks anyway).
 */
class IndonesiaBankSeeder extends Seeder
{
    public function run(): void
    {
        $countryId = optional(Country::where('code', 'ID')->first())->id;

        $banks = [
            'Bank Central Asia (BCA)',
            'Bank Mandiri',
            'Bank Rakyat Indonesia (BRI)',
            'Bank Negara Indonesia (BNI)',
            'Bank Tabungan Negara (BTN)',
            'Bank CIMB Niaga',
            'Bank Danamon',
            'Bank Permata',
            'Bank Maybank Indonesia',
            'Bank OCBC NISP',
            'Bank Panin',
            'Bank BTPN',
            'Bank Mega',
            'Bank Syariah Indonesia (BSI)',
            'Bank Sinarmas',
            'Bank Jago',
            'Bank DBS Indonesia',
            'Bank UOB Indonesia',
            'Bank HSBC Indonesia',
            'Bank Bukopin',
        ];

        foreach ($banks as $name) {
            Bank::updateOrCreate(
                ['name' => $name, 'country_id' => $countryId],
                ['is_active' => true]
            );
        }
    }
}
