<?php

namespace App\Models\DeliveryPlatforms;

use App\Models\DeliveryPlatform;
use App\Models\DeliveryPlatformOperator;
use App\Models\DeliveryProductMappingVend;
use App\Interfaces\DeliveryPlatformInterface;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Http;

class Grab extends DeliveryPlatform implements DeliveryPlatformInterface
{
    use HasFactory;

    const CAMPAIGN_TYPE_DOLLAR = 'net';
    const CAMPAIGN_TYPE_PERCENT = 'percentage';
    const CAMPAIGN_TYPE_BUNDLE_SAME_FIXED = 'bundleSameFixPrice';
    const CAMPAIGN_TYPE_BUNDLE_DIFF_FIXED = 'bundleDiffFixPrice';
    const CAMPAIGN_TYPE_BUNDLE_SAME_DOLLAR = 'bundleSameNet';
    const CAMPAIGN_TYPE_BUNDLE_DIFF_DOLLAR = 'bundleDiffNet';
    const CAMPAIGN_TYPE_BUNDLE_SAME_PERCENT = 'bundleSamePercentage';
    const CAMPAIGN_TYPE_BUNDLE_DIFF_PERCENT = 'bundleDiffPercentage';
    const CAMPAIGN_TYPE_DELIVERY = 'delivery';
    const CAMPAIGN_TYPE_FREEITEM = 'freeItem';

    const CAMPAIGN_SCOPE_ITEM = 'items';
    const CAMPAIGN_SCOPE_CATEGORY = 'category';
    const CAMPAIGN_SCOPE_ORDER = 'order';

    const START_TIME = '00:00';
    const END_TIME = '23:59';
    const MAX_END_DATETIME = '9999-12-31 23:59:59';

    const PAYMENT_METHOD_GRABMART = 209;

    const PAYMENT_TYPE_CASH = 'CASH';
    const PAYMENT_TYPE_CASHLESS = 'CASHLESS';

    const STATE_PENDING = 'PENDING';
    const STATE_ACCEPTED = 'ACCEPTED';
    const STATE_DRIVER_ALLOCATED = 'DRIVER_ALLOCATED';
    const STATE_DRIVER_ARRIVED = 'DRIVER_ARRIVED';
    const STATE_COLLECTED = 'COLLECTED';
    const STATE_DELIVERED = 'DELIVERED';
    const STATE_CANCELLED = 'CANCELLED';
    const STATE_FAILED = 'FAILED';

    const STATUS_AVAILABLE = 'AVAILABLE';
    const STATUS_UNAVAILABLE = 'UNAVAILABLE';
    const STATUS_UNAVAILABLE_TODAY = 'UNAVAILABLETODAY';
    const STATUS_HIDE = 'HIDE';

    const STATUS_MAPPING = [
        1 => 'AVAILABLE',
        0 => 'UNAVAILABLE',
    ];

    public static $sandbox_endpoint = 'https://integration-middleware.stg.restaurant-partners.com';
    public static $endpoint = 'https://integration-middleware.as.restaurant-partners.com';

    protected $deliveryPlatformOperator;

    public function __construct(DeliveryPlatformOperator $deliveryPlatformOperator)
    {
        $this->deliveryPlatformOperator = $deliveryPlatformOperator;
    }

    // retrieve and fill in oauth params from delivery platform
    public function getIncomingOauthParams($params = [])
    {
        return [
            'access_token' => $params['access_token'],
            'expired_in' => $params['expires_in'],
            'token_type' => $params['token_type'],
        ];
    }

    // retrieve and fill in oauth params from own web service to delivery platform
    private function getOutgoingOauthParams($params = [])
    {
        return [
            'access_token' => $params['access_token'],
            'expires_in' => $params['expired_at'],
            'token_type' => $params['type'],
        ];
    }

    // Get Grab OAuth token
    public function getOAuthToken()
    {
        if(!$this->deliveryPlatformOperator->externalOauthToken()->exists()) {
            throw new \Exception('Oauth Client ID and Secret Not Found: ');
        }

        $response = Http::withHeaders($this->getHeaders())
        ->post($this->getMainEndpoint() . '/v2/login', [
            'grant_type' => $this->deliveryPlatformOperator->externalOauthToken->granted_type,
            'password' => $this->deliveryPlatformOperator->externalOauthToken->client_id,
            'username' => $this->deliveryPlatformOperator->externalOauthToken->client_secret,
        ]);

        return $this->getResponse($response, 'getOAuthToken');

        throw new \Exception('Get OAuthToken Failed: ' . $response->body());
      }

    // Update menu record
    public function submitCatalog($merchantIdParam = [])
    {
        $this->verifyOauthAccessToken();

        if(!$singleProductParam) {
            throw new \Exception('No Single Product Param for Update Menu Record ');
        }

        $response = Http::withHeaders($this->getHeaders())
        ->put($this->getPartnerEndpoint() . '/v2/chains/' . $merchantIdParam);

        return $this->getResponse($response, 'updateMenuRecord');

        throw new \Exception('Update Single Menu Failed: ' . $response->body());
    }

