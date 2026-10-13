<?php

namespace App\Services\Citybox;

use App\Contracts\Citybox\ChillerGateway;
use App\Models\CityboxPriceGroup;
use App\Models\CityboxProduct;
use App\Models\Product;
use App\Models\SellingPrice;
use App\Services\Product\SellingPriceHistory;
use Illuminate\Support\Facades\DB;

/**
 * CityBox price groups (OPS Pro 价格组管理, `price_group_list`) → mark1 (Brian, 2026-10-13).
 *
 *  1. Mirror: every row as they send it into `citybox_price_groups` (one per group × product), a
 *     row that left their list stamped `removed_at`. Kept whatever the group is called.
 *  2. Project: a group named RP1–RP5 — their naming already matches ours — becomes that RP's
 *     `selling_prices` row on each CityBox-OWNED product (Product::isCityboxOwned: code = their
 *     id). Their portal is the master for those products, so the sync owns their RP rows: adds,
 *     changes and removes them, and nobody edits them on Product → Edit (read-only there).
 *     A product a person mapped by hand keeps its own prices; it is never touched.
 *
 * A change follows the house rule for selling prices: delete + add, `previous_amount` on the new
 * row, `created_by` NULL (no person), and a `user_logs` pair (source citybox-sync) so "Show price
 * history" says "CityBox price group". The charged chiller price is still their live
 * `device_product` price — which group a machine is on is not in their API yet (asked 2026-10-13).
 */
class CityboxPriceGroupSync
{
    public const LOG_SOURCE = 'citybox-sync';

    public const LOG_NAME = 'CityBox price group';

    public function __construct(
        private ChillerGateway $gateway,
        private SellingPriceHistory $history,
    ) {}

    /** @return array{rows:int, groups:int, removed:int, added:int, changed:int, deleted:int} */
    public function sync(): array
    {
        $rows = $this->gateway->priceGroups()->filter(fn ($r) => $r->groupId > 0 && $r->cityboxProductId > 0 && $r->active);
        $now = now();
        $links = CityboxProduct::whereIn('citybox_product_id', $rows->pluck('cityboxProductId')->unique())
            ->whereNotNull('product_id')->pluck('product_id', 'citybox_product_id')->map(fn ($id) => (int) $id)->all();

        $stats = ['rows' => $rows->count(), 'groups' => $rows->pluck('groupId')->unique()->count(), 'removed' => 0, 'added' => 0, 'changed' => 0, 'deleted' => 0];

        DB::transaction(function () use ($rows, $now, $links, &$stats) {
            foreach ($rows->chunk(500) as $chunk) {
                CityboxPriceGroup::upsert($chunk->map(fn ($r) => [
                    'group_id' => $r->groupId,
                    'group_name' => mb_substr($r->groupName, 0, 64),
                    'citybox_product_id' => $r->cityboxProductId,
                    'product_id' => $links[$r->cityboxProductId] ?? null,
                    'price_cents' => $r->priceCents,
                    'img_url' => $r->imgUrl ? mb_substr($r->imgUrl, 0, 512) : null,
                    'first_seen_at' => $now,
                    'last_seen_at' => $now,
                    'removed_at' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ])->values()->all(), ['group_id', 'citybox_product_id'],
                    ['group_name', 'product_id', 'price_cents', 'img_url', 'last_seen_at', 'removed_at', 'updated_at']);
            }
            // An empty answer is not "every group was deleted" — an API hiccup must not wipe prices.
            if ($rows->isNotEmpty()) {
                // By membership, not by time: two syncs inside one second must still see a removal.
                $seen = $rows->mapWithKeys(fn ($r) => [$r->groupId.':'.$r->cityboxProductId => true])->all();
                $gone = CityboxPriceGroup::whereNull('removed_at')->get(['id', 'group_id', 'citybox_product_id'])
                    ->reject(fn ($g) => isset($seen[$g->group_id.':'.$g->citybox_product_id]))->pluck('id');
                if ($gone->isNotEmpty()) {
                    $stats['removed'] = CityboxPriceGroup::whereIn('id', $gone)->update(['removed_at' => $now, 'updated_at' => $now]);
                }
            }
        });

        if ($rows->isNotEmpty()) {
            foreach ($this->project() as $key => $n) {
                $stats[$key] += $n;
            }
        }

        return $stats;
    }

