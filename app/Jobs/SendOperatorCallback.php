<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendOperatorCallback implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public $url;
    public $data;
    public $method;
    public $headers;

    /**
     * Create a new job instance.
     */
    public function __construct($url, $data = [], $method = 'POST', $headers = [])
    {
        $this->url = $url;
        $this->data = $data;
        $this->method = strtoupper($method);
        $this->headers = $headers;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $http = \Illuminate\Support\Facades\Http::withHeaders($this->headers);

        if ($this->method === 'POST') {
            $response = $http->post($this->url, $this->data);
        } else {
            // Add other methods if needed, default to POST for now as per requirement
            $response = $http->post($this->url, $this->data);
        }

        if ($response->failed()) {
            \Illuminate\Support\Facades\Log::error('Operator callback failed', [
                'url' => $this->url,
                'status' => $response->status(),
                'body' => $response->body()
            ]);
            // Re-throw to allow queue retry if configured
            $response->throw();
        }
    }
}

