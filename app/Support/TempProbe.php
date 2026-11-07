<?php

namespace App\Support;

use App\Models\Vend;
use App\Models\VendTemp;
use Carbon\Carbon;
use Carbon\CarbonInterface;

/**
 * (1C) Temperature probe error (Brian, 2026-11-07, machine 2573).
 *
 * A probe that read normally and then starts sending the controller's error value (32767) is a fault:
 * 2573's T1 did it from 3 Nov 08:08 for almost three days. The machine stayed online and its sales and
 * fan kept arriving, so no other alert fired, and the error readings are not stored, so the graph
 * simply went blank.
 *
 * One fault is one episode, tracked per frame by SyncVendParameter / SyncAcbStatus through track():
 *
 *  - it starts on an error reading from a probe whose previous reading was valid (a probe that was
 *    never fitted reads 32767 from the start and never starts one);
 *  - it lasts while the probe keeps erroring, even through a single good reading in between (2573
 *    read normally once in a while between long error runs);
 *  - it ends once every probe in it has read normally for recoverMinutes() in a row.
 *
 * SyncOnlineStatus alerts on it with the offline alert's rising buckets once the probe has been in
 * error for errorMinutes() and is in error right now (`temp_probe_ok_since` null).
 */
class TempProbe
{
    /** vend_logs / machine_health_histories alert type. */
    public const ALERT_TYPE = 'temp_probe_error';

    /** Frame key => probe name. TEMP is T1 on VENDER frames, t1 on ACBSTATUS. */
    public const PROBES = ['T1' => ['TEMP', 't1'], 'T2' => ['t2']];

    public static function errorMinutes(): int
    {
        return max(1, (int) config('vend.attention.temp_probe_error_minutes', 30));
    }

    public static function recoverMinutes(): int
    {
        return max(1, (int) config('vend.attention.temp_probe_recover_minutes', 60));
    }

    /** The table has the columns (deploy order: code may run before the migration). */
    public static function isTracked(Vend $vend): bool
    {
        return array_key_exists('temp_probe_error_since', $vend->getAttributes());
    }

    /**
     * Move the machine's probe episode on by one frame. Call it BEFORE the frame is saved over the previous one:
     * the previous frame is what tells a probe that broke from one that was never fitted.
     *
     * @param  array<string, mixed>  $input  this frame
     * @param  mixed  $previous  the same kind of frame before it (VENDER: parameter_json, ACBSTATUS: acb_status_json)
     */
    public static function track(Vend $vend, array $input, mixed $previous, ?CarbonInterface $now = null): void
    {
        if (! self::isTracked($vend) || ! self::isVendingMachine($vend)) {
            return;
        }
        $now ??= Carbon::now();
        $previous = is_array($previous) ? $previous : [];
        $episode = self::probes($vend);

        $erroring = [];
        $reported = [];
        foreach (self::PROBES as $probe => $keys) {
            $value = self::read($input, $keys);
            if ($value === null) {
                continue; // not in this frame: says nothing either way
            }
            $reported[] = $probe;
            if (! self::isError($value)) {
                continue;
            }
            $previousValue = self::read($previous, $keys);
            if (in_array($probe, $episode, true) || ($previousValue !== null && ! self::isError($previousValue))) {
                $erroring[] = $probe;
            }
        }

        if ($erroring !== []) {
            $vend->temp_probe_error_since ??= $now;
            $vend->temp_probe_ok_since = null;
            $vend->temp_probe_error_probes = implode(',', self::sorted(array_unique([...$episode, ...$erroring])));

            return;
        }

        if ($episode === [] || array_diff($episode, $reported) !== []) {
            return; // no episode, or a probe in it was not in this frame
        }

        // Every probe in the episode read normally.
        $vend->temp_probe_ok_since ??= $now;
        if (Carbon::parse($vend->temp_probe_ok_since)->diffInMinutes($now) >= self::recoverMinutes()) {
            $vend->temp_probe_error_since = null;
            $vend->temp_probe_ok_since = null;
            $vend->temp_probe_error_probes = null;
        }
    }

    /** In an episode and erroring right now. */
    public static function isErroring(object $vend): bool
    {
        return ! empty($vend->temp_probe_error_since) && empty($vend->temp_probe_ok_since);
    }

    public static function errorHours(object $vend, ?CarbonInterface $now = null): ?float
    {
        if (empty($vend->temp_probe_error_since)) {
            return null;
        }

        return round(Carbon::parse($vend->temp_probe_error_since)->diffInMinutes($now ?? Carbon::now()) / 60, 2);
    }

    /** @return list<string> e.g. ['T1'] */
    public static function probes(object $vend): array
    {
        $value = $vend->temp_probe_error_probes ?? null;

        return $value ? explode(',', (string) $value) : [];
    }

    public static function isError(mixed $value): bool
    {
        return is_numeric($value) && (int) $value === VendTemp::TEMPERATURE_ERROR;
    }

    /**
     * Vending machines only. A smart freezer's reading comes from the Zijia host, which sends its own
     * placeholder during a link hang that clears by itself (50002, 2026-11-04); chillers report none.
     */
    public static function isVendingMachine(object $vend): bool
    {
        return ($vend->machine_type ?? null ?: Vend::MACHINE_TYPE_VENDING_MACHINE) === Vend::MACHINE_TYPE_VENDING_MACHINE;
    }

    /** @param  list<string>  $keys */
    private static function read(array $frame, array $keys): mixed
    {
        foreach ($keys as $key) {
            if (! isset($frame[$key]) || $frame[$key] === '' || ! is_numeric($frame[$key])) {
                continue;
            }
            // ACBSTATUS sends t1 = 0 for "no reading" (SyncAcbStatus skips it too).
            if ($key === 't1' && (int) $frame[$key] === 0) {
                continue;
            }

            return $frame[$key];
        }

        return null;
    }

    /** @param  array<int, string>  $probes */
    private static function sorted(array $probes): array
    {
        sort($probes);

        return array_values($probes);
    }
}
