<?php

namespace App\Console\Commands;

use App\Services\Citybox\CityboxPriceGroupSync;
use Illuminate\Console\Command;

/** Hourly: CityBox price groups → citybox_price_groups + RP rows on CityBox-owned products. */
class CityboxSyncPriceGroups extends Command
{
    protected $signature = 'citybox:sync-price-groups';

    protected $description = 'Mirror CityBox price groups and project RP1–RP5 groups onto selling prices';

    public function handle(CityboxPriceGroupSync $sync): int
    {
        if (! config('citybox.openapi.enabled')) {
            return self::SUCCESS;
        }

        try {
            $s = $sync->sync();
        } catch (\Throwable $e) {
            $this->error('Price group sync failed: '.$e->getMessage());

            return self::FAILURE;
        }
        $this->info(sprintf('%d row(s) in %d group(s); %d removed; RP prices: %d added, %d changed, %d deleted.',
            $s['rows'], $s['groups'], $s['removed'], $s['added'], $s['changed'], $s['deleted']));

        return self::SUCCESS;
    }
}
