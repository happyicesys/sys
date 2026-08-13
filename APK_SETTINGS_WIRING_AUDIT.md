# apk_settings ↔ terminal wiring — audit

**Date:** 2026-08-13 · **Scope:** the full chain from the `apk_settings` editor
to the terminal's `SharedPreferences` and back. Verified against the mark1
working tree, the mark1-apk v302 tree, and the live production DB (read-only).
**No mark1 code was changed** except one additive Vue option (`mixed` banner
kind, `ApkSetting/Edit.vue`). Fix list at the end, ordered by leverage.

## Verdict in one paragraph

`apk_settings` is structurally the right place for the source of truth, and the
read path (`GET /api/vends/{code}/parameters/{apkver?}`) genuinely works — but
today it is the source of truth for **less than half the active fleet**, the
push machinery behind it is **hard-gated to one beta machine**, and the most
prominent editing page (`Setting/Parameter.vue`) **writes to a column the
terminal never reads**. The pieces exist; several of the wires between them are
not carrying current.

## Production reality (queried live, 2026-08-13)

| Fact | Value |
|---|---|
| `apk_settings` rows | 33 |
| Vends bound via `apk_setting_vend` | 206 (no vend bound twice) |
| Vends with a sale in the last 30 days | 404 |
| **Active vends with NO settings binding** | **205 (51%)** — their `/parameters` call 400s; the APK retries 5× at boot, then silently runs on local prefs forever |
| Legacy `vends.settings_parameter_json` rows | 1,115 — still being edited by `Setting/Parameter.vue`, read by nothing on the terminal path |

## The chain, piece by piece

### 1. Editor → database — MOSTLY WORKS, two real bugs

- `ApkSetting/Edit.vue` → `ApkSettingController::update` → `AsApkSettingParameters`
  cast → `ApkSettingParameters::fromArray()` (whitelist, defaults, canonical
  order). Sound design; adding a key = one DEFAULTS line.
- **BUG — cross-operator unbinding.** `update()` does
  `$apkSetting->vends()->sync($request->vends)` with the vend list the
  *operator-scoped* user could see. `sync()` detaches everything else — an
  operator-restricted user saving a shared setting silently unbinds other
  operators' machines (`ApkSettingController.php:250`).
- **BUG — blank address becomes `"\n\n"`.** `Edit.vue:1623` joins three address
  lines unconditionally. Combined with the APK's `!TextUtils.isEmpty()` guard,
  this non-empty junk **overwrites a good address** on every machine on every
  sync.
- Four DEFAULTS keys (`enableHeaderTextRunning`, `enablePromoRunningText`,
  `runningTextStartDate/EndDate`) have **no fields in this editor** — they are
  only editable on the page that writes the dead column (§5).

### 2. Database → terminal (read path) — WORKS, with sharp edges

- Route confirmed: `api` prefix, **no auth, no rate limit** (`Limit::none()`).
  Anyone who can guess a small-integer vend code can read company details and
  the full pricing/campaign config. Worth a per-device token eventually
  (REMOTE_SETTINGS_PLAN in mark1-apk proposes the shape); at minimum restore a
  sane throttle.
- `$apkVer >= 213` correctly strips `promoLabelItems` (the APK ignores it).
- **Unbound vend → 400 → silent dead end.** The APK's error branches are
  literally empty; the machine keeps stale prefs with no log and no telemetry.
- `->first()` on the settings relation has **no ordering** — if a vend is ever
  bound twice (schema allows it), which row wins is MySQL-arbitrary.
- Unguarded `PROMO_TYPE_MAPPINGS[$item->promo_type]` — an unvalidated
  `promo_type` outside {1,2} 500s the terminal's read path.

### 3. Push path (server → machine "go fetch") — LARGELY INERT

- **`VendJobService::dispatch` is hard-gated: `if ((string) $vendCode !== '2007')`**
  (`app/Services/VendJobService.php:26-35`). For every machine except the beta
  unit: no `vend_jobs` row, no `vend_job_id` in the payload, so —
  - the every-minute `RetryVendJobs` command has nothing to retry,
  - the `JOBAPKSETTING` ack from the machine matches nothing and is discarded,
  - the "last response at" column in the settings UI is blank fleet-wide.
- **QoS 0** on the settings push (explicit `0`, overriding the QoS-1 defaults).
  Fire-and-forget: an offline machine misses the push permanently. Nothing
  re-pushes on reconnect. The only self-heal is the APK's boot-time fetch.
- Saving in `ApkSetting/Edit.vue` does **not** push (deliberate — the loop is
  commented out); the user must press Push. But the sibling vend-level page
  auto-pushes on save. Two different mental models for the same feature.
