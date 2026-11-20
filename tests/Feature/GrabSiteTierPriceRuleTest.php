<?php

namespace Tests\Feature;

use App\Http\Requests\Grab\GrabPriceRuleRequest;
use App\Models\DeliveryPlatformRefNumber;
use App\Models\DeliveryProductMappingVend;
use App\Models\GrabMenuProduct;
use App\Models\GrabMenuSetting;
use App\Models\GrabStorePriceRule;
use App\Models\User;
use App\Models\Vend;
use App\Models\VendChannel;
use App\Services\DeliveryPlatformService;
use App\Services\Grab\Menu\GrabMenuItemId;
use App\Services\Grab\Menu\GrabMenuOverview;
use App\Services\Grab\Menu\GrabMenuService;
use App\Services\Grab\Menu\GrabPriceRule;
use App\Services\GrabStoreService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use InvalidArgumentException;
use Spatie\Permission\Models\Permission;
use Tests\Support\Grab\BuildsGrabStore;
use Tests\TestCase;

/**
 * A multiplier per RP tier (Brian, 2026-11-21: "allow user to set each RP with multiplier" — RP3 is already dearer,
 * so RP3 × 1.25 is too high). Under "Each store's Site RP" a Grab store is priced from its machine's Site tier with
 * that tier's multiplier; a product with no price on the Site's tier, or a Site with no tier, uses RP2 and RP2's
 * multiplier, so it stays on Grab. A rule that names one tier is unchanged.
 */
class GrabSiteTierPriceRuleTest extends TestCase
{
    use BuildsGrabStore;
    use RefreshDatabase;

    private const RP2_STORE = '4-C7RP2RP2RP2RP2';

    private const RP3_STORE = '4-C7RP3RP3RP3RP3';

    private const NO_TIER_STORE = '4-C7NONENONENONE';

    private const SUB = ['id' => 'SGITEDP20200707065112014691', 'name' => 'Ice Cream Sticks'];

    private int $operatorId;

    private int $u49;

    private int $u26;

    /** @var array<string, DeliveryProductMappingVend> */
    private array $stores = [];

    protected function setUp(): void
    {
        parent::setUp();

        config(['grab.webhook_auth' => 'off']);
        Queue::fake();
        $this->buildGrabOperator();
        $this->operatorId = $this->grabMapping->operator_id;
        $this->u49 = $this->grabItem('U-49', 11, 280)->product_id;
        $this->u26 = $this->grabItem('U-26', 12, 500)->product_id;

        foreach ([self::RP2_STORE => [4761, 2], self::RP3_STORE => [4762, 3], self::NO_TIER_STORE => [4763, null]] as $merchantId => [$code, $tier]) {
            $binding = $this->bindGrabStore($code, $merchantId, [11 => ['qty' => 3, 'capacity' => 10], 12 => ['qty' => 3, 'capacity' => 10]]);
            VendChannel::query()->where('vend_id', $binding->vend_id)->where('code', 11)->update(['product_id' => $this->u49]);
            VendChannel::query()->where('vend_id', $binding->vend_id)->where('code', 12)->update(['product_id' => $this->u26]);
            // The third machine sits on no Site, so it has no tier.
            if ($tier !== null) {
                $siteId = DB::table('customers')->insertGetId(['code' => 'S'.$code, 'name' => 'Site '.$code, 'selling_price_type' => $tier, 'created_at' => now(), 'updated_at' => now()]);
                Vend::query()->withoutGlobalScopes()->whereKey($binding->vend_id)->update(['customer_id' => $siteId]);
            }
            $this->stores[$merchantId] = $binding;
        }

        // U-49: RP2 $3.20, RP3 $3.70. U-26: RP2 $4.70 only (no RP3 price).
        DB::table('selling_prices')->insert([
            ['product_id' => $this->u49, 'type' => 2, 'amount' => 320, 'created_at' => now(), 'updated_at' => now()],
            ['product_id' => $this->u49, 'type' => 3, 'amount' => 370, 'created_at' => now(), 'updated_at' => now()],
            ['product_id' => $this->u26, 'type' => 2, 'amount' => 470, 'created_at' => now(), 'updated_at' => now()],
        ]);
        foreach ([$this->u49, $this->u26] as $productId) {
            GrabMenuProduct::create(['operator_id' => $this->operatorId, 'product_id' => $productId, 'sub_category_json' => self::SUB]);
        }

        // Today's rule: RP2 × 1.25 for every store.
        app(GrabMenuService::class)->saveSetting($this->operatorId, ['pricing_mode' => 'rp_multiplier', 'rp_type' => 2, 'multiplier_bp' => 12500, 'round_up_cents' => 10], null);
        foreach (array_keys($this->stores) as $merchantId) {
            app(GrabStoreService::class)->setMenuSource($this->ref($merchantId), true, null);
        }
    }

