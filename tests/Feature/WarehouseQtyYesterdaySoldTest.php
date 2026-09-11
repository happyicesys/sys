<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * "Y'day sold" on both Warehouse Qty pages: yesterday's sold qty, counted the
 * same way as the 7-day average beside it (detailed product count, attempted
 * sales included — SyncAvgSalesQtyProducts' definition, via
 * VendTransactionSalesAggregator::productDayCounts).
 */
class WarehouseQtyYesterdaySoldTest extends TestCase
{
    use RefreshDatabase;

    private function plannerUser(): User
    {
        foreach (['read products', 'update products', 'read product-availability'] as $perm) {
            Permission::findOrCreate($perm, 'web');
        }
        // HIPL must be operator id 1 — OperatorProductFilterScope keys "sees every
        // operator" on id 1 (as in prod), and RefreshDatabase does not reset autoincrement.
        $op = \App\Models\Operator::create(['code' => 'HIPL', 'name' => 'HI SG', 'country_id' => 1]);
        DB::table('operators')->where('id', $op->id)->update(['id' => 1]);
        $u = User::factory()->create(['operator_id' => 1]);
        $u->givePermissionTo(['read products', 'update products', 'read product-availability']);

        return $u;
    }

    /** One single (non-basket) sale of $qty units at $at. */
    private function sale(Product $product, string $at, int $qty, ?int $errorId = null, int $operatorId = 1): void
    {
        static $seq = 0;
        $seq++;

        DB::table('vend_transactions')->insert([
            // uniq_order_id_vend_id: every row needs its own order id.
            'order_id' => 'YSOLD-'.$seq,
            'transaction_datetime' => $at,
            'product_id' => $product->id,
            'vend_channel_id' => 1,
            'vend_id' => 1,
            'operator_id' => $operatorId,
            'qty' => $qty,
            'amount' => 100 * $qty,
            'gst_vat_rate' => 0,
            'vend_channel_error_id' => $errorId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_availability_page_reports_yesterdays_sold_qty_per_product(): void
    {
        $u = $this->plannerUser();
        config(['app.cms_url' => 'https://cms.test']);
        Http::fake(['cms.test/*' => Http::response([['code' => 'Y1', 'qty' => 10], ['code' => 'Y2', 'qty' => 10]])]);

        $sold = Product::create(['code' => 'Y1', 'name' => 'Sold yesterday', 'operator_id' => 1, 'is_available' => 1]);
        Product::create(['code' => 'Y2', 'name' => 'Not sold', 'operator_id' => 1, 'is_available' => 1]);

        $this->sale($sold, now()->subDay()->setTime(9, 0)->toDateTimeString(), 2);
        // A failed dispense still counts — demand planning cares about the attempt,
        // exactly as the 7-day average does.
        $this->sale($sold, now()->subDay()->setTime(18, 0)->toDateTimeString(), 1, 3);
        // Today and the day before yesterday are outside the window.
        $this->sale($sold, now()->setTime(8, 0)->toDateTimeString(), 5);
        $this->sale($sold, now()->subDays(2)->setTime(8, 0)->toDateTimeString(), 7);

        $this->actingAs($u)->get('/products/availability?operators[]=all')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Vend/ProductAvailability')
                ->where('products.data', function ($rows) {
                    $rows = collect($rows);

                    return $rows->firstWhere('code', 'Y1')['yesterday_sold_count'] === 3
                        && $rows->firstWhere('code', 'Y2')['yesterday_sold_count'] === 0;
                }));
    }

    /**
     * A viewer's machine list (users.vends) must not shrink Y'day sold: the
     * 7-day average beside it counts every machine, so Y'day has to as well.
     * Prod 2026-09-10: a product owner listed on one machine read "Y'day 2"
     * under an average of 484.
     */
    public function test_yesterdays_sold_ignores_the_viewers_machine_list(): void
    {
        $u = $this->plannerUser();
        config(['app.cms_url' => 'https://cms.test']);
        Http::fake(['cms.test/*' => Http::response([['code' => 'Y1', 'qty' => 10]])]);

        $sold = Product::create(['code' => 'Y1', 'name' => 'Sold yesterday', 'operator_id' => 1, 'is_available' => 1]);

        // Both sales land on vend 1; the viewer's list holds a DIFFERENT machine.
        $this->sale($sold, now()->subDay()->setTime(9, 0)->toDateTimeString(), 2);
        $this->sale($sold, now()->subDay()->setTime(18, 0)->toDateTimeString(), 3);

        $listedVendId = DB::table('vends')->insertGetId([
            'code' => 99001,
            'name' => 'Listed machine',
            'operator_id' => 1,
            'is_active' => 1,
            'is_disposed' => 0,
            'is_testing' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $u->vends()->attach($listedVendId);
        $this->assertSame([$listedVendId], $u->fresh()->vends->pluck('id')->all());

        $this->actingAs($u->fresh())->get('/products/availability?operators[]=all')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Vend/ProductAvailability')
                ->where('products.data', fn ($rows) => collect($rows)->firstWhere('code', 'Y1')['yesterday_sold_count'] === 5));
    }

    /**
     * Dropping the machine list meant removing OperatorTransactionFilterScope,
     * which also carries the operator boundary, and putting that boundary back
     * by hand. This pins it: another operator's sales never reach Y'day.
     */
    public function test_yesterdays_sold_keeps_the_operator_boundary(): void
    {
        $this->plannerUser(); // creates HIPL as operator 1
        $other = \App\Models\Operator::create(['code' => 'OP2', 'name' => 'Other operator', 'country_id' => 1]);
        $viewer = User::factory()->create(['operator_id' => $other->id]);
        $viewer->givePermissionTo(['read products', 'read product-availability']);

        config(['app.cms_url' => 'https://cms.test']);
        Http::fake(['cms.test/*' => Http::response([['code' => 'O1', 'qty' => 10]])]);

        $product = Product::create(['code' => 'O1', 'name' => 'Shared SKU', 'operator_id' => $other->id, 'is_available' => 1]);

        // Own operator's sale counts; operator 1's sale of the same SKU must not.
        $this->sale($product, now()->subDay()->setTime(9, 0)->toDateTimeString(), 2, null, $other->id);
        $this->sale($product, now()->subDay()->setTime(10, 0)->toDateTimeString(), 40, null, 1);

        $this->actingAs($viewer)->get('/products/availability')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Vend/ProductAvailability')
                ->where('products.data', fn ($rows) => collect($rows)->firstWhere('code', 'O1')['yesterday_sold_count'] === 2));
    }

    public function test_ledger_page_reports_yesterdays_sold_qty_per_product(): void
    {
        $u = $this->plannerUser();
        config(['app.cms_url' => null]);

        $sold = Product::create(['code' => 'L1', 'name' => 'Sold yesterday', 'operator_id' => 1, 'is_available' => 1]);
        Product::create(['code' => 'L2', 'name' => 'Not sold', 'operator_id' => 1, 'is_available' => 1]);

        $this->sale($sold, now()->subDay()->setTime(9, 0)->toDateTimeString(), 4);
        $this->sale($sold, now()->setTime(8, 0)->toDateTimeString(), 5);

        $this->actingAs($u)->get('/products/movements?operators[]=all')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Vend/ProductMovement')
                ->where('products.data', function ($rows) {
                    $rows = collect($rows);

                    return $rows->firstWhere('code', 'L1')['yesterday_sold_count'] === 4
                        && $rows->firstWhere('code', 'L2')['yesterday_sold_count'] === 0;
                }));
    }
}
