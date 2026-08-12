<?php

namespace App\Services\Citybox;

use App\Exceptions\CityboxApiException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Client for the Citybox smart-chiller apiThredDetail API.
 *
 * Contract source: citybox/apiThredDetail_2026-08-12.md (supplier doc, not
 * final — no transactions endpoint yet). Signed form POSTs: business params +
 * unix `timestamp` (±5 min server tolerance) + random `nonce`, MD5 signature
 * via CityboxSigner. Envelope: {code, msg, data}; 200 = success.
 *
 * Endpoint paths keep the supplier's own spelling ("Eqiupment") — that is the
 * live route, do not "fix" it.
 */
class CityboxClient
{
    // Their envelope codes → operator-readable hints (msg from them is Chinese).
    private const CODE_HINTS = [
        400 => 'missing required system param (timestamp/nonce)',
        401 => 'signature verification failed — check api_secret and sign algorithm',
        402 => 'request expired — server clock skew over 5 minutes',
        403 => 'missing sign param',
        500 => 'Citybox internal server error',
        501 => 'update failed',
    ];

    /**
     * List their devices. Filters per doc: equipment_id / equipment_name (fuzzy),
     * equipment_status (0 banned, 1 running, 98 removing, 99 removed),
     * equipment_online_status (1 online, 2 offline).
     */
    public function getEquipment(array $filters = [], int $page = 1, int $limit = 10): array
    {
        return $this->post('getEqiupment', array_merge($filters, [
            'page' => $page,
            'limit' => $limit,
        ]));
    }

    /**
     * On-sale products for one device (or all devices when $equipmentId is null):
     * product_name, id, stock, pre_qty. Doc caps limit at 100.
     */
    public function getEquipmentProducts(?string $equipmentId = null, int $page = 1, int $limit = 100): array
    {
        return $this->post('getEqiupmentProduct', array_filter([
            'equipment_id' => $equipmentId,
            'page' => $page,
            'limit' => min($limit, 100),
        ], fn ($v) => $v !== null));
    }

    /** Product detail incl. the AI vision images. Empty data ([]) when unknown. */
    public function getProduct(int $productId): array
    {
        return $this->post('getProduct', ['product_id' => $productId]);
    }

    /**
     * Push absolute stock levels to Citybox (the topup write-back).
     * $list: [['equipment_id' => ..., 'product_id' => ..., 'stock' => ...], ...]
     *
     * Their endpoint reports business failure INSIDE a code-200 envelope
     * (success_count = 0 + a Chinese msg, transactional rollback on their side),
     * so that case is promoted to an exception here — a silent 0-row "success"
     * after a driver topup must never pass unnoticed.
     */
    public function editEquipmentProductStock(array $list): array
    {
        // `list` MUST be pre-encoded to the JSON-string form their doc allows:
        // form-encoding a nested PHP array would send list[0][equipment_id]=…
        // while the signature was computed over the JSON — guaranteed 401.
        // Encoding once here keeps the signed value and the wire value identical.
        $data = $this->post('editEqiupmentProduct', [
            'list' => json_encode($list, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);

        if ((int) ($data['success_count'] ?? 0) === 0) {
            throw new CityboxApiException(
                'Citybox stock update failed (rolled back): '.($data['msg'] ?? 'unknown reason'),
                501,
            );
        }

        return $data;
    }

    /** Sign + POST one method; return the envelope's `data` or throw. */
    private function post(string $method, array $params): array
    {
        if (! config('citybox.enabled')) {
            throw new CityboxApiException('Citybox integration is disabled (CITYBOX_ENABLED)');
        }

        $baseUrl = config('citybox.base_url');
        $secret = config('citybox.api_secret');
        if (! $baseUrl || ! $secret) {
            throw new CityboxApiException('Citybox is not configured — set CITYBOX_BASE_URL and CITYBOX_API_SECRET');
        }

        $params['timestamp'] = now()->timestamp;
        $params['nonce'] = Str::random(24);
        $params['sign'] = CityboxSigner::sign($params, $secret);

        $response = Http::asForm()
            ->timeout((int) config('citybox.timeout', 10))
            ->post(rtrim($baseUrl, '/').'/api/apiThredDetail/'.$method, $params);

        if ($response->failed()) {
            throw new CityboxApiException("Citybox {$method} HTTP {$response->status()}");
        }

        // json() is null on a non-JSON body (proxy error page etc.) — keep that
        // inside CityboxApiException instead of a null-offset ErrorException.
        $body = $response->json() ?? [];
        $code = (int) ($body['code'] ?? 0);

        if ($code !== 200) {
            $hint = self::CODE_HINTS[$code] ?? 'unrecognised code';
            throw new CityboxApiException(
                "Citybox {$method} failed: code {$code} ({$hint}) — ".($body['msg'] ?? ''),
                $code,
            );
        }

        // data may legitimately be [] (e.g. getProduct on an unknown id).
        return $body['data'] ?? [];
    }
}
