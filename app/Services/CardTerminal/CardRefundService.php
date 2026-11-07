<?php

namespace App\Services\CardTerminal;

use App\Models\CardPaymentIntent;
use App\Models\CardRefund;
use App\Models\Product;
use App\Models\RefundTicket;
use App\Models\User;
use App\Models\Vend;
use App\Models\VendTransaction;
use App\Services\Freezer\FreezerSaleAudit;
use App\Services\Refund\RefundTicketService;
use App\Services\SmartFreezer\FreezerSaleLocator;
use Carbon\Carbon;
use DomainException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Refunds a captured T05 charge through Payrallel, per SKU or whatever is left of it.
 *
 * Runs under the same per-intent lock as every other call on the charge, and writes the refund
 * ahead of the call: a timeout or a dead run leaves it open (`CardRefund`), and nothing more goes
 * out on that charge until a person has checked Payrallel, because a second send can pay twice.
 *
 * Only a confirmed refund touches `refunded_cents`, the sale (FreezerSaleAudit::applyRefund) and the
 * claim. A failure in those secondary writes never undoes the record of the money having moved.
 */
class CardRefundService
{
    public function __construct(
        private readonly CardPaymentService $payments,
        private readonly RemoteCardTerminalGatewayFactory $gateways,
        private readonly CardTerminalEventLog $events,
        private readonly FreezerSaleLocator $sales,
        private readonly FreezerSaleAudit $audit,
        private readonly RefundTicketService $tickets,
    ) {}

    /** The T05 charge behind a sale, or null when it was paid any other way. */
    public function intentForSale(VendTransaction $sale): ?CardPaymentIntent
    {
        $frame = $sale->vend_transaction_json;
        if (is_string($frame)) {
            $frame = json_decode($frame, true);
        }
        $ref = is_array($frame) ? ($frame['SFREF'] ?? null) : null;
        if (! is_string($ref) || $ref === '') {
            return null;
        }

        return CardPaymentIntent::query()
            ->where('vend_id', $sale->vend_id)->where('session_ref', $ref)
            ->where('state', CardPaymentIntent::STATE_CAPTURED)
            ->orderByDesc('id')->first();
    }

    /** @return array<string, mixed>|null */
    public function panelForSale(VendTransaction $sale): ?array
    {
        $intent = $this->intentForSale($sale);

        return $intent ? $this->panel($intent, $sale) : null;
    }

    /** @return array<string, mixed> everything the refund panel shows, and all it may offer */
    public function panel(CardPaymentIntent $intent, ?VendTransaction $sale = null): array
    {
        $sale ??= $this->saleOf($intent);
        $refunds = CardRefund::where('card_payment_intent_id', $intent->id)->orderBy('id')->get();
        $blocked = $this->blockedReason($intent, $sale, $refunds);
        $card = (array) (((array) $intent->last_response)['transaction']['cardinfo'] ?? []);

        return [
            'intent_id' => $intent->id,
            'order_id' => $sale?->order_id,
            'sale_id' => $sale?->id,
            'captured_at' => $intent->captured_at?->format('Y-m-d H:i:s'),
            'payment_method' => $intent->payment_method,
            'card_masked' => isset($card['maskedPan']) ? (string) $card['maskedPan'] : null,
            'captured_cents' => (int) $intent->captured_cents,
            'refunded_cents' => (int) $intent->refunded_cents,
            'refundable_cents' => $this->refundable($intent, $refunds),
            'correction_refund_cents' => $this->correctionRefund($intent, $sale, $refunds),
            'blocked_reason' => $blocked,
            'lines' => $this->lines($intent, $sale, $refunds),
            'refunds' => $refunds->map(fn (CardRefund $r) => [
                'id' => $r->id,
                'kind' => $r->kind,
                'product_name' => $r->product_name,
                'qty' => $r->qty,
                'amount_cents' => $r->amount_cents,
                // A send whose run died reads the same as one with no answer.
                'state' => $r->state === CardRefund::STATE_SENDING && $r->created_at?->lt(Carbon::now()->subMinutes(2))
                    ? CardRefund::STATE_UNCERTAIN : $r->state,
                'error' => $r->error,
                'by' => $r->requested_by_name,
                'resolved_by' => $r->resolved_by_name,
                'at' => $r->created_at?->format('Y-m-d H:i:s'),
            ])->values()->all(),
        ];
    }

