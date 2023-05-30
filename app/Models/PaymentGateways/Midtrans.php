<?php

namespace App\Models\PaymentGateways;

use App\Models\PaymentGateway;
use App\Interfaces\PaymentGateway AS PaymentGatewayInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Http;

class Midtrans extends PaymentGateway implements PaymentGatewayInterface
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
    private $orderId;
    private $operatorPaymentGateway;
    private $referenceId;

    public function __construct($apiKey)
    {
        $this->apiKey = $apiKey;
    }

    public function createPayment($amount, $currency)
    {
        $response = $this->createCharge($params);

        $this->orderId = isset($response['order_id']) ? $response['order_id'] : null;
        $this->referenceId = isset($response['transaction_id']) ? $response['transaction_id'] : null;

        return $response;
    }

    public function createCharge($params = [])
    {
        $response = Http::withHeaders($this->getHeaders($this->secretKey))
            ->post($this->getEndpoint(), [
                'payment_type' => 'qris',
                'transaction_details' => [
                    'order_id' => $params['metadata']['order_id'],
                    'gross_amount' => $params['amount'],
                  ],
                  'qris' => [
                    'acquirer' => $params['type'],
                  ],
                  'custom_expiry' => [
                    'order_time' => Carbon::now()->setTimeZone($params['timezone'])->format('Y-m-d H:i:s O'),
                    'expiry_duration' => $params['expiry_seconds'],
                    'unit' => 'second',
                  ]
            ]);

        if ($response->successful()) {
            return $response->json();
        }

        throw new \Exception('Charge creation failed: ' . $response->body());
    }

    private function getEndpoint()
    {
        $endpoint = '';

        if(config('app.env') === 'production') {
            $endpoint = self::$production;
        }else {
            $endpoint = self::$sandbox;
        }

        $endpoint .= '/v2/charge';

        return $endpoint;
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
