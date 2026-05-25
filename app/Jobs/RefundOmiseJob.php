<?php

namespace App\Jobs;

use App\Models\PaymentGatewayLog;
use App\Models\PaymentGateways\Omise;
use App\Models\VendTransaction;
use App\Services\ErrorService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RefundOmiseJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $errorService;
    protected $orderId;
    /**
     * Create a new job instance.
     */
    public function __construct($orderId)
    {
        $this->errorService = new ErrorService();
        $this->orderId = $orderId;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $paymentGatewayLog = PaymentGatewayLog::where('order_id', $this->orderId)->where('status', PaymentGatewayLog::STATUS_APPROVE)->first();

        if(!$paymentGatewayLog) {
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

        if($response->failed()) {
            $this->errorService->throwErrorWithMqtt('Refund failed' . $response->body(), $paymentGatewayLog->vend);
        }

        $paymentGatewayLog->update([
            'status' => PaymentGatewayLog::STATUS_REFUND,
            'response' => $response->json(),
        ]);

        // Unified transactions only: keep the linked vend_transaction in sync —
        // a refund voids the sale. Gated per-vend so legacy refund accounting
        // (where refunded rows still appear in gross sales) is unchanged for any
        // machine the feature doesn't apply to. No-op if no transaction linked.
        if (\App\Support\GatewayUnifiedTransaction::appliesToVend($paymentGatewayLog->vend_code)) {
            $vendTransaction = VendTransaction::withoutGlobalScopes()
                ->where('payment_gateway_log_id', $paymentGatewayLog->id)
                ->first();

            if ($vendTransaction) {
                $vendTransaction->forceFill([
                    'is_refunded' => true,
                    'settlement_status' => VendTransaction::SETTLEMENT_REFUNDED,
                ])->save();
            }
        }
    }
}
