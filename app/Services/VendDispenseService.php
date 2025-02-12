<?php

namespace App\Services;

use App\Jobs\PublishMqtt;
use App\Jobs\PublishDispenseMqttLoop;
use App\Models\DispenseRecord;
use App\Models\PaymentGatewayLog;
use Carbon\Carbon;

class VendDispenseService
{
  public function dispense($paymentGatewayLogID, $topic, $dataArr)
  {
    PublishDispenseMqttLoop::dispatch($topic, $dataArr, 1, $this->initDispenseData($paymentGatewayLogID));
  }

  public function initDispenseData($paymentGatewayLogID)
  {
    $paymentGatewayLog = PaymentGatewayLog::find($paymentGatewayLogID);

    $dispenseRecord = DispenseRecord::create([
      'payment_gateway_log_id' => $paymentGatewayLogID,
      'order_id' => $paymentGatewayLog->order_id,
      'vend_id' => $paymentGatewayLog->vend_id,
      'vend_code' => $paymentGatewayLog->vend_code,
    ]);

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