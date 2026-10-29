# Grab revamp plan: store, catalogue, stock, campaigns (2026-10-29)

**Status (2026-10-29): Phase 0 + Phase 1 DEPLOYED** (`0cdc338af7`, 09:43) — see §6 for what shipped and what is
still a switch. First prod check: `grab:menu-diff` = every selling store "same" or "stock only". Phases 2–5 are plan only. Every number below was read from prod
(read-only MCP) on 2026-10-29, and every Grab rule comes from the Partner API v1.1.3 docs (GrabFood and
GrabMart pages).

## 1. What runs today

| | Prod value |
|---|---|
| Grab credentials (`delivery_platform_operators`) | 12, **all `mart.partner_api`** (no GrabFood at all). 3 sandbox. Live with orders: HIPL (dpo 15), KMY (24), ARK (25) |
| Grab stores bound to a machine (`delivery_product_mapping_vend`, open) | 83 (18 of them "paused"); 127 rows ever |
| Grab mapping templates (`delivery_product_mappings`) | 16, with 262 items and 2,130 per-machine channel rows |
| Orders | 13,397 ever; 950 in the last 90 days; 243 in the last 30 (~8 a day) |
| Campaign rows (`delivery_platform_campaign_item_vends`) | 1,633, **none live**: the last ended 2026-08-31 |
| Menu sync callbacks stored (`delivery_platform_menu_records`) | **2.8 M rows**, ~2,250 a day |
| Inbound Grab calls since 2026-08-07 (`delivery_platform_auth_probes`) | 599k. **100% carry a valid Passport bearer token** |

How it fits together today:

```
products ─ ProductMapping (vending planogram, by channel code)
              │ copied by hand into
              ▼
        DeliveryProductMapping ("Grab template": own price per channel, own Mart sub-category per channel)
              │ bound per machine (platform_ref_id = Grab merchantID, vend_code = partnerMerchantID)
              ▼
        DeliveryProductMappingVend ──► DeliveryProductMappingVendChannel (per VMC channel: qty mirror,
              │                          reserved %, order_qty counter)
              ▼
   Grab item id = delivery_product_mapping_items.id   (one Grab item per CHANNEL)
   Campaign = per template → fanned out to one Grab campaign per (campaign item × machine)
```

## 2. What is built wrongly or poorly

Ordered by impact. Each finding is either confirmed on prod or read directly in the code; the evidence is
named.

### A. Orders and money

- **A1. A repeated order webhook gets a 500, forever.** `createOrder` throws `Replicated Order ID on Creation`
  when the orderID exists (`DeliveryPlatformService.php:96`). Grab retries a slow or failed answer. It also
  re-sends the same orderID with `isMexEditOrder=true` when an order is edited, so every edit is refused as
  well. Prod: merchant 4-C6VGNELVVNAZN6 got 15 create calls on 2026-10-28 and 1 order was saved.
- **A2. The order webhook makes Grab API calls before it answers.** `markOrderReady` runs inline, and so do
  `checkOrderCancelable` + `cancelOrder` when a channel is missing (`DeliveryPlatformController::createGrabOrder`).
  Grab gives us 10 s. A slow Grab answer becomes a retry, and the retry becomes A1.
- **A3. "Paused" stores reopen after 24 h, and their orders are refused.** Grab caps a pause at 24 h.
  `delivery:sync-store-pause` exists but is **not scheduled** (`Kernel.php` has only `grab:sync-all-menu`).
  4754 (4-C7W2RE5APADUGN) has had 265 order calls since 08-07 with 25 saved, and it still gets about 5 a day
  in October. Each refused call is a Grab customer whose order was rejected.
- **A4. A store is found by machine code, not by Grab store.** Both the order and the menu lookup need
  `vend_code = partnerMerchantID`. Grab keeps the partnerMerchantID that was registered at activation, so
  moving a Grab store to another machine breaks it without any warning. Prod: 4-C7CCRTEJJYUJR6 is bound to
  2401, but Grab still sends 2114. Result: 175 failed menu syncs a week and 15 order calls with **0 saved**.
  That store has effectively been off Grab.
- **A5. The `order_qty` reservation counter is corrupt.**
  - `dispenseOrder` decrements it three times: once per item in the loop, again in
    `syncVendChannelOrderQtyByDeliveryOrder`, and once more in that function's stray copy after its loop,
    which reuses the last loop variable.
  - A cancel releases the item qty from **every** channel of the machine (`syncProductMappingVendChannelOrderQtyByOrder`).
  - The clamp at 0 hides all of this. Prod: 62 units sit "reserved" on 39 channels while 0 orders are open,
    so those SKUs show less stock on Grab than the machine holds.
