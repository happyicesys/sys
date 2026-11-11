<?php

namespace App\Services\Product;

use App\Models\SellingPrice;
use App\Models\User;
use App\Models\Vend;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Has the machine taken the price mark1 wants it to sell at? (Brian, 2026-11-11, machine 10033 in Indonesia: the
 * machine page showed "Ref Price(3)" over RP2 figures, so staff could not tell whether a Site RP or selling price
 * change had reached the machine, and switched the RP back and forth.)
 *
 * A machine that follows the Site's pricing reads its prices from `/thumbnails` (`server_price` per channel, the
 * Site's tier) — at start, and whenever mark1 tells it to re-read (a Site RP change through VendPricingSourceService,
 * a selling price change through PushMappingMenuSync). Its own fetch is the proof: `recordFetch()` stores when it was
 * served and the price served for each channel, and `report()` compares that with what it would be served NOW:
 *   current — the machine holds today's price;
 *   stale   — it fetched an older price, and has not re-read since the change;
 *   unknown — no fetch recorded yet (tracking began 2026-11-11), or the channel was not in the menu it fetched.
 * Same idea as the payment menu (OperatorPaymentMenu::deliveryState). A machine on its board price (Machine Price)
 * sells at the VMC's own prices, so there is nothing to deliver and `report()` says so.
 */
class MachinePriceDelivery
{
    /** Shown in the page's hover: when tracking began, so "not confirmed yet" on an old machine makes sense. */
    public const TRACKING_SINCE = '2026-11-11';

    /** A machine heard on MQTT within this window is reachable by the re-read frame (SyncOnlineStatus's own rule). */
    private const MQTT_REACHABLE_MINUTES = 15;

    /**
     * The machine fetched its menu: remember what it was given. `$served` = [channel code => server_price cents or
     * null], exactly as `/thumbnails` sent it. Never throws — a failure here must not cost the machine its menu.
     */
    public static function recordFetch(int $vendId, array $served): void
    {
        try {
            DB::table('vends')->where('id', $vendId)->update([
                'price_menu_fetched_at' => now(),
                'price_menu_prices_json' => json_encode((object) $served),
            ]);
        } catch (\Throwable $e) {
            Log::warning('price menu fetch not recorded', ['vend_id' => $vendId, 'error' => $e->getMessage()]);
        }
    }

    /**
     * Whether a `/thumbnails` request came from a machine's app. Every vending APK fetches it with OkHttp
     * (`okhttp/4.12.0` on the 30x line, okhttp 3 on the older ones), which names itself in the User-Agent; a browser
     * or a script does not, so it never counts as the machine loading its prices.
     */
    public static function isMachineClient(?string $userAgent): bool
    {
        $ua = strtolower((string) $userAgent);

        return str_starts_with($ua, 'okhttp/') || str_contains($ua, 'dalvik/');
    }

    /** mark1 has just told the machine to re-read its menu. Never throws. */
    public static function recordPush(int $vendId): void
    {
        try {
            DB::table('vends')->where('id', $vendId)->update(['price_menu_pushed_at' => now()]);
        } catch (\Throwable $e) {
            Log::warning('price menu push not recorded', ['vend_id' => $vendId, 'error' => $e->getMessage()]);
        }
    }

    /**
     * The price `/thumbnails` would serve each channel now, by the same rule (VendController::getVendAllChannelThumbnails):
     * the mapping item on the channel's code, priced at the Site's tier; null when the machine sells at its board price,
     * the code has no mapping item, or the product has no price in that tier.
     *
     * @return array<string, int|null> keyed by channel code
     */
    public function expected(Vend $vend): array
    {
        $vend->loadMissing(['vendChannels', 'productMapping.productMappingItems']);
        $items = $vend->productMapping?->productMappingItems->keyBy('channel_code');
        if (! $items) {
            return [];
        }

        $type = $vend->serverPriceType();
        $prices = $type
            ? SellingPrice::query()->setEagerLoads([])->where('type', $type)->whereIn('product_id', $items->pluck('product_id')->unique()->all())->get()->keyBy('product_id')
            : collect();

        $out = [];
        foreach ($vend->vendChannels as $channel) {
            $item = $items->get((int) $channel->code);
            $out[(string) $channel->code] = $item ? (isset($prices[$item->product_id]) ? (int) $prices[$item->product_id]->amount : null) : null;
        }

        return $out;
    }

