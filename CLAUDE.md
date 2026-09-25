# mark1 — working notes

## Push is a deploy: run the pre-push guard

`git push` on `main` deploys. The repo carries its own guard in `.githooks/`,
but **`core.hooksPath` is config, and config is never cloned** — a fresh clone
runs no hook at all and fails open. After any clone, once:

```
git config core.hooksPath .githooks
```

Worktrees inherit it from the shared `.git/config`, but the hook file itself is
resolved from *that worktree's* checkout — a worktree parked on a commit from
before the hook existed silently has none. Keep worktrees current.

`.githooks/pre-push` blocks five things, each one an incident that already
happened here. Override with `git push --no-verify` when you genuinely mean it:

1. **Stale checkout** — >20 tracked files in HEAD are missing from disk. That is
   the *mixed* `git reset` signature: HEAD and the index move, the files do not,
   so the tree sits at an old commit while `git status` reads as mass deletion
   (2026-09-22: 379 files).
2. **`public/build` not fully committed** — any untracked or modified asset.
   `git commit <path>` does not pick up untracked files; that took prod down on
   2026-09-18. Always `git add -A public/build`.
3. **Broken manifest** — `manifest.json` naming files that do not exist, i.e. a
   half-finished `npm run build`. The site would 404 its own bundle.
4. **Frontend source without its bundle** — a `resources/js|css` change pushed
   with no `public/build` change. Production serves the committed bundle, so
   users would get stale JS.
5. **Advisory only**: uncommitted work outside `public/build`, since another
   session may share this checkout.

Config this repo also sets: `pull.ff only` (no surprise merge commits — decide
rebase or merge deliberately), `fetch.prune` , `merge.conflictStyle zdiff3`,
`rerere.enabled`.

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

What an HIPL viewer's Operator filter **opens with** is a third list,
`OperatorScope::DEFAULT_FILTER_CODES` (HIPL, HIMD, LEA, HIESG, UL-ST, XO, MSW —
XO and MSW since 2026-09-11, Brian: their machines take payment on our Omise
account and NETS terminals). It also decides whose sales the Dashboard's
monthly sales popup totals. PHP reads it through
`OperatorScope::defaultFilterIds()`; Vue through `hiplDefaultOperators()`
(`resources/js/constants/defaultOperators.js`, fed by the shared
`defaultOperatorCodes` Inertia prop). It used to be copied into ~50 controllers,
exports and pages; `tests/Unit/NoInlineOperatorGroupTest.php` fails on a new
copy. Change who HIPL sees by default there and nowhere else.

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

