<?php

namespace App\Services\Citybox;

use App\Exceptions\CityboxApiException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Client for the CityBox-Openapi surface (citybox/openapi_2026-08-14.pdf).
 *
 * Auth: every business call carries app_id + timestamp (±3 min) + sign
 * (OpenapiSigner) + access_token; the token comes from get_access_token and
 * is cached for its express_in TTL minus a safety buffer. Envelope:
 * {code, body}; code 200 = success, errors put {success:false, message} in
 * body.
 *
 * Scope is deliberately READ + auth only. open_device / pay_callback /
 * refund endpoints / add_yue / transfer are NOT wired: whether we build the
 * consumer flow (mode B) and which doc governs stock writes are open
 * supplier questions (citybox/API_REQUEST_2026-08-14.md Q2/Q6) — wiring
 * money-moving endpoints ahead of those answers invites building the wrong
 * half of the integration.
 */
class OpenapiClient
{
    private const TOKEN_CACHE_KEY = 'citybox:openapi:access_token';

    // Refresh this many seconds before their express_in claims expiry, so a
    // token never dies mid-flight between our request and their clock.
    private const TOKEN_TTL_BUFFER = 120;

    /**
     * Merchant device list. Filters (all optional): equipment_id (exact),
     * equipment_name (fuzzy), equipment_status (0/1/98/99),
     * equipment_online_status (1/2).
     */
    public function boxList(array $filters = []): array
    {
        return $this->post('api/Openapi/box_list', $filters);
    }

    /** Live on-sale products for one device: qty, price, volume, unit, class, thumbnail. */
    public function deviceProduct(string $deviceId): array
    {
        return $this->post('api/Openapi/device_product', ['device_id' => $deviceId]);
    }

    /** Merchant product catalog; filter by product_id (exact) or product_name (fuzzy). */
    public function productList(array $filters = []): array
    {
        return $this->post('api/Openapi/product_list', $filters);
    }

    /** Restock config (shipping_config) per device — includes per-product `layer`. */
    public function shippingProduct(string $deviceId): array
    {
        return $this->post('api/Openapi/shipping_product', ['device_id' => $deviceId]);
    }

    /** Current device state (e.g. FREE / BUSY / MAINTENANCE ...). */
    public function deviceStatus(string $deviceId): array
    {
        return $this->post('api/Openapi/get_device_status_new', ['device_id' => $deviceId]);
    }

    /** One order, rich detail (status enum, goods, refund block, device block). */
    public function orderDetail(string $orderName, string $openId): array
    {
        return $this->post('api/Openapi/get_order_detail', [
            'order_name' => $orderName,
            'open_id' => $openId,
        ]);
    }

    /** Unpaid orders in a date range (their cap: ≤ ~30 days). Defaults to today. */
    public function orderNotPay(?string $startDate = null, ?string $endDate = null): array
    {
        return $this->post('api/Openapi/order_not_pay', array_filter([
            'start_date' => $startDate,
            'end_date' => $endDate,
        ], fn ($v) => $v !== null));
    }

    /** Sign + POST one business method with a cached access_token; return `body` or throw. */
    private function post(string $path, array $params): array
    {
        $config = $this->requireConfig();

        $params['access_token'] = $this->accessToken();

        return $this->send($path, $params, $config);
    }

    /**
     * Cached token, fetched via get_access_token when absent.
     *
     * NOT Cache::remember(): remember() fixes the TTL BEFORE the callback
     * runs, which would cache the first token under a guessed TTL even when
     * their express_in says it dies sooner. Fetch first, then cache under the
     * TTL the response actually reported (minus a buffer so a token never
     * expires between our send and their clock).
     */
    public function accessToken(): string
    {
        $cached = Cache::get(self::TOKEN_CACHE_KEY);
        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        $body = $this->send('api/Openapi/get_access_token', [], $this->requireConfig());

        $token = $body['access_token'] ?? null;
        if (! is_string($token) || $token === '') {
            throw new CityboxApiException('Citybox openapi returned no access_token');
        }

        $ttl = max(60, (int) ($body['express_in'] ?? 3600) - self::TOKEN_TTL_BUFFER);
        Cache::put(self::TOKEN_CACHE_KEY, $token, $ttl);

        return $token;
    }

    /** The raw signed POST shared by token fetch and business calls. */
    private function send(string $path, array $params, array $config): array
    {
        $params['app_id'] = $config['app_id'];
        $params['timestamp'] = now()->timestamp;
        $params['sign'] = OpenapiSigner::sign($params, $config['secret']);

        $response = Http::asForm()
            ->timeout((int) $config['timeout'])
            ->post(rtrim($config['base_url'], '/').'/'.$path, $params);

        if ($response->failed()) {
            throw new CityboxApiException("Citybox openapi {$path} HTTP {$response->status()}");
        }

        $envelope = $response->json() ?? [];
        $code = (int) ($envelope['code'] ?? 0);
        $body = $envelope['body'] ?? [];

        if ($code !== 200) {
            throw new CityboxApiException(
                "Citybox openapi {$path} failed: code {$code} — ".($body['message'] ?? ''),
                $code,
            );
        }

        return is_array($body) ? $body : [];
    }

    /** @return array{base_url:string,app_id:string,secret:string,timeout:int} */
    private function requireConfig(): array
    {
        if (! config('citybox.openapi.enabled')) {
            throw new CityboxApiException('Citybox openapi is disabled (CITYBOX_OPENAPI_ENABLED)');
        }

        $config = config('citybox.openapi');
        if (empty($config['base_url']) || empty($config['app_id']) || empty($config['secret'])) {
            throw new CityboxApiException(
                'Citybox openapi is not configured — set CITYBOX_OPENAPI_APP_ID and CITYBOX_OPENAPI_SECRET'
            );
        }

        return $config;
    }
}
