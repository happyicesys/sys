# Partial refund for multi-item purchases — exploration + plan (2026-08-25)

> Status: **PLAN — nothing implemented.** Exploration of mark1 + mark1-apk code and
> live DB, 2026-08-25. Companion to `REFUND_INTEGRITY_AUDIT_2026-08-23.md`, which
> this plan extends from single-item to multi-item purchases.

## 0. TL;DR

- Today a multi-item (cart) purchase with a failed channel gets **no automatic
  refund at all** — `VendTransactionService::isSingleItemDispenseFailure()`
  explicitly returns `false` for `is_multiple`, so `HandleFailedVendTransaction`
  never fires. The customer's only recourse is the refund-form QR the APK shows.
- Partial refund **is feasible**, and for the auto-refundable population
  (Omise/QR gateway sales on unified vends) it is **a server-only change** —
  the APK already uploads per-channel `SErr` in `transf_info[]`, and
  `Omise::refundCharge()` already takes an arbitrary amount.
- The two real blockers are **(a) per-item price is never persisted**
  (`vend_transaction_items.unit_price_amount` is 0 on every prod row) and
  **(b) refund idempotency is "PG log status flips to 98"**, which makes a
  second (partial) refund on the same charge unreachable.
- Card (NETS) multi-item sales **cannot** be partially reversed — the reader
  reversal is all-or-nothing, VMC-driven, and single-item only. Those stay on
  the manual refund-ticket path (which already supports per-item partial
  amounts, paid via PayNow/PayPal/CIMB).

## 1. What actually happens today (corrected mental model)

The framing "APK calls a refund API on channel error" is not how it works:

1. **The APK never calls a refund API.** Exhaustive search of mark1-apk finds no
   refund/reversal/void call. On a failed dispense it renders a **QR deep link**
   to the public refund form (`showRefundQrIfNeeded`, `Main2Activity.java:7377`,
   gate = `success_count < shipment_count`), URL carries only `machineID` +
   `order_id`.
2. **The refund decision is server-side, off the TRADE frame.** APK posts the
   whole-cart TRADE to `POST /api/v1/vend-data` → `CreateVendTransaction` →
   `VendTransactionService::create()`. If the purchase is single-item and
   `SErr ∉ {0,6}` and `success_qty == 0`, `HandleFailedVendTransaction` is
   dispatched (`VendTransactionService.php:267-273`, `:513-527`).
3. **Only Omise refunds.** `HandleFailedVendTransaction` switches on gateway;
   only `omise` does anything → `RefundOmiseJob` → `Omise::refundCharge()` with
   **always the full `payment_gateway_logs.amount`**. Recording via
   `OmiseRefundRecorder`: PG log `status=98`, txn `is_refunded=1`,
   `settlement_status=REFUNDED(1)`, `auto_refund_source`.
4. **Error-code semantics** (`VendTransactionService::processInput`):
   success codes `{0,6}`, dispensed codes `{0,6,7,9}`. Per-child codes from
   `transf_info[].SErr` land in `vend_transaction_items.vend_channel_error_code`.
   (APK side: 303's `GrabItemAdapter` now treats only `SErr==0` as success in
   the UI; the server rule stays `{0,6}`.)

### Prod evidence (read-only MCP, 2026-08-25)

- ~15k multi-item purchases/month (`is_multiple=1`).
- Since 2026-07-01, item-level failures inside multi purchases:
  **SErr 7 × ~700**, plus 3/4/8/9 in small numbers. Every one has
  `vend_transaction_items.is_refunded = 0` and (almost always) no refund ticket.
  Typical row: `amount=600, qty=2, success_qty=1` — customer paid for 2, got 1.
