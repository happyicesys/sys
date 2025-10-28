<?php

namespace App\Console\Commands;

use App\Jobs\Vend\BackfillVendTempMetrics;
use Carbon\Carbon;
use Illuminate\Console\Command;

class QueueVendTempMetricsBackfill extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'vend-temp:queue-backfill
        {--days=90 : Number of days to backfill (ending at END date).}
        {--end= : End date (YYYY-MM-DD). Defaults to yesterday.}
        {--vend= : Restrict to a single vend ID.}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Queue a background job to backfill vend temperature metrics.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $end = $this->option('end')
            ? Carbon::parse($this->option('end'))->toDateString()
            : Carbon::now()->subDay()->toDateString();

        $days = (int) $this->option('days');
        $vendId = $this->option('vend') ? (int) $this->option('vend') : null;

        BackfillVendTempMetrics::dispatch(
            endDate: $end,
            days: $days,
            vendId: $vendId
        );

        $this->info(sprintf(
            'Queued vend temp metrics backfill job (days=%d, end=%s%s).',
            $days,
            $end,
            $vendId ? ", vend={$vendId}" : ''
        ));

        return self::SUCCESS;
    }
}
