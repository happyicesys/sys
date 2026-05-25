# Plan: Merge "Payment Gateway Transactions" into "Sales Transactions"

> Status: DRAFT for review. Nothing implemented yet. This is an architecture/impact analysis.

## 1. The goal in one sentence

For payment methods whose `payment_methods.payment_gateway_id` is **not null** (Omise / Midtrans / Fiuu / future), create the `vend_transactions` row **when the gateway reports "paid"** — using the data we already captured at QR-request time — instead of waiting for the machine's TRADE callback. The machine's TRADE then becomes a *confirmation* that flips a "found in transaction" flag and overwrites provisional fields with ground truth.

Non-gateway methods (cash = code 0, card = code 1, etc.) keep today's behaviour: the machine completes the purchase, sends TRADE, and `vend_transactions` is created at that moment.

---

## 2. Validation of your core assumption

> "at this moment, we will already get the orderid and i think might also know which product purchased: help me validate this"

**Confirmed — yes, with caveats.** At QR-request time, `PaymentGatewayService::createPaymentQrText()` already persists everything we need on the `payment_gateway_logs` row (before paid):

| Data | Where it's stored | Notes |
|------|-------------------|-------|
| `order_id` | `payment_gateway_logs.order_id` (from metadata) | The join key to TRADE later. |
| Amount | `payment_gateway_logs.amount` + `request['PRICE']` | **Stored in major units (dollars)**, not cents — see §6.4. |
| Channel(s) + product(s) | `payment_gateway_logs.vend_channels_json` | Built from `slotIdList` (multi) or `SId` (single); each entry carries `id`, `code`, `product_id` with `product:{id,code,name}` eager-loaded. |
| Vend | `vend_id`, `vend_code` | |

So at "paid" we know order id, machine, channel(s), product(s), and intended price. This is enough to build a `vend_transactions` row (single) or row + `vend_transaction_items` (multi, `is_multiple = true`).

**Caveats to design around:**

