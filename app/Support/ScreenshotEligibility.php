<?php

namespace App\Support;

use App\Models\Vend;
use App\Services\OtaChannelResolver;

/**
 * Which machines answer Setting/Edit "View Screen" — the ONE place this is decided: the page
 * disables the button with this reason, and VendScreenshotController refuses with it.
 *
 *  - Smart freezer (`sg.mark1.freezer`): app >= `vend.screenshot.min_freezer_apk` (12).
 *  - Vending machine, BIG touchscreen board (mark1-apk): app >= `vend.screenshot.min_vending_apk`
 *    (317, the first build with a SCREENSHOT handler — every earlier big board dropped the frame and
 *    the operator sat through a 2-minute timeout).
 *  - Small board (ZC-83A family, OTA channel `vending_small`): no handler on that stream.
 *  - Chiller: CityBox's app, not ours.
 *
 * Reads only loaded attributes (no query).
 */
final class ScreenshotEligibility
{
    private const SMALL_BOARD_CHANNEL = 'vending_small';

    public static function supports(Vend $vend): bool
    {
        return self::reason($vend) === null;
    }

    /** Null when the machine can be asked for a screenshot; otherwise why not, in words for the page. */
    public static function reason(Vend $vend): ?string
    {
        if (empty($vend->code)) {
            return 'This machine has no code.';
        }

        return match ($vend->machine_type) {
            Vend::MACHINE_TYPE_SMART_FREEZER => self::versionReason((int) $vend->apk_version_code, self::minFreezerApk(), 'freezer app'),
            Vend::MACHINE_TYPE_SMART_CHILLER => 'A Smart Chiller runs CityBox\'s app, which has no screen capture.',
            default => self::vendingReason($vend),
        };
    }

    public static function minVendingApk(): int
    {
        return (int) config('vend.screenshot.min_vending_apk', 317);
    }

    public static function minFreezerApk(): int
    {
        return (int) config('vend.screenshot.min_freezer_apk', 12);
    }

    private static function vendingReason(Vend $vend): ?string
    {
        $reported = $vend->reportedApkVersionDetail();
        if ($reported->deviceType !== null
            && app(OtaChannelResolver::class)->forDeviceType($reported->deviceType) === self::SMALL_BOARD_CHANNEL) {
            return "Small board ({$reported->deviceType}): screen capture is built for the big touchscreen board only.";
        }

        return self::versionReason((int) $reported->code, self::minVendingApk(), 'APK');
    }

    private static function versionReason(int $code, int $min, string $what): ?string
    {
        if ($code >= $min) {
            return null;
        }

        return $code > 0
            ? "Screen capture needs {$what} v{$min} or newer. This machine is on v{$code} — update it first."
            : "Screen capture needs {$what} v{$min} or newer. This machine has never reported its version — update it first.";
    }
}
