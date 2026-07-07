<?php

use App\Models\RefundTicket;
use App\Services\Refund\RefundRequestSync;
use Illuminate\Database\Migrations\Migration;

/**
 * Retire the "Auto-resolved" refund status. Tickets the system auto-refunds now
 * stay in "Received" flagged already-refunded (auto_refund_detected = true), so
 * Ops rejects or drops them by hand and Approve is disabled for them.
 *
 * This one-time migration converts every existing auto_resolved ticket to
 * Received + the flag, and re-syncs the denormalised refund-request snapshot on
 * vend_transactions so the Transactions page shows the new status. Forward-only:
 * the original status can't be reconstructed, so down() is a no-op.
 */
return new class extends Migration
{
    public function up(): void
    {
        RefundTicket::withTrashed()
            ->where('status', 'auto_resolved')
            ->get()
            ->each(function (RefundTicket $ticket) {
                $ticket->status = RefundTicket::STATUS_SUBMITTED;
                $ticket->auto_refund_detected = true;
                // These were genuinely auto-refunded, so seed the frozen
                // self-validation verdict too — the list icon, ticket-page badge
                // and Approve-guard read system_validation_json.already_refunded,
                // not the auto_refund_detected column, for new tickets.
                $sv = $ticket->system_validation_json ?? [];
                $sv['already_refunded'] = true;
                $ticket->system_validation_json = $sv;
                // saveQuietly + explicit sync: don't fire the audit-log observer for
                // a bulk data migration, but keep the vend_transactions denormalised
                // refund_request_* snapshot correct.
                $ticket->saveQuietly();
                RefundRequestSync::syncTicket($ticket);
            });
    }

    public function down(): void
    {
        // Forward-only: which Received tickets were previously auto_resolved can't
        // be reconstructed. No-op.
    }
};