1. **Product is only known if the channel had a product mapped at request time.** `vend_channels_json` falls back to `{code, product: null}` when the channel has no product. So `product_id` can be null on the pre-created row (same as today's TRADE path when mapping is missing).
2. **The amount is the *requested* price, not what the machine settled.** For single vends these match; for multi-vends a partial dispense means the machine's TRADE reports a different success set than what was requested/charged.
3. **"Dispensed?" is NOT known at the paid moment.** `is_dispensed` is set later by `GetPurchaseConfirm` when the machine ACKs the dispense signal. So the row is **provisional at creation** and its sale/refund classification resolves afterwards (see §4).

---

## 3. Current architecture (as-is)

Two parallel records that link by `order_id`:

```
QR requested
  └─ PaymentGatewayService::createPaymentQrText()
       → payment_gateway_logs (status = PENDING, vend_channels_json, amount, order_id)

Gateway webhook "paid"
  └─ PaymentController::createPaymentGatewayLog()  (status → APPROVE, approved_at set)
       ├─ LogNofoundTxnIfStillMissing (delay 5 min)  ← "no-found" anomaly counter
       └─ processPayment() → VendDispenseService::dispense()  (tell machine to vend)

Machine ACKs dispense
  └─ GetPurchaseConfirm → payment_gateway_logs.is_dispensed = true

Machine posts TRADE
  └─ VendDataService (case 'TRADE') → CreateVendTransaction
       → VendTransactionService::create()
            ├─ dedup by order_id (returns null if a row already exists)
            ├─ createVendTransaction()  ← THE row is born here today
            ├─ links payment_gateway_log_id (this is what makes "Found in Transaction" = true)
            └─ DecrementVendDailyStat('nofound_txn')   ← self-heals the anomaly counter
```

**"Found in Transaction?"** on the PG page = "does a `vend_transaction` with this `payment_gateway_log_id` exist?" It is a *derived* relation check, **not a stored column**.

**Auto-refund paths today:**
- `RefundPaymentGatewayEveryTenMinutes` (scheduled): `status = APPROVE AND is_dispensed = false AND approved_at older than 10 min` → refund. **Omise only** (Midtrans/Fiuu hit `default:` = no-op).
- `PaymentController` line ~205: paid webhook arrives >210s after QR creation (expired) → refund (Omise only).
- `HandleFailedVendTransaction`: a TRADE-created row that failed to dispense → refund (Omise only).

**The variance you're solving:** gateway says paid + dispensed, but the machine never posts TRADE → no `vend_transaction` → the sale is invisible to Sales Transactions / reports / billing. (This is exactly the gap we patched today by adding "dispensed-but-not-found" gateway amount into the Total Sales QR figure — see §7.1, that patch must be removed when this lands.)

---

## 4. Proposed design (to-be)

### 4.1 New lifecycle for gateway-backed transactions

Treat the `vend_transaction` as a small state machine instead of a write-once record:

| Event | Action on `vend_transactions` |
|-------|-------------------------------|
| Gateway "paid" (APPROVE) | **Create** row from `payment_gateway_logs`: `order_id`, `payment_gateway_log_id`, `payment_method_id` (gateway method), product/channel/amount (scaled to cents), `is_payment_received = true`, `transaction_datetime = approved_at`, **new** `is_found_in_transaction = false`, and a **new pending/settlement state** so reports don't count it as a confirmed sale yet (see §4.3). Multi-channel → also create `vend_transaction_items`. |
| Dispense ACK (`GetPurchaseConfirm`) | Optionally stamp a `dispensed_at` / keep using PG log's `is_dispensed`. |
| TRADE arrives (same order id) | **Update** the existing row: set `is_found_in_transaction = true`, overwrite provisional fields with machine ground truth (error codes, `success_qty`/`dispensed_qty`, real per-item results, possibly amount), recompute revenue/GP. **Do not create a second row.** |
| 10-min no-dispense → refund | Mark row as refunded / not-a-sale (`is_refunded = true` or a dedicated state) so it drops out of sales totals. |

### 4.2 Sale-vs-not rules (your stated rules, encoded)

- **Dispensed + not found in transaction → count as sale.** (Machine vended but never reported.)
- **Not dispensed + not found in transaction → refund → not a sale.**
- Found in transaction (TRADE arrived) → machine ground truth wins, classified exactly as today.

### 4.3 The hard part: how reports classify a *provisional* row

Today, "success" in nearly every aggregation = `vend_channel_errors.code IN (0,6) OR code IS NULL OR is_multiple = true`. A freshly pre-created row has **no error code yet (NULL)** — which today's logic treats as **success**. That means a paid-but-not-yet-dispensed row (which might get refunded in 10 minutes) would **transiently count as a sale** across every report.

**Recommendation:** introduce an explicit settlement state on `vend_transactions` (e.g. `settlement_status`: `pending` → `dispensed`/`sale` / `refunded`) and update the shared "success" predicate so `pending` and `refunded` are excluded. This touches the central aggregation predicate used in many places (see §5), so it must be done in one shared scope, not copy-pasted.

### 4.4 TRADE path rework (critical)

`VendTransactionService::create()` currently **returns null on duplicate order id**, discarding the payload. If we pre-create the row, the machine's real TRADE would be thrown away. The dedup branch must become: *if a gateway-origin row already exists for this order id → **update** it (fill ground truth + flip `is_found_in_transaction`) rather than skip.* All the downstream dispatches that currently fire on create (totals sync, channel-error log, callbacks, dcvend, voucher) must be reconciled so they fire **once** and at the right time (see §5).

---

## 5. Side effects / blast radius

Severity: 🔴 high (financial/billing or counts), 🟡 medium (reports/UX), 🟢 low (cleanup/observability).

### 5.1 Daily rollups & denormalized aggregates
- 🔴 **`vend_records`** via `StoreVendsRecord` — daily amount/qty/revenue/GP rollup keyed on `transaction_datetime`. Gateway sales shift to paid-time and now include previously-missing "paid-not-reported" sales. (You correctly anticipated this.)
- 🔴 **`gp_metrics`** via `GpMetricsAggregator` — revenue/gross-profit aggregation. Provisional rows need a unit cost at creation (we *can* derive it from product mapping) or GP will be wrong until TRADE.
- 🟡 **`Vend.vend_transaction_totals_json` & `Customer.totals_json`** via `SyncVendTransactionTotalsJson` — today/2d/7d/30d buckets, error rates. Re-sync semantics change; provisional/refunded rows must be excluded.
- 🟡 **`vend_daily_stats` `nofound_txn` metric** + `LogNofoundTxnIfStillMissing` + `DecrementVendDailyStat` — this entire anomaly mechanism is built around "row doesn't exist yet". Once the row always exists at paid-time, "found" changes meaning to "machine reported (TRADE)". This counter logic must be reworked or retired.

### 5.2 Billing / customer-facing money 🔴
- 🔴 **`CustomerSummaryAggregator` → `CustomerPeriodSummary.sales_cents` → `CustomerInvoiceService`** — sales feed **invoices** sent to the CMS. Any change to what counts as a sale (provisional rows, refund timing, paid-time vs dispense-time bucketing across a month boundary) **directly changes invoiced amounts**. Treat as the highest-risk area; validate against `ValidateCustomerSummarySales`.
- 🔴 **`PerformanceReportContentService`** — location/PS/utility fee calcs derived from the same sales figures.

### 5.3 Reports & dashboards 🟡
- `DashboardController` (day/week graphs, best/worst performers), `ReportController` (sales/GP), `MachineHealthDashboardService` ("no transactions" stockout detection — a pre-created row could mask a genuine no-vend), and their Vue pages.

### 5.4 Exports 🟡
- `ExportVendTransactionCsv` / `VendTransactionExport` — new rows + nullable provisional fields (error code, qty) need sensible column output; `is_found_in_transaction` likely wanted as a column.

### 5.5 Transaction creation & external callbacks 🔴
- `VendTransactionService::create()` dedup/update rework (§4.4).
- 🔴 **`SendOperatorCallback` (`transaction_upload`)** — currently fires once at TRADE-time. With pre-creation it would fire at paid-time (before dispense, with provisional data) and possibly again at TRADE. External operator integrations may receive earlier/duplicate/provisional payloads. Decide: fire on settle only, or send create + update.
- `SendDataToDcvend` — DCVend member integration timing shifts similarly.
- `updateVendPaymentTimestamps` (`last_vend_transaction_at`, etc.) would stamp at paid-time.

### 5.6 Cleanup / dedup jobs 🟢🟡
- `DeleteDuplicatedTransactions`, `RemoveDuplicatedVendTransaction`, `RemoveYesterdayDuplicatedVendTransaction`, `RemoveOddTransactions` — these assume TRADE-origin dedup semantics. A pre-created + TRADE pair could look like a duplicate and be wrongly deleted. Audit each.
- `HandleFailedVendTransaction` — interplay with pre-created rows and the refund trigger.
- Backfill commands (qty, cashless_mfg, unit cost, GP) — mostly unaffected but should be aware of provisional rows.

### 5.7 Frontend
- `Transaction.vue` (Sales) — gateway rows now appear here; add `is_found_in_transaction` / settlement state columns. The page we edited today (Total Sales / Transaction Count cards) — see §7.1.
- `PaymentGatewayTransaction.vue` — once merged, this page may become a filtered view of Sales Transactions (or be retired). That's the actual "merge" you're describing.

---

## 6. Key decisions / open questions

1. **Where exactly to create the row** — in `PaymentController::createPaymentGatewayLog()` on APPROVE, or in `processPayment()`, or a dedicated `CreateGatewayVendTransaction` job (recommended: a job, for retry-safety and to keep the webhook fast).
2. **`transaction_datetime` = paid time (`approved_at`)?** Recommended yes (dispense is seconds later). Flag the **day-boundary edge case** (paid 23:59, TRADE 00:01) for billing.
3. **Settlement state model** — add `is_found_in_transaction` (boolean) *and* a settlement state (`pending`/`sale`/`refunded`), or overload existing `is_payment_received` / `is_refunded`? Recommended: explicit new fields + one shared "is counted as sale" query scope.
4. **Refund coverage gap** 🔴 — auto-refund-on-not-dispensed exists for **Omise only**. Midtrans/Fiuu paid-not-dispensed rows would *not* be auto-refunded; decide how those classify (pending forever? manual? implement their refund?). This matters because "not dispensed → not a sale" relies on the refund actually happening.
5. **Multi-vend partial dispense** — when TRADE reports some items failed, reconcile amount/items against the provisional row.
6. **Backfill** — do we retro-create rows for historical paid-but-not-found gateway logs, or only apply going forward? (Affects past reports/invoices.)

### 6.4 Amount unit mismatch (already confirmed)
`vend_transactions.amount` is in **minor units (cents)** (`VendTransactionResource` does `/100`); `payment_gateway_logs.amount` is in **major units (dollars)**. Pre-creation must scale by `10^currency_exponent`. (This is the same mismatch handled in today's Total Sales patch.)

---

## 7. Critical interactions to not forget

### 7.1 Remove today's Total Sales workaround 🔴
Earlier today we added a block in `VendController::transactionIndex()` that *adds* "dispensed-but-not-found gateway amount" into the QR Payment Gateway figure and the Total Sales headline. Once this architecture creates real `vend_transactions` for those same payments, that block would **double-count** them. **It must be removed as part of this rollout.**

### 7.2 The "no-found" anomaly tooling
`LogNofoundTxnIfStillMissing` / `nofound_txn` daily stat / the Customer Index anomaly badge all assume the row is absent until TRADE. Their meaning inverts under the new model — plan to rework or retire them together.

---

## 8. Suggested phased rollout

1. **Schema + shared predicate first.** Add `is_found_in_transaction` + settlement state to `vend_transactions`; centralise the "counts as sale" logic into one query scope and migrate all aggregators to use it (no behaviour change yet). This de-risks §5 by making the predicate a single edit point.
2. **Pilot on one gateway + one operator/machine.** Implement paid-time creation behind a per-operator or per-payment-gateway feature flag. Keep TRADE as the updater. Verify on a test machine that paid→create→dispense→TRADE→update produces exactly one correct row.
3. **Reconcile reports & billing** on the pilot data; run `ValidateCustomerSummarySales` and compare `vend_records`/invoices before vs after.
4. **Handle refund gap** for Midtrans/Fiuu (decision from §6.4).
5. **Remove the Total Sales workaround** (§7.1) and rework the no-found tooling (§7.2).
6. **Roll out**, then converge the two UI pages (make Payment Gateway Transactions a filtered view of Sales Transactions, or retire it).

---

## 10. Revision 2 — locked decisions & answers (supersedes conflicting notes above)

### 10.1 No new table / no UNION
`CreateGatewayVendTransaction` is a queued job that `INSERT`s straight into the existing `vend_transactions` table. There is **no separate gateway-records table and no UNION**. Gateway payments become normal `vend_transactions` rows (with `payment_method_id` = the gateway method). The `PaymentGatewayTransaction` page becomes a filtered view of `vend_transactions` (`payment_gateway_log_id IS NOT NULL`) or is retired.

### 10.2 `transaction_datetime = approved_at` (paid time) — LOCKED
On pre-create, set `transaction_datetime = payment_gateway_logs.approved_at`. On TRADE update, **do not move it**. One residual edge for awareness only (no action): a payment paid at 23:59 that the machine reports at 00:01 lands in the paid day's bucket — correct for "money received" billing, just noted.

### 10.3 Settlement state model — RECOMMENDED (perf-first, zero backfill)
Add **two `TINYINT` columns** to `vend_transactions`, both `NOT NULL` with a default, added as the **last columns** so MySQL 8.0 applies `ALGORITHM=INSTANT` (metadata-only, no rebuild, no lock on the 4M rows):

| Column | Type / default | Meaning |
|--------|----------------|---------|
| `is_found_in_transaction` | `TINYINT(1) NOT NULL DEFAULT 1` | 1 = machine reported via TRADE. Legacy + all non-gateway rows default to 1 (correct: they *are* TRADE-born). Gateway pre-create sets 0, flips to 1 when TRADE arrives. |
| `settlement_status` | `TINYINT UNSIGNED NOT NULL DEFAULT 2` | 0 = pending (paid, dispense outcome unknown), 1 = refunded/void (not a sale), 2 = settled (counts as sale, then normal error-code logic applies). Legacy 4M rows default to **2**, so existing success logic is unchanged. |

**Why this is the performant + correct choice:**
- Defaults are already correct for every existing row → **no backfill, no UPDATE pass over 4M rows**.
- The shared "counts as a sale" predicate gains exactly one cheap term:
  `settlement_status = 2 AND (vend_channel_errors.code IN (0,6) OR code IS NULL OR is_multiple = true)`.
- This sidesteps the "NULL error code transiently counts as a sale" trap: a pending gateway row is `settlement_status = 0` and is excluded regardless of its (still-empty) error code. When it settles we set `settlement_status = 2` **and** `vend_channel_error_id` = success (code 0).
- `settlement_status` is ~99.9% one value, so it is a **post-filter, not an index lead** — do **not** create a standalone index on it (low selectivity = wasted B-tree + write cost). Reuse the existing `idx_vtrans_datetime_amount_operator`. Only if `EXPLAIN` later shows heap reads, extend that covering index to include `settlement_status` as a trailing column (matches the team's existing covering-index methodology).

**Centralise the predicate** in ONE Eloquent scope (e.g. `VendTransaction::scopeCountsAsSale()`) and migrate every aggregator (`GpMetricsAggregator`, `CustomerSummaryAggregator`, `SyncVendTransactionTotalsJson`, `StoreVendsRecord`, dashboard/report/export queries, `ValidateCustomerSummarySales`) to call it. Single edit point = no drift.

### 10.4 Refund behaviour — LOCKED
- Auto-refund stays **Omise-only**. Midtrans/Fiuu/future do not refund unless explicitly added later.
- **Multi-vend partial dispense:** if *some* (not all) items fail, **treat the whole transaction as a sale** (`settlement_status = 2`, `is_multiple = true`, per-item results recorded on `vend_transaction_items`). Partial refund is intentionally not wired.
- **Multi-vend total failure (all items fail):** follow the current refund protocol — Omise → full refund → `settlement_status = 1` (not a sale). Non-Omise → no refund fires today, so the row would be "paid, nothing dispensed, not refunded". See open question 10.7(a).

### 10.5 Amount sync — minor vs major units — LOCKED RULES
Single source of truth for money collected = `payment_gateway_logs.amount` (**major units / dollars**). `vend_transactions.amount` is **minor units / cents**.
- **On pre-create:** `vend_transactions.amount = round(payment_gateway_logs.amount * 10^currency_exponent)` (e.g. ×100). Use the operator's country `currency_exponent`, same helper as elsewhere (`auth()->user()->operator?->country?->currency_exponent ?? 2`).
- **On TRADE update: keep the gateway amount as authoritative for `amount`** (it is the money actually charged). Use TRADE only for dispense outcome (error codes, `success_qty`, `dispensed_qty`, per-item results) — **do not overwrite `amount` with the machine's `Price`.** This deliberately avoids the existing TRADE quirk in `VendTransactionService::processInput()` where `Price` is `×100` for multi but raw for single, and prevents any double-conversion.
- **Per-item `unit_price_amount` (multi):** derive from `vend_channels.amount` (already cents), mirroring `determineUnitPriceAmount()`.
- **Revenue / GP:** computed from the cents `amount` exactly as today, so `gp_metrics` / GP columns stay consistent.
- Note: today's Total Sales patch (which scales gateway dollars→cents for display) confirmed this exact mismatch — same conversion, now done once at write time.

### 10.6 Migration performance plan (4M rows) — LOCKED
1. **Columns:** one migration adding the two `TINYINT` columns as the **last** columns, `NOT NULL DEFAULT`, MySQL 8.0 `INSTANT`. Verified instant, no rebuild, no lock, **no backfill**. Wrap in an `indexExists`/column-exists guard like the team's other migrations.
2. **No new index by default.** Do not index `settlement_status`. 
3. **Verify the TRADE-update lookup is indexed:** the update path finds the pre-created row by `order_id` (+ `vend_id`). Confirm an index on `vend_transactions.order_id` exists (the current dedup already queries it, so likely present); if not, add ONE online index (`ALGORITHM=INPLACE, LOCK=NONE`). This is the only index that might be needed and it directly serves the new hot write path.
4. **Anomaly index deferred:** if the merged UI needs fast `is_found_in_transaction = 0` filtering, add a composite online index then — not now.

### 10.7 Dispense-detection model — CLARIFIED
- **Non-gateway (cash/card/etc.):** dispensing happens entirely inside the machine; the machine then posts TRADE. We treat the arrival of TRADE as a completed dispense ("natural"). Unchanged.
- **Gateway / QR:** the server actively pings dispense status — `GetPurchaseConfirm` sets `payment_gateway_logs.is_dispensed = true` when the machine ACKs the dispense signal. So for gateway rows, `is_dispensed` is the authoritative "did it vend?" signal, independent of whether TRADE ever arrives.

This drives settlement of a pre-created (pending) gateway row:
- `is_dispensed = true` → settle as **sale** (`settlement_status = 2`).
- `is_dispensed = false` after the grace window → **not a sale**: Omise → refund → `settlement_status = 1`; non-Omise → no refund fires, leave `settlement_status = 0` (excluded from sales) and surface for manual review (money held). 

### 10.8 Settler — LEAN, mostly event-driven (decision: yes, keep it light)
Primary path is **event-driven, no scan**:
- In `GetPurchaseConfirm` (already runs on dispense ACK): when it flips `is_dispensed = true`, also settle the linked pre-created `vend_transaction` inline (`settlement_status 0 → 2`). One indexed update by `payment_gateway_log_id`/`order_id`.
- When TRADE arrives: settle + set `is_found_in_transaction = 1` (the existing update path).

Backstop **sweeper** (cheap, runs on the small table): drive it from `payment_gateway_logs` (orders of magnitude smaller than the 4M `vend_transactions`), not from `vend_transactions`. Query `status = APPROVE AND approved_at < now()-grace` and reconcile any whose linked row is still `settlement_status = 0`. Because it scans the small PG table over a short recent window, it stays fast and never sequentially scans the 4M-row table. No new index on `vend_transactions` required for it.

### 10.9 DCVend — DROPPED
DCVend project is discontinued. Ignore `SendDataToDcvend` timing entirely.

### 10.10 What the `nofound_txn` tooling is, and what to do
Today: when a gateway payment is approved, `LogNofoundTxnIfStillMissing` waits 5 min and, if no `vend_transaction` has appeared, bumps a per-machine daily counter (`vend_daily_stats`, metric `nofound_txn`) that surfaces as an **anomaly badge on the Customer Index page** — i.e. "this machine took money but produced no transaction record". When the transaction later lands, `DecrementVendDailyStat` undoes it, so the badge reflects only currently-unresolved cases.

Under the new model a row is **pre-created at paid-time, so a transaction always exists immediately** → the counter would always read zero and lose meaning. The equivalent useful anomaly becomes "paid but machine never confirmed dispense **and** never reported TRADE" = an aged `settlement_status = 0` / `is_found_in_transaction = 0` row. **Recommendation:** repoint the same +1/−1 mechanism at that condition (so the Customer Index badge keeps working with the new semantics), or retire it if the badge isn't relied upon. Low priority; can ship after the core change.

### 10.11 Scope: only the Transaction table / page — CONFIRMED
The `PaymentGatewayTransaction` page stays as-is for now; all modification effort focuses on `vend_transactions` and the Sales Transactions page. **One required consistency fix:** the PG page's "Found in Transactions?" column today checks "does a linked `vend_transaction` exist". Since a linked row will now always exist (pre-created), that column must switch to reading the new `vend_transactions.is_found_in_transaction` flag (true only after TRADE), otherwise it shows green for everything. This is a read-only display tweak, not a data-source change.

---

## 11. Full audit — consolidated affected areas + newly-found gaps (rev 3)

### 11.A Consolidated map (area → impact → solution)

| Area | Impact of the change | Solution |
|------|----------------------|----------|
| `vend_transactions` write path | Row now born at paid-time, updated by TRADE | Single idempotent upsert by `order_id` (see G1) |
| `VendTransactionService::create()` | Create-or-skip discards machine data if row pre-exists | Rework to create-or-**update**; fill ground truth on TRADE |
| Success/sale predicate (all aggregators) | Provisional rows must not count | `settlement_status = 2` term in one shared `scopeCountsAsSale()` |
| `vend_records` (StoreVendsRecord) | Gateway sales shift to paid-time + include previously-missing sales | Uses shared scope; re-validate daily rollup |
| `gp_metrics` (GpMetricsAggregator) | Revenue/GP per date | Unit cost computed at pre-create from product mapping; shared scope |
| `CustomerSummaryAggregator` → `CustomerPeriodSummary` → invoices | **Billing amounts can change** | Shared scope + validate via `ValidateCustomerSummarySales` before/after |
| `SyncVendTransactionTotalsJson` / totals_json | Denormalized buckets | Fire on settle, not provisional; shared scope |
| Dashboard / ReportController / MachineHealth | Date-bucketed sales; stockout detection | Shared scope; confirm "no-txn" stockout still meaningful |
| `ExportVendTransactionCsv` | New rows, provisional/nullable fields | Add `is_found_in_transaction` column; null-safe output |
| Amounts (minor/major units) | dollars vs cents | Convert at pre-create; gateway amount authoritative (rev2 §10.5) |
| `transaction_datetime` | = `approved_at`, fixed at create | Locked (rev2 §10.2) |
| Refund paths | Must mark the transaction, not just the PG log | See G2 |
| Downstream jobs (channel-error log, totals, failed-txn, callback) | Fire on create today | Re-dispatch on update path (see G3) |
| PG Transactions page | "Found?" column + filter break | Read new flag for both (see G5) |
| Today's Total Sales patch | Double-counts once rows are real | Remove (rev1 §7.1) |
| `nofound_txn` anomaly tooling | Meaning inverts | Repoint or retire (rev2 §10.10) |
| DCVend | n/a | Dropped — discontinued (rev2 §10.9) |
| Migration on 4M rows | Must be cheap | 2× INSTANT TINYINT, no backfill, no new low-selectivity index (rev2 §10.6) |

### 11.B Newly-found gaps (were NOT covered before this audit)

- **G1 — Race / idempotency between paid pre-create and TRADE (CRITICAL).** The order of the paid webhook vs the machine's TRADE is **not guaranteed** (queue lag, fast machines, retries). If TRADE lands first, it creates the row (old path); the pre-create job must then *update, not insert a second row* — and vice-versa. Today there is no DB uniqueness on `order_id`; `RemoveDuplicatedVendTransaction` keeps the **latest by `created_at`**, so a double-insert could delete the ground-truth TRADE row. **Solution:** one idempotent upsert keyed on `order_id` (+`vend_id`) with `lockForUpdate`, shared by both entry points; whoever runs first creates, the other updates. Optionally add a DB unique index `(vend_id, order_id)` later as a hard guard (requires de-duping existing rows first → heavier, defer).
- **G2 — Refund paths don't touch the transaction.** `RefundPaymentGatewayEveryTenMinutes` (is_dispensed=false after 10 min) and the 210s-late refund in `PaymentController` update only `payment_gateway_logs.status = 98`. They must also flip the linked `vend_transaction` → `settlement_status = 1`, `is_refunded = true`. (`HandleFailedVendTransaction` sets `is_refunded` but not the new state, and only runs from create today.)
- **G3 — Downstream dispatches must move to the update path.** `SyncVendChannelErrorLog`, `SyncVendTransactionTotalsJson`, `HandleFailedVendTransaction`, and the `transaction_upload` operator callback currently fire inside `create()`. For gateway rows the meaningful moment is settle/TRADE, not the provisional pre-create. Ensure each fires exactly once, at settle.
- **G4 — order_id key quirks.** `interface_type '50'` date-prefixes the order id and **vend `2007` skips dedup entirely**. If any gateway/QR payment can run through interface_type 50 or on vend 2007, the upsert key won't match (orphan/duplicate). **Confirm** gateway payments are never interface_type 50 and 2007 is not a gateway machine; otherwise special-case.
- **G5 — PG page "Found in Transactions?" is a column *and* a filter.** `filterIndex` uses `has/doesntHave('vendTransaction')`; since a linked row will now always exist, the filter returns everything. Both the display and the filter must read the new `is_found_in_transaction` flag.
- **G6 — Campaign labels & vouchers at pre-create.** `label_json` (campaign attribution) and voucher redemption arrive via the TRADE payload and are handled in `create()`. Gateway rows created at paid-time would lack campaign labels until TRADE (or forever if TRADE never comes) → campaign reports under-count gateway sales. Vouchers risk double-redeem across pre-create + TRADE. **Confirm** whether QR payments can carry vouchers/campaign labels; if yes, decide where redemption/labelling happens (once).
- **G7 — Provisional JSON & qty columns.** At pre-create, `items_json` / `vend_transaction_json` aren't available and `qty`/`success_qty`/`dispensed_qty` are provisional → the "Total Qty" (dispensed vs purchased) card and any `vend_transaction_json` reader see partial data until settle. Acceptable, but ensure no NOT-NULL/JSON-shape assumption breaks on a provisional row.
- **G8 — DispenseRecord (minor).** `GetPurchaseConfirm` also flips `DispenseRecord.is_vm_receive_dispense_signal`; it's a parallel dispense signal that could corroborate `is_dispensed`. No action needed unless we want a redundancy check.

### 11.C Still to confirm (quick decisions)
1. Can a gateway/QR payment ever be `interface_type 50` or on vend `2007`? (G4)
2. Can QR payments carry vouchers and/or campaign labels? (G6)
3. OK to make refund paths update the linked transaction's state? (G2 — expected yes)
4. Add a DB unique `(vend_id, order_id)` index eventually, or rely on app-level upsert only? (G1)

---

## 12. Revision 4 — answers locked + schema-confirmed refinements

### 12.1 Idempotency (G1) — RESOLVED by existing DB constraint
`vend_transactions` already has `UNIQUE KEY uniq_order_id_vend_id (order_id, vend_id)`. So:
- A duplicate insert is blocked at the DB level (this is what stopped the old APK bug that fired two identical TRADEs within milliseconds).
- `RemoveDuplicatedVendTransaction` should effectively never have anything to delete going forward.
- Implementation = `updateOrCreate(['order_id','vend_id'], [...])` (or insert-on-duplicate-key-update). Whoever runs first creates; the other updates. **No separate race handling needed.**
- Paid-vs-TRADE ordering is safe: QR scan → pay → machine dispenses (≥~10s) → TRADE. Paid reliably precedes TRADE by a wide margin (this applies to QR/gateway methods only).

### 12.2 Refund must update BOTH tables (G2) — LOCKED
Every refund path must update the linked `vend_transaction`, not just the PG log:
- `HandleFailedVendTransaction` (all-items-fail): already sets `is_refunded = true` + dispatches `RefundOmiseJob`; **add `settlement_status = 1`** and make it reachable from the TRADE-update path.
- `RefundPaymentGatewayEveryTenMinutes` (is_dispensed=false after 10 min) and the 210s-late refund in `PaymentController`: today they only set `payment_gateway_logs.status = 98` → **must also** set the linked row `settlement_status = 1`, `is_refunded = true`.

### 12.3 order_id alignment for TXN_SRC 50 (G4) — MUST-SOLVE (gateway CAN be 50)
`interface_type` = machine TXN_SRC; known values `0`, `1`, `50`. For `50`, `create()` prefixes the order id with `Ym`-style digits and cleans up the short version. Since gateway payments can be TXN_SRC 50, and `payment_gateway_logs.order_id` is the **un-prefixed** value:
- **Latent bug to verify:** TXN_SRC-50 gateway payments may already fail to link today (prefixed TRADE order id ≠ un-prefixed PG order id → `payment_gateway_log_id` null, "Found" false). Check real data.
- **Fix for the merge:** `payment_gateway_logs` stores `txn_src`; at pre-create, apply the **same prefix rule** so the pre-created row and the eventual TRADE share one canonical `order_id`. Then the unique key + `updateOrCreate` behave. Set `interface_type` on the pre-created row from the PG log `txn_src`.

### 12.4 Vouchers & campaign labels (G6) — LOCKED
Handle on TRADE only. Pre-create does not redeem vouchers or write `label_json`. If TRADE never arrives, leave them absent (acceptable). This removes any double-redeem risk since redemption stays solely in the TRADE path.

### 12.5 Schema-confirmed write-path notes (from the live CREATE TABLE)
- `amount int NOT NULL DEFAULT 0` — cents. Pre-create writes `round(pg.amount * 10^exponent)`.
- `vend_channel_id bigint NOT NULL` — pre-create **must** set it (single: from `vend_channels_json`; resolve/create the channel like `createVendChannel()` if only a code is known; multi-parent: 0, as today).
- `gst_vat_rate decimal(5,2) NOT NULL` — set from product mapping (0 if no product).
- `effective_transaction_datetime` = STORED generated `coalesce(transaction_datetime, created_at)` — auto-maintained; several reads use it. `transaction_datetime = approved_at` flows through correctly.
- `error_code_normalized` = VIRTUAL generated from `vend_transaction_json->'$.SErr'` (indexed). For a provisional pre-created row `vend_transaction_json` is null → `error_code_normalized` is null (which legacy "success" logic treats as success). **This is exactly why settlement gating is required** — `settlement_status = 0` keeps the provisional row out of sales regardless of the null error code. On settle, set the real `vend_channel_error_id` / json.
- New columns `is_found_in_transaction`, `settlement_status` to be added as the last columns (INSTANT, rev2 §10.3/§10.6). Confirmed not present today.

### 12.6 vend 2007 (note)
Vend `2007` is a beta machine that bypasses the dedup SELECT in `create()`. With `updateOrCreate` + the unique key this bypass is moot (DB still blocks dupes), but ensure the reworked path doesn't reintroduce a "skip dedup → blind insert" branch that could throw on the unique key for a pre-created 2007 gateway row.

---

## 9. TL;DR

Your plan is sound and the data needed at paid-time exists. The three things that make this non-trivial: (1) the row is **provisional** at paid-time (dispense outcome unknown) so reports must treat a new "pending" state correctly or they'll transiently count soon-to-be-refunded payments as sales; (2) the TRADE path must be reworked from *create-or-skip* to *create-then-update* or machine ground truth is lost; (3) the blast radius reaches **billing/invoices** (`CustomerPeriodSummary` → `CustomerInvoiceService`), so this needs validation, not just a code change. Also: only Omise auto-refunds non-dispensed payments today, and today's Total Sales patch must be removed to avoid double-counting.
