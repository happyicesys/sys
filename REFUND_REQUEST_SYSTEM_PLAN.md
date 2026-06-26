# Customer Refund Request System — Plan & Build

> **BUILD STATUS (2026-06-26): Phases 1–4 implemented in this repo.** See "As-built" at the bottom for the file list and the deploy steps you must run (migrate + npm build). Below is the design; it matches what was built.

---


Replacing the third‑party Jotform refund flow with a self‑hosted, transaction‑aware refund module inside **mark1 (sys)**. Customers scan a QR on the machine, the form auto‑matches their recent transaction, and Ops/Admin/Manager process the refund through a tracked workflow with bulk bank payout and automated emails.

Status: planning / design. Nothing here is built yet. No schema or infra changes are made until you approve.

---

## 1. Why move off Jotform

The current Jotform flow (per the attached workflow sheet) has Ops manually cross‑referencing each free‑text submission against `sys` transactions, Admin keying PayNow numbers into the bank one‑by‑one, and all status/email tracking living in inboxes. It is slow and hard to audit.

The proposed "Form (fr sys)" flow fixes the root cause: because the form lives inside mark1, it already knows which machine the customer scanned (`machineID`) and can show them their actual transactions to pick from — eliminating the manual matching step entirely, and giving every refund a tracked status and an audit trail.

| | Current (Jotform) | Proposed (sys form) |
|---|---|---|
| Identify transaction | Customer types free text; Ops matches by hand | Form auto‑lists 2–3 candidate transactions by `machineID` + time; customer taps one |
| Refund scope | Implicit / free text | Customer **ticks the exact failed item(s)** from the transaction's line items; amount is derived, not typed |
| PayNow/PayPal capture | Email back‑and‑forth | Captured + validated at submission |
| Bank payout | Key in one‑by‑one | **Bulk PayNow CSV** export → upload to bank |
| Auto‑refunds (not dispensed) | Found manually | System flags transactions already auto‑refunded → instant "already refunded" reply |
| Status & approvals | In inboxes | Tracked status machine + Ops→Admin→Manager approval + audit log |
| Customer emails | Manual copy/paste templates | Auto‑sent from system using stored templates |

---

## 2. Recommendation: hosting & rollout

**Host inside mark1 (sys).** The whole value of the redesign is reading `vend_transactions` / `payment_gateway_logs` to auto‑match. A standalone app would have to duplicate that matching logic over an API — more surface, more drift. Build it as a module in mark1 with a public (unauthenticated) customer route and an authenticated admin section.

**Rollout: gradual, per‑machine coexistence (recommended).** The `mqtt_settings` table already stores `refund_request_form_url` **per row**, and the APK appends `?machineID=<vend_code>` when it serves the URL to the machine. So coexistence is free: leave most rows pointing at Jotform, switch a pilot operator/region's row to the new mark1 URL, validate end‑to‑end, then migrate the rest. No big‑bang cutover, easy rollback (revert the URL value). This also satisfies your "don't affect other infra" rule — switching is a data change to one column value, not a schema change.

Suggested sequence: (1) build module behind a feature flag, (2) pilot on one `mqtt_settings` row, (3) widen row by row, (4) retire Jotform once all rows are migrated.

---

## 3. How the APK reaches the form

No APK change needed. Today `VendMqttController` returns:

```php
'refund_request_form_url' => $vendMqtt->mqttSetting->refund_request_form_url
    ? $vendMqtt->mqttSetting->refund_request_form_url.'?machineID='.$vendMqtt->vend_code
    : '',
```

So a machine receives e.g. `https://lsh.happyice.com.sg/refund?machineID=SG-00123`. The APK turns that into the on‑screen QR. To switch a machine to the new form, set its `mqtt_settings.refund_request_form_url` to the new mark1 route. Everything downstream (the `?machineID=` append) already works.

> Note: `delivery_complaint_form_url` exists in the schema but is currently commented out in the controller — the same pattern can later serve a complaint form if wanted.

---

## 4. Customer-facing flow (mobile-first, guided)

