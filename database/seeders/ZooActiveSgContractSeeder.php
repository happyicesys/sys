<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\CustomerContractLog;
use App\Models\Vend;
use App\Services\CustomerSummaryAggregator;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeds the placement-contract details for the 30 customers in the Excel master:
 *   "contract - Master - to sys - 6 types.xlsx" (latest revision adds an
 *   explicit "PS term (%)" column — no longer assumed to default to 70).
 *
 * Covers six of the seven contract types currently in use:
 *   F      Free Placement     (1 row)
 *   S      Subsidized Plan    (1 row)
 *   R      Fix Rental         (12 rows — ActiveSG)
 *   PS     PS only            (14 rows — 13 Zoo + Sentosa Beach 2150)
 *   PS+U   PS + Utility       (1 row)
 *   PSORU  PS OR Utility      (1 row)
 *
 * For each row we:
 *   1. Look up the vend by `vends.code = machine_id`, then take its customer.
 *   2. Overwrite the customer's contract fields with the Excel values.
 *   3. Append a `customer_contract_logs` row with effective_from = contract_from
 *      (closing any prior currently-active log).
 *   4. Recompute `customer_period_summaries` from the earliest contract_from
 *      month through the current month, so the Summary page reflects the new
 *      contracts immediately rather than after tonight's 01:00 cron.
 *
 * Idempotent — safe to re-run. Existing log rows that already match
 * effective_from + source='seeder' are updated in place rather than duplicated.
 *
 *   php artisan db:seed --class=ZooActiveSgContractSeeder
 *
 * Encoding decisions (per user direction):
 *   - PS term is taken PER-ROW from the sheet's "PS term (%)" column rather
 *     than assumed to default to 70. Most rows are 70%, but Sentosa Beach
 *     2150 is 100% — meaning its PS commission applies to ALL sales (no
 *     70% term reduction).
 *     Effective rate examples:
 *       PS   33%, term 70  → sales × 70% × 33%        = 23.1% of sales (Zoo)
 *       PS   30%, term 100 → sales × 100% × 30%       = 30.0% of sales (2150)
 *       PS+U 18% + $30 @70 → sales × 70% × 18% + $30  = 12.6% sales + $30
 *       PSORU 15%/$20 @70  → max(sales × 70% × 15%, $20) = max(10.5% sales, $20)
 *   - Subsidized: $value stored positive on the customer; the aggregator
 *     negates it (income, not expense).
 *   - Free Placement: type='F', no values needed (formula returns 0).
 *   - Match customers via vends.code (the "machine_id" column in the sheet).
 *   - Fractional notice periods (0.25 / 0.5) are stored as int via intval()
 *     to match Edit.vue's parseInt() behaviour — they become 0. Flagged in
 *     the run log so it's visible at seed time.
 */
