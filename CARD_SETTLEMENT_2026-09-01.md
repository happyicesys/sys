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
- **A CSV opened + re-saved in Excel loses the hour**: `21:48:59` becomes `48:59.0` (and Merchant ID
  becomes `1.11E+11`). 29 of the 30 August files are damaged like this; only the 30/8 file was raw.
  The parser handles both — damaged rows are flagged `time_is_partial` and match circularly on mm:ss
  within the hour; two plausible hours ⇒ AMBIGUOUS for the user. **Upload raw portal downloads.**
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
| UI | `Pages/CardSettlement/{Index,Show}.vue` (Transactions menu → "Card Settlement"); Sales Transactions grid got a "Settle Sync" ✓/✗ column (card-terminal rows only, plain select column, no extra query) |
| Permission | `card-settlements` (read/create/update/delete) → superadmin/admin/supervisor, in `RolePermissionSyncSeeder` |
| Bindings import | `php artisan card-settlement:import-bindings <csv> [--apply]` (dry-run default) |

## Row lifecycle

PENDING → MATCHED / UNMATCHED (`No terminal binding` · `No matching sale in window` ·
`All matching sales already claimed`) / AMBIGUOUS (user picks a candidate) / IGNORED (Logon,
user-dismissed) / DUPLICATE. Claims are unique both directions (fingerprint UNIQUE;
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
  `RefundTicketService::markAutoRefundedByCharge` — the exact write path the inference used. A sale
  already `is_refunded` (any source) is left as it is. `refunded_count` on the report.
- **`config('refund.card_reversal_terminals')` is now `[]`** — the TRADE-footprint inference
  (`card_terminal_reversal`) no longer fires for NETS. Consequence to remember: a NETS reversal is
  known only after that day's report is uploaded and synced, not at TRADE time, so a refund ticket
  raised in between is only auto-crossed on Sync. The Show page flags the opposite case too —
  a sale the old inference marked refunded but the report has no reversal for ("⚠ refunded by
  inference, no reversal in report") — for the historical August uploads; it does not un-refund.
- `matched_count` / "Sync N matched" counts purchase lines only; paired reversal lines are MATCHED
  but claim no sale (the UNIQUE `matched_vend_transaction_id` stays with the purchase line).

## Payment Status, Auto-refunded, Refund Request badge — one source per rail (2026-09-02)

| Rail | "Auto-refunded?" / `is_refunded` | Payment Status (`SaleStatus::payment`) |
|---|---|---|
| Omise | API job / `refund.create` webhook / `refund:sync-omise` → `OmiseRefundRecorder` | Paid → Refunded |
| Midtrans | `refund` / `partial_refund` webhook → same recorder, source `midtrans_external` (**new** — before this the webhook only flipped the gateway log) | Paid → Refunded |
| NETS card | settlement-report reversal line → `CardSettlementSyncService` (`settlement_report_reversal`) | Paid → **Settled** (stamp) → Refunded (reversal) |
| Cash | — | Paid |

Every one of those writes goes through `RefundTicketService::markAutoRefundedByCharge`, which sets
`auto_refund_detected` (and pulls approved/scheduled tickets back to Rejected); the
`RefundTicketObserver` → `RefundRequestSync` chain then refreshes the denormalised
`refund_request_*` columns, so the **Refund Request badge** on Sales Transactions follows without
any extra wiring. Dispense Status is the machine's verdict and never changes from any of this.

**File storage:** uploads go to the private `digitaloceanspaces` disk (S3-compatible;
`CARD_SETTLEMENT_DISK` to override, `local` fallback without credentials) under `card-settlements/`,
recorded on `card_settlement_reports.storage_disk`, and are served only via the authed
`GET /card-settlements/{id}/download`. Nothing lands in the app's public disk.

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
