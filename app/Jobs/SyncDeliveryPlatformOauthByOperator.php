<?php

namespace App\Jobs;

use App\Models\Operator;
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
    protected $operatorId;
    protected $type;
    /**
     * Create a new job instance.
     */
    public function __construct($operatorId, $type = 'grab')
    {
        $this->deliveryPlatformService = new DeliveryPlatformService();
        $this->operatorId = $operatorId;
        $this->type = $type;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $operator = Operator::findOrFail($this->operatorId);
        $response = $this->deliveryPlatformService->getOauth($operator, $this->type);
        if(!$this->deliveryPlatformService->getDeliveryPlatformOperator()->externalOauthToken()->exists()) {
            throw new \Exception('Please set init Oauth Client ID and Client Secret');
        }
        $this->deliveryPlatformService->getDeliveryPlatformOperator()->externalOauthToken()->update($response);
    }
}
