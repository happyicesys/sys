<?php

namespace App\Services;

use App\Models\OperatorPaymentGateway;
use App\Models\PaymentGatewayLog;
use App\Models\PaymentGateways\Fiuu;
use App\Models\PaymentGateways\Midtrans;
use App\Models\PaymentGateways\Omise;
use App\Models\Vend;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Intervention\Image\Laravel\Facades\Image;
use Symfony\Component\BrowserKit\HttpBrowser;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpClient\HttpClient;
// use Imagick;
// use ImagickPixel;
use Zxing\QrReader;

class PaymentGatewayService
{
    protected $errorService;

    public function __construct()
    {
        $this->errorService = new ErrorService;
    }

    public function createPaymentQrText(Vend $vend, $params): array
    {
        $qrCodeText = '';
        $qrCodeUrl = '';
        $errorMsg = '';
        $isCreateInput = false;
        $isResizeImage = false;
        $isRequiredDecode = false;

        $paymentRequest = $this->createPaymentRequest($vend, $params);
        $response = $paymentRequest['response'];
        $rawResponse = $paymentRequest['raw_response'] ?? null;
        $operatorPaymentGateway = $paymentRequest['operatorPaymentGateway'];

        switch ($operatorPaymentGateway->paymentGateway->name) {
            case 'midtrans':
                if (isset($response['actions']) and isset($response['actions'][0]['url'])) {
                    $isCreateInput = true;
                    $qrCodeUrl = $response['actions'][0]['url'];
                } elseif (isset($response['validation_messages']) or isset($response['status_message'])) {
                    $errorMsg .= 'Error: ';
                    $errorMsg .= isset($response['validation_messages']) ? $response['validation_messages'][0] : $response['status_message'];
                }
                $isResizeImage = true;
                $isRequiredDecode = true;
                break;
            case 'omise':
                if (
                    (isset($response['source']['flow'])
                        and $response['source']['flow'] == 'offline'
                        and isset($response['source']['scannable_code']['image']['download_uri'])
                    ) or
                    (isset($response['source']['flow'])
                        and $response['source']['flow'] == 'redirect'
                        and isset($response['authorize_uri'])
                    )
                ) {
                    $isCreateInput = true;
                    if ($response['source']['flow'] == 'offline') {
                        $qrCodeUrl = $response['source']['scannable_code']['image']['download_uri'];
                        $isRequiredDecode = true;
                    } elseif ($response['source']['flow'] == 'redirect') {
                        $qrCodeUrl = $response['authorize_uri'];
                        $isRequiredDecode = false;
                    }

                } elseif (isset($response['code']) and isset($response['message'])) {
                    $errorMsg .= 'Error: ';
                    $errorMsg .= $response['code'].' '.$response['message'];
                }
                break;
            case 'fiuu':
                $isCreateInput = true;
                $rawHtml = '';
                if (is_string($rawResponse)) {
                    $rawHtml = $rawResponse;
                } elseif ($rawResponse instanceof \Illuminate\Http\Client\Response) {
                    $rawHtml = $rawResponse->body();
                } elseif (is_array($response) && isset($response['html'])) {
                    $rawHtml = base64_decode($response['html']);
                }

                if ($rawHtml) {
                    $decodedHtml = html_entity_decode($rawHtml);

                    $qrString = $this->extractFiuuQrString($decodedHtml);
                    if ($qrString) {
                        $qrCodeText = $qrString;
                    }

                    $qrImage = $this->extractFiuuQrImage($decodedHtml);
                    if ($qrImage) {
                        $qrCodeUrl = $qrImage;
                        $isRequiredDecode = true;
                    }
                }

                if (! $qrCodeText && ! $qrCodeUrl) {
                    $errorMsg .= 'Error: Unable to extract Fiuu QR response.';
                    $isCreateInput = false;
                }
                break;
        }
        // dd(file_get_contents($qrCodeUrl));
        // dd($qrCodeUrl, $isCreateInput, $isRequiredDecode, $isResizeImage);
        $img = false;
        if ($isRequiredDecode) {
            $qrCodeText = $this->readQrText($qrCodeUrl, $isResizeImage);
            if ($qrCodeText === '' && $isCreateInput) {
                // Never record or send a blank QR: tell the machine, so the customer taps again.
                $errorMsg .= 'Error: QR code could not be read, please try again';
                $isCreateInput = false;
            }
        } else {
            switch ($operatorPaymentGateway->paymentGateway->name) {
                case 'omise':
                    if (isset($params['type']) and $params['type'] == 'shopeepay') {
                        // use crawler programmatically crawl for qr code text
                        $browser = new HttpBrowser(HttpClient::create());
                        $crawler = $browser->request('GET', $qrCodeUrl);
                        $form = $crawler->filter('form')->form();
                        $firstResponse = $browser->submit($form);
                        $htmlString = $firstResponse->html();
                    } else {
                        $htmlString = Http::get($qrCodeUrl)->body();
                    }

                    $doc = new \DOMDocument;
                    $doc->loadHTML($htmlString);
                    $xpath = new \DOMXpath($doc);
                    $val = $xpath->query('//input[@type="hidden" and @id = "qr_string"]/@value');
                    $qrCodeText = isset($val[0]) ? $val[0]->nodeValue : null;
                    break;
            }
        }

        if ($isCreateInput) {
            $vendChannelCodesArr = [];
            if (isset($params['request']['slotIdList']) && ! empty($params['request']['slotIdList'])) {
                $vendChannelCodesArr = $params['request']['slotIdList'];
            } else {
                $vendChannelCodesArr[] = $params['request']['SId'];
            }

            if (! $vendChannelCodesArr) {
                $this->errorService->throwErrorWithMqtt('Vend channel(s) is not detect upon request QR code', $vend);
            }

            $vendChannelsCollections = collect();
            foreach ($vendChannelCodesArr as $vendChannelCode) {
                $vendChannel = $vend->vendChannels()->where('code', $vendChannelCode)->first();

                $vendChannel =
                    $vendChannel && $vendChannel->product ?
                    $vend
                        ->vendChannels()
                        ->with(['product:id,code,name'])
                        ->where('code', $vendChannelCode)->first(['id', 'code', 'product_id']) :
                    [
                        'code' => $vendChannelCode,
                        'product' => null,
                    ];
                $vendChannelsCollections->push($vendChannel);
            }

            $paymentGatewayLog = PaymentGatewayLog::create([
                'request' => $params['request'],
                'response' => $paymentRequest['response'],
                'order_id' => $params['metadata']['order_id'],
                'amount' => $params['amount'],
                'qr_url' => $qrCodeUrl,
                'qr_text' => $qrCodeText,
                'operator_payment_gateway_id' => $operatorPaymentGateway->id,
                'payment_gateway_id' => $operatorPaymentGateway->paymentGateway->id,
                'status' => PaymentGatewayLog::STATUS_PENDING,
                'txn_src' => $params['metadata']['txn_src'] ?? null,
                'vend_channels_json' => $vendChannelsCollections,
                'vend_code' => $vend->code,
                'vend_id' => $vend->id,
            ]);

            return [
                'paymentGatewayLog' => $paymentGatewayLog,
                'errorMsg' => null,
            ];
        }

        return [
            'paymentGatewayLog' => null,
            'errorMsg' => $errorMsg,
        ];
    }

