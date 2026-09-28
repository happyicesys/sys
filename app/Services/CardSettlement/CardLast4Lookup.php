<?php

namespace App\Services\CardSettlement;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The paying card's last 4 digits for a batch of sales, from the NETS line
 * each sale was matched to (card_settlement_rows.card_last4).
 *
 * A sale has at most one line (matched_vend_transaction_id is unique). A
 * retained-credit sale also gets the card of the failed sale whose credit it
 * used, so "Card Last 4" vs "Credit From Card" shows whether the same card —
 * the same customer — was behind both (5073, 2026-09-21: …9265 paid the
 * failed $1.70, …2599 paid the $0.70 top-up that took the item).
 *
 * One indexed query per batch, however many sales it holds.
 */
class CardLast4Lookup
{
    /**
     * @param  iterable<object>  $sales  rows carrying id, is_retained_credit_settlement, retained_credit_settles_txn_id
     * @return array<int, array{card: ?string, credit_from: ?string}> sale id → cards (sales with neither are omitted)
     */
    public static function forSales(iterable $sales): array
    {
        $sales = Collection::make($sales);
        $sourceOf = $sales
            ->filter(fn ($s) => $s->is_retained_credit_settlement && $s->retained_credit_settles_txn_id)
            ->mapWithKeys(fn ($s) => [(int) $s->id => (int) $s->retained_credit_settles_txn_id]);

        $cards = self::byTransactionId($sales->pluck('id')->merge($sourceOf->values())->all());
        if (! $cards) {
            return [];
        }

        $out = [];
        foreach ($sales as $s) {
            $card = $cards[(int) $s->id] ?? null;
            $creditFrom = isset($sourceOf[(int) $s->id]) ? ($cards[$sourceOf[(int) $s->id]] ?? null) : null;
            if ($card !== null || $creditFrom !== null) {
                $out[(int) $s->id] = ['card' => $card, 'credit_from' => $creditFrom];
            }
        }

        return $out;
    }

    /**
     * @param  int[]  $transactionIds
     * @return array<int, string> vend_transaction id → last 4
     */
    public static function byTransactionId(array $transactionIds): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $transactionIds))));
        if (! $ids) {
            return [];
        }

        $out = [];
        foreach (array_chunk($ids, 1000) as $chunk) {
            $out += DB::table('card_settlement_rows')
                ->whereIn('matched_vend_transaction_id', $chunk)
                ->whereNotNull('card_last4')
                ->pluck('card_last4', 'matched_vend_transaction_id')
                ->mapWithKeys(fn ($v, $k) => [(int) $k => $v])
                ->all();
        }

        return $out;
    }
}
