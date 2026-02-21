<?php

namespace App\Jobs;

use App\Mail\VendPowerRestoredNotificationMail;
use App\Models\Scopes\OperatorVendFilterScope;
use App\Models\Vend;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class UpdateHttpLastUpdated implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 0;
    public $timeout = 2;

    protected $vendID;
    /**
     * Create a new job instance.
     */
    public function __construct($vendID)
    {
        $this->vendID = $vendID;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $vend = Vend::withoutGlobalScope(OperatorVendFilterScope::class)->find($this->vendID);
        if (!$vend) {
            return;
        }

        $now = Carbon::now();
        if (!$vend->last_updated_at || $vend->last_updated_at->diffInSeconds($now) >= 30 || !$vend->is_online) {
            $vend->update([
                'last_updated_at' => clone $now,
                'is_online' => true,
            ]);
        }

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
        //     ]);
        // }
    }
}
