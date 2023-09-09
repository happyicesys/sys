<?php

namespace App\Models\DeliveryServices;

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

    public static $production_endpoint = 'https://partner-api.grab.com';
    public static $sandbox_scope = 'sandbox.mart.partner_api';
    public static $production_scope = 'mart.partner_api';
    private $deliveryPlatformOperator;

    public function __construct(DeliveryPlatformOperator $deliveryPlatformOperator)
    {
        $this->deliveryPlatformOperator = $deliveryPlatformOperator;
    }

    public function getGrabOAuthToken()
    {
        if(!$this->deliveryPlatformOperator->externalOauthTokens()->exists()) {
            throw new \Exception('Oauth Client ID and Secret Not Found: ' . $response->body());
        }

        $repsonse = Http::withHeaders($this->getHeaders([
            'client_id' => $this->deliveryPlatformOperator->externalOauthTokens->client_id,
            'client_secret' => $this->deliveryPlatformOperator->externalOauthTokens->client_secret,
        ]))
        ->post($this->getEndpoint() . '/grabid/v1/oauth2/token', [
            'grant_type' => $this->deliveryPlatformOperator->externalOauthTokens->granted_type,
            'scope' => $this->getScope(),
        ]);

        if ($response->successful()) {
            return $response->json();
        }

        throw new \Exception('Get OAuthToken Failed: ' . $response->body());
    }

    public function getMartCategories()
    {
        $this->verifyOauthAccessToken();

        $repsonse = Http::withHeaders($this->getHeaders([
            'Authorization' => 'Bearer ' . $this->deliveryPlatformOperator->externalOauthTokens->access_token,
            'countryCode' => $this->deliveryPlatformOperator->operator->country->code,
        ]))
        ->get($this->getEndpoint() . '/partner/v1/menu/categories');

        if ($response->successful()) {
            return $response->json();
        }

        throw new \Exception('Get Mart Categories Failed: ' . $response->body());
    }

    public function notifyUpdateMenu()
    {
        $this->verifyOauthAccessToken();

        $repsonse = Http::withHeaders($this->getHeaders([
            'Authorization' => 'Bearer ' . $this->deliveryPlatformOperator->externalOauthTokens->access_token,
        ]))
        ->post($this->getEndpoint() . '/partner/v1/merchant/menu/notification', [
            'merchantID' => $this->merchantId,
        ]);

        if ($response->successful()) {
            return $response->json();
        }

        throw new \Exception('Get Mart Categories Failed: ' . $response->body());
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

    private function getEndpoint()
    {
        return self::$production_endpoint;
    }

    private function getScope()
    {
        $scope = self::$sandbox_scope;

        if(config('app.env') === 'local') {
            $scope = self::$production_scope;
        }

        return $scope;
    }

    private function getOperatorCountryCode()
    {
        return self::$countryCode;
    }

    private function verifyOauthAccessToken()
    {
        if(!$this->deliveryPlatformOperator->externalOauthTokens()->exists() or !$this->deliveryPlatformOperator->externalOauthTokens->access_token) {
            throw new \Exception('Oauth Client ID, Secret, Access Key Not Available: ' . $response->body());
        }

        return true;
    }
}
