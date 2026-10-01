<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Middleware\VerifyDeviceSignature;
use App\Models\CardPaymentIntent;
use App\Models\Vend;
use App\Services\CardTerminal\CardPaymentService;
use App\Services\CardTerminal\CardTerminalException;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Device → mark1 card rail for a machine with a remote (cloud-commanded)
 * terminal: the smart freezer's PayrallelCardRail. All rules live in
 * CardPaymentService; this only translates HTTP.
 *
 *   GET  /api/v1/vends/{code}/card/terminal             readiness for the card button
 *   POST /api/v1/vends/{code}/card/authorize            {reference, amount_cents} → 202 intent
 *   GET  /api/v1/vends/{code}/card/{reference}          poll; re-queries the provider
 *   POST /api/v1/vends/{code}/card/{reference}/cancel
 *   POST /api/v1/vends/{code}/card/{reference}/capture  {amount_cents}
 *   POST /api/v1/vends/{code}/card/{reference}/void
 *
 * Every route is signed by the machine (VerifyDeviceSignature). Errors are
 * JSON {error}: 404 unknown machine/reference, 409 a refused transition,
 * 503 no terminal or the provider unreachable.
 */
class FreezerCardController extends Controller
{
    public function __construct(private readonly CardPaymentService $payments) {}

    public function terminal(Request $request): JsonResponse
    {
        $status = $this->payments->terminalStatus($this->vend($request));
        if (! $status) {
            return response()->json(['configured' => false, 'online' => false, 'state' => null, 'ready' => false]);
        }

        return response()->json([
            'configured' => true,
            'online' => $status->online,
            'state' => $status->state,
            'ready' => $status->isReady(),
        ]);
    }

    public function authorizePayment(Request $request): JsonResponse
    {
        $data = $request->validate([
            'reference' => ['required', 'string', 'max:64', 'regex:/^[A-Za-z0-9_-]+$/'],
            'amount_cents' => ['required', 'integer', 'min:1', 'max:10000000'],
        ]);

        return $this->guard(fn () => response()->json(
            $this->present($this->payments->authorize($this->vend($request), $data['reference'], (int) $data['amount_cents'])),
            202,
        ));
    }

    public function show(Request $request, string $code, string $reference): JsonResponse
    {
        return $this->guard(fn () => response()->json(
            $this->present($this->payments->refresh($this->intent($request, $reference))),
        ));
    }

    public function cancel(Request $request, string $code, string $reference): JsonResponse
    {
        return $this->guard(fn () => response()->json(
            $this->present($this->payments->cancel($this->intent($request, $reference))),
        ));
    }

    public function capture(Request $request, string $code, string $reference): JsonResponse
    {
        $data = $request->validate(['amount_cents' => ['required', 'integer', 'min:1', 'max:10000000']]);

        return $this->guard(fn () => response()->json(
            $this->present($this->payments->capture($this->intent($request, $reference), (int) $data['amount_cents'])),
        ));
    }

    public function void(Request $request, string $code, string $reference): JsonResponse
    {
        return $this->guard(fn () => response()->json(
            $this->present($this->payments->void($this->intent($request, $reference))),
        ));
    }

    /** What the device needs to decide; nothing internal (no raw provider body). */
    private function present(CardPaymentIntent $intent): array
    {
        return [
            'reference' => $intent->reference,
            'state' => $intent->state,
            'final' => $intent->isFinal(),
            'amount_cents' => $intent->amount_cents,
            'captured_cents' => $intent->captured_cents,
            'payment_method' => $intent->payment_method,
            'provider_txn_id' => $intent->provider_txn_id,
            'error' => $intent->last_error,
        ];
    }

    private function guard(callable $action): JsonResponse
    {
        try {
            return $action();
        } catch (DomainException $e) {
            return response()->json(['error' => $e->getMessage()], 409);
        } catch (CardTerminalException $e) {
            return response()->json(['error' => $e->getMessage()], 503);
        }
    }

    private function vend(Request $request): Vend
    {
        return $request->attributes->get(VerifyDeviceSignature::VEND_ATTRIBUTE);
    }

    private function intent(Request $request, string $reference): CardPaymentIntent
    {
        return CardPaymentIntent::query()
            ->where('vend_id', $this->vend($request)->id)
            ->where('reference', $reference)
            ->firstOrFail();
    }
}
