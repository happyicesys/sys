# 10.000 Timing Game — cross-project plan (2026-08-21)

> **Status: PLAN ONLY — nothing implemented.** Written after reading mark1,
> `apk/mark1-apk`, `apk/smart-freezer` and the live DB. Every file/line cited
> was verified on 2026-08-21; re-verify before coding.
> Mock-ups: see the "10.000 Challenge" design canvas (link in chat) — VM idle
> entry, game states, freezer checkout variant, mark1 settings card, Sales
> Transactions row.

## 0. The game

The viral "10 seconds" challenge, on the machine screen:

1. Customer taps **START**. A stopwatch runs from `0.000`.
2. Digits are visible for the first `hideAfterMs` (default **3000 ms**), then
   the readout hides (dots). Customer has to *feel* the remaining seconds.
3. Customer taps **STOP**. Elapsed is measured with `SystemClock.elapsedRealtime()`
   (monotonic, not wall-clock).
4. **Hit** = `|elapsed − 10000| ≤ toleranceMs` (default **50 ms**).
5. **Win** = hit AND a uniform roll `< winRatePercent/100` (default **100 %** —
   probability is a cost lever for later, not a default).
6. Win → one unit of the configured **reward product** is dispensed / added at
   **$0** and recorded in `vend_transactions` tagged as a game reward.
7. Guard-rails: date window, **max wins per machine per day**, **cooldown
   between plays**, and the entry point is hidden whenever the reward product
   has no vendable stock.

Lose screen always shows the miss ("10.327 — off by 0.327 s") and a **buy-one**
CTA into the normal order flow — the game is a footfall + upsell tool.

## 1. Decisions (recommended; confirm before build)

| # | Decision | Why |
|---|---|---|
| D1 | **Config lives in the APK Settings profile** (`apk_settings.settings_parameter_json`), registered in `ApkSettingParameters::SCHEMA`, edited on `resources/js/Pages/ApkSetting/Edit.vue` (page header "Edit UI Setting"). | That is the only settings bag the terminal actually reads (`VendController::getVendParameters`, `routes/api.php:87`); it already carries the enable/start/end pattern for Buy1Free1 and label-promo; one SCHEMA line = defaults + validation + wire order + UI metadata. `vends.settings_parameter_json` (per-vend `Setting/Parameter.vue`) is dead — never read. If "Setting Edit" meant the **per-machine** page (`Setting/Edit.vue`), add a `vends.is_timing_game_enabled` wire-time override exactly like `selectedPricingSource` (`VendController.php:3428-3431`); the profile stays the source of the parameters. |
| D2 | **Transaction = PAY_TYPE 10 "Free Vend"**, `amount 0`, plus a new `game` object in the TRADE payload, plus new columns `reward_source` / `reward_product_id` / `reward_qty` on `vend_transactions`. | Free Vend already exists (`payment_methods.code 10`, id 9). `RemoveOddTransactions` deletes every `amount = 0` row nightly **unless** its payment method code is 10/11 — any other marker gets swept. `GpMetricsAggregator` / `DailyFactsBuilder` already filter `amount > 0`, so rewards never pollute sales/GP. A *new* PAY_TYPE is rejected: the byte is also sent down the serial link to the VMC (`ThreadForBrd.java:432`). |
| D3 | **Win decided on the device**, audited on the server. | The game must work offline and has no payment round-trip. The TRADE carries `elapsed_ms, tolerance_ms, roll, win_rate, wins_today` so mark1 can audit/flag anomalies. The APK is ours; a rigged device is a physical-access problem, not a protocol one. |
| D4 | **Reward product → channel on the device, fail-safe.** `productId` → channel list from the thumbnails mapping → first channel passing `chkSlotValidForPay`. None available → game entry hidden. | Never promise a prize the machine cannot drop. Reuses the same stock rule the order flow uses. |
| D5 | **Trigger mode is config**: `idle` (free play from attract screen), `after_purchase` (offered on the thank-you screen), `checkout` (freezer: before payment, prize appended at $0 to a *paid* order). Default VM = `idle`; default freezer = `checkout`. | On a vending machine a free dispense is channel-controlled. On the freezer a free door-open with no card hold is an abuse vector (take more than the prize) and v1 Zijia host treats our opens as unpaid; riding the prize on a paid order keeps the hold + AI validation in place. |
| D6 | **Phasing**: Phase 1 = mark1 + `mark1-apk` (big board, **30x stream**). Phase 2 = `smart-freezer`. `mark1-apk-small` = **out of scope** (it has no `/parameters` fetch, no `clsSettingPromoParam`, no free-vend path, no fragments). | Verified by grep; porting the settings machinery to the small board is its own project. |
| D7 | **Giveaway cost is reported separately**, not in GP dashboards. | Free Vend rows today land with `unit_cost 0` (prod rows 5776115/5776120). Fix cost resolution for `amount 0` in `VendTransactionService` and add a "Game Rewards" report (count, COGS, hit rate) rather than bending `gp_metrics`. |

