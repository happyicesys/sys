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

class SyncOnlineStatus implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected MqttService $mqttService;
    protected AlertEmailService $alertEmailService;

    public function __construct()
    {
        $this->mqttService = new MqttService();
        $this->alertEmailService = new AlertEmailService();
    }

    public function handle(): void
    {
        $now = Carbon::now();

        Vend::chunkById(200, function ($vends) use ($now) {
            foreach ($vends as $vend) {
                try {
                    // 1. Core online statuses, updated for ALL machines
                    $vend->is_online = $vend->last_updated_at && $vend->last_updated_at->diffInMinutes($now) < 15;
                    $vend->is_temp_active = $vend->temp_updated_at && $vend->temp_updated_at->diffInMinutes($now) < 15;

                    if ($vend->is_mqtt) {
                        $vend->is_mqtt_active = $vend->mqtt_last_updated_at && $vend->mqtt_last_updated_at->diffInMinutes($now) < 15;
                    }

                    // Skip the alert logic if not active, testing, no customer, specific codes, or specific operator
                    if (!$vend->customer_id || !$vend->is_active || $vend->is_testing || in_array((string) $vend->code, ['808', '6001', '6002', '831']) || $vend->operator_id == 23) {
                        $vend->save();
                        continue;
                    }

                    // 2. Alert & Modems Reset logic
                    $isMqttOffline = false;
                    $isMqttRecovered = false;

                    if ($vend->is_mqtt) {
                        $isMqttOffline = !$vend->mqtt_last_updated_at || $vend->mqtt_last_updated_at->diffInMinutes($now) > 30;
                        $isMqttRecovered = $vend->mqtt_last_updated_at && $vend->mqtt_last_updated_at->diffInMinutes($now) < 15;

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
                            $this->alertEmailService->sendVendOfflineNotificationMail($vend, $label);
                        } catch (\Throwable $e) {
                            Log::error('SyncVendOnlineStatus: Failed to send offline mail for ' . $vend->code, ['error' => $e->getMessage()]);
                        }
                        $vend->offline_notification_level = $level;
                        $vend->is_offline_notification_sent = true;
                    }

                    // Restoration Check
                    if ($duration < 15 && ($currentLevel > 0 || $vend->is_offline_notification_sent)) {
                        try {
                            $this->alertEmailService->sendVendPowerRestoredNotificationMail($vend);
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
                            $modemUnit = ModemUnit::find($vend->modem_unit_id);
                            if ($modemUnit) {
                                $content = ['action' => 'RESET', 'time' => $now->timestamp];
                                $processed = $this->mqttService->publishModemParamMapping($modemUnit, 2, $content);
                                PublishMqtt::dispatch(
                                    $processed['topic'],
                                    $processed['message'],
                                    $processed['qos'],
                                    $processed['connection']
                                )->onQueue('high');
                            }
                        } catch (\Throwable $e) {
                            Log::error('SyncVendOnlineStatus: Failed to reset modem for ' . $vend->code, ['error' => $e->getMessage()]);
                        }
                    }

                    $vend->save();
                } catch (\Throwable $e) {
                    Log::error('SyncVendOnlineStatus: Unhandled error for vend ' . ($vend->code ?? 'unknown'), [
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString()
                    ]);
                }
            }
        });

        // Modem online status
        ModemUnit::whereHas('modemType', fn($q) => $q->where('name', 'Air724UGB4'))
            ->chunkById(100, function ($modems) use ($now) {
                foreach ($modems as $modem) {
                    $modem->is_online = $modem->last_updated_at && $modem->last_updated_at->diffInMinutes($now) < 15;
                    $modem->save();
                }
            });
    }
}
