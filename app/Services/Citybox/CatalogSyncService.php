<?php

namespace App\Services\Citybox;

use App\Contracts\Citybox\ChillerGateway;
use App\Models\CityboxProduct;
use App\Models\CityboxProductSyncLog;
use App\Models\Operator;
use App\Models\Product;
use App\Services\Citybox\DTO\ChillerCatalogItem;
use App\Services\Citybox\DTO\ChillerStockLine;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Keeps `citybox_products` a faithful, idempotent mirror of CityBox's SKU
 * catalog. Two entry points converge on one upsert:
 *
 *  - syncCatalog()       hourly (or manual): full `product_list` → add /
 *                        update / SOFT-delist absent rows. The only path
 *                        allowed to delist, because only it sees the whole set.
 *  - noteSeenOnDevice()  from the 3-min stock poll: upsert any product a
 *                        device shows that we don't have yet, and enrich
 *                        volume/unit/class/price (catalog rows lack them).
 *                        Never delists — a device sees a subset.
 *
 * Idempotency is the UNIQUE index on citybox_product_id; every path is an
 * upsert on that key.
 *
 * Brian, 2026-08-19 (after seeing the mapping page): NO human mapping step.
 * Every live CityBox SKU gets a mark1 `products` row automatically — code =
 * the CityBox product id, under the Citybox operator, warehouse qty from the
 * mark1 ledger, CityBox's image as its thumbnail — and the link is written
 * here (ensureMark1Products). A link a human made earlier is left alone.
 */
class CatalogSyncService
{
    public function __construct(private ChillerGateway $gateway) {}

    public function syncCatalog(string $source = CityboxProductSyncLog::SOURCE_CATALOG_SCHEDULED, ?int $userId = null): CityboxProductSyncLog
    {
        $log = CityboxProductSyncLog::create([
            'source' => $source, 'triggered_by' => $userId, 'started_at' => now(),
        ]);

        try {
            $items = $this->gateway->catalog();
            $result = $this->applyCatalog($items);
            $result['details_json']['products_created'] = $this->ensureMark1Products();
            $log->fill($result + ['fetched' => $items->count(), 'finished_at' => now()])->save();
        } catch (\Throwable $e) {
            $log->fill(['error' => $e->getMessage(), 'finished_at' => now()])->save();
            throw $e;
        }

        return $log;
    }

    /**
     * Called by the stock poller with one device's lines. Cheap: only rows
     * whose id is unknown, or whose enrichment fields are still empty, are
     * written. Returns the ids it ADDED (for change-gated logging).
     *
     * @param  Collection<int,ChillerStockLine>  $lines
     * @return int[]
     */
    public function noteSeenOnDevice(Collection $lines): array
    {
        if ($lines->isEmpty()) {
            return [];
        }
        $ids = $lines->map(fn (ChillerStockLine $l) => $l->cityboxProductId)->all();
        $existing = CityboxProduct::whereIn('citybox_product_id', $ids)->get()->keyBy('citybox_product_id');
        $added = [];

        foreach ($lines as $line) {
            $row = $existing->get($line->cityboxProductId);
            $enrich = [
                'volume' => $line->volume, 'unit' => $line->unit,
                'class_id' => $line->classId, 'class_name' => $line->className,
                'last_price_cents' => $line->effectivePriceCents(),
                'last_seen_at' => now(), 'last_seen_source' => CityboxProduct::SOURCE_DEVICE,
            ];
            if (! $row) {
                CityboxProduct::create($enrich + [
                    'citybox_product_id' => $line->cityboxProductId,
                    'name' => $line->name,
                    'img_url' => $line->thumbnailUrl,
                    'first_seen_at' => now(),
                ]);
                $added[] = $line->cityboxProductId;

                continue;
            }
            // Only fill blanks + refresh price/seen; the catalog run owns name/images.
            $row->fill(array_filter($enrich, fn ($v) => $v !== null) + [
                'volume' => $row->volume ?? $line->volume,
                'unit' => $row->unit ?? $line->unit,
                'class_id' => $row->class_id ?? $line->classId,
                'class_name' => $row->class_name ?? $line->className,
            ])->save();
        }

        if ($added) {
            $created = $this->ensureMark1Products();
            CityboxProductSyncLog::create([
                'source' => CityboxProductSyncLog::SOURCE_DEVICE_POLL, 'started_at' => now(), 'finished_at' => now(),
                'fetched' => $lines->count(), 'added' => count($added), 'details_json' => ['added' => $added, 'products_created' => $created],
            ]);
        }

        return $added;
    }

