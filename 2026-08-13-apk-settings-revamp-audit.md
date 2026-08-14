# APK Settings — full-chain audit & revamp plan

> Date: 2026-08-13. Status: **investigation + proposal, nothing implemented.**
> Covers: mark1 `/apk-settings` + `/product-mappings`, big-board APK (`mark1-apk`,
> v301/302), small-board APK (`mark1-apk-small`, v134), and every other surface
> found to control APK behaviour. All file:line references verified today;
> production values verified via read-only DB access.

---

## 1. How it works today (verified end-to-end)

```
Web UI (/apk-settings Edit.vue, 1895 lines)
  └─ POST /apk-settings/{id}/update  → apk_settings.settings_parameter_json (1 JSON blob, 46 keys)
        └─ PushApkSettingSync (5s debounce)
              └─ MQTT  CM{vend_code}  {"Type":"TYPESYNCSETTINGSPARAM", ...}   ← QoS 0 doorbell, carries NO data
                    └─ APK: GET /api/vends/{code}/parameters/{apkver}          ← unauthenticated HTTP, the real payload
                          └─ writes SharedPreferences (only if "Sync setting from Server" = Yes)
                          └─ ack: MQTT {"Type":"JOBAPKSETTING","vend_job_id":N}
                                 └─ server matches vend_jobs row… but jobs are only created for vend 2007
```

Key facts:

- **Profiles, not per-machine.** One `apk_settings` row (33 in prod) binds N vends
  via `apk_setting_vend` (206 bindings; largest profile covers 84 machines).
  There is **no per-vend override layer**.
- The MQTT push is a content-free "go fetch" (`VendJobService.php:65-103`), QoS 0,
  fire-and-forget. The data channel is `GET /api/vends/{code}/parameters/{apkver?}`
  (`VendController.php:3357-3437`) — **no auth, no throttle**.
- Canonical parameter schema: `app/ValueObjects/ApkSettingParameters.php` `DEFAULTS`
  (46 keys; the "42 keys" comment is stale). `fromArray()` drops unknown keys,
  backfills defaults, keeps canonical order. Update path uses
  `mergeCampaignParameter` so absent form keys keep stored values
  (`VendParameterService.php:41-66` — deliberate, protects `selectedPricingSource`).
- The APK fetches settings **once per boot** plus on each MQTT doorbell
  (`Main2Activity.java:1630-1670`; counter slammed to 5 on first success).
  No periodic reconcile, no fetch on MQTT reconnect, no version/etag —
  **last writer wins silently** between local edits and server pushes.
- Reverse direction: on every MQTT connect and every local Save the APK publishes
  `FEATUREAPKSETTING` carrying exactly 6 flags (grab collection, 4 soft-keypad
  payment toggles, hasDisplayScreen) → `SyncFeatureApkSetting` writes them onto
  `vends.*` columns. These are **machine-authoritative**; the server merely mirrors
  and echoes `isGrabEnabled` back in `/parameters`.
- **Small board is a different animal.** `mark1-apk-small` never calls
  `/parameters` at all — slots/prices come from the VMC over serial; its only
  server bootstrap is one-shot `mqtt-config /api/validate`. Binding a small-board
  vend to an apk_settings profile does nothing. Its settings UI is a single
  dialog (no tabs) with a much smaller key set.

### Product mappings delivery (validated — the "/api/thumbnails tunnel")

- Web `/product-mappings` → `product_mappings` / `product_mapping_items`
  (`channel_code`, `product_id`, `selling_price_id`, `sequence`, `server_amount`
  stored in **cents**, ÷100 accessor).
- Delivered to APK via **two near-duplicate endpoints**:
  - `GET /api/vends/{code}/thumbnails` (`VendController.php:2858-2955`) — built
    from `vend_channels` ∩ `product_mapping_items` (what the machine reports ∩ planogram).
  - `GET /api/vends/{code}/menu` (`VendController.php:2961-3062`) — built from
    `product_mapping_items` directly (pure planogram; the smart-freezer path).
    Identical field list. Cache-busting `?v={timestamps}` on thumbnails **only
    when `is_smart`**.
- Per-slot image: `GET /api/vends/{code}/vend-channels/{slot}/thumbnail` → raw
  300×300 JPEG.
- Big-board APK consumes `/thumbnails`, caches whole list in pref
  `apiChannelSlotsGson`, merges onto VMC slot list by `channel_code`;
  `server_price` used only when `selectedPricingSource == "Server"`.
  Images cached 24h TTL; MQTT `TYPESYNCAPICHANNELSLOTLIST` forces refresh.
- Price change reaches a machine **only** via re-fetch (`TYPESYNCAPICHANNELSLOTLIST`
  doorbell or reboot). There is no dedicated price-push command.
- `server_price` resolves as `selling_prices.amount WHERE type = vends.server_price_type`
  — per-vend price tier selection lives on the vend row, not the profile.

### Every other control surface found (the "what else affects APK" audit)