Deliberately **not** Jotform's wall of fields. One decision per screen, plain reassuring language, big tap targets — guide the customer instead of dumping a form. The customer narrows down their own transaction (day → amount), so the system shows a short, confident candidate list instead of asking them to describe it.

1. **Hero / start.** A single big friendly button: *"Ice cream didn't come out? 🍦 Request a refund."* Reassures them they're in the right place; shows the machine name/location resolved from `machineID`.
2. **When did you buy?** Two big buttons: **Today** or **Yesterday** (covers the realistic window; sets the date range). No date picker.
3. **How much did you pay?** Large numeric amount entry (keypad‑friendly). This is the main disambiguator.
4. **Is this your purchase?** The system shows the **matching transactions** for that machine + day + amount, each with its **product(s)**, time, and payment method. Customer taps theirs.
   - 1 match → pre‑selected, just confirm. Multiple → pick. **No match** → friendly "we couldn't find it" screen with a manual path (approx time + note) flagged for Ops.
   - If the tapped transaction was **already auto‑refunded** (gateway `is_dispensed = false` / status REFUND), tell them right there — no waiting on Ops.
5. **Pick the problem item(s).** No "full vs partial" choice — the system already knows the line items. The form lists the transaction's `vend_transaction_items` (product + price + channel); the customer ticks the one(s) that failed (one, several, or all). **Single‑item purchases are auto‑selected.** Only ticked items are submitted for refund.
6. **Reason.** Short dropdown (not dispensed / partially dispensed / wrong item / quality / double charge / other) + optional note.
7. **Where to send the refund.** PayNow (mobile / NRIC / UEN) **or** PayPal (email), light validation, plus a contact for updates.
8. **Review → submit → reference.** Summary card → submit → confirmation screen with a screenshot‑able reference (`RFD-000123`) + optional email.

UX/best practices: progress indicator, inline validation, no login, fast first paint, works on low‑end Android webview, localized (you run per‑country instances — EN/ID/TH already exist in the APK strings). Rate‑limit by machine + IP; block duplicate requests against the same transaction.

---

## 4a. Matching logic & endpoint contract (verified against schema)

**Verified data path:** `machineID` (query param, appended server‑side by mqtt‑config) **= `vends.code`**. `vend_transactions` has no `vend_code` — it keys on `vend_id`, so resolve `vends.code → id` first. `payment_gateway_logs` *does* carry `vend_code` directly. **Amounts are stored in cents** on both tables (`VendTransactionResource` divides by 100 for display), so multiply the customer's dollar input by 100. App timezone is authoritative (single instance per country), so Today/Yesterday need no TZ juggling.

**`GET /refund?machineID=<vend_code>`** — public, no auth. Resolves `Vend::where('code',$machineID)->first()`; friendly error page if not found. Returns the mobile SPA.

**`POST /refund/candidates`** — `{ machineID, day: 'today'|'yesterday', amount }` → candidate list. Server logic:

```php
$vend = Vend::where('code', $machineID)->firstOrFail();
$cents = (int) round($amount * 100);
[$from, $to] = $day === 'yesterday'
    ? [today()->subDay()->startOfDay(), today()->subDay()->endOfDay()]
    : [today()->startOfDay(), now()];

// Source A — canonical sales (by vend_id)
$txns = VendTransaction::where('vend_id', $vend->id)
    ->whereBetween('transaction_datetime', [$from, $to])
    ->where('amount', $cents)
    ->where('is_refunded', false)
    ->where('settlement_status', '!=', VendTransaction::SETTLEMENT_REFUNDED)
    // ->whereDoesntHave('openRefundTicketItems')   // dup guard once table exists
    ->latest('transaction_datetime')->limit(10)->get();

// Source B — gateway charges (by vend_code) → PayNow/QR + auto-refund signal
$logs = PaymentGatewayLog::where('vend_code', $machineID)
    ->whereBetween('approved_at', [$from, $to])
    ->where('amount', $cents)
    ->whereIn('status', [PaymentGatewayLog::STATUS_APPROVE, PaymentGatewayLog::STATUS_REFUND])
    ->get();
```

