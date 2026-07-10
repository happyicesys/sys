# Refund Settlement — Plan & Design

> **STATUS: design / planning (2026-07-05). Nothing built yet. No schema or infra changes until approved.**
> Companion to `REFUND_REQUEST_SYSTEM_PLAN.md`. This adds a **batch settlement layer** on top of the existing refund module so the CIMB bulk file is no longer exported the instant a ticket is approved.

---

## 1. Why this change

Today "Export CIMB Bulk" on `/refunds` builds a bank file on the spot from whatever Approved PayNow tickets are selected, and marks them scheduled/completed immediately. Exporting the moment a ticket is approved **confuses operations** — there is no controlled "this is the batch we are paying now" boundary, and the same ticket can drift toward a second export.

The fix is to insert a **Refund Settlement** between approval and payout: approved tickets are **pooled** into a dated settlement, an admin **closes** it at a cutoff, the CIMB file is exported **from the settlement** (not from the ticket list), and only after the bank is paid does the admin **mark the paid tickets done** — which flows the "done" status back to the refund request page. One controlled batch boundary → no premature or duplicated exports.

The UI name is **Refund Settlement** (user requirement). Under the hood it reuses/extends the existing `RefundPayoutBatch` class — same architecture, given a lifecycle and a page.

---

## 2. Where it lives

New sub-tab **Refund Settlement** under the **Transactions** menu in `resources/js/Layouts/Authenticated.vue`, next to **Refund Requests**. Routes `/refund-settlements` (index) and `/refund-settlements/{settlement}` (detail), gated by refund permissions (reuse `payout refunds` for close/export/mark-done, `read refunds` for view).

The existing `/refunds` "Export CIMB Bulk" button is **removed / hidden behind permission** so ops cannot bypass the batch flow — that removal is the whole point. The `/refunds` list keeps `Export for Bank TXF` and `Refund Done?` as **read-only mirrors** of the owning settlement's state.

---

## 3. Lifecycle

```
                approved + paynow ticket
                          │  (observer: find-or-create today's OPEN settlement for this payout group)
                          ▼
   OPEN  ──(admin adds/removes approved tickets; cutoff by time or count)──▶  CLOSED
                          │                                                      │
   (post-close same-day approvals spawn a new OPEN, batch -02)                   ▼
                                                                          EXPORTED (CIMB .txt generated + stored)
                                                                                 │
                                              (admin ticks paid rows, clicks "Mark refund done")
                                                                                 ▼
                                                                    DONE-tickets → RefundTicket.status = completed
                                                            (unchecked/bounced rows stay pending in the settlement)
```

A settlement is never fully "done" as one object — **individual ticket rows** are marked done. A settlement is considered settled when all its rows are either completed or removed.

---

## 4. The shared-account problem → **payout groups**

**Constraint:** CIMB requires one originating account (account_no + account_name) per bulk file, and `CimbBizChannelTemplate::generate()` today reads that account off a single `operator`. Its export path also **rejects mixed operators** in one file.

**Reality:** HIPL, LEA, HIESG, UL-ST, HIMD all pay refunds from the **same** CIMB account. They are split into separate operator IDs only for management. Third-party operators each pay from their own account. So keying the account off `operator_id` produces five files where you want one.

Note the current relationship is not even modelled — the `HIPL → {HIMD, LEA, HIESG, UL-ST}` set is a **hardcoded literal** in `resources/js/Pages/Vend/CustomerIndex.vue` (the operator multi-select default). We promote that to data.

### Solution: a general `payout_groups` table that owns the bank account

```
payout_groups
  id
  code            (e.g. HIPL)        -- used in settlement reference
  name
  bank_id         (FK banks, for BIC)
  bank_account_no
  bank_account_name
  is_active
  timestamps

operators
  + payout_group_id  (nullable FK → payout_groups)
```

