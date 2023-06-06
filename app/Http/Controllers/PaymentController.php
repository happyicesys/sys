<?php

namespace App\Http\Controllers;

use App\Models\Country;
use App\Models\PaymentGateways\Midtrans;
use App\Models\PaymentGateways\Omise;
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

  public function __construct(
    MqttService $mqttService,
    PaymentGatewayService $paymentGatewayService, VendDataService $vendDataService)
  {
    $this->mqttService = $mqttService;
    $this->paymentGatewayService = $paymentGatewayService;
    $this->vendDataService = $vendDataService;
  }

  public function createPaymentGatewayLog(Request $request, $company)
  {
    if(!$company) {
      throw new \Exception('Payment gateway not parsed in url');
    }

    $input = $request->all();
    $status = null;
    $orderId = null;
    $refId = null;
    $isSkipFindPrevious = false;

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
            case 'refund':
            case 'partial_refund':
              $status = PaymentGatewayLog::STATUS_REFUND;
              break;
          }
        }
        $orderId = $input['order_id'];
        $refId = $input['transaction_id'];
      break;

      case 'omise':
        switch($input['data']['object']) {
          case 'charge':
            $isSkipFindPrevious = true;
            break;
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
            break;
          case 'refund':
            $status = PaymentGatewayLog::STATUS_REFUND;
            break;
          default:
            throw new \Exception('Payment gateway is not found');
        }
        $orderId = $input['data']['metadata']['order_id'];
        $refId = $input['data']['id'];
      break;
    }

    $paymentGatewayLogSearchStatus = PaymentGatewayLog::STATUS_PENDING;
    switch($status) {
      case PaymentGatewayLog::STATUS_PENDING:
      case PaymentGatewayLog::STATUS_APPROVE:
      case PaymentGatewayLog::STATUS_DECLINE:
        $paymentGatewayLogSearchStatus = PaymentGatewayLog::STATUS_PENDING;
        break;
      case PaymentGatewayLog::STATUS_REFUND:
        $paymentGatewayLogSearchStatus = PaymentGatewayLog::STATUS_APPROVE;
    }
    $paymentGatewayLog = PaymentGatewayLog::where('order_id', $orderId)->where('status', $paymentGatewayLogSearchStatus)->first();
    // dd($paymentGatewayLog, $orderId, $paymentGatewayLogSearchStatus);
    if(!$paymentGatewayLog and !$isSkipFindPrevious) {
      throw new \Exception('This payment is not trigger before');
    }

    $updatedPaymentGatewayLog = PaymentGatewayLog::updateOrCreate([
      'order_id' => $orderId,
    ], [
      'response' => $input,
      'history_json' => isset($paymentGatewayLog->history_json) ? array_merge($paymentGatewayLog->history_json, $input) : $input,
      'ref_id' => $refId,
      'status' => $status,
    ]);

    if($updatedPaymentGatewayLog and $status === PaymentGatewayLog::STATUS_APPROVE) {
      $this->processPayment($updatedPaymentGatewayLog);
    }
  }

  private function processPayment(PaymentGatewayLog $paymentGatewayLog)
  {
    // $vend = Vend::where('code', ltrim(substr($paymentGatewayLog->order_id, -5), '0'))->first();
    if($paymentGatewayLog->status === PaymentGatewayLog::STATUS_APPROVE and $paymentGatewayLog->paymentGateway()->exists()) {

      $paymentMethod = null;
      switch($paymentGatewayLog->paymentGateway->name){
        case 'midtrans':
            $paymentMethod = array_search($paymentGatewayLog->response['issuer'], Midtrans::PAYMENT_METHOD_MAPPING);

            // switch($paymentGatewayLog->response['issuer']) {
            //   case 'gopay':
            //     $paymentMethod = Midtrans::PAYMENT_METHOD_GOPAY;
            //     break;
            //   case 'airpay shopee':
            //     $paymentMethod = Midtrans::PAYMENT_METHOD_AIRPAY_SHOPEE;
            //     break;
            //   case 'dana':
            //     $paymentMethod = Midtrans::PAYMENT_METHOD_DANA;
            //     break;
            //   case 'ovo':
            //     $paymentMethod = Midtrans::PAYMENT_METHOD_OVO;
            //     break;
            //   case 'tcash':
            //     $paymentMethod = Midtrans::PAYMENT_METHOD_TCASH;
            //     break;
            // }
          break;
        case 'omise':
          $paymentMethod = array_search($paymentGatewayLog->response['data']['source']['type'], Omise::PAYMENT_METHOD_MAPPING);

          // switch($paymentGatewayLog->response['data']['source']['type']) {
          //   case 'paynow':
          //     $paymentMethod = Omise::PAYMENT_METHOD_PAYNOW;
          //     break;
          //   case 'duitnow_qr':
          //     $paymentMethod = Omise::PAYMENT_METHOD_DUITNOW;
          //     break;
          //   case 'promptpay':
          //     $paymentMethod = Omise::PAYMENT_METHOD_PROMPTPAY;
          //     break;
          // }
          break;
      }

      $result = $this->vendDataService->getPurchaseRequest([
        'orderId' => $paymentGatewayLog->order_id,
        'amount' => $paymentGatewayLog->request['PRICE'],
        'vendCode' => $paymentGatewayLog->vend_code,
        'productCode' =>  $paymentGatewayLog->vendChannel && $paymentGatewayLog->vendChannel->product()->exists() ? $paymentGatewayLog->vendChannel->product->code : null,
        'productName' => $paymentGatewayLog->vendChannel && $paymentGatewayLog->vendChannel->product()->exists() ? $paymentGatewayLog->vendChannel->product->name : null,
        'channelCode' =>  $paymentGatewayLog->vendChannel ? $paymentGatewayLog->vend_channel_code : null,
        'paymentMethod' => $paymentMethod,
      ]);

      $fid = $paymentGatewayLog->id;
      $content = base64_encode(json_encode($result));
      $contentLength = strlen($content);
      $key = '123456789110138A';
      $md5 = md5($fid.','.$contentLength.','.$content.$key);

      $this->mqttService->publish('CM'.$paymentGatewayLog->vend_code, $fid.','.$contentLength.','.$content.','.$md5);

    }else {
      $this->mqttService->publish('CM'.$paymentGatewayLog->vend_code, 'Error: QR code expired or payment gateway invalid');
    }
  }
}
