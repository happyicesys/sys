<?php

namespace App\Console\Commands;

use App\Models\DeliveryProductMappingVend;
use App\Services\DeliveryPlatformService;
use Illuminate\Console\Command;

/**
 * Builds each selling store's menu as Grab would pull it now and compares it with
 * the menu Grab pulled last (last_menu_json). Read-only. Run after a deploy that
 * touches the menu builder: anything but stock / availability moving is a change
 * the deploy made.
 */
class GrabMenuDiff extends Command
{
    protected $signature = 'grab:menu-diff
                            {--mapping-vend=* : Only these delivery_product_mapping_vend ids}
                            {--details : List every changed item}';

    protected $description = 'Compare the menu mark1 would serve each Grab store with the one Grab pulled last';

    public function handle(DeliveryPlatformService $deliveryPlatformService): int
    {
        $query = DeliveryProductMappingVend::query()
            ->withoutGlobalScopes()
            ->selling()
            ->whereHas('deliveryProductMapping.deliveryPlatformOperator.deliveryPlatform', fn ($q) => $q->where('slug', 'grab'))
            ->orderBy('id');

        if ($ids = $this->option('mapping-vend')) {
            $query->whereIn('id', $ids);
        }

        $rows = [];
        $details = [];

        foreach ($query->get() as $mappingVend) {
            try {
                $built = $deliveryPlatformService->buildMenu($mappingVend);
            } catch (\Throwable $e) {
                $rows[] = [$mappingVend->id, $mappingVend->vend_code, 'ERROR: '.mb_strimwidth($e->getMessage(), 0, 60, '…'), '', '', '', '', ''];

                continue;
            }

            $last = $mappingVend->last_menu_json;

            if (! $last) {
                $rows[] = [$mappingVend->id, $mappingVend->vend_code, 'never pulled', '', '', '', '', ''];

                continue;
            }

            if (DeliveryPlatformService::comparableMenu($built) === DeliveryPlatformService::comparableMenu($last)) {
                $rows[] = [$mappingVend->id, $mappingVend->vend_code, 'same', 0, 0, 0, 0, 0];

                continue;
            }

            $now = self::itemsById($built);
            $before = self::itemsById($last);
            $count = ['added' => 0, 'removed' => 0, 'price' => 0, 'stock' => 0, 'other' => 0];

            foreach (array_diff_key($now, $before) as $id => $item) {
                $count['added']++;
                $details[] = [$mappingVend->vend_code, $id, 'added', $item['name'] ?? ''];
            }
            foreach (array_diff_key($before, $now) as $id => $item) {
                $count['removed']++;
                $details[] = [$mappingVend->vend_code, $id, 'removed', $item['name'] ?? ''];
            }
            foreach (array_intersect_key($now, $before) as $id => $item) {
                $old = $before[$id];
                if ((int) ($item['price'] ?? 0) !== (int) ($old['price'] ?? 0)) {
                    $count['price']++;
                    $details[] = [$mappingVend->vend_code, $id, 'price', ($old['price'] ?? '?').' → '.($item['price'] ?? '?')];
                }
                if ((int) ($item['maxStock'] ?? 0) !== (int) ($old['maxStock'] ?? 0) || ($item['availableStatus'] ?? null) !== ($old['availableStatus'] ?? null)) {
                    $count['stock']++;
                }
                $rest = fn ($i) => DeliveryPlatformService::comparableMenu(['x' => array_diff_key($i, array_flip(['price', 'maxStock', 'maxCount', 'availableStatus']))]);
                if ($rest($item) !== $rest($old)) {
                    $count['other']++;
                    $details[] = [$mappingVend->vend_code, $id, 'other', $item['name'] ?? ''];
                }
            }

            $structural = array_sum($count) - $count['stock'] === 0 ? 'stock only' : 'CHANGED';
            $rows[] = [$mappingVend->id, $mappingVend->vend_code, $structural, $count['added'], $count['removed'], $count['price'], $count['stock'], $count['other']];
        }

        $this->table(['mapping_vend', 'vend', 'result', 'added', 'removed', 'price', 'stock / availability', 'other'], $rows);

        if ($this->option('details') && $details) {
            $this->table(['vend', 'item id', 'change', 'detail'], $details);
        }

        return self::SUCCESS;
    }

    // Items keyed by Grab item id, from a Mart (category → subCategories → items) or a Food (category → items) menu.
    private static function itemsById(array $menu): array
    {
        $items = [];

        foreach ($menu['categories'] ?? [] as $category) {
            foreach ($category['items'] ?? [] as $item) {
                $items[(string) $item['id']] = $item;
            }
            foreach ($category['subCategories'] ?? [] as $subCategory) {
                foreach ($subCategory['items'] ?? [] as $item) {
                    $items[(string) $item['id']] = $item;
                }
            }
        }

        return $items;
    }
}
