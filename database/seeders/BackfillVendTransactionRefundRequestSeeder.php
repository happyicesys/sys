<?php

namespace Database\Seeders;

use App\Models\RefundTicket;
use App\Models\VendTransaction;
use Illuminate\Database\Seeder;

/**
 * One-off backfill for vend_transactions.refund_request_* (the "Refund Request"
 * column on the Transactions page). Going forward these columns are kept live by
 * RefundTicketObserver; this seeder fills history for tickets that already exist.
 *
 * Matches the exact rule the observer uses (latest ticket per transaction, by
 * vend_transaction_id then order_id) so seeded rows agree with live ones.
 *
 * Idempotent & non-destructive:
 *   1. Clears any previous snapshot (rows where refund_request_id IS NOT NULL),
 *      so a re-run after tickets change never leaves a stale value behind.
 *   2. Re-applies from every live (non-deleted) refund ticket, oldest id first,
 *      so the newest ticket's overwrite wins per transaction.
 * Only refund-matched transactions are ever written — the rest of the (large)
 * vend_transactions table is untouched.
 *
 *   php artisan db:seed --class=BackfillVendTransactionRefundRequestSeeder
 */
class BackfillVendTransactionRefundRequestSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Reset any prior snapshot (bounded to rows that carry one).
        $cleared = VendTransaction::query()
            ->whereNotNull('refund_request_id')
            ->update([
                'refund_request_id' => null,
                'refund_request_reference' => null,
                'refund_request_status' => null,
                'refund_request_is_dropped' => false,
            ]);

        // 2. Re-apply from live tickets, oldest first so the latest ticket wins.
        $applied = 0;
        RefundTicket::query()
            ->orderBy('id')
            ->select(['id', 'reference', 'status', 'is_dropped', 'vend_transaction_id', 'order_id'])
            ->chunk(500, function ($tickets) use (&$applied) {
                foreach ($tickets as $ticket) {
                    $applied += VendTransaction::query()
                        ->where(function ($q) use ($ticket) {
                            if ($ticket->vend_transaction_id) {
                                $q->orWhere('id', $ticket->vend_transaction_id);
                            }
                            if (filled($ticket->order_id)) {
                                $q->orWhere('order_id', $ticket->order_id);
                            }
                        })
                        ->update([
                            'refund_request_id' => $ticket->id,
                            'refund_request_reference' => $ticket->reference,
                            'refund_request_status' => $ticket->status,
                            'refund_request_is_dropped' => (bool) $ticket->is_dropped,
                        ]);
                }
            });

        $this->command?->info(sprintf(
            'Refund-request snapshot backfilled: cleared %d prior row(s), applied %d ticket→transaction write(s).',
            $cleared,
            $applied
        ));
    }
}
