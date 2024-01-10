<?php

namespace App\Jobs;

use App\Models\DeliveryPlatformCampaignItemVend;
use App\Services\DeliveryPlatformCampaignService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class CreateDeliveryPlatformCampaign implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $deliveryPlatformCampaignItemVend;
    protected $deliveryPlatformCampaignService;
    public $tries = 1;
    /**
     * Create a new job instance.
     */
    public function __construct(DeliveryPlatformCampaignItemVend $deliveryPlatformCampaignItemVend)
    {
        $this->deliveryPlatformCampaignItemVend = $deliveryPlatformCampaignItemVend;
        $this->deliveryPlatformCampaignService = new DeliveryPlatformCampaignService();
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $this->deliveryPlatformCampaignService->createCampaign($this->deliveryPlatformCampaignItemVend);
    }
}
