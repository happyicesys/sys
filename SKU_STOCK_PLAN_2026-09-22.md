# SKU-keyed stock for Smart Freezer and Smart Chiller — scan + plan (2026-09-22)

**Status: PLAN ONLY. Nothing implemented. Brian answered Phase 0 on
2026-09-22 (see §7); §4 keeps the original questions for the record. Two
items still open: §7.5 (freezer suffix codes need an APK change) and the
`capacity_override` column name.**

All file:line references are to mark1 HEAD `99b96c6364` (2026-09-21). The
working tree on Brian's Mac was 461 files behind HEAD when this was written —
**that was repaired on 2026-09-22**: the checkout had been left by two mixed
`git reset`s with HEAD at `main` but the files still at `96dbde6eb1`, and it has
since been restored to HEAD. The checkout is now trustworthy; read the
chiller/freezer files named here directly.

## 1. The decision

| | Vending machine (unchanged) | Smart Freezer + Smart Chiller (new) |
|---|---|---|
| Unit of stock identity | `(vend_id, channel code)` — a physical motor lane | `(vend_id, product_id)` — one SKU, one qty, one capacity |
| Where the channel code lives | Board-reported, immutable per slot | **Reference label only**: where the driver should put the SKU (101, 501, basket 2b). Free to move between mappings |
| Mapping changeover diff | Per code: "101 changed from A to B" | Per SKU set: added / removed. A SKU that moves 101 → 501 is **not a change** |
| Supplier / APK shape | CHANNEL frame per slot | CityBox `device_product` is per SKU (id, name, qty, price); freezer APK sells a SKU and reports the planogram code as `SId` |
| Where qty comes from | Board | Our ledger (freezer: topup in, sale out) / CityBox live qty (chiller) — both per SKU already |

