<?php

namespace App\Jobs;

use App\Models\DeliveryPlatformMenuRecord;
use App\Services\Grab\Menu\GrabMenuSyncTracker;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;

class ProcessGrabMenuWebhook implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $data;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(array $data)
    {
        $this->data = $data;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $data = [
            'request_json' => $this->data,
            'delivery_platform_slug' => 'grab-menu',
            'platform_ref_id' => $this->data['merchantID'] ?? null,
        ];

        if (array_key_exists('partnerMerchantID', $this->data)) {
            $data['vend_code'] = $this->data['partnerMerchantID'];
        }

        DeliveryPlatformMenuRecord::updateOrCreate([
            'ref_id' => $this->data['jobID'] ?? null,
        ], $data);

        // Grab's verdict on the menu it pulled: SUCCESS finishes the store's open Grab Menu Sync rows.
        $at = null;
        try {
            $at = isset($this->data['updatedAt']) ? Carbon::parse($this->data['updatedAt'])->setTimezone(config('app.timezone')) : null;
        } catch (\Throwable) {
        }
        app(GrabMenuSyncTracker::class)->grabAnswered(
            $this->data['merchantID'] ?? null,
            $this->data['status'] ?? null,
            $at,
            (array) ($this->data['errors'] ?? $this->data['errorMessages'] ?? []),
        );
    }
}
