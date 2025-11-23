<?php

namespace App\Jobs;

use App\Models\DeliveryProductMappingVend;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class UpdateDeliveryMenuJson implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $deliveryProductMappingVendId;
    protected $menuJson;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($deliveryProductMappingVendId, array $menuJson)
    {
        $this->deliveryProductMappingVendId = $deliveryProductMappingVendId;
        $this->menuJson = $menuJson;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $deliveryProductMappingVend = DeliveryProductMappingVend::find($this->deliveryProductMappingVendId);

        if ($deliveryProductMappingVend) {
            $deliveryProductMappingVend->update([
                'last_menu_json' => $this->menuJson,
            ]);
        }
    }
}
