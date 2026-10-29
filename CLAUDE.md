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

## File storage: everything goes to DO Spaces

Prod's default disk is `digitaloceanspaces` (bucket `happyice-space`, sgp1), so
`storePublicly()`, `Storage::put/url/delete` with no disk name land on Spaces.
Write new upload code that way — **never name the `public` or `local` disk for an
uploaded file**: prod has no files there, so `exists()` is false, copies are
skipped and deletes do nothing (both happened until 2026-10-09).

- **Deleting an `attachments` row goes through `Attachment::deleteFileIfUnshared()`.**
  Mapping replicate shared files until 2026-10-09 (52 files held by 158 rows in
  prod), so a file is deleted only when no other row points at it. Rows whose
  `local_url` holds a full URL (product thumbnails, machine photos) are never
  deleted from disk.
- **Private files never get a URL.** Card settlement reports, refund ticket
  photos and bank payout files sit PRIVATE on Spaces and are served only through
  their authed routes. Bank payout files (refund CIMB / PayNow CSV / PayPal
  worklist, commission CIMB) go through `App\Support\PayoutFiles`, which reads
  Spaces first and falls back to `local` for older files. Incoming Stock batch
  files (supplier invoices / DOs, cost prices) are private since 2026-10-17
  (audit UI-02): served by `product-movements.incoming-attachments.show`, which
  is what `IncomingBatchAttachment::full_url` returns; each row carries the
  batch's `operator_id` under `OperatorIncomingBatchAttachmentScope`; add needs
  `update products`, delete `delete products`. Regression coverage:
  `tests/Feature/IncomingBatchAttachmentTest.php`.
