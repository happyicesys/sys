# Grab revamp plan: store, catalogue, stock, campaigns (2026-10-29)

**Status (2026-10-29): Phase 0 + Phase 1 DEPLOYED** (`0cdc338af7`, 09:43) — see §6 for what shipped and what is
still a switch. **§7 (same day, ops feedback + prod check): Grab sells the wrong product on 58 of 64 stores after
mapping changes; the Grab menu must follow the machine mapping — Phase 3 brought forward. §7 pricing: two
modes per operator (RP × multiplier / own SKU price). §8: Grab promotions written once per operator, by
product/category, no template. §9: the whole Delivery Platform tab page by page (5 entries → 4) and the order
of work. Brian, 2026-10-29: plan first — nothing built yet.** First prod check: `grab:menu-diff` = every selling store "same" or "stock only". Phases 2–5 are plan only. Every number below was read from prod
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
7. **Campaigns are defined once per operator** (detailed in §8, which supersedes this rule's wording). A promotion has a type, value, SKUs, schedule and targets:
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

1. **Grab price**: ANSWERED 2026-10-29 — both, chosen per operator (§7 "Grab pricing"): RP × multiplier (HIPL)
   or the operator's own SKU price with the normal price as reference (Ariska, Kumoyo).
2. **Item id = `products.id`** (recommended; it is stable and unique). The barcode goes in Mart's `barcode` field.
3. **GrabFood**: no credential uses it today. Build Mart only, with Food as a flavour flag for later?
4. **Safety buffer** default (today 0 everywhere) and whether to **auto-pause** an offline machine (after how long).
5. **Onboarding**: Grab made self-serve activation mandatory for middleware POS partners from Aug 2025. Do we
   fall under that, and should new stores go through `POST /partner/v1/self-serve/activation` +
   `pushIntegrationStatus`?
6. **The 24 delivered-not-dispensed orders**: who reviews them, and is there a refund rule?
7–12. See §7 (2026-10-29 user feedback): stopgap for wrong-product items, unpriced products, G101 / A001,
   HIPL's multiplier and rounding, Grab's commission rate, Bulla (LEA).
13–15. See §8 (Grab promotions): deal types on day one, below-cost promotions, who may manage them.
16–19. See §9 (the tab): merge into Grab Stores, promotions defined here + mirrored, "dispense again", client rights.

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
| Store cross-check (afternoon) | **Inactive ref number = store off.** `DeliveryProductMappingVend::isSelling()` / `scopeSelling()`: binding open, on, and ref number not Inactive. Menu pull, orders, notify, daily sync and menu-diff use it; the pause re-assert also covers open rows whose ref is Inactive (keeps Grab from reopening them). Before: the status only filtered pickers, so GoWell 2870 kept selling on a [CLOSED] listing and 2871 (ref Inactive) relied on its paused binding | — |

Not done in Phase 1: B3 (keypad order search unsigned — needs the APK to sign), full Grab-edit handling,
`getGrabMenuSellingTimes` still moves `startTime` daily (ignored by the change check instead).

After deploy, on prod: `php artisan grab:menu-diff` (expect only "same" / "stock only"),
`php artisan grab:health --days=1` daily for a week against the 2026-10-29 baseline, and
`grep "Grab webhook auth would refuse" storage/logs/laravel.log` before switching auth to enforce.


## 7. User feedback (2026-10-29): the Grab mapping must follow the machine mapping

Source: Daniel's screen recording of the Grab Product Mapping page (Dropbox
`Engineering/Frozen VM/share video/ScreenRecorderProject103.mp4`, 93 s, Mandarin voice-over).

**What ops said**
- The Grab mapping and the machine mapping are kept apart, so every mapping change means pairing the Grab one
  again by hand. For a year our mappings never changed; now they change about **once a month, machine by machine**.
- Combine them: when a flavour is swapped out of a machine it must **disappear from that machine on Grab**,
  because the machine's real mapping no longer has it.
