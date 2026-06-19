<?php

namespace Database\Seeders;

use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Floor every customer's begin_date forward to 2023-01-01.
 *
 * The Customer Summary now clamps both the displayed period window and the
 * lifetime "Accumulate Vending Earning" running sum at the reporting floor —
 * see App\Http\Controllers\CustomerController::summaryFloorDate(). The reason
 * is that pre-floor monthly rows in customer_period_summaries were
 * reconstructed from imported Excel and are incomplete (the system only
 * captures reliable live monthly aggregates from the floor date onwards).
 *
 * Leaving Customer.begin_date earlier than that floor implies a contract
 * history the Summary refuses to show, which confuses operators ("why
 * does this customer's begin date say 2019 but their Summary starts in
 * Jan 2023?"). This seeder bumps any pre-floor begin_date forward to
 * 2023-01-01 so the field agrees with the Summary's earliest visible
 * month.
 *
 * Behaviour:
 *   begin_date IS NULL          → untouched
 *   begin_date >= 2023-01-01    → untouched
 *   begin_date <  2023-01-01    → set to 2023-01-01 00:00:00
 *
 * Idempotent — re-running after a successful pass reports 0 updated.
 * (Customers floored to 2022-01-01 by an earlier run will be bumped
 * forward again to the new 2023-01-01 floor on the next run.)
 *
 *   php artisan db:seed --class=FloorCustomerBeginDateSeeder
 *
 * Notes:
 *   - Uses DB::table so customers.updated_at is NOT bumped and no Eloquent
 *     events fire. Pure data-normalisation, invisible to any future
 *     audit logic keyed off updated_at.
 *   - No global scopes apply on DB::table, so soft-deleted or otherwise
 *     scoped customers (none today, but future-proofing) are still
 *     included.
 *   - A bulk UPDATE is fine here — the candidate set is at most a few
 *     thousand rows on the largest deployments.
 */
class FloorCustomerBeginDateSeeder extends Seeder
{
    public function run(): void
    {
        // Single app-wide reporting floor — see CustomerController::summaryFloorDate()
        // (config('reporting.floor_date')). Idempotent; re-run if the floor moves.
        $floor = Carbon::parse(\App\Http\Controllers\CustomerController::summaryFloorDate())->startOfDay();
        $floorString = $floor->toDateTimeString(); // '2023-01-01 00:00:00'

        $candidateQuery = DB::table('customers')
            ->whereNotNull('begin_date')
            ->where('begin_date', '<', $floorString);

        $candidateCount = (clone $candidateQuery)->count();

        if ($candidateCount === 0) {
            $this->command?->info(sprintf(
                'FloorCustomerBeginDateSeeder: no customers with begin_date earlier than %s — nothing to update.',
                $floor->toDateString()
            ));
            return;
        }

        // Log a small sample so the operator can spot-check what's about
        // to change. Pulled before the UPDATE so the "old" values appear.
        $sample = (clone $candidateQuery)
            ->select('id', 'code', 'begin_date')
            ->orderBy('begin_date')
            ->limit(5)
            ->get();

        $this->command?->info(sprintf(
            'FloorCustomerBeginDateSeeder: %d customer(s) have begin_date < %s. Flooring to %s.',
            $candidateCount,
            $floor->toDateString(),
            $floor->toDateString()
        ));
        foreach ($sample as $row) {
            $this->command?->line(sprintf(
                '  - #%d (%s) begin_date %s → %s',
                $row->id,
                $row->code ?? '-',
                $row->begin_date,
                $floor->toDateString()
            ));
        }
        if ($candidateCount > $sample->count()) {
            $this->command?->line(sprintf(
                '  …and %d more.',
                $candidateCount - $sample->count()
            ));
        }

        $affected = $candidateQuery->update([
            'begin_date' => $floorString,
        ]);

        $this->command?->info(sprintf(
            'FloorCustomerBeginDateSeeder: updated %d row(s).',
            $affected
        ));
    }
}
