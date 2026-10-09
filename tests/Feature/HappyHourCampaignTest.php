<?php

namespace Tests\Feature;

use App\Jobs\NudgeFreezerMenu;
use App\Models\Customer;
use App\Models\HappyHourCampaign;
use App\Models\HappyHourSlot;
use App\Models\Product;
use App\Models\ProductMapping;
use App\Models\ProductMappingItem;
use App\Models\SellingPrice;
use App\Models\User;
use App\Models\Vend;
use App\Models\VendTransaction;
use App\Services\HappyHour\HappyHourPlanner;
use App\Services\HappyHour\HappyHourPricing;
use App\Services\HappyHour\HappyHourRanker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Happy Hour campaigns: ranking (days of cover, ties by qty), exclusions, the planned schedule
 * (slot duration, rank order, sold-out swap and early end), slot results, the freezer menu field,
 * the AI-charge price and the campaign page.
 */
class HappyHourCampaignTest extends TestCase
{
    use RefreshDatabase;

    private Vend $vend;

    /** @var array<string, Product> */
    private array $products = [];

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        Carbon::setTestNow(Carbon::parse('2026-10-09 13:55:00')); // a Friday
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /**
     * A freezer on app 30 at a Site priced in tier 2, with SKUs given as code => [qty, capacity, price $, sold in 14 days].
     *
     * @param  array<string, array{0: int, 1: int, 2: float, 3: int}>  $skus
     */
    private function freezer(array $skus, int $apk = 30): Vend
    {
        $customer = Customer::create([
            'name' => 'Site', 'code' => 'HH1', 'operator_id' => 1,
            'status_id' => Customer::STATUS_ACTIVE, 'selling_price_type' => SellingPrice::TYPE_2,
        ]);
        $mapping = ProductMapping::create([
            'name' => 'Freezer', 'machine_type' => Vend::MACHINE_TYPE_SMART_FREEZER, 'is_smart' => true, 'is_active' => true, 'operator_id' => 1,
        ]);
        $vend = new Vend;
        $vend->forceFill([
            'code' => 50001, 'machine_type' => Vend::MACHINE_TYPE_SMART_FREEZER, 'is_active' => 1, 'operator_id' => 1,
            'vend_model_id' => 1, 'product_mapping_id' => $mapping->id, 'customer_id' => $customer->id,
            'apk_version_code' => $apk,
        ])->save();

        $i = 0;
        foreach ($skus as $code => [$qty, $capacity, $price, $sold]) {
            $product = Product::forceCreate(['code' => $code, 'name' => "Treat {$code}", 'operator_id' => 1]);
            $this->products[$code] = $product;
            SellingPrice::create(['product_id' => $product->id, 'type' => SellingPrice::TYPE_2, 'amount' => $price]);
            $channel = 11 + 10 * $i++;
            ProductMappingItem::create(['product_mapping_id' => $mapping->id, 'channel_code' => (string) $channel, 'product_id' => $product->id]);
            DB::table('vend_channels')->insert([
                'vend_id' => $vend->id, 'code' => $channel, 'product_id' => $product->id, 'amount' => (int) round($price * 100),
                'qty' => $qty, 'capacity' => $capacity, 'is_active' => 1,
            ]);
            // The machine has records from 30 days ago, so the lookback spans the full 14 days.
            DB::table('vend_product_records')->insert([
                ['vend_id' => $vend->id, 'product_id' => $product->id, 'date' => now()->subDays(30)->toDateString(), 'total_count' => 0],
                ['vend_id' => $vend->id, 'product_id' => $product->id, 'date' => now()->subDays(3)->toDateString(), 'total_count' => $sold],
            ]);
        }

        return $this->vend = $vend;
    }

