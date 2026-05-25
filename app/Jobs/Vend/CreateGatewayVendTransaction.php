<?php

namespace App\Jobs\Vend;

use App\Models\PaymentGatewayLog;
use App\Services\GatewayVendTransactionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Pre-creates the provisional vend_transaction for a paid gateway payment.
 *
 * Dispatched from PaymentController on the APPROVE webhook (behind the
 * `gateway_unified_txn_enabled` flag). Kept as a job so the webhook returns
 * fast and so the work is retry-safe — the underlying service is idempotent on
 * (order_id, vend_id), so a retry never produces a duplicate.
 */
class CreateGatewayVendTransaction implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $paymentGatewayLogId;

    public function __construct(int $paymentGatewayLogId)
    {
        $this->paymentGatewayLogId = $paymentGatewayLogId;
    }

    public function handle(GatewayVendTransactionService $service): void
    {
        $log = PaymentGatewayLog::find($this->paymentGatewayLogId);

        if (! $log || $log->status !== PaymentGatewayLog::STATUS_APPROVE) {
            return;
        }

        $service->createFromPaymentGatewayLog($log);
    }
}
