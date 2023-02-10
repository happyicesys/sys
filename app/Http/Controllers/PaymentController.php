<?php

namespace App\Http\Controllers;

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
    VendData::create([
      'value' => $request->all()
    ]);
  }
}
