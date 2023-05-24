<?php

namespace App\Http\Controllers;

use App\Models\PaymentGateway\Midtrans;
use App\Models\PaymentGateway\Omise;
use App\Models\PaymentGateway;
use App\Models\PaymentGatewayLog;
use App\Models\Vend;
use App\Models\VendData;
use App\Services\MqttService;
use App\Services\PaymentGatewayService;
use App\Services\VendDataService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PaymentController extends Controller
{

  protected $mqttService;
  protected $paymentGatewayService;
  protected $vendDataService;

  public function __construct(MqttService $mqttService, PaymentGatewayService $paymentGatewayService, VendDataService $vendDataService)
  {
    $this->mqttService = $mqttService;
    $this->paymentGatewayService = $paymentGatewayService;
    $this->vendDataService = $vendDataService;
  }

  public function createPaymentResult(Request $request, $company = 'midtrans')
  {
    $input = $request->all();
    $status = null;
    $orderId = null;
    $paymentGatewayId = PaymentGateway::where('name', $company)->first() ? PaymentGateway::where('name', $company)->first()->id : null;

    if($company) {
      switch($company) {
        case 'midtrans':
          if(isset($input['transaction_status'])) {
            switch($input['transaction_status']) {
              case 'pending':
                $status = PaymentGatewayLog::STATUS_PENDING;
                break;
              case 'capture':
              case 'settlement':
                $status = PaymentGatewayLog::STATUS_APPROVE;
                break;
              case 'cancel':
              case 'deny':
              case 'expire':
                $status = PaymentGatewayLog::STATUS_DECLINE;
                break;
            }
          }
          $orderId = $input['order_id'];
          break;

        case 'omise':
          $objectName = $input['data']['object'];
          switch($objectName) {
            case 'charge':
            case 'source':
              if(isset($input['data']['status'])) {
                switch($input['data']['status']) {
                  case 'pending':
                    $status = PaymentGatewayLog::STATUS_PENDING;
                    break;
                  case 'successful':
                    $status = PaymentGatewayLog::STATUS_APPROVE;
                    break;
                  case 'failed':
                  case 'expired':
                    $status = PaymentGatewayLog::STATUS_DECLINE;
                    break;
                }
              }
              $orderId = $input['data']['metadata']['order_id'];
              break;
            case 'refund':
              $status = PaymentGatewayLog::STATUS_REFUND;
              $orderId = $input['data']['metadata']['order_id'];
              break;
          }
          break;
      }

      if($status == PaymentGatewayLog::STATUS_REFUND) {
        $pendingLog = PaymentGatewayLog::where('order_id', $orderId)->where('status', PaymentGatewayLog::STATUS_APPROVE)->first();
      }else if ($status == PaymentGatewayLog::STATUS_PENDING) {
        $pendingLog = PaymentGatewayLog::where('order_id', $orderId)->where('status', PaymentGatewayLog::STATUS_PENDING)->first();
      }
      Log::debug('Status: '.$status);
      Log::debug('OrderId: '.$orderId);
      Log::debug('param:'.$pendingLog);
      Log::debug('is exists order:'.PaymentGatewayLog::where('order_id', $orderId)->first());

      $historyArr = [];
      if($input and isset($pendingLog) and $pendingLog->history_json) {
        $historyArr = array_merge($pendingLog->history_json, $input);
      }else {
        $historyArr = $input;
      }

      if($pendingLog) {
        $paymentGatewayLog = $pendingLog->updateOrCreate([
          'order_id' => $orderId,
        ],[
          'request' => $pendingLog->request,
          'response' => $input,
          'history_json' => $historyArr,
          'status' => $status,
          'amount' => $pendingLog->request['PRICE'] * 100,
          'payment_gateway_id' => $paymentGatewayId,
        ]);

        if($paymentGatewayLog and $paymentGatewayLog->status === PaymentGatewayLog::STATUS_APPROVE) {
          $this->processPayment($paymentGatewayLog);
        }
      }else {
        throw new \Exception('Error: This QR isnt requested');
      }

    }else {
      throw new \Exception('Error: Payment Gateway not found');
    }


  }

  private function processPayment(PaymentGatewayLog $paymentGatewayLog)
  {
    $vend = Vend::where('code', ltrim(substr($paymentGatewayLog->order_id, -5), '0'))->first();
    if($paymentGatewayLog->status === PaymentGatewayLog::STATUS_APPROVE and $paymentGatewayLog->paymentGateway()->exists() and $vend) {

      $paymentMethod = null;
      switch($paymentGatewayLog->paymentGateway->name){
        case 'midtrans':
            switch($paymentGatewayLog->response['issuer']) {
              case 'gopay':
                $paymentMethod = Midtrans::PAYMENT_METHOD_GOPAY;
                break;
              case 'airpay shopee':
                $paymentMethod = Midtrans::PAYMENT_METHOD_AIRPAY_SHOPEE;
                break;
              case 'dana':
                $paymentMethod = Midtrans::PAYMENT_METHOD_DANA;
                break;
              case 'ovo':
                $paymentMethod = Midtrans::PAYMENT_METHOD_OVO;
                break;
              case 'tcash':
                $paymentMethod = Midtrans::PAYMENT_METHOD_TCASH;
                break;
            }
          break;
        case 'omise':
          // dd($paymentGatewayLog->toArray());
          switch($paymentGatewayLog->response['data']['source']['type']) {
            case 'paynow':
              $paymentMethod = Omise::PAYMENT_METHOD_PAYNOW;
              break;
          }
          break;
      }
      $vendChannel = $vend->vendChannels()->where('code', $paymentGatewayLog->request['SId'])->first();
      $result = $this->vendDataService->getPurchaseRequest([
        'orderId' => $paymentGatewayLog->order_id,
        'amount' => $paymentGatewayLog->request['PRICE'],
        'vendCode' => $vend->code,
        'productCode' =>  $vendChannel && $vendChannel->product()->exists() ? $vendChannel->product->code : null,
        'productName' => $vendChannel && $vendChannel->product()->exists() ? $vendChannel->product->name : null,
        'channelCode' =>  $vendChannel ? $vendChannel->code : null,
        'paymentMethod' => $paymentMethod,
      ]);

      $fid = $paymentGatewayLog->id;
      $content = base64_encode(json_encode($result));
      $contentLength = strlen($content);
      $key = '123456789110138A';
      $md5 = md5($fid.','.$contentLength.','.$content.$key);

      $this->mqttService->publish('CM'.$vend->code, $fid.','.$contentLength.','.$content.','.$md5);

    }else {
      $this->mqttService->publish('CM'.ltrim(substr($paymentGatewayLog->order_id, -5), '0'), 'Error: QR code expired or payment gateway invalid');
    }
  }
}
