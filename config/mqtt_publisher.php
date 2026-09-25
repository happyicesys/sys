<?php

/*
|--------------------------------------------------------------------------
| MQTT publisher (App\Services\Mqtt\MqttPublisher)
|--------------------------------------------------------------------------
|
| How mark1 sends frames to the brokers. See the class docblock for why a
| persistent per-worker connection replaced connect-per-publish (2026-09-25).
|
| persistent_codes: machine codes whose CM<code> topic is published over the
| persistent connection. Everything else keeps the old connect-per-publish
| path until the canary is proven. "*" = every topic on every connection.
| Canary started with the two bench rigs: 2009 (smart freezer) and 2031
| (big-board vending).
|
*/

return [
    'persistent_codes' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('MQTT_PUBLISHER_PERSISTENT_CODES', '2009,2031'))
    ), fn ($code) => $code !== '')),

    // Keepalive the broker enforces on the persistent connection (it drops us
    // after 1.5x of silence). The subscriber daemon keeps its own 10 s setting.
    'keep_alive' => (int) env('MQTT_PUBLISHER_KEEP_ALIVE', 60),

    // A worker that has not published for this long reconnects before the next
    // publish instead of writing into a socket the broker may already have
    // closed (at 1.5 x keep_alive). Must stay below keep_alive.
    'idle_reconnect_after' => (int) env('MQTT_PUBLISHER_IDLE_RECONNECT_AFTER', 45),

    // Seconds to wait for TCP + CONNACK. Healthy is ~10-50 ms.
    'connect_timeout' => (int) env('MQTT_PUBLISHER_CONNECT_TIMEOUT', 3),

    // Seconds to wait for the PUBACK of a QoS 1 publish before treating the
    // publish as failed (reconnect once, publish again).
    'ack_timeout' => (int) env('MQTT_PUBLISHER_ACK_TIMEOUT', 3),
];
