<?php

namespace App\Jobs;

use App\Models\DeliveryPlatformOrder;
use App\Services\DeliveryPlatformService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Grab's follow-up for a newly stored order: mark it ready, or cancel it when no
 * channel can serve it. These used to run inside the order webhook, where a slow
 * Grab answer pushed the webhook past Grab's 10 s and Grab re-sent the order.
 */
class ProcessDeliveryPlatformOrder implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;

    public $timeout = 60;

    public function __construct(public DeliveryPlatformOrder $deliveryPlatformOrder) {}

    public function backoff(): array
    {
        return [10, 60];
    }

    public function handle(DeliveryPlatformService $deliveryPlatformService): void
    {
        $deliveryPlatformService->processNewOrder($this->deliveryPlatformOrder);
    }
}
