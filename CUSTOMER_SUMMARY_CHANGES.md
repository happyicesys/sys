# Customer Summary — Pending Changes & Pre-Patch Audit

Status: **REVIEW** · Nothing has been migrated or `npm run build`-deployed yet.

## 0. Files touched

| File | Kind | Scope |
|---|---|---|
| `database/migrations/2026_05_28_010000_add_segmentation_to_customer_period_summaries.php` | **NEW** | Phase 1 segmentation foundation (DB) |
| `database/migrations/2026_05_28_020000_add_report_email_audit_to_customer_period_summaries.php` | **NEW** | Email audit columns |
| `app/Models/CustomerPeriodSummary.php` | edit | Fillable + casts + `reportEmailedBy()` relation |
| `app/Services/CustomerSummaryAggregator.php` | edit | Segment-aware `persistMonth` + 3 new helpers |
| `app/Http/Controllers/CustomerController.php` | edit | `attachPreviousMonthSummary`, `attachContractChangeFlags`, period-range, `sendPerformanceReport` (re-purposed), eager loads |
| `app/Http/Resources/CustomerPeriodSummaryResource.php` | edit | `prev_month`, `contract_diff`, `report_emailed_at`, `report_emailed_by_user` |
| `resources/js/Pages/Customer/Summary.vue` | edit | Layout, alignment, trend arrows, "New" badges, modal email button |
| `CONTRACT_SEGMENTATION_PLAN.md` | **NEW** | Design doc (already reviewed) |

## 1. UI-only changes (low risk, build-only)

1. **Header layout** — *"Customer Summary"* description sits below the title instead of beside it (single wrapper switched to `flex flex-col`).
2. **Contract badge colour** — clickable "Contract" attachment badge changed blue → green (`bg-green-100 / text-green-800 / border-green-300`).
3. **Trend arrows removed from Location Fees + Net Loc Fee** — the two `<TrendIcon>` instances dropped; unused wrapper functions `trendLocFees` / `trendNetLocFee` removed. `netLocFeeCents()` is still used by colour-class and value display.
4. **Numeric data cells right-aligned** (per your "all numeric cells" choice): Vend ID, Machine Prefix, Period Report YYMM, Period Start/End dates, # of Job, and (originally) Placement Contract Type. The multi-vend & multi-prefix flex columns switched `items-center` → `items-end`.
5. **Arrow-slot alignment** — `TrendIcon` now renders a same-size **invisible placeholder (`w-4`) when `dir` is null** so every value line reserves the slot. Two arrow-less lines (Sales incl-GST grey line, Vend Earning rate line) got a placeholder `<TrendIcon :dir="null" />` and `inline-flex items-center` wrappers.
6. **Gross Earning / Vend Earning cell right-align** — both inner `flex flex-col` switched to `flex flex-col items-end space-y-0.5`.
7. **Location Fees / External Subsidize / Net Loc Fee right-align** — outer `flex flex-col space-y-4` switched to `flex flex-col items-end space-y-4`.
8. **Placement Contract Type column reverted to centre** — cell + inner div changed back to `text-center` / `items-center` per your follow-up.
9. **Per-row Email button removed** from the Action column (moved into the Report Content modal — see §3).

**Validate**: visual sweep of the table at 100% / 75% / mobile widths; confirm columns line up and no clipping.

## 2. "New" badge for contract changes (log-based)

**Backend** — `CustomerController::attachContractChangeFlags()` (called from `summary()` after `attachPreviousMonthSummary`).

- For each visible row, resolves the contract version active at **end of this period** vs **end of the previous period** from `customer_contract_logs` (NOT from the snapshot row — the log is the immutable source of truth).
- Sets `$row->contract_diff = ['placement_type'=>bool, 'contract_until'=>bool, 'auto_renewal'=>bool, 'notice_period'=>bool]`.
- No baseline (no version active in the previous period) → all flags false.
- Comparison normalises: type/notice as string, commission values as `number_format(.., 2)`, contract_until as `Y-m-d`, auto_renewal as bool.

