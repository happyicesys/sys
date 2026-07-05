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

## 6. Data model

Reuse **`refund_payout_batches`** as the settlement object (it already has `csv_path`, `count`, `total_cents`, `status`, `uploaded_at`, `tickets()` via `RefundTicket.payout_batch_id`). Add the lifecycle it lacks:

`refund_payout_batches` (extend)
- `+ settlement_date` (date, the yymmdd bucket)
- `+ payout_group_id` (nullable FK)
- `+ sequence` (int, the NN)
- `+ status` values extended: `open`, `closed`, `exported`, `done` (mapping the old `generated`/`uploaded`)
- `+ closed_by / closed_at`, `exported_by / exported_at`
- unique index `(settlement_date, payout_group_id, sequence)`

`RefundTicket` — no new columns; keep `payout_batch_id` as the link. A ticket enters a settlement only from **Approved + PayNow**. Its per-row "done" is the existing `status = completed` + `paid_at` + `completed_at`.

`payout_groups` + `operators.payout_group_id` — as §4.

**Audit** — reuse the existing per-ticket log (`RefundPayoutCsvService` already writes an `exported` line per ticket) and add settlement-level entries. Simplest is a small `refund_settlement_logs` (or reuse `refund_ticket_logs` with a `batch_id`) capturing `actor_id`, `action`, `created_at` for: `entry_added` (ticket + by whom), `entry_removed`, `closed`, `exported_cimb` (file ref + who/when), `marked_done` (which tickets). Surfaced as an audit trail on the settlement detail page, mirroring `Show.vue`. The app-wide `UserLog` wildcard listener also captures the row writes at DB level, so who/when is answerable two ways.

---

## 7. Behaviours & guards (the blind spots)

- **Approved-only intake.** Observer fires on the transition into `approved` + `paynow`. Nayax/`auto_resolved` and Card-terminal tickets never enter a CIMB PayNow settlement.
- **Live totals, re-filtered at export.** `count`/`total_cents` are derived live from currently-eligible member tickets, not frozen at pool time. If a pooled ticket is later Rejected or moved to Pending-info, it drops out; the CIMB generator re-asserts "Approved + PayNow + no conflicting active refund" at the moment of export (same defensive check `completeBatch()` uses today).
- **Per-ticket done, not batch done.** Settlement detail lists member tickets with a **leftmost checkbox + select-all header**. Admin ticks the rows the bank actually paid and clicks **Mark refund done**; only checked rows go `completed` (+ `paid_at`, completion email per existing flow). Bounced / wrong-PayNow / wrong-phone rows are left unchecked and stay pending for fix or re-export. No all-or-nothing.
- **Double-refund guard preserved.** `RefundTicket::conflictingRefund()` over `ACTIVE_REFUND_STATUSES` still blocks a second ticket for the same transaction; entering a settlement puts the ticket in an active status so a sibling can't slip through the widened approve→pay window.
- **Re-export vs re-generate.** Re-download serves the **stored** `csv_path`; it does not regenerate a new batch ref / new money instruction. Regeneration is locked once `exported`.
- **Stale / empty settlements.** Define whether an un-closed `OPEN` settlement auto-closes at a daily cutoff (cron) or just warns; and void an `OPEN` settlement whose only ticket got rejected (never export empty).

---

## 8. Access control

Filtering is by **payout group / operator scope**, consistent with the existing rule: operator_id 1 (HIPL) has global visibility (`OperatorFilterScope` nullifies the filter), while third-party operators see only their own operator → their own group → their own settlements. No new auth system.

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
- seeder: `PayoutGroupSeeder` (HIPL group + memberships) — idempotent, run-once

---

## 11. Build phases

- **Phase 1 — grouping foundation:** `payout_groups`, `operators.payout_group_id`, seed, and the `CimbBizChannelTemplate` resolver change. Verifiable in isolation (export still works, now group-aware).
- **Phase 2 — settlement object + observer:** extend `refund_payout_batches`, RST numbering, auto-create-on-approval, open/closed lifecycle, audit log.
- **Phase 3 — Refund Settlement UI:** index + detail pages, close button, export-from-settlement, per-ticket checkbox + Mark refund done, read-only mirror columns on `/refunds`, remove the old export button.
- **Phase 4 — polish:** stale-settlement cutoff cron, re-export download, and (optional) point the commission CIMB export at payout groups too.

---

## 12. Open questions before build

1. **Group membership + account:** confirm the five operators in the HIPL group and the exact shared CIMB account_no / account_name.
2. **Cutoff:** is close always manual, or should a cron auto-close `OPEN` at a set time? If a count threshold, what number?
3. **Ungrouped fallback:** for third-party operators, use `operators.bank_account_no`, or require every operator to belong to a (singleton) payout group so the account always lives in one place?
4. **Audit store:** dedicated `refund_settlement_logs` vs. extending `refund_ticket_logs` with a batch id.
5. **Legacy:** any already-exported `refund_payout_batches` rows to backfill into the new statuses, or start clean from first `OPEN`.