- **View Screen history** (2026-10-15): each delivered Setting/Edit "View Screen"
  picture is kept PRIVATE under `vend-screenshots/{vend}/` with a `vend_screenshots`
  row (who clicked). `App\Services\Screenshot\VendScreenshotHistory` is its only
  writer and prunes each machine to the newest five, file then row, on every save —
  a screen can show a customer's QR or member details, so never raise that or
  serve them by URL. The system also captures every eligible online machine at
  07:00 and 17:00 (Brian, 2026-10-16; `screenshots:capture-scheduled`, spread over
  `vend.screenshot.auto_spread_minutes`, off with `SCREENSHOT_AUTO_CAPTURE=false`),
  kept in the same five with `requested_by` NULL ("Auto") — every request, click or
  schedule, goes through `VendScreenshotRequester`. "View Screen" opens on the newest
  kept picture; "Capture again" asks the machine. The Operation Dashboard's Operator
  column shows the newest one as a thumbnail (`VendController::attachLatestScreenshot`,
  only for `update machine-settings`, the image route's gate). Regression coverage:
  `tests/Feature/VendScreenshotTest.php`, `tests/Feature/OpsDashboardLatestScreenshotTest.php`.
- **Local on purpose:** `storage/app/ota-cache` (the verified APK copy MQTT OTA
  serves), the customer-summary state JSON, logs and temp files.

Regression coverage: `tests/Feature/SpacesFileStorageTest.php`.

## Dates posted back as UTC ISO strings: convert, never store the wall time

Laravel serializes every date cast as UTC ISO-8601 (`2023-10-21T16:00:00.000000Z` for a Singapore midnight),
and the Site and Machine edit pages post the whole record back as received. Eloquent keeps a parsed string's
WALL time and drops its zone, so until 2026-10-19 each save moved `begin_date` / `termination_date` /
`last_invoice_date` 8 hours back — a day every second or third save (1,954 saves since the audit log began; ~490
Site and ~356 machine begin dates were left at 16:00 / 08:00). `App\Models\Concerns\StoresDatesInAppTimezone`
(on `Customer` and `Vend`) converts a date STRING that names its zone to the app timezone before storing; plain
strings and Carbons are untouched. A new model edited by posting its own JSON back takes the trait too.
Regression coverage: `tests/Feature/DateRoundTripTimezoneTest.php`.

## UI language: English or Simplified Chinese, every text wrapped

Brian, 2026-10-26. Each user picks English (default) or 简体中文 with the top-right switch (`users.locale`,
NULL = English; `LocaleController`); `SetUserLocale` sets it for the server too. **Every text a person reads is
written in English and wrapped**: `$t('Save')` in templates, `t('…', { count })` / `tc('one|many', n)` in
scripts (`resources/js/i18n/index.js`), `tk('…')` where a text is stored in a script and shown later with
`$t()` (menus, option lists), `__('…')` in PHP for messages a page shows. The English text is the key, so
an untranslated text shows in English. Never glue pieces (`t('Last sale :hours ago', { hours })`, not
`t('Last sale') + hours`). Not wrapped: database data, request ids / filter values, CSV/Excel headers,
anything sent to machines.

Wording = `lang/zh_CN.json` (shipped) with `ui_translations` (one row per text a person changed, any
language, English too) on top — `App\Services\I18n\TranslationCatalog`, the one reader for the Vue pages
(`/i18n/{locale}.json?v=<version>`, fetched before mount, refetched when the shared `i18n.version` moves)
and for `__()` (`OverridingTranslationLoader`). Admins change wording without a deploy on **Admin →
Translations** (each text says where it is shown — `TranslationPlaces`: menu section › entry › page title ·
web address, never a file name; Brian, 2026-10-26; forms / pop-ups count as the page that opens them, a piece on
> 6 pages is "Shared", server messages name their area; a Page filter lists one page's texts) or with **Edit
wording** on any page (`Components/I18n/WordingEditor.vue`: t() output then
carries invisible markers naming its key; every click is captured so nothing on the page acts). Permission
`translations` (read, update): superadmin, admin. Glossary: `lang/GLOSSARY_zh_CN.md`.

**After adding or changing text**: `php artisan i18n:scan --sync`, then translate the new keys in
`lang/zh_CN.json` — `tests/Feature/I18nTest.php` fails while any marked text has no Chinese. `php artisan
i18n:scan --hardcoded` (or `--file=Pages/X`) lists text not wrapped yet: the to-do list of the full pass
(2026-10-26: ~10,800 findings in 308 files before it started). IDN is not on this code yet.

## Machine IDs link to the Operation Dashboard

Brian, 2026-10-29: every Machine ID on screen, unless a page says otherwise, opens that machine on the
Operation Dashboard in a new tab. Render it with `Components/MachineIdLink.vue` (`:code` = the label as
shown, "C6003" included, or `:vend`), never a hand-built `/vends/customers?codes=` link. The URL is
`opsDashboardUrl()` in `utils/vendCode.js`: label + trailing comma (exact match), `autoload=true`;
Dashboard (Lite) for a product-restricted viewer (shared prop `auth.productRestricted` — the full
Dashboard 403s them) or a Lite-only role; plain text for anyone who can open neither. Not linked: the Operation
Dashboard pages themselves (CustomerIndex, CustomerIndexLite, Components/OpsDashboard, SmartChillerRowCells), inputs / filters / options, and an ID that already opens something else
(e.g. Setting/Index → Setting/Edit). On User edit's machine list the Site opens its Site edit page and
Machine L30d Sales opens Sales Transactions for that machine over today and the 29 days before.

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

**A push deploys sys AND lsh, but nothing runs the seeder on lsh** (own DB, `forge@206.189.39.162`,
`/home/forge/lsh.happyice.com.sg/current`). A new `can:`/`permission:` gate therefore 403s every lsh
user until someone seeds it there. 2026-10-17: the UI Setting per-verb gate locked out lsh
operator_admin (Edit refused). lsh turned out ~170 grants behind (Stock Count, Service Notice,
Happy Hour, the `picker` role, …); the full sync was run there the same day (Brian's go-ahead)
and lsh now matches sys grant for grant. **After every deploy that touches the seeder, run it on
lsh too.** **Indonesia (`idn-sys.happyice.net`, `forge@178.128.31.69`, plain checkout
`/home/forge/idn-sys.happyice.net`, DB `idn_sys`) is not deployed by a push at all** — its checkout
sat at 2026-09-21, 754 commits behind, on 2026-10-28. That day (Brian: "same roles and permission
access level" on all three) main's seeder was run against IDN's DB from a temp copy under a renamed
class, leaving its old code untouched; sys, lsh and IDN then matched exactly (331 permissions, 18
roles, 1,580 grants). Permissions IDN's old code never checks are inert there. Sync IDN the same way
after a seeder change. `observer_transactions` holds zero permissions on both hosts (it did before, too).

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
`OperatorScope::DEFAULT_FILTER_CODES` (HIPL, HIMD, LEA, HIESG, UL-ST, XO, MSW, CB —
XO and MSW since 2026-09-11, Brian: their machines take payment on our Omise
account and NETS terminals; CB (Citybox) since 2026-10-01, Brian: HIPL ops run
the Smart Chillers). It also decides whose sales the Dashboard's
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

**Products follow the operator group, parent only** (Brian, 2026-10-27). A product belongs to one operator
(`products.operator_id`), but HIPL's group sells HIPL's catalogue — HIMD owns no product at all. So a member of
`PARENT_GROUP_CODES` works with its OWN products plus HIPL's, never another member's (CB / XO / LEA / MSW
catalogues stay apart); anyone outside the group with its own; HIPL sees every product.
`App\Support\ProductOwners` (scoped, memoised per request) is the one rule: `OperatorProductFilterScope`, every
Operator filter on a product list (Products index, Warehouse Qty + export, Product Movement — picking HIMD lists
HIPL's products) and the Access Product(s) lists on User / Operator edit read it. The user's own product allow-list
(`ProductAccess`) still narrows on top. Regression coverage: `tests/Feature/ProductOperatorGroupVisibilityTest.php`.

**What a user may see: three levels, in order — operator, products, machines** (Brian, 2026-10-27; User edit shows
them in that order). Operator = the rules above. Products = Access Product(s) (`App\Support\ProductAccess`).
Machines = `App\Support\VendAccess`, the ONE reader for every machine limit (the transaction / record / UI Setting /
delivery scopes, `HasFilter`, the dashboard and report raw queries, the Sales Transactions page and both CSV jobs,
the Ops Dashboard per-site money endpoint, Warehouse Qty's machine counts and low-stock drill-down) — never read
`$user->vends` to restrict a query again. Its modes: **ALL**; **ONLY** = the bind-a-machine list (`user_vend`, empty =
all), for users on "All products"; **EXCEPT** = for a user on "Only the product(s) listed below", every machine but
the ones switched to **No Show** (`user_hidden_vends`). User edit lists such a user's machines LIVE — active machines
carrying their products now (`App\Services\Access\UserMachineVisibility`, the only writer of that table) plus any No
Show one — each Show (default) / No Show, saved at once (`POST /users/{id}/machine-visibility`, `update users`). Each row also shows Prefix, SKU Start Date (Brian, 2026-10-27: when each of their products went into the machine — the first completed refill visit carrying it after the last visit without it, from `ops_job_item_channels`; never the Site's Begin Dt), Machine L30d Sales (the machine's own `vend_transaction_totals_json`, hidden from an editor who is product-limited) and SKU L30d Sales (`ProductScopedSales::thirtyDaysFor`, the user's products only); every header sorts client-side. A
deny-list on purpose: a product owner keeps the history of a machine that stopped carrying their product. Product-limited
users ignore `user_vend`; the two such users bound on prod (test1, powerpop) were carried over to No Show rows on
2026-10-27 so their view did not change. Regression coverage: `tests/Feature/UserMachineAccessLevelsTest.php`.

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

`ProductMovement` deliberately has NO global scope — ops-job pick / unpick read
movements across operators. The Incoming Stock pages (history, batch detail, both
exports, tracking) apply `ProductMovement::visibleToViewer()` /
`capOperatorsToViewer()` by hand instead (2026-10-17), and keying stock needs
`update products`. Regression coverage: `tests/Feature/IncomingStockAccessTest.php`.

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
Machine Settings save, the per-machine "Is Server Price?" switch on the Site edit
page (`CustomerController::updateVendPricingSource`, `update customers`, saves at
once), and a Site RP change — each tells the terminal to re-read settings **and**
re-fetch its menu. Both switches write the one column, so the two pages always
agree, and the save is in `user_logs`, so Setting/Edit's "last changed" names a
change made from the Site page too. **The UI Setting page only SHOWS it** (Brian,
2026-10-15): its per-machine dropdown and the unprotected
`/apk-settings/vends/{id}/pricing-source` route are gone. Wording everywhere is
"Server Price" / "Machine Price" (Server Price = the Site's RP, so the RP is not
repeated). The Machine Settings index and the Site index filter on it (request
`is_server_price` = server / machine, in `Vend::scopeFilterIndex` and
`Customer::scopeFilterIndex`). Regression coverage: `tests/Feature/VendServerPriceSourceTest.php`.

**A Smart Freezer has no choice: always Yes** (Brian, 2026-09-14). The freezer
APK has no board price and drops every menu row without `server_price`, so No
means an empty kiosk (50001 was created that way). `Vend::requiresServerPrice()`
plus a `saving` hook on `Vend` force the flag on every write — creation,
Machine Settings, API — and the Site page switch refuses No with an error.
The selector is locked in Setting/Edit and APK Settings → Edit. The menu nudge
(`VendJobService::syncChannelSlotListToVend`) reaches freezers too — only
chillers are skipped; the freezer APK re-fetches `/menu` only on boot or on that
frame, so skipping it left the kiosk stale until a reboot.

**CityBox chillers are outside all of this.** A CityBox-owned product — one
their catalog sync created, so `products.code` IS the `citybox_product_id`
(`Product::isCityboxOwned()`) — is priced by their portal: channel amounts come
from their API through `ChillerPlanogram` / `ChannelFrameAdapter`, never from
`selling_prices`. Their **price groups** (OPS Pro 价格组, `price_group_list`) are
mirrored hourly into `citybox_price_groups` by `CityboxPriceGroupSync`, and a group
NAMED RP1–RP5 is written onto that RP's `selling_prices` row of every owned product
(delete + add, `previous_amount`, a `user_logs` pair "CityBox price group", source
`citybox-sync`) — Brian, 2026-10-13. So for an owned product Product → Edit shows
those groups READ-ONLY, `ProductController@update` refuses a price and
`deleteSellingPrice` refuses a delete (the next sync would put it back), the same
way OpsJob → Edit Item already suppresses the RP label for a chiller. Which group a
chiller is on is not in their API yet, so the charged price stays their live one.
Regression coverage: `tests/Feature/CityboxPriceGroupSyncTest.php`. **Unit Cost is NOT affected** —
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
  never ticked from a missing line; unbound machine → untouched. **A terminal of
  another rail is not a NETS binding** (audit CARD-01, 2026-10-17): the reconciler
  only counts units whose `settlementProvider()` is `nets`, so a freezer on a
  Payrallel (T05) reads `unbound` ("No NETS terminal"), never "NA in NETS" — before
  the fix 13 freezer 50001 sales (2026-10-05, 10-09) were left `not_captured`;
  `card-settlement:reconcile-refunds --from=2026-10-05 --to=2026-10-09 [--apply]`
  rewrites them. The verdict
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
  captured / No report yet / No NETS terminal) so ops decide on evidence.
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

**A line that failed to dispense keeps its price** (Brian, 2026-10-21: "we already receive the payment, so
payment will not reduced because of error, since we can not do partial auto refund"). A multiple purchase is
paid as a whole — none of 1,099 baskets with a failed line in the 90 days to 2026-10-21 left it out of the
header amount — and is never partly auto-refunded; a claim is paid on request from the line's price
(`RefundItemPrice`). So `VendTransactionService::determineUnitPriceAmount` prices a failed line exactly like
a dispensed one (the TRADE's line price, else the channel's), where it used to store 0. Nothing that counts
SOLD moves: every revenue / GP / sold-qty reader leaves a failed line out by its error code
(`DispenseVerdict::sqlSale`), and `GpMetricsAggregator` already spread the header over zero-priced lines.
Lines stored at 0 before the fix: `sales:backfill-failed-item-prices [--days=31] [--apply]` — run once on
2026-10-21 for the last 31 days, where today's channel price is still what was charged; older lines stay 0.
Regression coverage: `tests/Feature/FailedItemKeepsPriceTest.php`.

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

## Selling prices: never edited in place, so each row records who set it

Product → Edit changes an RP by deleting its `selling_prices` row and adding a new one. So a row's
`created_at` IS when that price was set, `created_by` who set it and `previous_amount` (cents) the
same RP's price before it (2026-10-12). `ProductController::update` writes both on every add, through
`App\Services\Product\SellingPriceHistory::previousAmount()`; rows from before the audit log
(2026-07-18) stay NULL — `SellingPriceAuthorBackfillSeeder` filled the 147 that the log covers. The
page's "Show price history" is read from `user_logs` by the same class (a delete + re-add of an RP =
one change). A new writer of `selling_prices` must set both columns the same way. Regression coverage:
`tests/Feature/SellingPriceHistoryTest.php`.

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
| `card_terminals` | Data Management → **Card Terminal Company** | The supplier list: Nayax, Nets, Nets-Auresys, PAX, MLS, HID, Payrallel (T05). `vends.card_terminal_id` points here. `can_bind_multiple_vends` (default **off**, 2026-10-03): off = binding one of the company's terminals to a machine releases it from any other machine; on = it stays bound to every machine it is given (`CardTerminalBindingService::assignToVend` skips closing the other open bindings; the T05 binder skips deactivating the same token elsewhere). Turning it on for a NETS company makes a TID open on two machines, which the settlement matcher cannot attribute — leave NETS off. |
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
  The Pay Method **filter** request id `cc:<terminal>` is a request contract
  (bookmarked URLs) — keep the ids. What an id MATCHES follows the label
  (2026-09-26, Brian): `VendTransaction::applyPaymentMethodFilter` resolves the
  sale's terminal exactly as the bracket does — the bound unit's company at the
  sale's moment, else `cashless_mfg` — because matching raw `cashless_mfg`
  put 4610's "(Nets-Auresys)" rows under "Credit Card (Nets)". Legacy board
  codes map through `VendTransaction::CASHLESS_MFG_ALIASES` (NYX ⇒ Nayax: boards
  sent "NYX" until 2026-05-14, "Nayax" after) in the filter, the grid label and
  both CSV exports; the STORED value stays raw, because refund classification
  (`config('refund.auto_refund_terminals')`) reads it. Keep the filter's
  subquery and `VendController::transactionIndex`'s `card_terminal_company`
  loop in step. Regression coverage:
  `tests/Feature/RefundIndexPaymentMethodFilterTest.php`.
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
  its TRADE (`release()`); an adopted one is a real sale and stays — `release()`
  re-reads it FOR UPDATE and deletes only `WHERE is_found_in_transaction = 0`, and
  Assign / Ignore run in one transaction, because a live TRADE may be adopting it
  at that moment (audit CARD-05).
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

- **Hands-off (Brian, 2026-09-26: "I need come and run check every week is
  not a way").** A person only uploads the daily NETS file:
  - **NETS lines only meet NETS machines**: every candidate query goes through
    `CardSettlementMatcher::restrictToProvider()` — judged by the MACHINE's
    `vends.card_terminal_id` company (`foreignCompanyIds`), never the sale's
    `cashless_mfg` (2114's board labels its NETS sales "Nayax"). A machine with
    no company set stays eligible and is listed by the nightly check.
    `moveToVend` refuses a machine whose reader belongs to another provider.
  - **Retained credit from the report** (`RetainedCreditLinker`): a failed vend
    NETS charged, then within 15 min on that machine — a same-amount dispense
    with no line = RE-VEND (linked at Sync / nightly once NETS is final), or a
    line equal to (sale − that charge) = TOP-UP (LateTradePairer tier, first,
    note "Matched as top-up"). The dispensed sale becomes a retained-credit
    settlement of the failed one (same columns `RetainedCreditSettlementRecorder`
    writes for TXN_SRC 1). Nothing writes `is_refunded`. **A link is not proof
    the claimant got the item** (Brian, 2026-09-28: on 5073 the failed $1.70
    was card …9265, the $0.70 top-up that took the item card …2599 — two
    people). `RetainedCreditRetry` compares the NETS card on both sales:
    `same_card` → "Retained credit used by same card" (the customer got the
    item on the retry — don't pay), `different_card` → "Retained credit used by
    another card" (a new customer used the stuck credit; this claim stands, left
    out of the email), `unknown` → no card on one side (EFTPOS, a re-vend with
    no line, a TXN_SRC 1 phantom days later) → check. A claim on the RETRY sale
    itself (the second customer, `RetainedCreditRetry::forRetrySales`) reads
    "Paid with retained credit" — they got the item. Refund rows carry
    `retained_credit_retry` / `retained_credit_source` (batched in
    `buildRows`); badged by `netsReportBadge.js`, and said again beside
    Approve on the ticket page (`retainedCreditAdvice`) — advice only, Approve
    is never blocked by it.
  - **Card last 4** (2026-09-28): `card_settlement_rows.card_last4`, from the
    NETS "CashCard Application Number (CAN)" column
    (`NetsMerchantConnectParser::cardLast4` — kept only when the value truly
    ends in 4 digits: EFTPOS lines are blank, a cross-border line showing one
    digit or an Excel-mangled `1.11E+15` stays NULL). Never stored on
    `vend_transactions`: read through `matched_vend_transaction_id` (unique)
    by `CardLast4Lookup`, one query per chunk. Surfaces: the converted-time
    download ("Card Last 4") and both Sales Transactions CSV jobs ("Card Last
    4" + "Credit From Card" = the failed sale's card on a retained-credit
    row, the last two columns). Reports ingested before it:
    `card-settlement:backfill-card-last4 [--report=] [--apply]` re-parses the
    stored file and writes only NULL cells, skipping a whole report if any
    line's terminal/date/amount disagrees with the stored row.
  - **Strong terminal moves apply themselves** after matching
    (`MatchCardSettlementReport::autoMove`, via `TerminalMoveSuggestions`, the
    service the page buttons also use): ≥ `auto_move_min_lines` (3) on one
    NETS machine, clear winner, nothing synced broken, and the machine's
    current terminal silent there since. Weaker evidence waits for a human.
  - **Reports sync themselves** after matching (`auto_sync`); query lines never
    block a Sync.
  - **Undo sync, then Delete** (Brian, 2026-10-05: a wrong or partial file could
    never be removed). A synced report cannot be deleted; **Undo Sync** on its page
    (`CardSettlementUnsyncService`, permission `update card-settlements`) puts it back
    in review and takes back what its Sync wrote: NA sales it created that are still
    awaiting a TRADE are deleted (lines back to "No matching sale in window"; an
    adopted one stays), the `card_settlement_synced_at` stamp comes off its sales,
    its two days stop being final, report-made re-vend links there are removed (an
    APK-recorded one — `CSHL_ARMED_MS` — stays), and every day it touched is unwound
    (`CardSettlementRefundReconciler::unwindDay`: reversal / NA-in-NETS ticks no
    synced report supports any more are cleared and their tickets released, states
    the day can no longer support go to NULL, then the day is reconciled again) and
    rebuilt. Matching and terminal moves are not undone. Delete does the same unwind
    for what the file vouched for beyond its own rows — a purchase in ANOTHER report
    its reversal line undid, and top-up links its lines proved. **One action per
    report at a time** (audit CARD-06): Sync, Undo Sync, Delete and the match job's
    auto-sync all go through `CardSettlementReport::exclusively()` (cache lock,
    status re-checked under it; a busy report answers "try again in a minute").
    Undo Sync records the days still to unwind (`unwind_pending_json`) in the same
    transaction that flips the report to review and clears it when the unwind
    finishes; a failure part-way stays listed in the 08:30 email until
    `card-settlement:finish-unwind` re-runs it (idempotent). Delete removes the
    rows in one transaction and the file only after it commits; if its unwind
    fails, the page and the log give the `finish-unwind --days=…` command.
    Regression coverage: `tests/Feature/CardSettlementUnsyncTest.php`.
  - **`card-settlement:health` at 08:30** emails the HIPL alert-email list
    (`alert_operator_code`) ONLY what needs a person — missing NETS files,
    unsynced reports, weak moves, open refunds on retried vends (same card,
    card unknown, or a claim on the retry itself — never a different card), machines with
    no reader company, double-bound terminals, NETS lines on non-NETS
    machines, days above `alert_unexplained_per_day` dispensed-but-uncharged
    sales, lines pointing at deleted sales. Nothing to act on → no email.

Regression coverage: `tests/Feature/CardSettlementLateTradeTest.php`, `tests/Feature/CardSettlementHandsOffTest.php`,
`tests/Feature/CardSettlementCardLast4Test.php`,
`tests/Feature/CardSettlementOrphanSalesTest.php`,
`tests/Feature/CardSettlementStateAndVoidTickTest.php`,
`tests/Feature/CardSettlementWideMatchTest.php`,
`tests/Feature/CardSettlementDualAnchorMatchTest.php`,
`tests/Feature/CardSettlementOrphanRepairTest.php`,
`tests/Feature/CardTerminalAutoRefundFlagTest.php`.

Regression coverage: `tests/Feature/CardTerminalUnitTest.php` (including an
end-to-end proof that a terminal bound from Setting/Edit still matches a
settlement report).

## Txn, Revenue & Settlement: one sale, three sources, one line

Transactions → **Txn, Revenue & Settlement** (`/vends/txn-revenue-settlement`,
Brian 2026-09-28) follows each sale machine → rail → bank. All rules live in
`App\Services\Sales\TxnRevenueSettlement`; the page only draws them.

- **Revenue is what a RAIL confirms, never the machine's amount.** Card = the
  matched NETS line's `amount_cents` (a retained-credit top-up line is smaller
  than its sale — that is correct); QR = the approved Omise charge; a smart
  freezer's Payrallel (T05) card sale = its intent's `captured_cents` (2026-10-17,
  the open half of audit CARD-01 — no NETS report carries T05). The sale finds its
  intent by vend + TRADE `SFREF` = `card_payment_intents.session_ref` (freezer app
  26+; newest intent per session, card sales on freezers only — `joinT05Intent`):
  captured → Matched (an AI-judged charge can differ from the sale), voided →
  Refunded ("Hold released"), declined / cancelled / error → "No T05 charge",
  anything else → Pending. A sale with no intent keeps the NETS rules (`unbound` →
  Not verifiable). Payrallel's MDR and payout dates are not in mark1, so T05
  revenue counts under "No rate on file" (`unpriced_cents`) with no bank-in. Cash, HID
  and Grab have no rail and stay blank. No figure when the rail returned the
  money (reversal line, `settlement_status` REFUNDED, Omise status 98,
  `is_refunded`) — **except `retained_credit_revend`**, whose money was kept.
- The verdict is ONE SQL expression, `revenueStateSql()`, shared by the grid,
  the Revenue Status filter and the totals. Add a state there, not in Vue.
- **MDR arithmetic reads only `mdr_bps` / `mdr_plus_gst` / `mdr_deducted`** in
  `config('card_settlement.payout_terms')` (`PayoutTerms::mdrCents`), integer
  cents, half-up per line — an ESTIMATE for NETS (the acquirer may round per
  batch). The `mdr` string is a label; keep the two in step by hand. `mdr_deducted`
  false ("full back in") = bank-in is the gross and MDR is billed separately.
  Omise MDR/net are Omise's ACTUAL `fee + fee_vat` / `net` on the charge; the
  Omise transfer date is not tracked, so its Settlement Date is blank.
- Totals group card lines by (terms, amount) and multiply, so they equal the sum
  of the per-line rounding the grid shows. Testing machines are excluded.
- Permission `transactions-revenue-settlement` (read, export): superadmin,
  admin, supervisor. Regression coverage: `tests/Feature/TxnRevenueSettlementTest.php`.

## Smart Freezer videos + AI recognition (Zijia)

`POST /api/smart-freezer/zijia/videos` (`SmartFreezer\ZijiaVideoWebhookController`)
receives the door-session camera video URLs Zijia's servers push. Static shared
token (`config('smart_freezer.zijia.video_webhook_token')`, env
`ZIJIA_VIDEO_WEBHOOK_TOKEN`; Bearer / `X-Api-Key` / `?token=`), 503 until set.
The payload shape is still not final, so every push is kept whole in
`smart_freezer_videos` (`raw_body` exact bytes, `payload` parsed) and
`App\Services\SmartFreezer\Zijia\ZijiaVideoPush` lifts out imei / device no /
their `tradeId` / OUR session ref / video URLs — tighten THAT class when the
contract is final, nothing else parses the body. Every refusal is logged
(`zijia video push refused`), and nginx logs every request to
`/var/log/nginx/sys.happyice.com.sg-access.log` (query string included — the
token is in it, Zijia cannot send headers). **Their notifier counts a push as
delivered only on a plain-text `SUCCESS` body** — anything else is retried every
few minutes, forever (2026-09-30). Their live push carries their session id as
`orderNo` ("SDK…"); an `orderNo` that is not our SF- ref is taken as the trade id.

Each push opens one `smart_freezer_recognitions` row per door session — `trade_id`
is UNIQUE and the row is made with `createOrFirst`, so concurrent pushes (one per
camera) land on ONE row, and a later push fills in the freezer if an earlier one
could not name it (`FreezerRecognitionService`, 2026-09-27): **open** (the freezer is named by
`FreezerDeviceResolver` — our session ref, else the IMEI / device no each freezer
reports in its own status snapshot `identity`, so there is no second copy of the
identity) → **submit** to Zijia's algorithm (`ZijiaAlgorithmClient`,
`dynamic.cabinet.add.queue`, videos + the freezer's LIVE planogram products by
`products.barcode`) → **complete** on `POST /api/smart-freezer/zijia/algorithm/notify`
(`cabinet.algorithm.order.result`, MD5-signed envelope) → **evaluate** against the
paid sale (`RecognitionVerdict`: match / took_more / took_less / mixed /
unrecognised). Rules:

- **Requests are signed with our appSecret** (`ZijiaSigner`) — proven live
  2026-09-27; the key printed in their document is rejected. Their callbacks are
  signed with it too (first live results 2026-09-30), so prod runs
  `callback_verification = enforce`: an unsigned or mis-signed callback is refused.
  The verdict is stored as `callback_verified`. The callback also carries an
  undocumented `jsOrderStatus` / `jsOrderStatusName` — the finer reason behind
  `orderStatus` (501 came with 503 商品未上架) — shown by `RecognitionResult::statusLabel()`.
- **A recognition is metered on their side.** Only a `pending` row is ever sent;
  `submit()` claims it (`pending` → `submitting`) so it is never sent twice, and
  saves the proof of the call (`request_id`, status) before anything else. The
  job has one try; a failed row goes back only on request
  (`smart-freezer:zijia-recognition <id> --retry`, `--force` for one stuck in
  `submitting` — its first call may have landed). `auto_submit` is ON in prod
  (`ZIJIA_ALGO_AUTO_SUBMIT=true`, since 2026-10-02; config default off). Every stop records `status_reason` — the row alone
  must explain why nothing happened.
- **An unverified callback** (log mode) may complete a row mark1 sent, but never
  overwrites a verified answer and never creates a row for a trade mark1 did not
  send; the signature is checked on the raw body (`JSON_BIGINT_AS_STRING`).
- **A door session may arrive as several pushes** (one per camera is possible —
  Zijia has not said). Each `smart_freezer_videos` row points at its recognition
  (`smart_freezer_recognition_id`); the submit job waits until the session has
  been quiet for `submit_delay_seconds` (60) since its LAST push, and sends all
  their videos. A camera arriving after the send is kept and logged, not sent. On
  the sync queue driver the job cannot wait and submits at once (re-queueing
  there would recurse).
- **The sale is found by the TRADE's `SFREF`** (freezer APK v20: our txnRef, the
  same string the door-open hands Zijia as `orderNo`) inside
  `vend_transaction_json`, narrowed by the ref's own epoch on the
  `(vend_id, transaction_datetime)` index (`FreezerSaleLocator`). No column on
  vend_transactions, no TRADE-ingest change. The ref's epoch is the BOARD's
  clock, so a miss falls back to OUR clock (`created_at` ± 2 days of the push).
  A result that lands before the TRADE waits; `smart-freezer:zijia-evaluate-pending`
  (every 10 min, last 7 days) judges it once the sale arrives.
- **The verdict moves money only on a T05 hold; never stock.** A sale paid on a
  Payrallel (T05) terminal by a freezer on app 26+ is charged by its session's
  verdict (`CardPaymentService::settleAwaitingAi`, see the Remote card terminals
  section). NETS and QR sales were charged at the machine and nothing here touches
  them. A paid product the algorithm could
  not have named (no barcode, or a slot with no product) makes the verdict
  `incomplete`, never `took_less` — its "0 taken" is not evidence (a T05 hold then
  charges what the AI saw plus that product at the price paid).
- **`goodsList.sn` is the product's BARCODE** — the 商品条形码 it was modelled with in
  Zijia's portal (vms4.zjoyvd.cn → 商品管理 → 商品申请, or their mini program), which
  their algorithm library stores as `productCode`. It is NOT the 商品编码, even though
  ops type our mark1 code there: proven 2026-09-30 on 50001 — "CC-01" was refused
  ("goodsList入参异常，存在sku商品为null"), its barcode 9726436016148 was accepted and the
  AI answered 1 × 9726436016148, the paid item. Put that barcode in the mark1 product's
  Barcode. ANY `sn` their library lacks rejects the whole request (free: no requestId),
  and a tradeId they refused once is burnt ("订单保存失败" on every resend). Their central
  library (`smart-freezer:zijia-skus`) holds China packs; check it before modelling
  a product that may already exist. **`smart-freezer:zijia-barcode-sync` runs every 3 minutes**
  (Brian, 2026-10-05, until Zijia offers an approval callback): for each product on a smart
  freezer's planogram with no barcode it searches their library by name and writes the barcode
  ONLY on a unique exact name match (case/punctuation aside) — their library is shared by all
  their customers and carries no link to our product, so a near name ("Cat, Basque Cheesecake
  Original") or two entries with our name are left for a person; an existing barcode is never
  touched. Each write is a `user_logs` row (`source` zijia-sync, "Zijia sync"). What it could not
  fill is the 08:30 health email's "Freezer products the AI cannot recognise" section
  (`ZijiaBarcodeSync`). **Product approvals land on `POST /api/smart-freezer/zijia/sku/notify`**
  (`ZijiaSkuNotifyController`) in two shapes. (1) Their DOCUMENTED one — 算法服务接口文档 §7
  商品审批回调 (the doc is in Brian's WeChat files, `智佳算法服务接口文档 20260914.md`): plain
  UNSIGNED JSON `{pass, msg, sku:{productCode, skuName, sysSkuId, applicationNo, attach…}}`, sent
  to the `callbackUrl` set on each application (§5 `sys.sku.sync.put`; portal applications may
  have none). No signature exists, so (audit SF-02, 2026-10-17 — an unsigned push could CLEAR any
  freezer product's barcode or plant ANOTHER product's real barcode, and the barcode decides what
  the AI names and so what a T05 hold is charged): **every §7 callback must carry `?token=`
  `ZIJIA_SKU_CALLBACK_TOKEN`, and while that env is unset every callback is REFUSED**
  (`refused_no_token`, listed in the 08:30 health email) and AI Training refuses to Submit
  (`callbackUrl()` carries the token, so an application sent without one could never be decided).
  Even tokened, a change needs our own application (its `applicationNo`) or their public library
  (§8): an approval is applied only when the library files that barcode under THIS product's name
  (the 3-minute crawl's rule; else `library_name_differs`), a rejection only when the library no
  longer lists it (else `still_in_library`); both wait for a person. Product: `attach` /
  `applicationNo` = `products.code`, else a unique exact name among freezer-planogram products.
  (2) The signed envelope we proposed (2026-10-05), signed like the result callback (enforce:
  unsigned changes nothing), matched by 商品编码 = `products.code`. In both, a code shared by
  several products resolves only to the one on a smart freezer's planogram.
  **Smart Freezer AI Training** (Product → Edit, 2026-10-06; `ZijiaSkuApplicationService`, only
  writer of `zijia_sku_applications` + `zijia_sku_application_events`): staff draft a product's
  modelling application (name, barcode, brand, spec, §5.3 category / package type, package photo,
  model photos per angle — `high` required), saved to DO Spaces `sys/zijia-sku/{product}`; Submit
  sends `sys.sku.sync.put` with `applicationNo` (ours, digits), `attach` = products.code and
  `callbackUrl` = our `/sku/notify`. Every exchange is an event (draft, photos, the exact signed
  envelope, their answer, the §7 callback, the barcode outcome) shown as the section's activity
  log. The §7 callback (tokened) finds the application by `applicationNo`; approving the barcode we submitted
  sets it on the product (no library check needed), anything else is library-checked; a rejection
  keeps their `msg`. Submitted = locked; a new application copies the last one (new number).
  Permission `update products`. The barcode is NEVER set at submit — only on approval.
  **Every photo sent is square (1:1)** (Zijia's rule, 2026-10-09; `ZijiaTrainingPhoto`): PADDED, never
  cropped or stretched (Brian) — white on a photo (JPEG q90), transparent white on a cut-out (PNG/WebP
  with alpha → PNG), EXIF rotation applied first, long side capped at 2048 px, never enlarged. Uploads
  are squared on save (before the DB transaction; original kept under `…/original/`); a link a draft
  starts with (product thumbnail, photos copied from an earlier / vms4 application) is squared on the
  next save and again at submit; submit refuses any photo still not square (`missing()['photos.square']`).
  The folder `sys/zijia-sku/{product}/square/{sha1}` IS the proof (`isSquare()`, no download), so never
  put a file there by any other route. vms4 mirrors stay as Zijia approved them. Prod PHP's
  `max_file_uploads` is 20 and drops extra files silently, so the page caps one save at that
  (`max_files_per_save`). Regression coverage: `tests/Feature/ZijiaTrainingPhotoSquareTest.php`.
  **vms4 mirror** (2026-10-06; `ZijiaLibraryImport`, run by the 3-minute
  `smart-freezer:zijia-barcode-sync` for every smart-freezer planogram product WITH a barcode):
  what a product was approved with in Zijia's vms4 portal is read from their public library by
  barcode and kept as a `source = vms4`, approved application — name, brand, spec, category,
  package type, package + model photos COPIED to DO Spaces `sys/zijia-sku/{product}/vms4/`
  (their link kept only when a copy fails, logged as a warning). Refreshed when their
  `updatedTime` moves (only new photos downloaded); skipped when mark1 itself already has an
  approved application for that barcode. A draft or pending application always stays the
  section's current one. Training happens in mark1 from now on (Brian); vms4 is mirrored. Approved → fills an empty barcode
  (a different existing one is left and reported); rejected / withdrawn → clears a barcode
  equal to theirs (an unsigned §7 one only once their library no longer lists it) (an unknown sn would make Zijia reject the whole session). Every push is
  kept in `zijia_sku_notifications` with its outcome; the ones needing a person are in the
  08:30 health email. The 3-minute crawl stays as the backstop. `modelIdList` is optional ("可以先不用传"):
  empty config sends `[]`, never omits the key.

**Freezer welcome sketches** (Brian, 2026-10-08). The freezer's welcome scene drops a hand-drawn
sketch of each mapped product; mark1 owns them, not the APK. `ProductWelcomeSketchService` is the only
writer of `product_welcome_sketches` (one LIVE row per product) and its album
`product_welcome_sketch_versions` (2026-10-12, Brian: "save to an album to let us shortlist and select"):
every drawing, cut-out, upload and seed is kept as a version; the live row carries a copy of the picked one
(`version_id`) so the menu still reads one row. **A person's Draw (1–4 at once, at most 4 in flight per product)
never replaces the live drawing** — it waits in the album until someone presses Use (`selected_by` /
`selected_at`), except a product with no drawing yet takes the first one that finishes. Automatic draws (new
photo, freezer sweep) still go live, unless the live drawing is curated: seed, upload, or a person's pick.
Product → Edit drives it over JSON (`ProductWelcomeSketchController`: show / regenerate {count} / upload /
versions/{id}/select | shortlist | DELETE) so an unsaved product form is never reloaded. A Draw may carry a
person's **instructions** (2026-10-14, ≤ 1000 chars) — appended after the fixed prompt and ranked above it, kept on
that album entry (`product_welcome_sketch_versions.instructions`); automatic draws never carry any; the live drawing cannot
be deleted, and a file is removed only when no other entry shares its content-hash path. Redraws before
2026-10-12 left their files on Spaces: `products:welcome-sketches --recover [--dry-run]` turns them into
`recovered` album entries (seed art recognised by hash). Served on `/api/vends/{code}/menu` as
`welcome_sketch` — smart mappings only, `?v=` versioned, null = the freezer drops the photo; vending
menus are byte-for-byte unchanged. The Products index shows the live drawing as its Artwork column (`ProductResource.welcome_sketch_url`, the same versioned URL; eager-loaded only there). Sources: **seed** (the approved set in
`resources/welcome-sketches/{product code}.webp`, `ProductWelcomeSketchSeeder`, idempotent, never over an
upload; a duplicated code resolves to the freezer-planogram product), **generated** (OpenAI image edit,
`OpenAiSketchGenerator` behind `SketchGenerator`, the photo + three seed sketches as the style;
config `smart_freezer.welcome_sketch`, inert without `OPENAI_API_KEY`), **upload** (Product → Edit →
Freezer Welcome Sketch), and **cutout** — the free fallback when no model key is set or its drawing
fails: rembg (MIT, `RembgCutout`, CLI at `/home/forge/.local/bin/rembg` installed with uv, model in
`/home/forge/.rembg`, ~7 s / 1.2 GB per photo on prod) removes the background and `sticker()` adds a white
outline + soft shadow. A cut-out made only because no key was set is upgraded to a drawn sketch once
`OPENAI_API_KEY` exists (next save / hourly sweep). Automatic drawing is queued (`GenerateProductWelcomeSketch`, `low`, one try) when a
product is created with a photo, gets a new photo, or is a freezer product saved without a sketch, and by
the hourly `products:welcome-sketches --missing-freezer` sweep (a product just added to a mapping). Rules:
each paid call happens once (the version's pending → generating claim; redis `retry_after` is shorter than a
generation), a photo already drawn or failed is not re-paid automatically until the photo changes, a
pending/generating version older than 15 min is abandoned, a failed draw never touches the live drawing, and
curated art (seed / upload / a person's pick) is never redrawn automatically. Images are trimmed, ≤ 512 px, transparent WebP under
`sys/products/{id}/welcome-sketch/`. A freezer re-reads `/menu` only on boot or a nudge, so every stored
sketch nudges the active freezers carrying that product (`NudgeFreezerMenu`, 90 s delay, debounced per
freezer). The model prompt forbids text not printed on the product (it invented labels on plain bars,
2026-10-08) and asks for the food as eaten — no separate wrapper/packet beside an ice bar; a container
only when eaten from it (cup, tub, fruit tray) (Brian, 2026-10-08: Magnum/Yakoo/Oukuk came with wrappers). Freezer APK 29+ drops the sketch (`welcome_sketch`), then its built-in drawing, then the
photo. Regression coverage: `tests/Feature/ProductWelcomeSketchTest.php`.

**Happy Hour campaigns** (Brian, 2026-10-09; Machine Management → Happy Hour Campaign, permission
`happy-hour-campaigns`: superadmin, admin).

Operator isolation (2026-10-12): `happy_hour_campaigns.operator_id` is the OWNER (the creator's operator;
backfilled from `created_by`, else HIPL). `OperatorHappyHourCampaignScope` (global) shows an operator-restricted
viewer only its own operator's campaigns — every route, since update / destroy / preview reach them by id. Owner
only, never "has one of my machines": a HIPL campaign may span operators and editing it would re-price theirs.
The page lists only the viewer's machines (and products, through a hand-applied boundary on the raw `vends` join),
and a save never unbinds a machine the viewer cannot see. The overlap check, the minute planner and the Sales
Transactions badge read every campaign (`withoutGlobalScope`). Regression coverage:
`tests/Feature/HappyHourOperatorScopeTest.php`.

What a campaign is:
- A scheduled percentage discount on a smart freezer's slowest movers, one SKU per slot.
- Settings: days, hours, active dates, duration per SKU, how many SKUs take turns, discount %, stock floor %,
  minimum units, price rounding, exclusions, prioritized SKUs, allow below cost, bestseller hold after Stock In. Sales look back (days) only feeds days of cover,
  so the form shows it only for that rule.
- The page's Status column shows the slot running now; clicking a machine chip opens that machine's lineup
  ranked at this moment (`preview?vend_id=`). There is no per-day slot list any more (Brian, 2026-10-11).
- Each Status box also lists what the machine is NOT offering and why (Brian, 2026-10-17: "a better picture of how
  the HH is being run"): every skipped SKU on its own line with a tag and the reason spelled out, no hover (Brian,
  same day) — held top sellers first ("TOP 1 … held till Mon 05:33"), then LOW / N/A / PRICE / COST / NEVER — and
  "n holding top sellers back" under the machine count. Ranked LIVE by
  `HappyHourSkipped` (the ranker now, not the reasons at pick time; a SKU on offer now is never listed), fetched by
  the page after it renders from `/{id}/skipped` (~9 queries per live machine) and cached 60 s per viewer. The reason
  key is `HappyHourCandidate::$excludedCode` (`HappyHourRanker::EXCLUDED_*`, one per rule of `exclusion()`) — a new
  exclusion rule needs its own key, or the page files it under "Other".
- The Campaign column shows Total sales / Total $disc / Total qty / Avg daily qty per VM, last 24 h over last 30 days,
  and "Show graph" opens daily bar charts for a chosen range (default 7 days, `/{id}/stats`) — Brian, 2026-10-16.
  `HappyHourCampaignStats` owns the figures: the campaign's TAGGED sales (`HappyHourSales::dailyTotals`, the slot
  results' rules) on machines the viewer may see; "per VM" = per machine-day on which it ran a slot (cancelled slots
  never ran), so a young campaign is not divided by 30.
- **Related Total Sales** (Daniel, 2026-10-22, `HAPPY_HOUR_UPLIFT_GRAPH_PLAN_2026-10-22.md`: HH is the bridge to the rest
  of the basket): every settled, not-refunded transaction whose HEADER carries `happy_hour_slot_id`, counted WHOLE (header
  amount; dispensed units = a single sale's verdict / a basket's lines with a sale code) —
  single HH sales from `HappyHourSales::dailyTotals` + tagged baskets from `dailyBasketTotals`, the same set Sales
  Transactions lists under the campaign's label. Window and graph figures are cached 5 min (`HappyHourCampaignStats::CACHE_SECONDS`:
  ~2 s each on prod, as `happy_hour_slot_id` has no index). The graph stacks
  HH + the rest on sales, units and units per machine, with a `non_hh_*_pct` line (0–100%); the Campaign column shows
  "Related sales" and "Non-HH %". The related total is clamped to never read below the HH part.

`HappyHourPlanner` is the only writer of `happy_hour_slots`. It runs as `happy-hour:run` every minute, and once
on each save. It:
1. picks each slot's SKU **just in time**, `lead_minutes` (2) before the slot starts, from that machine's live
   stock (Brian, 2026-10-11 — never a whole day ahead): `HappyHourRanker`, default rule highest balance %
   (qty ÷ Real Capacity; unknown capacity last), or days of cover (stock ÷ average daily sales from
   `vend_product_records`, nothing sold = first); a tie goes to the higher qty. The top `sku_count` ("Total
   SKUs") of the ranking AT THAT MOMENT take turns, `skus_per_slot` ("At the same time", Brian 2026-10-12) of
   them together: each slot time takes the best ones that were not in today's last `sku_count − skus_per_slot`
   SKU-slots (`nextInTurn`) — 4 total, 2 at a time runs ranks 1+2, then 3+4, then 1+2 again; with 1 total the
   best SKU of each moment repeats. Each SKU of a group is its own `happy_hour_slots` row (same start, unique
   `(vend_id, starts_at, product_id)`), so sold-out, swap, sale tagging and totals all work per SKU: a SKU that
   stops qualifying as the group starts, or sells out mid-slot, is replaced by the best one not already on offer;
2. freezes each slot's product and both prices (integer cents, Site tier, rounded UP to the step since 2026-10-14 `72f1b9f0cd` — Brian: ceiling, not floor; if rounding up would remove the discount it rounds down; screens advertise the campaign's own %);
3. swaps a SKU that no longer qualifies as its slot starts;
4. ends a slot early when its SKU sells out;
5. records units / takings / discount when the slot closes, from the settled sales TAGGED with it (below).

Exclusion rules:
- The stock floor % applies only where Real Capacity is set; most freezer SKUs have none, which is why minimum
  units exists.
- A promo below the current unit cost is excluded unless the campaign allows it.
- A SKU whose warehouse **Available?** tick is off (`products.is_available`, Product Movement) is excluded while the
  campaign's `skip_unavailable` box is ticked — on by default, existing campaigns included (Brian, 2026-10-16). Like
  every exclusion it applies when a slot picks or swaps its SKU, so a SKU made unavailable mid-slot keeps that slot.
- **Bestseller hold** (Brian, 2026-10-16: "after stock in within X hours, exclude last 7 days top Y bestseller SKU").
  A Stock In fills every SKU, so balance % handed the first slots after a visit to the fast sellers. For
  `bestseller_hold_hours` (X) after the machine's last Stock In, its top `bestseller_hold_count` (Y) SKUs by units
  sold over `bestseller_lookback_days` are left out (`HappyHourRanker::bestsellerHold`); then they rank like any SKU.
  Either number 0 = off (the default, so existing campaigns are unchanged). **Stock In** = an `ops_job_items` row at
  that machine with `completed_at` set (Undo Stock In clears it, so the hold lifts with it), not cancelled, and not
  a goods-out action (`OpsJobItem::AUTO_PICK_STOCK_ACTIONS`: return / on-site adjustment / melted stock). Sales =
  `vend_product_records` up to yesterday, every sale (Happy Hour ones too, Brian), only SKUs the machine holds now
  and that sold ≥ 1; a tie at the cut goes to the higher on-hand qty, then product id. The window accepts 7 or 14
  days (`HappyHourCampaign::BESTSELLER_LOOKBACK_DAYS`); the page offers only 7 for now (Brian: "for now we use 7"),
  so opening 14 is a form field, not a migration. A slot picked before the driver arrives is swapped as it starts;
  a live slot keeps its SKU, like every exclusion.
- **Prioritize** (Brian, 2026-10-16: "sku will be selected for HH on every other hour" — to clear stock, or when a
  supplier funds the discount). `priority_product_ids` on the campaign. Every other slot time of the day (positions
  0, 2, 4 … from the window start; `HappyHourPlanner::isPriorityTime`) offers the machine's prioritized SKUs first,
  taking turns among themselves, and fills the places left from the normal ranking; the slots between run the normal
  ranking WITHOUT them. A machine holding none runs the normal ranking every slot. A prioritized SKU keeps the
  campaign's discount %, rounding and below-cost rule (Brian) and needs a price, but only 1 unit: the stock floor %,
  minimum units, warehouse-availability box and bestseller hold do not apply, and it takes no place in the hold's top
  Y. "Never discount" wins, and the form refuses a SKU on both lists. Each slot records `is_priority`: the normal
  rotation and the priority rotation each look back only at their own slots (`picksFor` / `inTurn`), so priority
  slots in between never make ranks 1+2 run twice; a priority slot's sold-out or starting swap tries the
  prioritized SKUs first. The flag is also the hook for a supplier-claim report (none yet).

Machine groups (Brian, 2026-10-14): "All VM - Big Board" / "All Smart Freezers" (`machine_groups`) are LIVE —
every ACTIVE machine of that kind of the campaign OWNER's operator GROUP that can run Happy Hour now
(`HappyHourEligibility`; the group is `OperatorScope::groupOf` — an HIPL campaign covers HIPL and its siblings
HIMD, UL-ST, LEA, HIESG, XO, MSW, CB, Brian 2026-10-15, reversing 10-14's own-operator-only; any other owner covers
itself; an owner missing from `operators` covers itself), joining as it qualifies (a big board on reaching
315) and leaving when it stops. `HappyHourMachineGroups` is the only writer of `happy_hour_campaign_vend`: it
materialises members there with `via_group` (NULL = picked by hand), on every save and in the minute planner
(`followGroups`, which cancels a leaver's slots and nudges the bear fallback), so every pivot reader keeps
working. **Picked by name beats a group** (Brian, 2026-10-14): a machine another campaign IN FORCE (on, inside
its active dates — `HappyHourCampaign::inForce`) picked by hand leaves every group campaign for as long as that
campaign is in force — the WHOLE machine, whatever the hours (Brian's choice) — and rejoins when it is paused,
ended or deleted; save and delete call `HappyHourPlanner::followGroups` so the handover is immediate, and the
group campaign's row lists it as "2031 → <campaign>". Clashes on save are only equal-priority ones: two
campaigns naming the same machine at overlapping hours, or the same group on two overlapping campaigns whose
owners' operator groups meet (an HIMD group campaign clashes with HIPL's). The form's picker shows hand-picked machines only.
Regression coverage: `tests/Feature/HappyHourMachineGroupsTest.php`.

Machines and the freezer:
- One machine, one campaign at a time: overlap is refused on save, and unique `(vend_id, starts_at, product_id)` is the backstop.
- Only freezers reporting `apk_version_code` ≥ `config('happy_hour.min_freezer_apk_version')` (30) get slots.
  An older app would show the full price while mark1 believed in a discount.
- `/menu` rows carry `happy_hour` (smart mappings; that product's open slots in the next 24 h — in practice the
  live one and the next — ISO times with offset, and the slot's `rank` at pick time). With several SKUs at once,
  freezer app 30/31 prices and badges each of them but its welcome Happy Hour page and guess game feature only the
  FIRST promo product in menu order (`indexOfFirst { it.promo != null }`); `rank` is there for it to feature rank 1. The freezer switches on its own clock, and each slot write
  nudges it (`NudgeFreezerMenu`). A cancel deletes a slot under a minute old, so a second save in the same
  minute can re-take that start time (unique per machine).

Vending machines (Brian, 2026-10-12; contract `apk/mark1-apk/UNRELEASED_V315.md`, built on branch
`vending-happy-hour`):
- **Who:** `App\Services\HappyHour\HappyHourEligibility` is the ONE gate (`HappyHourPricing::supports()`
  delegates to it): freezer app ≥ `min_freezer_apk_version` (30), or a vending machine on the BIG board at
  `Vend::reportedApkVersion()` ≥ `min_vending_apk_version` (315), never the ZC-83A small-board family
  (the board the `vending_small` OTA channel claims). Never widen the freezer number to vending.
- **Price:** the ranker prices a vending SKU at what its screen shows — the highest board price
  (`vend_channels.amount`) of its active channels, or the Site tier where the machine follows server
  price and the product has one. The slot records that; `/thumbnails` re-prices each row from its own
  channel price with the campaign's rounding (`HappyHourPricing::channelSlots`).
- **`/thumbnails`** rows gain `welcome_sketch` for eligible machines, and `happy_hour` (`slot_id`, ISO
  `starts_at`/`ends_at`, `price`, `original_price` = that row's price, `discount_pct`, `rank`; `[]` when
  none) only while the machine is in an ACTIVE campaign (`HappyHourCampaign::hasActiveFor`, 2026-10-14):
  APK 316 re-reads this feed on every MQTT reconnect only when it sees the key. Every other machine's
  payload is byte-identical.
- **Nudge:** `NudgeFreezerMenu` sends an eligible vending board the lightweight `HAPPYHOUR` frame
  (`VendJobService::nudgeHappyHourToVend`, re-reads `/thumbnails` JSON only) — never
  TYPESYNCAPICHANNELSLOTLIST, which re-downloads every image. Freezers keep the menu frame.
- **Failsafe — any UI Setting beats Happy Hour, per machine** (`HappyHourPause`; Brian, 2026-10-15,
  widening 10-12's "only while the UI Setting has a live campaign"): a machine with ANY `apk_setting_vend`
  row runs its UI Setting only, whether it joined through a group or was picked by name. A live campaign
  on that UI Setting is only named in the message. The planner gives such a machine no slot, ends a live
  one at the next minute run (cancelled, nudged), and resumes at the NEXT slot once the machine is
  unbound (a slot time that began while paused is not resumed part-way; `happy-hour-paused-at:{vend}`
  cache). The campaign page shows an exclamation icon with the reason on the chip and warns (never
  blocks) in the form. So Happy Hour runs only on machines with no UI Setting (freezers, and big boards
  served the bear by `HappyHourWelcomeFallback` = the HappyIce default UI Setting + bear, Brian's choice).
- **Tagging:** a vending sale is NEVER tagged by price (its own label/bundle/P2 discounts can hit the same
  figure). A 315 touchscreen TRADE carries `"HH": [{"SId": <channel>, "HHId": <slot>}]`; a line is
  tagged only when its channel is named there, the slot is this machine's (and the line's product), and
  the sale is inside the slot ± grace. Malformed entries are ignored; keypad TRADEs carry no `HH`. A
  board-price machine's slot discount is each line's channel price less what it took.
- **A vending basket's items are repriced at tagging** (Brian, 2026-10-16). The TRADE carries ONE `Price` for the
  basket and none per line, so the ingest stores every item at its channel's board price — a promo line too, which
  made a basket's Happy Hour discount $0 and overstated product revenue / GP. `HappyHourSales::repriceVendingBasket`
  rewrites each tagged item to the slot's discount on that channel's price (HappyHourPricing, as `/thumbnails`
  priced it), ONLY when the items then add up to the header's amount; otherwise nothing is guessed (logged).
  The header's `Price` charged failed lines too (2301, 2026-10-16: $2.40 + failed $2.40 = $4.80 kept the dispensed
  unit at $3.40 until then); since 2026-10-21 a failed line is stored at its channel price like any line and repriced
  like any tagged line. Lines stored at 0 before that count in the proof at their channel price — discounted when
  tagged — and a header that leaves them out still proves with them at 0. `planVendingBasketReprice` is the
  one decision (adds_up / reprice / unprovable / none), shared by the tagger and the command.
  Baskets from before the fix: `happy-hour:reprice-vending-baskets [--since=] [--apply]` (re-opens their slots'
  totals, dirties past days).
- **The bear without a UI Setting** (Brian, 2026-10-14; `HappyHourWelcomeFallback`): an eligible big board
  in an ACTIVE Happy Hour campaign with NO UI Setting gets, from `/parameters`, the schema DEFAULTS
  (`ApkSettingParameters::defaults()`) + `enableWelcomeScene = true` instead of the usual 400. It must be
  the complete payload: the APK writes every field it receives, so a partial one would reset the rest.
  When it leaves the campaign, the next fetch gets the defaults with the bear OFF once (cache
  `hh-welcome-fallback:{vend}`), then 400 again. A bound UI Setting always wins. Campaign
  store/update/delete sends TYPESYNCSETTINGSPARAM to its unbound machines so it applies at once.
- **Sketches:** the hourly sweep also draws products on vending machines that SHOW the welcome scene
  (a UI Setting with `enableWelcomeScene` = Bear welcome screen, or served the bear by
  `HappyHourWelcomeFallback`; being on app 315+ alone is NOT enough since 2026-10-14 — the whole fleet
  on 316 would have paid to draw every vending product; active channels only), at most
  `happy_hour.vending_sketch_sweep_limit` (6) new draws a run; freezer products are never held back.
Regression coverage: `tests/Feature/HappyHourVendingTest.php`.

Where the promo price applies:
- An AI-judged extra unit taken during a slot is valued at the promo price (`CardPaymentService::shelfPrices`).
- The freezer's TRADE carries the promo unit price but NOT the slot.
- A refund claim on a tagged line pays back the promo, not the list price (Brian, 2026-10-16,
  RF-261016005): a failed line was stored at 0 then, and the claim fell back to the channel's LIST price.
  `App\Services\Refund\RefundItemPrice` is the one place a refund prices a `vend_transaction_items` row —
  the customer form's candidates, the ticket's items, Items Flagged and Related Transactions all read it. A
  tagged line stored below its channel price is already the charged price (repriced, or a freezer's
  promo) and is kept; otherwise the slot's discount is applied to it. Regression coverage:
  `tests/Feature/RefundHappyHourPriceTest.php`.

Which sales a slot priced (Brian, 2026-10-11): `happy_hour_slot_id` on `vend_transactions` AND
`vend_transaction_items`, written only by `HappyHourSales::tag()`, called from both TRADE ingest paths
(pre-created gateway row and fresh create) once the items exist, never throws — a freezer by price (below), a vending machine by its `HH` list (above).
- A line is the slot's when the same machine sold the slot's product at EXACTLY its promo price within the
  slot ± `happy_hour.sale_grace_seconds` (300; a cart from the last minute is paid after the slot). Price is
  the proof, time only picks between slots. Tagged once, so a later campaign edit never re-attributes a sale.
- A single sale (`is_multiple = 0`) is its header row only — the freezer writes NO item row for it (prod
  2026-10: 15 sales, 6 items). A multiple purchase is tagged per promo item AND on its header ("includes a
  promo unit"; one slot per machine at a time).
- No index and no FK on purpose (INSTANT add on the 4.7M-row table): every reader goes through
  `(vend_id, transaction_datetime)` first — `HappyHourSales::results()` searches the slot ± grace.
- A TRADE tagged after its slot was totalled clears the slot's `units_sold`; the minute run recounts.
- **Happy Hour is a campaign label** (Brian, 2026-10-15): Sales Transactions shows "★ HH 30%" in Campaign Label(s)
  (`HappyHourBadge.vue`; the sale row for a single sale, the item row for a basket), one slot lookup per page.
  `App\Support\Sales\CampaignLabelFilter` is the one definition of a sale's labels: the filter (request key `tag`:
  `all` / `any` = a product label OR Happy Hour / `hh` / `hhc:<campaign id>` / a product tag id-name-slug; `hh:<pct>`
  is no longer offered but still answers bookmarked URLs), its options (`happyHourOptions()`: "★ Happy Hour (all)" first,
  then one "★ <campaign name>" per campaign the viewer's operator owns — Brian, 2026-10-15: the name, not the % — live
  A–Z, then deleted ones that ran slots, "(deleted)") and the "HH n%" wording the grid and both CSV exports + the Excel
  export put in Campaign Labels. Regression coverage:
  `tests/Feature/TransactionIndexHappyHourBadgeTest.php`.
- Forward-only: sales before the column existed are untagged (none had sold at a promo price by 2026-10-11).

A save never errors on a planning hiccup; the minute run catches up. Regression coverage:
`tests/Feature/HappyHourCampaignTest.php`.

**Our own door recordings — camera 5** (Brian, 2026-10-13: "for our own records"). Zijia's push only
ever carries cameras 3 and 4, so the freezer app (v33+, `data/clip/`) records camera 5 itself through
the host's `takeVideo`: from the door unlocking until it closes + 3 s (max 5 min) — not from payment:
the host grabs its cameras for its own order video while the door opens, and on a shared camera ours
gets nothing (bench 2009, 2026-10-13) — shrinks it to
640×360 H.264 and uploads one mp4 per sale to `POST /api/v1/vends/{code}/clips/{SFREF}/{camera}`
(signed like the card rail, raw mp4 body). `FreezerClipStore` is the only writer of
`smart_freezer_clips` — private on Spaces under `freezer-videos/{vend}/`, one row per (machine, session,
camera), a re-upload replaces. **Never put these in `smart_freezer_videos`**: every URL there goes to
Zijia's metered AI. The switch is `GET …/clips/settings` (`smart_freezer.clips`: `FREEZER_CLIP_CAMERA`,
0 = off everywhere, no OTA; `FREEZER_CLIP_VENDS` = "50001" or "2009:3" to narrow / pick a camera); the
freezer re-reads it every 30 min and records nothing until mark1 has answered once. Shown as "▶ Cam 5" in
the Sales Transactions AI Recognition cell and "▶ Ours · Cam 5" on AI Recognition (`FreezerClipLinks`,
by SFREF), played through `/freezer-clips/{clip}` (permission `read ai-recognition` + operator
boundary → a 30-min signed Spaces link). Remote control op `clip` {cameraId, seconds 3–60} (app 33+,
refused mid-sale) records a camera check under `SF-<code>-<epoch>-0`; the panel timeline links it.
Regression coverage: `tests/Feature/FreezerClipTest.php`, `tests/Feature/FreezerRemoteControlTest.php`.

**Camera guard + unfulfilled PayNow refund (Brian, 2026-10-16; freezer app 34+).** Zijia's AI judges a
session from cameras 3 and 4, so a freezer listed in `FREEZER_CAMERA_GUARD_VENDS` (`smart_freezer.camera_guard`,
empty = off; "50001" uses `FREEZER_CAMERA_GUARD_CAMERAS` default 3,4; "50002:3+4" its own) gets
`guard_cameras` on the same `clips/settings` answer (`FreezerCameraGuard`) and asks the host whether they are
online at checkout and again after payment, before the door unlocks. Down (after one 2 s re-look) stops the
sale; a host that does not answer lets it go ahead. Before payment: "technical problem" page, nothing
charged. After payment: a card hold (T05 / NETS) is voided by the freezer's own abort; a PayNow payment is
reported on signed `POST /api/v1/vends/{code}/orders/{orderId}/unfulfilled` {session_ref, amount_cents,
reason} and `FreezerUnfulfilledRefund` refunds it in full via `RefundOmiseJob` (source
`omise_door_not_opened`) — only for reasons that mean the door was never commanded to open (`cameras_down`,
`unlock_authorization_failed`; never `door_open_failed`), only an APPROVE Omise log of this machine with the
same cents, approved within 6 h, with no TRADE and no Zijia video for the session; once per order (cache).
Without this a paid-but-never-opened freezer order was booked as a code-99 sale and never refunded (the freezer
CONFIRMs before unlocking, so the no-dispense refund cannot fire). 404/409/422 = refused for good, logged
"freezer unfulfilled refund refused", the order waits for a person. Coverage:
`tests/Feature/FreezerUnfulfilledRefundTest.php`.

**Transactions → AI Recognition** (`/ai-recognition`, `FreezerRecognitionController`,
permission `ai-recognition` read: superadmin/admin/supervisor) lists every recognition:
machine, Zijia's order no + our SF ref, links to the original videos, the AI's answer
and the verdict against the sale. It reads `smart_freezer_recognitions` only — the sale
link is its `vend_transaction_id`; nothing is added to `vend_transactions`. The table has
no global scope: an operator-restricted viewer gets only its own freezers' rows, and a
session matched to no freezer (a supplier test cabinet) only the unrestricted operator.
Each row is badged **AI** (`orderStatus` 0) or **Human** (any other
code: Zijia's AI could not answer on its own) — one rule, `RecognitionResult::processedBy()`, which
the badge and the Processed By filter (`processed_by`: all / ai / human / none) both read (Brian, 2026-10-09).

**Sales Transactions → "AI Recognition" column** (`App\Services\SmartFreezer\RecognitionBadges`,
attached per page in `VendController::transactionIndex` as `ai_recognition`): the verdict (or
where the check stands), what the AI saw, and the processing time — `ai_seconds` (sent → result,
their turnaround) and `total_seconds` (videos received → result). A sale is matched by the
recognition's `vend_transaction_id`, or before the verdict by its TRADE's `SFREF` =
`session_ref`: two indexed lookups per page, no column on `vend_transactions`. Built for any
AI-checked machine (a CityBox chiller once their order data exists). Regression coverage:
`tests/Feature/TransactionIndexAiRecognitionColumnTest.php`.

Integration record, live proofs and open questions for Zijia:
`apk/smart-freezer/ZIJIA_ALGORITHM_2026-09-27.md` (algorithm) and
`ZIJIA_VIDEO_WEBHOOK_2026-09-14.md` (push). Regression coverage:
`tests/Feature/ZijiaVideoWebhookTest.php`, `tests/Feature/ZijiaAlgorithmRecognitionTest.php`,
`tests/Unit/ZijiaAlgorithmPiecesTest.php`.

## Remote card terminals (Payrallel): one writer, void what nobody fulfils

Built 2026-09-30 for the smart freezer, **not yet run against Payrallel** — bench
steps: `apk/smart-freezer/PAYRALLEL_BENCH_RUNBOOK_2026-09-30.md`. Inert until
`PAYRALLEL_API_BASE_URL` is set and a terminal is bound
(a Payrallel (T05) Card Terminal unit picked on Setting/Edit — see below; the
freezer's runtime row is `remote_card_terminals`). Nothing here touches NETS: those
terminals are `card_terminal_units`, reconciled from the NETS CSV.

- **`App\Services\CardTerminal\CardPaymentService` is the only writer of
  `card_payment_intents`** (state diagram on the model). The device API
  (`Api\V1\FreezerCardController`, `/api/v1/vends/{code}/card/*`) and
  `card-payments:reconcile` (every minute) both go through it.
- **An approval nobody will act on is voided:** a cancel that raced the tap, a
  send that timed out but may have reached the terminal (`CardTerminalException::$mayHaveReachedTerminal`),
  an attempt past `intent_ttl_seconds`. A void is refused after capture or past
  `device_void_window_minutes` — the goods may be gone.
- **Pre-auth is the mode** (`payrallel.mode`, default `preauth` since
  2026-10-01, Brian — Payrallel's flow, auth_incr on their side): the tap holds,
  the freezer's capture after the door opens charges, a failed door voids
  (releases) the hold. A hold the freezer never confirms is captured in full by
  `card-payments:reconcile` once the void window has passed — goods are
  presumed released, and an uncaptured hold expiring (~a month) would give them
  away. **Every capture is written ahead** (audit CARD-02, 2026-10-17): the
  device's capture and that auto-capture move the intent to `capturing`
  (`ai_decision.inflight`) before the call. An answer → `captured`; an explicit
  refusal (4xx) or no connection → back to `approved`, retried every 10 min; a
  timeout / 5xx / a run that died mid-call stays `capturing`, flagged
  `uncertain`, and is NEVER resent — not by the sweep, not by the device's retry
  (which gets `capturing` back), and it cannot be voided. A person checks
  Payrallel and runs `card-payments:resolve-charge {reference} --charged|--not-charged`
  (old name `resolve-ai-charge` still works), the same command as the AI path.
  The per-intent lock lives `CardPaymentService::lockSeconds()` = 3 × (timeout +
  connect timeout) + 10 s, the longest call chain under it; a device request that
  cannot get it answers 409 "busy — retry", never 500 (audit CARD-04).
- **The AI verdict decides a T05 charge** (Brian, 2026-10-03; plan
  `apk/smart-freezer/AI_GATED_CARD_CAPTURE_PLAN_2026-10-03.md`). A door-closed
  capture carrying `session_ref` (the kiosk txnRef = the TRADE's SFREF, freezer app
  26+) charges nothing: the intent goes `awaiting_ai`, and `card-payments:reconcile`
  charges it once `smart_freezer_recognitions` (same vend + session_ref) has a
  verdict, by `AiCaptureDecision`: the hold is the ceiling (no buffer); match →
  cart; took_less / mixed / took_more → what the AI saw, valued at the price paid
  in that sale else the machine's `vend_channels.amount`; nothing taken → void;
  above the hold → ONE capture of the whole judged amount (Brian, 2026-10-04, as
  Payrallel told us: "you only need to do capture, as auth_incr is done on our
  backend" — never several captures on one order); if Payrallel refuses that (a 4xx
  other than 408/409/423/425/429 — `CardTerminalException::isDefiniteRefusal()`;
  a rate limit or a connect failure is retried at the full amount, audit CARD-08),
  the hold itself is captured at once and the rest recorded in `owed_cents`
  (`ai_decision.increment_refused`); **incomplete** (a paid product had no barcode, so
  the AI was never asked about it) → the middle ground (Brian, 2026-10-05): products the
  AI was asked about count as it saw them, and each unasked paid product (its verdict line
  has `product_id` but no `code`) counts as taken at the price paid — never less than the
  old cart rule, still catches extras; an incomplete result never releases a hold;
  unrecognised / a taken product with no price → cart. **No verdict → never charged on its
  own** (Brian, 2026-10-09 for an AI answer without a verdict — a callback came: `orderStatus`
  ≠ 0 / the "Human" badge, or the sale never matched; 2026-10-10 for a session the AI never
  answered — the old 72 h cart backstop and `ai_capture_backstop_hours` are gone). The hold stays
  `awaiting_ai` until a person presses **Key in charge** on AI Recognition, keys in the final
  amount and confirms it twice (`CardPaymentService::chargeByPerson`, route
  `ai-recognition/card-holds/{intent}/charge`, permission `charge ai-recognition`: superadmin,
  admin). `personNeededBecause()` is the one rule: an answer without a verdict can be keyed in at
  once; a session the AI never answered only `payrallel.ai_person_after_minutes` (30) after the
  door closed, so a late answer is not pre-empted. A hold whose session Zijia never pushed has no
  row on the page, so it is listed above the table ("T05 card holds with no AI session"). 0 releases
  the hold; above the hold is the same one capture with the hold as fallback; the person is kept
  on `ai_decision.by_person`. Every such hold is in the 08:30 health email with its age — an
  uncaptured hold expires (~a month) with the goods given away. Only T05 holds come here: NETS
  and QR sales were paid at the machine and the AI only reconciles their stock.
  The decision is made ONCE (`ai_decision`: `capture_cents`, `fallback_cents`; the first
  verdict is final); a refused capture of the hold amount (4xx) is retried every 10 min.
  **A capture whose outcome is unknown is never resent** (a lost answer may already be
  money taken, and a second capture on one order could double it): the capture is
  written ahead (`inflight`); a timeout / 5xx / a run that died mid-call
  flags it `uncertain`, the intent stays `awaiting_ai` with the error on AI Recognition,
  and a person checks Payrallel's portal and runs
  `php artisan card-payments:resolve-charge {reference} --charged|--not-charged`.
  A DNS / TCP-connect failure is a definite "not sent" (`PayrallelGateway::neverConnected`),
  only a read timeout or 5xx is uncertain. Safety rules from the 2026-10-04 review:
  a verdict decides the amount only if it is the session's ONLY verdict, its callback is
  signature-verified, and its sale is a card TRADE (`TXN_SRC` 1) for exactly the cart —
  otherwise the cart is charged; a TRADE price ≤ 0 never counts (shelf price, else cart),
  and the hold is released only when the AI saw zero units taken. Each intent snapshots
  its T05 token (`card_payment_intents.access_token`, encrypted; `terminalOf`) because a
  T05 swap edits `remote_card_terminals` in place. Each intent in the sweep is isolated
  (`isolated()`), so one bad row never stops the others; the schedule is
  `withoutOverlapping(5)`. The 08:30 `card-settlement:health` email lists T05 charges
  needing a person (uncertain, refused, increment refused, waiting > 2 days), and no longer
  flags a terminal of a `can_bind_multiple_vends` company as double-bound. A capture with no session ref
  (app 25) still charges at once; `PAYRALLEL_AI_CAPTURE=false` restores that for
  all. Shown on AI Recognition ("Sale / Card charge"). `vend_transactions.amount`
  is NOT changed to the captured figure — revenue follow-up open.
  Regression coverage: `tests/Feature/AiGatedCardCaptureTest.php`, `tests/Unit/AiCaptureDecisionTest.php`.
- **The sale waits for the charge** (Brian, 2026-10-05; on main 2026-10-28). A T05 freezer TRADE whose
  hold is still `awaiting_ai` is written with `settlement_status = PENDING` + `audit_pending_at`
  (`App\Services\Freezer\FreezerSaleAudit::record`, fresh ingest path only): listed, but out of every
  sales / revenue / GP / qty figure ("Pending AI" on Sales Transactions; the Dispense cell still shows
  the TRADE's verdict). When the charge is decided `settle()` writes the CHARGED cents as the amount
  (SETTLED; a released hold = REFUNDED, never a sale), rebuilds the item rows into the basket the
  decision charged for (one row per unit; a cart-total fallback leaves them alone), re-tags Happy
  Hour (a rebuilt row keeps its slot; a slot totalled meanwhile recounts) and dirties the day.
  `CardPaymentService` calls it after every AI capture / void, and `reconcile()` settles any held sale
  whose intent was resolved in the last 48 h by any path (person, `resolve-charge`, device void). A
  TRADE landing after its charge was decided is booked at once. A cabinet with no barcoded product is
  never held. Regression coverage: `tests/Feature/FreezerSaleAuditTest.php`.
- Only an exact provider `approved` approves (`PayrallelGateway::query`); an
  unknown word stays processing.
- Device requests are HMAC-signed with the vend's `private_key`
  (`VerifyDeviceSignature`, alias `device.signed`); the freezer's
  `DeviceRequestSigner` is pinned to it byte-for-byte. A vend on the fleet
  fallback key is only as private as that key.
- A new provider = one more `RemoteCardTerminalGateway` + a line in
  `RemoteCardTerminalGatewayFactory`; the rules above do not move.
- **The trial timeline** (`card_payment_events`, file `storage/logs/payrallel.log`):
  `CardTerminalEventLog` is its only writer and never throws. It records every
  Payrallel call with the raw body, every intent state change (model hook, so no
  call site can skip it), terminal status changes, binds, refused device
  requests, and the freezer's own `device.*` events (`POST …/card/events`). Read
  it with `php artisan payrallel:timeline {vend} [--since= --ref= --raw]`.
- The freezer picks this rail only where a terminal is bound here
  (`SF_CARD_RAIL=auto`, `SelectingCardRail`): binding is the switch, deactivating
  switches back. **A T05 is a Data Management → Card Terminal unit** (2026-10-03) under
  the company Payrallel (T05): `terminal_id` is its **SN** and `card_terminal_units.access_token`
  its Payrallel token (encrypted, `$hidden`, write-only on the form — blank keeps it; the
  NETS-only fields are hidden for it). It is put on a freezer like any terminal, with the
  **Card Terminal picker on Setting/Edit** (T05 units are offered on smart freezers only), so
  the binding has the usual dated history. `CardTerminalBindingService::assignToVend` then
  drives the rail through `App\Services\CardTerminal\RemoteCardTerminalBinder` — the one
  writer of `remote_card_terminals`: a T05 unit → active row (token + `card_terminal_unit_id`
  copied from the unit); another terminal or none → that row off. **A Setting/Edit save only
  switches off a row bound FROM a unit**: the form posts the terminal on every save, so a row
  made by the old token-prompt command (no unit — 50001 since 2026-10-02) survives unrelated
  saves; `payrallel:bind-terminal {vend} --deactivate` takes off either kind. Editing a unit's
  token/SN reaches every row made from it (`syncUnit`); deleting the unit switches them off.
  One T05, one machine unless the company's `can_bind_multiple_vends` is on (the binding
  service keeps the other open binding, the binder keeps the other row). The Setting/Edit
  panel "Remote card terminal (T05 · Payrallel)" is read-only status (SN, online/state, Check).
  CLI: `php artisan payrallel:bind-terminal {vend} --sn=<SN>` binds a registered unit.
  Regression coverage: `tests/Feature/RemoteCardTerminalBindingTest.php`.

Regression coverage: `tests/Feature/CardPaymentServiceTest.php`,
`tests/Feature/PayrallelGatewayTest.php`, `tests/Feature/FreezerCardApiTest.php`.

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
- **Their orders are our chiller sales, and they move our stock** (Brian, 2026-10-13;
  `citybox/OPENAPI_REPLY_PLAN_2026-10-13.md`). `order_list` is a PULL by ORDER day —
  no callback — and a past day keeps changing (pay-later captured up to 14 days late,
  refunds 1 → 3 → 4), so `citybox:pull-orders` runs every minute (today), hourly (3
  days) and 01:20 (31 days), each a unique queued job; one call per day, an unchanged
  order (payload hash) costs one bulk `last_seen_at` update. `CityboxOrderSync` mirrors
  each order into `citybox_orders` / `citybox_order_items`, then:
  - **`CityboxOrderProjector`** writes ONE `vend_transactions` row per order —
    `order_id` = their order number, `transaction_datetime` = when the money came in,
    one unit = product on the row, more = `is_multiple` + one item row PER UNIT, unit
    cost / GST / GP as the TRADE ingest, payment method `Citybox - Card / Apple Pay /
    TNG / Other` (codes 900–903, never Card Terminal — card settlement keys on code 1),
    Site frozen at the first projection. Status 1 and 3 (refund applied) are sales; 4 is
    `is_refunded` + REFUNDED + `auto_refund_source = citybox_refunded` through
    `markAutoRefundedByCharge`. Status 0 unpaid / 2 paying (goods taken, money not captured)
    is listed too (Brian, 2026-10-21) as `settlement_status = 3` UNPAID, booked at the order
    time: Payment "Unpaid" on Sales Transactions (filter "Unpaid"), never a sale — every sales
    gate is `= 2` — and the same row turns SETTLED on the pay time once CityBox reports it
    paid. 5 rejected / −1 cancelled move no stock and are not listed (`skip:status`). Last sale
    (`last_vend_transaction_at`) ignores unpaid rows. A test-rig amount (`ODD_TRANSACTION_AMOUNTS`) is never
    projected (`skip:test_amount`) or the 23:59 sweep and the next pull would fight. A day
    the sale lands on or leaves is dirtied for the 02:00 rollup rebuild, so every sales /
    GP / product report picks chillers up with no report change.
  - **`ChillerStockLedger`**: a chiller's `vend_channels.qty` is OUR ledger now, like a
    freezer's. Absolute counts set it — the Stock In A-frame read-back, an Undo Stock In's
    revert, the chiller's first read — each recording `vends.stock_baseline_at` (when their
    count was READ; its own column since 2026-10-17, audit STK-02 — it lived in
    `citybox_status_json`, which six writers save whole from stale copies, so a poll put old
    baselines back). The baseline only moves forward (conditional UPDATE) and is written by
    `SyncVendChannels` in the transaction that writes the count, under the vend row lock
    `ChillerStockLedger::apply` also takes; an order the ledger deducted while that frame waited
    on the queue is taken off again (`countLanded`). A hand overwrite sets ONE SKU and records
    no baseline (the baseline is per vend). Each order takes its units off ONCE
    (`stock_deducted_json` per product, so a corrected / cancelled order moves only the
    difference), unless taken at or before the baseline (already inside that count) or
    corrected after a newer count. A refund returns money, not goods: stock stays out.
    Unpaid orders are in `order_list` too, lines and all (status 0, `pay_succ_time`
    0000-00-00 — first seen 2026-10-19, order 319 paid 32 min later), so their goods leave
    the ledger when taken, not when paid.
  - **Their live count only checks ours.** `device_product` / device state / signal are
    read every `citybox.stock_check_every_minutes` (10), not every minute (fleet status,
    one `box_list` call, still is). The scheduled frame flags `qty_seed_only`, so
    `SyncVendChannels` writes qty only into a row with no ledger yet (new, or back from
    retirement) — never over a live row, or a deduction made after their read would be
    lost. `StockPollService::checkLedger` keeps the per-SKU gap on
    `citybox_status_json.stock_check` (`confirmed` = still there a check later); the
    dashboard row badges it and the overview shows "CityBox n" under our qty.
  - **The Operation Dashboard shows them like any machine's** (Brian, 2026-10-14).
    `SmartChillerRowCells` draws only the chiller's own cells (`part="lead"`: ID →
    Stock; `part="tail"`: Machine Status → Operator); Sales(qty), Last / Upcoming Job,
    Refilling Routes, Lifetime / Accumulated VendEarning and Contract / L30d earning are
    the page's shared cells — they read Site rollups, which the projector already feeds.
    The Machines page (Vend/Index) is split the same way (`lead` / `status` / `tail`
    around the shared Sales(qty) and Last Visited / Avg Per Day cells). Never blank
    them for a chiller again.
  - **A stop's "Transaction S$ (Qty)" counts a chiller's sales by PAY time.**
    `OpsJobItemSalesWindow` (the only writer of `ops_job_items.acc_total_*`) places a
    vending sale by `created_at` as always, a chiller's by `transaction_datetime` — its
    `created_at` is the pull (the 10-13 backfill created every earlier order that day).
    Late orders keep landing in closed windows, so `ops-jobs:recompute-sales-window`
    re-counts chiller stops of the last 31 days at 01:50, after the 01:20 pull.
    Regression coverage: `tests/Feature/OpsJobItemSalesWindowTest.php`.
  `CITYBOX_STOCK_LEDGER=false` restores the old mirror. Regression coverage:
  `tests/Feature/CityboxOrderSyncTest.php`.
- **Restock photos gate the door** (Brian, 2026-10-13: "we locally control and
  detect") — on a chiller stop AND a Smart Freezer stop (same day: "store in local as a
  record in ops job"). The driver takes a BEFORE photo, and the door controller
  (`CityboxOpsJobItemController::openDoor`, `FreezerOpsJobItemController::openDoor`)
  refuses until it exists — only before Stock In (`config('citybox.require_before_photo')`);
  after Stock In the item page asks for the AFTER photo. They are `attachments` in
  `OpsJobItem::PHOTO_RESTOCK_BEFORE / _AFTER` (types 11/12, `RestockPhotoService` the only
  writer: upright, ≤ 1600 px JPEG, public on Spaces; routes in `RestockPhotoController`,
  `RestockPhotoService::appliesTo` = which machine kinds), split out of the generic item
  attachments by `OpsJobItemResource` (`restock_photos`). Only a CHILLER's go further: once
  the stop is stocked in and both exist, the latest before + after URL go to CityBox in ONE
  `deliver_pic_submit` call with the visit's door-open `msg_id` (2026-10-15;
  `PushCityboxRestockPhotos`, `citybox_photo_push_*`, shown under the photo boxes), triggered
  at Stock In or by a photo taken after it; `CITYBOX_RESTOCK_PHOTO_PUSH=false` keeps them in
  mark1. A freezer has no supplier API; its photos are the job's own record. Regression coverage:
  `tests/Feature/CityboxRestockPhotoTest.php`.
- **Signal** comes from their `signal_strength` in the stock check. Since 2026-10-15 it answers
  a STRING, "RSRP-好(-89dBm,31asu)", not the number its doc shows; `ChillerSignal` reads the dBm
  onto 5 bars (≥ -80 → 5, ≥ -90 → 4, ≥ -100 → 3, ≥ -110 → 2, else 1; a plain number is 0–100).
  The bars land on the same `vends.internet_*` columns a SIM reading uses (max 5, network 4G),
  and the dBm + their word go on `citybox_status_json.signal`, so the existing pill reads it with
  no query change. Regression coverage: `tests/Unit/CityboxSignalTest.php`.
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
  `product_mapping_items.capacity_override` ("Real Capacity" on ProductMapping →
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
  Freezer sales still resolve the channel from the frame's `SId`; chiller sales
  (CityBox orders, pulled since 2026-10-13) resolve it by PRODUCT — the SKU's row,
  else 0 for an off-planogram SKU (`CityboxOrderProjector`).
- Only an EXACT repeat of a code is refused (`assertUniqueChannelCode`, and the
  red cell + blocked Save in the editor). "102" and "102A" may coexist (Brian,
  2026-09-23): they are distinct rows under the unique index, distinct labels
  everywhere, and they sort 102 before 102A.

Regression coverage: `tests/Feature/SkuStockIdentityTest.php`,
`tests/Unit/ChannelCodeTest.php`, `tests/Feature/FreezerChannelSyncTest.php`.

**Hand overwrite of on-hand qty** (Setting/Edit, 2026-09-30 — ops and technicians
correct a count without an ops job). Neither machine has a separate Stock Qty table: a freezer's
planogram cells carry qty and Adjust (Brian, 2026-10-03), a chiller's mapping table has Qty +
"Last changed by hand" columns (Brian, 2026-10-08). Shown only where the saved machine holds the
same product as the mapping picked on screen (freezer: same code AND product; chiller: by
product, the SKU's first row Adjusts, later facings show "same stock as 101" read-only).
Both draw `Components/StockQtyInline.vue` over `composables/useStockQty.js`. `App\Services\Stock\ChannelQtyAdjuster`
is the only writer, and every applied change is a `vend_channel_qty_adjustments` row
(who, when, before → after), shown on the row as "brian · 260930 11:03 pm (3 → 7)".
Freezer: sets `vend_channels.qty` (our ledger). Chiller: sets our ledger too
(ChillerStockLedger, 2026-10-13) AND sends the new figure to CityBox through
`device_stock_submit` on the machine's LAST door-open session, as the FULL live list with
one line changed (a partial stocktake is unproven), and reads it back; no door-open ever
from mark1 = refused. Vending machine: refused — its VMC's next CHANNEL frame would
overwrite it. Every overwrite carries the qty the person saw and is refused if the machine
moved since (a sale, a Stock In) — for a chiller compared with OUR ledger, so a count that
differs from CityBox's can still be corrected (with `CITYBOX_STOCK_LEDGER=false`, with
theirs). A chiller is checked twice: before the CityBox calls, and again under the row locks
after them, since those calls take seconds and an order can be deducted meanwhile (audit STK-05);
the second refusal says CityBox already holds the new figure. No baseline is recorded, so a
pay-later order for that SKU taken before the count and paid after it is still deducted from the
hand-set figure. A chiller DECREASE made here is recorded by the next stock check as a `sale`
movement in `citybox_stock_movements` (no door-open window) — nothing reads that table yet. Regression coverage:
`tests/Feature/VendStockQtyAdjustTest.php`.

## Dashboard > Performance: a product filter switches the rollup

Daniel, 2026-10-29. `vend_records` (date × machine) has no product column, so Performance reads it only while
no product is picked. The Product / Product Category / Product Category Group filters (same row as Lite) switch
that request to `vend_product_records` (`DashboardController::show()` sets `$lite`; the page stays `Dashboard`,
prop `productGrain`): sales are the picked products' lines, and every machine / site count — "machines in
operation", Historical Analysis, "Based on n machines" — counts only machines that SOLD one of them (a product row
exists only on a day it sold; there is no daily "carried" history). Earnings breakdown is hidden then (site
earnings are not split by product). The filter's one definition is `VendProductRecord::applyProductFilters()`
(Eloquent and the raw "in operation" query) / `hasProductFilter()` (`'all'` and empty = none); today's bar and the
top-10 pie read live sales per product line via `matchingProductIds()`. Every chart cache key carries the filter.
With no product picked, every figure is byte-identical to before (checked 2026-10-29 over 8 filter sets on both
pages). Expect "all products" to read ~0.5–0.9% above the whole-machine total: baskets are rolled up per line at
unit price, the header at what was charged. Historical Analysis "Machine Count" = machines selling on an average
day of the month (machine-days ÷ days with a sale), so Daily Sales/ VM × Machine Count × days = Total Sales; it read
a column the query stopped returning on 2025-07-08 and showed 0 until 2026-10-29. Regression coverage:
`tests/Feature/DashboardPerformanceProductFilterTest.php`.

## Operation Dashboard: "Needs attention" before Search

Brian, 2026-10-17. On first load the grid stays empty (Search loads it), and a section under the
Last 30 days / Current cards lists the machines that need someone now, grouped by what is wrong
(`Components/OpsDashboard/CriticalAttentionPanel.vue`, fetched after paint from
`POST /vends/customers/critical-attention`, permission `read vend-customers`). Every rule lives in
`App\Services\OpsDashboard\CriticalAttention`; the page only draws them:

- **(1) offline and 2A–2E are Machine Health's own alerts**: offline with a last sign of life under
  `MachineHealthDashboardService::OFFLINE_ALERT_MAX_HOURS` (60, `lastContactSql()` — one definition
  for both pages), and the ACTIVE `vend_smart_alerts` rows `DetectTempTrends` keeps. Vending machines
  only, like Machine Health (a freezer's or chiller's probes mean something else).
- **APK reboot (connection issue)** (Brian, 2026-10-18: "reboot means connection issue"): the
  board's own offline auto-reboots — no HTTP to mark1 for 6 min, `Main2Activity::triggerOfflineReboot`.
  The APK counts them in `OfflineRestartCount` on every `P` heartbeat; `SyncP` books the rise since the
  stored count (`App\Support\OfflineRebootCounter` — a new install or the Clear button restamps
  `OfflineRestartCountDatetime`, which means "counted from 0") as the `offline_reboot` daily stat, under
  the vend row lock. `vend.attention.apk_restarts_per_day` (5) of them on today or yesterday flags a
  machine. **No crash group yet**: `App\Support\ApkRestartDetector` still counts every process restart
  as `apk_restart` (each PWRON's frame counter `f` at or below the previous one's, same `apkver`), but
  that also holds crashes, power cuts and reloads, which nothing can tell apart — add an "APK crash"
  group only once a crash is detectable (e.g. the APK reporting its crash handler). The first deploy
  ran `ops-attention:backfill-offline-reboots --apply` (today + yesterday estimated from the running
  total). Before it the card read "APK keeps restarting (crash / reboot)" and 2525's 21 a day were all
  no-internet reboots.
- **Touch screen frozen**: the 306+ UI watchdog, today or yesterday — a looper stall that did NOT recover,
  `ui_no_frames`, or a day whose `ui_probe_fail` had no `ui_probe_pass`. Older builds never report it. The
  APK sends `looper_recovered` seconds after a stall that clears by itself; since 2026-10-25 the ingest
  counts it as `ui_looper_recovered` and the rule nets stalls − recoveries over the two days (Brian: "turn
  off the alarm once situation is recovered" — 2809 was flagged all day for an 8 s GPU stall that cleared
  in 15 s). A stall with no recovery — still stuck, or ended in a reboot — stays flagged. `no_frames` has
  no recovery event, so each one counts.
- **No sales**: last sale older than `vend.attention.no_sales_hours` (48), every machine kind; a
  machine that never sold is not flagged.

The section counts the SAME machines as the cards: `VendController::applyCustomerIndexRequestDefaults()`
(visited, Include unbound?, status by role, operator group, `indexType`) and
`customerIndexFilteredQuery()` are shared by the page, the cards, the Grouped? seed and this endpoint —
add a population default there, never in one caller. Every flagged machine is listed as "code - site" with
its reason, no Show / more click (Brian, 2026-10-18).

**The flagged machines' full grid rows load by themselves** (Brian, 2026-10-18: "show the complete
customerindex result entries"). Once the section has loaded, `CustomerIndex.vue` fetches the rows as an
Inertia partial reload of `vends` only (`codes`, `numberPerPage=All`, `preserveUrl`; `indexCustomer`
skips `initialStats` when `codes` is set, so the cards stay as they are). It loads only the
`ATTENTION_AUTO_ROWS` (60) most urgent first: a full row costs ~80 KB raw and ~70 ms of server time
(prod 2026-10-18: 44 rows = 3.8 MB raw / 434 KB gzip, ~3 s). The other rows load when their category
or line is opened. Each row's "#" cell carries its reason tags. A category's count ("7 show entries")
or a machine line narrows the loaded rows client-side with no request; the alert lookup
(`fetchActiveAlerts`) skips a subset of the ids it already fetched. A lone code gets a trailing comma
so `VendCode::whereSearch` matches it exactly. Search replaces all of this with the normal grid.
Regression coverage: `tests/Feature/OpsDashboardCriticalAttentionTest.php` (including the
partial-reload contract).

**"Show Alert/Error" button** (Brian, 2026-10-19: "if operator want to focus in alert/error machines,
click this button (instead of filter)"). Beside Operations; it searches with the page's other filters
kept plus `alerts_only=true`, and lists only the machines with at least one red badge in the Error /
Machine Status / Payment Device columns or a Needs attention reason. `App\Services\OpsDashboard\MachineAlerts`
is the one rule, and each reason mirrors its badge's colour rule in `CustomerIndex.vue` — change the
two together: HTTP offline (past Needs attention's 60 h window, so never tagged twice), an uncleared
channel error, a 1d / 2d / 7d error rate ≥ 3%, drop sensor off (even `Sensor`), a reporting modem unit
offline, bill / coin acceptor `Stat` 1, coin float ≤ 3000, and the three idle payment rails
(`PaymentRailIdle`). Left out: "Dispensing Door: Open" (156 of 469 active machines read open,
2026-10-19 — the door flag is not usable), MQTT Offline (grey, not red), the refund trend. Active or
testing machines only. The population is `customerIndexFilteredQuery()` (Needs attention's), not
Grouped?'s clusters. The `machineAlerts` prop carries each machine's tags (the "#" cell) and a count
per reason; a reason chip re-searches with `alert_reason=<key>`, the counts staying over the whole
list. The button carries a red count like an unread badge: the listed total while the list is open,
otherwise `POST /vends/customers/alert-count` (`customerIndexAlertCount`, same population and gate as
Needs attention), fetched after paint and again after a Search that changes the filters. Prod 2026-10-19: 255 of 402 machines flagged, the rule ran in 268 ms; error rate ≥ 3% alone
puts 39 on the list. Regression coverage: `tests/Feature/OpsDashboardMachineAlertsTest.php`.

**Silent payment rails get a red frame** (Brian, 2026-10-17: components "long time in no function
condition, causing no transaction in either Omise, Cash or Card"). In the grid, the Card Terminal,
LCD Monitor (the QR shows on it = Omise) and Coin Float badges are framed red when the machine HAS that
rail and took no sale on it for `vend.attention.payment_idle_hours` (48). `App\Support\PaymentRailIdle`
is the one rule, emitted as `VendResource.payment_idle` (only where the grid selected the last-sale
columns). It reads only `vends.last_card_ / last_cashless_ / last_cash_vend_transaction_at`, which the TRADE
ingest keeps (payment method code 1 / a gateway method / code 0; checked 2026-10-17: under 3% of active
machines differ from their real last sale by more than 5 min). Active, non-testing machines only. Hover
says why, and whether the WHOLE machine went quiet too (then check the machine, not the device); the
frame (Coin Float: its red tag, since the badge itself opens the float history) opens the machine's
Sales Transactions in a new tab, from that rail's last sale, 7 to 31 days back. Regression coverage:
`tests/Unit/PaymentRailIdleTest.php`, `tests/Feature/OpsDashboardPaymentRailIdleTest.php`.

**Trend graphs by transaction count + Happy Hour** (Brian, 2026-10-22/23). Small four-point line graphs, L30d →
L7d → Y'day → Today, all drawn by `Components/OpsDashboard/TrendSparkline.vue` from points built in
`composables/opsTrendPoints.js`: green when the latest point (today, else yesterday) is at or above L30d, red below,
grey with nothing to compare; hover lists the four with their counts; a window with nothing breaks the line. Under
the Card Terminal badge: **Touch screen** (transactions made on the touch screen ÷ all transactions) and **Cashless**
(transactions paid any way but cash ÷ transactions with a payment method on record) — by TRANSACTION, never pieces
(Brian, 2026-10-23: a touch-screen basket holds several, which skewed the first, piece-based version); each hidden on a
machine that never used that way in 30 days. `App\Support\TransactionMix` is the one rule: a transaction = a settled
sale that sold ≥ 1 piece (Sales(qty)'s count; a failed or refunded sale is none); touch = `interface_type` 1 (TRADE
`TXN_SRC` 1) except Grab Mart (`Grab::PAYMENT_METHOD_GRABMART`, also 1 but ordered online) — keypad is 0 / blank, keypad
QR 50, remote dispense 2; cashless = `payment_methods.code` ≠ `PaymentMethod::CODE_CASH` (card, QR / e-wallets, HID,
Grab). In the first column's Happy Hour box: **L7d Non-HH %** and a **Daily HH sold qty** graph —
`App\Services\OpsDashboard\MachineHappyHourTrend`, the campaign page's rules for one machine: HH units / sales =
tagged sales (`HappyHourSales::dailyTotals`), related = every single HH sale + every tagged basket counted whole
(`dailyBasketTotals`), never below the HH part; Non-HH % = (related − HH) ÷ related in $; L30d / L7d qty = the
average over the days the machine ran Happy Hour (a non-cancelled slot started that day), Y'day / today as they are,
no point on a day without Happy Hour. Windows start at midnight: today, yesterday, today + 6, today + 29.
`SyncVendTransactionTotalsJson` (after each sale, unique per 3 min) computes them once and stores `touch_usage`,
`cashless_usage`, `happy_hour` in `vend_transaction_totals_json` and the Site's `totals_json` — vending machines only
(null otherwise; `happy_hour` null with no slot in 30 days). Indexed range scans: ≤ 40 ms for the mix, ~30 ms for Happy
Hour per machine on prod (2026-10-23), so the page adds no query. Regression coverage:
`tests/Feature/TransactionMixTest.php`, `tests/Feature/MachineHappyHourTrendTest.php`.

## Fan detection: the board decides, a person can only reset

Brian, 2026-10-19. `vends.is_fan_enabled` ("Fan speed signal") is no longer chosen on Setting/Edit:
`App\Support\FanSignal` is the one rule. A fan is RUNNING only above `FanSignal::RUNNING_MIN` (1000 —
real fans read ~3,600–5,400; fan-less inputs read 0 or 200–800 noise). A machine HAS a fan from the
first running reading, stamped once in `vends.fan_detected_at` (the readings in `vend_fans` are kept only
~14 days), set by the parameter ingest (`SyncVendParameter::createVendFan`) with
`check:vend-fan-enabled` (04:10) as backstop. It never goes back to "no fan" by itself — a fan that later
reads 0 is a broken fan, which 2A exists for. New machines start with no fan (column default 0); before,
every machine started Yes and fan-less boards raised 2A for months (2256: 240 days). Setting/Edit shows
Detected (since …) / Not detected and, with `update machine-settings`, **Reset fan detection**
(`POST /vends/{id}/fan-detection/reset`) for a board or fan change; `VendController::update` no longer
writes the column, so no save can set it. **Machine Health 2A** (`DetectTempTrends`, via
`FanSignal::offSince`) judges only REPORTED readings: off = readings still arriving but none running for
`OFF_AFTER_MINUTES` (40), from the last running one (or the 8 h lookback); no readings at all is a
connection gap, never "fan off" (2627 / 2527 alarmed on that before). The 2026-10-19 move ran
`fan:backfill-detection --apply` (dry run without `--apply`): detected = a running reading kept or in the
last parameters, else no fan — sys: 565 machines to no fan (26 active: the three false 2A alarms
2003 / 4730 / 2031 cleared, plus fan-less vending boards, freezers, chillers, benches), 365 stamped; lsh: 3
to no fan (2003, 2006, 2007), 15 stamped. The system's own changes leave no `user_logs` row (it records
only a person's web actions); a Reset does. Right after, the only active 2A on sys was 2301, whose ~400
signal never reads as running. Regression coverage: `tests/Feature/FanAutoDetectTest.php`.

## APK OTA: nudges and the updater's own report

`ota:nudge-stale` sends OTA_CHECK (`App\Services\OtaCheckNudge`) to online,
active machines behind the latest published build on their channel. Two runs
(`App\Console\Kernel`):

- **01:00–07:00, every 30 min**: every stale machine (installs land while idle).
- **07:30–23:30, every 30 min, `--unreachable`** (2026-10-04): only stale
  machines whose own OTA poll has not reached `/ota/manifest` for
  `ota.nightly_nudge.unreachable_after_hours` (12) — `apk_checked_in_at` NULL or
  older. These are the Air724 square-module + VoicePing boards: their ≤306
  updater gives a poll 30 s and a new connection there takes ~20 s+, so almost
  every poll times out (field logs 2026-09-27, bench repro 2026-10-04). Each
  nudge is another chance; a board that reaches 307 retries by itself
  (`apk/mark1-apk/UNRELEASED_V307.md`). Off switches: `OTA_NIGHTLY_NUDGE`,
  `OTA_DAYTIME_NUDGE`.

**OTA over MQTT (big-board 309+, 2026-10-05).** When a board's HTTPS manifest
poll fails 3 times in a row (Air724 + VoicePing on the CMI-HK route never
completes it while MQTT stays up), it sends `OTAREQ` and mark1 answers on
`CM<code>` with `OTAMANIFEST` — `ServeOtaManifestOverMqtt`, built by
`App\Services\Ota\OtaManifestService`, the SAME service `GET /ota/manifest`
uses (check-in included). After 2 failed HTTPS downloads the board pulls the
APK as `OTACHUNK` → `OTACHUNKDATA` 48 KB pieces (`ServeOtaChunkOverMqtt`),
served only for the channel's live release at the exact version + sha, from a
verified local copy (`OtaApkCache`, `storage/app/ota-cache`). Both jobs run on
`low` (never in front of a payment), are rate-limited per vend, and the frames
are never stored in `vend_data`. Change the manifest rules in
`OtaManifestService`, never in one transport. Regression coverage:
`tests/Feature/OtaOverMqttTest.php`.

Big-board 307+ also reports on every `P` heartbeat what the updater and the
square module see, stored on `vends` by `RecordVendOtaModem` (low queue):
`ota_fail_streak` (OtaFail, 0 = last poll fine), `ota_last_error` (OtaErr,
NULL once a poll succeeds), `modem_firmware` (ModemFw), `modem_pdp` (ModemPdp,
"IPV4V6/redone"). NULL = a build that does not report or no square module. A
per-vend cache fingerprint queues a write only when a value changed, and the job
writes only the columns that differ, so `ota_modem_changed_at` is "last
changed", not "last heard". Regression coverage: `tests/Feature/VendOtaModemTest.php`,
`tests/Feature/OtaNudgeStaleTest.php`.

## Vending menu refresh: every visible change tells the machines

A vending APK re-reads its names list (`/thumbnails`) only when told to
(TYPESYNCAPICHANNELSLOTLIST, the "Push Products Info to Machine" frame) — on
APK 209 not even on reboot. So a product added to a channel of a mapping the
machine was already on showed a photo and price with no name (4730,
2026-10-07). `App\Jobs\Vend\PushMappingMenuSync` now sends that frame,
debounced per mapping (10 s), to every active or testing **vending** machine on
the mapping; smart freezers keep their own immediate `SmartFreezerCatalogPush`,
chillers have no APK. Triggers: every planogram write in
`ProductMappingController` (via `nudgeBoundMachines()`, and Save), and in
`ProductController` a change to a column the machine shows
(`ProductController::MENU_COLUMNS`), its tags, photo or translated names → every
mapping carrying the product; a selling price added or deleted → only machines
on server price. A new write path that changes what a machine shows calls
`PushMappingMenuSync::schedule()` / `scheduleForProduct()`. Regression coverage:
`tests/Feature/MappingMenuAutoPushTest.php`.

## Nightly stock count: a lost night is estimated, never re-run

`save:today-stock-count` (23:59) snapshots every machine's live `vend_channels` into
`stock_counts` / `stock_count_items` (the "Stock Count Dashboard"; unrelated to ops-job
`stock_checks`). Because it reads LIVE stock, **never run `save:stock-count <past date>`**:
it stamps today's stock on that date. A night that lost its items is estimated with
`stock-count:reconstruct-day <date>` (preview, `--backtest`, `--apply`; only fills headers
with no items, `qty_warehouse` NULL). The CMS warehouse list is fetched once per run
(`StockCountWarehouseQty`), and a CMS outage costs only `qty_warehouse` (NULL = unknown,
not 0), never the machine qty. 2026-09-21 is the one reconstructed day:
`STOCK_COUNT_RECONSTRUCTION_2026-09-21.md`.

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
