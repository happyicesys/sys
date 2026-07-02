<?php

namespace Database\Seeders;

use App\Models\Bank;
use App\Services\Banking\CimbBankDirectory;
use Illuminate\Database\Seeder;

/**
 * Stamp banks.bic_code from the CIMB BizChannel BIC directory
 * (App\Services\Banking\CimbBankDirectory::NAME_TO_BIC).
 *
 * Idempotent and non-destructive: only fills banks whose bic_code is still
 * NULL/empty, so a manually corrected BIC on the Banks page is never
 * overwritten. Run after the migration:
 *
 *   php artisan db:seed --class=BankBicSeeder
 */
class BankBicSeeder extends Seeder
{
    public function run(): void
    {
        $updated = 0;

        Bank::query()
            ->where(fn ($q) => $q->whereNull('bic_code')->orWhere('bic_code', ''))
            ->get()
            ->each(function (Bank $bank) use (&$updated) {
                $bic = CimbBankDirectory::bicForBankName($bank->name);
                if ($bic) {
                    $bank->update(['bic_code' => $bic]);
                    $updated++;
                }
            });

        $this->command?->info("BIC codes stamped on {$updated} bank(s). Banks without a known mapping were left blank — set them under Data Management ▸ Banks.");
    }
}