    public function refundItem(CardPaymentIntent $intent, int $productId, int $unitCents, int $qty, int $confirmedCents, User $by): CardRefund
    {
        return $this->payments->locked($intent, function (CardPaymentIntent $fresh) use ($productId, $unitCents, $qty, $confirmedCents, $by) {
            $sale = $this->saleOf($fresh);
            $refunds = CardRefund::where('card_payment_intent_id', $fresh->id)->get();
            $this->assertNotBlocked($fresh, $sale, $refunds);

            $line = collect($this->lines($fresh, $sale, $refunds))
                ->first(fn (array $l) => $l['product_id'] === $productId && $l['unit_cents'] === $unitCents);
            if ($line === null || $qty > $line['refundable_qty']) {
                throw new DomainException(__('That item has nothing left to refund.'));
            }
            // Capped: the charge can be below the basket (Payrallel refused to go above the hold).
            $amount = min($unitCents * $qty, $this->refundable($fresh, $refunds));

            return $this->send($fresh, $sale, $amount, $confirmedCents, $by, [
                'kind' => CardRefund::KIND_ITEM,
                'product_id' => $productId,
                'product_name' => $line['name'],
                'qty' => $qty,
                'unit_cents' => $unitCents,
            ]);
        });
    }

    /** Whatever is left of the charge. */
    public function refundFull(CardPaymentIntent $intent, int $confirmedCents, User $by): CardRefund
    {
        return $this->payments->locked($intent, function (CardPaymentIntent $fresh) use ($confirmedCents, $by) {
            $sale = $this->saleOf($fresh);
            $refunds = CardRefund::where('card_payment_intent_id', $fresh->id)->get();
            $this->assertNotBlocked($fresh, $sale, $refunds);

            return $this->send($fresh, $sale, $this->refundable($fresh, $refunds), $confirmedCents, $by, ['kind' => CardRefund::KIND_FULL]);
        });
    }

    /** What a person's correction of the sale says should go back. */
    public function refundCorrection(CardPaymentIntent $intent, int $confirmedCents, User $by): CardRefund
    {
        return $this->payments->locked($intent, function (CardPaymentIntent $fresh) use ($confirmedCents, $by) {
            $sale = $this->saleOf($fresh);
            $refunds = CardRefund::where('card_payment_intent_id', $fresh->id)->get();
            $this->assertNotBlocked($fresh, $sale, $refunds);
            $amount = $this->correctionRefund($fresh, $sale, $refunds);
            if ($amount <= 0) {
                throw new DomainException(__('This adjustment has nothing left to refund.'));
            }

            return $this->send($fresh, $sale, $amount, $confirmedCents, $by, ['kind' => CardRefund::KIND_CORRECTION]);
        });
    }

    /** A person checked Payrallel's portal and says whether an unanswered refund happened. */
    public function resolve(CardRefund $refund, bool $wentThrough, User $by): CardRefund
    {
        return $this->payments->locked($refund->intent, function (CardPaymentIntent $fresh) use ($refund, $wentThrough, $by) {
            $refund->refresh();
            if (! in_array($refund->state, CardRefund::OPEN_STATES, true)) {
                throw new DomainException(__('That refund is already settled.'));
            }
            if ($refund->state === CardRefund::STATE_SENDING && $refund->created_at?->gte(Carbon::now()->subMinutes(2))) {
                throw new DomainException(__('That refund is still being sent. Try again in a moment.'));
            }
            $refund->update(['resolved_by_name' => $by->name, 'resolved_at' => Carbon::now()]);
            if ($wentThrough) {
                return $this->confirmed($fresh, $this->saleOf($fresh), $refund, null);
            }
            $refund->update(['state' => CardRefund::STATE_REFUSED, 'error' => __('Not in Payrallel, per :name', ['name' => $by->name])]);

            return $refund;
        });
    }

