<?php

namespace App\Jobs;

use App\Models\VendTransaction;
use App\Services\VendTransactionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;

class SendDataToDcvend implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $dcvendUserID;
    protected $endpoint;
    protected $vendTransactionID;
    protected $vendTransactionService;
    /**
     * Create a new job instance.
     */
    public function __construct($vendTransactionID, $dcvendUserID)
    {
        $this->vendTransactionID = $vendTransactionID;
        $this->endpoint = env('DCVEND_URL') . '/api/v1/transactions/create/users/' . $dcvendUserID;
        $this->vendTransactionService = new VendTransactionService();
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $vendTransaction = VendTransaction::find($this->vendTransactionID);

        $data = $this->vendTransactionService->setDcvendParam($vendTransaction->id);

        $response = Http::post($this->endpoint, $data);
    }
}
