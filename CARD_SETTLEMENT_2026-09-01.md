# Card Settlement — NETS report reconcile with vend_transactions (2026-09-01)

## Why

Omise is fully in sync (API tunnel → `payment_gateway_logs.ref_id` = Omise charge id). NETS card
terminals are not: mark1 only ever sees the APK TRADE frame, which carries **no acquirer reference
at all** (no RRN/STAN/TID). The only way to confirm NETS actually settled a sale is the NETS
MerchantConnect daily report — so this feature lets the user upload it on demand, match its rows to
`vend_transactions`, resolve queries, and stamp the matched sales.

## What was verified before building (2026-09-01, prod MCP + real August files)

- Report file: `MCONNECT_H06228_STDRPT01_<yyyymmdd>_NEW.csv`. Preamble (Merchant Account ID,
  Create Date/Time, Cutover Date, Total records counts) + `Product,…,Terminal ID,…` header + rows.
  Products seen: EFTPOS / Scheme Credit/Debit / FLASHPAY / CROSS BORDER; types: Purchase, Logon.
- **Times are LOCAL, not UTC.** The "time doesn't tally" confusion was Excel damage (next point) plus
  the terminal clock: the report stamps card-APPROVAL time, our TRADE frame lands **10–25 s later**
  (verified TID 23082824 ↔ vend 2542, 8/8 purchases matched, deltas 9–25 s). Matching window:
  txn − report ∈ [−60 s, +300 s] (`config/card_settlement.php`).
- **A CSV opened + re-saved in Excel loses the hour**: `23:12:41` becomes `12:41.0` in the file bytes
  (and dates become `1/8/2026`, Merchant ID `1.11E+11`). Opening such a file again, Excel's formula
  bar shows a fake `12:12:41 AM` — that is Excel parsing `12:41.0` as mm:ss with hour 0, NOT the
  real time (verified: that exact line is the sale at 2026-08-01 **23**:12:52 in prod). **5 of the 30
  August files are damaged like this** (0801, 0825, 0826, 0827, 0829 — corrected 2026-09-02 from an
  earlier "29 of 30" note that generalised from one file); the other 25 are raw
  (`2026-08-02`, `22:46:33.000`). The parser handles both — damaged rows are flagged
  `time_is_partial` and matched **in file order** (`CardSettlementMatcher::assignOrdered`): the NETS
  file is newest-first (0 order violations in 2,872 lines of a raw file) and Excel keeps row order,
  so per terminal, descending `row_no` is chronological — each line takes the earliest unclaimed sale
  that fits its mm:ss and sits at/after the previous line's sale. Offline check on a raw day with
  the hours stripped: 82/83 placed, 0 wrong hour; the earlier independent rule (which produced 635
  AMBIGUOUS lines on the live 0801 upload — 700 queries) is gone for partial rows. The report shows
  `partial_time_rows` as an amber warning. **Upload raw portal downloads** anyway — the ordered
  rule is inference; a raw file is fact.
- The NETS business day cuts over ~22:30, so one file spans two calendar dates. The row's own
  Transaction Date drives matching; consecutive daily files can share edge rows — fingerprint dedupe
  (sha1 of provider|tid|date|seq|amount|time, UNIQUE) marks re-ingested lines DUPLICATE.
- TID → machine mapping lives only in the ops sheet `Nets Terminal TID-2025-2026.xlsx`
  ("Device No." = TID, "MC ID" = vend code, monthly tabs, some rebind history). Extracted to
  `database/data/card_terminal_bindings_nets_2026-08.csv` (312 rows incl. 8 closed history rows;
  Ezlink suffixes stripped — the parenthesised EZ-Link numbers never appear as report TIDs).

## Pieces

