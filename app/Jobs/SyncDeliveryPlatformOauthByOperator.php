<?php

namespace App\Jobs;

use App\Models\Operator;
use App\Models\DeliveryPlatformOperator;
use App\Services\DeliveryPlatformService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SyncDeliveryPlatformOauthByOperator
//implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private $deliveryPlatformService;
    protected $deliveryPlatformOperator;
    // protected $operatorId;
    // protected $type;
    /**
     * Create a new job instance.
     */
    public function __construct(DeliveryPlatformOperator $deliveryPlatformOperator)
    {
        $this->deliveryPlatformService = new DeliveryPlatformService();
        $this->deliveryPlatformOperator = $deliveryPlatformOperator;
        // $this->operatorId = $operatorId;
        // $this->type = $type;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $response = $this->deliveryPlatformService->getOauth($this->deliveryPlatformOperator);

        if(!$this->deliveryPlatformService->getDeliveryPlatformOperator()->externalOauthToken()->exists()) {
            throw new \Exception('Please set init Oauth Client ID and Client Secret');
        }
        $this->deliveryPlatformService->getDeliveryPlatformOperator()->externalOauthToken()->update($response);
    }
}
