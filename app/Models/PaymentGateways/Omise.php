<?php

namespace App\Models\PaymentGateways;

use App\Models\PaymentGatewayLog;
use App\Interfaces\PaymentGateway;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Http;

class Omise extends Model implements PaymentGateway
{
    use HasFactory;

    const PAYMENT_METHOD_PAYNOW = 201;
    const PAYMENT_METHOD_DUITNOW = 301;
    public static $production = 'https://api.omise.co';
    protected $publicKey;
    protected $secretKey;

    public function __construct($publicKey, $secretKey)
    {
        $this->publicKey = $publicKey;
        $this->secretKey = $secretKey;
    }

    public function createPayment($amount, $currency)
    {
        $sourceId = $this->createSource($params);
        $this->createCharge($params, $sourceId);
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
            $source = $response->json();
            return $source['id'];
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
            $charge = $response->json();
            return $charge['id'];
        }

        throw new \Exception('Charge creation failed: ' . $response->body());
    }

    public function refundCharge($params = [], $sourceId)
    {
        $response = Http::withHeaders($this->getHeaders($this->secretKey))
            ->post('https://api.omise.co/charges/' . $chargeId . '/refunds', [
                'amount' => $params['amount'],
                'metadata' => $params['metadata'],
            ]);

        if ($response->successful()) {
            $refund = $response->json();
            return $refund['id'];
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