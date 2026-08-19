<?php

namespace App\Services\Citybox;

use App\Contracts\Citybox\ChillerGateway;
use App\Models\CityboxProduct;
use App\Models\CityboxProductSyncLog;
use App\Services\Citybox\DTO\ChillerCatalogItem;
use App\Services\Citybox\DTO\ChillerStockLine;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

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
 * upsert on that key. The human link (product_id) is NEVER touched here.
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
            CityboxProductSyncLog::create([
                'source' => CityboxProductSyncLog::SOURCE_DEVICE_POLL, 'started_at' => now(), 'finished_at' => now(),
                'fetched' => $lines->count(), 'added' => count($added), 'details_json' => ['added' => $added],
            ]);
        }

        return $added;
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
