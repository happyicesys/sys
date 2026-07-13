# Site Settlement (commission / location-fee payout) — Plan & Design

> **STATUS: design / planning (2026-07-06). Nothing built yet. No schema or infra changes until approved.**
> Sibling to `REFUND_SETTLEMENT_PLAN.md` — the same open→closed batch lifecycle, applied to paying **sites** their monthly Net Location Fee instead of paying customers refunds.

---

## 1. Why

Today the Site Summary pays commission ad-hoc: an admin ticks locked-unpaid rows and hits **Export CIMB**, then separately **Mark Paid**. There's no controlled "this is the batch we're paying now" boundary, the export and the paid-flagging are disconnected, and nothing stops a row being exported twice or paid down two different paths.

A **Site Settlement** inserts the same controlled batch layer we built for refunds: pool the eligible site rows into a dated, per-payout-group settlement, close it, export one CIMB file, then mark the paid rows done — which drives the *existing* paid-flags + `customer_settlements` ledger. One boundary, no double-processing.

**Naming:** the batch object is a **Commission Settlement** (`commission_settlements`, ref `CST-…`). This is deliberately distinct from the existing `customer_settlements` **ledger** (SET/PMT/LF entries) — the ledger records money movements; the settlement is the payout batch that, on completion, *posts* to that ledger.

---

## 2. What's being settled (verified against the code)

The payable item is an eligible `customer_period_summaries` row (model `CustomerPeriodSummary`):

- **Amount = Net Loc Fee = `location_fees_cents − external_subsidize_cents`**, summed across a customer+month's machine-split segments; rows ≤ 0 are skipped. Both operands are frozen per-period snapshots at lock time.
- **Eligibility gate (reused everywhere today):** `is_locked` + not paid (`paid_at` null) + **not** current month + `year_month >= 2026-05-01` (the "2605" cutoff).
- **Beneficiary = the SITE (customer):** `customers.bank_account_number` / `bank_account_name` / `bank_id → banks.bic_code` (CIMB detail column E). A missing account or BIC blocks that site.
- **Debit side = the operator/payout-group account** (file header): `operators.bank_account_no/_name`, or — per the grouping decision below — the payout group's shared account.
- **Grouping signal:** every row carries `operator_id`; `customer → operator → payout_group_id`.

---

## 3. Lifecycle (mirrors refunds, two states)

```
   eligible site rows (Site Summary)
        │  admin selects → Push to Settlement
        ▼
   OPEN ──(admin closes)──▶ CLOSED
    │  (remove rows while open)          │
    │                                    ├── Export CIMB (per group, always available)
    │                                    └── Mark done (per row) → row paid + ledger credit
```

Only **open** and **closed** (same simplification as the refund settlement). Export records the file but doesn't change status; Mark-done completes the individual site rows and stays Closed. Reopen (Undo-close) allowed from Closed.

---

## 4. Grouping — reuse `payout_groups`

Key it by **payout-group head** (`operator.payout_group_id ?? operator`), exactly like refunds. So HIPL-family operators' site payouts pool into one `CST-…-HIPL-01` and export as a single CIMB file with the HIPL debit account. (The existing on-summary export groups by raw `operator_id` and zips multiple operators; the settlement supersedes that with the cleaner group-based single file.) Reuses the `PayoutGroup` model, the `Operator → payoutGroup` relation, and the head-resolution logic already written for refunds.

Reference: **`CST-{yymmdd}-{groupcode}-{NN}`** (e.g. `CST-260706-HIPL-01`), per-`(date, head)` sequence with a unique index, same as RST.

---

## 5. Data model

New batch table `commission_settlements` — mirrors `refund_payout_batches`:
- `id`, `reference` (CST-…), `settlement_date`, `payout_group_id` (nullable), `operator_id` (nullable, for ungrouped), `sequence`
- `status` (`open`|`closed`), `count`, `total_cents`
- `closed_by/closed_at`, `exported_by/exported_at`, `csv_path`, `created_by`, timestamps
- unique `(settlement_date, payout_group_id, operator_id, sequence)`

Membership — **the "new field to record"** (§ your requirement):
- `customer_period_summaries.commission_settlement_id` (nullable FK). Set when a row is pushed; cleared on remove; **retained after paid** as the audit link to the settlement it was paid through.

Audit + exports (reuse the refund pattern):
- `commission_settlement_logs` (actor/action/note/meta) — created, entry_added, entry_removed, closed, reopened, exported_cimb, marked_done.
- `commission_settlement_exports` (one row per generated file) — method `commission`, format `cimb_txt`, file_path, count, total, exported_by/at.

---

## 6. Push / eligibility (mutual exclusion with Mark-Paid)

Admin selects eligible rows on Site Summary and **Push to Settlement**. A row is push-eligible when: locked + unpaid + not current month + `>= 2605` + Net Loc Fee > 0 + **`commission_settlement_id` is null** + not `is_paid`.

