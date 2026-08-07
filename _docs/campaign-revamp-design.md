# Campaign Management Revamp — Design Doc (v0.1 for discussion)

**Date:** 2026-08-06 (rev 2 — added §3.5 transaction attribution) · **Status:** DRAFT — nothing here is implemented; this is the planning doc we iterate on together.
**Scope:** mark1 CMS (`/apk-settings`, `/campaigns`) + mark1-apk validation engine. mark1-apk-small is **out of campaign scope** (it only needs to safely ignore campaign payloads). smart-freezer adopts the same contract later.
**Agreed principles so far:** design doc first (no code yet) · **best-deal-only stacking** · legacy mechanics are high-blast-radius, so migration is planned separately and carefully.

---

## 1. Where we are today (audit summary)

There are **three parallel campaign systems** stacked inside one page, plus a fourth (Grab) on the delivery side:

| System | Storage | Reaches machine? | State |
|---|---|---|---|
| Legacy JSON promo fields (buy1free1, buy2free1, discount01–03, label promo, header/running text) | `apk_settings.settings_parameter_json` blob — ~30 untyped keys, no validation, no schema | Yes — the blob **is** the machine payload | Live, in production, fragile |
| Old `campaign_items` ("Old Campaign Item Bindings") | `campaign_items` table (belongs to apk_setting, **not** to campaigns despite the name) | Served as `promoLabelItems`, but **dropped for apkVer ≥ 213** | Effectively dead for current fleet |
| New `campaigns` ("Campaign Bindings") | `campaigns` + `campaign_tag` + `apk_setting_campaign` | Yes — merged into `getVendParameters()` response as `campaigns[]` | Live — this is the system to standardize on |
| Grab delivery campaigns | `delivery_platform_campaign*` (3-level hierarchy) | Submitted to Grab API | Live — **best-engineered of the four**; the reference |

### 1.1 How the pipeline actually works (traced end-to-end)

1. CMS **Push** button → `syncApkSettings()` publishes a 4-field MQTT "go fetch" signal (`TYPESYNCSETTINGSPARAM`) — it carries **no settings**.
2. APK (`MainMqttListener.OnSyncApiSettingsParam`) fetches over HTTP → `VendController::getVendParameters($vendCode)`.
3. Server merges: legacy blob (verbatim) + `isGrabEnabled` + bound-and-active `campaigns[]` (id, label, slug, promo_type, value, bundle_qty, labels_x/y, start_date/end_date, min_basket_value, max_discount_value).
4. APK deserializes into `clsSettingPromoParam`; legacy fields → SharedPreferences; `campaigns[]` → **in-memory only** (`labelPromoGlobalArray`).
5. Cart-time: `ReceiptEngine.CountTotalPrice()` runs a fixed 7-stage pipeline; `CountLabelPromo()` dispatches on `promo_type` ∈ {Free, Amount, Percentage, Absolute}; anything else is silently ignored.

### 1.2 The money-unit situation (verified against live DB)

The `Campaign` model has dollar⇄cents accessors, but live rows show operators **enter machine-cents directly** ("Buy 2 for $16.80" → CMS input `1680` → stored `168000` → served `1680.0` → APK reads `1680` cents ✓). It round-trips correctly **by accident** — the model pretends the column is dollars while everyone uses cents. Percentage campaigns enter `10` for 10%. Some live rows have junk `min_basket_value` (e.g. `200` = $0.02) that only "works" because `bundle_qty` is checked first. One convention must be made explicit and enforced (proposal: integer cents everywhere, named `*Cents`).

---

## 2. Defects found during the audit

These are all confirmed with file:line during the audit. Grouped by severity. **None of them are fixed yet** — Phase 0 below proposes which to fix first.

### 2.1 Money-corruption class (silent, no crash)

