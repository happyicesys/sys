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
use Illuminate\Support\Facades\Log;

/**
 * Process gp_metrics for a contiguous date range INSIDE ONE JOB EXECUTION.
 *
 * Why this exists alongside ProcessGpMetricsDay:
 *
 *   - ProcessGpMetricsDay = 1 job per day. Used by the nightly self-heal cron
 *     where you only ever process 1–3 days, retries are isolated, and the
 *     queue overhead per job is irrelevant.
 *
 *   - ProcessGpMetricsRange = 1 job per N days. Used by heavy backfills
 *     ("--with-gp-metrics --since-begin-date") where dispatching 100s–1000s
 *     of one-day jobs creates real overhead AND each job opens its own MySQL
 *     connection, which can saturate the pool. A range job holds ONE
 *     connection for the whole month.
 *
 * Concurrency safety: WithoutOverlapping keys on the (from→to) pair so two
 * jobs covering overlapping ranges can't run at the same time.
 *
 * Failure isolation: a single bad day inside the loop is logged and SKIPPED
 * by default (don't let one date kill the whole month). Set $stopOnError=true
 * to abort the range on first failure.
 */
class ProcessGpMetricsRange implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /**
     * 30 minutes — generous enough for ~31 days × ~30s each in heavy operators,
     * but won't hang a worker forever if something goes very wrong.
     */
    public int $timeout = 1800;

    public function __construct(
        public string $fromDate,
        public string $toDate,
        public int $chunkSize = 5000,
        /**
         * Tiny pause between days inside the loop. Gives the DB room to breathe
         * and other queries (web requests, real-time MQTT writes) a chance to
         * grab a lock. 50ms × 30 days = 1.5s overhead per month — negligible.
         */
        public int $sleepBetweenDaysMs = 50,
        /**
         * If true, a deadlock / query error on any single day aborts the whole
         * range job. Default false: log + skip, let the nightly self-heal pick
         * up the missed day later.
         */
        public bool $stopOnError = false
    ) {
    }

    public function middleware(): array
    {
        $key = sprintf('gp-metrics-range-%s-%s', $this->fromDate, $this->toDate);
        return [
            (new WithoutOverlapping($key))
                ->releaseAfter(10)
                ->expireAfter(3600),
        ];
    }

    public function backoff(): array
    {
        return [30, 120, 300];
    }

    public function handle(): void
    {
        $start = Carbon::parse($this->fromDate)->startOfDay();
        $end = Carbon::parse($this->toDate)->startOfDay();

        if ($start->gt($end)) {
            [$start, $end] = [$end, $start];
        }

        $processed = 0;
        $skipped = 0;
        $cursor = $start->copy();

        while ($cursor->lte($end)) {
            try {
                GpMetricsAggregator::persistDay($cursor->copy(), $this->chunkSize);
                $processed++;
            } catch (QueryException $exception) {
                Log::warning('ProcessGpMetricsRange: day failed', [
                    'date' => $cursor->toDateString(),
                    'error_code' => $exception->errorInfo[1] ?? null,
                    'sql_state' => $exception->errorInfo[0] ?? null,
                    'message' => $exception->getMessage(),
                ]);

                if ($this->stopOnError) {
                    throw $exception;
                }
                $skipped++;
            }

            $cursor->addDay();
            if ($this->sleepBetweenDaysMs > 0) {
                usleep($this->sleepBetweenDaysMs * 1000);
            }
        }

        Log::info('ProcessGpMetricsRange: completed', [
            'from' => $this->fromDate,
            'to' => $this->toDate,
            'days_processed' => $processed,
            'days_skipped' => $skipped,
        ]);
    }
}
