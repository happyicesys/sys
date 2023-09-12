<?php

namespace App\Services;

use App\Models\DeliveryPlatform;
use App\Models\DeliveryPlatformOperator;
use App\Models\DeliveryPlatform\Grab;
use App\Models\Vend;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;

class DeliveryPlatformService
{
  private $type;
  private $vend;
  private $deliveryPlatformObject;

  public function __construct(Vend $vend, $type)
  {
    $this->type = $type;
    $this->vend = $vend;
    $this->deliveryPlatformObject = $this->getDeliveryPlatform($vend, $type);
  }

  public function createOrder(Model $model, $params = [])
  {
    if($model instanceof Grab) {
      return [
        'order_id' => $params['orderID'],
        'short_order_id' => $params['shortOrderNumber'],
        'merchant_id' => $params['merchantID'],//
        'partner_merchant_id' => $params['partnerMerchantID'], //
        'payment_type' => $params['paymentType'],//
        'order_created_at' => $params['orderTime'],
        'submit_time' => $params['submitTime'],
        'order_completed_at' => $params['completeTime'],
        'scheduled_time' => $params['scheduledTime'],
        'status' => $params['orderState'],

      ];
    }
  }

  public function deleteOrder()
  {

  }

  public function editOrder()
  {

  }

  public function getCategories()
  {

  }

  public function pauseStore()
  {

  }

  public function syncIncomingAuth()
  {

  }

  public function syncOutgoingAuth()
  {

  }

  public function syncMenu()
  {

  }

  public function updateOrderStatus()
  {

  }

  public function updateQty()
  {

  }

  private function getDeliveryPlatform(Vend $vend, $type)
  {
    $operator = $this->getOperator($vend);

    if($operator->deliveryPlatformOperators()->exists()) {
      $deliveryPlatformOperator = $operator->deliveryPlatformOperators()->where('type', $this->getAppEnvironment())->first();

      if(!$deliveryPlatformOperator) {
        throw new \Exception('Api key environment not match with current environment');
      }

      switch($deliveryPlatformOperator->deliveryPlatform->slug) {
        case 'grab':
          $object = new Grab($deliveryPlatformOperator);
          return $object;
          break;
      }

      throw new InvalidArgumentException('Invalid delivery platform specified.');

    }else {
      throw new \Exception('Delivery Platform is not set within operator');
    }
  }

  private function getOperator(Vend $vend)
  {
    if(!$vend->currentOperator()->exists()) {
      throw new \Exception('Vend is not set to any operator');
    }

    return $vend->currentOperator()->first();
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