<?php

namespace App\Services;
use App\Models\PaymentGateway;
use App\Models\PaymentGateway\Midtrans;
use Carbon\Carbon;

class PaymentGatewayService
{
  public function create($type = '', Vend $vend, $params)
  {
    $paymentGateway = PaymentGateway::where('type', $type)->first();
    $vendOperatorPaymentGateway = $this->getOperatorPaymentGateway($vend);
    if($paymentGateway->id === $vendOperatorPaymentGateway->paymentGateway->id) {
      $response = '';
      switch($paymentGateway->name) {
        case 'midtrans':
          $newObj = new Midtrans($vendOperatorPaymentGateway->key1, 'QRIS');
          $response = $newObj->executeRequest($params);
          break;
      }
      return $response;
    }
  }

  public function getOperatorPaymentGateway(Vend $vend)
  {
    if($vend->operators()->exists()) {
      $operator = $vend->operators()->first();

      if($operator->operatorPaymentGateway()->exists()) {
        $operatorPaymentGateway = $operator->operatorPaymentGateway()->where('type', $this->getAppEnvironment())->first();

        return $operatorPaymentGateway;
      }
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