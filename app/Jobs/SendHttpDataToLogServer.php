<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;

class SendHttpDataToLogServer implements ShouldQueue
{
    use Queueable;

    protected $data;
    protected $endpoint;
    protected $token;
    /**
     * Create a new job instance.
     */
    public function __construct($data)
    {
        $this->data = $data;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $baseUrl = config('app.log_server_url');
        $token = config('app.log_server_access_token');

        if (!$baseUrl || !$token) {
            return;
        }

        $this->endpoint = $baseUrl . '/api/vend-data';
        $this->token = $token;

        Http::withToken($this->token)
            ->post($this->endpoint, $this->data);
    }
}