- Few flavours on Grab is probably **the biggest reason Grab sales dropped**.
- Someone reported items **selling at $0** because "product pricing was not updated". Not found in mark1: no
  production store's menu or order in the last 30 days has a price under $1. The only Grab prices under $1 (U-11
  $0.20, U-49 $0.30 …) are on the TEST template `UFI_19_V30_2405_Testing`, bound to sandbox machine 2005 (ref
  Inactive since 2026-10-29). We need the store and item to look further — it may be on Grab's side.

**What the data shows (prod, 2026-10-29)** — worse than a chore:
- A Grab template item is (channel code → product, price). The menu names the TEMPLATE's product and takes stock
  from whatever the machine holds at that channel (`getGrabMenuSubCategoriesItems`). Nothing checks that the two are
  the same product.
- On **58 of 64 selling stores** at least one Grab item now names a different product from the machine's channel:
  2873 15 of 16 channels (template `CFY_16_V01.0_2511` is built on mapping 429, the machine runs 695); 2733 12 of 16;
  the 38 stores on `UEB_F_2606_Pist_Rocket` 2–5 each (31 U-91 → U-16, 37 U-89 → U-90, 44 U-21a → U-16, 65 U-01 → U-93).
- So a Grab customer is charged the template's price for product A and the machine drops product B. Upper bound
  (the channel's product is today's, an order may predate the change): **19 of 54 orders in the last 7 days**
  (43 units), 79 of 244 in 30 days.
- 13 templates sit behind 18 different machine mappings. Of 960 SKU × store pairs a machine holds, Grab lists 713
  correctly; 247 are missing or wrong.

**Target (replaces the Phase 3 wording in §3 rule 2 / §4)**
1. **The menu is built from the machine's live channels**, never from a template's channel list: for each SKU the
   machine holds now (`vend_channels.product_id`, active), list it if the operator has a Grab price for it. So a
   mapping change — applied by the ops job as today — reaches Grab through the CHANNEL sync that already notifies
   it (debounced, diffed). Ops do nothing on the Grab side when they change a mapping.
2. **One Grab product list per operator**: product → Mart sub-category, listed yes/no, and — only for an operator
   on the SKU-price mode — its Grab price (integer cents). The price comes from the operator's pricing mode
   ("Grab pricing" below). It replaces the per-mapping templates. The page lists "on a Grab machine, not listed /
   no price" at the top so a new flavour is listed (and priced) the day it is mapped. Products on Grab machines with
   no template price today: HIPL BC-02, E-01, GW05, GW07, OK-01, OK-02, U-16, U-50, U-90, U-92, U-93; KMY KMY13.
3. **Grab item id = `products.id`** (decision 2), one item per SKU, stock summed over its channels, an order
   allocated to the channels holding that SKU (most stock first). A channel item id cannot follow the machine: the
   same id would quietly turn into another product, and a campaign on it with it.
4. Per-store exclusions stay possible (a SKU not to sell on Grab at one site), but the default is: everything the
   machine holds and the operator prices.

**Grab pricing: two modes, chosen per operator** (Brian, 2026-10-29)

Each operator picks one mode on its Grab settings. The mode decides the price of every SKU it lists; the product
list (sub-category, listed yes/no) is the same in both.

