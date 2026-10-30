<?php

namespace Tests\Feature;

use App\Jobs\NudgeFreezerMenu;
use App\Models\Customer;
use App\Models\HappyHourCampaign;
use App\Models\HappyHourSlot;
use App\Models\OpsJob;
use App\Models\OpsJobItem;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\ProductMapping;
use App\Models\ProductMappingItem;
use App\Models\SellingPrice;
use App\Models\User;
use App\Models\Vend;
use App\Models\VendChannelError;
use App\Models\VendTransaction;
use App\Services\HappyHour\HappyHourPlanner;
use App\Services\HappyHour\HappyHourPricing;
use App\Services\HappyHour\HappyHourRanker;
use App\Services\HappyHour\HappyHourSales;
use App\Services\VendTransactionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Happy Hour campaigns: ranking (days of cover, balance %, ties by qty), exclusions, the schedule
 * (each slot picked just in time from live stock, top SKUs taking turns, sold-out swap and early end), slot results, the freezer menu field,
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

    public function test_promo_price_rounds_up_to_the_price_step_and_never_reaches_zero_or_the_original(): void
    {
        $this->assertSame(240, HappyHourPricing::promoPrice(400, 40, 10), 'exact: no rounding');
        $this->assertSame(170, HappyHourPricing::promoPrice(280, 40, 10), '$1.68 rounds up to $1.70');
        $this->assertSame(180, HappyHourPricing::promoPrice(250, 30, 10), '30% off $2.50 = $1.75 rounds up to $1.80');
        $this->assertSame(168, HappyHourPricing::promoPrice(280, 40, 1));
        $this->assertSame(169, HappyHourPricing::promoPrice(281, 40, 1), '168.6¢ rounds up to 169¢, not down to 168¢');
        $this->assertSame(150, HappyHourPricing::promoPrice(220, 32, 50), '$1.496 rounds up to $1.50 at 50¢');
        $this->assertSame(20, HappyHourPricing::promoPrice(30, 30, 10), '21¢ would round up to the full 30¢: rounds down to 20¢ instead');
        $this->assertSame(90, HappyHourPricing::promoPrice(100, 5, 10), '95¢ would round up to the full $1: rounds down to 90¢');
        $this->assertSame(10, HappyHourPricing::promoPrice(11, 40, 10), '6.6¢ rounds up to 10¢, still under 11¢');
        $this->assertNull(HappyHourPricing::promoPrice(5, 40, 10), '3¢: 10¢ is over the price and 0¢ is no price');
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
            'FREE' => [9, 10, 0.05, 0],    // 40% off 5¢ has no price: 10¢ is over it, 0¢ is nothing
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

    public function test_a_sku_not_available_in_the_warehouse_is_skipped_unless_the_campaign_allows_it(): void
    {
        // A is the best pick (never sold), but the warehouse has ticked it not Available.
        $this->freezer(['A' => [9, 10, 4.00, 0], 'B' => [9, 10, 3.00, 9]]);
        DB::table('products')->where('id', $this->products['A']->id)->update(['is_available' => 0]);
        $campaign = $this->campaign(['sku_count' => 1]);
        $this->assertTrue($campaign->skip_unavailable, 'on by default');

        $ranked = app(HappyHourRanker::class)->rank($campaign, $this->vend, now());
        $this->assertSame([$this->products['B']->id, $this->products['A']->id], array_map(fn ($c) => $c->productId, $ranked));
        $this->assertSame('Not available in the warehouse', $ranked[1]->excludedReason);

        Carbon::setTestNow('2026-10-09 13:58:00');
        $this->planner()->run(now());
        $this->assertSame($this->products['B']->id, HappyHourSlot::where('position', 0)->value('product_id'));

        $campaign->update(['skip_unavailable' => false]);
        $this->assertTrue(app(HappyHourRanker::class)->rank($campaign, $this->vend, now())[0]->isEligible());
        $this->assertSame($this->products['A']->id, app(HappyHourRanker::class)->rank($campaign, $this->vend, now())[0]->productId);
    }

    /**
     * The bestseller hold (Brian, 2026-10-16). Balance %: A, B and C are full after the visit, D 60%.
     * Sold in the last 7 days: A 30, B 20, C 5, D 0; D also sold 100 ten days ago.
     */
    private function restockedFreezer(): void
    {
        $this->freezer(['A' => [10, 10, 4.00, 30], 'B' => [10, 10, 3.00, 20], 'C' => [9, 10, 3.00, 5], 'D' => [6, 10, 2.00, 0]]);
        DB::table('vend_product_records')->insert(
            ['vend_id' => $this->vend->id, 'product_id' => $this->products['D']->id, 'date' => now()->subDays(10)->toDateString(), 'total_count' => 100],
        );
    }

    /** A stop at this machine, stocked in at $at (null = not stocked in yet). */
    private function stockIn(?string $at, array $overrides = []): OpsJobItem
    {
        $job = OpsJob::create(['code' => 900000 + OpsJob::count(), 'date' => now()->toDateString(), 'status' => 1, 'operator_id' => 1]);

        return OpsJobItem::create(array_merge([
            'ops_job_id' => $job->id, 'vend_id' => $this->vend->id, 'customer_id' => $this->vend->customer_id,
            'status' => $at ? OpsJob::STATUS_DELIVERED : OpsJob::STATUS_PICKED, 'completed_at' => $at,
        ], $overrides));
    }

    /** @return list<int> the eligible product ids, best first */
    private function lineupIds(HappyHourCampaign $campaign): array
    {
        return array_map(fn ($c) => $c->productId, app(HappyHourRanker::class)->lineup($campaign->fresh(), $this->vend, now()));
    }

    public function test_the_top_sellers_are_held_for_x_hours_after_a_stock_in_then_rank_again(): void
    {
        $this->restockedFreezer();
        $campaign = $this->campaign(['selection_rule' => HappyHourCampaign::RULE_BALANCE_PCT, 'bestseller_hold_hours' => 6, 'bestseller_hold_count' => 2]);
        [$a, $b, $c, $d] = [$this->products['A']->id, $this->products['B']->id, $this->products['C']->id, $this->products['D']->id];

        // No Stock In yet: nothing is held.
        $this->assertSame([$a, $b, $c, $d], $this->lineupIds($campaign));

        // Stocked in at 13:00: the top 2 of the last 7 days (A 30, B 20) are left out until 19:00.
        $item = $this->stockIn('2026-10-09 13:00:00');
        $ranked = app(HappyHourRanker::class)->rank($campaign, $this->vend, now());
        $this->assertSame([$c, $d], $this->lineupIds($campaign));
        $this->assertSame('Top 2 seller (#1, 30 sold in 7 days): not discounted until 09 Oct 19:00, 6 h after the Stock In',
            collect($ranked)->firstWhere('productId', $a)->excludedReason);
        $this->assertStringStartsWith('Top 2 seller (#2, 20 sold in 7 days)', collect($ranked)->firstWhere('productId', $b)->excludedReason);

        // The planner picks from what is left.
        Carbon::setTestNow('2026-10-09 13:58:00');
        $this->planner()->run(now());
        $this->assertSame($c, HappyHourSlot::where('position', 0)->value('product_id'));

        // At 13:00 + 6 h the hold is over and they rank again.
        Carbon::setTestNow('2026-10-09 19:00:00');
        $this->assertSame([$a, $b, $c, $d], $this->lineupIds($campaign));

        // Undo Stock In clears completed_at, so the hold lifts with it.
        Carbon::setTestNow('2026-10-09 15:00:00');
        $this->assertSame([$c, $d], $this->lineupIds($campaign));
        $item->update(['completed_at' => null, 'status' => OpsJob::STATUS_PICKED]);
        $this->assertSame([$a, $b, $c, $d], $this->lineupIds($campaign));
    }

    public function test_the_bestseller_hold_needs_a_real_stock_in_inside_the_window(): void
    {
        $this->restockedFreezer();
        $campaign = $this->campaign(['selection_rule' => HappyHourCampaign::RULE_BALANCE_PCT, 'bestseller_hold_hours' => 6, 'bestseller_hold_count' => 1]);
        [$a, $b, $c, $d] = [$this->products['A']->id, $this->products['B']->id, $this->products['C']->id, $this->products['D']->id];
        $all = [$a, $b, $c, $d];

        $this->stockIn(null);
        $this->stockIn('2026-10-09 07:30:00'); // 6 h 25 min ago: the hold is over
        $this->stockIn('2026-10-09 13:00:00', ['status' => OpsJob::STATUS_CANCELLED]);
        $this->stockIn('2026-10-09 13:00:00', ['stock_action_type' => 'return_stock']); // takes goods out
        $this->stockIn('2026-10-09 14:30:00'); // later than now (13:55)
        $this->assertSame($all, $this->lineupIds($campaign), 'none of these is a Stock In within the last 6 h');

        $this->stockIn('2026-10-09 12:00:00', ['stock_action_type' => 'implement_new_mapping']);
        $this->assertSame([$b, $c, $d], $this->lineupIds($campaign), 'a mapping changeover is a Stock In');

        // Off when either number is 0.
        $campaign->update(['bestseller_hold_count' => 0]);
        $this->assertSame($all, $this->lineupIds($campaign));
        $campaign->update(['bestseller_hold_count' => 1, 'bestseller_hold_hours' => 0]);
        $this->assertSame($all, $this->lineupIds($campaign));
    }

    public function test_the_bestseller_hold_counts_units_over_its_own_window_and_only_skus_that_sold(): void
    {
        $this->restockedFreezer();
        $campaign = $this->campaign(['selection_rule' => HappyHourCampaign::RULE_BALANCE_PCT, 'bestseller_hold_hours' => 6, 'bestseller_hold_count' => 4]);
        [$a, $b, $c, $d] = [$this->products['A']->id, $this->products['B']->id, $this->products['C']->id, $this->products['D']->id];
        $this->stockIn('2026-10-09 13:00:00');

        // Top 4 over 7 days: only A, B and C sold, so D is never a bestseller and stays in.
        $this->assertSame([$d], $this->lineupIds($campaign));

        // Over 14 days D's 100 units ten days ago make it the #1 seller.
        $campaign->update(['bestseller_hold_count' => 1, 'bestseller_lookback_days' => 14]);
        $ranked = app(HappyHourRanker::class)->rank($campaign->fresh(), $this->vend, now());
        $this->assertSame([$a, $b, $c], $this->lineupIds($campaign));
        $this->assertStringStartsWith('Top 1 seller (#1, 100 sold in 14 days)', collect($ranked)->firstWhere('productId', $d)->excludedReason);

        // A tie at the cut goes to the higher on-hand qty: B (10) over C (9) at 20 units each.
        DB::table('vend_product_records')->where('product_id', $c)->where('total_count', 5)->update(['total_count' => 20]);
        $campaign->update(['bestseller_hold_count' => 2, 'bestseller_lookback_days' => 7]);
        $this->assertSame([$c, $d], $this->lineupIds($campaign));
    }

    /**
     * The Status box's "Skipped" lines (Brian, 2026-10-17): per live machine, the SKUs not on offer and why, ranked
     * live — the held top sellers first with when they rank again, then the rest; a SKU on offer now never shows.
     */
    public function test_the_skipped_endpoint_lists_held_top_sellers_first_then_the_other_reasons_live(): void
    {
        $this->restockedFreezer();
        // E: one unit left, under the campaign's minimum of 2.
        $e = Product::forceCreate(['code' => 'E', 'name' => 'Treat E', 'operator_id' => 1]);
        SellingPrice::create(['product_id' => $e->id, 'type' => SellingPrice::TYPE_2, 'amount' => 2.00]);
        DB::table('vend_channels')->insert(['vend_id' => $this->vend->id, 'code' => 51, 'product_id' => $e->id, 'amount' => 200, 'qty' => 1, 'capacity' => 10, 'is_active' => 1]);
        $campaign = $this->campaign(['selection_rule' => HappyHourCampaign::RULE_BALANCE_PCT, 'bestseller_hold_hours' => 6, 'bestseller_hold_count' => 2]);
        [$a, $b, $c] = [$this->products['A']->id, $this->products['B']->id, $this->products['C']->id];
        $this->stockIn('2026-10-09 13:00:00');

        $user = User::factory()->create(['operator_id' => 1]);
        $user->givePermissionTo(Permission::findOrCreate('read happy-hour-campaigns', 'web'));

        // Nothing running yet: no machine to report on.
        $this->actingAs($user)->getJson("/happy-hour-campaigns/{$campaign->id}/skipped")->assertOk()->assertExactJson([
            'at' => '2026-10-09 13:55:00', 'machines' => [],
        ]);

        Carbon::setTestNow('2026-10-09 13:58:00');
        $this->planner()->run(now());
        $this->assertSame($c, HappyHourSlot::where('position', 0)->value('product_id'), 'A and B are held, so C (90%) runs');

        Carbon::setTestNow('2026-10-09 14:05:00');
        $list = $this->actingAs($user)->getJson("/happy-hour-campaigns/{$campaign->id}/skipped")->assertOk()
            ->json("machines.{$this->vend->id}");
        $this->assertSame([$a, $b, $e->id], array_column($list, 'product_id'), 'held top sellers first, by place; D is eligible');
        $this->assertSame(['bestseller', 'bestseller', 'min_qty'], array_column($list, 'code'));
        $this->assertSame([1, 30], [$list[0]['hold_place'], $list[0]['hold_sold']]);
        $this->assertSame('2026-10-09T19:00:00+08:00', $list[0]['held_until']);
        $this->assertSame('Only 1 in stock (minimum 2)', $list[2]['reason']);
        $this->assertSame([1, 10], [$list[2]['qty'], $list[2]['capacity']]);

        // Live: E is topped up and C drops to 1 unit mid-slot. Within the 60 s cache nothing moves; after it E is
        // gone, and C keeps its slot, so it is not listed as skipped on the machine offering it.
        DB::table('vend_channels')->where('vend_id', $this->vend->id)->where('product_id', $e->id)->update(['qty' => 5]);
        DB::table('vend_channels')->where('vend_id', $this->vend->id)->where('product_id', $c)->update(['qty' => 1]);
        $this->travel(30)->seconds();
        $this->assertSame([$a, $b, $e->id], array_column($this->actingAs($user)->getJson("/happy-hour-campaigns/{$campaign->id}/skipped")
            ->json("machines.{$this->vend->id}"), 'product_id'));
        $this->travel(31)->seconds();
        $this->assertSame([$a, $b], array_column($this->actingAs($user)->getJson("/happy-hour-campaigns/{$campaign->id}/skipped")
            ->json("machines.{$this->vend->id}"), 'product_id'));
    }

    public function test_a_stock_in_swaps_a_held_bestseller_as_its_slot_starts_but_never_ends_a_live_slot(): void
    {
        $this->restockedFreezer();
        $campaign = $this->campaign(['selection_rule' => HappyHourCampaign::RULE_BALANCE_PCT, 'sku_count' => 1, 'bestseller_hold_hours' => 6, 'bestseller_hold_count' => 1]);
        $a = $this->products['A']->id;

        // 14:00 is picked at 13:58, before the driver arrives: A.
        Carbon::setTestNow('2026-10-09 13:58:00');
        $this->planner()->run(now());
        $slot = HappyHourSlot::where('position', 0)->firstOrFail();
        $this->assertSame($a, $slot->product_id);

        // Stocked in at 13:59: as the slot starts, A no longer qualifies and the next in turn takes over — on a
        // new slot row; A's keeps its id and planned end, cancelled, for a board that missed the nudge.
        Carbon::setTestNow('2026-10-09 13:59:00');
        $this->stockIn('2026-10-09 13:59:00');
        $this->planner()->run(now());
        $this->assertSame($a, $slot->fresh()->product_id);
        $this->assertSame(HappyHourSlot::STATUS_CANCELLED, $slot->fresh()->status);
        $slot = HappyHourSlot::open()->where('position', 0)->sole();
        $this->assertSame($this->products['B']->id, $slot->product_id, 'B: A is held, B is next at 100%');
        $this->assertSame('2026-10-09 14:00:00', $slot->starts_at->toDateTimeString());
        $this->assertSame('2026-10-09 15:00:00', $slot->ends_at->toDateTimeString());

        // A live slot keeps its SKU when a Stock In lands mid-slot (like every exclusion); the next slot follows the hold.
        DB::table('ops_job_items')->delete();
        HappyHourSlot::where('status', HappyHourSlot::STATUS_CANCELLED)->delete();
        HappyHourSlot::whereKey($slot->id)->update(['product_id' => $a]);
        Carbon::setTestNow('2026-10-09 14:10:00');
        $this->stockIn('2026-10-09 14:10:00');
        $this->planner()->run(now());
        $this->assertSame($a, $slot->fresh()->product_id);
        $this->assertSame(HappyHourSlot::STATUS_SCHEDULED, $slot->fresh()->status);

        Carbon::setTestNow('2026-10-09 14:58:00');
        $this->planner()->run(now());
        $this->assertSame($this->products['B']->id, HappyHourSlot::where('position', 1)->value('product_id'), 'A is held from 14:10');
    }

    /** Runs the planner as each of the 14:00–17:00 slots comes up, and once more inside each start minute. */
    private function runAfternoon(): void
    {
        foreach (['13:58', '14:58', '15:58', '16:58'] as $minute) {
            Carbon::setTestNow("2026-10-09 {$minute}:00");
            $this->planner()->run(now());
            $this->planner()->run(now()->addMinute());
        }
    }

    /** @return list<string> "code" or "code*" (priority) per slot at that start, by rank */
    private function slotsAt(string $hhmm): array
    {
        return HappyHourSlot::where('starts_at', "2026-10-09 {$hhmm}:00")->orderBy('rank')->get()
            ->map(fn ($s) => $this->codeOf($s->product_id).($s->is_priority ? '*' : ''))->all();
    }

    public function test_prioritized_skus_run_every_other_slot_take_turns_and_stay_out_of_the_slots_between(): void
    {
        // Balance %: A 100, B 90, C 80; D 30; E 10 with 1 unit — under the 15% floor and the 2-unit minimum.
        $this->freezer(['A' => [10, 10, 4.00, 0], 'B' => [9, 10, 4.00, 0], 'C' => [8, 10, 4.00, 0], 'D' => [3, 10, 4.00, 0], 'E' => [1, 10, 4.00, 0]]);
        $campaign = $this->campaign(['selection_rule' => HappyHourCampaign::RULE_BALANCE_PCT, 'sku_count' => 2,
            'priority_product_ids' => [$this->products['D']->id, $this->products['E']->id]]);

        $ranked = collect(app(HappyHourRanker::class)->rank($campaign, $this->vend, now()))->keyBy('productId');
        $this->assertTrue($ranked[$this->products['E']->id]->isEligible(), 'a prioritized SKU sells down to its last unit');
        $this->assertSame([1, 2], [$ranked[$this->products['D']->id]->rank, $ranked[$this->products['E']->id]->rank], 'prioritized first');

        $this->runAfternoon();
        $this->assertSame(['D*'], $this->slotsAt('14:00'), '1st slot: prioritized');
        $this->assertSame(['A'], $this->slotsAt('15:00'), '2nd: the normal ranking, prioritized SKUs left out');
        $this->assertSame(['E*'], $this->slotsAt('16:00'), '3rd: the next prioritized SKU in turn');
        $this->assertSame(['B'], $this->slotsAt('17:00'), '4th: the normal rotation was not thrown off by the priority slots');
        $this->assertSame(240, HappyHourSlot::where('starts_at', '2026-10-09 14:00:00')->value('promo_price'), "the campaign's 40%");
    }

    public function test_a_priority_slot_tops_up_from_the_normal_ranking_and_falls_back_when_nothing_prioritized_qualifies(): void
    {
        $this->freezer(['A' => [10, 10, 4.00, 0], 'B' => [9, 10, 4.00, 0], 'C' => [8, 10, 4.00, 0], 'D' => [3, 10, 4.00, 0]]);
        $campaign = $this->campaign(['selection_rule' => HappyHourCampaign::RULE_BALANCE_PCT, 'sku_count' => 2, 'skus_per_slot' => 2,
            'priority_product_ids' => [$this->products['D']->id]]);

        Carbon::setTestNow('2026-10-09 13:58:00');
        $this->planner()->run(now());
        $this->assertSame(['D*', 'A'], $this->slotsAt('14:00'), 'one prioritized SKU + the best normal one');

        // The prioritized SKU's promo is under its unit cost: the below-cost rule still applies, so the next
        // priority slot runs the normal ranking.
        DB::table('unit_costs')->insert(['product_id' => $this->products['D']->id, 'cost' => 300, 'is_current' => 1, 'date_from' => now()->subMonth()]);
        Carbon::setTestNow('2026-10-09 14:58:00');
        $this->planner()->run(now());
        $this->assertSame(['A', 'B'], $this->slotsAt('15:00'));
        Carbon::setTestNow('2026-10-09 15:58:00');
        $this->planner()->run(now());
        $this->assertSame(['A', 'B'], $this->slotsAt('16:00'));
        $campaign->update(['allow_below_cost' => true]);
        Carbon::setTestNow('2026-10-09 16:58:00');
        $this->planner()->run(now());
        $this->assertSame(['A', 'B'], $this->slotsAt('17:00'), 'an odd slot never offers it');
    }

    public function test_a_prioritized_sku_skips_the_availability_box_and_the_bestseller_hold_but_never_discount_wins(): void
    {
        $this->freezer(['A' => [10, 10, 4.00, 30], 'B' => [9, 10, 4.00, 20], 'C' => [8, 10, 4.00, 5]]);
        $campaign = $this->campaign(['selection_rule' => HappyHourCampaign::RULE_BALANCE_PCT, 'sku_count' => 1,
            'bestseller_hold_hours' => 6, 'bestseller_hold_count' => 1, 'priority_product_ids' => [$this->products['A']->id]]);
        DB::table('products')->where('id', $this->products['A']->id)->update(['is_available' => 0]);
        $this->stockIn('2026-10-09 13:00:00');

        $ranked = collect(app(HappyHourRanker::class)->rank($campaign, $this->vend, now()))->keyBy('productId');
        $this->assertTrue($ranked[$this->products['A']->id]->isEligible(), 'not Available + top seller after a Stock In, still prioritized');
        $this->assertTrue($ranked[$this->products['A']->id]->priority);
        // The hold's one place goes to the best seller that is not prioritized: B.
        $this->assertStringStartsWith('Top 1 seller (#1, 20 sold', $ranked[$this->products['B']->id]->excludedReason);

        $campaign->update(['excluded_product_ids' => [$this->products['A']->id]]);
        $ranked = collect(app(HappyHourRanker::class)->rank($campaign->fresh(), $this->vend, now()))->keyBy('productId');
        $this->assertSame('Excluded in this campaign', $ranked[$this->products['A']->id]->excludedReason);
        $this->assertFalse($ranked[$this->products['A']->id]->priority);

        // A machine that holds none of the prioritized SKUs runs the normal ranking in every slot.
        $campaign->update(['excluded_product_ids' => null, 'priority_product_ids' => [999999]]);
        $this->runAfternoon();
        $this->assertSame(['B'], $this->slotsAt('14:00'));
        $this->assertSame(0, HappyHourSlot::where('is_priority', true)->count());
    }

    public function test_a_priority_slot_whose_sku_sells_out_is_refilled_from_the_prioritized_list(): void
    {
        $this->freezer(['A' => [10, 10, 4.00, 0], 'D' => [3, 10, 4.00, 0], 'E' => [2, 10, 4.00, 0]]);
        $this->campaign(['selection_rule' => HappyHourCampaign::RULE_BALANCE_PCT, 'sku_count' => 1,
            'priority_product_ids' => [$this->products['D']->id, $this->products['E']->id]]);
        Carbon::setTestNow('2026-10-09 13:58:00');
        $this->planner()->run(now());
        $this->assertSame(['D*'], $this->slotsAt('14:00'));

        DB::table('vend_channels')->where('product_id', $this->products['D']->id)->update(['qty' => 0]);
        Carbon::setTestNow('2026-10-09 14:20:00');
        $this->planner()->run(now());
        $this->assertSame(HappyHourSlot::STATUS_SOLD_OUT, HappyHourSlot::where('product_id', $this->products['D']->id)->value('status'));
        $this->assertSame(['E*'], HappyHourSlot::open()->where('ends_at', '>', now())->get()
            ->map(fn ($s) => $this->codeOf($s->product_id).($s->is_priority ? '*' : ''))->all());
    }

    public function test_each_slot_is_picked_just_before_it_starts_from_live_stock_and_the_top_skus_take_turns(): void
    {
        // Balance %: A 50, B 80, C 60.
        $this->freezer(['A' => [5, 10, 4.00, 0], 'B' => [8, 10, 4.00, 7], 'C' => [6, 10, 4.00, 28]]);
        $this->campaign(['slot_minutes' => 90, 'selection_rule' => HappyHourCampaign::RULE_BALANCE_PCT]);
        $slots = fn () => HappyHourSlot::orderBy('starts_at')->get()
            ->map(fn ($s) => $s->starts_at->format('H:i').'-'.$s->ends_at->format('H:i').' '.$this->codeOf($s->product_id))->all();

        Carbon::setTestNow('2026-10-09 13:57:00');
        $this->planner()->run(now());
        $this->assertSame(0, HappyHourSlot::count(), 'too early: a slot is picked 2 minutes before it starts');

        Carbon::setTestNow('2026-10-09 13:58:00');
        $this->planner()->run(now());
        $this->assertSame(['14:00-15:30 B'], $slots(), 'only the slot coming up, highest balance first');
        $first = HappyHourSlot::firstOrFail();
        $this->assertSame([400, 240, 40, 80.0], [$first->original_price, $first->promo_price, $first->discount_pct, $first->qty_at_pick / $first->capacity_at_pick * 100]);
        Queue::assertPushed(NudgeFreezerMenu::class, fn ($job) => $job->vendId === $this->vend->id);
        $this->planner()->run(now()->addMinute());
        $this->assertSame(1, HappyHourSlot::count(), 'idempotent');

        // B sells down to 20% during its slot: the next pick follows the stock of that moment.
        DB::table('vend_channels')->where('product_id', $this->products['B']->id)->update(['qty' => 2]);
        Carbon::setTestNow('2026-10-09 15:28:00');
        $this->planner()->run(now());
        Carbon::setTestNow('2026-10-09 16:58:00');
        $this->planner()->run(now());

        // Top 2 now are C (60%) and A (50%); C just ran, so A takes its turn.
        $this->assertSame(['14:00-15:30 B', '15:30-17:00 C', '17:00-18:00 A'], $slots());
    }

    public function test_one_sku_count_repeats_the_best_sku_of_each_moment(): void
    {
        $this->freezer(['A' => [5, 10, 4.00, 0], 'B' => [8, 10, 4.00, 7]]);
        $this->campaign(['sku_count' => 1, 'selection_rule' => HappyHourCampaign::RULE_BALANCE_PCT]);

        Carbon::setTestNow('2026-10-09 13:58:00');
        $this->planner()->run(now());
        Carbon::setTestNow('2026-10-09 14:58:00');
        $this->planner()->run(now());
        DB::table('vend_channels')->where('product_id', $this->products['B']->id)->update(['qty' => 4]);
        Carbon::setTestNow('2026-10-09 15:58:00');
        $this->planner()->run(now());

        $this->assertSame(['B', 'B', 'A'], HappyHourSlot::orderBy('starts_at')->pluck('product_id')->map(fn ($id) => $this->codeOf($id))->all());
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
        $this->assertSame(1, HappyHourSlot::open()->where('ends_at', '>', now())->count(), 'later slots are picked as they come up');
        Carbon::setTestNow('2026-10-09 11:36:00');
        $this->planner()->run(now());
        $this->assertSame('11:38', HappyHourSlot::open()->orderByDesc('starts_at')->value('starts_at')->format('H:i'));
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
        Carbon::setTestNow('2026-10-09 13:58:00');
        $this->planner()->run(now());
        Carbon::setTestNow('2026-10-09 14:58:00');
        $this->planner()->run(now());
        $this->assertSame('B', $this->codeOf(HappyHourSlot::where('position', 1)->value('product_id')));

        // B sells down to one unit before its slot.
        DB::table('vend_channels')->where('product_id', $this->products['B']->id)->update(['qty' => 1]);
        Carbon::setTestNow('2026-10-09 15:00:00');
        $this->planner()->run(now());

        // A ran the slot before, so the next qualifying SKU in turn (C) takes it, on a new slot row; B's slot
        // is cancelled.
        $this->assertSame(HappyHourSlot::STATUS_CANCELLED, HappyHourSlot::where('position', 1)->where('product_id', $this->products['B']->id)->value('status'));
        $slot = HappyHourSlot::open()->where('position', 1)->sole();
        $this->assertSame('C', $this->codeOf($slot->product_id));
        $this->assertNotNull($slot->checked_at);
    }

    public function test_a_live_slot_ends_early_when_its_sku_sells_out_and_records_its_results(): void
    {
        $this->freezer(['A' => [5, 10, 4.00, 0]]);
        $this->campaign(['sku_count' => 1]);
        Carbon::setTestNow('2026-10-09 13:58:00');
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
        Carbon::setTestNow('2026-10-09 13:58:00');
        $this->planner()->run(now());

        $rows = collect($this->getJson('/api/vends/50001/menu')->assertOk()->json())->keyBy('product_code');
        $this->assertCount(1, $rows['A']['happy_hour']);
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
        $this->assertSame(1, HappyHourSlot::count(), 'the live slot is planned on save');
        $live = HappyHourSlot::where('position', 0)->first();

        $this->actingAs($user)->post("/happy-hour-campaigns/{$campaign->id}", $this->form(['name' => 'Renamed']))->assertSessionHasNoErrors();
        $this->assertSame(HappyHourSlot::STATUS_SCHEDULED, $live->fresh()->status);

        // Two price edits in the same minute as the slot began: the slot is replaced, never lost.
        $this->actingAs($user)->post("/happy-hour-campaigns/{$campaign->id}", $this->form(['discount_pct' => 45]))->assertSessionHasNoErrors();
        $this->assertNull($live->fresh(), 'a slot under a minute old is replaced');
        $this->assertSame(220, HappyHourSlot::open()->where('ends_at', '>', now())->value('promo_price'));

        Carbon::setTestNow('2026-10-09 14:34:00');
        $live = HappyHourSlot::open()->firstOrFail();
        $this->actingAs($user)->post("/happy-hour-campaigns/{$campaign->id}", $this->form(['discount_pct' => 50]))->assertSessionHasNoErrors();
        $this->assertSame(HappyHourSlot::STATUS_CANCELLED, $live->fresh()->status, 'a price change ends the live slot');
        $this->assertSame(200, HappyHourSlot::open()->where('ends_at', '>', now())->value('promo_price'), 'and re-plans the rest of it at 50% off');

        // A second campaign may not take the same machine at overlapping times.
        $this->actingAs($user)->post('/happy-hour-campaigns', $this->form(['name' => 'Clash', 'window_start' => '17:00', 'window_end' => '20:00']))
            ->assertSessionHasErrors('vend_ids');
        $this->actingAs($user)->post('/happy-hour-campaigns', $this->form(['name' => 'Evening', 'window_start' => '18:00', 'window_end' => '20:00']))
            ->assertSessionHasNoErrors();

        // The machine chips carry what the ⚠ tooltip explains for a machine that cannot run it.
        $this->actingAs($user)->get('/happy-hour-campaigns')->assertOk()
            ->assertInertia(fn ($page) => $page->where('campaigns.0.vends.0.is_active', true)
                ->where('campaigns.0.vends.0.apk_version_code', 30)
                ->where('campaigns.0.vends.0.supported', true));

        // The Status column carries only the slot running now, with the machine's Site + RP and the SKU's
        // stock on hand / capacity (Brian, 2026-10-15).
        $liveSlot = HappyHourSlot::open()->where('starts_at', '<=', now())->where('ends_at', '>', now())->firstOrFail();
        $onHand = DB::table('vend_channels')->where('vend_id', $this->vend->id)->where('product_id', $liveSlot->product_id)->first();
        $this->actingAs($user)->get('/happy-hour-campaigns')->assertOk()
            ->assertInertia(fn ($page) => $page->where('campaigns.1.id', $campaign->id)
                ->has('campaigns.1.live', 1)
                ->where('campaigns.1.live.0.promo_price', 200)
                ->where('campaigns.1.live.0.qty', (int) $onHand->qty)
                ->where('campaigns.1.live.0.capacity', (int) $onHand->capacity)
                ->where('campaigns.1.vends.0.site', 'Site')
                ->where('campaigns.1.vends.0.customer_id', $this->vend->customer_id)
                ->where('campaigns.1.vends.0.rp', 'RP2')
                ->where('campaigns.1.vends.0.board_price', false)
                ->has('campaigns.0.live', 0));

        // A machine chip ranks just that machine.
        $this->actingAs($user)->getJson("/happy-hour-campaigns/{$campaign->id}/preview?vend_id={$this->vend->id}")->assertOk()
            ->assertJsonCount(1, 'machines')
            ->assertJsonPath('machines.0.candidates.0.rank', 1);
        $this->actingAs($user)->getJson("/happy-hour-campaigns/{$campaign->id}/preview?vend_id=999999")->assertOk()
            ->assertJsonCount(0, 'machines');

        // The bestseller hold is off by default, saves, and takes a 7- or 14-day window only.
        $this->assertSame([0, 0, 7], [$campaign->fresh()->bestseller_hold_hours, $campaign->fresh()->bestseller_hold_count, $campaign->fresh()->bestseller_lookback_days]);
        $this->actingAs($user)->post("/happy-hour-campaigns/{$campaign->id}", $this->form(['bestseller_hold_hours' => 6, 'bestseller_hold_count' => 3]))->assertSessionHasNoErrors();
        $this->assertSame([6, 3, 7], [$campaign->fresh()->bestseller_hold_hours, $campaign->fresh()->bestseller_hold_count, $campaign->fresh()->bestseller_lookback_days]);
        $this->actingAs($user)->post("/happy-hour-campaigns/{$campaign->id}", $this->form(['bestseller_lookback_days' => 30]))->assertSessionHasErrors('bestseller_lookback_days');
        $this->actingAs($user)->post("/happy-hour-campaigns/{$campaign->id}", $this->form(['bestseller_lookback_days' => 14]))->assertSessionHasNoErrors();
        $this->assertSame([6, 3, 14], [$campaign->fresh()->bestseller_hold_hours, $campaign->fresh()->bestseller_hold_count, $campaign->fresh()->bestseller_lookback_days], 'absent fields keep their value');
        $this->actingAs($user)->post("/happy-hour-campaigns/{$campaign->id}", $this->form(['bestseller_hold_hours' => 0, 'bestseller_lookback_days' => 7]))->assertSessionHasNoErrors();

        // Prioritize saves, an old tab without the field keeps it, and a SKU on both lists is refused.
        [$a, $b] = [$this->products['A']->id, $this->products['B']->id];
        $this->actingAs($user)->post("/happy-hour-campaigns/{$campaign->id}", $this->form(['priority_product_ids' => [$a]]))->assertSessionHasNoErrors();
        $this->assertSame([$a], $campaign->fresh()->priorityProductIds());
        $this->actingAs($user)->post("/happy-hour-campaigns/{$campaign->id}", $this->form())->assertSessionHasNoErrors();
        $this->assertSame([$a], $campaign->fresh()->priorityProductIds());
        $this->actingAs($user)->post("/happy-hour-campaigns/{$campaign->id}", $this->form(['priority_product_ids' => [$a, $b], 'excluded_product_ids' => [$b]]))
            ->assertSessionHasErrors(['priority_product_ids' => 'A SKU cannot be both prioritized and never discounted: B.']);
        $this->actingAs($user)->post("/happy-hour-campaigns/{$campaign->id}", $this->form(['priority_product_ids' => []]))->assertSessionHasNoErrors();
        $this->assertSame([], $campaign->fresh()->priorityProductIds());

        // The warehouse-availability box saves both ways.
        $this->assertTrue($campaign->fresh()->skip_unavailable);
        $this->actingAs($user)->post("/happy-hour-campaigns/{$campaign->id}", $this->form(['discount_pct' => 50, 'skip_unavailable' => false]))->assertSessionHasNoErrors();
        $this->assertFalse($campaign->fresh()->skip_unavailable);

        $this->actingAs($user)->delete("/happy-hour-campaigns/{$campaign->id}")->assertRedirect();
        $this->assertSame(0, HappyHourSlot::where('happy_hour_campaign_id', $campaign->id)->where('starts_at', '>', now())->count());
        $this->assertSoftDeleted($campaign);
    }

    public function test_a_sale_at_the_promo_price_inside_its_slot_is_tagged_and_counted(): void
    {
        $this->freezer(['A' => [9, 10, 4.00, 0], 'B' => [9, 10, 3.00, 9]]);
        $this->campaign(['sku_count' => 1]);
        Carbon::setTestNow('2026-10-09 13:58:00');
        $this->planner()->run(now());
        $slot = HappyHourSlot::where('position', 0)->firstOrFail();
        $a = $this->products['A']->id;
        $b = $this->products['B']->id;

        $promo = $this->sale($a, 240, '2026-10-09 14:10:00');
        $full = $this->sale($a, 400, '2026-10-09 14:11:00');             // full price: not Happy Hour
        $other = $this->sale($b, 300, '2026-10-09 14:12:00');            // another SKU
        $late = $this->sale($a, 240, '2026-10-09 15:03:00');             // cart from the slot's last minute, paid after it
        $stray = $this->sale($a, 240, '2026-10-09 13:40:00');            // same cents, but long before the slot
        $this->assertSame(400, (int) $promo->fresh()->happy_hour_list_price, "the slot's price, frozen with the tag");
        $basket = $this->basket([[$a, 240], [$b, 300]], '2026-10-09 14:20:00');

        $this->assertSame($slot->id, $promo->fresh()->happy_hour_slot_id);
        $this->assertNull($full->fresh()->happy_hour_slot_id);
        $this->assertNull($other->fresh()->happy_hour_slot_id);
        $this->assertSame($slot->id, $late->fresh()->happy_hour_slot_id);
        $this->assertNull($stray->fresh()->happy_hour_slot_id);
        $this->assertSame($slot->id, $basket->fresh()->happy_hour_slot_id, 'the header says the basket holds a promo unit');
        $this->assertSame([$slot->id, null], DB::table('vend_transaction_items')->where('vend_transaction_id', $basket->id)
            ->orderBy('id')->pluck('happy_hour_slot_id')->map(fn ($v) => $v === null ? null : (int) $v)->all());

        // The slot's 15:00 close counts the tagged single sales and the basket's promo line.
        Carbon::setTestNow('2026-10-09 15:00:00');
        $this->planner()->run(now());
        $slot->refresh();
        $this->assertSame(HappyHourSlot::STATUS_ENDED, $slot->status);
        $this->assertSame([3, 720], [$slot->units_sold, $slot->revenue_cents]);

        // A TRADE landing after the close re-opens the totals and the next run recounts.
        $this->sale($a, 240, '2026-10-09 14:59:30');
        $this->assertNull($slot->fresh()->units_sold);
        Carbon::setTestNow('2026-10-09 15:01:00');
        $this->planner()->run(now());
        $this->assertSame([4, 960, 640], [$slot->fresh()->units_sold, $slot->fresh()->revenue_cents, $slot->fresh()->discount_cents]);
    }

    public function test_the_status_box_shows_units_sold_in_the_live_slot_and_the_machines_last_24_hours(): void
    {
        $this->freezer(['A' => [9, 10, 4.00, 0], 'B' => [9, 10, 3.00, 9]]);
        $campaign = $this->campaign(['sku_count' => 1]);
        $a = $this->products['A']->id;
        $b = $this->products['B']->id;
        Carbon::setTestNow('2026-10-09 13:58:00');
        $this->planner()->run(now());
        $first = HappyHourSlot::where('position', 0)->firstOrFail();

        // The 14:00 slot sells two promo units: a single sale and a basket's promo line.
        $this->sale($a, 240, '2026-10-09 14:10:00');
        $this->basket([[$a, 240], [$b, 300]], '2026-10-09 14:20:00');

        foreach (['14:58:00', '15:00:00'] as $t) {
            Carbon::setTestNow("2026-10-09 {$t}");
            $this->planner()->run(now());
        }
        $second = HappyHourSlot::where('position', 1)->firstOrFail();
        $this->assertSame($a, $second->product_id);
        $this->sale($a, 240, '2026-10-09 15:10:00');

        // Neither counts: a refunded promo sale, and a promo sale older than 24 h.
        $refunded = $this->sale($a, 240, '2026-10-09 15:12:00');
        DB::table('vend_transactions')->where('id', $refunded->id)->update(['is_refunded' => 1]);
        $old = $this->sale($a, 240, '2026-10-09 14:30:00');
        DB::table('vend_transactions')->where('id', $old->id)->update(['transaction_datetime' => '2026-10-08 14:30:00']);

        Carbon::setTestNow('2026-10-09 15:20:00');
        $user = User::factory()->create(['operator_id' => 1]);
        $user->givePermissionTo(Permission::findOrCreate('read happy-hour-campaigns', 'web'));

        // "HH: n" is the slot running now only (1, not 3); the machine's last 24 h covers every slot:
        // 3 units at $1.60 off each.
        $this->actingAs($user)->get('/happy-hour-campaigns')->assertOk()
            ->assertInertia(fn ($page) => $page->where('campaigns.0.id', $campaign->id)
                ->where('campaigns.0.live.0.id', $second->id)
                ->where('campaigns.0.live.0.sold', 1)
                ->where('campaigns.0.vends.0.l24h_units', 3)
                ->where('campaigns.0.vends.0.l24h_discount', 480));

        $totals = app(HappyHourSales::class)->slotTotals([$this->vend->id], now()->subDay(), now());
        $this->assertSame(2, $totals[$first->id]['units']);
        $this->assertSame(app(HappyHourSales::class)->results($first)['discount'], $totals[$first->id]['discount'],
            'the grouped totals agree with a closed slot\'s results()');
    }

    public function test_the_campaign_figures_and_graph_count_tagged_sales_per_machine_day(): void
    {
        $this->freezer(['A' => [9, 10, 4.00, 0], 'B' => [9, 10, 3.00, 9]]);
        $campaign = $this->campaign(['sku_count' => 1]);
        $a = $this->products['A']->id;
        $b = $this->products['B']->id;
        foreach (['13:58:00', '14:58:00', '15:00:00'] as $t) {
            Carbon::setTestNow("2026-10-09 {$t}");
            $this->planner()->run(now());
        }
        $this->sale($a, 240, '2026-10-09 14:10:00');
        $this->basket([[$a, 240], [$b, 300]], '2026-10-09 14:20:00');
        $this->sale($a, 240, '2026-10-09 15:10:00');
        $this->sale($a, 400, '2026-10-09 15:11:00'); // full price: not Happy Hour

        Carbon::setTestNow('2026-10-09 15:20:00');
        $user = User::factory()->create(['operator_id' => 1]);
        $user->givePermissionTo(Permission::findOrCreate('read happy-hour-campaigns', 'web'));

        // 3 promo units at $2.40 ($1.60 off each) on one machine that ran Happy Hour today.
        $expect = ['revenue' => 720, 'discount' => 480, 'units' => 3, 'machines' => 1, 'machine_days' => 1, 'avg_daily_units_per_machine' => 3];
        // The figures are deferred: absent from the page itself, fetched by the browser right after.
        $this->actingAs($user)->get('/happy-hour-campaigns')->assertOk()
            ->assertInertia(fn ($page) => $page->has('campaigns', 1)->missing('campaignStats'));
        $stats = $this->deferredCampaignStats($user)[$campaign->id];
        foreach ($expect as $key => $value) {
            $this->assertEquals($value, $stats['l24h'][$key], "l24h {$key}");
            $this->assertEquals($value, $stats['l30d'][$key], "l30d {$key}");
        }

        // The graph: the last 7 days by default, every date present.
        $this->actingAs($user)->getJson("/happy-hour-campaigns/{$campaign->id}/stats")->assertOk()
            ->assertJsonPath('from', '2026-10-03')
            ->assertJsonPath('to', '2026-10-09')
            ->assertJsonCount(7, 'days')
            ->assertJsonPath('days.0.units', 0)
            ->assertJsonPath('days.0.machines', 0)
            ->assertJsonPath('days.6.units', 3)
            ->assertJsonPath('days.6.discount', 480)
            ->assertJsonPath('days.6.machines', 1)
            ->assertJsonPath('total.revenue', 720);
        $this->actingAs($user)->getJson("/happy-hour-campaigns/{$campaign->id}/stats?from=2026-10-01&to=2026-10-02")->assertOk()
            ->assertJsonCount(2, 'days')
            ->assertJsonPath('total.units', 0)
            ->assertJsonPath('total.avg_daily_units_per_machine', null);
        $this->actingAs($user)->getJson("/happy-hour-campaigns/{$campaign->id}/stats?from=junk")->assertStatus(422);
    }

    public function test_related_total_sales_count_each_transaction_holding_a_happy_hour_unit_whole(): void
    {
        $this->freezer(['A' => [9, 10, 4.00, 0], 'B' => [9, 10, 3.00, 9]]);
        $campaign = $this->campaign(['sku_count' => 1]);
        $a = $this->products['A']->id;
        $b = $this->products['B']->id;
        foreach (['13:58:00', '14:00:00'] as $t) {
            Carbon::setTestNow("2026-10-09 {$t}");
            $this->planner()->run(now());
        }
        $this->sale($a, 240, '2026-10-09 14:05:00');                                        // HH alone: 0% non-HH
        $five = $this->basket([[$a, 240], [$b, 300], [$b, 300], [$b, 300], [$b, 300]], '2026-10-09 14:10:00'); // 1 of 5 is HH: counted whole
        DB::table('vend_transaction_items')->where('vend_transaction_id', $five->id)->where('product_id', $b)->limit(1)
            ->update(['vend_channel_error_code' => 7]);                                      // a failed line is paid but not a unit
        DB::table('vend_transactions')->where('id', $this->sale($a, 240, '2026-10-09 14:15:00')->id)->update(['is_refunded' => 1]);
        $this->basket([[$b, 300], [$b, 300]], '2026-10-09 14:20:00');                      // no HH unit: not related
        $this->sale($b, 300, '2026-10-09 14:25:00');                                        // full price: not related

        Carbon::setTestNow('2026-10-09 14:40:00');
        $user = User::factory()->create(['operator_id' => 1]);
        $user->givePermissionTo(Permission::findOrCreate('read happy-hour-campaigns', 'web'));

        // HH: 2 units, $4.80. Related: 2 transactions, $2.40 + $14.40 = $16.80, 1 + 4 dispensed units.
        $expect = [
            'revenue' => 480, 'units' => 2,
            'related_transactions' => 2, 'related_multiple' => 1, 'related_revenue' => 1680, 'related_units' => 5,
            'related_units_per_machine' => 5, 'non_hh_revenue_pct' => 71.4, 'non_hh_units_pct' => 60,
        ];
        $stats = $this->deferredCampaignStats($user)[$campaign->id];
        foreach ($expect as $key => $value) {
            $this->assertEquals($value, $stats['l24h'][$key], "l24h {$key}");
        }
        $json = $this->actingAs($user)->getJson("/happy-hour-campaigns/{$campaign->id}/stats")->assertOk()->json();
        foreach ($expect as $key => $value) {
            $this->assertEquals($value, $json['days'][6][$key], "day {$key}");
            $this->assertEquals($value, $json['total'][$key], "total {$key}");
        }
        $this->assertNull($json['days'][0]['non_hh_revenue_pct'], 'a day with no sales has no share');
        $this->assertSame(0, $json['days'][0]['related_revenue']);
    }

    public function test_the_trade_ingest_tags_a_freezer_sale_made_at_the_promo_price(): void
    {
        PaymentMethod::firstOrCreate(['code' => 0], ['name' => 'Cash', 'is_active' => true]);
        VendChannelError::firstOrCreate(['code' => 0], ['desc' => 'No Malfunction (0)']);
        $this->freezer(['A' => [9, 10, 4.00, 0]]);
        $this->campaign(['sku_count' => 1]);
        Carbon::setTestNow('2026-10-09 13:58:00');
        $this->planner()->run(now());
        $slot = HappyHourSlot::firstOrFail();
        $a = $this->products['A']->id;

        Carbon::setTestNow('2026-10-09 14:05:00');
        $trade = fn (string $orderId, int $cents) => app(VendTransactionService::class)->create($this->vend->fresh(), [
            // As the freezer sends it (prod 2026-10-09): header Price in dollars, each unit's Price in cents.
            'ORDRID' => $orderId, 'PAY_TYPE' => 0, 'TIME' => now()->format('Y-m-d H:i:s'), 'SErr' => 0, 'SId' => 11,
            'Price' => number_format($cents / 100, 2, '.', ''), 'TXN_SRC' => 0,
            'transf_info' => [['SId' => 11, 'SErr' => 0, 'Price' => $cents, 'goods_id' => $a]],
        ]);
        $trade('26100914050050001', 240);
        $trade('26100914050150001', 400);

        $this->assertSame(
            ['26100914050050001' => $slot->id, '26100914050150001' => null],
            VendTransaction::withoutGlobalScopes()->orderBy('id')->get(['order_id', 'happy_hour_slot_id'])
                ->mapWithKeys(fn ($t) => [$t->order_id => $t->happy_hour_slot_id === null ? null : (int) $t->happy_hour_slot_id])->all()
        );
    }

    public function test_two_skus_at_a_time_run_together_and_the_groups_take_turns(): void
    {
        // Balance %: A 90, B 80, C 70, D 60, E 50 (E is outside the top 4).
        $this->freezer(['A' => [9, 10, 4.00, 0], 'B' => [8, 10, 4.00, 0], 'C' => [7, 10, 4.00, 0], 'D' => [6, 10, 4.00, 0], 'E' => [5, 10, 4.00, 0]]);
        $this->campaign(['sku_count' => 4, 'skus_per_slot' => 2, 'selection_rule' => HappyHourCampaign::RULE_BALANCE_PCT]);
        $at = fn (string $hhmm) => HappyHourSlot::where('starts_at', "2026-10-09 {$hhmm}:00")->orderBy('rank')->get()
            ->map(fn ($s) => $this->codeOf($s->product_id))->all();

        foreach (['13:58', '14:58', '15:58', '16:58'] as $minute) {
            Carbon::setTestNow("2026-10-09 {$minute}:00");
            $this->planner()->run(now());
            $this->planner()->run(now()->addMinute()); // idempotent, and each starting SKU is checked
        }

        $this->assertSame(['A', 'B'], $at('14:00'));
        $this->assertSame(['C', 'D'], $at('15:00'));
        $this->assertSame(['A', 'B'], $at('16:00'), 'back to the first group');
        $this->assertSame(['C', 'D'], $at('17:00'));
        $this->assertSame(8, HappyHourSlot::count());

        // Both SKUs of the live slot are on the menu at the promo price, each with its rank.
        Carbon::setTestNow('2026-10-09 16:30:00');
        $rows = collect($this->getJson('/api/vends/50001/menu')->assertOk()->json())->keyBy('product_code');
        $live = fn (string $code) => collect($rows[$code]['happy_hour'])->first(fn ($s) => $s['starts_at'] === '2026-10-09T16:00:00+08:00');
        $this->assertSame([240, 1], [$live('A')['price'], $live('A')['rank']]);
        $this->assertSame([240, 2], [$live('B')['price'], $live('B')['rank']]);
        $this->assertNull($live('C'));

        // A sale of either is the slot's own.
        $slotB = HappyHourSlot::where('starts_at', '2026-10-09 16:00:00')->where('product_id', $this->products['B']->id)->firstOrFail();
        $this->assertSame($slotB->id, $this->sale($this->products['B']->id, 240, '2026-10-09 16:20:00')->fresh()->happy_hour_slot_id);
    }

    public function test_a_sku_that_stops_qualifying_as_its_group_starts_is_replaced_by_one_not_already_on_offer(): void
    {
        $this->freezer(['A' => [9, 10, 4.00, 0], 'B' => [8, 10, 4.00, 0], 'C' => [7, 10, 4.00, 0], 'D' => [6, 10, 4.00, 0]]);
        $this->campaign(['sku_count' => 4, 'skus_per_slot' => 2, 'selection_rule' => HappyHourCampaign::RULE_BALANCE_PCT]);
        Carbon::setTestNow('2026-10-09 13:58:00');
        $this->planner()->run(now());

        // A sells down to one unit before 14:00: its place goes to C (the best not already on offer).
        DB::table('vend_channels')->where('product_id', $this->products['A']->id)->update(['qty' => 1]);
        Carbon::setTestNow('2026-10-09 14:00:00');
        $this->planner()->run(now());

        $group = HappyHourSlot::open()->where('starts_at', '2026-10-09 14:00:00')->get();
        $this->assertEqualsCanonicalizing(['B', 'C'], $group->map(fn ($s) => $this->codeOf($s->product_id))->all());
        $this->assertTrue($group->every(fn ($s) => $s->checked_at !== null));

        // B sells out mid-slot: it ends, and D fills its place for the rest of the hour.
        DB::table('vend_channels')->where('product_id', $this->products['B']->id)->update(['qty' => 0]);
        Carbon::setTestNow('2026-10-09 14:20:00');
        $this->planner()->run(now());
        $this->assertSame(HappyHourSlot::STATUS_SOLD_OUT, $group->firstWhere('product_id', $this->products['B']->id)->fresh()->status);
        $open = HappyHourSlot::open()->where('starts_at', '<=', now())->where('ends_at', '>', now())->get();
        $this->assertEqualsCanonicalizing(['C', 'D'], $open->map(fn ($s) => $this->codeOf($s->product_id))->all());
    }

    public function test_at_the_same_time_cannot_exceed_the_total(): void
    {
        $this->freezer(['A' => [5, 10, 4.00, 0]]);
        $user = User::factory()->create(['operator_id' => 1]);
        $user->givePermissionTo(Permission::findOrCreate('create happy-hour-campaigns', 'web'));
        $this->actingAs($user)->post('/happy-hour-campaigns', $this->form(['sku_count' => 2, 'skus_per_slot' => 3]))
            ->assertSessionHasErrors('skus_per_slot');
    }

    public function test_one_writer_at_a_time_the_run_skips_a_minute_while_the_planner_lock_is_held(): void
    {
        $this->freezer(['A' => [5, 10, 4.00, 0], 'B' => [4, 10, 4.00, 7]]);
        $this->campaign();
        Carbon::setTestNow('2026-10-09 13:58:00');

        $lock = Cache::lock(HappyHourPlanner::LOCK, 300);
        $this->assertTrue($lock->get());
        $this->assertSame(0, $this->planner()->run(now(), 0)['planned'], 'a save holds the lock: this minute is skipped');
        $this->assertSame(0, HappyHourSlot::count());

        $lock->release();
        $this->assertSame(1, $this->planner()->run(now(), 0)['planned'], 'the next minute catches up');
    }

    public function test_the_run_takes_back_slots_of_a_paused_campaign_or_a_machine_that_left_or_stopped_qualifying(): void
    {
        $this->freezer(['A' => [5, 10, 4.00, 0], 'B' => [4, 10, 4.00, 7]]);
        $campaign = $this->campaign();
        Carbon::setTestNow('2026-10-09 13:58:00');
        $this->planner()->run(now());
        Carbon::setTestNow('2026-10-09 14:05:00');
        $this->planner()->run(now());
        $live = HappyHourSlot::open()->sole();

        // Paused behind a run's back (its save hook never ran): the next run ends the live slot.
        HappyHourCampaign::whereKey($campaign->id)->update(['is_active' => false]);
        Carbon::setTestNow('2026-10-09 14:06:00');
        $this->assertSame(1, $this->planner()->run(now())['orphaned']);
        $this->assertSame(HappyHourSlot::STATUS_CANCELLED, $live->fresh()->status);
        $this->assertSame('2026-10-09 14:06:00', $live->fresh()->ends_at->toDateTimeString());
        $this->assertSame('2026-10-09 15:00:00', $live->fresh()->planned_ends_at->toDateTimeString(), 'its planned end stays');

        // Back on, then the machine is switched off: taken back again.
        HappyHourCampaign::whereKey($campaign->id)->update(['is_active' => true]);
        Carbon::setTestNow('2026-10-09 14:07:00');
        $this->planner()->run(now());
        $live = HappyHourSlot::open()->sole();
        Vend::whereKey($this->vend->id)->update(['is_active' => 0]);
        Carbon::setTestNow('2026-10-09 14:09:00');
        $this->planner()->run(now());
        $this->assertSame(HappyHourSlot::STATUS_CANCELLED, $live->fresh()->status);

        // On again, then taken off the campaign's machines: the same.
        Vend::whereKey($this->vend->id)->update(['is_active' => 1]);
        Carbon::setTestNow('2026-10-09 14:10:00');
        $this->planner()->run(now());
        $live = HappyHourSlot::open()->sole();
        $campaign->vends()->detach($this->vend->id);
        Carbon::setTestNow('2026-10-09 14:12:00');
        $this->planner()->run(now());
        $this->assertSame(HappyHourSlot::STATUS_CANCELLED, $live->fresh()->status);
        $this->assertSame(0, HappyHourSlot::open()->count());
    }

    public function test_reordering_a_sku_list_on_save_leaves_the_live_slots_alone(): void
    {
        $this->freezer(['A' => [5, 10, 4.00, 0], 'B' => [4, 10, 4.00, 7], 'C' => [6, 10, 4.00, 28]]);
        $user = User::factory()->create(['operator_id' => 1]);
        foreach (['read', 'create', 'update'] as $action) {
            $user->givePermissionTo(Permission::findOrCreate("{$action} happy-hour-campaigns", 'web'));
        }
        [$a, $c] = [$this->products['A']->id, $this->products['C']->id];
        Carbon::setTestNow('2026-10-09 14:30:00');
        $this->actingAs($user)->post('/happy-hour-campaigns', $this->form(['priority_product_ids' => [$c, $a]]))->assertSessionHasNoErrors();
        $campaign = HappyHourCampaign::firstOrFail();
        $this->assertSame([$a, $c], $campaign->priorityProductIds(), 'stored as a sorted set');
        // A list saved unsorted before this fix is still the same set.
        HappyHourCampaign::whereKey($campaign->id)->update(['priority_product_ids' => json_encode([$c, $a])]);
        Carbon::setTestNow('2026-10-09 14:35:00');
        $live = HappyHourSlot::open()->pluck('id')->all();
        $this->assertNotSame([], $live);

        $this->actingAs($user)->post("/happy-hour-campaigns/{$campaign->id}", $this->form(['priority_product_ids' => [$a, $c]]))->assertSessionHasNoErrors();
        $this->assertSame($live, HappyHourSlot::open()->pluck('id')->all(), 're-ordered: nothing re-planned');

        $this->actingAs($user)->post("/happy-hour-campaigns/{$campaign->id}", $this->form(['priority_product_ids' => [$a]]))->assertSessionHasNoErrors();
        $this->assertNotSame($live, HappyHourSlot::open()->pluck('id')->all(), 'a real change re-plans');
    }

    public function test_the_deferred_figures_request_runs_only_the_figures(): void
    {
        $this->freezer(['A' => [5, 10, 4.00, 0], 'B' => [4, 10, 4.00, 7]]);
        $this->campaign();
        $user = User::factory()->create(['operator_id' => 1]);
        $user->givePermissionTo(Permission::findOrCreate('read happy-hour-campaigns', 'web'));

        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->deferredCampaignStats($user);
        $sql = collect(DB::getQueryLog())->pluck('query')->implode("\n");
        DB::disableQueryLog();
        // The campaign list (machines through the pivot), the UI Setting check and the machine and product
        // pickers belong to the first request only.
        $this->assertStringNotContainsString('happy_hour_campaign_vend', $sql);
        $this->assertStringNotContainsString('apk_setting_vend', $sql);
    }

    public function test_the_midnight_slot_is_picked_before_midnight_and_23_59_runs_to_midnight(): void
    {
        $this->freezer(['A' => [5, 10, 4.00, 0], 'B' => [4, 10, 4.00, 7]]);
        $campaign = $this->campaign(['window_start' => '00:00:00', 'window_end' => '23:59:00']);
        $this->assertSame('2026-10-10 00:00:00', $campaign->windowOn(Carbon::parse('2026-10-09'))[1]->toDateTimeString());

        Carbon::setTestNow('2026-10-09 22:58:00');
        $this->planner()->run(now());
        $this->assertSame('2026-10-10 00:00:00', HappyHourSlot::where('starts_at', '2026-10-09 23:00:00')->firstOrFail()->ends_at->toDateTimeString(), 'no 23:59–00:00 gap');

        Carbon::setTestNow('2026-10-09 23:58:00');
        $this->planner()->run(now());
        $first = HappyHourSlot::where('starts_at', '2026-10-10 00:00:00')->sole();
        $this->assertSame('2026-10-10', $first->slot_date->toDateString());
        $this->assertSame(0, $first->position);
    }

    public function test_a_freezer_that_missed_a_pause_still_tags_promo_sales_until_the_slots_planned_end(): void
    {
        $this->freezer(['A' => [9, 10, 4.00, 0]]);
        $campaign = $this->campaign(['sku_count' => 1]);
        Carbon::setTestNow('2026-10-09 13:58:00');
        $this->planner()->run(now());
        Carbon::setTestNow('2026-10-09 14:10:00');
        $slot = HappyHourSlot::sole();
        $this->planner()->cancel($campaign, now());
        $this->assertSame(HappyHourSlot::STATUS_CANCELLED, $slot->fresh()->status);

        // Its menu still says $2.40 until 15:00: a sale at 14:40 is the cancelled slot's, one after 15:05 is not.
        $this->assertSame($slot->id, (int) $this->sale($this->products['A']->id, 240, '2026-10-09 14:40:00')->fresh()->happy_hour_slot_id);
        $this->assertNull($this->sale($this->products['A']->id, 240, '2026-10-09 15:06:00')->fresh()->happy_hour_slot_id);
    }

    public function test_a_slot_change_seconds_after_a_nudge_still_nudges(): void
    {
        $this->freezer(['A' => [5, 10, 4.00, 0], 'B' => [4, 10, 4.00, 7]]);
        $campaign = $this->campaign();
        Carbon::setTestNow('2026-10-09 13:58:00');
        $this->planner()->run(now());
        Queue::assertPushed(NudgeFreezerMenu::class, 1);

        // 6 s later (after the first nudge's job read the menu) the slot is taken back: a second nudge.
        Carbon::setTestNow('2026-10-09 13:58:06');
        $this->planner()->cancel($campaign, now());
        Queue::assertPushed(NudgeFreezerMenu::class, 2);
    }

    /** @param  array<string, mixed>  $overrides */
    /**
     * The deferred `campaignStats` prop, fetched the way the browser does after the page draws: an Inertia
     * partial reload asking for that prop only.
     *
     * @return array<int|string, array<string, mixed>>
     */
    private function deferredCampaignStats(User $user): array
    {
        $version = (string) app(\App\Http\Middleware\HandleInertiaRequests::class)->version(\Illuminate\Http\Request::create('/'));

        return $this->actingAs($user)->get('/happy-hour-campaigns', [
            'X-Inertia' => 'true',
            'X-Inertia-Version' => $version,
            'X-Inertia-Partial-Component' => 'HappyHourCampaign/Index',
            'X-Inertia-Partial-Data' => 'campaignStats',
        ])->assertOk()->json('props.campaignStats');
    }

    private function form(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Clear slow movers', 'is_active' => true, 'starts_on' => null, 'ends_on' => null,
            'days_mask' => HappyHourCampaign::EVERY_DAY, 'window_start' => '14:00', 'window_end' => '18:00',
            'slot_minutes' => 60, 'sku_count' => 2, 'skus_per_slot' => 1, 'selection_rule' => HappyHourCampaign::RULE_DAYS_OF_COVER,
            'lookback_days' => 14, 'discount_pct' => 40, 'min_balance_pct' => 15, 'min_qty' => 2,
            'price_step_cents' => 10, 'allow_below_cost' => false, 'skip_unavailable' => true, 'excluded_product_ids' => [],
            'vend_ids' => [$this->vend->id],
        ], $overrides);
    }

    /** A single sale as the freezer's TRADE writes it — the header row only, no item row — then tagged. */
    private function sale(int $productId, int $cents, string $at): VendTransaction
    {
        $id = DB::table('vend_transactions')->insertGetId([
            'vend_id' => $this->vend->id, 'order_id' => 'HH-'.uniqid(), 'transaction_datetime' => $at, 'amount' => $cents,
            'product_id' => $productId, 'qty' => 1, 'is_multiple' => 0,
            'settlement_status' => VendTransaction::SETTLEMENT_SETTLED, 'created_at' => $at, 'updated_at' => $at,
        ]);
        $sale = VendTransaction::withoutGlobalScopes()->findOrFail($id);
        app(HappyHourSales::class)->tag($this->vend, $sale);

        return $sale;
    }

    /** A multiple purchase: header + one item row per unit, then tagged. @param list<array{0: int, 1: int}> $units */
    private function basket(array $units, string $at): VendTransaction
    {
        $id = DB::table('vend_transactions')->insertGetId([
            'vend_id' => $this->vend->id, 'order_id' => 'HH-'.uniqid(), 'transaction_datetime' => $at,
            'amount' => array_sum(array_column($units, 1)), 'qty' => count($units), 'is_multiple' => 1,
            'settlement_status' => VendTransaction::SETTLEMENT_SETTLED, 'created_at' => $at, 'updated_at' => $at,
        ]);
        foreach ($units as [$productId, $cents]) {
            DB::table('vend_transaction_items')->insert([
                'vend_transaction_id' => $id, 'product_id' => $productId, 'unit_price_amount' => $cents, 'created_at' => $at,
            ]);
        }
        $sale = VendTransaction::withoutGlobalScopes()->findOrFail($id);
        app(HappyHourSales::class)->tag($this->vend, $sale);

        return $sale;
    }

    private function codeOf(int $productId): string
    {
        return collect($this->products)->search(fn (Product $p) => $p->id === $productId);
    }
}
