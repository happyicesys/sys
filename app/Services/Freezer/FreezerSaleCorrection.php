<?php

namespace App\Services\Freezer;

use App\Models\CardPaymentIntent;
use App\Models\CardRefund;
use App\Models\Product;
use App\Models\RefundTicket;
use App\Models\SmartFreezerRecognition;
use App\Models\User;
use App\Models\Vend;
use App\Models\VendChannel;
use App\Models\VendTransaction;
use App\Services\CardTerminal\CardPaymentService;
use App\Services\SmartFreezer\FreezerSaleLocator;
use App\Support\FreezerTestSale;
use Carbon\Carbon;
use DomainException;

/**
 * A person says what really left the cabinet (AI Recognition → Correct; Brian, 2026-11-03: the table
 * is a control panel, "not only info"). The sale's items and the stock follow the count; the money
 * depends on where the sale stands:
 *
 *   hold still open (T05)  → that count is charged (CardPaymentService::chargeByPerson); 0 releases
 *   charged or released    → money does not move here. Too much taken: the refund panel offers the
 *                            difference and a person presses it. Too little: recorded as owed, since
 *                            a T05 charge cannot be topped up
 *   paid at the machine    → items and stock only; a refund goes through Refund Request
 *
 * The AI's own verdict is never overwritten: the correction sits beside it, on the sale
 * (`meta_json.correction`, each one kept in `meta_json.corrections`). The sale's amount stays what
 * was collected, so revenue follows the money and moves only when the money does.
 */
class FreezerSaleCorrection
{
    public const MODE_HOLD = 'hold';

    public const MODE_CHARGED = 'charged';

    public const MODE_PAID = 'paid';

    /** The most units a person may put on one product. */
    private const MAX_UNITS = 50;

    public function __construct(
        private readonly CardPaymentService $payments,
        private readonly FreezerSaleAudit $audit,
        private readonly FreezerStockLedger $ledger,
        private readonly FreezerSaleLocator $sales,
    ) {}

    /** @return array<string, mixed> everything the Correct screen shows */
    public function editor(SmartFreezerRecognition $recognition): array
    {
        $sale = $this->saleOf($recognition);
        $vend = $this->vendOf($recognition);
        $intent = $sale ? $this->intentOf($recognition) : null;
        $blocked = $this->blockedReason($sale, $vend, $intent);
        $mode = $this->mode($intent);

        $paid = $sale ? $this->paidUnits($sale) : [];
        $ai = [];
        foreach ((array) $recognition->verdict_lines as $line) {
            if ((int) ($line['product_id'] ?? 0) > 0) {
                $ai[(int) $line['product_id']] = (int) ($line['taken'] ?? 0);
            }
        }
        $current = $sale ? $this->currentBasket($sale) : [];
        $start = $mode === self::MODE_HOLD ? ($this->aiCounted($recognition) ? $ai : $paid) : $current;

        $machine = $vend ? VendChannel::where('vend_id', $vend->id)->where('is_active', true)->whereNotNull('product_id')->pluck('product_id')->map(fn ($id) => (int) $id)->all() : [];
        $ids = array_values(array_unique(array_filter(array_merge(array_keys($paid), array_keys($ai), array_keys($current), $machine))));
        $prices = $sale && $vend ? $this->unitPrices($sale, $vend, $intent, $ids) : [];
        $products = Product::withoutGlobalScopes()->whereIn('id', $ids)->get(['id', 'name', 'code'])->keyBy('id');

        $lines = array_map(fn (int $id) => [
            'product_id' => $id,
            'name' => (string) ($products[$id]->name ?? 'Item'),
            'code' => $products[$id]->code ?? null,
            'unit_cents' => $prices[$id] ?? null,
            'paid' => $paid[$id] ?? 0,
            'ai' => $ai[$id] ?? 0,
            'taken' => max(0, $start[$id] ?? 0),
        ], $ids);
        usort($lines, fn (array $a, array $b) => [$b['taken'] > 0 || $b['paid'] > 0 || $b['ai'] > 0, $a['name']] <=> [$a['taken'] > 0 || $a['paid'] > 0 || $a['ai'] > 0, $b['name']]);

        return [
            'recognition_id' => $recognition->id,
            'order_id' => $sale?->order_id,
            'mode' => $mode,
            'blocked_reason' => $blocked,
            'hold_cents' => $intent ? (int) $intent->amount_cents : null,
            'collected_cents' => $sale ? $this->collected($sale, $intent) : null,
            'lines' => $lines,
            'max_units' => self::MAX_UNITS,
        ];
    }