1. **RP × multiplier** — for HIPL, whose machines sit on different RP tiers. Grab price = the store's Site RP tier
   price for that SKU (`customers.selling_price_type` → `selling_prices`, the "Server Price") × the operator's
   multiplier, rounded UP to a step (like Happy Hour's rounding). The same SKU therefore costs more on Grab at an RP4
   site than at an RP2 one, as it does at the machine, and an RP change on Product → Edit reaches Grab with no
   Grab step. A SKU with no price on that store's tier is not listed there (shown on the page as "no RP on tier n").
   - Why it matters today: HIPL templates carry ONE Grab price per SKU whatever the tier. On the 708 correctly
     mapped HIPL channels, Grab is below the machine's own RP on 207, and on **every one of the 84 RP4 channels**
     (by 56¢ on average); at RP2 it is above by 10%+ on 244 of 495. Average Grab ÷ RP is ×1.024 (×0.71–×1.18).
   - The base is the Site's RP even where the machine sells at its board price: the two agree on 700 of 708
     channels, and the RP is the price ops keep.
2. **SKU price** — for clients (Ariska, Kumoyo) who set their own Grab price. The list has a Grab price per SKU, and
   beside it, as reference: the **normal price** (the RP tier their stores use — RP2 for both today; each tier in
   use if their stores differ), **% vs normal**, and **what they keep after Grab's commission** (30%). E.g. normal
   $2.50, Grab $2.92 → "+17%", keeps $2.04 = 82% of normal. Seeded from today's templates. Today Ariska is exactly ×1.10 of normal on all 48
   channels, Kumoyo ×1.16–×1.18 on 11 — so after 30% both keep 77–82% of their machine price.

Both modes show the same reference columns (normal price, Grab price, %, net after commission), so an operator on
RP × multiplier also sees what each store charges. Grab campaigns discount from the resulting Grab price.

**Order of work (changes §4)**
- **Now — stop wrong-product sales (one small deploy, on today's code):** a Grab item whose template product is not
  the product the machine holds at that channel is left out of the menu, and an order for it gets the 4xx. Menu
  items fall by the mismatched channels until step 2 lands, so Grab shows fewer flavours for a few weeks — but it
  stops charging for one product and dropping another. Brian decides (below).
- **Next — Phase 3 brought forward, before the store record and batch updates:** Grab Price List + menu from the
  machine + product-id items. Switch store by store as §4 Phase 3 says (old item ids accepted for orders in
  flight, no live campaign, one full sync), templates read-only, then removed in Phase 5.
- Phase 2 (store record, batch stock updates) follows; it does not change what ops see when a mapping changes.

**Decisions for Brian (added to §5)**
7. Ship the stopgap now (hide mismatched items), or leave Grab as it is until the Price List lands?
8. SKU-price mode, a product with no Grab price yet: hidden until priced (recommended), or normal price ×
   a default %?
9. G101 and A001 ($2.00 / $2.50, $2.40 / $2.50 in different HIPL templates): moot if HIPL takes RP × multiplier.
10. HIPL's multiplier and rounding step: ×1.00 keeps Grab at the machine price (today's average is ×1.024); a
    higher one recovers part of Grab's 30%. Round up to 10¢?
11. Grab's commission: 30% for every operator, or a rate per operator (clients may have their own Grab terms)?
12. Bulla (LEA, 1 store): its site has no RP tier, so only the SKU-price mode works for it — confirm.

## 8. Grab campaigns: one promotion, every store, no mapping (Brian, 2026-10-29: "most important is easy to use, even a layman can manage it")

**Today (prod, 2026-10-29)** — a campaign hangs off ONE Grab template and picks that template's channel items
(`objectIDs` = `delivery_product_mapping_items.id`). So the same deal is typed again per template: "Any 2 B&J pint
for 24.90" exists 9 times, "3 Magnum for $11.90" 15 times, "Any 5 Cornetto for $12.90" 13 times; 1,633 per-machine
rows in all. When a mapping changes, the channel item points at another product, the deal with it (§7) — and
nothing has run since 2026-08-31 (D1). What ops actually ran, by rows: bundles "any N for $X" (~65%), "free item
above $X" (~30%), delivery off above $X (~4%), % off a range.

**Target: a promotion is written once per operator, in one sentence, and mark1 does the rest.**

What the person sees — one page, Grab → Promotions, a list and a "New promotion" button:

1. **Pick what kind of deal** — five cards, each a sentence with blanks, nothing else:
   - **Any [2] of [these] for $[24.90]** (Grab `bundleDiffFixPrice`)
   - **[20]% off [these]** (`percentage`, items)
   - **$[1.00] off each of [these]** (`net`, items)
   - **Free [1 Magnum Almond] when the order is above $[25]** (`freeItem`)
   - **$[2] off delivery when the order is above $[35]** (`delivery`)
   The other Grab types (same-item bundles, bundle $ / % off, order-level % off, category scope) stay off the page
   until someone asks; the planner can still send them.
2. **"[these]" is a product picker, never a channel or a template**: tick whole **categories** ("Magnum Series",
   "Classic Cornetto" — `products.category_id`) and/or single products. A category stays live: a new Magnum mapped
   next month joins the deal by itself. Only products on the operator's Grab list can be picked.
3. **Where**: "All my Grab stores" (default, live — a store opened later gets it) or "Only these stores". The
   page then says, before saving, **"Runs on 38 of 55 stores"** and lists the 17 others with the plain reason:
   "doesn't carry any of these now", "deal is not cheaper here" (an "any 2 for $24.90" where two already cost less —
   with RP × multiplier the price differs per store, §7), "already in 'Free Magnum…' 1–30 Nov".
4. **When**: quick buttons — "This weekend", "1 week", "Until end of month", "1 month" — or dates. Grab allows
   2 h to 2 months per campaign, so a longer run is just **"Repeat until [date]"**: mark1 creates the next stretch
   before the last one ends. Start "Now" means the next allowed minute (Grab needs a future start).
5. **Check before save** — a box that reads like a receipt, per store or summed: normal price → deal price →
   **what we keep after Grab's commission** → unit cost. Red when a store would sell below cost (save still allowed,
   ticked "I know"). Every Grab rule is checked here (D5), in words: "Grab does not allow an item in two promotions
   at once — Magnum Almond is in 'Free Magnum…' until 30 Nov at 12 stores".
