<?php

namespace App\Services\Refund;

use App\Models\VendTransaction;
use Illuminate\Support\Facades\Log;

/**
 * The ONE place a retained-credit settlement is recorded.
 *
 * A card TRADE whose approval landed under CARD_APPROVAL_SUSPECT_MS after the
 * request was armed was served from credit the VMC/reader RETAINED after an
 * earlier failed paid vend — no card was presented, no terminal settlement
 * will ever match it (real taps measure 20–25 s; phantoms 0.9–3.3 s, bench
 * 2031, 2026-08-29). The APK cannot refuse the approval — bench-falsified:
 * only a dispense consumes the credit, so refusing bricks the machine's card
 * rail — so the vend proceeds and mark1 must book it as what it is:
 * settlement of the machine's PRECEDING failed sale.
 *
 * Recorded here, on the settlement trade itself:
 *   - is_retained_credit_settlement = true (never fresh card revenue;
 *     reporting can exclude it, reconciliation can explain it)
 *   - retained_credit_settles_txn_id → the failed sale whose banked payment
 *     this vend consumed (best-effort: most recent prior paid trade on the
 *     same machine with an undispensed line — the credit is NOT slot- or
 *     amount-bound, $2.40 was served against a $0.20 failure on the bench)
 *
 * Nothing is written on the settled (source) sale: its auto-refund tick is
 * owned by the NETS settlement report alone (CardSettlementRefundReconciler,
 * 2026-09-08) — a retained credit shows there as a capture with no reversal,
 * which the reconciler reads as "charged, not refunded".
 *
 * Deliberately NOT done here: touching revenue/gp columns or gp_metrics —
 * the flag is the hook for reporting to exclude these; changing money
 * aggregates is a separate, wider change. And nothing is written for trades
 * without the key: VMC-keypad card sales (TXN_SRC 0) can also be served from
 * retained credit but carry no CSHL_ARMED_MS, so they are undetectable until
 * firmware fixes the fault (VMC_VENDOR_TICKET_2026-08-29.md in mark1-apk).
 */
class RetainedCreditSettlementRecorder
{
    /**
     * How far back to look for the failed sale this vend settles. The credit
     * survives error clears, VMC restarts and re-powers, so the failed sale
     * can be days old (machine errored → driver cleared → next card customer);
     * beyond a week a link would be a guess, and null is more honest.
     */
    public const SOURCE_LOOKBACK_DAYS = 7;

    /**
     * Record $settlement as a retained-credit settlement and link its source.
     * Best-effort by contract: callers run it post-ingest, and a failure here
     * must never break TRADE processing.
     */
    public function record(VendTransaction $settlement): void
    {
        $source = $this->findSourceTransaction($settlement);

        $settlement->forceFill([
            'is_retained_credit_settlement' => true,
            'retained_credit_settles_txn_id' => $source?->id,
        ])->save();

        Log::info('Retained-credit settlement recorded', [
            'vend_transaction_id' => $settlement->id,
            'order_id' => $settlement->order_id,
            'vend_id' => $settlement->vend_id,
            'settles_txn_id' => $source?->id,
            'armed_ms' => $settlement->vend_transaction_json['CSHL_ARMED_MS'] ?? null,
        ]);
    }

    /**
     * The failed sale whose banked payment this settlement consumed: the most
     * recent prior trade on the same machine that took money and left at
     * least one line undispensed. Includes earlier settlement trades that
     * themselves failed — their failure re-banks the same credit, so the
     * chain stays walkable back to the origin.
     */
    private function findSourceTransaction(VendTransaction $settlement): ?VendTransaction
    {
        return VendTransaction::withoutGlobalScopes()
            ->where('vend_id', $settlement->vend_id)
            ->where('id', '!=', $settlement->id)
            ->where('amount', '>', 0)
            ->whereColumn('success_qty', '<', 'qty')
            ->where('transaction_datetime', '<=', $settlement->transaction_datetime)
            ->where('transaction_datetime', '>=', $settlement->transaction_datetime->copy()->subDays(self::SOURCE_LOOKBACK_DAYS))
            ->orderByDesc('transaction_datetime')
            ->orderByDesc('id')
            ->first();
    }
}
