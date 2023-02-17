<?php

namespace App\Http\Controllers;

use App\Models\PaymentGateway;
use App\Models\PaymentGatewayLog;
use App\Models\Vend;
use App\Models\VendData;
use App\Services\MqttService;
use App\Services\PaymentGatewayService;
use App\Services\VendDataService;
use Illuminate\Http\Request;

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

  public function createPaymentResult(Request $request)
  {
    $input = $request->all();
    $status = null;
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

    $pendingLog = PaymentGatewayLog::where('order_id', $input['order_id'])->where('status', PaymentGatewayLog::STATUS_PENDING)->first();
    if($pendingLog) {
      $paymentGatewayLog = PaymentGatewayLog::create([
        'request' => $pendingLog->request,
        'response' => $input,
        'order_id' => $input['order_id'],
        'status' => $status,
        'amount' => $input['gross_amount'],
        'payment_gateway_id' => PaymentGateway::where('name', 'midtrans')->first() ? PaymentGateway::where('name', 'midtrans')->first()->id : null,
        // hardcode midtrans
      ]);

      if($paymentGatewayLog) {
        $this->processPayment($paymentGatewayLog);
      }
    }else {
      throw new \Exception('Error: This QR isnt requested');
    }
  }

  private function processPayment(PaymentGatewayLog $paymentGatewayLog)
  {
    if($paymentGatewayLog->status === PaymentGatewayLog::STATUS_APPROVE and $paymentGatewayLog->paymentGateway()->exists()) {
      switch($paymentGatewayLog->paymentGateway->name){
        case 'midtrans':
          $vend = Vend::where('code', ltrim(substr($paymentGatewayLog->response['order_id'], -5)))->first();
          if($vend) {
            $vendChannel = $vend->vendChannels()->where('code', $paymentGatewayLog->request['SId'])->first();
            $result = $this->vendDataService->getPurchaseRequest([
              'orderId' => $paymentGatewayLog->order_id,
              'amount' => $paymentGatewayLog->response['gross_amount'],
              'vendCode' => $vend->code,
              'productCode' =>  $vendChannel->product()->exists() ? $vendChannel->product->code : null,
              'productName' => $vendChannel->product()->exists() ? $vendChannel->product->name : null,
              'channelCode' =>  $vendChannel->code,
            ]);

            $fid = $paymentGatewayLog->id;
            $content = base64_encode(json_encode($result));
            $contentLength = strlen($content);
            $key = '123456789110138A';
            $md5 = md5($fid.','.$contentLength.','.$content.$key);

            $this->mqttService->publish('CM'.$vend->code, $fid.','.$contentLength.','.$content.','.$md5);
          }
          break;
      }
    }
    // $this->paymentGatewayService()
  }
}