6. **Save = live.** No "Submit", no per-machine rows to look at. The list shows each promotion as **Scheduled /
   Running / Ended / Needs attention**, with "Running on 38 stores" and, under it, only the stores with a problem
   and why (Grab refused, store paused, …).
7. **Change it**: Edit any time. Before start, Grab is updated; once running (Grab cannot change a started one),
   the page says "This ends the running promotion and starts the new version now" and mark1 does both. **Stop**
   ends it everywhere on Grab at once (fixes D2). Delete is only for Ended / never-sent ones.
8. **Results** on each row: orders, units, sales, discount given, for the period — from each order's `campaigns[]`
   (`deductedAmount`). A promotion made in the Grab merchant app shows read-only, labelled "made in Grab app" (D6).

**How it works underneath** (replaces §3 rule 7's wording; same idea as Happy Hour machine groups):
- Tables: `grab_promotions` (operator, type, values in integer cents / %, schedule, repeat-until, store mode,
  status) + `grab_promotion_products` (category or product) + `grab_promotion_stores` (only for "only these") +
  `grab_promotion_runs` — one row per store per stretch, the only thing that maps to a Grab campaign id (request,
  response, state). `GrabPromotionPlanner` is the only writer of runs, like `HappyHourPlanner`.
- Item ids are `products.id` (§7 point 3), so a promotion never names a channel or template: for each store the
  planner sends the promotion's products that store lists now.
- The planner runs every few minutes and on every save: creates due runs (checks Grab's List first, never a
  blind retry), stops runs of stopped / edited promotions, starts the next stretch of a repeating one, adds a store
  that joined. When a store's product set changes (mapping change) mid-run, the store's run is replaced only if
  it would change what customers see (a product added or removed) — ended and recreated at once.
- Nightly reconcile against Grab's ongoing / upcoming list (D6): drift shown on the row, orphans stopped.
- The old campaign pages and tables go read-only at the switch (nothing has been live since 2026-08-31, so no
  live campaign has to be migrated) and are removed in Phase 5. **Promotions need product-id items, so they ship
  with Phase 3 (§7), not before.**

**Open / to verify in Grab's sandbox before building**
- Does Grab accept a campaign naming an item the store's menu does not list (so one product list could go to
  every store), or must each store get only its own items (the plan assumes the latter)?
