<?php

namespace App\Services;

use App\Models\ModemUnit;
use App\Models\Vend;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use PhpMqtt\Client\Facades\MQTT;
use PhpMqtt\Client\MqttClient;

class MqttService
{
  const IP_ADDRESS = '143.198.221.235';
  const CONNECTION_TYPE = 'mqtt';
  const SUBSCRIBED_TOPIC = '#';

  public function publish($topic, $message, $qos = MqttClient::QOS_AT_LEAST_ONCE, $connection = null)
  {
    $startTime = Carbon::now();
    $mqtt = $connection ? MQTT::connection($connection) :  MQTT::connection();

    $mqtt->publish($topic, $message, $qos);

    // compare start time with now in every mqtt loop, if it is more than 30 seconds, interrupt the loop
    // $mqtt->registerLoopEventHandler(function ($mqtt, float $elapsedTime) {
    //     if (Carbon::now()->diffInSeconds($startTime) >= 20) {
    //         $mqtt->interrupt();
    //     }
    // });
    $mqtt->loop(true, true);
    // $mqtt->disconnect();
  }

  public function publishModemParamMapping(ModemUnit $modemUnit, $fid, $input)
  {
    $content = base64_encode(json_encode($input));
    $contentLength = strlen($content);
    $key = $modemUnit->imei."A";
    $topic = 'CM'.ltrim(substr($modemUnit->imei, -6), "0");
    $md5 = strtoupper(md5($fid.','.$contentLength.','.$content.$key));

    return [
      'topic' => $topic,
      'message' => $fid.','.$contentLength.','.$content.','.$md5,
      'qos' => MqttClient::QOS_AT_LEAST_ONCE,
      'connection' => null
    ];
  }

  public function publishVend(Vend $vend, $fid, $input)
  {
    $fid = $fid;
    $content = base64_encode(json_encode($input));
    $contentLength = strlen($content);
    $key = $vend && $vend->private_key ? $vend->private_key : '123456789110138A';
    $md5 = md5($fid.','.$contentLength.','.$content.$key);

    $this->publish('CM'.$vend->code, $fid.','.$contentLength.','.$content.','.$md5);
  }
}