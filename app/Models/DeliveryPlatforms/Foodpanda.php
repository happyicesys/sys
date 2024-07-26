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

class Foodpanda extends DeliveryPlatform implements DeliveryPlatformInterface
{
    use HasFactory;

    const START_TIME = '00:00';
    const END_TIME = '23:59';
    const MAX_END_DATETIME = '9999-12-31 23:59:59';

    const ORDER_ACCEPTED = 'order_accepted';
    const ORDER_REJECTED = 'order_rejected';

    const ORDER_REJECTED_REASONS = [
        'ADDRESS_INCOMPLETE_MISSTATED' => 'Address Incomplete',
        'BAD_WEATHER' => 'Bad Weather',
        'BLACKLISTED' => 'Blacklisted',
        'CARD_READER_NOT_AVAILABLE' => 'Card Reader Not Available',
        'CLOSED' => 'Closed',
        'CONTENT_WRONG_MISLEADING' => 'Content Misleading',
        'FOOD_QUALITY_SPILLAGE' => 'Food Quality Spillage',
        'FRAUD_PRANK' => 'Fraud Prank',
        'ITEM_UNAVAILABLE' => 'Item Unavailable',
        'LATE_DELIVERY' => 'Late Delivery',
        'MENU_ACCOUNT_SETTINGS' => 'Menu Account Settings',
        'MOV_NOT_REACHED' => 'MOV Not Reached',
        'NO_COURIER' => 'No Courier',
        'NO_PICKER' => 'No Picker',
        'NO_RESPONSE' => 'No Response',
        'OUTSIDE_DELIVERY_AREA' => 'Outside Delivery Area',
        'TECHNICAL_PROBLEM' => 'Technical Problem',
        'TEST_ORDER' => 'Test Order',
        'TOO_BUSY' => 'Too Busy',
        'UNABLE_TO_FIND' => 'Unable To Find',
        'UNABLE_TO_PAY' => 'Unable To Pay',
        'UNPROFESSIONAL_BEHAVIOUR' => 'Unprofessional Behaviour',
        'WILL_NOT_WORK_WITH' => 'Will Not Work With',
        'WRONG_ORDER_ITEMS_DELIVERED' => 'Wrong Order Items Delivered',
    ];

    const PAYMENT_METHOD_FOODPANDA = 211;

    const STATE_PENDING = 'PENDING';
    const STATE_ACCEPTED = 'ACCEPTED';
    const STATE_DRIVER_ALLOCATED = 'DRIVER_ALLOCATED';
    const STATE_DRIVER_ARRIVED = 'DRIVER_ARRIVED';
    const STATE_COLLECTED = 'COLLECTED';
    const STATE_DELIVERED = 'DELIVERED';
    const STATE_CANCELLED = 'CANCELLED';
    const STATE_FAILED = 'FAILED';

    const STATUS_AVAILABLE = 'OPEN';
    const STATUS_UNAVAILABLE = 'CLOSED';
    const STATUS_UNAVAILABLE_TODAY = 'CLOSED_TODAY';

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

    // Update Order Status
    public function updateOrderStatus($orderID, $isAccepted = true, $message = null)
    {
        $this->verifyOauthAccessToken();

        $dataArr = [];
        if($isAccepted) {
            $dataArr = [
                'acceptanceTime' => Carbon::now()->toIso8601String(),
                'remoteOrderId' => $orderID,
                'status' => self::ORDER_ACCEPTED,
            ];
        }else {
            $dataArr = [
                'status' => self::ORDER_REJECTED,
                'message' => $message,
                'reason' => self::ORDER_REJECTED_REASONS[ITEM_UNAVAILABLE],
            ];
        }

        $response = Http::withHeaders($this->getHeaders())->put($this->getMainEndpoint() . '/v2/order/status/' . $orderID, $dataArr);

        return $this->getResponse($response, 'updateOrderStatus');

        throw new \Exception('Update Order Status Failed: ' . $response->body());
    }

    // Mark an order as prepared
    public function markOrderPrepared($orderID)
    {
        $this->verifyOauthAccessToken();

        $response = Http::withHeaders($this->getHeaders())
        ->post($this->getMainEndpoint() . '/v2/orders/' . $orderID . '/preparation-completed');

        return $this->getResponse($response, 'markOrderPrepared');

        throw new \Exception('Mark Order Prepared Failed: ' . $response->body());
    }

    // Get availability status
    public function getAvailabilityStatus($chainCode, $posVendorID)
    {
        $this->verifyOauthAccessToken();

        $response = Http::withHeaders($this->getHeaders())
        ->get($this->getMainEndpoint() . '/v2/chains/' . $chainCode . '/remoteVendors/' . $posVendorID . '/availability');

        return $this->getResponse($response, 'getAvailabilityStatus');

        throw new \Exception('Get Availability Status Failed: ' . $response->body());
    }

    // Update availability status
    public function updateAvailabilityStatus($chainCode, $posVendorID, $isAvailable = true)
    {
        $this->verifyOauthAccessToken();

        $existingStoreResponse = $this->getAvailabilityStatus($chainCode, $posVendorID);

        if(!$existingStoreResponse['successful']) {
            throw new \Exception('Existing Store Not Found');
        }
        $response = Http::withHeaders($this->getHeaders())
        ->put($this->getMainEndpoint() . '/v2/chains/' . $chainCode . '/remoteVendors/' . $posVendorID . '/availability', [
            'availabilityState' => $isAvailable ? self::STATUS_AVAILABLE : self::STATUS_UNAVAILABLE,
            'platformKey' => $existingStoreResponse['data']['platformKey'],
            'platformRestaurantId' => $existingStoreResponse['data']['platformRestaurantId'],
        ]);

        return $this->getResponse($response, 'updateAvailabilityStatus');

        throw new \Exception('Update Availability Status Failed: ' . $response->body());
    }

    //   Submit a catalog
    public function submitCatalog($merchantIdParam = [])
    {
        $this->verifyOauthAccessToken();

        if(!$merchantIdParam) {
            throw new \Exception('The Submit Catalog Param is Empty');
        }

        $response = Http::withHeaders($this->getHeaders())
        ->put($this->getMainEndpoint() . '/v2/chains/' . $merchantIdParam);

        return $this->getResponse($response, 'submitCatalog');

        throw new \Exception('Submit Catalog Failed: ' . $response->body());
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
        if($this->deliveryPlatformOperator->type === 'sandbox') {
            $endpoint = self::$sandbox_endpoint;
        }else {
            $endpoint = self::$endpoint;
        }

        return $endpoint;
    }

    private function verifyOauthAccessToken()
    {
        if(!$this->deliveryPlatformOperator->externalOauthToken()->exists() or !$this->deliveryPlatformOperator->externalOauthToken->access_token) {
            throw new \Exception('Oauth Client ID, Secret, Access Key Not Available');
        }

        return true;
    }
}
