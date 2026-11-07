<?php

namespace App\Jobs;

use App\Models\MachineHealthHistory;
use App\Models\ModemUnit;
use App\Models\Vend;
use App\Models\VendLog;
use App\Services\AlertEmailService;
use App\Services\MqttService;
use App\Support\ControllerLink;
use App\Support\ModemReset;
use App\Support\TempProbe;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use PhpMqtt\Client\MqttClient;

class SyncOnlineStatus implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct() {}

    public function handle(MqttService $mqttService, AlertEmailService $alertEmailService): void
    {
        $now = Carbon::now();
        $threshold = $now->copy()->subMinutes(15);
        $thresholdStr = $threshold->toDateTimeString();

        // 1. Bulk update online statuses for ALL machines (Very fast)
        Vend::query()->update([
            'is_online' => DB::raw("CASE WHEN last_updated_at >= '{$thresholdStr}' THEN 1 ELSE 0 END"),
            'is_temp_active' => DB::raw("CASE WHEN temp_updated_at >= '{$thresholdStr}' THEN 1 ELSE 0 END"),
            'is_mqtt_active' => DB::raw("CASE WHEN is_mqtt = 1 THEN (mqtt_last_updated_at >= '{$thresholdStr}') ELSE is_mqtt_active END"),
        ]);

        // 2. Alert & Reset logic - only for relevant machines
        // We filter out machines that would be skipped anyway according to the original logic
        Vend::with(['alertSetting', 'modemType', 'modemUnit', 'operator'])
            ->whereNotNull('customer_id')
            ->where('is_active', true)
            ->where('is_testing', false)
            ->whereNotIn('code', ['808', '6001', '6002', '831'])
            ->where('operator_id', '!=', 23)
            ->chunkById(200, function ($vends) use ($now, $mqttService, $alertEmailService) {
                foreach ($vends as $vend) {
                    try {
                        // Restoration / Level logic
                        $isMqttOffline = false;

                        if ($vend->is_mqtt) {
                            $isMqttOffline = ! $vend->mqtt_last_updated_at || $vend->mqtt_last_updated_at->diffInMinutes($now) > 30;

                            if ($isMqttOffline && ! $vend->is_mqtt_offline_notified) {
                                $vend->is_mqtt_offline_notified = true;
                            }
                        }

                        $dates = collect([
                            $vend->last_updated_at,
                            $vend->last_vend_transaction_at,
                            $vend->offline_restart_count_datetime,
                            $vend->is_mqtt ? $vend->mqtt_last_updated_at : null,
                        ])->filter()->max();

                        $duration = $dates ? $dates->diffInMinutes($now) : 999999;

                        $level = 0;
                        $label = null;

                        $customOfflineMinutes = $vend->offlineAlertMinutes();

                        if ($duration >= 720) {
                            $level = 6;
                            $label = '> 12hr';
                        } elseif ($duration >= 480) {
                            $level = 5;
                            $label = '< 12hr';
                        } elseif ($duration >= 240) {
                            $level = 4;
                            $label = '< 8hr';
                        } elseif ($duration >= 120) {
                            $level = 3;
                            $label = '< 4hr';
                        } elseif ($duration >= 60) {
                            $level = 2;
                            $label = '< 2hr';
                        } elseif ($duration >= $customOfflineMinutes) {
                            $level = 1;
                            $label = '< 1hr';
                        }

                        $currentLevel = (int) $vend->offline_notification_level;

                        // Send Alert if Level Increased
                        if ($level > $currentLevel) {
                            if ($currentLevel > 0) {
                                $oldLabels = [];
                                if ($currentLevel >= 1) {
                                    $oldLabels[] = '< 1hr';
                                }
                                if ($currentLevel >= 2) {
                                    $oldLabels[] = '< 2hr';
                                }
                                if ($currentLevel >= 3) {
                                    $oldLabels[] = '< 4hr';
                                }
                                if ($currentLevel >= 4) {
                                    $oldLabels[] = '< 8hr';
                                }
                                if ($currentLevel >= 5) {
                                    $oldLabels[] = '< 12hr';
                                }

                                \App\Models\VendLog::where('vend_id', $vend->id)
                                    ->where('event', \App\Models\VendLog::EVENT_POWER_OFF)
                                    ->whereIn('context->label', $oldLabels)
                                    ->where('created_at', '>=', now()->subHours(48))
                                    ->delete();
                            }

                            try {
                                $alertEmailService->sendVendOfflineNotificationMail($vend, $label);
                            } catch (\Throwable $e) {
                                Log::error('SyncVendOnlineStatus: Failed to send offline mail for '.$vend->code, ['error' => $e->getMessage()]);
                            }
                            $vend->offline_notification_level = $level;
                            $vend->is_offline_notification_sent = true;
                        }

                        // Restoration Check
                        if ($duration < 15 && ($currentLevel > 0 || $vend->is_offline_notification_sent)) {
                            try {
                                $alertEmailService->sendVendPowerRestoredNotificationMail($vend);
                            } catch (\Throwable $e) {
                                Log::error('SyncVendOnlineStatus: Failed to send restored mail for '.$vend->code, ['error' => $e->getMessage()]);
                            }
                            $vend->offline_notification_level = 0;
                            $vend->is_offline_notification_sent = false;
                            $vend->is_mqtt_offline_notified = false;
                        }

                        // Modem reset: only when the board is unreachable on both links and its modem is up
                        // (App\Support\ModemReset; the old every-minute reset at 5 min made 20-30 min loops).
                        try {
                            $this->maybeResetModem($vend, $now, $mqttService);
                        } catch (\Throwable $e) {
                            Log::error('SyncVendOnlineStatus: Failed to reset modem for '.$vend->code, ['error' => $e->getMessage()]);
                        }

                        try {
                            $this->checkControllerLink($vend, $now, $alertEmailService);
                        } catch (\Throwable $e) {
                            Log::error('SyncVendOnlineStatus: Failed controller check for '.$vend->code, ['error' => $e->getMessage()]);
                        }

                        try {
                            $this->checkTempProbe($vend, $now, $alertEmailService);
                        } catch (\Throwable $e) {
                            Log::error('SyncVendOnlineStatus: Failed probe check for '.$vend->code, ['error' => $e->getMessage()]);
                        }

                        if ($vend->isDirty()) {
                            $vend->save();
                        }
                    } catch (\Throwable $e) {
                        Log::error('SyncVendOnlineStatus: Unhandled error for vend '.($vend->code ?? 'unknown'), [
                            'error' => $e->getMessage(),
                            'trace' => $e->getTraceAsString(),
                        ]);
                    }
                }
            });

        // 3. Modem online status - Bulk update
        ModemUnit::whereHas('modemType', fn ($q) => $q->where('name', 'Air724UGB4'))
            ->update([
                'is_online' => DB::raw("CASE WHEN last_updated_at >= '{$thresholdStr}' THEN 1 ELSE 0 END"),
            ]);
    }

    /**
     * (1B) Controller not responding: the same rising buckets as the offline alert, from
     * App\Support\ControllerLink. Clears only when the controller is heard again, so a machine that
     * also goes fully offline keeps its alert.
     */
    private function checkControllerLink(Vend $vend, Carbon $now, AlertEmailService $alertEmailService): void
    {
        // Not migrated yet (deploy order, or a server that pulls by hand): nothing to record a level in.
        if (! array_key_exists('controller_silent_level', $vend->getAttributes())) {
            return;
        }

        $currentLevel = (int) $vend->controller_silent_level;

        if (ControllerLink::heardRecently($vend, $now)) {
            if ($currentLevel > 0) {
                $this->logControllerBack($vend, $currentLevel, $now);
                $vend->controller_silent_level = 0;
            }

            return;
        }

        $state = ControllerLink::state($vend, $now);
        if ($state === null) {
            return;
        }

        $hours = (float) ControllerLink::silentHours($vend, $now);
        [$level, $label] = ControllerLink::bucket($hours);
        if ($level <= $currentLevel) {
            return;
        }

        $this->logControllerSilent($vend, $label, $state, $hours, $currentLevel, $now);

        // A first alert right after the last one is the same fault dropping out again (2351 on 4 Nov).
        $cooldown = (int) config('vend.attention.controller_silent_email_cooldown_minutes', 60);
        $isRepeatDrop = $currentLevel === 0 && $vend->controller_silent_alert_at
            && $vend->controller_silent_alert_at->diffInMinutes($now) < $cooldown;

        if (config('vend.attention.controller_silent_email', true) && ! $isRepeatDrop) {
            // Like the offline alert, the level moves on even if the send fails: no re-log every minute.
            try {
                $alertEmailService->sendVendControllerSilentNotificationMail($vend, $label, $state);
                $vend->controller_silent_alert_at = $now;
            } catch (\Throwable $e) {
                Log::error('SyncVendOnlineStatus: Failed to send controller mail for '.$vend->code, ['error' => $e->getMessage()]);
            }
        }

        $vend->controller_silent_level = $level;
    }

    private function logControllerSilent(Vend $vend, string $label, string $state, float $hours, int $previousLevel, Carbon $now): void
    {
        // Only this silence's highest bucket stays, as with the offline alert.
        if ($previousLevel > 0) {
            VendLog::where('vend_id', $vend->id)
                ->where('event', 'machine_health_alert')
                ->where('context->alert_type', ControllerLink::ALERT_TYPE)
                ->where('occurred_at', '>=', $vend->temp_updated_at)
                ->delete();
            MachineHealthHistory::where('vend_id', $vend->id)
                ->where('event', 'machine_health_alert')
                ->where('alert_type', ControllerLink::ALERT_TYPE)
                ->where('occurred_at', '>=', $vend->temp_updated_at)
                ->delete();
        }

        $context = [
            'bucket' => $label,
            'alert_type' => ControllerLink::ALERT_TYPE,
            'state' => $state,
            'hours_offline' => $hours,
            'triggered_at' => $vend->temp_updated_at?->copy()->addMinutes(ControllerLink::silenceMinutes())->toIso8601String(),
        ];
        VendLog::create([
            'vend_id' => $vend->id,
            'event' => 'machine_health_alert',
            'subject' => ($state === ControllerLink::STATE_SILENT ? 'Controller not responding' : 'Machine data not arriving')." ({$label})",
            'context' => $context,
            'occurred_at' => $now,
        ]);
        MachineHealthHistory::log($vend->id, 'machine_health_alert', ControllerLink::ALERT_TYPE, $label, null, $context, $now);
    }

    private function logControllerBack(Vend $vend, int $level, Carbon $now): void
    {
        $label = array_values(ControllerLink::BUCKETS)[count(ControllerLink::BUCKETS) - $level] ?? null;
        $trigger = MachineHealthHistory::where('vend_id', $vend->id)
            ->where('event', 'machine_health_alert')
            ->where('alert_type', ControllerLink::ALERT_TYPE)
            ->orderByDesc('occurred_at')
            ->first();

        $context = [
            'bucket' => $label,
            'alert_type' => ControllerLink::ALERT_TYPE,
            'lapse_hours' => $trigger ? max(0, round(Carbon::parse($trigger->occurred_at)->diffInMinutes($now) / 60, 2)) : null,
        ];
        VendLog::create([
            'vend_id' => $vend->id,
            'event' => 'machine_health_alert_dismissed',
            'subject' => "Controller responding (was {$label})",
            'context' => $context,
            'occurred_at' => $now,
        ]);
        MachineHealthHistory::log($vend->id, 'machine_health_alert_dismissed', ControllerLink::ALERT_TYPE, (string) $label, null, $context, $now);
    }

    /**
     * (1C) Temperature probe error: a probe that read normally now sends the error value
     * (App\Support\TempProbe keeps the episode per frame). The offline alert's rising buckets, counted
     * from when the probe first failed. It rises only while the controller is still heard (otherwise
     * 1B or the offline alert has the machine) and the probe is in error right now; it clears when
     * the episode ends, i.e. every probe in it read normally for an hour.
     */
    private function checkTempProbe(Vend $vend, Carbon $now, AlertEmailService $alertEmailService): void
    {
        if (! TempProbe::isTracked($vend) || ! TempProbe::isVendingMachine($vend)) {
            return;
        }

        $currentLevel = (int) $vend->temp_probe_error_level;

        if (! $vend->temp_probe_error_since) {
            if ($currentLevel > 0) {
                $this->logTempProbeBack($vend, $currentLevel, $now);
                $vend->temp_probe_error_level = 0;
            }

            return;
        }

        if (! TempProbe::isErroring($vend) || ! ControllerLink::heardRecently($vend, $now)) {
            return;
        }

        $hours = (float) TempProbe::errorHours($vend, $now);
        if ($hours * 60 < TempProbe::errorMinutes()) {
            return;
        }

        [$level, $label] = ControllerLink::bucket($hours);
        if ($level <= $currentLevel) {
            return;
        }

        $this->logTempProbeError($vend, $label, $hours, $currentLevel, $now);

        // A probe that keeps failing and recovering emails once per cooldown, not per episode.
        $cooldown = (int) config('vend.attention.temp_probe_error_email_cooldown_minutes', 60);
        $isRepeat = $currentLevel === 0 && $vend->temp_probe_error_alert_at
            && $vend->temp_probe_error_alert_at->diffInMinutes($now) < $cooldown;

        if (config('vend.attention.temp_probe_error_email', true) && ! $isRepeat) {
            // Like the offline alert, the level moves on even if the send fails: no re-log every minute.
            try {
                $alertEmailService->sendVendTempProbeErrorNotificationMail($vend, $label);
                $vend->temp_probe_error_alert_at = $now;
            } catch (\Throwable $e) {
                Log::error('SyncVendOnlineStatus: Failed to send probe mail for '.$vend->code, ['error' => $e->getMessage()]);
            }
        }

        $vend->temp_probe_error_level = $level;
    }

    private function logTempProbeError(Vend $vend, string $label, float $hours, int $previousLevel, Carbon $now): void
    {
        // Only this episode's highest bucket stays, as with the offline alert.
        if ($previousLevel > 0) {
            VendLog::where('vend_id', $vend->id)
                ->where('event', 'machine_health_alert')
                ->where('context->alert_type', TempProbe::ALERT_TYPE)
                ->where('occurred_at', '>=', $vend->temp_probe_error_since)
                ->delete();
            MachineHealthHistory::where('vend_id', $vend->id)
                ->where('event', 'machine_health_alert')
                ->where('alert_type', TempProbe::ALERT_TYPE)
                ->where('occurred_at', '>=', $vend->temp_probe_error_since)
                ->delete();
        }

        $probes = implode(' & ', TempProbe::probes($vend));
        $context = [
            'bucket' => $label,
            'alert_type' => TempProbe::ALERT_TYPE,
            'probes' => TempProbe::probes($vend),
            'hours_offline' => $hours,
            'triggered_at' => $vend->temp_probe_error_since?->copy()->addMinutes(TempProbe::errorMinutes())->toIso8601String(),
        ];
        VendLog::create([
            'vend_id' => $vend->id,
            'event' => 'machine_health_alert',
            'subject' => "Temperature probe error: {$probes} ({$label})",
            'context' => $context,
            'occurred_at' => $now,
        ]);
        MachineHealthHistory::log($vend->id, 'machine_health_alert', TempProbe::ALERT_TYPE, $label, null, $context, $now);
    }

    private function logTempProbeBack(Vend $vend, int $level, Carbon $now): void
    {
        $label = array_values(ControllerLink::BUCKETS)[count(ControllerLink::BUCKETS) - $level] ?? null;
        $trigger = MachineHealthHistory::where('vend_id', $vend->id)
            ->where('event', 'machine_health_alert')
            ->where('alert_type', TempProbe::ALERT_TYPE)
            ->orderByDesc('occurred_at')
            ->first();

        $context = [
            'bucket' => $label,
            'alert_type' => TempProbe::ALERT_TYPE,
            'lapse_hours' => $trigger ? max(0, round(Carbon::parse($trigger->occurred_at)->diffInMinutes($now) / 60, 2)) : null,
        ];
        VendLog::create([
            'vend_id' => $vend->id,
            'event' => 'machine_health_alert_dismissed',
            'subject' => "Temperature probe reading again (was {$label})",
            'context' => $context,
            'occurred_at' => $now,
        ]);
        MachineHealthHistory::log($vend->id, 'machine_health_alert_dismissed', TempProbe::ALERT_TYPE, (string) $label, null, $context, $now);
    }

    private function maybeResetModem(Vend $vend, Carbon $now, MqttService $mqttService): void
    {
        if (! ModemReset::isResettable($vend)) {
            return;
        }
        $modemUnit = $vend->modemUnit;
        if (ModemReset::blockedBecause($vend, $modemUnit, $now) !== null || ! ModemReset::claim($vend)) {
            return;
        }

        $processed = $mqttService->publishModemParamMapping($modemUnit, 2, ['action' => 'RESET', 'time' => $now->timestamp]);
        if (! $processed) {
            return;
        }
        // QoS 0: a RESET is "now or never"; a queued one replayed on reconnect would reset a working modem.
        PublishMqtt::dispatch($processed['topic'], $processed['message'], MqttClient::QOS_AT_MOST_ONCE, $processed['connection'])
            ->onQueue('high');

        VendLog::create([
            'vend_id' => $vend->id,
            'event' => VendLog::EVENT_MODEM_RESET,
            'subject' => 'Modem reset sent',
            'context' => [
                'modem_unit_id' => $modemUnit->id,
                'http_quiet_minutes' => $vend->last_updated_at?->diffInMinutes($now),
                'mqtt_quiet_minutes' => $vend->mqtt_last_updated_at?->diffInMinutes($now),
                'modem_heard_at' => $modemUnit->last_updated_at?->toIso8601String(),
            ],
            'occurred_at' => $now,
        ]);
    }
}
