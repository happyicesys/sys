<?php

namespace App\Services\Grab\Menu;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * What one store's Grab customers would see change: the menu Grab last pulled for the store
 * (delivery_product_mapping_vend.last_menu_json) against its machine menu under a price rule (MachineMenuLine).
 * Matched by product: a machine-menu item id carries it ("P123"), an old template item id is looked up in
 * delivery_product_mapping_items. Read only; the page's store panel shows it before anything is switched or saved.
 */
final class GrabMenuChange
{
    const SAME = 'same';

    const UP = 'up';

    const DOWN = 'down';

    // On the machine, not on Grab's menu today.
    const ADDED = 'added';

    // On Grab today, not on the machine (it leaves Grab).
    const REMOVED = 'removed';

    // On the machine, but no price or sub-category, so not listed.
    const NOT_LISTED = 'not_listed';

    // Grab has never pulled this store's menu, so there is nothing to compare with.
    const NO_CURRENT = 'no_current';

    /** @var array<int, array{name: string, price: ?int, available: bool}> what Grab shows now, by product id */
    private array $current;

    private bool $hasCurrent;

    /** @param  Collection<int, MachineMenuLine>  $lines  the store's machine menu, keyed by product id */
    public function __construct(?array $lastMenu, private Collection $lines)
    {
        $items = collect($lastMenu['categories'] ?? [])
            ->flatMap(fn ($category) => $category['subCategories'] ?? [])
            ->flatMap(fn ($sub) => $sub['items'] ?? []);
        $this->hasCurrent = $lastMenu !== null && $items->isNotEmpty();
        $this->current = $this->byProduct($items);
    }

    /** One row per product on the machine or on Grab today, the ones that change first. */
    public function rows(): array
    {
        $rows = $this->lines->map(function (MachineMenuLine $line) {
            $now = $this->current[$line->product->id] ?? null;
            $after = $line->isListed() ? $line->price : null;

            return [
                'product_id' => $line->product->id,
                'code' => $line->product->code,
                'name' => $line->product->name,
                'photo' => $line->product->thumbnail?->full_url,
                'rp' => $line->rp,
                'now' => $now['price'] ?? null,
                'after' => $after,
                'available' => $line->available(),
                'problem' => $line->problem(),
                'change' => $this->changeOf($now, $line),
            ];
        })->values();

        $removed = collect($this->current)
            ->reject(fn ($item, $productId) => $this->lines->has($productId))
            ->map(fn ($item, $productId) => [
                'product_id' => $productId,
                'code' => null,
                'name' => $item['name'],
                'photo' => null,
                'rp' => null,
                'now' => $item['price'],
                'after' => null,
                'available' => 0,
                'problem' => null,
                'change' => self::REMOVED,
            ])->values();

        $order = [self::UP => 0, self::DOWN => 1, self::ADDED => 2, self::REMOVED => 3, self::NOT_LISTED => 4, self::NO_CURRENT => 5, self::SAME => 6];

        return $rows->merge($removed)
            ->sortBy([fn ($a, $b) => $order[$a['change']] <=> $order[$b['change']], fn ($a, $b) => strcmp((string) $a['name'], (string) $b['name'])])
            ->values()
            ->all();
    }

    /** @return array{listed: int, sold_out: int, up: int, down: int, added: int, removed: int, not_listed: int, has_current: bool} */
    public function summary(): array
    {
        $rows = collect($this->rows());
        $listed = $rows->whereNotNull('after');

        return [
            'listed' => $listed->count(),
            'sold_out' => $listed->where('available', 0)->count(),
            'up' => $rows->where('change', self::UP)->count(),
            'down' => $rows->where('change', self::DOWN)->count(),
            'added' => $rows->where('change', self::ADDED)->count(),
            'removed' => $rows->where('change', self::REMOVED)->count(),
            'not_listed' => $rows->where('change', self::NOT_LISTED)->count(),
            'has_current' => $this->hasCurrent,
        ];
    }

    private function changeOf(?array $now, MachineMenuLine $line): string
    {
        if (! $line->isListed()) {
            return self::NOT_LISTED;
        }
        if (! $this->hasCurrent) {
            return self::NO_CURRENT;
        }
        if ($now === null || $now['price'] === null) {
            return self::ADDED;
        }

        return match ($line->price <=> $now['price']) {
            1 => self::UP,
            -1 => self::DOWN,
            default => self::SAME,
        };
    }

    /** @return array<int, array{name: string, price: ?int, available: bool}> */
    private function byProduct(Collection $items): array
    {
        $templateIds = $items->pluck('id')->filter(fn ($id) => GrabMenuItemId::productId($id) === null && ctype_digit((string) $id))->values();
        $templateProducts = $templateIds->isEmpty() ? collect() : DB::table('delivery_product_mapping_items')
            ->whereIn('id', $templateIds)
            ->pluck('product_id', 'id');

        $byProduct = [];
        foreach ($items as $item) {
            $productId = GrabMenuItemId::productId($item['id'] ?? null) ?? $templateProducts[(string) ($item['id'] ?? '')] ?? null;
            if (! $productId) {
                continue;
            }
            // A product on two template items (two slots) shows once, at its lowest price.
            $price = isset($item['price']) ? (int) $item['price'] : null;
            $known = $byProduct[$productId] ?? null;
            if ($known && $known['price'] !== null && ($price === null || $known['price'] <= $price)) {
                continue;
            }
            $byProduct[(int) $productId] = [
                'name' => (string) ($item['name'] ?? ''),
                'price' => $price,
                'available' => ($item['availableStatus'] ?? 'AVAILABLE') === 'AVAILABLE',
            ];
        }

        return $byProduct;
    }
}
