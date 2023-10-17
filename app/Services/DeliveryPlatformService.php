<?php

namespace App\Services;

use App\Models\DeliveryPlatform;
use App\Models\DeliveryPlatforms\Grab;
use App\Models\DeliveryPlatformOperator;
use App\Models\Operator;
use App\Models\Vend;
use App\Jobs\SyncDeliveryPlatformOauthByOperator;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;

class DeliveryPlatformService
{
  private $deliveryPlatformOperator;
  private $model;

  public function __construct()
  {
    $this->deliveryPlatformOperator = new DeliveryPlatformOperator();
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

  public function getDeliveryPlatformOperator()
  {
    return $this->deliveryPlatformOperator;
  }

  public function getMenu(DeliveryPlatformOperator $deliveryPlatformOperator, $params = [])
  {
    $this->deliveryPlatformOperator = $deliveryPlatformOperator;
    $this->setDeliveryPlatformOperator($deliveryPlatformOperator);

    switch($deliveryPlatformOperator->deliveryPlatform->slug) {
      case 'grab':
        $deliveryProductMapping = DeliveryProductMapping::query()
          ->with([
            'deliveryProductMappingItems',
            'deliveryProductMappingItems.product.thumbnail'
            ])
          ->where('delivery_platform_operator_id', $deliveryPlatformOperator->id)
          ->first();




        if($response['success']) {
          return $this->model->getIncomingOauthParams($response['data']);
        }
        break;
    }
  }

  public function getOauth(DeliveryPlatformOperator $deliveryPlatformOperator)
  {
    $this->deliveryPlatformOperator = $deliveryPlatformOperator;
    $this->setDeliveryPlatformOperator($deliveryPlatformOperator);

    switch($deliveryPlatformOperator->deliveryPlatform->slug) {
      case 'grab':
        $response = $this->model->getOauthToken();
        if($response['success']) {
          return $this->model->getIncomingOauthParams($response['data']);
        }
        break;
    }
  }

  // get category options from delivery platform
  public function getCategories(DeliveryPlatformOperator $deliveryPlatformOperator)
  {
    $this->deliveryPlatformOperator = $deliveryPlatformOperator;
    $this->setDeliveryPlatformOperator($deliveryPlatformOperator);

    $response = $this->model->listMartCategories();

    if($response['success']) {
      switch($deliveryPlatformOperator->deliveryPlatform->slug) {
        case 'grab':
          return $response['data'];
          break;
      }
    }else {
      if($response['code'] === 401) {
        SyncDeliveryPlatformOauthByOperator::dispatch($deliveryPlatformOperator);
        $this->getCategories($deliveryPlatformOperator);
      }else {
        throw new \Exception('Get Categories Failed, Other than 401');
      }
    }

  }

  public function pauseStore()
  {

  }

  public function sendOauth()
  {

  }

  public function setDeliveryProductMappingParams(DeliveryProductMapping $deliveryProductMapping)
  {
    $params = [
      'category_json' => $deliveryProductMapping->category_json,
      'status' => $deliveryProductMapping->status,
      'currency' => [
        'code' => $deliveryProductMapping->deliveryPlatformOperator->operator->country->currency_code,
        'symbol' => $deliveryProductMapping->deliveryPlatformOperator->operator->country->currency_symbol,
        'exponent' => $deliveryProductMapping->deliveryPlatformOperator->operator->country->currency_exponent,
      ],
      'subCategories' => [],
    ];
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

  // set delivery platform operator
  private function setDeliveryPlatformOperator($deliveryPlatformOperator)
  {
    switch($deliveryPlatformOperator->deliveryPlatform->slug) {
      case 'grab':
        $this->model = new Grab($deliveryPlatformOperator);
        return $this->model;
        break;
    }

    throw new \Exception('Invalid delivery platform specified.');
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

  private function incomingMenuHeaderParams($params = [])
  {
    switch($this->deliveryPlatform->slug) {
      case 'grab':
        return [
          'merchantID' => $params['store_id'],
          'partnerMerchantID' => $params['partner_store_id'],
          'currency' => [
            'code' => $params['currency']['code'],
            'symbol' => $params['currency']['symbol'],
            'exponent' => $params['currency']['exponent'],
          ],
        ];
        break;
    }
  }

  private function grabMenuCategories($params = [])
  {
    return [
      'id' => $params['category_id'],
      'name' => $params['category_name'],
      'availableStatus' => 'AVAILABLE',
      'sellingTimeID' => 'ST-1001',
      'subCategories' => [],
    ];
  }

  private function grabMenuItems($params = [])
  {
    return [
      'id' => $params['item_id'],
      'name' => $params['item_name'],
      'description' => $params['desc'],
      'price' =>  $params['amount'],
      'availableStatus' => 'AVAILABLE',
      'sellingTimeID' => 'ST-1001',
      'photos' => [
        $params['image_url'],
      ],
      'specialType' => '',
      'maxStock' => $params['available_qty'],
      'maxCount' => $params['available_qty'],
      'soldByWeight' => false,
      'advancedPricing' => [],
      'purchasability' => [],
      'modifierGroups' => [],
    ];
  }

  private function grabMenuSubCategories($params = [])
  {
    return [
      'id' => $params['sub_category_id'],
      'name' => $params['sub_category_name'],
      'availableStatus' => 'AVAILABLE',
      'sellingTimeID' => 'ST-1001',
      'items' => [],
    ];
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
}