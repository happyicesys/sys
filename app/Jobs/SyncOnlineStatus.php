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

class SyncOnlineStatus implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $mqttService;
    protected $emailRecipients;

    public function __construct()
    {
        $this->mqttService = new MqttService();

        if(env('APP_URL') == 'https://idn-sys.happyice.net') {
            $this->emailRecipients = [
                'daniel.ma@happyice.com.sg',
                'brianlee@happyice.com.my',
                'it.gentong@gmail.com',
                'afa7heaven@gmail.com',
                'arganopraiskoti@gmail.com',
                'yardizhen@gmail.com',
                'Rhpmail@gmail.com'
            ];
        }else {
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
        Vend::has('customer')
            ->where('is_active', true)
            ->where('is_testing', false)
            ->whereNotIn('code', ['808', '6001', '6002', '831'])
            ->where('operator_id', '!=', 23)
            ->chunk(100, function($vends) {

            foreach($vends as $vend) {
                $now = Carbon::now();

                // Update online status
                $vend->is_online = $vend->last_updated_at && $vend->last_updated_at->diffInMinutes($now) < 15;
                $vend->is_temp_active = $vend->temp_updated_at && $vend->temp_updated_at->diffInMinutes($now) < 15;

                // Handle MQTT status
                if ($vend->is_mqtt) {
                    $vend->is_mqtt_active = $vend->mqtt_last_updated_at && $vend->mqtt_last_updated_at->diffInMinutes($now) < 15;

                    // MQTT offline notification
                    if (
                        $vend->mqtt_last_updated_at &&
                        $vend->mqtt_last_updated_at->diffInMinutes($now) > 30 &&
                        !$vend->is_mqtt_offline_notified
                    ) {
                        // Mail::to($this->emailRecipients)->send(new VendMqttOfflineNotificationMail($vend));
                        $vend->is_mqtt_offline_notified = true;
                    }
                }

                // Trigger modem reset
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

                // Restore notification (either HTTP or MQTT back online)
                $httpRecovered = $vend->is_online;
                $mqttRecovered = !$vend->is_mqtt_offline_notified || $vend->is_mqtt_active;
                if (($httpRecovered || $mqttRecovered) && $vend->is_offline_notification_sent) {
                    Mail::to($this->emailRecipients)->queue(new VendPowerRestoredNotificationMail($vend));
                    $vend->is_offline_notification_sent = false;
                }

                // Send offline notification if fully offline for 60+ mins
                // 60 to 40 minutes for online check
                if (
                    $vend->last_updated_at &&
                    $vend->last_updated_at->diffInMinutes($now) >= 50 &&
                    !$vend->is_offline_notification_sent
                ) {
                    Mail::to($this->emailRecipients)->queue(new VendOfflineNotificationMail($vend));
                    $vend->is_offline_notification_sent = true;
                }

                $vend->save();
            }
        });

        // Sync ModemUnit online status
        ModemUnit::whereHas('modemType', function($query) {
            $query->where('name', 'Air724UGB4');
        })->chunk(100, function($modems) {
            foreach($modems as $modem) {
                $modem->is_online = $modem->last_updated_at && $modem->last_updated_at->diffInMinutes(Carbon::now()) < 15;
                $modem->save();
            }
        });
    }
}
