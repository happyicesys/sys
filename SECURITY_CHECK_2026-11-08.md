# Security check: who can change a temperature? (2026-11-08)

**Trigger:** other operators' freezers were hacked and their thermostats set above 0 °C, spoiling stock.
Brian asked for every one of our systems to be checked.

**Scope:** mark1 (web, API, servers), mqtt-config, the MQTT broker, the smart-freezer APK (v52), the
big-board and small-board APKs, and Citybox. Secret values are deliberately not written here.

## Verdict

**No sign that anyone has changed one of our temperatures.** Every freezer setpoint and compressor
command since 2026-09-16 (`freezer_control_commands`) came from named staff (Daniel, WenBin, Brian) or
from our own test runs. No setpoint schedules exist.

**But an outsider could, on the smart freezers, without any staff password.** This is the same
attack described in the trigger:

1. **mqtt-config hands out the broker login.** `POST /api/validate` returns the broker host, username
   and password to anyone who sends a freezer's IMEI with a signature. The signing key is the OEM's
   fixed key (`mqtt-config app/Models/VendMqtt.php:12`, also in the freezer's `BootstrapSigner.kt`).
   IMEIs are printed on the unit labels.
2. **Every command frame is signed with one fleet-wide key.** mark1 falls back to a hard-coded default
   (`config/vend.php:5`). Prod sets no `VM_DEFAULT_PRIVATE_KEY`, and none of the 5 active freezers,
   9 chillers or 223 of 451 active vending machines has a `vends.private_key` of its own. The freezer
   APK bakes in the same key (`build.gradle.kts:266`).
3. **The freezer obeys any well-signed `FREEZERCTL` frame:**
   - setpoint up to 0 °C (v39, by design);
   - compressor Off for up to 12 h (v40, by design);
   - door unlock, refused only during a sale;
   - `sdkcall`, which passes any controller action through with no value check
     (`RemoteControlParser.kt:87-96`, `BoxSdkClient.rawCall`), including `setTemperature` and
     `thermostatControl`. mark1 limits `sdkcall` to superadmins, but the device does not.

The broker refuses anonymous logins. It runs plain MQTT on 1883 (no TLS). Devices share one login per
settings group. Whether that login may publish to another machine's `CM<code>` topic was not tested,
because testing it needs the real password.

## All paths

| # | Path | Who | Gap | Severity |
|---|---|---|---|---|
| 1 | MQTT `FREEZERCTL` `sdkcall` → `setTemperature` / `thermostatControl` / compressor | Anyone with broker access + the fleet key | Device accepts raw controller writes | **Critical** |
| 2 | MQTT `FREEZERCTL` `setpoint` (≤ 0 °C), `compressor` Off (≤ 12 h), `unlock` | Same | Device limits do not protect stock | **Critical** |
| 3 | mqtt-config `/api/validate` | Anyone with one IMEI | Returns the broker password; key is fixed in source | **Critical** (enables 1–2) |
| 4 | One fleet-wide frame key | n/a | No per-machine keys; MD5(content + key) | **Critical** (enables 1–2) |
| 5 | mark1 `/register` | **Anyone on the internet** | Self sign-up made a logged-in account in operator 1, which sees the whole fleet | **High: FIXED 2026-11-08** |
| 6 | Freezer service panel | Anyone at the glass | 9 corner taps + one 4-digit PIN shared by the fleet; its unsalted hash is in source; 5-try limit resets when the pad reopens | High |
| 7 | Broker | n/a | Plain 1883, shared login per group | High |
| 8 | Big/small-board APK inbound MQTT | Anyone with broker access | Signature check commented out (`CvMqttService.java` `ChkSign`). No temperature command exists, but an `UPDATE` frame makes InHand boards (3 active) download and install an APK from any URL; a `RESET` frame publishes the board's local settings password | High (not temperature) |
| 9 | mark1 Setting/Edit → Remote controls | Staff with `update machine-settings` (28 active users); door needs `freezer-remote-door`; raw SDK needs superadmin | Permission-checked, scoped to the user's operator. Risk = a stolen staff password (no 2FA) | Medium |
| 10 | Citybox chillers | n/a | No temperature control through our systems | None |

**Checked and clean:**
- Prod SG and LSH: `APP_DEBUG=false`, Telescope off. Telescope, debugbar, clockwork, ignition and the log viewer all 404; Horizon 403; `.env` 403.
- SG firewall: MySQL, Redis and memcached are not reachable from outside. SSH keys are only Forge's and Brian's; password SSH is off.
- No account without a role exists. The 8 accounts made in the last 120 days are all known operator or product-owner accounts.
- Login is throttled to 5 tries per email + IP.

## Done

- **mark1 self-registration removed:** routes and controller deleted; `RegistrationTest` now asserts 404 and that no user is created.

## Needs Brian (in order)

1. **Freezer 53, device side (small, no key changes):**
   - refuse thermostat and compressor writes through `sdkcall`;
   - optionally lower the setpoint ceiling (0 °C today, Brian's v39 call) and the 12 h compressor-off cap (v40).
2. **Per-machine frame keys for freezers** (then all machines), delivered off MQTT. Then set a secret prod
   `VM_DEFAULT_PRIVATE_KEY`.
3. **Broker:** per-device logins with ACLs (read own `CM<code>`, publish upstream only), and TLS on 8883.
4. **mqtt-config:** stop returning the broker password for a bare IMEI (pairing, or per-device creds from 3).
5. **Freezer tech PIN:** per unit, set from mark1, with a lockout that survives reopening the pad.
6. **Big/small boards:** turn `ChkSign` back on; restrict `UPDATE` URLs to our own host.
7. **Staff accounts:** 2FA for roles with `update machine-settings`.
8. **Detection:** alert when a freezer reports a setpoint above −15 °C or a change mark1 did not send.