    /**
     * @param  array<int|string, int>  $taken  product id => units a person says left the cabinet
     * @return array{mode: string, intent: ?CardPaymentIntent, value_cents: int, to_refund_cents: int, owed_cents: int}
     *
     * @throws DomainException when this sale cannot be corrected, or the amount moved since the page loaded
     */
    public function correct(SmartFreezerRecognition $recognition, array $taken, int $confirmedCents, User $by): array
    {
        $basket = $this->normalise($taken);
        $sale = $this->saleOf($recognition);
        $vend = $this->vendOf($recognition);
        $intent = $sale ? $this->intentOf($recognition) : null;

        if ($this->mode($intent) === self::MODE_HOLD) {
            $this->assertCorrectable($sale, $vend, $intent);
            [$cents, $prices] = $this->value($basket, $sale, $vend, $intent, $confirmedCents);
            $intent = $this->payments->chargeByPerson($intent, $cents, $by, $basket, $prices);

            return ['mode' => self::MODE_HOLD, 'intent' => $intent, 'value_cents' => $cents, 'to_refund_cents' => 0, 'owed_cents' => (int) $intent->owed_cents];
        }

        if ($intent === null) {
            $this->assertCorrectable($sale, $vend, null);
            [$cents, $prices] = $this->value($basket, $sale, $vend, null, $confirmedCents);
            $this->write($sale, $basket, $prices, $cents, $by);

            return ['mode' => self::MODE_PAID, 'intent' => null, 'value_cents' => $cents, 'to_refund_cents' => 0, 'owed_cents' => 0];
        }

        return $this->payments->locked($intent, function (CardPaymentIntent $fresh) use ($basket, $sale, $vend, $confirmedCents, $by) {
            $this->assertCorrectable($sale->fresh(), $vend, $fresh);
            [$cents, $prices] = $this->value($basket, $sale, $vend, $fresh, $confirmedCents);
            $this->write($sale, $basket, $prices, $cents, $by);
            $this->payments->noteCorrection($fresh, $cents, $by);
            $collected = (int) $fresh->captured_cents - (int) $fresh->refunded_cents;

            return [
                'mode' => self::MODE_CHARGED,
                'intent' => $fresh->fresh(),
                'value_cents' => $cents,
                'to_refund_cents' => max(0, $collected - $cents),
                'owed_cents' => max(0, $cents - $collected),
            ];
        });
    }

    /** @param  array<int, int>  $prices */
    private function write(VendTransaction $sale, array $basket, array $prices, int $cents, User $by): void
    {
        $names = Product::withoutGlobalScopes()->whereIn('id', array_keys($basket))->pluck('name', 'id');

        $this->audit->applyCorrection($sale, $basket, $prices, [
            'value_cents' => $cents,
            'basket' => $basket,
            'lines' => array_map(fn (int $id, int $units) => [
                'product_id' => $id, 'name' => (string) ($names[$id] ?? 'Item'), 'qty' => $units, 'unit_cents' => $prices[$id] ?? 0,
            ], array_keys($basket), $basket),
            'by' => ['user_id' => $by->id, 'name' => $by->name],
            'at' => Carbon::now()->toIso8601String(),
        ]);
    }

    /**
     * What this count is worth, checked against what the person confirmed: a stale page must not
     * charge or refund something else.
     *
     * @param  array<int, int>  $basket
     * @return array{0: int, 1: array<int, int>}
     */
    private function value(array $basket, VendTransaction $sale, Vend $vend, ?CardPaymentIntent $intent, int $confirmedCents): array
    {
        $prices = $this->unitPrices($sale, $vend, $intent, array_keys($basket));
        $cents = 0;
        foreach ($basket as $productId => $units) {
            if (! isset($prices[$productId])) {
                throw new DomainException(__('A product in this count has no price, so it cannot be valued.'));
            }
            $cents += $units * $prices[$productId];
        }
        if ($cents !== $confirmedCents) {
            throw new DomainException(__('The amount changed to :amount since this page loaded. Check it and confirm again.', ['amount' => '$'.number_format($cents / 100, 2)]));
        }

        return [$cents, array_intersect_key($prices, $basket)];
    }

    /**
     * The price paid for a product in this sale, else the machine's price at the door session, the
     * Happy Hour one where a slot covered it. The same rule as the AI's own count.
     *
     * @param  list<int>  $productIds
     * @return array<int, int> product id => unit cents (positive only; no entry = no price)
     */
    private function unitPrices(VendTransaction $sale, Vend $vend, ?CardPaymentIntent $intent, array $productIds): array
    {
        if ($productIds === []) {
            return [];
        }
        if (FreezerTestSale::is($sale)) {
            return array_fill_keys($productIds, FreezerTestSale::UNIT_PRICE_CENTS);
        }
        $at = $intent?->door_closed_at ?? $sale->transaction_datetime;
        $shelf = $this->payments->shelfPrices($vend->id, $productIds, $at ? Carbon::parse($at) : null);
        $paid = array_filter($this->sales->paidUnitPrices($sale), fn ($cents) => (int) $cents > 0);

        return array_filter(array_intersect_key($paid + $shelf, array_flip($productIds)), fn ($cents) => (int) $cents > 0);
    }

    private function assertCorrectable(?VendTransaction $sale, ?Vend $vend, ?CardPaymentIntent $intent): void
    {
        if (($reason = $this->blockedReason($sale, $vend, $intent)) !== null) {
            throw new DomainException($reason);
        }
    }

