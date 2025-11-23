<?php

namespace App\Jobs;

use App\Models\DeliveryPlatformMenuRecord;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

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
        DeliveryPlatformMenuRecord::updateOrCreate([
            'ref_id' => $this->data['jobID'],
        ], [
            'request_json' => $this->data,
            'delivery_platform_slug' => 'grab-menu',
            'platform_ref_id' => $this->data['merchantID'],
            'vend_code' => $this->data['partnerMerchantID'],
        ]);
    }
}
