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
use App\Models\VendData;
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

    public function getOauth($deliveryPlatformOperatorId)
    {
        try {
            $deliveryPlatformOperator = DeliveryPlatformOperator::findOrFail($deliveryPlatformOperatorId);
            SyncDeliveryPlatformOauthByOperator::dispatch($deliveryPlatformOperator);
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
        DeliveryPlatformMenuRecord::updateOrCreate([
            'ref_id' => $request->jobID,
        ], [
            'request_json' => $request->all(),
            'delivery_platform_slug' => 'grab-menu',
            'platform_ref_id' => $request->merchantID,
            'request_json' => $request->all(),
            'vend_code' => $request->partnerMerchantID,
        ]);
    }

    // order
    public function createGrabOrder(Request $request)
    {
        $merchantID = $request->merchantID;
        $partnerMerchantID = $request->partnerMerchantID;

        $deliveryPlatformOrder = $this->deliveryPlatformService->createOrder($merchantID, $partnerMerchantID, $request->all());

        // auto mark order as ready after success creation
        if($deliveryPlatformOrder) {
            $this->deliveryPlatformService->markOrderReady($deliveryPlatformOrder);
        }

        return true;
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
    public function searchGrabOrder(Request $request, $dispenseSearch = true)
    {
        // delivery/order/search/1

        $code = $request->Vid;
        $driverPhoneNumber = $request->driver_phone_number;
        $shortOrderID = $request->short_order_id;

        VendData::create([
            'value' => $request->all(),
            'connection' => 'GRAB-DRIVER-REQUEST',
            'vend_code' => $code,
            'processed' => '[]',
            'type' => $dispenseSearch,
            'is_keep' => true,
        ]);

        if(!$shortOrderID || !$code) {
            abort(response([
                'error_code' => 400,
                'error_message' => 'Parameters missing',
            ], 400));
        }

        $shortOrderIDNum = preg_replace("/[^0-9]/", "", $shortOrderID);

        $deliveryPlatformOrder = DeliveryPlatformOrder::query()
            // ->where(function($query) use ($shortOrderID) {
            //     $query->where('short_order_id', $shortOrderID)
            //         ->orWhere('short_order_id', $shortOrderID.'T');
            // })
            ->whereRaw('REGEXP_REPLACE(short_order_id, "[^0-9]", "") = ?', $shortOrderIDNum)
            ->where('vend_code', $code)
            ->orderby('created_at', 'desc')
            ->first();

        if(!$deliveryPlatformOrder) {
            abort(response([
                'error_code' => 404,
                'error_message' => 'Order not found',
            ], 404));
        }

        $deliveryPlatformOrder->update([
            'error_json' => $request->all(),
        ]);

        if(($deliveryPlatformOrder->deliveryPlatformOperator->type === 'production') and ((Carbon::parse($deliveryPlatformOrder->created_at)->diffInHours(Carbon::now()) >= DeliveryPlatformOrder::DEFAULT_VALID_COLLECTION_HOURS) or ($deliveryPlatformOrder->status > DeliveryPlatformOrder::STATUS_DELIVERED))) {
            abort(response([
                'error_code' => 405,
                'error_message' => 'Order expired',
            ], 405));
        }

        if(!$deliveryPlatformOrder->is_verified or $deliveryPlatformOrder->deliveryPlatformOperator->type === 'sandbox') {
            if($dispenseSearch) {
                $deliveryPlatformOrder->update([
                    'driver_phone_number' => $driverPhoneNumber,
                    'driver_request_json' => $request->all(),
                    'status' => DeliveryPlatformOrder::STATUS_REQUESTED,
                    'status_json' => array_merge_recursive($deliveryPlatformOrder->status_json, [
                        'status' => DeliveryPlatformOrder::STATUS_MAPPING[DeliveryPlatformOrder::STATUS_REQUESTED],
                        'datetime' => Carbon::now()->toDateTimeString(),
                    ])
                ]);
                DispenseDeliveryPlatformOrder::dispatch($deliveryPlatformOrder);
                return true;
            }else {
                $deliveryPlatformOrder->update([
                    'driver_phone_number' => $driverPhoneNumber,
                    'driver_request_json' => $request->all(),
                ]);
                return $deliveryPlatformOrder->response_history_json;
            }

        } else {
            abort(response([
                'error_code' => 405,
                'error_message' => 'Order has been redeemed',
            ], 405));
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