- **A6. A slot is checked only after the order is accepted.** When no channel can serve it, we try to cancel,
  but Grab allows a cancel only after a driver is allocated. The cancel fails quietly and the order hangs.
  The fix is accurate stock (maxStock), so Grab never sells what we do not have.
- **A7. Delivered but never dispensed: 24 orders in 90 days** (status 9, no CONFIRM, the driver never keyed
  the order in). Nothing lists them for anyone.
- **A8. Money is handled as floats.** Accessors divide by 100 (`DeliveryProductMappingItem` always by 100,
  `…VendChannel` by the currency exponent, so the two disagree for a 0-exponent currency). The menu price is
  `round($amount * 100)`, and the dispense amount is `subtotal - promo` in dollars. `total_amount` is 0 on
  945 of 950 orders (Mart sends `eaterPayment` 0), so that column is useless. This breaks the estate rule
  "integer cents everywhere".
- **A9. Ended mapping rows could still take orders** (`createOrder` / `getMenu` filtered `is_active` only,
  never `end_date`). Historic: the 63 such orders are all from 2024 (rows 89 / 93); today only row 17 (2003,
  ended 2025-03-05) is ended-but-active, with no order since.

### B. Security

- **B1. Grab webhooks have no authentication.** `delivery.authprobe` only LOGS the header. Anyone can POST a
  fake order to `/api/delivery/grab/order/create` or read any store's menu. Over 83 days the probe shows
  every real Grab call carries a valid token, so `auth:api` can be enforced without breaking anything.
- **B2. Internal helpers are public.** `GET /api/delivery/grab/oauth/{id}` refreshes any operator's Grab token.
  `/categories/{operatorId}/{type}` does no route-model binding (the parameter name does not match), so it
  works on an empty model.
- **B3. The keypad order lookup is unsigned.** `POST /api/delivery/order/search` dispenses an unverified
  order from just a short order no. + machine code, and every repeat before CONFIRM dispatches another
  dispense. The `device.signed` middleware already exists. Still to check: whether the APK de-duplicates a
  repeated fid.

### C. Menu sync

- **C1. A full menu sync runs on every VMC CHANNEL frame.** `SyncVendChannels.php:217` →
  `syncVendChannels(null, $vend->id)` → `notifyUpdatedMenu`. That is ~750 full syncs a day over ~70 stores
  (~11 per store per day). Grab's guidance is a full sync off-peak about once a day, with stock, price and
  availability sent by Update Menu Record / Batch Update (real time, 200 items a call).
  - We also skirt the 120 s identical-request lock.
  - The partial-update code (`updateMenu`) exists but **is never called**. If it were, it would be wrong: it
    sends `product.code` as the item id while the menu publishes the mapping-item id, and it sends the price
    in dollars.
- **C2. The notify races the sync.** `NotifyDeliveryPlatformUpdateMenu::dispatchSync` fires before the queued
  `SyncDeliveryProductMappingVendChannels` job runs, so Grab can pull the old menu.
- **C3. Paused and ended stores are still notified, and the menu endpoint answers them with text.**
  `getMenu` → `firstOrFail` → the controller returns `$e->getMessage()` as `text/html` 200. That is 774
  FAILED syncs in 7 days across 8 stores ("unknown content-type header text/html"). The daily
  `grab:sync-all-menu` also notifies all 127 rows, including ended ones.
- **C4. Grab item ids are unstable.** The item id is `delivery_product_mapping_items.id`, so each new template
  version gives every item a new id. That loses Grab's item history, invalidates campaign `objectIDs`, and
  makes an order placed on the old menu fail with "No items found" (500).
- **C5. There is one Grab item per CHANNEL, not per SKU.** The same product on two channels appears twice on
  Grab, each listing with half the stock.
- **C6. Prices and categories are typed per template channel.** The Grab price is hand-kept per template and
  is not linked to the Site's RP tier. The Mart sub-category is chosen per channel. So each new vending
  mapping version needs a hand-made Grab twin, and that is most of the ops pain.
- **C7. Selling time is hard-coded** (`ST1004`, 24/7), and its `startTime` is "today", so the menu changes
  every day. Grab has answered "version should be consistent".
- **C8. `delivery_platform_menu_records` is never pruned** (2.8 M rows). Grab sends each callback at least
  once and asks us to de-duplicate on `requestID`; we key on `jobID`.

### D. Campaigns

- **D1. Nothing has run since 2026-08-31.** The process is per template, then a manual "Submit" per campaign,
  which fans out to 1,633 per-machine rows. Ops have stopped using it.
