# Daily sales-analysis report — query pack (read-only)

Copy/paste starting queries for the 8:30am sales-analysis report, run against the
mark1 read-only MCP (the `mark1_ro` SELECT-only user). They read the nightly
pre-aggregated facts (see the "Daily sales-analysis report facts" section of
`data_dictionary.md`), so each is a couple of fast SELECTs.

Conventions:
- Every query targets **yesterday** via `CURDATE() - INTERVAL 1 DAY` (the report
  runs the morning after the day closes; the 03:30 nightly build has already
  populated it). To pin a specific day, replace that expression with a literal
  `'2026-07-19'`.
- All money is cents → shown `/100`.
- These read pre-agg tables only; they require `report:build-daily-facts` to have
  run (or a manual backfill). The NEA next-day **forecast** is NOT here — that
  stays a live outlook call at report time.

---

## 1. Company headline for the day (+ calendar context)

```sql
SELECT
  cal.date,
  cal.day_type_bucket,
  cal.is_public,
  cal.is_school_term,
  cal.is_madrasah_active,
  cal.holiday_name,
  SUM(f.sales_cents)/100 AS sales,
  SUM(f.gp_cents)/100    AS gp,
  SUM(f.txns)            AS txns,
  SUM(f.success_qty)     AS units
FROM fact_site_daily f
JOIN dim_calendar cal ON cal.date = f.date
WHERE f.date = CURDATE() - INTERVAL 1 DAY
GROUP BY cal.date, cal.day_type_bucket, cal.is_public,
         cal.is_school_term, cal.is_madrasah_active, cal.holiday_name;
```

## 2. Hourly sales curve (company-wide)

```sql
SELECT hour,
       SUM(sales_cents)/100 AS sales,
       SUM(success_txns)    AS txns
FROM fact_sales_hourly
WHERE date = CURDATE() - INTERVAL 1 DAY
GROUP BY hour
ORDER BY hour;
```

## 3. Hourly sales curve by cohort

```sql
SELECT hour, cohort,
       SUM(sales_cents)/100 AS sales
FROM fact_sales_hourly
WHERE date = CURDATE() - INTERVAL 1 DAY
GROUP BY hour, cohort
ORDER BY hour, cohort;
```

## 4. Biggest weekday-over-weekday movers (anomaly ranking)

Pre-computed — just filter/sort. Drops first; flip to `DESC` for the biggest
jumps. `sales_same_dow_prev_wk > 0` skips brand-new sites with no comparison.

```sql
SELECT c.name AS site,
       f.cohort,
       f.day_type_bucket,
       f.sales_cents/100             AS sales,
       f.sales_same_dow_prev_wk/100  AS same_dow_prev_wk,
       f.delta_abs/100               AS delta,
       f.delta_pct
FROM fact_site_daily f
JOIN customers c ON c.id = f.customer_id
WHERE f.date = CURDATE() - INTERVAL 1 DAY
  AND f.sales_same_dow_prev_wk > 0
ORDER BY f.delta_pct ASC
LIMIT 20;
```

## 5. Cohort performance vs same day last week

```sql
SELECT cohort,
       SUM(sales_cents)/100                                   AS sales,
       SUM(sales_same_dow_prev_wk)/100                        AS prev_wk,
       (SUM(sales_cents) - SUM(sales_same_dow_prev_wk))/100   AS delta,
       ROUND(100 * (SUM(sales_cents) - SUM(sales_same_dow_prev_wk))
             / NULLIF(SUM(sales_same_dow_prev_wk),0), 1)      AS delta_pct
FROM fact_site_daily
WHERE date = CURDATE() - INTERVAL 1 DAY
GROUP BY cohort
ORDER BY sales DESC;
```

## 6. Rainfall vs sales by hour (nearest-station)

Joins each site's hourly sales to its nearest station's hourly rainfall, so you
can line up wet hours against demand.

```sql
SELECT s.hour,
       SUM(s.sales_cents)/100    AS sales,
       ROUND(AVG(r.rainfall_mm), 2) AS avg_rain_mm_nearest
FROM fact_sales_hourly s
JOIN dim_site_cohort dsc ON dsc.customer_id = s.customer_id
LEFT JOIN fact_rainfall_hourly r
       ON r.date = s.date
      AND r.hour = s.hour
      AND r.weather_station_id = dsc.nearest_weather_station_id
WHERE s.date = CURDATE() - INTERVAL 1 DAY
GROUP BY s.hour
ORDER BY s.hour;
```

Wettest sites the day before (top rainfall totals, mapped back to sites):

```sql
SELECT ws.name AS station,
       ROUND(SUM(r.rainfall_mm), 1) AS rain_mm,
       COUNT(DISTINCT dsc.customer_id) AS sites_near
FROM fact_rainfall_hourly r
JOIN weather_stations ws ON ws.id = r.weather_station_id
LEFT JOIN dim_site_cohort dsc ON dsc.nearest_weather_station_id = r.weather_station_id
WHERE r.date = CURDATE() - INTERVAL 1 DAY
GROUP BY ws.id, ws.name
HAVING rain_mm > 0
ORDER BY rain_mm DESC
LIMIT 15;
```

## 7. Day-type record check ("is today a record?")

The record is the all-machine **company** total, so today's figure comes from
`gp_metrics` (binded + unbinded) to match how the record is computed.

```sql
SELECT rec.day_type_bucket,
       rec.record_sales_cents/100 AS record_sales,
       rec.record_date,
       rec.driver_note,
       today.sales/100            AS todays_sales,
       (today.sales >= rec.record_sales_cents) AS is_new_record
FROM fact_daytype_record rec
JOIN dim_calendar cal
  ON cal.date = CURDATE() - INTERVAL 1 DAY
 AND cal.day_type_bucket = rec.day_type_bucket
JOIN (
  SELECT SUM(amount_cents) AS sales
  FROM gp_metrics
  WHERE txn_date = CURDATE() - INTERVAL 1 DAY
) today;
```

## 8. Top sites for the day (context / leaderboard)

```sql
SELECT c.name AS site, f.cohort, f.day_type_bucket,
       f.sales_cents/100 AS sales,
       f.gp_cents/100    AS gp,
       f.delta_pct
FROM fact_site_daily f
JOIN customers c ON c.id = f.customer_id
WHERE f.date = CURDATE() - INTERVAL 1 DAY
ORDER BY f.sales_cents DESC
LIMIT 20;
```