| # | Defect | Where |
|---|---|---|
| M1 | Campaign with empty/null `labels_x` **matches every item in the cart** — a mis-saved campaign discounts the whole basket | `ReceiptEngine.java:1495-1497, 1641-1643, 1797-1799`; backend allows `labels_x nullable` |
| M2 | `Absolute` discount has no floor — value > group total ⇒ **negative receipt total**; `CountDiscountPercent` uses `Math.abs`, so the UI shows it as a positive discount | `ReceiptEngine.java:1815-1818` |
| M3 | **`max_discount_value` is never read by the APK** — percentage campaigns are uncapped, though the CMS collects the field | verified zero references in both APKs |
| M4 | All matching campaigns stack and compound with no order, priority, or cap; two overlapping campaigns double-discount | `ReceiptEngine.java:1044-1058` |
| M5 | Empty `campaigns[]` in a fetch **never clears** the previous list (guard is `size() > 0`) — a deleted campaign stays live until app restart | `Main2Activity.java:6973-6978` |
| M6 | `campaigns[]` is never persisted — app restart loses all campaigns until the next sync; meanwhile the master switch (`enableLabelPromo`) *is* persisted, so the two have different lifetimes | `Main2Activity.java:6973` vs `:6970-6972` |

### 2.2 Crash class

| # | Defect | Where |
|---|---|---|
| C1 | `value` is nullable server-side but never null-checked in the APK → NPE unboxing in Amount/Absolute paths; the NPE surfaces as "item added but total silently stale" | `ReceiptEngine.java:1508, 1815`; `CampaignController` `'value' => 'nullable'` |
| C2 | Null elements in `labels_x`/`labels_y` → NPE in enhanced-for unboxing | `ReceiptEngine.java:1496, 1642, 1798` |
| C3 | FREE-type `addedCount++` fires every outer-loop iteration → early/inconsistent termination when no `labels_y` slot is in stock | `ReceiptEngine.java:1407` |

### 2.3 CMS validation & lifecycle gaps

| # | Defect | Where |
|---|---|---|
| V1 | `ApkSettingController::update()` validates **nothing** — raw `$request->all()` → service → `fill()`; a request without `vends` key detaches the entire fleet via `sync(null)` | `ApkSettingController.php:233-262` |
| V2 | No conditional rules: `Percentage` doesn't require `value` ≤ 100 or even a value; `Free` doesn't require `labels_y` or `bundle_qty`; `is_using_qty='qty'` doesn't require `bundle_qty` | `CampaignController.php:97-115` |
| V3 | `is_active` can never be changed after create — no way to deactivate a campaign (UI renders the flag anyway) | `CampaignController.php:122, 216-230` |
| V4 | No operator scoping on `Campaign` (unlike `ApkSetting`), and `bindCampaigns()` lets Operator A's campaign bind to Operator B's machines | `Campaign.php` (no global scope); `ApkSettingController.php:97-119` |
| V5 | Binding/unbinding a campaign doesn't trigger any push; saving settings doesn't either (auto-push commented out) — machine state drifts from CMS state with **zero delivery observability** | `ApkSettingController.php:248-259` |
| V6 | Push button's Inertia call passes callbacks as POST body — success/error toasts never fire | `ApkSetting/Edit.vue:1929-1944` |
| V7 | `slug` (customer-facing on machine) has no uniqueness rule; `name ?? slug` fallback chains differ between `label` and `slug` in the serializer | `CampaignController.php`; `VendController.php:3316-3321` |
| V8 | Dates: CMS stores browser-local naive datetimes, serves `toDateString()`, APK compares with device-local `yyyy-MM-dd` at **day granularity** — no timezone anywhere, no time-of-day support | `CommonFunctions.checkWithinDate`, `VendController.php:3323-3324` |

### 2.4 Structural debt (the reason "small fixes" keep being risky)

The APK promo engine is ~920 lines inside `ReceiptEngine` with state on `Main2Activity` (~25 shared fields), three ~150-line near-identical copies of the qualification loop (Amount/Percentage/Absolute), a fourth divergent one (Free) that mutates the cart mid-pricing-pass, plus a large dead-code layer (the disabled `onCheckLabelPromo` path, `oldLabelQualification`, the fully commented-out `LabelPromoEngine.java` — an abandoned attempt at exactly this refactor). The CMS side mirrors this with three vocabularies for the same concepts (`promo_type` string vs int vs enable-flags; `bundle_qty`/`qty`; `min_basket_value`/`cart_amount_threshold`/`minBasketAmount`; four date-field naming conventions).

