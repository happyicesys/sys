<?php

namespace App\Services\Citybox;

use App\Contracts\Citybox\ChillerGateway;
use App\Models\CityboxInventoryPoll;
use App\Models\CityboxProduct;
use App\Models\CityboxStockMovement;
use App\Models\Vend;
use App\Services\Citybox\DTO\ChillerDevice;
use App\Services\Citybox\DTO\ChillerStockLine;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * STOCK half of the 3-min poll (design §5b). Per linked vend:
 *   1. read device_product (live qty)
 *   2. write ONE citybox_inventory_polls row (what we saw — or the error)
 *   3. diff against the previous successful poll → citybox_stock_movements
 *      rows, each classified sale / restock / correction / unknown
 *   4. mirror the snapshot onto vends.citybox_status_json.stock (debug view)
 *   5. tell CatalogSyncService which SKUs it saw (opportunistic upsert)
 *
 * A failed stock read still writes a poll row (with `error`, no snapshot) so
 * gaps are visible, keeps the vend's previous snapshot, and produces NO
 * movements — the next successful poll diffs against the last GOOD one.
 * Step 5 adds the SyncVendChannels dispatch here.
 */
class StockPollService
{
    public function __construct(
        private ChillerGateway $gateway,
        private CatalogSyncService $catalog,
        private MovementClassifier $classifier,
    ) {}

    /**
     * @param  Collection<string,Vend>  $vends  keyed by equipment id
     * @param  Collection<string,ChillerDevice>  $devicesSeen  from DeviceSyncService (status per device)
     * @return array<string,string> equipment_id => error message for failed stock reads
     */
    public function pollAll(Collection $vends, Collection $devicesSeen): array
    {
        $errors = [];
        foreach ($devicesSeen as $equipmentId => $device) {
            $vend = $vends->get($equipmentId);
            if (! $vend) {
                continue;
            }
            $poll = $this->pollOne($vend, $device);
            if ($poll->error) {
                $errors[$equipmentId] = $poll->error;
            }
        }

        return $errors;
    }

    /**
     * Poll one vend. Never throws on an API failure — records it on the poll
     * row and returns it, so the caller's loop keeps going for other vends.
     */
    public function pollOne(Vend $vend, ?ChillerDevice $device = null): CityboxInventoryPoll
    {
        $startedAt = now();
        $t0 = hrtime(true);

        try {
            $lines = $this->gateway->deviceStock((string) $vend->citybox_equipment_id);
        } catch (\Throwable $e) {
            return CityboxInventoryPoll::create([
                'vend_id' => $vend->id,
                'citybox_equipment_id' => $vend->citybox_equipment_id,
                'polled_at' => $startedAt,
                'online' => $device?->online ?? (bool) $vend->is_online,
                'device_status' => $device?->opsStatus?->value,
                'error' => $e->getMessage(),
                'duration_ms' => (int) ((hrtime(true) - $t0) / 1e6),
            ]);
        }

        $durationMs = (int) ((hrtime(true) - $t0) / 1e6);
        $snapshot = $this->snapshot($lines);

        return DB::transaction(function () use ($vend, $device, $startedAt, $lines, $snapshot, $durationMs) {
            $previous = CityboxInventoryPoll::previousFor($vend->id);

            $poll = CityboxInventoryPoll::create([
                'vend_id' => $vend->id,
                'citybox_equipment_id' => $vend->citybox_equipment_id,
                'polled_at' => $startedAt,
                'online' => $device?->online ?? (bool) $vend->is_online,
                'device_status' => $device?->opsStatus?->value,
                'products_seen' => $lines->count(),
                'total_qty' => $lines->sum(fn (ChillerStockLine $l) => $l->quantity),
                'snapshot_json' => $snapshot,
                'duration_ms' => $durationMs,
            ]);

            $movements = $previous ? $this->diff($vend, $previous, $poll, $lines) : 0;
            if ($movements) {
                $poll->update(['movements_count' => $movements]);
            }

            $this->mirrorOntoVend($vend, $snapshot);
            $this->catalog->noteSeenOnDevice($lines);

            return $poll;
        });
    }

    /** @param Collection<int,ChillerStockLine> $lines */
    private function snapshot(Collection $lines): array
    {
        return $lines->keyBy(fn (ChillerStockLine $l) => 'p'.$l->cityboxProductId)
            ->map(fn (ChillerStockLine $l) => [
                'product_id' => (string) $l->cityboxProductId,
                'name' => $l->name,
                'quantity' => $l->quantity,
                'layer' => $l->layer,
                'price' => $l->priceCents,
                'active_price' => $l->activePriceCents,
                'thumbnail' => $l->thumbnailUrl,
            ])->all();
    }

    /** Compare against the previous successful poll; write one movement per changed product. Returns count. */
    private function diff(Vend $vend, CityboxInventoryPoll $previous, CityboxInventoryPoll $current, Collection $lines): int
    {
        $before = $previous->snapshot_json ?? [];
        $ids = $lines->map(fn (ChillerStockLine $l) => $l->cityboxProductId)->all();
        $productLinks = CityboxProduct::whereIn('citybox_product_id', $ids)->pluck('product_id', 'citybox_product_id');

        $count = 0;
        foreach ($lines as $line) {
            $qtyBefore = $before['p'.$line->cityboxProductId]['quantity'] ?? null;
            if ($qtyBefore === null || (int) $qtyBefore === $line->quantity) {
                continue; // new-to-this-device or unchanged
            }
            $delta = $line->quantity - (int) $qtyBefore;
            $verdict = $this->classifier->classify($vend->id, $delta, $previous->polled_at, $current->polled_at);

            CityboxStockMovement::create([
                'vend_id' => $vend->id,
                'citybox_equipment_id' => $vend->citybox_equipment_id,
                'citybox_product_id' => $line->cityboxProductId,
                'product_id' => $productLinks->get($line->cityboxProductId),
                'poll_id' => $current->id,
                'prev_poll_id' => $previous->id,
                'qty_before' => (int) $qtyBefore,
                'qty_after' => $line->quantity,
                'delta' => $delta,
                'movement_type' => $verdict['type'],
                'occurred_between_start' => $previous->polled_at,
                'occurred_between_end' => $current->polled_at,
                'ops_job_item_id' => $verdict['ops_job_item_id'],
            ]);
            $count++;
        }

        return $count;
    }

    private function mirrorOntoVend(Vend $vend, array $snapshot): void
    {
        $vend->forceFill([
            'citybox_synced_at' => now(),
            'citybox_status_json' => array_merge($vend->citybox_status_json ?? [], ['stock' => $snapshot]),
        ])->save();
    }
}
