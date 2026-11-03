<?php

namespace App\Http\Controllers;

use App\Models\CardPaymentIntent;
use App\Models\CardRefund;
use App\Models\Scopes\OperatorVendFilterScope;
use App\Models\Vend;
use App\Services\CardTerminal\CardRefundService;
use DomainException;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The T05 refund panel on AI Recognition and on a Refund Request (T05RefundPanel.vue). JSON, as one
 * panel serves both pages. Every money figure is worked out on the server; the page only sends back
 * the amount the person confirmed, so a stale page cannot refund something else.
 */
class CardRefundController extends Controller
{
    public function __construct(private readonly CardRefundService $refunds) {}

    public function panel(CardPaymentIntent $intent): JsonResponse
    {
        $this->assertVisible($intent);

        return response()->json(['panel' => $this->refunds->panel($intent)]);
    }

    public function refundItem(Request $request, CardPaymentIntent $intent): JsonResponse
    {
        $data = $request->validate([
            'product_id' => ['required', 'integer', 'min:1'],
            'unit_cents' => ['required', 'integer', 'min:1'],
            'qty' => ['required', 'integer', 'min:1', 'max:100'],
            'amount_cents' => ['required', 'integer', 'min:1'],
        ]);
        $this->assertVisible($intent);

        return $this->answer($intent, fn () => $this->refunds->refundItem(
            $intent, (int) $data['product_id'], (int) $data['unit_cents'], (int) $data['qty'], (int) $data['amount_cents'], $request->user(),
        ));
    }

    public function refundFull(Request $request, CardPaymentIntent $intent): JsonResponse
    {
        $data = $request->validate(['amount_cents' => ['required', 'integer', 'min:1']]);
        $this->assertVisible($intent);

        return $this->answer($intent, fn () => $this->refunds->refundFull($intent, (int) $data['amount_cents'], $request->user()));
    }

    public function refundCorrection(Request $request, CardPaymentIntent $intent): JsonResponse
    {
        $data = $request->validate(['amount_cents' => ['required', 'integer', 'min:1']]);
        $this->assertVisible($intent);

        return $this->answer($intent, fn () => $this->refunds->refundCorrection($intent, (int) $data['amount_cents'], $request->user()));
    }

    /** A person checked Payrallel's portal: the refund whose answer never came did, or did not, happen. */
    public function resolve(Request $request, CardRefund $refund): JsonResponse
    {
        $data = $request->validate(['went_through' => ['required', 'boolean']]);
        $intent = $refund->intent;
        $this->assertVisible($intent);

        return $this->answer($intent, fn () => $this->refunds->resolve($refund, (bool) $data['went_through'], $request->user()));
    }

    /** @param callable(): CardRefund $work */
    private function answer(CardPaymentIntent $intent, callable $work): JsonResponse
    {
        try {
            $refund = $work();
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage(), 'panel' => $this->refunds->panel($intent->fresh())], 422);
        } catch (LockTimeoutException) {
            return response()->json(['message' => __('This charge is busy. Try again in a moment.'), 'panel' => $this->refunds->panel($intent->fresh())], 409);
        }

        $amount = '$'.number_format($refund->amount_cents / 100, 2);
        $message = match ($refund->state) {
            CardRefund::STATE_REFUNDED => __('Refunded :amount to the card.', ['amount' => $amount]),
            CardRefund::STATE_REFUSED => __('Not refunded: :reason', ['reason' => $refund->error ?: __('no reason given')]),
            default => __('The refund of :amount may have gone through. It is not resent: check Payrallel\'s portal, then say what happened.', ['amount' => $amount]),
        };

        return response()->json([
            'message' => $message,
            'ok' => $refund->state === CardRefund::STATE_REFUNDED,
            'panel' => $this->refunds->panel($intent->fresh()),
        ]);
    }

    /** Viewer ceiling, as on AI Recognition: an operator-restricted user acts on its own freezers only. */
    private function assertVisible(CardPaymentIntent $intent): void
    {
        if (($operatorId = OperatorVendFilterScope::viewerOperatorId()) !== null
            && (int) Vend::withoutGlobalScopes()->whereKey($intent->vend_id)->value('operator_id') !== $operatorId) {
            abort(404);
        }
    }
}