| Piece | Where |
|---|---|
| TID ↔ vend binding (effective-dated) | `card_terminal_bindings` table, `CardTerminalBinding`, `CardTerminalBindingController`, `Pages/CardTerminalBinding/Index.vue` |
| Report + rows | `card_settlement_reports` / `card_settlement_rows` (file itself = polymorphic `Attachment` on the report) |
| Parser abstraction | `App\Contracts\CardSettlement\SettlementReportParser` + `Services/CardSettlement/ParserRegistry` (config-driven — another acquirer = one parser class + a `config/card_settlement.php` entry) |
| NETS parser | `Services/CardSettlement/Parsers/NetsMerchantConnectParser` (raw + Excel-damaged dialects) |
| Matcher | `Services/CardSettlement/CardSettlementMatcher` — binding (as of row date) + exact cents + time window; greedy best-first assignment; partial-time rows circular within the hour; excludes `is_retained_credit_settlement` (no card presented — never settles) |
| Ingest+match job | `Jobs/MatchCardSettlementReport` (`low` queue, tries 1) |
| Sync | `Services/CardSettlement/CardSettlementSyncService` — chunked UPDATE stamping `vend_transactions.card_settlement_synced_at` |
| UI | `Pages/CardSettlement/{Index,Show}.vue` (Transactions menu → "Card Settlement"); Sales Transactions grid got a "Settle Sync" ✓/✗ column — card-terminal rows from `card_settlement_synced_at`; gateway rows (Omise/Midtrans) from the linked `payment_gateway_logs.status` (2 approved / 98 approved-then-refunded = ✓); cash blank. Page-bounded PK join, no extra query |
| Permission | `card-settlements` (read/create/update/delete) → superadmin/admin/supervisor, in `RolePermissionSyncSeeder` |
| Bindings import | `php artisan card-settlement:import-bindings <csv> [--apply]` (dry-run default) |

## Row lifecycle

PENDING → MATCHED / UNMATCHED / AMBIGUOUS (legacy; hour-less rows are now placed by order) /
IGNORED (Logon, user-dismissed) / DUPLICATE. UNMATCHED notes, and what each means (2026-09-03):

| Note | Meaning | What to do |
|---|---|---|
| `No terminal binding` | no binding covers the line's date. The Show page splits these: **"Terminal IDs not created yet"** (red — no `card_terminal_units` row; create it under Data Management → Card Terminal, then assign) vs **"Terminal IDs not bound to any machine yet"** (amber — unit exists, assign it from the machine's Settings page). Both lists carry the matcher's suggestion — "likely on 2835 (14 of 16 lines fit, from date)" — from `suggestMachineForUnbound` (a VOTE across the TID's full-time lines: the machine fitting the most lines wins if it fits ≥ half of them, ≥ 2, and beats the runner-up — a bystander fitting one common-price line no longer vetoes); ticked TIDs go through `POST /{id}/bind-unbound` → create unit if new + `assignToVend` from that date + rematch | accept the suggestion, or create / assign by hand → Rematch |
| `No matching sale on bound machine — found on machine X` | the sale exists, on another machine: **the binding sheet is wrong** for this TID (live: 23082812 said 2787, sales on 2696) — the Show page groups these as "Terminals that look bound to the wrong machine" | move the binding (close old, open new from the right date) → Rematch |
| `All matching sales already claimed` | every fitting sale is held by another line — **NETS charged more times than mark1 recorded** (live: two $9.80 taps 44 s apart, one 5-item sale) — likely a double tap | check the two lines; refund the extra charge if real; Ignore the line |
| `No matching sale in window` | nothing on any machine at that time/amount — TRADE never reached mark1 (machine offline) | manual Assign if the sale is found later, else Ignore |

Candidates a line cannot take (held by another line) are shown as "held by row #N", not as a Pick
button — Pick would only bounce with "already claimed". Picking a sale on another machine asks for
confirmation ("Moving this line from machine A to machine B…") and the line then follows that
machine. Open queries carry a checkbox (header = select all on the page) and an **Ignore Selected**
batch button (`POST /card-settlements/{id}/rows/ignore-batch`, Unmatched/Ambiguous only). The manual **Txn ID / Order ID** box takes either the sale's numeric id (`#5963112`, as the page prints it) or the Order ID shown on Sales Transactions (17–19 digits; unique per machine, so a duplicate across machines is refused with a hint to use the numeric id). Claims are unique both directions (fingerprint UNIQUE;
`matched_vend_transaction_id` UNIQUE across ALL reports ever).

