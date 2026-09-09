<?php

namespace App\Http\Controllers\Citybox;

use App\Http\Controllers\Controller;
use App\Models\CityboxWebhookEvent;
use App\Services\Citybox\CityboxWebhookIngest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Public receivers for the CityBox-Openapi pushes (order / refund /
 * close-door). Citybox POSTs x-www-form-urlencoded with app_id + sign +
 * data(JSON string) and retries order/refund pushes until we respond
 * {status:200, success:true} — so success:true is only ever returned after
 * CityboxWebhookIngest reports durable storage. Signature check happens
 * inside the ingest service (needs the raw `data` bytes, and a failed check
 * must still store the event for forensics).
 *
 * Smart Chiller (CityBox) surface only — these routes serve no other machine
 * type. URLs to register with Citybox:
 *
 *   POST /api/citybox/push          ← ONE url for all three (Brian, 2026-09-09)
 *
 * and, still live for a supplier that prefers one URL per event:
 *   POST /api/citybox/order-push
 *   POST /api/citybox/refund-push
 *   POST /api/citybox/close-push
 *
 * All four land in the same ingest; only how the TYPE is decided differs.
 */
class CityboxWebhookController extends Controller
{
    public function __construct(private CityboxWebhookIngest $ingest) {}

    /**
     * The single-URL receiver: one endpoint for all three push types, so
     * Citybox only has to register one callback. The type is taken from an
     * explicit hint when they send one, otherwise inferred from the payload
     * shape (see detectType) — the stored row keeps the raw bytes either way,
     * so a mis-inference is re-classifiable and never loses an event.
     */
    public function push(Request $request): JsonResponse
    {
        $params = $request->post();
        $type = $this->detectType($params);

        Log::info('Citybox unified webhook received', ['type' => $type, 'fields' => array_keys($params)]);

        return $this->handle($type, $request);
    }

    /**
     * PLACEHOLDER, pending Citybox (asked 2026-09-09): we do not yet know which
     * parameter — if any — they would send to say which push this is. Until
     * they answer we accept any of these field names, in the form params or
     * inside `data`, and any of the value spellings in HINT_VALUES. When they
     * confirm, narrow this to the real field: nothing else has to change.
     */
    private const HINT_FIELDS = ['type', 'push_type', 'msg_type', 'notify_type', 'event_type', 'action'];

    /** Value spellings we accept for the hint → our stored type. */
    private const HINT_VALUES = [
        'order' => CityboxWebhookEvent::TYPE_ORDER,
        'order_push' => CityboxWebhookEvent::TYPE_ORDER,
        'refund' => CityboxWebhookEvent::TYPE_REFUND,
        'refund_push' => CityboxWebhookEvent::TYPE_REFUND,
        'close' => CityboxWebhookEvent::TYPE_CLOSE,
        'close_push' => CityboxWebhookEvent::TYPE_CLOSE,
        'close_door' => CityboxWebhookEvent::TYPE_CLOSE,
    ];

    /**
     * Which push is this? Ordered most-specific first:
     *  1. an explicit hint field, if they send one (see HINT_FIELDS — a
     *     placeholder until Citybox tells us the real parameter);
     *  2. REFUND — `order_name` rides OUTSIDE data on refund pushes (an order
     *     push carries it nested in data.order), and refund_status/refund_money
     *     appear only there;
     *  3. CLOSE — close_time is unique to the close-door push;
     *  4. ORDER — the default, and the one their retry loop depends on.
     *
     * Inference (2-4) is what makes the single URL work TODAY, with no answer
     * from them: the three documented payloads have no overlapping shape.
     */
    private function detectType(array $params): string
    {
        // Same defensive decode as the ingest: anything that is not a JSON
        // object/array becomes [] rather than throwing on a malformed push.
        $rawData = $params['data'] ?? '';
        $decoded = is_array($rawData) ? $rawData : json_decode((string) $rawData, true);
        $payload = is_array($decoded) ? $decoded : [];

        if ($hinted = $this->hintedType($params, $payload)) {
            return $hinted;
        }

        if (isset($params['order_name']) || isset($payload['refund_status']) || isset($payload['refund_money'])) {
            return CityboxWebhookEvent::TYPE_REFUND;
        }

        if (isset($payload['close_time'])) {
            return CityboxWebhookEvent::TYPE_CLOSE;
        }

        return CityboxWebhookEvent::TYPE_ORDER;
    }

    /** An explicit type hint from the params or the payload, or null. */
    private function hintedType(array $params, array $payload): ?string
    {
        foreach (self::HINT_FIELDS as $field) {
            foreach ([$params[$field] ?? null, $payload[$field] ?? null] as $value) {
                if (! is_string($value) && ! is_int($value)) {
                    continue;
                }
                $key = strtolower(trim((string) $value));
                if (isset(self::HINT_VALUES[$key])) {
                    return self::HINT_VALUES[$key];
                }
            }
        }

        return null;
    }

    public function orderPush(Request $request): JsonResponse
    {
        return $this->handle(CityboxWebhookEvent::TYPE_ORDER, $request);
    }

    public function refundPush(Request $request): JsonResponse
    {
        return $this->handle(CityboxWebhookEvent::TYPE_REFUND, $request);
    }

    public function closePush(Request $request): JsonResponse
    {
        return $this->handle(CityboxWebhookEvent::TYPE_CLOSE, $request);
    }

    private function handle(string $type, Request $request): JsonResponse
    {
        try {
            $accepted = $this->ingest->ingest($type, $request->post());
        } catch (Throwable $e) {
            // A storage failure must NOT ack — their retry is our redelivery.
            Log::error('Citybox webhook ingest crashed', ['type' => $type, 'error' => $e->getMessage()]);
            $accepted = false;
        }

        // Their documented ack envelope. msg is free-text; keep it terse and
        // non-leaky (no exception details to an external caller).
        return response()->json([
            'status' => 200,
            'success' => $accepted,
            'msg' => $accepted ? '操作成功' : '操作失败',
            'time' => now()->format('Y-m-d H:i:s'),
        ]);
    }
}
