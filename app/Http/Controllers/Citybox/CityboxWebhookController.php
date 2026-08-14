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
 * Public receivers for the three CityBox-Openapi pushes (order / refund /
 * close-door). Citybox POSTs x-www-form-urlencoded with app_id + sign +
 * data(JSON string) and retries order/refund pushes until we respond
 * {status:200, success:true} — so success:true is only ever returned after
 * CityboxWebhookIngest reports durable storage. Signature check happens
 * inside the ingest service (needs the raw `data` bytes, and a failed check
 * must still store the event for forensics).
 *
 * Smart Chiller (CityBox) surface only — these routes serve no other machine
 * type. URLs (to be registered with Citybox once credentials arrive):
 *   POST /api/citybox/order-push
 *   POST /api/citybox/refund-push
 *   POST /api/citybox/close-push
 */
class CityboxWebhookController extends Controller
{
    public function __construct(private CityboxWebhookIngest $ingest) {}

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
