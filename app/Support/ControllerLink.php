<?php

namespace App\Support;

use App\Models\Vend;
use Carbon\Carbon;
use Carbon\CarbonInterface;

/**
 * Is the machine's controller (VMC) still talking? (Brian, 2026-11-05, machine 2351.)
 *
 * The offline alert asks whether ANY part of a machine made contact, so an Android board that stays
 * connected hides a dead controller: 2351's controller went silent for 41 hours on 2 Nov while MQTT
 * stayed up, the freezer warmed from -21°C to -7°C and nobody was told.
 *
 * The controller's own proof of life is `vends.temp_updated_at`. Both frames that stamp it, VENDER
 * (every 5 min, all 393 vending machines) and ACBSTATUS (every ~3 min, 35 machines), are relayed off
 * the serial link by ThreadForBrd, so neither can arrive while the controller is silent. Other HTTP
 * traffic (the board's own heartbeat) still moves `last_updated_at`, which tells the two cases apart:
 *
 *  - STATE_SILENT: HTTP still arrives, the controller's frames do not. The controller is silent.
 *  - STATE_NO_DATA: only MQTT is left. VENDER travels over HTTP, so a healthy controller would look
 *    the same; say what is known, never blame the controller.
 *  - null: the controller was heard recently, or nothing is left at all (the offline alert's job).
 *
 * Vending machines only: freezers and chillers do not have this controller.
 */
class ControllerLink
{
    public const STATE_SILENT = 'silent';

    public const STATE_NO_DATA = 'no_data';

    /** vend_logs / machine_health_histories alert type. */
    public const ALERT_TYPE = 'controller_silent';

    /** The same wait as vends.is_online: contact within this long is contact. */
    public const CONTACT_MINUTES = 15;

    /** minimum hours silent => bucket label; a machine's level is the position in this list + 1. */
    public const BUCKETS = [12 => '> 12hr', 8 => '< 12hr', 4 => '< 8hr', 2 => '< 4hr', 1 => '< 2hr', 0 => '< 1hr'];

    public static function silenceMinutes(): int
    {
        return max(1, (int) config('vend.attention.controller_silent_minutes', 30));
    }

    public static function state(object $vend, ?CarbonInterface $now = null): ?string
    {
        $now ??= Carbon::now();

        if (! self::isVendingMachine($vend) || self::heardRecently($vend, $now) || self::time($vend->temp_updated_at ?? null) === null) {
            return null;
        }

        if (self::within($vend->last_updated_at ?? null, $now)) {
            return self::STATE_SILENT;
        }

        return ! empty($vend->is_mqtt) && self::within($vend->mqtt_last_updated_at ?? null, $now)
            ? self::STATE_NO_DATA
            : null;
    }

    /** True once a controller frame arrived inside the wait; a machine that never reported is not "heard". */
    public static function heardRecently(object $vend, ?CarbonInterface $now = null): bool
    {
        $now ??= Carbon::now();
        $heard = self::time($vend->temp_updated_at ?? null);

        return $heard !== null && $heard->diffInMinutes($now) < self::silenceMinutes();
    }

    public static function silentHours(object $vend, ?CarbonInterface $now = null): ?float
    {
        $heard = self::time($vend->temp_updated_at ?? null);

        return $heard === null ? null : round($heard->diffInMinutes($now ?? Carbon::now()) / 60, 2);
    }

    /** @return array{0: int, 1: string} [level 1-6, bucket label] */
    public static function bucket(float $hours): array
    {
        $level = count(self::BUCKETS);
        foreach (self::BUCKETS as $minHours => $label) {
            if ($hours >= $minHours) {
                return [$level, $label];
            }
            $level--;
        }

        return [1, '< 1hr'];
    }

    private static function isVendingMachine(object $vend): bool
    {
        return ($vend->machine_type ?? null ?: Vend::MACHINE_TYPE_VENDING_MACHINE) === Vend::MACHINE_TYPE_VENDING_MACHINE;
    }

    private static function within(mixed $value, CarbonInterface $now): bool
    {
        $time = self::time($value);

        return $time !== null && $time->diffInMinutes($now) < self::CONTACT_MINUTES;
    }

    private static function time(mixed $value): ?Carbon
    {
        return $value === null || $value === '' ? null : Carbon::parse($value);
    }
}