Per your rule — **once Mark-Paid, a row can no longer be pushed** — and symmetrically, a row already in an open/closed settlement is excluded from the old on-summary batch Mark-Paid / Export CIMB (so it can't be paid down two paths). That's a small additive `whereNull('commission_settlement_id')` on the existing eligibility (flagged in §9).

Push find-or-creates the day's OPEN settlement for the row's payout-group head and stamps `commission_settlement_id`.

---

## 7. Export & Mark-done (reuse existing money machinery)

- **Export CIMB:** reuse `CimbBulkPaymentFile` + the site-beneficiary detail mapping from `CommissionCimbExportService`. Because a settlement is one payout group = one debit account, it's a single `.txt` (no zip). Same "block on any missing site account/BIC" guard. Refactor: extract the "build a CIMB file from a set of summary rows for one debit account" core out of `CommissionCimbExportService` so both the legacy on-summary export and the settlement call one implementation (flagged in §9).
- **Mark done (per row):** completes the checked site rows using the *existing* `batchMarkPaid` path — sets `is_paid/paid_at/paid_date/paid_by`, and posts the negative `customer_settlements` credit (`entry_type=payment`, `source=paid-action`, linked via `customer_period_summary_id`) + `CustomerSettlementLog`. So Payment History and the ledger update exactly as they do today; the settlement is just the workflow wrapper. Unchecked rows stay pending in the settlement.

---

## 8. Behaviours & guards (blind spots)

- **Live amount at export.** Net Loc Fee is frozen at lock, but re-verify each row is still locked+unpaid at export/mark-done (a row could be paid via the ledger elsewhere in between).
- **Missing site bank details block the file**, same as today — surface which sites are missing account/BIC before export (don't ship a partial file).
- **Paid-elsewhere race.** If a row is Mark-Paid on the summary while sitting in an open settlement, it must drop out of the settlement (or be blocked). The mutual-exclusion gate (§6) prevents entering; also re-filter at export/mark-done.
- **Remove (open only)** and **freeze after close**, same as refunds.
- **Amount override.** Batch Mark-Paid accepts a `paid_amount_cents`; decide whether settlement mark-done always pays the frozen Net Loc Fee or allows a per-row override (refunds allow a final-amount override).
- **Unlock/unpaid after settling.** Marking a row unpaid reverses its ledger credit today; decide whether that also detaches it from the settlement.
- **Current-month / cutoff** rows never enter (gate handles it).

---

## 9. What touches shared infra (flag before build)

1. **`customer_period_summaries.commission_settlement_id`** — additive nullable column + FK/index.
2. **Existing eligibility filters** (`batchMarkPaid`, `CommissionCimbExportService`, and the summary's push/paid queries) get an additive `whereNull('commission_settlement_id')` so settled rows don't double-process. Behaviour-preserving for rows not in a settlement, but it edits the live commission flow — needs sign-off.
3. **Refactor `CommissionCimbExportService`** to expose a reusable "build CIMB from summary rows + debit account" core (used by both legacy export and the settlement). Behaviour-preserving; verify the legacy on-summary export byte-for-byte.
4. New nav item + routes + admin permission (additive).

Everything else (the `commission_settlements` table, its controller/service/pages, logs/exports) is new and self-contained.

---

## 10. Migrations (anticipated)

- `..._create_commission_settlements_table`
- `..._add_commission_settlement_id_to_customer_period_summaries`
- `..._create_commission_settlement_logs_table`
- `..._create_commission_settlement_exports_table`
- permission seeder: `read/manage commission-settlements` (admin only)

---

## 11. Build phases

- **Phase 1 — object + push:** `commission_settlements` + membership column, push-from-Site-Summary (eligibility + mutual exclusion), open/closed lifecycle, RST-style numbering, audit log.
- **Phase 2 — export:** refactor `CommissionCimbExportService` core, export CIMB from a settlement (single group/debit account), exports table + re-download.
- **Phase 3 — mark done:** per-row checkbox + mark-done wired to `batchMarkPaid` + ledger credit; remove/return, reopen.
- **Phase 4 — UI:** Site Settlement index + detail pages (mirror refund settlement), nav item, the "Push to Settlement" batch action on Site Summary, and the read-only "In settlement" indicator/column there.

---

## 12. Decisions locked (2026-07-06)

- Settle the **site location fee / commission** (Net Loc Fee per site+period).
- **New membership field** on the period summary; **a Mark-Paid row can no longer be pushed** (mutual exclusion both ways).
- **Mirror the refund lifecycle** (open → closed, export CIMB, mark done).
- **Group per payout-group head** = `operator.payout_group_id ?? operator` (grouped operators share one CST/file; ungrouped operators settle on their own) — identical to refunds.
- **UI home:** Site Management ▸ **Site Settlement** (next to Site Summary).
- **Retire the on-summary Export CIMB** once settlements are live — commission payouts go through settlements only. Manual **Mark-Paid stays** (for payments made outside the CIMB batch), gated to exclude rows already in a settlement.
- **Amount = frozen Net Loc Fee**, no per-row override at mark-done.

## 13. Open / to confirm during build

1. **Does manual Mark-Paid stay fully?** Assumed yes (for out-of-band payments), just gated so a row in a settlement can't also be Mark-Paid. Flag if you'd rather Mark-Paid go away too.
2. **Legacy commission-export code** (`CommissionCimbExportService`) is refactored into a reusable core; the on-summary *button* is removed but the service stays (settlement uses it). Confirm no other caller depends on the old public method.
