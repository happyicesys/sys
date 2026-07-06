<?php

namespace App\Observers;

use App\Models\RefundTicket;
use App\Services\Refund\RefundRequestSync;
use Illuminate\Support\Facades\Log;

/**
 * Mirrors each refund ticket's live status onto its matched sales transaction
 * (vend_transactions.refund_request_*), so the Transactions page reads the
 * "Refund Request" column straight off the row. Fires on create, every status
 * change, drop, soft-delete and restore.
 *
 * Deliberately defensive: the snapshot is only a display convenience, so a sync
 * failure is swallowed and never breaks a refund ticket write. Re-run
 * BackfillVendTransactionRefundRequestSeeder to heal anything missed.
 */
class RefundTicketObserver
{
    public function saved(RefundTicket $ticket): void
    {
        $this->sync($ticket);
    }

    public function deleted(RefundTicket $ticket): void
    {
        $this->sync($ticket);
    }

    public function restored(RefundTicket $ticket): void
    {
        $this->sync($ticket);
    }

    public function forceDeleted(RefundTicket $ticket): void
    {
        $this->sync($ticket);
    }

    private function sync(RefundTicket $ticket): void
    {
        try {
            RefundRequestSync::syncTicket($ticket);
        } catch (\Throwable $e) {
            Log::warning('RefundTicketObserver sync failed for refund_ticket ' . $ticket->id . ': ' . $e->getMessage());
        }
    }
}
