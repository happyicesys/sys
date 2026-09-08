# "NA" channel error (code 99) for gateway sales with no TRADE — plan (2026-09-08)

Status: **Phases 1–2 DEPLOYED 2026-09-09; Phase 3 (nightly marker, frame-time
resolver, dirty-day resync) BUILT 2026-09-09 — see "Build log" at the end.** Rev 3: no grace period,
nightly once-a-day processing, month-late TRADEs (30-day window), Dispense
blank without a TRADE, product/qty data kept.

## Rules (Brian, 2026-09-08)

- **Payment truth = the payment gateway.** A gateway row is Paid the moment
  the paid webhook created it. Revenue counts it.
- **Dispense truth = the vending machine's TRADE, only.** No TRADE → dispense
  is unknown → **not** dispensed. The APK's dispense ACK
  (`payment_gateway_logs.is_dispensed`) is "order received", not proof of a
  drop, and never promotes a row to dispensed.
- **Code 99 = "Machine transaction not found (NA)"**, server-defined; the VMC
  and APK never emit it (ingest refuses it).
- **No grace period.** Machines can be offline for a month and then replay
  every queued TRADE. Rows are marked 99 once their day is over; a TRADE that
  arrives later — days or weeks — clears the mark and re-syncs that day.
- **Once a night, per whole day**, not hourly.

## What the data says (prod, 2026-09-08)

Gateway rows with no TRADE since 2026-08-01:

| settlement | dispense ACK | refunded | multiple | rows |
|---|---|---|---|---|
| SETTLED | yes | no | no | 3,885 |
| SETTLED | yes | no | yes | 870 headers |
| REFUNDED | no | yes | no | 84 |
| REFUNDED | no | yes | yes | 34 headers |
| PENDING | yes | no | no | 3 |

Multiples: 904 headers → **2,355 item rows, all NULL code**. The paid-time
pre-create writes item rows (`GatewayVendTransactionService` ~L178); the
TRADE later deletes and recreates them. Items must be marked too or the CSV
item lines stay blank.

~4,450 of the settled rows are the 10–16 Aug pre-check gap
(`RepairGatewayTradeGap`); background rate 3–9/day.

**Machine clocks are not trustworthy.** Non-gateway single TRADEs since
2026-08-17, frame `TIME` vs server arrival:

| bucket | rows |
|---|---|
| within 5 min | 61,848 |
| 5–60 min behind (offline replay / drift) | 520 |
| 1–24 h behind | 9 |
| 1–30 d behind | 0 |
| > 30 d behind (clock stuck in the past) | 618 |
| > 1 d in the FUTURE (clock at 2070 etc.) | 1,889 |
| 5 min – 1 d in the future | 66 |
| no TIME field | 3,620 |

**The APK replays offline TRADEs unchanged.** `TradeFileStore` appends the
raw frame to `HttpTradeFile.txt` and replays it line by line on reconnect —
same frame, same `TIME` (device clock at sale time,
`clsToolBox.getDateTimeString()`), no "replayed" flag. The APK never sets
the device clock. So a replayed frame is indistinguishable from a live one
except that its `TIME` is in the past.

## Blockages / blind spots

1. **`code` is `int NOT NULL`** → 99, verified never used by firmware (all
   `SErr` ever seen ∈ {0,3,4,5,6,7,8,9}). "NA" is a display label
   (`VendChannelError::displayCode()`).

2. **NULL means "success" in 105 literal SQL predicates across 20 files**
   (`VendController` 22, `StoreVendProductRecords` 22, `StoreVendsRecord` 13,
   `VendTransaction` 13, `GpMetricsAggregator` 8, …). Writing 99 without
   first centralising the rule drops ~4,450 August sales from every dashboard.

3. **A late TRADE for a NEW row is stamped with server "now", not the sale
   time.** `VendDataService` dispatches `CreateVendTransaction(…, true)` and
   `createVendTransaction()` does `transaction_datetime = isCurrentTime ?
   now() : parse($input['time'])`. Only pre-created gateway rows keep their
   paid time. So today a cash/card sale replayed a month late is booked on
   the arrival day. Brian's "sync into the right past day" needs this to
   change — but the clock table above says frame `TIME` cannot be trusted
   blindly (2,500+ frames since Aug are years off). See Design D.

4. **gp_metrics fault columns** would count 99 as a machine fault → Machine
   Health / error rates / VendCriteria weightage skew. 99 is not a fault.

5. **Product / qty data is KEPT for 99 rows** (Brian, 2026-09-08). In every
   aggregate — `$`, revenue, GP, success_count, sold qty, product records,
   warehouse "Y'day sold", stock-count deltas — 99 behaves exactly like NULL
   does today. Nothing moves when a row is marked; the only change is what
   the reader sees (Error Code "NA", Dispense blank). Nothing goes wrong
   with that: it is today's behaviour, the money was taken and the APK ACKed
   the order, and a physical stock count already reconciles the rare
   not-actually-dropped case. A late TRADE reporting a fault still pulls the
   row out of sales through the dirty-day rebuild.

   Pre-existing wrinkle, NOT caused by 99: pre-created gateway rows carry
   `success_qty = 0` (not NULL), and two readers use that column directly —
   the Dashboard "today" bar (`SUM(success_qty)`, DashboardController ~L729)
   and `DailyFactsBuilder` (`COALESCE(success_qty, qty, 1)` → 0). Both
   already under-count every no-TRADE row by its qty; `SyncVendTransactionTotalsJson`
   gets it right by falling back to `qty` when `success_qty = 0`. Fix
   separately by giving both readers the same fallback.

6. **Rollups after a late TRADE.** Nothing in `applyTradeToPreCreatedRow` /
   `GetPurchaseConfirm` rebuilds a day; `reconcile:sales-rollups` heals only
   `SUM(amount)` drift. A late TRADE that confirms a dispense on a 99 row
   changes nothing in `$`; one that reports a fault on a multiple changes
   item counts but not `$` → invisible to the drift check. Needs
   an explicit "this day changed" signal (Design E).

7. **Index audit — no new index.** The nightly marker scans one day window on
   `idx_datetime_error (transaction_datetime, vend_channel_error_id)`; the
   `IS NULL` part is filtered inside the index (ICP), leaving a few hundred
   candidates per day for the two unindexed predicates. The one-off seed from
   2026-08-01 walks ~1.3M index entries day by day, once. A composite on
   `(is_found_in_transaction, transaction_datetime)` would rebuild a 4.8M-row
   table and tax every TRADE insert to speed a once-nightly query. `EXPLAIN`
   is blocked on the MCP (SELECT only) — run it over SSH on prod before
   scheduling, and again after the first seed run.

8. ~~Prod-only column~~ **Corrected:** `vend_transactions.error_code_normalized`
   (VIRTUAL, indexed, `$.SErr` from the TRADE JSON) was added by migration
   `2023_05_22_111621_add_error_code_normalized_vend_transactions`, since
   squashed into `database/schema/mysql-schema.sql`. Declared, not drift. Its
   last reader (the card-reversal backfill) was removed 2026-09-08
   (1a85db74a3); today it costs one index write per TRADE and nothing else.
   Keep it — it is how "99 never sent by firmware" was verified. 99 rows have
   no JSON so it stays NULL for them.

