<?php

namespace App\Jobs\Vend;

use App\Mail\VendPowerRestoredNotificationMail;
use App\Models\Vend;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class UpdateMqttLastUpdated implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 0;
    public $timeout = 2;

    protected $vend;
    /**
     * Create a new job instance.
     */
    public function __construct(Vend $vend)
    {
        $this->vend = $vend;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $this->vend->update([
            'is_mqtt' => true,
            'is_mqtt_active' => true,
            'mqtt_last_updated_at' => Carbon::now(),
        ]);

        // if($this->vend->is_offline_notification_sent) {
        //     Mail::to([
        //         'daniel.ma@happyice.com.sg',
        //         'kent@happyice.com.sg',
        //         // 'stephen@happyice.com.sg',
        //         'brianlee@happyice.com.my',
        //         'technician1@happyice.com.sg',
        //     ])->queue(new VendPowerRestoredNotificationMail($this->vend));
        //     $this->vend->update([
        //         'is_offline_notification_sent' => false,
        //         'is_mqtt_offline_notified' => false,
        //     ]);
        // }
    }
}