- **D2. Deleting in mark1 leaves the campaign running on Grab.** `deleteItem` and `destroy` delete local rows
  only. Only "delete item-vend" and "unbind" terminate on Grab.
- **D3. 1,189 of 1,633 campaign rows point at mapping-vend rows that no longer exist.**
- **D4. Changes do not follow.** A machine bound later gets nothing until someone re-submits. An edit creates
  NEW rows (`updateOrCreate` keyed on `settings_json`) while the old submitted rows stay live.
- **D5. Grab's rules are not checked before submit.** Grab requires:
  - a start in the future and a duration of 2 h to 2 months;
  - no item in two overlapping campaigns;
  - for an order-level campaign, a minimum basket and a discount ≤ 0.5 × that basket;
  - edits only while the campaign is upcoming and more than 1 h before its start.
  Every one of these fails at Grab instead.
- **D6. Campaigns are never reconciled with Grab.** `GET /partner/v1/campaigns` (ongoing / upcoming) is never
  read, so campaigns made in the Grab merchant app are invisible.
- **D7. The merchantID is read inconsistently.** Campaigns read `platform_ref_id`, while menu and pause read
  the ref number first.
- **D8. Bundles (`delivery_product_mapping_bulks`) have 0 rows.** It is a dead feature.

### E. Code health (`app/Models/DeliveryPlatforms/Grab.php`)

- `batchUpdateMenu` is a placeholder (hard-coded Google logo, undefined `$this->merchantId`).
- `listOrders` POSTs to `order/prepare`.
- `editOrder` uses a path that does not exist.
- `updateDeliveryState` reads an undefined `$state`.
- `getPartnerEndpoint` has an undefined variable on an unknown scope.
- `getCategories` recurses on a 401 without returning.
- Many throws can never be reached.
- `Gojek.php` is empty.
- Tests cover only store pause.

## 3. Target design

Seven rules. Each has one writer, as the rest of mark1 already does.

1. **The Grab store is the entity, and Grab's `merchantID` is its key.** Extend `delivery_platform_ref_numbers`
   (its `ref_number` already IS the merchantID) into the store record with these fields:
   - operator credential;
   - the `partner_merchant_id` Grab registered, kept as Grab has it;
   - a dated machine binding: start/end, one open row, moved by closing a row and opening a new one, the way
     card terminals are moved;
   - state: Live / Paused-until / Closed;
   - Grab's integration status.

   Inbound calls resolve by merchantID first, then by partnerMerchantID. Moving a store to another machine
   becomes a rebind, and Grab notices nothing.
2. **One Grab item per SKU, with a stable id.**
   - Item id = `products.id` (unique; `products.code` is not unique across operators).
   - Mart category / sub-category and photo are set once per product (Grab catalogue page).
   - A store lists the products of its machine's ProductMapping that are on the operator's Grab list, minus
     per-store exclusions.
   - The separate "Grab template" (`delivery_product_mappings`) goes away.
   - Price = the Site RP tier + operator Grab markup %, rounded up to the step, or a per-product Grab override.
     Integer cents.
3. **Stock is per SKU and absolute.** `maxStock` = the sum of the SKU's active, not-faulted channel qty − a
   safety buffer − units held by open orders. The held units are computed from open orders, never a mutable
   counter. Grab decrements maxStock itself per order. We re-send it only when it changes.
4. **Menu publishing is diffed.** `GrabMenuPublisher` keeps `grab_store_items` (the last pushed
   price/stock/status per store × SKU):
   - A stock, price or availability change → Batch Update Menu, changed items only, debounced per store,
     ≤ 200 items a call, 1 rps.
   - A structural change (item added or removed, name, photo, category) → one menu notification, plus a
     daily off-peak full sync.
   - The callback is de-duplicated on `requestID` and kept 30 days.
5. **Store state is a schedule, not a button.**
   - Paused = re-asserted on Grab every hour until resumed, and checked against
     `GET /merchants/{id}/store/status`.
   - Closed = all items UNAVAILABLE plus a pause.
   - Optional auto-pause when the machine is offline > N min or nothing is in stock.
   - The page shows Grab's real state, not only ours.
6. **Orders are idempotent and asynchronous.** The webhook stores the raw body and answers 200 within ms:
   - a repeated orderID is an update;
   - an `isMexEditOrder` replaces the items.

   A queued job then:
   - resolves items by product id (old mapping-item ids still accepted during migration);
   - allocates channels (most stock first, splitting across facings);
   - marks the order ready.

   Grab's state and our dispense state get two separate columns. That replaces the numeric "status >"
   comparison, which mixes Grab's states with ours. A nightly List Orders (30 days) recovers missed orders.
   A "Delivered, not dispensed" list goes to ops.
