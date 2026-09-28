<?php

namespace App\Services\CardSettlement;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Who used a failed sale's retained credit — the question a refund claim on
 * that sale turns on. One rule, read by the refund screens
 * (RefundController.retained_credit_retry) and the 08:30 health email.
 *
 * A link (retained_credit_settles_txn_id) proves only that a later sale on
 * the machine was served from the credit. It does not prove the claimant got
 * the item (Brian, 2026-09-28): on 5073, 2026-09-21, the failed $1.70 was
 * card …9265 and the $0.70 top-up that took the item was card …2599 — two
 * people. So the verdict compares the NETS card on both sales:
 *
 *  - same_card       both known and equal → the customer got the item on a
 *                    retry; paying the claim refunds delivered goods.
 *  - different_card  both known, not equal → someone else got the item; the
 *                    claimant did not, and the claim stands.
 *  - unknown         a card is missing — EFTPOS lines carry none, and a
 *                    re-vend or a TXN_SRC 1 phantom approval has no NETS line
 *                    of its own (RF-260924002: used 2½ days later). Check.
 */
class RetainedCreditRetry
{
    const SAME_CARD = 'same_card';

    const DIFFERENT_CARD = 'different_card';

    const UNKNOWN = 'unknown';

    /**
     * Failed sale id → verdict; a sale whose credit nothing used is omitted.
     *
     * @param  int[]  $failedIds  vend_transactions ids of the failed (claimed) sales
     * @return array<int, array{verdict: string, retry_id: int, retry_at: string, retry_amount: int, failed_card: ?string, retry_card: ?string}>
     */
    public static function forFailedSales(array $failedIds): array
    {
        $failedIds = array_values(array_unique(array_filter(array_map('intval', $failedIds))));
        if (! $failedIds) {
            return [];
        }

        // The FIRST sale served from each failed sale's credit. A failed
        // settlement re-banks the same credit, so a chain can hold several;
        // the first is the retry the claimant could have made.
        $retries = DB::table('vend_transactions')
            ->whereIn('retained_credit_settles_txn_id', $failedIds)
            ->where('is_retained_credit_settlement', true)
            ->orderBy('transaction_datetime')
            ->orderBy('id')
            ->get(['id', 'retained_credit_settles_txn_id', 'transaction_datetime', 'amount'])
            ->unique('retained_credit_settles_txn_id');
        if ($retries->isEmpty()) {
            return [];
        }

        $cards = CardLast4Lookup::byTransactionId([...$retries->pluck('id'), ...$retries->pluck('retained_credit_settles_txn_id')]);

        $out = [];
        foreach ($retries as $r) {
            $failedId = (int) $r->retained_credit_settles_txn_id;
            $failedCard = $cards[$failedId] ?? null;
            $retryCard = $cards[(int) $r->id] ?? null;

            $out[$failedId] = [
                'verdict' => self::verdict($failedCard, $retryCard),
                'retry_id' => (int) $r->id,
                'retry_at' => Carbon::parse($r->transaction_datetime)->toDateTimeString(),
                'retry_amount' => (int) $r->amount,
                'failed_card' => $failedCard,
                'retry_card' => $retryCard,
            ];
        }

        return $out;
    }

    /**
     * The other side: sales that were SERVED from a failed sale's retained
     * credit — a claim on one of these is the second customer's (or the same
     * customer's retry), and they got the item. Sale id → verdict, the failed
     * sale it drew on, and what their own NETS line charged (null = nothing:
     * a re-vend or a phantom approval took the whole item from the credit).
     *
     * @param  int[]  $saleIds
     * @return array<int, array{verdict: string, failed_id: int, failed_at: string, failed_amount: int, failed_card: ?string, retry_card: ?string, paid_cents: ?int}>
     */
    public static function forRetrySales(array $saleIds): array
    {
        $saleIds = array_values(array_unique(array_filter(array_map('intval', $saleIds))));
        if (! $saleIds) {
            return [];
        }

        $sales = DB::table('vend_transactions as s')
            ->join('vend_transactions as f', 'f.id', '=', 's.retained_credit_settles_txn_id')
            ->whereIn('s.id', $saleIds)
            ->where('s.is_retained_credit_settlement', true)
            ->get(['s.id', 'f.id as failed_id', 'f.transaction_datetime as failed_at', 'f.amount as failed_amount']);
        if ($sales->isEmpty()) {
            return [];
        }

        $lines = DB::table('card_settlement_rows')
            ->whereIn('matched_vend_transaction_id', [...$sales->pluck('id'), ...$sales->pluck('failed_id')])
            ->get(['matched_vend_transaction_id', 'card_last4', 'amount_cents'])
            ->keyBy('matched_vend_transaction_id');

        $out = [];
        foreach ($sales as $s) {
            $failedCard = $lines[$s->failed_id]->card_last4 ?? null;
            $retryCard = $lines[$s->id]->card_last4 ?? null;
            $out[(int) $s->id] = [
                'verdict' => self::verdict($failedCard, $retryCard),
                'failed_id' => (int) $s->failed_id,
                'failed_at' => Carbon::parse($s->failed_at)->toDateTimeString(),
                'failed_amount' => (int) $s->failed_amount,
                'failed_card' => $failedCard,
                'retry_card' => $retryCard,
                'paid_cents' => isset($lines[$s->id]) ? (int) $lines[$s->id]->amount_cents : null,
            ];
        }

        return $out;
    }

    public static function verdict(?string $failedCard, ?string $retryCard): string
    {
        if ($failedCard === null || $retryCard === null) {
            return self::UNKNOWN;
        }

        return $failedCard === $retryCard ? self::SAME_CARD : self::DIFFERENT_CARD;
    }
}
