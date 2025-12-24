<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class RetryVendJobs extends Command
{

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'vend:retry-jobs';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Retry unreturned vend jobs older than ' . \App\Models\VendJob::RETRY_TIMEOUT_MINUTES . ' minutes';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $timeout = \App\Models\VendJob::RETRY_TIMEOUT_MINUTES;
        $jobs = \App\Models\VendJob::where('is_returned', false)
            ->whereHas('vend', function ($query) {
                // BETA TESTING: Only retry for vend 2007
                $query->where('code', '2007');
            })
            ->where('updated_at', '<', \Carbon\Carbon::now()->subMinutes($timeout))
            ->with('vend')
            ->get();

        foreach ($jobs as $job) {
            if ($job->vend) {
                // Since we store the exact final message string in payload (no array cast)
                // We just send it as is.
                $message = (string) $job->payload;

                // Publish MQTT
                \App\Jobs\PublishMqtt::dispatch('CM' . $job->vend->code, $message, 0)->onQueue('default');

                // Increment retries (updates updated_at, resetting the timer for next retry)
                $job->increment('retries_count');

                $this->info("Retried job ID: {$job->id} for Vend: {$job->vend->code}");
            }
        }
    }
}