Why: CityBox's API has no channel concept at all, and the freezer's baskets are
a placement hint, not a lane. Today mark1 fabricates per-code rows for both
(spreads one SKU's qty across "facings", then sums it back), and a mapping edit
that merely moves a SKU is booked as a full return + fresh pick, which is wrong.

## 2. Scan: where the channel code is the *identity* today

Live data 2026-09-22: 9 chillers (134 active rows), 5 freezers (28 rows, 20
mapped). **No smart-type SKU sits on more than one code, and there are zero
freezer or chiller rows in `vend_transactions` yet** (chiller sales exist only
as 78 `citybox_stock_movements` with no consumer). 31 `ops_job_item_channels`
rows belong to smart-type machines. So there is almost nothing to migrate.

### 2.1 Storage
- `vend_channels`: `code int NOT NULL`, unique `(vend_id, code)`
  (migration `2026_08_26_150000`). Upsert keyed on code in
  `app/Jobs/Vend/SyncVendChannels.php:60-131`; chiller "retire codes missing
  from the frame" at `:268-283`; stock events per channel row.
- `ops_job_item_channels.vend_channel_id NOT NULL`, `vend_channel_code int` —
  a snapshot per channel row, one per `vend->vendChannels` in
  `OpsJobController::createOpsJobItem` (`:2983`).
- `Vend::vendChannels()` (`app/Models/Vend.php:797`) hides `capacity = 0` rows
  except for freezers; chillers with an unmeasured `chiller_slot_qty` fall out.

### 2.2 Chiller: the per-code fabrication layer (all of it becomes redundant)
- `app/Services/Citybox/ChillerChannelMap.php` — `forMapping()` keys slots by
  code; `allocate()` spreads one SKU's live qty over its codes lowest-first;
  `sumBySku()` adds them back for `device_stock_submit`.
- `app/Services/Citybox/ChannelFrameAdapter.php` — builds a per-code frame from
  per-SKU stock lines.
- `app/Services/Citybox/RestockVisitService.php:140-240` — `pushCounts` builds
  `qtyByCode`, patches "sibling facings" from `vend_channels`, sums by SKU,
  then handles "a SKU that left the planogram → tell CityBox zero". Lines
  298-395 (`captureBefore/After`, `snapshotToChannels`, `writeVmcQty`) do the
  same allocate-by-code dance for the B/A `vend_channel_records`.
- `StockPollService::unrecognisableSlots` (`:297`) and
  `CityboxVendActionController` overview (`:100-160`, layers grouped by code
  prefix, off-planogram = SKU with no code).

### 2.3 Freezer
- `app/Services/Freezer/FreezerChannelSync.php:58` — qty is carried over by
  **existing row code** (`$existing->get($code)`), and a code that left the
  planogram is sent with capacity 0 and retired (`:73-77`). Moving a SKU from
  basket 11 to 21 therefore retires 11 (qty gone) and creates 21 at qty 0.
  This is the exact failure Brian described.
- `getVendMenu` (`VendController.php:3233`) serves mapping items to the APK
  with `channel_code`; the APK echoes it back as `SId` on TRADE
  (`apk/smart-freezer` `PlanogramSlot.kt`, `Cart.kt`). Fine as a label.

### 2.4 Mapping sync and changeover (shared by both)
- `app/Services/ProductMappingService.php::syncChannelsByVend` — assigns
  `product_id` to a row by code. Harmless, but under the new model it must
  never *create identity*; it only relabels.
- `OpsJobController::applyNewMappingToItem` (`:2683-2810`) — diff by
  `channel_code`: same code, different product ⇒ stage `is_upcoming_product`
  row + `picked_qty = -qty` on the old; code gone ⇒ clear off; chiller-only
  block creates an inactive `vend_channels` row for a brand-new code.
- `OpsJobController::enforceMappingSwapReturns` (`:2856`) — keyed by
  `vend_channel_code`; returns the old product's full qty. **A moved SKU is
  returned to warehouse and picked again from zero.**
- Completion (`:786-880`): chiller ⇒ `StockPollService::rebuildChannels`;
  freezer ⇒ `syncChannelsByVend` + MQTT nudge.
- `OpsJobItem::resolveUpcomingMapping` — unaffected (mapping-level).

### 2.5 Sales
- `app/Services/VendTransactionService.php:1137,1157,1177,1256` — the channel
  is resolved from the frame's `SId` (`VendChannel::where('code', SId)`).
  For SKU-keyed machines this must resolve by product (`goods_id`) instead,
  keeping `SId` only as `vend_channel_code`. `PreCreatedSaleFactory.php:133`
  already writes `vend_channel_id = 0` for unmapped sales.
- Chiller sales are not in `vend_transactions` at all yet
  (`CityboxStockMovement` is written by `StockPollService::diff` and read by
  nothing). Out of scope here, but the SKU model is what that ingest needs.

### 2.6 Already SKU-shaped (good news)
- `StockCheckController` groups eligible rows by `product_id` (`:128`).
- `CityboxProduct` links product ↔ CityBox SKU; `products.chiller_slot_qty`
  and `products.freezer_slot_qty` are per SKU.
- `SaveVendChannelsJson` totals are sums; indifferent to row identity.

### 2.7 UI that draws by code (display only — keep, relabel)
`resources/js/Pages/Vend/SmartChillerChannelOverview.vue`,
`SmartFreezerChannelOverview.vue`, `Components/SmartFreezerPlanogramGrid.vue`,
`ProductMapping/Edit.vue` + `SmartFreezerLayout.vue` + `Form.vue`,
`OpsJob/EditItem.vue` (14 refs), `Vend/CustomerIndex.vue` planogram cell.

## 3. Options

**A — one `vend_channels` row per SKU for smart-type vends (recommended).**
Identity = `(vend_id, product_id)`; `code` stays populated with the SKU's
reference position from the mapping (lowest code if listed twice) so every
existing consumer that reads `code` for display still works. Unique
`(vend_id, code)` remains satisfiable because a mapping already forbids two
SKUs on one code. Smallest blast radius: ops jobs, dashboards, sales,
stock-count, delivery mirror all keep joining `vend_channels`.

**B — new `vend_sku_stocks` table.** Cleanest model, but every consumer above
gains a second join path and `ops_job_item_channels.vend_channel_id NOT NULL`
has to be relaxed. Not worth it for 14 machines.

**C — keep per-code rows, make only the changeover/ledger logic SKU-keyed.**
Fixes the "moved SKU" bug but leaves `allocate/sumBySku` and the sibling logic
in place. Half a model; rejected.

Go with A. Add one helper, `Vend::isSkuStocked()` (freezer || chiller), and
branch on it — never on `machine_type` at call sites (same rule as
`isSmartChiller()` in CLAUDE.md).

## 4. Phase 0 — decisions Brian must make before code

1. **Capacity per SKU.** `chiller_slot_qty` / `freezer_slot_qty` × number of
   codes the SKU occupies in the mapping, or the par alone? (Recommend × codes:
   a SKU on 101 and 102 physically holds two facings.)
2. **May a mapping list one SKU on several codes?** Today chillers may
   (facings), freezers may not. Under A both become "labels"; recommend allow
   for both, collapse to one stock row.
3. **Rebind to a mapping that drops a SKU still holding qty.** Retire the row
   (freezer today) or keep it visible until an ops job returns it? Recommend
   keep it active with `code` unchanged and flag "not on planogram", so stock
   value and the return are not lost; retire only when qty reaches 0 or on
   the ops-job swap return.
4. **Freezer sale resolution.** Resolve by `goods_id` (product) and ignore
   `SId` mismatches? Recommend yes; store `SId` in `vend_channel_code`.
5. Chiller channel code range 101–599 stays as label validation? Recommend
   yes (ops still type it; the overview still groups by layer).

## 5. Phases (each independently deployable; mark1 push = deploy)

**Phase 1 — identity + sync (backend only, no UI change).**
- `Vend::isSkuStocked()`.
- New `App\Services\SkuStockSync` replacing `FreezerChannelSync` and the
  chiller `ChannelFrameAdapter` + `ChillerChannelMap::allocate`: input = the
  vend's mapping (SKU → codes) + per-SKU qty source (freezer: existing row by
  `product_id`; chiller: `device_product` line) + per-SKU price; output = ONE
  `channels[]` entry per SKU with `channel_code` = reference code,
  `product_id` carried in the frame.
