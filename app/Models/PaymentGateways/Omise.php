<?php

namespace App\Models\PaymentGateways;

use App\Models\PaymentGateway;
use App\Interfaces\PaymentGateway AS PaymentGatewayInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class Omise extends PaymentGateway implements PaymentGatewayInterface
{
    use HasFactory;

    const AMOUNT_MULTIPLIER = 100;

    const PAYMENT_METHOD_PAYNOW = 201;
    const PAYMENT_METHOD_DUITNOW = 301;
    const PAYMENT_METHOD_PROMPTPAY = 401;

    const PAYMENT_METHOD_MAPPING = [
        self::PAYMENT_METHOD_PAYNOW => 'paynow',
        self::PAYMENT_METHOD_DUITNOW => 'duitnow_qr',
        self::PAYMENT_METHOD_PROMPTPAY => 'promptpay',
    ];

    public static $production = 'https://api.omise.co';
    protected $publicKey;
    protected $secretKey;
    private $orderId;
    private $operatorPaymentGateway;
    private $referenceId;

    public function __construct($publicKey, $secretKey)
    {
        $this->publicKey = $publicKey;
        $this->secretKey = $secretKey;
    }

    public function createPayment($params = [])
    {
        $sourceId = $this->createSource($params);
        $response = $this->createCharge($params, $sourceId['id']);
        $this->referenceId = isset($response['id']) ? $response['id'] : null;
        $this->orderId = isset($response['metadata']) && isset($response['metadata']['order_id']) ? $response['metadata']['order_id'] : null;

        return $response;
    }

    public function createSource($params = [])
    {
        $response = Http::withHeaders($this->getHeaders($this->publicKey))
            ->post('https://api.omise.co/sources', [
                'type' => $params['type'],
                'amount' => $params['amount'] * self::AMOUNT_MULTIPLIER,
                'currency' => $params['currency'],
            ]);

        if ($response->successful()) {
            return $response->json();
        }
        throw new \Exception('Source creation failed: ' . $response->body());
    }


    public function createCharge($params = [], $sourceId)
    {
        // dd($params, $params['metadata'], $sourceId);
        $response = Http::withHeaders($this->getHeaders($this->secretKey))
            ->post('https://api.omise.co/charges', [
                'amount' => $params['amount'] * self::AMOUNT_MULTIPLIER,
                'currency' => $params['currency'],
                'source' => $sourceId,
                'metadata' => $params['metadata'],
                'return_uri' => $params['return_uri'],
            ]);

        if($response->successful()) {
            return $response->json();
        }
        throw new \Exception('Charge creation failed: ' . $response->body());
    }

    public function getOperatorPaymentGateway()
    {
       return $this->operatorPaymentGateway;
    }

    public function getOrderId()
    {
        return $this->orderId;
    }

    public function getReferenceId()
    {
        return $this->referenceId;
    }

    public function refundCharge($params = [], $chargeId)
    {
        Log::info('Refund params: ' . 'amount = '.$params['amount'] * self::AMOUNT_MULTIPLIER. ', orderId = '.$params['metadata']['order_id']);
        $response = Http::withHeaders($this->getHeaders($this->secretKey))
            ->post('https://api.omise.co/charges/' . $chargeId . '/refunds', [
                'amount' => $params['amount'] * self::AMOUNT_MULTIPLIER,
                'metadata' => $params['metadata'],
            ]);

        if ($response->successful()) {
            return $response->json();
        }
        throw new \Exception('Refund creation failed: ' . $response->body());
    }

    public function setOperatorPaymentGateway($operatorPaymentGateway)
    {
        $this->operatorPaymentGateway = $operatorPaymentGateway;
    }

    private function getHeaders($apiKey)
    {
        $headers = array(
            'Authorization' => 'Basic '.base64_encode($apiKey.':'),
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        );

        return $headers;
    }

}