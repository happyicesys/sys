<?php

namespace Database\Seeders;

use App\Http\Controllers\CustomerController;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * One-off backfill for customers.active_date (the start of the Site's current
 * active interval, which drives the Summary commission window).
 *
 * Per the agreed design, active_date initially mirrors begin_date, FLOORED at
 * the Summary reporting floor (CustomerController::summaryFloorDate(),
 * default 2023-01-01): any site whose begin_date is on/before the floor starts at the
 * floor; later begin_dates are retained as-is. Sites with no begin_date are
 * left null — the aggregator falls back to begin_date / treats them as always
 * having started.
 *
 * Only fills rows where active_date IS NULL, so it is safe and idempotent and
 * never clobbers a date a user has since set via the status prompt.
 *
 * Run with:
 *   php artisan db:seed --class=SeedCustomerActiveDateFromBeginDateSeeder
 */
class SeedCustomerActiveDateFromBeginDateSeeder extends Seeder
{
    public function run(): void
    {
        $floor = Carbon::parse(CustomerController::summaryFloorDate())->startOfDay();

        $updated = 0;
        DB::table('customers')
            ->whereNull('active_date')
            ->whereNotNull('begin_date')
            ->orderBy('id')
            ->select('id', 'begin_date')
            ->chunkById(500, function ($rows) use ($floor, &$updated) {
                foreach ($rows as $row) {
                    $begin = Carbon::parse($row->begin_date)->startOfDay();
                    $active = $begin->lessThanOrEqualTo($floor) ? $floor->copy() : $begin;

                    DB::table('customers')
                        ->where('id', $row->id)
                        ->update(['active_date' => $active->toDateString()]);
                    $updated++;
                }
            });

        $this->command?->info("active_date backfill complete: {$updated} site(s) seeded from begin_date (floored at {$floor->toDateString()}).");
    }
}