Merge & dedupe (a gateway log links to a txn via `vend_transactions.payment_gateway_log_id`). Each candidate DTO returns: `time`, `amount` (cents/100), `payment_method`, `products[]` (from `items_json` / `label_json` / `product_name`), `dispensed` (bool), `already_refunded` (bool, from log status/`is_dispensed`), and source ref ids. Empty array → UI's "no match" path.

**`POST /refund`** (submit) → `{ machineID, source_ref (vend_transaction_id / payment_gateway_log_id) or manual flag, selected_item_ids[] (vend_transaction_item_id), reason_code, reason_text, refund_method, payout_destination, contact }` → creates a `refund_tickets` row + `refund_ticket_items` for each flagged item (amount derived from those items), runs the §6a validation pass, returns `RFD-000123`.

All three are **read‑only against existing tables**; writes go only to the new `refund_ticket*` / `refund_payout_batches` tables — no impact on existing reporting/settlement infra.

---

## 5. Admin/back-office flow

Maps directly to the "to be" columns of your sheet. Roles reuse mark1 auth.

```
SUBMITTED ──▶ (Ops verify) ──▶ VERIFIED ──▶ (Admin submit) ──▶ PENDING_MANAGER_APPROVAL
   │                │                                                    │
   │                ├─▶ REJECTED            (Ops: can't refund + template)│
   │                └─▶ AUTO_REFUND_DETECTED (system: already refunded + template)
   │                                                                     ▼
   │                                                                 APPROVED
   │                                          ┌──────────────────────────┤
   │                          PayNow path ────┘                          └──── PayPal path
   │                              ▼                                              ▼
   │                  PENDING_TRANSFER_INFO (if PayNow invalid → email for valid PayNow)
   │                              ▼                                              ▼
   │                          SCHEDULED (added to PayNow CSV batch)      (Admin transfers manually)
   │                              ▼                                              ▼
   └────────────────────────▶ COMPLETED  (Admin "Refund done" → auto email template 3)
```

Screens:

- **Refund queue / dashboard** — filterable table (status, operator, date, method, machine, amount). Status‑count chips at top. This is the daily Ops workspace.
- **Refund detail** — the matched transaction side‑by‑side with the claim; buttons to Verify, Reject, request PayNow info, submit for approval, schedule to pay, mark done; full audit trail and email history.
- **PayNow payout batch** — select all `SCHEDULED` PayNow refunds → **generate bank bulk‑transfer CSV** → mark batch uploaded. Replaces one‑by‑one keying.
- **Email templates** — manage the canned responses (your sheet already has the wording); system auto‑fills name/amount and sends, logging each send against the refund.

---

## 6. Data model (new tables only — additive, no edits to existing tables)

The core entity is a **`RefundTicket`** — it logs everything the customer entered, captures the *exact items* they flagged, and stores the system's auto‑validation verdict for the admin to act on.

`refund_tickets`
- `id`, `reference` (unique, `RFD‑000123`)
- `vend_code`, `vend_id`, `operator_id`
- `vend_transaction_id` (nullable — null for manual‑review tickets), `payment_gateway_log_id` (nullable)
- `reason_code`, `reason_text`
- `refund_method` enum(`paynow`,`paypal`), `payout_destination` (number/email), `payout_meta_json`
- `contact_email`, `contact_phone`
- `claimed_amount_cents` (derived = sum of selected items; **not** entered by customer)
- `is_manual` bool (the "can't find it" path: stores entered day, approx time, free‑text explanation)
- `system_recommendation` enum(`proceed`,`review`,`reject`), `system_validation_json` (the evidence — see §6a)
- `auto_refund_detected` bool
- `status`, `ops_verified_by/at`, `ops_remarks`, `submitted_for_approval_by`, `manager_approved_by/at`
- `scheduled_at`, `payout_batch_id` (nullable), `paid_at`, `completed_at`
- `last_email_template`, `last_email_sent_at`
- timestamps, soft deletes

