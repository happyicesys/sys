# mark1 — working notes

## Roles and permissions: one file, always

`database/seeders/RolePermissionSyncSeeder.php` is the **single source of truth**.
Its `$permissionsData` table declares every permission the app checks and every
role that holds it.

- To change access, **amend that table**. Nothing else.
- Do **not** write a one-off seeder for a permission.
- Do **not** seed permissions from a migration.

Both of those get silently undone, because the sync seeder rebuilds the entire
permission set from its own table every time it runs.

```
php artisan db:seed --class=RolePermissionSyncSeeder
```

Amending it:

| Want | Do |
|---|---|
| New permission | add a tuple `['thing', ['read','export'], ['role', …]]` |
| Grant to a role | add the role name to that tuple's third array |
| New role | just name it — roles are created automatically |
| Revoke | remove the role from the tuple |

Removing a *tuple* deletes the permission itself, so anything still checking it
starts 403ing. Grep before deleting.

The rebuild is atomic in the DB *and* in Spatie's permission cache (the cache is
what actually decides access and is not transactional, so the seeder isolates it
on an `array` store for the duration and republishes once, in a `finally`). There
is no window where live users hold zero permissions, and a failed run cannot
leave a half-built map cached.

Changes apply on the **next page load** — no logout needed.
`HandleInertiaRequests` shares permissions from a plain `share()` closure, which
Inertia evaluates every response. The real caveat: that closure reads
`roles->first()->permissions`, so a user with two roles only ever sees the first
role's permissions in the sidebar.

Superseded, kept only as history — do not run:
`ProdOwnerPermissionsSeeder`, `ProdOwnerRoleSeeder`,
`DashboardPerformanceLitePermissionSeeder`.

## Operator isolation: the global scopes are not a safety net

`Vend` and `VendTransaction` carry operator/user global scopes
(`OperatorVendFilterScope`, `OperatorTransactionFilterScope`, …). They only fire
on Eloquent queries **rooted at those models**. Three routes bypass them, and
each has already shipped a cross-operator leak:

1. **Raw joins.** `->join('vends', …)` from a table with no scope of its own
   (`vend_channel_error_logs`, `payment_gateway_logs`) reaches machine data with
   no boundary at all. Apply `OperatorVendFilterScope::viewerOperatorId()` by
   hand — that static exists so the rule has one definition.
2. **`whereDoesntHave()` / `doesntHave()`.** Global scopes are applied INSIDE the
   existence subquery, so "no row exists" silently becomes "no row exists **that
   I may see**" — which is true of every other operator's rows. Never use it to
   test for absence across a tenancy boundary; write a raw `whereNotExists`.
   (`whereHas()` is the opposite and is *useful*: it inherits the scope, so
   `->whereHas('vend')` is a working viewer boundary. Do not delete it as a
   no-op.)
3. **Shared caches.** A cache key built from request filters alone is one entry
   shared by every operator, so whoever warms it decides what everyone else
   sees. Any cached payload cut by the viewer must key on `auth()->id()`.

A request filter — including an "All" chip — is a **preference, not an
entitlement**. Apply the viewer ceiling first and let the filter only narrow it;
see `App\Support\OperatorScope` (sibling-group rule) and
`OperatorVendFilterScope::viewerOperatorId()` (the narrower rule the vend and
transaction grids enforce). The two are deliberately different — do not swap one
for the other, it moves live numbers.

Symptom to watch for: a summary card showing money against an empty grid. That
means the card and the grid are drawn from different populations, and the card
is almost always the one that has escaped the boundary. Regression coverage:
`tests/Feature/OperatorScopeLeakTest.php`.

`users` can never carry a global scope — auth, notifications and the driver
APIs all read it unfiltered — so any user-picker has to apply the boundary
itself, and several still don't. The "Assign Job(s)" driver dropdown
(`VendController::indexCustomer`) is the one that has been fixed: own operator
only, `OperatorVendFilterScope::viewerOperatorId()`, so operator 1 keeps seeing
everyone. Regression coverage:
`tests/Feature/OpsJobDriverOperatorScopeTest.php`.

Where a model is reachable by id from write endpoints (`findOrFail`), prefer a
**global scope** over a `where` in `index()` — the listing is rarely the only
door. `OperatorDeliveryProductMappingScope` is the worked example: it also
covers edit/update/delete/bindVend and two `::all()` option lists.
Regression coverage: `tests/Feature/DeliveryProductMappingOperatorScopeTest.php`.

