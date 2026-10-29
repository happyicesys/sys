<?php

namespace App\Jobs;

use App\Models\DeliveryProductMappingVend;
use App\Services\DeliveryPlatformService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Tells Grab to re-pull one store's menu.
 *
 * Every VMC CHANNEL frame used to send this at once (~750 full re-syncs a day over
 * ~70 stores, 2026-10), against Grab's own rule of a full sync about once a day.
 * Now a change is sent through debounced(): the job waits
 * grab.menu_notify_delay_seconds, changes made while it waits ride along (one job
 * per store), and it sends nothing when the menu Grab would pull equals the one it
 * pulled last. A store mark1 does not sell (paused or unbound) is never notified,
 * forced or not: its menu pull is refused.
 */
class NotifyDeliveryPlatformUpdateMenu implements ShouldBeUniqueUntilProcessing, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    // Kept so a job serialized before 2026-10-29 still unserializes; no longer set.
    protected $deliveryPlatformService;

    protected $deliveryProductMappingVend;

    // true = the daily full sync: send even when nothing changed.
    protected bool $force;

    public function __construct(DeliveryProductMappingVend $deliveryProductMappingVend, bool $force = false)
    {
        $this->deliveryProductMappingVend = $deliveryProductMappingVend;
        $this->force = $force;
    }

    public static function debounced(DeliveryProductMappingVend $deliveryProductMappingVend): void
    {
        static::dispatch($deliveryProductMappingVend)
            ->onQueue('default')
            ->delay(now()->addSeconds(config('grab.menu_notify_delay_seconds', 180)));
    }

    public function uniqueId(): string
    {
        return $this->deliveryProductMappingVend->id.($this->force ? ':force' : '');
    }

    public function uniqueFor(): int
    {
        return (int) config('grab.menu_notify_delay_seconds', 180) + 600;
    }

    public function handle(DeliveryPlatformService $deliveryPlatformService): void
    {
        $mappingVend = $this->deliveryProductMappingVend->fresh();

        if (! $mappingVend || ! $mappingVend->isSelling()) {
            return;
        }

        if (! $this->force && ! $deliveryPlatformService->menuChangedSinceLastPull($mappingVend)) {
            return;
        }

        $deliveryPlatformService->notifyUpdatedMenu($mappingVend);
    }
}