- `vend_transaction_items.unit_price_amount` = **0 on every row** — the column
  exists but nothing populates it (APK's `transf_info` has no price field, and
  `GatewayVendTransactionService` doesn't write it either).
- Multi-txn headers have `error_code_normalized = NULL` (frame-level `SErr`
  absent on cart TRADEs) — item rows / `items_json` are the only failure signal.
- `vend_transaction_items.is_refunded` is written `false` at create and **never
  set true anywhere** — but it *is* already read by
  `RefundTicketService::normalizeTransactionItems()` and
  `RefundValidationService::validate()` as "already refunded". Dead plumbing
  that partial refund can bring to life: setting it makes the customer refund
  form automatically exclude auto-refunded items.

## 2. Feasibility per payment channel

| Population | Partial refund possible? | How |
|---|---|---|
| Omise QR (unified vends) | **Yes — API, server-only** | `Omise::refundCharge(['amount' => …])` already parameterised; Omise supports multiple partial refunds per charge and reports `refunded_amount`. |
| Midtrans / Fiuu QR | Observe-only | No refund client exists in mark1 (webhook can *recognise* `partial_refund`). Manual ticket path. Building their refund clients is a separate project. |
| NETS / card terminal | **No** (at the terminal) | Reversal is VMC/reader-driven, all-or-nothing, single-item only; `0x19 CLEAR_CREDIT` is dead code. Manual ticket path (already supports per-item partial payout via PayNow/PayPal/CIMB). |
| Cash / Nayax / Grab | n/a | Unchanged. |

So the automatic partial refund targets **Omise multi-item sales**; everyone
else keeps the QR → refund-ticket flow, which this plan also improves (per-item
`is_refunded` finally being written makes ticket validation exact).

## 3. Design

### 3.1 The refund unit and amount

Refund unit = **failed item** (`vend_transaction_items` row with
`vend_channel_error_code ∉ {0,6}`; policy question: treat 7 and 9 — dispensed
codes with sensor doubt — see §6). Refund amount = **sum of those items'
`unit_price_amount`**, never `amount / qty` (promos/labels make per-item prices
unequal). Cap: cumulative refunds on a charge ≤ charged amount, exact-cents.

### 3.2 Fix the price gap first (prerequisite, Phase 1)

Per-item price must be persisted at charge time. For the Omise population the
server already knows it: the QR order is priced server-side when the payment is
created and the pre-created txn writes per-channel `vend_transaction_items`
(`GatewayVendTransactionService.php:176-193`). Change: populate
`unit_price_amount` there, with discount allocation such that
`sum(items) == amount` exactly (largest-remainder rounding on the split).

Fallback rule: **no per-item price ⇒ no auto partial refund** (show QR, status
quo). This keeps the feature safe on legacy rows and non-unified vends.

APK enhancement (Phase 4, optional): add per-item price + real
`success_count/fail_count` to the upload, and stop hardcoding `ISOK:1`
(`StaticFunction.java:188`). Nice for card-sale ticket accuracy, **not
required** for the Omise auto-refund.

### 3.3 Schema changes

`vend_transactions` (4M rows — appended columns, `ALGORITHM=INSTANT`, follow
the pattern in `2026_05_25_000000_add_settlement_columns…`):

- `refunded_amount_cents` int NOT NULL DEFAULT 0 — cumulative money returned on
  this purchase. `amount` is **never** decremented (existing invariant).
- Semantics kept: `is_refunded` stays "fully refunded"; `settlement_status`
  stays 0/1/2 with `REFUNDED(1)` = full. A partial refund is
  `settlement_status = SETTLED` + `refunded_amount_cents > 0`. (Alternative —
  a new `PARTIALLY_REFUNDED` enum value — rejected: every aggregator reads the
  ternary today and partial *is still a sale*.)

`vend_transaction_items`:

- Reuse existing `is_refunded` (finally written). Add `refunded_at` timestamp
  NULL and `refund_source` varchar(40) NULL for audit.
- **Preserve refund flags across the delete-and-recreate** in
  `applyTradeToPreCreatedRow()` (`VendTransactionService.php:454-459`) — carry
  `is_refunded/refunded_at/refund_source` over by channel code, or the
  OMISE_NO_DISPENSE → late-TRADE sequence wipes them.

New table `gateway_refunds` (the idempotency fix, see 3.4):

```
id, payment_gateway_log_id FK, vend_transaction_id NULL,
scope varchar(64),            -- 'full' | sorted item-id hash e.g. 'items:123,124'
amount_cents int NOT NULL,
status enum-ish varchar: pending | succeeded | failed,
gateway_refund_id varchar NULL,   -- Omise refund id
source varchar(40),               -- AutoRefundSource
response json NULL, timestamps
UNIQUE (payment_gateway_log_id, scope)
```

### 3.4 Idempotency redesign (the structural blocker)

Today `RefundOmiseJob` guards on `payment_gateway_logs.status == APPROVE` and
`OmiseRefundRecorder` flips it to 98 — correct for full refunds, fatal for
partials (second refund on the same charge becomes unreachable, and a retry of
the *same* partial could double-refund).

New rule set (refined 2026-08-25 after design audit):

1. **Outbox pattern**: the `gateway_refunds` row is created at *decision time*,
   inside the same DB transaction as the trigger, and the job is dispatched
   with the row id only ("execute ledger row N"). A duplicate decision dies on
   the `(log, scope)` unique key before any job queues; the job carries one id
   instead of four scalars and is naturally idempotent on retry.
2. Guard: `sum(succeeded amounts) + this amount <= charge amount` (PG log
   `amount` is decimal **dollars**; convert once, compare in cents) — and the
   guard MUST run under `DB::transaction` with `lockForUpdate()` on the PG log
   row. Without the lock, two *different* scopes can race past the cap (real
   case: the 10-min no-dispense scanner's full refund vs. a late TRADE's
   partial).
3. **Pending-resume rule**: a retry that finds its ledger row still `pending`
   must first list the charge's refunds at Omise (match by amount + metadata)
   before re-attempting — Omise has no idempotency-key header, and the crash
   window between API success and ledger write would otherwise double-refund.
   `refund:sync-omise` remains the eventual backstop, not the retry mechanism.
4. PG log `status` stays `APPROVE` while partially refunded; flips to 98 only
   when cumulatively fully refunded. Existing full-refund paths (no-dispense,
   stale-approve, manual, external webhook) migrate onto the same ledger with
   `scope='full'` — behaviour unchanged.
5. `OmiseRefundRecorder` grows a partial branch: marks the scoped items
   `is_refunded=1`, increments `refunded_amount_cents`, sets header
   `is_refunded/settlement_status` only when fully refunded; writes
   `auto_refund_source` per item (header: NULL until fully covered, then the
   completing source — decision #2). Revenue/GP recompute is a small extracted
   class the recorder calls, not more weight inside `record()` (SRP).
6. `SyncOmiseRefunds` reconciler: replace the "partial recorded as full —
   review by hand" warning (`SyncOmiseRefunds.php:89`) with proper ledger
   reconciliation against Omise's `refunded_amount`.

Audit notes (2026-08-25): the multi-branch decision logic (collect failed
items, compute amount, build scope) lives in a pure support class in the
`PreCreatedSettlementResolver` style — unit-tested, not inline in
`HandleFailedVendTransaction`'s gateway switch (the switch stays; one gateway
doesn't justify an interface yet). `gateway_refunds` gets FKs on
`payment_gateway_log_id`/`vend_transaction_id` plus a `(status, created_at)`
index for the reconciler. Any future UI over the ledger must apply the
operator-isolation boundary (raw joins bypass the global scopes — see
mark1/CLAUDE.md).

### 3.5 Trigger logic for multi (server)

New resolver next to `isSingleItemDispenseFailure()`:

```
isMultiItemPartialFailure(input): is_multiple
  && failed_items = children where SErr ∉ {0,6} (policy: see §6 for 7/9)
  && count(failed_items) > 0
```

Wire into both TRADE paths (fresh create + `resolvePreCreatedSettlement`).
Dispatch a generalized `HandleFailedVendTransaction` / `RefundOmiseJob` carrying
the failed item ids + computed amount + `scope`. New
`AutoRefundSource::OMISE_TRADE_PARTIAL` (fits varchar(40)).

Gates: unified-vend gate (`GatewayUnifiedTransaction::appliesToVend`) **and** a
new feature flag (`REFUND_PARTIAL_ENABLED` or per-vend list) so rollout can be
one machine first (2031 bench rig). All-items-failed multi ⇒ full refund via the
same path (`scope='full'` amount = charge).

### 3.6 Refund tickets / refund form interplay

- `RefundTicketService::markAutoRefundedByCharge()` currently rejects any
  open/approved ticket on the order when *any* auto-refund lands — under
  partials this would wrongly kill a ticket claiming the *other* channel. Change
  to item-scoped: cross off only tickets whose claimed items are fully covered
  by refunded items; otherwise recompute the ticket's owed amount.
- The form side mostly self-heals: `normalizeTransactionItems()` already reads
  item `is_refunded` and `claimed_amount_cents` already zeroes
  `already_refunded` items — once we actually set the flag, a customer scanning
  the QR after an auto partial refund sees only the still-owed items.
- APK QR behaviour can stay as-is (it's the fallback and the card-sale path);
  optionally suppress the QR when the server has confirmed an auto refund
  (needs a response channel — Phase 4, not required).

### 3.7a vend_records / gp_metrics impact (verified 2026-08-25)

Both are rollups rebuilt from `vend_transactions` (`StoreVendsRecord` daily;
`GpMetricsAggregator` nightly + live Sales Report read). Both filter
`settlement_status = SETTLED`, so a FULL refund on a unified vend already
removes the row entirely; neither reads `is_refunded`.

Today, for a multi purchase with a failed item:
- `vend_records.total_amount` counts the **full** `vt.amount` (multi branch has
  no error-code exclusion — "maps to Transactions page Total Sales");
  `total_count` (qty dispensed) already excludes failed items via
  `success_item_count`.
- `gp_metrics`: multi `amount_cents`/`revenue_cents`/`gross_profit_cents` also
  count the full basket (no per-item error exclusion); `error_count` counts the
  failed items. Because `unit_price_amount` is 0 everywhere, the per-item
  amount allocation degrades to an **equal split** (`amount / total_count`).

After the build:
- **Phase 1** (prices populated): per-product attribution inside a basket
  changes from equal split to true per-item prices for NEW transactions —
  day/machine totals unchanged (allocation still sums to `amount`), per-product
  splits become accurate. Historical rows keep the equal split (forward-only).
- **Phase 3** (partial refunds): header `revenue`/`gross_profit` recompute
  flows into `gp_metrics.revenue_cents`/`gross_profit_cents` automatically
  (`adjustedRevenueExpr = COALESCE(vt.revenue, vt.amount)/count`). The
  amount-based columns (`vend_records.total_amount`,
  `gp_metrics.amount_cents`/`txn_amount_cents`) read `vt.amount`, which is
  never decremented — so they must subtract `refunded_amount_cents` in the
  aggregator expressions to stay consistent with full-refund semantics (full
  refund = excluded entirely, so "Total Sales" is already net of refunds;
  partial should be net too). **Decision #6 (Brian, 2026-08-25): subtract —**
  aggregator amount expressions become `amount - refunded_amount_cents`;
  `vt.amount` itself stays untouched. Phase 3 scope grows by the two aggregator
  edits (`GpMetricsAggregator` single+multi branches, `StoreVendsRecord`) plus
  rollup regression tests.
- Historical figures do not move: forward-only, and rebuilds of unchanged rows
  reproduce identical numbers.

### 3.7 Reporting / downstream

- Revenue-style reads should become `amount - refunded_amount_cents` for
  partially refunded rows. Audit the consumers: `revenue/gross_profit` stored
  columns, `VendTransactionSalesAggregator`, `scopeApplyRefundedFilter`,
  `fact_*`/`gp_metrics` builders, daily summaries. Decision needed: recompute
  stored `revenue` on partial-refund write (fits the existing "late qty
  correction must be tolerated" doctrine), or subtract at read time. Leaning:
  **recompute stored columns in the recorder**, since aggregates are rebuilt
  from `vend_transactions` anyway.
- `vend_channel_stock_events` / stock: failed item was (usually) not dispensed;
  SErr 7/9 ambiguity is the same as today — no change in this plan.

## 3.9 NETS / MDB terminal — partial reversal is structurally impossible (explored 2026-08-25)

Question asked: single-item card sales already reverse at the terminal on channel
error — can a multi-item card sale reverse just the failed item's amount?
**No. There is no protocol path, and it is structural, not a missing feature:**

1. **Android is a pure serial slave** (VMC-polls-Android). It cannot initiate
   any command toward the VMC, let alone a reversal. `0x19
   CMD_PC_MDB_CLEAR_CREDIT` is declared and handled **nowhere** — not in
   mark1-apk, mark1-apk-small, or the OEM `vender/` baseline.
2. **Exactly one amount ever crosses the wire, once**: the whole-cart total in
   the `0x20` reply (`cashamount`, `Main2Activity.java:5005`), before the tap.
   `0x21` answers a 1-byte canceled flag; `0x22` returns approval with no
   amount. The `0x14` per-channel handoff re-sends the *same whole-order total*
   on every poll (`ThreadForBrd.java:437`) — no per-item amount exists.
3. **One MDB vend covers the whole cart.** One `0x20` request, one `0x22`
   approval, then N channel handoffs. The reversal that works for singles is
   the **VMC firmware autonomously** sending MDB VEND FAILURE after the drop
   sensor misses — Android is not involved (its `0x21` "canceled" answer is
   identical for singles and multis, so it cannot be the differentiator).
   Prod confirms the footprint: all failed NETS rows arrive as **VMC raw
   frames** (`TXN_SRC:0, ISOK:0`), and only `is_multiple=0` rows ever reverse.
4. The `0x14` reply carries a 1-byte **card+single flag**
   (`ThreadForBrd.java:441-445`, set only when `pay_type==CARD &&
   shipment_count==1`) — almost certainly the firmware's "reversal allowed"
   toggle. It is a boolean; even if the vendor documented it, it cannot carry
   an amount.
5. **MDB cashless itself has no partial-reversal primitive** — VEND FAILURE
   refunds the full VEND REQUEST amount. And there is no acquirer-side escape
   hatch: `card_terminals` is a bare name lookup (no credentials/endpoints),
   and no NETS/Auresys API client exists anywhere in mark1.

Consequence for this plan: **multi-item card sales stay on the refund-ticket
path** (§2 table row unchanged). The ticket flow already supports per-item
partial payout via PayNow/PayPal/CIMB, and Phase 3's item-level `is_refunded`
flags make its validation exact. Theoretical alternatives, all rejected:

- *Per-item sequential MDB sessions* (one `0x20`/`0x22` per cart item): needs
  VMC firmware changes + APK rework, and means N card taps + N acquirer fees —
  UX-destroying.
- *Vendor partial-reversal command*: the open vendor ticket (see
  `apk/mark1-apk/CARD_RETAINED_CREDIT_2026-08-22.md` §questions) can be
  extended to ask about `0x19` semantics, but MDB has no primitive underneath,
  so don't plan around it.
- *NETS acquirer API refund*: a from-scratch integration mark1 has no schema or
  credentials for; same "separate project" bucket as Midtrans/Fiuu refund
  clients.

### NETS refund-API reality check (web, 2026-08-25)

NETS SG's public developer portal (developer.nets.com.sg) documents **online**
products only — eNETS gateway, NETS QR, NETS Click — which do have refund /
partial-capture APIs. Our machines' card sales are a different rail:
**card-present EFTPOS/contactless at a NETS Certified Unattended Terminal
(integrated via Auresys)**. No public merchant-initiated refund API is
documented for that rail; NETS' stated process for terminal refunds is the
merchant portal / merchant services, ~5 working days. So "get the API from
NETS" means asking the NETS account manager + Auresys whether a
refund-by-reference API exists for unattended terminal transactions (partial
amounts included), under what agreement, and priced how.

Second, harder prerequisite if such an API exists: **mark1 never sees the
acquirer transaction reference (RRN/STAN).** The VMC drives the MDB reader;
Android/mark1 only get the TRADE footprint (amount, time, SErr). An API refund
must reference the original charge, so we'd also need either (a) Auresys/NETS
exposing a per-transaction feed (terminal id + RRN + amount + time) to match
against `vend_transactions`, or (b) matching by terminal id + amount +
timestamp, which is fuzzy for same-priced sales. Ask for (a) in the same
conversation.

If it materialises, it plugs straight into the `gateway_refunds` ledger
(§3.4 is deliberately gateway-agnostic: scope + amount + status + gateway ref).
Until then, the value gap is narrow: the ticket path already pays partial
amounts via PayNow, typically faster than NETS' 5-day card refund — the API's
real win would be zero-touch (no customer form) refund to card.

## 4. Phases

**Phase 1 — price foundation (server, no behaviour change).**
Populate `unit_price_amount` in `GatewayVendTransactionService` pre-created
items with exact-sum discount allocation; also write it on the fresh-create
path where per-child `Price/Amount` arrives (`extractChildAmountCents` already
parses it — today's APKs just don't send it). Migration for
`refunded_amount_cents` + item `refunded_at`/`refund_source` + `gateway_refunds`.
Tests: allocation sums exactly; INSTANT migrations.

**Phase 2 — idempotency ledger.**
Move all existing full-refund dispatchers onto `gateway_refunds` (`scope='full'`);
`RefundOmiseJob` takes (log, scope, amount); recorder split full/partial;
`SyncOmiseRefunds` reconciles per-ledger. Behaviour for singles unchanged —
regression-test against `PreCreatedSettlementResolverTest` + refund integrity
tests.

**Phase 3 — the feature.**
`isMultiItemPartialFailure` resolver + dispatch on both TRADE paths, item-scoped
`markAutoRefundedByCharge`, ticket-form already-refunded interplay, reporting
recompute, feature flag, bench test on 2031 (Omise QR multi purchase with a
forced SErr on one channel), then staged rollout.

**Phase 4 — APK enrichment (optional, 30x stream).**
Per-item price in `transf_info`, real `ISOK`/success counts, QR suppression on
confirmed auto refund. Server must stay tolerant of old APKs (field is on 301).

## 5. Effort / risk notes

- Phases 1–2 are the careful part (touching the money path shipped 2026-08-23);
  Phase 3 is comparatively small once the ledger exists.
- Forward-only, like the integrity work: **no backfill** of historical failed
  multi items (2 months × ~350/month ≈ known money, but refunding weeks-old
  charges automatically is a business decision, not a default).
- Deploy = push (auto-patching on main): each phase must land green on the full
  suite; Phase 2's dispatcher migration is the riskiest single push.

## 6. Decisions (Brian, 2026-08-25)

1. **SErr policy: auto-refund all non-{0,6}** — consistent with the
   single-item rule (which already auto-refunds SErr 7). Free-goods exposure
   from drop-sensor misses is accepted; revisit per-vend config if abused.
2. Header `auto_refund_source`: stays NULL while partially refunded; set on
   the refund that completes full coverage. Detail lives at item level +
   `gateway_refunds` ledger. (Default chosen during planning, not separately
   asked.)
3. **Reporting: recompute stored `revenue`/`gross_profit` in the recorder**
   when a partial refund lands — fits the existing late-correction doctrine;
   aggregates rebuilt from `vend_transactions` pick it up.
4. **Backfill: forward-only** — same as the refund-integrity deploy. The
   ~700 Jul–Aug failed multi items stay ticket-driven.
5. Sequencing (Brian, 2026-08-25): **Omise phases 1–3 first; NETS
   acquirer-API track and APK Phase 4 on hold.**

## 7. Key code references

| Concern | Where |
|---|---|
| Multi parse, success/dispensed codes | `app/Services/VendTransactionService.php:882-989` (`processInput`), `:715-729` (`extractChildAmountCents`) |
| Single-item-only refund gate | `app/Services/VendTransactionService.php:513-527` |
| Failed-txn dispatch | `app/Services/VendTransactionService.php:267-273`; `app/Jobs/HandleFailedVendTransaction.php` |
| Refund job + idempotency gate | `app/Jobs/RefundOmiseJob.php:66-98` |
| Recording (single path) | `app/Services/Refund/OmiseRefundRecorder.php:34-70` |
| Omise client (partial-capable) | `app/Models/PaymentGateways/Omise.php:154-166` |
| Pre-created gateway rows + items | `app/Services/GatewayVendTransactionService.php:176-193`; `VendTransactionService.php:403-460` (items delete-recreate `:454-459`) |
| Ticket ↔ auto-refund crossfire | `app/Services/Refund/RefundTicketService.php:599+` (`markAutoRefundedByCharge`), `:528-576` (`normalizeTransactionItems`) |
| Partial-refund reconcile warning | `app/Console/Commands/SyncOmiseRefunds.php:89` |
| APK: whole-cart upload, `ISOK:1` hardcode | `mark1-apk .../StaticFunction.java:181-226` |
| APK: refund QR gate | `mark1-apk .../Main2Activity.java:7377-7397` |
| APK: per-poll single-channel handoff | `mark1-apk .../ThreadForBrd.java:406-491` |
