<?php

namespace Tests\Feature;

use App\Jobs\NotifyDeliveryPlatformUpdateMenu;
use App\Models\DeliveryPlatformOrder;
use App\Models\DeliveryPlatformRefNumber;
use App\Models\DeliveryPlatformStoreEvent;
use App\Models\DeliveryProductMappingVend;
use App\Models\GrabMenuProduct;
use App\Models\GrabMenuSetting;
use App\Models\User;
use App\Models\VendChannel;
use App\Services\DeliveryPlatformService;
use App\Services\Grab\Menu\GrabMenuItemId;
use App\Services\Grab\Menu\GrabMenuService;
use App\Services\Grab\Menu\GrabPriceRule;
use App\Services\GrabPromotionService;
use App\Services\GrabStoreService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia;
use Spatie\Permission\Models\Permission;
use Tests\Support\Grab\BuildsGrabStore;
use Tests\TestCase;

/**
 * Grab Menu & Prices (Brian, 2026-11-01): a store on the machine menu lists every product
 * on its machine's slots — one item per product, stock summed — priced by its operator's
 * rule (RP tier, RP × multiplier rounded up, or a custom price). Nothing is added or
 * removed by hand; stores still on a template are untouched.
 */
class GrabMachineMenuTest extends TestCase
{
    use BuildsGrabStore;
    use RefreshDatabase;

    private const STORE = '4-C7AAAAAAAAAAAA';

    private const SUB = ['id' => 'SGITEDP20200707065112014691', 'name' => 'Ice Cream Sticks'];

    private DeliveryProductMappingVend $binding;

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

        // Slots 11 and 13 hold U-49, slot 12 holds U-26; the template knows 11 and 12 only.
        $this->binding = $this->bindGrabStore(4754, self::STORE, [
            11 => ['qty' => 2, 'capacity' => 10],
            12 => ['qty' => 4, 'capacity' => 10],
            13 => ['qty' => 5, 'capacity' => 10],
        ]);
        $this->slot(11)->update(['product_id' => $this->u49]);
        $this->slot(12)->update(['product_id' => $this->u26]);
        $this->slot(13)->update(['product_id' => $this->u49]);

