# Stock Count job (spot check 盘点) in Ops Jobs — plan (2026-09-19)

> **Status: SHIPPED to main 2026-09-20 (push = deploy)** with
> `SERVICE_NOTICE_PLAN_2026-09-19.md` — same commits, same ship notes there.
> C1–C7 are done; phase 2 is not started.
>
> Brian's answers (2026-09-19) and how each was built:
> - *"a checkbox when check is random, redraw by supervisor and above"* →
>   `is_random` checkbox reveals "How many channels"; `redraw stock-checks` is
>   held by supervisor-and-above only (so are create / sync / delete / export).
>   Manual add/remove of a channel (§C2) was NOT built — re-draw covers it.
> - *"spot check can't validate what we have sold out … system showing 1 left,
>   driver found nothing, set 0"* → a channel the system shows as EMPTY is never
>   drawn (no toggle — §C2's "Skip empty channels" became a fixed rule in
>   `StockCheckSampler::eligible`); the Real Qty dropdown runs 0…capacity.
> - *"a button to sync with the spot checked result"* → **Sync**, §C5 reversed.
>   It applies the VARIANCE to `vend_channels.qty` (not the counted figure —
>   sales may have happened since), per machine kind:
>   - vending machine / smart freezer → `SystemQtySyncTarget`. mark1 has NO
>     frame that sets a VMC's channel qty, and the machine's next CHANNEL report
>     overwrites `vend_channels.qty`, so for a vending machine the correction
>     holds only if the machine is corrected on site too. The page says so
>     before the person confirms. A freezer sends no CHANNEL frame — it sticks.
>   - CityBox chiller → `CityboxSyncTarget` REFUSES: their poll overwrites us in
>     minutes, and their stocktake-submit overwrites device stock with unknown
>     behaviour for products left out of a partial payload.
>   Each channel is validated before (still exists, same product, row-locked)
>   and after (read back == expected, else rollback); before/after qty kept on
>   the row. A synced count can no longer be reopened or deleted.
> - *"driver should see the variance"* → yes, in pieces and money, on submit.
> - The standalone "Stock Count Variance" report (§C6) was built as the
>   **Stock Counts** list page + Excel export (one row per counted channel);
>   rollups by driver / site / product are not built.

## 1. The ask (Brian, 2026-09-19)

A **job-like row** inside an ops job. The office assigns it to a machine with a
**random sample of channels** — all channels or only N, N configurable,
optionally limited to certain products. The driver sees each sampled channel's
**current qty**, physically counts, and picks the **real qty from a numeric
dropdown**.

So: an audit spot check. Not blind, not a top-up, not every channel.

## 2. What exists (verified in code)

- No human count anywhere in mark1; drivers only key deltas. `onsite_adjustment`
  stores a delta, never the counted figure. No variance/shrinkage report.
- cms `transactions.stock_balance_count`: one total per machine, typed by the
  driver, **never read by anything**. Lesson: ship the report with the count.
- Name collision: tables `stock_counts` / `stock_count_items` and the reports
  "Daily Stock Count" / "Stock Count Dashboard" already exist (nightly
  machine-reported valuation snapshot). New code uses **`stock_checks`**. The
  UI label is Brian's call (§7.1).
- An `ops_job_item` always fans out **every** channel, allows only one open item
  per machine per job, and feeds cms sync, freeze, tally, pick list and ops
  performance. A sampled count fits none of that → **own tables**, a sibling row
  type like `ops_job_tasks` (and the planned `service_notices`).

```
ops_jobs
 ├─ ops_job_items      machine top-up stop        existing
 ├─ ops_job_tasks      non-machine stop           existing
 ├─ service_notices    repair stop                planned
 └─ stock_checks       count stop                 NEW
      └─ stock_check_channels
```

## 3. Data

**`stock_checks`**: `id, code (per-operator running no. → SC-10001),
operator_id, ops_job_id, vend_id, customer_id, sequence decimal(8,2),
status (1 Pending · 3 Completed · 99 Cancelled), sample_mode ('all'|'random'),
sample_size (nullable), product_filter json (nullable), remarks,
counted_at/by, undo_counted_at/by, cancelled_at/by, created_by, updated_by,
timestamps`.

**`stock_check_channels`**: `id, stock_check_id, vend_channel_id,
vend_channel_code, product_id (snapshot at create), capacity, amount (cents,
snapshot), system_qty (nullable — machine qty frozen at submit),
counted_qty (nullable), variance_qty (stored: counted − system),
note, timestamps`.

The sampling settings are stored so anyone can later see how the sample was
drawn.

## 4. Features

### C1 — Entry: third button on the shared dropdown
**Open New Job · Open Stock Count · Open Service Notice.** The dropdown lists
all bound machines (a machine being topped up today can still be counted).

### C2 — Create modal (the sampling rules)
- Machine pre-filled; shows how many channels it has.
- **Channels**: `All` or `Random`. Random reveals **How many** (1…channel
  count).
- **Products** (optional multi-select of the products currently in that
  machine): sample only from channels holding them. If fewer channels match
  than "How many", it takes them all and says so.
- **Skip empty channels** toggle (default on — a channel at 0 is rarely worth
  counting; off when checking for "machine says 0 but stock is there").
