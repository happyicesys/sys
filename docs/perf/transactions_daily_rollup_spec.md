# Transactions-page daily rollup — design spec

**Status:** proposed (not built). Retires 3 recurring slow queries on the vend-transactions
index page, with **no write-path compromise** on `vend_transactions`.

## Problem

The transactions index page computes three aggregates **live** over a full day of
fleet-wide `vend_transactions` on every load:

1. **Totals** (`VendController` ~`getTransactionTotals`) — ~20 conditional
   `COUNT`/`SUM` columns (success/cash/cashless/QR/delivery counts + amounts, qty,
   rates). 0.9–2.4s.
2. **Pagination COUNT(*)** of the day. ~0.4s.
3. **Unreported gateway sum** — `SUM(payment_gateway_logs.amount)` for
   dispensed-but-unreported PayNow/QR. 0.24–0.55s.

Each is individually well-indexed; each is slow only because it scans/counts the
day's volume live. The only per-query fix is a wide covering index on
`vend_transactions` — **rejected**, because that's the hottest insert path and
`settlement_status` churns through pending→settled.

## Solution: per-operator-per-day summary table, read live-blended

Precompute yesterday-and-older days into a summary; compute **only today** live
(today is small and still changing). Page reads = `summary rows (past) + 1 live
today query`.

### 1. Schema — `vend_transaction_daily_summaries`

```
id
operator_id           (indexed)
date                  (DATE)         -- transaction_datetime::date
-- totals block (mirror the live CASE definitions exactly; store amounts in cents as-is)
total_transaction_count        BIGINT
success_count                  BIGINT
success_amount_cents           BIGINT
cash_amount_cents              BIGINT
cash_count                     BIGINT
cashless_terminal_amount_cents BIGINT
cashless_terminal_count        BIGINT
qr_payment_amount_cents        BIGINT
qr_payment_count               BIGINT
delivery_platform_success_count      BIGINT
delivery_platform_success_amount_cents BIGINT
single_qty                     BIGINT
success_single_qty             BIGINT
multiple_count_delivery_platform BIGINT
multiple_count_machine         BIGINT
-- unreported gateway
unreported_gateway_amount_cents BIGINT
computed_at            TIMESTAMP
UNIQUE (operator_id, date)
```

Rates (success_payment_rate, success_count_rate) are **derived on read** from the
stored counts — never stored (avoids rounding drift).

**Testing-vend exclusion & vend_id NOT IN:** the live queries exclude a testing-vend
id list. The rollup must exclude the SAME set. Store rows already excluding testing
vends (so the summary == "billable" totals). If the page ever needs *with* testing,
that's a separate column set — out of scope; current page always excludes.

**Filters that DON'T fold into a daily rollup:** the live queries also accept
optional `codes` / `customer` / date-subrange filters. The rollup only serves the
**default** (no code/customer filter, whole-day) path — which is the common, slow
one. When those filters are present, fall back to the live query (already fast
because the filter is selective). Gate: use rollup only when
`!$request->codes && !$request->customer` and the range is whole-days.

### 2. Maintenance

Reuse the existing nightly reconcile cadence (same place `vend_records`/`gp_metrics`
are tallied). Add a `transactions:rollup-daily` step:

- For each (operator_id, date) in a moving window (default last 14 days, to catch
  late settlements — same rationale as the sales-rollup reconcile), recompute the
  row with `INSERT ... ON DUPLICATE KEY UPDATE`, running the **exact same** CASE
  expressions as the live query (single grouped scan per day, `GROUP BY operator_id,
  DATE(transaction_datetime)`).
- `settlement_status = 2` only (settled). Late settlements are why the 14-day
  re-window matters — a day isn't "final" until its stragglers settle.
- Do NOT rollup **today** (it's still mutating); today is always live.

Idempotent, self-healing: re-running recomputes from `vend_transactions` (source of
truth), so any drift heals on the next window pass.

### 3. Read path

```
if (rollup eligible: no code/customer filter, whole-day range) {
    pastRows  = summary WHERE operator_id IN (...) AND date BETWEEN range_start AND yesterday
    todayRow  = <existing live totals query> for [today 00:00 .. now]   // only if range includes today
    result    = sum(pastRows) + todayRow                                 // add counts/amounts, then derive rates
} else {
    result = <existing live query>   // unchanged fallback
}
```

Same for the pagination COUNT (sum `total_transaction_count`) and the unreported
sum (sum `unreported_gateway_amount_cents`).

### 4. Verification (must pass before cutover)

Diff harness command `transactions:rollup-verify --from --to`:

- For each day in range, run **old live query** and **rollup-sum** for the same
  operators/testing-exclusion, assert every field identical (counts exact; amounts
  exact; rates equal to 2dp).
- Run across ≥60 days incl. days with refunds, late settlements, delivery orders,
  and multi-item transactions.
- Cutover only when the diff is empty. Keep the live fallback wired so a mismatch
  is recoverable by flipping a config flag (`ENABLE_TRANSACTIONS_ROLLUP`).

### 5. Rollout

1. Migration: create table (empty).
2. Ship rollup command + backfill (`--from` a chosen floor date, e.g. reporting floor).
3. Run verify harness on staging; fix any CASE mismatch.
4. Ship read-path behind `ENABLE_TRANSACTIONS_ROLLUP=false`.
5. Flip flag on after staging diff is clean; monitor.

## Non-goals / notes
- No change to `vend_transactions` schema or indexes → zero write-path impact.
- Backfill and nightly rollup are batch reads, not on any hot path.
- The CustomerIndex snapshot table is a **separate** build (different columns,
  different page) — same pattern, don't conflate.
```