    /**
     * @throws Exception
     */
    public function createPaymentRequest(Vend $vend, $params): array
    {
        $paymentGateway = $this->getOperatorPaymentGateway($vend);
        $operatorPaymentGateway = $paymentGateway->getOperatorPaymentGateway();
        if (! $params['amount']) {
            $this->errorService->throwErrorWithMqtt('Amount is not set', $vend);
        }
        if (! $params['metadata']) {
            $this->errorService->throwErrorWithMqtt('OrderID is not set within metadata', $vend);
        }

        $processedParams = [
            'amount' => $params['amount'] ? $params['amount'] : 0,
            'currency' => $params['currency'] ?? $operatorPaymentGateway->paymentGateway->country->currency_name,
            'expiry_seconds' => $params['expiry_seconds'] ?? 150,
            'metadata' => $params['metadata'] ?? [],
            'timezone' => $params['timezone'] ?? $operatorPaymentGateway->paymentGateway->country->timezone,
            'type' => (isset($params['type']) && $params['type']) ? $params['type'] :
                ($operatorPaymentGateway->paymentGateway->defaultPaymentMethod->exists() ?
                    $operatorPaymentGateway->paymentGateway->defaultPaymentMethod->type_name :
                    ''
                ),
            'return_uri' => $params['return_uri'] ?? env('APP_URL'),
            'callback_url' => $params['callback_url'] ?? null,
            'cancel_url' => $params['cancel_url'] ?? null,
            'country' => $params['country'] ?? optional($operatorPaymentGateway->paymentGateway->country)->code ?? 'MY',
            'channel_code' => $params['channel_code'] ?? null,
            'channel' => $params['channel'] ?? null,
            'customer_name' => $params['customer_name'] ?? 'Guest',
            'customer_email' => $params['customer_email'] ?? 'guest@example.com',
            'customer_phone' => $params['customer_phone'] ?? '0000000000',
            'description' => $params['description'] ?? 'Purchase',
        ];

        $response = $paymentGateway->createPayment($processedParams);

        if ($response->failed()) {
            $this->errorService->throwErrorWithMqtt('Payment creation failed: '.$response->body(), $vend);
        }

        $contentType = $response->header('Content-Type');
        $isJson = $contentType && str_contains(strtolower($contentType), 'application/json');

        $responsePayload = $isJson ? $response->json() : [
            'html' => base64_encode($response->body()),
        ];

        if (method_exists($paymentGateway, 'getLastRequestDetails')) {
            $responsePayload['request_details'] = $paymentGateway->getLastRequestDetails();
        }

        return [
            'response' => $responsePayload,
            'raw_response' => $response,
            'operatorPaymentGateway' => $operatorPaymentGateway,
        ];
    }

