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
        $deliveryProductMappingVends = DeliveryProductMappingVend::query()
            ->whereHas('deliveryProductMapping.deliveryPlatformOperator.deliveryPlatform', function ($query) {
                $query->where('slug', 'grab');
            })
            ->get();

        if(count($deliveryProductMappingVends) > 0) {
            foreach($deliveryProductMappingVends as $deliveryProductMappingVend) {
                NotifyDeliveryPlatformUpdateMenu::dispatch($deliveryProductMappingVend)->onQueue('high');
            }
        }
    }
}
