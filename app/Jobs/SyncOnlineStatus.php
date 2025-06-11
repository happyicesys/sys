<?php

namespace App\Jobs;

use App\Mail\VendMqttOfflineNotificationMail;
use App\Mail\VendOfflineNotificationMail;
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
    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        $this->mqttService = new MqttService();
        $this->emailRecipients = [
            'daniel.ma@happyice.com.sg',
            'kent@happyice.com.sg',
            // 'stephen@happyice.com.sg',
            'brianlee@happyice.com.my',
            'technician1@happyice.com.sg',
        ];
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // Use chunking to process Vends in batches
        Vend::has('customer')->where('is_active', true)->chunk(100, function($vends) {
            foreach($vends as $vend) {
                // Sync online status
                $vend->is_online = $vend->last_updated_at && $vend->last_updated_at->diffInMinutes(Carbon::now()) < 15 ? true : false;
                $vend->is_temp_active = $vend->temp_updated_at && $vend->temp_updated_at->diffInMinutes(Carbon::now()) < 15 ? true : false;

                // Trigger modem reset if necessary
                if ($vend->last_updated_at && $vend->last_updated_at->diffInMinutes(Carbon::now()) >= 5 && $vend->modemType?->is_resetable && $vend->modem_unit_id && $vend->modem_unit_id != 65) {
                    $modemUnit = ModemUnit::find($vend->modem_unit_id);
                    if ($modemUnit) {
                        $content = [
                            'action' => 'RESET',
                            'time' => Carbon::now()->timestamp,
                        ];
                        $processedData = $this->mqttService->publishModemParamMapping($modemUnit, 2, $content);
                        PublishMqtt::dispatch($processedData['topic'], $processedData['message'], $processedData['qos'], $processedData['connection'])->onQueue('high');
                    }
                }

                if ($vend->is_online && $vend->is_offline_notification_sent) {
                    $vend->is_offline_notification_sent = false;
                }
                if ($vend->is_mqtt && $vend->is_mqtt_active && $vend->is_mqtt_offline_notified) {
                    $vend->is_mqtt_offline_notified = false;
                }

                // Send offline notification mail after 60 minutes
                if ($vend->last_updated_at && $vend->last_updated_at->diffInMinutes(Carbon::now()) >= 60 && !$vend->is_offline_notification_sent) {
                    Mail::to($this->emailRecipients)->send(new VendOfflineNotificationMail($vend));
                    $vend->is_offline_notification_sent = true;
                }

                // Handle MQTT status
                if ($vend->is_mqtt) {
                    $vend->is_mqtt_active = $vend->mqtt_last_updated_at && $vend->mqtt_last_updated_at->diffInMinutes(Carbon::now()) < 15;
                    if ($vend->mqtt_last_updated_at && $vend->mqtt_last_updated_at->diffInMinutes(Carbon::now()) > 60 && !$vend->is_mqtt_offline_notified) {
                        Mail::to($this->emailRecipients)->send(new VendMqttOfflineNotificationMail($vend));
                        $vend->is_mqtt_offline_notified = true;
                    }
                }

                $vend->save();
            }
        });

        ModemUnit::whereHas('modemType', function($query) {
            $query->where('name', 'Air724UGB4');
        })->chunk(100, function($modems) {
            foreach($modems as $modem) {
                $modem->is_online = $modem->last_updated_at && $modem->last_updated_at->diffInMinutes(Carbon::now()) < 15 ? true : false;
                $modem->save();
            }
        });
    }
}