    /**
     * @throws Exception
     */
    private function getOperatorPaymentGateway(Vend $vend)
    {
        if ($this->getOperator($vend)) {
            $operator = $this->getOperator($vend);
            if ($operator->operatorPaymentGateways()->exists()) {
                $operatorPaymentGateway = $operator->operatorPaymentGateways()->where('type', $this->getAppEnvironment())->first();

                if ($operatorPaymentGateway) {
                    switch ($operatorPaymentGateway->paymentGateway->name) {
                        case 'midtrans':
                            $obj = new Midtrans($operatorPaymentGateway->key1);
                            $obj->setOperatorPaymentGateway($operatorPaymentGateway);

                            return $obj;
                            break;
                        case 'omise':
                            $obj = new Omise($operatorPaymentGateway->key1, $operatorPaymentGateway->key2);
                            $obj->setOperatorPaymentGateway($operatorPaymentGateway);

                            return $obj;
                            break;
                        case 'fiuu':
                            $obj = new Fiuu(
                                $operatorPaymentGateway->key1,
                                $operatorPaymentGateway->key2,
                                $operatorPaymentGateway->key3,
                                $operatorPaymentGateway->type === OperatorPaymentGateway::TYPE_SANDBOX
                            );
                            $obj->setOperatorPaymentGateway($operatorPaymentGateway);

                            return $obj;
                            break;
                    }
                    $this->errorService->throwErrorWithMqtt('Invalid payment gateway specified.', $vend);
                } else {
                    $this->errorService->throwErrorWithMqtt('Api key environment not match with current environment', $vend);
                }

            } else {
                $this->errorService->throwErrorWithMqtt('Payment Gateway is not set within operator', $vend);
            }
        } else {
            $this->errorService->throwErrorWithMqtt('Vend is not set to any operator', $vend);
        }
    }

