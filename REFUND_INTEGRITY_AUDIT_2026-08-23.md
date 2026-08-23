# Auto-refund integrity audit — 2026-08-23

**Objective (Brian):** never refund the same purchase twice. Every surface that
says "auto-refunded" — Sales Transactions `Auto-refunded?`, Refund Request
`Auto Refunded?`, the Refund Request validation column's 3rd icon, and the
server Approve-guard — must agree with what the processor/terminal actually did.

**Status: findings + plan only. Nothing executed.** Verified against code in
`mark1`, `apk/mark1-apk`, `apk/mark1-apk-small`, `vender/` and prod via the
read-only `sys-happyice` MCP on 2026-08-23.

---

## 1. What each surface reads today (all read the SAME two bits)

| Surface | Rule | Code |
|---|---|---|
| Sales Transactions → `Auto-refunded?` badge | `vend_transactions.is_refunded` **OR** a linked ticket that is `auto_resolved` / `refund_method = nayax_auto` | `VendController::resolveRefundBadge` (3495-3514), `VendTransaction::scopeApplyRefundedFilter` (373-430) |
| Refund Request index → `Auto Refunded?` | same rule; `null` when no matched txn | `RefundController.php:1405` |
| Refund Request validation 3rd icon (✓/✗) and Approve-guard | **frozen** `system_validation_json.already_refunded` = `txn.is_refunded` ‖ `is_auto_refund_channel` (cashless_mfg ∈ `config('refund.auto_refund_terminals') = ['Nayax']`) ‖ any item already refunded. Re-synced only by `matchOrder` or `markAutoRefundedByCharge` | `RefundValidationService::validate`, `RefundTicket::isAlreadyRefunded`, `RefundController.php:645-653`, `Refund/Index.vue:282-291` |
| Gateway-only ticket (no txn) | `payment_gateway_logs.status = 98` | `RefundTicketService.php:127`, `RefundMatchingService::buildGatewayCandidate` |

So the surfaces are internally consistent: they all hinge on
**`vend_transactions.is_refunded`** (plus the Nayax config bit). The integrity
problem is upstream — **who sets `is_refunded`, and when**.

Writers of `is_refunded = true` today:
- `RefundOmiseJob` (Omise refund API succeeded; gated `appliesToVend`)
- `HandleFailedVendTransaction` (Omise only)
- Nothing for card terminals. Nothing for NETS reversals.

---

## 2. Findings

### F1 — Omise: auto-refund on dispense error is dead since 2026-05-26 (unified txn)
Full trace in the 2026-08-23 chat; summary:
- `RefundOmiseJob` is dispatched by (a) 10-min no-`CONFIRM` scanner, (b) stale
  approve >210 s, (c) `HandleFailedVendTransaction` on TRADE error, (d) manual
  artisan.
- (c) only fires when the pre-created row is still `PENDING` after the TRADE
  (`VendTransactionService.php:232`). `applyTradeToPreCreatedRow` forces
  `SETTLED` when `dispensed_qty>0 || success_qty>0` (err **7 and 9 count as
  dispensed**, `$dispensedErrorCodes=[0,6,7,9]`, line 829) **or** when
  `payment_gateway_logs.is_dispensed=1` — which `GetPurchaseConfirm` sets on the
  APK's `CONFIRM`, sent **before the motor runs**. So err 3/4/7/9 single
  purchases are settled, never refunded.
- Prod: **0** Omise `status=98 & is_dispensed=1` since 26 May; scanner path (a)
  is airtight (0 rows `status=2 & is_dispensed=0` older than 10 min in the last
  5 weeks).
- **106 refund tickets** sit on Omise single-purchase dispense errors with the
  charge still `status=2`: 79 already paid by PayNow/PayPal, 10 `approved`, 16
  `submitted`, 1 rejected. These are *not* double refunds (Omise kept the
  money) but they are manual payouts for refunds the system used to make.