## Pricing source: the Site owns the tier, the machine only follows it

`customers.selling_price_type` (RP1–RP5, Customer/Site edit) is the **only**
place a reference-price tier is chosen. A machine carries just a switch,
`vends.is_using_server_price` ("Is Using Server Price?"):

- **No** → the terminal sells at the VMC board price.
- **Yes, follow Site's pricing** → mark1 selling prices in the Site's tier.

There is no per-machine RP override (`vends.server_price_type` was dropped
2026-08-21 — Brian). Derive, never store: `Vend::usesServerPrice()` /
`Vend::serverPriceType()`, and the raw-query equivalent in
`VendChannel::getServerAmountAttribute()`.

On the wire, `/api/vends/{code}/parameters` emits `selectedPricingSource`
**per vend** from that flag — the `selectedPricingSource` stored in the shared
`apk_settings` row is ignored (kept in `ApkSettingParameters::SCHEMA` only so
old rows normalize and Gson keeps a schema-complete payload). `/thumbnails` and
`/menu` carry `server_price` only while the flag is on.

Every write path goes through `VendPricingSourceService` (or nudges via it):
Machine Settings save, the per-machine column on APK Settings → Edit, and a Site
RP change — each tells the terminal to re-read settings **and** re-fetch its
menu. Regression coverage: `tests/Feature/VendServerPriceSourceTest.php`.

**CityBox chillers are outside all of this.** A CityBox-owned product — one
their catalog sync created, so `products.code` IS the `citybox_product_id`
(`Product::isCityboxOwned()`) — is priced by their portal: channel amounts come
from their API through `ChillerPlanogram` / `ChannelFrameAdapter`, never from
`selling_prices`. So Product → Edit hides the Selling Price(s) block for one and
`ProductController@update` refuses the write, the same way OpsJob → Edit Item
already suppresses the RP label for a chiller. **Unit Cost is NOT affected** —
that one is ours and still drives COGS/GP, and every CB product is missing it
today. A product a human mapped by hand to a CityBox SKU keeps its own code, is
not "owned", and keeps its selling prices — it may also sell in vending
machines. Regression coverage: `tests/Feature/ProductEditCityboxPricingTest.php`.

## Auto-refund integrity: `is_refunded` means the money has already gone back

`vend_transactions.is_refunded` is the ONE boolean every refund surface reads —
Sales Transactions "Auto-refunded?", Refund Request "Auto Refunded?", the
validation 3rd icon and the server Approve-guard (`RefundTicket::isAlreadyRefunded`).
Because of that it is written **only after** the customer's money has actually
been returned, and always together with `auto_refund_source`
(`App\Support\AutoRefundSource`):

