<?php

namespace App\Services\HappyHour;

use App\Models\Vend;
use App\Services\OtaChannelResolver;

/**
 * Which machines can run Happy Hour — the ONE place this is decided (HappyHourPricing::supports()
 * delegates here). Two families, each with its own app gate:
 *
 *  - Smart freezer (`sg.mark1.freezer`): app >= `happy_hour.min_freezer_apk_version` (30).
 *  - Vending machine, BIG touchscreen board only (mark1-apk, 30x stream): app >=
 *    `happy_hour.min_vending_apk_version` (315), the first build that reads `happy_hour` on
 *    `/thumbnails`. The freezer gate must never be widened to vending: every big board (163–314)
 *    and every old small board (129–145) would pass it.
 *
 * A small board shares applicationId `com.venderroute` and reports 11–99 (new series) or 129–145
 * (old), so the version gate alone already keeps it out today; its board family (ZC-83A, the one
 * `config('ota.channels')` routes to `vending_small`) is excluded outright as a backstop, so a small
 * build that ever reaches 315 still never gets a promo it cannot price. Chillers never qualify.
 *
 * Reads only loaded attributes (no query), so it is safe in the planner's per-minute loop.
 */
final class HappyHourEligibility
{
    /** The OTA channel a no-touch small board's family (apk_ver_json.deviceType) belongs to. */
    private const SMALL_BOARD_CHANNEL = 'vending_small';

    public static function supports(Vend $vend): bool
    {
        return self::reason($vend) === null;
    }

    /** A vending machine that can run Happy Hour (big board, app 315+, active). */
    public static function supportsVending(Vend $vend): bool
    {
        return $vend->machine_type === Vend::MACHINE_TYPE_VENDING_MACHINE && self::reason($vend) === null;
    }

    /** Null when the machine can run Happy Hour; otherwise why not, in words for the campaign page. */
    public static function reason(Vend $vend): ?string
    {
        if (! (bool) $vend->is_active) {
            return 'inactive';
        }

        return match ($vend->machine_type) {
            Vend::MACHINE_TYPE_SMART_FREEZER => (int) $vend->apk_version_code >= self::minFreezerApk()
                ? null
                : sprintf('freezer app %s — needs %d or later', $vend->apk_version_code ?: 'unknown', self::minFreezerApk()),
            Vend::MACHINE_TYPE_VENDING_MACHINE => self::vendingReason($vend),
            default => 'not a smart freezer or touchscreen vending machine',
        };
    }

    public static function minFreezerApk(): int
    {
        return (int) config('happy_hour.min_freezer_apk_version', 30);
    }

    public static function minVendingApk(): int
    {
        return (int) config('happy_hour.min_vending_apk_version', 315);
    }

    private static function vendingReason(Vend $vend): ?string
    {
        $reported = $vend->reportedApkVersionDetail();
        if ($reported->deviceType !== null
            && app(OtaChannelResolver::class)->forDeviceType($reported->deviceType) === self::SMALL_BOARD_CHANNEL) {
            return "small board ({$reported->deviceType}) — Happy Hour runs on the big touchscreen board only";
        }
        if ($reported->code < self::minVendingApk()) {
            return sprintf('app %s — needs %d or later', $reported->code ?: 'unknown', self::minVendingApk());
        }

        return null;
    }
}