**A Smart Freezer has no choice: always Yes** (Brian, 2026-09-14). The freezer
APK has no board price and drops every menu row without `server_price`, so No
means an empty kiosk (50001 was created that way). `Vend::requiresServerPrice()`
plus a `saving` hook on `Vend` force the flag on every write — creation,
Machine Settings, API — and the APK Settings toggle refuses No with an error.
The selector is locked in Setting/Edit and APK Settings → Edit. The menu nudge
(`VendJobService::syncChannelSlotListToVend`) reaches freezers too — only
chillers are skipped; the freezer APK re-fetches `/menu` only on boot or on that
frame, so skipping it left the kiosk stale until a reboot.

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
  2026-09-09) when the sale is a FAILED single item — the approval was voided
  before batch upload, the only way a Visa/MasterCard failure is ever made
  good — otherwise tick OFF (dispensed / multiple / no TRADE; those sit on the
  verify list). **The terminal's `card_terminal_units.is_will_auto_refund` does
  NOT gate this** (Brian, 2026-09-09, revising the same day's first rule): the
  flag describes the terminal model, while "no line in either file that could
  carry this sale" is direct evidence no money was taken, so a No / Unknown
  terminal ticks too and the flag stays informational (the "Auto refund"
  badge). Nets-Auresys terminals
  (`config('card_settlement.report_coverage_gap_companies')`) → `uncovered`,
  never ticked from a missing line; unbound machine → untouched. The verdict
  is persisted in `vend_transactions.card_settlement_state` (reversed at
  once; the rest once the day is final). The "NA in NETS" badge on Sales
  Transactions and both Refund Request screens states the REPORT FACT behind
  the tick: it shows for any failed single vend whose state is `not_captured`
  (`VendTransactionResource.na_in_nets` and `RefundController`, both from
  `CardSettlementRefundReconciler::isVoidableShape()`), and the auto-refund
  tick beside it always follows it (Brian, 2026-09-09). Both Refund Request
  screens also carry its opposite, **"Matched in NETS"**
  (`RefundController.matched_in_nets`, state `captured`): the report HAS a line
  and no reversal, so the customer was charged and a valid claim still has to
  be paid — without it "Auto refunded? No" looked identical on a captured sale
  and on one nothing had ruled on yet. Not shown on Sales Transactions, where
  the Settle Sync column already says it and nearly every card row would carry
  the badge. **Every other state is badged too**, from one shared map
  (`resources/js/constants/netsReportBadge.js`, fed by
  `RefundController.nets_report_state`): `uncovered` → "NETS covers this
  terminal partly", `unbound` → "No NETS terminal", a card sale whose day is
  not final → "NETS report pending", `not_captured` on a shape that cannot be
  voided (dispensed / multiple) → "No line in NETS". `reversed` returns none —
  the source badge beside it already reads "NETS reversal". A silent row was
  the actual complaint (Brian, 2026-09-09, on an Auresys claim: "single
  purchase, error 7, and no badge?").
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
  failed rebuild keeps its date for the next night. Syncing a NETS settlement
  report rebuilds ITS OWN days at once (`CardSettlementSyncService` →
  `App\Services\Sales\RollupRebuilder`, the one recipe both reconcile modes
  use), so a report that creates orphan sales does not leave the dashboards
  a day behind; the dirty entry stays for the nightly cascade. Locked Site Summary
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
- **Bindings are time ranges to the second** (2026-09-25): `from_at` /
  `until_at` DATETIME, `[from_at, until_at)`, NULL = open. A technician swaps a
  terminal at 14:30, not at midnight — the matcher resolves each NETS line by
  ITS time (`CardTerminalBinding::coversAt`), the sale snapshot by the sale's
  moment (`terminalIdAt`), so morning lines go to the old machine. `bound_from`
  / `bound_until` are DERIVED dates kept by the model's `saving` hook for the
  day-level screens and SQL; write the `_at` columns (a raw `DB::table` insert
  skips the hook — the app has none; tests that do must set both). Where legacy
  day-precision rows overlap on a swap day, the latest `from_at` wins.
  `source`: `manual` (a person on Setting/Edit), `report` (NETS evidence),
  `import` (the seed CSV).
- **Two writers, both through `App\Services\CardSettlement\CardTerminalBindingService`.**
  `VendController::update` (`assignToVend`) only acts when the request carries
  `card_terminal_unit_id`; Bound From is a date-time input, blank = the save
  moment, a bare date = that day 00:00 (today = now). **It is never pre-filled**:
  pre-filling it with the CURRENT terminal's start back-dated 13 of 18 human
  bindings by 44–470 days (fixed 2026-09-25; the server also treats that exact
  stale date from an old tab as "now"; repair: `card-settlement:repair-backdated-bindings`).
  `CardSettlementController::fixBindings` / `bindUnbound` (`moveToVend`) write
  NETS evidence as a SEGMENT from the first proving line: it runs to the next
  recorded thing (a later binding of the terminal or the machine) and **never
  past the moment a person recorded a change** (`created_at` of a `manual`
  row) — a person's back-dated claim yields to evidence only before that
  moment. Evidence that contradicts a change a person recorded BEFORE it is
  refused with "check which is right"; another terminal's FINISHED stay on the
  machine is refused too (two owners); an OPEN binding there is displaced.
- **Move suggestions are change points** (2026-09-25). The matcher flags a line
  "found on machine X" only when the terminal matched no real sale on its bound
  machine AFTER it (day before → day after); a graze between home sales is an
  NA orphan. The report page suggests a move only with ≥ 2 such lines on ONE
  machine, clearly ahead of any other, gathered over the day before and the
  report's days from EVERY report; the move starts at the first such line, to
  the second. Unbound TIDs follow the same ≥ 2, day-before rule.
- **A terminal that moves is never edited in place.** The old row is CLOSED
  (`until_at`) and a new one opened at the same instant — rewriting the row
  would re-point last month's report at this month's machine. One open-ended
  binding per terminal, always; two make matching pick a machine arbitrarily.
- **`cashless_mfg` is NOT the supplier.** The board reports `"Nets"` for every
  NETS-family reader, Auresys included, so a Nets-Auresys terminal (28 units,
  21 machines) read as plain "Nets" on Sales Transactions and Refund Request
  while the reconciler treated it as partially covered — the label and the
  verdict disagreed with nothing on screen to explain it (Brian, 2026-09-09).
  Both grids now put the bound unit's COMPANY in that bracket
  (`card_terminal_company`, from `attachTerminalFlags()` in `RefundController`
  and `VendController::transactionIndex`), falling back to `cashless_mfg` when
  no binding covers the sale's date; the hover names both when they differ.
  The Pay Method **filter** still travels on `cashless_mfg` (request id
  `cc:<terminal>`) — that is a request contract, do not repoint it.
- **`provider` is derived from the company**, via
  `config('card_settlement.company_provider')` (`CardTerminalUnit::settlementProvider()`).
  Nets **and** Nets-Auresys both resolve to `'nets'` — Auresys terminals appear
  on the same NETS MerchantConnect report, and all 312 pre-2026-09-05 rows carry
  `'nets'`. A company with no entry gets a slug of its own name, which matches
  no report on purpose: better unreconciled than mis-assigned to NETS.
- **`vend_transactions.terminal_id` is a frozen snapshot, not a lookup**
  (2026-09-12, Brian). Every card-terminal sale records the TID bound to its
  machine AT THE SALE'S MOMENT at write time (`CardTerminalBinding::terminalIdAt`,
  latest `from_at` wins where rows overlap — the matcher's tie-break); a NETS-report
  orphan takes the TID straight off the report line. A later rebind never
  rewrites it, so it is the sibling of `cashless_mfg`. Null on cash and QR
  sales, on a card sale with no binding that day, and on every row written
  before the column existed (no backfill — the binding history can derive
  one, but that is the live view, not what mark1 knew at the time). The
  Sales / Refund grids still derive `card_terminal_unit_id` from the binding
  history per page: that view FOLLOWS a binding repair ("Move N terminals &
  rematch"), the column does not — they disagree exactly when a binding was
  wrong at the moment of sale. Regression coverage:
  `tests/Feature/VendTransactionTerminalSnapshotTest.php`, `tests/Feature/CardTerminalBindingTimeTest.php`.
- **A Nets-Auresys unit has TWO ids.** `card_terminal_units.terminal_id` is the
  NETS TID — the only one settlement matching, the bindings and the
  MerchantConnect report ever resolve on. `auresys_terminal_id` (2026-09-12) is
  Auresys' own EZ terminal ID, the key THEIR report is written against; ops had
  been typing it into remarks as "EZTID: 25670011" and
  `CardTerminalAuresysTerminalIdSeeder` backfills it from there (idempotent,
  never overwrites a set value, keeps the remark). Nullable, not unique, no
  behaviour hangs off it yet — it exists so an Auresys report can be matched
  later without parsing free text. Never swap the two.
- `card-settlement:import-bindings` creates the `card_terminal_units` row
  alongside the binding, or the imported terminal would be invisible in the
  Setting/Edit picker and could never be moved.
- **`card_terminal_units.batch` + `is_will_auto_refund`** (Yes / No / Unknown,
  Card Terminal Index column and filter) is the per-terminal capability the
  partner documented. It gated the reconciler's "NA in NETS" tick for one day
  and no longer does (Brian, 2026-09-09) — it is now informational only: the
  "Auto refund" badge on Sales Transactions, the Operation Dashboard and both
  Refund Request screens (it says what the TERMINAL does, never that this sale
  will be refunded — renamed from "Will refund" 2026-09-09 because a page of
  ordinary card sales read as a page of pending refunds). Seeded from the partner's
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
  unbound TIDs and hour-less lines are never turned into sales. Neither are
  lines at a test-rig amount (`VendTransaction::ODD_TRANSACTION_AMOUNTS`, off
  the retained rigs): the nightly `RemoveOddTransactions` sweep deletes every
  sale at those amounts by `created_at`, so the NA sale vanished that night and
  left the line pointing at nothing (77 lines, 09-09 → 09-24). They are
  Ignored with `NOTE_TEST_AMOUNT` instead.
  `card-settlement:create-orphan-sales --apply` seeds reports synced before
  this existed. Assign / Ignore on such a line deletes an orphan still awaiting
  its TRADE (`release()`); an adopted one is a real sale and stays.
- **A late card TRADE adopts the orphan** (`VendTransactionService::findSettlementOrphan`:
  same machine, same cents, report time within −300/+60 s of EITHER the frame
  time or the moment we received the frame — the same two anchors the matcher
  uses, closest to the expected lag wins — row-locked) and overwrites the
  synthetic order id; `applyTradeToPreCreatedRow`
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
- **Two time anchors per sale, never one** (2026-09-15). A NETS line is tested
  against the sale's `transaction_datetime` (the frame's own TIME — for
  TXN_SRC 0 keypad sales the VMC **board** builds the whole TRADE JSON, so
  ORDRID and TIME are the same board-RTC instant, and ~60 boards drift by
  whole minutes: 2760 −314 s, 2116 −611 s) AND against `received_at` (our
  clock at ingest, which agrees with the NETS terminal — but is wrong when an
  offline machine flushes its outbox in one burst, 2502 on 2026-09-03).
  `CardSettlementMatcher::timeDeltaDetail()` returns the fit closer to the
  expected +15 s lag; a receive-anchor match carries the note "Matched on
  receive time". Rows older than the column fall back to `created_at` only
  when the TRADE itself created them (`receivedAnchor()`); the receive anchor
  is ignored beyond `match_received_anchor_max_lag_seconds` (24 h) so a
  replayed TRADE cannot claim a line at its arrival time. **Do not "fix"
  matching by switching to the order-id timestamp** — it is the same clock as
  TIME. Every candidate query must select `CardSettlementMatcher::candidateColumns()`
  — which also pulls the raw frame TIME out of `vend_transaction_json` for
  rows with no `received_at`: before 09-09 `transaction_datetime` WAS the
  server time, so for those rows the board's stamp lives only in the JSON
  (`legacyFrameAnchor()`; the August reports still in review hit this).
  `card-settlement:repair-orphans [--from --to --vend] [--apply]`
  (`CardSettlementOrphanRepair`) replaces orphans the single-anchor era
  created with the real sale that fits on either anchor (greedy, unique both
  ways, normal window only), carries the Sync stamp over, dirties the days
  and re-runs the refund reconciler; the line keeps the note
  "Repaired: orphan replaced by the machine's sale".

- **The leftover pass — when no window can reach the TRADE** (Brian,
  2026-09-24; `App\Services\CardSettlement\LateTradePairer`). Same machine,
  same cents, then two tiers. **A. Learned clock:** the machine's board-clock
  offset (raw frame `TIME` − NETS time) is learned from its own matched
  sales within `match_clock_offset_reference_days`; a sale within
  `match_clock_offset_tolerance_seconds` of a learned offset is the line's
  sale (2300 on 2026-09-23: board reset to 2001, offset −811,226,089 s, ±3 s;
  the raw TIME is read from the JSON because the 30-day guard booked those
  frames at arrival). **B. Sequence:** the TRADE reached us within
  `match_late_max_lag_seconds` (3 h) of the tap, and a sane board clock still
  puts the sale inside the NORMAL window of it (only a broken clock — 2001,
  "14:06:112" — leaves arrival order as the evidence) — late delivery is
  forgiven, a late sale is not (prod dry run: 2760's second charge would
  otherwise have taken a sale its clock put 23 min later); per machine+amount, equal counts pair in order, otherwise
  only unique-both-ways pairings, ties stay queries. Failed TRADEs pair too.
  Notes: "Matched on the machine's learned clock" / "Matched late (same
  machine, amount, order)"; sequence and wide matches never serve as clock
  references. **C. Same day** (Brian, 2026-09-24: the NETS report is proof
  the money came in): once NETS is FINAL for both days involved
  (`isDayFinal`: files D and D+1 synced), an NA line and an unclaimed card
  TRADE on the same machine, same cents, same date pair up — the sale's time
  is its sane board clock else its arrival, `match_same_day_margin_seconds`
  (3 h) either side of midnight. Several same-amount NAs and TRADEs on one
  machine are an order-preserving minimum-cost assignment (DP): most pairs
  first, then least total gap, earlier tap ↔ earlier TRADE, a TRADE stamped
  before its tap costs double. Note "Matched
  same day (NETS confirms the charge)", kept by the repair so it is never a
  clock reference. **Sync** runs the orphan repair over the days it covers
  right after creating orphans, so the report that makes day D final pairs
  D's orphans at once. It runs in ONE logic on upload and **Rematch** (`CardSettlementMatcher::assignLate`,
  before the wrong-machine check), on Rematch over the report's own NA orphans
  (`MatchCardSettlementReport::repairOrphans`), in `repair-orphans`, and
  nightly at 01:40 (`repair_orphans_nightly_days`, 45; 0 = off) ahead of the
  02:00 dirty-day rebuild. Live TRADE adoption at ingest still uses the
  normal window only — a later arrival is fixed that night.