- `SyncVendChannels`: when `isSkuStocked()`, upsert by `(vend_id, product_id)`
  and relabel `code`; retire rows whose **product** is absent (not whose code
  is). Stock events unchanged. Keep `isCodeInRange` as label validation.
- `ProductMappingService::syncChannelsByVend`: skip identity assignment for
  SKU-stocked vends (the sync above owns the rows).
- `ChillerChannelMap`: keep `forMapping()` (SKU → codes, capacity) and
  `sumBySku()` collapses to a pass-through; delete `allocate()`.
- Tests: `FreezerChannelSyncTest`, `CityboxChannelsTest`,
  `CityboxStockPollTest`, `VendChannelDuplicateGuardTest` (+ a new
  "SKU moved code keeps qty" test for both types).

**Phase 2 — changeover by SKU set.**
- `applyNewMappingToItem`: for SKU-stocked vends diff `product_id` sets.
  Removed ⇒ clear-off row (`picked_qty = -qty`); added ⇒ upcoming row at
  qty 0 hung on a new inactive `vend_channels` row; moved ⇒ update
  `vend_channel_code` label only. `enforceMappingSwapReturns`: key by
  `product_id`. Completion path unchanged (rebuild / nudge).
- `RestockVisitService::pushCounts`: `counts[cityboxId] = before + actual`
  per row; drop the sibling-facing patch and `qtyByCode`; the "SKU left the
  planogram ⇒ zero" block stays. `captureBefore/After`, `snapshotToChannels`,
  `writeVmcQty`: key by `product_id`.
- Tests: `CityboxRestockVisitTest`, `OpsJobItemMappingRemarksTest`, new
  changeover test "Coke 101 → 501 stages nothing".

**Phase 3 — sales.**
- `VendTransactionService::processMapping` + `:1256`: for SKU-stocked vends
  resolve `vend_channel_id` by `product_id` (`goods_id`) on that vend; keep
  `SId` as `vend_channel_code`. Add the per-sale qty decrement for freezers
  (the "qty ledger to build" item from 2026-09-16) keyed by product.