- Seed **one** group ("HIPL Group") holding the shared CIMB account; assign HIPL, LEA, HIESG, UL-ST, HIMD to it.
- The account lives **once** on the group — no risk of the five operators drifting to different numbers.
- **Settlement head** = `operator.payout_group_id`. Ungrouped operators fall back to their own `operators.bank_account_no` (head = the operator itself), then to `config('refund.banks.cimb')`. So third parties keep their own account and their own file automatically.
- **One file, by construction:** a settlement is keyed by the payout group, so every approved ticket from any of the five operators pools into the *same* settlement and exports as one `.txt` with one header account. No mixed-operator 422 is possible.

Chosen **general** (not refund-only) so the same grouping can later back the **commission CIMB export** (`project_commission_cimb_export`), which has the identical shared-account issue. Solve it once.

Only code change in the existing export path: the account resolver in `CimbBizChannelTemplate::generate()` reads **payout group → operator → config** instead of **operator → config**. Format, header/detail records, and `.txt` output are unchanged.

### Bonus cleanup
The hardcoded HIPL list in `Vend/CustomerIndex.vue` can be replaced by "operators where `payout_group_id` = my group", so the same data drives both settlement grouping and the operator multi-select default. (Flagged as an infra-touching change — see §9.)

---

## 5. Settlement reference (RST) & numbering

Format: **`RST-{yymmdd}-{groupcode}-{NN}`** — e.g. `RST-260705-HIPL-01`.

- `yymmdd` = app-local date (app TZ = operator TZ per deployment model, so no TZ juggling).
- `groupcode` = payout group `code` (or the operator code when ungrouped). Makes the ref self-describing and prevents two groups colliding on the same day.
- `NN` = per-`(date, group)` sequence. **Batch 2** (`-02`) is a *reopen* after close on the same day, not a second group.

**Concurrency:** the per-day counter is **not** the primary key, so two simultaneous approvals on a fresh day could both try `-01`. Guard with a unique DB index on `(settlement_date, payout_group_id, sequence)` + an atomic find-or-create (row lock / `insertOrIgnore` then re-select). This differs from the `SET-000123` / `BATCH-000045` refs, which just pad the primary key and need no counter.

---

## 5a. Settlement is PayNow-only; PayPal is marked done on the index

**Revised 2026-07-06.** A settlement handles **PayNow only** → **CIMB bulk `.txt`** via `CimbBizChannelTemplate`, subject to the one-originating-account rule (grouped by payout group). `nayax_auto`/`auto_resolved`, Card-terminal, and `none`-method tickets never enter a settlement.

**PayPal is excluded from settlements.** PayPal refunds are paid manually, so the admin marks them done directly on the **Refund Requests** page: the batch toolbar is method-aware — PayNow selection shows **Push to Settlement**, PayPal selection shows **Mark as done** (completes the ticket + sends the completion email via `/refunds/batch/complete`); a mixed selection prompts the admin to pick one method. The settlement's PayPal stream / `.xlsx` export code remains in place but dormant (no PayPal ticket ever enters), so it can be re-enabled later without a migration.

---

## 6. Data model

Reuse **`refund_payout_batches`** as the settlement object (it already has `csv_path`, `count`, `total_cents`, `status`, `uploaded_at`, `tickets()` via `RefundTicket.payout_batch_id`). Add the lifecycle it lacks:

`refund_payout_batches` (extend)
- `+ settlement_date` (date, the yymmdd bucket)
- `+ payout_group_id` (nullable FK)
- `+ sequence` (int, the NN)
- `+ status` values extended: `open`, `closed`, `exported`, `done` (mapping the old `generated`/`uploaded`)
- `+ closed_by / closed_at`, `exported_by / exported_at`
- unique index `(settlement_date, payout_group_id, sequence)`

`RefundTicket` — no new columns; keep `payout_batch_id` as the link. A ticket enters a settlement from **Approved** with method `paynow` or `paypal` (it lands in its stream by `refund_method`). Its per-row "done" is the existing `status = completed` + `paid_at` + `completed_at`.

