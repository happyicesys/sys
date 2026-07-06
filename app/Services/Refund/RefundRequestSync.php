<?php

namespace App\Services\Refund;

use App\Models\RefundTicket;
use App\Models\VendTransaction;

/**
 * Keeps the denormalised refund-request snapshot on vend_transactions
 * (refund_request_id / _reference / _status / _is_dropped) in sync with the
 * refund_tickets table, so the Transactions page can render the "Refund Request"
 * column straight off the row instead of joining refund_tickets on every load.
 *
 * Single source of truth for the "latest ticket per transaction wins" rule
 * (matched by vend_transaction_id, falling back to order_id) — used by both the
 * live RefundTicketObserver and the one-off backfill seeder.
 */
class RefundRequestSync
{
    /**
     * Recompute and persist the refund-request columns for one transaction from
     * its current latest (non-deleted) refund ticket. Writes quietly so it never
     * trips vend_transaction model events (audit log, etc.).
     */
    public static function syncTransaction(VendTransaction $txn): void
    {
        $ticket = self::latestTicketFor($txn->id, $txn->order_id, $txn->vend_id);

        $txn->forceFill([
            'refund_request_id' => $ticket?->id,
            'refund_request_reference' => $ticket?->reference,
            'refund_request_status' => $ticket?->status,
            'refund_request_is_dropped' => $ticket ? (bool) $ticket->is_dropped : false,
        ])->saveQuietly();
    }

    /**
     * Resync every transaction a ticket could touch — both its current target and
     * (on an update) whatever it pointed at before, so re-matching a ticket also
     * clears the stale snapshot off the old transaction.
     */
    public static function syncTicket(RefundTicket $ticket): void
    {
        $txnIds = collect([
            $ticket->vend_transaction_id,
            $ticket->getOriginal('vend_transaction_id'),
        ])->filter()->unique()->values();

        $orderIds = collect([
            $ticket->order_id,
            $ticket->getOriginal('order_id'),
        ])->filter()->unique()->values();

        // order_id is NOT globally unique — machines reuse small per-machine order
        // numbers — so the order_id fallback MUST be scoped to the ticket's own
        // machine (vend_id), or one ticket stamps every machine sharing that
        // number. vend_transaction_id stays the precise, unscoped match.
        $vendId = $ticket->vend_id;

        if ($txnIds->isEmpty() && ($orderIds->isEmpty() || !$vendId)) {
            return;
        }

        VendTransaction::query()
            ->where(function ($q) use ($txnIds, $orderIds, $vendId) {
                if ($txnIds->isNotEmpty()) {
                    $q->orWhereIn('id', $txnIds->all());
                }
                if ($orderIds->isNotEmpty() && $vendId) {
                    $q->orWhere(fn ($w) => $w->whereIn('order_id', $orderIds->all())->where('vend_id', $vendId));
                }
            })
            ->get(['id', 'order_id', 'vend_id'])
            ->each(fn (VendTransaction $txn) => self::syncTransaction($txn));
    }

    /**
     * The latest (highest id) live refund ticket matched to a transaction by
     * vend_transaction_id or, failing that, order_id PAIRED WITH vend_id (order_id
     * alone isn't unique across machines). Soft-deleted tickets are excluded by
     * the model's default scope.
     */
    protected static function latestTicketFor($vendTransactionId, $orderId, $vendId): ?RefundTicket
    {
        return RefundTicket::query()
            ->where(function ($q) use ($vendTransactionId, $orderId, $vendId) {
                if ($vendTransactionId) {
                    $q->orWhere('vend_transaction_id', $vendTransactionId);
                }
                if (filled($orderId) && $vendId) {
                    $q->orWhere(fn ($w) => $w->where('order_id', $orderId)->where('vend_id', $vendId));
                }
            })
            ->orderByDesc('id')
            ->first(['id', 'reference', 'status', 'is_dropped', 'vend_transaction_id', 'order_id']);
    }
}
