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
    private $action;
    private $curlData;
    private $url;

    public function __construct($apiKey = '', $action = 'QRIS')
    {
        $this->apiKey = $apiKey;
        $this->action = $action;
        $this->setUrl($action);
    }

}