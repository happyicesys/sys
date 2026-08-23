<?php

namespace App\Jobs;

use App\Models\VendTransaction;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class HandleFailedVendTransaction implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $vendTransaction;

    /**
     * Create a new job instance.
     */
    public function __construct(VendTransaction $vendTransaction)
    {
        $this->vendTransaction = $vendTransaction;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        if ($this->vendTransaction->paymentGatewayLog()->exists()) {
            $paymentGateway = $this->vendTransaction->paymentGatewayLog->operatorPaymentGateway->paymentGateway;

            switch ($paymentGateway->name) {
                case 'omise':
                    RefundOmiseJob::dispatch($this->vendTransaction->order_id, \App\Support\AutoRefundSource::OMISE_TRADE_FAIL);

                    // Unified transactions: RefundOmiseJob is the ONLY writer of
                    // is_refunded / settlement_status = REFUNDED, and it writes
                    // them only after Omise accepted the refund. Marking here,
                    // before the API call, would show "auto-refunded" on the
                    // Sales Transactions + Refund Request pages for a charge the
                    // processor may never have returned (integrity: never claim
                    // a refund that did not happen). Legacy (non-unified) vends
                    // keep the old immediate flag so their accounting is unchanged.
                    if (! \App\Support\GatewayUnifiedTransaction::appliesToVend($this->vendTransaction->paymentGatewayLog->vend_code)) {
                        $this->vendTransaction->update(['is_refunded' => true]);
                    }
                    break;
            }
        }
    }
}