`refund_settlement_exports` (new child — one row per generated file) — `id`, `settlement_id` (→ `refund_payout_batches`), `method` (`paynow`/`paypal`), `format` (`cimb_txt`/`xlsx`), `file_path`, `count`, `total_cents`, `exported_by`, `exported_at`. Lets one settlement carry two artifacts (CIMB `.txt` + PayPal `.xlsx`), supports re-download of the stored file, and is its own audit of who exported what and when. The batch's single legacy `csv_path` becomes first-PayNow-only.

`payout_groups` + `operators.payout_group_id` — as §4.

**Audit** — reuse the existing per-ticket log (`RefundPayoutCsvService` already writes an `exported` line per ticket) and add settlement-level entries. Simplest is a small `refund_settlement_logs` (or reuse `refund_ticket_logs` with a `batch_id`) capturing `actor_id`, `action`, `created_at` for: `entry_added` (ticket + by whom), `entry_removed`, `closed`, `exported_cimb` (file ref + who/when), `marked_done` (which tickets). Surfaced as an audit trail on the settlement detail page, mirroring `Show.vue`. The app-wide `UserLog` wildcard listener also captures the row writes at DB level, so who/when is answerable two ways.

---

## 7. Behaviours & guards (the blind spots)

- **Approved-only intake (manual push).** Admin selects Approved tickets on `/refunds` and clicks **Push to Settlement**; the service find-or-creates the day's OPEN settlement per payout group. Only `approved` + `paynow`/`paypal` tickets are accepted; Nayax/`auto_resolved`, Card-terminal and `none`-method tickets are ignored. (Built as an explicit push rather than an approval observer — more controllable.)
- **Live totals, re-filtered at export.** `count`/`total_cents` are derived live from currently-eligible member tickets, not frozen at pool time. If a pooled ticket is later Rejected or moved to Pending-info, it drops out; the CIMB generator re-asserts "Approved + PayNow + no conflicting active refund" at the moment of export (same defensive check `completeBatch()` uses today).
- **Per-ticket done, not batch done.** Settlement detail lists member tickets with a **leftmost checkbox + select-all header**. Admin ticks the rows the bank actually paid and clicks **Mark refund done**; only checked rows go `completed` (+ `paid_at`, completion email per existing flow). Bounced / wrong-PayNow / wrong-phone rows are left unchecked and stay pending for fix or re-export. No all-or-nothing.
- **Double-refund guard preserved.** `RefundTicket::conflictingRefund()` over `ACTIVE_REFUND_STATUSES` still blocks a second ticket for the same transaction; entering a settlement puts the ticket in an active status so a sibling can't slip through the widened approve→pay window.
- **Re-export vs re-generate.** Re-download serves the **stored** `csv_path`; it does not regenerate a new batch ref / new money instruction. Regeneration is locked once `exported`.
- **Stale / empty settlements + nightly auto-close.** Admin closes manually, but a scheduled command **`refund-settlements:auto-close`** (daily 23:58, app TZ) is a safety net: it closes any still-open settlement at end of day, and **voids** empty ones (all tickets removed). An `OPEN` settlement whose `settlement_date` is in the past still shows a **stale** badge until the cron (or the admin) closes it.
- **Originating-account guard (verified gap).** `CimbBizChannelTemplate::generate()` today throws only if *both* the operator field and the config default are blank — so a non-HIPL operator with empty bank fields would be silently paid from HIPL's env account. New rule: resolve the CIMB account from the settlement's **payout group → operator only**; if empty, **block export** with a clear message — never fall through to the global `config` default for a non-HIPL settlement. Best practice: store HIPL's account on its payout-group row so `config` stops being a silent catch-all.
- **PayNow proxy validity (verified gap).** `paynowMobile()` falls back to the raw value when parsing fails, so a bad number ships a malformed row the bank rejects. Validate each PayNow proxy at **approval** (so ops can request corrected info early) and again at **close**; flag invalid rows so they cannot be selected for export.
- **Remove (Open only).** While a settlement is **Open**, the admin can **Remove** a member ticket — it goes back to `approved` (unlinked) so it can be pushed into another settlement. Once **Closed/Exported the pool is locked** — no removal (enforced in `returnToPool()` + the UI hides the button). Bounced rows after export are handled by leaving them unchecked at Mark-done (they stay pending in the settlement).
- **Freeze on export.** Once a row is in an EXPORTED settlement, its `payout_destination`, `claimed_amount_cents`, and status are **locked** (enforced in the model, not just the UI); the only way to change them is Return to pool, which re-opens the fields in the new pool.

