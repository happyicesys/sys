<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\CustomerPeriodSummary;
use App\Services\CustomerSummaryAggregator;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * ONE-OFF data fix — site 24291 (Temasek Club), Apr & May 2026.
 *
 * The June 2026 contract (PS+U, 8% + $50, PS Term 33%) should have been
 * effective from April. April (2604) and May (2605) were locked with the old
 * deal (10% + $40, PS Term 70%). Because the rows are LOCKED, the nightly
 * aggregator skips them, so they must be patched directly.
 *
 * This rewrites ONLY the two target rows and recomputes every derived money
 * column through CustomerSummaryAggregator::computeLocationFeeCents() — the
 * exact same formula + inputs (frozen sales_cents, operator GST rate, active-
 * day ratio) the pipeline uses — so the figures match what a real recompute
 * would have produced. No other site, month, or row is touched.
 *
 * NOTE: 24291 is the DISPLAYED Site ID (ref_id). The real customers.id is
 * ref_id - Customer::RUNNING_NUMBER_INIT (20000) = 4291.
 *
 * SAFE BY DEFAULT: dry-run. It prints a before/after diff and rolls back.
 * To actually commit:  FIX_APPLY=1 php artisan db:seed --class=FixSite24291AprMayContractSeeder
 */
class FixSite24291AprMayContractSeeder extends Seeder
{
    private const REF_ID = 24291;                 // displayed Site ID
    private const EXPECT_NAME_CONTAINS = 'Temasek';

    // Target deal (matches June 2026).
    private const NEW_VALUE  = 8.0;   // commission %
    private const NEW_VALUE2 = 50.0;  // flat $ (utility)
    private const NEW_PSTERM = 33.0;  // PS Term %

    private const MONTHS = ['2026-04-01', '2026-05-01'];

    public function run(): void
    {
        $apply = filter_var(getenv('FIX_APPLY') ?: env('FIX_APPLY'), FILTER_VALIDATE_BOOLEAN);

        $realId = self::REF_ID - Customer::RUNNING_NUMBER_INIT;

        $customer = Customer::withoutGlobalScopes()->find($realId);
        if (!$customer) {
            $this->command->error("Customer id {$realId} (Site ID " . self::REF_ID . ") not found. Aborting.");
            return;
        }

        $name = trim(($customer->name ?? '') . ' ' . ($customer->site_name ?? ''));
        if (stripos($name, self::EXPECT_NAME_CONTAINS) === false) {
            $this->command->error(sprintf(
                "Safety check failed: customer id %d resolved to '%s' (expected to contain '%s'). Aborting.",
                $realId, $name, self::EXPECT_NAME_CONTAINS
            ));
            return;
        }

        $gstRatePct = (float) (DB::table('operators')
            ->where('id', $customer->operator_id)
            ->value('gst_vat_rate') ?? 0);

        $this->command->info(sprintf(
            "Site ID %d -> customers.id %d (%s) | operator_id=%s | GST=%.2f%% | mode=%s",
            self::REF_ID, $realId, $name, $customer->operator_id, $gstRatePct,
            $apply ? 'APPLY (will commit)' : 'DRY-RUN (rollback)'
        ));

        DB::beginTransaction();
        try {
            foreach (self::MONTHS as $monthStr) {
                $monthStart = Carbon::parse($monthStr)->startOfMonth();

                $rows = CustomerPeriodSummary::where('customer_id', $realId)
                    ->where('year_month', $monthStart->toDateString())
                    ->get();

                if ($rows->isEmpty()) {
                    $this->command->warn(" - {$monthStr}: no row found, skipping.");
                    continue;
                }
                if ($rows->count() > 1) {
                    // Segmented month (mid-month change). Recomputing per-segment
                    // is out of scope for this one-off — surface and skip so we
                    // never half-fix a split month.
                    $this->command->error(" - {$monthStr}: {$rows->count()} segment rows found. SKIPPING — handle segmented month manually.");
                    continue;
                }

                $row = $rows->first();

                $flatDayRatio = CustomerSummaryAggregator::computeActiveDayRatio(
                    $customer->active_date ?? $customer->begin_date,
                    $customer->removed_date,
                    $monthStart
                );

                $salesCents = (int) $row->sales_cents;
                $grossCents = (int) $row->gross_earning_cents;
                $extSubCents = (int) $row->external_subsidize_cents; // preserve frozen subsidy

                $newLocFee = CustomerSummaryAggregator::computeLocationFeeCents(
                    $row->contract_commission_type, // unchanged (PS+U)
                    self::NEW_VALUE,
                    self::NEW_VALUE2,
                    self::NEW_PSTERM,
                    $salesCents,
                    $grossCents,
                    $gstRatePct,
                    $flatDayRatio
                );
                $newNetLoc = $newLocFee - $extSubCents;
                $newLocEarning = $grossCents - $newNetLoc;
                $newLocRate = $salesCents > 0 ? round($newLocEarning / $salesCents, 4) : 0;

                $this->command->line(sprintf(
                    " - %s  BEFORE: %s%%+\$%s term %s%% | locFee %s netLoc %s vendEarn %s",
                    $monthStr,
                    $this->fmt($row->contract_commission_value),
                    $this->fmt($row->contract_commission_value2),
                    $this->fmt($row->contract_ps_term),
                    $this->money($row->location_fees_cents),
                    $this->money($row->location_fees_cents - $extSubCents),
                    $this->money($row->location_earning_cents)
                ));
                $this->command->line(sprintf(
                    "         AFTER : %s%%+\$%s term %s%% | locFee %s netLoc %s vendEarn %s  (ratio=%.4f)",
                    $this->fmt(self::NEW_VALUE),
                    $this->fmt(self::NEW_VALUE2),
                    $this->fmt(self::NEW_PSTERM),
                    $this->money($newLocFee),
                    $this->money($newNetLoc),
                    $this->money($newLocEarning),
                    $flatDayRatio
                ));

                $row->contract_commission_value = self::NEW_VALUE;
                $row->contract_commission_value2 = self::NEW_VALUE2;
                $row->contract_ps_term = self::NEW_PSTERM;
                $row->location_fees_cents = $newLocFee;
                $row->location_earning_cents = $newLocEarning;
                $row->location_earning_rate = $newLocRate;
                $row->save();
            }

            if ($apply) {
                DB::commit();
                $this->command->info('Committed. Reload the Summary page to verify.');
            } else {
                DB::rollBack();
                $this->command->warn('DRY-RUN complete — rolled back. Re-run with FIX_APPLY=1 to commit.');
            }
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->command->error('Error, rolled back: ' . $e->getMessage());
            throw $e;
        }
    }

    /** Trim trailing zeros after the decimal only: 50.00 -> "50", 8.00 -> "8". */
    private function fmt($value): string
    {
        $s = number_format((float) $value, 2, '.', '');
        return rtrim(rtrim($s, '0'), '.');
    }

    private function money($cents): string
    {
        return 'S$' . number_format(((int) $cents) / 100, 2);
    }
}
