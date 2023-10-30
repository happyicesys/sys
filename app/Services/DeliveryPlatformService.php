<?php

namespace App\Services;

use App\Models\DeliveryPlatform;
use App\Models\DeliveryPlatforms\Grab;
use App\Models\DeliveryPlatformMenuRecord;
use App\Models\DeliveryPlatformOperator;
use App\Models\DeliveryPlatformOrder;
use App\Models\DeliveryProductMapping;
use App\Models\DeliveryProductMappingVend;
use App\Models\DeliveryProductMappingVendChannel;
use App\Models\Operator;
use App\Models\Vend;
use App\Models\VendChannel;
use App\Traits\GetUserTimezone;
use App\Jobs\SyncDeliveryPlatformOauthByOperator;
use App\Services\DeliveryPlatformOperatorService;
use Carbon\Carbon;
use DB;
use Illuminate\Support\Facades\Http;

class DeliveryPlatformService
{
  use GetUserTimezone;

  private $deliveryPlatformOperator;
  private $deliveryProductMappingService;
  private $model;

  public function __construct()
  {
    $this->deliveryPlatformOperator = new DeliveryPlatformOperator();
    $this->deliveryProductMappingService = new DeliveryProductMappingService();
    $this->model = new DeliveryPlatform();
  }

  public function createOrder($platformRefId = null, $vendCode = null, $input)
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
        DB::beginTransaction();

        $deliveryPlatformOrder = new DeliveryPlatformOrder();
        $deliveryPlatformOrder->fill($this->setGrabOrderIncomingParam($input));
        $deliveryPlatformOrder->delivery_platform_id = $this->deliveryPlatformOperator->deliveryPlatform->id;
        $deliveryPlatformOrder->delivery_platform_operator_id = $this->deliveryPlatformOperator->id;
        $deliveryPlatformOrder->delivery_product_mapping_vend_id = $deliveryProductMappingVend->id;
        $deliveryPlatformOrder->save();

        $this->createDeliveryPlatformOrderItems($deliveryPlatformOrder, $input);

        DB::commit();

