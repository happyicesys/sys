<?php

namespace App\Http\Controllers\SmartFreezer;

use App\Http\Controllers\Controller;
use App\Models\SmartFreezerVideo;
use App\Services\SmartFreezer\FreezerDeviceResolver;
use App\Services\SmartFreezer\FreezerRecognitionService;
use App\Services\SmartFreezer\Zijia\ZijiaVideoPush;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * POST /api/smart-freezer/zijia/videos — Zijia's servers push the door-session camera video URLs
 * here, one push per door session, carrying the freezer's IMEI (Brian's request, 2026-09-27).
 *
 * The payload contract is not final, so the body is stored verbatim (`raw_body`, `payload`) and
 * ZijiaVideoPush lifts out what can be recognised; FreezerDeviceResolver names the freezer; and
 * FreezerRecognitionService opens the AI check for the session. An unmatched push is still stored
 * and still answered 200, so Zijia does not retry-loop while the mapping is being worked out.
 *
 * Every refusal is logged too: nginx keeps no access log for this site, so without these lines a
 * push that failed on its token would leave no trace at all ("did they call us?" was unanswerable
 * on 2026-09-22).
 *
 * Auth is one shared static token (config smart_freezer.zijia): no credential exchange
 * round-trip for the supplier to implement.
 */
class ZijiaVideoWebhookController extends Controller
{
    public function __construct(
        private readonly FreezerDeviceResolver $devices,
        private readonly FreezerRecognitionService $recognitions,
    ) {}

    public function store(Request $request): JsonResponse
    {
        $token = config('smart_freezer.zijia.video_webhook_token');
        if (! $token) {
            return $this->refuse($request, 503, 'receiver not configured');
        }

        if (! hash_equals((string) $token, $this->presentedToken($request))) {
            return $this->refuse($request, 401, 'unauthorized');
        }

        if (strlen($request->getContent()) > (int) config('smart_freezer.zijia.video_webhook_max_bytes')) {
            return $this->refuse($request, 413, 'payload too large');
        }

        $payload = $request->except('token');
        // A body Laravel cannot parse (text/plain, a mislabelled JSON) is kept as-is.
        $raw = $request->getContent();
        if ($payload === [] && trim($raw) !== '' && ! in_array(json_decode($raw, true), [[]], true)) {
            $payload = ['raw' => $raw];
        }
        if ($payload === []) {
            return $this->refuse($request, 422, 'empty payload');
        }

        $push = ZijiaVideoPush::fromPayload($payload);
        $vend = $this->devices->resolve($push);

        $video = SmartFreezerVideo::create([
            'supplier' => 'zijia',
            'vend_id' => $vend?->id,
            'order_no' => $push->sessionRef ?? $push->tradeId,
            'device_id' => $push->deviceIdentifier(),
            'video_urls' => $push->videoUrls,
            'payload' => $payload,
            'raw_body' => $raw !== '' ? $raw : null,
            'content_type' => mb_substr((string) $request->header('Content-Type'), 0, 128) ?: null,
            'source_ip' => $request->ip(),
        ]);

        // The push is stored by now. A failure opening its recognition must not become a 500 — Zijia
        // would retry, and every retry would be another copy of the same push.
        try {
            $recognition = $this->recognitions->open($video, $push, $vend);
        } catch (Throwable $e) {
            report($e);
            $recognition = null;
        }

        Log::info('zijia video push', [
            'id' => $video->id, 'vend_id' => $vend?->id, 'imei' => $push->imei, 'trade_id' => $push->tradeId,
            'urls' => count($push->videoUrls), 'recognition' => $recognition?->id, 'blocked' => $recognition?->status_reason,
        ]);

        return response()->json(['code' => 0, 'message' => 'ok', 'id' => $video->id]);
    }

    private function refuse(Request $request, int $status, string $message): JsonResponse
    {
        // Never the token itself: only whether one was presented.
        Log::warning('zijia video push refused', [
            'status' => $status, 'ip' => $request->ip(), 'bytes' => strlen($request->getContent()),
            'token_presented' => $this->presentedToken($request) !== '',
        ]);

        return response()->json(['code' => $status, 'message' => $message], $status);
    }

    private function presentedToken(Request $request): string
    {
        return (string) ($request->bearerToken()
            ?? $request->header('X-Api-Key')
            ?? $request->query('token')
            ?? '');
    }
}
