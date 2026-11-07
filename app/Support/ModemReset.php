<?php

namespace App\Support;

use App\Models\ModemUnit;
use App\Models\Vend;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Cache;

/**
 * When mark1 may send RESET to a machine's external 4G modem (the Air724 "square module", Brian 2026-11-07).
 *
 * The old rule (from 2025) reset the modem every minute once the board had sent no HTTP for 5 minutes. A
 * board's own HTTP comes about every 5 minutes (P heartbeat + VENDER), so one late packet started a loop:
 * reset, link down, still no HTTP, reset again each minute, the app's 6-minute offline reboot, until one
 * packet got through 20-30 minutes later. 52 of the 55 such machines did it every day (2388 on 6 Nov
 * 01:05-01:25: a reconnect on every minute; CONTROLLER_SILENCE_AUDIT_2026-11-07.md).
 *
 * A reset can only help when the modem itself is up (the RESET reaches it over its own MQTT link) and the
 * board behind it cannot get out. So now:
 *  - the board is unreachable on BOTH links for offline_minutes (10): no HTTP, and no MQTT where it has
 *    MQTT (an MQTT session that is up runs through the same modem, so the modem works);
 *  - the modem itself was heard within modem_contact_minutes (15);
 *  - at most one reset per cooldown_minutes (30) per machine;
 *  - sent at QoS 0, so a broker never queues RESETs and replays them when the modem reconnects.
 */
class ModemReset
{
    public static function enabled(): bool
    {
        return (bool) config('vend.modem_reset.enabled', true);
    }

    public static function offlineMinutes(): int
    {
        return max(1, (int) config('vend.modem_reset.offline_minutes', 10));
    }

    public static function cooldownMinutes(): int
    {
        return max(1, (int) config('vend.modem_reset.cooldown_minutes', 30));
    }

    public static function modemContactMinutes(): int
    {
        return max(1, (int) config('vend.modem_reset.modem_contact_minutes', 15));
    }

    /** The modem is one mark1 may reset (type, a unit, and not the excluded unit 65). */
    public static function isResettable(Vend $vend): bool
    {
        return (bool) $vend->modemType?->is_resetable && $vend->modem_unit_id && (int) $vend->modem_unit_id !== 65;
    }

    /** Null = send; otherwise the reason not to (for tests and logs). */
    public static function blockedBecause(Vend $vend, ?ModemUnit $modem, CarbonInterface $now): ?string
    {
        if (! self::enabled()) {
            return 'disabled';
        }
        if (! self::isResettable($vend) || ! $modem) {
            return 'not_resettable';
        }
        if (! self::quietFor($vend->last_updated_at, $now)) {
            return 'http_recent';
        }
        if ($vend->is_mqtt && $vend->mqtt_last_updated_at && ! self::quietFor($vend->mqtt_last_updated_at, $now)) {
            return 'mqtt_up';
        }
        if (! $modem->last_updated_at || $modem->last_updated_at->diffInMinutes($now) >= self::modemContactMinutes()) {
            return 'modem_unreachable';
        }
        if (Cache::has(self::cooldownKey($vend))) {
            return 'cooldown';
        }

        return null;
    }

    /** Claim the cooldown slot; false when another run already took it. */
    public static function claim(Vend $vend): bool
    {
        return Cache::add(self::cooldownKey($vend), true, now()->addMinutes(self::cooldownMinutes()));
    }

    /** A machine whose board never sent HTTP is not reset (as before); an MQTT link never seen does not block. */
    private static function quietFor(mixed $at, CarbonInterface $now): bool
    {
        return $at !== null && $at->diffInMinutes($now) >= self::offlineMinutes();
    }

    private static function cooldownKey(Vend $vend): string
    {
        return 'modem-reset:'.$vend->id;
    }
}
