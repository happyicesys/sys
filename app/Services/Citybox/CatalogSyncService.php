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
 *  - noteSeenOnDevice()  from the 3-min stock poll AND the planogram mirror
 *                        (par lines — SKU first, stock second, Brian
 *                        2026-08-20): upsert any product a
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
    public function __construct(private ChillerGateway $gateway, private ThumbnailImporter $thumbnails) {}

    public function syncCatalog(string $source = CityboxProductSyncLog::SOURCE_CATALOG_SCHEDULED, ?int $userId = null): CityboxProductSyncLog
    {
        $log = CityboxProductSyncLog::create([
            'source' => $source, 'triggered_by' => $userId, 'started_at' => now(),
        ]);

        try {
            $items = $this->gateway->catalog();
            $result = $this->applyCatalog($items);
            $result['details_json']['products_created'] = $this->ensureMark1Products();
            $this->warnOnUnknownStatuses($items);
            $result['details_json'] += $this->applyStatusToProducts($items);
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
     * Per-minute status refresh (Brian, 2026-09-05). CityBox's `status` is the
     * ONLY signal that a SKU has been retired — their catalog keeps returning
     * disabled rows forever, so the absence-based delisting in applyCatalog()
     * can never fire for one — and ops want it acted on promptly rather than at
     * the top of the hour.
     *
     * Deliberately light next to syncCatalog(): one `product_list` call, a write
     * only for rows whose status actually moved, and NO product creation,
     * thumbnail download or delisting. Those stay hourly.
     *
     * @return array{checked:int,changed:int,deactivated:int,reactivated:int}
     */
    public function syncStatuses(): array
    {
        $items = $this->gateway->catalog();
        if ($items->isEmpty()) {
            return ['checked' => 0, 'changed' => 0, 'deactivated' => 0, 'reactivated' => 0];
        }

        $rows = CityboxProduct::whereIn(
            'citybox_product_id',
            $items->map(fn (ChillerCatalogItem $i) => $i->cityboxProductId)->all()
        )->get()->keyBy('citybox_product_id');
        $changed = [];

        foreach ($items as $item) {
            $row = $rows->get($item->cityboxProductId);
            // Unknown SKU → the hourly run adds it. Unchanged → nothing to write:
            // at this cadence a blind save would be 50-odd UPDATEs every minute.
            if ($item->status === null || ! $row || $row->citybox_status === $item->status) {
                continue;
            }
            $row->forceFill(['citybox_status' => $item->status, 'citybox_status_at' => now()])->save();
            $changed[] = $item->cityboxProductId;
        }

        $flips = $this->applyStatusToProducts($items);

        if ($changed || $flips['deactivated'] || $flips['reactivated']) {
            Log::info('Citybox: SKU status changed', ['citybox_product_ids' => $changed] + $flips);
        }

        return ['checked' => $items->count(), 'changed' => count($changed)] + $flips;
    }

    /**
     * Mirror their enabled/disabled flag onto the mark1 product — but ONLY for a
     * product WE created and own, whose code IS their id. A product a human
     * mapped by hand is left alone, the same rule refreshAutoProduct() already
     * follows for name and thumbnail.
     *
     * A disabled SKU is deactivated even while it is still bound to a live
     * channel (Brian, 2026-09-05): six were, at the time this landed, including
     * Cocacola on three machines. The planogram greys such a channel out instead
     * of dropping it, so the cabinet still reads correctly and the binding — and
     * with it the ops history — survives.
     *
     * @param  Collection<int,ChillerCatalogItem>  $items
     * @return array{deactivated:int,reactivated:int}
     */
    private function applyStatusToProducts(Collection $items): array
    {
        $byId = $items->keyBy(fn (ChillerCatalogItem $i) => $i->cityboxProductId);
        $rows = CityboxProduct::whereNotNull('product_id')
            ->whereIn('citybox_product_id', $byId->keys()->all())->get();
        $products = Product::withoutGlobalScopes()
            ->whereIn('id', $rows->pluck('product_id')->filter()->all())->get()->keyBy('id');
        $deactivated = $reactivated = 0;

        foreach ($rows as $row) {
            $item = $byId->get($row->citybox_product_id);
            $product = $products->get($row->product_id);
            // No status in the payload ⇒ claim NOTHING. Their field is
            // undocumented and was absent before 2026-09-05; if it ever stops
            // being sent, silence must not read as "every SKU is disabled".
            if (! $item || $item->status === null || ! $product) {
                continue;
            }
            if ((string) $product->code !== (string) $row->citybox_product_id) {
                continue; // human mapping — not ours to flip
            }
            $wanted = $item->isEnabled();
            if ((bool) $product->is_active === $wanted) {
                continue;
            }
            $product->forceFill(['is_active' => $wanted])->save();
            $wanted ? $reactivated++ : $deactivated++;
        }

        return ['deactivated' => $deactivated, 'reactivated' => $reactivated];
    }

    /**
     * A status value we have no meaning for (99 was seen once, on 90348).
     * Warned from the HOURLY run only — the per-minute path would repeat it
     * 1440 times a day. Treated as not-enabled meanwhile; confirm with CityBox.
     *
     * @param  Collection<int,ChillerCatalogItem>  $items
     */
    private function warnOnUnknownStatuses(Collection $items): void
    {
        $unknown = $items->filter(fn (ChillerCatalogItem $i) => $i->hasUnknownStatus())
            ->mapWithKeys(fn (ChillerCatalogItem $i) => [$i->cityboxProductId => $i->status])->all();
        if ($unknown) {
            Log::warning('Citybox: unrecognised product status — treated as NOT enabled; confirm the meaning with Citybox', [
                'status_by_citybox_product_id' => $unknown,
            ]);
        }
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
                // firstOrCreate, not create: ONE product per CityBox id, even if
                // two runs overlap. A unique index on products.code cannot
                // enforce that — 25 duplicate codes live legitimately under other
                // operators today — so the guard is the lookup itself, matching
                // on their id under the CityBox operator. $byCode has already
                // ruled out a product with this code under ANY operator, so this
                // only ever fires when nothing exists yet.
                $product = Product::withoutGlobalScopes()->firstOrCreate(
                    ['code' => $code, 'operator_id' => $operator->id],
                    [
                        'name' => $row->name,
                        'is_inventory' => true,
                        // Born disabled if CityBox already says so; NULL status
                        // (they sent no flag) means we claim nothing and default active.
                        'is_active' => $row->citybox_status === null || $row->isEnabledInCitybox(),
                        'is_available' => true,
                        'measurement_count' => 1,
                        'warehouse_qty_source' => \App\Enums\WarehouseQtySource::Ledger->value,
                        'desc' => 'CityBox SKU '.$code.' — created by the catalog sync',
                    ]
                );
                $byCode->put($code, $product);
                if ($product->wasRecentlyCreated) {
                    $created[] = $row->citybox_product_id;
                } else {
                    Log::info('Citybox: product for this SKU already existed — reused, not duplicated', [
                        'citybox_product_id' => $row->citybox_product_id, 'product_id' => $product->id,
                    ]);
                }
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

    /**
     * Name + thumbnail follow CityBox for a product we own (code = their id).
     * The image is COPIED onto our storage, shrunk to ≤ 490 KB (ThumbnailImporter);
     * the attachment's `desc` remembers which CityBox URL it came from so a
     * re-run only re-downloads when CityBox changes the picture. If the download
     * fails we hot-link their URL for now (desc marks it) and retry next sync.
     */
    private function refreshAutoProduct(Product $product, CityboxProduct $row): void
    {
        if ($row->name !== '' && $product->name !== $row->name) {
            $product->forceFill(['name' => $row->name])->save();
        }
        if (! $row->img_url) {
            return;
        }
        $thumb = $product->thumbnail;
        $origin = 'citybox:'.$row->img_url;
        $stale = 'citybox-remote:'.$row->img_url;
        if ($thumb && $thumb->desc === $origin) {
            return; // already our shrunk copy of this exact picture
        }
        $ours = $thumb === null || str_starts_with((string) $thumb->desc, 'citybox')
            || parse_url((string) $thumb->full_url, PHP_URL_HOST) === parse_url($row->img_url, PHP_URL_HOST);
        if (! $ours) {
            return; // a human uploaded their own picture — keep it
        }

        $stored = $this->thumbnails->import($row->img_url, 'cb-'.$row->citybox_product_id);
        $attrs = $stored
            ? ['full_url' => $stored['full_url'], 'local_url' => $stored['local_url'], 'desc' => $origin]
            : ['full_url' => $row->img_url, 'local_url' => $row->img_url, 'desc' => $stale];
        if ($thumb) {
            $thumb->update($attrs);
        } else {
            $product->thumbnail()->create(['type' => 1] + $attrs);
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
                    'citybox_status' => $item->status, 'citybox_status_at' => now(),
                ];
                $row = $existing->get($item->cityboxProductId);
                if (! $row) {
                    CityboxProduct::create($attrs + ['citybox_product_id' => $item->cityboxProductId, 'first_seen_at' => now()]);
                    $added[] = $item->cityboxProductId;

                    continue;
                }
                $before = $row->only(['name', 'sku_code', 'img_url', 'vision_imgs', 'is_delisted', 'citybox_status']);
                $row->fill($attrs)->save();
                $after = $row->only(['name', 'sku_code', 'img_url', 'vision_imgs', 'is_delisted', 'citybox_status']);
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
