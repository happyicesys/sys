<?php

namespace App\Services\Stock;

use App\Contracts\Citybox\ChillerGateway;
use App\Jobs\Vend\SaveVendChannelsJson;
use App\Models\CityboxDoorOpenLog;
use App\Models\User;
use App\Models\Vend;
use App\Models\VendChannel;
use App\Models\VendChannelQtyAdjustment;
use App\Services\Citybox\ChillerChannelMap;
use App\Services\Citybox\DTO\ChillerStockLine;
use App\Services\Citybox\DTO\RestockSession;
use App\Services\Citybox\DTO\StockCount;
use App\Services\Citybox\StockPollService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Overwrite one SKU's on-hand qty by hand (Setting/Edit "Stock Qty", 2026-09-30): ops and
 * technicians correct the count when what is inside does not match the system, instead of
 * waiting for the next ops-job Stock In. Every applied change is logged with who, when and
 * before → after (`vend_channel_qty_adjustments`).
 *
 * Each machine kind owns its number differently, so each is written where it lives:
 *
 *  - **Smart Freezer** — `vend_channels.qty` IS the stock (FreezerStockLedger), so the row is
 *    set directly. Stock In / sales keep moving it relative to the new figure.
 *  - **Smart Chiller** — CityBox owns the number and the minute poll re-reads it, so a local
 *    write alone would be gone within a minute. The new figure is sent through
 *    `device_stock_submit` on the machine's latest door-open session (a stale one is accepted,
 *    see DepartedSkuSync), then read back and mirrored. The payload is the FULL live list with
 *    one line changed, never that line alone: what CityBox does to products left out of a
 *    stocktake is unproven (CityboxSyncTarget), and a full list with unchanged numbers is a
 *    stocktake we know is safe.
 *  - **Vending machine** — refused. The VMC reports its own qty on every CHANNEL frame and
 *    mark1 has no frame that sets it, so a write here would be overwritten silently.
 *
 * `$expectedQty` is the figure the person was looking at. If the machine moved since (a sale,
 * a Stock In), the overwrite is refused rather than silently undoing that movement.
 */
class ChannelQtyAdjuster
{
    public const MAX_QTY = 999;

    public function __construct(
        private ChillerGateway $gateway,
        private ChillerChannelMap $channelMap,
        private StockPollService $stock,
    ) {}

    /** Why this machine's qty cannot be set from mark1; null when it can. */
    public function refusal(Vend $vend): ?string
    {
        if (! $vend->isSkuStocked()) {
            return 'A vending machine\'s qty is what its VMC reports, and its next report would overwrite a change made here. Correct it on the machine.';
        }
        if ($vend->isSmartChiller() && ! $vend->citybox_equipment_id) {
            return 'This chiller is not linked to a CityBox device.';
        }

        return null;
    }

    public function adjust(Vend $vend, int $channelId, int $qty, int $expectedQty, User $by): VendChannelQtyAdjustment
    {
        if (($reason = $this->refusal($vend)) !== null) {
            throw new QtyAdjustRefused($reason);
        }
        if ($qty < 0 || $qty > self::MAX_QTY) {
            throw new QtyAdjustRefused('Qty must be between 0 and '.self::MAX_QTY.'.');
        }

        $channel = VendChannel::where('vend_id', $vend->id)->whereKey($channelId)
            ->where('is_active', true)->whereNotNull('product_id')->first();
        if ($channel === null) {
            throw new QtyAdjustRefused('This product is no longer on the machine. Reload the page.');
        }

        return $vend->isSmartChiller()
            ? $this->adjustChiller($vend, $channel, $qty, $expectedQty, $by)
            : $this->adjustLedger($vend, $channel, $qty, $expectedQty, $by);
    }

    private function adjustLedger(Vend $vend, VendChannel $channel, int $qty, int $expectedQty, User $by): VendChannelQtyAdjustment
    {
        $log = DB::transaction(function () use ($vend, $channel, $qty, $expectedQty, $by) {
            $row = VendChannel::whereKey($channel->id)->lockForUpdate()->first();
            $before = (int) $row->qty;
            $this->assertUnmoved($before, $expectedQty, 'The system');
            $this->assertChanged($before, $qty);

            $row->update(['qty' => $qty]);

            return $this->record($vend, $row, $before, $qty, null, null, $by);
        });

        SaveVendChannelsJson::dispatch($vend->id)->onQueue('default');

        return $log;
    }

