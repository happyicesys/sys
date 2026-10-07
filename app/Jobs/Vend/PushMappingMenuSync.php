<?php

namespace App\Jobs\Vend;

use App\Models\ProductMappingItem;
use App\Models\Vend;
use App\Services\VendJobService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Tell every VENDING machine on a product mapping to re-fetch its menu
 * (TYPESYNCAPICHANNELSLOTLIST - the frame "Push Products Info to Machine" sends)
 * after something it shows has changed: a mapping item added / removed / edited,
 * or a product's name, photo, labels or price.
 *
 * Before this, only binding a machine to a mapping pushed. A product added to a
 * channel of a mapping the machine was ALREADY on reached the screen as a photo
 * and a price with no name (4730, 2026-10-07): the APK re-reads its names list
 * only when told to, and nothing told it.
 *
 * Smart freezers are left to SmartFreezerCatalogPush, which ProductMappingController
 * already calls on every planogram write; chillers have no APK. Vending machines
 * are told here.
 *
 * DEBOUNCED per mapping, like PushApkSettingSync: the mapping editor commits each
 * cell as its own request and the product page saves every field at once, so a
 * burst of edits collapses into one push per machine. The frame carries no data -
 * the machine reads whatever is current when it fetches - and the delay also puts
 * the fetch safely after the request's commit.
 */
class PushMappingMenuSync implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** Window in which further edits join this push instead of queuing another. */
    public const DEBOUNCE_SECONDS = 10;

    /** Guard TTL - a safety net only; the job clears the key when it runs. */
    private const GUARD_TTL_SECONDS = 120;

    public function __construct(
        protected int $productMappingId,
        protected bool $serverPriceOnly = false,
    ) {}

    public static function cacheKey(int $productMappingId, bool $serverPriceOnly = false): string
    {
        return 'mapping-menu-push:'.$productMappingId.($serverPriceOnly ? ':server-price' : '');
    }

    /**
     * Queue a push for this mapping unless one is already pending.
     *
     * $serverPriceOnly: the change is a selling price, which only machines that
     * follow the Site's pricing display - the rest sell at the board price.
     */
    public static function schedule(?int $productMappingId, bool $serverPriceOnly = false): void
    {
        if (! $productMappingId) {
            return;
        }

        // Cache::add is atomic: only the first caller in the window wins.
        if (Cache::add(self::cacheKey($productMappingId, $serverPriceOnly), true, self::GUARD_TTL_SECONDS)) {
            self::dispatch($productMappingId, $serverPriceOnly)
                ->delay(now()->addSeconds(self::DEBOUNCE_SECONDS))
                ->onQueue('default');
        }
    }

    /**
     * A product changed: push every mapping it sits on.
     */
    public static function scheduleForProduct(?int $productId, bool $serverPriceOnly = false): void
    {
        if (! $productId) {
            return;
        }

        $mappingIds = ProductMappingItem::where('product_id', $productId)
            ->distinct()
            ->pluck('product_mapping_id');

        foreach ($mappingIds as $mappingId) {
            self::schedule((int) $mappingId, $serverPriceOnly);
        }
    }

    public function handle(VendJobService $vendJobService): void
    {
        // Released first, so an edit made while this job runs schedules the
        // next push instead of being swallowed by a stale guard.
        Cache::forget(self::cacheKey($this->productMappingId, $this->serverPriceOnly));

        // withoutGlobalScopes: a queue worker has no authenticated user, and the
        // push must reach every operator's machine on this mapping.
        $vends = Vend::withoutGlobalScopes()
            ->where('product_mapping_id', $this->productMappingId)
            // machine_type is set on every row in prod (2026-10-07), and every
            // "Smart Vend" model row is a smart_freezer - this one test is enough.
            ->where('machine_type', Vend::MACHINE_TYPE_VENDING_MACHINE)
            ->where(fn ($q) => $q->where('is_active', true)->orWhere('is_testing', true))
            ->when($this->serverPriceOnly, fn ($q) => $q->where('is_using_server_price', true))
            ->get(['id', 'code', 'private_key', 'machine_type']);

        foreach ($vends as $vend) {
            try {
                $vendJobService->syncChannelSlotListToVend($vend);
            } catch (\Throwable $e) {
                // One unreachable machine must not stop the rest.
                Log::warning('PushMappingMenuSync failed for vend', [
                    'product_mapping_id' => $this->productMappingId,
                    'vend_code' => $vend->code,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}
