<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\CustomerContractLog;
use App\Models\Operator;
use App\Services\CustomerSummaryAggregator;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * One-off: set every customer's placement-contract PS Term ("Period Report" %)
 * according to a tiered rule, effective from the start of the current month.
 *
 * Tiers (evaluated top-down, first match wins):
 *   1. Operator code UL-ST            → 100%
 *   2. Customer name contains "zoo"   →  70%   (case-insensitive)
 *   3. Everyone else                  →  50%
 *
 * So a UL-ST customer is 100% even if its name has "zoo" (operator rule wins).
 *
 * Mirrors the live Edit-form / ApplyScheduledContracts contract-change path so
 * it stays consistent with existing infra:
 *
 *   1. Writes the new value onto the live `customers.contract_ps_term` and
 *      stamps contract_detail_updated_at / _by (so the Edit audit line + the
 *      Customer Summary current-month live row reflect it).
 *   2. Appends a customer_contract_logs version effective_from = 1st of the
 *      current month (closing the previously-active version at the same
 *      instant). Because effective_from lands on the 1st, the aggregator's
 *      mid-month segmentation keeps the month as a single whole-month row.
 *      See [[reference_contract_history]] / [[future_dated_contract]].
 *   3. Recomputes the current month so the Summary page shows the new rates
 *      immediately instead of waiting for the nightly customer-summary:compute.
 *
 * Idempotent: customers already at their target rate are skipped, so it is
 * safe to re-run.
 *
 *   php artisan db:seed --class=SetPsTermRatesSeeder
 *
 * Dry run (report only, no writes):
 *   DRY_RUN=1 php artisan db:seed --class=SetPsTermRatesSeeder
 */
class SetPsTermRatesSeeder extends Seeder
{
    /** Tier rates. */
    private const RATE_UL_ST = 100.0;  // operator code UL-ST
    private const RATE_ZOO   = 70.0;   // name contains "zoo"
    private const RATE_REST  = 50.0;   // everyone else

    /** Operator code that gets RATE_UL_ST. */
    private const UL_ST_CODE = 'UL-ST';

    /** Case-insensitive substring that gets RATE_ZOO. */
    private const ZOO_NEEDLE = 'zoo';

    /**
     * Attribute the contract-log + audit stamp to this user id (nullable).
     * Leave null for a system-attributed change; set to your user id if you
     * want it to read as you in the contract history.
     */
    private const ACTOR_USER_ID = null;

    /**
     * If TRUE, the current-month recompute also OVERWRITES locked rows
     * (forceSingleRow). Default FALSE keeps locked snapshots frozen and only
     * refreshes unlocked rows — the seeder reports any locked rows it finds so
     * you can decide. NOTE: forceSingleRow collapses ANY pre-existing legit
     * mid-month split into one row, so only flip this on if you've reviewed the
     * locked-row report below.
     */
    private const OVERWRITE_LOCKED = false;