    private function getOperator(Vend $vend)
    {
        return $vend->operator;
    }

    private function getAppEnvironment()
    {
        $envName = 'sandbox';

        if (config('app.env') === 'production') {
            $envName = 'production';
        }

        return $envName;
    }

    private function extractFiuuQrString(string $html): ?string
    {
        $patterns = [
            '/name="qr_string"\s+value="([^"]+)"/i',
            '/id="qr_string"\s+value="([^"]+)"/i',
            '/name="qrString"\s+value="([^"]+)"/i',
            '/id="qrString"\s+value="([^"]+)"/i',
            '/"qr_string"\s*:\s*"([^"]+)"/i',
            '/"qrString"\s*:\s*"([^"]+)"/i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $html, $matches)) {
                return html_entity_decode($matches[1]);
            }
        }

        return null;
    }

    private function extractFiuuQrImage(string $html): ?string
    {
        $patterns = [
            '/<img[^>]+src="(data:image\/[^"]+)"[^>]*>/i',
            '/<img[^>]+src="(https?:[^\"]+)"[^>]*>/i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $html, $matches)) {
                return $matches[1];
            }
        }

        return null;
    }

    /**
     * Reads the payment text out of the gateway's QR image (downloaded, or unpacked from a data
     * URI). Two attempts: a truncated download or a TLS hiccup is usually fine the second time.
     * Every failure is caught — the decoder can throw Errors (DivisionByZeroError on a corrupt
     * image, 2470 on 2026-10-10) that `catch (\Exception)` let escape and abort the REQQR.
     *
     * @return string the QR payload, or '' when it could not be read
     */
    private function readQrText(string $qrCodeUrl, bool $resize): string
    {
        for ($attempt = 1; $attempt <= 2; $attempt++) {
            $tempFile = tempnam(sys_get_temp_dir(), 'qr_');
            try {
                if ($this->isDataUri($qrCodeUrl)) {
                    $content = $this->decodeDataUri($qrCodeUrl);
                } elseif (! $resize || filter_var($qrCodeUrl, FILTER_VALIDATE_URL) !== false) {
                    $content = $this->downloadQrImage($qrCodeUrl);
                } else {
                    $content = $qrCodeUrl;
                }

                if ($resize) {
                    $image = Image::read($content);
                    $image->resize(150, 150);
                    $content = (string) $image->toPng();
                }

                file_put_contents($tempFile, $content);
                $text = (new QrReader($tempFile))->text([
                    'POSSIBLE_FORMATS' => 'QR_CODE',
                    'TRY_HARDER' => true,
                ]);
                if (is_string($text) && $text !== '') {
                    return $text;
                }
                Log::warning('QR image decoded to no text', ['attempt' => $attempt]);
            } catch (\Throwable $e) {
                Log::warning('QR image could not be read', [
                    'attempt' => $attempt,
                    'exception' => $e::class,
                    'error' => $e->getMessage(),
                ]);
            } finally {
                @unlink($tempFile);
            }
        }

        return '';
    }

    /**
     * Times out after 5 s: this runs inside the MQTT subscriber, where PHP's default 60 s socket
     * timeout would hold up every machine's frames behind one slow download.
     */
    private function downloadQrImage(string $url): string
    {
        $body = file_get_contents($url, false, stream_context_create(['http' => ['timeout' => 5]]));
        if ($body === false || $body === '') {
            throw new \RuntimeException('QR image download returned nothing');
        }

        return $body;
    }

    private function isDataUri(string $value): bool
    {
        return str_starts_with($value, 'data:image');
    }

    private function decodeDataUri(string $value): string
    {
        if (! str_contains($value, ',')) {
            return $value;
        }

        [, $data] = explode(',', $value, 2);
        $decoded = base64_decode($data);

        return $decoded !== false ? $decoded : '';
    }
}
