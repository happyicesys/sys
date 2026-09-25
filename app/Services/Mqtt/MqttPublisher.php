<?php

namespace App\Services\Mqtt;

use Closure;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use PhpMqtt\Client\ConnectionSettings;
use PhpMqtt\Client\Contracts\MqttClient as MqttClientContract;
use PhpMqtt\Client\Contracts\Repository;
use PhpMqtt\Client\Exceptions\MqttClientException;
use PhpMqtt\Client\Facades\MQTT;
use PhpMqtt\Client\MqttClient;
use PhpMqtt\Client\Repositories\MemoryRepository;

/**
 * Sends frames to the MQTT brokers over ONE connection per worker process.
 *
 * Why (2026-09-25): every PublishMqtt job used to open a brand-new broker
 * connection. Laravel's queue worker calls Facade::clearResolvedInstances()
 * after every job, and php-mqtt/laravel-client registers its ConnectionManager
 * with bind() rather than singleton(), so each job built a fresh client,
 * connected, authenticated, published and dropped the socket without a
 * DISCONNECT. The SG broker logged ~181,000 such connections in 6 hours
 * (~8/s, mostly the "1" ack for every device frame), each costing a connect
 * round trip on the dispense / QR path and a "Socket error" log line.
 *
 * This class is a container SINGLETON (AppServiceProvider), which survives the
 * per-job facade reset, so a worker keeps its connection across jobs:
 *   - lazy connect, clean session, keepalive 60 s, connect timeout 3 s;
 *   - client id "mk1pub-<conn>-<pid>-<rand>" (<= 23 chars: the brokers speak
 *     MQTT 3.1, whose limit Mosquitto 1.4 enforces), readable in the broker log;
 *   - a connection idle for longer than idle_reconnect_after is replaced
 *     before publishing (the broker drops it at 1.5 x keepalive);
 *   - QoS 1 waits at most ack_timeout for the PUBACK, polling every 1 ms
 *     (the library's loop() sleeps 100 ms per idle pass);
 *   - any failure (socket error, missing PUBACK) drops the connection,
 *     reconnects once and publishes once more; a second failure propagates so
 *     the job fails visibly instead of silently;
 *   - a clean DISCONNECT when the worker stops (WorkerStopping) or the
 *     process ends.
 *
 * Rollout is gated by config('mqtt_publisher.persistent_codes'): only the
 * CM<code> topics of those machines use the persistent connection, everything
 * else keeps the old connect-per-publish path (publishLegacy) until the canary
 * is proven; "*" moves every topic over. Delivery semantics are unchanged:
 * QoS 1 is at-least-once on both paths.
 *
 * PHP workers are single-threaded, so one client per process is never shared
 * between concurrent publishes.
 */
class MqttPublisher
{
    /** @var array<string, array{client: MqttClientContract, repository: Repository, last_used: float}> */
    private array $connections = [];

    /**
     * @param  array  $config  config('mqtt_publisher')
     * @param  array  $connectionConfigs  config('mqtt-client.connections')
     * @param  Closure|null  $clientFactory  fn (string $name, string $clientId, Repository $repo): MqttClientContract — tests
     * @param  Closure|null  $legacyPublisher  fn (string $topic, string $message, int $qos, ?string $connection) — tests
     * @param  Closure|null  $clock  fn (): float seconds — tests
     */
    public function __construct(
        private readonly array $config,
        private readonly array $connectionConfigs,
        private readonly string $defaultConnection,
        private readonly ?Closure $clientFactory = null,
        private readonly ?Closure $legacyPublisher = null,
        private readonly ?Closure $clock = null,
    ) {}

    public function publish(string $topic, string $message, int $qos = MqttClient::QOS_AT_LEAST_ONCE, ?string $connection = null): void
    {
        $name = $connection ?: $this->defaultConnection;

        if (! $this->usesPersistentConnection($topic, $name)) {
            $this->publishLegacy($topic, $message, $qos, $connection);

            return;
        }

        try {
            $this->send($name, $topic, $message, $qos);
        } catch (MqttClientException $e) {
            Log::warning('MQTT publish failed on the persistent connection; reconnecting once', [
                'connection' => $name,
                'topic' => $topic,
                'error' => $e->getMessage(),
            ]);
            $this->drop($name);
            $this->send($name, $topic, $message, $qos);
        }
    }

    public function usesPersistentConnection(string $topic, string $connection): bool
    {
        $codes = $this->config['persistent_codes'] ?? [];
        if (in_array('*', $codes, true)) {
            return true;
        }
        if ($connection !== $this->defaultConnection) {
            return false;
        }

        return preg_match('/^CM(\d+)$/', $topic, $m) === 1 && in_array($m[1], $codes, true);
    }

    /** Clean DISCONNECT for every open connection. Safe to call at any time. */
    public function disconnectAll(): void
    {
        foreach (array_keys($this->connections) as $name) {
            $this->drop($name);
        }
    }

    public function __destruct()
    {
        $this->disconnectAll();
    }

