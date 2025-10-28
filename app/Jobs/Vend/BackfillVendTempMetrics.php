<?php

namespace App\Jobs\Vend;

use App\Services\VendTempMetricService;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class BackfillVendTempMetrics implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public ?string $endDate = null,
        public int $days = 90,
        public ?int $vendId = null
    ) {
    }

    /**
     * Execute the job.
     */
    public function handle(VendTempMetricService $service): void
    {
        $end = $this->resolveEndDate();
        $days = max(1, $this->days);
        $start = (clone $end)->subDays($days - 1);

        $cursor = $start->copy();

        while ($cursor->lte($end)) {
            $service->computeDailyMetrics($cursor->copy(), $this->vendId);
            $cursor->addDay();
        }
    }

    private function resolveEndDate(): Carbon
    {
        if ($this->endDate) {
            return Carbon::parse($this->endDate)->startOfDay();
        }

        return Carbon::now()->subDay()->startOfDay();
    }
}
