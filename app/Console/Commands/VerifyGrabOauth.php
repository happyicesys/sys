<?php

namespace App\Console\Commands;

use App\Models\DeliveryPlatformOperator;
use App\Services\DeliveryPlatformService;
use Illuminate\Console\Command;

class VerifyGrabOauth extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'grab:verify-oauth';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Auto verify oauth every midnight';

    /**
     * Execute the console command.
     */

     public function __construct(DeliveryPlatformService $deliveryPlatformService)
     {
        parent::__construct();
        $this->deliveryPlatformService = $deliveryPlatformService;
     }

    public function handle()
    {
        $deliveryPlatformOperators = DeliveryPlatformOperator::all();

        if($deliveryPlatformOperators) {
            foreach($deliveryPlatformOperators as $deliveryPlatformOperator) {
                if($deliveryPlatformOperator->externalOauthToken()->exists()) {
                    $this->deliveryPlatformService->getCategories($deliveryPlatformOperator);
                }
            }
        }
    }
}
