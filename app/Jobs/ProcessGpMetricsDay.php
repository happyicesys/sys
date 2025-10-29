<?php

namespace App\Jobs;

use App\Services\GpMetricsAggregator;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessGpMetricsDay implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public string $date,
        public int $chunkSize = 1000
    ) {
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $day = Carbon::parse($this->date)->startOfDay();
        GpMetricsAggregator::persistDay($day, $this->chunkSize);
    }
}
