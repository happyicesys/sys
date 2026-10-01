<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Middleware\VerifyDeviceSignature;
use App\Models\CardPaymentIntent;
use App\Models\Vend;
use App\Services\CardTerminal\CardPaymentService;
use App\Services\CardTerminal\CardTerminalEventLog;
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
    public function __construct(
        private readonly CardPaymentService $payments,
        private readonly CardTerminalEventLog $events,
    ) {}

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

        return $this->logged('authorize', $request, $data['reference'], fn () => response()->json(
            $this->present($this->payments->authorize($this->vend($request), $data['reference'], (int) $data['amount_cents'])),
            202,
        ), ['amount_cents' => (int) $data['amount_cents']]);
    }

    public function show(Request $request, string $code, string $reference): JsonResponse
    {
        return $this->guard(fn () => response()->json(
            $this->present($this->payments->refresh($this->intent($request, $reference))),
        ));
    }

    public function cancel(Request $request, string $code, string $reference): JsonResponse
    {
        return $this->logged('cancel', $request, $reference, fn () => response()->json(
            $this->present($this->payments->cancel($this->intent($request, $reference))),
        ));
    }

    public function capture(Request $request, string $code, string $reference): JsonResponse
    {
        $data = $request->validate(['amount_cents' => ['required', 'integer', 'min:1', 'max:10000000']]);

        return $this->logged('capture', $request, $reference, fn () => response()->json(
            $this->present($this->payments->capture($this->intent($request, $reference), (int) $data['amount_cents'])),
        ), ['amount_cents' => (int) $data['amount_cents']]);
    }

    public function void(Request $request, string $code, string $reference): JsonResponse
    {
        return $this->logged('void', $request, $reference, fn () => response()->json(
            $this->present($this->payments->void($this->intent($request, $reference))),
        ));
    }

    /**
     * The device's own view of the trial (rail chosen, readiness flips, calls that
     * never reached mark1, step timings) onto the same timeline as `device.<event>`.
     * Best effort on the device side; batches of up to 50.
     */
    public function events(Request $request): JsonResponse
    {
        $data = $request->validate([
            'events' => ['required', 'array', 'max:50'],
            'events.*.event' => ['required', 'string', 'max:40', 'regex:/^[a-z0-9_.]+$/'],
            'events.*.level' => ['nullable', 'in:debug,info,warning,error'],
            'events.*.reference' => ['nullable', 'string', 'max:64', 'regex:/^[A-Za-z0-9_-]+$/'],
            'events.*.ms' => ['nullable', 'integer', 'min:0', 'max:86400000'],
            'events.*.at' => ['nullable', 'integer'],
            'events.*.detail' => ['nullable', 'array'],
        ]);
        $vend = $this->vend($request);
        foreach ($data['events'] as $e) {
            $this->events->record('device.'.$e['event'], array_filter([
                'device_at' => isset($e['at']) ? date('c', (int) intdiv((int) $e['at'], 1000)) : null,
                'reference' => $e['reference'] ?? null,
            ], fn ($v) => $v !== null) + array_slice((array) ($e['detail'] ?? []), 0, 30, true),
                null,
                isset($e['reference']) ? CardPaymentIntent::customOrderIdFor($vend, $e['reference']) : null,
                $e['ms'] ?? null,
                $e['level'] ?? 'info',
                $vend->id);
        }

        return response()->json(['recorded' => count($data['events'])]);
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

    /**
     * A money-moving device action, written to the trial timeline with what mark1
     * answered and how long it took. Status polls are not logged here: the state
     * changes they cause already are (intent.state), and a poll every 1.5 s would
     * bury them.
     */
    private function logged(string $action, Request $request, string $reference, callable $work, array $detail = []): JsonResponse
    {
        $started = hrtime(true);
        $response = $this->guard($work);
        $vend = $this->vend($request);
        $body = (array) $response->getData(true);
        $this->events->record('device.request', [
            'action' => $action,
            'reference' => $reference,
            'http_status' => $response->getStatusCode(),
            'state' => $body['state'] ?? null,
            'error' => $body['error'] ?? null,
        ] + $detail, null, CardPaymentIntent::customOrderIdFor($vend, $reference),
            (int) round((hrtime(true) - $started) / 1_000_000),
            $response->getStatusCode() < 400 ? 'info' : 'warning', $vend->id);

        return $response;
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
