
## offline_restart_count — stop the fleet erasing its own metric

- **O1 (APK, 303) — the counter is reported on every heartbeat.**
  `clsHttpFrame.SendHttpPollFrame` only added `OfflineRestartCount` /
  `OfflineRestartCountDatetime` inside `if (enableDebugMode)`, so 462 of 464
  machines never sent them. They are now always sent; `MqttConnectionLostCount`
  and `MqttStartDatetime` stay debug-only. Two ints on a 5-minute heartbeat.

- **O2 (mark1) — an absent key is "no news", not zero.** `VendDataService`
  case 'P' coalesced the missing key to 0 with `?? 0`, compared that against the
  real stored value, saw a difference and dispatched `SyncP`, which wrote the 0.
  Every reporting machine wiped its own counter within one heartbeat. Now gated
  on `array_key_exists`.

- **O3 (mark1) — `SyncP` only writes fields the payload carried.** It defaulted
  to `0` / `null` for absent keys, so it would clobber a good value even if
  dispatched by mistake. Now builds the update array from present keys only, and
  skips the write entirely when nothing was reported.

**Evidence this was live:** only 6 of 464 active machines had a non-zero counter,
and every one fits the theory — the two that are ONLINE (1185 = 1922 restarts,
2642 = 39) are exactly the two on the debug-mode-ON profile, and the other four
(4614/4615/4616/4622) have been OFFLINE since July, frozen before their own
heartbeat could wipe them.

Regression coverage: `tests/Feature/SyncPOfflineRestartCountTest.php` — verified
that only the missing-key case fails against the old code, while a real update
and a genuine explicit 0 both keep working.

**Note the ordering:** O2/O3 stop the corruption and can deploy without a build,
but they do not by themselves produce data — debug-off machines still report
nothing until O1 ships in 303. Both halves are needed before the counter can
answer whether the offline auto-reboot is worth keeping.
