<?php

namespace App\Services\Grab\Menu;

use App\Models\DeliveryPlatformOrder;
use App\Models\DeliveryProductMappingVend;
use App\Models\GrabMenuProduct;
use App\Models\GrabMenuSetting;
use App\Models\Product;
use App\Models\VendChannel;
use App\Services\DeliveryProductMappingService;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * A store's Grab menu read from its machine (Brian, 2026-11-01: "use back the original
 * product mapping from the machine"): every product on the machine's active slots, one
 * Grab item per product, its stock summed over the slots that hold it. Nothing is picked
 * or removed by hand; a planogram change reaches Grab with the machine's next channel
 * report. Read by the menu Grab pulls, order intake, Grab Promotions and the preview.
 */
class GrabMachineMenu
{
    public function __construct(private DeliveryProductMappingService $mappingService) {}

    /** @return Collection<int, MachineMenuLine> keyed by product id, by product code */
    public function lines(DeliveryProductMappingVend $binding): Collection
    {
        $operatorId = (int) ($binding->deliveryPlatformRefNumber?->operator_id ?? $binding->deliveryProductMapping?->operator_id);
        $channels = VendChannel::query()
            ->where('vend_id', $binding->vend_id)
            ->where('is_active', true)
            ->whereNotNull('product_id')
            ->with('vendChannelLatestError.vendChannelError')
            ->get(['id', 'vend_id', 'code', 'suffix', 'product_id', 'qty', 'capacity', 'is_active']);
        if ($channels->isEmpty()) {
            return collect();
        }

        $productIds = $channels->pluck('product_id')->unique()->values()->all();
        $held = self::heldQtyByVendChannel($channels->pluck('id')->all());
        $prices = new GrabPriceList(GrabMenuSetting::forOperator($operatorId), $productIds);
        $subCategories = GrabMenuProduct::query()
            ->where('operator_id', $operatorId)
            ->whereIn('product_id', $productIds)
            ->pluck('sub_category_json', 'product_id');
        $products = Product::query()->withoutGlobalScopes()
            ->with('thumbnail:id,full_url,attachments.modelable_type,attachments.modelable_id')
            ->whereIn('id', $productIds)
            ->get(['id', 'code', 'name', 'desc', 'translated_names_json'])
            ->keyBy('id');

        return $channels->groupBy('product_id')
            ->filter(fn ($slots, $productId) => $products->has($productId))
            ->map(function ($slots, $productId) use ($products, $held, $prices, $subCategories) {
                $productId = (int) $productId;

                return new MachineMenuLine(
                    product: $products[$productId],
                    slots: $slots->map(fn (VendChannel $channel) => [
                        'vend_channel_id' => $channel->id,
                        'code' => (string) $channel->code,
                        'available' => $this->available($channel, $held[$channel->id] ?? 0),
                    ])->sortByDesc('available')->values(),
                    price: $prices->price($productId),
                    rp: $prices->rp($productId),
                    subCategory: $subCategories[$productId] ?? null,
                    priceProblem: $prices->missingReason($productId),
                );
            })
            ->sortBy(fn (MachineMenuLine $line) => $line->product->code);
    }

    /** @return Collection<int, MachineMenuLine> only what Grab lists */
    public function listed(DeliveryProductMappingVend $binding): Collection
    {
        return $this->lines($binding)->filter->isListed();
    }

    /**
     * Units on open Grab orders per slot (order_item_vend_channels.vend_channel_id): the
     * same open-order rule as DeliveryProductMappingService::heldQtyByChannel, keyed by
     * the slot so template and machine-menu orders count alike.
     *
     * @param  int[]  $vendChannelIds
     * @return array<int, int>
     */
    public static function heldQtyByVendChannel(array $vendChannelIds): array
    {
        if (! $vendChannelIds) {
            return [];
        }

        return DB::table('order_item_vend_channels')
            ->join('delivery_platform_orders', 'delivery_platform_orders.id', '=', 'order_item_vend_channels.delivery_platform_order_id')
            ->whereIn('order_item_vend_channels.vend_channel_id', $vendChannelIds)
            ->where('delivery_platform_orders.created_at', '>=', Carbon::now()->subHours(DeliveryPlatformOrder::ORDER_EXPIRED_HOURS))
            ->where('delivery_platform_orders.is_cancelled', false)
            ->whereNull('delivery_platform_orders.dispensed_at')
            ->whereNotIn('delivery_platform_orders.status', DeliveryPlatformOrder::STATUSES_NOT_HOLDING_STOCK)
            ->groupBy('order_item_vend_channels.vend_channel_id')
            ->selectRaw('order_item_vend_channels.vend_channel_id AS channel_id, SUM(order_item_vend_channels.qty) AS held')
            ->pluck('held', 'channel_id')
            ->map(fn ($qty) => (int) $qty)
            ->all();
    }

    // What one slot can sell on Grab now: its stock less units on open orders; 0 while in error.
    private function available(VendChannel $channel, int $held): int
    {
        if ($channel->capacity <= 0 || $this->mappingService->getVendChannelErrorStatus($channel)) {
            return 0;
        }

        return max(0, (int) $channel->qty - $held);
    }
}
