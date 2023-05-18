<?php

namespace App\Jobs;

use App\Models\PaymentGatewayLog;
use App\Models\PaymentGateway\Omise;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RefundOmiseJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $orderId;
    /**
     * Create a new job instance.
     */
    public function __construct($orderId)
    {
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

        $newObj = new Omise([
            'public' => $paymentGatewayLog->operatorPaymentGateway->key1,
            'secret' => $paymentGatewayLog->operatorPaymentGateway->key2
          ]);
        $response = $newObj->refunds([
            'order_id' => $this->orderId,
            'amount' => $paymentGatewayLog->amount,
        ], $paymentGatewayLog->response['data']['id']); // charge id

        dd($response->collect());

        $paymentGatewayLog->update([
            'status' => PaymentGatewayLog::STATUS_REFUND,
        ]);
    }
}
