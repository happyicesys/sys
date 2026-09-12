<?php

namespace App\Services\Sales;

use App\Models\CardSettlementRow;
use App\Models\PaymentMethod;
use App\Models\Vend;
use App\Models\VendChannelError;
use App\Models\VendTransaction;
use App\Support\AutoRefundSource;
use Carbon\Carbon;

/**
 * What every sale a PAYMENT RAIL pre-creates before the machine reports has
 * in common. Two rails write such rows:
 *
 *   - a gateway paid-time row (GatewayVendTransactionService — Omise/Midtrans
 *     /Fiuu webhook, carries the basket, waits for the TRADE);
 *   - a NETS-report orphan (fromSettlementLine — money in the settlement
 *     file, no TRADE ever came, created at Sync; NA_ERROR_CODE_PLAN Part 2).
 *
 * Both derive customer / operator / location type / contract / model /
 * prefix / planogram / meta from the machine THE SAME WAY, here, so the two
 * rails can never drift apart on revenue, GP or attribution. The gateway rail
 * keeps its own basket mapping (a NETS line has no product information).
 *
 * Money: integer cents; revenue = amount / (1 + gst). A rail row with no
 * mapped product takes the OPERATOR's GST rate, not 0 — a rate of 0 would
 * book the GST as revenue and overstate GP (Part 4 item 9).
 */
final class PreCreatedSaleFactory
{
    /**
     * Columns copied from the machine at pre-create time. The vend must have
     * customer.locationType, customer.operator, vendContract, vendModel and
     * vendPrefix loaded (eager) — every caller loads them in one query.
     */
    public static function vendAttributes(Vend $vend): array
    {
        $customer = $vend->customer;

        return [
            // Frozen at pre-create so a no-dispense row (which never receives
            // a TRADE) still carries it; a later TRADE refreshes it.
            'product_drop_sensor' => $vend->productDropSensorEnabled(),
            'vend_id' => $vend->id,
            'vend_contract_id' => $vend->vendContract?->id,
            'vend_model_id' => $vend->vendModel?->id,
            'vend_prefix_id' => $vend->vendPrefix?->id,
            // Planogram in force at pre-create time — vends.product_mapping_id
            // is rewritten on every changeover.
            'product_mapping_id' => $vend->product_mapping_id ?: null,
            'customer_id' => $customer?->id,
            'location_type_id' => $customer?->locationType?->id,
            'operator_id' => $customer?->operator?->id ?? $vend->operator_id ?? 1,
        ];
    }

    /** The meta_json audit bag every rail row carries, plus the rail's own keys. */
    public static function meta(Vend $vend, array $extra = []): array
    {
        $customer = $vend->customer;

        return array_merge([
            'apk_ver' => $vend->apk_ver_json['apkver'] ?? null,
            'firmware_ver' => isset($vend->firmware_ver) ? dechex($vend->firmware_ver) : null,
            'vend_code' => $vend->code,
            'customer_code' => $customer ? ($customer->id + 20000) : null,
            'customer_name' => $customer?->name,
            'vend_prefix_name' => $vend->vendPrefix?->name,
        ], $extra);
    }

    /** The GST rate a rail row with no mapped product is booked under. */
    public static function operatorGstRate(Vend $vend): float
    {
        return (float) ($vend->customer?->operator?->gst_vat_rate ?? $vend->operator?->gst_vat_rate ?? 0);
    }

    /** revenue / cost / GP columns for an amount in cents at a GST rate (percent). */
    public static function money(int $amountCents, float $gstVatRate, float $unitCostCents = 0): array
    {
        $revenue = $amountCents / (1.00 + ($gstVatRate / 100));
        $grossProfit = $revenue - $unitCostCents;

        return [
            'gst_vat_rate' => $gstVatRate,
            'revenue' => $revenue,
            'unit_cost' => $unitCostCents,
            'gross_profit' => $grossProfit,
            'gross_profit_margin' => $revenue ? (($grossProfit * 100) / $revenue) : 0,
        ];
    }

    /**
     * A sale created FROM a NETS report line that no TRADE fits (Part 2
     * orphan). The report is the money truth: SETTLED, or REFUNDED when the
     * line is paired with a reversal. The dispense is unknown: channel error
     * 99, no product, qty 1 / success 0, `is_found_in_transaction = false` so
     * a late TRADE can adopt it (VendTransactionService::create). The
     * synthetic order id `CS-<row>` (letters) can never collide with an APK
     * ORDRID (digits); the TRADE overwrites it on adoption.
     *
     * Runs inside the caller's transaction.
     */
    public function fromSettlementLine(CardSettlementRow $row, Vend $vend, ?int $paymentMethodId, ?string $cashlessMfg): VendTransaction
    {
        $at = Carbon::parse($row->transaction_date->toDateString().' '.$row->transaction_time);
        $refunded = $row->reversed_by_row_id !== null;
        $now = Carbon::now();

        $sale = new VendTransaction;
        $sale->forceFill(array_merge(
            self::vendAttributes($vend),
            self::money((int) $row->amount_cents, self::operatorGstRate($vend)),
            [
                'transaction_datetime' => $at,
                'amount' => (int) $row->amount_cents,
                'is_zero_amount' => (int) $row->amount_cents === 0,
                'order_id' => 'CS-'.$row->id,
                'interface_type' => null,
                'is_multiple' => false,
                'is_payment_received' => true, // the rail confirmed the money
                'items_json' => [],
                'payment_method_id' => $paymentMethodId ?? PaymentMethod::query()->where('code', PaymentMethod::CODE_CARD_TERMINAL)->value('id'),
                'cashless_mfg' => $cashlessMfg,
                // The report names the terminal outright — no binding lookup.
                'terminal_id' => $row->terminal_id,
                'qty' => 1,
                'success_qty' => 0,
                'dispensed_qty' => 0,
                'vend_channel_code' => 0,
                'vend_channel_id' => 0, // NOT NULL column; same placeholder the gateway rail uses for unmapped
                'vend_channel_error_id' => VendChannelError::notFoundId(),
                'vend_transaction_json' => null,
                'product_id' => null,
                'product_mapping_item_id' => null,
                'unit_cost_id' => null,
                'label_json' => null,
                'meta_json' => self::meta($vend, [
                    'source' => 'card_settlement',
                    'card_settlement_row_id' => $row->id,
                    // Same stamp the nightly marker writes: a late TRADE that
                    // adopts this row clears it through the one shared rule.
                    'missing_trade' => ['marked_at' => $now->toDateTimeString()],
                ]),
                'payment_gateway_log_id' => null,
                'is_found_in_transaction' => false,
                'settlement_status' => $refunded ? VendTransaction::SETTLEMENT_REFUNDED : VendTransaction::SETTLEMENT_SETTLED,
                'is_refunded' => $refunded,
                'auto_refund_source' => $refunded ? AutoRefundSource::SETTLEMENT_REPORT_REVERSAL : null,
                'card_settlement_synced_at' => $now,
                'card_settlement_row_id' => $row->id,
            ]
        ))->save();

        return $sale;
    }
}