**Resource** — passthrough: `'contract_diff' => isset($this->contract_diff) ? $this->contract_diff : null`.

**Vue** — tiny amber pill `New` rendered when `row.contract_diff?.<key>` is true, next to: Placement Contract Type label, Contract End Date, Auto Renewal icon, Notice Period. Tooltip: *"Changed from the previous period"*.

**Validate**: pick a customer with a known contract change (`customer_contract_logs`), confirm the badge fires only on rows whose period boundary spans the change. Confirm no badge on first-ever-contract rows.

## 3. Per-row trend arrows for the current period (`prev_month` fallback)

**Backend** — `CustomerController::attachPreviousMonthSummary()`.

- For **current-month rows only**, batches a single query for each customer's previous-month `customer_period_summaries` row and re-derives the live location fee / external subsidy / vend earning the same way the Resource does for unlocked rows (mirrors `attachAccumulatedVendingEarning`'s pattern). Attaches `$row->previous_month_summary = [...]`.
- Resource exposes it as `prev_month`.
- Vue's `prevMonthRow(row)` falls back to `row.prev_month` (merging `row.customer` so GST-dependent getters resolve) when there's no older row in the visible list.

**Validate**: in the default "Current" view (single visible row per customer), trend arrows now appear next to Sales / Gross / Vend on rows where data existed last month.

## 4. Period range responsive to filters

`CustomerController::summary()` — the existing totals query gained `MIN(period_start) AS earliest_period_start`. The Inertia render uses that as `rangeStart` (fallback to the resolved `rangeStart` when the filter matches no rows). `rangeEnd` is unchanged (end of resolved month).

**Validate**: switch to "All" with no customer filter (rangeStart = floor); apply a narrow filter (e.g. only one operator) and confirm the displayed range collapses to that customer set's earliest `period_start`.

## 5. Email button — moved into Report Content modal, mailto-based

**Migration** `2026_05_28_020000_…` — adds `report_emailed_at` (nullable timestamp) and `report_emailed_by` (nullable FK → `users`, nullOnDelete) to `customer_period_summaries`, after `locked_by`.

**Model** — fillable + datetime cast + `reportEmailedBy()` belongsTo `User`.

**Controller** `sendPerformanceReport()` — **repurposed**. No longer pretends to dispatch mail. Validates `period_start`/`period_end`, requires customer opt-in + email, looks up the matching summary row, **refuses unless `locked_at` is set**, then `forceFill(['report_emailed_at' => now(), 'report_emailed_by' => auth()->id()])->save()`.

**Resource** — exposes `report_emailed_at` (toDateTimeString) and `report_emailed_by_user: {id, name}`. `summary()` eager-loads `reportEmailedBy:id,name`.

**Vue** —
- Row-level Email button removed.
- New section inside the Report Content modal, visible **only when `reportContentRow?.is_locked`**, showing either *"Last sent by X on YYYY-MM-DD HH:mm:ss"* or *"Not yet emailed for this period."*.
- `Email` button beside it (when customer has both opt-in + email); otherwise an italic *"Email not enabled on this customer"* hint.
- On click (`onModalEmailClicked`):
  1. Builds a plain-text body from `reportContent` — title banner, period label, days, machine ID/prefix, calculation lines (**skipping `formula_internal` admin-only lines**), total, footnote.
  2. Triggers `window.location.href = mailto:?subject=…&body=…`.
  3. POSTs the audit endpoint via `router.post`. Optimistic update of `reportContentRow.report_emailed_at` + `report_emailed_by_user`; rolled back on error.

**Validate**: lock a row, open Report Content, see the Email button + "Not yet emailed" line, click it, confirm your mail client opens with the expected body, and confirm the modal now shows *"Last sent by ..."* (and check the DB row stamped). Unlock the row and confirm the modal hides the section entirely.

## 6. Phase 1 — Mid-month contract segmentation (DATA FOUNDATION ONLY)

