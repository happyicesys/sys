<?php

namespace App\Services\SmartFreezer\Zijia;

use Carbon\Carbon;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use JsonException;

/**
 * Zijia's algorithm service (智佳算法服务接口文档 v1.0). The only class that knows its URLs, its
 * envelope and its signature; everything else hands it value objects and gets value objects back.
 *
 * Calls never throw for a business or transport failure — they return an AlgorithmResponse whose
 * `ok()` is false, so the caller records what happened instead of losing it in a stack trace.
 */
class ZijiaAlgorithmClient
{
    public const METHOD_RECOGNISE = 'dynamic.cabinet.add.queue';

    public const METHOD_RESULT = 'cabinet.algorithm.order.result';

    private const API_PATH = '/api/algorithm/api';

    private const SKU_QUERY_PATH = '/core/expose/sys_sku/v1/query_list';

    private const VERSION = 'v1';

    private const SIGN_TYPE = 'md5';

    private readonly array $config;

    /** @param  array<string, mixed>|null  $config  defaults to `smart_freezer.zijia.algorithm` */
    public function __construct(?array $config = null)
    {
        $this->config = $config ?? (array) config('smart_freezer.zijia.algorithm');
    }

    /** Credentials present — the one precondition for any signed call. */
    public function isConfigured(): bool
    {
        return filled($this->config['app_id'] ?? null) && filled($this->config['app_secret'] ?? null);
    }

    /** @return list<string> */
    public function modelIds(): array
    {
        return array_values((array) ($this->config['model_ids'] ?? []));
    }

    public function notifyUrl(): string
    {
        return (string) ($this->config['notify_url'] ?? '') ?: route('smart-freezer.zijia.algorithm.notify');
    }

    public function signer(): ZijiaSigner
    {
        return new ZijiaSigner((string) ($this->config['app_secret'] ?? ''));
    }

    /** Queue one door session for recognition; the answer arrives later on the notify URL. */
    public function submitRecognition(RecognitionRequest $request): AlgorithmResponse
    {
        return $this->call(self::METHOD_RECOGNISE, $request->bizContent());
    }

    /**
     * One page of their SKU library (§8) — unauthenticated on their side. Used to find the
     * barcode (`productCode`) the algorithm knows a product by.
     *
     * @return array{list: list<array<string, mixed>>, totalPage: int, currentPage: int, totalCount: int}|null
     */
    public function querySkus(int $page = 1, int $pageSize = 50, ?string $name = null, ?string $productCode = null): ?array
    {
        try {
            $body = Http::timeout($this->timeout())
                ->acceptJson()
                ->post($this->url(self::SKU_QUERY_PATH), array_filter([
                    'current' => max(1, $page),
                    'pageSize' => min(200, max(1, $pageSize)),
                    'skuName' => $name,
                    'productCode' => $productCode,
                ], fn ($v) => $v !== null && $v !== ''))
                ->throw()
                ->json();
        } catch (ConnectionException|RequestException $e) {
            Log::warning('zijia algorithm sku query failed', ['error' => $e->getMessage()]);

            return null;
        }

        return is_array($body) ? [
            'list' => array_values((array) ($body['list'] ?? [])),
            'totalPage' => (int) ($body['totalPage'] ?? 0),
            'currentPage' => (int) ($body['currentPage'] ?? $page),
            'totalCount' => (int) ($body['totalCount'] ?? 0),
        ] : null;
    }

    /**
     * The signed envelope for a method + business content (§3.1). Public so a test — or a support
     * conversation with Zijia — can see exactly what would be sent.
     *
     * @return array<string, string>
     */
    public function envelope(string $method, array $bizContent, ?Carbon $at = null): array
    {
        try {
            $biz = json_encode($bizContent, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        } catch (JsonException $e) {
            throw new \InvalidArgumentException('bizContent is not encodable: '.$e->getMessage(), 0, $e);
        }

        $params = [
            'appId' => (string) $this->config['app_id'],
            'version' => self::VERSION,
            'signType' => self::SIGN_TYPE,
            'timestamp' => ($at ?? Carbon::now())->copy()->setTimezone($this->config['timezone'] ?? 'Asia/Shanghai')->format('Y-m-d H:i:s'),
            'method' => $method,
            'bizContent' => $biz,
        ];
        $params['sign'] = $this->signer()->sign($params);

        return $params;
    }

    private function call(string $method, array $bizContent): AlgorithmResponse
    {
        if (! $this->isConfigured()) {
            return AlgorithmResponse::transportFailure('algorithm service not configured');
        }

        $envelope = $this->envelope($method, $bizContent);
        try {
            $response = Http::timeout($this->timeout())
                ->acceptJson()
                ->withBody(json_encode($envelope, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 'application/json')
                ->post($this->url(self::API_PATH));
        } catch (ConnectionException $e) {
            Log::warning('zijia algorithm call failed', ['method' => $method, 'error' => $e->getMessage()]);

            return AlgorithmResponse::transportFailure($e->getMessage());
        }

        // They answer text/plain JSON even on errors, so read the body rather than trust the header.
        $body = json_decode($response->body(), true);
        if (! is_array($body)) {
            return AlgorithmResponse::transportFailure("HTTP {$response->status()}: unreadable reply");
        }

        $result = AlgorithmResponse::fromBody($body);
        Log::info('zijia algorithm call', [
            'method' => $method, 'code' => $result->code, 'msg' => $result->message, 'request_id' => $result->requestId,
        ]);

        return $result;
    }

    private function url(string $path): string
    {
        return rtrim((string) ($this->config['base_url'] ?? ''), '/').$path;
    }

    private function timeout(): int
    {
        return max(1, (int) ($this->config['timeout'] ?? 20));
    }
}