class ZooActiveSgContractSeeder extends Seeder
{
    /**
     * Source of truth — mirrors the uploaded Excel exactly.
     *
     * Each row:
     *   machine_id, contract_type ('F'|'S'|'R'|'PS'|'PS+U'|'PSORU'),
     *   value (rental$ for R, subsidy$ for S, PS% for PS variants),
     *   value2 (utility$ for PS+U / PSORU; null otherwise),
     *   ps_term (% for PS variants; null otherwise),
     *   contract_from, contract_until,
     *   auto_renewal (bool|null), notice_period_months (float), remarks
     */
    private const ROWS = [
        // --- PS + U: 18% PS @ term 70 + $30 utility ---
        [1809, 'PS+U',  18.00, 30.00, 70.00,  '2023-07-26', '2026-05-31', true,  0.25, null],

        // --- PS OR U: max(15% PS @ term 70, $20 utility) ---
        [2014, 'PSORU', 15.00, 20.00, 70.00,  '2022-04-16', '2026-05-31', true,  0.25, null],

        // --- Subsidized Plan: $150/mo income (we receive) ---
        [2700, 'S',    150.00, null,  null,   '2025-05-01', '2025-10-31', true,  0.5,  'Corperate plan. P1'],

        // --- Free Placement: no fee either way ---
        [2142, 'F',    null,   null,  null,   '2026-04-24', '2026-05-31', true,  0,    null],

        // --- PS only: Sentosa Beach @ 30% PS, term 100 (full sales basis) ---
        [2150, 'PS',    30.00, null,  100.00, '2026-04-09', '2026-12-31', true,  1.0,  null],

        // --- ActiveSG: Fix Rental $300 ---
        [2541, 'R',    300.00, null,  null,   '2026-02-20', '2027-11-30', null,  1.0,  'Master contract is Dec24-Nov27 with option ext 3ys'],
        [2603, 'R',    300.00, null,  null,   '2024-08-02', '2027-11-30', null,  1.0,  'Master contract is Dec24-Nov27 with option ext 3ys'],
        [2638, 'R',    300.00, null,  null,   '2023-07-04', '2027-11-30', null,  1.0,  'Master contract is Dec24-Nov27 with option ext 3ys'],
        [2776, 'R',    300.00, null,  null,   '2023-08-19', '2027-11-30', null,  1.0,  'Master contract is Dec24-Nov27 with option ext 3ys'],
        [2794, 'R',    300.00, null,  null,   '2023-07-01', '2027-11-30', null,  1.0,  'Master contract is Dec24-Nov27 with option ext 3ys'],
        [2803, 'R',    300.00, null,  null,   '2023-08-18', '2027-11-30', null,  1.0,  'Master contract is Dec24-Nov27 with option ext 3ys'],
        [2832, 'R',    300.00, null,  null,   '2025-09-17', '2027-11-30', null,  1.0,  'Master contract is Dec24-Nov27 with option ext 3ys'],
        [2835, 'R',    300.00, null,  null,   '2023-07-08', '2027-11-30', null,  1.0,  'Master contract is Dec24-Nov27 with option ext 3ys'],
        [2841, 'R',    300.00, null,  null,   '2025-11-12', '2027-11-30', null,  1.0,  'Master contract is Dec24-Nov27 with option ext 3ys'],
        [2847, 'R',    300.00, null,  null,   '2025-10-10', '2027-11-30', null,  1.0,  'Master contract is Dec24-Nov27 with option ext 3ys'],
        [2850, 'R',    300.00, null,  null,   '2023-08-31', '2027-11-30', null,  1.0,  'Master contract is Dec24-Nov27 with option ext 3ys'],
        [2851, 'R',    300.00, null,  null,   '2026-02-26', '2027-11-30', null,  1.0,  'Master contract is Dec24-Nov27 with option ext 3ys'],

        // --- Zoo: PS only @ 33%, term 70 ---
        [2112, 'PS',    33.00, null,  70.00,  '2021-12-24', '2026-10-31', false, 0.5,  'Option to extend 2ys'],
        [2113, 'PS',    33.00, null,  70.00,  '2021-11-23', '2026-10-31', false, 0.5,  'Option to extend 2ys'],
        [2129, 'PS',    33.00, null,  70.00,  '2021-12-09', '2026-10-31', false, 0.5,  'Option to extend 2ys'],
        [2130, 'PS',    33.00, null,  70.00,  '2021-12-06', '2026-10-31', false, 0.5,  'Option to extend 2ys'],
        [2240, 'PS',    33.00, null,  70.00,  '2021-11-26', '2026-10-31', false, 0.5,  'Option to extend 2ys'],
        [2268, 'PS',    33.00, null,  70.00,  '2022-06-22', '2026-10-31', false, 0.5,  'Option to extend 2ys'],
        [2356, 'PS',    33.00, null,  70.00,  '2023-10-20', '2026-10-31', false, 0.5,  'Option to extend 2ys'],
        [2363, 'PS',    33.00, null,  70.00,  '2021-12-24', '2026-10-31', false, 0.5,  'Option to extend 2ys'],
        [2443, 'PS',    33.00, null,  70.00,  '2021-12-10', '2026-10-31', false, 0.5,  'Option to extend 2ys'],
        [2475, 'PS',    33.00, null,  70.00,  '2021-12-13', '2026-10-31', false, 0.5,  'Option to extend 2ys'],
        [2476, 'PS',    33.00, null,  70.00,  '2021-12-17', '2026-10-31', false, 0.5,  'Option to extend 2ys'],
        [2517, 'PS',    33.00, null,  70.00,  '2021-11-30', '2026-10-31', false, 0.5,  'Option to extend 2ys'],
        [2573, 'PS',    33.00, null,  70.00,  '2025-09-05', '2026-10-31', false, 0.5,  'Option to extend 2ys'],
    ];

