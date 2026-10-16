<?php

namespace Tests\Feature;

use App\Jobs\NudgeFreezerMenu;
use App\Jobs\PublishMqtt;
use App\Models\Campaign;
use App\Models\Customer;
use App\Models\HappyHourCampaign;
use App\Models\HappyHourSlot;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\ProductMapping;
use App\Models\ProductMappingItem;
use App\Models\ProductWelcomeSketch;
use App\Models\SellingPrice;
use App\Models\User;
use App\Models\Vend;
use App\Models\VendChannelError;
use App\Models\VendTransaction;
use App\Services\HappyHour\HappyHourEligibility;
use App\Services\HappyHour\HappyHourPause;
use App\Services\HappyHour\HappyHourPlanner;
use App\Services\HappyHour\HappyHourPricing;
use App\Services\HappyHour\HappyHourRanker;
use App\Services\HappyHour\HappyHourSales;
use App\Services\Products\WelcomeSketch\CutoutMaker;
use App\Services\Products\WelcomeSketch\ProductWelcomeSketchService;
use App\Services\Products\WelcomeSketch\SketchGenerator;
use App\Services\VendJobService;
use App\Services\VendTransactionService;
use App\ValueObjects\ApkSettingParameters;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Happy Hour on big-board vending machines (mark1-apk 315, apk/mark1-apk/UNRELEASED_V315.md): the
 * eligibility gate, board-price pricing, the UI Setting campaign failsafe, `/thumbnails`, the
 * HAPPYHOUR nudge, `HH` sale tagging, the welcome-sketch sweep and the `enableWelcomeScene` key —
 * and that the smart freezer's Happy Hour is unchanged by any of it.
 */
class HappyHourVendingTest extends TestCase
{
    use RefreshDatabase;

    /** @var array<string, Product> */
    private array $products = [];

    private ProductMapping $mapping;

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
     * A touchscreen vending machine at the board price. $channels: code => [product code, board price ¢, qty, capacity].
     *
     * @param  array<int, array{0: string, 1: int, 2: int, 3: int}>  $channels
     * @param  array<string, mixed>  $attributes
     */
    private function vending(int $code, array $channels, int $apk = 315, string $deviceType = 'ZC-328', array $attributes = []): Vend
    {
        $customer = Customer::create([
            'name' => "Site {$code}", 'code' => "S{$code}", 'operator_id' => 1,
            'status_id' => Customer::STATUS_ACTIVE, 'selling_price_type' => SellingPrice::TYPE_2,
        ]);
        $this->mapping ??= ProductMapping::create([
            'name' => 'Vending', 'machine_type' => Vend::MACHINE_TYPE_VENDING_MACHINE, 'is_active' => true, 'operator_id' => 1,
        ]);
        $vend = new Vend;
        $vend->forceFill(array_merge([
            'code' => $code, 'machine_type' => Vend::MACHINE_TYPE_VENDING_MACHINE, 'is_active' => 1, 'operator_id' => 1,
            'vend_model_id' => 1, 'product_mapping_id' => $this->mapping->id, 'customer_id' => $customer->id,
            'apk_ver_json' => ['apkver' => $apk, 'buildtime' => '2026-10-12', 'deviceType' => $deviceType],
            'private_key' => 'TESTKEY000000001',
        ], $attributes))->save();

        foreach ($channels as $channel => [$productCode, $cents, $qty, $capacity]) {
            $product = $this->products[$productCode] ??= Product::forceCreate(['code' => $productCode, 'name' => "Treat {$productCode}", 'operator_id' => 1]);
            if (! ProductMappingItem::where('product_mapping_id', $this->mapping->id)->where('channel_code', (string) $channel)->exists()) {
                ProductMappingItem::create(['product_mapping_id' => $this->mapping->id, 'channel_code' => (string) $channel, 'product_id' => $product->id, 'sequence' => $channel]);
            }
            DB::table('vend_channels')->insert([
                'vend_id' => $vend->id, 'code' => $channel, 'product_id' => $product->id, 'amount' => $cents,
                'qty' => $qty, 'capacity' => $capacity, 'is_active' => 1,
            ]);
        }

        return $vend->fresh();
    }

    /** A freezer on app 30, Site tier 2 prices. */
    private function freezer(): Vend
    {
        $customer = Customer::create([
            'name' => 'Freezer site', 'code' => 'FZ1', 'operator_id' => 1,
            'status_id' => Customer::STATUS_ACTIVE, 'selling_price_type' => SellingPrice::TYPE_2,
        ]);
        $mapping = ProductMapping::create([
            'name' => 'Freezer', 'machine_type' => Vend::MACHINE_TYPE_SMART_FREEZER, 'is_smart' => true, 'is_active' => true, 'operator_id' => 1,
        ]);
        $vend = new Vend;
        $vend->forceFill([
            'code' => 50001, 'machine_type' => Vend::MACHINE_TYPE_SMART_FREEZER, 'is_active' => 1, 'operator_id' => 1,
            'vend_model_id' => 1, 'product_mapping_id' => $mapping->id, 'customer_id' => $customer->id, 'apk_version_code' => 30,
            'private_key' => 'TESTKEY000000001',
        ])->save();
        $product = $this->products['FZ'] = Product::forceCreate(['code' => 'FZ', 'name' => 'Freezer treat', 'operator_id' => 1]);
        SellingPrice::create(['product_id' => $product->id, 'type' => SellingPrice::TYPE_2, 'amount' => 4.00]);
        ProductMappingItem::create(['product_mapping_id' => $mapping->id, 'channel_code' => '11', 'product_id' => $product->id]);
        DB::table('vend_channels')->insert([
            'vend_id' => $vend->id, 'code' => 11, 'product_id' => $product->id, 'amount' => 400, 'qty' => 9, 'capacity' => 10, 'is_active' => 1,
        ]);

        return $vend->fresh();
    }

    /** @param  list<Vend>  $vends */
    private function campaign(array $vends, array $overrides = []): HappyHourCampaign
    {
        $campaign = HappyHourCampaign::create(array_merge([
            'name' => 'Vending HH', 'is_active' => true, 'days_mask' => HappyHourCampaign::EVERY_DAY,
            'window_start' => '14:00:00', 'window_end' => '18:00:00', 'slot_minutes' => 60, 'sku_count' => 1,
            'selection_rule' => HappyHourCampaign::RULE_BALANCE_PCT, 'lookback_days' => 14, 'discount_pct' => 40,
            'min_balance_pct' => 15, 'min_qty' => 2, 'price_step_cents' => 10, 'allow_below_cost' => false,
        ], $overrides));
        $campaign->vends()->attach(collect($vends)->pluck('id')->all());

        return $campaign;
    }

