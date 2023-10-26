<?php

namespace App\Services;

use App\Models\DeliveryPlatform;
use App\Models\DeliveryPlatforms\Grab;
use App\Models\DeliveryPlatformMenuRecord;
use App\Models\DeliveryPlatformOperator;
use App\Models\DeliveryProductMapping;
use App\Models\DeliveryProductMappingVend;
use App\Models\DeliveryProductMappingVendChannel;
use App\Models\Operator;
use App\Models\Vend;
use App\Models\VendChannel;
use App\Jobs\SyncDeliveryPlatformOauthByOperator;
use App\Services\DeliveryPlatformOperatorService;
use Carbon\Carbon;
use DB;
use Illuminate\Support\Facades\Http;

class DeliveryPlatformService
{
  private $deliveryPlatformOperator;
  private $deliveryProductMappingService;
  private $model;

  public function __construct()
  {
    $this->deliveryPlatformOperator = new DeliveryPlatformOperator();
    $this->deliveryProductMappingService = new DeliveryProductMappingService();
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

  public function getMenu($platformRefId = null, $vendCode = null)
  {
    $deliveryProductMappingVend = DeliveryProductMappingVend::query()
      ->when($platformRefId, function($query) use ($platformRefId) {
        return $query->where('platform_ref_id', $platformRefId);
      })
      ->where('vend_code', $vendCode)
      ->first();

    if(!$deliveryProductMappingVend) {
      throw new \Exception('No Vending Machine found for this Platform Ref ID.');
    }

    $this->deliveryPlatformOperator = $deliveryProductMappingVend->deliveryProductMapping->deliveryPlatformOperator;
    $this->setDeliveryPlatformOperator($deliveryProductMappingVend->deliveryProductMapping->deliveryPlatformOperator);

    switch($this->deliveryPlatformOperator->deliveryPlatform->slug) {
      case 'grab':
        $deliveryProductMappingVendObj = DeliveryProductMappingVend::query()
          ->with([
            'deliveryProductMapping' => function($query) {
              $query->select(
                'id',
                DB::raw('json_unquote(json_extract(category_json, "$.id")) AS platform_category_id'),
                DB::raw('json_unquote(json_extract(category_json, "$.name")) AS platform_category_name'),
                'delivery_platform_operator_id',
                'is_active',
                'name',
                'operator_id'
              );
            },
            'deliveryProductMapping.operator:id,name,country_id',
            'deliveryProductMapping.operator.country:id,name,code,currency_name,currency_symbol,currency_exponent',
            'deliveryProductMappingVendChannels' => function($query) {
              $query->select(
                'id',
                'amount',
                'delivery_product_mapping_item_id',
                'delivery_product_mapping_vend_id',
                'order_qty',
                'is_active',
                'vend_channel_code',
                'vend_channel_id'
              );
            },
            'deliveryProductMappingVendChannels.deliveryProductMappingVend:id',
          ])
          ->where('id', $deliveryProductMappingVend->id)
          ->select(
            'id',
            'delivery_product_mapping_id',
            'platform_ref_id',
            'vend_id',
            'vend_code'
          )
          ->first();

          $response = [
            'merchantID' => (string)$deliveryProductMappingVendObj->platform_ref_id,
            'partnerMerchantID' => (string)$deliveryProductMappingVendObj->vend_code,
            'currency' => $this->getGrabMenuCurrency($deliveryProductMappingVendObj),
            'sellingTimes' => [$this->getGrabMenuSellingTimes()],
            'categories' => [[
              ...$this->getGrabMenuCategories($deliveryProductMappingVendObj),
              'subCategories' => $this->getGrabMenuSubCategoriesItems($deliveryProductMappingVendObj->deliveryProductMappingVendChannels()->pluck('id'))
            ]],
          ];

        return $response;
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

  public function notifyUpdatedMenu(DeliveryProductMappingVend $deliveryProductMappingVend)
  {
      $this->deliveryPlatformOperator = $deliveryProductMappingVend->deliveryProductMapping->deliveryPlatformOperator;
      $this->setDeliveryPlatformOperator($deliveryProductMappingVend->deliveryProductMapping->deliveryPlatformOperator);

      switch($this->deliveryPlatformOperator->deliveryPlatform->slug) {
        case 'grab':
          $response = $this->model->notifyUpdatedMenu([
            'merchantID' => $deliveryProductMappingVend->platform_ref_id,
          ]);
          if($response['success']) {
            DeliveryPlatformMenuRecord::create([
              'delivery_platform_operator_id' => $deliveryProductMappingVend->deliveryProductMapping->deliveryPlatformOperator->id,
              'delivery_product_mapping_vend_id' => $deliveryProductMappingVend->id,
              'menu_json' => $this->getMenu($deliveryProductMappingVend->platform_ref_id, $deliveryProductMappingVend->vend_code),
              'platform_ref_id' => $response['data'],
              'platform_ref_json' => $response,
              'request_datetime' => Carbon::now()->toDateTimeString(),
              'type' => DeliveryPlatformMenuRecord::TYPE_PASSIVE,
            ]);
            return $response['data'];
          }
          break;
      }

  }

  public function pauseStore()
  {

  }

  public function sendOauth()
  {

  }

  public function updateMenu(DeliveryProductMappingVendChannel $deliveryProductMappingVendChannel)
  {
    $this->deliveryPlatformOperator = $deliveryProductMappingVendChannel->deliveryProductMappingVend->deliveryProductMapping->deliveryPlatformOperator;
    $this->setDeliveryPlatformOperator($deliveryProductMappingVendChannel->deliveryProductMappingVend->deliveryProductMapping->deliveryPlatformOperator);

    switch($this->deliveryPlatformOperator->deliveryPlatform->slug) {
      case 'grab':
        $response = $this->model->updateMenuRecord([
          'merchantID' => $deliveryProductMappingVendChannel->deliveryProductMappingVend->platform_ref_id,
          'field' => 'ITEM',
          'id' => (string) $deliveryProductMappingVendChannel->deliveryProductMappingItem->product->code,
          'price' => $deliveryProductMappingVendChannel->amount,
          'availableStatus' => Grab::STATUS_MAPPING[$deliveryProductMappingVendChannel->is_active],
          'maxStock' => Grab::STATUS_MAPPING[$deliveryProductMappingVendChannel->is_active] === Grab::STATUS_AVAILABLE ? $this->deliveryProductMappingService->getDeliveryVendChannelStatus($deliveryProductMappingVendChannel->vendChannel, $deliveryProductMappingVendChannel)['available_qty'] : 0,
        ]);
        if($response['success']) {
          DeliveryPlatformMenuRecord::create([
            'delivery_platform_operator_id' => $deliveryProductMappingVendChannel->deliveryProductMappingVend->deliveryProductMapping->deliveryPlatformOperator->id,
            'delivery_product_mapping_vend_id' => $deliveryProductMappingVendChannel->deliveryProductMappingVend->id,
            'menu_json' => $this->getMenu($deliveryProductMappingVendChannel->deliveryProductMappingVend->platform_ref_id, $deliveryProductMappingVendChannel->deliveryProductMappingVend->vend_code),
            'platform_ref_id' => $response['data'],
            'platform_ref_json' => $response,
            'request_datetime' => Carbon::now()->toDateTimeString(),
            'type' => DeliveryPlatformMenuRecord::TYPE_ACTIVE,
          ]);
          return $response['data'];
        }
        break;
    }
  }


  public function updateOrderStatus()
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

  // retrieve grab subcategory with items
  private function getGrabMenuSubCategoriesItems($vendChannelsId = [])
  {
    $deliveryProductMappingVendChannels = DeliveryProductMappingVendChannel::query()
      ->with([
        'deliveryProductMappingItem' => function($query) {
          $query->select(
            'id',
            'amount',
            'channel_code',
            'is_active',
            'product_id',
            DB::raw('json_unquote(json_extract(sub_category_json, "$.id")) AS platform_sub_category_id'),
            DB::raw('json_unquote(json_extract(sub_category_json, "$.name")) AS platform_sub_category_name'),
          );
        },
        'deliveryProductMappingItem.product:id,code,name,desc,barcode,measurement_value,measurement_unit,measurement_count',
        'deliveryProductMappingItem.product.thumbnail:id,full_url,attachments.modelable_type,attachments.modelable_id',
        'deliveryProductMappingVend:id'
      ])
      ->whereIn('id', $vendChannelsId)
      ->select(
        'id',
        'amount',
        'delivery_product_mapping_item_id',
        'delivery_product_mapping_vend_id',
        'order_qty',
        'is_active',
        'reserved_percent',
        'reserved_qty',
        'vend_channel_code',
        'vend_channel_id'
      )
      ->get();

      $data = [];
      foreach($deliveryProductMappingVendChannels as $deliveryProductMappingVendChannelIndex => $deliveryProductMappingVendChannel) {
        $data[$deliveryProductMappingVendChannel->deliveryProductMappingItem->platform_sub_category_id]['id'] = $deliveryProductMappingVendChannel->deliveryProductMappingItem->platform_sub_category_id;
        $data[$deliveryProductMappingVendChannel->deliveryProductMappingItem->platform_sub_category_id]['name'] = $deliveryProductMappingVendChannel->deliveryProductMappingItem->platform_sub_category_name;
        $data[$deliveryProductMappingVendChannel->deliveryProductMappingItem->platform_sub_category_id]['availableStatus'] = Grab::STATUS_MAPPING[$deliveryProductMappingVendChannel->deliveryProductMappingItem->is_active];
        $data[$deliveryProductMappingVendChannel->deliveryProductMappingItem->platform_sub_category_id]['sellingTimeID'] = $this->getGrabMenuSellingTimes()['id'];
        $data[$deliveryProductMappingVendChannel->deliveryProductMappingItem->platform_sub_category_id]['items'][] =
        $this->getGrabMenuItems([
          'item_id' => $deliveryProductMappingVendChannel->deliveryProductMappingItem->product->id,
          'item_name' => $deliveryProductMappingVendChannel->deliveryProductMappingItem->product->name,
          'desc' => $deliveryProductMappingVendChannel->deliveryProductMappingItem->product->desc,
          'amount' => $deliveryProductMappingVendChannel->deliveryProductMappingItem->amount,
          'image_url' => $deliveryProductMappingVendChannel->deliveryProductMappingItem->product->thumbnail->full_url,
          'is_active' => Grab::STATUS_MAPPING[$deliveryProductMappingVendChannel->is_active],
          'available_qty' => Grab::STATUS_MAPPING[$deliveryProductMappingVendChannel->is_active] === Grab::STATUS_AVAILABLE ? $this->deliveryProductMappingService->getDeliveryVendChannelStatus($deliveryProductMappingVendChannel->vendChannel, $deliveryProductMappingVendChannel)['available_qty'] : 0,
        ]);
      }
      foreach($data as $index => $item) {
        $dataArr[] = $item;
      }
      return $dataArr;
  }

  // grab parameter getter
  private function getGrabMenuCategories($model)
  {
    return [
      'id' => $model->deliveryProductMapping->platform_category_id,
      'name' => $model->deliveryProductMapping->platform_category_name,
      'availableStatus' => Grab::STATUS_MAPPING[$model->deliveryProductMapping->is_active],
      'sellingTimeID' => $this->getGrabMenuSellingTimes()['id'],
    ];
  }

  private function getGrabMenuCurrency($model)
  {
    return [
      'code' => $model->deliveryProductMapping->operator->country->currency_name,
      'symbol' => $model->deliveryProductMapping->operator->country->currency_symbol,
      'exponent' => $model->deliveryProductMapping->operator->country->currency_exponent,
    ];
  }

  private function getGrabMenuSellingTimes()
  {
    return [
      'startTime' => Carbon::now()->startOfDay()->setTimezone('UTC')->toDatetimeString(),
      'endTime' => Carbon::now()->endOfDay()->setTimezone('UTC')->toDatetimeString(),
      'id' => 'ST-1001',
      'name' => '24/7',
      'serviceHours' => [
        'mon' => [
          'openPeriodType' => "OpenPeriod",
          'openPeriods' => [
            [
              'startTime' => Carbon::now()->startOfDay()->format('H:i'),
              'endTime' => Carbon::now()->endOfDay()->format('H:i'),
            ],
          ],
        ],
        'tue' => [
          'openPeriodType' => "OpenPeriod",
          'openPeriods' => [
            [
              'startTime' => Carbon::now()->startOfDay()->format('H:i'),
              'endTime' => Carbon::now()->endOfDay()->format('H:i'),
            ],
          ],
        ],
        'wed' => [
          'openPeriodType' => "OpenPeriod",
          'openPeriods' => [
            [
              'startTime' => Carbon::now()->startOfDay()->format('H:i'),
              'endTime' => Carbon::now()->endOfDay()->format('H:i'),
            ],
          ],
        ],
        'thu' => [
          'openPeriodType' => "OpenPeriod",
          'openPeriods' => [
            [
              'startTime' => Carbon::now()->startOfDay()->format('H:i'),
              'endTime' => Carbon::now()->endOfDay()->format('H:i'),
            ],
          ],
        ],
        'fri' => [
          'openPeriodType' => "OpenPeriod",
          'openPeriods' => [
            [
              'startTime' => Carbon::now()->startOfDay()->format('H:i'),
              'endTime' => Carbon::now()->endOfDay()->format('H:i'),
            ],
          ],
        ],
        'sat' => [
          'openPeriodType' => "OpenPeriod",
          'openPeriods' => [
            [
              'startTime' => Carbon::now()->startOfDay()->format('H:i'),
              'endTime' => Carbon::now()->endOfDay()->format('H:i'),
            ],
          ],
        ],
        'sun' => [
          'openPeriodType' => "OpenPeriod",
          'openPeriods' => [
            [
              'startTime' => Carbon::now()->startOfDay()->format('H:i'),
              'endTime' => Carbon::now()->endOfDay()->format('H:i'),
            ],
          ],
        ],
      ]
    ];
  }

  private function getGrabMenuSubCategories($model)
  {
      return [
          // 'id' => $model->,
          'name' => $params['sub_category_json']['name'],
          'availableStatus' => isset($params['status']) ? $params['status'] : self::STATUS_AVAILABLE,
          'sellingTimeID' => $this->getGrabMenuSellingTimes()['id'],
      ];
  }

  private function getGrabMenuItems($params = [])
  {
    return [
      'id' => (string)$params['item_id'],
      'name' => $params['item_name'],
      'description' => $params['desc'],
      'price' =>  round($params['amount'] * 100),
      'availableStatus' => isset($params['is_active']) ? $params['is_active'] : self::STATUS_AVAILABLE,
      'sellingTimeID' => $this->getGrabMenuSellingTimes()['id'],
      'photos' => [
        $params['image_url'],
      ],
      'specialType' => '',
      'maxStock' => $params['available_qty'],
      'maxCount' => $params['available_qty'],
      'soldByWeight' => false,
      // 'advancedPricing' => [],
      // 'purchasability' => [],
      // 'modifierGroups' => [],
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