<?php

namespace App\Jobs;

use App\Models\ModemUnit;
use App\Models\Vend;
use App\Jobs\PublishMqtt;
use App\Services\MqttService;
use App\Services\AlertEmailService;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class SyncOnlineStatus implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct()
    {
    }

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
                            $isMqttOffline = !$vend->mqtt_last_updated_at || $vend->mqtt_last_updated_at->diffInMinutes($now) > 30;

                            if ($isMqttOffline && !$vend->is_mqtt_offline_notified) {
                                $vend->is_mqtt_offline_notified = true;
                            }
                        }

                        $dates = collect([
                            $vend->last_updated_at,
                            $vend->last_vend_transaction_at,
                            $vend->offline_restart_count_datetime,
                            $vend->is_mqtt ? $vend->mqtt_last_updated_at : null
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
                                if ($currentLevel >= 1)
                                    $oldLabels[] = '< 1hr';
                                if ($currentLevel >= 2)
                                    $oldLabels[] = '< 2hr';
                                if ($currentLevel >= 3)
                                    $oldLabels[] = '< 4hr';
                                if ($currentLevel >= 4)
                                    $oldLabels[] = '< 8hr';
                                if ($currentLevel >= 5)
                                    $oldLabels[] = '< 12hr';

                                \App\Models\VendLog::where('vend_id', $vend->id)
                                    ->where('event', \App\Models\VendLog::EVENT_POWER_OFF)
                                    ->whereIn('context->label', $oldLabels)
                                    ->where('created_at', '>=', now()->subHours(48))
                                    ->delete();
                            }

                            try {
                                $alertEmailService->sendVendOfflineNotificationMail($vend, $label);
                            } catch (\Throwable $e) {
                                Log::error('SyncVendOnlineStatus: Failed to send offline mail for ' . $vend->code, ['error' => $e->getMessage()]);
                            }
                            $vend->offline_notification_level = $level;
                            $vend->is_offline_notification_sent = true;
                        }

                        // Restoration Check
                        if ($duration < 15 && ($currentLevel > 0 || $vend->is_offline_notification_sent)) {
                            try {
                                $alertEmailService->sendVendPowerRestoredNotificationMail($vend);
                            } catch (\Throwable $e) {
                                Log::error('SyncVendOnlineStatus: Failed to send restored mail for ' . $vend->code, ['error' => $e->getMessage()]);
                            }
                            $vend->offline_notification_level = 0;
                            $vend->is_offline_notification_sent = false;
                            $vend->is_mqtt_offline_notified = false;
                        }

                        // Modem reset if HTTP offline > 5 min
                        if (
                            $vend->last_updated_at &&
                            $vend->last_updated_at->diffInMinutes($now) >= 5 &&
                            $vend->modemType?->is_resetable &&
                            $vend->modem_unit_id &&
                            $vend->modem_unit_id != 65
                        ) {
                            try {
                                $modemUnit = $vend->modemUnit;
                                if ($modemUnit) {
                                    $content = ['action' => 'RESET', 'time' => $now->timestamp];
                                    $processed = $mqttService->publishModemParamMapping($modemUnit, 2, $content);
                                    if ($processed) {
                                        PublishMqtt::dispatch(
                                            $processed['topic'],
                                            $processed['message'],
                                            $processed['qos'],
                                            $processed['connection']
                                        )->onQueue('high');
                                    }
                                }
                            } catch (\Throwable $e) {
                                Log::error('SyncVendOnlineStatus: Failed to reset modem for ' . $vend->code, ['error' => $e->getMessage()]);
                            }
                        }

                        if ($vend->isDirty()) {
                            $vend->save();
                        }
                    } catch (\Throwable $e) {
                        Log::error('SyncVendOnlineStatus: Unhandled error for vend ' . ($vend->code ?? 'unknown'), [
                            'error' => $e->getMessage(),
                            'trace' => $e->getTraceAsString()
                        ]);
                    }
                }
            });

        // 3. Modem online status - Bulk update
        ModemUnit::whereHas('modemType', fn($q) => $q->where('name', 'Air724UGB4'))
            ->update([
                'is_online' => DB::raw("CASE WHEN last_updated_at >= '{$thresholdStr}' THEN 1 ELSE 0 END")
            ]);
    }
}
