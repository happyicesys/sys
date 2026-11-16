<?php

namespace Tests\Feature;

use App\Models\DeliveryPlatformOrder;
use App\Models\DeliveryPlatformRefNumber;
use App\Models\DeliveryProductMappingVend;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Spatie\Permission\Models\Permission;
use Tests\Support\Grab\BuildsGrabStore;
use Tests\TestCase;

/**
 * Grab Performance (Brian, 2026-11-16): each store's sales over a period against the period
 * before, by Grab Stores' rule (the machine on the store now, delivered, less Grab promo), and the
 * Grab Stores row's daily graph (yesterday, today).
 */
class GrabPerformanceTest extends TestCase
{
    use BuildsGrabStore;
    use RefreshDatabase;

    private User $user;

    private int $n = 0;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-11-16 12:00:00'));
        config(['grab.webhook_auth' => 'off', 'grab.store_api_spacing_ms' => 0]);
        $this->buildGrabOperator();
        $this->grabItem('U-49', 11, 280);
        Queue::fake();
        Http::fake(fn () => Http::response([], 200));

        Permission::findOrCreate('read delivery-platform-vends', 'web');
        $this->user = User::factory()->create(['operator_id' => $this->grabMapping->operator_id]);
        $this->user->givePermissionTo('read delivery-platform-vends');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function store(int $vendCode, string $merchantId, int $daysOnMachine): DeliveryProductMappingVend
    {
        $binding = $this->bindGrabStore($vendCode, $merchantId, [11 => ['qty' => 5, 'capacity' => 10]]);
        $binding->forceFill(['start_date' => now()->subDays($daysOnMachine)])->save();

        return $binding->fresh();
    }

    private function order(DeliveryProductMappingVend $binding, Carbon $at, int $cents, int $promo = 0, int $status = DeliveryPlatformOrder::STATUS_DELIVERED): void
    {
        $this->n++;
        DeliveryPlatformOrder::withoutGlobalScopes()->forceCreate([
            'delivery_platform_id' => $this->grabOperator->delivery_platform_id,
            'delivery_platform_operator_id' => $this->grabOperator->id,
            'order_id' => "PERF-{$this->n}",
            'short_order_id' => "GF-{$this->n}",
            'order_created_at' => $at,
            'platform_ref_id' => $binding->platform_ref_id,
            'vend_code' => $binding->vend_code,
            'delivery_product_mapping_vend_id' => $binding->id,
            'status' => $status,
            'subtotal_amount' => $cents,
            'promo_amount' => $promo,
        ]);
    }

    public function test_stores_are_ranked_by_sales_against_the_period_before_and_flagged(): void
    {
        $busy = $this->store(2868, '4-BUSY', 100);
        $quiet = $this->store(2867, '4-QUIET', 100);
        $fresh = $this->store(2869, '4-FRESH', 5);
        DeliveryPlatformRefNumber::forceCreate([
            'delivery_platform_id' => $this->grabOperator->delivery_platform_id,
            'operator_id' => $this->grabMapping->operator_id,
            'ref_number' => '4-SPARE',
        ]);

        $this->order($busy, now()->subHour(), 1000);                                        // today
        $this->order($busy, now()->subDays(3), 2000, 200);                                   // less promo
        $this->order($busy, now()->subDays(2), 9900, 0, DeliveryPlatformOrder::STATUS_CANCELLED); // never a sale
        $this->order($busy, now()->subDays(40), 6000);                                       // the period before
        $this->order($busy, now()->subDays(70), 9900);                                       // older than both
        $this->order($fresh, now()->subDay(), 700);                                          // yesterday

        $this->actingAs($this->user)->get('/grab-performance?days=30')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('GrabPerformance/Index')
                ->where('report.days', 30)
                ->where('report.from', '2026-10-18')
                ->where('report.previous_from', '2026-09-18')
                ->has('report.stores', 3) // the spare store has no machine and no sale
                ->where('report.stores.0.ref_number', '4-BUSY')
                ->where('report.stores.0.sales', 2800)
                ->where('report.stores.0.orders', 2)
                ->where('report.stores.0.average_order', 1400)
                ->where('report.stores.0.previous_sales', 6000)
                ->where('report.stores.0.change_pct', -53.3)
                ->where('report.stores.0.days_with_orders', 2)
                ->where('report.stores.0.flag', 'dropping')
                ->has('report.stores.0.daily', 30)
                ->where('report.stores.0.daily.29', ['date' => '2026-11-16', 'sales' => 1000, 'orders' => 1])
                ->where('report.stores.1.ref_number', '4-FRESH')
                ->where('report.stores.1.flag', 'new')
                ->where('report.stores.1.change_pct', null)
                ->where('report.stores.1.binding.days_on_machine', 6)
                ->where('report.stores.2.ref_number', '4-QUIET')
                ->where('report.stores.2.flag', 'no_orders')
                ->where('report.stores.2.last_order_at', null)
                ->where('report.summary.sales', 3500)
                ->where('report.summary.orders', 3)
                ->where('report.summary.previous_sales', 6000)
                ->where('report.summary.stores_selling', 3)
                ->where('report.summary.stores_with_orders', 2)
                ->where('report.summary.stores_no_orders', 1)
                ->where('report.summary.stores_dropping', 1)
                ->where('report.daily.29.sales', 1000)
                ->where('report.daily.28.sales', 700));

        // A period the page does not offer falls back to 30 days; 7 days leaves the 3-day-old order in.
        $this->actingAs($this->user)->get('/grab-performance?days=5')
            ->assertInertia(fn ($page) => $page->where('report.days', 30)->where('filters.days', 30));
        $this->actingAs($this->user)->get('/grab-performance?days=7&codes=2868')
            ->assertInertia(fn ($page) => $page
                ->has('report.stores', 1)
                ->where('report.stores.0.sales', 2800)
                ->where('report.stores.0.previous_sales', 0)
                ->where('report.stores.0.change_pct', null));
    }

    public function test_a_store_counts_only_the_machine_on_it_now(): void
    {
        $binding = $this->store(2868, '4-MOVED', 100);
        $this->order($binding, now()->subDays(2), 1500);
        $binding->forceFill(['end_date' => now()->subDay()])->save();
        $this->store(2869, '4-MOVED', 1);

        $this->actingAs($this->user)->get('/grab-performance')
            ->assertInertia(fn ($page) => $page
                ->where('report.stores.0.ref_number', '4-MOVED')
                ->where('report.stores.0.sales', 0)
                ->where('report.stores.0.binding.vend_code', 2869)
                ->where('report.stores.0.flag', 'new'));
    }

    public function test_the_grab_stores_row_carries_yesterday_and_today_for_its_daily_graph(): void
    {
        $binding = $this->store(2868, '4-DAILY', 100);
        $this->order($binding, now()->subDay(), 700);
        $this->order($binding, now()->subDay()->subHour(), 300, 100);
        $this->order($binding, now()->subHour(), 450);

        $this->user->givePermissionTo(Permission::findOrCreate('update delivery-platform-vends', 'web'));
        $this->actingAs($this->user)->get('/grab-stores?search=4-DAILY')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('stores.data.0.sales_yesterday', 900)
                ->where('stores.data.0.sales_yesterday_orders', 2)
                ->where('stores.data.0.sales_today', 450)
                ->where('stores.data.0.sales_today_orders', 1)
                ->where('stores.data.0.sales_7d', 1350));
    }
}
