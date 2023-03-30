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
    private $apiKey;
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
            $sourceResponse = Http::withHeaders($this->getHeaders('sources'))->post($this->getUrl('sources'), [
                'type' => $params['type'],
                'amount' => $params['amount'],
                'currency' => $params['currency'],
            ]);

            if($sourceResponse and $sourceResponse->collect()) {
                $source = $sourceResponse->collect();
                $chargeResponse = Http::withHeaders($this->getHeaders('charges'))->post($this->getUrl('charges'), [
                    'amount' => $params['amount'],
                    'currency' => $params['currency'],
                    'source' => $source['id'],
                ]);
                $this->curlData = $chargeResponse;

                return $this->curlData;
            }

        } catch (ClientException $e) {
            $response = $e->getResponse();
        }
        $this->curlData = $response;

        return $this->curlData;
    }

    private function getHeaders($action)
    {
        $headers = array(
            'Authorization' => 'Basic '.base64_encode($this->getApiKey($action).':'),
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        );

        return $headers;
    }


    public function getApiKey($action)
    {
        switch($action) {
            case 'sources':
                $this->apiKey = $this->apiKeys['public'];
                break;
            case 'charges':
                $this->apiKey = $this->apiKeys['secret'];
                break;
        }
        return $this->apiKey;
    }

    private function getUrl($action)
    {
        $this->url = self::$main;
        $this->url .= '/'.$action;

        return $this->url;
    }

}