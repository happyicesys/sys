<?php

namespace App\Console\Commands;

use App\Jobs\StoreVendsRecord;
use App\Jobs\PersistGpMetricsJob;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Console\Command;

class SyncHistoricalSalesMetrics extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sync:historical-sales-metrics
                            {--from= : Start date for sync (YYYY-MM-DD, default 4 months ago)}
                            {--to= : End date for sync (YYYY-MM-DD, default today)}
                            {--queue=low : Queue to dispatch to}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync historical sales metrics for Dashboard and Sales Reports in the background.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $fromStr = $this->option('from') ?? Carbon::today()->subMonths(4)->toDateString();
        $toStr = $this->option('to') ?? Carbon::today()->toDateString();
        $queue = $this->option('queue') ?? 'low';

        $startDate = Carbon::parse($fromStr);
        $endDate = Carbon::parse($toStr);

        if ($startDate->gt($endDate)) {
            $this->error('Start date cannot be after end date.');
            return 1;
        }

        $period = CarbonPeriod::create($startDate, $endDate);

        $this->info("Enqueuing historical sync from {$startDate->toDateString()} to {$endDate->toDateString()} to '{$queue}' queue...");

        $bar = $this->output->createProgressBar(count($period));
        $bar->start();

        foreach ($period as $date) {
            $dateStr = $date->toDateString();

            // 1. Sync machine-level records (Dashboard & Standard Reports)
            StoreVendsRecord::dispatch($dateStr, $dateStr, true)->onQueue($queue);

            // 2. Sync product-level records (Sales & GP Reports)
            PersistGpMetricsJob::dispatch($dateStr)->onQueue($queue);

            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info("Success: All sync jobs have been dispatched to the '{$queue}' queue.");
        $this->comment("Please monitor the queue workers to ensure jobs are processed.");

        return 0;
    }
}
