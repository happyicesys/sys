<?php

namespace App\Console\Commands;

use App\Mail\VendMqttOfflineNotificationMail;
use App\Mail\VendOfflineNotificationMail;
use App\Models\Vend;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class SyncVendOnlineStatus extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sync:vend-online-status';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Run scheduler check online status (more than 5 mins last updated time becomes offline)';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $vends = Vend::all();

        foreach($vends as $vend) {
            // sync online offline
            $vend->is_online = false;
            if($vend->last_updated_at and $vend->last_updated_at->diffInMinutes(Carbon::now()) < 5) {
                $vend->is_online = true;
                $vend->is_offline_notification_sent = false;
            }
            // send offline notification mail after 30 mins
            if($vend->last_updated_at and $vend->last_updated_at->diffInMinutes(Carbon::now()) >= 60) {
                if(!$vend->is_offline_notification_sent) {
                    Mail::to([
                        'daniel.ma@happyice.com.sg',
                        'kent@happyice.com.sg',
                        'stephen@happyice.com.sg',
                        'brianlee@happyice.com.my',
                        'technician1@happyice.com.sg',
                    ])->send(new VendOfflineNotificationMail($vend));

                    $vend->is_offline_notification_sent = true;
                }
            }

            // sync mqtt if offline
            if($vend->is_mqtt) {
                if($vend->mqtt_last_updated_at and $vend->mqtt_last_updated_at->diffInMinutes(Carbon::now()) <= 30) {
                    $vend->is_mqtt_active = true;
                    $vend->is_mqtt_offline_notified = false;
                }

                if($vend->mqtt_last_updated_at and $vend->mqtt_last_updated_at->diffInMinutes(Carbon::now()) > 30) {
                    $vend->is_mqtt_active = false;
                    if(!$vend->is_mqtt_offline_notified) {
                        Mail::to([
                            'daniel.ma@happyice.com.sg',
                            'kent@happyice.com.sg',
                            'stephen@happyice.com.sg',
                            'brianlee@happyice.com.my',
                            'technician1@happyice.com.sg',
                        ])->send(new VendMqttOfflineNotificationMail($vend));
                        $vend->is_mqtt_offline_notified = true;
                    }
                }
            }

            $vend->save();
        }
    }
}
