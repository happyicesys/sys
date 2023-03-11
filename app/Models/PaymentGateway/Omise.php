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

    public function __construct($apiKeys = [], $action = 'QRIS')
    {
        $this->apiKeys = $apiKeys;
        $this->action = $action;
        $this->setUrl($action);
    }

}