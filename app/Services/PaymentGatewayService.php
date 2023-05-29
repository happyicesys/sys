<?php

namespace App\Services;
use App\Models\OperatorPaymentGateway;
use App\Models\PaymentGateway;
use App\Models\PaymentGatewayLog;
use App\Models\PaymentGateways\Omise;
use App\Models\PaymentGateways\Midtrans;
use App\Models\Vend;
use Carbon\Carbon;

class PaymentGatewayService
{
  public function createPaymentRequest(Vend $vend, $params)
  {
    $paymentGateway = $this->getOperatorPaymentGateway($vend);

    $processedParams = [
      'amount' => isset($params['amount']) ? $params['amount'] : throw new \Exception('Amount is not set'),
      'currency' => isset($params['currency']) ? $params['currency'] : $paymentGateway->country->currency_name,
      'expiry_seconds' => isset($params['expiry_seconds']) ? $params['expiry_seconds'] : 150,
      'metadata' => isset($params['metadata'])  ? $params['metadata']['order_id'] : throw new \Exception('OrderID is not set within metadata'),
      'timezone' => isset($params['timezone']) ? $params['timezone'] : throw new \Exception('Timezone is not set in operator'),
      'type' => isset($params['type']) ? $params['type'] : ($this->defaultPaymentMethod->exists() ? $this->defaultPaymentMethod->type_name : throw new \Exception('Payment Method is not set')),
      'return_uri' => isset($params['return_uri']) ? $params['return_uri'] : 'https://sys.happyice.com.sg',
    ];

    $response = $paymentGateway->createPayment($processedParams);

    return $response;
  }

  private function getOperatorPaymentGateway(Vend $vend)
  {
    if($vend->operators()->exists()) {
      $operator = $vend->operators()->first();

      if($operator->operatorPaymentGateways()->exists()) {
        $operatorPaymentGateway = $operator->operatorPaymentGateways()->where('type', $this->getAppEnvironment())->first();

        if($operatorPaymentGateway) {
          switch($operatorPaymentGateway->paymentGateway->name) {
            case 'midtrans':
              return new Midtrans($operatorPaymentGateway->paymentGateway->key1);
              break;
            case 'omise' :
              return new Omise($operatorPaymentGateway->paymentGateway->key1, $operatorPaymentGateway->paymentGateway->key2);
              break;
          }
          throw new InvalidArgumentException('Invalid payment gateway specified.');
        }else {
          throw new \Exception('Api key environment not match with current environment');
        }

      }else {
        throw new \Exception('Payment Gateway is not set within operator');
      }
    }else {
      throw new \Exception('Vend is not set to any operator');
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