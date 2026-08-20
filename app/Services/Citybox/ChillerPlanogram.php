<?php

namespace App\Services\Citybox;

use App\Contracts\Citybox\ChillerGateway;
use App\Models\CityboxProduct;
use App\Models\ProductMapping;
use App\Models\ProductMappingItem;
use App\Models\Vend;
use App\Services\Citybox\DTO\ChillerStockLine;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * The chiller planogram in mark1 = a READ-ONLY MIRROR of CityBox's
 * Pre-Stock Setup (design §4b.3, decided 2026-08-19: their portal is the
 * single source of truth). One ProductMapping per chiller vend
 * (machine_type = smart_chiller), items = one per (layer, position):
 *
 *   channel_code = <layer><position>, position = 1..n by CityBox product id
 *   within the layer (deterministic → re-sync is idempotent)
 *   product_id   = via citybox_products link (NULL until a human maps)
 *   server_amount= their list price
 *   basket_layout_json = [{layer:1, positions:n}, …] (freezer precedent)
 *
 * capacity (= their par) is NOT on the mapping — it goes onto vend_channels
 * through the ChannelFrame (§3). Nothing here is ever edited in mark1.
 */
class ChillerPlanogram
{
    public function __construct(private ChillerGateway $gateway, private CatalogSyncService $catalog) {}

    /**
     * Pull shipping_product for the vend and overwrite its mirror mapping.
     * Returns the code map the adapter needs: citybox_product_id => [code, par, layer].
     *
     * @return array<int,array{code:int,par:int,layer:int}>
     */
    public function sync(Vend $vend): array
    {
        $lines = $this->gateway->restockConfig((string) $vend->citybox_equipment_id);

        return $this->apply($vend, $lines);
    }

    /** @param Collection<int,ChillerStockLine> $parLines */
    public function apply(Vend $vend, Collection $parLines): array
    {
        // SKU FIRST, stock second (Brian, 2026-08-20): their portal can add a
        // product to the par config before it ever appears in device stock.
        // Register every par-line SKU (citybox_products upsert + mark1 product
        // create/link) BEFORE reading the links, so the mirror mapping carries
        // an item for a brand-new SKU in this same pass instead of skipping it
        // until the next catalog run.
        $this->catalog->noteSeenOnDevice($parLines);

        $codes = self::assignCodes($parLines);
        $links = CityboxProduct::whereIn('citybox_product_id', array_keys($codes))->pluck('product_id', 'citybox_product_id');

        DB::transaction(function () use ($vend, $parLines, $codes, $links) {
            $mapping = $this->mappingFor($vend);

            $wanted = [];
            foreach ($parLines as $line) {
                $code = $codes[$line->cityboxProductId]['code'];
                $productId = $links->get($line->cityboxProductId);
                // product_mapping_items.product_id is NOT NULL (prod schema): an
                // UNMAPPED SKU cannot have a mapping item yet. It still gets a
                // channel code (above) and therefore a vend_channels row from the
                // frame — with product_id NULL, which vend_channels allows — so
                // stock/pick math work; cost/GP resolve once a human maps it and
                // the next planogram sync adds the item.
                if ($productId === null) {
                    continue;
                }
                $wanted[$code] = true;
                ProductMappingItem::updateOrCreate(
                    ['product_mapping_id' => $mapping->id, 'channel_code' => (string) $code],
                    [
                        'product_id' => $productId,
                        // ProductMappingItem::serverAmount mutator multiplies by 100 — pass dollars.
                        'server_amount' => ($line->effectivePriceCents() ?? 0) / 100,
                        'sequence' => $code,
                    ],
                );
            }
            // Their portal is truth: anything not in this response leaves the mirror.
            $keep = array_map('strval', array_keys($wanted));
            $mapping->productMappingItems()->when($keep !== [], fn ($q) => $q->whereNotIn('channel_code', $keep), fn ($q) => $q)->delete();

            $layout = [];
            foreach (range(1, \App\Enums\Citybox\DeviceType::Visual2->layerCount()) as $layer) {
                $layout[] = ['layer' => $layer, 'positions' => count(array_filter($codes, fn ($c) => $c['layer'] === $layer))];
            }
            $mapping->forceFill(['basket_layout_json' => $layout])->save();

            if ($vend->product_mapping_id !== $mapping->id) {
                $vend->forceFill(['product_mapping_id' => $mapping->id])->save();
            }
        });

        return $codes;
    }

    /**
     * Deterministic code assignment: within each layer, sort by CityBox id,
     * position 1..n → code = layer*10 + position. Same planogram, same codes.
     *
     * @param  Collection<int,ChillerStockLine>  $lines
     * @return array<int,array{code:int,par:int,layer:int}>
     */
    public static function assignCodes(Collection $lines): array
    {
        $out = [];
        $byLayer = $lines->filter(fn (ChillerStockLine $l) => $l->layer !== null && $l->layer >= 1 && $l->layer <= 6)
            ->groupBy(fn (ChillerStockLine $l) => $l->layer);
        foreach ($byLayer as $layer => $group) {
            $pos = 0;
            foreach ($group->sortBy(fn (ChillerStockLine $l) => $l->cityboxProductId)->values() as $line) {
                $pos++;
                if ($pos > 9) {
                    Log::warning('Citybox planogram: more than 9 products on one layer — 10th+ dropped from channel codes', ['layer' => $layer]);
                    break;
                }
                $out[$line->cityboxProductId] = ['code' => (int) $layer * 10 + $pos, 'par' => $line->quantity, 'layer' => (int) $layer];
            }
        }

        return $out;
    }

    /** The vend's mirror mapping, created on first sync. */
    private function mappingFor(Vend $vend): ProductMapping
    {
        if ($vend->product_mapping_id) {
            $existing = ProductMapping::withoutGlobalScopes()->find($vend->product_mapping_id);
            if ($existing && $existing->machine_type === Vend::MACHINE_TYPE_SMART_CHILLER) {
                return $existing;
            }
        }

        return ProductMapping::create([
            'name' => 'CityBox '.$vend->citybox_equipment_id.' (mirror)',
            'machine_type' => Vend::MACHINE_TYPE_SMART_CHILLER,
            'is_smart' => false,
            'is_active' => true,
            'operator_id' => $vend->operator_id,
            'remarks' => 'Read-only mirror of CityBox Pre-Stock Setup — edit in the CityBox portal, then Re-sync.',
        ]);
    }
}