        return $deliveryPlatformOrder;
      break;
    }
  }

  public function updateOrder($platformRefId = null, $orderId = null , $input)
  {
    $deliveryPlatformOrder = DeliveryPlatformOrder::query()
      ->when($platformRefId, function($query, $search) {
        return $query->where('platform_ref_id', $search);
      })
      ->when($orderId, function($query, $search) {
        return $query->where('order_id', $search);
      })
      ->first();

    if(!$deliveryPlatformOrder) {
      throw new \Exception('No Order Found for this merchant ID and order ID.');
    }

    $this->deliveryPlatformOperator = $deliveryPlatformOrder->deliveryPlatformOperator;
    $this->setDeliveryPlatformOperator($deliveryPlatformOrder->deliveryPlatformOperator);

    switch($this->deliveryPlatformOperator->deliveryPlatform->slug) {
      case 'grab':
        $deliveryPlatformOrder->update([
          'status' => DeliveryPlatformOrder::GRAB_STATUS_MAPPING[$input['state']],
          'request_history_json' => $deliveryPlatformOrder->request_history_json ? array_merge($deliveryPlatformOrder->request_history_json, $input) : $input,
          'driver_eta_seconds' => isset($input['driverETA']) ? $input['driverETA'] : null,
          'driver_eta_updated_at' => isset($input['driverETA']) ? Carbon::now() : null,
        ]);
        return $deliveryPlatformOrder;
      break;
    }
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
            return $response['data'];
          }
          break;
      }
  }

  public function pauseStore()
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

  private function createDeliveryPlatformOrderItems(DeliveryPlatformOrder $deliveryPlatformOrder, $input)
  {
    if(!isset($input['items'])) {
      throw new \Exception('No items found in the request.');
    }
    $items = collect($input['items']);

    // get all the vend channels on this vend for this product id
    // use group by product id on ver2
    $deliveryProductMappingVendChannels =
      $deliveryPlatformOrder
      ->deliveryProductMappingVend
      ->deliveryProductMappingVendChannels()
      ->whereHas('deliveryProductMappingItem', function($query) use ($items) {
        $query->whereIn('product_id', $items->pluck('id'));
      })
      ->get();

    if($deliveryProductMappingVendChannels->count() === 0) {
      throw new \Exception('No items found in the mapping.');
    }
    foreach($items as $item) {
      foreach($deliveryProductMappingVendChannels as $index => $deliveryProductMappingVendChannel) {
        if($item['id'] == $deliveryProductMappingVendChannel->deliveryProductMappingItem->product->id) {
          $deliveryPlatformOrder->deliveryPlatformOrderItems()->create([
            'delivery_product_mapping_item_id' => $deliveryProductMappingVendChannel->deliveryProductMappingItem->id,
            'amount' => $item['price'],
            'product_id' => $deliveryProductMappingVendChannel->deliveryProductMappingItem->product->id,
            'qty' => $item['quantity'],
          ]);
        }
      }
    }

    $this->createOrderItemVendChannels($deliveryPlatformOrder);
  }

  // assign which vend channel to dispense, make sure call this after delivery platform order item created
  private function createOrderItemVendChannels(DeliveryPlatformOrder $deliveryPlatformOrder)
  {
    $deliveryPlatformOrderItems = $deliveryPlatformOrder->deliveryPlatformOrderItems()->get();

    foreach($deliveryPlatformOrderItems as $deliveryPlatformOrderItem) {
      $deliveryProductMappingVendChannels = $deliveryPlatformOrder->deliveryProductMappingVend->deliveryProductMappingVendChannels()
        ->whereHas('deliveryProductMappingItem.product', function($query) use ($deliveryPlatformOrderItem) {
          $query->where('id', $deliveryPlatformOrderItem->product_id);
        })
        ->get();
        dd($deliveryProductMappingVendChannels->toArray());
      if(count($deliveryProductMappingVendChannels) === 1) {
        // logic to check the qty available can cope order qty
        $deliveryProductMappingVendChannel = $deliveryProductMappingVendChannels->first();

        if($this->verifyOrderQtyAvailable($deliveryPlatformOrderItem, $deliveryProductMappingVendChannel)) {
          $deliveryPlatformOrder->orderItemVendChannels()->create([
            'delivery_product_mapping_vend_channel_id' => $deliveryProductMappingVendChannel->id,
            'delivery_product_mapping_item_id' => $deliveryProductMappingVendChannel->deliveryProductMappingItem->id,
            'delivery_platform_order_item_id' => $deliveryPlatformOrderItem->id,
            'vend_channel_id' => $deliveryProductMappingVendChannel->vend_channel_id,
            'vend_channel_code' => $deliveryProductMappingVendChannel->vend_channel_code,
            'qty' => $deliveryPlatformOrderItem->qty,
          ]);
          return true;
        }
        return false;
      }else {
        // handle multiple vend channel same product id case
      }
    }
  }

  // verify whether available qty can cope order qty
  private function verifyOrderQtyAvailable(DeliveryPlatformOrderItem $deliveryPlatformOrderItem, DeliveryProductMappingVendChannel $deliveryProductMappingVendChannel)
  {
    $response = $this->deliveryProductMappingService->getDeliveryVendChannelStatus($deliveryProductMappingVendChannel->vendChannel, $deliveryProductMappingVendChannel);

    if($response['status']) {
      if($response['available_qty'] >= $deliveryPlatformOrderItem->qty) {
        return true;
      }
    }
    return false;
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
  // menu
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
          'periods' => [
            [
              'startTime' => Carbon::now()->startOfDay()->format('H:i'),
              'endTime' => Carbon::now()->endOfDay()->format('H:i'),
            ],
          ],
        ],
        'tue' => [
          'openPeriodType' => "OpenPeriod",
          'periods' => [
            [
              'startTime' => Carbon::now()->startOfDay()->format('H:i'),
              'endTime' => Carbon::now()->endOfDay()->format('H:i'),
            ],
          ],
        ],
        'wed' => [
          'openPeriodType' => "OpenPeriod",
          'periods' => [
            [
              'startTime' => Carbon::now()->startOfDay()->format('H:i'),
              'endTime' => Carbon::now()->endOfDay()->format('H:i'),
            ],
          ],
        ],
        'thu' => [
          'openPeriodType' => "OpenPeriod",
          'periods' => [
            [
              'startTime' => Carbon::now()->startOfDay()->format('H:i'),
              'endTime' => Carbon::now()->endOfDay()->format('H:i'),
            ],
          ],
        ],
        'fri' => [
          'openPeriodType' => "OpenPeriod",
          'periods' => [
            [
              'startTime' => Carbon::now()->startOfDay()->format('H:i'),
              'endTime' => Carbon::now()->endOfDay()->format('H:i'),
            ],
          ],
        ],
        'sat' => [
          'openPeriodType' => "OpenPeriod",
          'periods' => [
            [
              'startTime' => Carbon::now()->startOfDay()->format('H:i'),
              'endTime' => Carbon::now()->endOfDay()->format('H:i'),
            ],
          ],
        ],
        'sun' => [
          'openPeriodType' => "OpenPeriod",
          'periods' => [
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

  // order
  private function setGrabOrderIncomingParam($params = [])
  {
    return [
      'order_id' => $params['orderID'],
      'short_order_id' => $params['shortOrderNumber'],
      'platform_ref_id' => $params['merchantID'],
      'vend_code' => $params['partnerMerchantID'],
      'order_created_at' => isset($params['orderTime']) ? Carbon::parse($params['orderTime'], 'UTC')->setTimezone($this->getUserTimezone()) : null,
      'order_completed_at' => isset($params['completeTime']) ? Carbon::parse($params['completeTime']) : null,
      'request_history_json' => $params,
      'status' => isset($params['orderState']) ? DeliveryPlatformOrder::GRAB_STATUS_MAPPING[$params['orderState']] : DeliveryPlatformOrder::GRAB_STATUS_MAPPING[Grab::STATE_PENDING],
      'currency' => isset($params['currency']) ? $params['currency'] : null,
      'featureFlags' => isset($params['featureFlags']) ? $params['featureFlags'] : null,
      'items' => isset($params['items']) ? $params['items'] : null,
      'price' => isset($params['price']) ? $params['price'] : null,
    ];
  }
}