## Design

### A. Reference row + reserved-code guard

Migration: `updateOrInsert(['code' => 99], ['desc' => 'Machine transaction
not found (NA)', 'weightage' => 0])` (not `VendChannelErrorSeeder`, which is
fresh-install only). `VendChannelError::CODE_NOT_FOUND = 99`,
`SERVER_RESERVED_CODES = [99]`, `notFoundId()` cached, `displayCode()` → `NA`.
`VendTransactionService` (~L1073) refuses a reserved code from a frame: null
id for that channel + warning log with the vend code.

### B. One verdict rule, three questions (refactor first, zero drift)

`App\Support\DispenseVerdict`:

| question | codes that answer yes | used by |
|---|---|---|
| `countsAsSale` | NULL, 0, 6, 99 | every aggregate: `$`, revenue, GP, success_count, sold qty, product records |
| `isMachineFault` | not NULL and ∉ {0, 6, 99} | error_count, error rates, weightage |
| `hasDispenseVerdict` (display only) | 0, 6 → Dispensed; other firmware codes → Failed; NULL / 99 → blank | `SaleStatus`, CSV Dispense Status |

So in SQL, 99 is simply appended wherever NULL is already accepted; no
aggregate's population changes. The split only exists in PHP, for display.

SQL fragment builders for header (`vend_channel_errors.code`) and item
(`vti.vend_channel_error_code`) columns, plus PHP predicates. Replace all 105
literals. Ship **before any 99 row exists**: with no 99 in the DB the three
predicates collapse to today's one, so `reconcile:sales-rollups --dry-run` +
`transactions:rollup-verify` before/after prove zero drift.

`SaleStatus` (Brian, 2026-09-08): **the Dispense column is blank unless the
row was matched with a TRADE.** `dispense()` returns `''` whenever
`is_found_in_transaction` is false — a 99 row, a not-yet-marked row from
today, and a PENDING row alike; the current "Pending" / "No report" labels
go. The Error Code column ("NA") is what tells the reader why it is blank.
`itemDispense(99)` → `''` as well, `itemDispensed(99)` → false. `payment()`
unchanged (Paid — gateway truth). The grid's Dispense Status filter keeps
listing Dispensed / Failed only; blank rows match neither.

### C. Nightly marker (new service + command; nothing existing does this)

`App\Services\Sales\MissingTradeMarker`:

- scope (ONE method, so NETS-report-created rows plug in later):
  `payment_gateway_log_id IS NOT NULL AND is_found_in_transaction = 0 AND
  vend_channel_error_id IS NULL AND transaction_datetime < <day boundary>`.
- window: `[settings.missing_trade_marked_until, today 00:00)` — normally
  exactly yesterday; a missed night self-heals. Operator timezone boundary.
- per chunk of 500 header ids: `UPDATE vend_transactions SET
  vend_channel_error_id = :na WHERE id IN (…)`; `UPDATE vend_transaction_items
  SET vend_channel_error_id = :na, vend_channel_error_code = 99 WHERE
  vend_transaction_id IN (…) AND vend_channel_error_id IS NULL`; stamp
  `meta_json.missing_trade.marked_at`. Settlement, is_found, qty untouched.
- result object: headers, items, dates.

Command `sales:mark-missing-trade {--from} {--to} {--apply}` (report-only
without `--apply`). **Scheduled `dailyAt('00:01')`**, `withoutOverlapping`,
i.e. BEFORE the builders that read yesterday
(`store:previous-day-vend-records` moves from `daily()` = 00:00 to
`dailyAt('00:06')`; gp at 00:40, customer summary 01:00 already follow). So
yesterday is built once, already correct, and the marker needs no rebuild of
its own.

### D. Late TRADE, correct day (`TradeTimestampResolver`)

`App\Support\TradeTimestampResolver::resolve(?string $frameTime, Carbon $now,
int $maxDaysBack): Carbon` — pure, unit-tested:

- frame `TIME` parseable AND `now − maxDaysBack ≤ TIME ≤ now + 5 min` →
  use `TIME` (the offline replay case, and ordinary live frames);
- otherwise (missing, future, stuck years back) → `now()`, and stamp
  `meta_json.frame_time = {raw, rejected: true}` so it is auditable.
- `maxDaysBack` = `config('sales.trade_max_days_back')` = **30** (Brian,
  2026-09-08). Still rejects the 618 stuck-clock rows, which are years off;
  a machine offline longer than 30 days gets its replayed sales booked on
  the arrival day, stamped rejected, and they show up in the dirty-day
  rebuild of that day like any other.

Wired into `createVendTransaction()` in place of the `isCurrentTime` ternary
(`SyncBackDateVendTransaction` keeps its explicit date). Pre-created gateway
rows keep paid time as now. Effect on today's data: the 520 "5–60 min
behind" and 9 "1–24 h behind" rows would have been booked a few minutes or
hours earlier — mostly the same day; nothing else moves.

**Residual risk, undetectable by design:** a clock that is wrong by less
than `maxDaysBack` (a few days slow) is trusted. There is no server-side
signal to catch it — the replay is byte-identical to a live frame.

### E. Resync of a past day, once a night, via the existing command

- **Signal:** at ingest — `createVendTransaction()` and
  `applyTradeToPreCreatedRow()` — if the row's `transaction_datetime` day is
  before today, `Redis SADD sales:rollups:dirty-days <Y-m-d>` (O(1), no
  table, no scan). Also `meta_json.missing_trade.cleared_at` when the row
  carried 99.
- **Drain:** `reconcile:sales-rollups` gains `--dirty`: `SPOP` every date,
  heal each **unconditionally** (skips the amount pre-check) with the
  command's existing per-day jobs (`StoreVendsRecord`, `ProcessGpMetricsDay`,
  `StoreVendProductRecords`) and ONE cascade (totals-JSON, Site Summary,
  `transactions:rollup-daily` for those dates). Scheduled `dailyAt('02:00')`,
  before the existing 02:15 amount-drift pass, which stays as the safety net
  (if Redis is ever flushed, `$` drift is still caught; count-only drift
  would wait for the next late TRADE on that day).
- A machine replaying 400 trades across 30 days at 14:00 costs 30 `SADD`s at
  ingest and ≤30 day rebuilds that night — not 400 rebuilds, and none during
  trading hours.
- `CreateVendTransaction::isAlreadyApplied` already treats a 99 row as
  "awaiting TRADE" (`is_found_in_transaction = 0` + PG log id) — verified, so
  the late TRADE reaches `applyTradeToPreCreatedRow`, which overwrites the
  code and rebuilds items.

### F. Surfaces

- CSV exports: Error Code prints `NA` on header and item rows; Dispense
  Status blank (no TRADE); Payment Status stays "Paid".
- `VendTransactionResource`: `vend_channel_error_code_display`;
  `Transaction.vue` already shows `desc` for any code ∉ {0,6}.