    private function adjustChiller(Vend $vend, VendChannel $channel, int $qty, int $expectedQty, User $by): VendChannelQtyAdjustment
    {
        // Each push carries the whole cabinet; two at once would each put back the other's line.
        $lock = Cache::lock('citybox:stock-adjust:'.$vend->id, 60);
        if (! $lock->get()) {
            throw new QtyAdjustRefused('Another qty change for this chiller is being sent to CityBox. Try again in a moment.');
        }

        try {
            if ($this->stock->submitPendingFor($vend)) {
                throw new QtyAdjustRefused('A Stock In for this chiller is still being sent to CityBox. Adjust after it settles.');
            }

            $slot = $this->channelMap->forVend($vend)[(int) $channel->product_id] ?? null;
            if ($slot === null) {
                throw new QtyAdjustRefused('This product is not on the chiller\'s current mapping.');
            }
            $theirConfig = $this->stock->theirConfig($vend);
            if ($theirConfig !== [] && ! isset($theirConfig[$slot->cityboxProductId])) {
                throw new QtyAdjustRefused('CityBox\'s machine does not carry this product (Pre-Stock Setup in OPS Pro), so it cannot hold a qty for it. Add it there first.');
            }

            $msgId = CityboxDoorOpenLog::where('vend_id', $vend->id)
                ->where('result', CityboxDoorOpenLog::RESULT_OPENED)
                ->whereNotNull('msg_id')
                ->latest('requested_at')
                ->value('msg_id');
            if ($msgId === null) {
                throw new QtyAdjustRefused('CityBox only accepts a stock update after a door-open from mark1, and this chiller has none yet. Press Open Door once, then try again.');
            }

            $equipmentId = (string) $vend->citybox_equipment_id;
            try {
                $live = $this->liveByCityboxId($equipmentId);
            } catch (\Throwable $e) {
                throw new QtyAdjustRefused('Could not read CityBox stock: '.$e->getMessage());
            }

            // Their live list omits a SKU they hold none of.
            $before = isset($live[$slot->cityboxProductId]) ? $live[$slot->cityboxProductId]->quantity : 0;
            $this->assertUnmoved($before, $expectedQty, 'CityBox');
            $this->assertChanged($before, $qty);

            $counts = [];
            foreach ($live as $cityboxId => $line) {
                if ($cityboxId > 0) {
                    $counts[$cityboxId] = max(0, $line->quantity);
                }
            }
            $counts[$slot->cityboxProductId] = $qty;

            try {
                $this->gateway->submitCount(new RestockSession($equipmentId, $msgId, '', now()->toImmutable()), StockCount::of($counts));
            } catch (\Throwable $e) {
                throw new QtyAdjustRefused('CityBox refused the update: '.$e->getMessage());
            }

            // Read their number back, so what we show is theirs and not our hope.
            $readBack = null;
            $lines = null;
            try {
                $lines = $this->gateway->deviceStock($equipmentId);
                $line = $lines->first(fn (ChillerStockLine $l) => $l->cityboxProductId === $slot->cityboxProductId);
                $readBack = $line ? $line->quantity : 0;
            } catch (\Throwable $e) {
                Log::warning('Citybox qty adjust: read-back failed', ['vend_id' => $vend->id, 'error' => $e->getMessage()]);
            }

            $channel->update(['qty' => $readBack ?? $qty]);
            $log = $this->record($vend, $channel, $before, $qty, $readBack, $msgId, $by);

            if ($lines !== null) {
                $this->stock->applyStockOnly($vend, $lines);
                $this->stock->pushChannels($vend, $lines, null, force: true);
            }

            Log::info('Citybox qty adjusted by hand', [
                'vend_id' => $vend->id, 'product_id' => $channel->product_id, 'citybox_product_id' => $slot->cityboxProductId,
                'before' => $before, 'after' => $qty, 'read_back' => $readBack, 'msg_id' => $msgId, 'user_id' => $by->id,
                'lines_pushed' => count($counts),
            ]);

            return $log;
        } finally {
            $lock->release();
        }
    }

    private function assertUnmoved(int $current, int $expected, string $whose): void
    {
        if ($current !== $expected) {
            throw new QtyAdjustRefused("{$whose} now shows {$current}, not {$expected} — a sale or a stock-in happened since the page loaded. Check the count and try again.");
        }
    }

    private function assertChanged(int $current, int $qty): void
    {
        if ($current === $qty) {
            throw new QtyAdjustRefused("Qty is already {$qty}.");
        }
    }

    /** @return array<int,ChillerStockLine> keyed by citybox product id */
    private function liveByCityboxId(string $equipmentId): array
    {
        return $this->gateway->deviceStock($equipmentId)
            ->keyBy(fn (ChillerStockLine $l) => $l->cityboxProductId)
            ->all();
    }

    private function record(Vend $vend, VendChannel $channel, int $before, int $after, ?int $supplierAfter, ?string $msgId, User $by): VendChannelQtyAdjustment
    {
        return VendChannelQtyAdjustment::create([
            'vend_id' => $vend->id,
            'vend_channel_id' => $channel->id,
            'product_id' => $channel->product_id,
            'channel_label' => $channel->label,
            'qty_before' => $before,
            'qty_after' => $after,
            'supplier_qty_after' => $supplierAfter,
            'supplier_msg_id' => $msgId,
            'source' => VendChannelQtyAdjustment::SOURCE_SETTING_EDIT,
            'user_id' => $by->id,
        ]);
    }
}
