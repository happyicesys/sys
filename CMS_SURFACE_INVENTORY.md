# CMS Integration Surface — Full Inventory (for deprecation planning)

_Scanned: mark1 + cms repos. Goal: every place the two systems call or answer each
other, so the CMS role can be wound down with no hanging threads._

Legend — **Status**: 🟢 live (runs in normal operation) · 🟡 dormant (reachable but
not scheduled/triggered today) · 🔴 dead (caller commented out / unreachable).
**Verdict**: KEEP (needed for the agreed transitional role) · CUT (safe to delete) ·
DECIDE (needs your call).

---

## A. mark1 → CMS (outbound — mark1 calls CMS)

| # | Trigger (mark1) | CMS endpoint | Purpose | Status | Verdict |
|---|---|---|---|---|---|
| 1 | `sync:all-cms-vend-code-vend-prefix` cron **02:00** (`Kernel.php`) → `SyncVendCodeVendPrefixCMS` | `GET /api/sys/person/{id}/vendcode/{code}` | Push Sys Vend code → CMS **Vend ID** + prefix (nightly, all active linked vends) | 🟢 | **KEEP** |
| 2 | On **bind** a vend (`CustomerController` 2730/3639) → `SyncVendCustomerCms::dispatchSync` (only the vendcode callback survives; inbound migrate disabled) | `GET /api/sys/person/{id}/vendcode/{code}` | Real-time Vend ID push when a machine is bound | 🟢 | **KEEP** |
| 3 | On **unbind** a vend (`VendController` 3981 & 4037) | `GET /api/person/{id}/detach-vendcode` | Clear CMS Vend ID when machine unbound | 🟢 | **KEEP** |
| 4 | **Create API Invoice** (`CustomerController` 2598/2702) → `SyncCustomerInvoiceCMS` | `POST /api/transactions/deals` | Create CMS invoice/transaction from a customer period; stores `cms_transaction_id` back. Logged. | 🟢 | **KEEP** |
| 5 | Ops job delivered (`OpsJobController` 1071) → `SyncOpsJobTransactionCMS` | `POST /api/transactions/deals` | Ops-job delivery → CMS transaction (decrements CMS warehouse stock → feeds the planning page). Logged. | 🟢 | **KEEP** |
| 6 | Warehouse planning page (`ProductController::availability`) + `SaveStockCount` job (`save:today-stock-count` cron **23:59**) → `CmsService::getCMSQtyAvailableApi()` | `GET /api/items/available-pcs` | **Pull warehouse stock qty** ("Qty in Warehouse (from API)" + stock-count reconcile). **CMS is system-of-record for stock.** | 🟢 | **KEEP** (until mark1 owns inventory) |
| 7 | Ops job item delete/cancel (`OpsJobController` 1969 → `OpsJobService::deleteJobItemCMSTransaction`) → `DeleteTransactionsCMS` | `POST /api/sys/transactions/delete` | Reverse a CMS transaction when its ops-job item is removed | 🟢 | **KEEP** (pairs with #5) |
| 8 | `OpsJobService::createCMSEmptyInvoicesByOpsJobItem` → `CreateTransactionsCMS` | `POST /api/sys/transactions/create` | Older "empty invoice" path | 🔴 caller commented out (`OpsJobController` 1938) | **CUT** |
| 9 | `syncCmsInvoiceItems` (`CustomerController` 3227) → `SyncTransactionItemCMS` | `POST /api/transactions/deals` | Bulk push invoice items | 🟡 reachable via route, not in normal flow | DECIDE |
| 10 | `SyncOpsJobItemTransactionItemCMS` | `POST /api/transactions/deals` | Per-item transaction push | 🔴 dispatch commented out (`OpsJobController` 1940) | **CUT** |
| 11 | `UpdateCustomerCmsFields` job (seeder/ad-hoc only, per its own docblock) | `GET /api/person/migrate/{id}`, `/api/person/files/{id}` | One-off CMS→mark1 customer field + file backfill | 🟡 not in runtime flow | DECIDE (keep as one-off tool or CUT) |
| 12 | `sync:products-api` cmd → `SyncProductApi` | `GET /api/items/migrate` | Import products from CMS | 🟡 **not scheduled** | DECIDE |
| 13 | `SyncUnitCostProducts` cmd | `GET /api/items/unitcosts/profile/2` | Import unit costs from CMS (note: scheduled `sync:product-unit-costs-timing` is a **different** command) | 🟡 **not scheduled** | DECIDE |
| 14 | `SyncLocationType` cmd → `ProcessCustomerLocationType` | `GET /api/person/location-type` | Import location types from CMS | 🟡 **not scheduled** | DECIDE |
| 15 | `SyncLastInvoiceDate` cmd | `GET /api/people/last-invoice-date` | Pull last/next invoice dates | 🟡 **not scheduled** (data now arrives inbound — see B1) | **CUT** (superseded by B1) |
| 16 | `syncFromCms` (`CustomerController` 3201) | `GET /api/people` | Full customer mirror from CMS | 🟡 inbound sync intentionally disabled | **CUT** |
| 17 | Customer **Create** page options (`CustomerController` 2742) | `GET /api/vends/unbind` | "Pull from CMS" customer picker | 🟡 the Pull-from-CMS UI path is disabled | DECIDE |

## B. CMS → mark1 (inbound — CMS calls mark1; routes in `routes/api.php`)

| # | Trigger (CMS) | mark1 endpoint | Purpose | Status | Verdict |
|---|---|---|---|---|---|
| 1 | CMS `Transaction` created/updated with a **future** delivery date (`Transaction::boot` → `SyncVendSysJob` → `HasVendSys::syncVendSystem`) | `POST /api/v1/customers/people` → `syncNextDeliveryDate` | CMS pushes future transactions → mark1 updates `next_invoice_date` / `last_invoice_date` ("Next Planned Visit"). **This is what actually keeps next-delivery-date current** (not the dormant A15 cmd). | 🟢 | DECIDE (you said ops jobs now drive visit planning — if so this column is redundant) |
| 2 | CMS (`PersonController` 270, `TransactionController` 4850) → hardcoded `https://sys.happyice.com.sg/api/v1/binded-vends` | `GET /api/v1/binded-vends` → `getBindedVends` | CMS pulls the list of bound vends from mark1 | 🟢 | **KEEP** while CMS person/vend admin is used |
| 3 | `getCustomersByPersonID` | `POST /api/v1/customers/person/{personID?}` | CMS queries mark1 customers by person id | 🟡 CMS caller commented out (`PersonController` 468/476) | DECIDE |
| 4 | `syncOpsJobItem` | `POST /api/v1/ops-jobs/item/{opsJobItemID}` | CMS callback to set ops-job item sequence/txn | 🔴 CMS caller commented out (`HasVendSys` 162-168) | **CUT** (both ends) |

## C. UI-only references (cosmetic — not data dependencies)

`cmsEndpoint` / `cmsBaseUrl` props and `isCmsUrlSet` (`HandleInertiaRequests`) are passed
to Customer, Vend, Setting, ProductMapping, DeliveryPlatformOrder and OpsJob pages purely
to render **"Open in CMS ↗"** deep-links. No data flows through them; they vanish cleanly
when CMS goes. Leave until last.

---

## The actually-live CMS surface (what you must keep working)

Strip away the dormant/dead rows and the real footprint is small and all keyed on
`person_id`:

1. **Vend ID mirror** — push on bind, detach on unbind, nightly reconcile (A1–A3).
2. **Create API Invoice** — customer + ops-job transactions into CMS (A4, A5) and their reversal (A7).
3. **Warehouse stock pull** — CMS is system-of-record for stock qty (A6). The ops-job
   transactions in #2 are what decrement that stock, so #2 and #3 are one loop.
4. **Bound-vends answer** — CMS reads mark1's bound vends (B2).

Everything else is a hanging thread.

## Hanging threads to clean up (safe CUTs)

- **A8, A10, B4** — dead code (callers commented out). Delete the jobs/routes.
- **A15, A16** — superseded / disabled pulls. Delete.
- **A12, A13, A14, A11, A17, B3** — dormant import/pull paths. Confirm none are run
  manually, then delete. (These are the riskiest to assume — flagged DECIDE.)

## Two corrections to earlier claims in this thread

- **Warehouse Qty IS CMS-backed** (A6) — via `ProductController` + `CmsService`, not local
  tables. CMS owns stock today.
- **Next-delivery-date is NOT dormant** — it's fed **inbound** by CMS (B1) whenever a
  future-dated transaction is created, not by the unscheduled outbound command (A15).
  Whether you still need it depends on whether ops jobs have fully replaced it.

## Execution log — cuts applied 2026-06-13

**Deleted files — mark1:** Jobs `CreateTransactionsCMS`, `SyncOpsJobItemTransactionItemCMS`,
`SyncTransactionItemCMS`, `UpdateCustomerCmsFields`, `ProcessCustomerLocationType`;
Commands `SyncProductApi`, `SyncUnitCostProducts`, `SyncLocationType`, `SyncLastInvoiceDate`;
Seeder `UpdateCustomerCmsFieldsSeeder`.

**Deleted files — cms:** Job `SyncVendSysJob`.

**Edited — mark1:** `OpsJobService` (dropped `createCMSEmptyInvoicesByOpsJobItem` +
`CreateTransactionsCMS` import; kept `updateJobItemCMSTransactionID` — still used by the
live `SyncOpsJobTransactionCMS` — and `DeleteTransactionsCMS`). `OpsJobController` (removed
`syncOpsJobItem`, dead import, dead commented block). `CustomerController` (removed
`getCustomersByPersonID`, `syncFromCms`, `syncCmsInvoiceItems`, `syncNextDeliveryDate`,
`SyncTransactionItemCMS` import; neutered Create-page `cmsCustomerOptions` to `[]`).
`routes/web.php` (removed `/sync-next-delivery-date`, `/sync-cms-invoice-items`).
`routes/api.php` (removed `/customers/person`, `/customers/people`, `/ops-jobs/item`).
Vue `Index.vue`, `IndexComponent.vue` (removed "Next Planned Visit" column + the Sync-Next-
Delivery-Date button/handler/ref), `CustomerIndex.vue` (removed Sync-CMS-Invoice-Items button
+ both handlers + ref).

**Edited — cms:** `Transaction.php` (removed `SyncVendSysJob` dispatch from `created`/`updated`
boot hooks + import; kept the driver-fill logic). `HasVendSys.php` (removed `syncVendSystem` +
`syncVendCustomer`; kept `getFutureTransactions`, still used by `PersonController`).

**Verified:** no remaining code references to any removed symbol/route in either repo
(only inert HTML comments remain). PHP brace balance intact. **Not yet done:** `npm run build`
(Vue changes) — must run before deploy.

## Decisions needed from you

- **B1 / "Next Planned Visit"** — keep the CMS-fed column, or drop it now that ops jobs plan visits?
- **A9, A11, A12, A13, A14, A17, B3** — are any of these run manually today? If not, all CUT.
- **End-state for warehouse stock (A6)** — stay CMS-sourced, or migrate to mark1's own
  `product_movements` (the `ProductMovement` page already computes stock locally)? This is
  the one dependency blocking full CMS removal.