- **Omise** — every Omise refund is recorded through
  `App\Services\Refund\OmiseRefundRecorder`, whoever made it: `RefundOmiseJob`
  on API success (sources `omise_no_dispense` / `omise_stale_approve` /
  `omise_trade_fail` / `omise_manual`), the `refund.create` webhook for refunds
  made on the Omise dashboard or by a dispute (`omise_external` — map them by
  `data.charge`, NOT `data.metadata.order_id`, which only our own refunds carry),
  and `refund:sync-omise` to reconcile from Omise's records. Never pre-mark
  before the money has moved. The dispense ACK
  (`payment_gateway_logs.is_dispensed`, set on the APK's `CONFIRM`) is sent
  **before the motor runs** — it is "order received", not "product dropped",
  and must never out-rank the TRADE (`VendTransactionService::resolvePreCreatedSettlement`).
  A single-item TRADE with `success_qty = 0` and a machine fault per
  `DispenseVerdict` (code ∉ {0, 6, 99}) is refunded;
  multi-item purchases are never auto-refunded.
- **Card terminals (NETS) — the settlement report is the ONLY source of
  truth for `is_refunded` (Brian, 2026-09-08).** mark1 gets no processor
  callback from a NETS reader; the TRADE frame carries no acquirer reference.
  The NETS MerchantConnect daily CSV carries an explicit reversal line per
  reversal ("Reversal Code = Y", negative amount, same terminal, same
  timestamp as the purchase — the "Void Txn Indicator" column is never set);
  `CardSettlementMatcher` pairs it with the purchase line it undoes and
  `CardSettlementRefundReconciler` (run by `CardSettlementSyncService` on
  Sync, or by `card-settlement:reconcile-refunds` over already-synced days)
  applies the report to every card sale of the day: reversal line → tick on,
  source `settlement_report_reversal`; captured-not-reversed → tick OFF
  (customer still charged); no line on a bound, fully covered terminal →
  **"NA in NETS"** (source `settlement_report_not_captured`, Brian
  2026-09-09) when the sale is a FAILED single item AND the terminal's
  `card_terminal_units.is_will_auto_refund` is Yes — the terminal voided the
  approval before batch upload, the only way a Visa/MasterCard failure is ever
  made good — otherwise tick OFF (dispensed / multiple / terminal No or
  Unknown; those sit on the verify list); Nets-Auresys terminals
  (`config('card_settlement.report_coverage_gap_companies')`) → `uncovered`,
  never ticked from a missing line; unbound machine → untouched. The verdict
  is persisted in `vend_transactions.card_settlement_state` (reversed at
  once; the rest once the day is final). **The "NA in NETS" badge on Sales
  Transactions states the REPORT FACT, not the refund**: it shows for any
  failed single vend whose state is `not_captured`, flagged terminal or not
  (`VendTransactionResource.na_in_nets`, from
  `CardSettlementRefundReconciler::isVoidableShape()`), while the auto-refund
  tick beside it is claimed only on a Yes terminal — a missing line is
  evidence, never a deduction that the money came back (Brian, 2026-09-09).
  A tick is only CLEARED once the day
  is final (files D and D+1 both synced — a late capture or reversal can sit
  in the next day's file); a reversal sets it as soon as its report is synced.
  Every clear also releases the ticket the tick had crossed
  (`RefundTicketService::clearAutoRefundByCharge`). **No TRADE footprint or
  machine signal may set `is_refunded` any more** — the 2026-08-23 inference
  (`card_terminal_reversal`, right 46 times in 322 against the report) was
  removed 2026-09-08 along with `config('refund.card_reversal_terminals')`
  and `refund:backfill-card-reversals`; the source value survives only to
  label legacy rows until the reconciler relabels or clears them. Consequence:
  a NETS reversal is known to mark1 only once that day's report is uploaded
  and synced, never at TRADE time, and the refund ticket page shows the
  report's verdict ("NETS report": Reversed / Captured, not reversed / Not
  captured / No report yet / Terminal not bound) so ops decide on evidence.
  See `CARD_SETTLEMENT_2026-09-01.md`.
- Every write of `is_refunded` must also call
  `RefundTicketService::markAutoRefundedByCharge` so an open ticket's frozen
  verdict crosses and approved/scheduled ones are pulled out of payout.
- **Retained-credit settlements (2026-08-29, bench-proven on 2031):** a card
  TRADE with `CSHL_ARMED_MS` < 5000 was approved from credit the VMC/reader
  banked after an earlier failed paid vend — no card presented, no terminal
  settlement will ever match it. `RetainedCreditSettlementRecorder` (called
  from `VendTransactionService::create`) marks the row
  (`is_retained_credit_settlement`), links the failed sale it consumed
  (`retained_credit_settles_txn_id`, most recent prior failed paid trade on
  the machine, 7-day lookback — the credit is NOT slot- or amount-bound). It
  writes nothing on the source sale's tick (the NETS report owns it; a
  retained credit shows there as a capture with no reversal). The
  `retained_credit_revend` source — is_refunded meaning "made whole by
  goods" — has no writer since 2026-09-08 and the reconciler leaves any such
  row alone. Revenue/gp aggregates do
  NOT yet exclude these rows — the flag is the hook for that follow-up. The
  fault itself is VMC firmware (survives error-clear, VMC restart, re-power;
  only a dispense consumes the credit): see
  `apk/mark1-apk/VMC_VENDOR_TICKET_2026-08-29.md` and
  `CARD_RETAINED_CREDIT_2026-08-22.md` before "fixing" any of this.

Every refund surface also shows WHY, from one map: the card-terminal sources
badge what the settlement report showed ("NETS reversal", "NA in NETS"), while
the gateway sources badge WHO fired it — `AutoRefundSource::trigger()` returns
`server` (mark1 decided and called Omise: trade fail, no dispense ACK, stale
approve) or `user` (a person: `refund:omise` by hand, or a refund made at the
gateway / a dispute). It returns null for the card sources on purpose: calling
a terminal's own reversal "server" would credit us with money we did not
return. Adding a gateway source without a trigger fails
`tests/Unit/AutoRefundTriggerTest.php`.

