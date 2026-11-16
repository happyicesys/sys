<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Product;
use App\Models\ProductMapping;
use App\Models\ProductMappingItem;
use App\Models\Vend;
use App\Models\VendChannel;
use App\Models\VendProductArrival;
use App\Services\Menu\NewSkuBadge;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Menu NEW badge (Brian, 2026-11-15; no expiry 2026-11-16): a SKU is NEW on a machine once it appears in
 * THAT machine's product mapping after launch, until it leaves it — new to the machine, not to the company. Pinned: the `new_until`
 * key on /thumbnails and /menu rows (absent on every other row), per-machine dates on a shared
 * mapping, no expiry (until the SKU leaves), the "back within 60 days keeps what it had" rule, the deploy-day seed and a
 * menu still served when the table is missing.
 */
class NewSkuBadgeTest extends TestCase
{
    use RefreshDatabase;

    private Product $old;

    private Product $fresh;

    private ProductMapping $mapping;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-11-15 10:00:00'));

        $this->old = Product::create(['code' => 'OLD-1', 'name' => 'Cornetto Classic']);
        $this->fresh = Product::create(['code' => 'NEW-1', 'name' => 'Cornetto Royale']);
        $this->mapping = ProductMapping::forceCreate(['name' => 'Mapping X']);
        ProductMappingItem::create(['product_mapping_id' => $this->mapping->id, 'channel_code' => '11', 'product_id' => $this->old->id]);
    }

    private function makeVend(int $code): Vend
    {
        $customer = Customer::create([
            'name' => "Site {$code}", 'code' => $code, 'operator_id' => 1, 'status_id' => Customer::STATUS_ACTIVE,
        ]);
        $vend = Vend::forceCreate([
            'code' => $code, 'customer_id' => $customer->id, 'product_mapping_id' => $this->mapping->id,
            'operator_id' => 1, 'is_active' => true, 'machine_type' => Vend::MACHINE_TYPE_VENDING_MACHINE,
        ]);
        foreach ([11 => $this->old, 12 => $this->fresh] as $channel => $product) {
            VendChannel::forceCreate([
                'vend_id' => $vend->id, 'code' => $channel, 'product_id' => $product->id,
                'amount' => 250, 'qty' => 5, 'capacity' => 8, 'is_active' => 1,
            ]);
        }

        return $vend;
    }

    private function addFreshToMapping(): void
    {
        ProductMappingItem::create(['product_mapping_id' => $this->mapping->id, 'channel_code' => '12', 'product_id' => $this->fresh->id]);
    }

    /** @return array<string, array<string, mixed>> channel code => row */
    private function thumbnails(Vend $vend): array
    {
        return collect($this->getJson("/api/vends/{$vend->code}/thumbnails")->assertOk()->json())
            ->keyBy(fn ($row) => (string) $row['channel_code'])->all();
    }

    /** @return array<string, array<string, mixed>> */
    private function menu(Vend $vend): array
    {
        return collect($this->getJson("/api/vends/{$vend->code}/menu")->assertOk()->json())
            ->keyBy(fn ($row) => (string) $row['channel_code'])->all();
    }

    public function test_a_sku_added_to_the_mapping_is_new_with_no_expiry_on_both_feeds(): void
    {
        $vend = $this->makeVend(2031);
        $this->thumbnails($vend); // the machine has carried OLD-1 since before this read

        $this->travel(3)->days();
        $this->addFreshToMapping();

        $rows = $this->thumbnails($vend);
        $this->assertSame('2099-12-31T23:59:59+08:00', $rows['12']['new_until']);
        $this->assertArrayNotHasKey('new_until', $rows['11'], 'the first read is the baseline');

        $menu = $this->menu($vend);
        $this->assertSame('2099-12-31T23:59:59+08:00', $menu['12']['new_until']);

        $this->travel(400)->days();
        $this->assertArrayHasKey('new_until', $this->thumbnails($vend)['12'], 'no expiry (Brian, 2026-11-16)');

        ProductMappingItem::where('product_id', $this->fresh->id)->delete();
        $this->assertArrayNotHasKey('12', $this->menu($vend), 'gone from the mapping');
        $this->assertNotNull(VendProductArrival::where('product_id', $this->fresh->id)->value('left_at'));
    }

    public function test_rows_that_are_not_new_carry_no_key_at_all(): void
    {
        $vend = $this->makeVend(2031);
        VendProductArrival::create([
            'vend_id' => $vend->id, 'product_id' => $this->old->id, 'arrived_at' => '2000-01-01',
            'source' => VendProductArrival::SOURCE_SEED,
        ]);

        $this->assertArrayNotHasKey('new_until', $this->thumbnails($vend)['11']);
        $this->assertArrayNotHasKey('new_until', $this->menu($vend)['11']);
    }

    public function test_new_means_new_to_this_machine_on_a_shared_mapping(): void
    {
        $longBound = $this->makeVend(2031);
        $this->addFreshToMapping();
        VendProductArrival::insert([
            ['vend_id' => $longBound->id, 'product_id' => $this->old->id, 'arrived_at' => now()->subDays(200), 'source' => 'seed', 'created_at' => now(), 'updated_at' => now()],
            ['vend_id' => $longBound->id, 'product_id' => $this->fresh->id, 'arrived_at' => '2000-01-01', 'source' => 'seed', 'created_at' => now(), 'updated_at' => now()],
        ]);
        $newlyBound = $this->makeVend(2044);
        // 2044 was on another mapping until today, with another SKU.
        $other = Product::create(['code' => 'OTH-1', 'name' => 'Drink']);
        VendProductArrival::create(['vend_id' => $newlyBound->id, 'product_id' => $other->id, 'arrived_at' => now()->subDays(400), 'source' => 'seed']);

        $this->assertArrayNotHasKey('new_until', $this->thumbnails($longBound)['12']);
        $this->assertArrayHasKey('new_until', $this->thumbnails($newlyBound)['12']);
        $this->assertNotNull(VendProductArrival::where('vend_id', $newlyBound->id)->where('product_id', $other->id)->value('left_at'));
    }

    public function test_a_machine_seen_for_the_first_time_takes_its_mapping_as_the_baseline(): void
    {
        $vend = $this->makeVend(2031);
        $this->addFreshToMapping();

        $rows = $this->thumbnails($vend);
        $this->assertArrayNotHasKey('new_until', $rows['11']);
        $this->assertArrayNotHasKey('new_until', $rows['12']);
        $this->assertSame(2, VendProductArrival::where('vend_id', $vend->id)->where('source', 'seed')->count());
    }

    public function test_back_within_60_days_is_not_new_but_after_longer_it_arrives_again(): void
    {
        $vend = $this->makeVend(2031);
        $row = VendProductArrival::create([
            'vend_id' => $vend->id, 'product_id' => $this->old->id, 'arrived_at' => '2000-01-01',
            'source' => 'seed',
        ]);
        ProductMappingItem::where('product_id', $this->old->id)->delete();
        NewSkuBadge::sync($vend);
        $this->assertTrue($row->fresh()->left_at->eq(now()), 'the departure is seen');

        $this->travel(40)->days();
        ProductMappingItem::create(['product_mapping_id' => $this->mapping->id, 'channel_code' => '11', 'product_id' => $this->old->id]);
        NewSkuBadge::sync($vend);
        $this->assertNull($row->fresh()->left_at);
        $this->assertSame(VendProductArrival::SOURCE_SEED, $row->fresh()->source, 'gone 40 days: still old');
        $this->assertArrayNotHasKey('new_until', $this->thumbnails($vend)['11']);

        ProductMappingItem::where('product_id', $this->old->id)->delete();
        NewSkuBadge::sync($vend);
        $this->travel(61)->days();
        ProductMappingItem::create(['product_mapping_id' => $this->mapping->id, 'channel_code' => '11', 'product_id' => $this->old->id]);
        NewSkuBadge::sync($vend);
        $this->assertTrue($row->fresh()->arrived_at->eq(now()));
        $this->assertSame(VendProductArrival::SOURCE_RETURNED, $row->fresh()->source);
        $this->assertArrayHasKey('new_until', $this->thumbnails($vend)['11']);
    }

    public function test_an_unread_machine_does_not_turn_new_again(): void
    {
        $vend = $this->makeVend(2031);
        VendProductArrival::create([
            'vend_id' => $vend->id, 'product_id' => $this->old->id, 'arrived_at' => '2000-01-01', 'source' => 'seed',
        ]);
        $this->travel(200)->days(); // no menu read and no scan in between

        $this->assertArrayNotHasKey('new_until', $this->thumbnails($vend)['11']);
    }

    public function test_the_migration_seeds_everything_on_a_machine_today_as_old(): void
    {
        $vend = $this->makeVend(2031);
        Schema::drop('vend_product_arrivals');
        (require base_path('database/migrations/2026_11_15_120000_create_vend_product_arrivals_table.php'))->up();

        $row = VendProductArrival::where('vend_id', $vend->id)->sole();
        $this->assertSame(VendProductArrival::SOURCE_SEED, $row->source);
        $this->assertSame('2000-01-01', $row->arrived_at->toDateString());
        $this->assertArrayNotHasKey('new_until', $this->thumbnails($vend)['11']);
    }

    public function test_a_missing_table_still_serves_the_menu_without_badges(): void
    {
        $vend = $this->makeVend(2031);
        Schema::drop('vend_product_arrivals');

        $this->assertArrayNotHasKey('new_until', $this->thumbnails($vend)['11']);
        $this->assertArrayNotHasKey('new_until', $this->menu($vend)['11']);
    }

    public function test_hourly_scan_records_arrivals_for_machines_that_did_not_read_their_menu(): void
    {
        $vend = $this->makeVend(2031);
        $this->artisan('menu:track-new-skus')->assertSuccessful();

        $this->assertSame(1, VendProductArrival::where('vend_id', $vend->id)->count());
    }
}
