<?php

namespace App\Services;
use App\Models\OperatorPaymentGateway;
use App\Models\PaymentGateway;
use App\Models\PaymentGatewayLog;
use App\Models\PaymentGateways\Omise;
use App\Models\PaymentGateways\Midtrans;
use App\Models\Vend;
use Carbon\Carbon;
use Goutte\Client;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Image;
use Zxing\QrReader;
use Symfony\Component\DomCrawler\Crawler;

class PaymentGatewayService
{
  public function createPaymentRequest(Vend $vend, $params)
  {
    $paymentGateway = $this->getOperatorPaymentGateway($vend);
    $operatorPaymentGateway = $paymentGateway->getOperatorPaymentGateway();
    $processedParams = [
      'amount' => isset($params['amount']) ? $params['amount'] : throw new \Exception('Amount is not set'),
      'currency' => isset($params['currency']) ? $params['currency'] : $operatorPaymentGateway->paymentGateway->country->currency_name,
      'expiry_seconds' => isset($params['expiry_seconds']) ? $params['expiry_seconds'] : 150,
      'metadata' => isset($params['metadata'])  ? $params['metadata'] : throw new \Exception('OrderID is not set within metadata'),
      'timezone' => isset($params['timezone']) ? $params['timezone'] : $operatorPaymentGateway->paymentGateway->country->timezone,
      'type' => (isset($params['type']) && $params['type']) ? $params['type'] : ($operatorPaymentGateway->paymentGateway->defaultPaymentMethod->exists() ? $operatorPaymentGateway->paymentGateway->defaultPaymentMethod->type_name : throw new \Exception('Payment Method is not set')),
      'return_uri' => isset($params['return_uri']) ? $params['return_uri'] : env('APP_URL'),
    ];
    // dd($params, isset($params['type']), $params['type'], $processedParams);
    $response = $paymentGateway->createPayment($processedParams);

    return [
      'response' => $response,
      'operatorPaymentGateway' => $operatorPaymentGateway,
    ];
  }

  public function createPaymentQrText(Vend $vend, $params)
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

    switch($operatorPaymentGateway->paymentGateway->name) {
        case 'midtrans':
            if(isset($response['actions']) and isset($response['actions'][0]['url'])) {
                $isCreateInput = true;
                $qrCodeUrl = $response['actions'][0]['url'];
            }else if(isset($response['validation_messages']) or isset($response['status_message'])) {
                $errorMsg .= 'Error: ';
                $errorMsg .= isset($response['validation_messages']) ? $response['validation_messages'][0] : $response['status_message'];
            }
            $isResizeImage = true;
            $isRequiredDecode = true;
            break;
        case 'omise':
            if((isset($response['source']['flow']) and $response['source']['flow'] == 'offline' and isset($response['source']['scannable_code']['image']['download_uri'])) or (isset($response['source']['flow']) and $response['source']['flow'] == 'redirect' and isset($response['authorize_uri']))) {
                $isCreateInput = true;
                if($response['source']['flow'] == 'offline') {
                    $qrCodeUrl = $response['source']['scannable_code']['image']['download_uri'];
                    $isRequiredDecode = true;
                }else if($response['source']['flow'] == 'redirect') {
                    $qrCodeUrl = $response['authorize_uri'];
                    $isRequiredDecode = false;
                }

            }else if(isset($response['code']) and isset($response['message'])) {
                $errorMsg .= 'Error: ';
                $errorMsg .= $response['code'].' '.$response['message'];
            }
            break;
    }

    $img = false;
    if($isRequiredDecode) {
        if($isResizeImage) {
            $image = Image::make($qrCodeUrl)->resize(150, 150);
            $img = Storage::put('/qr-code/'.$params['metadata']['order_id'].'.png', $image->stream()->__toString(), 'public');
        }else {
            $img = Storage::put('/qr-code/'.$params['metadata']['order_id'].'.png', file_get_contents($qrCodeUrl), 'public');
        }
        $url = Storage::url('/qr-code/'.$params['metadata']['order_id'].'.png');

        $qrCodeReader = new QrReader($url);
        $qrCodeText = $qrCodeReader->text([
            'POSSIBLE_FORMATS' => 'QR_CODE',
        ]);
        Storage::disk('public')->delete('/qr-code/'.$params['metadata']['order_id'].'.png');
    }else {
        switch($operatorPaymentGateway->paymentGateway->name) {
            case 'omise':
                $htmlString = Http::get($qrCodeUrl)->body();

                // // Create a DOMCrawler instance from the HTML content
                // $crawler = new Crawler($htmlString);

                // // Find the form element you want to inspect (adjust the selector as needed)
                // $form = $crawler->filter('form')->first();

                // $action = $form->attr('action');

                // // Get the form name attribute
                // $formName = $form->attr('name');

                // // Find hidden input elements within the form
                // $hiddenInputs = $form->filter('input[type="hidden"]');
                //                 // Extract the hidden input values
                // $hiddenInputValues = [];
                // $hiddenInputs->each(function (Crawler $input) use (&$hiddenInputValues) {
                //     $name = $input->attr('name');
                //     $value = $input->attr('value');
                //     $hiddenInputValues[$name] = $value;
                // });

                // // dd($htmlString,$formName, $hiddenInputValues);
                // $response = Http::post($action, $hiddenInputValues)->body();
                // dd($response);

                // $crawler->filterXPath('//script')->each(function ($node) {
                //   $node->getNode(0)->parentNode->removeChild($node->getNode(0));
                // });
                // $html = $crawler->html();
                // dd($html);

                $doc = new \DOMDocument;
                $doc->loadHTML($htmlString);
                $xpath = new \DOMXpath($doc);
                // $val= $xpath->query('//input[@type="hidden" and @name = "qr_data"]/@value');
                $val= $xpath->query('//input[@type="hidden" and @id = "qr_string"]/@value');
                // dd($xpath);
                $qrCodeText = isset($val[0]) ? $val[0]->nodeValue : null;
                break;
        }
    }

    if($isCreateInput) {
      $vendChannel = $vend->vendChannels()->where('code', $params['request']['SId'])->first();
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
          'vend_channel_code' => $params['request']['SId'],
          'vend_channel_id' => $vendChannel ? $vendChannel->id : null,
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

  private function getOperatorPaymentGateway(Vend $vend)
  {
    if($this->getOperator($vend)) {
      $operator = $this->getOperator($vend);
      // dd($operator->toArray());
      if($operator->operatorPaymentGateways()->exists()) {
        $operatorPaymentGateway = $operator->operatorPaymentGateways()->where('type', $this->getAppEnvironment())->first();
        // dd($operatorPaymentGateway->toArray());
        if($operatorPaymentGateway) {
          switch($operatorPaymentGateway->paymentGateway->name) {
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
          throw new InvalidArgumentException('Invalid payment gateway specified.');
        }else {
          throw new \Exception('Api key environment not match with current environment');
        }

      }else {
        throw new \Exception('Payment Gateway is not set within operator');
      }
    }else {
      throw new \Exception('Vend is not set to any operator');
    }
  }

  private function getOperator(Vend $vend)
  {
    if($vend->operators()->exists()) {
      return $vend->operators()->first();
    }
  }

  private function getAppEnvironment()
  {
    $envName = 'sandbox';

    if(config('app.env') === 'production') {
      $envName = 'production';
    }else {
      $envName = 'sandbox';
    }

    return $envName;
  }
}