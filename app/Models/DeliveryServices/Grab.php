<?php

namespace App\Models\DeliveryServices;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Http;

class Grab extends Model
{
    use HasFactory;

    public static $staging = 'https://partner-api.grab.com';
    public static $production = 'https://partner-api.grab.com';
    private $clientId;
    private $clientSecret;
    private $environment;
    private $scope;

    public function __construct($clientId, $clientSecret, $environment = 'staging', $scope = 'mart:partner_api')
    {
        $this->clientId = $clientId;
        $this->clientSecret = $clientSecret;
        $this->environment = $environment;
        $this->scope = $scope;
    }

    public function getGrabOAuthToken()
    {
        $repsonse = Http::withHeaders($this->getHeaders([
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
        ]))
        ->post($this->getEndpoint() . '/grabid/v1/oauth2/token', [
            'grant_type' => 'client_credentials',
            'scope' => $this->scope,
        ]);

        if ($response->successful()) {
            return $response->json();
        }

        throw new \Exception('Get OAuthToken Failed: ' . $response->body());
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
