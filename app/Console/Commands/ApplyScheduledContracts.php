<?php

namespace App\Console\Commands;

use App\Models\Customer;
use App\Models\CustomerContractLog;
use App\Models\CustomerScheduledContract;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Apply pending future placement-contract changes that have reached their
 * effective date.
 *
 * For every customer_scheduled_contracts row with status = pending and
 * effective_date <= the run date (today by default), this:
 *
 *   1. Copies the scheduled values onto the live `customers` row and stamps
 *      contract_detail_updated_at / _by (so the Edit page audit line reflects
 *      it, and the Customer Summary current-month live row picks it up).
 *   2. Appends a customer_contract_logs version with effective_from =
 *      effective_date 00:00 (closing the previously-active version at the same
 *      instant). The aggregator's existing mid-month segmentation then does
 *      the rest: a 1st-of-month effective_from stays a single whole-month row;
 *      a mid-month one splits the month into old/new segments — exactly the
 *      behaviour the live Edit form produces today.
 *   3. Flips the schedule row to status = applied with applied_at set.
 *
 * Scheduled to run daily just BEFORE customer-summary:compute (01:00) so the
 * new contract version exists before the nightly summary recompute.
 *
 * Examples:
 *   php artisan contract:apply-scheduled
 *   php artisan contract:apply-scheduled --date=2026-07-01   # pretend it's that day
 *   php artisan contract:apply-scheduled --dry-run           # report only, no writes
 */
class ApplyScheduledContracts extends Command
{
    protected $signature = 'contract:apply-scheduled
        {--date= : Treat this date (YYYY-MM-DD) as "today" when deciding what is due. Default: today.}
        {--dry-run : List what would be applied without writing anything.}';

    protected $description = 'Apply pending future placement-contract changes whose effective date has arrived';

    public function handle(): int
    {
        $today = $this->option('date')
            ? Carbon::parse($this->option('date'))->startOfDay()
            : Carbon::today();

        $dryRun = (bool) $this->option('dry-run');

        $due = CustomerScheduledContract::query()
            ->where('status', CustomerScheduledContract::STATUS_PENDING)
            ->whereDate('effective_date', '<=', $today->toDateString())
            ->orderBy('effective_date')
            ->orderBy('id')
            ->get();

        if ($due->isEmpty()) {
            $this->info('No scheduled contracts are due as of ' . $today->toDateString() . '.');
            return self::SUCCESS;
        }

        $this->info(sprintf(
            '%d scheduled contract(s) due as of %s%s.',
            $due->count(),
            $today->toDateString(),
            $dryRun ? ' (dry-run)' : ''
        ));

        $applied = 0;
        $skipped = 0;

        foreach ($due as $sched) {
            // withoutGlobalScopes so an operator-scoped context (e.g. tinker)
            // can't hide rows; the nightly cron has no auth so it's a no-op there.
            $customer = Customer::withoutGlobalScopes()->find($sched->customer_id);
            if (!$customer) {
                $this->warn(" - schedule #{$sched->id}: customer {$sched->customer_id} not found, skipping.");
                $skipped++;
                continue;
            }

            // effective_from belongs to the new version (standard effective-
            // dating). Stamp it at the start of the effective day so the
            // aggregator buckets it onto that calendar date.
            $effectiveFrom = Carbon::parse($sched->effective_date)->startOfDay();

            $this->line(sprintf(
                ' - schedule #%d: customer #%d (%s) → effective %s',
                $sched->id,
                $customer->id,
                $customer->name ?? '—',
                $effectiveFrom->toDateString()
            ));

            if ($dryRun) {
                $applied++;
                continue;
            }

            DB::transaction(function () use ($sched, $customer, $effectiveFrom) {
                // 1) Live customer contract fields.
                $customer->contract_commission_type   = $sched->contract_commission_type;
                $customer->contract_commission_value  = $sched->contract_commission_value;
                $customer->contract_commission_value2 = $sched->contract_commission_value2;
                $customer->contract_ps_term           = $sched->contract_ps_term;
                $customer->is_external_subsidize      = (bool) $sched->is_external_subsidize;
                $customer->external_subsidize_amount  = $sched->is_external_subsidize ? $sched->external_subsidize_amount : null;
                $customer->contract_from              = $sched->contract_from;
                $customer->contract_until             = $sched->contract_until;
                $customer->contract_auto_renewal      = (bool) $sched->contract_auto_renewal;
                $customer->contract_notice_period     = $sched->contract_notice_period;
                $customer->contract_remarks           = $sched->contract_remarks;
                $customer->contract_detail_updated_at = $effectiveFrom;
                $customer->contract_detail_updated_by = $sched->created_by;
                $customer->save();

                // 2) Contract log version. Close the currently-active one at the
                //    same instant so the history stays contiguous.
                CustomerContractLog::query()
                    ->where('customer_id', $customer->id)
                    ->whereNull('effective_to')
                    ->update(['effective_to' => $effectiveFrom]);

                CustomerContractLog::query()->create([
                    'customer_id'                => $customer->id,
                    'effective_from'             => $effectiveFrom,
                    'effective_to'               => null,
                    'contract_commission_type'   => $customer->contract_commission_type,
                    'contract_commission_value'  => $customer->contract_commission_value,
                    'contract_commission_value2' => $customer->contract_commission_value2,
                    'contract_ps_term'           => $customer->contract_ps_term,
                    'is_external_subsidize'      => (bool) $customer->is_external_subsidize,
                    'external_subsidize_amount'  => $customer->external_subsidize_amount,
                    'contract_from'              => $customer->contract_from,
                    'contract_until'             => $customer->contract_until,
                    'contract_auto_renewal'      => (bool) $customer->contract_auto_renewal,
                    'contract_notice_period'     => $customer->contract_notice_period,
                    'contract_remarks'           => $customer->contract_remarks,
                    'changed_by'                 => $sched->created_by,
                    'source'                     => 'system',
                ]);

                // 3) Mark the schedule applied.
                $sched->status = CustomerScheduledContract::STATUS_APPLIED;
                $sched->applied_at = now();
                $sched->save();
            });

            $applied++;
        }

        $this->info(sprintf(
            'Done. %s%d applied, %d skipped.',
            $dryRun ? '(dry-run) ' : '',
            $applied,
            $skipped
        ));

        return self::SUCCESS;
    }
}