    /**
     * Everything the machine page needs, per channel: the live Ref Price (the Site's tier for the product on the
     * channel — what the page's Ref Price column means), the price the machine should hold, the price it loaded, and
     * who set that price and when.
     */
    public function report(Vend $vend): array
    {
        $vend->loadMissing(['customer:id,name,selling_price_type', 'vendChannels']);
        $tier = $vend->customer?->selling_price_type ? (int) $vend->customer->selling_price_type : null;
        $followsSite = $vend->usesServerPrice();
        $expected = $followsSite ? $this->expected($vend) : [];

        $fetchedAt = $vend->price_menu_fetched_at ? Carbon::parse($vend->price_menu_fetched_at) : null;
        $loaded = $vend->price_menu_prices_json;
        $loaded = is_string($loaded) ? json_decode($loaded, true) : $loaded;
        $loaded = is_array($loaded) ? $loaded : null;

        // The tier's rows for every product on the machine: the live Ref Price, and who set it and when.
        $productIds = $vend->vendChannels->pluck('product_id')->filter()->unique()->values()->all();
        $tierRows = $tier && $productIds
            ? SellingPrice::query()->setEagerLoads([])->where('type', $tier)->whereIn('product_id', $productIds)
                ->orderBy('id')->get(['id', 'product_id', 'amount', 'previous_amount', 'created_at', 'created_by'])->keyBy('product_id')
            : collect();
        $setters = User::whereIn('id', $tierRows->pluck('created_by')->filter()->unique()->all())->pluck('name', 'id');

        $channels = [];
        foreach ($vend->vendChannels as $channel) {
            $code = (string) $channel->code;
            $row = $channel->product_id ? $tierRows->get($channel->product_id) : null;
            $want = $expected[$code] ?? null;
            $has = $loaded !== null && array_key_exists($code, $loaded) ? ($loaded[$code] === null ? null : (int) $loaded[$code]) : null;
            $known = $loaded !== null && array_key_exists($code, $loaded);

            $state = null; // Machine Price: nothing to deliver.
            if ($followsSite) {
                $state = ! $known ? 'unknown' : ($has === $want ? 'current' : 'stale');
            }

            $channels[$code] = [
                'code' => $channel->code,
                'ref_price' => $row ? (int) $row->amount : null,
                'expected' => $want,
                'loaded' => $has,
                'state' => $state,
                'price_set_at' => $row?->created_at ? Carbon::parse($row->created_at)->toIso8601String() : null,
                'price_set_by' => $row?->created_by ? ($setters[$row->created_by] ?? null) : null,
                'previous_amount' => $row?->previous_amount !== null ? (int) $row->previous_amount : null,
            ];
        }

        $states = collect($channels)->pluck('state')->filter();
        $mqttSeen = $vend->mqtt_last_updated_at ? Carbon::parse($vend->mqtt_last_updated_at) : null;

        return [
            'vend_id' => $vend->id,
            'tier' => $tier,
            'follows_site' => $followsSite,
            'state' => ! $followsSite ? null : ($states->contains('stale') ? 'stale' : ($states->contains('unknown') ? 'unknown' : 'current')),
            'stale_count' => $states->filter(fn ($s) => $s === 'stale')->count(),
            'fetched_at' => $fetchedAt?->toIso8601String(),
            'pushed_at' => $vend->price_menu_pushed_at ? Carbon::parse($vend->price_menu_pushed_at)->toIso8601String() : null,
            'mqtt_seen_at' => $mqttSeen?->toIso8601String(),
            'reachable' => $mqttSeen !== null && $mqttSeen->gte(now()->subMinutes(self::MQTT_REACHABLE_MINUTES)),
            'tracking_since' => self::TRACKING_SINCE,
            'channels' => $channels,
        ];
    }

    /** The machine kinds this applies to: a vending machine (a freezer's and a chiller's prices travel other ways). */
    public static function applies(Vend $vend): bool
    {
        return $vend->machine_type === Vend::MACHINE_TYPE_VENDING_MACHINE || $vend->machine_type === null;
    }
}