- Payment Gateway Transactions page unchanged (`is_found_in_transaction`
  still means "TRADE applied").
- Error-code filter lists NA automatically.

### G. Tests

- `DispenseVerdictTest` — three predicates, SQL + PHP, incl. 99 and NULL.
- Rollup equivalence — `reconcile:sales-rollups --dry-run` clean before/after
  the refactor on a private test DB.
- `TradeTimestampResolverTest` — accepted window, future, stuck clock,
  missing TIME, meta stamp.
- `MissingTradeMarkerTest` — day boundary, watermark, items, refunded rows
  included, idempotent, reserved-code guard at ingest.
- `LateTradeResyncTest` — 99 row + TRADE → TRADE's code, cleared_at, dirty
  day added once; `--dirty` heals and empties the set; multiples rebuilt.
- `SaleStatusTest` additions; export prints NA on header and item rows.

## Open

None. The 118 refunded Omise rows are marked too: Brian's definition of 99
is "the payment side received the money, no TRADE matched", and the gateway
did receive it before the 10-minute auto-refund.

Decided: code 99, server-only; product/qty data kept (99 = NULL in every aggregate); Dispense column blank without a TRADE; frame TIME trusted within 30 days; payment truth = gateway, dispense truth = TRADE;
no grace, nightly at 00:01; items marked; seed floor 2026-08-01; reuse
`reconcile:sales-rollups` (+`--dirty`); no new index.

## Order of work

1. `DispenseVerdict` refactor + drift proof. Deploy.
2. Migration (99 row, `settings.missing_trade_marked_until`) + guard +
   `SaleStatus` + display + exports. Deploy.
3. `TradeTimestampResolver` + config. Deploy (watch the "rejected" stamps for
   a few days).
4. `MissingTradeMarker` + command + schedule shift; dirty-day `SADD` +
   `--dirty`; schedule. Deploy.
5. Prod over SSH: `EXPLAIN` the marker window; `sales:mark-missing-trade
   --from=2026-08-01` (report, then `--apply`); `reconcile:sales-rollups
   --from=2026-08-01 --to=<yesterday> --force`-equivalent via `--dirty` after
   seeding the set, or `reconcile:range --from=2026-08-01` (both rebuild
   counts under the new rule); confirm dashboards.

---

# Part 2 — NETS: report lines with no TRADE become sales rows (added 2026-09-08)

Status: **PLAN, nothing implemented.** Same rules as Part 1: payment truth =
the rail (here the NETS MerchantConnect report), dispense truth = the TRADE,
99 = "payment side received the money, no TRADE matched".

## What exists today

Card Settlement (`CARD_SETTLEMENT_2026-09-01.md`): a report line is matched to
a card TRADE row by terminal binding → vend, exact cents, and a time window
(−60 s / +300 s, `config('card_settlement.match_*_slack_seconds')`;
hour-less rows match circularly). Unresolved lines stay `UNMATCHED` with a
`resolution_note`; **Sync** stamps `card_settlement_synced_at` on matched
sales and runs the refund reconciler. Nothing is created for an unmatched line.

Prod, all reports (2026-07-31 → 2026-09-06):

| line outcome | lines | meaning |
|---|---|---|
| matched purchase | 85,157 | normal |
| matched reversal | 305 | refund tick via reconciler |
| `No matching sale in window` (bound machine) | 726, 231 terminals, 38 days | **TRADE never reached mark1 — the 99 population** |
| `All matching sales already claimed` | 202 | double tap — NETS charged twice, one sale |
| `No terminal binding` | 147, 6 TIDs | no vend → nothing can be created |
| `found on machine X` | ~60 | binding sheet wrong → fix binding, rematch |
| ignored (Logon etc.) | 11,572 | non-purchase |

≈ 19 orphan lines/day. Concentrated on known TRADE-losing machines (2518,
4605, 2399 — see the 2026-09-05 review).

## Answers to the three questions

**New row, not a union.** A query-time union would have to be taught to every
aggregate, export, refund ticket and dashboard — the same 105-predicate
problem Part 1 exists to remove. One truth table: a real `vend_transactions`
row, linked to its report line by a new nullable `card_settlement_row_id`
(mirror of `payment_gateway_log_id`), so "pre-created by a rail" is
`payment_gateway_log_id IS NOT NULL OR card_settlement_row_id IS NOT NULL`
in exactly one method (`VendTransaction::scopePreCreatedByRail()`).

**Created at Sync, not at upload/match.** A report in `review` is still being
repaired (bindings fixed, Rematch, manual Assign, Ignore). Sync is the
operator's "this report is settled" step, and it is already the only writer
of report-derived facts. Only lines that are `UNMATCHED` with
`resolution_note = 'No matching sale in window'` AND a bound `vend_id`
qualify. Not: double taps (a human decides refund/ignore), wrong-machine lines
(binding fix), unbound TIDs (no vend), ambiguous.

**Marked 99 immediately.** The line's day is already ≥ 1 day old when the
report exists (next-day download), and the matcher has just searched the
window, so there is nothing to wait for. The nightly marker of Part 1 is for
gateway rows only; its scope stays as designed.

## The row

Built by one shared factory, `App\Services\Sales\PreCreatedSaleFactory`,
extracted from `GatewayVendTransactionService` so both rails derive
customer / operator / location type / contract / model / prefix / planogram
the same way (OOP: one place, two callers):

| column | value |
|---|---|
| `transaction_datetime` | line `transaction_date` + `transaction_time` (NETS local; cutover rule as in Card Settlement); hour-less line → date + `00:mm:ss` and `meta_json.time_partial = true` |
| `amount` | line `amount_cents` |
| `payment_method_id` | Card Terminal (`payment_methods.code > 0`, no gateway) |
| `cashless_mfg` | the terminal unit's company (Nets / Nets-Auresys) |
| `vend_id`, `customer_id`, `operator_id`, `location_type_id`, contract, model, prefix, `product_mapping_id` | from the vend, as the gateway pre-create does |
| `vend_channel_id` / `vend_channel_code` | `0` (column NOT NULL; same placeholder the gateway path uses for unmapped) |
| `product_id`, `product_mapping_item_id`, `unit_cost_id` | NULL — no purchase info in a NETS line (unlike REQQR) |
| `unit_cost` | 0 → see GP note below |
| `gst_vat_rate`, `revenue` | operator's rate (NOT the mapped-item rate — there is no item; also fixes the gateway path's silent `0` for unmapped rows) |
| `order_id` | synthetic `CS-<row id>` — NOT NULL, unique per vend, letters so it can never collide with an APK `ORDRID` (numeric) |
| `qty` 1, `success_qty` 0, `dispensed_qty` 0, `is_multiple` 0 | unknown until a TRADE |
| `vend_channel_error_id` | **99** |
| `is_found_in_transaction` | false |
| `settlement_status` | SETTLED (the report is the money truth); REFUNDED + `is_refunded` + `auto_refund_source = settlement_report_reversal` when the line is paired with a reversal |
| `card_settlement_synced_at` | now |
| `card_settlement_row_id` | the line |
| `meta_json.origin` | `card_settlement` |

