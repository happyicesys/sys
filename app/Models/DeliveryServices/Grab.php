<?php

namespace App\Models\DeliveryServices;

use App\Models\DeliveryPlatform;
use App\Interfaces\DeliveryPlatformInterface;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Http;

class Grab extends DeliveryPlatform implements DeliveryPlatformInterface
{
    use HasFactory;

    public static $staging = 'https://partner-api.grab.com';
    public static $production = 'https://partner-api.grab.com';
    public static $scope = 'mart:partner_api';
    public static $countryCode = 'SG';
    private $clientId;
    private $clientSecret;
    private $accessToken;

    public function __construct($clientId, $clientSecret, $accessToken)
    {
        $this->clientId = $clientId;
        $this->clientSecret = $clientSecret;
        $this->accessToken = $accessToken;

        // $this->merchantId = $merchantId;
    }

    public function getGrabOAuthToken()
    {
        $repsonse = Http::withHeaders($this->getHeaders([
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
        ]))
        ->post($this->getEndpoint() . '/grabid/v1/oauth2/token', [
            'grant_type' => 'client_credentials',
            'scope' => self::$scope,
        ]);

        if ($response->successful()) {
            return $response->json();
        }

        throw new \Exception('Get OAuthToken Failed: ' . $response->body());
    }

    public function getMartCategories()
    {
        $repsonse = Http::withHeaders($this->getHeaders([
            'Authorization' => 'Bearer ' . $this->accessToken,
            'countryCode' => self::$countryCode,
        ]))
        ->get($this->getEndpoint() . '/partner/v1/menu/categories');

        if ($response->successful()) {
            return $response->json();
        }

        throw new \Exception('Get Mart Categories Failed: ' . $response->body());
    }

    public function notifyUpdateMenu()
    {
        $repsonse = Http::withHeaders($this->getHeaders([
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . $this->accessToken,
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
        $endpoint = $this->environment == 'staging' ? self::$staging : self::$production;

        return $endpoint;
    }


}
