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
 * One-off: set the placement-contract PS Term ("Period Report" %) to 50 for
 * EVERY customer EXCEPT those under the operator whose code is UL-ST, effective
 * from the start of the current month.
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
 *      mid-month segmentation keeps June as a single whole-month row — no
 *      split. See [[reference_contract_history]] / [[future_dated_contract]].
 *   3. Recomputes the current month so the Summary page shows 50% immediately
 *      instead of waiting for the nightly customer-summary:compute.
 *
 * Idempotent: customers already at 50 (and UL-ST customers) are skipped, so it
 * is safe to re-run.
 *
 *   php artisan db:seed --class=SetPsTermTo50ExceptUlStSeeder
 *
 * Dry run (report only, no writes):
 *   DRY_RUN=1 php artisan db:seed --class=SetPsTermTo50ExceptUlStSeeder
 */
class SetPsTermTo50ExceptUlStSeeder extends Seeder
{
    /** Target PS Term value. */
    private const NEW_PS_TERM = 50.0;

    /** Operator code to leave untouched. */
    private const EXCLUDE_OPERATOR_CODE = 'UL-ST';

    /**
     * Attribute the contract-log + audit stamp to this user id (nullable).
     * Leave null for a system-attributed change; set to your user id if you
     * want it to read as you in the contract history.
     */
    private const ACTOR_USER_ID = null;

    /**
     * If TRUE, the current-month recompute also OVERWRITES locked June rows
     * (forceSingleRow). Default FALSE keeps locked snapshots frozen and only
     * refreshes unlocked rows — the seeder reports any locked rows it finds so
     * you can decide. NOTE: forceSingleRow collapses ANY pre-existing legit
     * mid-June split into one row, so only flip this on if you've reviewed the
     * locked-row report below.
     */
    private const OVERWRITE_LOCKED_JUNE = false;

    public function run(): void
    {
        $dryRun = (bool) env('DRY_RUN', false);
        $effectiveFrom = Carbon::today()->startOfMonth(); // 1st of current month, 00:00
        $now = now();

        // Operator id(s) carrying the excluded code.
        $excludedOperatorIds = Operator::query()
            ->where('code', self::EXCLUDE_OPERATOR_CODE)
            ->pluck('id')
            ->all();

        $this->command?->info(sprintf(
            'PS Term → %s for all customers except operator "%s" (id %s). Effective %s.%s',
            number_format(self::NEW_PS_TERM, 0),
            self::EXCLUDE_OPERATOR_CODE,
            $excludedOperatorIds ? implode(',', $excludedOperatorIds) : 'none found',
            $effectiveFrom->toDateString(),
            $dryRun ? '  [DRY RUN — no writes]' : ''
        ));

        if (empty($excludedOperatorIds)) {
            $this->command?->warn(sprintf(
                'No operator with code "%s" found — EVERY customer would be changed. Aborting as a safety guard.',
                self::EXCLUDE_OPERATOR_CODE
            ));
            return;
        }

        $changed = 0;
        $skippedAlready = 0;

        Customer::query()
            ->withoutGlobalScopes()
            // Everyone except the excluded operator. orWhereNull keeps
            // operator-less customers in scope (SQL NOT IN drops NULLs).
            ->where(function ($q) use ($excludedOperatorIds) {
                $q->whereNotIn('operator_id', $excludedOperatorIds)
                    ->orWhereNull('operator_id');
            })
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
                &$changed, &$skippedAlready, $effectiveFrom, $now, $dryRun
            ) {
                foreach ($customers as $customer) {
                    $current = $customer->contract_ps_term !== null
                        ? (float) $customer->contract_ps_term
                        : null;

                    // Already at target — nothing to do (keeps it idempotent
                    // and avoids contract-log noise on re-runs).
                    if ($current === self::NEW_PS_TERM) {
                        $skippedAlready++;
                        continue;
                    }

                    $changed++;

                    if ($dryRun) {
                        continue;
                    }

                    DB::transaction(function () use ($customer, $effectiveFrom, $now) {
                        // 1) Live contract field + audit stamp.
                        Customer::withoutGlobalScopes()
                            ->whereKey($customer->id)
                            ->update([
                                'contract_ps_term'           => self::NEW_PS_TERM,
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
                            $open->contract_ps_term = self::NEW_PS_TERM;
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
                                'contract_ps_term'           => self::NEW_PS_TERM,
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
            '%s%d customer(s) set to PS Term %s; %d already at %s (skipped).',
            $dryRun ? '(dry run) would change ' : 'Changed ',
            $changed,
            number_format(self::NEW_PS_TERM, 0),
            $skippedAlready,
            number_format(self::NEW_PS_TERM, 0)
        ));

        if ($dryRun) {
            $this->command?->warn('Dry run — no contract or summary rows were written.');
            return;
        }

        $this->recomputeCurrentMonth($effectiveFrom);
    }

    /**
     * Refresh the current month so the Summary page reflects the new PS Term
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
            self::OVERWRITE_LOCKED_JUNE ? '  [forceSingleRow — overwrites locked rows]' : ''
        ));

        $rows = CustomerSummaryAggregator::persistMonth($reference, $asOf, self::OVERWRITE_LOCKED_JUNE);

        $this->command?->info(sprintf('Recompute done — %d summary row(s) written.', $rows));

        if (self::OVERWRITE_LOCKED_JUNE) {
            return; // Locked rows were overwritten on purpose; nothing to flag.
        }

        // Surface locked rows in the current month — these keep their frozen
        // (old PS Term) snapshot. The live contract + contract log are already
        // updated, so they'll show 50% once unlocked/recomputed.
        $lockedCount = DB::table('customer_period_summaries')
            ->where('year_month', $monthStart->toDateString())
            ->whereNotNull('locked_at')
            ->count();

        if ($lockedCount > 0) {
            $this->command?->warn(sprintf(
                'ALERT: %d locked row(s) in %s still hold their old PS Term snapshot. '
                . 'Unlock + recompute them, or set OVERWRITE_LOCKED_JUNE = true and re-run to overwrite.',
                $lockedCount,
                $monthStart->format('Y-m')
            ));
        } else {
            $this->command?->info('No locked rows this month — every current-month row now reflects the new PS Term.');
        }
    }
}
