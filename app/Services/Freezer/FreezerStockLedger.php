<?php

namespace App\Services\Freezer;

use App\Jobs\Vend\SaveVendChannelsJson;
use App\Models\OpsJobItem;
use App\Models\Vend;
use App\Models\VendChannel;
use App\Models\VendTransaction;
use App\Support\DispenseVerdict;
use Illuminate\Support\Facades\DB;

/**
 * A Smart Freezer's stock, per SKU: in on Stock In, out on each sale.
 *
 * A vending machine's `vend_channels.qty` is whatever its VMC last reported. A freezer reports
 * nothing, so mark1 keeps the count itself (a CityBox chiller does the same since 2026-10-13, from
 * CityBox's orders — ChillerStockLedger)
 * (FreezerChannelSync already carries it across mapping changes, but until 2026-09-30 nothing wrote
 * it: 50001 stocked 3 cheesecakes in job 58533 and still read 0).
 *
 *  - **Stock In** adds each SKU's refill (`actual_qty`, negative for a return). Relative, not
 *    "count + refill": the driver never types a count — the page sends back the qty it loaded, so
 *    an absolute write would put back every sale made while the page was open.
 *  - **Undo Stock In** takes back what that Stock In actually moved — the exact inverse. Both
 *    directions clamp at zero, so the refill is not always what moved (a return of 5 against a
 *    ledger of 3 moves 3): each line records `ledger_applied_qty`, and undo reverses that (audit
 *    SF-14, 2026-10-17; a line stocked in before the column falls back to its refill). Only on rows
 *    still in the planogram: after a mapping swap a retired SKU's returned stock must not come back.
 *  - **A sale** takes one off per unit the TRADE reports dispensed, by product (`transf_info[].goods_id`
 *    = mark1 product id), once — only when the ingest creates or first fills the row.
 *
 * Never below zero: a sale the ledger never saw stocked in (the freezer went live before this
 * existed) must not leave a negative count on the dashboard. The AI verdict does not move stock yet
 * (Brian decides what a took_more / took_less should do).
 */
class FreezerStockLedger
{
    public function stockIn(OpsJobItem $item): void
    {
        $vend = $item->vend;
        if (! $vend || ! $vend->isSmartFreezer()) {
            return;
        }

        DB::transaction(function () use ($item, $vend) {
            foreach ($item->opsJobItemChannels()->get() as $line) {
                $refill = (int) $line->actual_qty;
                if (! $line->vend_channel_id || $refill === 0) {
                    continue;
                }
                $applied = $this->move(VendChannel::whereKey($line->vend_channel_id)->where('vend_id', $vend->id), $refill);
                $line->forceFill(['ledger_applied_qty' => $applied])->saveQuietly();
            }
        });

        $this->refreshJson($vend);
    }

    public function undoStockIn(OpsJobItem $item): void
    {
        $vend = $item->vend;
        if (! $vend || ! $vend->isSmartFreezer()) {
            return;
        }

        DB::transaction(function () use ($item, $vend) {
            foreach ($item->opsJobItemChannels()->get() as $line) {
                $applied = $line->ledger_applied_qty !== null ? (int) $line->ledger_applied_qty : (int) $line->actual_qty;
                if ($line->vend_channel_id && $applied !== 0) {
                    $this->move(VendChannel::whereKey($line->vend_channel_id)->where('vend_id', $vend->id)->where('is_active', true), -$applied);
                }
                if ($line->ledger_applied_qty !== null) {
                    $line->forceFill(['ledger_applied_qty' => null])->saveQuietly();
                }
            }
        });

        $this->refreshJson($vend);
    }

    /**
     * Adds $delta to one row, never below zero, under a row lock.
     *
     * @return int what actually moved (0 when the row is not there)
     */
    private function move($row, int $delta): int
    {
        $channel = $row->lockForUpdate()->first(['id', 'qty']);
        if (! $channel) {
            return 0;
        }
        $before = (int) $channel->qty;
        $after = max($before + $delta, 0);
        if ($after !== $before) {
            VendChannel::whereKey($channel->id)->update(['qty' => $after]);
        }

        return $after - $before;
    }

    /**
     * Called inside the ingest transaction, only on the call that creates or first fills the row,
     * so a replayed TRADE never deducts twice.
     */
    public function sale(Vend $vend, VendTransaction $sale): void
    {
        if (! $vend->isSmartFreezer()) {
            return;
        }

        foreach ($this->unitsDispensed($sale) as $productId => $units) {
            VendChannel::where('vend_id', $vend->id)->where('product_id', $productId)
                ->update(['qty' => DB::raw('GREATEST(CAST(qty AS SIGNED) - '.(int) $units.', 0)')]);
        }

        $this->refreshJson($vend);
    }

    /** @return array<int, int> product id => units dispensed */
    public function unitsDispensed(VendTransaction $sale): array
    {
        $frame = $sale->vend_transaction_json;
        if (is_string($frame)) {
            $frame = json_decode($frame, true);
        }

        $units = [];
        foreach ((array) (($frame['transf_info'] ?? null) ?: []) as $unit) {
            $productId = (int) ($unit['goods_id'] ?? 0);
            $code = isset($unit['SErr']) ? (int) $unit['SErr'] : null;
            if ($productId > 0 && DispenseVerdict::isDispensed($code)) {
                $units[$productId] = ($units[$productId] ?? 0) + 1;
            }
        }

        return $units;
    }

    /** After commit: the dashboard JSON must read the committed qty. */
    private function refreshJson(Vend $vend): void
    {
        DB::afterCommit(fn () => SaveVendChannelsJson::dispatch($vend->id)->onQueue('default'));
    }
}
