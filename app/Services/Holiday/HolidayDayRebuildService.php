<?php

namespace App\Services\Holiday;

use App\Models\Holiday;
use App\Models\HolidayDay;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Rebuilds the derived `holiday_days` table from the source `holidays` table.
 *
 * Every Holiday row (public single-days, school multi-day vacations, manual
 * entries) is expanded into one row per calendar date it covers, de-duplicated
 * so each date appears once with is_public / is_school flags OR'd across all
 * covering rows. The whole table is derived, so it is regenerated wholesale
 * inside a transaction — safe to run after any sync or seed.
 */
class HolidayDayRebuildService
{
    /** Ignore absurd ranges (e.g. a fat-fingered manual entry) so expansion can't run away. */
    protected int $maxRangeDays = 400;

    /**
     * @return array{dates:int}
     */
    public function rebuild(): array
    {
        $byDate = [];

        Holiday::query()
            ->select(['date_from', 'date_to', 'name', 'type'])
            ->orderBy('date_from')
            ->chunk(500, function ($holidays) use (&$byDate) {
                foreach ($holidays as $h) {
                    $this->accumulate($byDate, $h->date_from, $h->date_to, (string) $h->name, (string) $h->type);
                }
            });

        $now = now();
        $rows = [];
        foreach ($byDate as $date => $v) {
            $rows[] = [
                'date'       => $date,
                'is_public'  => $v['is_public'],
                'is_school'  => $v['is_school'],
                'name'       => $v['name'],
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        DB::transaction(function () use ($rows) {
            HolidayDay::query()->delete();
            foreach (array_chunk($rows, 500) as $chunk) {
                HolidayDay::insert($chunk);
            }
        });

        return ['dates' => count($rows)];
    }

    /**
     * Expand one holiday row's [from..to] range into $byDate, OR-ing flags and
     * choosing the best display name (public > school > first-seen).
     */
    protected function accumulate(array &$byDate, $from, $to, string $name, string $type): void
    {
        try {
            $start = Carbon::parse($from)->startOfDay();
            $end = Carbon::parse($to)->startOfDay();
        } catch (\Throwable $e) {
            return;
        }

        if ($end->lt($start)) {
            return;
        }
        if ($start->diffInDays($end) > $this->maxRangeDays) {
            return; // guard against runaway ranges
        }

        for ($d = $start->copy(); $d->lte($end); $d->addDay()) {
            $key = $d->toDateString();

            if (! isset($byDate[$key])) {
                $byDate[$key] = ['is_public' => false, 'is_school' => false, 'name' => null];
            }

            if ($type === 'public') {
                $byDate[$key]['is_public'] = true;
            }
            if ($type === 'school') {
                $byDate[$key]['is_school'] = true;
            }

            // Name precedence: a public label always wins; otherwise keep the
            // first non-empty name we saw for the date.
            if ($name !== '' && ($type === 'public' || $byDate[$key]['name'] === null)) {
                $byDate[$key]['name'] = $name;
            }
        }
    }
}
