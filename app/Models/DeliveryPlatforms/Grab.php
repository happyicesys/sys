<?php

namespace App\Models\DeliveryPlatforms;

use App\Models\DeliveryPlatform;
use App\Models\DeliveryPlatformOperator;
use App\Interfaces\DeliveryPlatformInterface;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Http;

class Grab extends DeliveryPlatform implements DeliveryPlatformInterface
{
    use HasFactory;

    public static $main_endpoint = 'https://api.grab.com';
    public static $partner_endpoint = 'https://partner-api.grab.com/grabmart';
    public static $partner_sandbox_endpoint = 'https://partner-api.grab.com/grabmart-sandbox';
    // public static $partner_sandbox_endpoint = 'https://partner-api.stg-myteksi.com/grabmart-sandbox';

    public static $sandbox_scope = 'sandbox.mart.partner_api';
    public static $production_scope = 'mart.partner_api';

    public static $product_active = 'AVAILABLE';
    public static $product_inactive = 'UNAVAILABLE';
    public static $product_inactive_today = 'UNAVAILABLETODAY';

    private $deliveryPlatformOperator;

    public function __construct(DeliveryPlatformOperator $deliveryPlatformOperator)
    {
        $this->deliveryPlatformOperator = $deliveryPlatformOperator;
    }

    // Get Grab OAuth token
    public function getGrabOAuthToken()
    {
        // dd($this->deliveryPlatformOperator->externalOauthToken);
        if(!$this->deliveryPlatformOperator->externalOauthToken()->exists()) {
            throw new \Exception('Oauth Client ID and Secret Not Found: ');
        }

        $response = Http::withHeaders($this->getHeaders())
        ->post($this->getMainEndpoint() . '/grabid/v1/oauth2/token', [
            'client_id' => $this->deliveryPlatformOperator->externalOauthToken->client_id,
            'client_secret' => $this->deliveryPlatformOperator->externalOauthToken->client_secret,
            'grant_type' => $this->deliveryPlatformOperator->externalOauthToken->granted_type,
            'scope' => $this->getScope(),
        ]);

        return $this->getResponse($response, 'getGrabOAuthToken');

        throw new \Exception('Get OAuthToken Failed: ' . $response->body());
    }

    // interface compulsory for delivery platform
    public function getOauthToken()
    {
        return $this->getGrabOAuthToken();
    }

    // List Mart Categories
    public function listMartCategories()
    {
        $this->verifyOauthAccessToken();

        // dd($this->deliveryPlatformOperator->operator->country->code);
        $response = Http::withHeaders($this->getHeaders([
            'Authorization' => 'Bearer ' . $this->deliveryPlatformOperator->externalOauthToken->access_token,
        ]))
        ->get($this->getPartnerEndpoint() . '/partner/v1/menu/categories', [
            'countryCode' => $this->deliveryPlatformOperator->operator->country->code,
        ]);

        return $this->getResponse($response, 'listMartCategories');

        throw new \Exception('Get Mart Categories Failed: ' . $response->body());
    }

    // Notify Grab of updated menu
    public function notifyUpdatedMenu()
    {
        $this->verifyOauthAccessToken();

        $response = Http::withHeaders($this->getHeaders([
            'Authorization' => 'Bearer ' . $this->deliveryPlatformOperator->externalOauthToken->access_token,
        ]))
        ->post($this->getPartnerEndpoint() . '/partner/v1/merchant/menu/notification', [
            'merchantID' => $this->merchantId,
        ]);

        return $this->getResponse($response, 'notifyUpdatedMenu');

        throw new \Exception('Notify Updated Menu Failed: ' . $response->body());
    }

    // Update menu record
    public function updateMenuRecord($singleProductParam = [])
    {
        $this->verifyOauthAccessToken();

        if(!$singleProductParam) {
            throw new \Exception('No Single Product Param for Update Menu Record ');
        }

        $response = Http::withHeaders($this->getHeaders([
            'Authorization' => 'Bearer ' . $this->deliveryPlatformOperator->externalOauthToken->access_token,
        ]))
        ->put($this->getPartnerEndpoint() . '/partner/v1/menu', [
            'merchantID' => $this->merchantId,
            'field' => 'ITEM',
            'id' => $singleProductParam['code'],
            'name' => $singleProductParam['name'],
            'description' => $singleProductParam['desc'],
            'price' => $singleProductParam['price'],
            'availableStatus' => $singleProductParam['is_active'] ? self::$product_active : self::$product_inactive,
            'maxStock' => $singleProductParam['available_qty'],
        ]);

        return $this->getResponse($response, 'updateMenuRecord');

        throw new \Exception('Update Single Menu Failed: ' . $response->body());
    }


