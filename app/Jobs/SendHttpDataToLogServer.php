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
        $this->endpoint = env('LOG_SERVER_URL') . '/api/vend-data';
        $this->token = env('LOG_SERVER_ACCESS_TOKEN');
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Http::withToken($this->token)
            ->post($this->endpoint, $this->data);
    }
}
