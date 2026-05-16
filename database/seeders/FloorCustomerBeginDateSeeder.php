<?php

namespace Database\Seeders;

use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Floor every customer's begin_date forward to 2022-01-01.
 *
 * The Customer Summary now clamps both the displayed period window and the
 * lifetime "Accumulate Vending Earning" running sum at 2022-01-01 — see
 * App\Http\Controllers\CustomerController::SUMMARY_FLOOR_DATE. The reason
 * is that pre-2022 monthly rows in customer_period_summaries were
 * reconstructed from imported Excel and are incomplete (the system itself
 * only started capturing live monthly aggregates from 2022 onwards).
 *
 * Leaving Customer.begin_date earlier than that floor implies a contract
 * history the Summary refuses to show, which confuses operators ("why
 * does this customer's begin date say 2019 but their Summary starts in
 * Jan 2022?"). This seeder bumps any pre-2022 begin_date forward to
 * 2022-01-01 so the field agrees with the Summary's earliest visible
 * month.
 *
 * Behaviour:
 *   begin_date IS NULL          → untouched
 *   begin_date >= 2022-01-01    → untouched
 *   begin_date <  2022-01-01    → set to 2022-01-01 00:00:00
 *
 * Idempotent — re-running after a successful pass reports 0 updated.
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
    /**
     * Keep this in lockstep with CustomerController::SUMMARY_FLOOR_DATE.
     * If that constant ever moves, update this one too (and run the
     * seeder again — it's idempotent).
     */
    private const FLOOR_DATE = '2022-01-01';

    public function run(): void
    {
        $floor = Carbon::parse(self::FLOOR_DATE)->startOfDay();
        $floorString = $floor->toDateTimeString(); // '2022-01-01 00:00:00'

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
