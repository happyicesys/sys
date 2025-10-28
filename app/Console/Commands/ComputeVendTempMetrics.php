<?php

namespace App\Console\Commands;

use App\Services\VendTempMetricService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class ComputeVendTempMetrics extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'vend-temp:compute-metrics
        {--date= : Target date (YYYY-MM-DD). Defaults to yesterday.}
        {--vend= : Restrict computation to a single vend ID.}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Compute and persist daily, rolling, and all-time temperature metrics.';

    /**
     * Execute the console command.
     */
    public function handle(VendTempMetricService $service): int
    {
        $date = $this->resolveDate();
        $vendId = $this->option('vend') ? (int) $this->option('vend') : null;

        $this->info(sprintf(
            'Computing vend temperature metrics for %s%s',
            $date->toDateString(),
            $vendId ? " (vend: {$vendId})" : ''
        ));

        $results = $service->computeDailyMetrics($date, $vendId);

        if ($results->isEmpty()) {
            $this->warn('No temperature readings found for the requested criteria.');
            return self::SUCCESS;
        }

        $results->each(function (array $row): void {
            $this->line(sprintf(
                '- Vend #%d type %d: min=%d max=%d (readings=%d)',
                $row['vend_id'],
                $row['temp_type'],
                $row['min_value'],
                $row['max_value'],
                $row['reading_count'],
            ));
        });

        $this->info('Metric computation complete.');

        return self::SUCCESS;
    }

    private function resolveDate(): Carbon
    {
        $dateOption = $this->option('date');

        if ($dateOption) {
            return Carbon::parse($dateOption)->startOfDay();
        }

        return now()->subDay()->startOfDay();
    }
}