- Multiple purchases: `is_payment_received` is forced true for any gateway
  method (`VendTransactionService.php:681`); unchanged, as Brian noted.

### F2 — NETS / card single purchase: terminal REVERSES, mark1 records nothing
- `mark1-apk` (big board) answers `CMD_PC_GET_TRADE_STATE` with a flag byte
  `=1` when `pay_type==CARD && shipment_count==1`
  (`ThreadForBrd.java:441`, not present in `vender/` OEM nor in
  `mark1-apk-small`). The VMC drives the MDB reader; on a failed vend it runs a
  **REVERSAL** on the NETS terminal (photo 2026-08-23) and the customer is
  credited. Android sends nothing else (`CARD_RETAINED_CREDIT_2026-08-22.md`
  §5).
- The TRADE that arrives carries the footprint: `ISOK=0`, `SErr∈{3,4,7,9}`,
  `PAY_TYPE=1`, single item. mark1 stores it as
  `is_payment_received=0`, `error_code_normalized=7`, **`is_refunded=0`**.
  Prod 21–22 Aug: every card-single failure is `ISOK=0`, every success
  `ISOK=1` — 1:1.
- Volume: 13 days (10–22 Aug) → Nets single err 7 = **406** txns ($1,223.70),
  err 4 = 10, err 3 = 8, err 9 = 10; Nets-Auresys err 7 = 30; PAX err 7 = 17;
  MLS err 5 = 2.
- Because `is_refunded=0` and `cashless_mfg≠Nayax`, all three surfaces say
  **"No / safe to process"** for these. Result in `refund_tickets`:
  - **28 tickets COMPLETED (paid by PayNow/PayPal), $100.90**, on card-single
    dispense errors 4 Jul – 14 Aug (RF-260814010, RF-260805006, RF-260802011,
    RF-260729017, RF-260719023/019/005/002, RF-260718015/016/014/011,
    RF-260717016/010, RF-260716004, RF-260715013, RF-260714002, RF-260713003,
    RF-260712014/006, RF-260708025/020/016/013, RF-260705009/006/002,
    RF-260704010). If the terminal reversed these, they are **double refunds**.
  - **~45 tickets** on the same population were hand-rejected with
    `auto_refund_detected=1` ("Reject → No charge / auto-refund") — i.e. ops
    already know, but the decision is per-ticket and inconsistent.
  - **12 OPEN now** (11 `submitted`, 1 `insufficient_info`): RF-260823005,
    RF-260822021, RF-260822020, RF-260822001, RF-260821014, RF-260821004,
    RF-260820007, RF-260820003, RF-260818003, RF-260818005, RF-260818006,
    RF-260709014. Note RF-260818005/006 are two tickets on the **same** order
    `2026081811254200124`.
- Card **multiple** purchase: flag byte = 0 → no reversal; mark1 sets
  `is_payment_received=1`; manual payout is correct. Keep.

### F3 — Small board (`mark1-apk-small`, 13x) — scanned 2026-08-23
- The small APK has **no Android-side cashless session at all**: commands
  0x20/0x21/0x22 (`CMD_PC_CHECK_CASHLESS_REQUEST_STATE` /
  `ANDROID_EXIT_CASHLESS_SESSION` / `TELL_CASHLESS_SESSION_RESULT`) do not exist
  in its `clsCmdConst`, there is no `CREDIT_CARD_*` flow in `Main2Activity`, and
  its `CMD_PC_GET_TRADE_STATE` reply is the OEM layout (no `txn_src` byte, no
  "card + single" flag byte). Card purchases on small boards are **VMC-keypad
  only**: VMC + MDB reader run the whole session; Android just forwards the
  VMC's TRADE (`PAY_TYPE=1`, `SErr`, `ISOK`) to mark1.
