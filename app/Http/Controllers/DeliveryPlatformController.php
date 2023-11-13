<?php

namespace App\Http\Controllers;

use App\Http\Resources\DeliveryProductMappingResource;
use App\Http\Resources\DeliveryProductMappingItemResource;
use App\Http\Resources\OperatorResource;
use App\Models\DeliveryPlatformMenuRecord;
use App\Models\DeliveryPlatformOperator;
use App\Models\DeliveryPlatformOrder;
use App\Models\DeliveryProductMapping;
use App\Models\DeliveryProductMappingItem;
use App\Models\Operator;
use App\Models\Vend;
use App\Jobs\DispenseDeliveryPlatformOrder;
use App\Jobs\SyncDeliveryPlatformOauthByOperator;
use App\Services\DeliveryPlatformService;
use Carbon\Carbon;
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

    // grab will push the menu update status to this endpoint
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

    // vend apk submit complaint for grab order, by driver
    public function submitGrabOrderComplaint(Request $request)
    {
        $code = $request->Vid;
        // $driverPhoneNumber = $request->driver_phone_number;
        // $remarks = $request->remarks;
        $shortOrderID = $request->short_order_id;

        if(!$shortOrderID || !$code) {
            throw new \Exception('Please provide Short Order ID and Vend ID');
        }

        $deliveryPlatformOrder = DeliveryPlatformOrder::query()
        ->where('short_order_id', $shortOrderID)
        ->where('vend_code', $code)
        ->first();

        if(!$deliveryPlatformOrder) {
            abort(response([
                'error_code' => 404,
                'error_message' => 'Order not found',
            ], 404));
        }

        if($deliveryPlatformOrder) {
            $deliveryPlatformOrder->deliveryPlatformOrderComplaint()->create([
                // 'driver_phone_number' => $driverPhoneNumber,
                'original_json' => $request->all(),
                // 'remarks' => $remarks,
            ]);
        }
        return true;
    }

    // search grab order in vm apk
    public function searchGrabOrder(Request $request)
    {
        $code = $request->Vid;
        $driverPhoneNumber = $request->driver_phone_number;
        $shortOrderID = $request->short_order_id;

        if(!$shortOrderID || !$code) {
            abort(response([
                'error_code' => 400,
                'error_message' => 'Parameters missing',
            ], 400));
        }

        $expiryHours = Carbon::now()->addHours(DeliveryPlatformOrder::DEFAULT_VALID_COLLECTION_HOURS);

        $deliveryPlatformOrder = DeliveryPlatformOrder::query()
            ->where('short_order_id', $shortOrderID)
            ->where('vend_code', $code)
            // ->where('is_verified', false)
            // ->where('order_created_at', '<=', $expiryHours)
            ->first();

        if($deliveryPlatformOrder) {
            $deliveryPlatformOrder->update([
                'driver_phone_number' => $driverPhoneNumber,
                'driver_request_json' => $request->all(),
                'is_verified' => true,
                'status' => DeliveryPlatformOrder::STATUS_COLLECTED,
            ]);
            DispenseDeliveryPlatformOrder::dispatch($deliveryPlatformOrder);
            // $this->deliveryPlatformService->dispenseOrder($deliveryPlatformOrder);
            return true;
        } else {
            abort(response([
                'error_code' => 404,
                'error_message' => 'Order not found',
            ], 404));
        }
    }

    public function updateGrabOrder(Request $request)
    {
        $merchantID = $request->merchantID;
        $orderID = $request->orderID;

        $response = $this->deliveryPlatformService->updateOrder($merchantID, $orderID, $request->all());
        return $response;
    }
}
