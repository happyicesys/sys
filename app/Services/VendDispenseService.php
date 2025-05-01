<?php

namespace App\Services;

use App\Jobs\PublishMqtt;
use App\Jobs\PublishDispenseMqttLoop;
use App\Models\DispenseRecord;
use App\Models\DeliveryPlatformOrder;
use App\Models\PaymentGatewayLog;
use App\Models\Vend;
use Carbon\Carbon;

class VendDispenseService
{
  public function dispense($refID, $topic, $dataArr, $type = 'payment-gateway')
  {
    PublishDispenseMqttLoop::dispatch($topic, $dataArr, 1, $this->initDispenseData($refID, $type));
  }

  public function initDispenseData($refID, $type)
  {
    if($type == 'payment-gateway') {
      $paymentGatewayLog = PaymentGatewayLog::find($refID);

      $dispenseRecord = DispenseRecord::updateOrCreate([
        'order_id' => $paymentGatewayLog->order_id,
      ],
      [
        'payment_gateway_log_id' => $refID,
        'vend_id' => $paymentGatewayLog->vend_id,
        'vend_code' => $paymentGatewayLog->vend_code,
      ]);
    } else if($type == 'delivery-platform') {
      $deliveryPlatformOrder = DeliveryPlatformOrder::find($refID);
      $vendID = Vend::where('code', $deliveryPlatformOrder->vend_code)->first()?->id;

      $dispenseRecord = DispenseRecord::updateOrCreate(
      [
        'order_id' => $deliveryPlatformOrder->vend_transaction_order_id,
      ],
      [
        'delivery_platform_order_id' => $refID,
        'vend_id' => $vendID,
        'vend_code' => $deliveryPlatformOrder->vend_code,
      ]);
    }

    return $dispenseRecord->id;

  }

  public function getSingleParam($params)
  {
    $dispenseParams = [];
    $dispenseParams = [
      'Type' => 'TRADE',
      'orderid' => isset($params['orderId']) ? $params['orderId'] : null,
      'get_type' => 1,
      'pay_type' => $params['paymentMethod'],
      'price' => isset($params['amount']) ? $params['amount'] : 0,
      'score' => 1,
      'receivetime' => Carbon::now()->timestamp,
      'action' => 'TRADE',
      'mid' => isset($params['vendCode']) ? (int)$params['vendCode'] : null,
      'txn_src' => isset($params['txn_src']) ? $params['txn_src'] : 0,
      'shipment_info' => [
        [
        'port_type' => 0,
        'goods_id' => isset($params['productID']) ? (int)$params['productID'] : 0,
        'goods_name' => isset($params['productName']) ? $params['productName'] : null,
        'goodroadid' => isset($params['channelCode']) ? (int)$params['channelCode'] : null,
        'num' => 1,
        'uselift' => 0,
        'usedropchk' => 1,
        ]
      ],
    ];

    return $dispenseParams;
  }

  public function getMultipleParam($params)
  {
    $dispenseParams = [];
    $dispenseParams = [
      'Type' => 'TRADE',
      'orderid' => isset($params['orderId']) ? $params['orderId'] : null,
      'get_type' => 1,
      'pay_type' => $params['paymentMethod'],
      'price' => isset($params['amount']) ? $params['amount'] : 0,
      'score' => 1,
      'receivetime' => Carbon::now()->timestamp,
      'action' => 'TRADE',
      'mid' => isset($params['vendCode']) ? (int)$params['vendCode'] : null,
      'txn_src' => isset($params['txn_src']) ? $params['txn_src'] : 0,
      'shipment_info' => $this->getChannelsParam($params['channels']),
    ];

    return $dispenseParams;
  }

  private function getChannelsParam($channels)
  {
    $data = [];
    if(count($channels) > 0) {
      foreach($channels as $channel) {
        $isArray = is_array($channel);
        $data[] = [
          'port_type' => 0,
          'goods_id' => $isArray && isset($channel['product']) && isset($channel['product']['id']) ? (int)$channel['product']['id'] : 0,
          'goods_name' => $isArray && isset($channel['product']) && isset($channel['product']['name']) ? $channel['product']['name'] : '',
          'goodroadid' => $isArray && isset($channel['code']) ? (int)$channel['code'] : (!$isArray ? (int)$channel : null),
          'num' => 1,
          'uselift' => 0,
          'usedropchk' => 1,
        ];
      }
    }
    return $data;
  }
}