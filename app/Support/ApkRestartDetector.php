<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;

/**
 * Tells a real APK process restart apart from an MQTT reconnect (Brian, 2026-10-17: the Ops
 * Dashboard's "APK crash" alert). The APK sends no crash report — its crash handler only writes
 * the local log — and every PWRON frame is an MQTT (re)connect, not a power-on. What survives a
 * reconnect but not a restart is the frame wrapper's `f` (`clsSendMqttFrame.FrameID`, a static
 * that climbs by 2 per frame in the app's one process): `f` at or below the previous PWRON's means
 * the process died and started again.
 *
 * An install also restarts the process, so a restart whose `apkver` differs from the previous
 * PWRON's is an update, not a crash (318 reached ~120 boards in one hour on 2026-10-17). A board
 * reboot or power cut still counts — the frame cannot tell those from a crash, which is why the
 * dashboard flags only a machine that restarts several times in a day.
 */
class ApkRestartDetector
{
    private const TTL_SECONDS = 3 * 86400;

    /** True when this PWRON follows a restart of the same APK build. */
    public static function observe(int $vendId, mixed $frameId, mixed $apkVersion): bool
    {
        if (! is_numeric($frameId)) {
            return false;
        }
        $frameId = (int) $frameId;
        $version = $apkVersion === null || $apkVersion === '' ? null : (string) $apkVersion;

        $key = self::cacheKey($vendId);
        $previous = Cache::get($key);
        Cache::put($key, ['f' => $frameId, 'ver' => $version], self::TTL_SECONDS);

        if (! is_array($previous) || ! isset($previous['f'])) {
            return false;
        }

        return $frameId <= (int) $previous['f'] && $version === ($previous['ver'] ?? null);
    }

    public static function cacheKey(int $vendId): string
    {
        return "apk-restart:last-pwron:{$vendId}";
    }
}
