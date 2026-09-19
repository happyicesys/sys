<?php

namespace App\Http\Controllers\SmartFreezer;

use App\Http\Controllers\Controller;
use App\Models\SmartFreezerVideo;
use App\Models\Vend;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * POST /api/smart-freezer/zijia/videos — Zijia's servers push the door-session
 * camera video URLs here.
 *
 * The payload contract is not agreed yet (2026-09-14), so this receiver is
 * deliberately lenient: any JSON or form body is stored verbatim, and the
 * fields we can already recognise are lifted out beside it —
 *  - every http(s) string anywhere in the body → video_urls;
 *  - our order number "SF-<vendCode>-<epoch>-<seq>" (what the freezer APK hands
 *    the host's orderOpenDoor), found under any key → order_no, and its vend
 *    code → vend_id;
 *  - a device id / IMEI / serial under a common key name → device_id.
 * An unmatched push is still stored and still answered 200, so Zijia does not
 * retry-loop while the mapping is being worked out.
 *
 * Auth is one shared static token (config smart_freezer.zijia): no credential
 * exchange round-trip for the supplier to implement.
 */
class ZijiaVideoWebhookController extends Controller
{
    private const ORDER_NO_PATTERN = '/\bSF-(\d+)-\d+-\d+\b/';

    private const DEVICE_KEYS = ['deviceid', 'device_id', 'devicesn', 'device_sn', 'sn', 'imei', 'deviceno', 'device_no', 'equipmentid', 'equipment_id'];

    public function store(Request $request): JsonResponse
    {
        $token = config('smart_freezer.zijia.video_webhook_token');
        if (! $token) {
            return response()->json(['code' => 503, 'message' => 'receiver not configured'], 503);
        }

        if (! hash_equals((string) $token, $this->presentedToken($request))) {
            return response()->json(['code' => 401, 'message' => 'unauthorized'], 401);
        }

        if (strlen($request->getContent()) > (int) config('smart_freezer.zijia.video_webhook_max_bytes')) {
            return response()->json(['code' => 413, 'message' => 'payload too large'], 413);
        }

        $payload = $request->except('token');
        // A body Laravel cannot parse (text/plain, a mislabelled JSON) is kept as-is.
        $raw = $request->getContent();
        if ($payload === [] && trim($raw) !== '' && ! in_array(json_decode($raw, true), [[]], true)) {
            $payload = ['raw' => $raw];
        }
        if ($payload === []) {
            return response()->json(['code' => 422, 'message' => 'empty payload'], 422);
        }

        $strings = [];
        $deviceId = null;
        array_walk_recursive($payload, function ($value, $key) use (&$strings, &$deviceId) {
            if (! is_scalar($value)) {
                return;
            }
            $value = trim((string) $value);
            if ($value === '') {
                return;
            }
            $strings[] = $value;
            if ($deviceId === null && is_string($key) && in_array(strtolower($key), self::DEVICE_KEYS, true)) {
                $deviceId = mb_substr($value, 0, 128);
            }
        });

        $urls = array_values(array_unique(array_filter(
            $strings,
            fn ($s) => preg_match('#^https?://#i', $s) === 1
        )));

        $orderNo = null;
        $vendId = null;
        foreach ($strings as $s) {
            if (preg_match(self::ORDER_NO_PATTERN, $s, $m)) {
                $orderNo = $m[0];
                $vendId = Vend::withoutGlobalScopes()->bareCode($m[1])->value('id');
                break;
            }
        }

        $video = SmartFreezerVideo::create([
            'supplier' => 'zijia',
            'vend_id' => $vendId,
            'order_no' => $orderNo,
            'device_id' => $deviceId,
            'video_urls' => $urls,
            'payload' => $payload,
            'raw_body' => $raw !== '' ? $raw : null,
            'content_type' => mb_substr((string) $request->header('Content-Type'), 0, 128) ?: null,
            'source_ip' => $request->ip(),
        ]);

        Log::info('zijia video push', [
            'id' => $video->id, 'order_no' => $orderNo, 'vend_id' => $vendId, 'urls' => count($urls),
        ]);

        return response()->json(['code' => 0, 'message' => 'ok', 'id' => $video->id]);
    }

    private function presentedToken(Request $request): string
    {
        return (string) ($request->bearerToken()
            ?? $request->header('X-Api-Key')
            ?? $request->query('token')
            ?? '');
    }
}
