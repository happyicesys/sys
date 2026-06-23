<?php

namespace Database\Seeders;

use App\Services\CustomerSummaryAggregator;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Recompute customer_period_summaries for the CURRENT (in-progress) month only,
 * then report any customer-month that ended up with more than one row.
 *
 * Thin wrapper around CustomerSummaryAggregator::persistMonth so the current
 * month can be refreshed on demand via `php artisan db:seed` without waiting
 * for the nightly `customer-summary:compute` schedule. Mirrors the nightly
 * default exactly: the month containing today, capped at yesterday (as-of),
 * which is the last fully-settled day.
 *
 * Behaviour notes (force-single + respect-locked — see the persistMonth call):
 *   - Locked rows are NOT regenerated — frozen snapshots stay as the user set
 *     (so locked previous months are never touched; this only refreshes the
 *     current in-progress month's UNLOCKED rows).
 *   - Every UNLOCKED site collapses to a SINGLE whole-month row computed from
 *     its LATEST contract. NO mid-month splits are produced here, regardless of
 *     contract history — the current month is consolidated to one row per site.
 *   - Splits only ever appear later, when a "Set Upcoming Term" change is
 *     applied by the cron on its effective date. The month is left NOT
 *     overridden, so such a future scheduled change can still split it.
 *
 * After recompute it scans the month for replicated rows (same customer +
 * year_month) and prints them. Post force-single there should be NONE; any that
 * appear are a stale/locked edge worth a look.
 *
 * Run with:
 *   php artisan db:seed --class=RecomputeCurrentMonthSummarySeeder
 */
class RecomputeCurrentMonthSummarySeeder extends Seeder
{
    public function run(): void
    {
        // Reference any day in the current month; persistMonth derives the
        // month window from it. As-of = yesterday, matching the nightly run.
        $reference = Carbon::today();
        $asOf = Carbon::today()->subDay();
        $monthStart = $reference->copy()->startOfMonth()->toDateString();

        $this->command?->info(sprintf(
            'Recomputing current month (%s), as-of %s ...',
            $reference->format('Y-m'),
            $asOf->toDateString()
        ));

        // Force-single + respect-locked: collapse EVERY unlocked site in the
        // current month to a single whole-month row computed from its LATEST
        // contract. No mid-month splits are produced here regardless of contract
        // history — splits only ever appear later when a "Set Upcoming Term"
        // change is applied by the cron on its effective date. Locked rows stay
        // frozen, and the month is NOT marked overridden, so such a future
        // scheduled change can still split it.
        $rows = CustomerSummaryAggregator::persistMonth($reference, $asOf, true, true);

        $this->command?->info(sprintf(
            'Done — %d summary row(s) written for %s.',
            $rows,
            $reference->format('Y-m')
        ));

        $this->reportReplicatedRows($monthStart);
    }

    /**
     * Find and print customer-months in $monthStart that hold more than one
     * row, flagging whether the segments carry identical fees (suspicious) or
     * different fees (a legitimate mid-month change).
     */
    protected function reportReplicatedRows(string $monthStart): void
    {
        $duplicateCustomerIds = DB::table('customer_period_summaries')
            ->select('customer_id')
            ->where('year_month', $monthStart)
            ->groupBy('customer_id')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('customer_id')
            ->all();

        if (empty($duplicateCustomerIds)) {
            $this->command?->info('No replicated rows this month — every customer has a single row.');
            return;
        }

        $rows = DB::table('customer_period_summaries')
            ->where('year_month', $monthStart)
            ->whereIn('customer_id', $duplicateCustomerIds)
            ->orderBy('customer_id')
            ->orderBy('period_start')
            ->get([
                'customer_id', 'segment_index', 'period_start', 'period_end',
                'contract_commission_type', 'contract_commission_value',
                'contract_commission_value2', 'contract_ps_term',
                'external_subsidize_cents',
            ]);

        // Fee signature per row — same signature across a customer's rows means
        // the split carries identical fees (nothing fee-relevant changed).
        $feeSig = function ($r): string {
            $n = fn ($v) => ($v === null || $v === '') ? '' : number_format((float) $v, 4, '.', '');
            return implode('|', [
                (string) ($r->contract_commission_type ?? ''),
                $n($r->contract_commission_value),
                $n($r->contract_commission_value2),
                $n($r->contract_ps_term),
                (string) ((int) ($r->external_subsidize_cents ?? 0)),
            ]);
        };

        $byCustomer = [];
        foreach ($rows as $r) {
            $byCustomer[$r->customer_id][] = $r;
        }

        $identical = 0;
        $differing = 0;

        $this->command?->warn(sprintf(
            'Replicated customer-months in %s: %d',
            $monthStart,
            count($byCustomer)
        ));

        foreach ($byCustomer as $customerId => $segs) {
            $sigs = array_unique(array_map($feeSig, $segs));
            $sameFees = count($sigs) === 1;
            $sameFees ? $identical++ : $differing++;

            $flag = $sameFees ? 'IDENTICAL fees → review' : 'fees differ (legit split)';
            // ref_id matches the on-screen Customer ID (id + RUNNING_NUMBER_INIT).
            $refId = (int) $customerId + \App\Models\Customer::RUNNING_NUMBER_INIT;
            $this->command?->line(sprintf(
                '  - customer %d (ref %d): %d rows — %s',
                $customerId,
                $refId,
                count($segs),
                $flag
            ));
            foreach ($segs as $s) {
                $this->command?->line(sprintf(
                    '      seg%d  %s..%s  %s %s%s',
                    (int) $s->segment_index,
                    $s->period_start,
                    $s->period_end,
                    $s->contract_commission_type ?? '-',
                    $s->contract_commission_value !== null ? number_format((float) $s->contract_commission_value, 2) : '-',
                    $s->contract_ps_term !== null ? (' term' . number_format((float) $s->contract_ps_term, 0)) : ''
                ));
            }
        }

        $this->command?->warn(sprintf(
            'Summary: %d legitimate (fees differ), %d to review (identical fees).',
            $differing,
            $identical
        ));
        if ($identical > 0) {
            $this->command?->warn(
                'Identical-fee splits should not occur post-fix. If any appear, check '
                . 'customer_contract_logs for that customer — a scheduled (source=scheduled) '
                . 'log with the same fees, or a stale segment row, may remain.'
            );
        }
    }
}
