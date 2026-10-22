<?php

namespace App\Support;

use App\Models\DeliveryPlatforms\Grab;
use App\Models\PaymentMethod;
use App\Models\VendTransaction;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * How a machine's sales were made, by TRANSACTION count (Brian, 2026-10-23: "Track base on transaction count
 * … not pieces, because touch screen allows multiple purchase"), today / yesterday / last 7 / last 30 days —
 * the Operation Dashboard's touch screen and cashless line graphs.
 *
 *  - A transaction is a settled sale that sold at least one piece (pieces as Sales(qty) counts them), so a
 *    failed single vend or a refunded sale is not one.
 *  - Touch screen: the APK built the order (TRADE `TXN_SRC` 1, `vend_transactions.interface_type`), except a
 *    Grab Mart order, which the APK also marks 1 but was placed online. Keypad is 0 / blank, keypad QR 50,
 *    remote dispense 2. Share of all transactions.
 *  - Cashless: paid any way but cash (payment method code 0) — card, the QR / e-wallet gateways, HID card,
 *    Grab Mart. Share of the transactions with a payment method on record.
 *
 * Windows end now and start at midnight: today, yesterday, the last 7 days (today + 6) and 30 days (today + 29).
 * One index range scan per machine on (vend_id, transaction_datetime), run by SyncVendTransactionTotalsJson
 * after each sale, so the page reads `touch_usage` / `cashless_usage` from the stored totals, no query of its own.
 */
class TransactionMix
{
    public const WINDOWS = ['today', 'yesterday', 'seven_days', 'thirty_days'];

    /**
     * @return array{touch_usage: array<string, array{touch: int, total: int}>, cashless_usage: array<string, array{cashless: int, total: int}>}
     */
    public static function forVend(int $vendId, ?CarbonInterface $now = null): array
    {
        $now = $now ? Carbon::instance($now) : Carbon::now();
        $today = $now->copy()->startOfDay();
        $from = [
            'today' => $today,
            'yesterday' => $today->copy()->subDay(),
            'seven_days' => $today->copy()->subDays(6),
            'thirty_days' => $today->copy()->subDays(29),
        ];

        $sold = '(CASE
            WHEN vt.success_qty IS NOT NULL AND vt.success_qty > 0 THEN vt.success_qty
            WHEN '.DispenseVerdict::sqlSaleById('vt.vend_channel_error_id', 'vce.code').' OR vt.is_multiple = 1 THEN COALESCE(vt.qty, 0)
            ELSE 0 END) > 0';
        $counts = [
            'total' => $sold,
            'touch' => "{$sold} AND vt.interface_type = 1 AND (pm.code IS NULL OR pm.code <> ".(int) Grab::PAYMENT_METHOD_GRABMART.')',
            'paid' => "{$sold} AND pm.id IS NOT NULL",
            'cashless' => "{$sold} AND pm.id IS NOT NULL AND pm.code <> ".(int) PaymentMethod::CODE_CASH,
        ];

        $query = DB::table('vend_transactions as vt')
            ->leftJoin('vend_channel_errors as vce', 'vce.id', '=', 'vt.vend_channel_error_id')
            ->leftJoin('payment_methods as pm', 'pm.id', '=', 'vt.payment_method_id')
            ->where('vt.vend_id', $vendId)
            ->whereBetween('vt.transaction_datetime', [$from['thirty_days'], $now])
            ->where('vt.settlement_status', VendTransaction::SETTLEMENT_SETTLED);
        foreach ($from as $window => $start) {
            // Yesterday ends where today starts; every other window runs to now.
            $range = $window === 'yesterday' ? 'vt.transaction_datetime >= ? AND vt.transaction_datetime < ?' : 'vt.transaction_datetime >= ?';
            $args = $window === 'yesterday' ? [$start, $today] : [$start];
            foreach ($counts as $name => $condition) {
                $query->selectRaw("SUM(CASE WHEN {$range} AND {$condition} THEN 1 ELSE 0 END) AS {$window}_{$name}", $args);
            }
        }
        $row = $query->first();

        $out = ['touch_usage' => [], 'cashless_usage' => []];
        foreach (self::WINDOWS as $window) {
            $n = fn (string $name) => (int) ($row->{$window.'_'.$name} ?? 0);
            $out['touch_usage'][$window] = ['touch' => $n('touch'), 'total' => $n('total')];
            $out['cashless_usage'][$window] = ['cashless' => $n('cashless'), 'total' => $n('paid')];
        }

        return $out;
    }
}
