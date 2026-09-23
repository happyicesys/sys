<?php

namespace App\Services\Citybox;

use App\Contracts\Citybox\ChillerGateway;
use App\Models\CityboxDoorOpenLog;
use App\Models\CityboxProduct;
use App\Models\Vend;
use App\Models\VendChannel;
use App\Services\Citybox\DTO\ChillerStockLine;
use App\Services\Citybox\DTO\RestockSession;
use App\Services\Citybox\DTO\StockCount;
use Illuminate\Support\Facades\Log;

/**
 * Tell CityBox that a SKU which has LEFT this chiller's planogram is gone.
 *
 * WHY. Switching a chiller's ProductMapping used to change nothing on their
 * side: we retired our row and CityBox went on believing the cabinet still
 * held that SKU (Brian, 2026-09-23). Only an ops-job Stock In ever pushed a
 * zero, so a mapping switched from the back office left their count wrong
 * until a driver happened to visit.
 *
 * WHY IT CAN RUN FROM A DESK. `device_stock_submit` needs the `msg_id` of a
 * door-open on that device, but NOT a fresh one — the ops-job push already
 * reuses the machine's latest open from any source, and prod shows a submit
 * succeeding on a session 27 h old (item 32279, 2026-09-04). So the switch
 * pushes straight away using the last known session.
 *
 * WHAT IT WILL NOT DO. Only a SKU that left OUR planogram while still holding
 * stock is zeroed — never every SKU their machine reports that we do not
 * carry. Those are the off-planogram leftovers the overview greys in (C6005,
 * five real units, 2026-09-12); zeroing them would erase stock that is
 * physically inside.
 *
 * THE SIGNAL is our own retired row: inactive, still carrying a product and a
 * qty. SyncVendChannels retires by product and keeps the qty, so nothing extra
 * has to be recorded. A successful push zeroes that qty, which is also what
 * stops it being pushed twice; a failed one leaves it, so the next mapping
 * change or the next Stock In retries.
 */
class DepartedSkuSync
{
    public function __construct(
        private ChillerGateway $gateway,
        private ChillerChannelMap $channelMap,
        private StockPollService $stock,
    ) {}

