<?php

namespace App\Models\PaymentGateways;

use App\Models\PaymentGateway;
use App\Interfaces\PaymentGateway AS PaymentGatewayInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Http;

class Omise extends PaymentGateway implements PaymentGatewayInterface
{
    use HasFactory;

    const PAYMENT_METHOD_PAYNOW = 201;
    const PAYMENT_METHOD_DUITNOW = 301;
    public static $production = 'https://api.omise.co';
    protected $publicKey;
    protected $secretKey;
    private $orderId;
    private $refId;

    public function __construct($publicKey, $secretKey)
    {
        $this->publicKey = $publicKey;
        $this->secretKey = $secretKey;
    }

    public function createPayment($params = [])
    {
        $sourceId = $this->createSource($params);
        $response = $this->createCharge($params, $sourceId);

        $this->refId = isset($response['id']) ? $response['id'] : null;
        $this->orderId = isset($response['metadata']) && isset($response['metadata']['order_id']) ? $response['metadata']['order_id'] : null;

        return $this->createCharge($params, $sourceId);
    }

    public function createSource($params = [])
    {
        $response = Http::withHeaders($this->getHeaders($this->publicKey))
            ->post('https://api.omise.co/sources', [
                'type' => $params['type'],
                'amount' => $params['amount'],
                'currency' => $params['currency'],
            ]);

        if ($response->successful()) {
            return $response->json();
        }

        throw new \Exception('Source creation failed: ' . $response->body());
    }


    public function createCharge($params = [], $sourceId)
    {
        $response = Http::withHeaders($this->getHeaders($this->secretKey))
            ->post('https://api.omise.co/charges', [
                'amount' => $params['amount'],
                'currency' => $params['currency'],
                'source' => $sourceId,
                'metadata' => $params['metadata'],
                'return_uri' => $params['return_uri'],
            ]);

        if ($response->successful()) {
            return $response->json();
            // return $charge['id'];
        }

        throw new \Exception('Charge creation failed: ' . $response->body());
    }

    public function getOrderId()
    {
        return $this->orderId;
    }

    public function getRefId()
    {
        return $this->refId;
    }

    public function refundCharge($params = [], $sourceId)
    {
        $response = Http::withHeaders($this->getHeaders($this->secretKey))
            ->post('https://api.omise.co/charges/' . $chargeId . '/refunds', [
                'amount' => $params['amount'],
                'metadata' => $params['metadata'],
            ]);

        if ($response->successful()) {
            return $response->json();
            // return $refund['id'];
        }

        throw new \Exception('Refund creation failed: ' . $response->body());
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