And the line flips to `MATCHED` with `matched_vend_transaction_id` = the new
row, note `Created from report (no TRADE)`. That reuses the existing UNIQUE
claim: a Rematch skips it, a re-uploaded file is `DUPLICATE` by fingerprint,
a second Sync finds nothing to create. Idempotent by construction.

Each created row's day → `SADD sales:rollups:dirty-days` (Part 1 E), so the
past day is rebuilt that night. Reports are always ≥ 1 day late, so every
orphan is a past-day insert.

## The late TRADE (poor connection) — adoption

A card TRADE carries no acquirer reference, so the orphan cannot be found by
`order_id` the way a gateway row is. Adoption uses the matcher's own rule in
reverse, inside `VendTransactionService::create` after the order-id lookup
misses, **only for terminal-paid TRADEs**:

```
orphan = vend_transactions
   WHERE vend_id = :vend AND card_settlement_row_id IS NOT NULL
     AND is_found_in_transaction = 0 AND amount = :cents
     AND transaction_datetime BETWEEN :tradeAt − 300 s AND :tradeAt + 60 s
   ORDER BY ABS(diff) LIMIT 1  FOR UPDATE
```

(`idx_vend_transaction_datetime (vend_id, transaction_datetime)` serves it;
one extra point query per card TRADE, ~3k/day.) Found → the existing
`applyTradeToPreCreatedRow()` (branch condition widened from
"has PG log" to "pre-created by a rail"; `CreateVendTransaction::isAlreadyApplied`
likewise) fills code, channel, product, qty, items, `is_found = true`, and
**overwrites the synthetic `order_id` with the TRADE's `ORDRID`**. The row's
day → dirty set. Not found → normal insert.

`resolvePreCreatedSettlement` keeps the report's SETTLED unless the TRADE
reports a fault — then the existing rule applies (NETS is not API-refundable,
so it stays SETTLED, and the refund tick stays with the report).

**Blind spot, accepted:** adoption needs the TRADE's timestamp to be right.
A machine offline > 30 days, or one with a broken clock (Part 1 D: frame
TIME rejected → booked "now"), produces a TRADE that cannot be placed in the
orphan's window → a NEW row is inserted and the orphan remains → the sale
is counted twice. Guard, not auto-merge: `card-settlement:orphans-audit`
(weekly, report-only) lists orphans that have an unclaimed card sale of the
same amount on the same machine within ±30 days, for a human to Assign — and
**Assign / Ignore on a line whose matched sale is an orphan deletes that
orphan row** (+ dirty day), so moving a line to the real sale can never leave
two rows. `destroy(report)` deletes its orphans the same way.

## Re-sync / re-upload

- Same file again → lines `DUPLICATE` by fingerprint → nothing created.
- Rematch → touches only unresolved lines → created orphans untouched.
- Sync again → creation query finds no qualifying lines → no-op; refund
  reconciler runs as today.
- Day D's sale in file D+1 (cutover) → created when D+1 is synced, dated D.
- Binding moved later (`moveToVend`) → lines are re-matched; an orphan whose
  line moves to another machine is deleted and re-created on the right vend
  by the next Sync (the delete rule above covers it).

## Seed

`card-settlement:create-orphan-sales --from=2026-08-01 --to=<date> --apply`
walks already-synced reports and applies the same creation rule: the 726
lines today. Report-only without `--apply`. Then the dirty set drains that
night, or `reconcile:range --from=2026-08-01` by hand.

## Open ❓

1. **Gross profit on an orphan row.** `product_id` is NULL and `unit_cost`
   0, so `gross_profit = revenue`. Options: (a) leave it — matches today's
   unmapped gateway rows; (b) exclude orphans from GP columns while keeping
   them in sales `$` (one more `DispenseVerdict`-style predicate:
   `hasCost`). Propose **(b)** — a machine's GP% should not rise because its
   modem is bad.
2. **Double taps** (`All matching sales already claimed`, 202 lines): stay
   manual as proposed, or also create a 99 row (customer WAS charged twice)?
   Propose **manual** — most are refunded, and a row would inflate sales.
3. Orphan rows in the Sales Transactions grid: Product blank, Channel 0,
   Error Code NA, Dispense blank, Payment "Settled" (report). Confirm that
   reads right for ops.

## Order of work (after Part 1 is live)

1. Migration: `vend_transactions.card_settlement_row_id` (nullable, INSTANT,
   index), `scopePreCreatedByRail()`, factory extraction (behaviour-preserving
   for the gateway path, tests).
2. Orphan creation in `CardSettlementSyncService::sync` + line flip + dirty
   day; delete rule on Assign / Ignore / destroy; tests.
3. Adoption in `VendTransactionService::create` for terminal TRADEs; widen
   the pre-created branch; `isAlreadyApplied`; tests incl. the ±window and
   the "no window → insert" path.
4. `card-settlement:orphans-audit` (weekly, report-only) +
   `create-orphan-sales` seed command.
5. Prod: seed from 2026-08-01, drain, verify Card Settlement totals now equal
   Sales Transactions card totals per day.

---

# Part 3 — "Will auto refund?" per card terminal (added 2026-09-08)

Status: **PLAN, nothing implemented.** Source: partner workbook
`HappyIce_Auto_Refund_TID_Analysis_1.xlsx` (reversal-line only) + prod
re-verification below.

## Two ways a failed single-item card sale is made good without a ticket

1. **Reversal line** in the NETS report (what the workbook counted: 305 events).
2. **Never captured** — the terminal voids the approval before batch upload,
   so the sale has a TRADE (error code) but NO line in the report at all. The
   reconciler already knows this state (`STATE_NOT_CAPTURED`) but nothing
   surfaces it as "auto-refunded", and the workbook does not count it.

Prod, failed single-item card sales on bound NETS terminals, 2026-08-01 →
09-06, only days where the report carries matched lines for that TID
(so a missing report cannot masquerade as a void):

| batch (TID prefix) | failed sales | never captured | reversed | captured, not reversed | made good |
|---|---|---|---|---|---|
| Nets #1 (23005, 23012, 90602) | 87 | 7 | 2 | 78 | 10% |
| Nets #2 (23082) | 137 | 19 | 1 | 117 | 15% |
| Nets #3 (23100) | 286 | 201 | 55 | 30 | 90% |
| Nets #4 (23102) | 222 | 168 | 36 | 18 | 92% |
| Nets #5 (23104) | 424 | 315 | 66 | 43 | 90% |
| Nets #6 (23107) | 140 | 105 | 20 | 15 | 89% |
| Nets #7 (23108) | 128 | 97 | 17 | 14 | 89% |
| Auresys #1 (23077, 92023) | 70 | 32 | 0 | 38 | 46% |
| Auresys #2 (23113) | 4 | 2 | 0 | 2 | 50% |

Findings:

- **The capability is a property of the terminal batch, not of the TID.**
  Batches #3–#7 make ~90% of failures good, and ~75% of those are voids, not
  reversals. Batches #1/#2 charge the customer ~87% of the time. Auresys is
  half/half.