- `syncApkSettings()` is duplicated verbatim in two controllers; the fallback
  private key `'123456789110138A'` is hardcoded in both.

### 4. Machine → server (report path) — WORKS mechanically, one design gap

- `FEATUREAPKSETTING` → 6 capability flags written onto `vends`. But
  `is_enable_grab_collection` is written **only** by the machine and then echoed
  back as `isGrabEnabled` — the server cannot set grab; it mirrors. And the echo
  is doubly dead on the APK: the DTO field is named `isGrabActivated` (Gson name
  mismatch, stays null) *and* its consumer is commented out.
- A partial `FEATUREAPKSETTING` **nulls** absent flags rather than leaving them.

### 5. The dead-column page — BROKEN BY DESIGN

`Setting/Parameter.vue` (per-vend "Parameter" page) saves to
`vends.settings_parameter_json` and then fires a push — but `getVendParameters`
reads **only** `apk_settings.settings_parameter_json`. The operator sees a save
and a push; the machine re-fetches the **unchanged** `apk_settings` values.
Every edit on that page is a no-op with confirmation theater. Decide its fate:
point it at the vend's bound `apk_settings` row, or remove it.

### 6. Type contract — tolerable today, two live hazards

- The `'true'`/`'false'` **strings** the UI stores parse fine on the APK
  (Gson coerces into primitive booleans/ints). Not fatal, but the column is
  type-inconsistent between UI-saved and code-created rows.
- **HAZARD — nulls delete prefs.** Every DEFAULTS key that is `null`
  (`promoHeaderText`, `promoRunningText`, all 12 date keys, and any unset
  string) deserializes into a null String field, and
  `editor.putString(key, null)` **removes the pref** on the machine. Downstream
  reads then fall back to code defaults, not server values.
- **HAZARD — campaigns can't be turned off.** The APK clears its campaign array
  only *inside* the "response non-empty" guard (`Main2Activity.java:7346-7351`),
  so pushing an empty `campaigns: []` leaves the old campaign running forever.
- Small APK bug spotted in passing: on sync, the in-memory `companyUrl` variable
  is overwritten with the company *name* (`Main2Activity.java:7293`).
- A machine-local toggle (`selectedRemoteSetting = No`) silently vetoes every
  push, invisible to the CMS. (REMOTE_SETTINGS_PLAN keeps this as the escape
  hatch but adds drift reporting so the office can see it.)

## Fix list, by leverage

| # | Fix | Where | Effort |
|---|---|---|---|
| 1 | Remove the `!== '2007'` beta gate; QoS 0 → 1 | `VendJobService.php:26-35` | minutes — activates jobs, retries, acks, last-sync UI fleet-wide |
| 2 | Bind the other 205 active vends to settings rows (script or UI sweep); until then the "source of truth" governs half the fleet | data task | hours |
| 3 | `sync()` → operator-safe (merge with unseen bindings, or `syncWithoutDetaching` + explicit detach of visible ones) | `ApkSettingController.php:250` | small |
| 4 | Blank address → `null`, not `"\n\n"` | `Edit.vue:1623` | one line |
| 5 | Retire or repoint `Setting/Parameter.vue` (dead column) | product decision | — |
| 6 | APK: move `labelPromoGlobalArray.clear()` outside the non-empty guard so campaigns can be switched off | `Main2Activity.java:7346` | one line, next APK build |
| 7 | APK: fix `companyUrl = obj.getCompanyName()` mixup | `Main2Activity.java:7293` | one line, same build |
| 8 | Decide null semantics: server sends `""` instead of `null` for clearable strings, or APK treats null as "clear deliberately" | both sides | design choice |
| 9 | Rate-limit `/api/vends/*`; longer-term per-device token | `RouteServiceProvider` | small / later |
| 10 | Grab: rename `isGrabEnabled` → `isGrabActivated` AND uncomment the APK consumer — then decide if grab is server-authoritative | both sides | small, coordinated |

Items 6 and 7 are one-liners in the APK build currently in progress — they can
ride the next bench-tested release. Items 1–4 are mark1 changes that need no APK
release at all.

## Relation to the remote-settings plan

`apk/mark1-apk/REMOTE_SETTINGS_PLAN.md` (2026-08-13) assumed the push/ack rails
were live. This audit shows the rails exist but are gated (item 1) and lossy
(QoS 0, no reconnect re-push) — both are prerequisites for that plan's Phase 2
(convergence) and are cheap. The plan's Phase 3 (drift visibility) is exactly
what makes §4's silent gaps and the local-veto toggle observable.
