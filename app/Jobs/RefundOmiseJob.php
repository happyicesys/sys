<?php

namespace App\Jobs;

use App\Models\PaymentGatewayLog;
use App\Models\PaymentGateways\Omise;
use App\Services\ErrorService;
use App\Support\AutoRefundSource;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RefundOmiseJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Retry a failed Omise refund call. A single failed attempt used to be final:
     * the 10-min scanner never re-picks a charge (its cursor has moved on) and the
     * TRADE-failure path fires once per TRADE, so a transient API/network error
     * silently left the charge un-refunded. The job is idempotent (it re-reads
     * the PG log and returns if it is no longer APPROVE, and an Omise
     * `refund.create` webhook also flips the log to REFUND), so retrying is safe.
     */
    public $tries = 3;

    public $backoff = [30, 120];

    protected $errorService;

    protected $orderId;

    /** One of App\Support\AutoRefundSource::OMISE_* — why this refund is being made. */
    protected $source;

    /**
     * Create a new job instance.
     */
    public function __construct($orderId, ?string $source = null)
    {
        $this->errorService = new ErrorService;
        $this->orderId = $orderId;
        $this->source = $source ?: AutoRefundSource::OMISE_MANUAL;
    }

    /**
     * All attempts exhausted: make it visible. The PG log stays status=APPROVE
     * and the vend_transaction stays un-refunded (never claim a refund that did
     * not happen) — this is the row the "Auto-refunded?" audit must find.
     */
    public function failed(\Throwable $e): void
    {
        \Illuminate\Support\Facades\Log::error('Omise refund failed after all attempts', [
            'order_id' => $this->orderId,
            'error' => $e->getMessage(),
        ]);
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $paymentGatewayLog = PaymentGatewayLog::where('order_id', $this->orderId)->where('status', PaymentGatewayLog::STATUS_APPROVE)->first();

        if (! $paymentGatewayLog) {
            return;
        }

        $newObj = new Omise(
            $paymentGatewayLog->operatorPaymentGateway->key1,
            $paymentGatewayLog->operatorPaymentGateway->key2
        );
        $response = $newObj->refundCharge([
            'metadata' => [
                'order_id' => $this->orderId,
            ],
            'amount' => $paymentGatewayLog->amount,
        ], $paymentGatewayLog->ref_id); // charge id

        $refundFailed = $response->failed();
        if ($refundFailed) {
            \Illuminate\Support\Facades\Log::warning('Omise refund attempt failed', [
                'order_id' => $this->orderId,
                'attempt' => $this->attempts(),
                'http_status' => $response->status(),
                'body' => $response->body(),
            ]);
            // Throws → this attempt fails → Horizon retries per $tries/$backoff.
            $this->errorService->throwErrorWithMqtt('Refund failed'.$response->body(), $paymentGatewayLog->vend);
        }

        // Single recording path (log status, linked vend_transaction, ticket
        // guard) shared with the webhook + reconcile command. The ?: covers a
        // job serialized by pre-source code and executed after deploy, whose
        // $source deserializes as null — must never TypeError after a refund
        // has already succeeded at Omise.
        app(\App\Services\Refund\OmiseRefundRecorder::class)
            ->record($paymentGatewayLog, $response->json(), $this->source ?: AutoRefundSource::OMISE_MANUAL);
    }
}