- Later, same seam: chiller `CityboxStockMovement` → `vend_transactions`.

**Phase 4 — UI relabel.**
Overviews keep grouping by layer/basket from `code`, but rows are SKUs;
`OpsJob/EditItem.vue` shows one row per SKU with "Ref 101" instead of a slot;
mapping editors unchanged except the freezer grid may show one SKU in two
baskets. `CityboxVendActionController` overview: layers from `code`, no
change to off-planogram.

**Phase 5 — data + docs.**
- Backfill: nothing to collapse today (0 multi-facing SKUs). One-off command
  to re-run `SkuStockSync` for the 14 machines and repoint the 31
  `ops_job_item_channels` rows if any row id changes (reuse the
  `VendChannelDuplicateResolver` repoint list).
- CLAUDE.md: rewrite the Smart Chiller planogram bullet and add a "SKU-stocked
  machines" rule beside the `vend_channels` invariants; root `CLAUDE.md`
  China section: "reuses `vend_channels`" stays true, add "one row per SKU".

## 6. Risks
- Relabelling `code` on an existing row can collide with the unique index if
  two SKUs swap codes in one edit — write the sync as "retire/relabel in id
  order inside one transaction", or relabel via a temporary negative code.
- `ops_job_item_channels` snapshots taken before Phase 1 carry the old
  per-code rows; the 31 rows are on jobs that should be completed first.
- `Vend::vendChannels()` `capacity > 0` filter still drops unmeasured chiller
  SKUs; extend the freezer exception to chillers in the same change.
- Anything that assumed "code ⇒ exactly one row per physical slot" for
  vending machines must stay untouched: gate every branch on
  `isSkuStocked()`, never on "not vending".

## 7. Brian's answers, 2026-09-22 (supersede §4 where they differ)

### 7.1 Capacity: product default, overridable per mapping item
- Default stays on Product → Edit: `products.freezer_slot_qty` and
  `products.chiller_slot_qty` (two sections, `ProductController.php:893-895`,
  `Product/Edit.vue:376-415`). Unchanged.
- **New:** ProductMapping → Edit gets two columns per row for smart-type
  mappings: *Default* (read-only, from the product) and *Reality* (editable
  override). Store as `product_mapping_items.capacity_override int NULL`
  (name TBC). Effective capacity = `override ?? product par`.
- Resolution order used everywhere (`SkuStockSync`, ops-job upcoming rows,
  overviews): mapping-item override → product par → 0 ("-" on dashboards).
- On a rebind or mapping change the row's capacity is **re-derived** from
  this rule, never carried from the previous row (Brian: "capacity follows
  back to the preset"). Qty is the SKU's: freezer ledger carried by
  `product_id`; chiller pulled from `device_product` per SKU.
- Adds to Phase 1 (column + resolution helper) and Phase 4 (two columns in
  `ProductMapping/Edit.vue`; `ProductMappingItemResource` gains
  `capacity_default` + `capacity_override`).

### 7.2 One SKU on several codes: "will never happen" — keep it possible
Decision: no DB constraint forbidding it, no UI affordance for it. Concerns
if it is ever used, all handled by the SKU model rather than by the label:
- Capacity: sum of the SKU's items' effective capacity (7.1), so two
  facings of par 5 read 10. Do NOT multiply par × codes blindly, because an
  override may differ per item.
- `vend_channels.code` holds the SKU's **first** code (lowest number, then
  suffix); the other labels are display-only from the mapping. Overviews
  that draw one cell per mapping item will show the SKU twice with the same
  qty — acceptable, note it in the cell tooltip.
- `assertUniqueChannelCode` (one product per code) stays; a per-SKU
  uniqueness check is NOT added.
- Chiller "sibling facing" logic in `RestockVisitService::pushCounts` is
  deleted regardless (one row per SKU makes it moot).

### 7.3 Suffix codes: `101A`, `101B`, `101C` share one number
Requirement: one channel number may carry several SKUs, distinguished by a
letter postfix; the mapping editor orders them number → suffix.

