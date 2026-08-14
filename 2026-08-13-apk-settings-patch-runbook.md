# APK-settings patch — deploy & rollback runbook

> 2026-08-13. Companion to `2026-08-13-apk-settings-revamp-audit.md`.
> The patch is code-only: **no migrations, no seeders, no APK deployment.**

## 1. Before deploying — create the rollback point

On the machine that pushes to the repo (tag what production currently runs,
i.e. the tip BEFORE the patch commit):

```bash
git tag mark1-pre-apk-settings-20260813 origin/main
git push origin mark1-pre-apk-settings-20260813
```

Then commit the patch (note `git add tests/` explicitly — 19 test files were
gitignore-shadowed until today's `.gitignore` fix) and push.

## 2. Deploy (on the server)

```bash
git pull && php artisan config:cache && php artisan horizon:terminate
```

- `config:cache` is required: `config/app.php` gained `raw_ack_vends`.
- `horizon:terminate` is required: workers must load the new
  `PushApkSettingSync` job class and the changed `VendJobService`.

## 3. Rollback — if anything looks wrong

**Emergency (fastest, detached HEAD — fix-forward later):**

```bash
git checkout mark1-pre-apk-settings-20260813 && php artisan config:cache && php artisan horizon:terminate
```

**Clean (keeps history, stays on main; use if the patch is one commit):**

```bash
git revert --no-edit mark1-pre-apk-settings-20260813..HEAD && php artisan config:cache && php artisan horizon:terminate
```

### Why rollback is safe (verified, not assumed)

- **No schema change** — nothing to migrate down.
- **Data written by the patched code is fully readable by the old code.**
  The old `fromArray()` passes values through verbatim; the normalized shape
  (string bools, real ints, `Y-m-d` dates) is inside what old code and all
  deployed APKs already accepted. Profiles saved during the patched window
  keep working after rollback.
- **Old frontend assets come back with the revert** (the build is committed).
- The only behavioral residue after rollback: rows saved while patched stay
  normalized (harmless), and `.gitignore`-recovered tests disappear again if
  you use the emergency checkout (they're in the patch commit).

## 4. Post-deploy health checks

### 4a. Old-APK transaction health (the "are old machines still selling" check)

Run via the sys-happyice MCP (or ask Claude to run it). Compares the last 6
hours against the same 6-hour window one week ago, split by APK version band.
Excludes payment methods that do not prove the APK is alive:
`2 = Card Terminal` (POS pad, bypasses the APK payment loop),
`26 = Grab Mart` (delivery platform), `29 = Remote Dispense` (ops).
`multi_item_txns` = soft-keypad cart purchases (`is_multiple = 1`).

```sql
WITH bands AS (
  SELECT v.id,
    CASE
      WHEN COALESCE(v.apk_version_code, CAST(JSON_UNQUOTE(JSON_EXTRACT(v.apk_ver_json,'$.apkver')) AS UNSIGNED), 0) >= 301 THEN '301+'
      WHEN COALESCE(v.apk_version_code, CAST(JSON_UNQUOTE(JSON_EXTRACT(v.apk_ver_json,'$.apkver')) AS UNSIGNED), 0) >= 213 THEN '213-300'
      WHEN COALESCE(v.apk_version_code, CAST(JSON_UNQUOTE(JSON_EXTRACT(v.apk_ver_json,'$.apkver')) AS UNSIGNED), 0) > 0 THEN 'below 213'
      ELSE 'unknown'
    END AS band
  FROM vends v
)
SELECT b.band,
  CASE WHEN t.transaction_datetime >= NOW() - INTERVAL 6 HOUR
       THEN 'last 6h' ELSE 'same 6h last week' END AS window_label,
  COUNT(*) AS apk_txns,
  SUM(t.is_multiple = 1) AS multi_item_txns,
  COUNT(DISTINCT t.vend_id) AS active_vends
FROM vend_transactions t
JOIN bands b ON b.id = t.vend_id
WHERE t.payment_method_id NOT IN (2, 26, 29)
  AND t.transaction_datetime <= NOW()
  AND (t.transaction_datetime >= NOW() - INTERVAL 6 HOUR
    OR (t.transaction_datetime >= NOW() - INTERVAL 174 HOUR
        AND t.transaction_datetime <  NOW() - INTERVAL 168 HOUR))
GROUP BY 1, 2 ORDER BY 1, 2;
```

> **`transaction_datetime <= NOW()` is required** (added post-deploy
> 2026-08-13): ~3,000 rows written Sep–Dec 2022 carry year-2070 timestamps
> from broken VMC clocks and match every "recent" window forever. None have
> been created since 2022-12-08 — but if
> `SELECT MAX(created_at) FROM vend_transactions WHERE transaction_datetime > NOW()`
> ever returns a recent date, a live machine's clock has broken: investigate.
> The baseline table below PRE-DATES this fix and is inflated by the junk;
> genuine evening rates are ≈55 txns/h across ~37 vends (213-300) and
> ≈33 txns/h across ~23 vends (below-213); the 'unknown' band is genuinely
> zero (its 9 machines died in 2022).

**Baseline captured pre-deploy (2026-08-13 ~19:00):**

| band | window | apk_txns | multi_item | active_vends |
|---|---|---|---|---|
| 213-300 | last 6h | 496 | 63 | 124 |
| 213-300 | last week | 502 | 60 | 134 |
| below 213 | last 6h | 768 | 0 | 108 |
| below 213 | last week | 548 | 0 | 123 |
| unknown | last 6h | 408 | — | 9 |

**Read it like this:** `active_vends` collapsing toward zero in any band, or
`multi_item_txns` going to 0 in the 213-300 band while `apk_txns` continues,
is the abnormal signal. `below 213` showing 0 multi-item is NORMAL (cart UI
did not exist before 213). Weather/day-of-week swings of ±30% on txn counts
are normal; a band-wide stop is not.

### 4b. Settings payload spot-check (the "did the wire change" check)

Before deploying, capture the payload for one machine bound to an
old-campaign profile (e.g. a profile-6/17/25 vend):

```bash
curl -s https://sys.happyice.com.sg/api/vends/<CODE>/parameters/212 > /tmp/params-before.json
```

After deploying:

```bash
curl -s https://sys.happyice.com.sg/api/vends/<CODE>/parameters/212 | diff /tmp/params-before.json - && echo IDENTICAL
```

Expected: identical, or only stringly-int → int (`"5"` → `5`) on the four
known profiles. Anything else = investigate before the fleet's next reboot
cycle picks it up.

### 4c. Bench checks

- Vend **2031** (Brian VM Testing) is the only machine whose `/vend-data` ack
  format changes (`raw_ack_vends` staged default). Confirm it still vends.
- Vend **2007** (JB IT Testing) is the only machine with settings-push job
  tracking; a push from `/apk-settings` should still produce its `vend_jobs`
  row and ack.

## 5. What does NOT need rolling back

Machines. No APK ships in this patch; every machine keeps running whatever it
runs today. If the server is rolled back, machines simply keep their last
SharedPreferences — the same offline behavior they have every day.