- Prod confirms the flag is irrelevant to the observed failures on **both**
  boards: every card-single dispense error 15–22 Aug has `interface_type
  (TXN_SRC) = 0` = VMC keypad flow, never the Android soft-keyboard flow where
  the big-board flag is sent. Split: small board **178 err-7 on 77 machines**
  (+ 19 Nets-Auresys, 10 PAX, 4 err-4, 2 err-3, 2 MLS err-5); big board 81
  err-7 on 44 machines (+3/3/3). All `ISOK=0`.
- Therefore the REVERSAL seen on the NETS terminal is **VMC firmware + MDB
  reader behaviour on the keypad flow** (MDB VEND FAILURE → reader refund),
  not something the APK triggers. The big-board flag byte only affects
  Android-initiated (soft-keyboard) card orders, which produce none of the
  failures in the sample.
- Still to verify before any auto rule: (a) a small-board NETS machine
  actually reverses (same VMC family is likely but unproven — the small board
  is the *bigger* population, ~70% of card-single failures); (b) PAX / MLS /
  Nets-Auresys readers honour VEND FAILURE the same way; (c) the soft-keyboard
  card flow on big board (flag=1) — zero failures in sample, so irrelevant to
  the backlog but should be covered by the same rule.
- `config/refund.php auto_refund_terminals = ['Nayax']` — no Nayax txns in the
  sample; rule is dormant but still the only "auto" card rule in code.
- QR/Omise path is identical on the small APK (`mPreTransfor` → `CONFIRM`
  before dispense, `Main2Activity.java:552/894`), so F1 applies to both boards.

### F4 — Fragile spots in the Omise job itself
- `RefundOmiseJob` throws on API failure (`ErrorService::throwErrorWithMqtt`)
  → Horizon `tries=1` → never retried; scanner cursor
  `payment_gateway_log_refund_scanned_at` has already moved on → charge stays
  `status=2` silently (only an MQTT error to the machine). Prod shows 0 such
  rows in 5 weeks, so it has not bitten, but there is no alarm.
- `is_dispensed` is named like "product dropped" but means "machine ACKed the
  paid order". It is used as a settle signal (F1) and is shown as "Dispensed?"
  in the PG export. Naming is actively misleading for this audit.

### F6 — Omise refunds made OUTSIDE mark1 (dashboard / dispute) were invisible — FIXED 2026-08-23
- Trigger: Omise email 2026-08-12, WeChat dispute on `chrg_68n67o8khxc8go7inac`
  (order `26081115390302129`, vend 2129, $3.50) "accepted the chargeback as a
  refund". Prod: PG log 512827 still `status=2`, last write = `charge.complete`
  on 11 Aug; `vend_transactions` 5938373 `is_refunded=0`.
- Cause: Omise DID send `refund.create`, but `PaymentController` read
  `data.metadata.order_id` — that is the REFUND's metadata, stamped only on
  refunds WE make via the API; dashboard/dispute refunds carry `{}` → no
  order → dropped. `dispute.*` events hit `default: throw` → 500 → Omise retries.
- Fix: resolve refunds via `data.charge` (= our `ref_id`), keep `ref_id` = charge
  id, acknowledge `dispute.*`, and record through the new
  `OmiseRefundRecorder` (log → REFUND, txn → `is_refunded` +
  `auto_refund_source = omise_external` + REFUNDED, open tickets crossed) —
  the same single path `RefundOmiseJob` now uses.
  Tests: `tests/Feature/OmiseExternalRefundWebhookTest.php`.
- Reconcile: `php artisan refund:sync-omise --charge=chrg_… | --from=Y-m-d
  [--apply]` reads Omise's own records (`GET /charges/{id}`, `GET /refunds`).
  Dry-run 2026-05-01→23 from the local copy: **21 external refunds
  ($120.70) mark1 never recorded**, logs 431982, 434061, 450330, 431800,
  462883, 479879, 480557, 482813, 486841, 490916, 495248, 500132, 504490,
  505032, 510631, 510845, 512827, 517196, 515903, 508010, 518156. Two of them
  were ALSO paid by ticket afterwards = **confirmed double refunds**:
  RF-260802004 ($5.00 PayPal, log 505032) and RF-260809006 ($4.50 PayNow,
  log 510631). Two Omise accounts (keys …b8loiu, …3l515k) returned 403 on
  `GET /refunds` — their keys lack list permission; check on the Omise dashboard.