        // RP2 (cents), as Product → Edit stores it.
        DB::table('selling_prices')->insert([
            ['product_id' => $this->u49, 'type' => 2, 'amount' => 275, 'created_at' => now(), 'updated_at' => now()],
            ['product_id' => $this->u26, 'type' => 2, 'amount' => 450, 'created_at' => now(), 'updated_at' => now()],
            ['product_id' => $this->u49, 'type' => 4, 'amount' => 330, 'created_at' => now(), 'updated_at' => now()],
        ]);
        foreach ([$this->u49, $this->u26] as $productId) {
            GrabMenuProduct::create(['operator_id' => $this->operatorId, 'product_id' => $productId, 'sub_category_json' => self::SUB]);
        }
    }

    /** @return string[] machine-menu item ids, sorted as strings */
    private function itemIds(int ...$productIds): array
    {
        return collect($productIds)->map(fn ($id) => GrabMenuItemId::forProduct($id))->sort()->values()->all();
    }

    private function slot(int $code): VendChannel
    {
        return VendChannel::query()->where('vend_id', $this->binding->vend_id)->where('code', $code)->sole();
    }

    private function switchStore(): void
    {
        app(GrabStoreService::class)->setMenuSource(DeliveryPlatformRefNumber::where('ref_number', self::STORE)->sole(), true, null);
        $this->binding->refresh();
    }

    /** @return array<string, array> Grab's items by id */
    private function menuItems(): array
    {
        $menu = app(DeliveryPlatformService::class)->buildMenu($this->binding->fresh());

        return collect($menu['categories'][0]['subCategories'])->flatMap(fn ($sub) => $sub['items'])->keyBy('id')->all();
    }

    private function setting(array $values): void
    {
        app(GrabMenuService::class)->saveSetting($this->operatorId, $values, null);
    }

    public function test_the_multiplier_always_rounds_up_to_the_step_in_integer_cents(): void
    {
        $this->assertSame(310, GrabPriceRule::multiply(275, 11000, 10));   // 302.5 → 310
        $this->assertSame(400, GrabPriceRule::multiply(275, 11000, 100));  // → $4
        $this->assertSame(280, GrabPriceRule::multiply(275, 10000, 10));   // ×1.00 still rounds up to 10¢
        $this->assertSame(300, GrabPriceRule::multiply(300, 10000, 10));   // already on the step
        $this->assertSame(303, GrabPriceRule::multiply(275, 11000, 1));    // to the cent
    }

    public function test_a_store_on_the_machine_menu_lists_each_product_on_its_machine_once_with_stock_summed(): void
    {
        $this->switchStore();

        $items = $this->menuItems();
        $this->assertSame($this->itemIds($this->u49, $this->u26), collect($items)->keys()->sort()->values()->all());
        $u49 = $items[GrabMenuItemId::forProduct($this->u49)];
        $this->assertSame(275, $u49['price'], 'RP2 by default, integer cents');
        $this->assertSame(7, $u49['maxStock'], 'slots 11 + 13');
        $this->assertSame('AVAILABLE', $u49['availableStatus']);

        $menu = app(DeliveryPlatformService::class)->buildMenu($this->binding->fresh());
        $this->assertSame('AVAILABLE', $menu['categories'][0]['availableStatus']);
        $this->assertSame('SGITEDP20200707065055013739', $menu['categories'][0]['id']);
    }

    public function test_each_pricing_rule_prices_the_menu_and_a_product_with_no_price_is_not_listed(): void
    {
        $this->switchStore();

        $this->setting(['pricing_mode' => 'rp', 'rp_type' => 4]);
        $items = $this->menuItems();
        $this->assertSame(330, $items[GrabMenuItemId::forProduct($this->u49)]['price']);
        $this->assertArrayNotHasKey(GrabMenuItemId::forProduct($this->u26), $items, 'no RP4 for U-26: not sold, never at $0');

        $this->setting(['pricing_mode' => 'rp_multiplier', 'rp_type' => 2, 'multiplier_bp' => 11000, 'round_up_cents' => 10]);
        $items = $this->menuItems();
        $this->assertSame(310, $items[GrabMenuItemId::forProduct($this->u49)]['price']);
        $this->assertSame(500, $items[GrabMenuItemId::forProduct($this->u26)]['price']); // 495 → 500

        $this->setting(['pricing_mode' => 'custom']);
        app(GrabMenuService::class)->saveProduct($this->operatorId, $this->u49, ['custom_amount' => 390], null);
        $items = $this->menuItems();
        $this->assertSame([GrabMenuItemId::forProduct($this->u49)], array_keys($items));
        $this->assertSame(390, $items[GrabMenuItemId::forProduct($this->u49)]['price']);

        // No sub-category: not listed either.
        app(GrabMenuService::class)->saveProduct($this->operatorId, $this->u49, ['sub_category_json' => null], null);
        $this->assertSame([], $this->menuItems());
    }

    public function test_the_menu_follows_a_planogram_change_with_nothing_done_on_the_grab_side(): void
    {
        $this->switchStore();
        $this->slot(12)->update(['product_id' => $this->u49]);

        $items = $this->menuItems();
        $this->assertSame([GrabMenuItemId::forProduct($this->u49)], array_keys($items));
        $this->assertSame(11, $items[GrabMenuItemId::forProduct($this->u49)]['maxStock']);
    }

    public function test_a_store_still_on_its_template_keeps_the_template_menu(): void
    {
        $items = $this->menuItems();

        $this->assertSame([$this->grabItems['U-49']->id, $this->grabItems['U-26']->id], array_keys($items), 'template item ids, as before');
        $this->assertSame(280, $items[$this->grabItems['U-49']->id]['price']);
    }

    public function test_a_machine_menu_order_is_split_over_the_slots_holding_the_product_most_stock_first(): void
    {
        $this->switchStore();
        $payload = $this->grabOrderPayload(self::STORE, 4754, [GrabMenuItemId::forProduct($this->u49)]);
        $payload['items'] = [array_merge($payload['items'][0], ['id' => GrabMenuItemId::forProduct($this->u49), 'quantity' => 6, 'price' => 275])];

        $this->postJson('/api/delivery/grab/order/create', $payload)->assertOk();

        $order = DeliveryPlatformOrder::sole();
        $line = $order->deliveryPlatformOrderItems()->sole();
        $this->assertNull($line->delivery_product_mapping_item_id);
        $this->assertSame($this->u49, (int) $line->product_id);
        $this->assertSame(1650, (int) $line->getRawOriginal('amount'));
        $this->assertSame(['13' => 5, '11' => 1], $order->orderItemVendChannels()->orderBy('id')->pluck('qty', 'vend_channel_code')->all());
        $this->assertSame([1375, 275], $order->orderItemVendChannels()->orderBy('id')->get()->map(fn ($row) => (int) $row->getRawOriginal('amount'))->all());

        // The open order holds its units: the menu now sells 7 − 6 = 1.
        $this->assertSame(1, $this->menuItems()[GrabMenuItemId::forProduct($this->u49)]['maxStock']);

        // The machine drops the order line's product (there is no template item).
        $dispenseProduct = (new \ReflectionMethod(DeliveryPlatformService::class, 'dispenseProduct'));
        $this->assertSame(['id' => $this->u49, 'name' => 'Product U-49'], $dispenseProduct->invoke(app(DeliveryPlatformService::class), $order->orderItemVendChannels()->first()));
    }

    public function test_a_machine_menu_order_for_a_product_not_on_the_machine_is_a_4xx(): void
    {
        $this->switchStore();
        $payload = $this->grabOrderPayload(self::STORE, 4754, ['P999999']);
        $payload['items'] = [array_merge($payload['items'][0], ['id' => 'P999999'])];

        $this->postJson('/api/delivery/grab/order/create', $payload)->assertStatus(422);
        $this->assertSame(0, DeliveryPlatformOrder::count());
    }

    public function test_switching_a_store_is_logged_and_asks_grab_to_read_the_menu(): void
    {
        $this->switchStore();

        Queue::assertPushed(NotifyDeliveryPlatformUpdateMenu::class);
        $event = DeliveryPlatformStoreEvent::query()->where('event', DeliveryPlatformStoreEvent::EVENT_MENU_SOURCE)->sole();
        $this->assertSame(['machine_menu' => true], $event->data);

        // Promotions on items take the machine menu's item ids.
        $this->assertSame($this->itemIds($this->u49, $this->u26), collect(app(GrabPromotionService::class)->menuItemIds([$this->binding->delivery_platform_ref_number_id])[$this->binding->delivery_platform_ref_number_id])->sort()->values()->all());
    }

    public function test_a_template_whose_stores_all_follow_their_machines_is_read_only(): void
    {
        $this->assertFalse($this->grabMapping->followsMachines());
        $this->switchStore();
        $this->assertTrue($this->grabMapping->fresh()->followsMachines());

        $user = $this->staff();
        $this->actingAs($user)->post('/delivery-product-mapping-items/'.$this->grabItems['U-49']->id.'/update', ['amount' => 9, 'sub_category_json' => self::SUB])
            ->assertSessionHasErrors('delivery_product_mapping');
        $this->assertSame(280, (int) $this->grabItems['U-49']->fresh()->getRawOriginal('amount'));
    }

    public function test_filling_from_the_old_templates_keeps_what_is_set(): void
    {
        GrabMenuProduct::query()->delete();
        GrabMenuProduct::create(['operator_id' => $this->operatorId, 'product_id' => $this->u26, 'custom_amount' => 520]);

        $result = app(GrabMenuService::class)->fillFromTemplates($this->operatorId, null);

        $this->assertSame(['category' => true, 'sub_categories' => 2, 'prices' => 1], $result);
        $this->assertSame(280, GrabMenuProduct::where('product_id', $this->u49)->value('custom_amount'));
        $this->assertSame(520, GrabMenuProduct::where('product_id', $this->u26)->value('custom_amount'), 'kept');
        $this->assertSame(self::SUB, GrabMenuProduct::where('product_id', $this->u49)->sole()->sub_category_json);
        $this->assertSame('SGITEDP20200707065055013739', GrabMenuSetting::forOperator($this->operatorId)->category_json['id']);
    }

    public function test_the_page_shows_the_operators_products_and_stores_and_saves_its_rule(): void
    {
        $user = $this->staff();

        $this->actingAs($user)->get('/grab-menu')->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
            ->component('GrabMenu/Index')
            ->where('operatorId', $this->operatorId)
            ->where('setting.pricing_mode', 'rp')
            ->where('setting.saved', false)
            ->has('products', 2)
            ->where('products.1.code', 'U-49')
            ->where('products.1.machines', 1)
            ->where('products.1.rp', 275)
            ->where('products.1.grab_price', 275)
            ->where('products.1.problem', null)
            ->has('stores', 1)
            ->where('stores.0.machine_menu_since', null)
            ->where('can.update', true));

        $this->actingAs($user)->post("/grab-menu/{$this->operatorId}/setting", [
            'pricing_mode' => 'rp_multiplier', 'rp_type' => 2, 'multiplier' => '1.1', 'round_up_cents' => 10,
        ])->assertSessionHasNoErrors();
        $setting = GrabMenuSetting::forOperator($this->operatorId);
        $this->assertSame(11000, $setting->multiplier_bp);
        $this->assertSame($user->id, $setting->updated_by);

        $this->actingAs($user)->post("/grab-menu/{$this->operatorId}/products/{$this->u49}", ['custom_price' => '3.90'])->assertSessionHasNoErrors();
        $this->assertSame(390, GrabMenuProduct::where('product_id', $this->u49)->value('custom_amount'));

        $this->actingAs($user)->post("/grab-menu/{$this->operatorId}/menu-source", ['machine_menu' => true, 'ref_ids' => [$this->binding->delivery_platform_ref_number_id]])->assertSessionHasNoErrors();
        $this->assertNotNull(DeliveryPlatformRefNumber::find($this->binding->delivery_platform_ref_number_id)->menu_from_machine_at);

        $this->actingAs($user)->getJson("/grab-menu/stores/{$this->binding->delivery_platform_ref_number_id}/preview")
            ->assertOk()
            ->assertJsonPath('rule.scope', 'company')
            ->assertJsonPath('summary.listed', 2)
            ->assertJsonPath('rows.0.code', 'U-26')
            ->assertJsonPath('rows.0.after', 500)
            ->assertJsonPath('rows.1.code', 'U-49')
            ->assertJsonPath('rows.1.after', 310);

        // A reader may look but not change.
        $reader = User::factory()->create(['operator_id' => $this->operatorId]);
        $reader->givePermissionTo('read delivery-platform-product-mappings');
        $this->actingAs($reader)->post("/grab-menu/{$this->operatorId}/setting", ['pricing_mode' => 'rp', 'rp_type' => 2, 'round_up_cents' => 10])->assertForbidden();
    }

    public function test_on_grab_now_shows_each_product_on_the_machine_and_why_one_is_not_listed(): void
    {
        $this->switchStore();
        app(GrabMenuService::class)->saveProduct($this->operatorId, $this->u26, ['sub_category_json' => null], null);
        Permission::findOrCreate('read delivery-platform-vends', 'web');
        $user = $this->staff();
        $user->givePermissionTo('read delivery-platform-vends');

        $this->actingAs($user)->getJson('/grab-stores/'.$this->binding->delivery_platform_ref_number_id.'/menu')
            ->assertOk()
            ->assertJsonPath('machine_menu', true)
            ->assertJsonPath('counts.items', 2)
            ->assertJsonPath('counts.available', 1)
            ->assertJsonPath('lines.0.product_code', 'U-26')
            ->assertJsonPath('lines.0.reason', 'no_sub_category')
            ->assertJsonPath('lines.1.slot', '13, 11');
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
}
