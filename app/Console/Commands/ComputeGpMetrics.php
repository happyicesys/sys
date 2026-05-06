<?php

namespace App\Console\Commands;

use App\Jobs\ProcessGpMetricsDay;
use Carbon\Carbon;
use Illuminate\Console\Command;

class ComputeGpMetrics extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'gp:compute-metrics
        {--date= : Process a specific YYYY-MM-DD}
        {--from= : Start date for range (YYYY-MM-DD)}
        {--to= : End date for range (YYYY-MM-DD)}
        {--chunk=5000 : Chunk size for inserts (bigger = fewer DB round trips)}
        {--sync : Process each day immediately instead of queueing the job}
        {--sleep=0 : Seconds to pause between days when using --sync}
        {--queue= : Override queue name (low|default|high). Defaults: backfill=low, nightly=default}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Aggregate vend transactions into the gp_metrics materialized table';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $chunkSize = (int) $this->option('chunk');
        if ($chunkSize <= 0) {
            $this->error('Chunk size must be greater than 0.');
            return self::FAILURE;
        }

        [$rangeStart, $rangeEnd, $isBackfill] = $this->determineRange();

        if ($rangeStart->gt($rangeEnd)) {
            $this->error('Invalid date range supplied.');
            return self::FAILURE;
        }

        // Backfill ranges (any explicit window) go on "low" queue so they
        // don't compete with realtime jobs. The nightly self-heal (no flags)
        // stays on "default" so morning reports are fresh by 01:00.
        // Explicit --queue= always wins.
        $queue = $this->option('queue')
            ?: ($isBackfill ? 'low' : 'default');

        $this->info(sprintf(
            'Computing GP metrics from %s to %s (sync=%s, queue=%s, backfill=%s)',
            $rangeStart->toDateString(),
            $rangeEnd->toDateString(),
            $this->option('sync') ? 'yes' : 'no',
            $queue,
            $isBackfill ? 'yes' : 'no'
        ));

        $current = $rangeStart->copy();
        while ($current->lte($rangeEnd)) {
            $this->processDay($current, $chunkSize, $queue);
            $current->addDay();
        }

        $this->info('GP metrics aggregation completed.');
        return self::SUCCESS;
    }

    /**
     * Determine the date range the command should process.
     *
     * @return array{0:Carbon,1:Carbon,2:bool}  start, end, isBackfill
     */
    protected function determineRange(): array
    {
        $dateOption = $this->option('date');
        $fromOption = $this->option('from');
        $toOption = $this->option('to');

        if ($dateOption) {
            $date = Carbon::parse($dateOption)->startOfDay();
            return [$date, $date->copy(), true];
        }

        if ($fromOption || $toOption) {
            $from = $fromOption ? Carbon::parse($fromOption)->startOfDay() : Carbon::today()->startOfDay();
            $to = $toOption ? Carbon::parse($toOption)->startOfDay() : Carbon::today()->startOfDay();

            if ($from->gt($to)) {
                [$from, $to] = [$to, $from];
            }

            return [$from, $to, true];
        }

        // Default: process last 3 days to self-heal any recent misses
        $today = Carbon::today()->startOfDay();
        // We want to process yesterday, 2 days ago, and 3 days ago.
        // E.g. if today is 10th, we process 7th, 8th, 9th.
        $from = $today->copy()->subDays(3);
        $to = $today->copy()->subDays(1);

        return [$from, $to, false]; // nightly self-heal — not a backfill
    }

    /**
     * Process a single day worth of data.
     *
     * @param  Carbon  $day
     * @param  int     $chunkSize
     * @param  string  $queue
     * @return void
     */
    protected function processDay(Carbon $day, int $chunkSize, string $queue = 'default'): void
    {
        if ($this->option('sync')) {
            $this->info(sprintf(' - Processing %s (sync)', $day->toDateString()));
            \App\Services\GpMetricsAggregator::persistDay($day, $chunkSize);

            $sleepSeconds = (int) $this->option('sleep');
            if ($sleepSeconds > 0) {
                sleep($sleepSeconds);
            }
            return;
        }

        $this->info(sprintf(' - Queuing %s on queue:%s', $day->toDateString(), $queue));
        ProcessGpMetricsDay::dispatch($day->toDateString(), $chunkSize)
            ->onQueue($queue);
    }
}
