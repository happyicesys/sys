<?php

namespace App\Jobs;

use App\Jobs\RefundOmiseJob;
use App\Models\VendTransaction;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
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
        if($this->vendTransaction->paymentGatewayLog()->exists()) {
            $paymentGateway = $this->vendTransaction->paymentGatewayLog->operatorPaymentGateway->paymentGateway;

            switch($paymentGateway->name) {
                case('omise'):
                    RefundOmiseJob::dispatch($this->vendTransaction->order_id);
                    // is_refunded is the existing (legacy) behaviour; the
                    // settlement_status demotion only applies under unified
                    // transactions so legacy sales accounting is unchanged.
                    $updates = ['is_refunded' => true];
                    if (config('app.gateway_unified_txn_enabled')) {
                        $updates['settlement_status'] = VendTransaction::SETTLEMENT_REFUNDED;
                    }
                    $this->vendTransaction->update($updates);
                    break;
            }
        }
    }
}
