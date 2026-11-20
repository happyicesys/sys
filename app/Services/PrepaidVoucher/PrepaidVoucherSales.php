<?php

namespace App\Services\PrepaidVoucher;

use App\Models\PaymentGatewayLog;
use App\Models\PaymentMethod;
use App\Models\PrepaidVoucher;
use App\Models\Vend;
use App\Models\VendChannelError;
use App\Models\VendTransaction;
use App\Support\DispenseVerdict;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Where a TRADE (or a QR approval) meets the voucher ledger. The big board (app 329+) adds a top-level `PV`
 * to two kinds of frame:
 *
 *   purchase:   {"role":"buy","phone":"91234567","pid":<product id>,"list":<cents>,"first":"<held order id>"}
 *               on the voucher's own sale (Price = the voucher price, the SKU's channel, SErr 0 — nothing drops).
 *               The same block rides on the REQQR of a QR purchase.
 *   redemption: {"role":"redeem","id":<voucher id>,"token":"…","mode":"sku"|"value","used":<cents>,
 *               "lost":<cents>,"balance":<cents>}
 *               on the redeemed item's sale (Price 0, or the balance the customer paid).
 *
 * Money follows Brian's rule (2026-11-20): the purchase IS the sale (revenue when paid, no goods, so no unit
 * cost); the redemption is a $0 sale (or the balance) with a Voucher badge, carrying the goods' cost.
 * `meta_json.prepaid_voucher` holds the role and voucher id for the badge. Never throws: the sale is
 * already committed.
 */
final class PrepaidVoucherSales
{
    public function __construct(
        private readonly PrepaidVoucherIssuer $issuer,
        private readonly PrepaidVoucherRedeemer $redeemer,
    ) {}

