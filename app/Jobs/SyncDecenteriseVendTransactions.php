<?php

namespace App\Jobs;

use App\Models\VendTransaction;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SyncDecenteriseVendTransactions implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $vendTransaction;

    public function __construct($vendTransaction)
    {
        $this->vendTransaction = $vendTransaction;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        VendTransaction::where('id', $this->vendTransaction->id)->update([
            'customer_id' => $this->vendTransaction->customer_id,
            'operator_id' => $this->vendTransaction->operator_id,
            'vend_channel_code' => $this->vendTransaction->vend_channel_code,
        ]);
    }
}
