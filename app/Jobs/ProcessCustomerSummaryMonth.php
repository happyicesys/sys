<?php

namespace App\Jobs;

use App\Services\CustomerSummaryAggregator;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;

/**
 * Recompute customer_period_summaries for the month containing $month
 * (a YYYY-MM-DD inside the target month).
 *
 * One job = one month. Use the ComputeCustomerSummary command to enqueue
 * many months, e.g. for a backfill range.
 */
class ProcessCustomerSummaryMonth implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(
        public string $month,        // any YYYY-MM-DD inside the target month
        public ?string $asOf = null  // optional cap ("yesterday" by default)
    ) {
    }

    public function middleware(): array
    {
        $key = 'customer-summary-' . Carbon::parse($this->month)->format('Y-m');
        return [
            (new WithoutOverlapping($key))
                ->releaseAfter(30)
                ->expireAfter(3600),
        ];
    }

    public function backoff(): array
    {
        return [30, 120, 300];
    }

    public function handle(): void
    {
        CustomerSummaryAggregator::persistMonth(
            Carbon::parse($this->month),
            $this->asOf ? Carbon::parse($this->asOf) : null
        );
    }
}
