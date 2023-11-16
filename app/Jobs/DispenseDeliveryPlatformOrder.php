<?php

namespace App\Jobs;

use App\Models\DeliveryPlatformOrder;
use App\Services\DeliveryPlatformService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class DispenseDeliveryPlatformOrder
//implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $deliveryPlatformOrder;
    protected $deliveryPlatformService;
    /**
     * Create a new job instance.
     */
    public function __construct(DeliveryPlatformOrder $deliveryPlatformOrder)
    {
        $this->deliveryPlatformOrder = $deliveryPlatformOrder;
        $this->deliveryPlatformService = new DeliveryPlatformService();
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $this->deliveryPlatformService->dispenseOrder($this->deliveryPlatformOrder);
    }
}
