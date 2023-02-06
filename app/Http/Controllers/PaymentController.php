<?php

namespace App\Http\Controllers;

use App\Services\PaymentGatewayService;
use Illuminate\Http\Request;

class PaymentController extends Controller
{

  protected $paymentGatewayService;

  public function __construct(PaymentGatewayService $paymentGatewayService)
  {
    $this->paymentGatewayService = $paymentGatewayService;
  }


}
