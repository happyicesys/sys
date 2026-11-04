<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Middleware\VerifyDeviceSignature;
use App\Models\FreezerFailedSession;
use App\Models\Vend;
use App\Services\SmartFreezer\FreezerUnfulfilledRefund;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;

/**
 * Device → mark1: a smart freezer's paid session that went wrong. An order paid by PayNow but never
 * fulfilled, below; a session that ended in an error is failedSession().
 *
 *   POST /api/v1/vends/{code}/orders/{orderId}/unfulfilled
 *   {"session_ref": "SF-50001-…", "amount_cents": 110, "reason": "cameras_down"}
 *
 * Signed by the machine (VerifyDeviceSignature). 202 refund queued, 200 already refunded; 404 / 409 /
 * 422 refused for good — the freezer stops retrying and the order waits for a person.
 * FreezerUnfulfilledRefund holds the rules.
 */
class FreezerOrderController extends Controller
{
    public function unfulfilled(Request $request, string $code, string $orderId, FreezerUnfulfilledRefund $refunds): JsonResponse
    {
        /** @var Vend $vend */
        $vend = $request->attributes->get(VerifyDeviceSignature::VEND_ATTRIBUTE);
        // A plain JSON 422, never a redirect: the freezer sends no Accept header.
        $validator = Validator::make($request->json()->all(), [
            'session_ref' => ['nullable', 'string', 'max:64'],
            'amount_cents' => ['required', 'integer', 'min:1'],
            'reason' => ['required', 'string', 'max:64'],
        ]);
        if ($validator->fails()) {
            return response()->json(['status' => 'refused', 'error' => $validator->errors()->first()], 422);
        }
        $data = $validator->validated();

        $result = $refunds->request($vend, $orderId, (string) ($data['session_ref'] ?? ''), (int) $data['amount_cents'], $data['reason']);

        return response()->json(array_diff_key($result, ['http' => true]), $result['http']);
    }

    /**
     * POST /api/v1/vends/{code}/sessions/{sessionRef}/failed: a paid session that ended in an error, with its
     * cart, so AI Recognition can say what the customer tried to buy and why it failed. A record only: it books
     * no sale, moves no stock and touches no money. The first report stands; a repeat answers 200.
     * 422 is refused for good, so the freezer stops sending it.
     */
    public function failedSession(Request $request, string $code, string $sessionRef): JsonResponse
    {
        /** @var Vend $vend */
        $vend = $request->attributes->get(VerifyDeviceSignature::VEND_ATTRIBUTE);
        if (! $vend->isSmartFreezer()) {
            return response()->json(['status' => 'refused', 'error' => 'not a smart freezer'], 422);
        }
        if (! str_starts_with($sessionRef, "SF-{$code}-")) {
            return response()->json(['status' => 'refused', 'error' => 'not this machine\'s session'], 422);
        }
        $validator = Validator::make($request->json()->all(), [
            'payment_method' => ['required', 'string', 'max:16'],
            'payment_ref' => ['nullable', 'string', 'max:96'],
            'order_id' => ['nullable', 'string', 'max:64'],
            'amount_cents' => ['required', 'integer', 'min:0'],
            'reason' => ['required', 'string', 'max:64'],
            'detail' => ['nullable', 'string', 'max:255'],
            'test' => ['nullable', 'boolean'],
            'started_at' => ['nullable', 'integer', 'min:0'],
            'ended_at' => ['nullable', 'integer', 'min:0'],
            'items' => ['present', 'array', 'max:50'],
            'items.*.sku' => ['nullable', 'string', 'max:64'],
            'items.*.name' => ['nullable', 'string', 'max:255'],
            'items.*.unit_cents' => ['required', 'integer', 'min:0'],
            'items.*.qty' => ['required', 'integer', 'min:1'],
            'items.*.channel' => ['nullable', 'string', 'max:16'],
            'items.*.product_id' => ['nullable', 'integer', 'min:1'],
        ]);
        if ($validator->fails()) {
            return response()->json(['status' => 'refused', 'error' => $validator->errors()->first()], 422);
        }
        $data = $validator->validated();
        $at = fn (?int $seconds) => $seconds ? Carbon::createFromTimestamp($seconds, config('app.timezone')) : null;

        $session = FreezerFailedSession::firstOrCreate(
            ['vend_id' => $vend->id, 'session_ref' => $sessionRef],
            [
                'payment_method' => $data['payment_method'],
                'payment_ref' => $data['payment_ref'] ?? null,
                'order_id' => $data['order_id'] ?? null,
                'amount_cents' => $data['amount_cents'],
                'reason' => $data['reason'],
                'detail' => $data['detail'] ?? null,
                'items' => $data['items'],
                'is_test' => (bool) ($data['test'] ?? false),
                'started_at' => $at($data['started_at'] ?? null),
                'ended_at' => $at($data['ended_at'] ?? null),
            ],
        );

        return response()->json(['status' => 'recorded'], $session->wasRecentlyCreated ? 201 : 200);
    }
}