    /** Caller holds the lock and has checked the charge may be refunded. */
    private function send(CardPaymentIntent $intent, ?VendTransaction $sale, int $amount, int $confirmedCents, User $by, array $what): CardRefund
    {
        if ($amount !== $confirmedCents) {
            throw new DomainException(__('The amount changed to :amount since this page loaded. Check it and confirm again.', ['amount' => '$'.number_format($amount / 100, 2)]));
        }

        $refund = CardRefund::create($what + [
            'card_payment_intent_id' => $intent->id,
            'vend_transaction_id' => $sale?->id,
            'amount_cents' => $amount,
            'state' => CardRefund::STATE_SENDING,
            'requested_by' => $by->id,
            'requested_by_name' => $by->name,
        ]);
        $terminal = $this->payments->terminalOf($intent);

        try {
            $answer = $this->gateways->for($terminal)->refund($terminal, $this->chargeId($intent), $amount, $intent->custom_order_id);
        } catch (CardTerminalException $e) {
            $refund->update([
                'state' => $e->mayHaveReachedTerminal ? CardRefund::STATE_UNCERTAIN : CardRefund::STATE_REFUSED,
                'error' => mb_substr($e->getMessage(), 0, 500),
            ]);
            Log::warning('T05 refund did not complete', ['refund' => $refund->id, 'intent' => $intent->id, 'state' => $refund->state, 'error' => $e->getMessage()]);

            return $refund;
        }

        if ($answer->status === TerminalTransaction::APPROVED) {
            return $this->confirmed($intent, $sale, $refund, $answer);
        }
        $refund->update([
            'state' => $answer->status === TerminalTransaction::DECLINED ? CardRefund::STATE_REFUSED : CardRefund::STATE_UNCERTAIN,
            'error' => "Payrallel answered status {$answer->providerStatus}",
            'response' => $answer->raw,
        ]);

        return $refund;
    }

    /** The money went back. */
    private function confirmed(CardPaymentIntent $intent, ?VendTransaction $sale, CardRefund $refund, ?TerminalTransaction $answer): CardRefund
    {
        // One unit: a run dying between the two writes would leave the refund done but the intent's
        // refundable amount too high (audit SF2-03).
        DB::transaction(function () use ($intent, $refund, $answer) {
            $refund->update([
                'state' => CardRefund::STATE_REFUNDED,
                'error' => null,
                'provider_refund_id' => $answer?->providerTxnId,
                'response' => $answer?->raw ?? $refund->response,
            ]);
            $intent->increment('refunded_cents', $refund->amount_cents);
        });
        $this->events->record('refund.done', [
            'refund_id' => $refund->id, 'amount_cents' => $refund->amount_cents, 'kind' => $refund->kind, 'by' => $refund->requested_by_name,
        ], $intent->terminal, $intent->custom_order_id);

        if ($sale !== null) {
            try {
                $this->writeToSale($intent, $sale, $refund);
            } catch (\Throwable $e) {
                report($e);
                Log::error('T05 refund done but its sale or claim was not updated', ['refund' => $refund->id, 'sale' => $sale->id, 'error' => $e->getMessage()]);
            }
        }

        return $refund;
    }

