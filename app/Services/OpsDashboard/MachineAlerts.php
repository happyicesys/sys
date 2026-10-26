<?php

namespace App\Services\OpsDashboard;

use App\Support\PaymentRailIdle;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * The Operation Dashboard's "Show Alert/Error" button (Brian, 2026-10-19: "if operator want to
 * focus in alert/error machines, click this button (instead of filter), then will show machine
 * with at least 1 error/alert"). A machine qualifies when any badge the grid paints red in its
 * Error, Machine Status or Payment Device column is red, or it is in a Needs attention group
 * (CriticalAttention). Each reason mirrors that badge's own rule in Vend/CustomerIndex.vue, so the
 * list and the badges never disagree; change a badge's colour rule and its reason here together.
 *
 * Left out on purpose:
 *  - "Dispensing Door: Open" — 156 of 469 active machines read open on prod (2026-10-19); the board's
 *    door flag is not a usable signal (see the VMC sell-gate finding of 2026-08-28).
 *  - MQTT Offline — the grid draws it grey, not red.
 *  - The "# of Refund" trend — a rising count, not a fault.
 *
 * Only active or testing machines, like the badges (an inactive machine's badges are grey).
 */
class MachineAlerts
{
    /** Error column: the grid's red threshold on the 1d / 2d / 7d error rate (%). */
    public const ERROR_RATE_PERCENT = 3;

    /** Payment Device: CoinCnt at or below this is red (resources/js/constants/vendThresholds.js). */
    public const COIN_FLOAT_LOW = 3000;

    /** key => [short tag, severity], in the order the tags and the reason bar show. */
    public const REASONS = [
        'http_offline' => ['HTTP offline', 'critical'],
        'channel_error' => ['Channel error', 'critical'],
        'error_rate' => ['Error rate ≥3%', 'warning'],
        'sensor_off' => ['Drop sensor off', 'critical'],
        'modem_offline' => ['Modem offline', 'warning'],
        'bill_inactive' => ['Bill acceptor off', 'critical'],
        'coin_inactive' => ['Coin acceptor off', 'critical'],
        'coin_float_low' => ['Coin float low', 'warning'],
        'rail_card' => ['No card sale', 'warning'],
        'rail_qr' => ['No QR sale', 'warning'],
        'rail_cash' => ['No cash sale', 'warning'],
    ];

    public function __construct(private CriticalAttention $attention) {}

    /**
     * @param  int[]  $vendIds  machines already chosen by the page's filters and viewer ceiling
     * @return array{total: int, reasons: array<int, array{key: string, short: string, severity: string, count: int}>, by_vend: array<int, array<int, array{key: string, short: string, severity: string, detail: ?string}>>}
     */
    public function forVendIds(array $vendIds, ?CarbonInterface $now = null): array
    {
        $now = $now ? Carbon::instance($now) : Carbon::now();
        $vendIds = array_values(array_unique(array_map('intval', $vendIds)));
        $byVend = [];

        $live = $vendIds === [] ? collect() : DB::table('vends')
            ->whereIn('id', $vendIds)
            ->where(fn ($q) => $q->where('is_active', true)->orWhere('is_testing', true))
            ->get([
                'id', 'is_active', 'is_testing', 'is_online', 'parameter_json', 'vend_transaction_totals_json',
                'modem_unit_id', 'card_terminal_id', 'lcd_monitor_id', 'last_vend_transaction_at',
                'last_card_vend_transaction_at', 'last_cashless_vend_transaction_at', 'last_cash_vend_transaction_at',
            ]);
        $liveIds = $live->pluck('id')->map(fn ($id) => (int) $id)->all();

        // Needs attention first: the section above the grid names these, so the tags read the same.
        foreach ($this->attention->forVendIds($liveIds, $now)['groups'] as $group) {
            foreach ($group['machines'] as $machine) {
                $byVend[$machine['vend_id']][] = [
                    'key' => $group['key'],
                    'short' => $group['short'],
                    'severity' => $group['severity'],
                    'detail' => $machine['detail'],
                ];
            }
        }

        $channelErrors = $this->unclearedChannelErrors($liveIds);
        $offlineModems = $this->offlineModems($live->pluck('modem_unit_id')->filter()->all());

        foreach ($live as $vend) {
            $id = (int) $vend->id;
            $params = $this->json($vend->parameter_json);
            $totals = $this->json($vend->vend_transaction_totals_json);
            $tags = [];

            if (! $vend->is_online && ! $this->hasKey($byVend[$id] ?? [], CriticalAttention::GROUP_OFFLINE)) {
                $tags['http_offline'] = null;
            }
            if (isset($channelErrors[$id])) {
                $tags['channel_error'] = __(':count uncleared', ['count' => $channelErrors[$id]]);
            }
            $rate = max(array_map(fn ($k) => (float) ($totals[$k] ?? 0), ['one_day_error_rate', 'two_days_error_rate', 'seven_days_error_rate']));
            if ($rate >= self::ERROR_RATE_PERCENT) {
                $tags['error_rate'] = round($rate, 1).'%';
            }
            if (array_key_exists('Sensor', $params) && is_numeric($params['Sensor']) && (int) $params['Sensor'] % 2 === 0) {
                $tags['sensor_off'] = null;
            }
            if ($vend->modem_unit_id && isset($offlineModems[(int) $vend->modem_unit_id])) {
                $tags['modem_offline'] = null;
            }
            if ((string) ($params['BILLStat'] ?? '') === '1') {
                $tags['bill_inactive'] = null;
            }
            if ((string) ($params['CHGEStat'] ?? '') === '1') {
                $tags['coin_inactive'] = null;
            }
            if (! empty($params['CoinCnt']) && (float) $params['CoinCnt'] <= self::COIN_FLOAT_LOW) {
                $tags['coin_float_low'] = null;
            }
            foreach (PaymentRailIdle::forRow((array) $vend, $now) as $rail => $idle) {
                $tags['rail_'.$rail] = $idle['hours_since'] !== null ? $this->hours($idle['hours_since']) : __('never');
            }

            foreach ($tags as $key => $detail) {
                [$short, $severity] = self::REASONS[$key];
                $byVend[$id][] = ['key' => $key, 'short' => __($short), 'severity' => $severity, 'detail' => $detail];
            }
        }

        $counts = [];
        foreach ($byVend as $tags) {
            foreach ($tags as $tag) {
                $counts[$tag['key']] ??= ['key' => $tag['key'], 'short' => $tag['short'], 'severity' => $tag['severity'], 'count' => 0];
                $counts[$tag['key']]['count']++;
            }
        }

        // A fixed order: Needs attention's groups as that section shows them, then the badges.
        $order = array_flip([
            CriticalAttention::GROUP_OFFLINE, ...array_keys(CriticalAttention::TEMPERATURE_GROUPS),
            CriticalAttention::GROUP_APK_RESTART, CriticalAttention::GROUP_SCREEN, CriticalAttention::GROUP_NO_SALES,
            ...array_keys(self::REASONS),
        ]);
        uksort($counts, fn ($a, $b) => ($order[$a] ?? PHP_INT_MAX) <=> ($order[$b] ?? PHP_INT_MAX));

        return [
            'total' => count($byVend),
            'reasons' => array_values($counts),
            'by_vend' => $byVend,
        ];
    }

    /** @return array<int, int> vend id => uncleared error logs on its active channels (the Error column's chips) */
    private function unclearedChannelErrors(array $vendIds): array
    {
        if ($vendIds === []) {
            return [];
        }

        return DB::table('vend_channel_error_logs')
            ->join('vend_channels', 'vend_channels.id', '=', 'vend_channel_error_logs.vend_channel_id')
            ->whereIn('vend_channels.vend_id', $vendIds)
            ->where('vend_channels.is_active', true)
            ->where('vend_channel_error_logs.is_error_cleared', false)
            ->groupBy('vend_channels.vend_id')
            ->pluck(DB::raw('COUNT(*)'), 'vend_channels.vend_id')
            ->mapWithKeys(fn ($n, $vendId) => [(int) $vendId => (int) $n])
            ->all();
    }

    /** @return array<int, true> modem units that reported and are offline (the red Remote Modem badge) */
    private function offlineModems(array $modemUnitIds): array
    {
        if ($modemUnitIds === []) {
            return [];
        }

        return DB::table('modem_units')
            ->whereIn('id', array_unique($modemUnitIds))
            ->whereNotNull('last_updated_at')
            ->where('is_online', false)
            ->pluck('id')
            ->mapWithKeys(fn ($id) => [(int) $id => true])
            ->all();
    }

    private function hasKey(array $tags, string $key): bool
    {
        return in_array($key, array_column($tags, 'key'), true);
    }

    private function json(mixed $value): array
    {
        if (is_string($value)) {
            $value = json_decode($value, true);
        }

        return is_array($value) ? $value : [];
    }

    private function hours(float $hours): string
    {
        return $hours >= 48 ? round($hours / 24, 1).'d' : round($hours, 1).'h';
    }
}