---

## 8. Access control

Filtering is by **payout group / operator scope**, consistent with the existing rule: operator_id 1 (HIPL) has global visibility (`OperatorFilterScope` nullifies the filter), while third-party operators see only their own operator → their own group → their own settlements. No new auth system.

**Maker-checker (recommended, not required for v1).** Close, export, and Mark-done all reuse `payout refunds` today, and every action is logged with a distinct actor. For stronger financial control later, split into `settle refunds` (close/export) and `complete refunds` (mark-done) so the person moving money is not the one closing the books.

---

## 9. What touches shared infra (flag before build — per "don't affect other infra")

1. **`payout_groups` table + seed** (assigns HIPL/LEA/HIESG/UL-ST/HIMD to one group). Additive, but it is new master data — needs your sign-off on membership + the CIMB account values.
2. **`operators.payout_group_id`** column (nullable, additive).
3. **`CimbBizChannelTemplate::generate()`** account resolver change (group → operator → config). This edits the **live commission/refund export path**, so behaviour-preserving for ungrouped operators must be verified.
4. **`Vend/CustomerIndex.vue`** operator multi-select default swapped from the hardcoded list to group-driven. Touches a page outside the new tab.
5. **Nav item** in `Authenticated.vue` + new routes/permissions (additive).

Everything else (the `refund_payout_batches` columns, the settlement controller/observer/pages, the audit log) is new and self-contained.

---

## 10. Migrations (anticipated)

- `..._create_payout_groups_table`
- `..._add_payout_group_id_to_operators`
- `..._add_settlement_lifecycle_to_refund_payout_batches` (settlement_date, payout_group_id, sequence, status values, closed/exported audit cols, unique index)
- `..._create_refund_settlement_logs_table` (or extend `refund_ticket_logs`)
- `..._create_refund_settlement_exports_table` (per-file export records, both streams)
- seeder: `PayoutGroupSeeder` (HIPL group + memberships) — idempotent, run-once

---

## 11. Build phases

- **Phase 1 — grouping foundation:** `payout_groups`, `operators.payout_group_id`, seed, and the `CimbBizChannelTemplate` resolver change. Verifiable in isolation (export still works, now group-aware).
- **Phase 2 — settlement object + observer:** extend `refund_payout_batches`, RST numbering, auto-create-on-approval, open/closed lifecycle, audit log.
- **Phase 3 — Refund Settlement UI:** index + detail pages (two streams), close button, **CIMB `.txt`** (PayNow) + **`.xlsx`** (PayPal) export, per-stream checkbox + Mark refund done, read-only mirror columns on `/refunds`, remove the old export button.
- **Phase 4 — polish:** stale badge + Return-to-pool, proxy-validity surfacing, freeze-on-export enforcement, and (optional) point the commission CIMB export at payout groups too.

---

## 12. Decisions locked (2026-07-05)

- **Close = manual, + nightly auto-close safety net (revised 2026-07-06).** Admin closes when ready; the scheduled command `refund-settlements:auto-close` (23:58 app-TZ, `Console\Kernel`) closes any still-open settlement at end of day and voids empty ones. `OPEN` settlements past their date still get a stale badge (§7).
- **Account resolver = payout group → `operators.bank_account_no`**, and for a **non-HIPL** settlement stop there (block if empty); `config('refund.banks.cimb')` is HIPL-only, not a silent global catch-all (§7).
- **Two streams per settlement.** PayNow → CIMB `.txt`; PayPal → `.xlsx`. Each exported and marked-done independently (§5a).
- **Singapore only (for now).** Built for the SG instance; ID/TH out of scope. A single config flag (default on for SG) hides the tab/export elsewhere — no multi-currency work.

## 13. Still open before build

