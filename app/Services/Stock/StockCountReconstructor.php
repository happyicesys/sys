<?php

namespace App\Services\Stock;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Estimates a lost nightly stock count (stock_count_items for one day) from
 * the snapshots either side of it, that day's sales and the refill visits.
 *
 * SaveStockCount reads live vend_channels, so a night it missed can never be
 * re-run (re-running stamps TODAY's stock on the old date). What can be done
 * is arithmetic, per machine x product:
 *
 *   no refill visit on D          qty(D) = qty(D-1) - sold(D)
 *   visit on D, none on D+1       qty(D) = qty(D+1) + sold(D+1)
 *   visits on both / one side     the side that exists, D+1 first
 *
 * sold = gp_metrics.success_count; a visit = a completed ops_job_item
 * (status >= 3, <> 99) on a job dated that day. Which products the machine
 * carried on D (measured on 09-17, 09-23 and 10-06):
 *
 *   on both sides                       yes
 *   on D-1 only                         yes if no visit on D and (a visit on
 *                                       D+1 took it off, or there is no D+1
 *                                       snapshot); else it went on D
 *   on D+1 only                         yes if a visit on D brought it, or no
 *                                       visit on D+1 could have (it was added
 *                                       outside a job), or D-1 has no snapshot
 *
 * Visits on both D and D+1: qty(D-1) - sold(D) measured closer than the next
 * side. Warehouse qty cannot be estimated (CMS keeps no history): NULL.
 * Score any real day with `stock-count:reconstruct-day <date> --backtest`.
 */
class StockCountReconstructor
{
    public const CASE_NO_VISIT = 'no_visit';

    public const CASE_REFILLED = 'refilled';

    public const CASE_FALLBACK = 'fallback';

    /**
     * @return list<array{stock_count_id:int, vend_id:int, product_id:int, qty_vend:int, stock_value_amount:int, stock_cost_amount:int, unit_cost_amount:int, case:string}>
     */
    public function estimate(Carbon $date): array
    {
        $day = $date->copy()->startOfDay();
        $prev = $this->snapshot($day->copy()->subDay());
        $next = $this->snapshot($day->copy()->addDay());
        $soldDay = $this->sold($day);
        $soldNext = $this->sold($day->copy()->addDay());
        $visitedDay = $this->visited($day);
        $visitedNext = $this->visited($day->copy()->addDay());

        $rows = [];
        foreach ($this->headers($day) as $header) {
            $vendId = (int) $header->vend_id;
            $before = $prev[$vendId] ?? [];
            $after = $next[$vendId] ?? [];
            $visit = isset($visitedDay[$vendId]);
            $visitNext = isset($visitedNext[$vendId]);

            foreach (array_unique(array_merge(array_keys($before), array_keys($after))) as $productId) {
                $b = $before[$productId] ?? null;
                $a = $after[$productId] ?? null;
                $carried = ($b && $a)
                    || ($b && ! $visit && ($visitNext || ! isset($next[$vendId])))
                    || ($a && ($visit || ! $visitNext || ! isset($prev[$vendId])));
                if (! $carried) {
                    continue;
                }

                $sold = (int) ($soldDay[$vendId][$productId] ?? 0);
                $soldAfter = (int) ($soldNext[$vendId][$productId] ?? 0);

                if (! $visit && $b) {
                    [$qty, $case, $source] = [max(0, $b->qty - $sold), self::CASE_NO_VISIT, $b];
                } elseif ($visit && ! $visitNext && $a) {
                    [$qty, $case, $source] = [$a->qty + $soldAfter, self::CASE_REFILLED, $a];
                } elseif ($b) {
                    [$qty, $case, $source] = [max(0, $b->qty - $sold), self::CASE_FALLBACK, $b];
                } else {
                    [$qty, $case, $source] = [$a->qty + $soldAfter, self::CASE_FALLBACK, $a];
                }

                // Per-unit value and cost come from the snapshot the qty came
                // from; the other side only when that one holds no stock.
                $other = $source === $a ? $b : $a;
                $priced = $source->qty > 0 ? $source : (($other && $other->qty > 0) ? $other : null);
                $value = $priced ? (int) round($priced->value * $qty / $priced->qty) : 0;
                $unitCost = (int) ($source->unit_cost ?: ($other->unit_cost ?? 0));

                $rows[] = [
                    'stock_count_id' => (int) $header->id,
                    'vend_id' => $vendId,
                    'product_id' => (int) $productId,
                    'qty_vend' => $qty,
                    'stock_value_amount' => $value,
                    'stock_cost_amount' => $unitCost * $qty,
                    'unit_cost_amount' => $unitCost,
                    'case' => $case,
                ];
            }
        }

        return $rows;
    }

    /** stock_counts headers of the day, with how many items each already has. */
    public function headers(Carbon $day)
    {
        return DB::table('stock_counts as sc')
            ->where(['sc.year' => $day->year, 'sc.month' => $day->month, 'sc.day' => $day->day])
            ->select('sc.id', 'sc.vend_id')
            ->selectRaw('(SELECT COUNT(*) FROM stock_count_items sci WHERE sci.stock_count_id = sc.id) AS item_count')
            ->orderBy('sc.id')
            ->get();
    }

    /** [vend_id => [product_id => {qty, value, unit_cost}]] for one day's snapshot. */
    public function snapshot(Carbon $day): array
    {
        $out = [];
        DB::table('stock_count_items as sci')
            ->join('stock_counts as sc', 'sc.id', '=', 'sci.stock_count_id')
            ->where(['sc.year' => $day->year, 'sc.month' => $day->month, 'sc.day' => $day->day])
            ->groupBy('sc.vend_id', 'sci.product_id')
            ->select('sc.vend_id', 'sci.product_id')
            ->selectRaw('SUM(COALESCE(sci.qty_vend, 0)) AS qty, SUM(sci.stock_value_amount) AS value, MAX(sci.unit_cost_amount) AS unit_cost')
            ->get()
            ->each(function ($r) use (&$out) {
                $out[(int) $r->vend_id][(int) $r->product_id] = (object) [
                    'qty' => (int) $r->qty, 'value' => (int) $r->value, 'unit_cost' => (int) $r->unit_cost,
                ];
            });

        return $out;
    }

    /** [vend_id => [product_id => units dispensed]] on one day. */
    private function sold(Carbon $day): array
    {
        $out = [];
        DB::table('gp_metrics')
            ->where('txn_date', $day->toDateString())
            ->whereNotNull('vend_id')
            ->whereNotNull('product_id')
            ->groupBy('vend_id', 'product_id')
            ->select('vend_id', 'product_id')
            ->selectRaw('SUM(success_count) AS units')
            ->get()
            ->each(function ($r) use (&$out) {
                $out[(int) $r->vend_id][(int) $r->product_id] = (int) $r->units;
            });

        return $out;
    }

    /** [vend_id => true] for machines with a completed ops job item that day. */
    private function visited(Carbon $day): array
    {
        return DB::table('ops_job_items as oji')
            ->join('ops_jobs as oj', 'oj.id', '=', 'oji.ops_job_id')
            ->where('oj.date', '>=', $day->copy()->startOfDay())
            ->where('oj.date', '<', $day->copy()->addDay()->startOfDay())
            ->where('oji.status', '>=', 3)
            ->where('oji.status', '<>', 99)
            ->distinct()
            ->pluck('oji.vend_id')
            ->mapWithKeys(fn ($id) => [(int) $id => true])
            ->all();
    }
}