Manual PayNow/PayPal payouts never set `is_refunded` — they live on
`refund_tickets`. History + reasoning: `REFUND_INTEGRITY_AUDIT_2026-08-23.md`.
Regression coverage: `tests/Unit/PreCreatedSettlementResolverTest.php`,
`tests/Feature/CardSettlementRefundReconcilerTest.php`,
`tests/Feature/CardSettlementSyncTest.php`.

## Channel error codes: one rule, `App\Support\DispenseVerdict`

What a `vend_channel_errors.code` means for money, for the dispense verdict
and for machine health is defined ONCE, in `App\Support\DispenseVerdict`
(2026-09-08, Brian). Three questions, three answers:

| question | yes for | used by |
|---|---|---|
| `isSaleCode` (the query scope `VendTransaction::countsAsSale()` is the settlement gate, not this) | NULL, 0, 6, **99** | every `$`/revenue/GP/sold-qty aggregate, rollup, export and dashboard filter |
| `isDispensed` | NULL, 0, 6 | display only (`SaleStatus`) — 99 is "not found", never "dispensed" |
| `isMachineFault` | present code ∉ {0, 6, 99} | error counts, error rates (`SyncVendChannels` via `sqlFaultId`), Machine Health, refund "genuine non-dispense" |
| `hasVerdict` | anything but a server-reserved code | `SaleStatus::itemDispense` — a 99 item is blank, not Failed |

Code **99 = "Machine transaction not found (NA)"**: a payment rail (Omise
today, the NETS report later) received the money and the TRADE never came.
Payment truth is the rail, dispense truth is the TRADE — so 99 stays in sales
and product data, shows a blank Dispense column, and is never a fault. It is
**server-reserved**: only the marking jobs write it and the TRADE ingest
refuses it from a frame (`VendChannelError::forFrameCode()`, which every
frame-code lookup goes through). Plan and evidence: `NA_ERROR_CODE_PLAN_2026-09-08.md`.

Never spell the predicate inline again. Raw SQL takes the fragment builders
(`sqlSale`, `sqlSaleById`, `sqlFault`, `sqlFaultById`, `sqlFaultStrict` — keep
the FK-vs-code shape the call site already had), query builders take
`DispenseVerdict::SALE_CODES`, PHP takes the three predicates.
`tests/Unit/NoInlineDispensePredicateTest.php` greps `app/` and fails on any
new `code = 0 OR …` / `IN (0, 6)` / `[0, 6]` outside that class.

## Missing TRADEs: mark nightly, trust frame time for 30 days, rebuild dirty days

Three rules from `NA_ERROR_CODE_PLAN_2026-09-08.md` Part 1, all live:

- **`sales:mark-missing-trade --apply` runs at 00:01** and stamps code 99 on
  every gateway row whose day is over and whose TRADE never came — header and
  item rows, `meta_json.missing_trade.marked_at`, nothing else. Watermark:
  `settings.missing_trade_marked_until`; it advances only when the applied
  window starts at or before it, and `--to` is clamped to today. No grace
  period: the day boundary is the rule. Each chunk re-reads its rows FOR
  UPDATE and re-checks `is_found_in_transaction = 0`, so a TRADE landing
  mid-run is never stamped. It moves no figure (99 is a sale code), so it queues no rebuild;
  `store:previous-day-vend-records` runs after it (00:06) on purpose.
- **A live TRADE keeps its frame `TIME` when that is within 30 days back / 5
  min ahead** (`App\Support\TradeTimestampResolver`, `config('sales')`);
  otherwise it books at arrival with `meta_json.frame_time.rejected`. The
  frame is read in the operator's timezone (the board's clock) and booked in
  the app zone. The
  APK replays a month of queued frames unchanged after a reconnect, but ~3.7%
  of frames carry a clock that is years off — never trust TIME blindly.
- **A TRADE (or orphan row) landing on a past day records that date** in the
  Redis set `sales:rollups:dirty-days` (`App\Services\Sales\DirtyDayRegistry`,
  container singleton, O(1), after the ingest commit, never throws) and
  `reconcile:sales-rollups --dirty` rebuilds exactly those days at 02:00,
  unconditionally, as one job chain per day whose tail clears the day — a
  failed rebuild keeps its date for the next night. Locked Site Summary
  months are rebuilt in vend_records / gp_metrics but their summary rows stay
  frozen — the command lists them for finance. The amount-drift passes at
  02:15 / weekly / monthly remain the safety net. A late TRADE that clears a
  99 mark stamps `meta_json.missing_trade.cleared_at`
  (`App\Services\Sales\LateTradeTracker`).