### F5 — Repeat tickets on one purchase
`RefundTicketService::create` tags repeats (`is_repeat`,
`replicated_from_reference`) but still creates them; `conflictingRefund()` only
blocks at approve time. RF-260818005/006 (Nets) and RF-260807007/008 (Omise)
are live examples. Not a double *payout* yet, but it is the path to one.

---

## 3. Plan (for decision — nothing below has been done)

### Phase 0 — containment (ops, no code)
1. Put the **12 open card-single-error tickets** on hold; ops check the
   terminal/NETS portal for a reversal on each before paying.
2. Reconcile the **28 paid tickets ($100.90)** against NETS settlement reports;
   decide recover / write off. Keep the list as the audit baseline.
3. Field check: one big-board NETS, one **small-board NETS (priority — 70% of
   the failures)**, one PAX, one Nets-Auresys machine — force an err-7 single
   card vend from the **VMC keypad**, confirm REVERSAL per type; also one
   soft-keyboard card vend on big board. Output = the authoritative
   "which terminal × board × flow reverses" table that F3 is missing. This
   decides the scope of Phase 2.

### Phase 1 — one source of truth for "auto-refunded" — **IMPLEMENTED 2026-08-23 (local, awaiting deploy)**
- Migration `2026_08_23_120000_add_auto_refund_source_to_vend_transactions`
  (`varchar(40) NULL` after `is_refunded`); values in `App\Support\AutoRefundSource`.
- Written by `RefundOmiseJob` (source passed by each dispatcher) and by the
  card-reversal path below. Shown in the Sales Transactions badge tooltip
  (`VendTransactionResource.auto_refund_source_label`).

### Phase 2 — card terminal reversal → `is_refunded` — **IMPLEMENTED 2026-08-23 (NETS-only gate, local, awaiting deploy + backfill)**
- Live rule: `VendTransactionService::isCardTerminalReversal` /
  `markCardTerminalReversal` on the fresh-create TRADE path: card + single +
  `success_qty=0` + err ∉ {0,6} + `ISOK=0` + `cashless_mfg ∈
  config('refund.card_reversal_terminals') = ['Nets','Nets-Auresys']` →
  `is_refunded=1`, `auto_refund_source=card_terminal_reversal`, then
  `markAutoRefundedByCharge`. PAX / MLS / Nayax excluded until field-checked
  (config change only to widen). No board/flow gate: both boards and both
  flows produce the same footprint and the reversal is VMC/MDB behaviour.
- Backfill: `php artisan refund:backfill-card-reversals --from=YYYY-MM-DD
  [--to=] [--apply]` — dry-run by default, index-friendly, chunked; COMPLETED
  tickets untouched. Local dry-run for 2026-08-01→23: 722 rows / $2,221.70.

### Deploy runbook — FORWARD-ONLY (Brian, 2026-08-23: "no need to go back and
### resync the history; going forward, only make it auto refund")
1. `php artisan migrate` (one nullable column, online ALTER).
2. Deploy code. From then on, automatically:
   - Omise single-item dispense failures refund again (any Omise method).
   - Omise refunds made on the dashboard / by dispute are mapped and recorded
     (`omise_external`).
   - NETS / Nets-Auresys single-item failures are marked auto-refunded at
     TRADE time (`card_terminal_reversal`).
3. **Nothing else.** The history is left as it is: the 26 open Omise tickets,
   the 21 unrecorded external Omise refunds, and the pre-deploy NETS
   reversals are NOT back-filled. Ops keep handling those tickets by hand as
   today. (`refund:backfill-card-reversals` and `refund:sync-omise` exist,
   dry-run by default, if that decision ever changes — do not run `--apply`.)

