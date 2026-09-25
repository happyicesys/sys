<?php

namespace App\Services;

use App\Models\ModemUnit;
use App\Models\Vend;
use App\Services\Mqtt\MqttPublisher;
use PhpMqtt\Client\MqttClient;

class MqttService
{
    const IP_ADDRESS = '143.198.221.235';

    const CONNECTION_TYPE = 'mqtt';

    const SUBSCRIBED_TOPIC = '#';

    /**
     * Every frame mark1 sends to a machine goes through here. Delegates to the
     * per-process MqttPublisher singleton (2026-09-25), which keeps one broker
     * connection per worker instead of opening one per publish; see its docblock.
     */
    public function publish($topic, $message, $qos = MqttClient::QOS_AT_LEAST_ONCE, $connection = null)
    {
        app(MqttPublisher::class)->publish((string) $topic, (string) $message, (int) $qos, $connection);
    }

    public function publishModemParamMapping(ModemUnit $modemUnit, $fid, $input)
    {
        $content = base64_encode(json_encode($input));
        $contentLength = strlen($content);
        $key = $modemUnit->imei.'A';
        $topic = 'CX'.ltrim(substr($modemUnit->imei, -6), '0');
        $md5 = strtoupper(md5($fid.','.$contentLength.','.$content.$key));

        return [
            'topic' => $topic,
            'message' => $fid.','.$contentLength.','.$content.','.$md5,
            'qos' => MqttClient::QOS_AT_LEAST_ONCE,
            'connection' => null,
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
