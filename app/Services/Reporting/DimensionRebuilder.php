<?php

namespace App\Services\Reporting;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Rebuilds the two logical dimensions the report facts hang off:
 *
 *   dim_calendar     one row per date  (day-type bucket + school/holiday flags)
 *   dim_site_cohort  one row per site  (cohort + nearest rainfall station)
 *
 * Both are fully derived and cheap (a few thousand / a few hundred rows), so
 * each is rebuilt wholesale every night before the facts are built. No Eloquent
 * models — DB::table only, so no global scopes apply (instance-wide by design,
 * matching GpMetricsAggregator / OpsMachineDailySnapshotBuilder).
 */
class DimensionRebuilder
{
    /**
     * Rebuild dim_calendar for [floor .. today + forward_days]. Idempotent
     * per-date upsert, so re-running only refreshes.
     */
    public function rebuildCalendar(?Carbon $from = null, ?Carbon $to = null): int
    {
        $from = ($from ?: Carbon::parse(config('calendar.calendar_floor', '2023-01-01')))->copy()->startOfDay();
        $to = ($to ?: Carbon::today()->addDays((int) config('calendar.calendar_forward_days', 200)))->copy()->startOfDay();

        // Holiday lookup: date => [is_public, is_school, name].
        $holidays = DB::table('holiday_days')
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->get()
            ->keyBy(fn ($r) => Carbon::parse($r->date)->toDateString());

        // Set of public-holiday dates, for "PH eve" detection.
        $publicDates = [];
        foreach ($holidays as $key => $h) {
            if ((int) $h->is_public === 1) {
                $publicDates[$key] = true;
            }
        }

        $terms = $this->normalizeTermRanges((array) config('calendar.school_terms', []));

        $now = Carbon::now();
        $rows = [];
        $written = 0;
        $cursor = $from->copy();

        while ($cursor->lte($to)) {
            $key = $cursor->toDateString();
            $dow = (int) $cursor->dayOfWeek;                  // 0=Sun .. 6=Sat
            $isWeekend = ($dow === 0 || $dow === 6);
            $isFriday = ($dow === 5);

            $h = $holidays->get($key);
            $isPublic = $h ? ((int) $h->is_public === 1) : false;
            $isSchoolHol = $h ? ((int) $h->is_school === 1) : false;
            $holidayName = $h->name ?? null;

            $nextKey = $cursor->copy()->addDay()->toDateString();
            $isPublicEve = isset($publicDates[$nextKey]);

            // Bucket precedence: Weekend/PH beats Fri/PH-eve beats Weekday.
            if ($isWeekend || $isPublic) {
                $bucket = 'Weekend_or_PH';
            } elseif ($isFriday || $isPublicEve) {
                $bucket = 'Fri_or_PH_eve';
            } else {
                $bucket = 'Weekday';
            }

            $isTerm = $this->isInTerm($cursor, $terms);
            $isMadrasah = $isTerm && $isWeekend;

            $rows[] = [
                'date'               => $key,
                'dow'                => $dow,
                'day_type_bucket'    => $bucket,
                'is_public'          => $isPublic,
                'is_school'          => $isSchoolHol,
                'is_school_term'     => $isTerm,
                'is_madrasah_active' => $isMadrasah,
                'holiday_name'       => $holidayName,
                'computed_at'        => $now,
            ];

            if (count($rows) >= 500) {
                $this->upsertCalendar($rows);
                $written += count($rows);
                $rows = [];
            }

            $cursor->addDay();
        }

        if (! empty($rows)) {
            $this->upsertCalendar($rows);
            $written += count($rows);
        }

        return $written;
    }

    private function upsertCalendar(array $rows): void
    {
        DB::table('dim_calendar')->upsert(
            $rows,
            ['date'],
            ['dow', 'day_type_bucket', 'is_public', 'is_school', 'is_school_term', 'is_madrasah_active', 'holiday_name', 'computed_at']
        );
    }

    /**
     * Normalize config term ranges into [[CarbonStart, CarbonEnd], ...].
     */
    private function normalizeTermRanges(array $byYear): array
    {
        $ranges = [];
        foreach ($byYear as $year => $termList) {
            foreach ((array) $termList as $pair) {
                if (! is_array($pair) || count($pair) < 2) {
                    continue;
                }
                $ranges[] = [
                    Carbon::parse($pair[0])->startOfDay(),
                    Carbon::parse($pair[1])->endOfDay(),
                ];
            }
        }

        return $ranges;
    }