- **Not the retained-credit fault.** None of the 205 batch #1/#2 captured
  failures had their credit consumed by a later vend
  (`retained_credit_settles_txn_id`) — the customer was simply charged.
  Only 24 of them raised a ticket.
- **The workbook's per-TID "does NOT support" list is mostly small-sample
  noise** on batches #3–#7 (1–3 events, no reversal, voids not counted) —
  e.g. 23100719 shows 28 voids + 6 reversals + 8 captured. Do not import the
  sheet's classification; import its **batch** column only and re-derive.
- Even a "supports" TID captures ~10% of the time: the reverse is a race
  between the VMC's failure signal and the terminal's window. So the flag
  means "usually", and the ticket page must still show the per-sale verdict.

## Design

**Schema — `card_terminal_units`** (the TID, Data Management → Card Terminal):

| column | meaning |
|---|---|
| `batch` varchar(32) nullable | "Nets #3 (50x)" etc., imported from the sheet (`card-settlement:import-terminal-batches <csv>`) |
| `is_will_auto_refund` tinyint nullable | Brian's flag; NULL = unknown |
| `auto_refund_flag_source` varchar(16) | `derived` / `manual` — manual sticks until cleared |
| `auto_refund_stats_json` json | `{from, to, failed, never_captured, reversed, captured, rate, computed_at}` — the tooltip and the audit |

**Schema — `vend_transactions.card_settlement_state` varchar(16) nullable,
indexed** — the reconciler's per-sale verdict, persisted
(`reversed` / `captured` / `not_captured` / `unbound`; NULL = not final).
`CardSettlementRefundReconciler::reconcileDay` already computes it for every
card sale of a final day; today it only flips `is_refunded`. Writing the state
is what makes everything below a cheap GROUP BY instead of the 4-table join
that timed out on the MCP three times while verifying this.

**Derivation — `card-settlement:classify-auto-refund {--days=60} {--apply}`**,
weekly after the reconciler: per TID over final days, failed single-item
card sales by state → `made_good = (not_captured + reversed) / failed`.
Rule: ≥ 5 events and rate ≥ 70% → true; ≥ 5 events and rate ≤ 30% → false;
otherwise inherit the batch's pooled rate with the same thresholds; else
NULL. `manual` rows are reported, not overwritten. Stats JSON always refreshed.

**UI**

- Card Terminal Index: column **Will auto refund?** — `CheckCircleIcon`
  green / `XCircleIcon` red / `QuestionMarkCircleIcon` grey, tooltip from the
  stats ("18 of 20 failed sales made good, 60 d"), plus a Batch column;
  filter Yes / No / Unknown; sortable.
- Card Terminal Edit: override select (Auto / Yes / No) → `manual`.
- Machine Setting/Edit: read-only line under the terminal picker showing the
  bound TID's flag, so a technician sees it where the binding is made.
- Refund ticket page: next to the existing "NETS report" verdict, "Terminal
  auto-refunds: Yes/No/Unknown (batch)". Recommendation text: batch #1/#2 →
  refund now, do not wait for the report; #3–#7 → wait for day-final, refund
  only if `captured`.

## What else is chained (the "left out" list)

1. **"Not captured" is invisible to ops today.** `is_refunded` stays OFF for
   it (correct: no money moved), so the Sales Transactions "Auto-refunded?"
   filter cannot find refund liabilities. Propose: Payment column shows
   **"Voided (not captured)"** from `card_settlement_state`, and the
   Auto-refunded filter gains a "made good by terminal" option covering both
   reversed and not_captured. ❓ Brian — this is a display rule, not a change
   to `is_refunded`.
2. **Refund liabilities list** (Transactions → Card Settlement, new tab):
   `card_settlement_state = captured` + error code ∉ {0,6} + single item, with
   ticket status — the 205 batch #1/#2 customers of the last 5 weeks, 181 of
   whom never claimed. Same tab lists the 184 unreversed double taps from the
   earlier check. NETS card refunds are manual (MerchantConnect), so the list
   is for ops, not automation.
3. **Hardware decision.** ~55 batch #1/#2 TIDs generate ~200 charged failures
   per 5 weeks. The flag + batch column is the swap list; nothing else
   changes that behaviour.
4. **45 captured-not-reversed rows still carry `is_refunded = 1`** (legacy
   inference). The reconciler deployed today should clear them once their
   days are final — verify after `card-settlement:reconcile-refunds` has run
   over August.
5. **Multi-item purchases never auto-refund** on any batch (workbook, and
   consistent with `HandleFailedVendTransaction`). The flag is single-item
   only; say so on the ticket page.
6. Part 1/2 tie-in: a 99 row (no TRADE) can never be "failed", so it never
   enters this statistic — correct, its dispense is unknown.

## Order of work (independent of Parts 1–2; can go first)

1. `card_settlement_state` column + reconciler writes it + backfill command
   over synced days.
2. Unit columns + batch import + classify command + tests.
3. Index/Edit/Setting/ticket-page surfaces.
4. Liabilities tab + "Voided" label (after ❓ 1).

---

# Part 4 — Review of Parts 1–3 (2026-09-08): blind spots, chain reactions, corrections

Each item was checked against code or prod, not reasoned from the plan text.
Items marked **FIX** amend the design above and take precedence over it.

## Logical errors / chain reactions found

1. **FIX — Refund recommendation would auto-approve every 99 sale.**
   `RefundMatchingService::isRealChannelError()` treats ANY non-zero code as a
   genuine fault; `RefundValidationService` then returns `REC_PROCEED`
   ("channel error logged → genuine non-dispense"). A ticket against a 99
   row (Omise or NETS orphan) would be recommended for payout although the
   dispense is unknown. The refund read path must use
   `DispenseVerdict::isMachineFault()` (99 excluded), not its own test.
   Pre-existing inconsistency surfaced by this: code **6** counts as a sale
   everywhere but is a "real error" for refunds → PROCEED on a dispensed
   sale. **Decided (Brian, 2026-09-08): 0 and 6 are both "dispensed" for
   refunds** — the refund path uses `DispenseVerdict::isMachineFault()`
   (fault = not NULL and ∉ {0, 6, 99}). Verified neither rail auto-refunds a
   code-6 sale today, so nothing changes in money terms: Omise's trade-fail
   refund already excludes 6 in code (`VendTransactionService` ~L560) and 4
   of 4 Omise code-6 rows since June are unrefunded; NETS captured 9 of 10
   code-6 card sales on covered days (0 reversals) — the VMC reports a code-6
   vend as success to the reader, and the APK never sends a cancel itself.
   No refund ticket has ever been raised against a code-6 sale. Only 12
   single card sales in five weeks carry code 6, so exposure is tiny either
   way.

2. **FIX — A reversed orphan would still count as revenue.** Revenue gates on
   `settlement_status` only; `CardSettlementRefundReconciler` writes
   `is_refunded` and never `settlement_status`, and no rollup filters on
   `is_refunded`. A Part 2 orphan (99 = sale, SETTLED) whose reversal line
   arrives in the next day's file stays in sales. Fix: the reconciler sets
   `settlement_status = REFUNDED` on any **rail-pre-created** row it marks
   reversed (gateway rows already get this from the Omise recorder). Wider
   pre-existing gap, same mechanism: a dispensed (code 0) card sale that NETS
   reversed — the 18 reversed double taps — still counts as revenue today.
   ❓ Brian: extend REFUNDED to those too (moves past revenue by a few
   dollars per case; the dirty-day rebuild handles the rollups).