- Remarks, sequence.
- The random draw is done **server-side at create** and then fixed. Before the
  count starts, supervisor/admin can **Re-draw**, or add/remove a channel by
  hand. Drivers cannot re-draw.

### C3 — Row in the job table and route page
Own row type merged by `sequence`; clipboard icon; `SC-xxxxx`, machine, site,
"5 channels", status, and after completion the net variance ("−3 pcs"). Appears
as a stop on `Route.vue` using the machine's lat/lng. Job counters and
`RemoveEmptyOpsJob` include it.

### C4 — Count page (`/stock-checks/{id}/edit`), phone-first
One line per sampled channel, ordered by channel code:

| Channel | Product (thumbnail + name) | Current Qty | Real Qty |
|---|---|---|---|
| 12 | Magnum Almond | 8 | `[ select 0…capacity ]` |

- **Current Qty** is live from `vend_channels.qty` while Pending.
- **Real Qty** is a numeric dropdown `0…capacity`, starting **unselected** —
  the driver must choose for every line (choosing the same number is a valid
  "matches"). Same select style as Stock In on `EditItem.vue`.
- Optional note per line; photo upload on the check (existing attachments).
- If a channel's product changed since the draw (mapping swap), the line shows
  the current product with a "changed" tag.
- **Submit** freezes `system_qty` for every line at that instant, stores
  `counted_qty` and `variance_qty`, stamps `counted_at/by`, status → Completed.
  Mismatched lines highlight red with the difference.
- **Undo** (update permission) reopens it; **Cancel**; **Delete**
  (admin/supervisor).

### C5 — What it deliberately does not do (phase 1)
Record and report only. It does **not** change `vend_channels.qty`, create
`ProductMovement`, sync to cms, enter freeze/tally, the pick list, or
`stock_in_cents`. Fixing the machine's figure remains the existing **Stock
Adjustment** action. (Pushing a partial count to CityBox is unsafe until we know
whether their stocktake-submit zeroes products left out of the payload.)

### C6 — Report: Stock Count Variance (same release)
- Row per check, drill-down per channel. Columns: date, SC code, machine, site,
  counted by, channels counted, system qty, real qty, variance qty,
  **variance value** (`variance × amount`, integer cents), matched %.
- Filters: date range, operator, driver, machine, site, product, mismatches
  only, shortfall only. Excel export.
- Rollups by machine / site / driver / product.

### C7 — Permissions
New `stock-checks` tuple in `RolePermissionSyncSeeder`
(`read/create/update/delete/export`). Count (update): driver, sup_driver,
technician, supervisor, admin, operator_* equivalents. Create / re-draw /
delete / export: supervisor, admin, operator_admin, operator_supervisor.
Constructor `permission:` middleware + operator ceiling through the parent job.
Seeder run on prod after deploy.

### Phase 2
- **Batch assign** from the Ops Dashboard "Assign Job(s)" modal: many machines,
  same sampling rule, each machine gets its own random draw.
- **Random machines too**: "pick 10 random machines from this route/operator".
- **One-click Stock Adjustment** from a completed check's mismatches.
- "Last counted" + variance indicator on the Ops Dashboard (correlated
  subquery, never a join).
- CityBox / freezer ledger consuming `counted_qty`.

## 5. Build order
1. Migrations, models, constants, factories, sampler service
   (`StockCheckSampler` — pure, seedable, unit-tested: all / N / product filter
   / skip-empty / fewer-than-N).
2. Seeder tuple, `StockCheckController` + FormRequests + resources, feature
   tests (permission matrix, operator ceiling, driver cannot re-draw, submit
   requires every line, `system_qty` frozen at submit, undo).
3. Dropdown button + modal + merged row (`Pages/OpsJob/StockCheck/*`).
4. Count page. 5. Route.vue, counters, `RemoveEmptyOpsJob`. 6. Report + export.
7. Full suite on a private DB, Pint, clean-worktree asset build, diff review,
   push, seeder on prod.

Shares the "third/fourth row type" plumbing with Service Notice (merged rows,
sequence save/renumber at `OpsJobController` ~1997–2081, 2400). Whichever ships
first should generalise that code so the second is cheap.

## 6. Risks
- A sale between the driver counting and pressing Submit shifts `system_qty` by
  one. Accept; door-open usually pauses selling, and the note field covers it.
- Smart freezer has no qty feed — Current Qty shows "—" and variance is blank
  until its ledger exists. Count still records the real figure.

## 7. Decisions needed from Brian
1. ~~UI label~~ **DECIDED 2026-09-19 (Brian):** it is an action / spot check,
   unrelated to the existing "Daily Stock Count" + "Stock Count Dashboard"
   reports. UI label = **"Stock Count"**; code/tables stay `stock_checks` only
   because `stock_counts` is taken. It must not read from, write to, or be
   merged into those reports or the `stock_counts` tables.
2. Own tables (recommended) — OK?
3. Random draw fixed at create, re-draw by supervisor/admin only (recommended)?
4. "Skip empty channels" default on?
5. Phase 1 is record + report only; machine qty fixed via the existing Stock
   Adjustment (recommended) — or must the count correct the figure itself?
6. Should drivers see the variance after submit (recommended yes), or only the
   office?
