# Setting/Edit field audit for Smart Chillers (CityBox) — 2026-09-02

> Scope: `resources/js/Pages/Setting/Edit.vue` (the VM Edit page, `/settings/vend/{id}/update`)
> viewed for a `machine_type = smart_chiller` vend. Sample: vend 1363.
>
> The page was built for vending machines and, apart from two blocks, renders
> every field for every machine type. Today exactly **two** things are
> chiller-aware: the Citybox Equipment ID field (line 685) and the CityBox ops
> button row (line 1371). Everything else on the page is vending-machine
> hardware, APK plumbing, or VMC telemetry that a CityBox chiller does not have.
>
> Counted: **38 fields + 3 whole sections**. Verdict — keep 11, hide 21,
> make read-only 2, plus remove 2 sections and 5 of the 7 Advance Control
> buttons.

## Why so much is N/A

A CityBox chiller is not our hardware and runs none of our software:

- **no APK, no VMC** — so version, config chart, restart, log upload, APK
  parameters and menu/screen hardware are all meaningless;
- **their connectivity** — `is_online` comes from CityBox's heartbeat
  (`box_list`), not from our modem/simcard;
- **their app collects payment** (mode A) — no card terminal, no payment-method
  flags of ours;
- **their portal owns the planogram** — mark1's ProductMapping is a mirror that
  `ChillerPlanogram` writes and re-points automatically;
- **no unbind/rebind** for China projects (root `CLAUDE.md`) — a chiller Site/Vend
  is simply active or inactive.

---

## A. Section "Vending Machine" (line 23) — field by field

| # | Field | Line | Column | Verdict | Why |
|---|---|---|---|---|---|
| 1 | Machine ID# | 60 | `code` | **Keep** | CB-prefixed code, allocated at provisioning. |
| 2 | Machine Type | 109 | `machine_type` | **Keep** (already read-only) | Shows "Smart Chiller". |
| 3 | Is Factory? | 143 | `is_testing` | **Keep** | Machine-agnostic test flag; #1 is effectively the test unit. |
| 4 | Is Active? (Vending Machine) | 162 | `is_active` | **Keep — relabel** | The only lifecycle a chiller has. Drop the "(Vending Machine)" suffix for chillers. |
| 5 | Status | 181 | `status` | **Keep** | |
| 6 | Model | 201 | `vend_model_id` | **Read-only** | Set from `box_list.type` at provisioning (CityBox F5 / C5) and re-derived by `DeviceSyncService`. A hand-edit is either overwritten or silently wrong. |
| 7 | Machine Sticker | 224 | `vend_sticker_id` | **Hide** | Our physical asset register. |
| 8 | Serial Number | 240 | `vend_serial_number_id` | **Hide** | This is *our* VM serial asset. The chiller's serial is `citybox_equipment_id`, a separate field lower on the same page — two "serial" fields on one chiller page is actively misleading. |
| 9 | Machine Key | 269 | `vend_key_id` | **Hide** | Physical key asset; CityBox holds the cabinet. |
| 10 | Operator | 289 | `operator_id` | **Keep** | CB — drives settlement and scoping. |
| 11 | Contract | 317 | `vend_contract_id` | **Keep** (your call) | Commercial, not hardware. Harmless to leave; remove if chillers never carry a machine contract. |
| 12 | Setting Chart * | 338 | `vend_config_id` | **Hide** | APK config chart. Marked required in the UI but the server rule is already commented out (`VendController.php:5328`), so hiding it breaks nothing. |
| 13 | Current Version | 369 | `vend_config_version` | **Hide** | APK version. Renders "-". |
| 14 | Latest Version | 388 | — | **Hide** | Same. |
| 15 | Machine Prefix * | 399 | `vend_prefix_id` | **Hide control, keep value** | Already incidentally hidden (it renders only when `form.vend_config_id` is set). Provisioning *does* set `vend_prefix_id` and code allocation depends on it — make the hiding explicit, don't null it. |
| 16 | Simcard & Number | 431 | `sim_card_id` | **Hide** | Their connectivity, not ours. |
| 17 | Card Terminal | 452 | `card_terminal_id` | **Hide** | Mode A: their app collects. |
| 18 | Modem Model | 473 | `modem_type_id` | **Hide** | |
| 19 | Modem IMEI | 500 | `modem_unit_id` | **Hide** | |
| 20 | Menu Frame * | 528 | `menu_frame_id` | **Hide** | VM part. |
| 21 | Claw Machine Body | 551 | — | no change | Already gated on `vend_model_id == 5`. |
| 22 | Claw Machine Board | 573 | — | no change | Same. |
| 23 | LCD Monitor * | 595 | `lcd_monitor_id` | **Hide** | VM part. |
| 24 | LED Matrix Panel | 617 | `led_matrix_panel_id` | **Hide** | VM part. |
| 25 | Is Using Server Price? | 636 | `is_use_server_price` | **Hide** | Prices come from CityBox's portal via `ChillerPlanogram`; the APK-side pricing fork does not exist here. |
| 26 | Fan speed signal available? | 665 | `is_fan_speed_signal` | **Hide** | VMC telemetry. |
| 27 | Citybox Equipment ID | 686 | `citybox_equipment_id` | **Keep** | Already chiller-only. The one identity field that matters. |
| 28 | Product Mapping (current) * | 752 | `product_mapping_id` | **Read-only** | `ChillerPlanogram` creates the mapping and re-points the vend at it (`ChillerPlanogram.php:98`). Letting ops pick a different mapping breaks the mirror on the next poll. Show the name + link, drop the picker. |
| 29 | Product Mapping (upcoming) | 784 | `upcoming_product_mapping_id` | **Hide** | Scheduled mapping swap is an APK-push concept. |

