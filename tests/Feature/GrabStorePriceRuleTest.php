<?php

namespace Tests\Feature;

use App\Http\Requests\Grab\GrabPriceRuleRequest;
use App\Jobs\NotifyDeliveryPlatformUpdateMenu;
use App\Models\DeliveryPlatformRefNumber;
use App\Models\DeliveryPlatformStoreEvent;
use App\Models\DeliveryProductMappingVend;
use App\Models\GrabMenuProduct;
use App\Models\GrabStorePriceRule;
use App\Models\User;
use App\Models\VendChannel;
use App\Services\DeliveryPlatformService;
use App\Services\Grab\Menu\GrabMenuChange;
use App\Services\Grab\Menu\GrabMenuItemId;
use App\Services\Grab\Menu\GrabMenuService;
use App\Services\Grab\Menu\GrabPriceRule;
use App\Services\Grab\Menu\GrabPriceRuleResolver;
use App\Services\Grab\Menu\GrabStorePriceRuleService;
use App\Services\GrabStoreService;
use Illuminate\Bus\UniqueLock;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use InvalidArgumentException;
use Spatie\Permission\Models\Permission;
use Tests\Support\Grab\BuildsGrabStore;
use Tests\TestCase;

/**
 * A Grab store's own price rule (Brian, 2026-11-10: "make sure the user able to set the different price rule for
 * some stores"). Every store follows its operator's (company) rule unless it has its own; the own rule prices only
 * that store, in every mode, and the page shows what its customers would see change before anything is saved.
 */
class GrabStorePriceRuleTest extends TestCase
{
    use BuildsGrabStore;
    use RefreshDatabase;

    private const QUIET = '4-C7QQQQQQQQQQQQ';

    private const BUSY = '4-C7BBBBBBBBBBBB';

    private const SUB = ['id' => 'SGITEDP20200707065112014691', 'name' => 'Ice Cream Sticks'];

    private DeliveryProductMappingVend $quiet;

    private DeliveryProductMappingVend $busy;

    private int $operatorId;

    private int $u49;

    private int $u26;

    protected function setUp(): void
    {
        parent::setUp();

        config(['grab.webhook_auth' => 'off']);
        Queue::fake();
        $this->buildGrabOperator();
        $this->operatorId = $this->grabMapping->operator_id;
        $this->u49 = $this->grabItem('U-49', 11, 280)->product_id;
        $this->u26 = $this->grabItem('U-26', 12, 500)->product_id;

        // Two stores of one operator, each on its own machine holding both products.
        $this->quiet = $this->bindGrabStore(4754, self::QUIET, [11 => ['qty' => 3, 'capacity' => 10], 12 => ['qty' => 3, 'capacity' => 10]]);
        $this->busy = $this->bindGrabStore(4755, self::BUSY, [11 => ['qty' => 3, 'capacity' => 10], 12 => ['qty' => 3, 'capacity' => 10]]);
        foreach ([$this->quiet, $this->busy] as $binding) {
            VendChannel::query()->where('vend_id', $binding->vend_id)->where('code', 11)->update(['product_id' => $this->u49]);
            VendChannel::query()->where('vend_id', $binding->vend_id)->where('code', 12)->update(['product_id' => $this->u26]);
        }

        DB::table('selling_prices')->insert([
            ['product_id' => $this->u49, 'type' => 2, 'amount' => 320, 'created_at' => now(), 'updated_at' => now()],
            ['product_id' => $this->u26, 'type' => 2, 'amount' => 470, 'created_at' => now(), 'updated_at' => now()],
        ]);
        foreach ([$this->u49, $this->u26] as $productId) {
            GrabMenuProduct::create(['operator_id' => $this->operatorId, 'product_id' => $productId, 'sub_category_json' => self::SUB]);
        }

        // The company rule: RP2 × 1.25, rounded up to 10¢ → U-49 $4.00, U-26 $5.90.
        app(GrabMenuService::class)->saveSetting($this->operatorId, ['pricing_mode' => 'rp_multiplier', 'rp_type' => 2, 'multiplier_bp' => 12500, 'round_up_cents' => 10], null);
        foreach ([self::QUIET, self::BUSY] as $merchantId) {
            app(GrabStoreService::class)->setMenuSource($this->ref($merchantId), true, null);
        }
    }

    private function ref(string $merchantId): DeliveryPlatformRefNumber
    {
        return DeliveryPlatformRefNumber::query()->where('ref_number', $merchantId)->sole();
    }