---

## 3. Target architecture

### 3.1 Design principles (borrowed from what already works — the Grab side)

1. **One canonical campaign model** with a **data-driven promo-type registry** (like `Grab::CAMPAIGN_MAPPINGS`) shared by CMS forms, server validation, and the machine contract — instead of constants hardcoded in PHP, Vue, and Java separately.
2. **Explicit machine contract**: a versioned JSON schema (`schemaVersion`) is the single source of truth for what a machine receives. Field names, units, and semantics documented in one place; golden test vectors checked into both repos.
3. **Trigger / effect separation** (industry-standard cart-rule shape used by commercetools, Magento, Voucherify): a campaign = *when it applies* (labels, qty, basket threshold, window) + *what it does* (percentage / amount / absolute / free) + *limits* (caps, stacking).
4. **Best-deal-only stacking** (your decision): per qualifying group, the engine computes every matching campaign's discount and applies only the largest; deterministic tiebreak (larger discount → lower campaign id). No compounding.
5. **Integer cents + explicit percent, everywhere.** Fields named so the unit is unmistakable (`valueCents`, `percentValue`, `minBasketCents`, `maxDiscountCents`). The dollar⇄cents model accessors are removed (they currently do a lossy pretend-conversion nobody uses).
6. **Delivery observability** (the itemVend pattern): every push records per-machine what was sent, when, and what the machine acknowledged. The CMS can answer "is machine 2031 running the config I saved?" — today it cannot.
7. **Fail-closed on the machine**: malformed campaign ⇒ that campaign is skipped *and reported*, never "discount the whole cart" or "crash and keep a stale total".

### 3.2 The v2 machine contract (draft)

Served by `getVendParameters` alongside the existing v1 fields (dual-serve during transition; see §4). Machines with `apkVer >= N` read `campaignConfig` and ignore legacy campaign fields; older machines keep reading v1.

```jsonc
"campaignConfig": {
  "schemaVersion": 2,
  "configHash": "sha256:…",          // hash of this block; used for ack/drift detection
  "generatedAt": "2026-08-06T09:30:00+08:00",
  "stackingPolicy": "best_deal",      // fleet-wide for now; per-campaign later if ever needed
  "campaigns": [
    {
      "id": 45,
      "uuid": "…",
      "displayLabel": "Buy 2 Musang King Durian for $16.80",   // customer-facing (today: slug)
      "window": {
        "startAt": "2026-07-29T00:00:00+08:00",   // ISO-8601 WITH offset; operator's tz
        "endAt":   "2026-08-31T23:59:59+08:00"    // inclusive end-of-day resolved server-side
      },
      "trigger": {
        "basis": "qty",                 // qty | amount | both  (today: is_using_qty)
        "labelsX": [12, 13],            // REQUIRED non-empty (fixes M1)
        "bundleQty": 2,
        "minBasketCents": null
      },
      "effect": {
        "type": "amount",               // percentage | amount | absolute | free
        "valueCents": 1680,             // for amount/absolute  (NOTE: true cents, not the ×100 DB value)
        "percentValue": null,           // for percentage, integer 1–100
        "freeQty": null,                // for free
        "labelsY": []                   // for free: what can be given away
      },
      "limits": {
        "maxDiscountCents": null,       // cap for percentage (fixes M3)
        "maxTriggersPerTransaction": null
      }
    }
  ]
}
```

Contract rules the APK engine enforces (each one closes an audit defect): campaign skipped unless `labelsX` non-empty (M1) · discounts clamped ≥ 0 (M2) · `maxDiscountCents` honored (M3) · best-deal selection (M4) · **full-replace semantics**: the received array *is* the truth, empty array clears everything (M5) · block persisted to disk with `configHash`, reloaded on restart (M6) · every numeric field null-checked, malformed campaign skipped + logged + reported in the ack (C1–C3).

### 3.3 CMS-side target

