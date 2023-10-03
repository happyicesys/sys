<?php

namespace App\Services;

use App\Services\CustomerService;
use App\Services\VendService;
use Carbon\Carbon;

class VendService
{
  const STATUS_ACTIVE = 1;
  const STATUS_INACTIVE = 0;

  protected $customerService;

  public function __construct()
  {
    $this->customerService = new CustomerService();
  }

  public function publish($topic, $message)
  {
    $mqtt = MQTT::connection();
    $mqtt->publish($topic, $message);
    // $mqtt = MQTT::publish($topic, $message);
    $mqtt->loop(true);
  }

  public function subscribe()
  {
    $mqtt = MQTT::connection();
    $mqtt->subscribe(MqttService::SUBSCRIBED_TOPIC, function (string $topic, string $message) {
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