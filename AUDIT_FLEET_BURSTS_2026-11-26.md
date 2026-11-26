# Fleet bursts audit — 2026-11-26

What can make many machines call mark1 at the same moment, after the outage of 2026-11-26 10:18–10:28 (SG down for
every user). Sources: the mark1 code at `3de43ec138`, the big-board APK at tag `v330-official`, and the SG nginx
access logs 2026-11-12 → 11-26.

## The outage

- 10:09–10:21 an admin ticked Halal / Healthier Choice on product after product (5 saves in 10:17–10:18).
- Each save sent every board carrying the product the full menu re-read (TYPESYNCAPICHANNELSLOTLIST), which
  re-downloads every channel photo.
- The photo endpoint downloaded each photo from Spaces and resized it on every request. ~224 boards × ~60 photos
  arrived together (~1,800 photo requests a minute against ~3 normally); the 20 PHP-FPM workers filled, boards gave up
  at 10 s and asked again, nginx ran out of connections, every page timed out.
- Knock-on: 201 boards rebooted themselves (`offline_reboot` 247 by 11:00 against 165 for all of 11-25), and 4 Air724
  modems were reset at 10:36 because the boards had only looked silent.

## Evidence from two weeks of logs

| When | What | Load |
|---|---|---|
| 16 Nov 10:40 | 328 published + Push OTA check: whole fleet installs, restarts, re-reads everything | 3,493 requests/min, 1,428 photos |
| 22 Nov 08:29, 18:37 | 329, 330 the same | 1,456 and 2,147/min |
| Every hour :58–:59 | Happy Hour slot pick/start nudges every campaign member at once | ~440 `/thumbnails` reads in the minute; p90 3.6 s (0.15 s otherwise) |
| Every hour :37–:38 (since 330) | Boards' hourly media check, in step since the same install minute | ~130 each of 4 media lists; cheap (0.05 s) |
| 25 Nov 10:34 | A menu re-read wave (photos) | 560 photo requests, 646 s of PHP time |

## Triggers and what was done

### Server side (mark1)

| # | Trigger | Reach | Status |
|---|---|---|---|
| 1 | Product save (name, Halal, Healthier, description, labels) | Every board carrying the product | **Fixed 10:33** `700e939e66`: light re-read (no photos) unless the photo changed |
| 2 | Photo endpoint: download + resize per request | Every photo request | **Fixed 10:33** `5f1d3d6f5c`: cached by photo URL. **Hardened today**: lock so one worker makes it, 5 s Spaces timeout, a failure waits a minute, expiry staggered over a day, the 24 h `Cache-Control` removed (the URL is per channel: a re-mapped channel could have kept the old photo a day) |
| 3 | Mapping/product menu push (`PushMappingMenuSync`) | Whole mapping | **Paced** `3de43ec138` |
| 4 | UI Setting auto-push (`PushApkSettingSync`) | Every machine on the setting | **Paced** `3de43ec138` |
| 5 | Payment menu push | Operator / all | **Paced** `3de43ec138` |
| 6 | Mapping bind (many machines) | Selected machines | **Paced** `3de43ec138` |
| 7 | Happy Hour "no UI Setting" welcome refresh | Campaign members | **Paced** `3de43ec138` |
| 8 | UI Setting **Push button** and unbinding machines on save | Every machine on the setting | **Paced in this patch** (it bypassed #4) |
| 9 | Happy Hour planner: slot pick (−2 min), slot start (−1 min), cancel/swap/sold-out, campaign save | Every member; group campaigns = whole fleet | **Paced in this patch** (`menu_auto`, not before 5 s). Measured 11:58 vs 10:58: this wave was already spread over ~30 s by the planner's own loop, so little changed. The real hourly peak is the boards' own re-read 45 s before slot end (11:59:15–:20, ~45 reads/s) — board-side item C, fixed in APK 331 |
| 10 | Welcome sketch goes live | Every freezer / 315+ board with the product | **Paced in this patch** (not before 90 s) |
| 11 | Next-visit voucher settings save | Every 330+ board of the operator group | **Paced in this patch** |
| 12 | Push OTA check button | Every active machine on the channel | **Paced in this patch** (2 machines/s) |
| 13 | `ota:nudge-stale` (every 30 min at night, unreachable by day) | Every stale machine | **Paced in this patch** |
| 14 | Modem reset after a mark1 outage | Every Air724 board that looked silent | **Capped in this patch**: 3 per minute (`MODEM_RESET_MAX_PER_RUN`) |
| 15 | Site RP change, pricing switch, Grab store change | One Site / store (1–few machines) | Left: small reach |
| 16 | `RetryVendJobs` | Gated to 2007 only | Left; would be fleet-wide if the gate went |
| 17 | `GET /api/vends/operators/{code}/update-dcvends-countries` | Every machine of an operator, **no login, no throttle** | **Open — Brian's call**: the board ignores the frame (no load), but anyone can make mark1 publish to a whole operator. No caller found in mark1, cms or mqtt-config. Suggest removing the route. |

Pacing (`MachineNudgePacer`, `vend.nudge_pace`): one clock for all senders, 10 units/s; a full menu, settings or OTA
check weighs 5 (2 machines/s, the fleet in ~2 min), a JSON-only frame 1 (10/s, the fleet in ~23 s). A frame waiting
for a machine swallows the same request again. `MACHINE_NUDGE_UNITS_PER_SECOND=0` restores send-at-once.

### Board side (APK 330) — needs an APK release, not done

| # | Behaviour | Effect | Suggested change |
|---|---|---|---|
| A | **Offline reboot**: HTTP to mark1 failing for 6 min → reboot (then hourly) | A slow mark1 reboots the whole fleet, which then re-reads everything while mark1 is still recovering (201 boards today) | Don't reboot when MQTT is up (the network is fine, mark1 is the problem); random delay before the startup fetches |
| B | `/parameters` asked every 1 s at start until 5 answers; a timeout never counts | No limit while mark1 is slow; the queued calls all land when it recovers | Count failures, back off (5, 10, 30, 60 s) with random delay |
| C | Happy Hour re-read at slot end −45 s on the synced clock | Every campaign board in the same second, every slot | Random offset within the 45 s |
| D | Media retries at fixed 1/2/5/10/30 min, hourly refresh from app start | Boards that failed together retry together | Add random delay |
| E | Photos re-downloaded after 24 h even if unchanged (no ETag) | Every restart > 24 h after the last download pulls every photo | Send an ETag / per-product URL and skip unchanged photos |
| F | OTA install at 1000‰ → ~220 restarts within 11 min | Startup burst, then timers in step | Server pacing (#12) spreads the checks; keep staging rollouts (100 → 300 → 1000‰) |

### Capacity — Brian's call

- `php8.4-fpm` `pm.max_children = 20` on a 16 GB server (~5 GB free, ~90 MB a worker): ~40 would fit. Needs Forge.
- Per-IP limits (`/ota/manifest` 120/min, the voucher limiter): nginx already logs the real client address from
  Cloudflare, but many boards share their SIM carrier's addresses (223.119.20.x, 38.146.206.x, 38.130.151.x in the
  logs), so one limit is shared by a group of boards. Fine at today's paced rates; keep in mind before tightening.
