<?php

namespace App\Http\Controllers;

use App\Models\PaymentGatewayLog;
use App\Models\VendData;
use App\Services\PaymentGatewayService;
use Illuminate\Http\Request;

class PaymentController extends Controller
{

  protected $paymentGatewayService;

  public function __construct(PaymentGatewayService $paymentGatewayService)
  {
    $this->paymentGatewayService = $paymentGatewayService;
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

    $paymentGatewayLog = PaymentGatewayLog::create([
      'response' => $input,
      'order_id' => $input['order_id'],
      'status' => $status,
      'amount' => $input['gross_amount'],
    ]);

    if($paymentGatewayLog) {
      $this->processPayment($paymentGatewayLog);
    }
  }

  private function processPayment(PaymentGatewayLog $paymentGatewayLog)
  {

    // $this->paymentGatewayService()
  }
}