7. **Campaigns are defined once per operator.** A promotion has a type, value, SKUs, schedule and targets:
   all Grab stores of the operator, a group, or picked stores. This is the same idea as Happy Hour machine
   groups. `GrabCampaignPlanner` is the only writer. It:
   - validates Grab's rules before submit;
   - materialises one Grab campaign per store, and gives a store that joins later the promotion
     automatically;
   - edits by terminating and recreating once a campaign has started (Grab cannot update a started one);
   - never blind-retries a create: it checks List first;
   - reconciles nightly against Grab's ongoing/upcoming list (drift shown; merchant-app campaigns read-only;
     orphans terminated);
   - reports orders and discount from each order's `campaigns[]` (`deductedAmount`, `mexFundedRatio`).

**Cross-cutting:**
- One `GrabClient` (scope → Food/Mart flavour; token cached for its 7 days; one refresh on a 401; rate-aware;
  only the endpoints we use).
- `auth:api` on every Grab webhook. The oauth/categories helpers move behind web auth. The keypad lookup goes
  behind `device.signed`.
- A Grab section in the 08:30 health email: refused orders, failed syncs, stores whose pause lapsed,
  delivered-not-dispensed orders, campaign drift.

### What ops get

- **Grab Stores**: store · machine · site · our state + Grab's state · last menu sync · items available ·
  orders today. Actions: Pause 30m / 1h / 24h / until resumed, Close, Resume, Move to machine, Resync.
- **Grab Catalogue** (per operator): listed?, Grab price (rule or override), Mart category, photo check
  (1:1, ≤ 2 MB), bulk edit.
- **Grab Promotions**: one form, target stores, status per store, drift.
- **Grab Orders**: today's page plus problem filters (refused, stuck, delivered-not-dispensed).

What goes away: templates per mapping version, per-channel toggles, Submit buttons, and the item × machine
campaign fan-out.

## 4. Migration: keep the live flow working at every step

Every phase ships on its own, behind a `config('grab.*')` flag, with the old path kept until the phase has run
7 days clean. Order: sandbox first (TEST dpo 18 with 4 machines, bench 2031), then KMY / ARK (1 and 3
stores), then HIPL.

**Phase 0: safety net (no behaviour change)**
- Contract fixtures from prod: about 20 real `last_menu_json` and `order_json` bodies (receiver data removed).
  `Http::fake` tests take an order through create → channels → dispense frame → CONFIRM → TRADE link →
  state webhooks, and check today's menu output for the same stores.
- `grab:health` command: the baseline from the queries above. It covers orders per day, % dispensed, refused
  calls per store (probe vs saved), failed syncs, delivered-not-dispensed and stuck `order_qty`. It is
  re-run after every phase.
- `grab:menu-diff`: builds the NEW menu per store and diffs it against `last_menu_json` (price, availability,
  stock, names). It reads only.

**Phase 1: stop the bleeding (small fixes on the current code)**
1. Idempotent order webhook (A1), Grab calls moved to the queue (A2).
2. Resolve by merchantID first (A4). This brings 4-C7CCRTEJJYUJR6 / 2401 back.
3. Schedule pause re-assertion hourly. Do not notify paused or ended stores. `getMenu` answers JSON, never
   text (A3, C3). Filter on `end_date` (A9).
4. Fix the `order_qty` triple decrement and the cancel release, then zero the 62 stuck units (A5). The reset
   needs Brian's go-ahead.
5. `auth:api` on the webhooks; move the oauth/categories routes (B1, B2).
6. Debounce the menu notify per store and send it only when the menu hash (without `startTime`) changed (C1, C2, C7).
7. Campaign delete/destroy terminate on Grab (D2). Prune menu records (C8).

Checked by: `grab:health` before vs after over 3 days, plus a sandbox order end to end on 2031.

**Phase 2: store record + stock by batch update.** The store table and binding history come from today's
rows. `grab_store_items` + `GrabMenuPublisher` run in **shadow** for a week (batches logged, not sent),
compared with what Grab pulled. Then they go live per operator.

**Phase 3: catalogue by SKU, stable ids.** This is the riskiest step, because a new id invalidates campaigns
and in-flight orders. The plan:
- Seed Grab prices as overrides equal to today's template prices, so day 1 changes no price.
- Order resolution accepts both id forms.
- Switch store by store, off-peak, with no open order and no live campaign, using one full sync.
- `grab:menu-diff` must show only id changes first.

**Phase 4: promotions.** Nothing is live, so nothing needs migrating. The old campaign tables become
read-only history.