Report: uploaded → matching → review → synced (failed on parser error). Rematch re-runs only
unresolved rows — the flow for "add missing binding, then Rematch".

## Reversals (added 2026-09-02) — the report replaces the TRADE-time inference

- In the NETS file a reversal is its **own line**: `Reversal Code = Y`, **negative amount**, same
  terminal, stamped at the moment the reader reversed (240 such lines across August 2026). The
  `Void Txn Indicator` column is **never** Y in practice; the parser honours both anyway.
- `CardSettlementMatcher::pairReversal` links each reversal line to the purchase line it undoes:
  same terminal, same absolute amount, latest purchase at/before the reversal within **5 min** (both
  lines carry the same terminal clock and the reader reverses within the vend cycle; a wider window
  risks pairing a different customer's dispensed sale when the true purchase line is missing —
  tightened from 60 min on 2026-09-02; 15 min circular when the hour was lost). Candidates never
  include DUPLICATE / IGNORED lines; equal deltas go to the earliest-ingested line. Deleting a report
  releases the links held by rows in other reports (`CardSettlementReport` `deleting` hook). The purchase may live in an earlier report (reversal after the
  ~22:30 cutover). Links: `reverses_row_id` (reversal → purchase), `reversed_by_row_id`
  (purchase → reversal). A reversal with no prior purchase is an UNMATCHED query.
- **Sync** then marks the purchase's sale refunded: `is_refunded = 1`,
  `auto_refund_source = settlement_report_reversal`, plus
  `RefundTicketService::markAutoRefundedByCharge`. `refunded_count` on the report. Since
  2026-09-08 Sync goes further — see "The report is the source of truth" below: a tick the
  report contradicts is cleared, not left as it is.
- The TRADE-footprint inference (`card_terminal_reversal`) was switched off 2026-09-02 and
  **removed 2026-09-08** (predicate, backfill command, config key). Consequence to remember: a NETS
  reversal is known only after that day's report is uploaded and synced, not at TRADE time, so a
  refund ticket raised in between is only auto-crossed on Sync.
- `matched_count` / "Sync N matched" counts purchase lines only; paired reversal lines are MATCHED
  but claim no sale (the UNIQUE `matched_vend_transaction_id` stays with the purchase line).

## The report is the source of truth for the auto-refund tick (2026-09-08, Brian)

Measured on the 7 reports synced on 2026-09-07 (Aug 31 – Sep 6): every Reversal-Code-Y line had a
tick on its sale, but 23 matched sales carried a tick with NO reversal and 44 more carried a tick
with no NETS line at all — every one of them written by the TRADE-time inference before 2 Sep. From
3 Sep the tick tallied 100%. So the rule became: **nothing mark1 deduces from a TRADE or a machine
signal may set `is_refunded` on a card sale; the report alone does, and it overrides what an
earlier writer left.** `CardSettlementRefundReconciler` applies it per calendar day D:

