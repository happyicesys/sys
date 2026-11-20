<?php

namespace App\Services\PrepaidVoucher;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * A voucher of N days expires at the END of the local day N days after the day it was bought (Brian,
 * 2026-11-20: 1 = end of tomorrow), in the machine operator's timezone, stored in the app's.
 */
final class PrepaidVoucherExpiry
{
    public static function expiresAt(CarbonInterface $issuedAt, int $days, ?string $timezone): Carbon
    {
        $tz = $timezone ?: config('app.timezone');

        return Carbon::instance($issuedAt)->setTimezone($tz)
            ->startOfDay()
            ->addDays(max(0, $days))
            ->endOfDay()
            ->setTimezone(config('app.timezone'));
    }
}
