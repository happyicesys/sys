<?php

namespace Tests\Feature;

use App\Models\Operator;
use App\Models\Product;
use App\Models\ProductMapping;
use App\Models\ProductMappingItem;
use App\Models\Scopes\OperatorVendFilterScope;
use App\Models\UnitCost;
use App\Models\Vend;
use App\Models\VendChannel;
use App\Models\VendTransaction;
use App\Services\Sales\ProductAttributionRepair;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Re-attributing sales booked against the wrong planogram (machine 2487,
 * 2026-09-17 → 09-23; see UNATTRIBUTED_SALES_AUDIT_2026-09-23.md).
 *
 * The arithmetic pinned here is the ingest arithmetic — if
 * VendTransactionService::processMapping ever changes how revenue, COGS or the
 * GST rate are derived, this test must move with it, or a repaired row stops
 * being identical to one booked correctly at the time.
 *
 * Run: php artisan test --filter=ProductAttributionRepairTest
 */
class ProductAttributionRepairTest extends TestCase
{
    use RefreshDatabase;

    private Operator $operator;

    private Vend $vend;

    private ProductMapping $right;   // what the machine really held

    private ProductMapping $wrong;   // what was bound by mistake

    private Product $rightProduct;

    private Product $wrongProduct;

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('operators')->insert([
            'id' => OperatorVendFilterScope::UNRESTRICTED_OPERATOR_ID,
            'code' => 'HIPL', 'name' => 'HIPL', 'is_active' => 1,
            'gst_vat_rate' => 9,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->operator = Operator::withoutGlobalScopes()->findOrFail(OperatorVendFilterScope::UNRESTRICTED_OPERATOR_ID);

        $this->rightProduct = $this->product('U-12', 90);   // 90c cost
        $this->wrongProduct = $this->product('U-70', 57);   // 57c cost

        $this->right = $this->mapping('USD_2608a');
        $this->wrong = $this->mapping('UEE_2609');

        // The right planogram covers the machine's real slot 13; the wrong one
        // does not cover 13 at all (that is the whole bug) but does cover 41.
        $this->item($this->right, '13', $this->rightProduct);
        $this->item($this->right, '41', $this->rightProduct);
        $this->item($this->wrong, '41', $this->wrongProduct);

        $this->vend = Vend::withoutGlobalScopes()->create([
            'code' => 2487,
            'operator_id' => $this->operator->id,
            'is_active' => true,
            'machine_type' => Vend::MACHINE_TYPE_VENDING_MACHINE,
            'product_mapping_id' => $this->wrong->id,
        ]);
    }

    private function product(string $code, int $costCents): Product
    {
        $product = Product::withoutGlobalScopes()->create([
            'code' => $code, 'name' => $code,
            'operator_id' => $this->operator->id, 'is_active' => true,
        ]);

        // UnitCost's mutator multiplies by 100, so pass dollars to store cents.
        UnitCost::create([
            'product_id' => $product->id,
            'cost' => $costCents / 100,
            'date_from' => '2026-01-01',
            'is_current' => true,
        ]);

        return $product->fresh();
    }

    private function mapping(string $name): ProductMapping
    {
        return ProductMapping::withoutGlobalScopes()->create([
            'name' => $name, 'operator_id' => $this->operator->id, 'is_active' => true,
        ]);
    }

    private function item(ProductMapping $mapping, string $channelCode, Product $product): ProductMappingItem
    {
        return ProductMappingItem::create([
            'product_mapping_id' => $mapping->id,
            'channel_code' => $channelCode,
            'product_id' => $product->id,
        ]);
    }

    /**
     * firstOrCreate, not create — vend_channels carries a unique (vend_id, code)
     * index (the 2026-08-26 dedupe), so a test that puts two sales on one slot
     * must reuse the row exactly as production does.
     */
    private function channel(string $code): VendChannel
    {
        return VendChannel::withoutGlobalScopes()->firstOrCreate(
            ['vend_id' => $this->vend->id, 'code' => $code],
            ['is_active' => true],
        );
    }

    /**
     * A sale as the broken ingest wrote it: no product, no cost, no GST.
     *
     * Each row needs its own order_id — vend_transactions carries a unique
     * (order_id, vend_id) index and every real sale arrives with one.
     */
    private function unattributedSale(VendChannel $channel, int $amount, string $at): VendTransaction
    {
        static $seq = 0;
        $seq++;

        return VendTransaction::withoutGlobalScopes()->create([
            'order_id' => 'TEST-'.$seq,
            'vend_id' => $this->vend->id,
            'operator_id' => $this->operator->id,
            'vend_channel_id' => $channel->id,
            'transaction_datetime' => $at,
            'amount' => $amount,
            'revenue' => $amount,        // GST never extracted
            'gross_profit' => $amount,   // booked at 100% margin
            'gross_profit_margin' => 100,
            'unit_cost' => 0,
            'gst_vat_rate' => 0,
            'product_id' => null,
            'product_mapping_id' => $this->wrong->id,
            'is_refunded' => false,
            'is_zero_amount' => false,
            'is_multiple' => false,
            'settlement_status' => VendTransaction::SETTLEMENT_SETTLED,
        ]);
    }

    private function plan()
    {
        return app(ProductAttributionRepair::class)->plan(
            $this->vend,
            $this->right,
            Carbon::parse('2026-09-17 19:27'),
            Carbon::parse('2026-09-23 10:09')
        );
    }

    public function test_it_reattributes_an_unattributed_sale_with_ingest_arithmetic(): void
    {
        $sale = $this->unattributedSale($this->channel('13'), 180, '2026-09-18 12:00');

        $plan = $this->plan();
        $this->assertCount(1, $plan);

        app(ProductAttributionRepair::class)->apply($plan);
        $sale->refresh();

        // 180 / 1.09 = 165.1 -> 165 ; GP = 165 - 90 = 75
        $this->assertSame($this->rightProduct->id, $sale->product_id);
        $this->assertSame(9.0, (float) $sale->gst_vat_rate);
        $this->assertSame(165, (int) $sale->revenue);
        $this->assertSame(90, (int) $sale->unit_cost);
        $this->assertSame(75, (int) $sale->gross_profit);
        $this->assertSame(180, (int) $sale->amount, 'the amount collected must never move');
    }

    public function test_it_corrects_a_sale_attributed_to_the_wrong_product(): void
    {
        $channel = $this->channel('41');
        $sale = $this->unattributedSale($channel, 180, '2026-09-18 13:00');
        // As the wrong mapping resolved it: real product, wrong one.
        $sale->forceFill([
            'product_id' => $this->wrongProduct->id,
            'unit_cost' => 57, 'gst_vat_rate' => 9, 'revenue' => 165, 'gross_profit' => 108,
        ])->save();

        app(ProductAttributionRepair::class)->apply($this->plan());
        $sale->refresh();

        $this->assertSame($this->rightProduct->id, $sale->product_id);
        $this->assertSame(90, (int) $sale->unit_cost);
        $this->assertSame(75, (int) $sale->gross_profit);
    }

    public function test_it_keeps_the_previous_values_in_meta_for_audit(): void
    {
        $sale = $this->unattributedSale($this->channel('13'), 180, '2026-09-18 12:00');

        app(ProductAttributionRepair::class)->apply($this->plan());

        $repair = $sale->fresh()->meta_json['attribution_repair'];
        $this->assertNull($repair['was_product_id']);
        $this->assertSame(180, $repair['was_gross_profit']);
        $this->assertSame(0, $repair['was_unit_cost']);
        $this->assertSame('13', $repair['channel_code']);
    }

    public function test_a_sale_outside_the_window_is_untouched(): void
    {
        $this->unattributedSale($this->channel('13'), 180, '2026-09-25 12:00');

        $this->assertCount(0, $this->plan());
    }

    public function test_a_refunded_or_pending_sale_is_never_repaired(): void
    {
        $refunded = $this->unattributedSale($this->channel('13'), 180, '2026-09-18 12:00');
        $refunded->forceFill(['is_refunded' => true])->save();

        $pending = $this->unattributedSale($this->channel('13'), 180, '2026-09-18 12:30');
        $pending->forceFill(['settlement_status' => VendTransaction::SETTLEMENT_PENDING])->save();

        $this->assertCount(0, $this->plan());
    }

    public function test_a_multiple_purchase_parent_is_skipped(): void
    {
        $sale = $this->unattributedSale($this->channel('13'), 180, '2026-09-18 12:00');
        $sale->forceFill(['is_multiple' => true])->save();

        $this->assertCount(0, $this->plan());
    }

    /**
     * The dangling-FK cause (~99% of unattributed sales fleet-wide) is out of
     * scope on purpose: with no channel row there is no channel code to resolve.
     */
    public function test_a_sale_whose_channel_row_is_gone_is_left_alone(): void
    {
        $channel = $this->channel('13');
        $this->unattributedSale($channel, 180, '2026-09-18 12:00');
        VendChannel::withoutGlobalScopes()->where('id', $channel->id)->delete();

        $this->assertCount(0, $this->plan());
    }

    public function test_a_channel_the_right_mapping_does_not_cover_is_left_alone(): void
    {
        $this->unattributedSale($this->channel('99'), 180, '2026-09-18 12:00');

        $this->assertCount(0, $this->plan());
    }

    public function test_an_already_correct_sale_is_not_rewritten(): void
    {
        $sale = $this->unattributedSale($this->channel('13'), 180, '2026-09-18 12:00');
        app(ProductAttributionRepair::class)->apply($this->plan());

        $this->assertCount(0, $this->plan(), 'second pass must be a no-op');

        $meta = $sale->fresh()->meta_json['attribution_repair'];
        $this->assertSame(0, $meta['was_unit_cost'], 'the audit trail must not be overwritten by a re-run');
    }

    public function test_it_reports_the_days_it_touched_so_rollups_can_rebuild(): void
    {
        $this->unattributedSale($this->channel('13'), 180, '2026-09-18 12:00');
        $this->unattributedSale($this->channel('13'), 180, '2026-09-19 12:00');

        $result = app(ProductAttributionRepair::class)->apply($this->plan());

        $this->assertSame(2, $result['repaired']);
        $this->assertSame(['2026-09-18', '2026-09-19'], $result['days']);
    }

    public function test_the_command_dry_run_writes_nothing(): void
    {
        $sale = $this->unattributedSale($this->channel('13'), 180, '2026-09-18 12:00');

        $this->artisan('sales:repair-attribution', [
            '--vend' => 2487,
            '--mapping' => $this->right->id,
            '--from' => '2026-09-17 19:27',
            '--to' => '2026-09-23 10:09',
        ])->assertSuccessful();

        $this->assertNull($sale->fresh()->product_id, 'a dry run must not write');
    }

    public function test_the_command_applies_when_asked(): void
    {
        $sale = $this->unattributedSale($this->channel('13'), 180, '2026-09-18 12:00');

        $this->artisan('sales:repair-attribution', [
            '--vend' => 2487,
            '--mapping' => $this->right->id,
            '--from' => '2026-09-17 19:27',
            '--to' => '2026-09-23 10:09',
            '--apply' => true,
        ])->assertSuccessful();

        $this->assertSame($this->rightProduct->id, $sale->fresh()->product_id);
    }
}
