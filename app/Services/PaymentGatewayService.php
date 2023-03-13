<?php

namespace App\Services;
use App\Models\OperatorPaymentGateway;
use App\Models\PaymentGateway;
use App\Models\PaymentGatewayLog;
use App\Models\PaymentGateway\Midtrans;
use App\Models\Vend;
use Carbon\Carbon;

class PaymentGatewayService
{
  public function create(OperatorPaymentGateway $operatorPaymentGateway, $params = [])
  {
    if($operatorPaymentGateway) {
      $response = '';
      $defaultParams = [];
      switch($operatorPaymentGateway->paymentGateway->name) {
        case 'midtrans':
          $defaultParams = [
            'payment_type' => 'qris',
            'transaction_details' => [
              'order_id' => isset($params['orderId']),
              'gross_amount' => isset($params['amount']) ? $params['amount'] : 0,
            ],
            'qris' => [
              'acquirer' => 'gopay',
            ],
            'custom_expiry' => [
              'order_time' => isset($params['now']) ? $params['now']->format('Y-m-d H:i:s O') : Carbon::now()->format('Y-m-d H:i:s O'),
              'expiry_duration' => 150,
              'unit' => 'second',
            ]
          ];
          $newObj = new Midtrans($operatorPaymentGateway->key1, 'QRIS');
          $response = $newObj->executeRequest($defaultParams);
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

  private function getAppEnvironment()
  {
    $envName = 'sandbox';

    // if(config('app.env') === 'production') {
    //   $envName = 'production';
    // }else {
    //   $envName = 'sandbox';
    // }

    return $envName;
  }
}