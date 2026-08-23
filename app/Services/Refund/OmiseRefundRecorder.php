<?php

namespace App\Services\Refund;

use App\Models\PaymentGatewayLog;
use App\Models\VendTransaction;
use App\Support\AutoRefundSource;
use App\Support\GatewayUnifiedTransaction;
use Illuminate\Support\Facades\Log;

/**
 * The ONE place an Omise refund is recorded in mark1, whoever initiated it:
 *   - RefundOmiseJob (our API call succeeded)
 *   - the Omise `refund.create` webhook (refund made on the Omise dashboard,
 *     or a dispute/chargeback Omise accepted as a refund — we never called the
 *     API for those)
 *   - `php artisan refund:sync-omise` (reconcile from Omise's own records)
 *
 * Writes, in this order: payment_gateway_logs.status = REFUND (+ the Omise
 * payload as response), the linked vend_transaction (is_refunded +
 * auto_refund_source + settlement REFUNDED, unified vends only), then the
 * refund-ticket guard (markAutoRefundedByCharge — open tickets cross their
 * "already refunded" icon, approved/scheduled ones are pulled from payout).
 * Idempotent: re-recording an already-REFUND log only refreshes the payload.
 */
class OmiseRefundRecorder
{
    public function __construct(protected RefundTicketService $tickets) {}

    /**
     * @param  array|null  $payload  the Omise object to store on the log (refund / charge / event)
     * @param  string  $source  one of AutoRefundSource::OMISE_*
     */
    public function record(PaymentGatewayLog $log, ?array $payload, string $source): void
    {
        $log->update([
            'status' => PaymentGatewayLog::STATUS_REFUND,
            'response' => $payload ?? $log->response,
        ]);

        // Unified transactions only: a refund voids the sale. Gated per-vend so
        // legacy refund accounting (refunded rows still in gross sales) is
        // unchanged for any machine the feature doesn't apply to.
        if (GatewayUnifiedTransaction::appliesToVend($log->vend_code)) {
            $txn = VendTransaction::withoutGlobalScopes()
                ->where('payment_gateway_log_id', $log->id)
                ->first();

            if ($txn) {
                $txn->forceFill([
                    'is_refunded' => true,
                    'auto_refund_source' => $source,
                    'settlement_status' => VendTransaction::SETTLEMENT_REFUNDED,
                ])->save();
            }
        }

        // Best-effort and isolated: a ticket/email failure must never undo the
        // refund record above.
        try {
            $this->tickets->markAutoRefundedByCharge($log->order_id, $log->id);
        } catch (\Throwable $e) {
            Log::error('Refund ticket auto-resolve after Omise refund failed', [
                'order_id' => $log->order_id,
                'payment_gateway_log_id' => $log->id,
                'source' => $source,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