    /** @return array{added:int, changed:int, deleted:int} */
    private function project(): array
    {
        $out = ['added' => 0, 'changed' => 0, 'deleted' => 0];

        // product id => type => cents, from groups named RP1–RP5 on CityBox-owned products.
        $want = [];
        foreach (CityboxPriceGroup::current()->whereNotNull('product_id')->get() as $row) {
            $type = self::rpType($row->group_name);
            if ($type !== null) {
                $want[(int) $row->product_id][$type] = (int) $row->price_cents;
            }
        }
        $owned = $this->ownedProductIds();
        $want = array_intersect_key($want, array_flip($owned));

        $have = SellingPrice::without('product')->whereIn('product_id', $owned)->get()->groupBy('product_id');
        foreach ($owned as $productId) {
            $rows = $have->get($productId, collect())->groupBy('type');
            $types = array_unique(array_merge(array_keys($want[$productId] ?? []), $rows->keys()->map(fn ($t) => (int) $t)->all()));
            foreach ($types as $type) {
                $current = $rows->get($type, collect());
                $target = $want[$productId][$type] ?? null;
                if ($target !== null && $current->count() === 1 && (int) $current->first()->getRawOriginal('amount') === $target) {
                    continue;
                }
                DB::transaction(function () use ($productId, $type, $current, $target, &$out) {
                    foreach ($current as $old) {
                        $this->log('deleted', $old);
                        $old->delete();
                    }
                    if ($target === null) {
                        $out['deleted']++;

                        return;
                    }
                    // Cents straight in: the model's amount mutator takes DOLLARS (×100), and 1.7 × 100
                    // is not an integer in floating point.
                    $new = new SellingPrice;
                    $new->setRawAttributes([
                        'product_id' => $productId,
                        'type' => $type,
                        'amount' => $target,
                        'created_by' => null,
                        'previous_amount' => $current->isNotEmpty() ? (int) $current->first()->getRawOriginal('amount') : $this->history->previousAmount($productId, $type),
                    ]);
                    $new->save();
                    $this->log('created', $new);
                    $out[$current->isNotEmpty() ? 'changed' : 'added']++;
                });
            }
        }

        return $out;
    }

    /** "RP1", "rp 2", "RP-3" → 1..5; anything else is not one of our tiers. */
    public static function rpType(string $groupName): ?int
    {
        return preg_match('/^\s*RP[\s\-_]*([1-5])\s*$/i', $groupName, $m) ? (int) $m[1] : null;
    }

    /** @return list<int> products whose code IS their CityBox id (Product::isCityboxOwned, in bulk) */
    private function ownedProductIds(): array
    {
        // Compared in PHP: a SQL `code = CAST(id AS CHAR)` mixes collations on prod MySQL
        // (products.code is utf8mb4_unicode_ci, a CAST takes the connection's 0900_ai_ci).
        $links = CityboxProduct::whereNotNull('product_id')->get(['product_id', 'citybox_product_id']);
        $codes = Product::withoutGlobalScopes()->whereIn('id', $links->pluck('product_id')->unique())->pluck('code', 'id');

        return $links->filter(fn ($l) => (string) ($codes[$l->product_id] ?? '') === (string) $l->citybox_product_id)
            ->pluck('product_id')->map(fn ($id) => (int) $id)->unique()->values()->all();
    }

    /** Console writes are not audited by UserLogger; write the pair the price history reads. */
    private function log(string $event, SellingPrice $row): void
    {
        DB::table('user_logs')->insert([
            'user_id' => null,
            'user_name' => self::LOG_NAME,
            'event' => $event,
            'auditable_type' => SellingPrice::class,
            'auditable_id' => $row->id,
            'changes' => json_encode(['id' => $row->id, 'amount' => (int) $row->getRawOriginal('amount'), 'product_id' => (int) $row->product_id, 'type' => (int) $row->type]),
            'source' => self::LOG_SOURCE,
            'ip' => null,
            'url' => 'citybox:sync-price-groups',
            'created_at' => now(),
        ]);
    }

    /** Current RP prices CityBox sets for one product: [type => ['group' => …, 'synced_at' => …]]. */
    public static function sourcesFor(Product $product): array
    {
        $out = [];
        foreach (CityboxPriceGroup::current()->where('product_id', $product->id)->orderBy('group_name')->get() as $row) {
            $type = self::rpType($row->group_name);
            $out[] = [
                'group_id' => (int) $row->group_id,
                'group_name' => $row->group_name,
                'type' => $type,
                'price_cents' => (int) $row->price_cents,
                'synced_at' => $row->last_seen_at?->toIso8601String(),
            ];
        }

        return $out;
    }
}
