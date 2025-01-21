<?php

namespace App\Jobs;

use App\Models\VendTransaction;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendDataToDcvend implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $dcvendUserID;
    protected $endpoint;
    protected $vendTransactionID;
    /**
     * Create a new job instance.
     */
    public function __construct($vendTransactionID, $dcvendUserID)
    {
        $this->vendTransactionID = $vendTransactionID;
        $this->endpoint = env('DCVEND_URL') . '/api/v1/transactions/create/users/' . $dcvendUserID;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $vendTransaction = VendTransaction::find($this->vendTransactionID);
    }
}
