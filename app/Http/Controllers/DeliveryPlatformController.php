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
use App\Jobs\ProcessDeliveryPlatformOrder;
use App\Jobs\ProcessGrabMenuWebhook;
use App\Services\DeliveryPlatformService;
use Carbon\Carbon;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DeliveryPlatformController extends Controller
{
    protected $deliveryPlatformService;

    public function __construct(DeliveryPlatformService $deliveryPlatformService)
    {
        $this->deliveryPlatformService = $deliveryPlatformService;
    }

    // grab
    // menu
    public function getGrabMenu(Request $request)
    {
        $merchantID = $request->merchantID;
        $partnerMerchantID = $request->partnerMerchantID;
        try {
            return $this->deliveryPlatformService->getMenu($merchantID, $partnerMerchantID);
        } catch (HttpResponseException $e) {
            throw $e;
        } catch (\Throwable $e) {
            // Never an error text as a 200: Grab reads it as a broken menu
            // ("unknown content-type header text/html").
            report($e);

            return response()->json([
                'error_code' => 500,
                'error_message' => 'Menu unavailable.',
            ], 500);
        }
    }

    // grab will push the menu update status to this endpoint
    public function syncGrabMenuWebhook(Request $request)
    {
        ProcessGrabMenuWebhook::dispatch($request->all());
    }

    // order
    public function createGrabOrder(Request $request)
    {
        $input = $request->all();

        if ($request->has('order')) {
            $input = $request->order;
        }

        $merchantID = $input['merchantID'];
        $partnerMerchantID = $input['partnerMerchantID'];

        $deliveryPlatformOrder = $this->deliveryPlatformService->createOrder($merchantID, $partnerMerchantID, $input);

        // auto mark order as ready after success creation - from the queue, once
        if ($deliveryPlatformOrder && $deliveryPlatformOrder->wasRecentlyCreated) {
            ProcessDeliveryPlatformOrder::dispatch($deliveryPlatformOrder)->onQueue('high');
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

        if (!$shortOrderID || !$code) {
            throw new \Exception('Please provide Short Order ID and Machine ID');
        }

        $deliveryPlatformOrder = DeliveryPlatformOrder::query()
            ->where('short_order_id', $shortOrderID)
            ->where('vend_code', $code)
            ->first();

        if (!$deliveryPlatformOrder) {
            abort(response([
                'error_code' => 404,
                'error_message' => 'Order not found',
            ], 404));
        }

        if ($deliveryPlatformOrder) {
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

        if (!$shortOrderID || !$code) {
            abort(response([
                'error_code' => 400,
                'error_message' => 'Parameters missing',
            ], 400));
        }

        $shortOrderIDNum = preg_replace("/[^0-9]/", "", $shortOrderID);

        $deliveryPlatformOrder = DeliveryPlatformOrder::query()
            ->whereRaw('REGEXP_REPLACE(short_order_id, "[^0-9]", "") = ?', $shortOrderIDNum)
            ->where('vend_code', $code)
            ->orderby('created_at', 'desc')
            ->first();
        // dd($deliveryPlatformOrder->toArray());

        if (!$deliveryPlatformOrder) {
            abort(response([
                'error_code' => 404,
                'error_message' => 'Order not found',
            ], 404));
        }

        if (($deliveryPlatformOrder->deliveryPlatformOperator->type === 'production') and (Carbon::parse($deliveryPlatformOrder->created_at)->diffInHours(Carbon::now()) > DeliveryPlatformOrder::ORDER_EXPIRED_HOURS)) {
            abort(response([
                'error_code' => 404,
                'error_message' => 'Order Expired',
            ], 404));
        }

        // if(!$deliveryPlatformOrder->is_verified or $deliveryPlatformOrder->deliveryPlatformOperator->type === 'sandbox') {
        if (!$deliveryPlatformOrder->is_verified) {
            if ($dispenseSearch) {
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
                // return response($deliveryPlatformOrder->response_history_json, 200);
                return true;
            } else {
                $deliveryPlatformOrder->update([
                    'driver_phone_number' => $driverPhoneNumber,
                    'driver_request_json' => $request->all(),
                ]);
                // return response($deliveryPlatformOrder->response_history_json, 200);
                return true;
            }

        } else {
            if ($deliveryPlatformOrder->vendTransaction) {
                abort(response($deliveryPlatformOrder->vendTransaction->vend_transaction_json, 405));
            }
            // else {
            //     abort(response([], 405));
            // }
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
