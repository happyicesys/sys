<?php

namespace App\Support;

use App\Models\VendFan;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * What a board's fan speed reading means (Brian, 2026-10-19). One rule for the parameter ingest, the
 * daily backstop, the fleet backfill and Machine Health's 2A "Cooling Fan, in OFF condition".
 *
 *  - A fan is RUNNING only above RUNNING_MIN. Fan-less inputs read 0 or electrical noise (200–800 on
 *    2099 / 2150 / 2242 / 2749, prod 2026-10-19); real fans read ~3,600–5,400.
 *  - A machine HAS a fan once it has reported one running (`vends.fan_detected_at`, `is_fan_enabled`),
 *    and keeps it: a fan that later reads 0 is a broken fan, which is exactly what 2A must catch. Only a
 *    person can undo it (Setting/Edit "Reset fan detection", after a board or fan change).
 *  - 2A judges only what the board REPORTED. No readings for OFF_AFTER_MINUTES is a connection gap
 *    (the offline alarm's job), never "fan off" — 2627 and 2527 alarmed that way (2026-10-06 / 10-09).
 */
class FanSignal
{
    public const RUNNING_MIN = 1000;

    /** No running reading for this long, while readings keep arriving, is 2A. */
    public const OFF_AFTER_MINUTES = 40;

    /** How far back 2A looks for the last running reading (bounds the query). */
    public const LOOKBACK_HOURS = 8;

    public static function isRunning(mixed $value): bool
    {
        return is_numeric($value) && (float) $value > self::RUNNING_MIN;
    }

    /**
     * When the fan stopped, or null when it is running or the board is not reporting.
     * Served by idx_vf_vid_type_created (vend_id, type, created_at).
     */
    public static function offSince(int $vendId, ?CarbonInterface $now = null): ?Carbon
    {
        $now = $now ? Carbon::instance($now) : Carbon::now();
        $lookback = $now->copy()->subHours(self::LOOKBACK_HOURS);
        $readings = DB::table('vend_fans')
            ->where('vend_id', $vendId)
            ->where('type', VendFan::TYPE_MAIN)
            ->where('created_at', '>=', $lookback->toDateTimeString());

        $latest = (clone $readings)->max('created_at');
        if ($latest === null || Carbon::parse($latest)->diffInMinutes($now, true) > self::OFF_AFTER_MINUTES) {
            return null;
        }

        $lastRunning = (clone $readings)->where('value', '>', self::RUNNING_MIN)->max('created_at');
        if ($lastRunning !== null && Carbon::parse($lastRunning)->diffInMinutes($now, true) <= self::OFF_AFTER_MINUTES) {
            return null;
        }

        return $lastRunning !== null ? Carbon::parse($lastRunning) : $lookback;
    }
}