> **This is the high-risk change. Validate carefully on a DB copy before pushing.** Phase 1 UI / per-segment lock / merge / per-segment invoices are NOT yet wired (per agreed gating).

### 6.1 Migration `2026_05_28_010000_…`

- **Drops** unique index `cps_customer_yearmonth_unique` (one-row-per-month).
- **Drops** non-unique index `cps_customer_periodstart_idx`.
- **Adds** unique index `cps_customer_periodstart_unique` on `(customer_id, period_start)`.
- **Adds** columns: `segment_index` (TINYINT UNSIGNED, default 0), `contract_log_id` (FK → `customer_contract_logs`, nullable, nullOnDelete), `segmentation_overridden` (boolean, default false).
- `down()` tries to restore the old unique. **See concern #1 below.**

### 6.2 Aggregator `CustomerSummaryAggregator::persistMonth`

- Before the chunked customer loop:
  - Loads `$overriddenCustomerIds` (rows for this month with `segmentation_overridden = true`).
  - Loads `$inMonthChangeCustomerIds` from `customer_contract_logs` where `effective_from > monthStart` AND `effective_from <= periodEnd`.
  - `$segmentCandidateSet` = in-month changers, **minus** locked AND overridden.
  - Loads `$candidateVersions` (one batched query) — every version that overlaps the month for the candidates.
- Chunk closure `use` includes `$overriddenSet`.
- Payload is now **keyed by `customer_id`** (instead of appended) and carries the three new columns: `segment_index = 0`, `segmentation_overridden = isset(...)`, `contract_log_id = null`.
- After the loop, **expansion**: for each segment-candidate customer, `buildMonthSegments()` derives segment date ranges; if >1 segment, `buildSegmentPayloads()` returns per-segment payloads that **replace** the whole-month payload. Final `$payloads` is flattened into a list before the existing delete-and-reinsert.

### 6.3 New helper methods on `CustomerSummaryAggregator`

- `buildMonthSegments($versions, $monthStart, $periodEnd)` — sorted unique start-dates (month start + each in-month change date); each segment's end = next-start-minus-1-day, or `periodEnd` for the last segment. Returns `['start' => Carbon, 'end' => Carbon, 'version' => stdClass|null]`.
- `versionActiveAt($versions, $moment)` — last version whose `[effective_from, effective_to)` covers `$moment`.
- `buildSegmentPayloads($base, $segments, $monthStart, $isCurrentMonth, $asOf, $now, $operatorGstRates, $testingVendIds)` — per-segment **date-bounded** queries: `gp_metrics` (`txn_date` between segStart and segEnd), `vend_transactions` (same filter as the whole-month query, restricted to segment datetime window, SETTLED only), `ops_job_items` (`ops_jobs.date` in window). Fees computed from the segment's own contract version. `is_current_month` set only on the LAST segment of the current month. Falls back to the base whole-month payload if every segment ends up empty (defensive).

### 6.4 Model `CustomerPeriodSummary`

Added to fillable: `segment_index`, `segmentation_overridden`, `contract_log_id`. Added casts: `segment_index => 'integer'`, `segmentation_overridden => 'boolean'`. (No relationship added for `contractLog` yet — not needed before the Resource exposes it.)

## 7. ⚠️ Bugs & logic concerns I found while auditing

Severity: **H** = will bite during validation, **M** = will bite as feature builds out, **L** = note only.

### H1. `migrate:rollback` on a populated, post-segmentation DB will fail

Once any month has more than one segment row, `down()` tries to restore the unique `(customer_id, year_month)` — which will error on duplicates. Rollback is effectively one-way after segmentation runs.
**Mitigation**: don't roll back after the first re-aggregation. If you must, manually `DELETE` extra segment rows first. Worth documenting in the migration's `down()` comment if you accept the trade-off.

### H2. mailto and the Inertia audit POST race

