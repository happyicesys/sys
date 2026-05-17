<?php

namespace App\Jobs\Vend;

use App\Models\PaymentGatewayLog;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * 5-minute delayed check for a PaymentGatewayLog: if no VendTransaction has
 * arrived yet, increment the per-machine `nofound_txn` counter in
 * vend_daily_stats. Mirrors the PWRON metric — same table, same indexes,
 * same writer (IncrementVendDailyStat) — just a different `metric` string.
 *
 * Why 5 minutes?
 *   - The vend_transactions row is created when the machine POSTs the
 *     transaction back; under normal network conditions this happens within
 *     a few seconds. 5 minutes gives a generous buffer for slow modems /
 *     temporary disconnects without losing the "anomaly" signal we want to
 *     track on the Customer Index page.
 *
 * Pair: when VendTransactionService later links the payment_gateway_log_id
 * onto the now-arrived VendTransaction (the "flip-to-found" moment), it
 * dispatches DecrementVendDailyStat so the counter goes back down. Together
 * the two jobs implement the +1 / -1 pattern the user asked for, with the
 * "if reaches 0 → delete" semantic handled inside Decrement.
 *
 * Date attribution: paid-at date (approved_at) — matches what the user
 * sees in the Payment Gateway Transactions page, so the daily bucket on
 * Customer Index lines up with that screen.
 */
class LogNofoundTxnIfStillMissing implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $paymentGatewayLogId;

    public function __construct(int $paymentGatewayLogId)
    {
        $this->paymentGatewayLogId = $paymentGatewayLogId;
    }

    public function handle(): void
    {
        // Re-read fresh — at queue execution time we want the CURRENT state
        // of the link, not whatever was true when the job was dispatched.
        $pgLog = PaymentGatewayLog::find($this->paymentGatewayLogId);
        if (!$pgLog) {
            return;
        }
        if (!$pgLog->vend_id) {
            // Defensive: PG logs without a vend can't be bucketed per-machine.
            return;
        }
        if ($pgLog->vendTransaction()->exists()) {
            // The matching VendTransaction landed within the 5-minute window —
            // this is the healthy path, nothing to log.
            return;
        }

        // Use approved_at to bucket — matches "Paid At" in the PG transactions
        // table. Fall back to today's date only if approved_at is somehow null
        // (shouldn't happen for STATUS_APPROVE rows).
        $date = $pgLog->approved_at
            ? Carbon::parse($pgLog->approved_at)->toDateString()
            : Carbon::now()->toDateString();

        IncrementVendDailyStat::dispatch(
            (int) $pgLog->vend_id,
            (string) $pgLog->vend_code,
            'nofound_txn',
            $date
        )->onQueue('low');
    }
}