    private function writeToSale(CardPaymentIntent $intent, VendTransaction $sale, CardRefund $refund): void
    {
        // A correction's refund gives back money for goods that never left: its rows are already gone.
        if ($refund->kind !== CardRefund::KIND_CORRECTION) {
            $items = $sale->vendTransactionItems()->where('is_refunded', false);
            if ($refund->kind === CardRefund::KIND_ITEM) {
                $items->where('product_id', $refund->product_id)->where('unit_price_amount', $refund->unit_cents)->limit((int) $refund->qty);
            }
            $items->get()->each(fn ($item) => $item->forceFill(['is_refunded' => true])->save());
        }

        $net = (int) $intent->captured_cents - (int) $intent->refunded_cents;
        $this->audit->applyRefund($sale, $net, [
            'refund_id' => $refund->id,
            'amount_cents' => $refund->amount_cents,
            'product_id' => $refund->product_id,
            'qty' => $refund->qty,
            'by' => $refund->requested_by_name,
            'at' => Carbon::now()->toIso8601String(),
        ]);

        // The claim's log says it either way; only nothing left crosses its verdict, the same as any
        // other refund of the whole charge. A partial one blocks Approve instead (RefundController).
        $what = match ($refund->kind) {
            CardRefund::KIND_ITEM => "{$refund->qty} × {$refund->product_name}",
            CardRefund::KIND_CORRECTION => 'a qty adjustment of the sale',
            default => 'the rest of the charge',
        };
        foreach ($this->claims($sale)->get() as $ticket) {
            $this->tickets->log($ticket, 't05_refund', $ticket->status, $ticket->status,
                sprintf('T05 refund of $%s through Payrallel: %s.', number_format($refund->amount_cents / 100, 2), $what),
                (string) $refund->requested_by_name, $refund->requested_by);
        }
        if ($net <= 0) {
            $this->tickets->markAutoRefundedByCharge($sale->order_id, $sale->payment_gateway_log_id, $sale->id);
        }
    }

    private function assertNotBlocked(CardPaymentIntent $intent, ?VendTransaction $sale, Collection $refunds): void
    {
        if (($reason = $this->blockedReason($intent, $sale, $refunds)) !== null) {
            throw new DomainException($reason);
        }
    }

    /** Why nothing may be refunded on this charge right now, in the order a person would fix it. */
    private function blockedReason(CardPaymentIntent $intent, ?VendTransaction $sale, Collection $refunds): ?string
    {
        if ($intent->state !== CardPaymentIntent::STATE_CAPTURED || (int) $intent->captured_cents <= 0) {
            return __('There is no captured T05 charge to refund.');
        }
        if ($refunds->contains(fn (CardRefund $r) => in_array($r->state, CardRefund::OPEN_STATES, true))) {
            return __('A refund on this charge has no answer yet. Check Payrallel\'s portal and settle it first.');
        }
        if ($this->refundable($intent, $refunds) <= 0) {
            return __('This charge is fully refunded.');
        }
        if ($sale?->audit_pending_at !== null) {
            return __('This sale is still waiting for its AI audit.');
        }
        if ($sale !== null && $sale->is_refunded) {
            return __('This sale is already marked refunded.');
        }
        if ($this->chargeIdOrNull($intent) === null) {
            return __('Payrallel\'s ID for this charge is not on record, so it can only be refunded in their portal.');
        }
        if (! config('payrallel.refund_same_day') && $intent->captured_at?->isToday()) {
            return __('Payrallel cannot refund a charge on the day it was made. Available from :date.', ['date' => $intent->captured_at->copy()->addDay()->toDateString()]);
        }
        if ($sale !== null && ($claim = $this->claims($sale)->whereIn('status', [...RefundTicket::ACTIVE_REFUND_STATUSES, RefundTicket::STATUS_VERIFIED])->first())) {
            return __('Claim :reference is already being paid another way (:status). Reject it first, or the customer is paid twice.', ['reference' => $claim->reference, 'status' => $claim->status]);
        }

        return null;
    }

    /** Every claim on this sale. */
    private function claims(VendTransaction $sale)
    {
        return RefundTicket::query()->where(function ($q) use ($sale) {
            $q->where('vend_transaction_id', $sale->id);
            if ($sale->order_id) {
                $q->orWhere('order_id', $sale->order_id);
            }
        });
    }