**Campaigns become fully self-contained** at `/campaigns` (they mostly already are): add the missing validation (V2), an is_active toggle + Deactivate action (V3), operator scoping + bind-time operator match (V4), unique slug per operator (V7), and a promo-type registry table (`campaign_type_registry` or a PHP enum class consumed by both the Vue form and the validator) describing per type: required fields, units, value bounds, and the human phrase templates (exactly what `Grab::CAMPAIGN_MAPPINGS` does today).

**/apk-settings sheds campaign authoring** and keeps only: display/branding settings, machine binding, campaign *binding* (pick from `/campaigns`), and push. The "Old Campaign Item Bindings" section is hidden behind a read-only "legacy" view (nothing serves it to apkVer ≥ 213 anyway).

**Delivery state** — new table `apk_setting_pushes` (modeled on `delivery_platform_campaign_item_vends`):

| Column | Purpose |
|---|---|
| `vend_id`, `apk_setting_id` | which machine, which setting |
| `config_hash`, `payload_json` | exactly what was served |
| `pushed_at` | when the MQTT nudge went out |
| `fetched_at` | when the machine actually called `getVendParameters` |
| `acked_at`, `acked_hash` | machine's confirmation (piggyback on the existing `NotifySettingsParamGetComplete` / PWRON path — PWRON already carries `installPath`, so the pattern exists) |

Edit page then shows per-machine: In sync / Stale (pushed, not fetched) / Drift (acked hash ≠ current hash) — the same three-bucket toast pattern `syncCampaigns()` already uses for Grab.

### 3.4 APK-side target (mark1-apk)

Resurrect the `LabelPromoEngine` idea properly: a **pure `CampaignEngine` module** (no Android imports, no `Main2Activity` fields) with one entry point:

```
CampaignResult evaluate(List<CartLine> cart, CampaignConfig config, long nowEpochMs)
```

It returns *decisions* (per-line discounts, free items to offer, receipt annotations) and the UI layer applies them — no cart mutation from inside pricing (fixes the FREE-type reentrancy mess). The four ~150-line duplicated qualification loops collapse into one loop + a small per-type strategy. `ReceiptEngine.CountLabelPromo` becomes a thin adapter calling the engine during transition, so the blast radius stays contained.

**Testing:** the engine ships with a JSON **golden-vector suite** (cart + config → expected totals) checked into *both* mark1 and mark1-apk. The server generates the vectors from the same registry that drives validation, so CMS and APK can never silently disagree on semantics again. This is also the "make sure it's bug free before announce" story: every defect in §2 becomes a named test vector.

**mark1-apk-small:** no campaign engine. The only change it ever needs is *ignore-safety* — confirm unknown JSON keys in the settings fetch can't crash it (it doesn't consume `clsSettingPromoParam` at all today, so this is likely already true; one-time check, not a project).

**smart-freezer (later):** adopts `campaignConfig` verbatim. The contract deliberately has zero vending-specific concepts (no discount groups, no P1/P2) — triggers/effects are label-based, and smart-freezer already has label/tag infrastructure via its catalog push. Same golden vectors, same engine module if it's Android-based.

### 3.5 Transaction-level campaign attribution (NEW — added 2026-08-06)

**Requirement:** every transaction must record which campaign(s) produced its discount/free items, queryable per transaction and per item.

**What exists today (traced):** at dispense time the APK builds a list of applied campaign IDs (`buildLabelPromoUsageIdList()` → `ob.setLabel(...)`, `Main2Activity.java:1380-1393`); the server (`VendTransactionService::processInput():757-794`) resolves them to `"slug(id)"` strings and stores them in `vend_transactions.label_json`. Verified live: `label_json = ["Buy 3 Cornetto at $12 dollar.(35)"]`. Three problems: the campaign id is **embedded inside a display string** (unqueryable without parsing), **no discount amount is recorded** (only the member-discount precedent `dcvendDiscountAmount` has a real field), and **`vend_transaction_items` has zero campaign columns** — a free item is indistinguishable from a paid one except by price, and per-item discounts don't exist. There's also a known APK bug: a campaign whose `id` deserializes as 0 applies its discount but is silently dropped from the usage list (`ReceiptEngine.java:1123-1126`).