\* = currently marked required in the UI.

## B. Section "Site" (line 956)

| Item | Line | Verdict | Why |
|---|---|---|---|
| Site picker / Select Existing / Create New | 990–1040 | **Keep** | A chiller must be bound to a site. |
| **Unbind Machine & Site** | 1107 | **Hide** | Root `CLAUDE.md`: no unbind/rebind for China projects. |
| **Unbind Machine & Deactivate Site** | 1118 | **Hide** | Same. |
| Site Binding History | 1135 | **Keep** | Harmless; will hold the single provisioning row. |

## C. Section "APK Logs" (line 1185) — **remove wholesale**

The section plus its "trigger log upload" button (1200). There is no APK to
upload logs from; the table is permanently empty.

## D. Section "APK Parameter" (line 1260) — **remove wholesale**

All six flags read from APK-reported columns and render "Not Detected" forever
on a chiller: Grab Enabled (1266), Display Screen Available (1276), QR (1286),
Cash (1296), Credit Card (1306), HID Payment Method (1316).

## E. Section "Advance Control" (line 1332)

| Control | Line | Verdict |
|---|---|---|
| "Remote Restart Instructions" blue panel | 1338 | **Hide** — it describes card terminal / cash / digital screen faults we don't have. |
| **Open Door (Restock)** | 1381 | **Keep** |
| **Pull from CityBox** | 1389 | **Keep** |
| Restart VMC | 1401 | **Hide** — no VMC. |
| Restart APK | 1410 | **Hide** — no APK. |
| Push Products Info to Machine (`syncChannels`) | 1419 | **Hide** — the channel push targets our APK; CityBox's portal owns the planogram and mark1 only mirrors it. Pressing this on a chiller is wrong in the exact direction that matters (it implies mark1 is upstream). |
| Sync APK Settings + its timestamp | 1428, 1435 | **Hide** — no APK. |

Result: the Advance Control row for a chiller becomes exactly the two buttons
that already work — Open Door and Pull.

---

## Implementation note (important)

**Hide the controls; do not stop posting the values.** The update endpoint still
validates `product_mapping_id` as required unless the mapping is N/A
(`VendController.php:5327`), and `vend_prefix_id` / `vend_model_id` /
`product_mapping_id` are all set by provisioning and by `ChillerPlanogram`. If a
hidden field stops being submitted, saving a chiller will either 422 or null out
a column the poller depends on.

The page already uses this pattern for Machine Type (see the comment at line
106: the field renders read-only but `form.machine_type` stays populated). Do
the same here — one `isChiller` computed, wrap the blocks in `v-if="!isChiller"`,
and leave `form` untouched.

Worth adding at the same time: a Vue-side guard test, and a note in
`mark1/CLAUDE.md` that Setting/Edit is machine-type-aware, so the next person
adding a field knows to gate it.

