<?php

namespace App\Support;

use App\Models\DeliveryPlatforms\Grab;
use App\Models\VendTransaction;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Touch screen usage rate (Brian, 2026-10-22): pieces sold through the machine's touch screen ÷ all
 * pieces sold, today / yesterday / last 7 days / last 30 days, for the Operation Dashboard's line graph.
 *
 * "Through the touch screen" = the APK built the order (TRADE `TXN_SRC` 1, `vend_transactions.interface_type`),
 * except a Grab Mart order, which the APK also marks 1 but was placed online. Keypad sales are `TXN_SRC` 0 or
 * blank (the VMC board builds those), keypad QR is 50, remote dispense 2. Pieces are counted the way the Sales
 * (qty) column counts them (SyncVendTransactionTotalsJson::calculateSuccessfulItemCount), settled sales only.
 * Windows end now and start at midnight: today, yesterday, the last 7 days (today + 6) and 30 days (today + 29).
 *
 * One range scan per machine on (vend_id, transaction_datetime), run by SyncVendTransactionTotalsJson after
 * each sale, so the page reads it from `vend_transaction_totals_json.touch_usage` with no query of its own.
 */
class TouchScreenUsage
{
    public const WINDOWS = ['today', 'yesterday', 'seven_days', 'thirty_days'];

    /** @return array<string, array{touch: int, total: int}> */
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

        $pieces = 'CASE
            WHEN vt.success_qty IS NOT NULL AND vt.success_qty > 0 THEN vt.success_qty
            WHEN '.DispenseVerdict::sqlSaleById('vt.vend_channel_error_id', 'vce.code').' OR vt.is_multiple = 1 THEN COALESCE(vt.qty, 0)
            ELSE 0 END';
        $touch = 'vt.interface_type = 1 AND (pm.code IS NULL OR pm.code <> '.(int) Grab::PAYMENT_METHOD_GRABMART.')';

        $query = DB::table('vend_transactions as vt')
            ->leftJoin('vend_channel_errors as vce', 'vce.id', '=', 'vt.vend_channel_error_id')
            ->leftJoin('payment_methods as pm', 'pm.id', '=', 'vt.payment_method_id')
            ->where('vt.vend_id', $vendId)
            ->whereBetween('vt.transaction_datetime', [$from['thirty_days'], $now])
            ->where('vt.settlement_status', VendTransaction::SETTLEMENT_SETTLED);
        $bindings = [];
        foreach ($from as $window => $start) {
            // Yesterday ends where today starts; every other window runs to now.
            $range = $window === 'yesterday' ? 'vt.transaction_datetime >= ? AND vt.transaction_datetime < ?' : 'vt.transaction_datetime >= ?';
            $args = $window === 'yesterday' ? [$start, $today] : [$start];
            $query->selectRaw("SUM(CASE WHEN {$range} THEN {$pieces} ELSE 0 END) AS {$window}_total", $args);
            $query->selectRaw("SUM(CASE WHEN {$range} AND {$touch} THEN {$pieces} ELSE 0 END) AS {$window}_touch", $args);
        }
        $row = $query->first();

        $out = [];
        foreach (self::WINDOWS as $window) {
            $out[$window] = ['touch' => (int) ($row->{$window.'_touch'} ?? 0), 'total' => (int) ($row->{$window.'_total'} ?? 0)];
        }

        return $out;
    }
}