    /**
     * Give every live CityBox SKU a mark1 product and link it (Brian, 2026-08-19).
     *
     *  - unlinked row  → reuse `products.code = <citybox id>` if it exists (any
     *                    operator — never create a second one), else CREATE it
     *                    under the Citybox operator; write the link.
     *  - linked row whose product carries our code → keep name + thumbnail in
     *    step with CityBox (their catalog is the master for these rows).
     *  - linked row whose product is a HUMAN mapping (different code) → untouched.
     *  - delisted rows → untouched (product stays; ops history still resolves).
     *
     * Idempotent on the link, then on products.code. Returns the ids created.
     *
     * @return int[] citybox_product_ids for which a product was created
     */
    public function ensureMark1Products(): array
    {
        $operator = Operator::where('code', config('citybox.operator_code', 'CB'))->first();
        if (! $operator) {
            Log::warning('Citybox: operator not seeded — SKUs left without mark1 products (run CityboxOperatorSeeder)');

            return [];
        }

        $rows = CityboxProduct::where('is_delisted', false)->get();
        $products = Product::withoutGlobalScopes()
            ->whereIn('code', $rows->map(fn (CityboxProduct $r) => (string) $r->citybox_product_id)->all())
            ->orWhereIn('id', $rows->pluck('product_id')->filter()->all())
            ->get();
        $byCode = $products->keyBy(fn (Product $p) => (string) $p->code);
        $byId = $products->keyBy('id');
        $created = [];

        foreach ($rows as $row) {
            $code = (string) $row->citybox_product_id;

            if ($row->product_id !== null) {
                $product = $byId->get($row->product_id);
                if ($product && (string) $product->code === $code) {
                    $this->refreshAutoProduct($product, $row);
                }

                continue; // human mapping (or dangling id) — never re-pointed here
            }

            $product = $byCode->get($code);
            if (! $product) {
                $product = Product::create([
                    'code' => $code,
                    'name' => $row->name,
                    'operator_id' => $operator->id,
                    'is_inventory' => true,
                    'is_active' => true,
                    'is_available' => true,
                    'measurement_count' => 1,
                    'warehouse_qty_source' => \App\Enums\WarehouseQtySource::Ledger->value,
                    'desc' => 'CityBox SKU '.$code.' — created by the catalog sync',
                ]);
                $byCode->put($code, $product);
                $created[] = $row->citybox_product_id;
            } elseif ($product->warehouseQtySource() === \App\Enums\WarehouseQtySource::Cms) {
                // A chiller sells it ⇒ its warehouse qty lives on the mark1 ledger (§8.1).
                $product->forceFill(['warehouse_qty_source' => \App\Enums\WarehouseQtySource::Ledger->value])->save();
            }
            $this->refreshAutoProduct($product, $row);

            $row->forceFill(['product_id' => $product->id, 'mapped_at' => now(), 'mapped_by' => null])->save();
        }

        if ($created) {
            Log::info('Citybox: mark1 products created for new SKUs', ['citybox_product_ids' => $created]);
        }

        return $created;
    }

    /** Name + thumbnail follow CityBox for a product we own (code = their id). */
    private function refreshAutoProduct(Product $product, CityboxProduct $row): void
    {
        if ($row->name !== '' && $product->name !== $row->name) {
            $product->forceFill(['name' => $row->name])->save();
        }
        if (! $row->img_url) {
            return;
        }
        $thumb = $product->thumbnail;
        $theirHost = parse_url($row->img_url, PHP_URL_HOST);
        $ownedByUs = $thumb && $thumb->full_url && parse_url($thumb->full_url, PHP_URL_HOST) === $theirHost;
        if (! $thumb) {
            $product->thumbnail()->create(['type' => 1, 'full_url' => $row->img_url, 'local_url' => $row->img_url]);
        } elseif ($ownedByUs && $thumb->full_url !== $row->img_url) {
            $thumb->update(['full_url' => $row->img_url, 'local_url' => $row->img_url]);
        }
    }

    /**
     * @param  Collection<int,ChillerCatalogItem>  $items
     * @return array{added:int,updated:int,delisted:int,unchanged:int,details_json:array}
     */
    private function applyCatalog(Collection $items): array
    {
        $added = $updated = $unchanged = $delisted = [];

        DB::transaction(function () use ($items, &$added, &$updated, &$unchanged, &$delisted) {
            $seenIds = $items->map(fn (ChillerCatalogItem $i) => $i->cityboxProductId)->all();
            $existing = CityboxProduct::whereIn('citybox_product_id', $seenIds)->get()->keyBy('citybox_product_id');

            foreach ($items as $item) {
                $attrs = [
                    'name' => $item->name, 'sku_code' => $item->skuCode,
                    'img_url' => $item->imgUrl, 'vision_imgs' => $item->visionImgs,
                    'is_delisted' => false, 'last_seen_at' => now(), 'last_seen_source' => CityboxProduct::SOURCE_CATALOG,
                ];
                $row = $existing->get($item->cityboxProductId);
                if (! $row) {
                    CityboxProduct::create($attrs + ['citybox_product_id' => $item->cityboxProductId, 'first_seen_at' => now()]);
                    $added[] = $item->cityboxProductId;

                    continue;
                }
                $before = $row->only(['name', 'sku_code', 'img_url', 'vision_imgs', 'is_delisted']);
                $row->fill($attrs)->save();
                $after = $row->only(['name', 'sku_code', 'img_url', 'vision_imgs', 'is_delisted']);
                if ($before == $after) {
                    $unchanged[] = $item->cityboxProductId;
                } else {
                    $updated[] = $item->cityboxProductId;
                }
            }

            // Full run ⇒ anything not seen is delisted (soft). Reappearing flips back above.
            $delisted = CityboxProduct::where('is_delisted', false)->whereNotIn('citybox_product_id', $seenIds)
                ->pluck('citybox_product_id')->all();
            if ($delisted) {
                CityboxProduct::whereIn('citybox_product_id', $delisted)->update(['is_delisted' => true]);
            }
        });

        return [
            'added' => count($added), 'updated' => count($updated), 'delisted' => count($delisted), 'unchanged' => count($unchanged),
            'details_json' => ['added' => $added, 'updated' => $updated, 'delisted' => $delisted],
        ];
    }
}