    /**
     * @return array{
     *   status: string,
     *   skus: array<int,array{citybox_product_id:int,product_id:int,name:string,qty_before:int,qty_after:?int}>,
     *   message: ?string
     * }
     */
    public function sync(Vend $vend, bool $force = false): array
    {
        $none = fn (string $status, ?string $message = null) => ['status' => $status, 'skus' => [], 'message' => $message];

        if (! $vend->isSmartChiller() || ! $vend->citybox_equipment_id) {
            return $none('skipped');
        }
        // A Stock In in flight owns the push; its own leaving-SKU block covers this
        // and a second write would race the driver's counts.
        if (! $force && $this->stock->submitPendingFor($vend)) {
            return $none('skipped', 'A stock submit is pending for this machine; the departed SKUs go with it.');
        }

        $departed = $this->departedRows($vend);
        if ($departed === []) {
            return $none('nothing_to_do');
        }

        try {
            $live = $this->liveByCityboxId($vend);
        } catch (\Throwable $e) {
            return $none('failed', 'Could not read CityBox stock: '.$e->getMessage());
        }

        // BEFORE: only SKUs their side actually still holds. One they already show
        // as empty needs no write, and one they do not list cannot be corrected.
        $rows = [];
        foreach ($departed as $row) {
            $cityboxId = $this->cityboxIdFor((int) $row->product_id);
            if ($cityboxId === null || ! isset($live[$cityboxId]) || $live[$cityboxId]->quantity <= 0) {
                continue;
            }
            $rows[$cityboxId] = ['row' => $row, 'line' => $live[$cityboxId]];
        }

        if ($rows === []) {
            // Nothing for CityBox, but our rows are stale — settle them locally.
            $this->clearLocal($departed);

            return $none('nothing_to_do', 'CityBox already shows these as empty.');
        }

        $msgId = $this->latestSessionId($vend);
        if ($msgId === null) {
            return $none('deferred', 'This machine has no door-open session yet, so CityBox cannot be corrected until its next visit.');
        }

        try {
            $session = new RestockSession((string) $vend->citybox_equipment_id, $msgId, '', now()->toImmutable());
            $this->gateway->submitCount($session, StockCount::of(array_fill_keys(array_keys($rows), 0)));
        } catch (\Throwable $e) {
            // Left un-zeroed on purpose: the retired rows keep their qty, so the
            // next mapping change or Stock In tries again.
            Log::warning('Citybox departed-SKU zero push failed', ['vend_id' => $vend->id, 'skus' => array_keys($rows), 'error' => $e->getMessage()]);

            return $none('deferred', 'CityBox refused the update ('.$e->getMessage().'). It will go with this machine\'s next stock submit.');
        }

        // AFTER: read their side back so the result is their number, not our hope.
        $after = [];
        try {
            $after = $this->liveByCityboxId($vend);
        } catch (\Throwable) {
            $after = [];
        }

        $report = [];
        foreach ($rows as $cityboxId => $pair) {
            $report[] = [
                'citybox_product_id' => $cityboxId,
                'product_id' => (int) $pair['row']->product_id,
                'name' => $pair['line']->name,
                'qty_before' => (int) $pair['line']->quantity,
                'qty_after' => isset($after[$cityboxId]) ? (int) $after[$cityboxId]->quantity : null,
            ];
        }

        $this->clearLocal(array_map(fn ($p) => $p['row'], $rows));

        Log::info('Citybox departed SKUs zeroed after a planogram change', [
            'vend_id' => $vend->id, 'msg_id' => $msgId, 'skus' => $report,
        ]);

        return ['status' => 'pushed', 'skus' => $report, 'message' => null];
    }

    /**
     * Rows retired by the last rebuild that still carry stock, minus anything the
     * CURRENT mapping brought back. Re-checked against the mapping rather than
     * trusting is_active alone, so a SKU re-added between the retire and now is
     * never zeroed out from under the operator.
     *
     * @return array<int,VendChannel>
     */
    private function departedRows(Vend $vend): array
    {
        $carried = $this->channelMap->forVend($vend); // keyed by product id

        return VendChannel::where('vend_id', $vend->id)
            ->where('is_active', false)
            ->whereNotNull('product_id')
            ->where('qty', '>', 0)
            ->get()
            ->reject(fn (VendChannel $row) => isset($carried[(int) $row->product_id]))
            ->values()
            ->all();
    }

    /** @return array<int,ChillerStockLine> keyed by citybox product id */
    private function liveByCityboxId(Vend $vend): array
    {
        return $this->gateway->deviceStock((string) $vend->citybox_equipment_id)
            ->keyBy(fn (ChillerStockLine $l) => (int) $l->cityboxProductId)
            ->all();
    }

    private function cityboxIdFor(int $productId): ?int
    {
        $id = CityboxProduct::where('product_id', $productId)
            ->where('is_delisted', false)
            ->orderBy('citybox_product_id')
            ->value('citybox_product_id');

        return $id === null ? null : (int) $id;
    }

    /** The machine's most recent successful open, from any source — same rule the ops-job push uses. */
    private function latestSessionId(Vend $vend): ?string
    {
        return CityboxDoorOpenLog::where('vend_id', $vend->id)
            ->where('result', CityboxDoorOpenLog::RESULT_OPENED)
            ->whereNotNull('msg_id')
            ->latest('requested_at')
            ->value('msg_id');
    }

    /** @param array<int,VendChannel> $rows */
    private function clearLocal(array $rows): void
    {
        $ids = array_map(fn (VendChannel $r) => $r->id, $rows);
        if ($ids !== []) {
            VendChannel::whereIn('id', $ids)->update(['qty' => 0]);
        }
    }
}