    /** @param  array<string, mixed>  $overrides */
    private function campaign(array $overrides = []): HappyHourCampaign
    {
        $campaign = HappyHourCampaign::create(array_merge([
            'name' => 'Clear slow movers', 'is_active' => true, 'days_mask' => HappyHourCampaign::EVERY_DAY,
            'window_start' => '14:00:00', 'window_end' => '18:00:00', 'slot_minutes' => 60, 'sku_count' => 2,
            'selection_rule' => HappyHourCampaign::RULE_DAYS_OF_COVER, 'lookback_days' => 14, 'discount_pct' => 40,
            'min_balance_pct' => 15, 'min_qty' => 2, 'price_step_cents' => 10, 'allow_below_cost' => false,
        ], $overrides));
        $campaign->vends()->attach($this->vend->id);

        return $campaign;
    }

    private function planner(): HappyHourPlanner
    {
        return app(HappyHourPlanner::class);
    }

    public function test_promo_price_rounds_down_to_the_price_step_and_never_reaches_zero_or_the_original(): void
    {
        $this->assertSame(240, HappyHourPricing::promoPrice(400, 40, 10));
        $this->assertSame(160, HappyHourPricing::promoPrice(280, 40, 10), '$1.68 rounds down to $1.60');
        $this->assertSame(168, HappyHourPricing::promoPrice(280, 40, 1));
        $this->assertNull(HappyHourPricing::promoPrice(11, 40, 10), '6.6¢ rounds to 0: no promo');
        $this->assertSame(90, HappyHourPricing::promoPrice(100, 5, 10), '95¢ rounds down to 90¢');
    }

    public function test_days_of_cover_ranks_unsold_stock_first_ties_go_to_the_higher_qty_and_exclusions_say_why(): void
    {
        $this->freezer([
            'FAST' => [6, 10, 4.00, 28],   // 2/day → 3 days of cover
            'SLOW' => [6, 10, 4.00, 7],    // 0.5/day → 12 days
            'IDLE-A' => [3, 4, 2.80, 0],   // never sold → first; tie with IDLE-B broken by qty
            'IDLE-B' => [5, 0, 2.80, 0],   // capacity never measured: the % floor cannot apply
            'LOW' => [1, 2, 2.80, 0],      // under the 2-unit minimum
            'FLOOR' => [2, 20, 2.80, 0],   // 10% ≤ 15% floor
            'FREE' => [9, 10, 0.11, 0],    // 40% of 11¢ rounds to nothing
        ]);
        $campaign = $this->campaign();

        $ranked = collect(app(HappyHourRanker::class)->rank($campaign, $this->vend, now()))->keyBy(fn ($c) => $this->codeOf($c->productId));

        $this->assertSame(['IDLE-B', 'IDLE-A', 'SLOW', 'FAST'], collect(app(HappyHourRanker::class)->lineup($campaign, $this->vend, now()))
            ->map(fn ($c) => $this->codeOf($c->productId))->all());
        $this->assertNull($ranked['IDLE-B']->capacity);
        $this->assertSame(12.0, $ranked['SLOW']->daysOfCover());
        $this->assertSame(240, $ranked['SLOW']->promoPrice);
        $this->assertStringContainsString('minimum 2', $ranked['LOW']->excludedReason);
        $this->assertStringContainsString('15% floor', $ranked['FLOOR']->excludedReason);
        $this->assertSame('The discount does not change this price', $ranked['FREE']->excludedReason);
    }

    public function test_promo_below_unit_cost_is_left_out_unless_allowed(): void
    {
        $this->freezer(['COSTLY' => [5, 10, 2.00, 0]]);
        DB::table('unit_costs')->insert(['product_id' => $this->products['COSTLY']->id, 'cost' => 150, 'is_current' => 1, 'date_from' => now()->subMonth()]);

        $campaign = $this->campaign();
        $this->assertStringContainsString('below unit cost $1.50', app(HappyHourRanker::class)->rank($campaign, $this->vend, now())[0]->excludedReason);

        $campaign->update(['allow_below_cost' => true]);
        $this->assertTrue(app(HappyHourRanker::class)->rank($campaign, $this->vend, now())[0]->isEligible());
    }