    private function ref(string $merchantId): DeliveryPlatformRefNumber
    {
        return DeliveryPlatformRefNumber::query()->where('ref_number', $merchantId)->sole();
    }

    /** @return array<int, int> Grab price by product id, as the menu Grab pulls has it */
    private function prices(string $merchantId): array
    {
        $menu = app(DeliveryPlatformService::class)->buildMenu($this->stores[$merchantId]->fresh());

        return collect($menu['categories'][0]['subCategories'] ?? [])->flatMap(fn ($sub) => $sub['items'])
            ->mapWithKeys(fn ($item) => [GrabMenuItemId::productId($item['id']) => $item['price']])
            ->sortKeys()
            ->all();
    }

    private function staff(): User
    {
        foreach (['read', 'update'] as $verb) {
            Permission::findOrCreate("{$verb} delivery-platform-product-mappings", 'web');
        }
        $user = User::factory()->create(['operator_id' => $this->operatorId]);
        $user->givePermissionTo(['read delivery-platform-product-mappings', 'update delivery-platform-product-mappings']);

        return $user;
    }

    private function siteTierRule(array $multipliers = ['1' => '1.25', '2' => '1.25', '3' => '1', '4' => '1', '5' => '1']): array
    {
        return ['pricing_mode' => 'rp_multiplier', 'rp_type' => GrabPriceRule::SITE_TIER, 'multiplier' => '1.25', 'tier_multipliers' => $multipliers, 'round_up_cents' => 10];
    }

    public function test_one_named_tier_still_prices_every_store_alike(): void
    {
        foreach (array_keys($this->stores) as $merchantId) {
            $this->assertSame([$this->u49 => 400, $this->u26 => 590], $this->prices($merchantId), $merchantId);
        }
    }

    public function test_each_store_takes_its_site_tier_and_that_tiers_multiplier_saved_from_the_page(): void
    {
        $this->actingAs($this->staff())->post("/grab-menu/{$this->operatorId}/setting", $this->siteTierRule())->assertSessionHasNoErrors();

        $setting = GrabMenuSetting::forOperator($this->operatorId);
        $this->assertSame(GrabPriceRule::SITE_TIER, $setting->rp_type);
        $this->assertSame(12500, $setting->multiplier_bp, 'the rule\'s own multiplier is RP2\'s');
        $this->assertEquals([1 => 12500, 2 => 12500, 3 => 10000, 4 => 10000, 5 => 10000], $setting->tier_multipliers_json);

        // RP2 site: RP2 × 1.25. RP3 site: U-49 RP3 $3.70 × 1; U-26 has no RP3, so RP2 $4.70 × 1.25 = $5.90.
        $this->assertSame([$this->u49 => 400, $this->u26 => 590], $this->prices(self::RP2_STORE));
        $this->assertSame([$this->u49 => 370, $this->u26 => 590], $this->prices(self::RP3_STORE));
        // A machine with no Site (so no tier) is priced as RP2.
        $this->assertSame([$this->u49 => 400, $this->u26 => 590], $this->prices(self::NO_TIER_STORE));
    }

    public function test_an_rp_price_rule_on_the_site_tier_takes_the_sites_rp_as_it_is(): void
    {
        app(GrabMenuService::class)->saveSetting($this->operatorId, GrabPriceRuleRequest::valuesFrom(['pricing_mode' => 'rp', 'rp_type' => 0, 'round_up_cents' => 10]), null);

        $this->assertSame([$this->u49 => 370, $this->u26 => 470], $this->prices(self::RP3_STORE));
        $this->assertSame([$this->u49 => 320, $this->u26 => 470], $this->prices(self::RP2_STORE));
        $this->assertNull(GrabMenuSetting::forOperator($this->operatorId)->tier_multipliers_json, 'only a multiplier rule keeps tiers');
    }

