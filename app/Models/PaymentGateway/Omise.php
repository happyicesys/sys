<?php

namespace App\Models\PaymentGateway;

use App\Interfaces\PaymentGatewayInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Http;

class Omise extends Model implements PaymentGatewayInterface
{
    use HasFactory;

    const PAYMENT_METHOD_PAYNOW = 201;

    public static $main = 'https://api.omise.co';
    private $apiKeys;
    private $action;
    private $curlData;
    private $url;

    public function __construct($apiKeys = [])
    {
        $this->apiKeys = $apiKeys;
    }

    public function executeRequest($params = '')
    {
        try {
            // Http::withHeaders()->post($this->getUrl)
            // $sourceResponse = Http::withHeaders($this->getHeaders())->post($this->getUrl('sources'), $params);
            // $response = Http::withHeaders($this->getHeaders())->post($this->getUrl(), $params);

        } catch (ClientException $e) {
            $response = $e->getResponse();
        }
        $this->curlData = $response;

        return $this->curlData;
    }

    private function getHeaders()
    {
        $headers = array(
            'Authorization' => 'Basic '.base64_encode($this->getApiKey().':'),
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        );

        return $headers;
    }

    private function getUrl($action)
    {
        $this->url = self::$main;

        $this->url .= '/'.$action;

        return $this->url;
    }

}