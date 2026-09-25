<?php

namespace App\Services\Mqtt;

use PhpMqtt\Client\Exceptions\MqttClientException;

/** A QoS 1 publish whose PUBACK did not arrive within mqtt_publisher.ack_timeout. */
class PublishNotAcknowledged extends MqttClientException {}