## Payment vs dispense: `is_payment_received` is not a payment flag

The machine's TRADE carries only the dispense verdict (`SErr` per channel;
`ISOK` is hard-coded 1 on APK-built frames). There is no "payment collected"
field, and `vend_transactions.is_payment_received` is derived from the error
code in `VendTransactionService::processMapping` (dispensed codes → true, forced true for
QR gateways) — on cash and card sales it is the dispense result under a
payment name. Never read it as "was the money taken".

Both labels are deduced in one place, `App\Support\SaleStatus`, from
`App\Support\SaleFacts::fromRow($row)` (Brian's rule, 2026-09-02):

- **Payment** — only what a payment rail has CONFIRMED. Gateway sales
  (payment method with a `payment_gateway_id`: Omise / Midtrans / Fiuu) are
  Paid because the gateway API created the row, Refunded when the API/webhook
  or `refund:sync-omise` returned the money. NETS card sales are Settled once
  the uploaded acquirer report matched them (`card_settlement_synced_at`) and
  Refunded when that report carried the reversal line. Not reconciled by any
  rail — cash, a card sale before its report is synced — stays **blank**.
  Retained-credit rows read "Retained credit" (the sale that consumed banked
  credit, `is_retained_credit_settlement`) and "Re-vended" (the failed sale it
  made whole, `auto_refund_source = retained_credit_revend`) — goods, not
  money; see the card-terminal bullet above.
- **Dispense** — the machine's verdict: a `DispenseVerdict` dispensed code or no code = Dispensed, else
  Failed. A single sale carries it on its row; a **multiple purchase carries
  it on each item row and the parent row is blank**. A row with no matched
  TRADE — waiting, never reported, or marked code 99 — is **blank**, never
  Dispensed and never Failed; the Error Code column says why (the grid shows
  the row's description "Machine transaction not found (NA)", the CSV exports
  print "NA" via `DispenseVerdict::displayCode`). `SaleStatus::dispenseReason()`
  tells the two blanks (no TRADE vs. verdict on the items) apart.

The Sales Transactions grid, both CSV export jobs (+ the appended unreported
gateway rows) and the refund screen's related transactions all call it — add
a new consumer there, do not re-derive; a consumer's query must select the
`payment_methods.payment_gateway_id AS payment_method_gateway_id` alias (or
load `paymentMethod`) or every row reads as unconfirmed. **Selecting the column
is only half the contract when the CELL decides in Vue**: `VendTransactionResource`
must emit it too. "Settle Sync" was decided client-side from five fields the
resource never listed, so from 2026-09-01 to 2026-09-09 it drew a grey cross on
every row — `undefined === null` is false, so even a card sale whose report was
synced took the gateway branch. Decide in `SaleStatus` server-side where you
can; when a cell must decide in Vue, pin the payload (types included, the cell
compares with `===`) in `tests/Feature/TransactionIndexStatusColumnsTest.php`.
The grid's "Dispense
Status" filter still travels as request key `is_payment_received` (bookmarked
URLs) and lists no blank-Dispense (no-TRADE) row on either side; its
"pending" / "no_report" option ids are that request contract.
Regression coverage: `tests/Unit/SaleStatusTest.php`,
`tests/Feature/TransactionIndexDispenseFilterTest.php`,
`tests/Feature/TransactionIndexStatusColumnsTest.php`.

## Per-field attribution on Machine Settings: every editable field carries one

Setting/Edit shows "who last changed this, and when" under each control
(`Components/FieldAudit.vue`, fed by `VendController::fieldAudit`). Nothing is
stored for it — the endpoint derives it from the app-wide `user_logs` audit
(`App\Services\UserLogger`), taking the newest row per changed column. So:

- **A new editable field on that page needs its `<FieldAudit :entry="fieldAudit.<column>" />` line**,
  and nothing else. No migration, no controller change — the audit is already
  being written.
- The endpoint discards type-only diffs (`is_active [1 -> true]`, `key_id
  [100 -> "100"]`), because the form posts booleans/strings against int
  columns and an unchanged save would otherwise stamp every field.
- **A pivot is invisible to it.** `belongsToMany::sync()` fires no Eloquent
  event on the parent, so the Machine Stickers picker calls
  `UserLogger::recordChanges($vend, ['sticker_ids' => [$before, $after]])` by
  hand — a synthetic column named after the form field. Any future pivot on
  this page does the same.
