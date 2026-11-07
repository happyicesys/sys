# Controller silence audit — 2026-11-07

Brian asked: machines now often stay online while the controller (VMC / thermostat) stops reporting. Is this
new? Is it ours? Audit of every active vending machine, from prod `vend_data` (7 days, 31 Oct – 6 Nov),
`vend_temps` (14 days), `vend_temp_metrics` (daily reading counts back to Oct 2025) and `vend_daily_stats`.

## Verdict

**Mostly ours, but not the APK and not a new release.** The silences people now see are:

| Kind | Machines | Cause | Ours? |
|---|---|---|---|
| A. 20–30 min gaps, several a day | 52 of the 55 Air724 "square module" boards, every day | mark1's own modem-reset loop (below) | **Yes, mark1 server** |
| B. Controller really silent for hours, board alive | 2351 (2–4 Nov, again 4–5 Nov); 2083 (no controller data since Apr 2025, no site) | VMC / serial cable / UART | No: hardware |
| C. Off every night ~17:45–06:30 | 2129 (whole machine), 2363 (controller off, board stays on) | Site power schedule | No |
| D. Several machines at once, 1.5–8 h | 2731, 2840, 2584, 2007, 2826, 2671 (31 Oct 15:2x, 2 Nov 06:2x, 3 Nov 23:4x) | Site power cut | No |
| E. Probe error, controller fine | 2573 (T1 = 32767 since 3 Nov) | T1 probe | No: hardware (alert 1C built) |

Nothing got worse with an APK release. VENDER gaps of 30–59 min stayed at 6–19 a day from 31 Oct to 6 Nov
(325 went out on 4 Nov). The board's offline reboots have stayed at about 300 a day on about 90 machines since that
counter began on 17 Oct. The serial code is unchanged from v305 to v325 and from v12 to v14 of the small board (code
review this session). What is new is the **(1B) Controller not responding** alert of 5 Nov: it made visible
gaps that nobody saw before, because MQTT stayed up and the offline alert never fired.

## A. The modem-reset loop (mark1, `SyncOnlineStatus`)

`SyncOnlineStatus` runs every minute. On a machine with a resettable modem (Air724UGB4, `modem_types.is_resetable`)
it publishes `RESET` to the modem **every minute while `vends.last_updated_at` is 5 minutes old or more**. This rule
has been unchanged since at least June 2025.

A board's HTTP traffic is its own P heartbeat (~301 s) plus the VMC's VENDER (~300 s). So "5 minutes without HTTP"
is the normal gap plus a few seconds. `last_updated_at` is also written by a queued job (`UpdateHttpLastUpdated`,
`default` queue, 30 s debounce), so queue latency adds to it.

One late packet starts a loop. Machine 2388 on 6 Nov:

- 01:05:52 last VENDER over HTTP.
- 01:11, 01:12, 01:13, 01:14, 01:15, 01:16 — an MQTT reconnect (PWRON) **on every minute**: mark1 resetting the
  modem, one reset per run of the job.
- 01:18 the app's own offline reboot (`f` back to 8): 6 minutes with no HTTP. The resets go on.
- 01:23 one HTTP packet gets through, `last_updated_at` is fresh, the resets stop, VENDER resumes at 01:25.

Each loop is a 20–30 min gap. 1B reports it as "Controller not responding", because `temp_updated_at` stops while
other traffic still arrives.

Offline reboots, 31 Oct – 6 Nov, app 325:

| Board's modem | Machines | Reboots / machine / week |
|---|---|---|
| Air724UGB4 (square module), reset by mark1 | 51 | 28.1 |
| Quectel EC25 (built in), never reset | 150 | 2.7 |

52 of the 55 resettable-modem machines rebooted on every one of the 7 days. The 3 that did not are 2804, 2137 and
2294. The worst are 4406, 2845, 4417 and 2489 (38–42 a week).

**Fix (built 2026-11-07, `App\Support\ModemReset`, `tests/Feature/ModemResetTest.php`):**
- Reset the modem only when the board is unreachable by **both** HTTP and MQTT. While MQTT is up the modem is
  working, and a reset only breaks it.
- Only when the modem itself was heard within 15 min (the RESET reaches it over its own link).
- Wait 10 min, at most one reset per 30 min per machine, sent at QoS 0 (no queued RESETs replayed on reconnect).
- Log each reset to `vend_logs`, so it can be counted and shown on the timeline.

## B. Real controller silence

- **2351** (small board v14, ZC-83A): no VENDER from 2 Nov 14:49 for 41 h, then again 4 Nov 11:02 (231 min),
  20:18 (165 min), 23:50 (75 min) and 5 Nov 13:47 (237 min).
  - In the 4–5 Nov gaps the frame counter `f` kept rising with no restart, so the board was alive. The app's serial
    loop logs `serial silent … reopening` every ≤ 30 s (thousands of lines), so the app kept trying and nothing
    came back on the wire.
  - Check the VMC, its power and the serial cable. Pull the board log around 4 Nov 07:49: a `Link` handshake or a
    VMC reset flag before reports resumed would show the VMC itself rebooted.
- **2083**: no site, controller silent since 2025-04-24 while the board stays connected. 1B lists it as > 12 h for
  good. Park it (inactive) or fix it.

## Watch-outs

- 1B will also fire every evening for **2363** (Zoo, controller off overnight, board on) and for the reset loops
  in A until the fix ships.
- `vend_data` keeps ~7 days. Repeat this audit from it before the evidence ages out.
- The daily T1 reading count per machine fell from ~650 (Jul) to ~320 (Nov) across the fleet. It looks like
  duplicate frames stopping, not a loss, but it means a fixed "short day" threshold is not comparable across months.