| Report says about the sale | Tick | Source | When |
|---|---|---|---|
| purchase line paired with a reversal line (synced report) | ON | `settlement_report_reversal` (an inference tick is relabelled) | as soon as that report is synced |
| purchase line, no reversal | OFF | cleared | day final |
| no line, terminal bound on D | OFF | cleared | day final |
| no line, terminal NOT bound on D | untouched | — | never (report can't say) |

"Day final" = the reports cut on D **and D+1** are both synced — a sale on D can be captured late
into the D+1 file, and its reversal can sit there while the purchase sits in D. Clearing waits for
that; setting does not. Only ticks the report owns are cleared (`card_terminal_reversal`,
`settlement_report_reversal`); `retained_credit_revend` and every gateway source are left alone.
Every clear releases the ticket the tick had crossed (`clearAutoRefundByCharge`: verdict un-frozen,
recommendation Reject → Review, status untouched, log line "auto_refund_cleared"); every set crosses
it (`markAutoRefundedByCharge`), as before.

Runs: `CardSettlementSyncService::sync` reconciles the report's cutover day and the day before,
after the status flip (the flip is part of "is the day final"); the flash message reports ticks
set / cleared / deferred. `php artisan card-settlement:reconcile-refunds [--from --to] [--apply]`
re-applies it over already-synced days (dry-run default) — needed once for the reports synced
before this rule existed, and again whenever the August reports are synced.

The refund ticket page shows the same verdict under System self-checking → **NETS report**
(Reversed / Captured, not reversed / Not captured / In review / No report yet / Terminal not bound,
with a link to the report), so an ops decision on a card claim is made on the report, not the tick.

## Payment Status, Auto-refunded, Refund Request badge — one source per rail (2026-09-02)

| Rail | "Auto-refunded?" / `is_refunded` | Payment Status (`SaleStatus::payment`) |
|---|---|---|
| Omise | API job / `refund.create` webhook / `refund:sync-omise` → `OmiseRefundRecorder` | Paid → Refunded |
| Midtrans | `refund` / `partial_refund` webhook → same recorder, source `midtrans_external` (**new** — before this the webhook only flipped the gateway log) | Paid → Refunded |
| NETS card | settlement-report reversal line → `CardSettlementRefundReconciler` on Sync (`settlement_report_reversal`); the report also CLEARS a tick it contradicts | Paid → **Settled** (stamp) → Refunded (reversal) |
| Cash | — | Paid |

Both columns are filterable on Sales Transactions: **Payment Status** (request key `payment_status`:
paid / settled / refunded / unconfirmed / retained_credit / re_vended — `VendTransaction::scopeFilterTransactionIndex`
is `SaleStatus::payment()` in SQL, pinned by `TransactionIndexPaymentFilterTest`) and **Dispense
Status** (`is_payment_received`, kept for bookmarks: true / false / pending / no_report). Sync on the
Card Settlement page applies whatever matched — open queries can be resolved and re-synced later.

Every one of those writes goes through `RefundTicketService::markAutoRefundedByCharge`, which sets
`auto_refund_detected` (and pulls approved/scheduled tickets back to Rejected); the
`RefundTicketObserver` → `RefundRequestSync` chain then refreshes the denormalised
`refund_request_*` columns, so the **Refund Request badge** on Sales Transactions follows without
any extra wiring. Dispense Status is the machine's verdict and never changes from any of this.

**File storage:** uploads go to the private `digitaloceanspaces` disk (S3-compatible;
`CARD_SETTLEMENT_DISK` to override, `local` fallback without credentials) under `card-settlements/`,
recorded on `card_settlement_reports.storage_disk`, and are served only via the authed
`GET /card-settlements/{id}/download`. Nothing lands in the app's public disk.

## Settlement date — when the money reaches the bank (2026-09-12)

Sales Transactions has a **Settlement Date** column, right after Settle Sync: the banking day the
acquirer pays that card sale out, with the payment gateway underneath (`260902` / `COS`). The whole
thing is **derived on read, never stored** — it is arithmetic on the matched report line's own
transaction date, so a corrected rule or a newly seeded public holiday fixes every historical row at
once instead of needing a backfill.

NETS payout schedule (Brian's NETS settlement standard, 2026-09-12), in
`config/card_settlement.php` → `payout_terms.nets`, evaluated top-down, first fit wins:

| Report `Product` | Issuer | Gateway | Term | MDR |
|---|---|---|---|---|
| `Scheme Credit/Debit` | VISA / MasterCard | DBS CARD CENTER | T+2 | 2.5%, after deduct MDR bank in |
| `EFTPOS` | any (DBS Card / DBS PayLah / OCBC / UOB Mighty / HSBC / Maybank / SC / Unknown) | COS | T+1 | 0.8%, full back in |
| `FLASHPAY` | NETS FlashPay | COS | T+1 | 0.8% |
| `CROSS BORDER` | WeChat Pay | POS | T+1 | 0.8% |
| `CROSS BORDER` | UnionPay / BHIM / Alipay+ / PayNet / RINTIS / JALIN / ARTAJASA | COS | T+1 | 1.8% |
| (EZ-LINK) | — | AURESYS | T+2 | 2% + GST |

Checked against every card type in the live reports (98,000 lines, 31 Jul – 9 Sep 2026): **0
unmapped purchase lines**. Two of those rows are not from Brian's table and were his call on
2026-09-12 — FlashPay placed on the NETS schedule, and Alipay+ / the ASEAN QR schemes grouped with
UnionPay+BHIM at 1.8%; revisit if a NETS statement ever shows them settling elsewhere. **EZ-Link has
never appeared as a line in a MerchantConnect file** (its TIDs are not NETS TIDs) — the rule records
the term, it does not fire. `EFTPOS / DBS Settlement` is Logon-only, so it never reaches the column.

- **T+N counts BANKING days.** Weekends and Singapore public holidays (`holiday_days.is_public`) are
  skipped and a term landing on a closed day rolls forward: Friday 4 Sep T+1 → Monday 7 Sep; Friday
  7 Aug T+1 → Tuesday 11 Aug, past the National Day pair. School holidays are not bank holidays.
  `holiday_days` is populated 2020–2027; beyond that the calendar degrades to weekends only.
- **The LINE's transaction date drives it** (Brian's instruction, 2026-09-12), not the file's cutover
  date. There is no alternative in the data: the report HAS a `Business Date` / `Business Time` pair,
  and it is **empty on all 91,748 rows of the 35 August files** — verified 2026-09-12, so the parser
  is right to ignore it and the capture date is the only date a line carries.
- **Two measured caveats on that basis** (both from the raw files, 2026-09-12):
  1. **10.4 % of purchase lines are batched a day after they were captured** (cutover date −
     transaction date = 1; 8,417 of 80,824, plus 7 at two days). These are terminals whose batch
     uploaded late — a file's "previous day" rows span that whole day, not just the post-22:30 tail.
     If NETS in fact funds from the batch date rather than the capture date, those rows land one
     banking day later than the column says.
  2. **41.9 % of purchase lines were captured on a weekend or public holiday** (33,877 of 80,824 —
     residential machines, weekends are the busy days), which is why
     `payout_calendar.non_banking_origin` is an explicit setting rather than an accident. Live
     default `transaction_date`: on T+1 a Friday, Saturday and Sunday sale all land on the Monday.
     The alternative `next_banking_day` rolls T to the Monday first, putting the weekend a day
     behind Friday. Both readings agree on every banking-day capture, which is why the NETS standard
     does not settle it. Pinned both ways in `CardSettlementPayoutDateTest`.
- **A rail with no mapped schedule shows nothing.** No resolver for the provider (Midtrans, any
  non-Singapore gateway) or no rule for the card type ⇒ blank cell, never a borrowed date. Same for a
  card sale no report line has claimed yet.
- The column does not wait for Sync: the line existing is what says the acquirer has the money.
  Settle Sync remains the separate "we have stamped it" fact.

| Piece | Where |
|---|---|
| Schedule contract | `App\Contracts\CardSettlement\PayoutTermsResolver` (sibling of `SettlementReportParser` — the parser says what a line is, this says how it gets paid) |
| Config-table base + NETS | `Services/CardSettlement/Payout/{ConfigPayoutTermsResolver,NetsPayoutTermsResolver}` — another acquirer is a 3-line subclass plus a `payout_terms.<key>` block |
| Banking days | `Services/CardSettlement/Payout/BankingCalendar` (singleton; loads `holiday_days` once per request) |
| Entry point | `Services/CardSettlement/Payout/SettlementPayoutResolver` → `App\Support\CardSettlement\SettlementPayout` (`shortDate()`, `gateway()`, `describe()` = the tooltip) |
| Grid | one bounded per-page lookup in `VendController::transactionIndex` (`matched_vend_transaction_id` is UNIQUE, so PK-shaped), three fields on `VendTransactionResource`, column in `Pages/Vend/Transaction.vue` |
| Tests | `tests/Unit/NetsPayoutTermsResolverTest.php`, `tests/Feature/CardSettlementPayoutDateTest.php` |

Not done, deliberately: the CSV export has no Settle Sync column either, so it got no Settlement Date
column; and there is no SQL-level filter on the settlement date — **that** is the day a persisted
column on `card_settlement_rows` earns its migration, not before.

## Semantics / invariants

- `card_settlement_synced_at` means "confirmed by an uploaded acquirer settlement report". It is
  display/reporting metadata — **it does not feed the revenue gate** (`settlement_status` is
  untouched; do not overload it, per the model's docs).
- Matched-but-refunded sales sync too (the money did flow at the terminal; the reversal is separate).
- Retained-credit settlements are excluded as candidates up front — they will NEVER appear in a
  NETS report, and letting them match would steal a genuine sale's line.
- Matching runs `withoutGlobalScopes` (queued job, no auth; operator scopes are viewer boundaries,
  not system ones). The pages are permission-gated to HappyIce admin roles — NETS account H06228 is
  HIPL-wide, so no operator scoping is applied to the reconcile screens.

## Deploy / first run

1. Migrations: two new tables + INSTANT nullable column add on `vend_transactions` (no index).
2. `php artisan db:seed --class=RolePermissionSyncSeeder` (new `card-settlements` tuple).
3. `php artisan card-settlement:import-bindings database/data/card_terminal_bindings_nets_2026-08.csv --apply`
   (dry-run first; expect ~312 rows; unknown machine codes are reported and skipped).
4. Upload the August reports (Transactions → Card Settlement), oldest first; resolve queries; Sync.
   Prefer re-downloading raw CSVs from MerchantConnect over the Excel-damaged copies.

## Known follow-ups

- PAX terminals have their own sheet tabs (PAX26-xx) and a different report format — the provider
  registry is ready, the parser is not written.
- The binding sheet's monthly history before 2026-08 was not imported; if pre-August reports are
  ever uploaded, matching needs those effective-dated rows (import another CSV, same command).
- No index on `vend_transactions.card_settlement_synced_at` yet — add one if an "unsynced card
  sales" filter is ever queried at scale.

## Reconciliation review 2026-09-05 (35 reports, Aug 1 – Sep 4) — patterns and guards added

- Match rate 98.8% (79,499 / ~80,500 purchase lines). Unmatched: 650 "no sale in window" (0.8%, 219
  terminals — TRADE loss), 202 double taps (135 terminals, diffuse = customers, not machines), 147 on
  6 unbound TIDs, ~40 "on another machine".
- **Nothing was synced** — every report sat in `review`; matching alone stamps nothing.
- Binding churn: 24 TIDs with ≥ 2 bindings. Real moves have a clean cut in the per-day matched
  machine (23104047: 2810 → 2637 on Aug 12; 23102916: 2310 → 4609 by Aug 7; 23082812: 2696 → 2003 on
  Sep 2). A batch of bindings dated Aug 21 is an artefact: `bound_from` = the day the report was
  reviewed, not the day the terminal moved, leaving Aug 5–20 unbound for 23005588 / 23107346 /
  23100703 / 23100717 → `bindUnbound` now widens back via `moveToVend` instead of "already current".
- False flip: 23102952 went 4183 → 2337 → 4183 for one day on ONE \$1.60 line whose sale sat 19 s
  BEFORE the terminal time (bystander; 4183 was offline that afternoon). Suspect suggestions with
  < `MIN_LINES_TO_MOVE_TERMINAL` (2) fitting lines are now `weak`: shown, no checkbox, skipped by
  the bulk move.
- Machines losing TRADEs (NETS has the sale, ConnectVend doesn't): 2518 (Aug 30–31, 0 sales recorded
  on Aug 31 while NETS has 15; also the slowest TRADE lag, avg 40 s), 4605 (Aug 6–9, ~50% loss),
  2399 (chronic ~13%). Most reversals: 2673 (11), then 2475 / 2573 / 2674 / 2749 / 2737 (7 each).
