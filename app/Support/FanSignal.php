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
 *  - A machine HAS a fan once it has reported one running for DETECT_AFTER_MINUTES in a row
 *    (`vends.fan_detected_at`, `is_fan_enabled`; Brian, 2026-10-30 — one running reading was enough
 *    before, so a single spike re-detected a fan a person had just reset). It keeps it: a fan that later
 *    reads 0 is a broken fan, which is exactly what 2A must catch. Only a person can undo it
 *    (Setting/Edit "Reset fan detection", after a board or fan change).
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

    /** A fan is detected after running this long without a break. */
    public const DETECT_AFTER_MINUTES = 60;

    /** Boards report every 5 min; a longer silence breaks the run rather than bridging it. */
    public const DETECT_MAX_GAP_MINUTES = 15;

    public static function isRunning(mixed $value): bool
    {
        return is_numeric($value) && (float) $value > self::RUNNING_MIN;
    }

    /**
     * Whether the board's latest readings show the fan running for DETECT_AFTER_MINUTES with no reading
     * at or below RUNNING_MIN and no gap over DETECT_MAX_GAP_MINUTES. Served by idx_vf_vid_type_created.
     */
    public static function hasRunLongEnough(int $vendId, ?CarbonInterface $now = null): bool
    {
        $now = $now ? Carbon::instance($now) : Carbon::now();
        $readings = DB::table('vend_fans')
            ->where('vend_id', $vendId)
            ->where('type', VendFan::TYPE_MAIN)
            ->where('created_at', '>=', $now->copy()->subMinutes(self::DETECT_AFTER_MINUTES + self::DETECT_MAX_GAP_MINUTES)->toDateTimeString())
            ->where('created_at', '<=', $now->toDateTimeString())
            ->orderByDesc('created_at')
            ->get(['value', 'created_at']);

        $runEnd = null;
        $runStart = null;
        $next = $now;
        foreach ($readings as $reading) {
            $at = Carbon::parse($reading->created_at);
            if (! self::isRunning($reading->value) || $at->diffInMinutes($next, true) > self::DETECT_MAX_GAP_MINUTES) {
                break;
            }
            $runEnd ??= $at;
            $runStart = $next = $at;
        }

        return $runStart !== null && $runStart->diffInMinutes($runEnd, true) >= self::DETECT_AFTER_MINUTES;
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
