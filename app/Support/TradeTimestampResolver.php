<?php

namespace App\Support;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use Throwable;

/**
 * Which moment does a TRADE frame belong to?
 *
 * The APK stamps `TIME` from the device clock and replays queued frames
 * byte-for-byte after a reconnect — sometimes a month later — so a replayed
 * sale carries its true time. But the same clock is years wrong on ~3.7% of
 * frames (2070, 2030…), so the frame time is trusted only inside a window:
 * not more than `maxDaysBack` behind now, not more than `maxSecondsAhead`
 * ahead. Anything else (unparseable, missing, outside) is booked at arrival
 * time and flagged, never silently.
 *
 * Pure: no clock, no config — the caller passes both, so it is unit-tested
 * against fixed instants. Wired in VendTransactionService::createVendTransaction.
 */
final class TradeTimestampResolver
{
    public const REASON_MISSING = 'missing';

    public const REASON_UNPARSEABLE = 'unparseable';

    public const REASON_TOO_OLD = 'too_old';

    public const REASON_FUTURE = 'future';

    public static function resolve(?string $frameTime, CarbonInterface $now, int $maxDaysBack, int $maxSecondsAhead = 300): ResolvedTradeTime
    {
        $raw = $frameTime === null ? null : trim($frameTime);
        if ($raw === null || $raw === '') {
            return ResolvedTradeTime::rejected($now, $raw, self::REASON_MISSING);
        }

        try {
            $at = Carbon::parse($raw, $now->getTimezone());
        } catch (Throwable) {
            return ResolvedTradeTime::rejected($now, $raw, self::REASON_UNPARSEABLE);
        }

        if ($at->gt($now->copy()->addSeconds($maxSecondsAhead))) {
            return ResolvedTradeTime::rejected($now, $raw, self::REASON_FUTURE);
        }
        if ($at->lt($now->copy()->subDays($maxDaysBack))) {
            return ResolvedTradeTime::rejected($now, $raw, self::REASON_TOO_OLD);
        }

        return ResolvedTradeTime::trusted($at, $raw);
    }

    /** Convenience for the ingest path: window from config, clock = now. */
    public static function fromFrame(?string $frameTime): ResolvedTradeTime
    {
        return self::resolve(
            $frameTime,
            Carbon::now(),
            (int) config('sales.trade_max_days_back', 30),
            (int) config('sales.trade_max_seconds_ahead', 300),
        );
    }
}
