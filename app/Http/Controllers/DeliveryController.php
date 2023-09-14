<?php

namespace App\Http\Controllers;

use App\Models\Operator;
use App\Models\Vend;
use App\Jobs\SyncDeliveryPlatformOauthByOperator;
use App\Services\DeliveryPlatformService;
use Illuminate\Http\Request;

class DeliveryController extends Controller
{
    protected $deliveryPlatformService;

    public function __construct(DeliveryPlatformService $deliveryPlatformService)
    {
        $this->deliveryPlatformService = $deliveryPlatformService;
    }
    // Get mart menu
    // {
    //     "merchantID": "1-CYNGRUNGSBCCC",
    //     "partnerMerchantID": "Partner-ABECU",
    //     "currency": {
    //         "code": "SGD",
    //         "symbol": "S$",
    //         "exponent": 2
    //     },
    //     "sections": [
    //         {
    //             "categories": [
    //                 {
    //                 "id": "category_id",
    //                 "name": "category_name",
    //                 "availableStatus": "AVAILABLE",
    //                 "subCategories": []
    //                 }
    //             ]
    //         }
    //     ]
    // }
    public function getMenu(Request $request)
    {
        $merchantId = $request->merchantID;
        $partnerMerchantID = $request->partnerMerchantID;


    }

    public function getOauth($operatorId, $type)
    {
        try {
            SyncDeliveryPlatformOauthByOperator::dispatch($operatorId, $type);
        } catch(\Exception $e) {
            return $e->getMessage();
        }
    }

    public function sendOauth(Request $request)
    {
        try {
            $operator = Operator::findOrFail($operatorId);
            $response = $this->deliveryPlatformService->getOauth($operator, $type);
            if(!$this->deliveryPlatformService->getDeliveryPlatformOperator()->externalOauthToken()->exists()) {
                throw new \Exception('Please set init Oauth Client ID and Client Secret');
            }
            $this->deliveryPlatformService->getDeliveryPlatformOperator()->externalOauthToken()->update($response);
        } catch(\Exception $e) {
            return $e->getMessage();
        }
    }
}