    // Batch Update Menu
    public function batchUpdateMenu()
    {
        $this->verifyOauthAccessToken();

        $response = Http::withHeaders($this->getHeaders([
            'Authorization' => 'Bearer ' . $this->deliveryPlatformOperator->externalOauthToken->access_token,
        ]))
        ->put($this->getPartnerEndpoint() . '/partner/v1/batch/menu', [
            'merchantID' => $this->merchantId,
            'field' => 'ITEM',
            'menuEntities' => [
                [
                    'id' => 'item_id',
                    'name' => 'item_name',
                    'description' => 'item_description',
                    'price' => [
                        'amount' => 100,
                        'currency' => 'SGD',
                    ],
                    'availableStatus' => 'AVAILABLE',
                    'categories' => [
                        [
                            'id' => 'category_id',
                            'name' => 'category_name',
                            'availableStatus' => 'AVAILABLE',
                        ],
                    ],
                    'images' => [
                        [
                            'url' => 'https://www.google.com/images/branding/googlelogo/1x/googlelogo_color_272x92dp.png',
                            'type' => 'PRIMARY',
                        ],
                    ],
                ],
            ],
        ]);

        return $this->getResponse($response, 'batchUpdateMenu');

        throw new \Exception('Batch Update Menu Failed: ' . $response->body());
    }

    // Manually accept/reject orders
    public function manuallyAcceptRejectOrder($orderId, $isAccept = true)
    {
        $this->verifyOauthAccessToken();

        $response = Http::withHeaders($this->getHeaders([
            'Authorization' => 'Bearer ' . $this->deliveryPlatformOperator->externalOauthToken->access_token,
        ]))
        ->post($this->getPartnerEndpoint() . '/partner/v1/order/prepare', [
            'orderID' => $orderId,
            'toState' => $isAccept ? 'ACCEPTED' : 'REJECTED',
        ]);

        return $this->getResponse($response, 'manuallyAcceptRejectOrder');

        throw new \Exception('Manualy Accept Reject Order Failed: ' . $response->body());
    }

    // List orders
    public function listOrders($date, $pageNumber = 1, $filters)
    {
        $this->verifyOauthAccessToken();

        $response = Http::withHeaders($this->getHeaders([
            'Authorization' => 'Bearer ' . $this->deliveryPlatformOperator->externalOauthToken->access_token,
            'merchantID' => $this->merchantId,
            'date' => $date ? $date : Carbon::today()->toDateString(),
            'page' => $pageNumber,
        ]))
        ->post($this->getPartnerEndpoint() . '/partner/v1/order/prepare', $filters);

        return $this->getResponse($response, 'listOrders');

        throw new \Exception('List Orders Failed: ' . $response->body());
    }

    // Edit order
    public function editOrder($orderId, $items = [])
    {
        $this->verifyOauthAccessToken();

        if(!$items) {
            throw new \Exception('Items Arr Not Found for Edit Order ');
        }

        $response = Http::withHeaders($this->getHeaders([
            'Authorization' => 'Bearer ' . $this->deliveryPlatformOperator->externalOauthToken->access_token,
        ]))
        ->put($this->getPartnerEndpoint() . '/partner/v1/order/prepare/'. $orderId, [
            'orderID' => $orderId,
            'items' => $items,
            'onlyRecalculate' => config('app.env') === 'local' ? true : false,
        ]);

        return $this->getResponse($response, 'editOrder');

        throw new \Exception('Edit Order Failed: ' . $response->body());
    }

    // Mark order as ready
    public function markOrderReady($orderId)
    {
        $this->verifyOauthAccessToken();

        $response = Http::withHeaders($this->getHeaders([
            'Authorization' => 'Bearer ' . $this->deliveryPlatformOperator->externalOauthToken->access_token,
        ]))
        ->post($this->getPartnerEndpoint() . '/partner/v1/orders/mark', [
            'orderID' => $orderId,
            'markStatus' => 1
        ]);

        return $this->getResponse($response, 'markOrderReady');

        throw new \Exception('Mark Order Ready Failed: ' . $response->body());
    }

    // Update delivery state
    public function updateDeliveryState($orderId, $fromState, $toState)
    {
        $this->verifyOauthAccessToken();

        if(!$state) {
            throw new \Exception('State Not Found for Update Delivery State ');
        }

        $response = Http::withHeaders($this->getHeaders([
            'Authorization' => 'Bearer ' . $this->deliveryPlatformOperator->externalOauthToken->access_token,
        ]))
        ->post($this->getPartnerEndpoint() . '/partner/v1/order/delivery', [
            'orderID' => $orderId,
            'fromState' => $fromState,
            'toState' => $toState,
        ]);

        return $this->getResponse($response, 'updateDeliveryState');

        throw new \Exception('Update Delivery State Failed: ' . $response->body());
    }

