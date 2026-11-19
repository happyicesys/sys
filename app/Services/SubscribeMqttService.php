<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use PhpMqtt\Client\Facades\MQTT;

class SubscribeMqttService
{
    const IP_ADDRESS = '143.198.221.235';

    const CONNECTION_TYPE = 'mqtt';

    const SUBSCRIBED_TOPIC = '#';

    protected $vendDataService;

    public function __construct()
    {
        $this->vendDataService = app(VendDataService::class);
    }

    public function subscribe()
    {
        $mqtt = MQTT::connection();
        $mqtt->subscribe(self::SUBSCRIBED_TOPIC, function (string $topic, string $message) {
            if (self::isFreezerAiTopic($topic)) {
                return;
            }
            $this->processData($message, self::IP_ADDRESS, self::CONNECTION_TYPE, $topic);
        }, 1);
        $mqtt->loop(true);
    }

    /**
     * Our own freezer AI's traffic (mark1 ↔ the office Macs, 2026-11-19) shares this broker but is not machine data:
     * `freezer-ai:listen` reads it. This listener subscribes to `#`, so without this it would feed every job and answer
     * to VendDataService as if a machine had sent it.
     */
    public static function isFreezerAiTopic(string $topic): bool
    {
        $prefix = trim((string) config('smart_freezer.own_ai.topic_prefix', 'freezer-ai'), '/');

        return $prefix !== '' && ($topic === $prefix || str_starts_with($topic, $prefix.'/'));
    }

    /**
     * One frame, fault-isolated. php-mqtt catches whatever the callback throws
     * and hands it to its own logger — which is a NullLogger unless
     * `enable_logging` is on — so until 2026-09-16 a Redis blip, a DB outage
     * or a malformed segment dropped the frame with no trace at all. Log it
     * here with enough to replay, and let the loop go on to the next frame.
     */
    private function processData($message, $ipAddress, $connectionType, ?string $topic = null)
    {
        $standardizedVendData = null;
        $decodedData = null;

        try {
            $standardizedVendData = $this->vendDataService->standardizedVendData($message, $connectionType);
            $decodedData = $this->vendDataService->decodeVendData($standardizedVendData);
            $this->vendDataService->processVendData($standardizedVendData, $decodedData, $ipAddress, $connectionType);
        } catch (\Throwable $e) {
            Log::error('MQTT frame processing failed', [
                'topic' => $topic,
                'vend_code' => $standardizedVendData['m'] ?? null,
                'frame_type' => is_array($decodedData) ? ($decodedData['Type'] ?? null) : null,
                'exception' => get_class($e),
                'message' => $e->getMessage(),
                'raw' => mb_substr((string) $message, 0, 500),
            ]);
        }
    }
}
