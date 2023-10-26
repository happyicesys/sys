<?php

namespace App\Jobs;

use App\Models\DeliveryProductMappingVend;
use App\Services\DeliveryPlatformService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class NotifyDeliveryPlatformUpdateMenu implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $deliveryPlatformService;
    protected $deliveryProductMappingVend;
    /**
     * Create a new job instance.
     */
    public function __construct(DeliveryProductMappingVend $deliveryProductMappingVend)
    {
        $this->deliveryPlatformService = new DeliveryPlatformService();
        $this->deliveryProductMappingVend = $deliveryProductMappingVend;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $this->deliveryPlatformService->notifyUpdatedMenu($this->deliveryProductMappingVend);
    }
}
