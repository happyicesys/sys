<?php

namespace App\Jobs;

use App\Models\DeliveryProductMappingVend;
use App\Services\Grab\Menu\GrabMenuSyncTracker;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;

class UpdateDeliveryMenuJson implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $deliveryProductMappingVendId;

    // null = Grab pulled the same menu as last time (only the pull is recorded).
    protected $menuJson;

    // When Grab pulled it (ISO); null on a job queued before 2026-11-21.
    protected $pulledAt;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($deliveryProductMappingVendId, ?array $menuJson, ?string $pulledAt = null)
    {
        $this->deliveryProductMappingVendId = $deliveryProductMappingVendId;
        $this->menuJson = $menuJson;
        $this->pulledAt = $pulledAt;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $deliveryProductMappingVend = DeliveryProductMappingVend::find($this->deliveryProductMappingVendId);

        if (! $deliveryProductMappingVend) {
            return;
        }

        $before = $deliveryProductMappingVend->last_menu_json;
        if ($this->menuJson !== null) {
            $deliveryProductMappingVend->update([
                'last_menu_json' => $this->menuJson,
            ]);
        }

        // Grab Menu & Prices shows when Grab last took this store's menu and when its items or prices changed.
        app(GrabMenuSyncTracker::class)->pulled(
            $deliveryProductMappingVend,
            $this->pulledAt ? Carbon::parse($this->pulledAt) : now(),
            is_array($before) ? $before : null,
            $this->menuJson,
        );
    }
}
