<?php

namespace App\Models\PaymentGateway;

use App\Interfaces\PaymentGatewayInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Http;

class Midtrans extends Model implements PaymentGatewayInterface
{
    use HasFactory;

    var $array, $obj, $url, $id, $deliverLevel, $errorMessage, $autoSubmit;
    public static $clientServerKey = '';

    public function __construct($clientServerKey)
    {
        $clientServerKey = $clientServerKey;
        $this->array = array();
        $this->obj = new MidtransAction;
        if (empty($clientServerKey)) {
            $this->obj->setClientServerKey(self::$clientServerKey);
        } else {
            $this->obj->setClientServerKey($clientServerKey);
        }
    }

    public function setClientServerKey($clientServerKey = '')
    {
        if (empty($clientServerKey)) {
            $this->obj->setClientServerKey(self::$clientServerKey);
        } else {
            $this->obj->setClientServerKey($clientServerKey);
        }
        return $this;
    }


    public function getClientServerKey()
    {
        return self::$clientServerKey;
    }

}

class MidtransAction
{
    var $url, $action, $curlData, $clientServerKey;
    public static $production = 'https://api.midtrans.com/';
    public static $staging = 'https://api.sandbox.midtrans.com/';

    public function setClientServerKey($clientServerKey)
    {
        $this->clientServerKey = $clientServerKey;
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
            $this->url .= 'v2/charge/';
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
