<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\CustomerSettlement;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;

/**
 * ONE-OFF, run-once initialiser for the settlement ledger
 * (Site Summary ▸ Payment History). Idempotent — safe to re-run; it never
 * double-posts.
 *
 * Two phases:
 *
 *  1) OPENING BALANCES — for every site whose legacy CMS-pulled
 *     customers.loc_fee_remarks carries a "since <yymmdd>, owe $<amount>"
 *     note, post ONE opening_balance row dated 2026-04-30 (the month before we
 *     start accruing properly). Item = "Since <yymmdd>", amount = the owe.
 *     Sites WITHOUT that note get no opening row.
 *
 *  2) MAY 2026 LOCATION FEES — accrue each owing site's Net Loc Fee for May by
 *     delegating to the same command the monthly cron uses
 *     (settlement:generate-monthly), so there is a single source of truth for
 *     the monthly logic. For a site with no opening balance, this May charge
 *     becomes its FIRST ledger entry (everything before May was paid/recorded
 *     in CMS).
 *
 * Idempotency: phase 1 skips sites that already have an opening_balance row;
 * phase 2's command skips sites that already have a May location_fee row.
 *
 * Usage:
 *   php artisan db:seed --class=SeedInitialSettlementsSeeder
 *   DRY_RUN=1 php artisan db:seed --class=SeedInitialSettlementsSeeder
 */
class SeedInitialSettlementsSeeder extends Seeder
{
    /** Opening balance is dated the last day of April 2026. */
    private const OPENING_DATE = '2026-04-30';
    private const OPENING_YEAR_MONTH = '2026-04-01';

    /** First month accrued properly (phase 2). */
    private const FIRST_ACCRUAL_MONTH = '2026-05';

    // "since 240531, owe $1,247.63" — case-insensitive, tolerant of spacing,
    // thousands separators and an optional cents fraction. Unanchored, so it
    // still matches when wrapped in other free-text.
    private const REMARK_PATTERN = '/since\s+(\d{6})\s*,\s*owe\s*\$?\s*([\d,]+(?:\.\d+)?)/i';

    public function run(): void
    {
        $dryRun = (bool) env('DRY_RUN', false);

        $this->command?->info(
            ($dryRun ? '[DRY RUN] ' : '')
            . 'Initialising settlement ledger — opening balances (' . self::OPENING_DATE . ') then May 2026 fees.'
        );

        $this->seedOpeningBalances($dryRun);
        $this->seedFirstMonth($dryRun);

        $this->command?->info('Done.' . ($dryRun ? ' (dry run — nothing written)' : ''));
    }

    /** Phase 1 — opening balances parsed from loc_fee_remarks. */
    private function seedOpeningBalances(bool $dryRun): void
    {
        // Sites that already have an opening balance — skip (idempotency).
        $haveOpening = CustomerSettlement::query()
            ->where('entry_type', CustomerSettlement::TYPE_OPENING_BALANCE)
            ->pluck('customer_id')
            ->flip();

        $customers = Customer::query()
            ->withoutGlobalScopes()
            ->whereNotNull('loc_fee_remarks')
            ->where('loc_fee_remarks', '<>', '')
            ->get(['id', 'name', 'operator_id', 'loc_fee_remarks']);

        $seeded = 0;
        $skippedExisting = 0;
        $skippedNoMatch = 0;
        $totalCents = 0;
        $preview = [];

        foreach ($customers as $c) {
            if (!preg_match(self::REMARK_PATTERN, (string) $c->loc_fee_remarks, $m)) {
                $skippedNoMatch++;
                continue;
            }
            if ($haveOpening->has($c->id)) {
                $skippedExisting++;
                continue;
            }

            $sinceCode = $m[1];                                   // e.g. "240531"
            $amountCents = (int) round(((float) str_replace(',', '', $m[2])) * 100);

            if ($amountCents <= 0) {
                $skippedNoMatch++;
                continue;
            }

            $totalCents += $amountCents;
            $seeded++;
            $preview[] = [$c->id, mb_strimwidth((string) $c->name, 0, 26, '…'), 'Since ' . $sinceCode, '$' . number_format($amountCents / 100, 2)];

            if ($dryRun) {
                continue;
            }

            CustomerSettlement::create([
                'customer_id'  => $c->id,
                'operator_id'  => $c->operator_id,
                'entry_date'   => self::OPENING_DATE,
                'year_month'   => self::OPENING_YEAR_MONTH,
                'entry_type'   => CustomerSettlement::TYPE_OPENING_BALANCE,
                'amount_cents' => $amountCents,                   // +ve — we owe.
                'item'         => 'Since ' . $sinceCode,
                'remarks'      => trim((string) $c->loc_fee_remarks),
                'source'       => CustomerSettlement::SOURCE_SEED,
                'created_by'   => null,
            ]);
        }

        if (!empty($preview)) {
            $this->command?->table(['Cust ID', 'Site', 'Item', 'Opening Owe'], $preview);
        }

        $this->command?->info(sprintf(
            'Phase 1 — opening balances: %s%d seeded totalling $%s. Skipped: %d already seeded, %d sites start at May (no "since … owe …").',
            $dryRun ? '(dry run) would seed ' : '',
            $seeded,
            number_format($totalCents / 100, 2),
            $skippedExisting,
            $skippedNoMatch
        ));
    }

    /** Phase 2 — May 2026 location fees via the shared monthly command. */
    private function seedFirstMonth(bool $dryRun): void
    {
        $this->command?->info('Phase 2 — accruing ' . self::FIRST_ACCRUAL_MONTH . ' location fees …');

        $options = [
            '--month'  => self::FIRST_ACCRUAL_MONTH,
            '--source' => CustomerSettlement::SOURCE_SEED,
        ];
        if ($dryRun) {
            $options['--dry-run'] = true;
        }

        Artisan::call('settlement:generate-monthly', $options);

        // Surface the command's own summary line(s) under this seeder's output.
        foreach (array_filter(explode("\n", trim(Artisan::output()))) as $line) {
            $this->command?->line('  ' . $line);
        }
    }
}
