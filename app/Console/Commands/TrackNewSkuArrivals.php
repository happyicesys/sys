<?php

namespace App\Console\Commands;

use App\Models\Vend;
use App\Services\Menu\NewSkuBadge;
use Illuminate\Console\Command;

/**
 * Hourly: records SKU arrivals for every mapped machine (NewSkuBadge::sync), so arrivals and
 * absences are dated even for a machine that has not re-read its menu. A menu read records
 * them too; this is the safety net.
 */
class TrackNewSkuArrivals extends Command
{
    protected $signature = 'menu:track-new-skus';

    protected $description = 'Record when each SKU arrived on each machine (menu NEW badge)';

    public function handle(): int
    {
        $count = 0;
        Vend::query()
            ->whereNotNull('product_mapping_id')
            ->select(['id', 'product_mapping_id'])
            ->chunkById(200, function ($vends) use (&$count) {
                foreach ($vends as $vend) {
                    NewSkuBadge::sync($vend);
                    $count++;
                }
            });

        $this->info("Synced {$count} machine(s).");

        return self::SUCCESS;
    }
}