    /** Binds a UI Setting carrying one campaign to the machine. */
    /** Binds the machine to a UI Setting that carries no campaign. */
    private function uiSetting(Vend $vend, string $name = 'XO'): int
    {
        $settingId = DB::table('apk_settings')->insertGetId([
            'name' => $name, 'settings_parameter_json' => json_encode([]),
            'operator_id' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('apk_setting_vend')->insert(['apk_setting_id' => $settingId, 'vend_id' => $vend->id, 'created_at' => now(), 'updated_at' => now()]);

        return $settingId;
    }

    private function uiSettingCampaign(Vend $vend, array $campaign = []): Campaign
    {
        $settingId = DB::table('apk_settings')->insertGetId([
            'name' => 'Cash& Cashless_2 Cornetto Royale $5', 'settings_parameter_json' => json_encode([]),
            'operator_id' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('apk_setting_vend')->insert(['apk_setting_id' => $settingId, 'vend_id' => $vend->id, 'created_at' => now(), 'updated_at' => now()]);
        $row = Campaign::create(array_merge([
            'name' => 'Buy any 2 Royale Cornettos for $5', 'operator_id' => 1, 'is_active' => true, 'promo_type' => 'Amount',
            'start_at' => '2026-03-04 00:00:00', 'end_at' => '2026-12-31 00:00:00',
        ], $campaign));
        DB::table('apk_setting_campaign')->insert(['apk_setting_id' => $settingId, 'campaign_id' => $row->id, 'created_at' => now(), 'updated_at' => now()]);

        return $row;
    }

    private function planner(): HappyHourPlanner
    {
        return app(HappyHourPlanner::class);
    }

    /** @return list<array{0: string, 1: array<string, mixed>, 2: int}> topic, decoded body, qos of every published frame */
    private function publishedFrames(): array
    {
        $frames = [];
        Queue::assertPushed(PublishMqtt::class, function (PublishMqtt $job) use (&$frames) {
            [$topic, $wire, $qos] = (fn () => [$this->topic, $this->message, $this->qos])->call($job);
            [, , $b64] = explode(',', $wire);
            $frames[] = [$topic, json_decode(base64_decode($b64), true), $qos];

            return true;
        });

        return $frames;
    }

    // ── 1. Eligibility ───────────────────────────────────────────────────────────────────────

    public function test_only_big_boards_on_315_and_freezers_on_30_are_eligible(): void
    {
        $cases = [
            'big board 315' => [$this->vending(2001, []), true],
            'big board 314' => [$this->vending(2002, [], 314), false],
            'small board new series' => [$this->vending(2003, [], 14, 'ZC-83A'), false],
            'small board old series' => [$this->vending(2004, [], 145, 'ZC-83A'), false],
            'small board family on 315 (backstop)' => [$this->vending(2005, [], 315, 'ZC-83A'), false],
            'inactive big board' => [$this->vending(2006, [], 315, 'ZC-328', ['is_active' => 0]), false],
            // The OTA check-in column counts too (max of the two channels).
            'OTA reports 315, frame 314' => [$this->vending(2007, [], 314, 'ZC-328', ['apk_version_code' => 315]), true],
            'freezer 30' => [$this->freezer(), true],
        ];
        foreach ($cases as $label => [$vend, $expected]) {
            $this->assertSame($expected, HappyHourEligibility::supports($vend), $label);
        }
        $this->assertFalse(HappyHourEligibility::supportsVending($cases['freezer 30'][0]), 'a freezer is not a vending machine');
        $this->assertStringContainsString('small board (ZC-83A)', HappyHourEligibility::reason($cases['small board family on 315 (backstop)'][0]));
        $this->assertStringContainsString('needs 315', HappyHourEligibility::reason($cases['big board 314'][0]));

        $old = (new Vend)->forceFill(['machine_type' => Vend::MACHINE_TYPE_SMART_FREEZER, 'is_active' => 1, 'apk_version_code' => 29]);
        $this->assertFalse(HappyHourEligibility::supports($old), 'the freezer gate is unchanged');
        $chiller = (new Vend)->forceFill(['machine_type' => Vend::MACHINE_TYPE_SMART_CHILLER, 'is_active' => 1, 'apk_version_code' => 999]);
        $this->assertFalse(HappyHourEligibility::supports($chiller));
    }

    // ── 3. Pricing ───────────────────────────────────────────────────────────────────────────

    public function test_a_vending_sku_is_priced_from_its_highest_board_price_unless_the_machine_follows_server_price(): void
    {
        $vend = $this->vending(2245, [11 => ['A', 160, 5, 10], 12 => ['A', 140, 4, 10], 21 => ['B', 200, 8, 10], 22 => ['C', 0, 9, 10]]);
        $campaign = $this->campaign([$vend]);

        $ranked = collect(app(HappyHourRanker::class)->rank($campaign, $vend, now()))->keyBy(fn ($c) => $c->productCode);
        $this->assertSame([160, 100], [$ranked['A']->originalPrice, $ranked['A']->promoPrice], 'the highest board price of the SKU; 96¢ rounds up');
        $this->assertSame(9, $ranked['A']->qty, 'stock is summed over both channels');
        $this->assertSame([200, 120], [$ranked['B']->originalPrice, $ranked['B']->promoPrice]);
        $this->assertSame('No selling price for this Site', $ranked['C']->excludedReason, 'a channel at 0¢ has no price');

        // A machine that follows server price takes the Site tier where the product has one.
        SellingPrice::create(['product_id' => $this->products['A']->id, 'type' => SellingPrice::TYPE_2, 'amount' => 3.00]);
        $vend->forceFill(['is_using_server_price' => 1])->save();
        $ranked = collect(app(HappyHourRanker::class)->rank($campaign, $vend->fresh(), now()))->keyBy(fn ($c) => $c->productCode);
        $this->assertSame([300, 180], [$ranked['A']->originalPrice, $ranked['A']->promoPrice]);
        $this->assertSame(200, $ranked['B']->originalPrice, 'no tier price: the board price the screen falls back to');

        // The planner freezes the slot at those prices.
        $vend->forceFill(['is_using_server_price' => 0])->save();
        Carbon::setTestNow('2026-10-09 13:58:00');
        $this->planner()->run(now());
        $slot = HappyHourSlot::firstOrFail();
        $this->assertSame([$this->products['B']->id, 200, 120], [$slot->product_id, $slot->original_price, $slot->promo_price], 'B has the highest balance %');
    }

    // ── 4. Failsafe ──────────────────────────────────────────────────────────────────────────

    public function test_a_machine_bound_to_a_ui_setting_ignores_happy_hour_until_it_is_unbound(): void
    {
        // Brian, 2026-10-15: any UI Setting wins, campaign or not (it was "only a live campaign" until then).
        $vend = $this->vending(2245, [11 => ['A', 160, 9, 10]]);
        $free = $this->vending(2246, [11 => ['A', 160, 9, 10]]);
        $this->campaign([$vend, $free]);
        $promo = $this->uiSettingCampaign($vend);

        // Planning: the bound machine gets no slot, the other one does.
        Carbon::setTestNow('2026-10-09 13:58:00');
        $stats = $this->planner()->run(now());
        $this->assertSame(1, $stats['paused_machines']);
        $this->assertSame([$free->id], HappyHourSlot::pluck('vend_id')->all());
        $this->assertSame(
            "Happy Hour is ignored on 2245 — it runs UI Setting 'Cash& Cashless_2 Cornetto Royale \$5' (live campaign 'Buy any 2 Royale Cornettos for \$5', until 31 Dec 2026). Unbind the UI Setting to run Happy Hour here.",
            HappyHourPause::forVends([$vend->id], now(), [$vend->id => '2245'])[$vend->id]['message']
        );

        // Its campaign ends: the UI Setting is still bound, so Happy Hour stays away.
        $promo->update(['is_active' => false]);
        Carbon::setTestNow('2026-10-09 14:58:00');
        $this->planner()->run(now());
        $this->assertSame(0, HappyHourSlot::where('vend_id', $vend->id)->count(), 'still bound to a UI Setting');

        // Unbound mid-slot: Happy Hour resumes at the NEXT slot by itself, not part-way through this one.
        Carbon::setTestNow('2026-10-09 15:05:00');
        $this->planner()->run(now());                                   // the minute run sees it still bound
        DB::table('apk_setting_vend')->where('vend_id', $vend->id)->delete();
        Carbon::setTestNow('2026-10-09 15:10:00');
        $this->planner()->run(now());
        $this->assertSame(0, HappyHourSlot::where('vend_id', $vend->id)->count(), 'the 15:00 slot time began while bound');
        Carbon::setTestNow('2026-10-09 15:58:00');
        $this->planner()->run(now());
        $this->assertSame(['2026-10-09 16:00:00'], HappyHourSlot::where('vend_id', $vend->id)->pluck('starts_at')->map(fn ($t) => $t->toDateTimeString())->all());

        // Bound to a UI Setting with NO campaign mid-slot: the live slot ends at the next minute run, nudged.
        Carbon::setTestNow('2026-10-09 16:10:00');
        $this->uiSetting($vend);
        Queue::fake();
        $stats = $this->planner()->run(now());
        $slot = HappyHourSlot::where('vend_id', $vend->id)->where('starts_at', '2026-10-09 16:00:00')->firstOrFail();
        $this->assertSame(1, $stats['paused']);
        $this->assertSame([HappyHourSlot::STATUS_CANCELLED, '2026-10-09 16:10:00'], [$slot->status, $slot->ends_at->toDateTimeString()]);
        Queue::assertPushed(NudgeFreezerMenu::class, fn ($job) => $job->vendId === $vend->id);
        $this->assertSame(HappyHourSlot::STATUS_SCHEDULED, HappyHourSlot::where('vend_id', $free->id)->where('starts_at', '2026-10-09 16:00:00')->value('status'));
    }

    public function test_any_ui_setting_binding_pauses_and_only_a_live_campaign_is_named(): void
    {
        $vend = $this->vending(2245, []);
        $this->assertFalse(HappyHourPause::isPaused($vend->id, now()), 'no UI Setting: Happy Hour runs');

        // Bound, with campaigns that are not live (ended, not started, switched off): paused all the same.
        $this->uiSettingCampaign($vend, ['end_at' => '2026-10-08 00:00:00']);
        $this->uiSettingCampaign($vend, ['start_at' => '2026-10-10 00:00:00']);
        $this->uiSettingCampaign($vend, ['is_active' => false]);
        $this->assertTrue(HappyHourPause::isPaused($vend->id, now()));
        $this->assertStringNotContainsString('live campaign', HappyHourPause::forVends([$vend->id], now())[$vend->id]['message']);

        $other = $this->vending(2246, []);
        $this->uiSettingCampaign($other, ['end_at' => '2026-10-09 00:00:00']);       // its last day is today
        $this->assertStringContainsString('until 9 Oct 2026)', HappyHourPause::forVends([$other->id], now())[$other->id]['message']);
        $this->assertFalse(HappyHourPause::isPaused($this->freezer()->id, now()), 'a freezer has no UI Setting');
    }

    // ── 2 + 5. Campaign page ─────────────────────────────────────────────────────────────────

    public function test_the_page_offers_eligible_vending_machines_and_warns_when_one_is_paused(): void
    {
        $eligible = $this->vending(2245, [11 => ['A', 160, 9, 10]]);
        $old = $this->vending(2246, [], 314);
        $small = $this->vending(2247, [], 315, 'ZC-83A');
        $this->uiSettingCampaign($eligible);
        $user = User::factory()->create(['operator_id' => 1]);
        foreach (['read', 'create', 'update', 'delete'] as $action) {
            $user->givePermissionTo(Permission::findOrCreate("{$action} happy-hour-campaigns", 'web'));
        }
        $form = fn (array $ids) => [
            'name' => 'Vending HH', 'is_active' => true, 'starts_on' => null, 'ends_on' => null, 'days_mask' => 127,
            'window_start' => '14:00', 'window_end' => '18:00', 'slot_minutes' => 60, 'sku_count' => 1, 'skus_per_slot' => 1,
            'selection_rule' => 'balance_pct', 'lookback_days' => 14, 'discount_pct' => 40, 'min_balance_pct' => 15,
            'min_qty' => 2, 'price_step_cents' => 10, 'allow_below_cost' => false, 'excluded_product_ids' => [], 'vend_ids' => $ids,
        ];

        $this->actingAs($user)->post('/happy-hour-campaigns', $form([$old->id]))->assertSessionHasErrors('vend_ids');
        $this->actingAs($user)->post('/happy-hour-campaigns', $form([$small->id]))->assertSessionHasErrors('vend_ids');
        // Paused is a warning, never a block.
        $this->actingAs($user)->post('/happy-hour-campaigns', $form([$eligible->id]))->assertSessionHasNoErrors();
        $campaign = HappyHourCampaign::firstOrFail();
        $this->assertSame(0, HappyHourSlot::count(), 'saved, but the paused machine gets no slot');

        $message = HappyHourPause::forVends([$eligible->id], now(), [$eligible->id => '2245'])[$eligible->id]['message'];
        $this->actingAs($user)->get('/happy-hour-campaigns')->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('minVendingApkVersion', 315)
                ->where('machines', fn ($machines) => collect($machines)->pluck('id')->all() === [$eligible->id])
                ->where('machines.0.paused', $message)
                ->where('campaigns.0.vends.0.supported', true)
                ->where('campaigns.0.vends.0.machine_type', Vend::MACHINE_TYPE_VENDING_MACHINE)
                ->where('campaigns.0.vends.0.paused', $message)
                ->where('products', fn ($products) => collect($products)->pluck('id')->all() === [$this->products['A']->id]));
        $this->actingAs($user)->getJson("/happy-hour-campaigns/{$campaign->id}/preview?vend_id={$eligible->id}")->assertOk()
            ->assertJsonPath('machines.0.paused', $message)
            ->assertJsonPath('machines.0.candidates.0.original_price', 160);

        // A bound machine that later drops below 315 stays bound on an edit and is shown why.
        $eligible->forceFill(['apk_ver_json' => ['apkver' => 314, 'deviceType' => 'ZC-328']])->save();
        $this->actingAs($user)->post("/happy-hour-campaigns/{$campaign->id}", $form([$eligible->id]))->assertSessionHasNoErrors();
        $this->actingAs($user)->get('/happy-hour-campaigns')->assertOk()
            ->assertInertia(fn ($page) => $page->where('campaigns.0.vends.0.supported', false)
                ->where('campaigns.0.vends.0.unsupported_reason', 'app 314 — needs 315 or later'));
    }

    // ── 6. /thumbnails ───────────────────────────────────────────────────────────────────────

    public function test_thumbnails_carry_the_sketch_and_each_channels_own_happy_hour_price_for_an_eligible_machine(): void
    {
        $vend = $this->vending(2245, [11 => ['A', 160, 5, 10], 12 => ['A', 140, 4, 10], 21 => ['B', 200, 1, 10]]);
        $this->campaign([$vend]);
        ProductWelcomeSketch::create([
            'product_id' => $this->products['A']->id, 'status' => ProductWelcomeSketch::STATUS_READY, 'source' => ProductWelcomeSketch::SOURCE_GENERATED,
            'url' => 'https://cdn.test/sketch-a.webp', 'generated_at' => Carbon::parse('2026-10-09 10:00:00'),
        ]);
        Carbon::setTestNow('2026-10-09 13:58:00');
        $this->planner()->run(now());
        $slot = HappyHourSlot::firstOrFail();
        // An ended slot of the same product is never sent.
        HappyHourSlot::create([
            'happy_hour_campaign_id' => $slot->happy_hour_campaign_id, 'vend_id' => $vend->id, 'product_id' => $this->products['A']->id,
            'slot_date' => '2026-10-09', 'position' => 9, 'starts_at' => '2026-10-09 12:00:00', 'ends_at' => '2026-10-09 13:00:00',
            'original_price' => 160, 'promo_price' => 90, 'discount_pct' => 40, 'rank' => 1, 'status' => HappyHourSlot::STATUS_ENDED,
        ]);

        $rows = collect($this->getJson('/api/vends/2245/thumbnails')->assertOk()->json())->keyBy('channel_code');
        $this->assertSame([[
            'slot_id' => $slot->id, 'starts_at' => '2026-10-09T14:00:00+08:00', 'ends_at' => '2026-10-09T15:00:00+08:00',
            'price' => 100, 'original_price' => 160, 'discount_pct' => 40, 'rank' => 1,
        ]], $rows[11]['happy_hour']);
        $this->assertSame([90, 140], [$rows[12]['happy_hour'][0]['price'], $rows[12]['happy_hour'][0]['original_price']], '40% of its own 140¢ (84¢), rounded up to 10¢');
        $this->assertSame([], $rows[21]['happy_hour']);
        $this->assertSame('https://cdn.test/sketch-a.webp?v='.Carbon::parse('2026-10-09 10:00:00')->timestamp, $rows[11]['welcome_sketch']);
        $this->assertNull($rows[21]['welcome_sketch']);

        // A channel whose own price the discount cannot lower is left without the slot.
        DB::table('vend_channels')->where('vend_id', $vend->id)->where('code', 12)->update(['amount' => 10]);
        $rows = collect($this->getJson('/api/vends/2245/thumbnails')->json())->keyBy('channel_code');
        $this->assertSame([], $rows[12]['happy_hour'], '40% of 10¢ rounds to 0: no promo on that channel');
    }

    public function test_thumbnails_for_a_machine_that_cannot_run_happy_hour_are_byte_identical(): void
    {
        $vend = $this->vending(2246, [11 => ['A', 160, 5, 10], 21 => ['B', 200, 4, 10]], 314);
        $before = $this->get('/api/vends/2246/thumbnails')->assertOk()->getContent();

        $campaign = $this->campaign([$vend]);
        HappyHourSlot::create([
            'happy_hour_campaign_id' => $campaign->id, 'vend_id' => $vend->id, 'product_id' => $this->products['A']->id,
            'slot_date' => '2026-10-09', 'position' => 0, 'starts_at' => '2026-10-09 14:00:00', 'ends_at' => '2026-10-09 15:00:00',
            'original_price' => 160, 'promo_price' => 90, 'discount_pct' => 40, 'rank' => 1, 'status' => HappyHourSlot::STATUS_SCHEDULED,
        ]);
        ProductWelcomeSketch::create(['product_id' => $this->products['A']->id, 'status' => 'ready', 'source' => 'generated', 'url' => 'https://cdn.test/a.webp']);

        $after = $this->get('/api/vends/2246/thumbnails')->assertOk()->getContent();
        $this->assertSame($before, $after);
        $this->assertSame([
            'vend_code', 'channel_code', 'product_id', 'product_code', 'product_name', 'product_desc', 'product_is_halal',
            'product_is_healthier_choice', 'product_nutri_grade', 'product_sub_category', 'product_volumn_weight', 'sequence',
            'thumbnail', 'server_price', 'labels',
        ], array_keys(json_decode($after, true)[0]));
    }

    // ── 7. Nudge ─────────────────────────────────────────────────────────────────────────────

    public function test_the_nudge_is_a_lightweight_happyhour_frame_for_vending_and_unchanged_for_freezers(): void
    {
        $vend = $this->vending(2245, [11 => ['A', 160, 9, 10]]);
        $old = $this->vending(2246, [], 314);
        $freezer = $this->freezer();

        Queue::fake();
        foreach ([$vend, $old, $freezer] as $machine) {
            (new NudgeFreezerMenu($machine->id))->handle(app(VendJobService::class));
        }
        $frames = $this->publishedFrames();
        $this->assertCount(2, $frames, 'nothing for the 314 board');
        [$topic, $body, $qos] = $frames[0];
        $this->assertSame(['CM2245', 1], [$topic, $qos]);
        $this->assertSame(['Type' => 'HAPPYHOUR', 'time' => now()->timestamp, 'action' => '', 'mid' => 2245], $body);
        $this->assertSame(['CM50001', 'TYPESYNCAPICHANNELSLOTLIST'], [$frames[1][0], $frames[1][1]['Type']], 'the freezer keeps its menu nudge');

        // The planner's slot writes dispatch it.
        Queue::fake();
        $this->campaign([$vend]);
        Carbon::setTestNow('2026-10-09 13:58:00');
        $this->planner()->run(now());
        Queue::assertPushed(NudgeFreezerMenu::class, fn ($job) => $job->vendId === $vend->id);
    }

    // ── 8. Sale tagging ──────────────────────────────────────────────────────────────────────

    public function test_a_vending_sale_is_tagged_only_by_its_hh_list_inside_the_slot(): void
    {
        $vend = $this->vending(2245, [11 => ['A', 160, 9, 10], 12 => ['A', 140, 9, 10], 21 => ['B', 200, 2, 10]]);
        $other = $this->vending(2246, [11 => ['A', 160, 9, 10]]);
        $this->campaign([$vend, $other]);
        Carbon::setTestNow('2026-10-09 13:58:00');
        $this->planner()->run(now());
        $slot = HappyHourSlot::where('vend_id', $vend->id)->firstOrFail();
        $otherSlot = HappyHourSlot::where('vend_id', $other->id)->firstOrFail();
        $a = $this->products['A']->id;
        $b = $this->products['B']->id;

        $tagOf = fn (VendTransaction $t) => $t->fresh()->happy_hour_slot_id === null ? null : (int) $t->fresh()->happy_hour_slot_id;
        $this->assertSame($slot->id, $tagOf($this->sale($vend, $a, 11, 90, '2026-10-09 14:10:00', [['SId' => 11, 'HHId' => $slot->id]])));
        $this->assertSame($slot->id, $tagOf($this->sale($vend, $a, 12, 80, '2026-10-09 15:04:00', [['SId' => '12', 'HHId' => (string) $slot->id]])), 'string numbers, inside the grace');
        $this->assertNull($tagOf($this->sale($vend, $a, 11, 90, '2026-10-09 14:11:00')), 'keypad: same cents, no HH list — never tagged by price');
        $this->assertNull($tagOf($this->sale($vend, $a, 11, 90, '2026-10-09 15:06:00', [['SId' => 11, 'HHId' => $slot->id]])), 'past the slot + grace');
        $this->assertNull($tagOf($this->sale($vend, $a, 11, 90, '2026-10-09 14:12:00', [['SId' => 11, 'HHId' => $otherSlot->id]])), "another machine's slot");
        $this->assertNull($tagOf($this->sale($vend, $b, 21, 120, '2026-10-09 14:13:00', [['SId' => 21, 'HHId' => $slot->id]])), "the slot's product is not this line's");
        $this->assertNull($tagOf($this->sale($vend, $a, 11, 90, '2026-10-09 14:14:00', [['SId' => 12, 'HHId' => $slot->id]])), 'the HH list names another channel');
        foreach (['junk', [null], [['SId' => 11]], [['HHId' => $slot->id]], [['SId' => -11, 'HHId' => $slot->id]], [['SId' => 11, 'HHId' => 'x']], [['SId' => 1.5, 'HHId' => $slot->id]], [[['nested']]]] as $malformed) {
            $this->assertNull($tagOf($this->sale($vend, $a, 11, 90, '2026-10-09 14:15:00', $malformed)), 'malformed HH: ignored, never thrown');
        }

        // A basket: only the named line is the slot's; the header says it holds a promo unit.
        $basket = $this->basket($vend, [[$a, 11, 90], [$b, 21, 200]], '2026-10-09 14:20:00', [['SId' => 11, 'HHId' => $slot->id]]);
        $this->assertSame($slot->id, $tagOf($basket));
        $this->assertSame([$slot->id, null], DB::table('vend_transaction_items')->where('vend_transaction_id', $basket->id)
            ->orderBy('id')->pluck('happy_hour_slot_id')->map(fn ($v) => $v === null ? null : (int) $v)->all());

        // The close counts the tagged units, takings and each line's discount off its own channel price.
        Carbon::setTestNow('2026-10-09 15:10:00');
        $this->planner()->run(now());
        $slot->refresh();
        $this->assertSame([3, 260, (160 - 90) + (140 - 80) + (160 - 90)], [$slot->units_sold, $slot->revenue_cents, $slot->discount_cents]);
    }

    public function test_a_basket_stored_at_board_prices_is_repriced_to_what_the_screen_charged(): void
    {
        // A vending TRADE has one Price for the basket and none per line, so the ingest stores each item at
        // its board price (2378, 2026-10-15: 3 × $3.40 items under a $7.20 sale → "L24h $0.00").
        $vend = $this->vending(2245, [11 => ['A', 160, 9, 10], 21 => ['B', 200, 2, 10]]);
        $this->campaign([$vend]);
        Carbon::setTestNow('2026-10-09 13:58:00');
        $this->planner()->run(now());
        $slot = HappyHourSlot::where('vend_id', $vend->id)->firstOrFail();
        $a = $this->products['A']->id;
        $b = $this->products['B']->id;
        $promo = HappyHourPricing::promoPrice(160, 40, 10);
        $prices = fn (VendTransaction $t) => DB::table('vend_transaction_items')->where('vend_transaction_id', $t->id)
            ->orderBy('id')->pluck('unit_price_amount')->map(fn ($v) => (int) $v)->all();

        $basket = $this->basket($vend, [[$a, 11, 160], [$b, 21, 200]], '2026-10-09 14:20:00', [['SId' => 11, 'HHId' => $slot->id]], $promo + 200);
        $this->assertSame([$promo, 200], $prices($basket), 'the promo line now carries the promo price; the other keeps its own');

        // Another discount in the basket: the items cannot be made to add up, so nothing is guessed.
        $odd = $this->basket($vend, [[$a, 11, 160], [$b, 21, 200]], '2026-10-09 14:25:00', [['SId' => 11, 'HHId' => $slot->id]], 250);
        $this->assertSame([160, 200], $prices($odd));

        // Baskets tagged before the fix: the repair command lists, then rewrites and re-opens the slot.
        $old = $this->basket($vend, [[$a, 11, 160], [$a, 11, 160]], '2026-10-09 14:30:00', [], 2 * $promo);
        DB::table('vend_transactions')->where('id', $old->id)->update(['happy_hour_slot_id' => $slot->id]);
        DB::table('vend_transaction_items')->where('vend_transaction_id', $old->id)->update(['happy_hour_slot_id' => $slot->id]);
        Carbon::setTestNow('2026-10-09 15:10:00');
        $this->planner()->run(now());
        $this->assertNotNull($slot->fresh()->units_sold);

        $this->artisan('happy-hour:reprice-vending-baskets', ['--since' => '2026-10-09'])->assertSuccessful();
        $this->assertSame([160, 160], $prices($old), 'a dry run writes nothing');
        $this->artisan('happy-hour:reprice-vending-baskets', ['--since' => '2026-10-09', '--apply' => true])->assertSuccessful();
        $this->assertSame([$promo, $promo], $prices($old));
        $this->assertSame([160, 200], $prices($odd), 'still left alone');
        $this->assertNull($slot->fresh()->units_sold, 'the slot is recounted by the next run');

        // The slot's discount now counts each repriced unit off its board price; the odd basket's line is
        // still the slot's unit, at the price it could not be proven to have charged (no discount claimed).
        $this->planner()->run(now());
        $slot->refresh();
        $this->assertSame([4, 3 * $promo + 160, 3 * (160 - $promo)], [$slot->units_sold, $slot->revenue_cents, $slot->discount_cents]);
    }

    public function test_the_trade_ingest_tags_a_touchscreen_sale_from_its_hh_list(): void
    {
        PaymentMethod::firstOrCreate(['code' => 0], ['name' => 'Cash', 'is_active' => true]);
        VendChannelError::firstOrCreate(['code' => 0], ['desc' => 'No Malfunction (0)']);
        $vend = $this->vending(2245, [11 => ['A', 160, 9, 10], 21 => ['B', 200, 2, 10]]);
        $this->campaign([$vend]);
        Carbon::setTestNow('2026-10-09 13:58:00');
        $this->planner()->run(now());
        $slot = HappyHourSlot::firstOrFail();

        Carbon::setTestNow('2026-10-09 14:05:00');
        $trade = fn (string $orderId, array $extra) => app(VendTransactionService::class)->create($vend->fresh(), array_merge([
            'ORDRID' => $orderId, 'PAY_TYPE' => 0, 'TIME' => now()->format('Y-m-d H:i:s'), 'SErr' => 0, 'SId' => 11,
            'Price' => '0.90', 'TXN_SRC' => 1,
            'transf_info' => [['SId' => 11, 'SErr' => 0, 'Price' => 90]],
        ], $extra));
        $trade('26100914050052245', ['HH' => [['SId' => 11, 'HHId' => $slot->id]]]);
        $trade('26100914050152245', []);
        $trade('26100914050252245', ['HH' => 'not a list']);
        $trade('26100914050352245', ['Price' => '2.90', 'transf_info' => [['SId' => 11, 'SErr' => 0, 'Price' => 90], ['SId' => 21, 'SErr' => 0, 'Price' => 200]],
            'HH' => [['SId' => 11, 'HHId' => $slot->id], 'garbage']]);

        // As prod sends it (2378, 2026-10-15): one Price for the basket, no price per line — the items are
        // stored at board price, then the Happy Hour line is repriced to what the screen charged.
        $promo = HappyHourPricing::promoPrice(160, 40, 10);
        $trade('26100914050452245', ['Price' => number_format(($promo + 200) / 100, 2, '.', ''),
            'transf_info' => [['SId' => 11, 'SErr' => 0], ['SId' => 21, 'SErr' => 0]],
            'HH' => [['SId' => 11, 'HHId' => $slot->id]]]);
        $real = VendTransaction::withoutGlobalScopes()->where('order_id', '26100914050452245')->firstOrFail();
        $this->assertSame([$promo, 200], DB::table('vend_transaction_items')->where('vend_transaction_id', $real->id)
            ->orderBy('id')->pluck('unit_price_amount')->map(fn ($v) => (int) $v)->all());

        $this->assertSame(
            ['26100914050052245' => $slot->id, '26100914050152245' => null, '26100914050252245' => null, '26100914050352245' => $slot->id, '26100914050452245' => $slot->id],
            VendTransaction::withoutGlobalScopes()->orderBy('id')->get(['order_id', 'happy_hour_slot_id'])
                ->mapWithKeys(fn ($t) => [$t->order_id => $t->happy_hour_slot_id === null ? null : (int) $t->happy_hour_slot_id])->all()
        );
    }

    // ── Freezer regression ───────────────────────────────────────────────────────────────────

    public function test_the_freezer_menu_and_price_tagging_are_unchanged(): void
    {
        $freezer = $this->freezer();
        $campaign = $this->campaign([$freezer]);
        Carbon::setTestNow('2026-10-09 13:58:00');
        $this->planner()->run(now());
        $slot = HappyHourSlot::firstOrFail();
        $this->assertSame([400, 240], [$slot->original_price, $slot->promo_price], 'Site tier, as before');

        $row = $this->getJson('/api/vends/50001/menu')->assertOk()->json()[0];
        $this->assertSame([
            'vend_code', 'channel_code', 'product_id', 'product_code', 'product_name', 'product_desc', 'product_is_halal',
            'product_is_healthier_choice', 'product_nutri_grade', 'product_sub_category', 'product_volumn_weight', 'sequence',
            'thumbnail', 'server_price', 'labels', 'welcome_sketch', 'happy_hour',
        ], array_keys($row));
        $this->assertSame([[
            'slot_id' => $slot->id, 'starts_at' => '2026-10-09T14:00:00+08:00', 'ends_at' => '2026-10-09T15:00:00+08:00',
            'price' => 240, 'original_price' => 400, 'discount_pct' => 40, 'rank' => 1,
        ]], $row['happy_hour']);

        // Price is still the proof for a freezer; an HH list on its frame changes nothing.
        $id = DB::table('vend_transactions')->insertGetId([
            'vend_id' => $freezer->id, 'order_id' => 'FZ-1', 'transaction_datetime' => '2026-10-09 14:10:00', 'amount' => 240,
            'product_id' => $this->products['FZ']->id, 'qty' => 1, 'is_multiple' => 0,
            'settlement_status' => VendTransaction::SETTLEMENT_SETTLED, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $sale = VendTransaction::withoutGlobalScopes()->findOrFail($id);
        $this->assertSame(1, app(HappyHourSales::class)->tag($freezer, $sale, ['HH' => [['SId' => 11, 'HHId' => 999999]]]));
        $this->assertSame($slot->id, (int) $sale->fresh()->happy_hour_slot_id);

        // A UI Setting campaign machinery never touches a freezer (it has none).
        $this->assertSame(0, $this->planner()->run(now())['paused_machines']);
        $this->assertTrue($campaign->is_active);
    }

    // ── 9. Welcome sketch sweep ──────────────────────────────────────────────────────────────

    public function test_the_hourly_sweep_covers_welcome_scene_vending_products_a_few_at_a_time(): void
    {
        $this->app->instance(SketchGenerator::class, new class implements SketchGenerator
        {
            public function isConfigured(): bool
            {
                return true;
            }

            public function model(): string
            {
                return 'fake';
            }

            public function generate(string $photo, string $productName, array $styleReferences, ?string $instructions = null): string
            {
                return '';
            }
        });
        $this->app->instance(CutoutMaker::class, new class implements CutoutMaker
        {
            public function isAvailable(): bool
            {
                return false;
            }

            public function name(): string
            {
                return 'none';
            }

            public function cutout(string $photo): string
            {
                return '';
            }
        });
        config(['happy_hour.vending_sketch_sweep_limit' => 2]);

        $inCampaign = $this->vending(2245, [11 => ['A', 160, 9, 10], 12 => ['B', 160, 9, 10], 13 => ['C', 160, 9, 10]]);
        $this->campaign([$inCampaign]);                                   // no UI Setting: served the bear
        $scene = $this->vending(2246, [21 => ['D', 160, 9, 10]], 220);    // old app, but its UI Setting shows the scene
        $this->vending(2247, [31 => ['E', 160, 9, 10]], 220);             // neither: never drawn
        $this->vending(2248, [41 => ['F', 160, 9, 10]]);                  // app 315+ alone (2026-10-14): bear off, never drawn
        DB::table('vend_channels')->insert([                              // a retired channel on a scene machine: not drawn
            'vend_id' => $scene->id, 'code' => 22, 'product_id' => Product::forceCreate(['code' => 'G', 'name' => 'Old', 'operator_id' => 1])->id,
            'amount' => 160, 'qty' => 0, 'capacity' => 10, 'is_active' => 0,
        ]);
        $settingId = DB::table('apk_settings')->insertGetId([
            'name' => 'Scene on', 'settings_parameter_json' => json_encode(['enableWelcomeScene' => 'true']), 'operator_id' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('apk_setting_vend')->insert(['apk_setting_id' => $settingId, 'vend_id' => $scene->id]);
        $this->freezer();
        foreach ($this->products as $product) {
            $product->thumbnail()->create(['type' => 1, 'full_url' => "https://cdn.test/{$product->code}.png", 'local_url' => "https://cdn.test/{$product->code}.png"]);
        }

        $service = app(ProductWelcomeSketchService::class);
        $this->assertEqualsCanonicalizing(
            ['A', 'B', 'C', 'D'],
            ProductWelcomeSketchService::welcomeVendingProductIds()->map(fn ($id) => Product::find($id)->code)->all()
        );
        $code = fn (array $ids) => collect($ids)->map(fn ($id) => Product::find($id)->code)->sort()->values()->all();
        $this->assertSame(['A', 'B', 'FZ'], $code($service->queueMissingForFreezers()), 'freezer products always, vending 2 a run');
        $this->assertSame(['C', 'D'], $code($service->queueMissingForFreezers()), 'the next run takes the next ones');
        $this->assertSame([], $service->queueMissingForFreezers());
    }

    // ── 10. UI Setting key ───────────────────────────────────────────────────────────────────

    public function test_enable_welcome_scene_is_a_bool_setting_served_on_parameters(): void
    {
        $vend = $this->vending(2245, []);
        $settingId = DB::table('apk_settings')->insertGetId([
            'name' => 'Old row', 'settings_parameter_json' => json_encode(['bannerKind' => 'video']), 'operator_id' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('apk_setting_vend')->insert(['apk_setting_id' => $settingId, 'vend_id' => $vend->id]);

        $this->assertSame('false', $this->getJson('/api/vends/2245/parameters/315')->assertOk()->json('enableWelcomeScene'), 'an old row defaults to off');

        DB::table('apk_settings')->where('id', $settingId)->update(['settings_parameter_json' => json_encode(['enableWelcomeScene' => true])]);
        $this->assertSame('true', $this->getJson('/api/vends/2245/parameters/315')->json('enableWelcomeScene'));
    }

    public function test_thumbnails_carry_the_happy_hour_key_only_while_the_machine_is_in_an_active_campaign(): void
    {
        $vend = $this->vending(2245, [11 => ['A', 160, 9, 10]]);
        $row = $this->getJson('/api/vends/2245/thumbnails')->assertOk()->json()[0];
        $this->assertArrayHasKey('welcome_sketch', $row, 'app 315+ still gets its sketch');
        // APK 316 re-reads this feed on every MQTT reconnect only when the key is there.
        $this->assertArrayNotHasKey('happy_hour', $row, 'no campaign: no key, so no reconnect re-reads');

        $campaign = $this->campaign([$vend]);
        $this->assertSame([], $this->getJson('/api/vends/2245/thumbnails')->json()[0]['happy_hour'], 'in a campaign: the key, even with no slot yet');

        $campaign->update(['is_active' => false]);
        $this->assertArrayNotHasKey('happy_hour', $this->getJson('/api/vends/2245/thumbnails')->json()[0]);
    }

    public function test_an_unbound_happy_hour_machine_gets_the_default_settings_with_the_bear_on(): void
    {
        $vend = $this->vending(2245, []);
        $this->getJson('/api/vends/2245/parameters/315')->assertStatus(400); // no UI Setting, no campaign: as before

        $campaign = $this->campaign([$vend]);
        $params = $this->getJson('/api/vends/2245/parameters/315')->assertOk()->json();
        $this->assertSame('true', $params['enableWelcomeScene']);
        // A COMPLETE payload: the APK writes every field it receives, so a partial one would reset the rest.
        foreach (ApkSettingParameters::defaults()->toArray() as $key => $default) {
            $this->assertArrayHasKey($key, $params, "default {$key} is served");
            if ($key !== 'enableWelcomeScene' && $key !== 'selectedPricingSource') {
                $this->assertSame($default, $params[$key], "{$key} is the schema default");
            }
        }
        $this->assertSame([], $params['campaigns'], 'no UI Setting campaigns');

        // Leaving the campaign turns the scene off ONCE, then the machine is unbound as before.
        $campaign->update(['is_active' => false]);
        $this->assertSame('false', $this->getJson('/api/vends/2245/parameters/315')->assertOk()->json('enableWelcomeScene'));
        $this->getJson('/api/vends/2245/parameters/315')->assertStatus(400);
    }

    public function test_the_fallback_needs_an_eligible_board_and_never_overrides_a_bound_ui_setting(): void
    {
        $old = $this->vending(2246, [], 314);
        $small = $this->vending(2247, [], 315, 'ZC-83A');
        $bound = $this->vending(2248, []);
        $this->campaign([$old, $small, $bound]);
        $this->getJson('/api/vends/2246/parameters/314')->assertStatus(400);
        $this->getJson('/api/vends/2247/parameters/315')->assertStatus(400);

        $settingId = DB::table('apk_settings')->insertGetId([
            'name' => 'Bear off', 'settings_parameter_json' => json_encode(['enableWelcomeScene' => 'false', 'poweredBy' => 'Ours']),
            'operator_id' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('apk_setting_vend')->insert(['apk_setting_id' => $settingId, 'vend_id' => $bound->id]);
        $params = $this->getJson('/api/vends/2248/parameters/315')->assertOk()->json();
        $this->assertSame(['false', 'Ours'], [$params['enableWelcomeScene'], $params['poweredBy']], 'the UI Setting wins');
    }

    public function test_saving_or_deleting_a_campaign_tells_its_unbound_machines_to_reread_settings(): void
    {
        $unbound = $this->vending(2245, [11 => ['A', 160, 9, 10]]);
        $bound = $this->vending(2246, [11 => ['A', 160, 9, 10]]);
        $settingId = DB::table('apk_settings')->insertGetId([
            'name' => 'Has one', 'settings_parameter_json' => json_encode([]), 'operator_id' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('apk_setting_vend')->insert(['apk_setting_id' => $settingId, 'vend_id' => $bound->id]);
        $user = User::factory()->create(['operator_id' => 1]);
        foreach (['read', 'create', 'update', 'delete'] as $action) {
            $user->givePermissionTo(Permission::findOrCreate("{$action} happy-hour-campaigns", 'web'));
        }
        $form = fn (array $ids) => [
            'name' => 'Vending HH', 'is_active' => true, 'starts_on' => null, 'ends_on' => null, 'days_mask' => 127,
            'window_start' => '14:00', 'window_end' => '18:00', 'slot_minutes' => 60, 'sku_count' => 1, 'skus_per_slot' => 1,
            'selection_rule' => 'balance_pct', 'lookback_days' => 14, 'discount_pct' => 40, 'min_balance_pct' => 15,
            'min_qty' => 2, 'price_step_cents' => 10, 'allow_below_cost' => false, 'excluded_product_ids' => [], 'vend_ids' => $ids,
        ];
        $settingsSyncs = function (): array {
            $topics = [];
            foreach (Queue::pushed(PublishMqtt::class) as $job) {
                [$topic, $wire] = (fn () => [$this->topic, $this->message])->call($job);
                [, , $b64] = explode(',', $wire);
                if ((json_decode(base64_decode($b64), true)['Type'] ?? null) === 'TYPESYNCSETTINGSPARAM') {
                    $topics[] = $topic;
                }
            }

            return $topics;
        };

        $this->actingAs($user)->post('/happy-hour-campaigns', $form([$unbound->id, $bound->id]))->assertSessionHasNoErrors();
        $this->assertSame(['CM2245'], $settingsSyncs(), 'only the machine with no UI Setting is told');

        $campaign = HappyHourCampaign::firstOrFail();
        $this->actingAs($user)->post("/happy-hour-campaigns/{$campaign->id}", $form([$bound->id]))->assertSessionHasNoErrors();
        $this->assertSame(['CM2245', 'CM2245'], $settingsSyncs(), 'taken out: told again, so the bear goes off');
    }

    /** A vending single sale (header only), tagged as the ingest does. */
    private function sale(Vend $vend, int $productId, int $channel, int $cents, string $at, mixed $hh = null): VendTransaction
    {
        $frame = ['SId' => $channel, 'Price' => $cents] + ($hh === null ? [] : ['HH' => $hh]);
        $id = DB::table('vend_transactions')->insertGetId([
            'vend_id' => $vend->id, 'order_id' => 'VH-'.uniqid(), 'transaction_datetime' => $at, 'amount' => $cents,
            'product_id' => $productId, 'vend_channel_code' => $channel, 'qty' => 1, 'is_multiple' => 0,
            'settlement_status' => VendTransaction::SETTLEMENT_SETTLED, 'vend_transaction_json' => json_encode($frame),
            'created_at' => $at, 'updated_at' => $at,
        ]);
        $sale = VendTransaction::withoutGlobalScopes()->findOrFail($id);
        app(HappyHourSales::class)->tag($vend, $sale, $frame);

        return $sale;
    }

    /** @param  list<array{0: int, 1: int, 2: int}>  $lines  product, channel, cents */
    private function basket(Vend $vend, array $lines, string $at, array $hh, ?int $amount = null): VendTransaction
    {
        $id = DB::table('vend_transactions')->insertGetId([
            'vend_id' => $vend->id, 'order_id' => 'VH-'.uniqid(), 'transaction_datetime' => $at,
            'amount' => $amount ?? array_sum(array_column($lines, 2)), 'qty' => count($lines), 'is_multiple' => 1,
            'settlement_status' => VendTransaction::SETTLEMENT_SETTLED, 'created_at' => $at, 'updated_at' => $at,
        ]);
        foreach ($lines as [$productId, $channel, $cents]) {
            DB::table('vend_transaction_items')->insert([
                'vend_transaction_id' => $id, 'product_id' => $productId, 'vend_channel_code' => $channel, 'unit_price_amount' => $cents, 'created_at' => $at,
            ]);
        }
        $sale = VendTransaction::withoutGlobalScopes()->findOrFail($id);
        app(HappyHourSales::class)->tag($vend, $sale, ['HH' => $hh]);

        return $sale;
    }
}