### Phase 3 — restore Omise dispense-error refund — **IMPLEMENTED 2026-08-23 (local, awaiting deploy)**

Which Omise methods auto-refund? **All of them.** The refund is charge-level
(`POST /charges/{id}/refunds`, `Omise::refundCharge`) and never looks at the
source type. Prod 24 Jul – 22 Aug, no-dispense scanner: paynow 80 refunds,
wechat_pay_mpm 11, alipayplus_mpm 7, shopeepay 1 — and **zero** approved
not-dispensed charges left behind for any method. The TRADE-failure path uses
the same job, so it is method-agnostic too. Single-purchase only (multi stays
a sale), as Brian specified.

Change set (all in mark1, tests green, Pint applied):
- `VendTransactionService::resolvePreCreatedSettlement()` (new, pure, public
  static) replaces the inline rule in `applyTradeToPreCreatedRow`: a
  single-item TRADE with `success_qty = 0` and numeric `errorCode ∉ {0,6}` →
  `PENDING` even if the ACK already settled the row → existing
  `HandleFailedVendTransaction` → `RefundOmiseJob`. Multi-item, REFUNDED and
  non-numeric-code cases unchanged. `isSingleItemDispenseFailure()` is the
  predicate.
- `GetPurchaseConfirm`: the ACK only settles rows whose TRADE has not landed
  (`is_found_in_transaction = false`) — a late ACK cannot un-fail a TRADE.
- `HandleFailedVendTransaction`: no longer pre-marks `is_refunded` /
  `REFUNDED` before Omise accepts (unified vends); `RefundOmiseJob` is the
  single writer, on success only. Legacy vends keep the old flag.
- `RefundOmiseJob`: `tries = 3`, `backoff = [30, 120]`, warning per failed
  attempt, `failed()` logs after the last (F4).
- `tests/Unit/PreCreatedSettlementResolverTest.php` (14 tests).

Original plan text kept below for reference.
- In `applyTradeToPreCreatedRow`: single-item TRADE with `success_qty=0` and
  `errorCode ∈ {3,4,5,7,9,42,45,77}` → leave `PENDING` so
  `HandleFailedVendTransaction` → `RefundOmiseJob` runs, regardless of the
  pre-dispense ACK (`is_dispensed`). Keep: multi-item partial (`success_qty>0`)
  stays SETTLED; REFUNDED never resurrected.
- `RefundOmiseJob` → `auto_refund_source = omise_trade_fail`; retry/alarm on
  API failure (F4): `tries=3` + log to the refund channel + leave
  `status=2` visible in a "refund failed" filter on the PG Transactions page.
- The 26 open Omise tickets (10 approved + 16 submitted) should then be
  refunded through Omise, not PayNow; the 79 completed are sunk cost (no double
  refund — Omise never returned the money).

### Phase 4 — UX / naming
- Rename "Dispensed?" (PG export) and the `is_dispensed` reading in refund
  pages to "Dispense ACK" (already done in ticket detail
  `gateway_dispense_ack`; index/export still say Dispensed).
- Refund Request submit: when the matched txn is `is_refunded=1`, show the
  existing "already refunded" block (it will appear automatically once Phase 2
  writes the flag) — no new UI.
- Block (not just tag) a second ticket on a txn that already has a
  non-rejected ticket (F5), or at least surface `is_repeat` in the index.

### Open decisions for Brian
1. Is `card_terminal_reversal` to be treated as **auto-refunded = never pay**
   (Nayax semantics) or **flag + ops confirm**? The former is what ops are
   already doing by hand via "Reject → No charge".
2. Scope of Phase 2 terminals/boards = Phase 0 result.
3. Phase 3 goes back to pre-May behaviour for Omise; the 26 open tickets get
   Omise refunds instead of PayNow — confirm.
4. Backfill horizon for Phase 2 (suggest 2026-07-01, the start of the refund
   ticket system).