    public function run(): void
    {
        $dryRun = (bool) env('DRY_RUN', false);
        $effectiveFrom = Carbon::today()->startOfMonth(); // 1st of current month, 00:00
        $now = now();

        // Operator id(s) carrying the UL-ST code (100% tier).
        $ulStOperatorIds = Operator::query()
            ->where('code', self::UL_ST_CODE)
            ->pluck('id')
            ->all();
        $ulStSet = array_flip($ulStOperatorIds);

        $this->command?->info(sprintf(
            'PS Term tiers → UL-ST(id %s)=%s%%, name~"%s"=%s%%, rest=%s%%. Effective %s.%s',
            $ulStOperatorIds ? implode(',', $ulStOperatorIds) : 'none found',
            number_format(self::RATE_UL_ST, 0),
            self::ZOO_NEEDLE,
            number_format(self::RATE_ZOO, 0),
            number_format(self::RATE_REST, 0),
            $effectiveFrom->toDateString(),
            $dryRun ? '  [DRY RUN — no writes]' : ''
        ));

        if (empty($ulStOperatorIds)) {
            $this->command?->warn(sprintf(
                'No operator with code "%s" found — the 100%% tier cannot be applied. '
                . 'Fix the operator code first. Aborting as a safety guard.',
                self::UL_ST_CODE
            ));
            return;
        }

        // Per-customer target rate (first match wins; UL-ST beats "zoo").
        $targetFor = function (Customer $c) use ($ulStSet): float {
            if (isset($ulStSet[$c->operator_id])) {
                return self::RATE_UL_ST;
            }
            if (stripos((string) $c->name, self::ZOO_NEEDLE) !== false) {
                return self::RATE_ZOO;
            }
            return self::RATE_REST;
        };

        $counts = ['100' => 0, '70' => 0, '50' => 0]; // changed, by resulting rate
        $skippedAlready = 0;

        Customer::query()
            ->withoutGlobalScopes() // ALL customers — every tier is in scope.
            ->select([
                'id', 'name', 'operator_id',
                'contract_commission_type', 'contract_commission_value',
                'contract_commission_value2', 'contract_ps_term',
                'is_external_subsidize', 'external_subsidize_amount',
                'contract_from', 'contract_until', 'contract_auto_renewal',
                'contract_notice_period', 'contract_remarks',
            ])
            ->orderBy('id')
            ->chunkById(500, function ($customers) use (
                &$counts, &$skippedAlready, $targetFor, $effectiveFrom, $now, $dryRun
            ) {
                foreach ($customers as $customer) {
                    $target = $targetFor($customer);

                    $current = $customer->contract_ps_term !== null
                        ? (float) $customer->contract_ps_term
                        : null;

                    // Already at this customer's target — nothing to do (keeps
                    // it idempotent and avoids contract-log noise on re-runs).
                    if ($current === $target) {
                        $skippedAlready++;
                        continue;
                    }

                    $counts[(string) (int) $target]++;

                    if ($dryRun) {
                        continue;
                    }

                    DB::transaction(function () use ($customer, $target, $effectiveFrom, $now) {
                        // 1) Live contract field + audit stamp.
                        Customer::withoutGlobalScopes()
                            ->whereKey($customer->id)
                            ->update([
                                'contract_ps_term'           => $target,
                                'contract_detail_updated_at' => $effectiveFrom,
                                'contract_detail_updated_by' => self::ACTOR_USER_ID,
                                'updated_at'                 => $now,
                            ]);

                        // 2) Contract-log version.
                        $open = CustomerContractLog::query()
                            ->where('customer_id', $customer->id)
                            ->whereNull('effective_to')
                            ->orderByDesc('effective_from')
                            ->first();

                        if ($open && Carbon::parse($open->effective_from)->gte($effectiveFrom)) {
                            // The currently-active version already starts on/after
                            // the 1st of this month — don't back-date a new row
                            // (that would invert effective_from/_to). Just amend
                            // the existing open row's PS Term in place.
                            $open->contract_ps_term = $target;
                            $open->changed_by = self::ACTOR_USER_ID;
                            $open->source = 'system';
                            $open->save();
                        } else {
                            // Normal path: close the open version at the effective
                            // instant and append a fresh active version carrying
                            // the rest of the contract unchanged.
                            if ($open) {
                                $open->effective_to = $effectiveFrom;
                                $open->save();
                            }

                            // Copy the rest of the contract from the LIVE
                            // customer row (the source of truth — matches
                            // CustomerController::update), overriding only the
                            // PS Term.
                            CustomerContractLog::query()->create([
                                'customer_id'                => $customer->id,
                                'effective_from'             => $effectiveFrom,
                                'effective_to'               => null,
                                'contract_commission_type'   => $customer->contract_commission_type,
                                'contract_commission_value'  => $customer->contract_commission_value,
                                'contract_commission_value2' => $customer->contract_commission_value2,
                                'contract_ps_term'           => $target,
                                'is_external_subsidize'      => (bool) $customer->is_external_subsidize,
                                'external_subsidize_amount'  => $customer->external_subsidize_amount,
                                'contract_from'              => $customer->contract_from,
                                'contract_until'             => $customer->contract_until,
                                'contract_auto_renewal'      => (bool) $customer->contract_auto_renewal,
                                'contract_notice_period'     => $customer->contract_notice_period,
                                'contract_remarks'           => $customer->contract_remarks,
                                'changed_by'                 => self::ACTOR_USER_ID,
                                'source'                     => 'system',
                            ]);
                        }
                    });
                }
            });

        $this->command?->info(sprintf(
            '%sUL-ST→100%%: %d, "%s"→70%%: %d, rest→50%%: %d  (%d already on target, skipped).',
            $dryRun ? '(dry run) would change — ' : 'Changed — ',
            $counts['100'],
            self::ZOO_NEEDLE,
            $counts['70'],
            $counts['50'],
            $skippedAlready
        ));

        if ($dryRun) {
            $this->command?->warn('Dry run — no contract or summary rows were written.');
            return;
        }

        $this->recomputeCurrentMonth($effectiveFrom);
    }

    /**
     * Refresh the current month so the Summary page reflects the new rates
     * right away, then report any LOCKED rows that the standard recompute left
     * untouched (so the operator can decide whether to overwrite them).
     */
    private function recomputeCurrentMonth(Carbon $monthStart): void
    {
        $reference = Carbon::today();
        $asOf = Carbon::today()->subDay();

        $this->command?->info(sprintf(
            'Recomputing current month (%s), as-of %s%s ...',
            $reference->format('Y-m'),
            $asOf->toDateString(),
            self::OVERWRITE_LOCKED ? '  [forceSingleRow — overwrites locked rows]' : ''
        ));

        $rows = CustomerSummaryAggregator::persistMonth($reference, $asOf, self::OVERWRITE_LOCKED);

        $this->command?->info(sprintf('Recompute done — %d summary row(s) written.', $rows));

        if (self::OVERWRITE_LOCKED) {
            return; // Locked rows were overwritten on purpose; nothing to flag.
        }

        // Surface locked rows in the current month — these keep their frozen
        // (old PS Term) snapshot. The live contract + contract log are already
        // updated, so they'll show the new rate once unlocked/recomputed.
        $lockedCount = DB::table('customer_period_summaries')
            ->where('year_month', $monthStart->toDateString())
            ->whereNotNull('locked_at')
            ->count();

        if ($lockedCount > 0) {
            $this->command?->warn(sprintf(
                'ALERT: %d locked row(s) in %s still hold their old PS Term snapshot. '
                . 'Unlock + recompute them, or set OVERWRITE_LOCKED = true and re-run to overwrite.',
                $lockedCount,
                $monthStart->format('Y-m')
            ));
        } else {
            $this->command?->info('No locked rows this month — every current-month row now reflects the new rates.');
        }
    }
}
