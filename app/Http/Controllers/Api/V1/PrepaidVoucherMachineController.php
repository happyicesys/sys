<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Middleware\VerifyDeviceSignature;
use App\Models\Vend;
use App\Services\PrepaidVoucher\PrepaidVoucherProgram;
use App\Services\PrepaidVoucher\PrepaidVoucherRedeemer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The big board's next-visit voucher API (app 329+), every call signed by the machine (device.signed): the
 * settings it offers with, and the three redemption steps — look up a number's vouchers, hold one, let it
 * go. A number is never sent back. Error codes are for the machine's screens, not for people.
 */
class PrepaidVoucherMachineController extends Controller
{
    public function __construct(
        private readonly PrepaidVoucherProgram $program,
        private readonly PrepaidVoucherRedeemer $redeemer,
    ) {}

    public function config(Request $request): JsonResponse
    {
        return response()->json($this->program->machineConfig($this->vend($request)));
    }

    public function lookup(Request $request): JsonResponse
    {
        $vend = $this->vend($request);
        $found = $this->redeemer->lookup($vend, (string) $request->input('phone', ''));
        if (! $found['ok']) {
            return response()->json(['ok' => false, 'error' => $found['error']], 422);
        }
        $tz = $this->program->locale($vend)['timezone'];

        return response()->json([
            'ok' => true,
            'vouchers' => $found['vouchers']->map(fn ($v) => $this->redeemer->payload($v, $tz))->values(),
        ]);
    }

    public function reserve(Request $request, string $code, int $id): JsonResponse
    {
        $vend = $this->vend($request);
        $held = $this->redeemer->reserve($vend, (string) $request->input('phone', ''), $id);
        if (! $held['ok']) {
            return response()->json(['ok' => false, 'error' => $held['error']], 409);
        }
        $voucher = $held['voucher'];

        return response()->json([
            'ok' => true,
            'token' => $voucher->reserved_token,
            'reserved_until' => $voucher->reserved_until?->toIso8601String(),
            'voucher' => $this->redeemer->payload($voucher, $this->program->locale($vend)['timezone']),
        ]);
    }

    public function release(Request $request, string $code, int $id): JsonResponse
    {
        $released = $this->redeemer->release($this->vend($request), $id, (string) $request->input('token', ''));

        return response()->json(['ok' => $released]);
    }

    private function vend(Request $request): Vend
    {
        return $request->attributes->get(VerifyDeviceSignature::VEND_ATTRIBUTE);
    }
}