In `onModalEmailClicked`, `window.location.href = mailto:...` fires **before** `router.post(...)`. If a browser/OS treats mailto as a navigation (rare on desktop, more common on mobile), the audit POST is cancelled mid-flight and the row never gets stamped.
**Fix**: swap to `axios.post(...)` for the audit (it isn't affected by Inertia state) and either fire it before `window.location.href`, or wrap mailto in a microtask (`setTimeout(..., 0)`). Easy 5-line fix; I'd recommend doing this before patching.

### H3. Downstream readers don't yet know about segments

When a month gets two segment rows:
- **`attachPreviousMonthSummary`** keys prev-month rows by `customer_id . '|' . year_month`. Two prev-month segments share `year_month` → keyBy collides → only one of them is the "previous" fallback → trend arrows on the current month will compare against an arbitrary segment.
- **`attachAccumulatedVendingEarning`** is keyed by `(customer_id, year_month)`. Both segments in a month would see the same prefix-sum total, not per-segment.
- **Frontend `monthRowsByCustomer`** groups visible rows by `customer_id` and orders by `year_month` — two segments in one month sort by insertion order, not by `period_start`. Trend arrows between them may be wrong.

These are *expected* and covered by the pending Phase 1 P1: Resource/readers task (#14), so they shouldn't surprise you — but they DO mean: **don't ship Phase 1 to prod with just the migration + aggregator**. The interim state on prod would mis-render trend arrows / accumulate for any month that gets segmented. Validate on the DB copy and keep Phase 1 off prod until the readers/UI land.

### M1. `customer_contract_logs.effective_from` range scan in `persistMonth`

The new `inMonthChangeCustomerIds` query is `WHERE effective_from > X AND effective_from <= Y` with no `customer_id` filter. The existing indexes are `(customer_id, effective_from)` and `(customer_id, effective_to)` — neither's leading column is `effective_from`, so MySQL will likely full-scan `customer_contract_logs` per `persistMonth` call.

Today the table is small (one row per contract change), so it's cheap. As it grows, it becomes a hidden cost on every aggregation.
**Mitigation**: add a single-column index on `effective_from` in the Phase 1 segmentation migration before patching. One-line add.

### M2. `whereDate('period_start', ...)` in `sendPerformanceReport` does not use the new unique index efficiently

`whereDate(...)` wraps the column in `DATE(...)` so MySQL can't use the index. The new `(customer_id, period_start)` unique would otherwise be a direct lookup. Easy fix: `where('period_start', $request->period_start)` instead of `whereDate(...)`. Same for `period_end`. Functionally identical (the column is already DATE), but index-friendly. Recommended before patching.

### M3. `auth.user` may be undefined in the optimistic update

The optimistic stamp uses `usePage().props.auth?.user?.id / .name`. The rest of the file reads `auth.operator`, `auth.permissions`, `auth.roles` — `auth.user` may not be shared by your `HandleInertiaRequests`. If undefined, the template falls back to `'someone'` for the name, which the next Inertia re-render replaces with the real name. **Not a correctness bug** (audit row still records the real user_id via `auth()->id()` server-side), just an aesthetic blink. If `usePage().props.auth.user.name` is genuinely missing, consider stamping from `authOperator` or just leaving the optimistic update off.

### M4. Empty-segment skip + reconciliation expectation

In `buildSegmentPayloads`, a segment is skipped if `sales = fee = txn = job = 0`. Combined with **fees being computed from each segment's own contract version**, the right reconciliation rule is:
- `sum(segment.sales_cents) == whole_month.sales_cents` ✓ (always; sales are partitioned by date)
- `sum(segment.fees) ≠ whole_month.fees` (by design; different contracts apply)

Use **sales only** as the reconciliation hook (`customer-summary:validate-sales`); don't expect fees to reconcile to the legacy single-row figure. Worth noting in the validation step.

### L1. Comparison vs displayed value mismatch (already discussed)

The "New" badge and per-segment data are log-derived; the page still displays *live* customer values for End Date / Auto Renewal / Notice Period and the unlocked Placement Type. On historical rows the badge can correctly say "changed" next to a value that's the current live one. Already accepted; resolved when the per-period contract display lands in P1 readers.

### L2. Aggregator delete still per-customer-month

The current locked-row preservation is whole-customer-month. Locking 1st–15th will stop the 16th–end segment from refreshing too. This is intentional Phase-1 safety (no double-counting risk) and is fully addressed by task #19 *True per-segment locking*. Confirmed during the plan review.

### L3. Migration order

Both new migrations dated `2026_05_28_…`; the segmentation one is `010000` and the email-audit one is `020000`, so they run in the right order regardless. ✓

## 8. Manual validation checklist (run in this order)

> Run everything on a **DB copy**, not prod.

### Pre-patch (recommended quick fixes before migrating)
- [ ] **M1** — add a single-column index on `customer_contract_logs.effective_from` in the segmentation migration.
- [ ] **M2** — change `whereDate(...)` → `where(...)` in `sendPerformanceReport`.
- [ ] **H2** — swap the audit `router.post` for `axios.post` and fire before `window.location.href = mailto:`.

### UI changes (no migration needed yet)
- [ ] Header: description sits under "Customer Summary" title.
- [ ] Contract badge: green.
- [ ] Numeric columns right-aligned: Vend ID, Prefix, Period (YYMM), Period dates, # of Job. Placement Contract Type stays centred.
- [ ] No arrows on Location Fees / Net Loc Fee. Other arrows correctly line up to the column right edge (placeholder slot working).
- [ ] Gross Earning, Vend Earning, Location Fees cells right-align their stacked lines.

### Migration + Resource + reader changes
- [ ] `php artisan migrate` — both new migrations succeed.
- [ ] **Period range** — apply a narrow filter on "All" and confirm the start collapses to the earliest filtered `period_start`.
- [ ] **Trend arrows on current view** — pick a customer with both this month and last month aggregated; the current row shows arrows.
- [ ] **"New" badge** — find a customer with a real contract change in the log; confirm the badge appears only on the row whose period boundary contains the change.
- [ ] **Email audit** — lock a row, open Report Content, click Email; mail client opens with the expected body. Modal updates to "Last sent by …". DB row has `report_emailed_at` + `report_emailed_by`. Unlock the row and confirm the section hides.

### Segmentation foundation (the load-bearing part)
- [ ] Pick a customer with a known mid-month contract change date. `php artisan customer-summary:compute --month=YYYY-MM --sync`.
- [ ] The customer's month is now **two rows** with `segment_index` 0 and 1, distinct `period_start` and `period_end`, different `contract_log_id`.
- [ ] **Sales reconcile**: `sum(segment.sales_cents) == old single-row.sales_cents`. (Fees won't — that's expected.)
- [ ] A customer with **no** in-month change in that month → still a single row, identical to before.
- [ ] Current month re-compute: only the **last** segment has `is_current_month = true`.
- [ ] `SHOW CREATE TABLE customer_period_summaries` — new unique is `(customer_id, period_start)`; new columns present.
- [ ] **Don't** run `migrate:rollback` after segmenting (per H1).
- [ ] On the page, segmented customers will show two rows for that month with no segment labels yet — that's expected (UI work pending). Confirm trend arrows / accumulate may *look off* on those rows (H3) — that's also expected and gated.

## 9. After-validation work (still pending)

These are the tasks from the segmentation plan that will land **after** you sign off on the foundation:

- P1.5: True per-segment locking (task #19) — needed for "lock 1–15 while 16–end keeps running".
- P1: Resource segment fields + downstream reader updates (task #14) — fixes H3 on the page.
- P1: Vue segment rendering + per-period contract display (task #15) — visual grouping + correct historical display.
- P2: Merge endpoint + confirm-dialog + unlock-first prompt (task #16).
- P3: Per-segment invoices, Excel export, optional one-off full-history re-seed (task #17).
