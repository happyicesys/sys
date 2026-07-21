# mark1 data dictionary (read-only analytics)

A curated map of the tables most useful for sales analysis. This is a guide, not
the full schema — always confirm exact columns with `describe_table(<name>)`.

## Which page maps to which table (read this first)

The questions usually come from these UI pages. Use the matching table — and
mind the "source of truth" notes, because some tables are pre-aggregated for
speed while others are the raw truth.

| UI page | Primary table(s) | Notes |
|---|---|---|
| **Sales Transactions** | `vend_transactions` (+ `vend_transaction_items`, `payment_gateway_logs`) | Raw, most granular truth. Per-transaction. |
| **Performance Dashboard** | `gp_metrics` (daily pre-agg); `vend_transactions` for detail | `gp_metrics` is fast for trends by dimension. |
| **Ops Dashboard** | `vends`, `ops_jobs`, `ops_job_items` | Machine status + restock jobs. |
| **Ops Performance** | `ops_machine_daily_snapshots` (components/health); `gp_metrics` (financials) | Snapshots are frozen daily, forward-only. |
| **Sites** (Site mgmt / index) | `customers` (= sites), `vends` (= machines) | One `customers` row = one site/location. |
| **Site Summary** | `customer_period_summaries` | Monthly per-site financial truth, lock/paid aware. |
| **Daily sales-analysis report** (8:30am) | `fact_sales_hourly`, `fact_site_daily`, `fact_rainfall_hourly`, `fact_daytype_record`, `dim_calendar`, `dim_site_cohort` | Nightly pre-agg for the morning report — hourly grain, weekday-match, rainfall, records. See section at the bottom. |

**Source-of-truth rule of thumb**

- Money/sales/GP, granular → `vend_transactions` (truth) or `gp_metrics` (daily roll-up of the same, faster for dashboards).
- Monthly per-site financials (loc fee, vend earning, paid status) → `customer_period_summaries`.
- Machine ops / stock-in / active counts → `ops_machine_daily_snapshots`.
- **Avoid `vend_records` for financial accuracy** — it's a legacy daily rollup that
  drifts on late settlement/refund/reassignment and is never re-reconciled. Use
  `vend_transactions`/`gp_metrics`/`customer_period_summaries` instead. (`vend_records`
  is documented below only because the old machine page still reads it.)

## Money/cents recap across these tables

`*_cents` columns (`gp_metrics`, `customer_period_summaries`,
`ops_machine_daily_snapshots`, `customer_settlements`) are minor units → /100.
On `vend_transactions`/`vend_records`, `amount`/`revenue`/`gross_profit` are also
minor units (cents) → /100. `*_rate`/`*_margin` are ratios, not cents.

## Money & unit conventions

- `revenue`, `gross_profit`, and `amount` on `vend_transactions` are stored in
  **cents (minor units)** — divide by 100 for currency. Sister summary tables use
  `*_cents` columns the same way.
- `gross_profit_margin` is a ratio/percentage, not cents.
- App timezone = operator timezone (one mark1 instance per country), so
  `transaction_datetime` is already local — no TZ math needed.

## What counts as a real sale (important)

A `vend_transactions` row is a clean, countable sale when:

- `is_refunded = 0`
- `is_zero_amount = 0`
- `settlement_status = 2` (SETTLED). Values: `0` = pending (dispense outcome
  unknown), `1` = refunded/void (never a sale), `2` = settled (counts).
  Legacy/non-gateway rows default to `2`, so this filter is safe for old data.

Always apply these filters in sales aggregates unless you specifically want
refunds/failures.

## Core table: `vend_transactions`  (the "Sales Transactions" page)

One row per transaction. Key columns:

| column | meaning |
|---|---|
| `id` | PK |
| `transaction_datetime` | when the sale happened (local) |
| `amount` | charged amount, **cents** |
| `revenue` | recognised revenue, **cents** |
| `gross_profit` | GP, **cents** |
| `gross_profit_margin` | GP margin (ratio/%) |
| `qty`, `success_qty`, `dispensed_qty` | quantities requested / vended ok / dispensed |
| `is_refunded`, `is_zero_amount`, `is_payment_received` | flags |
| `settlement_status` | 0 pending / 1 refunded / 2 settled |
| `customer_id` | → `customers.id` (the **site**) |
| `vend_id` | → `vends.id` (the **machine**) |
| `product_id` | → `products.id` |
| `operator_id` | → `operators.id` |
| `vend_channel_id` | → `vend_channels.id` (the slot) |
| `payment_method_id` | → `payment_methods.id` (cash/cashless/PayNow…) |
| `payment_gateway_log_id` | → `payment_gateway_logs.id` (gateway-backed rows) |
| `location_type_id` | site location type |
| `vend_channel_error_id` | non-null ⇒ a vend error occurred |