    /** What may still go back: an unanswered refund counts until a person settles it. */
    private function refundable(CardPaymentIntent $intent, Collection $refunds): int
    {
        $open = (int) $refunds->filter(fn (CardRefund $r) => in_array($r->state, CardRefund::OPEN_STATES, true))->sum('amount_cents');

        return max(0, (int) $intent->captured_cents - (int) $intent->refunded_cents - $open);
    }

    /** What was collected above what a person's correction says the goods are worth. */
    private function correctionRefund(CardPaymentIntent $intent, ?VendTransaction $sale, Collection $refunds): int
    {
        $value = ((array) $sale?->meta_json)['correction']['value_cents'] ?? null;
        if ($value === null) {
            return 0;
        }

        return max(0, min((int) $intent->captured_cents - (int) $intent->refunded_cents - (int) $value, $this->refundable($intent, $refunds)));
    }

    private function chargeIdOrNull(CardPaymentIntent $intent): ?string
    {
        // In `sale` mode the tap itself is the charge; in `preauth` the hold's id must never be used.
        return $intent->capture_txn_id ?: ($intent->mode === CardPaymentIntent::MODE_SALE ? $intent->provider_txn_id : null);
    }

    private function chargeId(CardPaymentIntent $intent): string
    {
        return (string) $this->chargeIdOrNull($intent);
    }

    private function saleOf(CardPaymentIntent $intent): ?VendTransaction
    {
        // Unscoped: whether the sale is written to must not depend on who pressed the button.
        $vend = Vend::withoutGlobalScopes()->find($intent->vend_id);

        return $intent->session_ref && $vend
            ? $this->sales->find($vend, $intent->session_ref, $intent->created_at ?? Carbon::now())
            : null;
    }

    /**
     * The basket that was charged, one line per product and price: the sale's item rows, the same
     * basket the AI audit left and the invoice lists. A single-unit sale has none.
     *
     * @return list<array{product_id: int, name: string, code: ?string, qty: int, unit_cents: int, refundable_qty: int}>
     */
    private function lines(CardPaymentIntent $intent, ?VendTransaction $sale, Collection $refunds): array
    {
        if ($sale === null) {
            return [];
        }
        $groups = [];
        $items = $sale->is_multiple ? $sale->vendTransactionItems()->get() : collect();
        if ($items->isEmpty()) {
            $qty = max(1, (int) $sale->qty);
            if ((int) $sale->product_id > 0) {
                $groups[] = ['product_id' => (int) $sale->product_id, 'qty' => $qty, 'unit_cents' => intdiv((int) $intent->captured_cents, $qty)];
            }
        } else {
            foreach ($items as $item) {
                $key = $item->product_id.':'.(int) $item->unit_price_amount;
                $groups[$key] ??= ['product_id' => (int) $item->product_id, 'qty' => 0, 'unit_cents' => (int) $item->unit_price_amount];
                $groups[$key]['qty']++;
            }
        }

        $products = Product::withoutGlobalScopes()->whereIn('id', array_column($groups, 'product_id'))->get(['id', 'name', 'code'])->keyBy('id');
        $counted = $refunds->filter(fn (CardRefund $r) => $r->kind === CardRefund::KIND_ITEM && in_array($r->state, CardRefund::COUNTED_STATES, true));

        // Nothing left of the charge: every unit has gone back, whichever refund took it.
        $allGone = (int) $intent->refunded_cents >= (int) $intent->captured_cents;

        return array_values(array_map(function (array $g) use ($products, $counted, $allGone) {
            $gone = $allGone ? $g['qty'] : (int) $counted->where('product_id', $g['product_id'])->where('unit_cents', $g['unit_cents'])->sum('qty');

            return $g + [
                'name' => (string) ($products[$g['product_id']]->name ?? 'Item'),
                'code' => $products[$g['product_id']]->code ?? null,
                'refundable_qty' => max(0, $g['qty'] - $gone),
            ];
        }, $groups));
    }
}
