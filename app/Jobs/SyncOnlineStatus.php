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

        Vend::has('customer')
            ->where('is_active', true)
            ->where('is_testing', false)
            ->whereNotIn('code', ['808', '6001', '6002', '831'])
            ->where('operator_id', '!=', 23)
            ->chunk(100, function ($vends) use ($now) {
                foreach ($vends as $vend) {
                    // online flags
                    $vend->is_online = $vend->last_updated_at && $vend->last_updated_at->diffInMinutes($now) < 15;
                    $vend->is_temp_active = $vend->temp_updated_at && $vend->temp_updated_at->diffInMinutes($now) < 15;

                    $isHttpOffline = !$vend->last_updated_at || $vend->last_updated_at->diffInMinutes($now) >= 50;
                    $isHttpRecovered = $vend->last_updated_at && $vend->last_updated_at->diffInMinutes($now) < 15;

                    // MQTT flags
                    $isMqttOffline = false;
                    $isMqttRecovered = false;

                    if ($vend->is_mqtt) {
                        $vend->is_mqtt_active = $vend->mqtt_last_updated_at && $vend->mqtt_last_updated_at->diffInMinutes($now) < 15;
                        $isMqttOffline = !$vend->mqtt_last_updated_at || $vend->mqtt_last_updated_at->diffInMinutes($now) > 30;
                        $isMqttRecovered = $vend->mqtt_last_updated_at && $vend->mqtt_last_updated_at->diffInMinutes($now) < 15;

                        if ($isMqttOffline && !$vend->is_mqtt_offline_notified) {
                            // If you want a separate MQTT-offline mail, add a service method similar to offline
                            $vend->is_mqtt_offline_notified = true;
                        }
                    }

                    // OFFLINE & RESTORED Check (Tiered Alerts)
                    // Calculate effective last contact time
                    $dates = [
                        $vend->last_updated_at,
                        $vend->last_vend_transaction_at,
                        $vend->offline_restart_count_datetime
                    ];
                    if ($vend->is_mqtt) {
                        $dates[] = $vend->mqtt_last_updated_at;
                    }

                    $lastContact = null;
                    foreach ($dates as $date) {
                        if ($date && ($lastContact === null || $date->gt($lastContact))) {
                            $lastContact = $date;
                        }
                    }

                    $duration = $lastContact ? $now->diffInMinutes($lastContact) : 999999;

                    // Determine current level
                    $level = 0;
                    $label = null;

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
                    } elseif ($duration >= 50) {
                        $level = 1;
                        $label = '< 1hr';
                    }

                    // Send Alert if Level Increased
                    if ($level > $vend->offline_notification_level) {
                        try {
                            $this->alertEmailService->sendVendOfflineNotificationMail($vend, $label);
                        } catch (\Throwable $e) {
                            Log::error('SyncVendOnlineStatus: Failed to send offline mail for ' . $vend->code, ['error' => $e->getMessage()]);
                        }
                        $vend->offline_notification_level = $level;
                        $vend->is_offline_notification_sent = true; // Sync legacy flag
                    }

                    // Restoration Check (if duration < 15 mins and was previously alerted)
                    if ($duration < 15 && ($vend->offline_notification_level > 0 || $vend->is_offline_notification_sent)) {
                        try {
                            $this->alertEmailService->sendVendPowerRestoredNotificationMail($vend);
                        } catch (\Throwable $e) {
                            Log::error('SyncVendOnlineStatus: Failed to send restored mail for ' . $vend->code, ['error' => $e->getMessage()]);
                        }
                        $vend->offline_notification_level = 0;
                        $vend->is_offline_notification_sent = false; // Sync legacy flag
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
                }
            });

        // Vends without customer: just update flags
        Vend::doesntHave('customer')
            ->chunk(100, function ($vends) use ($now) {
                foreach ($vends as $vend) {
                    $vend->is_online = $vend->last_updated_at && $vend->last_updated_at->diffInMinutes($now) < 15;
                    $vend->is_temp_active = $vend->temp_updated_at && $vend->temp_updated_at->diffInMinutes($now) < 15;
                    $vend->save();
                }
            });

        // Modem online status
        ModemUnit::whereHas('modemType', fn($q) => $q->where('name', 'Air724UGB4'))
            ->chunk(100, function ($modems) use ($now) {
                foreach ($modems as $modem) {
                    $modem->is_online = $modem->last_updated_at && $modem->last_updated_at->diffInMinutes($now) < 15;
                    $modem->save();
                }
            });
    }
}
