<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\CustomerPeriodSummary;
use App\Models\CustomerSettlement;
use App\Models\CustomerSettlementLog;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

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
 *     start accruing properly). Item = "Since <yymmdd>", amount = the owe
 *     (already GST-excluded — cms:sync-loc-fee-remarks strips GST before
 *     writing the note). Sites WITHOUT that note get no opening row.
 *
 *     RESYNC-SAFE: an existing opening_balance row is UPDATED in place (matched
 *     on customer_id) — the amount/item/remarks are refreshed from the current
 *     note rather than skipped. This lets a re-run correct stale figures (e.g.
 *     the earlier GST-inclusive amounts) WITHOUT creating a duplicate ledger
 *     line. The auto-assigned OB-###### reference_no is preserved on update.
 *
 *  2) MAY 2026 LOCATION FEES — accrue each owing site's Net Loc Fee for May by
 *     delegating to the same command the monthly cron uses
 *     (settlement:generate-monthly), so there is a single source of truth for
 *     the monthly logic. For a site with no opening balance, this May charge
 *     becomes its FIRST ledger entry (everything before May was paid/recorded
 *     in CMS).
 *
 *  3) BACKFILL PRE-FEATURE PAYMENTS — the "record amount → post a ledger credit"
 *     logic only shipped 2026-06-25 (commit f075b9a). Periods marked Paid BEFORE
 *     that date flipped is_paid = true but never wrote a settlement credit, so
 *     their Payment History shows the location-fee DEBIT with no offsetting
 *     payment (still "outstanding"). This phase posts the missing credit for each
 *     such period, sized to its matching location_fee DEBIT so the period nets to
 *     zero (the "paid in full" intent). Periods with no matching debit (fee was
 *     folded into the opening balance, or not yet accrued) are skipped — never a
 *     phantom credit. Periods paid on/after 2026-06-25 already wrote their own
 *     credit and are not touched.
 *
 * Idempotency: phase 1 UPSERTS the opening_balance row (one per site, updated
 * in place on re-run — never duplicated); phase 2's command skips sites that
 * already have a May location_fee row; phase 3 SKIPS any period that already
 * has a paid-action settlement row, so re-running never double-credits.
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

    /** First ledger month as a date — backfill (phase 3) only looks at periods
     *  from here on; anything earlier was settled inside the opening balance. */
    private const FIRST_ACCRUAL_MONTH_DATE = '2026-05-01';

    /** Date the Paid-action credit logic shipped (commit f075b9a). Periods marked
     *  Paid BEFORE this never got a ledger credit and are what phase 3 backfills;
     *  periods paid on/after already wrote their own credit. */
    private const PAID_CREDIT_FEATURE_DATE = '2026-06-25';

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
        $this->backfillPreFeaturePayments($dryRun);

        $this->command?->info('Done.' . ($dryRun ? ' (dry run — nothing written)' : ''));
    }

    /** Phase 1 — opening balances parsed from loc_fee_remarks (upsert). */
    private function seedOpeningBalances(bool $dryRun): void
    {
        // Existing opening balances, keyed by customer — used to label a row as
        // CREATE vs UPDATE and to compute the net change on a resync.
        $existing = CustomerSettlement::query()
            ->where('entry_type', CustomerSettlement::TYPE_OPENING_BALANCE)
            ->get(['customer_id', 'amount_cents'])
            ->keyBy('customer_id');

        $customers = Customer::query()
            ->withoutGlobalScopes()
            ->whereNotNull('loc_fee_remarks')
            ->where('loc_fee_remarks', '<>', '')
            ->get(['id', 'name', 'operator_id', 'loc_fee_remarks']);

        $created = 0;
        $updated = 0;
        $unchanged = 0;
        $skippedNoMatch = 0;
        $totalCents = 0;     // aggregate outstanding across ALL opening balances after sync
        $deltaCents = 0;     // net change this run (new - old) for the touched rows
        $preview = [];

        foreach ($customers as $c) {
            if (!preg_match(self::REMARK_PATTERN, (string) $c->loc_fee_remarks, $m)) {
                $skippedNoMatch++;
                continue;
            }

            $sinceCode = $m[1];                                   // e.g. "240531"
            $amountCents = (int) round(((float) str_replace(',', '', $m[2])) * 100);

            if ($amountCents <= 0) {
                $skippedNoMatch++;
                continue;
            }

            $totalCents += $amountCents;

            $prior = $existing->get($c->id);
            if ($prior === null) {
                $action = 'create';
                $created++;
                $deltaCents += $amountCents;
            } elseif ((int) $prior->amount_cents !== $amountCents) {
                $action = 'update';
                $updated++;
                $deltaCents += $amountCents - (int) $prior->amount_cents;
            } else {
                $action = 'unchanged';
                $unchanged++;
            }

            $priorLabel = $prior !== null ? '$' . number_format((int) $prior->amount_cents / 100, 2) : '—';
            $preview[] = [
                $c->id,
                mb_strimwidth((string) $c->name, 0, 26, '…'),
                'Since ' . $sinceCode,
                $priorLabel,
                '$' . number_format($amountCents / 100, 2),
                $action,
            ];

            if ($dryRun) {
                continue;
            }

            // Upsert keyed on (customer_id, entry_type): refresh the single
            // opening row in place — no duplicate line, OB-###### ref preserved.
            CustomerSettlement::updateOrCreate(
                [
                    'customer_id' => $c->id,
                    'entry_type'  => CustomerSettlement::TYPE_OPENING_BALANCE,
                ],
                [
                    'operator_id'  => $c->operator_id,
                    'entry_date'   => self::OPENING_DATE,
                    'year_month'   => self::OPENING_YEAR_MONTH,
                    'amount_cents' => $amountCents,                   // +ve — we owe.
                    'item'         => 'Since ' . $sinceCode,
                    'remarks'      => trim((string) $c->loc_fee_remarks),
                    'source'       => CustomerSettlement::SOURCE_SEED,
                ]
            );
        }

        if (!empty($preview)) {
            $this->command?->table(['Cust ID', 'Site', 'Item', 'Prev Owe', 'New Owe', 'Action'], $preview);
        }

        $this->command?->info(sprintf(
            'Phase 1 — opening balances%s: %d created, %d updated, %d unchanged, %d sites start at May (no "since … owe …"). Net change this run: %s$%s.',
            $dryRun ? ' (dry run — nothing written)' : '',
            $created,
            $updated,
            $unchanged,
            $skippedNoMatch,
            $deltaCents < 0 ? '-' : '',
            number_format(abs($deltaCents) / 100, 2)
        ));

        $this->command?->info(sprintf(
            '>> Aggregate outstanding opening balance synchronised across %d site(s): $%s',
            $created + $updated + $unchanged,
            number_format($totalCents / 100, 2)
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

    /**
     * Phase 3 — backfill payment/waiver credits for periods marked Paid BEFORE
     * the credit-posting feature shipped (PAID_CREDIT_FEATURE_DATE).
     *
     * Runs AFTER phase 2 so the location_fee debits it matches against already
     * exist. Credits are posted per customer/month (machine-split segments share
     * one rolled-up debit) equal to that month's location_fee debit, so the month
     * nets to zero. Idempotent: any customer/month that already carries a
     * paid-action settlement row is skipped, so re-runs never double-credit.
     */
    private function backfillPreFeaturePayments(bool $dryRun): void
    {
        $this->command?->info(
            'Phase 3 — backfilling pre-' . self::PAID_CREDIT_FEATURE_DATE
            . ' Paid periods that never got a ledger credit …'
        );

        // Candidate periods: actually Paid, in the ledger window (May onward),
        // and flipped Paid before the credit feature existed.
        $periods = CustomerPeriodSummary::query()
            ->whereNotNull('paid_at')
            ->where('is_paid', true)
            ->where('year_month', '>=', self::FIRST_ACCRUAL_MONTH_DATE)
            ->where('paid_at', '<', self::PAID_CREDIT_FEATURE_DATE . ' 00:00:00')
            ->get([
                'id', 'customer_id', 'operator_id', 'year_month',
                'paid_at', 'paid_date', 'paid_by', 'is_waived', 'waived_remarks',
            ]);

        // The ledger posts ONE rolled-up location_fee DEBIT per customer/month
        // (machine-split segments are summed into it), so we credit per
        // customer|year_month — never once per split row. Group qualifying paid
        // periods by that key; a month is a waiver only if EVERY segment was
        // waived (any non-waived segment makes the whole month a payment).
        $groups = [];
        foreach ($periods as $p) {
            $key = $p->customer_id . '|' . optional($p->year_month)->toDateString();
            if (!isset($groups[$key])) {
                $groups[$key] = [
                    'customer_id'    => $p->customer_id,
                    'operator_id'    => $p->operator_id,
                    'year_month'     => optional($p->year_month)->toDateString(),
                    'summary_id'     => $p->id,
                    'is_waived'      => (bool) $p->is_waived,
                    'waived_remarks' => $p->waived_remarks,
                    'paid_by'        => $p->paid_by,
                    'entry_date'     => $p->paid_date
                        ? \Carbon\Carbon::parse($p->paid_date)->toDateString()
                        : \Carbon\Carbon::parse($p->paid_at)->toDateString(),
                ];
            } elseif (!$p->is_waived) {
                $groups[$key]['is_waived'] = false;
            }
        }

        // Location-fee debits in the ledger, summed by customer|year_month. The
        // credit is sized to this so each month nets to zero — robust against any
        // Net-Loc-Fee recompute drift.
        $debits = CustomerSettlement::query()
            ->where('entry_type', CustomerSettlement::TYPE_LOCATION_FEE)
            ->where('year_month', '>=', self::FIRST_ACCRUAL_MONTH_DATE)
            ->get(['customer_id', 'year_month', 'amount_cents'])
            ->groupBy(fn ($r) => $r->customer_id . '|' . optional($r->year_month)->toDateString())
            ->map(fn ($g) => (int) $g->sum('amount_cents'));

        // Idempotency guard: months that ALREADY carry a paid-action credit, keyed
        // by customer|year_month. Catches both a previous run of this backfill and
        // any credit the live Paid flow wrote, so a re-run never double-credits.
        $alreadyCredited = CustomerSettlement::query()
            ->where('source', CustomerSettlement::SOURCE_PAID_ACTION)
            ->get(['customer_id', 'year_month'])
            ->mapWithKeys(fn ($r) => [$r->customer_id . '|' . optional($r->year_month)->toDateString() => true]);

        $created = 0;
        $skippedExisting = 0;
        $skippedNoDebit = 0;
        $totalCents = 0;
        $preview = [];

        foreach ($groups as $key => $g) {
            if ($alreadyCredited->has($key)) {
                $skippedExisting++;
                continue;
            }

            $debitCents = (int) ($debits[$key] ?? 0);
            if ($debitCents <= 0) {
                // No matching charge to settle (fee in opening balance or not
                // accrued) — never post a phantom credit.
                $skippedNoDebit++;
                continue;
            }

            $isWaived = $g['is_waived'];
            $monthLabel = $g['year_month']
                ? \Carbon\Carbon::parse($g['year_month'])->format('M Y')
                : '';

            $created++;
            $totalCents += $debitCents;
            $preview[] = [
                $g['customer_id'],
                $monthLabel,
                ($isWaived ? 'Waived' : 'Payment'),
                '$' . number_format($debitCents / 100, 2),
                $g['entry_date'],
            ];

            if ($dryRun) {
                continue;
            }

            DB::transaction(function () use ($g, $isWaived, $monthLabel, $debitCents) {
                $entry = CustomerSettlement::create([
                    'customer_id'  => $g['customer_id'],
                    'operator_id'  => $g['operator_id'],
                    'entry_date'   => $g['entry_date'],
                    'year_month'   => $g['year_month'],
                    'entry_type'   => $isWaived
                        ? CustomerSettlement::TYPE_WAIVER
                        : CustomerSettlement::TYPE_PAYMENT,
                    'amount_cents' => -$debitCents,   // credit — reduces what we owe.
                    'item'         => ($isWaived ? 'Waived' : 'Payment') . ($monthLabel ? ' — ' . $monthLabel : ''),
                    'remarks'      => $isWaived ? trim((string) $g['waived_remarks']) : null,
                    'customer_period_summary_id' => $g['summary_id'],
                    'source'       => CustomerSettlement::SOURCE_PAID_ACTION,
                    'created_by'   => $g['paid_by'],
                ]);

                CustomerSettlementLog::create([
                    'customer_id'            => $g['customer_id'],
                    'customer_settlement_id' => $entry->id,
                    'reference_no'           => $entry->reference_no,
                    'action'                 => $isWaived
                        ? CustomerSettlementLog::ACTION_WAIVER
                        : CustomerSettlementLog::ACTION_PAYMENT,
                    'entry_type'             => $entry->entry_type,
                    'old_amount_cents'       => null,
                    'new_amount_cents'       => $debitCents,
                    'note'                   => ($isWaived ? 'Waiver' : 'Payment')
                        . ' backfilled for ' . ($monthLabel ?: 'period')
                        . ' (pre-' . self::PAID_CREDIT_FEATURE_DATE . ' Paid action, no credit recorded at the time)',
                    'changed_by'             => $g['paid_by'],
                    'source'                 => CustomerSettlement::SOURCE_PAID_ACTION,
                ]);
            });
        }

        if (!empty($preview)) {
            $this->command?->table(
                ['Cust ID', 'Month', 'Type', 'Credit', 'Dated'],
                $preview
            );
        }

        $this->command?->info(sprintf(
            'Phase 3 — pre-feature payment backfill%s: %d credited ($%s total), %d already had a credit (skipped), %d had no matching debit (skipped).',
            $dryRun ? ' (dry run — nothing written)' : '',
            $created,
            number_format($totalCents / 100, 2),
            $skippedExisting,
            $skippedNoDebit
        ));
    }
}
