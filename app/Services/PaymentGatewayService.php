<?php

namespace App\Services;
use App\Models\PaymentGateway;
use App\Models\PaymentGatewayLog;
use App\Models\PaymentGateway\Midtrans;
use App\Models\Vend;
use Carbon\Carbon;

class PaymentGatewayService
{
  public function create($type = '', Vend $vend, $params)
  {
    $paymentGateway = PaymentGateway::where('name', $type)->first();
    $vendOperatorPaymentGateway = $this->getOperatorPaymentGateway($vend);
    if($vendOperatorPaymentGateway and $vendOperatorPaymentGateway->paymentGateway and $paymentGateway and $paymentGateway->id === $vendOperatorPaymentGateway->paymentGateway->id) {
      $response = '';
      switch($paymentGateway->name) {
        case 'midtrans':
          $newObj = new Midtrans($vendOperatorPaymentGateway->key1, 'QRIS');
          $response = $newObj->executeRequest($params);
          break;
      }
      return $response->collect();
    }
  }

  public function getOperatorPaymentGateway(Vend $vend)
  {
    if($vend->operators()->exists()) {
      $operator = $vend->operators()->first();

      if($operator->operatorPaymentGateways()->exists()) {
        $operatorPaymentGateway = $operator->operatorPaymentGateways()->where('type', $this->getAppEnvironment())->first();

        return $operatorPaymentGateway;
      }
    }
  }

  public function processPaymentGatewayResponse(PaymentGatewayLog $paymentGatewayLog)
  {

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