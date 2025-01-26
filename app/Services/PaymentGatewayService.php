<?php

namespace App\Services;
use App\Models\OperatorPaymentGateway;
use App\Models\PaymentGateway;
use App\Models\PaymentGatewayLog;
use App\Models\PaymentGateways\Omise;
use App\Models\PaymentGateways\Midtrans;
use App\Models\Vend;
use App\Services\ErrorService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Zxing\QrReader;
use Symfony\Component\BrowserKit\HttpBrowser;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpClient\HttpClient;
use Imagick;
use ImagickPixel;
use Intervention\Image\Laravel\Facades\Image;

class PaymentGatewayService
{
    protected $errorService;

    public function __construct()
    {
        $this->errorService = new ErrorService();
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
        $operatorPaymentGateway = $paymentRequest['operatorPaymentGateway'];

        switch ($operatorPaymentGateway->paymentGateway->name) {
            case 'midtrans':
                if (isset($response['actions']) and isset($response['actions'][0]['url'])) {
                    $isCreateInput = true;
                    $qrCodeUrl = $response['actions'][0]['url'];
                } else if (isset($response['validation_messages']) or isset($response['status_message'])) {
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
                    } else if ($response['source']['flow'] == 'redirect') {
                        $qrCodeUrl = $response['authorize_uri'];
                        $isRequiredDecode = false;
                    }

                } else if (isset($response['code']) and isset($response['message'])) {
                    $errorMsg .= 'Error: ';
                    $errorMsg .= $response['code'] . ' ' . $response['message'];
                }
                break;
        }
        // dd(file_get_contents($qrCodeUrl));
        // dd($qrCodeUrl, $isCreateInput, $isRequiredDecode, $isResizeImage);
        $img = false;
        if($isRequiredDecode) {
          if($isResizeImage) {

            if(filter_var($qrCodeUrl, FILTER_VALIDATE_URL) !== false) {
                $imagick = new Imagick();
                $imagick->readImageBlob(file_get_contents($qrCodeUrl));
            }else {
                $imagick = new Imagick($qrCodeUrl);
            }

            $imagick->resizeImage(150, 150, Imagick::FILTER_LANCZOS, 1);
            $img = Storage::put('/qr-code/'.$params['metadata']['order_id'].'.png', $imagick->getImageBlob(), 'public');


            // $imagick = new Imagick();
            // $imagick->setBackgroundColor(new ImagickPixel('transparent'));
            // $imagick->readImageBlob(file_get_contents($qrCodeUrl));
            // $imagick->setImageFormat('png24');
            // $img = Storage::put('/qr-code/'.$params['metadata']['order_id'].'.png', $imagick->getImageBlob(), 'public');
// dd($imagick->getImageBlob());
          }else {
            if(isset($params['type']) and $params['type'] == 'alipayplus_mpm') {
              $imagick = new Imagick();
              $imagick->setBackgroundColor(new ImagickPixel('transparent'));
              $imagick->readImageBlob(file_get_contents($qrCodeUrl));
              $imagick->setImageFormat('png24');
              $img = Storage::put('/qr-code/'.$params['metadata']['order_id'].'.png', $imagick->getImageBlob(), 'public');
            }else {
              $img = Storage::put('/qr-code/'.$params['metadata']['order_id'].'.png', file_get_contents($qrCodeUrl), 'public');
            }
          }
          $url = Storage::url('/qr-code/'.$params['metadata']['order_id'].'.png');

          $qrCodeReader = new QrReader($url);
          $qrCodeText = $qrCodeReader->text([
              'POSSIBLE_FORMATS' => 'QR_CODE',
              'TRY_HARDER' => true,
          ]);
          Storage::disk('public')->delete('/qr-code/'.$params['metadata']['order_id'].'.png');
        }else {
            switch($operatorPaymentGateway->paymentGateway->name) {
                case 'omise':
                  if(isset($params['type']) and $params['type'] == 'shopeepay') {
                    // use crawler programmatically crawl for qr code text
                    $browser = new HttpBrowser(HttpClient::create());
                    $crawler = $browser->request('GET', $qrCodeUrl);
                    $form = $crawler->filter('form')->form();
                    $firstResponse = $browser->submit($form);
                    $htmlString = $firstResponse->html();
                  }else {
                    $htmlString = Http::get($qrCodeUrl)->body();
                  }

                  $doc = new \DOMDocument;
                  $doc->loadHTML($htmlString);
                  $xpath = new \DOMXpath($doc);
                  $val= $xpath->query('//input[@type="hidden" and @id = "qr_string"]/@value');
                  $qrCodeText = isset($val[0]) ? $val[0]->nodeValue : null;
                  break;
            }
        }

        if ($isCreateInput) {
            $vendChannelCodesArr = [];
            if (isset($params['request']['slotIdList']) && !empty($params['request']['slotIdList'])) {
                $vendChannelCodesArr = $params['request']['slotIdList'];
            } else {
                $vendChannelCodesArr[] = $params['request']['SId'];
            }

            if (!$vendChannelCodesArr) {
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
                'response' => $response,
                'history_json' => $response,
                'order_id' => $params['metadata']['order_id'],
                'amount' => $params['amount'],
                'qr_url' => $qrCodeUrl,
                'qr_text' => $qrCodeText,
                'operator_payment_gateway_id' => $operatorPaymentGateway->id,
                'payment_gateway_id' => $operatorPaymentGateway->paymentGateway->id,
                'status' => PaymentGatewayLog::STATUS_PENDING,
                'vend_channel_code' => $params['request']['SId'] ?? null,
                'vend_channel_id' => isset($params['request']['SId']) &&
                $vend->vendChannels()->where('code', $params['request']['SId'])->first() ?
                    $vend->vendChannels()->where('code', $params['request']['SId'])->first()->id :
                    null,
                'vend_channels_json' => $vendChannelsCollections,
                'vend_code' => $vend->code,
                'vend_id' => $vend->id,
            ]);

            return [
                'paymentGatewayLog' => $paymentGatewayLog,
                'errorMsg' => null
            ];
        }

        return [
            'paymentGatewayLog' => null,
            'errorMsg' => $errorMsg
        ];
    }

    /**
     * @throws Exception
     */
    public function createPaymentRequest(Vend $vend, $params): array
    {
        $paymentGateway = $this->getOperatorPaymentGateway($vend);
        $operatorPaymentGateway = $paymentGateway->getOperatorPaymentGateway();
        if(!$params['amount']) {
            $this->errorService->throwErrorWithMqtt('Amount is not set', $vend);
        }
        if(!$params['metadata']) {
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
        ];

        $response = $paymentGateway->createPayment($processedParams);

        if($response->failed()) {
            $this->errorService->throwErrorWithMqtt('Payment creation failed: ' . $response->body(), $vend);
        }

        return [
            'response' => $response->json(),
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
                        case 'omise' :
                            $obj = new Omise($operatorPaymentGateway->key1, $operatorPaymentGateway->key2);
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
}
