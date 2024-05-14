<?php

namespace App\Services;

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

  public function publish($topic, $message, $qos = MqttClient::QOS_AT_LEAST_ONCE)
  {
    $startTime = Carbon::now();
    $mqtt = MQTT::connection();

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