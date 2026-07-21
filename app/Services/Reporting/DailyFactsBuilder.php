<?php

namespace App\Services\Reporting;

use App\Models\VendTransaction;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Builds the nightly materialized report facts for a single closed day. Every
 * method is idempotent (delete-by-natural-key then insert) and backfillable for
 * an arbitrary past date.
 *
 * Settlement gate is the canonical VendTransaction::settledSql() so the "real
 * sale" definition is never re-hardcoded here. Sites/hours read raw
 * vend_transactions with a RANGE filter on the indexed transaction_datetime
 * (never a bare DATE(), which times out on that table); site-daily reads the
 * already-settled gp_metrics roll-up so its figures reconcile with the rest of
 * the platform.
 *
 * DB::table / raw SQL only — no Eloquent global scopes apply (instance-wide).
 */
class DailyFactsBuilder
{
    /**
     * fact_sales_hourly for one day: settled sales bucketed by hour × site.
     * Success predicate matches the platform headline (error code IN (0,6) /
     * NULL / is_multiple), so SUM over hours ties to gp_metrics amount.
     */
    public function buildSalesHourly(Carbon $day): void
    {
        $date = $day->toDateString();
        $start = $day->copy()->startOfDay()->toDateTimeString();
        $next = $day->copy()->addDay()->startOfDay()->toDateTimeString();

        $settled = VendTransaction::settledSql('vt');           // "vt.settlement_status = 2"
        $success = '(e.code IN (0, 6) OR e.code IS NULL OR vt.is_multiple = 1)';

        DB::table('fact_sales_hourly')->where('date', $date)->delete();

        $sql = <<<SQL
            INSERT INTO fact_sales_hourly
                (date, hour, customer_id, location_type_id, cohort,
                 txns, success_txns, success_qty, sales_cents, computed_at)
            SELECT
                DATE(vt.transaction_datetime)                                   AS date,
                HOUR(vt.transaction_datetime)                                   AS hour,
                vt.customer_id                                                  AS customer_id,
                MAX(COALESCE(vt.location_type_id, c.location_type_id))          AS location_type_id,
                MAX(dsc.cohort)                                                 AS cohort,
                COUNT(*)                                                        AS txns,
                SUM(CASE WHEN {$success} THEN 1 ELSE 0 END)                     AS success_txns,
                SUM(CASE WHEN {$success} THEN COALESCE(vt.success_qty, vt.qty, 1) ELSE 0 END) AS success_qty,
                SUM(CASE WHEN {$success} THEN vt.amount ELSE 0 END)             AS sales_cents,
                NOW()                                                           AS computed_at
            FROM vend_transactions vt
            LEFT JOIN vend_channel_errors e ON e.id = vt.vend_channel_error_id
            LEFT JOIN customers c            ON c.id = vt.customer_id
            LEFT JOIN dim_site_cohort dsc    ON dsc.customer_id = vt.customer_id
            WHERE {$settled}
              AND vt.amount > 0
              AND vt.customer_id IS NOT NULL
              AND vt.transaction_datetime >= ?
              AND vt.transaction_datetime < ?
            GROUP BY DATE(vt.transaction_datetime), HOUR(vt.transaction_datetime), vt.customer_id
        SQL;

        DB::insert($sql, [$start, $next]);
    }

    /**
     * fact_site_daily for one day: per-site daily roll-up from gp_metrics with
     * the same-day-of-week-previous-week comparison pre-computed.
     */
    public function buildSiteDaily(Carbon $day): void
    {
        $date = $day->toDateString();
        $prev = $day->copy()->subDays(7)->toDateString();

        DB::table('fact_site_daily')->where('date', $date)->delete();

        $sql = <<<SQL
            INSERT INTO fact_site_daily
                (date, customer_id, location_type_id, cohort, day_type_bucket,
                 sales_cents, gp_cents, txns, success_qty,
                 sales_same_dow_prev_wk, delta_abs, delta_pct, computed_at)
            SELECT
                t.d                                                             AS date,
                t.customer_id                                                   AS customer_id,
                t.location_type_id                                              AS location_type_id,
                dsc.cohort                                                      AS cohort,
                cal.day_type_bucket                                             AS day_type_bucket,
                t.sales_cents                                                   AS sales_cents,
                t.gp_cents                                                      AS gp_cents,
                t.txns                                                          AS txns,
                t.success_qty                                                   AS success_qty,
                COALESCE(p.sales_cents, 0)                                      AS sales_same_dow_prev_wk,
                t.sales_cents - COALESCE(p.sales_cents, 0)                      AS delta_abs,
                CASE WHEN COALESCE(p.sales_cents, 0) = 0 THEN NULL
                     ELSE ROUND(100 * (t.sales_cents - p.sales_cents) / p.sales_cents, 2)
                END                                                             AS delta_pct,
                NOW()                                                           AS computed_at
            FROM (
                SELECT
                    txn_date                        AS d,
                    customer_id,
                    MAX(customer_location_type_id)  AS location_type_id,
                    SUM(amount_cents)               AS sales_cents,
                    SUM(gross_profit_cents)         AS gp_cents,
                    SUM(transaction_count)          AS txns,
                    SUM(success_count)              AS success_qty
                FROM gp_metrics
                WHERE txn_date = ? AND customer_id IS NOT NULL
                GROUP BY txn_date, customer_id
            ) t
            LEFT JOIN (
                SELECT customer_id, SUM(amount_cents) AS sales_cents
                FROM gp_metrics
                WHERE txn_date = ? AND customer_id IS NOT NULL
                GROUP BY customer_id
            ) p              ON p.customer_id = t.customer_id
            LEFT JOIN dim_calendar cal    ON cal.date = t.d
            LEFT JOIN dim_site_cohort dsc ON dsc.customer_id = t.customer_id
        SQL;

        DB::insert($sql, [$date, $prev]);
    }