## Line items: `vend_transaction_items`

One row per dispensed product within a transaction. Useful for product-mix.

`vend_transaction_id` → `vend_transactions.id`, plus `product_id`,
`unit_price_amount` (cents), `unit_cost` (cents), `vend_channel_code`,
`vend_channel_error_code`, `is_refunded`.

## Gateway payments: `payment_gateway_logs`  (PayNow / QR)

Cashless gateway attempts. `amount` (cents), `approved_at`, `status`,
`is_dispensed`, `method`, `vend_id`, `vend_code`, `order_id`, `ref_id`.
Some dispensed gateway payments never become a `vend_transaction` (unreported
revenue, only counted from a configured cutoff date). Join via
`payment_gateway_log_id` when reconciling.

## Dimensions you'll join to

- `customers` — sites. Name, status (Active/Removed), operator_id, location type,
  contract terms. (One row = one site/location.)
- `vends` — machines. `code` (machine ID), prefix, model, status, customer_id.
- `products` — catalogue. name, category, cost.
- `operators` — operating company / brand.
- `vend_channels` — physical slots, with configured `amount` (price).
- `payment_methods` — payment type lookup.

## `gp_metrics` — daily per-machine financial roll-up (dashboards)

One row per day × machine × product-dimension. The fast path for trend/ranking
questions. Key cols: `txn_date`, `operator_id`, `vend_id`, `customer_id`,
`product_id`, `category_id`, `category_group_id`, `vend_prefix_id`,
`vend_model_id`, `customer_location_type_id`, `transaction_location_type_id`,
`is_binded_customer`, and the measures: `sale_count`, `transaction_count`,
`success_count`, `error_count`, `error_count_4_5`, `error_count_no_4_5`,
`amount_cents`, `revenue_cents`, `gross_profit_cents`, `unit_cost_cents`.
Already excludes refunds/voids (it's a settled roll-up), so no need for the
`settlement_status` filter here — just `SUM(revenue_cents)/100`.

## `ops_machine_daily_snapshots` — daily per-machine ops snapshot (Ops Performance)

Frozen nightly, forward-only. One row per `snapshot_date` × `vend_id`. Cols:
`snapshot_date`, `vend_id`, `vend_code`, `operator_id`, `customer_id`,
`location_type_id`, `vend_prefix_id`, `vend_model_id`, `category_id`,
`is_active`, `is_testing`, `lcd_monitor_id`, `bill_stat`, `coin_stat`,
`card_terminal_id`, `firmware_ver`, `apk_ver`, `acb_rev`,
`l30d_vend_earning_cents` (trailing-30d vend earning), `stock_in_cents`.
Use for active-machine counts, stock-in value, component/firmware health by day.

## `customer_period_summaries` — monthly per-site financials (Site Summary)

One row per site × month (× contract segment). `year_month` is a DATE (first of
month); `period_start`/`period_end`/`as_of_date` bound it; `is_current_month`,
`segment_index`. Measures: `sales_cents`, `gross_earning_cents`,
`location_fees_cents`, `location_earning_cents` (= Net Loc Fee / Vend Earning),
`location_earning_rate`, `external_subsidize_cents`, `transaction_count`,
`vend_count`, `job_count`. State: `is_locked`/`locked_at`, `is_paid`/`paid_date`,
`is_waived`/`waived_remarks`. **Frozen contract terms** (use these for locked
rows, not live customer): `contract_commission_type`, `contract_commission_value`,
`contract_commission_value2`, `contract_ps_term`, `contract_selling_price_type`.

## `customers` — sites (78 cols; the important ones)

`id`, `code`, `name`, `site_name`, `operator_id`, `category_id`,
`location_type_id`, `status_id`, `zone_id`. Active/lifecycle: `is_active`,
`active_date`, `removed_date`, `begin_date`, `termination_date`. Contract:
`contract_commission_type`, `contract_commission_value(/2)`, `contract_ps_term`,
`contract_from`/`contract_until`, `selling_price_type`, `is_external_subsidize`,
`external_subsidize_amount`. `totals_json` = cached lifetime rollup (can drift —
prefer live sums). One site has many `vends`.

## `vends` — machines (96 cols; the important ones)

`id`, `code` (machine ID, int), `name`, `label_name`, `customer_id` (site),
`operator_id`, `vend_prefix_id`, `vend_model_id`, `location_type_id` (via
customer), `vend_contract_id`. Status: `is_active`, `is_disposed`, `is_sold`,
`is_testing` (exclude test machines from sales!), `is_online`, `is_mqtt`,
`last_vend_transaction_at`, `out_of_stock_sku_percent`, `balance_percent`,
`temp`. Filter `is_testing = 0 AND is_disposed = 0` for real fleet analysis.

## `customer_settlements` — payment/ledger lines (Payment History)

`reference_no` (SET-000123), `customer_id`, `operator_id`, `entry_date`,
`year_month`, `entry_type` (DR/CR direction), `amount_cents`, `item`, `remarks`,
`customer_period_summary_id`, `source`. SOA / who-owes-what.

## `ops_jobs` / `ops_job_items` — restock & service jobs (Ops Dashboard)

`ops_jobs`: `code`, `date`, `operator_id`, `status` (int lifecycle),
`stock_action_type`, `created_by`/`delivered_by`/`picked_by`. `ops_job_items`:
one per machine per job — `ops_job_id`, `vend_id`, `customer_id`, `status`,
`completed_at`, `cash_amount`, `cashless_amount`, `acc_total_amount`,
`acc_total_count`, `refillable_amount`, `refillable_count`. Completed jobs =
`status >= 3 AND status <> 99` (the convention used in ops snapshots).

## Dimensions (lookup tables)

`products` (`name`, `code`, `category_id`, `category_group_id`, `is_active`,
`operator_id`), `categories` (`name`, `category_group_id`, `classname`,
`type`), `operators` (`name`, `code`, `country_id`, `timezone`, `gst_vat_rate`),
`location_types` (`name`, `weightage`), `vend_channels` (slot: `vend_id`,
`product_id`, `code`, `amount` = configured price, `qty`, `capacity`,
`is_active`), `vend_models` (`name`), `vend_prefixes` (`name`, `operator_id`).

## `vend_records` — LEGACY daily machine rollup (avoid for $ accuracy)

`vend_id`, `customer_id`, `date`/`day`/`month`/`year`, `total_amount`,
`total_count`, `revenue`, `gross_profit`, `failure_count`, `error_count`,
`online_success_amount`. Drifts vs truth — see source-of-truth rule above.

## Handy starting queries

Daily settled sales (last 30 days), in currency:

```sql
SELECT DATE(transaction_datetime) AS d,
       COUNT(*) AS txns,
       SUM(revenue)/100 AS revenue,
       SUM(gross_profit)/100 AS gp
FROM vend_transactions
WHERE is_refunded = 0 AND is_zero_amount = 0 AND settlement_status = 2
  AND transaction_datetime >= NOW() - INTERVAL 30 DAY
GROUP BY DATE(transaction_datetime)
ORDER BY d;
```

Top products by revenue (last 90 days):

```sql
SELECT p.name, COUNT(*) AS units, SUM(vt.revenue)/100 AS revenue
FROM vend_transactions vt
JOIN products p ON p.id = vt.product_id
WHERE vt.is_refunded = 0 AND vt.is_zero_amount = 0 AND vt.settlement_status = 2
  AND vt.transaction_datetime >= NOW() - INTERVAL 90 DAY
GROUP BY p.id, p.name
ORDER BY revenue DESC
LIMIT 25;
```

Sites with the biggest sales drop (this month vs last month) — a starting point
for "where is sales slipping":

```sql
SELECT c.id, c.name,
       SUM(CASE WHEN vt.transaction_datetime >= DATE_FORMAT(NOW(),'%Y-%m-01')
                THEN vt.revenue END)/100 AS this_month,
       SUM(CASE WHEN vt.transaction_datetime >= DATE_FORMAT(NOW() - INTERVAL 1 MONTH,'%Y-%m-01')
                 AND vt.transaction_datetime <  DATE_FORMAT(NOW(),'%Y-%m-01')
                THEN vt.revenue END)/100 AS last_month
FROM vend_transactions vt
JOIN customers c ON c.id = vt.customer_id
WHERE vt.is_refunded = 0 AND vt.is_zero_amount = 0 AND vt.settlement_status = 2
  AND vt.transaction_datetime >= DATE_FORMAT(NOW() - INTERVAL 1 MONTH,'%Y-%m-01')
GROUP BY c.id, c.name
ORDER BY (COALESCE(last_month,0) - COALESCE(this_month,0)) DESC
LIMIT 25;
```

Dashboard-style revenue & error trend from the fast roll-up (`gp_metrics`):

```sql
SELECT txn_date,
       SUM(revenue_cents)/100      AS revenue,
       SUM(gross_profit_cents)/100 AS gp,
       SUM(sale_count)             AS sales,
       SUM(error_count)            AS errors
FROM gp_metrics
WHERE txn_date >= NOW() - INTERVAL 60 DAY
GROUP BY txn_date
ORDER BY txn_date;
```

Underperforming machines for a "boost sales" pass — low revenue + high error
rate over the last 30 days (exclude test/disposed machines):

```sql
SELECT v.code AS machine_id, c.name AS site,
       SUM(g.revenue_cents)/100 AS revenue,
       SUM(g.error_count) / NULLIF(SUM(g.transaction_count),0) AS error_rate
FROM gp_metrics g
JOIN vends v     ON v.id = g.vend_id
JOIN customers c ON c.id = g.customer_id
WHERE g.txn_date >= NOW() - INTERVAL 30 DAY
  AND v.is_testing = 0 AND v.is_disposed = 0
GROUP BY v.id, v.code, c.name
HAVING error_rate > 0.05
ORDER BY revenue ASC
LIMIT 25;
```

## Daily sales-analysis report facts (pre-aggregated nightly)

New (2026-07) fast tables built nightly by `report:build-daily-facts` for the
8:30am sales-analysis report — so the report is a few SELECTs, not a re-scrape.
Populated only when `reporting.daily_facts_enabled` is on; each fact is a
settled-sale roll-up using the **same "real sale" definition as `gp_metrics`**
(`settlement_status = 2` + non-zero amount), so **no `settlement_status` filter
is needed** when reading them. All `*_cents` are minor units → /100. Dates and
hours are already local (SGT). Every table is backfillable per day and rebuilt
idempotently.

Two logical dimensions + four fact tables:

### `dim_calendar` — one row per date
`date` (PK), `dow` (0=Sun .. 6=Sat), `day_type_bucket`
(`Weekday` | `Fri_or_PH_eve` | `Weekend_or_PH`), `is_public`,
`is_school` (school HOLIDAY/vacation), `is_school_term` (school IN SESSION,
from MOE term ranges), `is_madrasah_active` (school-term weekend),
`holiday_name`. Join any dated fact on `= date`.

### `dim_site_cohort` — one row per site (customer)
`customer_id` (PK) → `customers.id`, `cohort`
(`tourist_leisure` | `mosque_madrasah` | `weekday_routine` | `school_linked` |
`other`), `location_type_id`, `location_type_name`,
`nearest_weather_station_id` → `weather_stations.id`, `distance_km`,
`latitude`, `longitude`. Rebuilt nightly from ACTIVE sites; a site deactivated
since the last rebuild has no row here (so `cohort` reads NULL on the facts).

### `fact_sales_hourly` — grain: date × hour × site
`date`, `hour` (0-23), `customer_id`, `location_type_id`, `cohort`,
`txns` (settled), `success_txns`, `success_qty`, `sales_cents` (successful
amount). **The hourly grain `gp_metrics` never had** — powers every hourly
chart. Daily `SUM(sales_cents)` ties to `gp_metrics` amount within rounding.
Unique (date, hour, customer_id). Note: rows with a NULL `transaction_datetime`
(rare legacy) are not placed in an hour and are excluded.

### `fact_site_daily` — grain: date × site
`date`, `customer_id`, `location_type_id`, `cohort`, `day_type_bucket`,
`sales_cents`, `gp_cents`, `txns`, `success_qty`, plus the PRE-COMPUTED
weekday-over-weekday comparison: `sales_same_dow_prev_wk` (same site 7 days
earlier), `delta_abs`, `delta_pct` (NULL when prev = 0). Sourced from
`gp_metrics`, so it reconciles with the dashboards. Anomaly ranking is just a
filter/sort on `delta_pct` / `delta_abs` — no self-join. Unique (date, customer_id).

### `fact_rainfall_hourly` — grain: date × hour × station
`date`, `hour`, `weather_station_id` → `weather_stations.id`, `rainfall_mm`
(sum of the 5-min readings that hour), `reading_count`. Relate to sites via
`dim_site_cohort.nearest_weather_station_id`. Preserves rainfall history beyond
the ~12-day `weather_readings` retention. NOTE: only has data from the day this
feature went live onward — backfilling older dates yields nothing (the 5-min
source is already pruned).

### `fact_daytype_record` — 3 rows (one per day_type_bucket)
`day_type_bucket` (PK), `record_sales_cents` (all-time COMPANY daily record —
all machines, binded + unbinded — since the reporting floor, default
2026-04-01), `record_date`, `driver_note` (holiday name of the record day, if
any). Lets the report say "today is a record Weekend" in one lookup. NB: the
record is the all-machine company total, so compare it against
`SUM(gp_metrics.amount_cents)` for the day (not `fact_site_daily`, which is
binded-site only).

**Stays external (not stored):** the NEA next-day rainfall FORECAST is a live
outlook call at report time — these tables hold observed history only.
