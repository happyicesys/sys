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
        // 1. Reset any prior snapshot IN BOUNDED BATCHES. A single
        //    whereNotNull(...)->update() runs as ONE giant UPDATE over the whole
        //    (multi-million row) vend_transactions table — and after the earlier
        //    order_id fan-out had stamped a huge number of rows, that statement
        //    holds locks for minutes and stalls behind live inserts (the hang you
        //    saw). chunkById walks the refund_request_id index by primary-key
        //    ranges and clears each range in its own small, quickly-committed
        //    UPDATE, so it progresses steadily and never blocks the table.
        $cleared = 0;
        VendTransaction::query()
            ->whereNotNull('refund_request_id')
            ->select('id')
            ->chunkById(2000, function ($rows) use (&$cleared) {
                $cleared += VendTransaction::whereKey($rows->pluck('id'))->update([
                    'refund_request_id' => null,
                    'refund_request_reference' => null,
                    'refund_request_status' => null,
                    'refund_request_is_dropped' => false,
                ]);
            });

        // 2. Re-apply from live tickets, oldest first so the latest ticket wins.
        //    Two SEPARATE indexed updates per ticket (primary key, then the
        //    (order_id, vend_id) composite index) instead of one OR condition, so
        //    MySQL always drives off an index and never full-scans the table.
        //    order_id is paired with vend_id because it is not unique across
        //    machines (mirrors RefundRequestSync).
        $applied = 0;
        RefundTicket::query()
            ->orderBy('id')
            ->select(['id', 'reference', 'status', 'is_dropped', 'vend_transaction_id', 'order_id', 'vend_id'])
            ->chunk(500, function ($tickets) use (&$applied) {
                foreach ($tickets as $ticket) {
                    $snapshot = [
                        'refund_request_id' => $ticket->id,
                        'refund_request_reference' => $ticket->reference,
                        'refund_request_status' => $ticket->status,
                        'refund_request_is_dropped' => (bool) $ticket->is_dropped,
                    ];

                    // vend_transaction_id is the exclusive, precise link. Only when
                    // a ticket has NO pinned transaction do we fall back to the
                    // machine-scoped order_id match — otherwise a pinned ticket
                    // whose order number is reused on the same machine would stamp
                    // several rows.
                    if ($ticket->vend_transaction_id) {
                        $applied += VendTransaction::whereKey($ticket->vend_transaction_id)->update($snapshot);
                    } elseif (filled($ticket->order_id) && $ticket->vend_id) {
                        $applied += VendTransaction::where('order_id', $ticket->order_id)
                            ->where('vend_id', $ticket->vend_id)
                            ->update($snapshot);
                    }
                }
            });

        $this->command?->info(sprintf(
            'Refund-request snapshot backfilled: cleared %d prior row(s), applied %d ticket→transaction write(s).',
            $cleared,
            $applied
        ));
    }
}
