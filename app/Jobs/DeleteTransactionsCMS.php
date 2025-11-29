<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;

class DeleteTransactionsCMS implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $cmsTransactionID;
    protected $endpoint;
    /**
     * Create a new job instance.
     */
    public function __construct($cmsTransactionID)
    {
        $this->cmsTransactionID = $cmsTransactionID;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $baseUrl = config('app.cms_url');

        if (!$baseUrl) {
            return;
        }

        $this->endpoint = $baseUrl . '/api/sys/transactions/delete';

        $response = Http::post($this->endpoint, ['transaction_id' => $this->cmsTransactionID]);

        if ($response->successful()) {
            $responseArr = $response->json();
        }
    }
}