    /** Why this sale cannot be corrected right now, in the order a person would fix it. */
    private function blockedReason(?VendTransaction $sale, ?Vend $vend, ?CardPaymentIntent $intent): ?string
    {
        if ($sale === null || $vend === null || ! $vend->isSmartFreezer()) {
            return __('This session has no freezer sale matched to it yet.');
        }
        if ($sale->is_refunded) {
            return __('This sale is already marked refunded.');
        }
        if ($intent !== null) {
            $decision = (array) $intent->ai_decision;
            if (isset($decision['uncertain'])) {
                return __('A charge on this hold may have gone through. Check Payrallel\'s portal and settle it first.');
            }
            $reason = match ($intent->state) {
                CardPaymentIntent::STATE_AWAITING_AI => isset($decision['action'])
                    ? __('This charge has been decided and is being sent. Try again in a moment.')
                    : ($this->payments->personNeededBecause($intent) === null ? __('The AI still has time to answer this session.') : null),
                CardPaymentIntent::STATE_CAPTURED, CardPaymentIntent::STATE_VOIDED => null,
                default => __('This card payment is not finished, so there is nothing to correct yet.'),
            };
            if ($reason !== null) {
                return $reason;
            }
            $refunds = CardRefund::where('card_payment_intent_id', $intent->id)->get();
            if ($refunds->contains(fn (CardRefund $r) => in_array($r->state, CardRefund::OPEN_STATES, true))) {
                return __('A refund on this charge has no answer yet. Check Payrallel\'s portal and settle it first.');
            }
            // Item and whole refunds mark the sale's rows, which a correction rebuilds.
            if ($refunds->contains(fn (CardRefund $r) => $r->state === CardRefund::STATE_REFUNDED && $r->kind !== CardRefund::KIND_CORRECTION)) {
                return __('This charge already has an item or full refund, so it can no longer be corrected.');
            }
        }

        // A claim's item rows point at the rows a correction rebuilds.
        $claim = RefundTicket::query()
            ->where(fn ($q) => $q->where('vend_transaction_id', $sale->id)->when($sale->order_id, fn ($o) => $o->orWhere('order_id', $sale->order_id)))
            ->where('status', '<>', RefundTicket::STATUS_REJECTED)
            ->first();
        if ($claim) {
            return __('Claim :reference is on this sale (:status). Settle or reject it first.', ['reference' => $claim->reference, 'status' => $claim->status]);
        }

        return null;
    }

    private function mode(?CardPaymentIntent $intent): string
    {
        return match (true) {
            $intent === null => self::MODE_PAID,
            $intent->state === CardPaymentIntent::STATE_AWAITING_AI => self::MODE_HOLD,
            default => self::MODE_CHARGED,
        };
    }

    /** The money this sale has collected so far. */
    private function collected(VendTransaction $sale, ?CardPaymentIntent $intent): int
    {
        if ($intent === null) {
            return (int) $sale->amount;
        }

        return max(0, (int) $intent->captured_cents - (int) $intent->refunded_cents);
    }

    /** @return array<int, int> what the sale counts now */
    private function currentBasket(VendTransaction $sale): array
    {
        $meta = (array) $sale->meta_json;
        if (isset($meta['correction']['basket'])) {
            return array_map('intval', (array) $meta['correction']['basket']);
        }
        $items = $sale->vendTransactionItems()->get();
        if ($items->isEmpty()) {
            return $this->paidUnits($sale) ?: ((int) $sale->product_id > 0 ? [(int) $sale->product_id => max(1, (int) $sale->qty)] : []);
        }

        return $items->countBy(fn ($item) => (int) $item->product_id)->all();
    }

    /** @return array<int, int> */
    private function paidUnits(VendTransaction $sale): array
    {
        return $this->audit->paidUnits($sale);
    }

    /** A verdict that counted the goods: starting from it saves the person typing the AI's answer. */
    private function aiCounted(SmartFreezerRecognition $recognition): bool
    {
        return in_array($recognition->verdict, ['match', 'took_less', 'took_more', 'mixed'], true);
    }

    /**
     * @param  array<int|string, int>  $taken
     * @return array<int, int> only what was taken, sorted so the same count always reads the same
     */
    private function normalise(array $taken): array
    {
        $basket = [];
        foreach ($taken as $productId => $units) {
            if ((int) $productId > 0 && (int) $units > 0) {
                $basket[(int) $productId] = min((int) $units, self::MAX_UNITS);
            }
        }
        ksort($basket);

        return $basket;
    }

    private function saleOf(SmartFreezerRecognition $recognition): ?VendTransaction
    {
        return $recognition->vend_transaction_id
            ? VendTransaction::withoutGlobalScopes()->find($recognition->vend_transaction_id)
            : null;
    }

    private function vendOf(SmartFreezerRecognition $recognition): ?Vend
    {
        return $recognition->vend_id ? Vend::withoutGlobalScopes()->find($recognition->vend_id) : null;
    }

    private function intentOf(SmartFreezerRecognition $recognition): ?CardPaymentIntent
    {
        return $recognition->session_ref
            ? CardPaymentIntent::query()->where('vend_id', $recognition->vend_id)->where('session_ref', $recognition->session_ref)->orderByDesc('id')->first()
            : null;
    }
}