    public function test_the_day_is_planned_before_the_window_one_sku_per_slot_in_rank_order_cycling(): void
    {
        $this->freezer(['A' => [5, 10, 4.00, 0], 'B' => [4, 10, 4.00, 7], 'C' => [6, 10, 4.00, 28]]);
        $campaign = $this->campaign(['slot_minutes' => 90]);

        Carbon::setTestNow('2026-10-09 13:40:00');
        $this->planner()->run(now());
        $this->assertSame(0, HappyHourSlot::count(), 'too early: the lineup is picked 10 minutes before the window');

        Carbon::setTestNow('2026-10-09 13:55:00');
        $this->planner()->run(now());
        $slots = HappyHourSlot::orderBy('starts_at')->get();
        $this->assertSame(['14:00-15:30', '15:30-17:00', '17:00-18:00'], $slots->map(fn ($s) => $s->starts_at->format('H:i').'-'.$s->ends_at->format('H:i'))->all());
        $this->assertSame(['A', 'B', 'A'], $slots->map(fn ($s) => $this->codeOf($s->product_id))->all(), 'top 2 only, cycling');
        $this->assertSame([400, 240, 40], [$slots[0]->original_price, $slots[0]->promo_price, $slots[0]->discount_pct]);
        Queue::assertPushed(NudgeFreezerMenu::class, fn ($job) => $job->vendId === $this->vend->id);

        $this->planner()->run(now());
        $this->assertSame(3, HappyHourSlot::count(), 'idempotent');
    }

    public function test_a_window_edited_after_todays_slots_ran_is_planned_at_its_new_times(): void
    {
        $this->freezer(['A' => [5, 10, 4.00, 0]]);
        $campaign = $this->campaign(['window_start' => '09:58:00', 'window_end' => '10:18:00', 'slot_minutes' => 10, 'sku_count' => 1]);
        Carbon::setTestNow('2026-10-09 09:50:00');
        $this->planner()->run(now());
        Carbon::setTestNow('2026-10-09 10:20:00');
        $this->planner()->run(now()); // both morning slots end

        Carbon::setTestNow('2026-10-09 10:37:30');
        $campaign->update(['window_start' => '10:38:00', 'window_end' => '18:00:00', 'slot_minutes' => 60]);
        $this->planner()->cancel($campaign, now());
        $this->planner()->planCampaign($campaign->fresh(), now());

        $this->assertSame('10:38', HappyHourSlot::open()->where('ends_at', '>', now())->orderBy('starts_at')->first()->starts_at->format('H:i'));
        $this->assertSame(8, HappyHourSlot::open()->where('ends_at', '>', now())->count());
    }

    public function test_a_price_edit_mid_slot_continues_the_hour_at_the_new_price_from_now(): void
    {
        $this->freezer(['A' => [5, 10, 4.00, 0]]);
        $campaign = $this->campaign(['sku_count' => 1]);
        $this->planner()->run(now());

        Carbon::setTestNow('2026-10-09 14:25:20');
        $campaign->update(['discount_pct' => 50]);
        $this->planner()->cancel($campaign, now());
        $this->planner()->planCampaign($campaign->fresh(), now());

        $live = HappyHourSlot::open()->where('starts_at', '<=', now())->where('ends_at', '>', now())->first();
        $this->assertSame(['14:25', '15:00', 200], [$live->starts_at->format('H:i'), $live->ends_at->format('H:i'), $live->promo_price]);
        $this->planner()->run(now()->addMinute());
        $this->assertSame(1, HappyHourSlot::open()->where('starts_at', '<', '2026-10-09 15:00:00')->count(), 'no duplicate next minute');
    }

    public function test_a_freezer_on_an_older_app_is_never_given_slots(): void
    {
        $this->freezer(['A' => [5, 10, 4.00, 0]], apk: 29);
        $this->campaign();

        $this->planner()->run(now());

        $this->assertSame(0, HappyHourSlot::count());
    }

