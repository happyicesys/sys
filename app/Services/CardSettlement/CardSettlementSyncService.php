<?php

namespace App\Services\CardSettlement;

use App\Models\CardSettlementReport;
use App\Models\CardSettlementRow;
use App\Models\VendTransaction;
use App\Services\Refund\RefundTicketService;
use App\Services\Sales\RollupRebuilder;
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
 *  3. hands the report's two calendar days (cutover day and the day before)
 *     to CardSettlementRefundReconciler, which makes the report the ONLY
 *     source of truth for the auto-refund tick on those days: a tick an
 *     earlier writer left on a sale the report shows captured-and-not-
 *     reversed, or never captured, is cleared and its refund ticket released.
 *
 * Idempotent: an earlier stamp survives a re-sync, and re-syncing changes
 * nothing the report does not say.
 */
class CardSettlementSyncService
{
    const CHUNK = 500;

    /** @var array<int,array> per-day reconcile stats from the last sync() */
    protected array $lastReconcile = [];

    /** Sales created from unmatched lines by the last sync() */
    protected int $lastOrphansCreated = 0;

    public function __construct(
        protected RefundTicketService $tickets,
        protected CardSettlementRefundReconciler $reconciler,
        protected CardSettlementOrphanSales $orphans,
        protected RollupRebuilder $rollups,
    ) {}

    public function lastOrphansCreated(): int
    {
        return $this->lastOrphansCreated;
    }

    /** Per-day reconcile stats from the last sync() call. */
    public function lastReconcile(): array
    {
        return $this->lastReconcile;
    }

    /** @return int number of matched rows covered by the sync */
    public function sync(CardSettlementReport $report, ?int $userId): int
    {
        // Direction 1 of the NETS ↔ TRADE gap: money in the report, no sale.
        // Created BEFORE the stamp so the new rows are synced and reconciled
        // like any other matched line.
        $this->lastOrphansCreated = $this->orphans->createForReport($report);

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

        // After the status flip: the reconciler decides "is this day final"
        // from report statuses, and this report is part of that answer.
        $this->lastReconcile = [];
        $days = [];
        foreach (CardSettlementRefundReconciler::daysCoveredBy($report) as $day) {
            $this->lastReconcile[] = $this->reconciler->reconcileDay($day, true);
            $days[] = $day->toDateString();
        }

        // This sync just changed those days' sales — orphan rows created, ticks
        // and settlement states written — so rebuild their rollups now instead
        // of leaving every dashboard stale until the 02:00 dirty pass (Brian,
        // 2026-09-09). Queued on `low`, and the days stay in the dirty set on
        // purpose: tonight's run still does the downstream cascade (totals JSON
        // and Site Summary), and a rebuild is idempotent.
        $this->rollups->dispatchDays($days);

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
