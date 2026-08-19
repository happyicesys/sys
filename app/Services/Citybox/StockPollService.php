<?php

namespace App\Services\Citybox;

use App\Contracts\Citybox\ChillerGateway;
use App\Models\Vend;
use App\Services\Citybox\DTO\ChillerStockLine;
use Illuminate\Support\Collection;

/**
 * STOCK half of the poll: device_product per linked vend → per-product
 * snapshot on the vend. Step 2 of the build adds the poll/movement tables and
 * the SyncVendChannels dispatch here; for now it writes the same
 * `citybox_status_json.stock` shape phase 2 wrote (keys pinned to 'p<id>').
 *
 * A failing stock call keeps the previous snapshot rather than pretending
 * the chiller emptied — and reports the error so the job can log-on-change.
 */
class StockPollService
{
    public function __construct(
        private ChillerGateway $gateway,
        private CatalogSyncService $catalog,
    ) {}

    /**
     * @param  Collection<string,Vend>  $vends  keyed by equipment id (from DeviceSyncService)
     * @return array<string,string> equipment_id => error message, for the ones that failed
     */
    public function pollAll(Collection $vends, Collection $devicesSeen): array
    {
        $errors = [];
        foreach ($devicesSeen->keys() as $equipmentId) {
            $vend = $vends->get($equipmentId);
            if (! $vend) {
                continue;
            }
            try {
                $this->pollOne($vend);
            } catch (\Throwable $e) {
                $errors[$equipmentId] = $e->getMessage();
            }
        }

        return $errors;
    }

    /** Fetch + store one vend's live stock. Throws on API failure (caller decides). */
    public function pollOne(Vend $vend): Collection
    {
        $lines = $this->gateway->deviceStock((string) $vend->citybox_equipment_id);
        $this->applyStock($vend, $lines);
        // Opportunistic catalog upsert: a product appears on a chiller long
        // before anyone opens the mapping screen (§5.2). Never delists.
        $this->catalog->noteSeenOnDevice($lines);

        return $lines;
    }

    /** @param Collection<int,ChillerStockLine> $lines */
    public function applyStock(Vend $vend, Collection $lines): void
    {
        $stock = $lines->keyBy(fn (ChillerStockLine $l) => 'p'.$l->cityboxProductId)
            ->map(fn (ChillerStockLine $l) => [
                'product_id' => (string) $l->cityboxProductId,
                'name' => $l->name,
                'quantity' => $l->quantity,
                'layer' => $l->layer,
                'price' => $l->priceCents,
                'active_price' => $l->activePriceCents,
                'thumbnail' => $l->thumbnailUrl,
            ])->all();

        $vend->forceFill([
            'citybox_synced_at' => now(),
            'citybox_status_json' => array_merge($vend->citybox_status_json ?? [], ['stock' => $stock]),
        ])->save();
    }
}
