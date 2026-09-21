<?php

namespace App\Services\Citybox;

use App\Contracts\Citybox\ChillerGateway;
use App\Enums\Citybox\DeviceState;
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
        private ChillerPlanogram $planogram,
        private ChannelFrameAdapter $adapter,
        private ChillerChannelMap $channelMap,
    ) {}

    // Planogram code map is re-derived hourly / on Pull / on Open Door, not
    // every 3 min: it only changes when someone edits their portal.
    private const PLANOGRAM_TTL = 3600;

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
        $online = $device?->online ?? (bool) $vend->is_online;

        // Live session state (get_device_status_new): FREE / OPENING / BUSY / …
        // Their API answers NOT_FOUND for an offline device, so that call is
        // skipped when box_list already says offline. Best-effort: a failure
        // here never fails the stock poll.
        $state = $this->deviceState($vend, $online);

        try {
            $lines = $this->gateway->deviceStock((string) $vend->citybox_equipment_id);
        } catch (\Throwable $e) {
            $poll = CityboxInventoryPoll::create([
                'vend_id' => $vend->id,
                'citybox_equipment_id' => $vend->citybox_equipment_id,
                'polled_at' => $startedAt,
                'online' => $online,
                'device_status' => $device?->opsStatus?->value,
                'error' => $e->getMessage(),
                'duration_ms' => (int) ((hrtime(true) - $t0) / 1e6),
            ]);
            $this->mirrorHealthOntoVend($vend, $state, $poll);

            return $poll;
        }

        $durationMs = (int) ((hrtime(true) - $t0) / 1e6);
        $snapshot = $this->snapshot($lines);

        return DB::transaction(function () use ($vend, $device, $startedAt, $lines, $snapshot, $durationMs, $state) {
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
            $this->mirrorHealthOntoVend($vend, $state, $poll);
            $this->catalog->noteSeenOnDevice($lines);
            $this->pushChannels($vend, $lines);

            return $poll;
        });
    }

    /** @return DeviceState|null null = not asked (offline short-circuits to NotFound; errors → null) */
    private function deviceState(Vend $vend, bool $online): ?DeviceState
    {
        if (! $online) {
            return DeviceState::NotFound;
        }
        try {
            return $this->gateway->deviceState((string) $vend->citybox_equipment_id);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::info('Citybox device state read failed', ['vend_id' => $vend->id, 'error' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * Machine-parameter mirror for the rows/cards: the live session state and
     * the last poll's health, on the vend so index pages need no extra query.
     */
    private function mirrorHealthOntoVend(Vend $vend, ?DeviceState $state, CityboxInventoryPoll $poll): void
    {
        $json = $vend->citybox_status_json ?? [];
        if ($state !== null) {
            $json['device_state'] = $state->value;
            $json['device_state_at'] = now()->toDateTimeString();
        }
        $json['poll'] = [
            'at' => $poll->polled_at->toDateTimeString(),
            'ok' => $poll->error === null,
            'error' => $poll->error,
            'duration_ms' => $poll->duration_ms,
            'products_seen' => $poll->products_seen,
            'total_qty' => $poll->total_qty,
        ];
        $vend->forceFill(['citybox_status_json' => $json])->save();
    }

    /**
     * Turn the live stock into a CHANNEL frame and hand it to the SAME job the
     * vending fleet uses (design §1/§2). Skipped while a stock submit for this
     * vend is pending (§6.1) — step 6 sets that flag; until then it is never
     * set, so this always runs.
     */
    public function pushChannels(Vend $vend, Collection $lines, ?string $label = null, bool $force = false): void
    {
        // $force: the visit's own B/A frames must always land — the pending
        // guard exists to stop the SCHEDULED poll writing pre-restock numbers.
        if (! $force && $this->submitPendingFor($vend)) {
            \Illuminate\Support\Facades\Log::info('Citybox: channel push skipped — stock submit pending', ['vend_id' => $vend->id]);

            return;
        }
        // The channels are OURS (Brian, 2026-09-21): the vend's mapping decides which
        // codes exist and what sits on them, and the live call only fills in qty and
        // price. A SKU they report that we do not carry gets no channel — the overview
        // lists it as off-planogram instead.
        $slots = $this->channelMap->forVend($vend);
        if ($slots === []) {
            return; // no mapping bound yet — nothing to map onto
        }
        $frame = $this->adapter->toFrame($lines, $slots, $label);
        if ($frame->isEmpty()) {
            return;
        }
        \App\Jobs\Vend\SyncVendChannels::dispatch($frame->toArray(), $vend)->onQueue('high');
    }

    /**
     * Re-read CityBox's own Pre-Stock Setup (Pull / Open Door) and cache it. It no
     * longer decides our channels — it is the recognition check: a SKU our mapping
     * carries that their machine does not know cannot be recognised by their AI.
     *
     * @return array<int,array{par:int,layer:int|null,price:int,active:int,name:string}>
     */
    public function refreshTheirConfig(Vend $vend): array
    {
        $config = $this->planogram->theirConfig($vend);
        \Illuminate\Support\Facades\Cache::put($this->planogramKey($vend), $config, self::PLANOGRAM_TTL);

        return $config;
    }

    /**
     * The last read of their config, WITHOUT calling CityBox. Empty means "not
     * read inside the TTL" — callers must treat that as unknown, never as "their
     * machine carries nothing".
     *
     * @return array<int,array{par:int,layer:int|null,price:int,active:int,name:string}>
     */
    public function cachedTheirConfig(Vend $vend): array
    {
        return \Illuminate\Support\Facades\Cache::get($this->planogramKey($vend), []);
    }

    /** Their config, read through the cache. */
    public function theirConfig(Vend $vend): array
    {
        return \Illuminate\Support\Facades\Cache::remember($this->planogramKey($vend), self::PLANOGRAM_TTL, function () use ($vend) {
            try {
                return $this->planogram->theirConfig($vend);
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('Citybox pre-stock config read failed', ['vend_id' => $vend->id, 'error' => $e->getMessage()]);

                return [];
            }
        });
    }

    /**
     * SKUs our mapping puts on this machine that CityBox's Pre-Stock Setup does
     * not carry — their AI cannot recognise those, so ops must add them in OPS
     * Pro before the driver loads them (Brian, 2026-09-21).
     *
     * @return array<int,array{code:int,product_id:int,citybox_product_id:int}>
     */
    public function unrecognisableSlots(Vend $vend, bool $fresh = false): array
    {
        $config = $fresh ? $this->refreshTheirConfig($vend) : $this->theirConfig($vend);
        if ($config === []) {
            return []; // unknown, not "everything is missing"
        }

        $missing = [];
        foreach ($this->channelMap->forVend($vend) as $slot) {
            if (! isset($config[$slot->cityboxProductId])) {
                $missing[] = ['code' => $slot->code, 'product_id' => $slot->productId, 'citybox_product_id' => $slot->cityboxProductId];
            }
        }

        return $missing;
    }

    private function planogramKey(Vend $vend): string
    {
        return 'citybox:planogram:'.$vend->id;
    }

    /**
     * True while an ops item on this vend has a stock submit pending/failed
     * (§6.1): the scheduled poll would otherwise read their PRE-restock qty and
     * write a false low stock + a spurious movement. Bounded to 2 h so a
     * permanently failed submit can't freeze channels forever.
     */
    protected function submitPendingFor(Vend $vend): bool
    {
        return \App\Models\OpsJobItem::where('vend_id', $vend->id)
            ->whereIn('citybox_submit_status', ['pending', 'failed', 'reverting'])
            ->where(fn ($q) => $q->where('completed_at', '>=', now()->subHours(2))
                ->orWhere('undo_completed_at', '>=', now()->subHours(2)))
            ->exists();
    }

    /** Mirror live lines onto the vend WITHOUT writing a poll row (B/A visit pulls). */
    public function applyStockOnly(Vend $vend, Collection $lines): void
    {
        $this->mirrorOntoVend($vend, $this->snapshot($lines));
        $this->catalog->noteSeenOnDevice($lines);
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