    private function isInTerm(Carbon $day, array $ranges): bool
    {
        foreach ($ranges as [$start, $end]) {
            if ($day->betweenIncluded($start, $end)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Rebuild dim_site_cohort for every active site. Full replace inside a
     * transaction (only a few hundred rows), so deactivated sites drop out.
     */
    public function rebuildSiteCohorts(): int
    {
        $stations = DB::table('weather_stations')
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->get(['id', 'latitude', 'longitude'])
            ->all();

        // One primary (type=1) geocoded address per active customer, plus its
        // location-type name. groupBy id keeps a single row per site.
        $sites = DB::table('customers as c')
            ->leftJoin('location_types as lt', 'lt.id', '=', 'c.location_type_id')
            ->leftJoin('addresses as a', function ($join) {
                $join->on('a.modelable_id', '=', 'c.id')
                    ->where('a.modelable_type', '=', 'App\\Models\\Customer')
                    ->where('a.type', '=', 1);
            })
            ->where('c.is_active', 1)
            ->select([
                'c.id as customer_id',
                'c.name as name',
                'c.site_name as site_name',
                'c.location_type_id as location_type_id',
                'lt.name as location_type_name',
                DB::raw('MAX(a.latitude) as latitude'),
                DB::raw('MAX(a.longitude) as longitude'),
            ])
            ->groupBy('c.id', 'c.name', 'c.site_name', 'c.location_type_id', 'lt.name')
            ->get();

        $cohortCfg = (array) config('calendar.cohorts', []);
        $now = Carbon::now();

        $rows = [];
        foreach ($sites as $s) {
            $lat = $s->latitude !== null ? (float) $s->latitude : null;
            $lng = $s->longitude !== null ? (float) $s->longitude : null;

            [$stationId, $distanceKm] = $this->nearestStation($lat, $lng, $stations);

            $rows[] = [
                'customer_id'                => (int) $s->customer_id,
                'cohort'                     => $this->classifyCohort($s->name, $s->site_name, $s->location_type_name, $cohortCfg),
                'location_type_id'           => $s->location_type_id !== null ? (int) $s->location_type_id : null,
                'location_type_name'         => $s->location_type_name,
                'nearest_weather_station_id' => $stationId,
                'distance_km'                => $distanceKm,
                'latitude'                   => $lat,
                'longitude'                  => $lng,
                'computed_at'                => $now,
            ];
        }

        DB::transaction(function () use ($rows) {
            DB::table('dim_site_cohort')->delete();
            foreach (array_chunk($rows, 500) as $chunk) {
                DB::table('dim_site_cohort')->insert($chunk);
            }
        });

        return count($rows);
    }

    /**
     * Classify a site into a cohort. See config/calendar.php for precedence.
     */
    public function classifyCohort(?string $name, ?string $siteName, ?string $locationTypeName, array $cfg): string
    {
        $haystack = strtolower(trim(($name ?? '') . ' ' . ($siteName ?? '')));

        // 1. Mosque/madrasah name override (strongest signal).
        foreach ((array) ($cfg['mosque_name_patterns'] ?? []) as $token) {
            if ($token !== '' && str_contains($haystack, $token)) {
                return 'mosque_madrasah';
            }
        }

        // 2. Location-type mapping (primary signal).
        $ltKey = strtolower(trim((string) $locationTypeName));
        $ltMap = (array) ($cfg['location_type_cohort'] ?? []);
        if ($ltKey !== '' && isset($ltMap[$ltKey])) {
            return $ltMap[$ltKey];
        }

        // 3. Name-pattern fallback.
        foreach ((array) ($cfg['name_patterns'] ?? []) as $cohort => $tokens) {
            foreach ((array) $tokens as $token) {
                if ($token !== '' && str_contains($haystack, $token)) {
                    return $cohort;
                }
            }
        }

        // 4. Default.
        return (string) ($cfg['default_cohort'] ?? 'other');
    }

    /**
     * Nearest station by haversine. Returns [stationId|null, distanceKm|null].
     */
    private function nearestStation(?float $lat, ?float $lng, array $stations): array
    {
        if ($lat === null || $lng === null || empty($stations)) {
            return [null, null];
        }

        $bestId = null;
        $bestKm = null;
        foreach ($stations as $st) {
            $d = $this->haversineKm($lat, $lng, (float) $st->latitude, (float) $st->longitude);
            if ($bestKm === null || $d < $bestKm) {
                $bestKm = $d;
                $bestId = (int) $st->id;
            }
        }

        return [$bestId, $bestKm !== null ? round($bestKm, 3) : null];
    }

    private function haversineKm(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earth = 6371.0088; // km
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) ** 2;

        return $earth * 2 * asin(min(1.0, sqrt($a)));
    }
}
