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

                    // OFFLINE: per-vend mail via service (no hardcoded recipients)
                    // Logic update: Ensure that if a vend is flagged as MQTT, we don't alert if HTTP is offline but MQTT is online (or vice versa).
                    // We only alert if it appears to be truly offline on all expected channels.
                    $isOffline = $isHttpOffline;
                    if ($vend->is_mqtt) {
                        $isOffline = $isHttpOffline && $isMqttOffline;
                    }

                    if ($isOffline && !$vend->is_offline_notification_sent) {
                        $this->alertEmailService->sendVendOfflineNotificationMail($vend);
                        $vend->is_offline_notification_sent = true;
                    }

                    // RESTORED: per-vend mail via service (no hardcoded recipients)
                    $isRecovered = $isHttpRecovered;
                    if ($vend->is_mqtt) {
                        $isRecovered = $isHttpRecovered || $isMqttRecovered;
                    }

                    if ($isRecovered && $vend->is_offline_notification_sent) {
                        $this->alertEmailService->sendVendPowerRestoredNotificationMail($vend);
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