**Design.** Best-deal-only stacking makes this clean: an item group is discounted by **at most one** campaign, so per-item attribution is a single nullable FK, no allocation table needed.

*Machine → server (TRADE payload, add alongside the legacy id list during transition):*

```jsonc
"appliedCampaigns": [
  {
    "campaignId": 35,
    "effectType": "amount",
    "discountCents": 300,            // this campaign's contribution to the receipt discount
    "triggeredGroups": 1,            // how many times the bundle fired
    "lines": [                        // per cart line, so items can be attributed
      { "channelCode": 12, "qty": 3, "discountCents": 300, "isFree": false }
    ]
  }
]
```

*Server schema:*

| Change | Detail |
|---|---|
| New `vend_transaction_campaigns` | `vend_transaction_id` FK · `campaign_id` FK · `discount_cents` · `free_qty` · `triggered_count` · `meta_json` (raw line detail) — the queryable per-transaction record, one row per applied campaign |
| `vend_transaction_items` + 3 columns | `campaign_id` (nullable FK) · `discount_amount` (cents, this item's share) · `is_free` (bool) — per-item attribution; free items get `is_free=1`, `campaign_id` set, price 0 |
| Keep `label_json` | untouched, for backward compat and existing UI; new columns are the source of truth going forward |

*Why it pays off immediately:* campaign ROI reporting becomes a plain join (redemptions, discount cost, revenue on attributed transactions — could feed the Performance dashboard later); refund handling can see whether a refunded item was a freebie; GP/stock reports stop counting free items as full-price sales anomalies. Old APKs keep sending only the legacy id list — the server keeps writing `label_json` for them, and the new table simply has no `discount_cents` for those rows (nullable), so nothing breaks during rollout.

*Open sub-questions:* (a) backfill — historical `label_json` strings can be parsed into `vend_transaction_campaigns` rows (id is extractable from `"slug(id)"`), but without discount amounts; worth doing for redemption counts? (b) should `dcvendDiscountAmount` and vouchers eventually join the same structure (one `discounts[]` ledger per transaction) instead of three parallel mechanisms? Not in scope now, but the schema shouldn't preclude it.

### 3.6 What happens to legacy mechanics — planning only, per your call

No migration is executed in this revamp. The doc keeps the mapping table so we can discuss timing separately:

| Legacy mechanic | Unified equivalent | Migration risk notes |
|---|---|---|
| Buy 1 Free 1 (group X → group Y) | `free` campaign, `bundleQty: 1`, labelsX/Y | Needs discount-group → label mapping per operator; groups are free-typed ints today |
| Buy 2 Free 1 | `free` campaign, `bundleQty: 2` | Same; note today's "even count of whole group" quirk changes semantics slightly |
| Discount01/02/03 (buy 2/3/4 → %) | three `percentage` campaigns, `bundleQty` 2/3/4, empty→**all-items label** needed | These are the highest-usage legacy fields; needs an explicit "storewide" label concept first (M1 means we can't use empty labelsX) |
| Bundle date window | per-campaign windows | Tier dates currently inherit bundle dates implicitly |
| Label promo master switch + dates | disappears — per-campaign windows only | Today's nested two-window gating (global AND per-campaign) goes away; must communicate to ops |
| Header/running text, banner kind | **not campaigns** — stays in apk-settings as display settings | Just relabel; no migration |
| `campaign_items` | already dead for apkVer ≥ 213 | Archive table, hide UI |

The **cutover mechanic** when we do get there: dual-serve both representations, flip machines by `apkVer` gate (the codebase already does exactly this for `promoLabelItems` at ver 213 — proven pattern), keep legacy fields served until the fleet's minimum version passes the cutoff, then remove.

---

## 4. Phased plan (each phase independently shippable, no fleet breakage)

**Phase 0 — Safety patches (small, surgical, before any redesign).** APK: persist campaigns + full-replace on empty (M5/M6), clamp negative discounts (M2), honor `max_discount_value` (M3), null-guard value/labels (C1/C2), skip campaigns with empty `labels_x` (M1 — verified against live DB 2026-08-06: all bound campaigns have ≥ 1 X-label, so this is safe; re-verify at patch time). CMS: `Percentage` value bounds + required-field conditionals (V2), fix the `sync(null)` fleet-detach hazard (V1), operator match on bind (V4). *These fix real money bugs without touching architecture.*

**Phase 1 — Contract freeze.** Write the v2 schema + golden vectors; add `campaignConfig` block to `getVendParameters` (dual-serve; old fields untouched); add `configHash` + `apk_setting_pushes` delivery tracking; promo-type registry drives CMS validation + form. **Attribution schema lands here too** (§3.5): `vend_transaction_campaigns` table + the three `vend_transaction_items` columns, with the server accepting both the legacy id list and the new `appliedCampaigns` block — so the moment a Phase-2 APK ships, attribution is already being recorded.

**Phase 2 — APK engine.** Build `CampaignEngine` + vector tests; wire behind `schemaVersion` check with instant fallback to the old path (config-flag or apkVer gate); ship to test machines (falcon pattern) → staged rollout via the existing OTA permille buckets.

**Phase 3 — CMS UX split.** `/campaigns` gets lifecycle (activate/deactivate, duplicate, per-machine sync status); `/apk-settings` becomes display-settings + binding + push only; legacy campaign fields visually quarantined as "Legacy (frozen)".

**Phase 4 — Legacy migration.** Separate discussion, per §3.5 — only after Phases 0–3 are stable in the field.

**Phase 5 — smart-freezer adoption.** Same contract, same vectors.

---

## 5. Open questions for our next discussion

1. **Storewide campaigns:** with empty-labelsX banned (M1), do we need an explicit "all products" trigger flag for basket-threshold deals like "10% off orders > $30"? (Verified live: every campaign currently *bound* to a machine has ≥ 1 X-label — even campaign 41 — so the ban breaks nothing today; only unbound test campaigns have empty labels. But ops may expect "whole store" deals eventually.)
2. **Best-deal granularity:** best deal per *cart* or per *item group*? (Proposal: per item group — a Cornetto bundle deal and a basket-threshold deal on other items can coexist since they touch different lines; two deals competing for the same lines → bigger one wins.)
3. **Ack transport:** piggyback the config-hash ack on PWRON (already extended recently for `installPath`) vs the existing `NotifySettingsParamGetComplete` vend-job path?
4. **`value` entry UX:** keep operators typing cents (current habit: `1680` = $16.80) or switch the form to dollars with explicit formatting? (Contract is cents regardless; this is CMS-form-only.)
5. **Time-of-day windows:** day-granularity is today's reality; v2 contract carries full datetimes — should the engine honor time-of-day from day one, or truncate to days until ops asks? (Grab side hardcodes all-hours; same question will come from delivery later.)
6. Timeline/priority: is Phase 0 something you want soon (it's the money-bug layer), or does everything wait for the full design sign-off?
7. **Attribution backfill:** parse historical `label_json` into `vend_transaction_campaigns` for redemption counts (no discount amounts available), or start clean from the schema change?

---

## 6. References

Current-state audit: `VendController::getVendParameters` (mark1), `ReceiptEngine.java` + `Main2Activity.java` (mark1-apk), `DeliveryPlatformCampaignService.php` (Grab reference), live `campaigns` table via mark1 MCP (read-only).

Industry patterns consulted: promotion stacking & margin-control models ([Voucherify — promotion stacking](https://www.voucherify.io/glossary/promotion-stacking)), rule/priority-based cart promotions ([Magento 2 cart price rules](https://www.mgt-commerce.com/blog/magento-2-cart-price-rules/), [SAP Commerce promotion best practices](https://expert-soft.com/blog/10-technical-best-practices-for-working-with-promotions/)), promotion-engine capability surveys ([Uniqodo](https://www.uniqodo.com/post/the-5-best-promotions-engines), [Elastic Path promotions](https://documentation.elasticpath.com/commerce/docs/core/platform/commerce-manager/promotions.html), [discount-engine scaling notes](https://www.codesoltech.com/blog/coupon-discount-engine-development/)).
