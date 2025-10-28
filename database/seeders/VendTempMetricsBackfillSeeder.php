<?php

namespace Database\Seeders;

use App\Jobs\Vend\BackfillVendTempMetrics;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class VendTempMetricsBackfillSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        dispatch(new BackfillVendTempMetrics(
            endDate: Carbon::now()->subDay()->toDateString(),
            days: 90
        ));

        $this->command?->info('Queued vend temp metrics backfill job for the last 90 days.');
    }
}