    public function test_a_slot_whose_sku_no_longer_qualifies_when_it_starts_takes_the_next_ranked_one(): void
    {
        $this->freezer(['A' => [5, 10, 4.00, 0], 'B' => [4, 10, 4.00, 7], 'C' => [6, 10, 4.00, 28]]);
        $this->campaign(['sku_count' => 3]);
        $this->planner()->run(now());
        $this->assertSame('B', $this->codeOf(HappyHourSlot::where('position', 1)->value('product_id')));

        // B sells down to one unit before its slot.
        DB::table('vend_channels')->where('product_id', $this->products['B']->id)->update(['qty' => 1]);
        Carbon::setTestNow('2026-10-09 15:00:00');
        $this->planner()->run(now());

        $slot = HappyHourSlot::where('position', 1)->first();
        // A and C both hold later slots today, so the best-ranked qualifying SKU (A) takes it.
        $this->assertSame('A', $this->codeOf($slot->product_id));
        $this->assertNotNull($slot->checked_at);
    }

    public function test_a_live_slot_ends_early_when_its_sku_sells_out_and_records_its_results(): void
    {
        $this->freezer(['A' => [5, 10, 4.00, 0]]);
        $this->campaign(['sku_count' => 1]);
        $this->planner()->run(now());

        Carbon::setTestNow('2026-10-09 14:20:00');
        $slot = HappyHourSlot::where('position', 0)->first();
        $this->sale($slot->product_id, 240, '2026-10-09 14:10:00');
        $this->sale($slot->product_id, 240, '2026-10-09 14:12:00');
        $this->sale($slot->product_id, 400, '2026-10-09 13:50:00'); // before the slot
        DB::table('vend_channels')->update(['qty' => 0]);

        $this->planner()->run(now());
        $slot->refresh();
        $this->assertSame(HappyHourSlot::STATUS_SOLD_OUT, $slot->status);
        $this->assertSame('14:20', $slot->ends_at->format('H:i'));

        Carbon::setTestNow('2026-10-09 14:21:00');
        $this->planner()->run(now());
        $slot->refresh();
        $this->assertSame([2, 480, 320], [$slot->units_sold, $slot->revenue_cents, $slot->discount_cents]);
    }

    public function test_the_freezer_menu_carries_its_slots_and_the_ai_values_extras_at_the_promo_price(): void
    {
        $this->freezer(['A' => [5, 10, 4.00, 0], 'B' => [4, 10, 2.80, 7]]);
        $this->campaign(['sku_count' => 1]);
        $this->planner()->run(now());

        $rows = collect($this->getJson('/api/vends/50001/menu')->assertOk()->json())->keyBy('product_code');
        $this->assertCount(4, $rows['A']['happy_hour']);
        $this->assertSame([240, 400, 40], [$rows['A']['happy_hour'][0]['price'], $rows['A']['happy_hour'][0]['original_price'], $rows['A']['happy_hour'][0]['discount_pct']]);
        $this->assertSame('2026-10-09T14:00:00+08:00', $rows['A']['happy_hour'][0]['starts_at']);
        $this->assertSame([], $rows['B']['happy_hour']);

        $a = $this->products['A']->id;
        $this->assertSame([$a => 240], HappyHourPricing::promoPricesAt($this->vend->id, [$a], Carbon::parse('2026-10-09 14:30')));
        $this->assertSame([], HappyHourPricing::promoPricesAt($this->vend->id, [$a], Carbon::parse('2026-10-09 13:30')));
    }