    // Update order ready time
    public function updateOrderReadyTime($orderId, $newOrderReadyTime)
    {
        $this->verifyOauthAccessToken();

        $response = Http::withHeaders($this->getHeaders([
            'Authorization' => 'Bearer ' . $this->deliveryPlatformOperator->externalOauthToken->access_token,
        ]))
        ->put($this->getPartnerEndpoint() . '/partner/v1/order/readytime', [
            'orderID' => $orderId,
            'newOrderReadyTime' => $newOrderReadyTime ? $newOrderReadyTime : Carbon::now()->toDateTimeString(),
        ]);

        return $this->getResponse($response, 'updateOrderReadyTime');

        throw new \Exception('Update Order Ready Time Failed: ' . $response->body());
    }

    // Check order cancelable
    public function checkOrderCancelable($orderId)
    {
        $this->verifyOauthAccessToken();

        $response = Http::withHeaders($this->getHeaders([
            'Authorization' => 'Bearer ' . $this->deliveryPlatformOperator->externalOauthToken->access_token,
            'orderID' => $orderId,
            'merchantID' => $this->merchantId
        ]))
        ->get($this->getPartnerEndpoint() . '/partner/v1/order/cancelable');

        return $this->getResponse($response, 'checkOrderCancelable');

        throw new \Exception('Check Order Cancelable Failed: ' . $response->body());
    }

    // Cancel an order
    public function cancelOrder($orderId, $cancelCode)
    {
        $this->verifyOauthAccessToken();

        $response = Http::withHeaders($this->getHeaders([
            'Authorization' => 'Bearer ' . $this->deliveryPlatformOperator->externalOauthToken->access_token,
        ]))
        ->put($this->getPartnerEndpoint() . '/partner/v1/order/cancel', [
            'orderID' => $orderId,
            'merchantID' => $this->merchantId,
            'cancelCode' => $cancelCode,
        ]);

        return $this->getResponse($response, 'cancelOrder');

        throw new \Exception('Cancel Order Failed: ' . $response->body());
    }

    // Pause store
    public function pauseOrder($isPause = true, $duration = '24h')
    {
        $this->verifyOauthAccessToken();

        $response = Http::withHeaders($this->getHeaders([
            'Authorization' => 'Bearer ' . $this->deliveryPlatformOperator->externalOauthToken->access_token,
        ]))
        ->put($this->getPartnerEndpoint() . '/partner/v1/merchant/pause', [
            'merchantID' => $this->merchantId,
            'isPause' => $isPause,
            'duration' => $duration,
        ]);

        return $this->getResponse($response, 'pauseOrder');

        throw new \Exception('Pause Order Failed: ' . $response->body());
    }

    private function getHeaders($params = [])
    {

        $defaultHeaders = array(
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        );

        $headers = array_merge($defaultHeaders, $params);

        return $headers;
    }

    private function getMainEndpoint()
    {
        return self::$main_endpoint;
    }

    private function getPartnerEndpoint()
    {
        $endpoint = self::$partner_endpoint;

        if(config('app.env') === 'local') {
            $endpoint = self::$partner_sandbox_endpoint;
        }

        return $endpoint;
    }

    private function getResponse($response, $method)
    {
        $message = '';

        switch($response->status()) {
            case 200:
                $message = 'OK';
                break;
            case 204:
                $message = 'No Content';
                break;
            case 400:
                $message = 'Bad Request';
                break;
            case 401:
                $message = 'Unauthorized';
                break;
            case 403:
                $message = 'Forbidden';
                break;
            case 404:
                $message = 'Not Found';
                break;
            case 409:
                $message = 'Conflict';
                break;
            case 500:
                $message = 'Internal Server Error';
                break;
            case 503:
                $message = 'Service Unavailable';
                break;
        }

        $finalResponse =  [
            'success' => $response->successful(),
            'code' => $response->status(),
            'message' => $method.' '.$message,
            'data' => $response->json(),
        ];

        return $finalResponse;
    }

    private function getScope()
    {
        $scope = self::$production_scope;

        if(config('app.env') === 'local') {
            $scope = self::$production_scope;
        }

        return $scope;
    }

    private function verifyOauthAccessToken()
    {
        if(!$this->deliveryPlatformOperator->externalOauthToken()->exists() or !$this->deliveryPlatformOperator->externalOauthToken->access_token) {
            throw new \Exception('Oauth Client ID, Secret, Access Key Not Available');
        }

        return true;
    }
}