| Surface | What it does | Where |
|---|---|---|
| MQTT `CM{code}` commands | `TYPESYNCSETTINGSPARAM`, `TYPESYNCAPICHANNELSLOTLIST`, `REBOOTANDROID`, `RESET`, `UPDATELOG`, `OTA_CHECK`, `SCREENSHOT`, `TYPEUPDATECOUNTRYCODE`, `TRADE`, `CONFIRM`, `REQQR` | `VendJobService.php`, `VendController.php:2411-2508`, `ApkReleaseController.php:248`, `VendScreenshotController.php:99` |
| `/settings/vend/{id}/parameter` page | **DEAD but live**: writes `vends.settings_parameter_json` (a column nothing reads — `/parameters` reads only the `apk_settings` row), then pushes the doorbell. Only UI for 4 running-text keys. ~1,115 vend rows carry the dead column | `SettingController.php:674-699`, `Setting/Parameter.vue` |
| `mqtt-config` repo | Boot directory: broker creds + route overrides (`settingPara_route`, `channelslotlist_route`, `banner_route`, `payment_gateway_menu_url`, `refund_request_form_url`…) | `clsHttpManager.AddGetMqttMapFrame`, `clsSubData.java` |
| OTA | `GET /ota/manifest` (unauth, throttled 120/min), `apk_releases` table, `vends.apk_version_code/apk_checked_in_at/apk_ver_json`; APK polls 6h + `OTA_CHECK` doorbell | `OtaController.php`, `OtaCoordinator.java` |
| Inbound device frames | `POST /SetPara2` + `POST /api/v1/vend-data` (both unauth): `CHANNEL`, `PWRON` (writes apk version), `JOBAPKSETTING` (settings ack), `FEATUREAPKSETTING` (6 flags) | `VendDataService.php:307-340` |
| Media | 4 endpoints (`banner-image/video`, `campaign-image/video`) mirror-synced to device folders; **name-only caching** | `VendController.php:3220-3303`, `clsHttpManager.java:1502-1799` |
| Payment config | `GET /api/v1/payment-merchants/{CC}/{gateway}`; `vends.is_enable_grab_collection` etc. (machine-authoritative) | `PaymentController`, `SyncFeatureApkSetting.php` |
| Campaigns | `campaigns` + `campaign_items` + `apk_setting_campaign` pivot → `campaigns[]` / `promoLabelItems[]` in `/parameters` (label promos are **server-only**, no local UI) | `ApkSettingController::bindCampaigns` |
| Vend row flags | `vends.server_price_type` (price tier), `vends.product_mapping_id`, `vends.private_key` (MQTT signing), `vends.is_enable_grab_collection` + 5 siblings | migrations 2024-12-25, 2026-01-20/30 |

---

## 2. Audit findings, ranked

### A. Security (fix regardless of revamp)

1. **No route-level authorization** on `/apk-settings` (web.php:128-149) or
   `/product-mappings` (web.php:709-729) — only `auth`+`cors`. Any logged-in user
   can update/push/delete. The seeder tuple for `apk-settings` only declares
   `read, export`, so add `create/update/delete/push` abilities *and* `can:`
   middleware.
2. **`GET /api/vends/operators/{operatorCode}/update-dcvends-countries`**
   (api.php:90) — unauthenticated, state-changing, fans out MQTT to an entire
   operator fleet on a guessable GET. Make it POST + auth + confirm, or delete.
3. **Device endpoints are open**: `/parameters`, `/thumbnails`, `/menu`, media
   endpoints — no auth, no throttle. Competitor/scraper can read the entire
   catalog, prices, campaigns per vend code. Minimum: throttle + require the
   signed-envelope pattern already used on MQTT (device has `private_key`).
4. **APK inbound MQTT signature check is commented out** in BOTH APKs
   (`CvMqttService.java:658-662` big, `:421-425` small), and
   `TYPESYNCSETTINGSPARAM` / `TYPESYNCAPICHANNELSLOTLIST` / `OTA_CHECK` have no
   replay/timestamp guard (others check ±60s).
5. Hardcoded secrets: settings password `"0198"` (both APKs), MQTT fallback key
   `'123456789110138A'` (`VendJobService.php`), HTTP sign key `"CVND=WINWIN"`.

### B. Correctness bugs (cheap fixes, real impact)

6. **APK copy-paste bug**: `companyUrl = obj.getCompanyName()`
   (`Main2Activity.java:7612`) — company URL on device is silently the company name.
7. **Server push blanks device values**: `clsSettingPromoParam` defaults strings
   to `""` and only the 4 company fields have empty-guards — a payload missing
   `supportContactNum`/`poweredBy`/`bannerKind`/`selectedPricingSource` wipes
   them (`Main2Activity.java:7604-7664`). Server currently always sends full
   payload, so this is latent — but it's why the payload must stay
   full-fidelity and why the APK needs guards anyway.
8. **Settings ack tracking only works for vend 2007**
   (`VendJobService.php:25-36`): `vend_jobs` rows are only created for code 2007,
   so fleet acks (`JOBAPKSETTING`) are discarded — you cannot see who actually
   synced. Also the doorbell is QoS 0: an offline machine misses it entirely and
   only recovers on next reboot.