    public function run(): void
    {
        $now = now();
        $touchedCustomerIds = [];
        $earliestContractFrom = null;
        $missing = [];
        $updated = 0;

        foreach (self::ROWS as $row) {
            [$machineId, $type, $value, $value2, $psTerm, $contractFrom, $contractUntil,
                $autoRenewal, $noticePeriodFloat, $remarks] = $row;

            // 1. Find vend by code → customer.
            $vend = Vend::query()
                ->withoutGlobalScopes()
                ->where('code', (string) $machineId)
                ->first();

            if (!$vend || !$vend->customer_id) {
                $missing[] = $machineId;
                $this->command?->warn(sprintf(
                    ' - machine_id=%s: vend not found or unbound — skipping',
                    $machineId
                ));
                continue;
            }

            $customer = Customer::query()
                ->withoutGlobalScopes()
                ->find($vend->customer_id);
            if (!$customer) {
                $missing[] = $machineId;
                $this->command?->warn(sprintf(
                    ' - machine_id=%s: customer #%d not found — skipping',
                    $machineId,
                    $vend->customer_id
                ));
                continue;
            }

            // intval() to match Edit.vue's parseInt() behaviour: 0.5 → 0.
            $noticePeriod = (int) $noticePeriodFloat;
            if ((float) $noticePeriod !== $noticePeriodFloat) {
                $this->command?->warn(sprintf(
                    ' - machine_id=%s: notice_period %.1f truncated to %d (column is integer months)',
                    $machineId,
                    $noticePeriodFloat,
                    $noticePeriod
                ));
            }

            $contractFromCarbon = Carbon::parse($contractFrom)->startOfDay();

            DB::transaction(function () use (
                $customer, $type, $value, $value2, $psTerm, $contractFromCarbon,
                $contractUntil, $autoRenewal, $noticePeriod, $remarks, $now
            ) {
                // 2. Overwrite the contract block on the customer record.
                $customer->forceFill([
                    'contract_commission_type' => $type,
                    'contract_commission_value' => $value,
                    'contract_commission_value2' => $value2,
                    'contract_ps_term' => $psTerm,
                    'contract_from' => $contractFromCarbon->toDateString(),
                    'contract_until' => $contractUntil,
                    'contract_auto_renewal' => (bool) ($autoRenewal ?? false),
                    'contract_notice_period' => $noticePeriod,
                    'contract_remarks' => $remarks,
                    'contract_detail_updated_at' => $now,
                ])->save();

                // 3. customer_contract_logs:
                //    a) close any currently-active log at contract_from
                //       (only if it started strictly before contract_from)
                //    b) upsert a new active log at contract_from
                CustomerContractLog::query()
                    ->where('customer_id', $customer->id)
                    ->whereNull('effective_to')
                    ->where('effective_from', '<', $contractFromCarbon)
                    ->update([
                        'effective_to' => $contractFromCarbon,
                        'updated_at' => $now,
                    ]);

                $payload = [
                    'effective_to' => null,
                    'contract_commission_type' => $type,
                    'contract_commission_value' => $value,
                    'contract_commission_value2' => $value2,
                    'contract_ps_term' => $psTerm,
                    'contract_from' => $contractFromCarbon->toDateString(),
                    'contract_until' => $contractUntil,
                    'contract_auto_renewal' => (bool) ($autoRenewal ?? false),
                    'contract_notice_period' => $noticePeriod,
                    'contract_remarks' => $remarks,
                    'source' => 'seeder',
                    'updated_at' => $now,
                ];

                $existing = CustomerContractLog::query()
                    ->where('customer_id', $customer->id)
                    ->where('effective_from', $contractFromCarbon)
                    ->where('source', 'seeder')
                    ->first();

                if ($existing) {
                    $existing->forceFill($payload)->save();
                } else {
                    CustomerContractLog::query()->insert(array_merge($payload, [
                        'customer_id' => $customer->id,
                        'effective_from' => $contractFromCarbon,
                        'created_at' => $now,
                    ]));
                }
            });

            $touchedCustomerIds[$customer->id] = true;
            $updated++;
            if ($earliestContractFrom === null || $contractFromCarbon->lt($earliestContractFrom)) {
                $earliestContractFrom = $contractFromCarbon->copy();
            }

            $this->command?->info(sprintf(
                ' - machine_id=%s → customer #%d (%s) [%s]',
                $machineId,
                $customer->id,
                $customer->name,
                $type
            ));
        }

        // 4. Recompute customer_period_summaries from earliest contract_from
        //    month → current month. CustomerSummaryAggregator::persistMonth is
        //    idempotent and re-aggregates from gp_metrics for ALL customers in
        //    that month, so it picks up the new contract values automatically.
        if ($updated === 0) {
            $this->command?->warn('No customers were updated. Skipping summary recompute.');
            $this->logSummary($updated, $missing);
            return;
        }

        $rangeStart = $earliestContractFrom->copy()->startOfMonth();
        $rangeEnd = Carbon::today()->startOfMonth();
        $asOf = Carbon::today()->subDay();

        // Floor to gp_metrics earliest available date — pre-history months
        // would just produce empty aggregates.
        $gpMetricsEarliest = DB::table('gp_metrics')->min('txn_date');
        if ($gpMetricsEarliest) {
            $gpStartMonth = Carbon::parse($gpMetricsEarliest)->startOfMonth();
            if ($gpStartMonth->gt($rangeStart)) {
                $rangeStart = $gpStartMonth;
            }
        }

        $this->command?->info(sprintf(
            'Recomputing customer_period_summaries: %s → %s (as_of=%s)',
            $rangeStart->format('Y-m'),
            $rangeEnd->format('Y-m'),
            $asOf->toDateString()
        ));

        $cursor = $rangeStart->copy();
        $totalRows = 0;
        while ($cursor->lte($rangeEnd)) {
            $count = CustomerSummaryAggregator::persistMonth($cursor->copy(), $asOf);
            $totalRows += $count;
            $this->command?->info(sprintf(' - %s: %d rows', $cursor->format('Y-m'), $count));
            $cursor->addMonthNoOverflow();
        }

        $this->logSummary($updated, $missing, $totalRows);
    }

    private function logSummary(int $updated, array $missing, ?int $summaryRows = null): void
    {
        $this->command?->info(sprintf(
            'ZooActiveSgContractSeeder: customers updated=%d, missing/unbound machines=%d%s',
            $updated,
            count($missing),
            $summaryRows !== null ? sprintf(', summary rows persisted=%d', $summaryRows) : ''
        ));
        if (!empty($missing)) {
            $this->command?->warn('Missing machine_ids: ' . implode(', ', $missing));
        }
    }
}
