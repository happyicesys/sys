<?php

namespace Database\Seeders;

use App\Models\Bank;
use App\Models\Country;
use Illuminate\Database\Seeder;

/**
 * Seeds the major Singapore banks into the `banks` table.
 *
 * Per-country deployment: run this on the SG instance with
 *   php artisan db:seed --class=SingaporeBankSeeder
 *
 * Idempotent — updateOrCreate keyed on (name, country_id) so re-running
 * never duplicates rows. country_id is resolved from the SG Country row
 * (code = 'SG'); if that row is absent the banks are still seeded with a
 * null country_id (the customer dropdown shows all active banks anyway).
 */
class SingaporeBankSeeder extends Seeder
{
    public function run(): void
    {
        $countryId = optional(Country::where('code', 'SG')->first())->id;

        $banks = [
            'DBS Bank',
            'POSB Bank',
            'OCBC Bank',
            'United Overseas Bank (UOB)',
            'Standard Chartered Bank',
            'Citibank Singapore',
            'HSBC Singapore',
            'Maybank Singapore',
            'CIMB Bank Singapore',
            'Bank of China (Singapore)',
            'RHB Bank Singapore',
            'Trust Bank Singapore',
            'GXS Bank',
            'Sing Investments & Finance',
            'Hong Leong Finance',
        ];

        foreach ($banks as $name) {
            Bank::updateOrCreate(
                ['name' => $name, 'country_id' => $countryId],
                ['is_active' => true]
            );
        }
    }
}