    /**
     * fact_rainfall_hourly for one day: hourly rainfall per station from the
     * 5-minute weather_readings. Range filter on the indexed observed_at.
     */
    public function buildRainfallHourly(Carbon $day): void
    {
        $date = $day->toDateString();
        $start = $day->copy()->startOfDay()->toDateTimeString();
        $next = $day->copy()->addDay()->startOfDay()->toDateTimeString();

        DB::table('fact_rainfall_hourly')->where('date', $date)->delete();

        $sql = <<<SQL
            INSERT INTO fact_rainfall_hourly
                (date, hour, weather_station_id, rainfall_mm, reading_count, computed_at)
            SELECT
                DATE(observed_at)        AS date,
                HOUR(observed_at)        AS hour,
                weather_station_id       AS weather_station_id,
                ROUND(SUM(value), 2)     AS rainfall_mm,
                COUNT(*)                 AS reading_count,
                NOW()                    AS computed_at
            FROM weather_readings
            WHERE observed_at >= ? AND observed_at < ?
            GROUP BY DATE(observed_at), HOUR(observed_at), weather_station_id
        SQL;

        DB::insert($sql, [$start, $next]);
    }

    /**
     * fact_daytype_record: full recompute of the all-time company daily-sales
     * record per day-type bucket, since config('calendar.record_floor'). Cheap
     * (a few hundred grouped days), so recomputed each run — never drifts.
     *
     * $upTo defaults to yesterday (today is still mutating).
     */
    public function refreshDayTypeRecords(?Carbon $upTo = null): int
    {
        $floor = Carbon::parse(config('calendar.record_floor', '2026-04-01'))->toDateString();
        $upTo = ($upTo ?: Carbon::today()->subDay())->toDateString();

        if ($floor > $upTo) {
            return 0;
        }

        // Company daily sales per (bucket, date). One grouped scan of gp_metrics.
        $rows = DB::table('gp_metrics as g')
            ->join('dim_calendar as cal', 'cal.date', '=', 'g.txn_date')
            ->whereBetween('g.txn_date', [$floor, $upTo])
            ->groupBy('cal.day_type_bucket', 'g.txn_date')
            ->select([
                'cal.day_type_bucket as bucket',
                'g.txn_date as d',
                DB::raw('SUM(g.amount_cents) as day_sales'),
            ])
            ->get();

        // Reduce to the max day per bucket.
        $best = [];
        foreach ($rows as $r) {
            $bucket = $r->bucket;
            if ($bucket === null) {
                continue;
            }
            $sales = (int) $r->day_sales;
            if (! isset($best[$bucket]) || $sales > $best[$bucket]['record_sales_cents']) {
                $best[$bucket] = [
                    'record_sales_cents' => $sales,
                    'record_date'        => Carbon::parse($r->d)->toDateString(),
                ];
            }
        }

        if (empty($best)) {
            return 0;
        }

        // driver_note = holiday_name of each record_date, if any.
        $recordDates = array_map(fn ($b) => $b['record_date'], $best);
        $holidayNames = DB::table('dim_calendar')
            ->whereIn('date', $recordDates)
            ->pluck('holiday_name', 'date');

        $now = Carbon::now();
        $payload = [];
        foreach ($best as $bucket => $b) {
            $payload[] = [
                'day_type_bucket'    => $bucket,
                'record_sales_cents' => $b['record_sales_cents'],
                'record_date'        => $b['record_date'],
                'driver_note'        => $holidayNames[$b['record_date']] ?? null,
                'computed_at'        => $now,
            ];
        }

        DB::table('fact_daytype_record')->upsert(
            $payload,
            ['day_type_bucket'],
            ['record_sales_cents', 'record_date', 'driver_note', 'computed_at']
        );

        return count($payload);
    }
}
