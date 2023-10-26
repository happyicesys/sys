<?php

namespace App\Jobs;

use App\Models\DeliveryProductMappingVendChannel;
use App\Services\DeliveryPlatformService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class UpdateDeliveryPlatformMenu implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $deliveryPlatformService;
    protected $deliveryProductMappingVendChannel;
    /**
     * Create a new job instance.
     */
    public function __construct(DeliveryProductMappingVendChannel $deliveryProductMappingVendChannel)
    {
        $this->deliveryPlatformService = new DeliveryPlatformService();
        $this->deliveryProductMappingVendChannel = $deliveryProductMappingVendChannel;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $this->deliveryPlatformService->updateMenu($this->deliveryProductMappingVendChannel);
    }
}
