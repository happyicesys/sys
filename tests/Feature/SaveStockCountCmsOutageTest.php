<?php

namespace Tests\Feature;

use App\Jobs\SaveStockCount;
use App\Models\Customer;
use App\Models\Product;
use App\Models\ProductMapping;
use App\Models\StockCount;
use App\Models\Vend;
use App\Models\VendChannel;
use App\Services\Stock\StockCountWarehouseQty;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * 2026-09-21: CMS timed out at 23:59 and every SaveStockCount job failed after
 * writing its header but before writing a single item, so the Stock Count
 * Dashboard lost that whole day. A CMS outage may now cost the warehouse qty,
 * never the machine qty.
 */
class SaveStockCountCmsOutageTest extends TestCase
{
    use RefreshDatabase;

    private const DATE = '2026-09-21';

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.cms_url' => 'https://cms.test']);
        Cache::forget(StockCountWarehouseQty::cacheKey(self::DATE));
    }

    private function machine(): array
    {
        $site = Customer::create(['name' => 'SC Site', 'code' => 10771, 'operator_id' => 1, 'status_id' => Customer::STATUS_ACTIVE]);
        $mapping = ProductMapping::create(['name' => 'SC map', 'operator_id' => 1, 'machine_type' => Vend::MACHINE_TYPE_VENDING_MACHINE]);
        $vend = Vend::create(['code' => 9771, 'machine_type' => 'vending_machine', 'is_active' => 1, 'customer_id' => $site->id, 'product_mapping_id' => $mapping->id]);
        $a = Product::create(['code' => 'SCA', 'name' => 'Cone', 'operator_id' => 1]);
        $b = Product::create(['code' => 'SCB', 'name' => 'Bar', 'operator_id' => 1]);
        VendChannel::create(['vend_id' => $vend->id, 'code' => 11, 'qty' => 4, 'capacity' => 10, 'amount' => 200, 'product_id' => $a->id, 'is_active' => 1, 'error_rate_json' => []]);
        VendChannel::create(['vend_id' => $vend->id, 'code' => 12, 'qty' => 7, 'capacity' => 10, 'amount' => 250, 'product_id' => $b->id, 'is_active' => 1, 'error_rate_json' => []]);

        return [$vend, $a, $b];
    }

    private function items(Vend $vend)
    {
        $count = StockCount::where(['vend_id' => $vend->id, 'year' => 2026, 'month' => 9, 'day' => 21])->firstOrFail();

        return $count->stockCountItems()->get()->keyBy('product_id');
    }

    public function test_cms_unreachable_still_saves_machine_qty_with_warehouse_qty_unknown(): void
    {
        [$vend, $a, $b] = $this->machine();
        Http::fake(fn () => throw new ConnectionException('cURL error 28: Operation timed out after 10000 milliseconds'));

        SaveStockCount::dispatchSync(self::DATE, $vend->id);

        $items = $this->items($vend);
        $this->assertCount(2, $items);
        $this->assertSame(4, (int) $items[$a->id]->qty_vend);
        $this->assertSame(7, (int) $items[$b->id]->qty_vend);
        $this->assertNull($items[$a->id]->qty_warehouse);
        $this->assertNull($items[$b->id]->qty_warehouse);
        $this->assertNull(Cache::get(StockCountWarehouseQty::cacheKey(self::DATE)), 'a failure is never cached');
    }

    public function test_cms_error_status_also_leaves_warehouse_qty_unknown(): void
    {
        [$vend, $a] = $this->machine();
        Http::fake(['cms.test/*' => Http::response('Bad gateway', 502)]);

        SaveStockCount::dispatchSync(self::DATE, $vend->id);

        $this->assertSame(4, (int) $this->items($vend)[$a->id]->qty_vend);
        $this->assertNull($this->items($vend)[$a->id]->qty_warehouse);
    }

    public function test_cms_up_stamps_warehouse_qty_and_a_product_cms_lacks_reads_zero(): void
    {
        [$vend, $a, $b] = $this->machine();
        Http::fake(['cms.test/*' => Http::response([['code' => 'SCA', 'qty' => 120], ['code' => 'OTHER', 'qty' => 5]])]);

        SaveStockCount::dispatchSync(self::DATE, $vend->id);

        $items = $this->items($vend);
        $this->assertSame(120, (int) $items[$a->id]->qty_warehouse);
        $this->assertSame(0, (int) $items[$b->id]->qty_warehouse);
        $this->assertSame(4, (int) $items[$a->id]->qty_vend);
    }

    public function test_the_command_fetches_cms_once_for_the_whole_fleet(): void
    {
        [$vend] = $this->machine();
        $site2 = Customer::create(['name' => 'SC Site 2', 'code' => 10772, 'operator_id' => 1, 'status_id' => Customer::STATUS_ACTIVE]);
        $vend2 = Vend::create(['code' => 9772, 'machine_type' => 'vending_machine', 'is_active' => 1, 'customer_id' => $site2->id, 'product_mapping_id' => $vend->product_mapping_id]);
        VendChannel::create(['vend_id' => $vend2->id, 'code' => 11, 'qty' => 2, 'capacity' => 10, 'amount' => 200, 'product_id' => Product::where('code', 'SCA')->value('id'), 'is_active' => 1, 'error_rate_json' => []]);

        $this->travelTo(self::DATE.' 23:59:00');
        Http::fake(['cms.test/*' => Http::response([['code' => 'SCA', 'qty' => 120]])]);

        $this->artisan('save:today-stock-count')->assertSuccessful();

        Http::assertSentCount(1);
        $a = Product::where('code', 'SCA')->value('id');
        $this->assertSame(120, (int) $this->items($vend)[$a]->qty_warehouse);
        $this->assertSame(120, (int) $this->items($vend2)[$a]->qty_warehouse);
        $this->assertSame(2, (int) $this->items($vend2)[$a]->qty_vend);
    }

    public function test_a_job_retries_cms_itself_when_the_command_could_not_prime_it(): void
    {
        [$vend, $a] = $this->machine();
        $calls = 0;
        Http::fake(function () use (&$calls) {
            $calls++;
            if ($calls <= 3) {
                throw new ConnectionException('cURL error 28');
            }

            return Http::response([['code' => 'SCA', 'qty' => 33]]);
        });

        $this->travelTo(self::DATE.' 23:59:00');
        $this->assertFalse(app(StockCountWarehouseQty::class)->prime(self::DATE, 3, 0));

        SaveStockCount::dispatchSync(self::DATE, $vend->id);

        $this->assertSame(4, $calls);
        $this->assertSame(33, (int) $this->items($vend)[$a->id]->qty_warehouse);
    }
}
