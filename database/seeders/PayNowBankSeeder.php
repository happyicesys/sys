<?php

namespace Database\Seeders;

use App\Models\Bank;
use Illuminate\Database\Seeder;

/**
 * Explicit PayNow proxy "banks" so a site's payout method is unambiguous in the
 * Site Edit ▸ Bank Details dropdown. Column E of the CIMB file comes from
 * banks.proxy_type for these. Idempotent.
 *
 * The legacy generic "Paynow" row is left untouched (proxy_type null) — the export
 * auto-detects its proxy type from the account value, so existing sites keep working.
 */
class PayNowBankSeeder extends Seeder
{
    public function run(): void
    {
        $rows = [
            ['name' => 'PayNow (UEN)',    'proxy_type' => 'UEN'],
            ['name' => 'PayNow (Mobile)', 'proxy_type' => 'MOB'],
            ['name' => 'PayNow (NRIC)',   'proxy_type' => 'NRIC'],
        ];

        foreach ($rows as $row) {
            Bank::updateOrCreate(
                ['name' => $row['name']],
                [
                    'proxy_type' => $row['proxy_type'],
                    'bic_code' => null,
                    'country_id' => 1, // Singapore
                    'is_active' => true,
                ]
            );
        }
    }
}