## 2. Config keys (new `ApkSettingParameters::SCHEMA` entries, group `timingGame`)

Wire contract follows `tests/Feature/VendParametersWireContractTest.php`: bools are the
**strings** `'true'|'false'`, ints are JSON ints, dates `Y-m-d|null`.

| key | type | default | validation | meaning |
|---|---|---|---|---|
| `enableTimingGame` | bool | `'false'` | — | master switch |
| `timingGameStartDate` / `timingGameEndDate` | date | `null` | `date_format:Y-m-d`, end ≥ start | window (same semantics as `labelPromoStartDate/EndDate`, checked on device with `CommonFunctions.checkWithinDate`) |
| `timingGameTrigger` | enum | `'idle'` | `in:idle,after_purchase,checkout` | see D5 |
| `timingGameProductId` | int | `0` | `exists:products,id` (0 = unset ⇒ game hidden) | reward product |
| `timingGameToleranceMs` | int | `50` | `1..1000` | hit band |
| `timingGameWinRatePercent` | int | `100` | `0..100` | probability applied after a hit |
| `timingGameHideAfterMs` | int | `3000` | `0..10000` | when the readout hides (0 = always hidden) |
| `timingGameMaxWinsPerDay` | int | `3` | `0..100` | per machine per local day (0 = unlimited) |
| `timingGameCooldownSec` | int | `600` | `0..86400` | min gap between plays on one machine |
| `timingGameHeaderText` | string | `'10.000 Challenge — win a free ice cream'` | `max:80` | entry-pill copy |

UI: a new "10.000 Timing Game" section on `ApkSetting/Edit.vue` (divider style
of "Branding & Display"), `MultiSelect` yes/no like `enableLabelPromo`,
`DatePicker`s, and the **product picker** pattern from
`ProductMapping/Edit.vue:196-213` (`ProductResource` `full_name`, `open-direction="bottom"`).
Controller: products list added to `ApkSettingController::edit` props (same
query as `ProductMappingController.php:960-974`). Validation is generated by
`ApkSettingParameters::validationRules()`; merge-on-update via
`VendParameterService::mergeCampaignParameter()`. Push unchanged:
`PushApkSettingSync` → MQTT `TYPESYNCSETTINGSPARAM` doorbell → device re-fetches
`/api/vends/{code}/parameters/{apkver}`.

## 2b. Visual direction (2026-08-21, after web research)

Beats shared by the viral versions (restaurant wall-button "free meal at 10.00",
the free-pizza challenge, Tenstop / Stopwatch Challenge apps): target shown up
front, **one** giant START/STOP control, digits masked mid-run (difficulty =
how long they stay visible), result as a **signed delta** ("+0.327 OVER"), a
start-beep and stop-click, and a best-record readout. The mock canvas carries
three directions; **A · Arcade Night is recommended**:

- Full-screen dark stage over the idle screen (not a white card); 10-segment
  dial lights one tick per second for `hideAfterMs`, then goes dark and pulses.
- Digits in a monospaced display face (tabular), giant physical-style STOP disc
  (green START → red STOP) — reads from ~3 m.
- Result tiers: `PERFECT` (win) · `HIT · UNLUCKY ROLL` (hit but probability
  failed) · `SO CLOSE` (<150 ms) · `NOT BAD` (<500 ms) · `WAY OFF`. Over-shoot
  drawn as a red arc past 12 o'clock; under-shoot as unlit ticks.
- Win: dial turns gold, confetti, prize card (name, slot, $0.00, DROPPING).
  Lose: ruler marker + ORDER NOW upsell + "next free try in mm:ss".
- Audio: short beep on START, click on STOP, chime on win (APK `SoundPool`;
  assets ≤ 50 KB each, no `getIdentifier()`).
- "Best today on this machine" + "plays today" = device-local prefs
  (`timingGame.bestMsDate/bestMs/playsCount`), reset at local midnight; not
  synced (optional later via the Phase 1b `GAME` frame).
- Freezer reuses the same dial on the brand-deep navy stage with accent-yellow labels.

## 3. mark1 data model + ingest

Migration `add_reward_columns_to_vend_transactions`:

```php
$table->string('reward_source', 32)->nullable()->index();   // 'timing_game'
$table->unsignedBigInteger('reward_product_id')->nullable();
$table->unsignedTinyInteger('reward_qty')->default(0);
```

`VendTransactionService::processInput()` (`:769-874`): when `$input['game']`
is present → `meta_json['game'] = $input['game']`, `reward_source =
$input['game']['type']` (`timing_game`), `reward_product_id`, `reward_qty`.
Both `createVendTransaction()` and `applyTradeToPreCreatedRow()` set the columns.
`VendTransaction` model: fillable + `casts`. Constants:
`VendTransaction::REWARD_SOURCE_TIMING_GAME = 'timing_game'`.

Freezer `checkout` mode: the TRADE is a *paid* transaction (PAY_TYPE = real
rail, `amount` = paid total); the prize is an extra `transf_info` line with
`Price: 0`; `game` object sits at transaction level with `reward_qty 1`. Same
columns, same report.

TRADE payload addition (both APKs):

```jsonc
"game": {
  "type": "timing_game",
  "elapsed_ms": 10004,
  "tolerance_ms": 50,
  "hit": true,
  "roll": 0.2317,          // uniform [0,1) used for the probability gate
  "win_rate": 100,
  "wins_today": 1,         // after this win
  "product_id": 785,
  "channel": 14,
  "apk_setting_id": 50,    // profile that was live (from /parameters; add `id` to the payload)
  "trigger": "idle"
}
```

Reporting:
- Sales Transactions page: "Reward" badge + filter (`reward_source`).
- New `reports/game-rewards` (or dashboard card): wins/day/machine, COGS
  (`unit_cost`), hit distribution from `meta_json.game.elapsed_ms`.
- **Optional (Phase 1b):** a lightweight `GAME` frame for *every* play
  (win/lose) → `vend_game_plays` table, so conversion ("lost → bought") is
  measurable. Not needed for the reward itself.

## 4. mark1-apk (big board) — implementation plan

Touch points (all verified paths):

