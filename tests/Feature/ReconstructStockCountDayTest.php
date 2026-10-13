<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * stock-count:reconstruct-day fills a day whose nightly snapshot lost its
 * items (2026-09-21) from the days either side, sales and refill visits.
 */
class ReconstructStockCountDayTest extends TestCase
{
    use RefreshDatabase;

    private int $seq = 0;

    private function header(int $vendId, int $day): int
    {
        return DB::table('stock_counts')->insertGetId([
            'vend_id' => $vendId, 'year' => 2026, 'month' => 9, 'day' => $day,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function item(int $stockCountId, int $productId, int $qty, int $value = 0, int $unitCost = 0): void
    {
        DB::table('stock_count_items')->insert([
            'stock_count_id' => $stockCountId, 'product_id' => $productId, 'qty_vend' => $qty,
            'qty_warehouse' => 50, 'stock_value_amount' => $value, 'stock_cost_amount' => $unitCost * $qty,
            'unit_cost_amount' => $unitCost, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function sold(int $vendId, int $productId, string $date, int $units): void
    {
        DB::table('gp_metrics')->insert(['txn_date' => $date, 'vend_id' => $vendId, 'product_id' => $productId, 'success_count' => $units, 'sale_count' => $units]);
    }

    private function visit(int $vendId, string $date, int $status = 3): void
    {
        $job = DB::table('ops_jobs')->insertGetId(['code' => 880000 + (++$this->seq), 'date' => $date.' 00:00:00', 'operator_id' => 1, 'created_by' => 1, 'status' => 1]);
        DB::table('ops_job_items')->insert(['ops_job_id' => $job, 'vend_id' => $vendId, 'customer_id' => 1, 'status' => $status, 'updated_by' => 1]);
    }

    /** A = no visit on 21; B = refilled on 21; C = 21 already has items. */
    private function fleet(): array
    {
        [$a, $b, $c] = [701, 702, 703];

        // A: P1 10 -> sells 4 on 21; P2 3 untouched; P3 appears only on 22 (no visit on 21, so not there yet).
        $a20 = $this->header($a, 20);
        $this->item($a20, 1, 10, 2000, 80);
        $this->item($a20, 2, 3, 600, 50);
        $a22 = $this->header($a, 22);
        $this->item($a22, 1, 5, 1000, 80);
        $this->item($a22, 2, 3, 600, 50);
        $this->item($a22, 3, 8, 800, 40);
        $this->sold($a, 1, '2026-09-21', 4);
        $this->sold($a, 1, '2026-09-22', 1);
        $this->visit($a, '2026-09-21', 99); // cancelled job: not a visit

        // B: refilled on 21, P1 9 at end of 22 after selling 1; P4 arrived with the refill; P5 removed at it.
        $b20 = $this->header($b, 20);
        $this->item($b20, 1, 2, 400, 80);
        $this->item($b20, 5, 4, 400, 30);
        $b22 = $this->header($b, 22);
        $this->item($b22, 1, 9, 1800, 80);
        $this->item($b22, 4, 6, 900, 60);
        $this->sold($b, 1, '2026-09-22', 1);
        $this->visit($b, '2026-09-21');

        // C: snapshot on 21 survived.
        $c20 = $this->header($c, 20);
        $this->item($c20, 1, 7, 1400, 80);
        $this->item($this->header($c, 22), 1, 7, 1400, 80);
        $c21 = $this->header($c, 21);
        $this->item($c21, 1, 7, 1400, 80);

        return ['a' => $this->header($a, 21), 'b' => $this->header($b, 21), 'c' => $c21];
    }

    private function itemsOf(int $stockCountId)
    {
        return DB::table('stock_count_items')->where('stock_count_id', $stockCountId)->get()->keyBy('product_id');
    }

    public function test_preview_writes_nothing(): void
    {
        $h = $this->fleet();

        $this->artisan('stock-count:reconstruct-day', ['date' => '2026-09-21'])->assertSuccessful();

        $this->assertCount(0, $this->itemsOf($h['a']));
        $this->assertCount(0, $this->itemsOf($h['b']));
    }

    public function test_apply_estimates_each_machine_by_its_visits_and_leaves_warehouse_unknown(): void
    {
        $h = $this->fleet();

        $this->artisan('stock-count:reconstruct-day', ['date' => '2026-09-21', '--apply' => true])->assertSuccessful();

        $a = $this->itemsOf($h['a']);
        $this->assertSame([1, 2], $a->keys()->sort()->values()->all(), 'P3 had no visit to arrive with');
        $this->assertSame(6, (int) $a[1]->qty_vend);            // 10 - 4 sold
        $this->assertSame(1200, (int) $a[1]->stock_value_amount); // 6 x 200 per unit
        $this->assertSame(480, (int) $a[1]->stock_cost_amount);   // 6 x 80
        $this->assertSame(80, (int) $a[1]->unit_cost_amount);
        $this->assertSame(3, (int) $a[2]->qty_vend);
        $this->assertNull($a[1]->qty_warehouse);

        $b = $this->itemsOf($h['b']);
        $this->assertSame([1, 4], $b->keys()->sort()->values()->all(), 'P5 left at the refill, P4 came with it');
        $this->assertSame(10, (int) $b[1]->qty_vend);           // 9 + 1 sold on 22
        $this->assertSame(6, (int) $b[4]->qty_vend);

        $this->assertCount(1, $this->itemsOf($h['c']));
        $this->assertSame(50, (int) $this->itemsOf($h['c'])[1]->qty_warehouse, 'a header with items is never touched');
    }

    public function test_a_second_apply_is_a_no_op(): void
    {
        $h = $this->fleet();
        $this->artisan('stock-count:reconstruct-day', ['date' => '2026-09-21', '--apply' => true])->assertSuccessful();
        $before = DB::table('stock_count_items')->count();

        $this->artisan('stock-count:reconstruct-day', ['date' => '2026-09-21', '--apply' => true])->assertSuccessful();

        $this->assertSame($before, DB::table('stock_count_items')->count());
    }

    public function test_backtest_scores_without_writing(): void
    {
        $h = $this->fleet();
        $before = DB::table('stock_count_items')->count();

        $this->artisan('stock-count:reconstruct-day', ['date' => '2026-09-21', '--backtest' => true])
            ->expectsOutputToContain('Backtest 2026-09-21: 1 real rows, 100.0% exact')
            ->assertSuccessful();

        $this->assertSame($before, DB::table('stock_count_items')->count());
    }

    public function test_refuses_without_a_neighbouring_snapshot(): void
    {
        $this->header(701, 21);
        $this->item($this->header(701, 20), 1, 5);

        $this->artisan('stock-count:reconstruct-day', ['date' => '2026-09-21', '--apply' => true])
            ->expectsOutputToContain('No snapshot on 2026-09-22')
            ->assertFailed();
    }
}
