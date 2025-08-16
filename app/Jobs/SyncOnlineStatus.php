<?php

namespace App\Jobs;

use App\Mail\VendMqttOfflineNotificationMail;
use App\Mail\VendOfflineNotificationMail;
use App\Mail\VendPowerRestoredNotificationMail;
use App\Models\ModemUnit;
use App\Models\Vend;
use App\Jobs\PublishMqtt;
use App\Services\MqttService;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class SyncOnlineStatus implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $mqttService;
    protected $emailRecipients;

    public function __construct()
    {
        $this->mqttService = new MqttService();

        if (env('APP_URL') === 'https://idn-sys.happyice.net') {
            $this->emailRecipients = [
                'daniel.ma@happyice.com.sg',
                'brianlee@happyice.com.my',
                'it.gentong@gmail.com',
                'afa7heaven@gmail.com',
                'arganopraiskoti@gmail.com',
                'yardizhen@gmail.com',
                'Rhpmail@gmail.com'
            ];
        } else {
            $this->emailRecipients = [
                'daniel.ma@happyice.com.sg',
                'kent@happyice.com.sg',
                'brianlee@happyice.com.my',
                'technician1@happyice.com.sg'
            ];
        }
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
                    // Update online status
                    $vend->is_online = $vend->last_updated_at && $vend->last_updated_at->diffInMinutes($now) < 15;
                    $vend->is_temp_active = $vend->temp_updated_at && $vend->temp_updated_at->diffInMinutes($now) < 15;

                    $isHttpOffline = !$vend->last_updated_at || $vend->last_updated_at->diffInMinutes($now) >= 50;
                    $isHttpRecovered = $vend->last_updated_at && $vend->last_updated_at->diffInMinutes($now) < 15;

                    // Handle MQTT status
                    $isMqttOffline = false;
                    $isMqttRecovered = false;

                    if ($vend->is_mqtt) {
                        $vend->is_mqtt_active = $vend->mqtt_last_updated_at && $vend->mqtt_last_updated_at->diffInMinutes($now) < 15;

                        $isMqttOffline = !$vend->mqtt_last_updated_at || $vend->mqtt_last_updated_at->diffInMinutes($now) > 30;
                        $isMqttRecovered = $vend->mqtt_last_updated_at && $vend->mqtt_last_updated_at->diffInMinutes($now) < 15;

                        if ($isMqttOffline && !$vend->is_mqtt_offline_notified) {
                            // Mail::to($this->emailRecipients)->send(new VendMqttOfflineNotificationMail($vend));
                            $vend->is_mqtt_offline_notified = true;
                        }
                    }

                    // Send offline email
                    if (($isHttpOffline || $isMqttOffline) && !$vend->is_offline_notification_sent) {
                        Mail::to($this->emailRecipients)->queue(new VendOfflineNotificationMail($vend));
                        $vend->is_offline_notification_sent = true;
                    }

                    // Send restored email
                    if ($isHttpRecovered && ($isMqttRecovered || !$vend->is_mqtt) && $vend->is_offline_notification_sent) {
                        Mail::to($this->emailRecipients)->queue(new VendPowerRestoredNotificationMail($vend));
                        $vend->is_offline_notification_sent = false;
                        $vend->is_mqtt_offline_notified = false;
                    }

                    // Trigger modem reset if HTTP offline > 5 min
                    if (
                        $vend->last_updated_at &&
                        $vend->last_updated_at->diffInMinutes($now) >= 5 &&
                        $vend->modemType?->is_resetable &&
                        $vend->modem_unit_id &&
                        $vend->modem_unit_id != 65
                    ) {
                        $modemUnit = ModemUnit::find($vend->modem_unit_id);
                        if ($modemUnit) {
                            $content = [
                                'action' => 'RESET',
                                'time' => $now->timestamp,
                            ];
                            $processedData = $this->mqttService->publishModemParamMapping($modemUnit, 2, $content);
                            PublishMqtt::dispatch(
                                $processedData['topic'],
                                $processedData['message'],
                                $processedData['qos'],
                                $processedData['connection']
                            )->onQueue('high');
                        }
                    }

                    $vend->save();
                }
        });

        Vend::doesntHave('customer')
            ->chunk(100, function ($vends) use ($now) {
                foreach ($vends as $vend) {
                    // Update online status
                    $vend->is_online = $vend->last_updated_at && $vend->last_updated_at->diffInMinutes($now) < 15;
                    $vend->is_temp_active = $vend->temp_updated_at && $vend->temp_updated_at->diffInMinutes($now) < 15;

                    Log::info("Vend {$vend->code} online status updated: {$vend->is_online}");

                    $vend->save();
                }
            });

        // Update ModemUnit online status
        ModemUnit::whereHas('modemType', function ($query) {
            $query->where('name', 'Air724UGB4');
        })->chunk(100, function ($modems) use ($now) {
            foreach ($modems as $modem) {
                $modem->is_online = $modem->last_updated_at && $modem->last_updated_at->diffInMinutes($now) < 15;
                $modem->save();
            }
        });
    }
}