- The lines re-read after each save (`loadFieldAudit()`); the page stays
  mounted across Inertia's redirect-back, so `onMounted` alone lags a save.

## Card terminals: three tables, and only one of them binds a machine

Easy to confuse, so name them precisely:

| Table | UI name | What it is |
|---|---|---|
| `card_terminals` | Data Management → **Card Terminal Company** | The supplier list: Nayax, Nets, Nets-Auresys, PAX, MLS, HID. `vends.card_terminal_id` points here. |
| `card_terminal_units` | Data Management → **Card Terminal** | One physical terminal: acquirer TID + its company. `terminal_id` is unique fleet-wide. |
| `card_terminal_bindings` | machine **Setting/Edit** | That terminal sat on that machine over a date range. |

The standalone `/card-terminal-bindings` page was removed 2026-09-05. Since then:

- **Data Management can never bind a machine.** `CardTerminalUnitController` does
  CRUD on the TID + company only; its Machine ID column is read-only display.
  Adding a machine field there would let ops write bindings with no dated
  history, which is what breaks settlement.
- **Two writers, both through `App\Services\CardSettlement\CardTerminalBindingService`.**
  `VendController::update` (`assignToVend`) only acts when the request carries
  `card_terminal_unit_id`, so the APK/API callers of `update()` never close a
  live binding. `CardSettlementController::fixBindings` (`moveToVend`) is the
  Card Settlement page's "Move N terminals & rematch" button: it repairs every
  terminal the report shows selling on another machine, dating each new binding
  from the earliest line that PROVES the terminal was there, and rematches.
  It refuses rather than guesses — no `card_terminal_units` row, a machine code
  that is not unique, a terminal with two open bindings, or a back-date another
  binding already covers is skipped with its reason in the flash message.
  `moveToVend` may pull an existing binding's `bound_from` EARLIER (only into a
  window nothing else claims), which `assignToVend` will never do; that is what
  makes a month of reports uploaded out of order converge.
- **A terminal that moves is never edited in place.** The old row is CLOSED
  (`bound_until`) and a new one opened on the same date, because
  `CardSettlementMatcher` resolves a report line by (provider, terminal_id)
  **effective on that line's transaction date** — rewriting the row would
  re-point last month's report at this month's machine. One open-ended binding
  per terminal, always; two make matching pick a machine arbitrarily.
- **`provider` is derived from the company**, via
  `config('card_settlement.company_provider')` (`CardTerminalUnit::settlementProvider()`).
  Nets **and** Nets-Auresys both resolve to `'nets'` — Auresys terminals appear
  on the same NETS MerchantConnect report, and all 312 pre-2026-09-05 rows carry
  `'nets'`. A company with no entry gets a slug of its own name, which matches
  no report on purpose: better unreconciled than mis-assigned to NETS.
- `card-settlement:import-bindings` creates the `card_terminal_units` row
  alongside the binding, or the imported terminal would be invisible in the
  Setting/Edit picker and could never be moved.
