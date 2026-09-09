<?php

namespace App\Services;

use App\Models\Operator;
use App\Models\PaymentGatewayLog;
use App\Models\PaymentGateways\Fiuu;
use App\Models\PaymentGateways\Midtrans;
use App\Models\PaymentGateways\Omise;
use App\Models\PaymentMethod;
use App\Models\Vend;
use App\Models\VendTransaction;
use App\Models\VendTransactionItem;
use App\Services\Sales\PreCreatedSaleFactory;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Creates a PROVISIONAL vend_transaction the moment a gateway payment is marked
 * paid — before the machine reports its TRADE. The row is:
 *
 *   settlement_status       = PENDING  (excluded from every sales aggregation
 *                                        via the shared settledSql() gate)
 *   is_found_in_transaction = false    (no TRADE yet)
 *
 * It later transitions:
 *   - dispense confirmed (GetPurchaseConfirm)  → PENDING -> SETTLED
 *   - TRADE arrives (VendTransactionService)   → fills machine ground truth,
 *                                                 is_found_in_transaction = true
 *   - refunded (RefundOmiseJob)                → SETTLED/PENDING -> REFUNDED
 *
 * Product / unit-cost / GST mapping mirrors VendTransactionService exactly so
 * revenue and gross profit are identical to what the TRADE path would compute —
 * settling later never moves the money figures.
 *
 * Idempotent: keyed on (order_id, vend_id), which carries a UNIQUE constraint.
 * If a row already exists (duplicate webhook, or a TRADE that somehow beat us),
 * we never downgrade it — we leave the existing row untouched.
 */
