<?php

namespace App\Models\PaymentGateways;

use App\Interfaces\PaymentGateway as PaymentGatewayInterface;
use App\Models\PaymentGateway;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class Fiuu extends PaymentGateway implements PaymentGatewayInterface
{
    use HasFactory;

    public const PRODUCTION_BASE_URL = 'https://pay.fiuu.com/RMS/pay';
    public const SANDBOX_BASE_URL = 'https://sandbox-payment.fiuu.com/RMS/pay';

    public const PAYMENT_METHOD_GRABPAY_SG = 601;
    public const PAYMENT_METHOD_PAYNOW_SG = 602;
    public const PAYMENT_METHOD_AXS_SG = 603;
    public const PAYMENT_METHOD_SINGPOST_SG = 604;

    public const PAYMENT_METHOD_FPX_MY = 610;
    public const PAYMENT_METHOD_DUITNOW_MY = 611;
    public const PAYMENT_METHOD_TNG_MY = 612;
    public const PAYMENT_METHOD_GRABPAY_MY = 613;
    public const PAYMENT_METHOD_SHOPEEPAY_MY = 614;

    public const PAYMENT_METHOD_DANA_ID = 620;
    public const PAYMENT_METHOD_SHOPEEPAY_ID = 621;
    public const PAYMENT_METHOD_GOPAY_ID = 622;
    public const PAYMENT_METHOD_CIMB_QRIS_ID = 623;
    public const PAYMENT_METHOD_KREDIVO_ID = 624;
    public const PAYMENT_METHOD_INDODANA_ID = 625;

    public const PAYMENT_METHOD_MAPPING = [
        self::PAYMENT_METHOD_GRABPAY_SG => 'GrabPay_SG',
        self::PAYMENT_METHOD_PAYNOW_SG => 'PayNow',
        self::PAYMENT_METHOD_AXS_SG => 'AXS',
        self::PAYMENT_METHOD_SINGPOST_SG => 'singpost',

        self::PAYMENT_METHOD_FPX_MY => 'fpx',
        self::PAYMENT_METHOD_DUITNOW_MY => 'RPP_DuitNowQR',
        self::PAYMENT_METHOD_TNG_MY => 'TNG-EWALLET',
        self::PAYMENT_METHOD_GRABPAY_MY => 'GrabPay',
        self::PAYMENT_METHOD_SHOPEEPAY_MY => 'ShopeePay',

        self::PAYMENT_METHOD_DANA_ID => 'e2Pay_DANA',
        self::PAYMENT_METHOD_SHOPEEPAY_ID => 'e2Pay_SHOPEEPAY_QRIS',
        self::PAYMENT_METHOD_GOPAY_ID => 'e2Pay_GOPAY',
        self::PAYMENT_METHOD_CIMB_QRIS_ID => 'e2Pay_CIMB_QRIS',
        self::PAYMENT_METHOD_KREDIVO_ID => 'e2Pay_Kredivo_FN',
        self::PAYMENT_METHOD_INDODANA_ID => 'e2Pay_Indodana_FN',
    ];

    /**
     * Curated payment-channel catalogue for primary deployment countries.
     * Structure keeps things extensible for additional territories.
     */
    public const CHANNEL_CATALOGUE = [
        'SG' => [
            [
                'code' => 'GrabPay_SG',
                'channel' => 'GrabPay_SG',
                'filename' => 'GrabPay_SG.php',
                'label' => 'GrabPay (SG)',
                'currency' => 'SGD',
            ],
            [
                'code' => 'PayNow',
                'channel' => 'PayNow',
                'filename' => 'PayNow.php',
                'label' => 'PayNow',
                'currency' => 'SGD',
            ],
            [
                'code' => 'AXS',
                'channel' => 'AXS',
                'filename' => 'AXS.php',
                'label' => 'AXS Kiosk',
                'currency' => 'SGD',
            ],
            [
                'code' => 'singpost',
                'channel' => 'singpost',
                'filename' => 'singpost.php',
                'label' => 'SingPost SAM / eNETS',
                'currency' => 'SGD',
            ],
        ],
        'MY' => [
            [
                'code' => 'FPX',
                'channel' => 'fpx',
                'filename' => 'fpx.php',
                'label' => 'FPX (All Banks)',
                'currency' => 'MYR',
            ],
            [
                'code' => 'RPP_DuitNowQR',
                'channel' => 'RPP_DuitNowQR',
                'filename' => 'RPP_DuitNowQR.php',
                'label' => 'DuitNow QR',
                'currency' => 'MYR',
            ],
            [
                'code' => 'TNG-EWALLET',
                'channel' => 'TNG-EWALLET',
                'filename' => 'TNG-EWALLET.php',
                'label' => 'Touch ’n Go eWallet',
                'currency' => 'MYR',
            ],
            [
                'code' => 'GrabPay',
                'channel' => 'GrabPay',
                'filename' => 'GrabPay.php',
                'label' => 'GrabPay MY',
                'currency' => 'MYR',
            ],
            [
                'code' => 'ShopeePay',
                'channel' => 'ShopeePay',
                'filename' => 'ShopeePay.php',
                'label' => 'ShopeePay',
                'currency' => 'MYR',
            ],
        ],
        'ID' => [
            [
                'code' => 'e2Pay_DANA',
                'channel' => 'e2Pay_DANA',
                'filename' => 'e2Pay_DANA.php',
                'label' => 'DANA',
                'currency' => 'IDR',
            ],
            [
                'code' => 'e2Pay_ShopeePay_QRIS',
                'channel' => 'e2Pay_SHOPEEPAY_QRIS',
                'filename' => 'e2Pay_ShopeePay_QRIS.php',
                'label' => 'ShopeePay QRIS',
                'currency' => 'IDR',
            ],
            [
                'code' => 'e2Pay_GOPAY',
                'channel' => 'e2Pay_GOPAY',
                'filename' => 'e2Pay_GOPAY.php',
                'label' => 'GoPay',
                'currency' => 'IDR',
            ],
            [
                'code' => 'e2Pay_CIMB_QRIS',
                'channel' => 'e2Pay_CIMB_QRIS',
                'filename' => 'e2Pay_CIMB_QRIS.php',
                'label' => 'CIMB QRIS',
                'currency' => 'IDR',
            ],
            [
                'code' => 'e2Pay_Kredivo_FN',
                'channel' => 'e2Pay_Kredivo_FN',
                'filename' => 'e2Pay_Kredivo_FN.php',
                'label' => 'Kredivo Financing',
                'currency' => 'IDR',
            ],
            [
                'code' => 'e2Pay_Indodana_FN',
                'channel' => 'e2Pay_Indodana_FN',
                'filename' => 'e2Pay_Indodana_FN.php',
                'label' => 'Indodana Financing',
                'currency' => 'IDR',
            ],
        ],
    ];

    /**
     * Merchant credentials pulled from the operator gateway settings.
     */
    protected string $merchantId;
    protected string $verifyKey;
    protected string $secretKey;

    /**
     * Toggle for using the sandbox host.
     */
    protected bool $useSandbox = false;

    protected ?string $orderId = null;
    protected ?string $referenceId = null;
    protected $operatorPaymentGateway;
    protected array $lastRequestDetails = [];

    public function __construct(string $merchantId, string $verifyKey, string $secretKey, bool $useSandbox = false)
    {
        $this->merchantId = $merchantId;
        $this->verifyKey = $verifyKey;
        $this->secretKey = $secretKey;
        $this->useSandbox = $useSandbox;
    }

    /**
     * Initiate a payment with Fiuu.
     *
     * @param  array  $params
     * @return array
     */
    public function createPayment($params)
    {
        /**
         * Expected $params keys (subject to refinement):
         * - amount (float|string)
         * - order_id (string)
         * - description (string)
         * - customer_email (string)
         * - customer_name (string)
         * - customer_phone (string)
         * - currency (string: ISO-4217)
         * - channel_code/channel (string|null)
         * - return_url / callback_url / cancel_url (string|null)
         */

        $payload = $this->buildPayload($params);
        $endpoint = $this->buildEndpoint(Arr::get($payload, 'channel'));

        $this->orderId = Arr::get($payload, 'orderid');
        $this->referenceId = $this->orderId;
        $this->lastRequestDetails = [
            'endpoint' => $endpoint,
            'payload' => $payload,
        ];

        return Http::asForm()
            ->withHeaders([
                'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                'User-Agent' => 'mark1-integrator/1.0 (+https://mark1.local)',
            ])
            ->post($endpoint, $payload);
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

    /**
     * Build the payload that will be posted to Fiuu.
     */
    protected function buildPayload(array $params): array
    {
        $amount = number_format((float) Arr::get($params, 'amount', 0), 2, '.', '');
        $orderId = Arr::get($params, 'order_id', 'ORD-' . Str::uuid());
        $country = strtoupper(Arr::get($params, 'country', 'MY'));
        $channelCode = Arr::get($params, 'channel_code');
        $channelMeta = $channelCode ? $this->resolveChannel($country, $channelCode) : null;
        $channelValue = $channelMeta['channel'] ?? Arr::get($params, 'channel');

        return array_filter([
            'amount' => $amount,
            'orderid' => $orderId,
            'bill_name' => Arr::get($params, 'customer_name', 'Guest'),
            'bill_email' => Arr::get($params, 'customer_email', 'guest@example.com'),
            'bill_mobile' => Arr::get($params, 'customer_phone', '0000000000'),
            'bill_desc' => Arr::get($params, 'description', 'Purchase'),
            'country' => $country,
            'currency' => Arr::get($params, 'currency', $channelMeta['currency'] ?? 'MYR'),
            'channel' => $channelValue,
            'guest_checkout' => Arr::get($params, 'guest_checkout', 1),
            'vcode' => $this->generateVCode($amount, $orderId, Arr::get($params, 'currency', $channelMeta['currency'] ?? 'MYR')),
            'returnurl' => Arr::get($params, 'return_url'),
            'callbackurl' => Arr::get($params, 'callback_url'),
            'cancelurl' => Arr::get($params, 'cancel_url'),
        ], function ($value) {
            return !is_null($value) && $value !== '';
        });
    }

    protected function resolveChannel(string $country, string $channelCode): ?array
    {
        return collect(self::channelsForCountry($country))
            ->firstWhere(fn ($channel) => $channel['code'] === $channelCode);
    }

    protected function buildEndpoint(?string $paymentMethod = null): string
    {
        $base = $this->useSandbox ? self::SANDBOX_BASE_URL : self::PRODUCTION_BASE_URL;

        $path = rtrim($base, '/') . '/' . $this->merchantId;

        if ($paymentMethod) {
            $path .= '/' . ltrim($paymentMethod, '/');
        }

        return $path;
    }

    protected function generateVCode(string $amount, string $orderId, string $currency): string
    {
        // Default formula with extended format (amount + merchantID + orderID + verifyKey + currency)
        return md5($amount . $this->merchantId . $orderId . $this->verifyKey . $currency);
    }

    public function getLastRequestDetails(): array
    {
        return $this->lastRequestDetails;
    }

    /**
     * Convenience accessor for Fiuu channel metadata by ISO country code.
     */
    public static function channelsForCountry(string $countryCode): array
    {
        return self::CHANNEL_CATALOGUE[strtoupper($countryCode)] ?? [];
    }

    /**
     * Flattened list of channel codes keyed by country to enable dropdowns.
     */
    public static function availableChannelCodes(): array
    {
        $codes = [];

        foreach (self::CHANNEL_CATALOGUE as $country => $channels) {
            $codes[$country] = collect($channels)->pluck('code')->all();
        }

        return $codes;
    }

    /**
     * Verify Fiuu response signature (skey) to ensure integrity.
     */
    public function verifyResponse(array $payload): bool
    {
        $txnId = Arr::get($payload, 'txnID');
        $orderId = Arr::get($payload, 'orderid');
        $status = Arr::get($payload, 'status');
        $amount = Arr::get($payload, 'amount');
        $currency = Arr::get($payload, 'currency', 'MYR');
        $paydate = Arr::get($payload, 'paydate');
        $appcode = Arr::get($payload, 'appcode');
        $skey = Arr::get($payload, 'skey');

        if (!$txnId || !$orderId || !$status || !$amount || !$paydate || !$skey) {
            Log::warning('Fiuu response missing required fields', ['payload' => $payload]);
            return false;
        }

        $preSkey = md5($txnId . $orderId . $status . $this->merchantId . $amount . $currency);
        $expected = md5($paydate . $this->merchantId . $preSkey . $appcode . $this->secretKey);

        return hash_equals($expected, $skey);
    }
}