- **`card_terminal_units.batch` + `is_will_auto_refund`** (Yes / No / Unknown,
  Card Terminal Index column and filter) is the per-terminal capability the
  reconciler's "NA in NETS" tick is gated on. Seeded from the partner's
  workbook (`card-settlement:import-terminal-flags database/data/card_terminal_auto_refund_seed_2026-09-08.csv --apply`,
  source `seed`, batches Nets #3–#7 Yes, #1–#2 No, Auresys Unknown); a Yes/No
  set on the Card Terminal edit form is source `manual` and survives
  re-imports ("Auto" lifts it). The workbook is authoritative — nothing
  derives or flips the flag from observed statistics.

## NETS ↔ TRADE gap: orphan sales and adoption

Both directions of "the report and the machine disagree" are handled at Sync
(`NA_ERROR_CODE_PLAN_2026-09-08.md` Part 2):

- **Line, no TRADE** → `CardSettlementOrphanSales::createForReport()` turns each
  `UNMATCHED / "No matching sale in window"` purchase line on a bound terminal
  (full time only) into a real `vend_transactions` row via
  `App\Services\Sales\PreCreatedSaleFactory::fromSettlementLine()`: code 99,
  Dispense blank, no product, `order_id CS-<row>`, `card_settlement_row_id`
  set, SETTLED (REFUNDED when the line is reversed), operator GST rate; the
  line flips to MATCHED and claims it. Double taps, wrong-machine lines,
  unbound TIDs and hour-less lines are never turned into sales.
  `card-settlement:create-orphan-sales --apply` seeds reports synced before
  this existed. Assign / Ignore on such a line deletes an orphan still awaiting
  its TRADE (`release()`); an adopted one is a real sale and stays.
- **A late card TRADE adopts the orphan** (`VendTransactionService::findSettlementOrphan`:
  same machine, same cents, report time within −300/+60 s of the frame time,
  row-locked) and overwrites the synthetic order id; `applyTradeToPreCreatedRow`
  fills the rest exactly as for a gateway row. "Pre-created by a rail" is ONE
  rule, `VendTransaction::scopeAwaitingTrade()` / `isAwaitingTrade()`
  (gateway log OR settlement row, no TRADE yet) — the ingest, the nightly 99
  marker and `CreateVendTransaction::isAlreadyApplied` all read it.
- `card-settlement:orphans-audit` (weekly, report only) lists orphans that
  have an unclaimed same-amount card TRADE nearby — the double count a TRADE
  more than 30 days late or with a broken clock leaves; a human Assigns the
  line to the real sale, which deletes the orphan.
- Matcher hygiene that came with it: candidates are Card Terminal sales only
  (`PaymentMethod::CODE_CARD_TERMINAL`), and a line nothing fits inside the
  60/300 s window is paired by a second pass within
  `config('card_settlement.match_wide_window_seconds')` ONLY when the pairing
  is unique both ways (note "Matched in wide window").

Regression coverage: `tests/Feature/CardSettlementOrphanSalesTest.php`,
`tests/Feature/CardSettlementStateAndVoidTickTest.php`,
`tests/Feature/CardSettlementWideMatchTest.php`,
`tests/Feature/CardTerminalAutoRefundFlagTest.php`.

Regression coverage: `tests/Feature/CardTerminalUnitTest.php` (including an
end-to-end proof that a terminal bound from Setting/Edit still matches a
settlement report).

## Smart Chiller (CityBox): not a vending machine with extra fields

A `machine_type = smart_chiller` vend is CityBox's hardware running CityBox's
software. It has no APK, no VMC, no modem/simcard/terminal of ours, and its
planogram is theirs. Three rules follow, each enforced in code — extend them
rather than adding a new `if (citybox)` somewhere else:

- **`Vend::isSmartChiller()` is the one question.** Setting/Edit gates every
  vending-machine-only control on it (`isChiller` computed); `VendController::update`
  relaxes the hardware/binding `required` rules on it. A new vending-machine
  field on that page must be gated the same way, or a chiller can no longer be
  saved (prod 2026-09-02: vends 1363/1364 were unsavable for exactly this
  reason). `SettingController::edit` still loads every option list for a
  chiller on purpose: hidden pickers keep resolving and posting the stored ids,
  so an empty list would null hidden columns on save.
- **A chiller's ProductMapping is a read-only mirror.** `ChillerPlanogram`
  overwrites it every poll; `ProductMapping::isCityboxMirror()` /
  `assertEditable()` refuse every human write path, and the ops-job
  `implement_new_mapping` action is refused/skipped for chiller items (it
  would push an APK channel frame). Keep the mapping — ops jobs read it.
- **Their status is a separate layer, not our Status.** `ChillerStatus`
  (`Vend::chillerStatus()`) is their ops status / online / heartbeat, built
  from the last poll on the row and shown read-only. mark1's Status
  (active / factory / disposed / sold) stays manual; nothing auto-flips
  `is_active` from their API (`ChillerStatus::isRetired()` is the hook if that
  is ever decided).

Two Operation Dashboard (`/vends/customers`) row facts that bit the chiller
row: a row's `id` is the **customer** id (use `vend_id` for anything vend-scoped),
and JSON columns arrive as raw strings there (the query hydrates without
Eloquent casts) — decode in `VendResource`, as `citybox_status_json` now does.

The fleet lives in `citybox_devices` (`CityboxDeviceRegistry` is the only
writer — one upsert per poll, rows never deleted, `in_fleet` marks presence in
the latest complete listing). Read it; never write it from a controller.
Regression coverage: `tests/Feature/CityboxChillerGuardsTest.php`,
`tests/Feature/CityboxDeviceRegistryTest.php`, `tests/Unit/CityboxChillerStatusTest.php`.
Field-by-field reasoning: `CHILLER_SETTINGS_AUDIT_2026-09-02.md`.

---

# Laravel Boost guidelines

Curated by Laravel maintainers for this application. Follow closely.

**This app: Laravel 12.7 / PHP 8, Inertia 2 + Vue 3, Tailwind, Horizon,
Passport, Spatie Permission, Telescope, Clockwork, MQTT client.** Check the
installed version before applying an example from other Laravel docs — Boost
examples from another major version do not transfer.

## Code style

- Follow existing conventions in the application.
- Use Laravel conventions and established patterns.
- Use PHP 8.x features where appropriate.
- **Run Laravel Pint before considering PHP code complete.**
- Prefer clear, maintainable code over clever abstractions.

## Laravel

- Use built-in functionality whenever possible.
- Prefer Eloquent models and relationships over hand-written SQL.
- Form Requests for validation.
- Policies and gates for authorization — and see the permissions section above:
  permission *definitions* only ever change in `RolePermissionSyncSeeder`.
- Middleware for cross-cutting request concerns.
- Use the service container and dependency injection.
- Named routes and route model binding.
- Config files over hard-coded environment-specific values.

## Database

- Eloquent relationships over manual joins where practical.
- Migrations for all schema changes.
- Define appropriate indexes and foreign keys.
- Factories and seeders for test data.
- **Avoid N+1** — eager load relationships.

## Controllers

Keep them lightweight. Complex business logic goes to `app/Services`,
`app/Jobs`, or an action class — the app already has `Services/`, `Jobs/`,
`Observers/`, `Contracts/`, `ValueObjects/`; use them rather than inventing a
new layer. Form Requests for validation, route model binding, appropriate
Laravel responses.

## Models

Eloquent relationship methods; casts for appropriate attributes;
accessors/mutators where they improve readability; scopes for reusable query
constraints; factories for test setup.

## Validation

Prefer Laravel's validation facilities. Form Request classes for complex
request validation. Meaningful rules and messages.

## Testing

- Every new feature gets appropriate tests.
- Every bug fix gets a regression test where practical.
- Prefer Pest if Pest is installed.
- Run the smallest relevant test set first; the full suite when appropriate.

## Frontend

Inertia + Vue 3 with Tailwind is the stack — follow it. Reuse existing
components before creating new ones. Do not introduce another frontend
framework without a clear reason.

## Environment

- Never hard-code secrets. Never expose `.env` values to users.
- Read config through `config()`, not `env()`, outside config files.
- Do not modify `.env` unless explicitly required.

## Documentation

Use Laravel Boost's documentation tools when Laravel-specific information is
needed, and check installed package versions before applying examples.

---

# Performance

Optimise for performance as a default, not an afterthought — but with evidence.
Telescope and Clockwork are installed; use them rather than guessing.

The DB is large: `vend_transactions` ~4.8M rows, `gp_metrics` ~2.4M,
`vend_temps` ~12M, `stock_count_items` ~2M. Full-table scans are not free here.

- Read `data_dictionary()` on the `sys-happyice` MCP before writing reporting
  queries — it documents which tables are pre-aggregated (`gp_metrics`,
  `fact_*`, `customer_period_summaries`) versus raw truth, and the canonical
  metric definitions. Improvised SQL will not tally with the dashboards.
- Prefer the pre-aggregates for dashboard/trend work; reserve
  `vend_transactions` for genuinely transaction-level questions.
- Avoid `vend_records` for financial accuracy — legacy, drifts, never
  reconciled.
- Chunk or queue heavy work (Horizon is installed); do not block a request.
- **Never correlate an EXISTS on two columns with an OR.** MySQL cannot use an
  index for `WHERE a = outer.x OR b = outer.y` inside a correlated subquery — it
  re-scans the whole inner table per outer row. Write one EXISTS per key and OR
  them (`EXISTS(A OR B)` ≡ `EXISTS(A) OR EXISTS(B)`, and `NOT EXISTS(A OR B)` ≡
  `NOT EXISTS(A) AND NOT EXISTS(B)`). The Auto-Refunded filter did this over
  refund_tickets' two link keys and simply never returned on a five-day card
  filter (`VendTransaction::scopeApplyRefundedFilter`, fixed 2026-09-09).

# Production database

`sys-happyice` MCP is read-only against live production. Use it to confirm
schema and real values before writing migrations or queries — do not infer
schema from model files alone.

