<?php

namespace App\Console\Commands;

use App\Services\Citybox\CatalogSyncService;
use Illuminate\Console\Command;

/**
 * Every-minute refresh of CityBox's enabled/disabled flag (Brian, 2026-09-05).
 *
 * Separate from the hourly `citybox:sync-products` on purpose: this one only
 * reads `status` and flips `products.is_active`, so it stays cheap enough to
 * run at this cadence. Creating products, importing thumbnails and delisting
 * absent SKUs remain hourly. Silent no-op when the openapi is disabled.
 */
class CityboxSyncProductStatus extends Command
{
    protected $signature = 'citybox:sync-product-status';

    protected $description = 'Mirror CityBox enabled/disabled SKU status onto mark1 products (every minute)';

    public function handle(CatalogSyncService $catalog): int
    {
        if (! config('citybox.openapi.enabled')) {
            return self::SUCCESS;
        }

        $r = $catalog->syncStatuses();
        $this->info(sprintf(
            'Citybox status: %d checked, %d changed — %d deactivated, %d reactivated.',
            $r['checked'], $r['changed'], $r['deactivated'], $r['reactivated']
        ));

        return self::SUCCESS;
    }
}
