<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SyncDecenteriseVendTransactions implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $vendTransactions;

    public function __construct($vendTransactions)
    {
        $this->vendTransactions = $vendTransactions;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        foreach ($this->vendTransactions as $vendTransaction) {
            $vend = $vendTransaction->vend;

            $vendTransaction->customer_id = $vend->latestVendBinding()->exists() && $vend->latestVendBinding->customer()->exists() ? $vend->latestVendBinding->customer->id : null;
            $vendTransaction->operator_id = $vend->currentOperator()->exists() ? $vend->currentOperator->first()->id : null;
            $vendTransaction->vend_channel_code = $vendTransaction->vendChannel->code;
            $vendTransaction->save();
        }
    }
}