    public function test_a_stores_own_rule_can_read_its_site_tier_too(): void
    {
        $ref = $this->ref(self::RP3_STORE);
        $this->actingAs($this->staff())->post("/grab-menu/{$this->operatorId}/store-price-rule", [
            'ref_ids' => [$ref->id], 'use_company_rule' => false, ...$this->siteTierRule(['1' => '1.25', '2' => '1.25', '3' => '1.1', '4' => '1', '5' => '1']), 'note' => 'RP3 mall',
        ])->assertSessionHasNoErrors();

        $row = GrabStorePriceRule::query()->where('delivery_platform_ref_number_id', $ref->id)->sole();
        $this->assertSame(11000, $row->tier_multipliers_json[3]);
        // U-49: RP3 $3.70 × 1.1 = $4.07 → $4.10. U-26: no RP3 → RP2 $4.70 × 1.25 = $5.90.
        $this->assertSame([$this->u49 => 410, $this->u26 => 590], $this->prices(self::RP3_STORE));
        $this->assertSame([$this->u49 => 400, $this->u26 => 590], $this->prices(self::RP2_STORE), 'the company rule is untouched');
    }

    public function test_the_store_preview_names_the_tier_each_price_comes_from(): void
    {
        $response = $this->actingAs($this->staff())
            ->getJson('/grab-menu/stores/'.$this->ref(self::RP3_STORE)->id.'/preview?'.http_build_query($this->siteTierRule()))
            ->assertOk()
            ->assertJsonPath('rule.rp_type', 0)
            ->assertJsonPath('rule.tier_multipliers.3', 1);
        $rows = collect($response->json('rows'))->keyBy('product_id');
        $this->assertSame(['rp' => 370, 'rp_tier' => 3, 'after' => 370], collect($rows[$this->u49])->only(['rp', 'rp_tier', 'after'])->all());
        $this->assertSame(['rp' => 470, 'rp_tier' => 2, 'after' => 590], collect($rows[$this->u26])->only(['rp', 'rp_tier', 'after'])->all());
        $this->assertSame(0, GrabStorePriceRule::count(), 'a draft saves nothing');
    }

    public function test_the_products_table_lists_a_price_per_tier_the_stores_use(): void
    {
        app(GrabMenuService::class)->saveSetting($this->operatorId, GrabPriceRuleRequest::valuesFrom($this->siteTierRule()), null);

        $product = collect((new GrabMenuOverview($this->operatorId))->products())->firstWhere('product_id', $this->u49);
        $this->assertSame([
            ['tier' => 2, 'rp_tier' => 2, 'rp' => 320, 'grab_price' => 400],
            ['tier' => 3, 'rp_tier' => 3, 'rp' => 370, 'grab_price' => 370],
        ], $product['tier_prices']);
        $this->assertSame(400, $product['grab_price']);
        $stores = collect((new GrabMenuOverview($this->operatorId))->stores())->keyBy('merchant_id');
        $this->assertSame(3, $stores[self::RP3_STORE]['site_tier']);
        $this->assertNull($stores[self::NO_TIER_STORE]['site_tier']);
    }

    public function test_a_tier_multiplier_out_of_range_is_refused(): void
    {
        $this->actingAs($this->staff())
            ->post("/grab-menu/{$this->operatorId}/setting", $this->siteTierRule(['1' => '1', '2' => '1.25', '3' => '9', '4' => '1', '5' => '1']))
            ->assertSessionHasErrors('tier_multipliers.3');

        $this->expectException(InvalidArgumentException::class);
        new GrabPriceRule(mode: \App\Services\Grab\Menu\GrabPricingMode::RpMultiplier, rpType: 0, multiplierBp: 12500, roundUpCents: 10,
            scope: \App\Services\Grab\Menu\GrabPriceRuleScope::Company, operatorId: $this->operatorId, tierMultipliersBp: [3 => 1000]);
    }
}
