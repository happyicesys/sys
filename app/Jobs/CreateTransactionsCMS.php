<?php

namespace App\Jobs;

use App\Services\OpsJobService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;

class CreateTransactionsCMS implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $opsJobService;
    protected $dataArr;
    protected $endpoint;

    /**
     * Create a new job instance.
     */
    public function __construct($dataArr)
    {
        $this->opsJobService = new OpsJobService();
        $this->dataArr = $dataArr;
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

        $this->endpoint = $baseUrl . '/api/sys/transactions/create';

        $response = Http::post($this->endpoint, $this->dataArr);

        $responseArr = $response->body();
        if ($response->successful()) {
            $responseArr = $response->json();
            $this->opsJobService->updateJobItemCMSTransactionID($responseArr);
        }
    }
}