class GatewayVendTransactionService
{
    /**
     * @return VendTransaction|null The pre-created (or already-existing) row, or
     *                              null if it couldn't/shouldn't be created.
     */
    public function createFromPaymentGatewayLog(PaymentGatewayLog $log): ?VendTransaction
    {
        if (! $log->vend_id) {
            return null;
        }

        $vend = Vend::withoutGlobalScopes()
            ->with([
                'customer.locationType',
                'customer.operator',
                'operator.country',
                'vendContract',
                'vendModel',
                'vendPrefix',
                'vendChannels',
                'productMapping.productMappingItems.product.unitCosts',
                'productMapping.productMappingItems.product.operator',
            ])
            ->find($log->vend_id);

        if (! $vend) {
            return null;
        }

        $orderId = $this->canonicalOrderId($log);

        // Idempotency: bypass global (operator) scopes — this runs in a queue
        // with no authenticated user. Never clobber a row that already exists.
        $existing = VendTransaction::withoutGlobalScopes()
            ->where('order_id', $orderId)
            ->where('vend_id', $vend->id)
            ->first();

        if ($existing) {
            // Already present (TRADE-first, or a repeated paid webhook). Make
            // sure it is at least linked back to this PG log, then leave it be.
            if (! $existing->payment_gateway_log_id) {
                $existing->forceFill(['payment_gateway_log_id' => $log->id])->save();
            }

            return $existing;
        }

        $channels = $this->channelsFromLog($log);
        $isMultiple = count($channels) > 1;
        $amountCents = $this->toMinorUnits((float) $log->amount, $vend->operator);
        $paymentMethod = $this->resolvePaymentMethod($log);
        $approvedAt = $log->approved_at ? Carbon::parse($log->approved_at) : Carbon::now();

        $productMappingItems = $vend->productMapping
            ? $vend->productMapping->productMappingItems->keyBy('channel_code')
            : collect();

        // Parent-row product mapping: single = the one channel; multi = none
        // (per-item detail lives on vend_transaction_items, matching the TRADE path).
        $parentMap = (! $isMultiple && isset($channels[0]))
            ? $this->mapChannel($vend, $channels[0], $productMappingItems)
            : ['product' => null, 'mappingItem' => null, 'vendChannel' => null, 'unitCostId' => null, 'unitCostValue' => 0, 'gstVatRate' => 0];

        // Mapped product → its operator's rate; unmapped basket → the machine
        // operator's rate (a rate of 0 would book the GST as revenue, Part 4 item 9).
        $gstVatRate = $parentMap['product'] ? $parentMap['gstVatRate'] : PreCreatedSaleFactory::operatorGstRate($vend);
        $unitCostValue = $parentMap['unitCostValue'];
        $money = PreCreatedSaleFactory::money($amountCents, (float) $gstVatRate, (float) $unitCostValue);

        $vendChannelCode = ! $isMultiple && isset($channels[0]['code']) ? (int) $channels[0]['code'] : 0;
        $vendChannelId = $parentMap['vendChannel']?->id ?? 0; // column is NOT NULL → 0 placeholder for multi/unmapped

        try {
            return DB::transaction(function () use (
                $vend, $orderId, $approvedAt, $amountCents, $isMultiple, $paymentMethod,
                $channels, $parentMap, $money,
                $vendChannelCode, $vendChannelId, $log, $productMappingItems
            ) {
                // Machine-derived columns + meta come from the shared rail factory
                // (one definition with the NETS-orphan rail); the basket mapping is
                // this rail's own.
                $transaction = VendTransaction::create(array_merge(
                    PreCreatedSaleFactory::vendAttributes($vend),
                    $money,
                    [
                        'transaction_datetime' => $approvedAt,
                        'amount' => $amountCents,
                        'is_zero_amount' => $amountCents == 0,
                        'order_id' => $orderId,
                        'interface_type' => is_numeric($log->txn_src) ? (int) $log->txn_src : null,
                        'is_multiple' => $isMultiple,
                        'is_payment_received' => true,
                        'items_json' => $channels,
                        'payment_method_id' => $paymentMethod?->id,
                        'qty' => max(count($channels), 1),
                        'success_qty' => 0,   // unknown until dispense/TRADE
                        'dispensed_qty' => 0, // unknown until dispense/TRADE
                        'vend_channel_code' => $vendChannelCode,
                        'vend_channel_id' => $vendChannelId,
                        'vend_channel_error_id' => null, // pending — settlement_status keeps it out of sales
                        'vend_transaction_json' => null, // filled by TRADE
                        'product_id' => $parentMap['product']?->id,
                        'product_mapping_item_id' => $parentMap['mappingItem']?->id,
                        'unit_cost_id' => $parentMap['unitCostId'],
                        'label_json' => null, // campaign labels arrive with TRADE
                        'meta_json' => PreCreatedSaleFactory::meta($vend, ['source' => 'gateway_precreate']),
                        'payment_gateway_log_id' => $log->id,
                        'is_found_in_transaction' => false,
                        'settlement_status' => VendTransaction::SETTLEMENT_PENDING,
                    ]
                ));

                if ($isMultiple) {
                    foreach ($channels as $channel) {
                        $map = $this->mapChannel($vend, $channel, $productMappingItems);
                        VendTransactionItem::create([
                            'is_refunded' => false,
                            'product_id' => $map['product']?->id,
                            'product_mapping_item_id' => $map['mappingItem']?->id,
                            'unit_cost_id' => $map['unitCostId'],
                            'unit_cost' => $map['unitCostValue'],
                            'unit_price_amount' => $map['vendChannel']?->amount ?? 0,
                            'vend_channel_id' => $map['vendChannel']?->id ?? 0,
                            'vend_channel_code' => $channel['code'] ?? null,
                            'vend_channel_error_code' => null, // pending
                            'vend_channel_error_id' => null,
                            'vend_transaction_id' => $transaction->id,
                        ]);
                    }
                }

                return $transaction;
            }, 3);
        } catch (\Throwable $e) {
            // A unique-key clash means a concurrent TRADE/webhook already created
            // the row — that's fine, fetch and return it rather than failing.
            $raced = VendTransaction::withoutGlobalScopes()
                ->where('order_id', $orderId)
                ->where('vend_id', $vend->id)
                ->first();

            if ($raced) {
                return $raced;
            }

            Log::error('GatewayVendTransactionService: failed to pre-create vend_transaction', [
                'payment_gateway_log_id' => $log->id,
                'order_id' => $orderId,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Apply the same order_id transformation the TRADE path applies, so the
     * pre-created row and the eventual TRADE share one canonical key. TXN_SRC 50
     * order ids are prefixed with `yM`-style digits in VendTransactionService.
     */
    private function canonicalOrderId(PaymentGatewayLog $log): string
    {
        $orderId = (string) $log->order_id;

        if ((string) $log->txn_src === '50') {
            $now = Carbon::now();
            $orderId = $now->format('y').($now->format('m'))[0].$orderId;
        }

        return $orderId;
    }

    /**
     * Normalise vend_channels_json into a plain list of channel arrays. Each
     * element always has a 'code'; product info may be present or null.
     */
    private function channelsFromLog(PaymentGatewayLog $log): array
    {
        $raw = $log->vend_channels_json;

        if (is_string($raw)) {
            $raw = json_decode($raw, true) ?: [];
        }

        return array_values(array_filter((array) $raw, fn ($c) => is_array($c) && isset($c['code'])));
    }

    /**
     * Resolve the PaymentMethod for this gateway payment, mirroring
     * PaymentController::processPayment(): map the gateway's method name back to
     * a payment-method code, then load the PaymentMethod by that code.
     */
    private function resolvePaymentMethod(PaymentGatewayLog $log): ?PaymentMethod
    {
        if (! $log->paymentGateway()->exists() || ! $log->method) {
            return null;
        }

        $code = null;
        switch ($log->paymentGateway->name) {
            case 'midtrans':
                $code = array_search($log->method, Midtrans::PAYMENT_METHOD_MAPPING, true);
                break;
            case 'omise':
                $code = array_search($log->method, Omise::PAYMENT_METHOD_MAPPING, true);
                break;
            case 'fiuu':
                $code = array_search($log->method, Fiuu::PAYMENT_METHOD_MAPPING, true);
                break;
        }

        if ($code === false || $code === null) {
            return null;
        }

        return PaymentMethod::where('code', $code)->first();
    }

    /**
     * Resolve product / vend channel / current unit cost / GST for one channel,
     * mirroring VendTransactionService::processMapping().
     */
    private function mapChannel(Vend $vend, array $channel, $productMappingItems): array
    {
        $code = $channel['code'] ?? null;
        $vendChannel = $code !== null ? $vend->vendChannels->firstWhere('code', $code) : null;

        $product = null;
        $unitCostId = null;
        $unitCostValue = 0;
        $gstVatRate = 0;

        $mappingItem = $code !== null ? $productMappingItems->get($code) : null;
        if ($mappingItem && $mappingItem->product) {
            $product = $mappingItem->product;
            $unitCost = $product->unitCosts->where('is_current', true)->first();
            if ($unitCost) {
                $unitCostId = $unitCost->id;
                $unitCostValue = $unitCost->cost * 100;
            }
            $gstVatRate = $product->operator ? $product->operator->gst_vat_rate : 0;
        }

        return [
            'product' => $product,
            // Returned whenever the channel resolves to a planogram row, even if
            // that row has no product — the mapping row itself is still the
            // correct provenance for the sale.
            'mappingItem' => $mappingItem,
            'vendChannel' => $vendChannel,
            'unitCostId' => $unitCostId,
            'unitCostValue' => $unitCostValue,
            'gstVatRate' => $gstVatRate,
        ];
    }

    /**
     * Convert the gateway's major-unit amount (dollars) to the minor units
     * (cents) that vend_transactions.amount stores.
     */
    private function toMinorUnits(float $major, ?Operator $operator): int
    {
        $exponent = $operator?->country?->currency_exponent ?? 2;

        return (int) round($major * pow(10, $exponent));
    }
}