    /** @return array<int, int> Grab price by product id, as the menu Grab pulls has it */
    private function prices(DeliveryProductMappingVend $binding): array
    {
        $menu = app(DeliveryPlatformService::class)->buildMenu($binding->fresh());

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

    public function test_a_store_with_its_own_rule_is_priced_by_it_and_the_others_keep_the_company_rule(): void
    {
        app(GrabStorePriceRuleService::class)->useOwnRule($this->ref(self::QUIET), [
            'pricing_mode' => 'rp_multiplier', 'rp_type' => 2, 'multiplier_bp' => 11500, 'round_up_cents' => 10,
        ], 'Quiet store', null);

        $this->assertSame([$this->u49 => 370, $this->u26 => 550], $this->prices($this->quiet), 'RP2 × 1.15: 368 → 370, 540.5 → 550');
        $this->assertSame([$this->u49 => 400, $this->u26 => 590], $this->prices($this->busy), 'company rule untouched');

        // A company rule change reaches only the stores that follow it.
        app(GrabMenuService::class)->saveSetting($this->operatorId, ['pricing_mode' => 'rp', 'rp_type' => 2, 'multiplier_bp' => 10000, 'round_up_cents' => 10], null);
        $this->assertSame([$this->u49 => 370, $this->u26 => 550], $this->prices($this->quiet));
        $this->assertSame([$this->u49 => 320, $this->u26 => 470], $this->prices($this->busy));

        // Back to the company rule.
        $this->assertTrue(app(GrabStorePriceRuleService::class)->useCompanyRule($this->ref(self::QUIET), null));
        $this->assertSame([$this->u49 => 320, $this->u26 => 470], $this->prices($this->quiet));
        $this->assertFalse(app(GrabStorePriceRuleService::class)->useCompanyRule($this->ref(self::QUIET), null), 'already on it');
    }

    public function test_a_store_custom_rule_uses_the_stores_own_typed_prices_and_lists_nothing_untyped(): void
    {
        GrabMenuProduct::query()->where('product_id', $this->u49)->update(['custom_amount' => 999]);
        $service = app(GrabStorePriceRuleService::class);
        $service->useOwnRule($this->ref(self::QUIET), ['pricing_mode' => 'custom', 'rp_type' => 2, 'multiplier_bp' => 10000, 'round_up_cents' => 10], null, null);
        $service->saveCustomPrice($this->ref(self::QUIET), $this->u49, 350, null);

        $this->assertSame([$this->u49 => 350], $this->prices($this->quiet), "the store's price, not the company's 999; U-26 has none so is not sold");

        $service->saveCustomPrice($this->ref(self::QUIET), $this->u49, 360, null);
        $this->assertSame(350, DB::table('grab_store_custom_prices')->value('previous_amount'));
        $service->saveCustomPrice($this->ref(self::QUIET), $this->u49, null, null);
        $this->assertSame([], $this->prices($this->quiet));
    }

    public function test_the_page_sets_a_rule_on_picked_stores_with_a_note_logs_it_and_tells_grab(): void
    {
        $user = $this->staff();
        // setUp's switch already holds each store's unique notify (an array-store lock, which flush() keeps).
        foreach ([$this->quiet, $this->busy] as $binding) {
            (new UniqueLock(app(Repository::class)))->release(new NotifyDeliveryPlatformUpdateMenu($binding));
        }
        Queue::fake();

        $this->actingAs($user)->post("/grab-menu/{$this->operatorId}/store-price-rule", [
            'use_company_rule' => false,
            'ref_ids' => [$this->ref(self::QUIET)->id],
            'pricing_mode' => 'rp_multiplier', 'rp_type' => 2, 'multiplier' => '1.15', 'round_up_cents' => 10,
            'note' => 'Quiet store',
        ])->assertSessionHasNoErrors();

        $row = GrabStorePriceRule::sole();
        $this->assertSame(11500, $row->multiplier_bp);
        $this->assertSame('Quiet store', $row->note);
        $this->assertSame($user->id, $row->updated_by);
        Queue::assertPushed(NotifyDeliveryPlatformUpdateMenu::class, 1);
        $event = DeliveryPlatformStoreEvent::query()->where('event', DeliveryPlatformStoreEvent::EVENT_PRICE_RULE)->sole();
        $this->assertTrue($event->data['own']);
        $this->assertSame(1.15, $event->data['multiplier']);
        $this->assertSame($user->id, $event->user_id);

        // The store list says which rule each store follows.
        $this->actingAs($user)->get('/grab-menu')->assertOk()->assertInertia(fn ($page) => $page
            ->where('stores.0.vend_code', 4754)
            ->where('stores.0.price_rule.scope', 'store')
            ->where('stores.0.price_rule.multiplier', 1.15)
            ->where('stores.0.price_rule.note', 'Quiet store')
            ->where('stores.1.price_rule.scope', 'company')
            ->where('stores.1.price_rule.multiplier', 1.25));

        $this->actingAs($user)->post("/grab-menu/{$this->operatorId}/store-price-rule", [
            'use_company_rule' => true, 'ref_ids' => [$this->ref(self::QUIET)->id, $this->ref(self::BUSY)->id],
        ])->assertSessionHasNoErrors();
        $this->assertSame(0, GrabStorePriceRule::count());
    }

    public function test_a_store_of_another_operator_or_a_reader_is_refused(): void
    {
        $other = DeliveryPlatformRefNumber::forceCreate([
            'delivery_platform_id' => $this->grabOperator->delivery_platform_id, 'operator_id' => $this->operatorId + 100, 'ref_number' => '4-C7OTHEROTHEROT',
        ]);

        $this->actingAs($this->staff())->post("/grab-menu/{$this->operatorId}/store-price-rule", [
            'use_company_rule' => true, 'ref_ids' => [$other->id],
        ])->assertSessionHasErrors('ref_ids');

        $reader = User::factory()->create(['operator_id' => $this->operatorId]);
        $reader->givePermissionTo('read delivery-platform-product-mappings');
        $this->actingAs($reader)->post("/grab-menu/{$this->operatorId}/store-price-rule", [
            'use_company_rule' => true, 'ref_ids' => [$this->ref(self::QUIET)->id],
        ])->assertForbidden();
        $this->actingAs($reader)->postJson('/grab-menu/stores/'.$this->ref(self::QUIET)->id."/custom-prices/{$this->u49}", ['price' => '3.50'])->assertForbidden();
    }

    public function test_the_preview_compares_grab_today_with_a_draft_rule_without_saving_it(): void
    {
        // What Grab pulled last: the company rule, plus a product no longer on the machine.
        $menu = app(DeliveryPlatformService::class)->buildMenu($this->quiet->fresh());
        $menu['categories'][0]['subCategories'][0]['items'][] = ['id' => 'P999999', 'name' => 'Magnum La Pistache', 'price' => 500, 'availableStatus' => 'AVAILABLE'];
        $this->quiet->update(['last_menu_json' => $menu]);
        DB::table('products')->insert(['id' => 999999, 'code' => 'U-77', 'name' => 'Magnum La Pistache']);

        $response = $this->actingAs($this->staff())->getJson('/grab-menu/stores/'.$this->ref(self::QUIET)->id.'/preview?'.http_build_query([
            'pricing_mode' => 'rp_multiplier', 'rp_type' => 2, 'multiplier' => '1.15', 'round_up_cents' => 10,
        ]))->assertOk();

        $response->assertJsonPath('rule.scope', 'store')
            ->assertJsonPath('rule.multiplier', 1.15)
            ->assertJsonPath('summary.down', 2)
            ->assertJsonPath('summary.removed', 1)
            ->assertJsonPath('summary.has_current', true);
        $rows = collect($response->json('rows'))->keyBy('name');
        $this->assertSame(['now' => 400, 'after' => 370, 'change' => GrabMenuChange::DOWN], collect($rows['Product U-49'])->only(['now', 'after', 'change'])->all());
        $this->assertSame(GrabMenuChange::REMOVED, $rows['Magnum La Pistache']['change']);
        $this->assertSame(0, GrabStorePriceRule::count(), 'a draft saves nothing');
    }

    public function test_the_rule_object_refuses_an_impossible_rule_and_the_form_keeps_cents_integer(): void
    {
        $this->expectException(InvalidArgumentException::class);
        try {
            $this->assertSame(['pricing_mode' => 'rp_multiplier', 'rp_type' => 2, 'multiplier_bp' => 12500, 'round_up_cents' => 10],
                GrabPriceRuleRequest::valuesFrom(['pricing_mode' => 'rp_multiplier', 'rp_type' => '2', 'multiplier' => '1.25', 'round_up_cents' => '10']));
            $this->assertSame(10000, GrabPriceRuleRequest::valuesFrom(['pricing_mode' => 'rp', 'rp_type' => 2, 'multiplier' => '1.25', 'round_up_cents' => 10])['multiplier_bp'], 'an RP rule does not multiply');
            $this->assertSame('company', app(GrabPriceRuleResolver::class)->forStore($this->ref(self::BUSY))->toArray()['scope']);
        } finally {
            GrabPriceRule::draftForStore(['pricing_mode' => 'rp_multiplier', 'rp_type' => 2, 'multiplier_bp' => 60000, 'round_up_cents' => 10], $this->operatorId, $this->ref(self::QUIET)->id);
        }
    }
}
