<?php

namespace App\Services\OpsDashboard;

use App\Jobs\SyncP;
use App\Models\Vend;
use App\Models\VendDailyStat;
use App\Models\VendSmartAlert;
use App\Services\MachineHealthDashboardService;
use App\Support\MachineStatusSummary;
use App\Support\VendCode;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The Operation Dashboard's "Needs attention" section (Brian, 2026-10-17): before Search, the
 * machines that need someone now, grouped by what is wrong. It answers for the machines the
 * page's own filters and viewer ceiling already chose (VendController::customerIndexAttention);
 * nothing here widens that set.
 *
 * Rules, in the order they show:
 *  - (1) and 2A–2E are Machine Health's own alerts: offline under
 *    MachineHealthDashboardService::OFFLINE_ALERT_MAX_HOURS, and the active vend_smart_alerts rows
 *    DetectTempTrends keeps. Like Machine Health they apply to vending machines only — a freezer's
 *    or chiller's probes and links mean something else.
 *  - APK reboot (connection issue): the board's own offline auto-reboots — no HTTP to mark1 for
 *    6 min — counted as `offline_reboot` by SyncP from the heartbeat's counter, at or above
 *    `vend.attention.apk_restarts_per_day` on today or yesterday. Only those (Brian, 2026-10-18:
 *    "reboot means connection issue"): the `apk_restart` count (App\Support\ApkRestartDetector)
 *    also holds crashes, power cuts and reloads, which nothing can tell apart yet, so it is not
 *    shown. An "APK crash" group waits until a crash can be detected.
 *  - Screen frozen: the APK's UI watchdog (306+) — a looper stall or no-frames today or yesterday,
 *    or a day whose hourly page checks all failed.
 *  - No sales: a machine whose last sale is older than `vend.attention.no_sales_hours`. Every
 *    machine kind. A machine that has never sold (new) is not flagged.
 */
class CriticalAttention
{
    public const GROUP_OFFLINE = 'offline';

    public const GROUP_APK_RESTART = 'apk_restart';

    public const GROUP_SCREEN = 'screen';

    public const GROUP_NO_SALES = 'no_sales';

    /** Machine Health's operation errors, with Machine Health's own wording. */
    public const TEMPERATURE_GROUPS = [
        VendSmartAlert::TYPE_COMP_FAN_OFF => '2A) Cooling Fan, in OFF condition',
        VendSmartAlert::TYPE_TEMPS_ABOVE_0 => '2B) T1 or T2, above 0°C',
        VendSmartAlert::TYPE_TEMPS_ABOVE_MINUS_8 => '2C) T1 or T2, above -8°C',
        VendSmartAlert::TYPE_NOT_REACH_MINUS_18 => '2D) T1 or T2, did not reach -18°C',
        VendSmartAlert::TYPE_TEMPS_ABOVE_MINUS_17_UPWARD => '2E) T1 or T2, above -17°C and upward trending',
    ];

    private const SCREEN_FAULT_METRICS = ['ui_looper_stall', 'ui_no_frames'];

    /**
     * @param  int[]  $vendIds  machines already chosen by the page's filters and viewer ceiling
     * @return array{machine_count: int, flagged_count: int, generated_at: string, groups: array<int, array<string, mixed>>}
     */
    public function forVendIds(array $vendIds, ?CarbonInterface $now = null): array
    {
        $now = $now ? Carbon::instance($now) : Carbon::now();
        $vendIds = array_values(array_unique(array_map('intval', $vendIds)));

        $vends = $vendIds === [] ? collect() : DB::table('vends')
            ->leftJoin('customers', 'customers.id', '=', 'vends.customer_id')
            ->leftJoin('modem_units', 'modem_units.id', '=', 'vends.modem_unit_id')
            ->whereIn('vends.id', $vendIds)
            ->select([
                'vends.id', 'vends.code', 'vends.code_prefix', 'vends.machine_type',
                'vends.is_online', 'vends.last_vend_transaction_at',
                'customers.name AS site_name',
                DB::raw(MachineHealthDashboardService::lastContactSql().' AS last_contact_at'),
                // The live status shown under each machine (MachineStatusSummary).
                'vends.last_updated_at', 'vends.is_mqtt', 'vends.is_mqtt_active', 'vends.mqtt_last_updated_at',
                'vends.temp', 'vends.temp_updated_at', 'vends.is_temp_error', 'vends.parameter_json',
                'modem_units.is_online AS modem_unit_is_online', 'modem_units.last_updated_at AS modem_unit_last_updated_at',
            ])
            ->get()
            ->keyBy('id');

        $vendingIds = $vends
            ->filter(fn ($v) => ($v->machine_type ?: Vend::MACHINE_TYPE_VENDING_MACHINE) === Vend::MACHINE_TYPE_VENDING_MACHINE)
            ->keys()->map(fn ($id) => (int) $id)->all();

        $groups = [
            $this->group(self::GROUP_OFFLINE, '(1) Lost connectivity or electricity', 'critical', $this->offline($vends, $vendingIds, $now)),
        ];
        $alerts = $this->temperatureAlerts($vendingIds, $now);
        foreach (self::TEMPERATURE_GROUPS as $type => $label) {
            $groups[] = $this->group($type, $label, 'critical', $alerts[$type] ?? []);
        }
        $groups[] = $this->group(self::GROUP_APK_RESTART, 'APK reboot (connection issue)', 'warning', $this->connectionReboots($vendingIds, $now));
        $groups[] = $this->group(self::GROUP_SCREEN, 'Touch screen frozen', 'warning', $this->screenFaults($vendingIds, $now));
        $groups[] = $this->group(self::GROUP_NO_SALES, 'No sales for '.$this->noSalesHours().'h', 'warning', $this->noSales($vends, $now));

        $groups = array_map(function (array $group) use ($vends) {
            $group['machines'] = collect($group['machines'])
                ->filter(fn ($detail, $vendId) => $vends->has($vendId))
                ->map(fn ($detail, $vendId) => $this->machine($vends->get($vendId), $detail))
                ->sortBy('code_label', SORT_NATURAL)
                ->values()
                ->all();
            $group['count'] = count($group['machines']);

            return $group;
        }, $groups);

        $flagged = collect($groups)->flatMap(fn ($g) => array_column($g['machines'], 'vend_id'))->unique();

        return [
            'machine_count' => $vends->count(),
            'flagged_count' => $flagged->count(),
            'generated_at' => $now->toIso8601String(),
            'groups' => $groups,
        ];
    }

