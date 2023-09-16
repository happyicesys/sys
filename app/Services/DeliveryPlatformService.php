<?php

namespace App\Services;

use App\Models\DeliveryPlatform;
use App\Models\DeliveryPlatforms\Grab;
use App\Models\DeliveryPlatformOperator;
use App\Models\Operator;
use App\Models\Vend;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;

class DeliveryPlatformService
{
  private $deliveryPlatform;
  private $deliveryPlatformOperator;
  private $operator;

  public function __construct()
  {
    $this->deliveryPlatform = new DeliveryPlatform();
    $this->deliveryPlatformOperator = new DeliveryPlatformOperator();
    $this->operator = new Operator();
    $this->model = new DeliveryPlatform();
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

  public function getDeliveryPlatform()
  {
    return $this->deliveryPlatform;
  }

  public function getDeliveryPlatformOperator()
  {
    return $this->deliveryPlatformOperator;
  }

  public function getOauth(Operator $operator, $type)
  {
    $this->operator = $operator;
    $this->setDeliveryPlatformOperator($type);

    switch($type) {
      case 'grab':
        return $this->incomingOauthParams($this->model->getOauthToken());
        break;
    }
  }

  public function getOperator()
  {
    return $this->operator;
  }

  public function getCategories(Operator $operator, $type)
  {
    $this->operator = $operator;
    $this->setDeliveryPlatformOperator($type);

    switch($type) {
      case 'grab':
        return $this->model->listMartCategories();
        break;
    }
  }

  public function pauseStore()
  {

  }

  public function sendOauth()
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

  private function setDeliveryPlatform(Vend $vend, $type)
  {
    $this->operator = $this->setOperator($vend);
    $this->setDeliveryPlatformOperator($type);
  }

  private function setDeliveryPlatformOperator($type)
  {
    if(!($this->operator and $this->operator->deliveryPlatformOperators()->exists())) {
      throw new \Exception('Delivery Platform Operator not found.');
    }

    $this->deliveryPlatformOperator = $this
          ->operator->deliveryPlatformOperators()
          ->whereHas('deliveryPlatform', function($query) use ($type) {
            $query->where('slug', $type);
          })
          ->first();

    if(!$this->deliveryPlatformOperator) {
      throw new \Exception('Api key environment not match with current environment');
    }

    $this->deliveryPlatform = $this->deliveryPlatformOperator->deliveryPlatform;

    // dd($this->deliveryPlatformOperator->deliveryPlatform->slug);
    switch($this->deliveryPlatformOperator->deliveryPlatform->slug) {
      case 'grab':
        $this->model = new Grab($this->deliveryPlatformOperator);
        // dd($this->deliveryPlatformOperator->toArray(), $this->deliveryPlatform->toArray());
        return $this->model;
        break;
    }

    throw new \Exception('Invalid delivery platform specified.');
  }

  private function setOperator(Vend $vend)
  {
    if(!$vend->currentOperator()->exists()) {
      throw new \Exception('Vend is not set to any operator');
    }
    $this->operator = $vend->currentOperator()->first();

    return $this->operator;
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

  private function incomingOauthParams($params = [])
  {
    switch($this->deliveryPlatform->slug) {
      case 'grab':
        return [
          'access_token' => $params['access_token'],
          'expired_at' => $params['expires_in'],
          'token_type' => $params['token_type'],
        ];
        break;
    }
  }

  private function incomingOrderParams($params = [])
  {
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

  private function outgoingOauthParams($params = [])
  {
    switch($this->deliveryPlatform->slug) {
      case 'grab':
        return [
          'access_token' => $params['access_token'],
          'token_type' => $params['type'],
          'expires_in' => $params['expired_at'],
        ];
        break;
    }
  }

}