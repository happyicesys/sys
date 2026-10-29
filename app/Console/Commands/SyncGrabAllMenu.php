<?php

namespace App\Console\Commands;

use App\Jobs\NotifyDeliveryPlatformUpdateMenu;
use App\Models\DeliveryProductMappingVend;
use Illuminate\Console\Command;

class SyncGrabAllMenu extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'grab:sync-all-menu';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync all the grab menu with binded delivery product mapping vend';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // Only stores mark1 sells: an ended or paused row's menu pull is refused, so
        // notifying it only produced a FAILED sync (all 127 rows ever were notified).
        $deliveryProductMappingVends = DeliveryProductMappingVend::query()
            ->selling()
            ->whereHas('deliveryProductMapping.deliveryPlatformOperator.deliveryPlatform', function ($query) {
                $query->where('slug', 'grab');
            })
            ->get();

        // The daily full sync Grab recommends: sent even when nothing changed.
        foreach($deliveryProductMappingVends as $deliveryProductMappingVend) {
            NotifyDeliveryPlatformUpdateMenu::dispatch($deliveryProductMappingVend, true)->onQueue('high');
        }
    }
}
