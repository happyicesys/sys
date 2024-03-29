<?php

namespace App\Services;
use Carbon\Carbon;

class VendDispenseService
{
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
        $data[] = [
          'port_type' => 0,
          'goods_id' => isset($channel['id']) ? (int)$channel['id'] : 0,
          'goods_name' => isset($channel['name']) ? $channel['name'] : null,
          'goodroadid' => isset($channel['code']) ? (int)$channel['code'] : null,
          'num' => $channel['qty'],
          'uselift' => 0,
          'usedropchk' => 1,
        ];
      }
    }
    return $data;
  }
}