    private function send(string $name, string $topic, string $message, int $qos): void
    {
        $connection = $this->connection($name);
        $connection['client']->publish($topic, $message, $qos, false);

        if ($qos > MqttClient::QOS_AT_MOST_ONCE) {
            // Poll for the PUBACK every 1 ms rather than via loop(), whose idle
            // sleep is 100 ms: measured against a local broker, loop() made every
            // QoS 1 publish take ~100 ms although the PUBACK arrives in <1 ms.
            $startedAt = microtime(true);
            $deadline = $startedAt + max(1, (int) ($this->config['ack_timeout'] ?? 3));
            while ($connection['repository']->countPendingOutgoingMessages() > 0 && microtime(true) < $deadline) {
                $connection['client']->loopOnce($startedAt, true, 1000);
            }
            if ($connection['repository']->countPendingOutgoingMessages() > 0) {
                throw new PublishNotAcknowledged("No PUBACK for {$topic} within the ack timeout");
            }
        }

        $this->connections[$name]['last_used'] = $this->now();
    }

    /** @return array{client: MqttClientContract, repository: Repository, last_used: float} */
    private function connection(string $name): array
    {
        $existing = $this->connections[$name] ?? null;
        if ($existing !== null) {
            $idle = $this->now() - $existing['last_used'];
            if ($existing['client']->isConnected() && $idle < (int) ($this->config['idle_reconnect_after'] ?? 45)) {
                return $existing;
            }
            $this->drop($name);
        }

        $repository = new MemoryRepository;
        $clientId = $this->clientId($name);
        $client = $this->clientFactory !== null
            ? ($this->clientFactory)($name, $clientId, $repository)
            : $this->connect($name, $clientId, $repository);

        return $this->connections[$name] = [
            'client' => $client,
            'repository' => $repository,
            'last_used' => $this->now(),
        ];
    }

    private function connect(string $name, string $clientId, Repository $repository): MqttClientContract
    {
        $config = $this->connectionConfigs[$name] ?? null;
        if ($config === null) {
            throw new MqttClientException("MQTT connection [{$name}] is not configured");
        }

        $client = new MqttClient(
            (string) Arr::get($config, 'host'),
            (int) Arr::get($config, 'port', 1883),
            $clientId,
            (string) Arr::get($config, 'protocol', MqttClient::MQTT_3_1),
            $repository,
        );

        $settings = (new ConnectionSettings)
            ->setConnectTimeout(max(1, (int) ($this->config['connect_timeout'] ?? 3)))
            ->setSocketTimeout((int) Arr::get($config, 'connection_settings.socket_timeout', 5))
            ->setResendTimeout((int) Arr::get($config, 'connection_settings.resend_timeout', 10))
            ->setKeepAliveInterval(max(10, (int) ($this->config['keep_alive'] ?? 60)))
            ->setUsername(Arr::get($config, 'connection_settings.auth.username'))
            ->setPassword(Arr::get($config, 'connection_settings.auth.password'))
            ->setUseTls((bool) Arr::get($config, 'connection_settings.tls.enabled', false))
            ->setTlsSelfSignedAllowed((bool) Arr::get($config, 'connection_settings.tls.allow_self_signed_certificate', false))
            ->setTlsVerifyPeer((bool) Arr::get($config, 'connection_settings.tls.verify_peer', true))
            ->setTlsVerifyPeerName((bool) Arr::get($config, 'connection_settings.tls.verify_peer_name', true))
            ->setTlsCertificateAuthorityFile(Arr::get($config, 'connection_settings.tls.ca_file'))
            ->setTlsCertificateAuthorityPath(Arr::get($config, 'connection_settings.tls.ca_path'));

        $client->connect($settings, true);

        return $client;
    }

    /** The old path, unchanged: a fresh connection per publish via the facade. */
    private function publishLegacy(string $topic, string $message, int $qos, ?string $connection): void
    {
        if ($this->legacyPublisher !== null) {
            ($this->legacyPublisher)($topic, $message, $qos, $connection);

            return;
        }

        $mqtt = $connection ? MQTT::connection($connection) : MQTT::connection();
        $mqtt->publish($topic, $message, $qos);
        $mqtt->loop(true, true);
    }

    private function drop(string $name): void
    {
        $connection = $this->connections[$name] ?? null;
        unset($this->connections[$name]);
        if ($connection === null) {
            return;
        }

        try {
            if ($connection['client']->isConnected()) {
                $connection['client']->disconnect();
            }
        } catch (\Throwable $e) {
            // Already dead: nothing to say goodbye to.
        }
    }

    private function clientId(string $name): string
    {
        $tag = substr((string) preg_replace('/^mqtt_/', '', $name), 0, 1) ?: 'x';

        return substr('mk1pub-'.$tag.'-'.getmypid().'-'.bin2hex(random_bytes(2)), 0, 23);
    }

    private function now(): float
    {
        return $this->clock !== null ? (float) ($this->clock)() : microtime(true);
    }
}
