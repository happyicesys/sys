# Performance Optimization — 2026-06-12

App-wide speed pass. **All code changes are output-identical** — same JSON, same rows, same ordering. No frontend changes (no `npm run build` needed).

---

## 1. Code changes applied (safe to deploy as-is)

### Every-request overhead (all pages)

| File | Change | Why |
|---|---|---|
| `app/Traits/GetUserTimezone.php` | Memoize per user per request | Called up to ~30× per resource row × 28 resources. Each call rebuilt an Eloquent sub-query builder (`has('operator')`) — pure CPU waste. On a 50-row Vend index ≈ 1,500 wasted builder constructions per page. |
| `app/Http/Middleware/HandleInertiaRequests.php` + `app/Models/Setting.php` | `Setting::singleton()` — cached settings row, auto-flushed on save/delete | `Setting::query()->first()` ran on EVERY request. Cache can never go stale: model `saved`/`deleted` events flush it (the 10-min refund scanner saves via Eloquent, so it flushes too). 10-min TTL backstop. |
| `app/Http/Middleware/HandleInertiaRequests.php` | Cache the Ziggy route map (~540 routes), keyed by route-file mtimes + request host | Was rebuilt on every request. Key changes automatically when any `routes/*.php` is touched (i.e. on deploy), so it can't serve stale routes. `location` stays per-request. |
| `app/Models/User.php` | `getRedirectRoute()` queries the first role once instead of twice | Same value, same null behavior; one query saved per login. |

### Query fixes

| File | Change | Why |
|---|---|---|
| `ProductController.php`, `ProductMovementController.php`, `OpsJobController.php` | `whereDate()` → plain `where()` on **DATE columns** (`ops_jobs.date`, `product_limits.date`) | `whereDate()` compiles to `DATE(col) op val`, which disqualifies indexes. On a DATE column, `where()` is byte-identical in result but sargable. (Hot paths in Dashboard/Customer Summary were already converted in earlier rounds — this finishes the job.) |
| `ProductMovementController.php` | `whereDate()` on `product_movements.created_at` (DATETIME) → equivalent half-open ranges (`>= day 00:00` / `< day+1`) | Same rows; becomes sargable once the index below is added. |
| `ProductMovementController.php` `incomingHistory()` | Batched `Operator::find` / `User::find` (was 2 queries per row = 40/page) into 2 `findMany` calls | Same models attached, same output. |

### Audit notes (no change needed)
- Vend index, Customer index/Summary, OpsJob index, Ops Performance: hot paths already batched + keyed-lookup optimized in prior rounds — verified, left untouched.
- All Resources checked: relations correctly guarded with `whenLoaded()` — no N+1 found there.
- `payment_gateway_logs`, `dispense_records`, `vend_transactions`, `customer_period_summaries`: already well-indexed for their query patterns.
- `app/Http/Controllers/OpsPerformanceController.php` has your uncommitted changes — **not touched**.

---

## 2. DB indexes — ✅ migration file added (applies on next `php artisan migrate`)

### 2a. Clear gap (covered by migration): `product_movements`
`database/migrations/2026_06_12_000000_add_perf_indexes_to_product_movements_table.php` adds the two indexes below. It is **idempotent** (checks `SHOW INDEX` first), so it's also safe if you already ran the SQL manually. ADD INDEX is online DDL on MySQL 8 — still best run at low traffic.

```sql
-- What the migration executes (only if missing):
ALTER TABLE product_movements
  ADD INDEX idx_pm_operator_created (operator_id, created_at),
  ADD INDEX idx_pm_batch_number (batch_number);
```

### 2b. Optional — verify with EXPLAIN first
The GP-metrics queries filter `vend_transactions` by `operator_id + transaction_datetime + settlement_status`. The existing `idx_operator_datetime` probably covers it well (settlement_status is low-selectivity), so only add this if EXPLAIN shows a gain:

```sql
-- Check current plan first, e.g.:
-- EXPLAIN SELECT … FROM vend_transactions
--   WHERE operator_id = ? AND settlement_status = 1
--   AND transaction_datetime BETWEEN ? AND ?;
ALTER TABLE vend_transactions
  ADD INDEX idx_vtrans_op_settle_dt (operator_id, settlement_status, transaction_datetime);
```

### How to verify after applying
```sql
SHOW INDEX FROM product_movements;
EXPLAIN <the slow query>;  -- should show the new key in `key` column
```

---

## 3. Cache notes
- Cache driver is `file`. New cache keys: `setting_singleton_row` (auto-flushed on Setting save) and `ziggy_routes_<hash>` (auto-rotated on deploy).
- After deploying, no cache clearing is required, but `php artisan cache:clear` is harmless if you want a clean slate.
