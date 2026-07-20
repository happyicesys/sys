<?php

namespace Database\Seeders;

use App\Models\Bank;
use App\Models\Customer;
use App\Services\Banking\CimbBankDirectory;
use Illuminate\Database\Seeder;

/**
 * One-off: move every site currently on the generic "Paynow" bank onto an explicit
 * PayNow proxy bank. Detects the proxy type from the site's account number —
 * UEN (default, since most are UEN), or Mobile / NRIC when the value clearly is one
 * — so a payout is never mislabelled. Idempotent (after the move no site is on the
 * generic row anymore). Run PayNowBankSeeder first so the target banks exist.
 */
class ReassignPaynowToUenSeeder extends Seeder
{
    public function run(): void
    {
        // The generic PayNow pseudo-bank ONLY: exact name "Paynow", no proxy_type,
        // and no BIC. This guarantees real banks (all have a BIC), the explicit
        // PayNow (UEN/Mobile/NRIC) rows (have a proxy_type), and "Invoice" are
        // NEVER touched — only sites on the generic PayNow option are reassigned.
        $genericIds = Bank::query()
            ->whereRaw('LOWER(name) = ?', ['paynow'])
            ->whereNull('proxy_type')
            ->whereNull('bic_code')
            ->pluck('id')->all();

        if (empty($genericIds)) {
            $this->command?->warn('No generic "Paynow" bank found — nothing to reassign.');
            return;
        }

        $uen = Bank::whereRaw('LOWER(name) = ?', ['paynow (uen)'])->value('id');
        $mob = Bank::whereRaw('LOWER(name) = ?', ['paynow (mobile)'])->value('id');
        $nric = Bank::whereRaw('LOWER(name) = ?', ['paynow (nric)'])->value('id');

        if (!$uen) {
            $this->command?->error('PayNow (UEN) bank not found — run PayNowBankSeeder first.');
            return;
        }

        $counts = ['uen' => 0, 'mob' => 0, 'nric' => 0];

        Customer::withoutGlobalScopes()
            ->whereIn('bank_id', $genericIds)
            ->select('id', 'bank_account_number')
            ->orderBy('id')
            ->chunkById(500, function ($customers) use ($uen, $mob, $nric, &$counts) {
                foreach ($customers as $c) {
                    $type = CimbBankDirectory::detectProxyType($c->bank_account_number);
                    if ($type === 'MOB' && $mob) {
                        $target = $mob;
                        $counts['mob']++;
                    } elseif ($type === 'NRIC' && $nric) {
                        $target = $nric;
                        $counts['nric']++;
                    } else {
                        // UEN, or undetectable → default to PayNow (UEN).
                        $target = $uen;
                        $counts['uen']++;
                    }
                    // saveQuietly: bulk data fix — don't spam the audit log / observers.
                    $c->forceFill(['bank_id' => $target])->saveQuietly();
                }
            });

        $this->command?->info(sprintf(
            'Reassigned PayNow sites → UEN: %d, Mobile: %d, NRIC: %d.',
            $counts['uen'], $counts['mob'], $counts['nric']
        ));

        // Now that every site is off it, delete the generic "PayNow" option so the
        // Bank Name dropdown only offers the explicit UEN / Mobile / NRIC variants.
        // Guarded: only delete if nothing still references it (safety first).
        $remaining = Customer::withoutGlobalScopes()->whereIn('bank_id', $genericIds)->count();
        if ($remaining > 0) {
            $this->command?->warn("Generic \"PayNow\" bank kept — {$remaining} site(s) still reference it.");
            return;
        }
        try {
            $deleted = Bank::whereIn('id', $genericIds)->delete();
            $this->command?->info("Deleted the generic \"PayNow\" bank option ({$deleted} row(s)).");
        } catch (\Throwable $e) {
            $this->command?->error('Could not delete the generic PayNow bank (still referenced elsewhere): ' . $e->getMessage());
        }
    }
}
