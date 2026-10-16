<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Middleware\VerifyDeviceSignature;
use App\Models\Vend;
use App\Services\SmartFreezer\FreezerUnfulfilledRefund;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * Device → mark1: a smart freezer's PayNow order that was paid but never fulfilled.
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
}
