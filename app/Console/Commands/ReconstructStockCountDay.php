<?php

namespace App\Console\Commands;

use App\Services\Stock\StockCountReconstructor;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Fills a day whose nightly stock count lost its items (2026-09-21: CMS timed
 * out and every SaveStockCount job died after its header). See
 * StockCountReconstructor for the arithmetic and its backtested accuracy.
 *
 * Preview by default. --backtest estimates a day that HAS a real snapshot and
 * scores the estimate against it. --apply writes items only under headers that
 * have none, with qty_warehouse NULL; a header that already has items is never
 * touched, so a second run is a no-op.
 */
class ReconstructStockCountDay extends Command
{
    protected $signature = 'stock-count:reconstruct-day {date : Y-m-d}
        {--backtest : Score the estimate against a day that has a real snapshot}
        {--apply : Write the estimated items (headers with no items only)}';

    protected $description = 'Estimate a lost day of stock_count_items from the neighbouring snapshots, sales and refill visits';

    public function handle(StockCountReconstructor $reconstructor): int
    {
        $date = Carbon::parse($this->argument('date'))->startOfDay();
        $headers = $reconstructor->headers($date);

        if ($headers->isEmpty()) {
            $this->error("No stock_counts headers for {$date->toDateString()}.");

            return self::FAILURE;
        }

        foreach ([$date->copy()->subDay(), $date->copy()->addDay()] as $side) {
            if (empty($reconstructor->snapshot($side))) {
                $this->error("No snapshot on {$side->toDateString()} to estimate from.");

                return self::FAILURE;
            }
        }

        $rows = collect($reconstructor->estimate($date));

        $this->table(['Rule', 'Rows', 'Machine qty'], $rows->groupBy('case')->map(fn ($g, $case) => [
            $case, $g->count(), $g->sum('qty_vend'),
        ])->values()->push(['TOTAL', $rows->count(), $rows->sum('qty_vend')])->all());

        $this->line(sprintf(
            'Neighbours: %s %d units, %s %d units. Headers: %d (%d already have items).',
            $date->copy()->subDay()->toDateString(), $this->total($reconstructor, $date->copy()->subDay()),
            $date->copy()->addDay()->toDateString(), $this->total($reconstructor, $date->copy()->addDay()),
            $headers->count(), $headers->where('item_count', '>', 0)->count(),
        ));

        if ($this->option('backtest')) {
            return $this->backtest($reconstructor, $date, $rows);
        }

        $writable = $headers->where('item_count', 0)->pluck('id')->flip();
        $toWrite = $rows->filter(fn ($r) => $writable->has($r['stock_count_id']));

        if (! $this->option('apply')) {
            $this->info("Preview only: {$toWrite->count()} items under {$writable->count()} empty headers would be written. Re-run with --apply.");

            return self::SUCCESS;
        }

        $now = now();
        DB::transaction(function () use ($toWrite, $now) {
            foreach ($toWrite->chunk(500) as $chunk) {
                DB::table('stock_count_items')->insert($chunk->map(fn ($r) => [
                    'stock_count_id' => $r['stock_count_id'],
                    'product_id' => $r['product_id'],
                    'qty_vend' => $r['qty_vend'],
                    'qty_warehouse' => null,
                    'stock_value_amount' => $r['stock_value_amount'],
                    'stock_cost_amount' => $r['stock_cost_amount'],
                    'unit_cost_amount' => $r['unit_cost_amount'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ])->values()->all());
            }
        });

        $this->info("Wrote {$toWrite->count()} estimated items for {$date->toDateString()} (qty_warehouse NULL).");

        return self::SUCCESS;
    }

    private function backtest(StockCountReconstructor $reconstructor, Carbon $date, $rows): int
    {
        $actual = $reconstructor->snapshot($date);
        $estimated = $rows->keyBy(fn ($r) => $r['vend_id'].':'.$r['product_id']);

        $pairs = 0;
        $exact = 0;
        $within1 = 0;
        $actualTotal = 0;
        foreach ($actual as $vendId => $products) {
            foreach ($products as $productId => $row) {
                $actualTotal += $row->qty;
                $est = $estimated->get($vendId.':'.$productId);
                $pairs++;
                if ($est && $est['qty_vend'] === $row->qty) {
                    $exact++;
                }
                if ($est && abs($est['qty_vend'] - $row->qty) <= 1) {
                    $within1++;
                }
            }
        }

        $this->line(sprintf(
            'Backtest %s: %d real rows, %.1f%% exact, %.1f%% within 1 unit; total estimated %d vs actual %d (%+.2f%%).',
            $date->toDateString(), $pairs,
            $pairs ? 100 * $exact / $pairs : 0, $pairs ? 100 * $within1 / $pairs : 0,
            $rows->sum('qty_vend'), $actualTotal,
            $actualTotal ? 100 * ($rows->sum('qty_vend') - $actualTotal) / $actualTotal : 0,
        ));

        return self::SUCCESS;
    }

    private function total(StockCountReconstructor $reconstructor, Carbon $day): int
    {
        return (int) collect($reconstructor->snapshot($day))->flatten(1)->sum('qty');
    }
}
