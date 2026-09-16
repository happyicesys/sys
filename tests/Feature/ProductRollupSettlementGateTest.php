<?php

namespace Tests\Feature;

use App\Jobs\StoreVendProductRecords;
use App\Models\Operator;
use App\Models\Vend;
use App\Models\VendTransaction;
use App\Models\VendTransactionItem;
use App\Services\VendTransactionSalesAggregator;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Audit M1-02: the two product-level aggregators filtered on amount and the
 * DispenseVerdict sale code but never on settlement_status, unlike every
 * other money rollup (StoreVendsRecord, GpMetricsAggregator, ProductScopedSales).
 * A refunded gateway sale is code 99 (a sale code) with product_id set at
 * pre-create, so it landed in vend_product_records and in productTotals() as
 * product revenue. Prod 2026-09-02..16: 46 refunded singles, $150.50.
 */
class ProductRollupSettlementGateTest extends TestCase
{
    use RefreshDatabase;

    private const DAY = '2026-09-10';

    private const PRODUCT = 700;

    private int $vendId;

    private int $operatorId;

    protected function setUp(): void
    {
        parent::setUp();
        $operator = Operator::create(['code' => 'OP1', 'name' => 'Op', 'country_id' => 1]);
        $this->operatorId = $operator->id;
        $this->vendId = Vend::create(['code' => '9001', 'operator_id' => $operator->id])->id;
        DB::table('products')->insert(['id' => self::PRODUCT, 'name' => 'Gated SKU']);
    }

    private function single(string $order, int $cents, int $settlement): VendTransaction
    {
        return VendTransaction::forceCreate([
            'order_id' => $order, 'vend_id' => $this->vendId, 'operator_id' => $this->operatorId,
            'vend_channel_id' => 0, 'product_id' => self::PRODUCT, 'amount' => $cents, 'qty' => 1,
            'revenue' => $cents, 'gross_profit' => $cents, 'gst_vat_rate' => 9, 'is_multiple' => false,
            'transaction_datetime' => self::DAY.' 10:00:00', 'settlement_status' => $settlement,
        ]);
    }

    private function multi(string $order, int $cents, int $settlement): VendTransaction
    {
        $txn = VendTransaction::forceCreate([
            'order_id' => $order, 'vend_id' => $this->vendId, 'operator_id' => $this->operatorId,
            'vend_channel_id' => 0, 'amount' => $cents, 'qty' => 1, 'gst_vat_rate' => 9, 'is_multiple' => true,
            'transaction_datetime' => self::DAY.' 11:00:00', 'settlement_status' => $settlement,
        ]);
        VendTransactionItem::forceCreate([
            'vend_transaction_id' => $txn->id, 'product_id' => self::PRODUCT, 'vend_channel_id' => 0,
            'unit_price_amount' => $cents, 'unit_cost' => 0, 'vend_channel_error_code' => 0,
        ]);

        return $txn;
    }

    public function test_vend_product_records_count_settled_sales_only(): void
    {
        $this->single('S-OK', 250, VendTransaction::SETTLEMENT_SETTLED);
        $this->single('S-REFUNDED', 350, VendTransaction::SETTLEMENT_REFUNDED);
        $this->single('S-PENDING', 450, VendTransaction::SETTLEMENT_PENDING);
        $this->multi('M-OK', 500, VendTransaction::SETTLEMENT_SETTLED);
        $this->multi('M-REFUNDED', 600, VendTransaction::SETTLEMENT_REFUNDED);

        (new StoreVendProductRecords(self::DAY, self::DAY))->handle();

        $row = DB::table('vend_product_records')->where('product_id', self::PRODUCT)->where('date', self::DAY)
            ->selectRaw('SUM(total_amount) amount, SUM(total_count) cnt, SUM(revenue) revenue')->first();

        $this->assertSame(750, (int) $row->amount, 'only the settled single (250) + settled multi (500)');
        $this->assertSame(2, (int) $row->cnt);
        $this->assertSame(750, (int) $row->revenue);
    }

    public function test_product_totals_exclude_refunded_unless_attempted_counts_are_asked_for(): void
    {
        $this->single('S-OK', 250, VendTransaction::SETTLEMENT_SETTLED);
        $this->single('S-REFUNDED', 350, VendTransaction::SETTLEMENT_REFUNDED);
        $this->multi('M-OK', 500, VendTransaction::SETTLEMENT_SETTLED);
        $this->multi('M-REFUNDED', 600, VendTransaction::SETTLEMENT_REFUNDED);
        $start = Carbon::parse(self::DAY);
        $end = Carbon::parse(self::DAY);

        $sold = VendTransactionSalesAggregator::productTotals($start, $end)->get()->keyBy('product_id');
        $this->assertSame(750, (int) $sold[self::PRODUCT]->total_amount, 'dashboard product totals: settled only');
        $this->assertSame(2, (int) $sold[self::PRODUCT]->total_count);

        // includeAll = "attempted" (demand planning): a refunded attempt still counts as demand.
        $attempted = VendTransactionSalesAggregator::productTotals($start, $end, null, true)->get()->keyBy('product_id');
        $this->assertSame(4, (int) $attempted[self::PRODUCT]->total_count, 'attempted counts keep refunded rows');
    }
}
