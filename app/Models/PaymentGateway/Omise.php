<?php

namespace App\Models\PaymentGateway;

use App\Models\PaymentGatewayLog;
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
    private $chargeId;
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
                    'metadata' => $params['metadata'],
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

    public function refund($params = '')
    {
        try {
            $this->getChargeId($params['order_id']);

            $refundResponse = Http::withHeaders($this->getHeaders('refund'))->post($this->getUrl('refunds'), [
                'amount' => $params['amount'],
                'metadata' => [
                   'metadata' => $params['metadata']
                ]
            ]);
            $this->curlData = $refundResponse;

            return $this->curlData;
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
            case 'refunds':
                $this->apiKey = $this->apiKeys['secret'];
                break;
        }
        return $this->apiKey;
    }

    private function getUrl($action)
    {
        $this->url = self::$main;
        if($actions === 'refunds') {
            $this->url .= '/charges/'.$this->chargeId.'/'.$action;
        }else {
            $this->url .= '/'.$action;
        }

        return $this->url;
    }

    private function getChargeId($orderId)
    {
        $this->chargeId = PaymentGatewayLog::where('order_id', $orderId)->where('status', PaymentGatewayLog::STATUS_APPROVE)->first() ? PaymentGatewayLog::where('order_id', $orderId)->where('status', PaymentGatewayLog::STATUS_APPROVE)->first()->response['data']['id'] : null;

        return $this->chargeId;
    }

}