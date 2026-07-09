<?php

namespace Database\Seeders;

use App\Models\Holiday;
use App\Services\Holiday\HolidayDayRebuildService;
use Illuminate\Database\Seeder;

/**
 * Official MOE school terms & holidays for 2026 (MOE Kindergarten, Primary &
 * Secondary). School holidays have no public API, so they are seeded by hand
 * once a year from the MOE press release:
 *   https://www.moe.gov.sg/news/press-releases/20250730-school-terms-and-holidays-for-2026
 *
 * Idempotent — re-running updates the same rows (matched on date range + type).
 * JC/MI year-end vacation differs slightly (starts 28 Nov) and is intentionally
 * omitted; add rows here if that segment is needed.
 *
 * Run: php artisan db:seed --class=Database\\Seeders\\SgSchoolHolidaySeeder
 */
class SgSchoolHolidaySeeder extends Seeder
{
    public function run(): void
    {
        // Storage gate: same switch as the public-holiday sync. The Indonesia
        // app (HOLIDAY_SYNC_ENABLED off) never stores SG holiday data, even if
        // this seeder is invoked. Set HOLIDAY_SYNC_ENABLED=true on the SG app.
        if (! (bool) config('holiday.sync_enabled')) {
            $this->command?->warn(
                'Holiday storage is disabled (HOLIDAY_SYNC_ENABLED is off); skipping SG school holiday seed.'
            );

            return;
        }

        $rows = [
            // Multi-day school vacations (inclusive ranges)
            ['name' => 'School Holidays (Term I–II break)',   'date_from' => '2026-03-14', 'date_to' => '2026-03-22'],
            ['name' => 'School Holidays (Mid-Year)',          'date_from' => '2026-05-30', 'date_to' => '2026-06-28'],
            ['name' => 'School Holidays (Term III–IV break)', 'date_from' => '2026-09-05', 'date_to' => '2026-09-13'],
            ['name' => 'School Holidays (Year-End)',          'date_from' => '2026-11-21', 'date_to' => '2026-12-31'],

            // Scheduled single-day school holidays (schools closed, not public holidays)
            ['name' => 'Youth Day (school holiday)',      'date_from' => '2026-07-06', 'date_to' => '2026-07-06'],
            ['name' => "Teachers' Day (school holiday)",  'date_from' => '2026-09-04', 'date_to' => '2026-09-04'],
            ['name' => "Children's Day (school holiday)", 'date_from' => '2026-10-02', 'date_to' => '2026-10-02'],
        ];

        foreach ($rows as $row) {
            Holiday::updateOrCreate(
                [
                    'date_from' => $row['date_from'],
                    'date_to'   => $row['date_to'],
                    'type'      => 'school',
                ],
                [
                    'name'   => $row['name'],
                    'source' => 'seeder',
                ]
            );
        }

        // Refresh the derived daily table so the newly seeded school-vacation
        // ranges are expanded into holiday_days for equality joins.
        app(HolidayDayRebuildService::class)->rebuild();
    }
}
