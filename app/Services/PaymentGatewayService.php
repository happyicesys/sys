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
    $operatorPaymentGateway = $paymentGateway->getOperatorPaymentGateway();
    $processedParams = [
      'amount' => isset($params['amount']) ? $params['amount'] : throw new \Exception('Amount is not set'),
      'currency' => isset($params['currency']) ? $params['currency'] : $operatorPaymentGateway->paymentGateway->country->currency_name,
      'expiry_seconds' => isset($params['expiry_seconds']) ? $params['expiry_seconds'] : 150,
      'metadata' => isset($params['metadata'])  ? $params['metadata'] : throw new \Exception('OrderID is not set within metadata'),
      'timezone' => isset($params['timezone']) ? $params['timezone'] : $operatorPaymentGateway->paymentGateway->country->timezone,
      'type' => isset($params['type']) ? $params['type'] : ($operatorPaymentGateway->paymentGateway->defaultPaymentMethod->exists() ? $operatorPaymentGateway->paymentGateway->defaultPaymentMethod->type_name : throw new \Exception('Payment Method is not set')),
      'return_uri' => isset($params['return_uri']) ? $params['return_uri'] : 'https://sys.happyice.com.sg',
    ];
    $response = $paymentGateway->createPayment($processedParams);

    return $response;
  }

  private function getOperatorPaymentGateway(Vend $vend)
  {
    if($this->getOperator($vend)) {
      $operator = $this->getOperator($vend);
      // dd($operator->toArray());
      if($operator->operatorPaymentGateways()->exists()) {
        $operatorPaymentGateway = $operator->operatorPaymentGateways()->where('type', $this->getAppEnvironment())->first();
        if($operatorPaymentGateway) {
          switch($operatorPaymentGateway->paymentGateway->name) {
            case 'midtrans':
              $obj = new Midtrans($operatorPaymentGateway->key1);
              $obj->setOperatorPaymentGateway($operatorPaymentGateway);
              return $obj;
              break;
            case 'omise' :
              $obj = new Omise($operatorPaymentGateway->key1, $operatorPaymentGateway->key2);
              $obj->setOperatorPaymentGateway($operatorPaymentGateway);
              return $obj;
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

  private function getOperator(Vend $vend)
  {
    if($vend->operators()->exists()) {
      return $vend->operators()->first();
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