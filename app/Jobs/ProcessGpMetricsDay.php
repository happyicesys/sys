<?php

namespace App\Jobs;

use App\Services\GpMetricsAggregator;
use Carbon\Carbon;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;

class ProcessGpMetricsDay implements ShouldQueue
{
    // Batchable lets this job participate in Bus::batch([...])->then(...)
    // (used by customer-summary:compute --with-gp-metrics for parallel days).
    // Plain Bus::dispatch() / chain() still work — the trait is additive.
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Number of attempts before the job fails permanently.
     *
     * Keeping this modest so Horizon still surfaces persistent issues.
     */
    public int $tries = 5;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public string $date,
        public int $chunkSize = 1000
    ) {
    }

    /**
     * Prevent multiple jobs from rebuilding the same date simultaneously.
     */
    public function middleware(): array
    {
        return [
            (new WithoutOverlapping('gp-metrics-'.$this->date))
                ->releaseAfter(30)
                ->expireAfter(3600),
        ];
    }

    /**
     * Provide an exponential-ish backoff for Horizon.
     */
    public function backoff(): array
    {
        return [30, 120, 300, 600];
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            $day = Carbon::parse($this->date)->startOfDay();
            GpMetricsAggregator::persistDay($day, $this->chunkSize);
        } catch (QueryException $exception) {
            if ($this->shouldRetryForDeadlock($exception)) {
                $this->release($this->retryDelaySeconds());
                return;
            }

            throw $exception;
        }
    }

    /**
     * Determine if the given exception represents a transient deadlock.
     */
    protected function shouldRetryForDeadlock(QueryException $exception): bool
    {
        $errorCode = (int)($exception->errorInfo[1] ?? 0);

        return $exception->getCode() === '40001' || $errorCode === 1213;
    }

    /**
     * Stagger retries slightly to reduce the chance of repeated deadlocks.
     */
    protected function retryDelaySeconds(): int
    {
        return 60 + random_int(0, 120);
    }
}
