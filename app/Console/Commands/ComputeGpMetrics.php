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
        {--chunk=1000 : Chunk size for inserts}
        {--sync : Process each day immediately instead of queueing the job}
        {--sleep=0 : Seconds to pause between days when using --sync}';

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
        $chunkSize = (int)$this->option('chunk');
        if ($chunkSize <= 0) {
            $this->error('Chunk size must be greater than 0.');
            return self::FAILURE;
        }

        [$rangeStart, $rangeEnd] = $this->determineRange();

        if ($rangeStart->gt($rangeEnd)) {
            $this->error('Invalid date range supplied.');
            return self::FAILURE;
        }

        $this->info(sprintf(
            'Computing GP metrics from %s to %s',
            $rangeStart->toDateString(),
            $rangeEnd->toDateString()
        ));

        $current = $rangeStart->copy();
        while ($current->lte($rangeEnd)) {
            $this->processDay($current, $chunkSize);
            $current->addDay();
        }

        $this->info('GP metrics aggregation completed.');
        return self::SUCCESS;
    }

    /**
     * Determine the date range the command should process.
     *
     * @return array{0:Carbon,1:Carbon}
     */
    protected function determineRange(): array
    {
        $dateOption = $this->option('date');
        $fromOption = $this->option('from');
        $toOption = $this->option('to');

        if ($dateOption) {
            $date = Carbon::parse($dateOption)->startOfDay();
            return [$date, $date->copy()];
        }

        if ($fromOption || $toOption) {
            $from = $fromOption ? Carbon::parse($fromOption)->startOfDay() : Carbon::today()->startOfDay();
            $to = $toOption ? Carbon::parse($toOption)->startOfDay() : Carbon::today()->startOfDay();

            if ($from->gt($to)) {
                [$from, $to] = [$to, $from];
            }

            return [$from, $to];
        }

        // Default: process yesterday
        $yesterday = Carbon::yesterday()->startOfDay();
        return [$yesterday, $yesterday->copy()];
    }

    /**
     * Process a single day worth of data.
     *
     * @param  Carbon  $day
     * @param  int     $chunkSize
     * @return void
     */
    protected function processDay(Carbon $day, int $chunkSize): void
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

        $this->info(sprintf(' - Queuing %s', $day->toDateString()));
        ProcessGpMetricsDay::dispatch($day->toDateString(), $chunkSize);
    }
}
