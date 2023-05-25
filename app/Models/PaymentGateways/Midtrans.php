<?php

namespace App\Models\PaymentGateways;

use App\Interfaces\PaymentGateway;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Http;

class Midtrans extends Model implements PaymentGateway
{
    use HasFactory;

    const PAYMENT_METHOD_GOPAY = 101;
    const PAYMENT_METHOD_AIRPAY_SHOPEE = 102;
    const PAYMENT_METHOD_DANA = 103;
    const PAYMENT_METHOD_OVO = 104;
    const PAYMENT_METHOD_TCASH = 105;

    public static $sandbox = 'https://api.sandbox.midtrans.com';
    public static $production = 'https://api.midtrans.com';
    protected $apiKey;

    public function __construct($apiKey)
    {
        $this->apiKey = $apiKey;
    }

    public function createPayment($amount, $currency)
    {
        $sourceId = $this->createSource($params);
        $this->createCharge($params, $sourceId);
    }


    public function executeRequest($params = '')
    {
        // dd($this->getHeaders(), $this->getUrl(), $params);
        try {
            $response = Http::withHeaders($this->getHeaders())->post($this->getUrl(), $params);
        } catch (ClientException $e) {
            $response = $e->getResponse();
        }
        $this->curlData = $response;

        return $this->curlData;
    }

    public function getApiKey()
    {
        return $this->apiKey;
    }

    private function setUrl($action)
    {
        $this->url = self::$sandbox;
        // if(config('app.env') === 'production') {
        //     $this->url = self::$production;
        // }else {
        //     $this->url = self::$sandbox;
        // }

        if($this->action === 'QRIS') {
            $this->url .= '/v2/charge';
        }
    }

    private function getUrl()
    {
        return $this->url;
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
}
