<?php

namespace App\Services\SmartFreezer;

use App\Models\Vend;
use App\Models\VendTransaction;
use Carbon\Carbon;

/**
 * The sale a freezer door session belongs to.
 *
 * The freezer's TRADE carries mark1's order id as ORDRID; from APK v20 it also carries its own
 * session ref as `SFREF` — the same "SF-<vendCode>-<epoch>-<seq>" it hands Zijia's orderOpenDoor.
 * The whole frame is kept in `vend_transaction_json`, so the sale is found without a new column on
 * vend_transactions and without touching the TRADE ingest: the ref's own epoch narrows the search
 * to a few hours of one machine on the (vend_id, transaction_datetime) index, and only those rows
 * are matched on the JSON.
 *
 * That epoch is the BOARD's clock. When it is wrong, `transaction_datetime` is not near it either
 * (mark1 books a TRADE with an implausible TIME at arrival, and a QR row keeps its paid time), so
 * a second look runs on OUR clock — the (vend_id, created_at) index around when the push arrived.
 */
class FreezerSaleLocator
{
    private const SESSION_REF = '/^SF-\d+-(\d{9,11})-\d+$/';

    /** How far after the session started the sale may be stamped (settlement follows door close). */
    private const AFTER_HOURS = 6;

    /** Clock slack before it: the board's clock and ours are separate. */
    private const BEFORE_MINUTES = 30;

    /** How far either side of our own clock the second look searches. */
    private const OUR_CLOCK_DAYS = 2;

    /** @param  Carbon|null  $ourClock  when mark1 heard of the session (the push's arrival) */
    public function find(Vend $vend, string $sessionRef, ?Carbon $ourClock = null): ?VendTransaction
    {
        if (preg_match(self::SESSION_REF, $sessionRef, $m) !== 1) {
            return null;
        }
        $started = Carbon::createFromTimestamp((int) $m[1])->setTimezone(config('app.timezone'));

        $sale = $this->matching($vend, $sessionRef)
            ->whereBetween('transaction_datetime', [
                $started->copy()->subMinutes(self::BEFORE_MINUTES),
                $started->copy()->addHours(self::AFTER_HOURS),
            ])
            ->first();

        if ($sale === null && $ourClock !== null) {
            $sale = $this->matching($vend, $sessionRef)
                ->whereBetween('created_at', [
                    $ourClock->copy()->subDays(self::OUR_CLOCK_DAYS),
                    $ourClock->copy()->addDays(self::OUR_CLOCK_DAYS),
                ])
                ->first();
        }

        return $sale;
    }

    private function matching(Vend $vend, string $sessionRef)
    {
        return VendTransaction::withoutGlobalScopes()
            ->where('vend_id', $vend->id)
            ->where('vend_transaction_json->SFREF', $sessionRef)
            ->orderBy('id');
    }

    /**
     * What the customer paid for, per product: the TRADE's `transf_info` is one entry PER UNIT with
     * `goods_id` = mark1 product id (0 when the slot had no product).
     *
     * @return array<int, int> product id => units paid
     */
    public function paidUnits(VendTransaction $sale): array
    {
        $frame = $sale->vend_transaction_json;
        if (is_string($frame)) {
            $frame = json_decode($frame, true);
        }

        $paid = [];
        foreach ((array) (($frame['transf_info'] ?? null) ?: []) as $unit) {
            $productId = (int) ($unit['goods_id'] ?? 0);
            $paid[$productId] = ($paid[$productId] ?? 0) + 1;
        }

        return $paid;
    }

    /**
     * Product id => the unit price (cents) the customer paid for it in this sale, from the
     * TRADE's per-unit `Price` — what an AI-judged charge values a product at.
     *
     * @return array<int, int>
     */
    public function paidUnitPrices(VendTransaction $sale): array
    {
        $frame = $sale->vend_transaction_json;
        if (is_string($frame)) {
            $frame = json_decode($frame, true);
        }

        $prices = [];
        foreach ((array) (($frame['transf_info'] ?? null) ?: []) as $unit) {
            $productId = (int) ($unit['goods_id'] ?? 0);
            if ($productId > 0 && isset($unit['Price']) && is_numeric($unit['Price']) && (int) $unit['Price'] > 0) {
                $prices[$productId] = (int) $unit['Price'];
            }
        }

        return $prices;
    }
}
