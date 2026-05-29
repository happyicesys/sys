# Mid-Month Contract Segmentation — Implementation Plan

Status: **DRAFT for review** · Target page: Customer Management → Summary (`Customer/Summary.vue`, `CustomerController::summary`)

## 1. Goal & agreed behaviour

When a customer's contract changes part-way through a month, that month should be reported as **two (or more) segments** instead of one blended row.

Example — contract changed on the 15th:

| Segment | Period | Contract used | Lockable | Invoice |
|---|---|---|---|---|
| 1 | 1st – 15th | old contract | yes | its own |
| 2 | 16th – month end (in-progress) | new contract | yes (once finished) | its own |

Agreed decisions:

- **Split is automatic** — the moment a mid-month contract change is recorded in `customer_contract_logs`, the month is segmented on the next aggregation. No manual "split" action.
- **Per-segment invoices** — each segment invoices independently on its own contract + figures (the invoice table is already keyed by `customer + period_start + period_end`).
- **"Merge back" button** — collapses a month's segments into a single row recomputed under the **latest** contract for the whole month. Always shows an **"Are you sure?" confirmation** first. If any segment of that month is **locked**, merge is **blocked** and the UI **prompts the user to unlock first**. Merge is **permanent** — no "re-split" undo.
- **Locking stays per-row** — a finished segment locks exactly like a completed month does today.
- **Trend arrows & the "New" badge compare at the MONTH-total level** — not segment-to-segment — so the period-over-period signal stays meaningful.
- **Accumulate Vending Earning shows the per-segment total** (each segment row carries its own running figure).
- **Forward-only patch** — segmentation applies from now on; past months stay single-row. A one-off full **re-seed of history** is supported to regenerate all months (it will only segment past months where the contract log actually recorded a mid-month change — pre-log history can't be reconstructed).
- **Performance is a hard requirement** — see §10.

## 2. Why this is a significant change (blast radius)

The whole summary pipeline currently assumes **one row per (customer, month)**:

- **DB unique index** `cps_customer_yearmonth_unique` on `(customer_id, year_month)` — physically prevents two rows for one month. Must change.
- **Aggregator** `CustomerSummaryAggregator::persistMonth` deletes & re-inserts rows **by `year_month`**, and aggregates sales/gross/fees across the **whole month**. Must become segment-aware and date-bounded.
- **Downstream readers** that group/sum by `year_month`: the accumulate-vending-earning prefix sum, the new prev-month trend comparison, the contract-change badge, page totals. Each must treat a month as possibly-many rows.
- **Invoices & locking** are per-row already, so they mostly carry over — but merge must reconcile them.

This is the "option 1 segmentation" the `customer_contract_logs` table was explicitly built to enable (see its migration note).

## 3. Data model changes

### 3.1 `customer_period_summaries`

- **Replace** unique index `(customer_id, year_month)` → **`(customer_id, period_start)`**. Each segment has a distinct `period_start` (e.g. 05-01 and 05-16), while `year_month` stays the month bucket (05-01) for grouping. *Migration must drop the old index and add the new one; verify no raw query relies on the old name.*
- **Add** `segment_index` (unsignedTinyInt, default 0) — 0 for a whole-month/first segment, 1, 2, … for later segments. Ordering & display aid.
- **Add** `contract_log_id` (nullable FK → `customer_contract_logs`) — which contract version this segment was computed from. Traceability + lets the UI show the source contract.
- **Add** `merge_locked` / better named `segmentation_overridden` (boolean, default false) on the rows of a month that the user has explicitly **merged**, so the aggregator keeps it as a single row instead of re-splitting on the next run. (See §5.)

### 3.2 New columns already discussed but now sourced correctly

The per-segment contract is read from `customer_contract_logs` (the immutable, effective-dated history) at aggregation time, so each segment row stores the contract that was truly in effect for its date range. (This supersedes the earlier reverted "snapshot live contract" approach.)

## 4. Aggregator redesign (`persistMonth`)

For each `(customer, month)`:

1. Pull the customer's contract versions from `customer_contract_logs` that overlap the month (`effective_from < monthEnd` AND (`effective_to` is null OR `effective_to > monthStart`)).
2. If the month is flagged **merged** (§5) **or** there is only one overlapping version → **one row** for the whole month, using the latest in-month contract (current behaviour).
3. Otherwise → derive **segment boundaries** from the change instants inside the month:
   - Segment k spans `[boundary_k, boundary_{k+1})`, clamped to the month and to `as_of` for the current month.
   - For each segment: aggregate `sales_cents` / `gross_earning_cents` / `transaction_count` / `vend_count` / `job_count` **restricted to that date range** (date-bounded queries against `vend_transactions` / `gp_metrics` / ops jobs), and compute `location_fees_cents` etc. using **that segment's contract version**.
   - Set `period_start` / `period_end` / `segment_index` / `contract_log_id` / `is_current_month` (only the last segment of the current month is in-progress).
4. **Preserve locked segments**: the existing "don't overwrite locked rows" rule extends to segments — delete & re-insert only unlocked, non-overridden rows for the month.

Edge cases to handle: change exactly on the 1st (no split — single version effective all month); multiple changes in one month (N segments); a change with `effective_from` after `as_of` for the current month (not yet effective — ignore until reached).

## 5. Merge action

- New controller endpoint (e.g. `POST /customers/summary/merge-month`) + a **"Merge back"** button on segmented months.
- Always confirm first: **"Are you sure you want to merge this month's segments into one row under the latest contract? This can't be undone."**
- Behaviour: if **any** segment of that `(customer, month)` is locked → **reject** and the Vue side shows a prompt: *"This month has locked segment(s). Unlock them first to merge."* Otherwise, delete the month's segment rows and recompute a single whole-month row under the **latest** contract, and set `segmentation_overridden = true` so the nightly aggregator keeps it merged.
- **No re-split** — merge is permanent for that month (the override flag stays set).

## 6. Downstream code to update

- **`attachAccumulatedVendingEarning`** — runs **per segment**: each segment row shows the running accumulate total through its own `period_end` (decided). Keep the single batched prefix-sum query; key by `period_start` ordering within customer.
- **`attachPreviousMonthSummary` / trend arrows** — compare at **month-total level** (decided): sum a month's segments, compare that month bucket against the previous month bucket. Arrows render against month totals, not individual segments.
- **`attachContractChangeFlags` (badge)** — compare at **month level** (decided): the badge flags when the month's contract differs from the previous month. (With segmentation the contract change is also visible structurally as the split itself.)
- **Per-period contract display** (the deferred piece) — folds in here: each segment row displays its own `contract_log_id` contract.
- **Page totals** — sum across all rows (segments included) — already does; verify no double counting.
- **Resource** — expose `segment_index`, `period_start/end`, source contract, and a `is_segment`/`segment_count` hint for the UI.

## 7. UI (`Customer/Summary.vue`)

- Render multiple rows for a segmented month, visually grouped (e.g. a subtle month band, segment date-range label like "May 1–15" / "May 16–31").
- Lock button per segment (existing component, per row).
- "Merge back" button shown on segmented months; on click, confirm; handle the locked-segment prompt.
- Trend arrows / accumulate column behave sensibly across segments (show on month totals or per segment — decide during build).

## 8. Invoices

Per-segment invoices work with the existing `(customer, period_start, period_end)` key — each segment has a distinct period, so `attachExistingInvoice` and the create-invoice flow operate per segment with no schema change. Verify the CMS invoice creation uses the segment's contract figures.

## 9. Phasing

- **Phase 1 — core**: schema migration (index swap + new columns), aggregator segmentation, Resource fields, basic UI rendering of segments + per-segment contract display. (No merge yet.)
- **Phase 2 — merge**: merge endpoint + button + locked-segment prompt + `segmentation_overridden` handling (and optional re-split).
- **Phase 3 — polish**: per-segment invoice verification, trend/accumulate behaviour across segments, Excel export segment rows, backfill of past months from the log (optional).

## 10. Risks & mitigations

- **Index/uniqueness change is the highest risk** — audit every `where('year_month', …)`/`updateOrCreate` and the unique constraint usage before migrating; do it behind a careful migration with `down()`.
- **Aggregator correctness** — date-bounded sums must exactly reconcile to the old whole-month totals when merged (add a validation check, mirroring `customer-summary:validate-sales`).
- **Lock/merge/invoice interactions** — define and test the matrix (locked segment + merge attempt; invoiced segment + merge).
- **Backfill** — past months stay single-row unless re-aggregated; historical contract history before the log existed is unavailable.

## 10b. Performance requirements (hard)

Segmentation must not regress the page. Rules to hold to:

- **Most months stay one row.** Segments are only created where the contract log actually has a mid-month change, so the row count barely grows in practice.
- **Keep every read batched — no N+1.** The contract log is fetched once per page (one query, grouped by `customer_id`), as are accumulate/invoice/prev-month attachments. The per-row work stays in-memory.
- **Index discipline.** New unique index `(customer_id, period_start)`; rely on `customer_contract_logs (customer_id, effective_from)` index for version lookups; date-bounded segment aggregation must hit existing indexed columns on `vend_transactions` / `gp_metrics` (transaction date, customer/vend keys) — no full scans.
- **Aggregation cost is bounded.** Date-bounded segment sums reuse the same indexed queries as the whole-month path, just with tighter date ranges; total work per customer-month is ~equal to today.
- **Validate after build** with the existing `customer-summary:validate-sales` style check and spot EXPLAINs on the new queries; benchmark the Summary page before/after (there are existing `benchmark:*` commands).

## 11. Resolved decisions

1. **Trend arrows / "New" badge → month-total level.** Compare month buckets, not segments.
2. **Accumulate Vending Earning → per segment** (each segment row shows its own running total).
3. **Merge is permanent** — no re-split. Merge always asks an **"Are you sure?"** confirmation, and is blocked (with an unlock prompt) while any segment is locked.
4. **Forward-only patch**, with support for a **one-off full history re-seed** (segments appear only where the contract log recorded mid-month changes).
5. **Performance is a hard requirement** (§10b).
