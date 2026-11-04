<?php

namespace App\Models\PaymentGateways;

use App\Interfaces\PaymentGateway as PaymentGatewayInterface;
use App\Models\PaymentGateway;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\Http;

class Midtrans extends PaymentGateway implements PaymentGatewayInterface
{
    use HasFactory;

    const AMOUNT_MULTIPLIER = 1;

    /**
     * Longest a QR stays payable. Must stay under PaymentController's 210 s cutoff, past which a payment
     * is never served. Old boards send expiry_seconds = 120000 (their 120 s in milliseconds), which is
     * 33 hours to Midtrans; those boards are hand-install only, so the cap is here (2026-11-04).
     */
    const MAX_QR_SECONDS = 180;

    const PAYMENT_METHOD_GOPAY = 101;

    const PAYMENT_METHOD_AIRPAY_SHOPEE = 102;

    const PAYMENT_METHOD_DANA = 103;

    const PAYMENT_METHOD_OVO = 104;

    const PAYMENT_METHOD_TCASH = 105;

    const PAYMENT_METHOD_MAPPING = [
        self::PAYMENT_METHOD_GOPAY => 'gopay',
        self::PAYMENT_METHOD_AIRPAY_SHOPEE => 'airpay shopee',
        self::PAYMENT_METHOD_DANA => 'dana',
        self::PAYMENT_METHOD_OVO => 'ovo',
        self::PAYMENT_METHOD_TCASH => 'tcash',
    ];

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

    public function createPayment($params = [])
    {
        $response = $this->createCharge($params);

        $this->orderId = isset($response['order_id']) ? $response['order_id'] : null;
        $this->referenceId = isset($response['transaction_id']) ? $response['transaction_id'] : null;

        return $response;
    }

    public function createCharge($params = [])
    {
        $response = Http::withHeaders($this->getHeaders($this->apiKey))
            ->post($this->getEndpoint(), [
                'payment_type' => 'qris',
                'transaction_details' => [
                    'order_id' => $params['metadata']['order_id'],
                    'gross_amount' => $params['amount'] * self::AMOUNT_MULTIPLIER,
                ],
                'qris' => [
                    'acquirer' => $params['type'],
                ],
                'custom_expiry' => [
                    'order_time' => Carbon::now()->setTimeZone($params['timezone'])->format('Y-m-d H:i:s O'),
                    'expiry_duration' => self::qrLifetime($params['expiry_seconds'] ?? null),
                    'unit' => 'second',
                ],
            ]);

        // if ($response->successful()) {
        return $response;
        // }

        // throw new \Exception('Charge creation failed: ' . $response->body());
    }

    public static function qrLifetime($seconds): int
    {
        $seconds = (int) $seconds;

        return $seconds > 0 ? min($seconds, self::MAX_QR_SECONDS) : self::MAX_QR_SECONDS;
    }

    /** Midtrans's own record of an order (GET /v2/{order_id}/status). It can answer 200 with a 404 in the body. */
    public function getTransactionStatus(string $orderId, int $timeout = 8)
    {
        return Http::withHeaders($this->getHeaders($this->apiKey))
            ->timeout($timeout)
            ->get($this->getBaseUrl().'/v2/'.rawurlencode($orderId).'/status');
    }

    private function getEndpoint()
    {
        return $this->getBaseUrl().'/v2/charge';
    }

    private function getBaseUrl()
    {
        return config('app.env') === 'production' ? self::$production : self::$sandbox;
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
        $headers = [
            'Authorization' => 'Basic '.base64_encode($apiKey.':'),
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ];

        return $headers;
    }
}
