<?php

namespace App\Services;

use App\Services\VendDataService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use PhpMqtt\Client\Facades\MQTT;

class MqttService
{
  const IP_ADDRESS = '143.198.221.235';
  const CONNECTION_TYPE = 'mqtt';
  const SUBSCRIBED_TOPIC = '#';
  protected $vendDataService;

  public function __construct()
  {
    $this->vendDataService = new VendDataService();
  }

  public function publish($topic, $message)
  {
    MQTT::publish($topic, $message);
  }

  public function subscribe()
  {
    $mqtt = MQTT::connection();
    $mqtt->subscribe(MqttService::SUBSCRIBED_TOPIC, function (string $topic, string $message) {
        echo sprintf("\n".'Received message on topic [%s]: %s', $topic, $message);
        $this->processData($message, MqttService::IP_ADDRESS, MqttService::CONNECTION_TYPE);
    }, 1);
    $mqtt->loop(true);
  }

  private function processData($message, $ipAddress, $connectionType)
  {
      $standardizedVendData = $this->vendDataService->standardizedVendData($message, $connectionType);
      $decodedData = $this->vendDataService->decodeVendData($standardizedVendData);
      $response = $this->vendDataService->processVendData($standardizedVendData, $decodedData, $ipAddress, $connectionType);
  }
}