## Open questions for Brian

1. **Contract (#11)** — keep or hide for chillers?
2. **Hide vs. read-only** — my default above is to hide N/A fields outright. The
   alternative is to render them greyed with a "not applicable to Smart Chiller"
   note, which is noisier but teaches. Preference?
3. **Site Binding History** — keep the (single-row) table, or hide it along with
   the unbind buttons?

---

# Round 2 — Brian's four questions (2026-09-02)

## 1. Make the equipment ID a first-class object, sharing the serial-number class

**Recommendation: create a `citybox_devices` table/model, and keep
`vends.citybox_equipment_id` as the join key. Do NOT reuse `vend_serial_numbers`.**

Why not reuse it:

- `vend_serial_numbers` is an asset register of **our** hardware (`code`, `desc`,
  `hasOne(Vend)`), and it feeds pickers and filter lists on the vending-machine
  pages. Putting CityBox serials in it means a Serial Number dropdown on a
  *vending machine* would start offering CityBox devices.
- `vends.citybox_equipment_id` is `varchar(64) UNIQUE` and is the join key in
  `DeviceSyncService`, `StockPollService`, `DeviceProvisioningService`, the
  provisioning controller and the poller. Moving storage to an FK means a
  migration plus rewriting every lookup as a join, on a pipeline that currently
  works. Cost is real; benefit is only conceptual tidiness.

What the object model *should* buy us, and does:

Today the fleet exists only inside a 60-second cache
(`DeviceProvisioningService::UNLINKED_CACHE_KEY`). Devices CityBox knows about
but mark1 hasn't linked are not rows anywhere — which is why the Create-page
dropdown needs a live API call, and why the portal cross-reference had to be
gathered by hand. A `citybox_devices` table fixes that:

- one row per `box_list` device, **upserted on every poll** (create on first
  sight — exactly the "detect a new equipment ID → create one" rule);
- columns: `equipment_id` (unique), `name`, `type`, `ops_status`, `online`,
  `heartbeat_last_offline`, `heartbeat_last_recovery`, `last_seen_at`,
  plus `client_name` / `location` / `address` when CityBox answers the
  2026-09-02 request;
- **no update, no delete** in the UI — writes only from the poller. A device
  that vanishes from `box_list` gets `last_seen_at` left behind, never a delete;
- `vends.citybox_equipment_id` keeps pointing at it by code.

That gives the read-only, API-owned entity you're describing, keeps the
CRUD ban honest (there is simply no write path), and makes the create-page
dropdown work without a live API round-trip.

## 2. Pull Status from the API instead of setting it by hand

**Partly. Their status can't drive mark1's Status field as-is.**

The two vocabularies don't line up. mark1's Status is a 5-way projection over
four booleans (`VendController.php:5194`): factory (`is_testing`), active,
inactive, disposed (`is_disposed`), sold (`is_sold`). CityBox's `box_list.status`
is a 4-way operating state, already parsed and stored:
`DeviceOpsStatus` — 0 禁运 banned / 1 启运 running / 98 撤机中 being removed /
99 已撤机 removed, kept in `vends.citybox_status_json.equipment_status`.

"Disposed" and "sold" are statements about **our** asset register and can never
come from them; "factory" is our test flag. So a straight mirror would either
lose those states or misreport them.

`DeviceSyncService::applyStatus` already fetches this every poll and deliberately
does **not** touch `is_active` — it logs a warning instead (a phase-2 decision:
"Never flips is_active on their 禁运/撤机 status — ops decision").

Recommended shape:

- **Show their state read-only** on the chiller page — "CityBox status: Running
  (启运), online, last sync …". The data is already on the vend; it just isn't
  rendered. This is the honest answer to "can we pull it": yes, and we already do.
- **Keep mark1's Status manual**, because it carries meanings their API has no
  opinion about.
- Optionally, one narrow automation: **99 已撤机 (removed) → set `is_active = 0`**,
  since a device CityBox has retired cannot be operational for us either. Anything
  looser (auto-deactivating on 禁运) risks a poll silently pulling a machine out of
  ops jobs. Your call — I'd take the 99-only rule, or none.

## 3. Product mapping — read-only, but definitely keep it

**Confirmed on both halves.**

*It is API-owned:* `ChillerPlanogram` overwrites the mirror on every sync and
re-points the vend at it (`ChillerPlanogram.php:98`); the class docblock says
outright "Nothing here is ever edited in mark1". So any edit an ops user makes to
mapping items is silently reverted on the next poll — worse than being blocked.

*It is needed for ops jobs:* the ops-job screens eager-load
`vend.productMapping.productMappingItemsNormalSequence.product`
(`OpsJobController.php:1426`) to build the driver's pick list, and item completion
reads `vend->productMapping->productMappingItems` (`:969`). Remove the mapping and
chiller ops jobs lose their line items.

So: keep the mapping, show it read-only on Setting/Edit — and two further
consequences worth fixing at the same time:

- **The ProductMapping edit page itself** should be read-only for a chiller
  mirror, not just the picker on this page. Otherwise the edit route is still open
  and the next poll quietly reverts the work.
- **`implement_new_mapping` must never be offered as a stock action for a
  chiller.** On completion it calls `syncChannelsByVend` + `syncChannelSlotListToVend`
  (`OpsJobController.php:793`), i.e. an APK channel-frame push, and there is
  currently **no machine-type gate** on it.

## 4. Validation on hidden/required fields — this is already broken today

**This is not a future risk; chillers cannot be saved from this page right now.**

`VendController.php:5314` requires, unconditionally:

```
'lcd_monitor_id' => 'required',
'menu_frame_id'  => 'required',
'vend_model_id'  => 'required',
'product_mapping_id' => $isNA ? 'nullable' : 'required',
'vend_prefix_id'     => $isNA ? 'nullable' : 'required',
```

`$isNA` is true only when the selected **Setting Chart** is the "N/A" config —
a chiller has no Setting Chart at all, so `$isNA` is false and the last two are
required too.

Prod state of the three chillers:

| vend | code | vend_config | lcd_monitor | menu_frame | vend_prefix | product_mapping |
|---|---|---|---|---|---|---|
| 1362 | 10001 | 94 = **"N/A"** | 99 | 99 | NULL | 624 |
| 1363 | 10002 | NULL | **NULL** | **NULL** | 153 | 644 |
| 1364 | 10003 | NULL | **NULL** | **NULL** | 153 | 645 |

Vend 1362 was hand-patched — Setting Chart set to "N/A" and LCD/Menu set to the
placeholder 99 — to get a Save through. 1363 and 1364 have no such workaround, so
**any Save on them 422s** on `lcd_monitor_id` and `menu_frame_id`. That matches
the known "field jumps back after Save" symptom: the whole save is rejected on a
field the user never touched.

Fix, server-side, before hiding anything client-side:

```php
$isChiller = $machineType === Vend::MACHINE_TYPE_SMART_CHILLER;

'lcd_monitor_id'     => $isChiller ? 'nullable' : 'required',
'menu_frame_id'      => $isChiller ? 'nullable' : 'required',
'vend_prefix_id'     => ($isNA || $isChiller) ? 'nullable' : 'required',
'product_mapping_id' => ($isNA || $isChiller) ? 'nullable' : 'required',
'vend_model_id'      => 'required',   // chillers always have one (CityBox F5/C5)
```

`product_mapping_id` stays populated in practice (ChillerPlanogram sets it), but
it should be `nullable` rather than `required` so a chiller saved before its first
poll isn't blocked.

Order of work matters: **relax the validation first, then hide the fields.**
Hiding first without relaxing leaves the same 422 with no visible field to blame —
the worst version of this bug.

---

# Implemented 2026-09-02

Everything above, in this order, with 139 CityBox tests + the full suite green.

| Area | Change | Where |
|---|---|---|
| Validation | `lcd_monitor_id` / `menu_frame_id` nullable for a chiller; `product_mapping_id` / `vend_prefix_id` nullable for a chiller (as for an N/A chart) | `VendController::update` |
| Status layer | `ChillerStatus` value object (their ops status / online / heartbeats / staleness) built from the row, no API call; `Vend::chillerStatus()`, `Vend::isSmartChiller()`; rendered as a read-only "CityBox status" card. mark1's own Status stays manual. `isRetired()` is the hook for a future 已撤机 ⇒ inactive rule — **not** wired. | `app/Services/Citybox/ChillerStatus.php`, `SettingController::edit` (`chillerStatus` prop), `Setting/Edit.vue` |
| Setting/Edit | one `isChiller` computed gates: 16 hardware/APK fields hidden, Model + Product Mapping (current) read-only, both Unbind buttons hidden, APK Logs + APK Parameter sections hidden, Remote-Restart panel + Restart VMC / Restart APK / Push Products / Sync APK Settings hidden. Section heading reads "Smart Chiller". Form values keep posting. | `Setting/Edit.vue` |
| Option lists | Every hardware option list is still loaded for a chiller (an earlier draft skipped them; reverted 2026-09-02 because Edit.vue resolves the vend's stored ids against those lists and posts them back — an empty list nulled hidden columns on save and crashed on `vend_config_version` for vend 1362, which still carries config chart 94) | `SettingController::edit` |
| Mirror guard | `ProductMapping::isCityboxMirror()` + `assertEditable()`; refused on update / createItem / deleteItem / updateItem / updateItemSequence / reorderBasket / delete / bindVends / toggleActivateDeactivate; `is_citybox_mirror` on the resource; Edit page shows a banner and hides Add / row-delete / Activate / Save | `ProductMapping.php`, `ProductMappingController`, `ProductMapping/Edit.vue` |
| Ops-job gate | item-level `implement_new_mapping` on a chiller → 422; job-level bulk leaves the chiller item with **no** stock action (the flag must not land on it — completion keys on `stock_action_type` to run the old-stock auto-return and the APK channel-frame push); menu entry hidden for chiller items | `OpsJobController`, `OpsJob/EditItem.vue` |
| Device registry | `citybox_devices` table + `CityboxDevice` model + `CityboxDeviceRegistry` (the only writer: one upsert per sweep, `in_fleet` cleared for devices missing from a complete non-empty listing (an empty listing is treated as a transient bad response, not a mass removal), rows never deleted, `client_name` / `location` / `address` columns ready for CityBox's answer). Poller records every sweep; Create-page picker and `device()` read the table, refreshing only when stale (>5 min) or on Refresh. Replaces the 60 s cache. | migration `2026_09_02_100000`, `app/Models/CityboxDevice.php`, `app/Services/Citybox/CityboxDeviceRegistry.php`, `DeviceSyncService`, `DeviceProvisioningService` |
| Machine index rows | On Vend/Index and Vend/CustomerIndex (Operation Dashboard) a chiller row collapses every column after "#" / "Machine ID" into one `SmartChillerRowSummary` cell: identity (code, site, CityBox name, model, serial), CityBox status (ops status, online/offline since, staleness), mirrored stock (qty/capacity, SKUs in stock, per-channel chips → chiller layer overview), last/upcoming job, Open Door + Pull. Vending/freezer rows are untouched. Both selects gained the three `vends.citybox_*` columns (plain columns, no join). | `resources/js/Components/SmartChillerRowSummary.vue`, `Vend/Index.vue`, `Vend/CustomerIndex.vue`, `VendController::index/indexCustomer`, `VendResource` |
| Machine parameters (API) | The minute poll now also reads `get_device_status_new` per linked chiller (skipped when box_list says offline → NOT_FOUND) and mirrors `device_state` / `device_state_at` plus a `poll` health summary (at, ok, error, duration, SKUs seen) onto `citybox_status_json`; `ChillerStatus` exposes them (`deviceState`, `lastPoll`, `lastOpsOpen`, `canOpenDoor()`). The index-row cell shows a "Machine (from CityBox)" block: session state pill, layers in use, per-SKU price with promo price struck through, last ops door-open, last poll health, last back-online; Open Door is also gated on their state being FREE. Setting/Edit card shows state + last poll. | `StockPollService`, `DeviceState::label()`, `ChillerStatus`, `SmartChillerRowSummary.vue`, `Setting/Edit.vue` |
| Tests | `tests/Unit/CityboxChillerStatusTest.php`, `tests/Feature/CityboxDeviceRegistryTest.php`, `tests/Feature/CityboxChillerGuardsTest.php` | |

**Deploy needs `php artisan migrate --force`** (new table) on top of the usual pull / build / config:clear / horizon:terminate. The first poll after deploy fills `citybox_devices`.

Left as decided: Contract stays visible for chillers; Site Binding History stays; N/A fields are hidden (not greyed).
