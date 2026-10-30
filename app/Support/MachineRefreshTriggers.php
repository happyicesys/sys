<?php

namespace App\Support;

use App\Jobs\Vend\PushApkSettingSync;
use App\Jobs\Vend\PushMappingMenuSync;
use App\Jobs\Vend\PushVendSettingsSync;
use App\Models\Campaign;
use App\Models\CampaignItem;
use App\Models\Category;
use App\Models\DeliveryPlatformRefNumber;
use App\Models\DeliveryProductMappingVend;
use App\Models\Product;
use App\Models\Tag;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;

/**
 * Edits made outside a UI Setting or a mapping that still change what a machine shows, each told to the
 * machines at once instead of at their next restart (Brian, 2026-10-30). The pushes themselves are the
 * existing debounced jobs; this class only says WHICH edit reaches WHICH machines:
 *
 * - a campaign edited (its type, value, dates, labels) -> every UI Setting it is bound to re-sends
 *   `/parameters`, which carries `campaigns[]` (PushApkSettingSync);
 * - a label (tag) renamed or deleted -> the menu of every mapping holding a product with that label
 *   (`/thumbnails` `labels`), words only, plus every UI Setting whose campaign item names it
 *   (`promoLabelItems`, pre-213 boards);
 * - a category renamed or deleted -> the menu of every mapping holding a product in it
 *   (`/thumbnails` `product_sub_category`), words only;
 * - a Grab store bound, moved, paused, resumed, unbound or switched on/off -> that machine re-reads
 *   `/parameters` (`isGrabEnabled`, PushVendSettingsSync).
 *
 * Registered per model class, like OptionCacheBuster, and guarded on the columns that matter. A listener
 * never throws: a save must not fail because a push could not be queued. Mass query-builder updates
 * bypass it, like every Eloquent listener.
 */
class MachineRefreshTriggers
{
    public static function listen(): void
    {
        Event::listen('eloquent.saved: '.Campaign::class, fn (Campaign $campaign) => self::safely('campaign', function () use ($campaign) {
            foreach ($campaign->apkSettings()->withoutGlobalScopes()->pluck('apk_settings.id') as $apkSettingId) {
                PushApkSettingSync::schedule((int) $apkSettingId);
            }
        }));

        Event::listen('eloquent.saved: '.Tag::class, function (Tag $tag) {
            if ($tag->wasChanged('name')) {
                self::safely('tag', fn () => self::tagChanged((int) $tag->id));
            }
        });
        // deleting, not deleted: the bindings that say who shows the label may go with it.
        Event::listen('eloquent.deleting: '.Tag::class, fn (Tag $tag) => self::safely('tag', fn () => self::tagChanged((int) $tag->id)));

        Event::listen('eloquent.saved: '.Category::class, function (Category $category) {
            if ($category->wasChanged('name')) {
                self::safely('category', fn () => self::categoryChanged((int) $category->id));
            }
        });
        Event::listen('eloquent.deleting: '.Category::class, fn (Category $category) => self::safely('category', fn () => self::categoryChanged((int) $category->id)));

        // created / deleted always; updated only when what isGrabEnabled reads moved (the menu sync writes
        // last_menu_json there constantly). Not `saved` + wasRecentlyCreated: that flag stays true for the
        // object's life, so every later save of the same instance would push again.
        $grabBinding = fn (DeliveryProductMappingVend $binding) => self::safely('grab binding', function () use ($binding) {
            PushVendSettingsSync::schedule((int) $binding->vend_id);
            if ($binding->wasChanged('vend_id')) {
                PushVendSettingsSync::schedule((int) $binding->getOriginal('vend_id'));
            }
        });
        Event::listen('eloquent.created: '.DeliveryProductMappingVend::class, $grabBinding);
        Event::listen('eloquent.deleted: '.DeliveryProductMappingVend::class, $grabBinding);
        Event::listen('eloquent.updated: '.DeliveryProductMappingVend::class, function (DeliveryProductMappingVend $binding) use ($grabBinding) {
            if ($binding->wasChanged(['is_active', 'vend_id', 'delivery_platform_ref_number_id'])) {
                $grabBinding($binding);
            }
        });

        Event::listen('eloquent.saved: '.DeliveryPlatformRefNumber::class, function (DeliveryPlatformRefNumber $ref) {
            if ($ref->wasChanged('status')) {
                self::safely('grab store', function () use ($ref) {
                    DeliveryProductMappingVend::query()->withoutGlobalScopes()
                        ->where('delivery_platform_ref_number_id', $ref->id)
                        ->distinct()
                        ->pluck('vend_id')
                        ->each(fn ($vendId) => PushVendSettingsSync::schedule((int) $vendId));
                });
            }
        });
    }

    private static function tagChanged(int $tagId): void
    {
        $bindings = DB::table('tag_bindings')->where('tag_id', $tagId);

        PushMappingMenuSync::scheduleForProducts(
            (clone $bindings)->where('modelable_type', (new Product)->getMorphClass())->pluck('modelable_id'),
            textOnly: true,
        );

        $campaignItemIds = (clone $bindings)->where('modelable_type', (new CampaignItem)->getMorphClass())->pluck('modelable_id');
        if ($campaignItemIds->isNotEmpty()) {
            DB::table('campaign_items')->whereIn('id', $campaignItemIds)->whereNotNull('apk_setting_id')
                ->distinct()->pluck('apk_setting_id')
                ->each(fn ($apkSettingId) => PushApkSettingSync::schedule((int) $apkSettingId));
        }
    }

    private static function categoryChanged(int $categoryId): void
    {
        PushMappingMenuSync::scheduleForProducts(
            DB::table('products')->where('category_id', $categoryId)->pluck('id'),
            textOnly: true,
        );
    }

    private static function safely(string $what, callable $push): void
    {
        try {
            $push();
        } catch (\Throwable $e) {
            Log::warning('MachineRefreshTriggers: '.$what.' push not queued', ['error' => $e->getMessage()]);
        }
    }
}