- **"Found on machine X" needs evidence that outweighs the binding.** A
  same-amount sale on another machine inside six minutes is flagged as a
  binding query only when that machine fits at least as many of the
  terminal's lines that day as the bound machine matched (real TRADEs only);
  otherwise the line is `No matching sale in window` and becomes an NA orphan
  at Sync. Measured 2026-09-24: 44 of 51 such flags were coincidences
  (23104091: 67 matched at home vs 2 grazes).

Regression coverage: `tests/Feature/CardSettlementLateTradeTest.php`,
`tests/Feature/CardSettlementOrphanSalesTest.php`,
`tests/Feature/CardSettlementStateAndVoidTickTest.php`,
`tests/Feature/CardSettlementWideMatchTest.php`,
`tests/Feature/CardSettlementDualAnchorMatchTest.php`,
`tests/Feature/CardSettlementOrphanRepairTest.php`,
`tests/Feature/CardTerminalAutoRefundFlagTest.php`.

Regression coverage: `tests/Feature/CardTerminalUnitTest.php` (including an
end-to-end proof that a terminal bound from Setting/Edit still matches a
settlement report).

## Smart Freezer videos from Zijia: stored raw, contract not agreed

`POST /api/smart-freezer/zijia/videos` (`SmartFreezer\ZijiaVideoWebhookController`,
2026-09-14) receives the door-session camera video URLs Zijia's servers push.
Static shared token (`config('smart_freezer.zijia.video_webhook_token')`, env
`ZIJIA_VIDEO_WEBHOOK_TOKEN`; Bearer / `X-Api-Key` / `?token=`), 503 until set.
The payload shape is unknown, so every push is kept whole in
`smart_freezer_videos` (`raw_body` exact bytes, `payload` parsed) with
`video_urls`, `order_no` (`SF-<vendCode>-<epoch>-<seq>`, the APK's orderOpenDoor
ref) → `vend_id`, and `device_id` lifted out. Not `attachments` (255-char
`full_url`, needs a known parent). Once Zijia confirms fields, extend the
extraction there rather than dropping the raw columns. Not linked to
`vend_transactions` yet — mark1 does not store the APK txnRef. Spec to send
them + open questions: `apk/smart-freezer/ZIJIA_VIDEO_WEBHOOK_2026-09-14.md`.
Regression coverage: `tests/Feature/ZijiaVideoWebhookTest.php`.

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
  reason). **A Smart Freezer gets the same relaxation** (2026-09-14): our APK
  on a Zijia board, but no VMC, setting chart, prefix, menu frame, LCD / LED
  panel or fan signal, and its APK ignores RESET / REBOOTANDROID / UPDATELOG /
  screenshot frames. Setting/Edit gates those on `isVendingMachine` (neither
  chiller nor freezer) — P1/P2, APK Parameter, APK Logs, Restart VMC/APK and
  View Screen included; a freezer keeps Push Products Info and Sync APK
  Settings, the two frames its `PushMessageParser` acts on. So a new
  VMC-board-only field is gated on `isVendingMachine`, and a chiller-only
  exclusion on `isChiller`. `SettingController::edit` still loads every option list for a
  chiller on purpose: hidden pickers keep resolving and posting the stored ids,
  so an empty list would null hidden columns on save.