`refund_ticket_items` — one row per **flagged** item (the heart of the new logic)
- `id`, `refund_ticket_id`
- `vend_transaction_item_id` (the source line item), `product_id`, `vend_channel_code`
- `unit_price_cents`
- `had_channel_error` bool, `vend_channel_error_code`, `channel_error_weightage` (snapshotted from the item at submit)
- `item_recommendation` enum(`proceed`,`review`,`reject`), `approved` bool (admin's per‑item decision)

`refund_payout_batches` (PayNow bulk) — `id`, `reference`, `method`, `created_by`, `csv_path`, `count`, `total_cents`, `status`, `uploaded_at`, timestamps.

`refund_ticket_logs` (audit) — `id`, `refund_ticket_id`, `actor_id`, `action`, `from_status`, `to_status`, `note`, `created_at`.

> One transaction can have several items; the customer ticks a subset. Single‑item purchases auto‑select that one item. The refund amount is **computed from the ticked items' `unit_price_amount`**, never typed by the customer (the amount they entered earlier is only used to *find* the transaction).

---

## 6a. System auto-validation (RefundTicket → admin recommendation)

On submit, the system runs a validation pass over each flagged item and writes a recommendation, so Ops sees *advice + evidence*, not a raw claim. This leans entirely on data already captured at purchase:

- Each `vend_transaction_items` row carries `vend_channel_error_id` / `vend_channel_error_code` and the linked `VendChannelError` has a **`weightage`** (severity). A flagged item **with** a logged channel error → strong evidence it didn't dispense → `proceed`.
- A flagged item with **no** error and the gateway shows it dispensed → `review` (likely dispensed; needs human eyes).
- Item already refunded (`vend_transaction_items.is_refunded = true`) or whole txn `is_refunded` / `payment_gateway_logs` status `REFUND` / `is_dispensed = false` → `auto_refund_detected` (already handled → suggest the "already refunded" email, `reject` the new payout).
- Ticket‑level recommendation = roll‑up of item verdicts (any `review` → ticket `review`; all `proceed` → `proceed`; all already‑refunded → `reject`).

The admin still decides — the system only **advises** and shows the evidence (which channel error, what weightage, dispense flags). Per‑item `approved` lets Ops refund only the items that check out. This is exactly the "filter/validate then advise admin whether to proceed" behaviour you described.

---

## 7. Reuse of existing mark1 infrastructure

- **Matching**: query `vend_transactions` (and `payment_gateway_logs`) by `vend_code` + `transaction_datetime` window; surface amount, payment method, `is_dispensed`.
- **Auto‑refund detection**: `PaymentGatewayLog` already has `STATUS_REFUND`, `is_dispensed`, `approved_at`. A not‑dispensed/refunded log → set `auto_refund_detected` and offer template 1/its variant.
- **Card refunds can be automated**: `RefundOmiseJob` already refunds Omise card charges by `order_id`. For card‑paid transactions, the refund can go back through the gateway automatically instead of PayNow — worth offering where the original payment was a card via Omise. (PayNow/PayPal remain the manual paths for cash/QR cases.)
- **Email**: existing Laravel mail config; store the sheet's templates as records.
- **Auth/roles**: reuse current Ops/Admin/Manager roles; no new auth system.

All reads against existing tables are read‑only. The only writes are to the **new** tables, so existing reporting/summary/settlement logic is untouched (consistent with your "don't affect other infra" constraint). The one external change is a **data** update to `mqtt_settings.refund_request_form_url` per piloted machine.

---

## 8. Open questions / flags before build

1. **Card vs PayNow**: should card (Omise) transactions auto‑refund via gateway (faster, no PayNow needed), with PayNow/PayPal reserved for cash/QR? This changes the customer "where to send refund" step.
2. **Matching window**: how far back should candidate transactions go (30 min? same day?), and how many to show.
3. **Approval thresholds**: does every refund need Manager approval, or only above an amount?
4. **PayNow CSV format**: which bank / exact column spec for the bulk transfer file.
5. **Localization**: per‑country instances — any non‑English locales needed at launch.
6. **mqtt-config change**: switching a pilot row's URL is a one‑value data edit; flagging per your infra rule — I will not touch it without your go‑ahead.

---

## 9. Suggested build phases

- **Phase 1 — Customer form + capture**: public route, machineID resolve, transaction auto‑match, item‑level selection, submit → `refund_tickets` + `refund_ticket_items` + reference. (Highest value; lets you pilot on one machine.)
- **Phase 2 — Admin queue + status workflow + audit**: verify/reject/approve, detail screen.
- **Phase 3 — PayNow CSV batch + email templates + auto‑refund detection**: removes the remaining manual bank/email work.
- **Phase 4 — Card auto‑refund via Omise + analytics**: optional automation and reporting.

---

## As-built (2026-06-26)

**Decisions locked with you:** Nayax = external auto-refund (record only, no payout); QR/other-POS = manual PayNow/PayPal + bulk CSV; roles operator→admin→supervisor (superadmin bypasses); emails built but log-only behind `REFUND_EMAIL_ENABLED` (default off).

**Files added**

- Migrations: `database/migrations/2026_06_26_100000..100004_*` — `refund_tickets`, `refund_ticket_items`, `refund_payout_batches`, `refund_ticket_logs`, and an idempotent permission seeder (`read/create/update/verify/approve/payout refunds`, assigned to operator/admin/supervisor).
- Models: `app/Models/RefundTicket.php`, `RefundTicketItem.php`, `RefundPayoutBatch.php`, `RefundTicketLog.php`.
- Config: `config/refund.php` (email flag, match window/tolerance, `auto_refund_terminals=['Nayax']`, CSV columns).
- Services: `app/Services/Refund/` — `RefundMatchingService` (resolve machine by `vends.code`, day+amount candidates from `vend_transactions` by `vend_id` + `payment_gateway_logs` by `vend_code`, dedupe, exclude refunded/already-ticketed), `RefundValidationService` (per-item channel-error scoring → proceed/review/reject), `RefundTicketService` (create ticket+items, classify channel, double-refund guard, status/method resolution), `RefundPayoutCsvService` (bulk PayNow CSV), `RefundEmailService` (4 templates, flag-gated).
- Controllers: `RefundFormController` (public `show`/`candidates`/`store`), `RefundController` (admin queue/detail/verify/reject/approve/submit/schedule/complete/email/item-decision/batch CSV+download).
- Routes: public `/refund`, `/refund/candidates`, `/refund` (throttled, no auth); admin `/refunds/*` (auth + `can:` permissions).
- Vue: `resources/js/Pages/Refund/Form.vue` (public guided wizard, functional), `Index.vue` (admin queue), `Show.vue` (detail + actions). Nav item "Refunds" added to `Authenticated.vue`.

**Double-refund guard (your key requirement):** matching reads `vend_transactions.is_refunded` + `vend_transaction_items.is_refunded` (covers Nayax/gateway auto-refunds) AND excludes items already inside an active (non-rejected) refund ticket (covers our manual PayNow refunds). The refund amount is summed from owed items only — already-refunded items contribute 0. NOTE: completion does **not** write `vend_transactions.is_refunded` (that column drives sales reporting) — flag if you want it stamped on completion.

**Nayax path:** if a matched transaction's `cashless_mfg='Nayax'`, the form tells the customer it's auto-refunded, collects no payout details, and the ticket is created `auto_resolved` (method `nayax_auto`). Other POS (Nets/PAX/MLS) + QR go the manual PayNow/PayPal route.

**Deploy steps (you run these):**
1. `php artisan migrate` (creates 4 tables + seeds permissions). The permission seeder is the only touch to shared infra — additive/idempotent.
2. `npm run build` (compiles the 3 new Vue pages + nav).
3. Optional: `php artisan config:clear` / refresh Ziggy so `refunds.index` route name is available to the nav.
4. To switch a machine to the new form, set `mqtt_settings.refund_request_form_url` to `https://<sys-host>/refund` (machineID is appended automatically). Pilot one row first.
5. Customer emails stay OFF until you set `REFUND_EMAIL_ENABLED=true`.

**Not wired (by design / out of scope):** real Nayax API (external), live email send (flag off), stamping source `is_refunded` on completion (avoided to protect sales reporting).