3. **FIX — The dirty-day set cannot live in `Cache`.** Prod cache driver is
   `file`; only the queue is Redis. Use `Redis::connection()` explicitly
   (`sales:rollups:dirty-days`), wrap the `SADD` in try/catch so a Redis blip
   can never fail a TRADE ingest, and drain with `SMEMBERS` → heal → `SREM`
   per date, not `SPOP`, so a failed heal keeps the date. This also
   supersedes the earlier "queue reconcile per late TRADE behind a 10-minute
   lock" wording in Part 1 E: at TRADE time nothing is queued, only the date
   is recorded; the nightly `--dirty` run is the only consumer. (Verified:
   gp rebuild deletes the day first; vend_records writes explicit zero rows
   for vends without sales; so a day whose only sale was deleted or moved
   heals cleanly and cannot loop as drift.)

4. **FIX — Hour-less report lines must not become orphans.** Excel-damaged
   files carry mm:ss only. An orphan dated `00:mm:ss` books the sale in the
   wrong hour and can never be adopted (the ±window fails). Leave
   `time_is_partial` lines UNMATCHED for a human.

5. **FIX — No indexes on the two new `vend_transactions` columns.** Parts 2/3
   proposed indexed `card_settlement_row_id` and `card_settlement_state`.
   An index on a 4.8M-row table is an online rebuild with heavy IO and a
   permanent write cost on every TRADE, and neither query needs one:
   adoption seeks on `(vend_id, transaction_datetime)`, classification goes
   bindings → datetime, the liabilities list is a datetime range. Add both
   as nullable INSTANT columns (the pattern `card_settlement_synced_at`
   already proved), revisit only with an `EXPLAIN` that shows a scan.
   Consistent with Part 1's index verdict.

6. **Locked months diverge by design — report it.** The `--dirty` cascade
   calls `customer-summary:compute`, which preserves locked Site Summary rows.
   A late TRADE or an orphan landing in a closed month moves vend_records
   and gp_metrics but not the locked summary that commissions and customer
   settlements were paid from. Correct, but invisible: `--dirty` must print
   the dates that fall in locked months so finance sees them.

7. **"Voided (not captured)" label — the consumer trap.** `SaleStatus` reads
   `SaleFacts::fromRow($row)`; a consumer that forgets to select the new
   state column silently shows the old label (same trap as
   `payment_method_gateway_id`). Add the column to `SaleFacts` with a null
   default, and a test that runs the grid query, both CSV jobs and the
   refund related-transactions query and asserts the column is present.

