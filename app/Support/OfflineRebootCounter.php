<?php

namespace App\Support;

use Carbon\Carbon;

/**
 * How many offline auto-reboots happened between two "P" heartbeats (Brian, 2026-10-18: the Ops
 * Dashboard's restart alert must say the real cause).
 *
 * The big-board APK reboots the whole board when it cannot reach mark1 over HTTP for 6 minutes
 * (Main2Activity::triggerOfflineReboot, up to 5 per outage) and counts each one in
 * `OfflineRestartCount`, sent on every heartbeat with `OfflineRestartCountDatetime` — when the
 * counter last started from 0. It restarts on a new install AND on the technician's Clear button,
 * and both stamp that datetime, so a changed datetime means "counted from 0 since then".
 *
 * Pure: SyncP feeds it the stored and the reported pair.
 */
final class OfflineRebootCounter
{
    /**
     * Reboots to add for this heartbeat.
     *
     * @param  int|string|null  $storedCount  vends.offline_restart_count before this heartbeat
     * @param  mixed  $storedSince  vends.offline_restart_count_datetime before it
     * @param  int|string|null  $reportedCount  the heartbeat's OfflineRestartCount
     * @param  mixed  $reportedSince  the heartbeat's OfflineRestartCountDatetime
     */
    public static function delta(mixed $storedCount, mixed $storedSince, mixed $reportedCount, mixed $reportedSince): int
    {
        if (! is_numeric($reportedCount) || ! is_numeric($storedCount)) {
            return 0; // first report, or a malformed one: what came before is unknown
        }
        $stored = max(0, (int) $storedCount);
        $reported = max(0, (int) $reportedCount);

        if (self::restarted($storedSince, $reportedSince) || $reported < $stored) {
            return $reported;
        }

        return $reported - $stored;
    }

    /** The counter started again from 0 (new install or Clear): its start time moved. */
    private static function restarted(mixed $storedSince, mixed $reportedSince): bool
    {
        $stored = self::minute($storedSince);
        $reported = self::minute($reportedSince);

        return $stored !== null && $reported !== null && $stored !== $reported;
    }

    /** The APK sends "yyyy-MM-dd HH:mm"; the column is a datetime. Compare to the minute. */
    private static function minute(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        try {
            return Carbon::parse($value)->format('Y-m-d H:i');
        } catch (\Throwable) {
            return null;
        }
    }
}
