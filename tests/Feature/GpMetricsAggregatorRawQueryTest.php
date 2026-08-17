<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Vend;
use App\Models\VendTransaction;
use App\Models\VendTransactionItem;
use App\Services\GpMetricsAggregator;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GpMetricsAggregatorRawQueryTest extends TestCase
{
    use RefreshDatabase;

    private Vend $vend;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        Model::unguard();

        $this->vend = Vend::create(['code' => 'V900', 'name' => 'Aggregator Vend']);
        $this->product = Product::create(['code' => 'P900', 'name' => 'Aggregator Cola']);
    }

    private function multiTransaction(string $at, int $amount, array $itemPrices, string $orderId, ?int $productId = null): VendTransaction
    {
        $txn = VendTransaction::create([
            'order_id' => $orderId,
            'vend_id' => $this->vend->id,
            'transaction_datetime' => Carbon::parse($at),
            'amount' => $amount,
            'qty' => count($itemPrices),
            'is_multiple' => true,
            'vend_channel_id' => 0,
            'gst_vat_rate' => 0,
            'settlement_status' => VendTransaction::SETTLEMENT_SETTLED,
        ]);

        foreach ($itemPrices as $price) {
            VendTransactionItem::create([
                'vend_transaction_id' => $txn->id,
                'unit_price_amount' => $price,
                'vend_channel_id' => 0,
                'product_id' => $productId ?? $this->product->id,
            ]);
        }

        return $txn;
    }

    /**
     * Regression: the per-basket item subqueries (vti_sum, pcs_count) must be scoped
     * to the requested date range. Unscoped they grouped every row of
     * vend_transaction_items (~309k on prod) and were materialised on every run of
     * both the nightly builder and the live Sales Report read.
     */
    public function test_item_subqueries_are_scoped_to_the_requested_date_range()
    {
        $sql = GpMetricsAggregator::buildRawQuery(
            Carbon::parse('2026-08-10'),
            Carbon::parse('2026-08-16')
        )->toSql();

        // vti_sum
        $this->assertMatchesRegularExpression(
            '/from `vend_transaction_items` as `vti` inner join `vend_transactions`/',
            $sql,
            'vti_sum must join vend_transactions so it can be date-scoped.'
        );

        // pcs_count
        $this->assertMatchesRegularExpression(
            '/from `vend_transaction_items` as `pc_vti` inner join `vend_transactions` as `pc_vt`/',
            $sql,
            'pcs_count must join vend_transactions so it can be date-scoped.'
        );

        $this->assertStringContainsString('`pc_vt`.`transaction_datetime` between', $sql);
    }

    /**
     * Pushing the date filter into the subqueries must not move a single number:
     * they are keyed by vend_transaction_id and consumed only for transactions the
     * outer query has already restricted to the range.
     */
    public function test_out_of_range_baskets_do_not_affect_in_range_totals()
    {
        // In range: basket of 300 + 200, transaction amount 500.
        $this->multiTransaction('2026-08-12 10:00:00', 500, [300, 200], 'IN-RANGE-1');

        $inRangeOnly = GpMetricsAggregator::buildRawQuery(
            Carbon::parse('2026-08-10'),
            Carbon::parse('2026-08-16')
        )->get();

        // Now add busy baskets outside the range. If the subqueries leaked, these
        // would perturb item_sum / total_count and shift the in-range figures.
        $this->multiTransaction('2026-07-01 10:00:00', 9900, [1000, 2000, 3000, 3900], 'OUT-1');
        $this->multiTransaction('2026-09-01 10:00:00', 8800, [4400, 4400], 'OUT-2');

        $afterNoise = GpMetricsAggregator::buildRawQuery(
            Carbon::parse('2026-08-10'),
            Carbon::parse('2026-08-16')
        )->get();

        $this->assertEquals(
            $inRangeOnly->toArray(),
            $afterNoise->toArray(),
            'Out-of-range baskets must not change in-range aggregates.'
        );

        $this->assertCount(1, $afterNoise);
        $this->assertSame(500, (int) $afterNoise->first()->amount_cents);
        $this->assertSame(500, (int) $afterNoise->first()->txn_amount_cents);
    }

    /**
     * A zero-priced item absorbs the unallocated remainder of the basket, so
     * amount_cents still reconciles to vend_transactions.amount.
     */
    public function test_zero_priced_items_absorb_the_basket_remainder()
    {
        $this->multiTransaction('2026-08-12 11:00:00', 500, [300, 0], 'ZERO-1');

        $rows = GpMetricsAggregator::buildRawQuery(
            Carbon::parse('2026-08-12'),
            Carbon::parse('2026-08-12')
        )->get();

        $this->assertSame(500, (int) collect($rows)->sum('amount_cents'));
    }

    /**
     * Pinned known behaviour, not an endorsement. When neither the item nor its
     * channel resolves a product_id, the pcs_count join is an equality against
     * COALESCE(...) = NULL, which never matches — so txn_amount_cents comes back
     * NULL even though amount_cents is correct. This matters for the plan to
     * persist txn_amount_cents into gp_metrics: the backfill must decide whether
     * NULL here means "unknown" or "zero", and reconcile it against the
     * transaction page totals the column is supposed to match.
     */
    public function test_unresolvable_product_leaves_txn_amount_cents_null()
    {
        $txn = VendTransaction::create([
            'order_id' => 'NO-PRODUCT',
            'vend_id' => $this->vend->id,
            'transaction_datetime' => Carbon::parse('2026-08-12 12:00:00'),
            'amount' => 500,
            'qty' => 2,
            'is_multiple' => true,
            'vend_channel_id' => 0,
            'gst_vat_rate' => 0,
            'settlement_status' => VendTransaction::SETTLEMENT_SETTLED,
        ]);

        foreach ([300, 200] as $price) {
            VendTransactionItem::create([
                'vend_transaction_id' => $txn->id,
                'unit_price_amount' => $price,
                'vend_channel_id' => 0,
                'product_id' => null,
            ]);
        }

        $row = GpMetricsAggregator::buildRawQuery(
            Carbon::parse('2026-08-12'),
            Carbon::parse('2026-08-12')
        )->first();

        $this->assertSame(500, (int) $row->amount_cents);
        $this->assertNull($row->txn_amount_cents);
    }

    public function test_transactions_outside_the_range_are_excluded_entirely()
    {
        $this->multiTransaction('2026-08-09 23:59:59', 700, [700], 'BEFORE');
        $this->multiTransaction('2026-08-17 00:00:00', 700, [700], 'AFTER');

        $rows = GpMetricsAggregator::buildRawQuery(
            Carbon::parse('2026-08-10'),
            Carbon::parse('2026-08-16')
        )->get();

        $this->assertCount(0, $rows);
    }
}
