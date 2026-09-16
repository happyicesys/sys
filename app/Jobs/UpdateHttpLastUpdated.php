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

    // tries = 0 meant "retry forever" and 2 s was killing the worker on any lock
    // wait on the hot vends row (audit M3-06, 2026-09-16).
    public $tries = 3;

    public $backoff = [5, 30];

    public $timeout = 10;

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
        if (! $vend) {
            return;
        }

        $now = Carbon::now();
        if (! $vend->last_updated_at || $vend->last_updated_at->diffInSeconds($now) >= 30 || ! $vend->is_online) {
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