- **A chiller's ProductMapping is OURS** (2026-09-21, reversing the 2026-08-19
  mirror) **and its stock is keyed by SKU** (2026-09-22 — see "SKU-stocked
  machines" below). The vend's mapping decides which products exist and where
  they sit (`ChillerChannelMap::forVend`, keyed by product id), the mapping
  item's override or the SKU's `products.chiller_slot_qty` decides capacity,
  and CityBox supplies only qty and price — per PRODUCT, the same unit as a
  row, so nothing is split or summed any more. Codes are typed by ops, 101–599,
  first digit = layer, optionally with one letter (101A / 101B) when a position
  holds several SKUs; the dropdown offers only SKUs linked to CityBox's
  catalogue. `ChillerPlanogram` no longer writes anything: it reads THEIR
  Pre-Stock Setup for the recognition check —
  `StockPollService::unrecognisableSlots()` lists SKUs we map that their
  machine does not carry, which their AI cannot recognise, and the restock
  push WITHHOLDS those SKUs, sends the rest, and ends `failed` with the channel
  labels (an unreadable config withholds nothing — unknown is not missing).
  Two more rules of that push: a SKU that LEFT the planogram on an
  `implement_new_mapping` swap is pushed as 0 (else CityBox keeps counting
  stock the driver removed); and a SKU the live call omits (sold out) keeps its
  price from their config / the catalogue's last price — amount 0 zeroes stock
  value and refill amounts. Setting/Edit also flags the UPCOMING mapping's
  unloaded SKUs, so OPS Pro is fixed before the changeover job. An unbound or
  emptied mapping retires every row; rebinding on Setting/Edit (current AND
  upcoming pickers are live for chillers) and a mapping Save rebuild them at
  once via `StockPollService::rebuildChannels`. Their par is display-only: it
  is not writable through the OpenAPI and caps nothing (a push of 10 against
  par 5 was accepted, 2026-09-19).
  Regression coverage: `tests/Feature/CityboxChannelsTest.php`,
  `tests/Feature/CityboxRestockVisitTest.php`.
- **Its machine ID is OPS Pro's, stored as `vends.code_prefix` + `vends.code`**
  (2026-09-19, Brian: "do not recreate another ID"). Their machine name
  "C6003" → prefix `C`, code `6003`; label via `Vend::codeLabel()` / VendResource
  `code_label` / JS `vendCodeLabel()`. Every other machine has a NULL prefix and
  its label is its code. `App\Support\VendCode` owns the parse. Provisioning
  refuses a name with no ID or a (prefix, code) another vend holds; the minute
  poll (`DeviceSyncService`) follows a rename in OPS Pro and warns-and-keeps on a
  duplicate or unparseable name. The bare number MAY equal an old vending
  machine's (5001–5004, 6001, 6002 exist, inactive), so:
  **a lookup by a number a terminal/board/feed reports uses `Vend::bareCode()`**,
  never `where('code', …)->first()`; search boxes use `VendCode::whereSearch` /
  `whereLabels` so "C6003" matches; and machine create keeps refusing any number
  a prefixed vend holds, so a new vending machine can never collide.
  Regression coverage: `tests/Feature/VendCodePrefixTest.php`, `tests/Unit/VendCodeTest.php`.
- **A chiller is imported with NO site** (2026-09-21, "Site — Primary: Sys"). The
  Create page's CityBox branch imports the machine; the Site is created in mark1
  like any other and bound with the ordinary Site picker on Setting/Edit
  (`CustomerController::update`, `is_existing`). `ProvisionChillerVendRequest`
  PROHIBITS `new_customer`: creating a site from the device name is how the fleet
  got sites called "Singapore5". Picking an existing site at import stays as a
  shortcut. An unbound chiller cannot join an ops job (`ops_job_items.customer_id`
  is NOT NULL) — intended. Manual create (`SettingController::store`) never makes
  a chiller. Regression coverage: `tests/Feature/CityboxProvisioningTest.php`.
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

## SKU-stocked machines: the row is the product, the code is a label

Smart Freezer and Smart Chiller stock is keyed by SKU (Brian, 2026-09-22;
plan: `SKU_STOCK_PLAN_2026-09-22.md`). CityBox's API has no channel at all
and a freezer basket is a placement hint, so on those two machine kinds a
`vend_channels` row is identified by `(vend_id, product_id)`; `code` + the
one-letter `suffix` ("101A") is only where the driver puts the SKU, relabelled
from the mapping on every sync. A vending machine's rows stay identified by the
board's slot code. `Vend::isSkuStocked()` is the ONE question — gate on it,
never on "not a vending machine" at the call site.

- **Storage.** `vend_channels.code` stays `int` (layer/basket grouping, range
  checks, the 50–59 claw exclusion all compare numbers); the letter lives in
  `suffix CHAR(1) NULL`, and the unique index is `(vend_id, code, suffix_key)`
  where `suffix_key` is a stored generated `COALESCE(suffix, '')` — a NULL in
  a composite unique would have let the 2026-08-26 vending duplicates back in.
  `product_mapping_items.channel_code` (varchar) is what ops type;
  `App\Support\ChannelCode` parses / labels / orders it (101 < 101A < 102, SQL
  `CAST(channel_code AS UNSIGNED), channel_code`). `ops_job_item_channels.vend_channel_code`
  is not widened: the label is read through `vendChannel` (`vend_channel_label`).
- **One planogram reader.** `App\Services\Stock\SkuPlanogram::forMapping()`
  gives one `SkuSlot` per product (primary label = its lowest code, `labels` =
  all of them, capacity = the SUM of its items' effective capacity —
  `product_mapping_items.capacity_override` ("Reality" on ProductMapping →
  Edit) else the product's `freezer_slot_qty` / `chiller_slot_qty`). A SKU
  listed on two codes "never happens" but is not forbidden; it is one row.
- **Writers.** `FreezerChannelSync` (qty carried by product — our ledger) and
  `ChannelFrameAdapter` (qty from `device_product` per SKU) build a frame with
  `product_id` + `suffix` per entry; `SyncVendChannels` upserts by product,
  relabels the position (`releaseClaimedLabels()` parks a row whose label
  another SKU is taking on a negative, id-unique code first, so a swap never
  trips the index), and retires rows whose PRODUCT left the frame — qty kept,
  so a SKU that returns gets its ledger back. `ProductMappingService::syncChannelsByVend`
  returns early for these machines: nulling `product_id` by code would destroy
  the identity.
- **Changeover diffs product sets** (`OpsJobController::applyNewMappingBySku`,
  `enforceMappingSwapReturns`): a SKU in both mappings stages nothing and is
  never auto-returned, whatever code it moved to; a leaving SKU is cleared off
  and returned; an arriving SKU gets one `is_upcoming_product` row hung on its
  (reused or new, inactive, position-less) channel row. The post-swap sync
  (`rebuildChannels` / `FreezerChannelSync`) assigns the real codes.
- **Suffix codes are chiller-only, by decision** (Brian, 2026-09-24: a freezer
  basket does not need 11A/11B). `assertValidChannelCode` keeps freezer codes
  whole numbers; the APK constraint (`MqttPaymentClient.slotIds` drops a
  non-digit slot from the REQQR list) is a second reason, not the only one.
  Sales still resolve the channel from the frame's `SId`; resolving chiller
  sales by product waits until CityBox delivers their order callback — until
  then there are no chiller sales in mark1 to resolve (Brian, 2026-09-24).
- Only an EXACT repeat of a code is refused (`assertUniqueChannelCode`, and the
  red cell + blocked Save in the editor). "102" and "102A" may coexist (Brian,
  2026-09-23): they are distinct rows under the unique index, distinct labels
  everywhere, and they sort 102 before 102A.

Regression coverage: `tests/Feature/SkuStockIdentityTest.php`,
`tests/Unit/ChannelCodeTest.php`, `tests/Feature/FreezerChannelSyncTest.php`.

## Ops job stops: four kinds of row, one registry

An ops job (a driver-day) carries four row types: `ops_job_items` (machine
top-up), `ops_job_tasks` (non-machine stop), `service_notices` (repair) and
`stock_checks` (count). The last two are 2026-09-19 (plans:
`SERVICE_NOTICE_PLAN_2026-09-19.md`, `STOCK_CHECK_PLAN_2026-09-19.md`).

- **`App\Support\OpsJobStopRegistry` is the only writer of a stop's place in the
  visiting order.** Renumber / sequence / the Route page send `{type, id}`; add
  the next row type to `TYPES` there (and to `RemoveEmptyOpsJob`, which must
  treat a job holding ANY stop as non-empty — every stop cascade-deletes with
  its job). Never grow another `if type === …` at a call site.
- **Never put a repair or a count on `ops_job_items`.** That table feeds stock-in,
  freeze, tally, cms sync, the pick list and ops performance.
- **"Stock Count" in the UI is `stock_checks` in code.** `stock_counts` /
  `stock_count_items` / "Daily Stock Count" are the nightly machine-reported
  valuation snapshot — unrelated; the two never read or write each other.
- **A stop's machine need not share the job's operator** — operator-1 jobs carry
  sibling operators' machines (~16% of items, prod 2026-09). The rule is
  `ManagesOpsJobStops::vendVisibleToViewer()`; the tenancy boundary of a stop is
  its JOB's operator (`ScopesOpsJobToViewer`), on every route.
- **Stock Count sync is per machine kind** (`App\Services\StockCheck\Sync\*`,
  bound in `AppServiceProvider`). It applies the variance, never the counted
  figure. mark1 cannot set a VMC's qty, and a CityBox chiller is refused — read
  the class docblocks before changing either. A new machine kind that owns its
  stock differently is one more `StockCheckSyncTarget`.
- A channel the system shows as empty is never drawn (Brian: a spot check cannot
  validate a sold-out channel) — `StockCheckSampler::eligible`.
- `Eloquent\Collection::only()` / `keys()` speak MODEL ids. The sampler draws by
  position and calls `toBase()` first; a random draw over real query results
  came back empty without it (`StockCheckSamplerTest`).

Regression coverage: `tests/Feature/ServiceNoticeTest.php`,
`tests/Feature/StockCheckTest.php`, `tests/Feature/OpsJobStopsTest.php`,
`tests/Unit/StockCheckSamplerTest.php`.

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


## Omise webhooks are verified against Omise, not trusted

`POST /api/v1/payment-gateway-status/omise` is unauthenticated and Omise does
not sign events, so `App\Services\Payment\WebhookVerifier` re-reads the charge
with the merchant's secret key (`GET /charges/{id}`) and compares status,
amount (minor units per operator currency) and `metadata.order_id` before an
APPROVE dispenses or a REFUND marks a sale refunded (audit M3-01, 2026-09-15).
Mode is `config('payment.webhook_verification')`:

- `log` (config default) — the webhook is processed exactly as before; the
  verdict is written by `VerifyPaymentWebhook` on the `low` queue. Grep
  `payment.webhook.verify` in `storage/logs/laravel.log`; a `mismatch` line is
  a webhook that `enforce` would have refused. Live day one: 9/9 `verified`,
  139–218 ms per check.
- `enforce` (**prod since 2026-09-15 22:09**, `.env` `PAYMENT_WEBHOOK_VERIFICATION=enforce`) — inline; MISMATCH is refused with HTTP 200 (so Omise does not
  retry) and no state change; UNVERIFIABLE (Omise API down/slow, 8 s timeout)
  is allowed through with a warning, because refusing would stop every QR sale
  during an Omise outage and an attacker cannot cause that condition.
- `off` — the pre-2026-09-15 behaviour. Never ship it.

Enforce was switched on after 114/114 log-mode verdicts came back `verified`
(126–294 ms) and the first inline verdicts dispensed normally. To fall back,
set the env to `log` (never `off`). Fiuu verifies its own
signature in `PaymentController`; Midtrans is unused. Regression coverage:
`tests/Feature/PaymentWebhookVerificationTest.php`.
