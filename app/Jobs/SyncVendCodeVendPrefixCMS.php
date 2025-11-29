<?php

namespace App\Jobs;

use App\Models\Vend;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;

class SyncVendCodeVendPrefixCMS implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $vend;
    /**
     * Create a new job instance.
     */
    public function __construct(Vend $vend)
    {
        $this->vend = $vend;
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

        // $this->endpoint = $baseUrl . '/api/person/' . $personID . '/vendcode/';

        $response = Http::get($baseUrl . '/api/sys/person/' . $this->vend->customer->person_id . '/vendcode/' . $this->vend->code, [
            'vend_prefix' => $this->vend->vendPrefix ? $this->vend->vendPrefix->name : null,
        ]);
    }
}
