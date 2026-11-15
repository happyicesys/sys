<?php

namespace Tests\Feature;

use App\Console\Commands\BackfillNewSkuArrivals;
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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Menu NEW badge (Brian, 2026-11-15): a SKU is NEW on a machine for 60 days after it first appears in
 * THAT machine's product mapping — new to the machine, not to the company. Pinned: the `new_until`
 * key on /thumbnails and /menu rows (absent on every other row), per-machine dates on a shared
 * mapping, the 60-day expiry, the "back within 60 days is not new" rule, the deploy-day seed, the
 * history backfill, and a menu still served when the table is missing.
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

    public function test_a_sku_added_to_the_mapping_is_new_for_60_days_on_both_feeds(): void
    {
        $vend = $this->makeVend(2031);
        $this->thumbnails($vend); // the machine has carried OLD-1 since before this read

        $this->travel(3)->days();
        $this->addFreshToMapping();

        $rows = $this->thumbnails($vend);
        $this->assertSame('2027-01-17T10:00:00+08:00', $rows['12']['new_until']);
        $this->assertArrayNotHasKey('new_until', $rows['11'], 'the first read is the baseline');

        $menu = $this->menu($vend);
        $this->assertSame('2027-01-17T10:00:00+08:00', $menu['12']['new_until']);

        $this->travelTo(CarbonImmutable::parse('2027-01-17 10:00:01'));
        $this->assertArrayNotHasKey('new_until', $this->thumbnails($vend)['12'], 'expired after 60 days');
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
            ['vend_id' => $longBound->id, 'product_id' => $this->fresh->id, 'arrived_at' => now()->subDays(90), 'source' => 'mapping', 'created_at' => now(), 'updated_at' => now()],
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
            'vend_id' => $vend->id, 'product_id' => $this->old->id, 'arrived_at' => now()->subDays(300),
            'source' => 'mapping',
        ]);
        ProductMappingItem::where('product_id', $this->old->id)->delete();
        NewSkuBadge::sync($vend);
        $this->assertTrue($row->fresh()->left_at->eq(now()), 'the departure is seen');

        $this->travel(40)->days();
        ProductMappingItem::create(['product_mapping_id' => $this->mapping->id, 'channel_code' => '11', 'product_id' => $this->old->id]);
        NewSkuBadge::sync($vend);
        $this->assertNull($row->fresh()->left_at);
        $this->assertTrue($row->fresh()->arrived_at->lt(now()->subDays(300)), 'gone 40 days: still the old arrival');
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
            'vend_id' => $vend->id, 'product_id' => $this->old->id, 'arrived_at' => now()->subDays(10), 'source' => 'mapping',
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

    public function test_backfill_dates_seeded_skus_from_refill_visits(): void
    {
        $vend = $this->makeVend(2031);
        $this->addFreshToMapping();
        foreach ([$this->old, $this->fresh] as $product) {
            VendProductArrival::create([
                'vend_id' => $vend->id, 'product_id' => $product->id, 'arrived_at' => '2000-01-01',
                'source' => VendProductArrival::SOURCE_SEED,
            ]);
        }
        $this->visit($vend, '2026-07-01 09:00:00', [$this->old->id]);
        $this->visit($vend, '2026-10-20 09:00:00', [$this->old->id, $this->fresh->id]);
        $this->visit($vend, '2026-11-10 09:00:00', [$this->old->id, $this->fresh->id]);

        $this->artisan('menu:backfill-new-skus')->assertSuccessful();
        $this->assertSame(2, VendProductArrival::where('source', 'seed')->count(), 'dry run writes nothing');

        $this->artisan('menu:backfill-new-skus --apply')->assertSuccessful();
        $old = VendProductArrival::where('product_id', $this->old->id)->sole();
        $fresh = VendProductArrival::where('product_id', $this->fresh->id)->sole();
        $this->assertSame('2026-07-01', $old->arrived_at->toDateString());
        $this->assertSame('2026-10-20', $fresh->arrived_at->toDateString());
        $this->assertSame('2026-12-19T09:00:00+08:00', $this->thumbnails($vend)['12']['new_until']);
        $this->assertArrayNotHasKey('new_until', $this->thumbnails($vend)['11']);
    }

    public function test_a_short_absence_between_visits_keeps_the_first_arrival(): void
    {
        $vend = $this->makeVend(2031);
        $this->visit($vend, '2026-06-01 09:00:00', [$this->old->id]);
        $this->visit($vend, '2026-09-01 09:00:00', []);          // out for a visit
        $this->visit($vend, '2026-09-20 09:00:00', [$this->old->id]); // back 19 days later
        $this->visit($vend, '2026-10-01 09:00:00', [$this->fresh->id]); // out again…
        $this->visit($vend, '2026-10-30 09:00:00', [$this->old->id]); // …back 29 days later

        $arrivals = BackfillNewSkuArrivals::arrivalsFromVisits($vend->id, [$this->old->id], CarbonImmutable::now());
        $this->assertSame('2026-06-01', $arrivals[$this->old->id]->toDateString());

        $this->visit($vend, '2026-11-12 09:00:00', [$this->fresh->id]); // out since 3 days
        $arrivals = BackfillNewSkuArrivals::arrivalsFromVisits($vend->id, [$this->old->id], CarbonImmutable::now());
        $this->assertSame('2026-06-01', $arrivals[$this->old->id]->toDateString());
    }

    public function test_a_long_absence_starts_a_new_stay(): void
    {
        $vend = $this->makeVend(2031);
        $this->visit($vend, '2026-05-01 09:00:00', [$this->old->id]);
        $this->visit($vend, '2026-06-01 09:00:00', [$this->fresh->id]);
        $this->visit($vend, '2026-10-01 09:00:00', [$this->old->id]); // back after 122 days

        $arrivals = BackfillNewSkuArrivals::arrivalsFromVisits($vend->id, [$this->old->id], CarbonImmutable::now());
        $this->assertSame('2026-10-01', $arrivals[$this->old->id]->toDateString());
    }

    public function test_hourly_scan_records_arrivals_for_machines_that_did_not_read_their_menu(): void
    {
        $vend = $this->makeVend(2031);
        $this->artisan('menu:track-new-skus')->assertSuccessful();

        $this->assertSame(1, VendProductArrival::where('vend_id', $vend->id)->count());
    }

    /** A completed refill visit listing these products in the machine's slots. */
    private function visit(Vend $vend, string $completedAt, array $productIds): void
    {
        $jobId = DB::table('ops_jobs')->insertGetId(['code' => random_int(100000, 999999), 'date' => now()->toDateString(), 'status' => 1, 'operator_id' => 1, 'created_at' => now(), 'updated_at' => now()]);
        $itemId = DB::table('ops_job_items')->insertGetId([
            'ops_job_id' => $jobId, 'vend_id' => $vend->id, 'customer_id' => $vend->customer_id, 'status' => 1,
            'completed_at' => $completedAt, 'created_at' => now(), 'updated_at' => now(),
        ]);
        // Every visit records the machine's slots; an empty list still needs one row to count as a visit.
        $productIds = $productIds === [] ? [Product::create(['code' => 'X-'.$itemId, 'name' => 'Other'])->id] : $productIds;
        foreach (array_values($productIds) as $i => $productId) {
            DB::table('ops_job_item_channels')->insert([
                'ops_job_id' => $jobId, 'ops_job_item_id' => $itemId, 'product_id' => $productId,
                'vend_channel_code' => 11 + $i, 'vend_code' => $vend->code, 'qty' => 0, 'actual_qty' => 3, 'capacity' => 10,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }
}