**Phase 5: cleanup.**
- Remove the Grab template UI, bulks, the dead `Grab.php` methods and `Gojek.php`.
- Keep the old tables for history.
- Write the durable rules into `mark1/CLAUDE.md` and remove the stale ones.

## 5. Decisions for Brian

1. **Grab price**: RP tier + markup % with per-product overrides (recommended), or a hand-kept Grab price list?
2. **Item id = `products.id`** (recommended; it is stable and unique). The barcode goes in Mart's `barcode` field.
3. **GrabFood**: no credential uses it today. Build Mart only, with Food as a flavour flag for later?
4. **Safety buffer** default (today 0 everywhere) and whether to **auto-pause** an offline machine (after how long).
5. **Onboarding**: Grab made self-serve activation mandatory for middleware POS partners from Aug 2025. Do we
   fall under that, and should new stores go through `POST /partner/v1/self-serve/activation` +
   `pushIntegrationStatus`?
6. **The 24 delivered-not-dispensed orders**: who reviews them, and is there a refund rule?

## 6. Phase 0 + 1 as built (DEPLOYED 2026-10-29 09:43, `0cdc338af7`; pause hotfix after it)

| Finding | Change | Switch |
|---|---|---|
| A1 | Order submit idempotent on orderID (cache lock per orderID); a repeat answers 200; a Grab edit (`isMexEditOrder`) is kept in `request_history_json` + `is_edited` for a person, not re-allocated | — |
| A2 | Mark-ready / cancel moved to `ProcessDeliveryPlatformOrder` (queue `high`, 3 tries) | — |
| A4, A9 | `DeliveryPlatformService::resolveMappingVend()`: merchantID first (ref number or `platform_ref_id`), partnerMerchantID only breaks a tie; open (`end_date` NULL) selling rows only | — |
| A3 | `delivery:sync-store-pause --apply --only-if-open` every 10 min: asks Grab `store/status`, re-pauses only a store Grab reopened. After the first prod run (11 DCVIC stores answered 403, the burst drew 429s): calls spaced 300 ms, a 429 stops the run, a 403 store is skipped for 24 h | `GRAB_PAUSE_REASSERT` (default on), `GRAB_STORE_API_SPACING_MS` |
| A5 | Held stock read from open orders (`heldQtyByChannel`), never the `order_qty` counter; dispense / cancel double-counting and the stray block (crashed on an order with no channel) removed. The 62 stuck units need no reset: they are no longer read | — |
| C1, C2 | Menu notify debounced per store (`NotifyDeliveryPlatformUpdateMenu::debounced`, unique, waits `GRAB_MENU_NOTIFY_DELAY_SECONDS` 180) and skipped when Grab already has that menu (`menuChangedSinceLastPull`, keys sorted, `startTime` ignored); the daily `grab:sync-all-menu` still forces | — |
| C3 | Paused / ended stores never notified; a refused menu or order is JSON 404, an unexpected menu failure JSON 500 (no text/html) | — |
| C-price | Menu price = stored cents (int), not `round(dollars * 100)` (float) | — |
| — | A product with no photo no longer fails the whole store's menu (`photos: []`) | — |
| B1 | `VerifyGrabWebhook` (`grab.webhook`) on the four Grab routes: Passport client-credentials token of client 3 / 4 | `GRAB_WEBHOOK_AUTH` = `log` (default) → `enforce` after a clean week of logs |
| B2 | Public `/api/delivery/grab/oauth/{id}` and `/categories/…` removed (0 calls since 2026-08-07) | — |
| D2, D7 | Deleting a campaign or campaign item ends its live Grab campaigns first (`terminateOnPlatform`); campaigns read the merchant id like menu / pause | — |
| C8 | `grab:prune-menu-records` (by id, chunked) | `GRAB_MENU_RECORDS_KEEP_DAYS` (unset = not scheduled; first run deletes ~2.7M rows) |
| Phase 0 | `grab:health [--days]` (baseline report) and `grab:menu-diff [--details]` (menu now vs Grab's last pull); tests from a real prod order (`tests/Support/Grab`) | — |
| — | Index `oivc_order_id_idx` on `order_item_vend_channels.delivery_platform_order_id` (held-stock join) | migration |

Not done in Phase 1: B3 (keypad order search unsigned — needs the APK to sign), full Grab-edit handling,
`getGrabMenuSellingTimes` still moves `startTime` daily (ignored by the change check instead).

After deploy, on prod: `php artisan grab:menu-diff` (expect only "same" / "stock only"),
`php artisan grab:health --days=1` daily for a week against the 2026-10-29 baseline, and
`grep "Grab webhook auth would refuse" storage/logs/laravel.log` before switching auth to enforce.

