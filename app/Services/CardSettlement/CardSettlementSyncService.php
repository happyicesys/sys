<?php

namespace App\Services\CardSettlement;

use App\Models\CardSettlementReport;
use App\Models\CardSettlementRow;
use App\Models\VendTransaction;
use App\Services\Refund\RefundTicketService;
use App\Support\AutoRefundSource;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Applies a matched report to the sales it claims:
 *
 *  1. stamps card_settlement_synced_at onto every matched sale — chunked
 *     UPDATE ... WHERE id IN (...) so a full day's report (~3.5k rows) never
 *     holds a long row lock on the 4.6M-row table;
 *  2. marks the sale refunded when the report carried a reversal line for it
 *     (is_refunded + auto_refund_source = settlement_report_reversal, then
 *     RefundTicketService::markAutoRefundedByCharge so an open ticket can
 *     never pay the customer a second time — the same write path the
 *     TRADE-time inference used).
 *
 * Idempotent: an earlier stamp survives a re-sync, and a sale already
 * is_refunded (by any source) is left as it is.
 */
class CardSettlementSyncService
{
    const CHUNK = 500;

    public function __construct(protected RefundTicketService $tickets) {}

    /** @return int number of matched rows covered by the sync */
    public function sync(CardSettlementReport $report, ?int $userId): int
    {
        $txnIds = $report->rows()
            ->where('status', CardSettlementRow::STATUS_MATCHED)
            ->whereNotNull('matched_vend_transaction_id')
            ->pluck('matched_vend_transaction_id');

        foreach ($txnIds->chunk(self::CHUNK) as $chunk) {
            VendTransaction::query()
                ->withoutGlobalScopes()
                ->whereIn('id', $chunk)
                ->whereNull('card_settlement_synced_at')
                ->update(['card_settlement_synced_at' => now()]);
        }

        $refunded = $this->markReversed($report);

        $report->forceFill([
            'status' => CardSettlementReport::STATUS_SYNCED,
            'synced_count' => $txnIds->count(),
            'refunded_count' => $refunded,
            'synced_at' => now(),
            'synced_by' => $userId,
        ])->save();

        return $txnIds->count();
    }

    /**
     * Sales whose purchase line was paired with a reversal line. Returns how
     * many such sales the report covers (already-refunded ones included).
     */
    protected function markReversed(CardSettlementReport $report): int
    {
        // The purchase line may sit in an EARLIER report (reversal after the
        // ~22:30 cutover), so a reversal-only report must reach across: this
        // report's purchase lines that carry a reversal, PLUS the purchase lines
        // (any report) that this report's reversal lines point at. Whichever of
        // the two reports is synced later marks the sale; already-refunded
        // sales are skipped below, so syncing both is idempotent.
        $purchaseIdsReversedHere = $report->rows()
            ->where('is_reversal', true)
            ->whereNotNull('reverses_row_id')
            ->pluck('reverses_row_id');

        $reversedTxnIds = CardSettlementRow::query()
            ->where(fn ($q) => $q
                ->where('card_settlement_report_id', $report->id)
                ->orWhereIn('id', $purchaseIdsReversedHere))
            ->where('status', CardSettlementRow::STATUS_MATCHED)
            ->where('is_reversal', false)
            ->whereNotNull('reversed_by_row_id')
            ->whereNotNull('matched_vend_transaction_id')
            ->pluck('matched_vend_transaction_id')
            ->unique();

        if ($reversedTxnIds->isEmpty()) {
            return 0;
        }

        $txns = VendTransaction::query()
            ->withoutGlobalScopes()
            ->whereIn('id', $reversedTxnIds)
            ->get();

        foreach ($txns as $txn) {
            if ($txn->is_refunded) {
                continue;
            }

            $txn->forceFill([
                'is_refunded' => true,
                'auto_refund_source' => AutoRefundSource::SETTLEMENT_REPORT_REVERSAL,
            ])->save();

            try {
                $this->tickets->markAutoRefundedByCharge($txn->order_id, null, $txn->id);
            } catch (Throwable $e) {
                Log::error('Refund ticket auto-resolve after settlement-report reversal failed', [
                    'vend_transaction_id' => $txn->id,
                    'order_id' => $txn->order_id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $txns->count();
    }
}
