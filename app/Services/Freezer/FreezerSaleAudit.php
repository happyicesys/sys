<?php

namespace App\Services\Freezer;

use App\Jobs\Vend\SyncVendTransactionTotalsJson;
use App\Models\CardPaymentIntent;
use App\Models\Product;
use App\Models\Vend;
use App\Models\VendChannel;
use App\Models\VendChannelError;
use App\Models\VendTransaction;
use App\Models\VendTransactionItem;
use App\Services\Sales\LateTradeTracker;
use App\Services\SmartFreezer\FreezerRecognitionService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Keeps a smart-freezer sale out of the sales figures until its camera AI has audited the door
 * session — and writes the audited figures when it has (Brian, 2026-10-05: "the trade row cannot
 * be calculate as sales total and qty total bcz ai recognition havent done the actual audit").
 *
 * WHY a freezer sale is provisional at all: the customer pays for a cart, the door unlocks, and
 * what actually left the cabinet is decided afterwards by the AI. On a T05 (remote card rail) the
 * money follows that verdict — the hold is captured at the audited amount, which can be less than,
 * equal to, or more than the cart ({@see \App\Services\CardTerminal\AiCaptureDecision}). So until
 * the verdict lands, the amount on the TRADE row is a proposal, not a sale.
 *
 * HOW it is excluded: `settlement_status = PENDING` — "paid, dispense outcome not yet known",
 * which is already the one gate every sales, revenue, GP and qty aggregate applies
 * (VendTransaction::scopeCountsAsSale / settledSql / scopeIsSuccessful) and which the nightly
 * rollup inherits. No aggregate needed changing. `audit_pending_at` records why it is pending.
 * The row stays visible in Sales Transactions throughout, with its amount shown as pending.
 *
 * SCOPE — T05 only (Brian, 2026-10-05: "qr and nets still using the old fashioned way, only those
 * binded with t05"). A QR or NETS freezer sale is charged in full at door close and cannot be
 * re-charged or part-refunded, so there is nothing for an audit to settle: those rows count
 * immediately, exactly as they do today, and a disagreeing AI shows up as a badge for ops
 * instead of moving money.
 *
 * A session the AI can NEVER audit is not held either: with no barcoded product on the cabinet's
 * planogram the recognition is refused before it is ever sent
 * ({@see FreezerRecognitionService::blocker()}), so holding the row would park real revenue until
 * the 72 h backstop for nothing.
 *
 * THE BASKET FOLLOWS THE CHARGE (Brian, 2026-10-06). Settling writes what was charged AND, when
 * the AI's verdict differs from the cart, rebuilds the sale's item rows into the basket that was
 * charged for — one row per unit — so Total Qty and product attribution agree with the money
 * instead of still counting the cart. Both baskets are kept on the row (`meta_json.audit`), the
 * paid cart is still in `items_json` and the TRADE's `transf_info`, and Sales Transactions labels
 * the corrected amount "AI" so it is never read as the machine's own figure.
 *
 * Where Payrallel refused to raise an authorisation, the charge is the hold and the shortfall is
 * recorded in `card_payment_intents.owed_cents` — but the basket still follows the AI, because
 * those goods did leave the cabinet. Such a sale deliberately counts more units than its money
 * covers; the uncollected part is the owed amount, which the 08:30 health email reports.
 *
 * ORDER IS NOT GUARANTEED. The TRADE and the AI verdict arrive on separate paths, and a freezer
 * that was offline uploads its TRADE long after its charge was decided. Both orders end with the
 * charged amount on the row: {@see record()} holds a sale whose charge is still open and applies
 * one already decided, and {@see settle()} is idempotent, so the minute sweep can re-run it for a
 * charge whose sale had not arrived (or whose write failed) without double-counting.
 */
class FreezerSaleAudit
{
    public function __construct(
        private readonly FreezerRecognitionService $recognitions,
        private readonly LateTradeTracker $lateTrades,
    ) {}

    /**
     * A TRADE has just been written. Hold its amount while the T05 charge is still open, or — if
     * the charge was already decided before the TRADE arrived — write what was charged straight
     * away. Called inside the ingest transaction, so nothing is dispatched from here: the caller
     * resyncs the totals once committed.
     */
    public function record(Vend $vend, VendTransaction $sale): void
    {
        if (! $vend->isSmartFreezer() || ! $this->auditable($sale)) {
            return;
        }

        $sessionRef = $this->sessionRef($sale);
        if ($sessionRef === null) {
            return; // no kiosk session on the frame (freezer app < 20): nothing to tie an audit to
        }

        $intent = $this->holdFor($vend, $sessionRef);
        if ($intent === null) {
            return; // not a T05 sale: QR and NETS are charged in full at door close
        }

        if ($intent->state !== CardPaymentIntent::STATE_AWAITING_AI) {
            // The charge was decided while this TRADE was still on its way (an offline board, a
            // replayed outbox). There is nothing to wait for — book what was actually charged.
            // Amount only, never the basket: on the fresh ingest path this runs inside the
            // ingest transaction, BEFORE VendTransactionService writes the cart's item rows, so
            // a basket written here would be buried under them. A charge decided before its sale
            // existed can only ever be the cart total anyway (CardPaymentService::decideForAi
            // doubts any verdict with no sale), and settle() owns the basket.
            $this->writeAudited($sale, (int) $intent->captured_cents, $this->reasonOf($intent, 'charge already decided'), $intent, null);

            return;
        }

        // Nothing to wait for when this cabinet has no product the algorithm could be asked
        // about — the recognition would never be submitted.
        if ($this->recognitions->candidateCodes($vend) === []) {
            Log::info('freezer sale counted without an audit: no barcoded product on the planogram', [
                'vend' => $vend->code, 'sale' => $sale->id, 'session_ref' => $sessionRef,
            ]);

            return;
        }

        $sale->forceFill([
            'settlement_status' => VendTransaction::SETTLEMENT_PENDING,
            'audit_pending_at' => Carbon::now(),
        ])->save();

        Log::info('freezer sale held for its AI audit', [
            'vend' => $vend->code, 'sale' => $sale->id, 'session_ref' => $sessionRef,
            'intent' => $intent->id, 'cart_cents' => $sale->amount,
        ]);
    }

    /**
     * The hold's charge has been decided: write what was actually charged onto the sale and let
     * it count. `$chargedCents` is the one capture that went through, or 0 when the AI saw
     * nothing taken and the hold was released — a released hold is money never collected, so the
     * row is marked REFUNDED (never a sale) rather than settled at zero.
     *
     * Idempotent: only a sale still on hold is written, so the minute sweep may call this for
     * every recently-resolved intent. Returns the sale it settled, or null when there was none
     * (the TRADE has not arrived yet, or it was settled already).
     */
    public function settle(CardPaymentIntent $intent, int $chargedCents, string $reason): ?VendTransaction
    {
        $sale = $this->heldSale($intent);
        if ($sale === null) {
            return null;
        }

        $this->writeAudited(
            $sale,
            $chargedCents,
            $reason,
            $intent,
            $chargedCents > 0 ? $this->basketFollowingTheCharge($sale, $intent) : null,
        );

        // The sale's money moved after it was first written. If its day has already closed,
        // tonight's rollup must rebuild that day, and the Ops Dashboard totals need a resync.
        $this->lateTrades->noteLanded($sale);
        if ($vend = ($sale->vend ?? $intent->vend)) {
            SyncVendTransactionTotalsJson::dispatch($vend)->onQueue('default');
        }

        return $sale;
    }

    /** What was charged becomes the sale, and the sale counts again (or never does, at zero). */
    private function writeAudited(VendTransaction $sale, int $chargedCents, string $reason, ?CardPaymentIntent $intent, ?array $basket): void
    {
        $cart = (int) $sale->amount;
        $revenue = $chargedCents / (1.00 + ((float) $sale->gst_vat_rate / 100));
        $grossProfit = $revenue - (float) $sale->unit_cost;

        // One transaction: a failure between the amount and the basket would leave a sale with
        // money charged and no item rows at all — counted for nothing.
        DB::transaction(function () use ($sale, $chargedCents, $reason, $intent, $basket, $cart, $revenue, $grossProfit) {
            $sale->forceFill([
                'amount' => $chargedCents,
                'is_zero_amount' => $chargedCents === 0,
                'revenue' => $revenue,
                'gross_profit' => $grossProfit,
                'gross_profit_margin' => $revenue ? (($grossProfit * 100) / $revenue) : 0,
                'settlement_status' => $chargedCents > 0
                    ? VendTransaction::SETTLEMENT_SETTLED
                    : VendTransaction::SETTLEMENT_REFUNDED,
                'audit_pending_at' => null,
                'meta_json' => array_merge((array) $sale->meta_json, ['audit' => array_filter([
                    'source' => 'smart_freezer_ai',
                    'settled_at' => Carbon::now()->toIso8601String(),
                    'cart_cents' => $cart,
                    'charged_cents' => $chargedCents,
                    'reason' => $reason,
                    'intent_id' => $intent?->id,
                    'session_ref' => $intent?->session_ref,
                    // The label ops read: set only when the basket was actually changed, and carrying
                    // both sides of it. The paid cart itself is still on the row, untouched, in
                    // `items_json` and the TRADE's own `transf_info`.
                    'basket_paid' => $basket !== null ? $this->paidUnits($sale) : null,
                    'basket_charged' => $basket,
                ], fn ($v) => $v !== null)]),
            ])->save();

            if ($basket !== null) {
                $this->rewriteBasket($sale, $basket);
            }
        });

        Log::info('freezer sale settled by its AI audit', [
            'sale' => $sale->id, 'intent' => $intent?->id, 'cart_cents' => $cart,
            'charged_cents' => $chargedCents, 'reason' => $reason,
        ]);
    }

    /**
     * The basket the charge was valued from, when the sale's own basket should follow it — else
     * null, and the item rows are left exactly as the machine reported them.
     *
     * It comes from the decision that set the charge ({@see AiCaptureDecision}), never re-derived
     * from the verdict here: the money and the qty have to agree, and the decision alone knows
     * which units it counted (an `incomplete` session charges a product the AI was never asked
     * about as if taken). A charge that fell back to the cart total carries no basket, so a
     * backstop or an unusable AI answer never moves the qty either.
     *
     * Null as well when the AI agreed with the cart — there is nothing to change.
     *
     * @return array<int, int>|null product id => units charged for
     */
    private function basketFollowingTheCharge(VendTransaction $sale, CardPaymentIntent $intent): ?array
    {
        $basket = ((array) $intent->ai_decision)['basket'] ?? null;
        if (! is_array($basket) || $basket === []) {
            return null;
        }

        $charged = [];
        foreach ($basket as $productId => $units) {
            if ((int) $productId > 0 && (int) $units > 0) {
                $charged[(int) $productId] = (int) $units;
            }
        }
        if ($charged === []) {
            return null;
        }

        $paid = $this->paidUnits($sale);
        ksort($charged);
        ksort($paid);

        return $charged === $paid ? null : $charged;
    }

    /**
     * Make the sale's item rows the basket that was charged: one row per unit, so Total Qty counts
     * what the customer was charged for and product attribution follows it too.
     *
     * The paid cart is NOT lost — it stays on the header in `items_json` and in the TRADE's own
     * `transf_info`, and both sides are written to `meta_json.audit`. Safe because a sale is
     * audited once ({@see auditable}) within minutes of the TRADE, long before a refund ticket
     * could point at one of these rows.
     *
     * @param  array<int, int>  $basket  product id => units charged for
     */
    private function rewriteBasket(VendTransaction $sale, array $basket): void
    {
        $productIds = array_keys($basket);
        $templates = $sale->vendTransactionItems()->get()->groupBy('product_id');
        $saleCode = VendChannelError::forFrameCode(0, null, null);
        $units = array_sum($basket);

        // One query each for everything a new row needs, never one per unit.
        $channels = VendChannel::where('vend_id', $sale->vend_id)->whereIn('product_id', $productIds)
            ->orderBy('code')->get()->keyBy('product_id');
        $paidPrices = $this->paidUnitPrices($sale);
        $costs = Product::withoutGlobalScopes()->with(['unitCosts' => fn ($q) => $q->where('is_current', true)])
            ->whereIn('id', $productIds)->get()->keyBy('id');

        $sale->vendTransactionItems()->delete();

        foreach ($basket as $productId => $count) {
            $template = $templates->get($productId)?->first();
            $channel = $channels->get($productId);
            if ($template === null && $channel === null) {
                // The AI named a product this cabinet has no slot for. It was charged for, so it
                // has to count — with whatever can still be resolved about it.
                Log::warning('freezer audit basket names a product with no slot on the machine', [
                    'sale' => $sale->id, 'product' => $productId, 'units' => $count,
                ]);
            }

            // Priced the way the charge was priced (AiCaptureDecision): what the customer paid
            // for this product in this sale, else the machine's price for it today. Taking the
            // shelf price first would make the per-product figures disagree with the money.
            $unitPrice = (int) ($paidPrices[$productId]
                ?? $template?->unit_price_amount
                ?? $channel?->amount
                ?? 0);
            $cost = $costs->get($productId)?->unitCosts->first();

            for ($i = 0; $i < $count; $i++) {
                VendTransactionItem::create([
                    'vend_transaction_id' => $sale->id,
                    'is_refunded' => false,
                    'product_id' => $productId,
                    'product_mapping_item_id' => $template?->product_mapping_item_id,
                    // A single-unit cart carries no item row to copy, so the cost is resolved the
                    // way the ingest resolves it rather than left at zero, which would report the
                    // whole charge as profit.
                    'unit_cost_id' => $template?->unit_cost_id ?? $cost?->id,
                    'unit_cost' => $template?->unit_cost ?? ($cost ? $cost->cost * 100 : 0),
                    'unit_price_amount' => $unitPrice,
                    'vend_channel_id' => (int) ($template?->vend_channel_id ?? $channel?->id ?? 0),
                    'vend_channel_code' => $template?->vend_channel_code ?? $channel?->code,
                    // The goods left the cabinet: that is what the AI saw and what was charged.
                    'vend_channel_error_code' => 0,
                    'vend_channel_error_id' => $saleCode?->id,
                ]);
            }
        }

        // Per-unit item rows are how a basket is counted, so the row is a multiple from here on,
        // whatever the cart was — a single the AI saw two of has two rows now.
        $sale->forceFill([
            'is_multiple' => true,
            'qty' => $units,
            'success_qty' => $units,
            'dispensed_qty' => $units,
        ])->save();

        Log::info('freezer sale basket follows the charge', [
            'sale' => $sale->id, 'units' => $units, 'basket' => $basket,
        ]);
    }

    /**
     * The unit price the customer actually paid for each product in this sale, from the TRADE.
     *
     * @return array<int, int> product id => unit cents
     */
    private function paidUnitPrices(VendTransaction $sale): array
    {
        $prices = [];
        foreach ($this->transfInfo($sale) as $unit) {
            $productId = (int) ($unit['goods_id'] ?? 0);
            if ($productId > 0 && isset($unit['Price']) && is_numeric($unit['Price']) && (int) $unit['Price'] > 0) {
                $prices[$productId] = (int) $unit['Price'];
            }
        }

        return $prices;
    }

    /**
     * What the customer paid for, per product: the TRADE's `transf_info` is one entry per unit
     * with `goods_id` = mark1 product id.
     *
     * @return array<int, int>
     */
    private function paidUnits(VendTransaction $sale): array
    {
        $paid = [];
        foreach ($this->transfInfo($sale) as $unit) {
            $productId = (int) ($unit['goods_id'] ?? 0);
            if ($productId > 0) {
                $paid[$productId] = ($paid[$productId] ?? 0) + 1;
            }
        }

        return $paid;
    }

    /** The TRADE's per-unit lines, however the column hands them over. */
    private function transfInfo(VendTransaction $sale): array
    {
        $frame = $sale->vend_transaction_json;
        if (is_string($frame)) {
            $frame = json_decode($frame, true);
        }

        return (array) ((((array) $frame)['transf_info'] ?? null) ?: []);
    }

    /**
     * May this row's amount be rewritten by an audit? Only a plainly settled, never-audited sale:
     * a row the refund path owns (PENDING awaiting a refund, or REFUNDED) is not ours to move,
     * and one already audited must not be rewritten from a replayed frame.
     */
    private function auditable(VendTransaction $sale): bool
    {
        // A row this ingest has just inserted has not read the column's default back, so null
        // here means the default — SETTLED — not pending.
        $status = $sale->settlement_status ?? VendTransaction::SETTLEMENT_SETTLED;

        return (int) $status === VendTransaction::SETTLEMENT_SETTLED
            && ! isset(((array) $sale->meta_json)['audit']);
    }

    /** The held sale of this intent's door session, or null when there is nothing to settle. */
    private function heldSale(CardPaymentIntent $intent): ?VendTransaction
    {
        if ($intent->session_ref === null || $intent->vend_id === null) {
            return null;
        }

        return VendTransaction::withoutGlobalScopes()
            ->awaitingAudit()
            ->where('vend_id', $intent->vend_id)
            ->where('vend_transaction_json->SFREF', $intent->session_ref)
            ->orderBy('id')
            ->first();
    }

    /** This session's T05 hold, in whatever state it has reached. */
    private function holdFor(Vend $vend, string $sessionRef): ?CardPaymentIntent
    {
        return CardPaymentIntent::query()
            ->where('vend_id', $vend->id)
            ->where('session_ref', $sessionRef)
            ->orderByDesc('id')
            ->first();
    }

    /** The decision's own words, for the row's audit stamp. */
    private function reasonOf(CardPaymentIntent $intent, string $fallback): string
    {
        $reason = ((array) $intent->ai_decision)['reason'] ?? null;

        return is_string($reason) && $reason !== '' ? $reason : $fallback;
    }

    /** The kiosk session the TRADE names (freezer app 20+ carries SFREF). */
    private function sessionRef(VendTransaction $sale): ?string
    {
        $frame = $sale->vend_transaction_json;
        if (is_string($frame)) {
            $frame = json_decode($frame, true);
        }
        $ref = is_array($frame) ? ($frame['SFREF'] ?? null) : null;

        return is_string($ref) && $ref !== '' ? $ref : null;
    }
}
