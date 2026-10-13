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
 * (status >= 3, <> 99) on a job dated that day. A product is carried on D when
 * it is on both sides, or on D-1 with no visit on D (nothing could have taken
 * it off), or on D+1 with a visit on D (it arrived with the refill).
 *
 * Backtested against real days (2026-10-13): 09-17 98.2% of rows exact, fleet
 * total -0.14%; 09-23 95.3% exact, total -0.06%. Warehouse qty cannot be
 * estimated (CMS keeps no history), so it is left NULL.
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
                if (! (($b && $a) || ($b && ! $visit) || ($a && $visit))) {
                    continue;
                }

                $sold = (int) ($soldDay[$vendId][$productId] ?? 0);
                $soldAfter = (int) ($soldNext[$vendId][$productId] ?? 0);

                if (! $visit && $b) {
                    [$qty, $case, $source] = [max(0, $b->qty - $sold), self::CASE_NO_VISIT, $b];
                } elseif ($visit && ! $visitNext && $a) {
                    [$qty, $case, $source] = [$a->qty + $soldAfter, self::CASE_REFILLED, $a];
                } elseif ($a) {
                    [$qty, $case, $source] = [$a->qty + $soldAfter, self::CASE_FALLBACK, $a];
                } else {
                    [$qty, $case, $source] = [max(0, $b->qty - $sold), self::CASE_FALLBACK, $b];
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