    public function noSalesHours(): int
    {
        return max(1, (int) config('vend.attention.no_sales_hours', 48));
    }

    /** @return array<int, string> vend id => detail */
    private function offline(Collection $vends, array $vendingIds, Carbon $now): array
    {
        $out = [];
        foreach ($vendingIds as $id) {
            $vend = $vends->get($id);
            if ((bool) $vend->is_online || $vend->last_contact_at === null) {
                continue;
            }
            $hours = Carbon::parse($vend->last_contact_at)->diffInMinutes($now) / 60;
            if ($hours < MachineHealthDashboardService::OFFLINE_ALERT_MAX_HOURS) {
                $out[$id] = 'offline '.$this->hours($hours);
            }
        }

        return $out;
    }

    /** @return array<string, array<int, string>> alert type => (vend id => detail) */
    private function temperatureAlerts(array $vendingIds, Carbon $now): array
    {
        if ($vendingIds === []) {
            return [];
        }

        $out = [];
        VendSmartAlert::query()
            ->whereIn('vend_id', $vendingIds)
            ->whereIn('alert_type', array_keys(self::TEMPERATURE_GROUPS))
            ->where('is_active', true)
            ->orderBy('created_at')
            ->get(['vend_id', 'alert_type', 'created_at'])
            ->each(function (VendSmartAlert $alert) use (&$out, $now) {
                $out[$alert->alert_type][(int) $alert->vend_id] ??= $alert->created_at
                    ? 'since '.$this->hours($alert->created_at->diffInMinutes($now) / 60).' ago'
                    : 'active';
            });

        return $out;
    }

    /** @return array<int, string> vend id => detail; the worst of today and yesterday */
    private function connectionReboots(array $vendingIds, Carbon $now): array
    {
        $threshold = max(1, (int) config('vend.attention.apk_restarts_per_day', 5));

        return $this->dailyStats($vendingIds, [SyncP::METRIC_OFFLINE_REBOOT], $now)
            ->groupBy('vend_id')
            ->map(fn (Collection $rows) => (int) $rows->max('count'))
            ->filter(fn (int $max) => $max >= $threshold)
            ->map(fn (int $max) => "{$max} reboots in a day (no internet for 6 min)")
            ->all();
    }

    /** @return array<int, string> */
    private function screenFaults(array $vendingIds, Carbon $now): array
    {
        $out = [];
        foreach ($this->dailyStats($vendingIds, [...self::SCREEN_FAULT_METRICS, 'ui_probe_fail', 'ui_probe_pass'], $now)->groupBy('vend_id') as $vendId => $rows) {
            $faults = (int) $rows->whereIn('metric', self::SCREEN_FAULT_METRICS)->sum('count');
            if ($faults > 0) {
                $out[(int) $vendId] = "{$faults} freeze".($faults === 1 ? '' : 's');

                continue;
            }
            // A day of page checks that all failed: the watchdog could not get a page drawn.
            foreach ($rows->groupBy('date') as $day) {
                $failed = (int) $day->where('metric', 'ui_probe_fail')->sum('count');
                if ($failed > 0 && (int) $day->where('metric', 'ui_probe_pass')->sum('count') === 0) {
                    $out[(int) $vendId] = "{$failed} page checks failed";
                    break;
                }
            }
        }

        return $out;
    }

    /** @return array<int, string> */
    private function noSales(Collection $vends, Carbon $now): array
    {
        $cutoff = $now->copy()->subHours($this->noSalesHours());
        $out = [];
        foreach ($vends as $id => $vend) {
            if ($vend->last_vend_transaction_at === null) {
                continue;
            }
            $last = Carbon::parse($vend->last_vend_transaction_at);
            if ($last->lt($cutoff)) {
                $out[(int) $id] = 'last sale '.$this->hours($last->diffInMinutes($now) / 60).' ago';
            }
        }

        return $out;
    }

    /** Today's and yesterday's rows of the given metrics. */
    private function dailyStats(array $vendIds, array $metrics, Carbon $now): Collection
    {
        if ($vendIds === []) {
            return collect();
        }

        return VendDailyStat::query()
            ->whereIn('vend_id', $vendIds)
            ->whereIn('metric', $metrics)
            ->whereBetween('date', [$now->copy()->subDay()->toDateString(), $now->toDateString()])
            ->where('count', '>', 0)
            ->get(['vend_id', 'date', 'metric', 'count']);
    }

    private function group(string $key, string $label, string $severity, array $machines): array
    {
        return ['key' => $key, 'label' => $label, 'severity' => $severity, 'machines' => $machines];
    }

    private function machine(object $vend, string $detail): array
    {
        return [
            'vend_id' => (int) $vend->id,
            'code_label' => VendCode::label($vend->code_prefix, $vend->code),
            'site' => $vend->site_name,
            'detail' => $detail,
            'status' => MachineStatusSummary::forRow($vend),
        ];
    }

    private function hours(float $hours): string
    {
        return $hours >= 48 ? round($hours / 24, 1).'d' : round($hours, 1).'h';
    }
}