| Step | Where |
|---|---|
| DTO | `cvmqttmodule/.../para/clsSettingPromoParam.java` — add the 10 fields; **boxed** `Boolean`/`Integer` + null-guard before persisting (the `isGrabActivated` idiom, `:67-68` / `Main2Activity.java:7848-7850`) so an older mark1 never clears the setting. |
| Persist/apply | `Main2Activity.updateSettingsPromoParam()` `:7811-7936` → `SharedPreferences "Settings"`; `applySharedPreferences()` `:7699-7795` reads them and toggles the entry pill; counters `timingGame.winsDate`, `timingGame.winsCount`, `timingGame.lastPlayAt` in the same prefs. |
| Entry point | `activity_main2.xml` `frameLayout` (`:159-179`): a pill **above `btnPulse`** (inside the existing bottom scrim, so no dock tile is displaced — the dock is already full at 5×132dp). Visible iff enabled ∧ in window ∧ trigger=`idle` ∧ reward channel vendable ∧ cap/cooldown OK. For `after_purchase`, the same card is offered from the carousel's "done" overlay (`fragment_carousel_withrecyclerview.xml:468-600`). |
| Overlay | New `pageTimingGameDialog extends pageDialog` (template: `pagePromoInfoDialog.java`, 164 L) — full-screen scrim, 600dp card, states READY → RUNNING → HIDDEN → RESULT. Timer: `SystemClock.elapsedRealtime()` at the start tap and stop tap; UI tick via `Choreographer`/`Handler` 16 ms; the **measured** value is the tap timestamps, not the rendered digits. |
| OTA guard | `Main2Activity.otaBusyReason()` `:6035-6096` must treat a showing game dialog as busy (same class of bug as the QR/grab dialog entries). |
| Idle timer | use `MyDialog`-style touch forwarding so the idle watchdog sees taps; hard cap one game session at 60 s. |
| Dispense | new `clsCmdConst.ON_START_GAME_REWARD` (next free id in the `0x7xx` range) handled in the message pump (`:1352-2321`) by cloning the **`ON_START_DROP_FREE_ITEM` path** (`:2229-2309`): `OrderBean` with `PAY_TYPE = TRADE_TYPE_FREE_VEND (10)`, `Price "0"`, `TXN_SRC 1`, one `Shipment_info` on the chosen channel → `mPreTransfor()` + `onDispensingProductsForSoftKeyboard()`. |
| Channel choice | `productId` → `clsChannelSlot.product_id` map (thumbnails fetch, `clsHttpManager.java:1265-1428`) → candidate channels → `StaticFunction.chkSlotValidForPay()`; `ChkOrderCanBeTransfor` is still the last line of defence. |
| TRADE tag | `clsTradeRet` gets a `game` object (new POJO `clsGameResult`); `OrderBean` carries it from the dialog to `mUploadTradeRet()`. Gson serialises it; older mark1 ignores it. |
| Result | `ISOK == 1` on the TRADE echo → "Enjoy!"; `SErr ≠ 0` → "Sorry, the slot jammed — no charge" (and **don't** count the win against the daily cap). |
| Assets | vector icons only (no `getIdentifier()` — shrinkResources rule). Strings in `strings.xml` (translatable). |
| Version | ships in the **30x stream after 303 is bench-tested and published** (303 is still the OTA candidate; do not fold the game into it). |

Accuracy note: touch→event latency on these boards is ~20–40 ms and the same on
START and STOP, so it largely cancels; the default `±50 ms` band is fair but
tight — expect single-digit-percent hit rates. That is the intended lever.

## 5. smart-freezer — implementation plan (Phase 2)

Pre-requisites that do not exist yet (from the code survey):
1. **No settings fetch.** `PushCoordinator.handleConfigChanged(SETTINGS)` only
   invalidates the QR menu. Add a `Mark1ParametersRepository` that GETs
   `/api/vends/{code}/parameters` (Moshi, unknown keys ignored), cached on disk
   like `CatalogSnapshotStore`, refreshed on boot + on `ConfigChanged(SETTINGS)`.
2. **Unlock authorizer is still the stub** (`StubDoorUnlockAuthorizer`) and the
   v2 BoxSDK path is unverified on device (`SDK_V2_AUDIT.md`). The game must
   not be the thing that first exercises a free unlock.

Design (trigger = `checkout`, D5):
- New UI-local game state on `PaymentSelectScreen` (no `TransactionEvent`
  needed until the win): "Play 10.000 before you pay" card under the payment
  tiles; game runs full-screen; on win → `TransactionEvent.AddItem(prize, unitPrice = Money.ZERO, channel)`
  (a `Cart` line with `unitPrice 0` and a `reward` marker — `CartLine` needs the
  field), total unchanged, payment proceeds normally, the door opens **once**
  under the real hold, AI validation covers the prize unit.
- `Mark1SaleUploader.tradePayload()` emits the prize as a `transf_info` line
  with `Price: 0` and the transaction-level `game` object; `SaleWireDto` gains
  `g` (game JSON) so the outbox round-trips it.
- `idle` (free-play) mode on the freezer is **deferred** until (a) the signed
  unlock authorizer exists and accepts `amount = 0` with a game proof, and (b)
  v2 `orderOpenDoor` is proven with WAN blocked. Even then it needs a
  "prize-only door open" policy because nothing stops a customer taking more.

## 6. Guard-rails / economics

- `timingGameMaxWinsPerDay` (device-enforced, audited server-side via
  `wins_today`), `timingGameCooldownSec`, date window, stock-aware entry.
- Probability is a **second** lever after tolerance; start with tolerance.
- A play that ends in a dispense error does not consume the cap.
- Game session auto-closes after 60 s idle; machine returns to attract screen.
- Hidden-digit phase is what makes ±50 ms hard; `timingGameHideAfterMs 0`
  (always hidden) is the "hard mode" switch.

## 7. Test plan

mark1: `VendParametersWireContractTest` extended for the 10 keys; processInput
test for `game` → columns + `meta_json`; `RemoveOddTransactions` retention test
for a PAY_TYPE-10 reward row; `ApkSetting` update validation test.
Device: bench rig **machine 2031** (Brian's ADB terminal, operator TEST —
rewards there are PAY_TYPE 10, so they **survive** the nightly sweep; clean up
manually). Verify: entry pill gated by stock, dialog states, dispense, TRADE
payload in `payment_gateway_logs`/`vend_transactions`, OTA-busy guard, idle
timeout, daily cap rollover at local midnight.

## 8. Open questions

1. Which page did you mean by "Setting Edit" — the APK Settings **profile**
   (recommended, D1) or the per-machine `Setting/Edit`? (Can do both: profile
   holds parameters, per-machine toggle overrides.)
2. Reward: one product, or an ordered list / any product under $X as fallback?
3. Defaults: tolerance 50 ms, win-rate 100 %, 3 wins/day, 10 min cooldown —
   adjust?
4. Log every play (win + lose) for conversion analytics (Phase 1b), or wins only?
5. Freezer: agree that Phase 2 runs in `checkout` mode (prize rides on a paid
   order) and free-play waits for the signed unlock?