Format: `^(\d+)([A-Z])?$`; chiller number 101–599, freezer number 11–69 (the
basket/division pair). Plain `101` and `101A` on the same number in one
mapping: **disallow** (validation), so a number is either whole or split.
Existing data is all-numeric (0 non-numeric codes in `product_mapping_items`
today), so nothing to backfill.

Storage — `product_mapping_items.channel_code` is already `varchar(255)`,
nothing to change. `vend_channels.code` is `int NOT NULL` under the unique
`(vend_id, code)` index (`2026_08_26_150000`, 75,473 rows), and `101A`/`101B`
both cast to 101, so two SKU rows would collide. Plan:
- Add `vend_channels.suffix char(1) NULL` plus a **stored generated column**
  `suffix_key char(1) NOT NULL AS (COALESCE(suffix, ''))`, and swap the unique
  index to `(vend_id, code, suffix_key)`. A NULL-able column in a composite
  unique would let vending duplicates back in (NULLs compare distinct), which
  is exactly what the 2026-08-26 index exists to stop; the generated column
  keeps that guard bit-for-bit for vending rows (`suffix_key = ''`).
- `code` keeps the numeric part (101) so every layer/basket grouping,
  `isCodeInRange`, `ChillerPlanogram::layerOf` and the claw range 50–59
  exclusion keep working unchanged.
- One helper, `App\Support\ChannelCode` (`parse()`, `label()`, `sortKey()`),
  used by: `ProductMappingController` validation (`:836`, `:871`), the three
  order-by sites (`:248-250`, `:988-993`), `ProductMapping::productMappingItems`
  (`app/Models/ProductMapping.php:119`), `getVendMenu` sort
  (`VendController.php:3282`), `smartPlanogram` (`:3375`, already
  SORT_NATURAL), `SkuStockSync`, `ChillerChannelMap::forMapping`.
  SQL order = `CAST(channel_code AS UNSIGNED), channel_code` (MySQL casts the
  leading digits, so `101 < 101A < 101B < 102`).
- `ops_job_item_channels.vend_channel_code int` (519,434 rows) is NOT widened:
  the row already carries `vend_channel_id`, and `OpsJob/EditItem.vue` loads
  `channels.vendChannel`, so the label is read from the channel row
  (`code` + `suffix`). Same for `vend_transaction_items.vend_channel_code`.
- `SyncVendChannels` frames carry `channel_code` (int) + `suffix` for
  SKU-stocked vends; the upsert key is `(vend_id, product_id)` anyway (§5
  Phase 1), the label is what gets relabelled.

### 7.4 Chiller: identity, capacity and qty on rebind — confirmed
Matches §3 option A: the row is the SKU; on rebind capacity is re-derived
(7.1) and qty is re-pulled from CityBox per SKU; the code (with suffix) is
the placement hint, re-labelled from the new mapping. No APK, so suffix
codes can go live for chillers in Phase 1 with no external dependency.

### 7.5 Freezer: suffix codes need an APK change first (OPEN)
The freezer APK (`apk/smart-freezer`, `Mark1SaleUploader.kt:123-135`) sends
`goods_id` (product id) per unit on TRADE, so Phase 3 "resolve the sale by
product" works with the shipped APK. But:
- `SId` is `channelCode.toIntOrNull() ?: 0` — a suffixed code sends 0. Fine
  once mark1 resolves by `goods_id`; the label is lost on the sale row only.
- `MqttPaymentClient.slotIds()` (`:255-275`) **drops** any cart line whose
  code is not plain digits from the REQQR `slotIdList`, by design (it would
  rather under-report than mis-book). The QR pre-create in mark1
  (`PaymentGatewayService`, `PreCreatedSaleFactory`) would then see no slot
  and book the charge with no product.
So: freezer mappings must stay numeric until an APK release (v19+) sends the
product id list on REQQR (or sends the numeric part + suffix separately) and
mark1's pre-create resolves by product. Enforce this in
`assertValidChannelCode`: suffix allowed only when
`$mapping->isSmartChiller()` until the APK gate is lifted (config flag or a
freezer APK version floor via `vends.apk_version`, whichever the OTA work
already exposes). Add to Phase 3.