    public function fromTrade(Vend $vend, VendTransaction $sale, ?array $frame): void
    {
        $pv = $frame['PV'] ?? null;
        if (! is_array($pv) || ! isset($pv['role'])) {
            return;
        }

        try {
            match ((string) $pv['role']) {
                'buy' => $this->purchase($vend, $sale, $pv),
                'redeem' => $this->redemption($vend, $sale, $pv),
                default => null,
            };
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /**
     * A QR purchase is paid: write the voucher now, before the machine reports anything — the money is in.
     * The machine's dispense frame for this order carries `pv: 1`, so the board never drops the SKU.
     */
    public function fromGatewayApproval(PaymentGatewayLog $log): ?PrepaidVoucher
    {
        $request = is_array($log->request) ? $log->request : [];
        $pv = $request['PV'] ?? null;
        if (! is_array($pv) || ($pv['role'] ?? null) !== 'buy') {
            return null;
        }
        $vend = $log->vend_id ? Vend::withoutGlobalScopes()->find($log->vend_id) : null;
        if (! $vend) {
            return null;
        }

        try {
            // The voucher IS what this charge bought, delivered now: mark the log dispensed so the 10-minute
            // "paid, never dispensed" sweep cannot refund it if the board misses the pv frame (restart, MQTT).
            $log->forceFill(['is_dispensed' => true])->saveQuietly();

            return $this->issuer->issue($vend, [
                'order_id' => (string) $log->order_id,
                'phone' => isset($pv['phone']) ? (string) $pv['phone'] : null,
                'product_id' => (int) ($pv['pid'] ?? 0),
                'list_price_cents' => (int) ($pv['list'] ?? 0),
                // REQQR PRICE is in currency units ("1.80"), as Omise is charged; every amount here is ×100.
                'price_paid_cents' => (int) round(((float) ($request['PRICE'] ?? 0)) * 100),
                'pay_rail' => PrepaidVoucher::RAIL_QR,
                'first_order_id' => isset($pv['first']) ? (string) $pv['first'] : null,
                'payment_gateway_log_id' => (int) $log->id,
            ]);
        } catch (\Throwable $e) {
            report($e);

            return null;
        }
    }

    /**
     * The charge that bought a voucher was refunded (Omise dashboard, a dispute): an unused voucher is void.
     * A voucher already used stays as it is — the item has gone — and is logged for a person.
     */
    public function voidForRefundedLog(PaymentGatewayLog $log, string $source): void
    {
        $voucher = PrepaidVoucher::query()->where('payment_gateway_log_id', $log->id)->first();
        if (! $voucher) {
            return;
        }
        if (in_array($voucher->status, [PrepaidVoucher::STATUS_ACTIVE, PrepaidVoucher::STATUS_RESERVED], true)) {
            $voucher->forceFill([
                'status' => PrepaidVoucher::STATUS_VOIDED,
                'voided_at' => now(),
                'void_reason' => 'Payment refunded ('.$source.')',
                'reserved_token' => null,
                'reserved_until' => null,
            ])->save();
        } else {
            Log::warning('prepaid voucher: its charge was refunded after it was '.$voucher->status, ['voucher' => $voucher->id, 'source' => $source]);
        }
    }

    /** True for a REQQR / log that buys a voucher (the dispense frame must not drop anything). */
    public static function isPurchaseRequest(PaymentGatewayLog $log): bool
    {
        $request = is_array($log->request) ? $log->request : [];

        return is_array($request['PV'] ?? null) && ($request['PV']['role'] ?? null) === 'buy';
    }

    private function purchase(Vend $vend, VendTransaction $sale, array $pv): void
    {
        $method = $sale->payment_method_id ? PaymentMethod::find($sale->payment_method_id) : null;
        $voucher = $this->issuer->issue($vend, [
            'order_id' => (string) $sale->order_id,
            'phone' => isset($pv['phone']) ? (string) $pv['phone'] : null,
            'product_id' => (int) ($pv['pid'] ?? $sale->product_id ?? 0),
            'list_price_cents' => (int) ($pv['list'] ?? 0),
            'price_paid_cents' => (int) $sale->amount,
            'pay_rail' => $method?->payment_gateway_id ? PrepaidVoucher::RAIL_QR : PrepaidVoucher::RAIL_CARD,
            'first_order_id' => isset($pv['first']) ? (string) $pv['first'] : null,
            'vend_transaction_id' => (int) $sale->id,
            'payment_gateway_log_id' => $sale->payment_gateway_log_id ? (int) $sale->payment_gateway_log_id : null,
        ]);

        // No goods left the machine: no cost on this row (the redemption carries it).
        $this->stamp($sale, ['role' => 'purchase', 'id' => $voucher?->id], ['unit_cost' => 0]);
    }

    private function redemption(Vend $vend, VendTransaction $sale, array $pv): void
    {
        $id = (int) ($pv['id'] ?? 0);
        if ($id <= 0) {
            return;
        }
        $outcome = $this->redeemer->settle($vend, [
            'id' => $id,
            'token' => isset($pv['token']) ? (string) $pv['token'] : null,
            'mode' => (string) ($pv['mode'] ?? PrepaidVoucher::MODE_SKU),
            'used' => (int) ($pv['used'] ?? 0),
            'lost' => (int) ($pv['lost'] ?? 0),
            'balance' => (int) ($pv['balance'] ?? $sale->amount),
            'product_id' => $sale->product_id ? (int) $sale->product_id : null,
            'order_id' => (string) $sale->order_id,
            'vend_transaction_id' => (int) $sale->id,
            'dispensed' => $this->dispensed($sale),
        ]);
        if ($outcome === 'conflict' || $outcome === 'unknown') {
            Log::warning('prepaid voucher: redemption TRADE could not spend its voucher', [
                'vend' => $vend->code, 'order' => $sale->order_id, 'voucher' => $id, 'outcome' => $outcome,
            ]);
        }

        $this->stamp($sale, [
            'role' => 'redemption',
            'id' => $id,
            'mode' => (string) ($pv['mode'] ?? PrepaidVoucher::MODE_SKU),
            'used' => (int) ($pv['used'] ?? 0),
            'lost' => (int) ($pv['lost'] ?? 0),
            'outcome' => $outcome,
        ]);
    }

    private function dispensed(VendTransaction $sale): bool
    {
        if ($sale->is_multiple) {
            $codes = DB::table('vend_transaction_items')->where('vend_transaction_id', $sale->id)->pluck('vend_channel_error_code');

            return $codes->isNotEmpty() && $codes->every(fn ($c) => DispenseVerdict::isDispensed($c));
        }
        $code = $sale->vend_channel_error_id ? VendChannelError::query()->whereKey($sale->vend_channel_error_id)->value('code') : null;

        return DispenseVerdict::isDispensed($code);
    }

    private function stamp(VendTransaction $sale, array $meta, array $columns = []): void
    {
        $current = is_array($sale->meta_json) ? $sale->meta_json : (json_decode((string) $sale->meta_json, true) ?: []);
        $current['prepaid_voucher'] = array_filter($meta, fn ($v) => $v !== null);
        $sale->forceFill(['meta_json' => $current] + $columns)->saveQuietly();
    }
}
