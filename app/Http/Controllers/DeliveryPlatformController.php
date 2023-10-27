<?php

namespace App\Http\Controllers;

use App\Http\Resources\DeliveryProductMappingResource;
use App\Http\Resources\DeliveryProductMappingItemResource;
use App\Http\Resources\OperatorResource;
use App\Models\DeliveryPlatformMenuRecord;
use App\Models\DeliveryPlatformOperator;
use App\Models\DeliveryProductMapping;
use App\Models\DeliveryProductMappingItem;
use App\Models\Operator;
use App\Models\Vend;
use App\Jobs\SyncDeliveryPlatformOauthByOperator;
use App\Services\DeliveryPlatformService;
use Illuminate\Http\Request;
use Laravel\Passport\Client;
use Laravel\Passport\Token;
use Inertia\Inertia;

class DeliveryPlatformController extends Controller
{
    protected $deliveryPlatformService;

    public function __construct(DeliveryPlatformService $deliveryPlatformService)
    {
        $this->deliveryPlatformService = $deliveryPlatformService;
    }

    public function getCategories(DeliveryPlatformOperator $deliveryPlatformOperator)
    {
        try {
            $response = $this->deliveryPlatformService->getCategories($deliveryPlatformOperator);
            return $response;
        } catch(\Exception $e) {
            return $e->getMessage();
        }
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
            $clients = Client::with('tokens')
                    ->where('id', $request->client_id)
                    ->where('secret', $request->client_secret)
                    ->get();

            // dd($clients->toArray());

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

    // grab
    // menu
    public function getGrabMenu(Request $request)
    {
        $merchantID = $request->merchantID;
        $partnerMerchantID = $request->partnerMerchantID;
        try {
            $response = $this->deliveryPlatformService->getMenu($merchantID, $partnerMerchantID);
            return $response;
        } catch(\Exception $e) {
            return $e->getMessage();
        }
    }

    public function syncGrabMenuWebhook(Request $request)
    {
        try {
            $prevDeliveryPlatformMenuRecord = DeliveryPlatformMenuRecord::where('ref_id', $request->jobID)->first();

            $deliveryPlatformMenuRecord = DeliveryPlatformMenuRecord::updateOrCreate([
                'ref_id' => $request->jobID,
              ], [
                'delivery_platform_slug' => 'grab',
                'platform_ref_id' => $request->merchantID,
                'request_json' => $prevDeliveryPlatformMenuRecord && $prevDeliveryPlatformMenuRecord->request_json ? array_merge($prevDeliveryPlatformMenuRecord->request_json, $request->all()) : $request->all(),
                'vend_code' => $request->partnerMerchantID,
              ]);

        } catch(\Exception $e) {
            return $e->getMessage();
        }
    }

    // order
    public function createGrabOrder(Request $request)
    {
        $merchantID = $request->merchantID;
        $partnerMerchantID = $request->partnerMerchantID;

        $response = $this->deliveryPlatformService->createOrder($merchantID, $partnerMerchantID, $request->all());
        return $response;
    }

    public function updateGrabOrder(Request $request)
    {
        $merchantID = $request->merchantID;
        $orderID = $request->orderID;

        $response = $this->deliveryPlatformService->updateOrder($merchantID, $orderID, $request->all());
        return $response;
    }
}