    public function test_the_campaign_page_saves_plans_at_once_and_a_rename_leaves_the_live_slot_alone(): void
    {
        $this->freezer(['A' => [5, 10, 4.00, 0], 'B' => [4, 10, 4.00, 7]]);
        $user = User::factory()->create(['operator_id' => 1]);
        $this->actingAs($user)->post('/happy-hour-campaigns', $this->form())->assertForbidden();
        foreach (['read', 'create', 'update', 'delete'] as $action) {
            $user->givePermissionTo(Permission::findOrCreate("{$action} happy-hour-campaigns", 'web'));
        }

        Carbon::setTestNow('2026-10-09 14:30:00');
        $this->actingAs($user)->post('/happy-hour-campaigns', $this->form())->assertSessionHasNoErrors();
        $campaign = HappyHourCampaign::firstOrFail();
        $this->assertSame(4, HappyHourSlot::count(), 'planned on save, live slot included');
        $live = HappyHourSlot::where('position', 0)->first();

        $this->actingAs($user)->post("/happy-hour-campaigns/{$campaign->id}", $this->form(['name' => 'Renamed']))->assertSessionHasNoErrors();
        $this->assertSame(HappyHourSlot::STATUS_SCHEDULED, $live->fresh()->status);

        $this->actingAs($user)->post("/happy-hour-campaigns/{$campaign->id}", $this->form(['discount_pct' => 50]))->assertSessionHasNoErrors();
        $this->assertSame(HappyHourSlot::STATUS_CANCELLED, $live->fresh()->status, 'a price change ends the live slot');
        $this->assertSame(200, HappyHourSlot::open()->where('starts_at', '>', now())->value('promo_price'), 'and re-plans at 50% off');

        // A second campaign may not take the same machine at overlapping times.
        $this->actingAs($user)->post('/happy-hour-campaigns', $this->form(['name' => 'Clash', 'window_start' => '17:00', 'window_end' => '20:00']))
            ->assertSessionHasErrors('vend_ids');
        $this->actingAs($user)->post('/happy-hour-campaigns', $this->form(['name' => 'Evening', 'window_start' => '18:00', 'window_end' => '20:00']))
            ->assertSessionHasNoErrors();

        $this->actingAs($user)->getJson("/happy-hour-campaigns/{$campaign->id}/preview")->assertOk()
            ->assertJsonPath('machines.0.candidates.0.rank', 1);

        $this->actingAs($user)->delete("/happy-hour-campaigns/{$campaign->id}")->assertRedirect();
        $this->assertSame(0, HappyHourSlot::where('happy_hour_campaign_id', $campaign->id)->where('starts_at', '>', now())->count());
        $this->assertSoftDeleted($campaign);
    }

    /** @param  array<string, mixed>  $overrides */
    private function form(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Clear slow movers', 'is_active' => true, 'starts_on' => null, 'ends_on' => null,
            'days_mask' => HappyHourCampaign::EVERY_DAY, 'window_start' => '14:00', 'window_end' => '18:00',
            'slot_minutes' => 60, 'sku_count' => 2, 'selection_rule' => HappyHourCampaign::RULE_DAYS_OF_COVER,
            'lookback_days' => 14, 'discount_pct' => 40, 'min_balance_pct' => 15, 'min_qty' => 2,
            'price_step_cents' => 10, 'allow_below_cost' => false, 'excluded_product_ids' => [],
            'vend_ids' => [$this->vend->id],
        ], $overrides);
    }

    private function sale(int $productId, int $cents, string $at): void
    {
        $id = DB::table('vend_transactions')->insertGetId([
            'vend_id' => $this->vend->id, 'order_id' => 'HH-'.uniqid(), 'transaction_datetime' => $at, 'amount' => $cents,
            'settlement_status' => VendTransaction::SETTLEMENT_SETTLED, 'created_at' => $at, 'updated_at' => $at,
        ]);
        DB::table('vend_transaction_items')->insert([
            'vend_transaction_id' => $id, 'product_id' => $productId, 'unit_price_amount' => $cents, 'created_at' => $at,
        ]);
    }

    private function codeOf(int $productId): string
    {
        return collect($this->products)->search(fn (Product $p) => $p->id === $productId);
    }
}