9. **`enableDiscount01` default mismatch**: server default `true`
   (`ApkSettingParameters.php`), APK fragment default `true`, but APK engine
   default `false` (`Main2Activity.java:1030,7537`) — first-boot UI shows ticked
   while engine treats it off.
10. **Dead settings page** (`/settings/vend/{id}/parameter`) still ships and
    still publishes doorbells — edits look successful, never reach any machine.
    It is also the only UI for `enableHeaderTextRunning`, `enablePromoRunningText`,
    `runningTextStartDate/EndDate`; of those, `enablePromoRunningText` is a
    **dead key on the APK too** (read at `CartFragment.java:360`, never written) —
    the cart marquee can never show.
11. **Type chaos in the JSON blob** (verified in prod): booleans as `"true"/"false"`
    strings mixed with real bools, `discountPercent01` sometimes `"1"` string
    sometimes `1` int, `refund_url`/`company_url`/`company_address` snake_case
    among camelCase, `NULL_BECOMES_MINUS_ONE` legacy shim.
    **Full-column analysis of all 33 rows (2026-08-13):**
    - **14 of 46 keys are byte-identical in every profile** (all 3 dcvend
      values, the whole buy2free1/running-text blocks, etc.) — pure duplication;
      they belong in registry defaults, not in every row.
    - Of the 32 "varying" keys, most differ only via 1–2 outlier rows (test
      profiles like "falcon test", `poweredBy = "Powered By Happy Ice123"`).
      Genuine business variation is confined to ~10 keys: campaign texts/dates,
      `enablePromoHeaderText`/`enableLabelPromo`, `bannerKind`,
      `selectedPricingSource`, `supportContactNum`, company/refund fields.
    - **17 keys hold mixed JSON types across rows** (int in seeded rows, string
      in form-edited rows) — the write path never normalizes.
    - **Three schema generations coexist**: 4 rows with 38 keys, 25 with 42,
      4 with 46 — the `AsApkSettingParameters` cast heals on *read*, but rows
      not re-saved since a key was added stay stale on disk.
    - **`supportContactNum` code default is stale**: `DEFAULTS` says `87188597`,
      but 23 of 33 profiles (and the live device) carry `85488897`.
    - **15 of 33 profiles are bound to zero vends** (test/retired: "falcon
      test", "Brian Testing Server Price", "Actual DC Vend Setting"…); 2
      profiles cover 140 of the 206 bound machines.
    Remediation is already covered by 3.2 (typed registry + write-time
    normalization); add a one-time heal command that re-saves all rows through
    `fromArray()` (safe — read-heal already produces this shape), fix the
    default, and archive the 15 unbound profiles.
12. **No server-side validation at all** on `/apk-settings` update — no Form
    Request; client-side checks only. Company address joined as `\n` turns a
    blank address into `"\n\n"`.
13. **Media caching is name-only** on device (`FetchSingleVideo/Picture` skip if
    filename exists): replacing a banner's content under the same filename never
    propagates. Product thumbnails have the same issue for non-smart mappings
    (cache-buster `?v=` gated on `is_smart`).
14. Small board: `serialport`/`venderid` fields in the mqtt-config response have
    no getters — silently discarded; `image_interval` has two conflicting
    defaults (2000 vs 5000); `printer_baudrate` written, never read.
15. "Reboot when not online after 5 mins" actually fires at **6 minutes**
    (`ConfigEnum.ANDROID_RESTART_TIMING = 360000`). Label or constant, pick one.
16. **`update()` vend sync**: `$apkSetting->vends()->sync($request->vends)` can
    unbind other operators' vends due to the operator global scope (known bug).

### C. Design debt (what the revamp should actually fix)

17. **One blob, four different kinds of parameter.** The 46-key JSON mixes
    branding, pricing policy, and time-boxed campaign content; meanwhile payment
    toggles flow the *opposite* direction (machine→server), and device bootstrap
    (com port, secret key, locale) rightly stays local — but none of this is
    written down anywhere, so every new key is a judgment call.
18. **Campaign content is duplicated three ways**: profile JSON campaign block
    (texts/dates/buyXfreeY), `campaigns`+`campaign_items` label promos, and
    `apk_setting_campaign` bindings. The profile name itself is used as a
    campaign label in prod ("Musang King Durian launching 50% offer" is a
    *settings profile* name).
19. **No sync observability**: "Last sync to server time" on the device is
    device-local clock at apply time; server has nothing per-vend (acks
    discarded, see #8). Nobody can answer "which machines are running which
    settings/prices right now?".
20. **Two parallel catalog endpoints** (`/thumbnails` vs `/menu`) with duplicated
    field-building code and diverging behaviour (cache-buster, source table).
21. **Web editor drift**: 4 DEFAULTS keys have no field in `/apk-settings`
    Edit.vue; SOFTKEYPAD payment toggles visible on device but not on web;
    `Edit.vue` is a 1895-line monolith.

---

## 3. Proposed target model

### 3.1 Classify every parameter into one of four classes

This is the core best-practice move: **make sync direction and scope explicit
per parameter**, in one server-side registry.

| Class | Authority | Examples | Web UI treatment |
|---|---|---|---|
| **D — Device bootstrap** | Device only, never synced | `comPort`, `locale`/API base, `aesKey` (Secret Key), `Venderid`, `enableMQTT`, `selectedRemoteSetting` | Read-only display (from PWRON/FEATUREAPKSETTING telemetry) with "set on device" badge |
| **P — Fleet policy (profile)** | Server-authoritative | `poweredBy`, `supportContactNum`, `bannerKind`, `selectedPricingSource`, company/refund fields, `enableAutoReboot`*, soft-keypad module & carousel*, cart limit*, payment method toggles* (see 3.4) | Editable in profile; versioned; pushed |
| **C — Campaign (time-boxed)** | Server-authoritative, separate entity | running texts, promo banner kind, campaign dates, buy1free1/buy2free1, bundle discounts, label promos | Campaign editor with start/end, bound to profiles/vends; never baked into profile blob |
| **T — Telemetry / reported** | Device-reported, server read-only | `version`, `lastSyncApiDate`, `offlineRestartCount`, `consecutiveOfflineReboots`, current feature flags | Status panel per vend |

\* = today local-only or machine-authoritative; migrating them to P is a
deliberate direction change (see 3.4).

### 3.2 A parameter registry as single source of truth

Extend `ApkSettingParameters` from a defaults array into a declarative registry —
one entry per parameter:

```php
'supportContactNum' => [
    'type' => 'string', 'class' => 'profile', 'default' => '87188597',
    'rules' => ['nullable','string','max:20'],
    'apk_min_version' => null, 'restart_required' => false,
    'label' => 'Support Contact Number', 'group' => 'branding',
],
```

Everything derives from it:
- **Server-side validation** (the missing Form Request) generated from `rules`.
- **Vue form** generated (or at least driven) from registry metadata → kills the
  1895-line hand-built Edit.vue; new parameter = one registry entry.
- **Payload builder** keeps canonical order + full fidelity (protects against APK
  blanking bug #7) and can down-convert types for old APKs.
- **Docs**: the registry *is* the parameter inventory.

Type cleanup: store real JSON types in a new normalized blob, add
`"schemaVersion": 2` to `/parameters`; APK ≥ next release reads v2 (real bools),
older APKs get the legacy stringly payload from a compatibility serializer.
Never write `"true"`/`"false"` strings into new code.

### 3.3 Layered resolution + per-vend overrides

Resolution order at `/parameters` time:

```
registry defaults  →  operator defaults (optional)  →  profile (apk_settings)  →  per-vend override
```

- Per-vend override = repurpose the **existing** `vends.settings_parameter_json`
  column (today dead) as a sparse override map — this gives the dead
  `/settings/vend/{id}/parameter` page a real job, or delete both the page and
  the column and keep overrides out of scope. **Decide one; the current
  half-state is the worst option.**
- Store the resolved payload's hash per vend (see 3.5) so overrides are visible
  in sync status.

### 3.4 Fix the direction of the payment/feature toggles

Today `enableGrabCollection` + 4 payment toggles + `hasDisplayScreen` are set on
the device and mirrored up; server-side writes are commented out in the APK.
Pick a direction and enforce it:

- **Recommended**: make them class P (server-authoritative) — ops shouldn't
  climb into a machine to enable credit card. APK change: re-enable the server
  writes (guarded), keep local UI as an emergency override that reports a
  "locally overridden" flag upward. The `FEATUREAPKSETTING` frame then becomes
  pure telemetry (class T) — and fix its `?? null` partial-frame nulling
  (`SyncFeatureApkSetting.php:36-44`).
- Until an APK release ships, web UI must show these as **read-only reported
  state**, not editable fields (they'd silently do nothing).

### 3.5 Sync protocol: versioned, acked, observable

1. Add `settingsVersion` (hash of resolved payload + `updated_at`) to
   `/parameters` response.
2. APK stores it and echoes it in `JOBAPKSETTING` ack **and** in the periodic
   heartbeat / `PWRON`.
3. Server: remove the `!== '2007'` gate in `VendJobService::dispatch`, create
   `vend_jobs` for the whole fleet (or replace with a lighter
   `vends.last_settings_version` + `last_settings_synced_at` pair written by the
   ack handler), doorbell at **QoS 1**.
4. APK: re-fetch on MQTT **reconnect** (not just boot) + daily reconcile tick —
   closes the offline-missed-doorbell hole (#8) with no protocol change.
5. `lastSyncApiDate` should display the **server** time from the payload, not
   device clock.
6. Web UI per profile: bound machines list with version match (green = current
   hash acked, amber = pushed not acked, red = stale/never), replacing today's
   blind "Push" button.

### 3.6 Campaigns become the only campaign surface

- Move the profile-blob campaign block (running texts, dates, buyXfreeY,
  bundle/discount tiers) into the `campaigns` entity model (types: running_text,
  buy_x_free_y, bundle_discount, label_promo — `promo_type` already exists).
- `/parameters` continues to emit the flattened legacy keys for current APKs
  (computed from active bound campaigns at request time), so **no APK change
  required** to start.
- Wins: one place to schedule promos, natural history/audit, no more editing a
  fleet-wide profile to change one promo text, profile names stop doubling as
  campaign names (#18), and date-boxed content stops living in a permanent blob.

### 3.7 Product-mappings / catalog cleanup

- **Merge `/thumbnails` and `/menu`** into one builder with a `source` strategy
  (channel-join vs planogram) — the field list is already identical; today's
  duplication is where drift like the `is_smart`-only cache-buster comes from.
- Apply the `?v=` cache-buster to **all** thumbnails, not just smart mappings;
  device TTL then only bounds staleness for non-pushed changes.
- Media (banners/campaign media): move to content-hashed filenames on upload
  (name changes when content changes) — fixes #13 with zero APK changes, since
  device mirror-sync keys on filename.
- Document loudly (CLAUDE.md-level): `server_amount`/`server_price` are integer
  cents; `vends.server_price_type` (not the profile) picks the price tier;
  price changes need a `TYPESYNCAPICHANNELSLOTLIST` doorbell — consider
  auto-doorbelling on `selling_prices`/`product_mapping_items` writes the same
  way apk-settings edits auto-push.
- Add authz middleware to `/product-mappings` routes (same as #1).

### 3.8 UI structure for the revamped page

Organize by the classification, not by mirroring the APK tabs:

1. **Profiles list** — name, machine count, last-modified, sync health summary.
2. **Profile editor** — grouped: Branding & Support / Pricing / Payments &
   Modules / (campaigns shown as *bindings*, edited in the campaign section).
   Registry-driven fields with per-field badges: "requires APK ≥ N",
   "restart required", "server-authoritative since vX".
3. **Campaigns** — the consolidated campaign editor (3.6) with calendar view.
4. **Machine status** — per-vend: reported APK version, settings hash + last
   ack, feature flags as reported, com port/locale as reported, pending OTA.
   (Data already flows in via PWRON/FEATUREAPKSETTING/acks — it's just never
   been surfaced.)

### 3.10 Campaign section consolidation (investigated 2026-08-13; placed before 3.9 for flow — numbering kept for existing references)

**Three promo generations coexist on one edit page**, which is why the section
feels complicated:

| Gen | Where | Wire key | Who consumes | Prod usage |
|---|---|---|---|---|
| 1 | Flat keys in the profile blob (buy1free1/buy2free1, bundle/discount tiers, running texts) | individual keys | every APK version, VMC discount-group based | mostly 'false' fleet-wide |
| 2 | `campaign_items` on the profile + product-tag bindings ("old campaign binding") | `promoLabelItems[]` | **only APKs < v213** (stripped at ≥213) | 19 rows; **serves exactly 17 bound sub-213 machines** (182 bound machines are 213–300, 2 are 301+) |
| 3 | `campaigns` entity + `apk_setting_campaign` pivot ("new campaign binding") | `campaigns[]` | ≥213 (`LabelPromoEngine`, labels_x/labels_y, Percentage/Amount/Absolute/Free) | 20 bindings, actively used |

**Verified pain points:**

1. **Double entry is real and visible in prod**: profiles 45/47/49 carry the
   same promo entered BOTH as a campaign binding and as a campaign item with
   matching qty/value — staff must fill both sections to cover a mixed fleet,
   because sub-213 machines only read Gen 2 and ≥213 only read Gen 3.
2. **Live unit bug on the legacy wire.** `CampaignItem::value` has a
   ÷100-on-read / ×100-on-write accessor (added 2024-12-10, before every prod
   row), so the wire emits *whatever the user typed* — and staff have typed
   both units: Jan-2026 rows typed dollars (`5` → wire `5`), Jun-2026 rows
   typed cents (`500` → wire `500`). Result: **profiles 6/17/25 ("2 Cornetto
   $5") currently send `value: 5` (i.e. $0.05) to their 7 bound sub-213
   machines.** The ≥213 machines on the same profiles are fine (they use the
   consistently-stored campaigns[]). Violates the money-is-integer-cents
   invariant; the field has no unit label.
3. **A half-built Gen 2.5 sits in the schema**: `campaign_items` carries
   `campaign_type` (A1/A2/B1/B2), `action_type`, `action_value`,
   `cart_amount_threshold`, `free_qty`, `selection_strategy` — columns exist
   in prod, constants exist in the model, nothing in any UI or wire payload
   uses them. The Campaign entity already covers these mechanics.

**Proposal — one domain model, per-version wire adapters (legacy-safe):**

1. **Campaign (Gen 3) becomes the only authoring surface.** Staff bind
   campaigns; nobody types campaign items again.
2. **Derive the legacy payload instead of storing it.** A
   `CampaignWireSerializer` (or `VendParametersPayload` builder that also
   composes `ApkSettingParameters`) emits, from the same bound campaigns:
   - `campaigns[]` for apkVer ≥ 213 (unchanged shape), and
   - `promoLabelItems[]` for apkVer < 213, mapped as
     `{id, label: first labels_x tag, bundle_qty, promo_type, value}` —
     Amount/Percentage only; Absolute/Free are skipped (pre-213 engines never
     supported them). The `unset()` version-gate hack in `getVendParameters`
     collapses into the serializer, and the 80-line controller blob becomes a
     thin builder call.
   Wire shapes are byte-identical for both bands — only the *source* of
   promoLabelItems changes, which also fixes the unit bug for the 7 affected
   machines (campaigns store values consistently).
3. **UI collapses to one campaign section**: the Gen-3 bindings table + a
   "needed by N sub-213 machines" readiness meter (same pattern as the
   Deprecated section — currently N=17, far closer to retirement than the
   v301 threshold). The campaign-items form is deleted; existing rows stay in
   the DB as history until the meter hits zero, then table + Gen-2.5 columns
   go in the D4-style purge.
4. **Gen 1 (blob campaign block)** stays as-is for now and folds into Campaign
   types later per §3.6 — same serializer pattern, emitting the flat legacy
   keys from campaign entities.
   **First slice SHIPPED (2026-08-13): the bundle-tier ladder.**
   `Campaign::TYPE_QTY_TIER` ('QtyTier', bundle_qty ∈ {2,3,4} = threshold →
   slot 01/02/03, value = percent) + `App\Services\CampaignWireSerializer`:
   when ≥1 active QtyTier campaign is bound, the seven legacy tier keys
   (`enableBundleDiscount`, `bundleStart/EndDate`, `enableDiscount01..03`,
   `discountPercent01..03`) are DERIVED from the campaigns (window = union;
   any null bound on either side = unbounded, matching checkWithinDate);
   QtyTier campaigns never appear in `campaigns[]` (no deployed engine knows
   the type — the flat keys are the delivery mechanism for every APK version).
   **With no QtyTier bound the output is byte-identical passthrough** — the
   hand-set legacy fields stay fully functional until the fleet swaps, pinned
   by `tests/Unit/CampaignWireSerializerTest.php` +
   `tests/Feature/VendParametersQtyTierCampaignTest.php` (44 tests green
   across the apk-settings suites).

**Immediate remediation independent of the refactor:** re-enter or ×100 the
three dollar-typed campaign items (profiles 6/17/25), and give the value field
a unit label. Or accelerate: OTA the 17 sub-213 machines to the 30x stream and
skip the legacy serializer entirely.

### 3.9 DCVend decommission (directed by Brian 2026-08-13 — feature will not be used)

**Prod evidence (verified today):** 2 operators and 2 vouchers flagged
`is_dcvend`; **zero** transactions with a real `dcvend_user_id` since at least
2026-05 (every recent value is `""` — the APK always sends the key, empty when
nobody is logged in). The feature is dormant; scrapping is safe with staged
sequencing.

**Bug found while verifying:** the "DCVend member" transaction filter
(`VendTransaction.php:563` — `where('vend_transaction_json->dcvend_user_id', '>', 0)`)
matches **every** transaction where the key exists, because MySQL JSON type
ordering ranks any string above any number, so `"" > 0` is true. The filter has
been returning all APK transactions, not member ones. Moot once scrapped —
delete it with the rest.

**Inventory — mark1 (server), ~163 live references:**

| Area | Items |
|---|---|
| APK settings | 3 profile keys `dcvendFree/Gold/PlatinumPlanPromoValue` (`ApkSettingParameters` DEFAULTS; disabled fields `ApkSetting/Edit.vue:182-205,1520-1522`; hardcoded copy `VendController.php:4621-4623`) |
| MQTT / routes | `TYPEUPDATECOUNTRYCODE` publisher `updateDCVendsCountries` (`VendController.php:5804`) + the **unauthenticated GET fan-out route** (`api.php:90`, security finding #2 — deleting it resolves that finding outright); `getAllDCVends` (`VendController.php:2763`) + `POST /api/v1/client/dcvends`; `app/Http/Resources/DCVend/*` (6 resource classes) |
| Vouchers | `Voucher` model (`is_dcvend`, `dcvend_member_type`, `dcvend_qty_per_member`, member-type consts, platform `DC Vend`); `VoucherController` create/update/`getDCVends`/sync paths; `VoucherService::syncDCVendVouchers`; `SendDataToDcvend` job; `Voucher/Create.vue` + `Edit.vue` DCVend blocks; `VoucherResource` fields |
| Transactions | `VendTransaction` scopes incl. the buggy filter (`:563-608`); member column `Vend/Transaction.vue:897`; `member_id` in CSV exports (`ExportVendTransactionCsv.php:360,407`, chunk job); `VendController.php:4429,4475` |
| Misc | `operators.is_dcvend` column (2 rows); `DcvendAuthSeeder` (already commented out); `config/app.php:67` `dcvend_url` env; `dcvendHtml/` folder (outside estate — archive) |

**Inventory — big-board APK (large):** `enableDcVendFeature` pref + BASIC-tab
toggle; `LoginFragment`, `UserInfoFragment`, `pageDcVendDialog`,
`pageDcVendDiscountTimesDialog`, voucher/member adapters; `dcvend` DTO package
(`Plan`, `Voucher`, `clsUserData`, `Base`), `clsDcvendCountryCode`,
`DcVendEnum`; `https://dcvend.com/api/v1/login` + `/countries` calls;
`TYPEUPDATECOUNTRYCODE` handler; ~30 dcvend drawables/layouts;
`dcvend_user_id` in the TRADE/CONFIRM frames; plan-promo-value writes in
`updateSettingsPromoParam`. **Small board:** negligible (one layout + one color
reference — cleanup is trivial).

**Sequencing (interleaved with the phases below):**

- **D1 — hide, server-only (with Phase 0):** delete the unauth fan-out route +
  `TYPEUPDATECOUNTRYCODE` publisher; hide DCVend UI blocks in
  `ApkSetting/Edit.vue` and `Voucher/*` pages; remove the buggy transaction
  filter + member column/CSV field. **Keep emitting the 3 plan-promo keys in
  `/parameters`** — current APKs still parse them (ints won't blank like
  strings, but don't churn the payload before schemaVersion exists).
- **D2 — data:** unflag the 2 operators + 2 vouchers; leave columns in place.
- **D3 — APK release (with Phase 3's release train):** strip login/member UI,
  DTOs, dcvend.com calls, `TYPEUPDATECOUNTRYCODE` handler, the settings toggle.
  Keep sending `dcvend_user_id: ""` on TRADE frames until the server explicitly
  tolerates its absence (cheap wire-compat insurance).
- **D4 — purge (with Phase 4):** drop the 3 keys from the parameter registry /
  v2 payload, drop `operators.is_dcvend` + voucher DCVend columns in a cleanup
  migration, delete `DcvendAuthSeeder` + `dcvend_url` config, archive
  `dcvendHtml/`.

---

## 4. Phased plan

**Phase 0 — hygiene, no schema change (days)**
- Authz middleware + expanded permission tuples on `/apk-settings`,
  `/product-mappings` (#1); **delete** `update-dcvends-countries` (#2, per
  DCVend decommission D1); throttle device GETs (#3).
- DCVend D1 hide steps (3.9): drop DCVend UI blocks, buggy transaction filter,
  member column/CSV field.
- Form Request validation for apk-settings update (#12); fix cross-operator
  `sync()` bug (#16).
- Decide + execute on the dead page/column (#10, 3.3).
- APK (next release train): fix `companyUrl` copy-paste (#6), add empty-guards
  on server writes (#7), align `enableDiscount01` defaults (#9), re-enable MQTT
  signature check (A4), 5-vs-6-minute label (#15).

**Phase 1 — registry + typed schema (1–2 weeks)**
- Build the parameter registry (3.2); generate validation + payload; add
  `schemaVersion`/`settingsVersion`; normalize stored JSON types via migration
  (the `AsApkSettingParameters` cast already read-heals, which makes this safe).
- **Partially DONE 2026-08-13** (wire-compatible, no APK change needed):
  - `ApkSettingParameters` is now a declarative registry (`SCHEMA`: default,
    type, group, label, deprecation metadata per key) with per-type
    normalization to the dominant legacy wire shape (bools as `'true'/'false'`
    strings, ints as ints, dates as `Y-m-d`/null; null→-1 shim kept). Verified
    against all 33 prod rows: no value any deployed APK receives changes shape
    beyond the 4 stringly-number outliers, which Gson parses identically.
  - `UpdateApkSettingRequest`/`StoreApkSettingRequest` — server-side validation
    generated from the registry (closes finding #12); Edit.vue now surfaces
    422s instead of swallowing them.
  - Edit page gained a collapsed **Deprecated** section (dcvend keys +
    running-text block) with a fleet-readiness meter — "N of M bound machines
    on v301+" — driven by `DEPRECATION_FLEET_APK_VERSION = 301` and each
    vend's reported `apk_version_code` / `apk_ver_json.apkver`. Deprecated
    keys stay stored and pushed until the meter is full; then deleting the
    SCHEMA entries retires storage, payload and UI at once.
  - Stale `supportContactNum` default corrected to `85488897`.
  - Pinned by `tests/Unit/ApkSettingParametersTest.php` (production-row
    round-trip, canonical order, deprecated keys still on the wire).
  - **APK-side compatibility verified in source** (`clsSettingPromoParam.java`,
    `clsHttpManager.FetchSettingPara`, Gson 2.8.0): the DTO uses primitive
    `boolean`/`int` and `String` fields with initialized defaults, parsed via
    `GSON.fromJson` inside a catch-all (a parse failure skips the update, never
    crashes). Consequences, valid for every revision of this lineage:
    * **Adding a key is always safe** — Gson silently ignores unknown JSON.
    * **Removing/renaming a key is NEVER safe pre-retirement** — a missing
      field keeps the DTO default and the APK writes that default over the
      machine's pref (silent fleet reset). Hence: SCHEMA always emits every
      key; deprecated keys ride along until the fleet meter is full.
    * `'true'/'false'` strings, real bools, ints and numeric strings all
      coerce fine; a NON-numeric string in an int field throws inside
      `nextInt()` and aborts the whole update — the registry's int
      normalization + request validation make that unrepresentable.
    * `promoLabelItems` must keep shipping for apkVer < 213 (pre-migration
      machines) — the existing gate is preserved.
    * Small board (`mark1-apk-small`) never calls `/parameters`; unaffected.
  - Wire contract pinned end-to-end by
    `tests/Feature/VendParametersWireContractTest.php`: schema-complete
    superset payload, Gson-safe types per key, alias keys
    (`isGrabEnabled`/`companyUrl`/…), the 212/213 promoLabelItems gate, and
    identical output for a raw 38-key legacy row vs a freshly saved profile.
  - **Adversarial review pass (2026-08-13, 8-angle + APK-source verification)**
    fixed 10 confirmed issues before commit, notably: the readiness meter now
    accounts for small-board 13x-stream machines (they never fetch
    `/parameters`; `Vend::maybeSmallBoardStream()` heuristic — durable fix is
    the APK reporting its package/board type); `update()` no longer detaches
    the fleet on a vends-less request nor silently drops cross-operator
    bindings (raw-pivot merge + `sync()['detached']`); `''` is preserved on
    the wire for `supportContactNum`/`poweredBy` (the only two fields where
    the APK treats null differently — null resurrects hardcoded on-device
    defaults); media uploads store under the validated extension
    (`storePubliclyAs`) instead of MIME-guessed names the machines skip;
    cleared buyXfreeY groups normalize to −1 not the default group; unknown
    enum values pass through for forward compatibility;
    `Vend::reportedApkVersion()` is now THE version definition (replaces 4
    divergent inline variants); the manual Push button delegates to
    `VendJobService::syncSettingsToVend` (frame format single-sourced, key
    from `config('vend.private_key')`).
  - **Edit-page regroup shipped (2026-08-13, §3.8 partial):** sections now read
    Profile → Branding & Display → Pricing (pricing source + P2/cross-group
    moved out of the campaign block) → Contact & Support → System (debug) →
    Deprecated → Media → Campaigns → Campaign Bindings → Old Campaign Item
    Bindings (marked "Legacy: only APKs < v213 read these", with a CENTS unit
    warning on the value field per the §3.10 unit bug) → Machines. Purely
    presentational — field names, payload, and wire output unchanged. NOT yet
    done: registry-driven form generation, machine-status panel, campaign
    consolidation (§3.10 pending sign-off), and every mark1-apk screen change
    (bundled into the 303 patch: DCVend removal, locked server fields, board
    type + settingsVersion reporting, v2 parser).
  - **Still deliberately unfixed:** `SettingController::updateParameter`'s
    third frame copy (dead page, §3.3 decision pending); `raw_ack_vends`
    default `'2031'` (staged rollout per ACK_FIX_PLAN); the v2
    `toWire(schemaVersion)` serializer split (Phase 1 remainder — the
    string-bool wire format is currently baked into normalize(), fine for v1,
    must be split before schemaVersion 2 ships).

**Phase 2 — sync observability (1 week + APK release)**
- Fleet-wide ack tracking, QoS 1 doorbell, reconnect re-fetch, machine status
  panel (3.5, 3.8.4).

**Phase 3 — UI revamp + campaign consolidation (2–3 weeks)**
- Registry-driven profile editor; campaign entity migration with
  legacy-key emission (3.6); payment-toggle direction flip behind APK version
  gate (3.4).

**Phase 4 — catalog + fleet extension**
- Merge `/thumbnails`+`/menu`, universal cache-buster, content-hashed media
  (3.7).
- Decide small-board scope: either teach it `/parameters` (its `selectedRemoteSetting`
  concept doesn't exist) or mark small-board vends as non-profile machines in
  the UI so bindings can't silently no-op.
- Smart freezer (`sg.mark1.freezer`) adopts the registry + `/parameters` v2 from
  day one — cheaper than retrofitting, and consistent with the "one system for
  ops" objective.

---

**DCVend decommission** rides along: D1/D2 with Phase 0, D3 with Phase 3's APK
release, D4 with Phase 4 (see 3.9).

## 5. Open decisions (Brian)

> Decided 2026-08-13: **DCVend is scrapped** — decommission per §3.9, no longer
> an open question.

1. Dead per-vend page/column: **delete** or **promote to override layer**? (3.3)
2. Payment/feature toggles: flip to server-authoritative? (3.4 — needs APK release)
3. Campaign consolidation scope: full entity migration (3.6) or keep the blob's
   campaign block and only fix types/validation?
4. Small board: in-scope for server settings, or explicitly excluded and made
   visible as such?
5. Device endpoint auth: signed requests using `vends.private_key`, or
   throttle-only for now?