- Who funds: our promotions are 100% merchant-funded (`mexFundedRatio` 100); Grab co-funded deals come from the
  Grab app and are read-only here — confirm.

**Decisions for Brian**
13. The five deal cards above — enough, or is another type needed on day one (e.g. "% off the whole order above
    $X")?
14. Below-cost promotions: warn and allow with a tick (recommended), or block?
15. Who may manage promotions: the operator's own admins too (Ariska, Kumoyo), or HIPL staff only?

## 9. The Delivery Platform tab, page by page (Brian, 2026-10-29: "plan first, don't execute")

**How the tab is used today** (prod; page views are logged since 2026-07-28, edits since 2026-07-01):

| Page | Views | Who | Edits since 07-01 |
|---|---|---|---|
| Grab Orders | 64 | Brian, Daniel, Iris, LiangBao, MSW, XO | — (only Cancel exists) |
| Grab Platform ID | 53 | 8 users incl. Ariska, Kumoyo | 1 created (10-19), 1 updated |
| Grab Product Mapping | 50 + 59 edit-page views | Daniel, Kumoyo, Ariska, XO | Kumoyo 38 updates + 26 item add/deletes on their own mapping; Daniel deleted 21 templates (10-28) |
| Machines | 39 | 9 users | 5 binding updates |
| Grab Campaign | 12 | Brian, Daniel, Kumoyo, MSW, XO | none — nothing live since 2026-08-31 |

- Grab orders ran ~430 a month Jan–Aug, then 283 in Sep and 239 in Oct. That fall starts the month after the last
  campaign ended (08-31). This is a correlation, not proof, but it agrees with ops: fewer flavours and no promotions.
- **5 of 83 open bindings** use a Grab template built on the machine's CURRENT mapping. The other 78 run on a
  mapping the machine has left (§7).
- **12 Grab accounts** (`delivery_platform_operators`, one per operator, sandbox or production) are created on the
  Operator pages by two different endpoints. Only one of them fetches the OAuth token
  (`OperatorController.php:385` vs `:691`).

**What is wrong, by page** (code read 2026-10-29):
- **Grab Platform ID and Machines are one thing split in two.** A Grab store is a merchantID on a machine. One page
  types the merchantID; the other shows the binding and holds Pause / Unbind.
  - Binding is done from a third page (Product Mapping → Bind VM).
  - The merchantID is stored twice: the ref number, plus a string copy in `platform_ref_id`. Renaming a ref does
    not update the copy, and the Machines page filters and counts by the copy.
  - "Active" means two things. The ref's own `status` decides selling. The page's Status filter means "has an open
    binding".
- **Grab Product Mapping is a second copy of the machine mapping.**
  - It is copied once at create, then matched to the machine by channel code only. That is the wrong-product bug in §7.
  - Bind / Pause / Unbind are duplicated here. Unbind does not pause on Grab, and Pause All never resumes.
  - Deleting a mapping hard-deletes its bindings and ends no Grab campaign.
  - Bundle sales are written but never read.
- **Grab Campaign keeps its own copy and pushes it; nothing reads Grab back.**
  - The campaign is per template, then per machine, then "Submit".
  - A failed Grab DELETE is still recorded as ended locally. So mark1 believes a campaign is gone while Grab keeps
    it, and the next create for that store is refused ("same item in two campaigns"). This is the error ops see when
    they switch machines or re-run a deal.
  - `GET /partner/v1/campaigns` is never called, and update (PUT) is never used.
  - The Edit page's Save button updates a PRODUCT MAPPING whose id happens to equal the campaign id
    (`DeliveryPlatformCampaign/Edit.vue:900`).
- **Grab Orders works, and is the most used page.**
  - Its "Save Order" button posts to a route that does not exist (`DeliveryPlatformOrder/Edit.vue:450`).
  - Its form Save posts to `/operators/{id}/update`.
  - Items show by channel. There is no view of what needs a person.

### Target: five entries become four, one place per fact

| Today | Target | Change |
|---|---|---|
| Grab Platform ID + Machines | **Grab Stores** | merged: one row per Grab store |
| Grab Product Mapping | **Grab Menu & Prices** | per operator, by product; no template, no channel |
| Grab Campaign | **Grab Promotions** | §8, with Grab as the truth for what is live |
| Grab Orders | **Grab Orders** | kept; broken buttons removed; a "Needs attention" view added |

Grab accounts stay on the Operator page, with one writer and a "Test connection" button.

#### 1. Grab Stores (merges Grab Platform ID + Machines)

- **One row = one Grab store.** Columns:
  - merchantID;
  - Machine (`MachineIdLink`) and Site;
  - Operator;
  - **Status** — one word plus its reason: Selling / Paused by ops until 18:00 / Paused: machine offline /
    Off (Inactive) / No machine;
  - **Grab says** — the live open / closed state and reason, from Get Store Status, polled;
  - **Menu** — items listed, last full sync and its result (Trace menu sync / sync-state webhook);
  - last order and orders in the last 30 days.
- The header shows totals: bound / selling / paused / off (today's `grab:health`).
- **Actions**, each one step:
  - **Add store** — type the merchantID and pick the machine. mark1 checks the merchantID against Grab with the
    operator's account before saving, so a typo is caught.
  - **Move to another machine** — closes the binding, opens the new one and syncs the menu. It replaces Unbind +
    Bind on two pages.
  - **Pause until… / Resume** — with a reason. Grab's 24 h limit is handled by the existing re-assert.
  - **Switch off** — the ref goes Inactive (today's rule), and Grab is paused at once.
  - No template to pick: the menu comes from the machine (§7).
- **Store detail** (a drawer):
  - "What Grab shows now" — product, Grab price, available qty — from the same builder as Grab's menu webhook;
  - per-store settings: safety buffer (reserved qty / %; on the template today) and "don't sell here" SKUs;
  - history: bindings, pauses, menu syncs.
- **Data:**
  - The merchantID is stored once: `platform_ref_id` is dropped after reads move to the ref; the two match on all
    open bindings today.
  - "Active" means only the ref's status.
  - Per-channel pause and both ChannelOverview copies go; a per-SKU exclusion replaces them.

#### 2. Grab Menu & Prices (replaces Grab Product Mapping)

- **One page per operator.** Clients (Kumoyo, Ariska) manage their own, as Kumoyo does today.
- **Header:** pricing mode (§7: RP × multiplier, or own SKU price), multiplier and rounding, Grab commission %.
- **Grid: one row per product** — every product on any of the operator's Grab machines, plus any listed before. Columns:
  - photo, product, category;
  - Grab sub-category (Grab's list, fetched live as Create does today);
  - **Listed** on/off;
  - normal price (each RP tier in use);
  - Grab price (computed, or typed in SKU-price mode);
  - % vs normal;
  - kept after commission;
  - unit cost;
  - "on n stores".
- A banner at the top: "3 products on your machines are not on Grab yet". Bulk actions: give a whole category one
  sub-category, list all.
- **Preview:** pick a store and see its menu exactly as Grab receives it.
- **Gone:** templates; items per channel; "Refer to Product Mapping"; Bind VM; Pause All; bundle sales (0 rows,
  never read).

#### 3. Grab Promotions (replaces Grab Campaign) — §8, plus one source of truth per question

The fix for "we keep our own data and push it, so we drift": split the truth by question.

- **Grab is the truth for what IS live.**
  - mark1 mirrors every store's ongoing and upcoming campaigns from `GET /partner/v1/campaigns` into a mirror table.
  - It refreshes every 10 minutes, and again right before and right after every write.
  - Pages and reports read the mirror; nothing assumes a campaign is gone because mark1 asked.
- **mark1 is the truth for what SHOULD be live** (the promotion, §8). The planner compares wanted with the mirror,
  store by store:
  - wanted but missing → create;
  - ours but no longer wanted → end;
  - made in the Grab merchant app or by Grab → shown read-only, never touched.
- **Every write starts from Grab's list for that store.**
  - An old campaign of ours that blocks an item is ended first, so switching machines or re-running a deal no
    longer hits "already in another campaign".
  - A campaign we did not make that blocks an item is named on the page (what, until when), with an "End it"
    button behind a confirmation.
- A DELETE that fails leaves the run "still live on Grab", with a retry. It is never marked ended until the mirror
  shows it gone.
- Grab refuses concurrent create/update for one merchant, so writes go through one queue lane per merchantID.
- **Why not pull-only** (make campaigns in the Grab merchant app, mark1 only shows them):
  - it cannot put one deal on 55 stores in one go;
  - it cannot follow a mapping change;
  - it cannot check the price against cost.
  - Campaigns made there still appear and count in results, so ops can still use the Grab app for a one-off.
- **Switch-over:** the first mirror run lists every live Grab campaign. Ours with no promotion behind them are
  shown as "left over — End?" (one button ends them all).

#### 4. Grab Orders (kept)

- **Remove:** "Save Order", which posts to a missing route, and the form Save, which posts to the operator update.
- **Show:**
  - the status as a timeline in words, from `status_json`;
  - items by PRODUCT, with each line's dispense result and the slot it dropped from;
  - the promotion discount from `campaigns[].deductedAmount`;
  - Grab's order id and short id.
- **"Needs attention" view** (a default filter chip):
  - delivered but not dispensed (decision 6);
  - accepted but not collected after 30 min;
  - cancelled by mark1 for no stock;
  - driver complaints.
- **Actions:** Cancel (exists). Nothing else without a decision (18).

#### 5. Automation: what nobody does by hand any more

- The menu follows the machine mapping; prices follow RP (§7).
- Promotions reach stores added later, renew past Grab's 2 months, and clear their own leftovers (§8, mirror).
- A store is kept paused while it is Off (done). Auto-pause on a machine that is offline: decision 4.
- **`grab:health` at 08:30** emails only what needs a person, like `card-settlement:health`:
  - store open on Grab but off here, or the reverse;
  - menu sync failed;
  - unpriced products on a Grab machine;
  - promotion drift or leftovers;
  - orders delivered but not dispensed.
  - `grab:menu-diff` runs nightly behind it. Both commands exist but are not scheduled today.

### Order of work (replaces the ordering in §4 / §7 where they differ)

1. **Grab Stores** — merge the two pages; merchantID stored once; Unbind pauses; Move. No change on Grab's side;
   low risk.
2. **Grab Menu & Prices + product-id items** (§7, Phase 3) — the risky one. Switched store by store, as §4 says.
3. **Grab Promotions with the mirror** (§8 + above) — needs step 2's item ids. The mirror alone (read-only, plus
   the leftover list) can ship first, before step 2.
4. **Grab Orders clean-up, "Needs attention", `grab:health`** — independent; can run in parallel with steps 1–3.
5. Phase 2 (batch stock updates), then Phase 5 (remove templates and the old campaign tables).
- The §7 stopgap (decision 7) stays optional before step 2.

**Decisions for Brian**
16. Merge Grab Platform ID + Machines into one **Grab Stores** page?
17. Promotions: mark1 defines them and mirrors Grab (recommended), or the Grab merchant app only, with mark1
    showing and reporting?
18. Grab Orders: add a "dispense again" for a paid order whose drop failed (driver at the machine; risk of a
    double drop), or keep Cancel only?
19. What clients (Ariska, Kumoyo, …) may change on their own stores: prices and listing (they do today), pause /
    resume, promotions?
