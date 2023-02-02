<?php

namespace App\Models\PaymentGateway;

use App\Interfaces\PaymentGatewayInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Http;

class Midtrans extends Model implements PaymentGatewayInterface
{
    use HasFactory;

    public static $apiKey = '';
    public static $staging = 'https://api.sandbox.midtrans.com/';
    public static $production = 'https://api.midtrans.com/';

    public function __construct($apiKey)
    {
        if(config('app.env') === 'production') {
            $this->url = self::$production;
        }else {
            $this->url = self::$staging;
        }
    }

    public function setApiKey($apiKey)
    {
        $this->apiKey = $apiKey;
        return $this;
    }

    public function getApiKey()
    {
        return self::$apiKey;
    }

    public function setRequest($params = '')
    {
        if ($this->action == 'QRIS') {
            $reqType = 'POST';
        }

        $headers = array(
            'Authorization' => 'Basic '.base64_encode($this->clientServerKey.':'),
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        );

        try {
            $response = $Http::withHeaders($headers)->post($this->url, [$params]);
        } catch (ClientException $e) {
            $response = $e->getResponse();
        }
        $this->curlData = $response;

        return $this->curlData;
    }

}

class MidtransAction
{
    var $url, $action, $curlData, $apiKey;
    public static $staging = 'https://api.sandbox.midtrans.com/';
    public static $production = 'https://api.midtrans.com/';

    public function setApiKey($apiKey)
    {
        $this->apiKey = $apiKey;
        return $this;
    }

    public function setAction($action)
    {
        $this->action = $action;
        return $this;
    }

    public function setURL($mode, $id = '')
    {
        if ($mode == 'local') {
            $this->url = self::$staging;
        } else if ($mode == 'production') {
            $this->url = self::$production;
        } else {
            self::throwException('Invalid API Key Provided');
        }
        if ($this->action == 'QRIS') {
            $this->url .= 'v2/charge';
        }
        return $this;
    }

    public function curlAction($params = '')
    {
        $client = new Client();

        /*
         * Determine request type
         * Action Available:
         * QRIS (REQUEST QR FOR PAYMENT)
         */

        if ($this->action == 'QRIS') {
            $reqType = 'POST';
        }

        $headers = array(
            'Authorization' => 'Basic '.base64_encode($this->clientServerKey.':'),
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        );

        try {
            $response = $Http::withHeaders($headers)->post($this->url, [$params]);
        } catch (ClientException $e) {
            $response = $e->getResponse();
        }
        $this->curlData = $response;

        return $this->curlData;
    }
}