    // Manually accept/reject orders
    public function manuallyAcceptRejectOrder($orderId, $isAccept = true)
    {
        $this->verifyOauthAccessToken();

        $response = Http::withHeaders($this->getHeaders())
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

        $response = Http::withHeaders($this->getHeaders())
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

        $response = Http::withHeaders($this->getHeaders())
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

        $response = Http::withHeaders($this->getHeaders())
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

        $response = Http::withHeaders($this->getHeaders())
        ->put($this->getPartnerEndpoint() . '/partner/v1/order/readytime', [
            'orderID' => $orderId,
            'newOrderReadyTime' => $newOrderReadyTime ? $newOrderReadyTime : Carbon::now()->toDateTimeString(),
        ]);

        return $this->getResponse($response, 'updateOrderReadyTime');

        throw new \Exception('Update Order Ready Time Failed: ' . $response->body());
    }

    // Check order cancelable
    public function checkOrderCancelable($orderId, $merchantID)
    {
        $this->verifyOauthAccessToken();

        $response = Http::withHeaders($this->getHeaders())
        ->get($this->getPartnerEndpoint() . '/partner/v1/order/cancelable', [
            'orderID' => $orderId,
            'merchantID' => $merchantID,
        ]);

        return $this->getResponse($response, 'checkOrderCancelable');

        throw new \Exception('Check Order Cancelable Failed: ' . $response->body());
    }

    // Cancel an order
    public function cancelOrder($orderID, $merchantID, $cancelCode)
    {
        $this->verifyOauthAccessToken();

        $response = Http::withHeaders($this->getHeaders())
        ->put($this->getPartnerEndpoint() . '/partner/v1/order/cancel', [
            'orderID' => $orderID,
            'merchantID' => $merchantID,
            'cancelCode' => $cancelCode,
        ]);

        return $this->getResponse($response, 'cancelOrder');

        throw new \Exception('Cancel Order Failed: ' . $response->body());
    }

    // Pause store
    public function pauseStore($merchantID, $isPause = true, $duration = '24h')
    {
        $this->verifyOauthAccessToken();

        $response = Http::withHeaders($this->getHeaders())
        ->put($this->getPartnerEndpoint() . '/partner/v1/merchant/pause', [
            'merchantID' => $merchantID,
            'isPause' => $isPause,
            'duration' => $duration,
        ]);

        return $this->getResponse($response, 'pauseOrder');

        throw new \Exception('Pause Order Failed: ' . $response->body());
    }

    // create campaign
    public function createCampaign($campaignParams = [])
    {
        $this->verifyOauthAccessToken();

        if(!$campaignParams) {
            throw new \Exception('Campaign Param Not Found for Create Campaign ');
        }

        $response = Http::withHeaders($this->getHeaders())
        ->post($this->getPartnerEndpoint() . '/partner/v1/campaigns', $campaignParams);

        return $this->getResponse($response, 'createCampaign');

        throw new \Exception('Create Campaign Failed: ' . $response->body());
    }

    // create campaign
    public function deleteCampaign($platformRefID)
    {
        $this->verifyOauthAccessToken();

        if(!$platformRefID) {
            throw new \Exception('Campaign ID Not Found for Delete Campaign ');
        }

        $response = Http::withHeaders($this->getHeaders([
            'campaignId' => $platformRefID,
        ]))
        ->delete($this->getPartnerEndpoint() . '/partner/v1/campaigns/'. $platformRefID);

        return $this->getResponse($response, 'deleteCampaign');

        throw new \Exception('Delete Campaign Failed: ' . $response->body());
    }

    // update campaign
    public function updateCampaign($platformRefID, $campaignParams = [])
    {
        $this->verifyOauthAccessToken();

        if(!$platformRefID) {
            throw new \Exception('Campaign ID Not Found for Update Campaign ');
        }

        $response = Http::withHeaders($this->getHeaders([
            'campaignId' => $platformRefID,
        ]))
        ->put($this->getPartnerEndpoint() . '/partner/v1/campaigns/'. $platformRefID, $campaignParams);

        return $this->getResponse($response, 'updateCampaign');

        throw new \Exception('Update Campaign Failed: ' . $response->body());
    }

    // init default headers for grab
    private function getHeaders($params = [])
    {
        $defaultHeaders = array(
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . $this->deliveryPlatformOperator->externalOauthToken->access_token,
        );

        $headers = array_merge($defaultHeaders, $params);

        return $headers;
    }

    private function getMainEndpoint()
    {
        return self::$endpoint;
    }

    private function getPartnerEndpoint()
    {
        if($this->deliveryPlatformOperator->externalOauthToken->scopes === 'mart.partner_api') {
            if($this->deliveryPlatformOperator->type === 'sandbox') {
                $endpoint = self::$mart_partner_sandbox_endpoint;
            }else {
                $endpoint = self::$mart_partner_endpoint;
            }
        }else if($this->deliveryPlatformOperator->externalOauthToken->scopes === 'food.partner_api') {
            if($this->deliveryPlatformOperator->type === 'sandbox') {
                $endpoint = self::$food_partner_sandbox_endpoint;
            }else {
                $endpoint = self::$food_partner_endpoint;
            }
        }

        return $endpoint;
    }

    // response params
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

    private function verifyOauthAccessToken()
    {
        if(!$this->deliveryPlatformOperator->externalOauthToken()->exists() or !$this->deliveryPlatformOperator->externalOauthToken->access_token) {
            throw new \Exception('Oauth Client ID, Secret, Access Key Not Available');
        }

        return true;
    }
}
