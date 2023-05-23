<?php

namespace App\Services;
use App\Models\OperatorPaymentGateway;
use App\Models\PaymentGateway;
use App\Models\PaymentGatewayLog;
use App\Models\PaymentGateway\Omise;
use App\Models\PaymentGateway\Midtrans;
use App\Models\Vend;
use Carbon\Carbon;

class PaymentGatewayService
{
  public function create(OperatorPaymentGateway $operatorPaymentGateway, $params)
  {
    if($operatorPaymentGateway) {
      $response = '';
      $defaultParams = [];
      switch($operatorPaymentGateway->paymentGateway->name) {
        case 'midtrans':
          $defaultParams = [
            'payment_type' => 'qris',
            'transaction_details' => [
              'order_id' => isset($params['orderId']) ? $params['orderId'] : Carbon::now()->setTimeZone($params['tz'])->format('ymdhis'),
              'gross_amount' => isset($params['amount']) ? $params['amount'] : 0,
            ],
            'qris' => [
              'acquirer' => 'gopay',
            ],
            'custom_expiry' => [
              'order_time' => Carbon::now()->setTimeZone($params['tz'])->format('Y-m-d H:i:s O'),
              'expiry_duration' => $params['expiry_seconds'],
              'unit' => 'second',
            ]
          ];
          $newObj = new Midtrans($operatorPaymentGateway->key1, 'QRIS');
          $response = $newObj->executeRequest($defaultParams);
          break;
        case 'omise':
          $defaultParams = [
            'amount' => $params['amount'] * 100,
            'currency' => $params['currency'],
            'type' => $params['type'],
            'metadata' => [
              'order_id' => isset($params['orderId']) ? $params['orderId'] : Carbon::now()->setTimeZone($params['tz'])->format('ymdhis'),
            ],
            'return_uri' => 'https://sys.happyice.com.sg',
          ];
          $newObj = new Omise([
            'public' => $operatorPaymentGateway->key1,
            'secret' => $operatorPaymentGateway->key2
          ]);
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

    if(config('app.env') === 'production') {
      $envName = 'production';
    }else {
      $envName = 'sandbox';
    }

    return $envName;
  }
}