8. **Adoption needs a row lock.** The APK replays its offline file line by
   line and the duplicate-upload bug is recent history: two frames for one
   sale can arrive seconds apart. The orphan lookup must run inside the
   ingest transaction with `lockForUpdate`, and the second frame must then
   fall into the existing duplicate short-circuit (it will: after adoption
   the row carries the TRADE's `ORDRID`).

9. **Orphan GST.** The gateway pre-create stores `gst_vat_rate = 0` for an
   unmapped basket, so `revenue = amount` including GST → GP overstated.
   Part 2's factory uses the operator's rate; apply the same to the gateway
   unmapped path while extracting the factory (one behaviour change, tested).

10. **Part 3 staleness.** `card_settlement_state` exists only for day-final
    days (files D and D+1 synced). If uploads stop, the flag freezes silently.
    Stats JSON carries `to`; the Index shows grey with "stats older than 14
    days" instead of a confident tick.

## Verified safe (no change needed)

- **Late-TRADE re-dating vs "last sale" on the machine:** `updateVendPaymentTimestamps`
  only moves a timestamp forward (`shouldUpdateVendTimestamp`), so a replayed
  month-old frame cannot drag the Ops Dashboard's last-sale backwards.
- **Unreported-gateway merge (dashboard totals + CSV append):** keyed on the
  absence of ANY `vend_transactions` row for the log, not on
  `is_found_in_transaction`, so a 99 row is never counted twice.
- **`CreateVendTransaction::alreadyRecorded`** keys on `(order_id, vend_id)`;
  synthetic `CS-<id>` ids never collide with an `ORDRID`, so card TRADEs
  reach the service and the adoption lookup.
- **Day buckets:** gp_metrics and the marker both use `DATE(transaction_datetime)`
  in server time; no timezone seam between "day is over" and the rollup.
- **VendCriteria weightage** is never derived from transaction error codes
  (only from `vend_channel_error_logs`), so 99 does not touch ops-job
  priority. `weightage = 0` on the row is still correct.
- **`is_multiple = true` already counts as success at header level**, so
  marking items changes header counts nowhere; item-level predicates are
  covered by the helper's item variant.
- **`SyncVendTransactionTotalsJson`** falls from `success_qty > 0` to the
  code predicate for 99 rows (success_qty is 0 on every pre-created row) —
  covered by the helper.

## Performance / best-practice check

- Nothing runs per TRADE beyond one indexed point query (adoption, card
  TRADEs only) and one Redis `SADD` (late frames only). No per-event rebuilds.
- Nightly work is bounded: marker = one day window on an existing index;
  `--dirty` = at most the distinct days touched; classification weekly and
  GROUP BY on a persisted state.
- The predicate helper emits `(col IS NULL OR col IN (0, 6, 99))` where the
  literals said `col = 0 OR col = 6 OR col IS NULL` — semantically identical
  while no row carries 99, and neither column is indexed, so no plan changes.
  The proof is the full suite plus the rollup verify harness, not a byte diff.
- Schema: three nullable INSTANT columns, zero new indexes, one reference row.
- Idempotency by construction: watermark (marker), UNIQUE claim (orphans),
  `SREM`-after-heal (dirty days), delete-then-rebuild (gp), explicit zero
  rows (vend_records).
- A guard test greps `app/` for the literal `code = 0 OR` / `IN (0, 6)`
  patterns so no hard-coded predicate can return after the refactor.

## Pre-existing issues surfaced (not caused by this plan)

- Code 6 sale/fault inconsistency (item 1).
- Dispensed card sales reversed by NETS still count as revenue (item 2).
- Gateway unmapped rows carry GST rate 0 (item 9).
- Dashboard "today" bar and `DailyFactsBuilder` under-count pre-created rows
  via `success_qty = 0` (Part 1 blockage 5).
- 45 captured-not-reversed card failures still carry the legacy refund tick
  (Part 3, item 4).
- 75 of 118 refunded Omise rows have no `auto_refund_source` (Part 1 aside).

## ❓ Remaining decisions

1. ~~Code 6 in the refund path~~ — decided: not a fault (item 1).
2. Reconciler sets `settlement_status = REFUNDED` for all NETS-reversed
   sales, or only rail-pre-created rows (item 2).
3. Part 3 "Voided (not captured)" display + Auto-refunded filter (Part 3 ❓ 1).

---

# Part 5 — The other two NETS daily files (checked 2026-09-08, 1–2 Sep samples)

Brian supplied, per day, besides the MerchantConnect STDRPT01 CSV already
ingested: `EFTPOS_CASHCARD_H06228_STDRPT01_<date>_NEW.csv` (raw H/D/T layout)
and `NPXDRPTS_H06228_00_<date>0000_STDNPX…_Daily NPX Report System for<D+1>.csv`
("MerchantConnect Credit Card Daily Batch Report").

| file | what it is | datetime format | amount | reversal signal |
|---|---|---|---|---|
| `MCONNECT_…STDRPT01` (ingested) | all products: EFTPOS, Scheme Credit/Debit, CROSS BORDER, FLASHPAY, Logon | `dd/mm/yyyy` + `H:i:s(.000)`, local SGT | S$ | own line, negative amount, Reversal Code = Y |
| `EFTPOS_CASHCARD_…STDRPT01` (raw) | **the same report, byte-for-byte the same rows** (2,053 = 2,053 on 1 Sep; the 5 / 7 `Y`-flagged rows are exactly prod's reversal rows) | ISO `YYYY-MM-DDTHH:MM:SS.000`, local SGT | **cents** | col 26 = Y, negative cents |
| `NPXDRPTS_…` (credit batch) | **only the Scheme Credit/Debit subset**, seen from the credit-acquiring side: 1,366 = 1,366 rows on 1 Sep, every row present in the other file within ±1 s | `dd/mm/yyyy` + `HH:MM:SS`, local SGT | S$ + NET less MDR | `Reversal Indicator` / `Void Txn Indicator` columns — all N on both days |

So: **no new transactions, no new reversals, nothing that matches an
unmatched line or an orphan** — the 16 + 26 unmatched lines of 1–2 Sep are
in all three files with the same time and amount. The only extra keys (NPX
Merchant Ref Number, raw-file STAN/approval code, masked PAN) have no
counterpart on the TRADE frame.

What NPX does add, if ever wanted: the **credit TID ↔ EFTPOS TID map**
(4799xxxx → 23xxxxxx, 265 pairs derived from unique time+amount matches,
1:1) and **MDR net amounts** for settlement accounting. Not needed for
matching.

**Finding that matters:** across all reports ever ingested, failed
single-item sales whose line was captured split by product as
EFTPOS 164 reversed / 58 not, CROSS BORDER 33 / 1, FLASHPAY 0 / 11, and
**Scheme Credit/Debit 0 reversed / 288 not**. NETS reverses EFTPOS and
cross-border failures; **a Visa/MasterCard failure is never reversed in any
of the three files** (the NPX void columns stay N even on the following
day). A scheme-card customer is made whole only when the terminal voids
before capture (the "never captured" bucket), otherwise charged. This
sharpens Part 3: the per-TID flag should carry the split ("EFTPOS reverses;
scheme voids or charges"), and the liabilities list should show the
product.

**Refund-side sync verified for 1–2 Sep:** every matched purchase paired
with a reversal carries `is_refunded = 1`, `auto_refund_source =
settlement_report_reversal`; the one open ticket on such a sale
(RF-260901014) was crossed by the reconciler (`auto_refund_detected = 1`,
recommendation `reject`). Four reversal-paired purchase lines have no TRADE
(23082822 $4.30 on 1 Sep; 23082830 $1.70, 23100689 $2.00, 23104111 $1.80
on 2 Sep) — those are Part 2 orphans that would be created already REFUNDED.
One reversed sale (23102933 $3.40, 2 Sep) has code 0 — the "dispensed but
reversed still counts as revenue" case from Part 4 item 2.

**Parser note:** the ingested format is the right one to keep uploading. If
the raw `EFTPOS_CASHCARD` layout is ever uploaded instead, the parser needs
only: split the ISO datetime on `T`, drop `.000`, and treat the amount
column as cents (not S$). The NPX layout is parseable by the existing
MerchantConnect parser as-is (same "Product … Terminal ID" header) but must
NOT be uploaded alongside the STDRPT01 file — it would double every scheme
sale line on a different TID series.

**Direct test of "are the never-captured failures credit-card taps hiding in
the credit report?" (31 Aug – 2 Sep):** 78 failed single card sales on bound
terminals with no matched line were searched in both extra files by terminal
(EFTPOS TID and its mapped credit TID), exact cents and the matcher's window.
Result: **0 of 78** are in either file as their own line. 18 have a line 30–50 s
AFTER the failed TRADE — every one of those is already matched in prod to a
code-0 sale 11–17 s after the line, i.e. the customer tapped again and got the
product; the failed tap itself was never captured. So "never captured" =
voided before capture holds, and the credit report cannot recover any of
them. (Caveat: the 31 Aug day is only partially covered by the 0901 file; the
1–2 Sep conclusion is complete.) Side find: vend 2815 carried two live NETS
bindings on 31 Aug (23005589 and 23107348) — one failed sale is counted under
both TIDs; fix the binding overlap.

---

# Part 6 — Card TRADEs with no NETS line, all 37 ingested reports (1 Aug – 6 Sep)

Question (Brian): are the NETS-method TRADEs that have no report line all
channel errors? **No.** Single-item card sales (payment method Card Terminal,
machine bound to a NETS TID, retained-credit rows excluded):

| sale class | line matched | reversed | **no line** |
|---|---|---|---|
| channel error | 357 | 197 | **969** (voided before capture — Parts 3/5) |
| dispensed (0/6) | 79,175 | 5 | **2,631** ($5,292) |
| multiple purchase | 5,226 | 0 | **67** |

The 2,631 dispensed-no-line break down as:

1. **1,782 (68%, $2,819) on 9 Nets-Auresys-family terminals** — 23012480,
   23012481, 23077326/327/329/330, 92023001/002 (+23113x). For those TIDs
   the H06228 MerchantConnect file carries only 40–60% of the machine's card
   sales (e.g. 23012480: 505 sales, 197 lines; 92023002: 253 sales, 74
   lines), both EFTPOS and scheme products. That is a **report coverage
   gap**, not a machine fault: Auresys traffic settles partly elsewhere.
   Consequences: (a) Part 3's "Auresys 46% made good" is an artefact —
   classify Auresys TIDs as **unknown** until their own report exists;
   (b) the reconciler's "no line on a bound terminal → not captured" verdict
   is wrong for these TIDs — bind them under their own provider (`auresys`)
   so `isDayFinal` / `STATE_NOT_CAPTURED` never fire on them; (c) get the
   Auresys settlement export. Note 23077328 carries the 23077 prefix but is
   company "Nets" with full coverage — key on `card_terminal_units.company`,
   never on the TID prefix.
2. **299 on 6 Sep** — the last ingested day; its late sales sit in the 7 Sep
   file. Not final, ignore.
3. **~550 real cases over 36 days (~14/day, 0.65% of dispensed card sales)**
   outside Auresys. Sampled on 1–27 Aug (352 sales):
   - **40% have a failed card sale on the same machine within the previous
     10 minutes** — the retained-credit re-vend signature (the earlier
     charged failure's credit was consumed, no card presented, so no line).
     These are on pre-v303 APKs where `RetainedCreditSettlementRecorder`
     cannot flag them (no `CSHL_ARMED_MS`).
   - **16% have an unmatched line of the same amount on the same TID and
     day** — the money IS in the report; the matcher missed it (outside the
     −60/+300 s window: machine clock drift, or the line was claimed by a
     neighbouring same-price sale). Worth a second matching pass with a
     wider window restricted to lines that are still unmatched.
   - ~44% unexplained (~240 sales, ~$500 in 5 weeks): candidates are lines
     claimed by a double-tap sibling, binding gaps, cutover-hour edge cases.
     Small enough to list per machine and eyeball.

Matcher blind spot found on the way: the "cashless terminal" candidate rule
(`payment_gateway_id IS NULL AND code > 0`) also admits Grab Mart, Free Vend,
Passcode, Remote Dispense and HID Card rows. Grab Mart rows on bound machines
(~180 in the period) can steal a same-amount line inside the window. Restrict
candidates to the Card Terminal method (id 2).

## Part 3 addendum — partner workbook v3 as the seed (2026-09-08)

`Nets_AutoRefund_Verification_15Aug-6Sep2026_3.xlsx` applies the two methods
verified here (reversal line, or error with no line at all) to 1,034 failed
single card sales, 15 Aug – 6 Sep: 786 made good, 248 charged ($692). Its
per-TID status (202 supports / 40 does not / 83 unknown) is the right
evidence but the wrong rule, so it is used as **reference + batch column**,
not copied. Corrections applied when deriving
`database/data/card_terminal_auto_refund_seed_2026-09-08.csv`:

1. Code 6 ("Microswitch pressed over time", 9 rows) is dispensed, not a
   qualifying error — dropped (Brian's rule).
2. Test rigs dropped: 2003 Kent office (confirmed by Brian 2026-09-08; 24
   auto / 6 unrefunded, $0.10–$1.20 taps) and 2031 bench. 2638 "JB IT
   Testing" is kept as a real machine (not confirmed as test) — it changes
   no flag either way.
3. "Supports = at least one event" is too weak: batch #1/#2 TIDs with 1–3
   voids against 5–13 charges (23082822 1/13, 23082830 2/11, 23012476 1/5)
   are NOT auto-refunding terminals. Rule used: ≥ 5 own events → own rate
   (≥ 70% yes, ≤ 30% no); otherwise the batch's pooled rate (Nets #1 16%,
   #2 13%, #3–#7 88–91%); Auresys → unknown (coverage gap, Part 6).

Result: 243 yes (49 on own events, 194 by batch), 57 no (10 own, 47 by
batch), 25 unknown (Auresys). 29 TIDs differ from the sheet: 7 Auresys
"supports" → unknown, 14 batch #1/#2 "supports" → no, 8 batch #3–#7 "does
not" (a single charged event) → yes by batch. The seeder imports `batch`
and this flag with `flag_basis`; the weekly classifier recomputes from
`card_settlement_state` thereafter and never overwrites a `manual` row.


## Phase 1 review outcome (2026-09-09)

Eight-angle review of commits 91f9bfb1bc + 2095ad9629 found 10 items, all
fixed in the follow-up commit: Eloquent success/error scopes resolved ids
from codes instead of FK ids [1, 5]; `success_qty` backfill and ingest now
share `DISPENSED_CODES`, `dispensed_qty` shares the new `DROPPED_CODES`
(0, 6, 7, 9); totals-JSON error count uses `sqlFault`; the ingest guard now
EXISTS (`VendChannelError::forFrameCode()` refuses 99 with a warning — both
frame-code lookups go through it); `SaleStatus::itemDispensed` delegates;
repeated fragments hoisted to one local per method; the three hand-rolled
`IN (saleList()) OR IS NULL` sites use `sqlSale`; guard test also catches FK
id lists, `!= 0`, literal `IN (0, 6, 99)` and `[0, 6, 7, 9]`; tests pin the
refund rule (0/6/99 not a fault) and the guard. Prod fact used: 0 dangling
`vend_channel_error_id` in 5.06M rows and `code` is NOT NULL, so the five
SQL shapes are equivalent today (collapse = follow-up).

Follow-ups noted, not done: Machine Health has no bucket for TRADE loss once
99 exists (add a `not_found_count` column in the marker commit); Vue still
hard-codes `!= 0 && != 6` in `Transaction.vue` and
`DeliveryPlatformOrder/Index.vue` (Phase 2 surfaces); `scopeCountsAsSale`
(settlement gate) vs `DispenseVerdict::isSaleCode` naming — rename the scope
to `settled()` when next touched.


## Build log

| date | phase | commits | what landed |
|---|---|---|---|
| 2026-09-08 | 1 | 91f9bfb1bc, 2095ad9629 | `App\Support\DispenseVerdict`; 105 inline predicates rewritten; guard test; CLAUDE.md section |
| 2026-09-09 | 1 review | 739e9b2664 (pushed) | 10 review findings closed: code-resolved scopes, one `success_qty` writer set, `DROPPED_CODES`, ingest guard `VendChannelError::forFrameCode()`, hoisted locals, wider guard, refund-rule + guard tests |
| 2026-09-09 | 2 | (this commit) | migration seeds code 99 + `settings.missing_trade_marked_until`; `SaleStatus` Dispense blank for any row without a TRADE (`NO_TRADE`, replaces Pending / No report labels); `itemDispense(99)` blank; `DispenseVerdict::displayCode()` → "NA" in both CSV exports (header + item rows) and `VendTransactionResource.vend_channel_error_code_display`; tests `NotFoundChannelErrorTest`, `FrameCodeGuardTest`, `SaleStatusTest` 99 cases |

| 2026-09-09 | 3 | (this commit) | `config/sales.php`; `App\Services\Sales\MissingTradeMarker` + `MissingTradeMarkResult` + `sales:mark-missing-trade {--from} {--to} {--apply}` (dailyAt 00:01, `store:previous-day-vend-records` moved to 00:06); `App\Support\TradeTimestampResolver` / `ResolvedTradeTime` wired into `createVendTransaction` (30 d back / 5 min ahead, `meta_json.frame_time.rejected`); `App\Services\Sales\DirtyDayRegistry` (Redis set, array store in tests) + `LateTradeTracker` (dirty day + `meta_json.missing_trade.cleared_at`) called after a fresh TRADE row and after a pre-created row is filled; `reconcile:sales-rollups --dirty` (dailyAt 02:00, lists locked months); tests `TradeTimestampResolverTest`, `MissingTradeMarkerTest`, `DirtyDaysReconcileTest`; CLAUDE.md section |

Seed: on prod after deploy, `sales:mark-missing-trade --from=2026-08-01`
(report) then `--apply` — identical to what the first 00:01 run would do
with a NULL watermark. No rollup rebuild follows (99 moves no figure).

Next: Part 2 (NETS orphans) and Part 3 (per-TID will-auto-refund flag).
