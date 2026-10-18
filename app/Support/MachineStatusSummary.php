<?php

namespace App\Support;

use App\Models\Vend;
use App\Models\VendTemp;
use Carbon\Carbon;
use Carbon\CarbonInterface;

/**
 * A machine's live status in a few short badges, for the Operation Dashboard's Needs attention
 * list (Brian, 2026-10-18: "show the machine status without need to click in"). It reads the same
 * values as the grid's Machine Status column — HTTP / MQTT online, Remote Modem, the temperature,
 * Dispensing Door, Product Drop Sensor — with the grid's own colours (temperature red above -15 °C,
 * as the grid's T1 cell). A freezer or chiller gets the link badges only: its probes and door are
 * reported differently.
 *
 * Each badge is ['label' => …, 'value' => …, 'tone' => ok | bad | idle].
 */
class MachineStatusSummary
{
    private const TEMP_ALERT_ABOVE = -15.0;

    /** @return array<int, array{label: string, value: string, tone: string}> */
    public static function forRow(object $row, ?CarbonInterface $now = null): array
    {
        $now = $now ? Carbon::instance($now) : Carbon::now();
        $badges = [];

        $badges[] = self::badge('HTTP', ((bool) $row->is_online ? 'Online' : 'Offline').self::ago($row->last_updated_at ?? null, $now), (bool) $row->is_online ? 'ok' : 'bad');

        if ((bool) ($row->is_mqtt ?? false)) {
            $badges[] = self::badge('MQTT', ((bool) $row->is_mqtt_active ? 'Online' : 'Offline').self::ago($row->mqtt_last_updated_at ?? null, $now), (bool) $row->is_mqtt_active ? 'ok' : 'idle');
        }
        if (($row->modem_unit_last_updated_at ?? null) !== null) {
            $badges[] = self::badge('Modem', ((bool) $row->modem_unit_is_online ? 'Online' : 'Offline').self::ago($row->modem_unit_last_updated_at, $now), (bool) $row->modem_unit_is_online ? 'ok' : 'bad');
        }

        if (($row->machine_type ?: Vend::MACHINE_TYPE_VENDING_MACHINE) !== Vend::MACHINE_TYPE_VENDING_MACHINE) {
            return $badges;
        }

        $params = self::json($row->parameter_json ?? null);
        $temps = [];
        if ($row->temp !== null && ! (bool) ($row->is_temp_error ?? false)) {
            $temps['T1'] = ((int) $row->temp) / 10;
        }
        if (isset($params['t2']) && is_numeric($params['t2']) && (int) $params['t2'] !== VendTemp::TEMPERATURE_ERROR) {
            $temps['T2'] = ((int) $params['t2']) / 10;
        }
        if ($temps !== []) {
            $value = implode(' · ', array_map(fn ($k, $v) => "{$k} ".number_format($v, 1).'°C', array_keys($temps), $temps));
            $badges[] = self::badge('Temp', $value.self::ago($row->temp_updated_at ?? null, $now), max($temps) > self::TEMP_ALERT_ABOVE ? 'bad' : 'ok');
        }
        if (! empty($params['door'])) {
            $open = $params['door'] === 'open';
            $badges[] = self::badge('Door', $open ? 'Open' : 'Close', $open ? 'bad' : 'ok');
        }
        if (array_key_exists('Sensor', $params) && is_numeric($params['Sensor'])) {
            $enabled = ((int) $params['Sensor']) % 2 !== 0;
            $badges[] = self::badge('Drop sensor', $enabled ? 'Enabled' : 'Disabled', $enabled ? 'ok' : 'bad');
        }

        return $badges;
    }

    private static function badge(string $label, string $value, string $tone): array
    {
        return ['label' => $label, 'value' => $value, 'tone' => $tone];
    }

    private static function ago(mixed $at, Carbon $now): string
    {
        if ($at === null || $at === '') {
            return '';
        }
        $minutes = Carbon::parse($at)->diffInMinutes($now);

        return ' · '.match (true) {
            $minutes < 60 => max(0, (int) $minutes).'m',
            $minutes < 2880 => round($minutes / 60, 1).'h',
            default => round($minutes / 1440, 1).'d',
        };
    }

    private static function json(mixed $value): array
    {
        if (is_string($value)) {
            $value = json_decode($value, true);
        }

        return is_array($value) ? $value : [];
    }
}
