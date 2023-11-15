<?php

namespace App\Jobs;

use App\Models\Vend;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SyncDeliveryPlatformMenu
//implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $vend;
    /**
     * Create a new job instance.
     */
    public function __construct(Vend $vend)
    {
        $this->vend = $vend;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        if($this->vend->deliveryProductMappingVends()->exists()) {
            foreach($vend->deliveryProductMappingVends as $deliveryProductMappingVend) {
                NotifyDeliveryPlatformUpdateMenu::dispatch($deliveryProductMappingVend)->onQueue('high');
            }
        }
    }
}