1. **Group membership + account:** confirm the five operators in the HIPL group and the exact shared CIMB account_no / account_name (seeder can ship with placeholders you fill).
2. **PayPal `.xlsx` columns:** confirm the exact columns finance wants (proposed: reference, PayPal email, amount, operator, date).
3. **Audit store:** dedicated `refund_settlement_logs` vs. extending `refund_ticket_logs` with a batch id.
4. **Legacy in-flight tickets:** recommended to **drain** any currently-`scheduled` tickets through the old flow before deploy so the new lifecycle starts clean; otherwise map them into a synthetic closed/exported settlement.
5. **Beneficiary name (minor):** CIMB rows currently use the email local-part as payee name; decide whether to capture a real name in the refund form or leave as-is.

---

## As-built (2026-07-06)

**Built as a manual push flow** (not an approval observer) per your instruction to keep the batch section + checkboxes on the Refund Requests page. Intake = admin selects Approved tickets → **Push to Settlement** → day's OPEN settlement per payout group (find-or-create). One settlement holds both streams; PayNow → CIMB `.txt`, PayPal → `.xlsx`. Lifecycle `open → closed → exported → done`; per-row checkbox marks the actually-paid rows done. Reuses/extends `RefundPayoutBatch`. No new permissions (reuses `read`/`payout refunds`).

**Files added**
- Migrations `2026_07_05_120000..120004`: `payout_groups`, `operators.payout_group_id`, settlement lifecycle cols + unique index on `refund_payout_batches`, `refund_settlement_logs`, `refund_settlement_exports`.
- Models: `PayoutGroup`, `RefundSettlementLog`, `RefundSettlementExport`; extended `RefundPayoutBatch` (statuses `open/closed/exported/done`, `settlements()` scope, `isStale()`) and `Operator` (`payout_group_id` + `payoutGroup()`).
- Seeder: `PayoutGroupSeeder` (HIPL group + HIMD/LEA/HIESG/UL-ST, **placeholder account — set the real one**).
- Service: `app/Services/Refund/RefundSettlementService.php` (push / close / exportCimb / exportXlsx / markDone / returnToPool / voidEmpty; RST-`yymmdd`-`code`-`NN` numbering; account resolver blocks the config fallback for non-HIPL).
- Edited `CimbBizChannelTemplate::generate()` to accept `meta['originating_account']` (legacy `/refunds` export path unchanged).
- Controller `RefundSettlementController` + `/refund-settlements/*` routes.
- Vue: `Pages/RefundSettlement/Index.vue` + `Show.vue`, nav item under Transactions, repurposed the (previously `v-if="false"`) batch toolbar + checkboxes on `Pages/Refund/Index.vue` to **Push to Settlement**, and made the "Export for Bank Txf" column link to the settlement.

**Deploy steps (you run these):**
1. `php artisan migrate` — 5 additive migrations (the `refund_payout_batches` alter + unique index is the only touch to an existing table; legacy rows keep `is_settlement=false`).
2. `php artisan db:seed --class=PayoutGroupSeeder` — creates the HIPL group + memberships. **Then set the real shared CIMB `bank_account_no`/`bank_account_name` on that `payout_groups` row** (or via env before seeding).
3. `npm run build` — compiles the 2 new pages + the repurposed refund page + nav.
4. `php artisan route:clear` / refresh Ziggy if route names are cached.

**Verified:** all 3 Vue SFCs compile (`@vue/compiler-sfc`); independent code review found no blocking bugs (PHP couldn't be linted in-sandbox — no `php` binary).

**Freeze-on-settlement (added 2026-07-06):** `RefundTicketService::matchOrder()` and `clearMatch()` now block any ticket whose `payout_batch_id` points at a real settlement — the admin must **Return to pool** first. This enforces freeze-on-export and closes the re-match-while-scheduled edge in one guard (`assertNotInSettlement`); legacy non-settlement batches are exempt.

**Optional / not done (by design):** pointing the commission CIMB export at payout groups (separate feature); capturing a real beneficiary name (still email local-part).
