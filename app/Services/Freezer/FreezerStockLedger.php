<?php

namespace App\Services\Freezer;

use App\Jobs\Vend\SaveVendChannelsJson;
use App\Models\OpsJobItem;
use App\Models\Vend;
use App\Models\VendChannel;
use App\Models\VendTransaction;
use App\Support\DispenseVerdict;
use App\Support\FreezerTestSale;
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
 * A Testing Mode sale (TRADE `TEST`, FreezerTestSale) takes nothing off: unitsDispensed() reads
 * no unit from it, so neither the live ledger nor `freezer:rebuild-stock` counts it.
 *
 * Never below zero: a sale the ledger never saw stocked in (the freezer went live before this
 * existed) must not leave a negative count on the dashboard.
 *
 * **The AI's basket corrects a T05 sale** (Brian, 2026-11-03: 50001 read 3 Paddle Pops sold when none
 * had been). The TRADE takes off what was paid for, because the verdict comes minutes later;
 * followCharge() then moves the difference, once, in the transaction that settles the sale, and
 * records it on the sale (`meta_json.audit.stock_moved`, + put back, − taken off), which
 * unitsOut() and `freezer:rebuild-stock` read. FreezerSaleAudit::stockBasket() says when it applies:
 * only the AI's own view of what left, never a charge a person keyed in. QR and NETS sales keep
 * their TRADE's figure, as they keep its money.
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

    /**
     * Make a sale's stock movement what the AI saw leave instead of what the TRADE reported.
     * The caller runs it once, inside the transaction that records the result on the sale.
     *
     * @param  array<int, int>  $taken  product id => units the AI saw leave ([] = nothing did)
     * @return array<int, int> product id => units put back (+) or taken off (−)
     */
    public function followCharge(Vend $vend, VendTransaction $sale, array $taken): array
    {
        if (! $vend->isSmartFreezer()) {
            return [];
        }

        $moved = $this->chargeDifference($sale, $taken);
        foreach ($moved as $productId => $back) {
            VendChannel::where('vend_id', $vend->id)->where('product_id', $productId)
                ->update(['qty' => DB::raw('GREATEST(CAST(qty AS SIGNED) + '.(int) $back.', 0)')]);
        }

        if ($moved !== []) {
            $this->refreshJson($vend);
        }

        return $moved;
    }

    /**
     * A person's count of what left replaces whatever the sale's stock said before: only the
     * difference moves now, and the figure returned is its whole movement against the TRADE. Runs
     * inside the transaction that records it on the sale.
     *
     * @param  array<int, int>  $taken  product id => units a person says left
     * @return array<int, int> product id => units put back (+) or taken off (−), against the TRADE
     */
    public function followCorrection(Vend $vend, VendTransaction $sale, array $taken): array
    {
        if (! $vend->isSmartFreezer()) {
            return [];
        }

        $before = array_map('intval', (array) (((array) $sale->meta_json)['audit']['stock_moved'] ?? []));
        $moved = $this->chargeDifference($sale, $taken);
        foreach (array_keys($moved + $before) as $productId) {
            $delta = ($moved[$productId] ?? 0) - ($before[$productId] ?? 0);
            if ($delta !== 0) {
                VendChannel::where('vend_id', $vend->id)->where('product_id', $productId)
                    ->update(['qty' => DB::raw('GREATEST(CAST(qty AS SIGNED) + '.$delta.', 0)')]);
            }
        }
        $this->refreshJson($vend);

        return $moved;
    }

    /**
     * What followCharge() would move. A Testing Mode sale moved nothing, so nothing corrects it.
     *
     * @param  array<int, int>  $taken  product id => units the AI saw leave
     * @return array<int, int> product id => units put back (+) or taken off (−)
     */
    public function chargeDifference(VendTransaction $sale, array $taken): array
    {
        if (FreezerTestSale::is($sale)) {
            return [];
        }

        $out = $this->unitsDispensed($sale);
        $moved = [];
        foreach (array_keys($out + $taken) as $productId) {
            $back = ($out[$productId] ?? 0) - ($taken[$productId] ?? 0);
            if ($back !== 0) {
                $moved[$productId] = $back;
            }
        }
        ksort($moved);

        return $moved;
    }

    /**
     * What this sale finally took out of the cabinet: the TRADE's units, corrected by the AI or a person.
     *
     * @return array<int, int> product id => units
     */
    public function unitsOut(VendTransaction $sale): array
    {
        $units = $this->unitsDispensed($sale);
        foreach ((array) (((array) $sale->meta_json)['audit']['stock_moved'] ?? []) as $productId => $back) {
            $left = ($units[(int) $productId] ?? 0) - (int) $back;
            if ($left === 0) {
                unset($units[(int) $productId]);
            } else {
                $units[(int) $productId] = $left;
            }
        }

        return $units;
    }

    /** @return array<int, int> product id => units dispensed */
    public function unitsDispensed(VendTransaction $sale): array
    {
        $frame = $sale->vend_transaction_json;
        if (is_string($frame)) {
            $frame = json_decode($frame, true);
        }
        if (FreezerTestSale::frameIsTest($frame)) {
            return [];
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
