<?php

namespace Tests\Feature;

use App\Models\DeliveryPlatformRefNumber;
use App\Models\GrabMenuProduct;
use App\Models\GrabStorePriceRule;
use App\Models\Product;
use App\Models\User;
use App\Models\Vend;
use App\Models\VendChannel;
use App\Services\Grab\Menu\GrabMenuService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Spatie\Permission\Models\Permission;
use Tests\Support\Grab\BuildsGrabStore;
use Tests\TestCase;

/**
 * The put-on-machine window (Brian, 2026-11-11): merchant ID + machine ID, the machine's products are the menu, what is
 * missing (a new product with no Grab sub-category, no price under a typed-price rule) is asked there, the price rule
 * (company or the store's own RP tier × multiplier) is chosen there, and Save goes live — no old Grab menu.
 */
class GrabStoreSetupTest extends TestCase
{
    use BuildsGrabStore;
    use RefreshDatabase;

    private const SUB = ['id' => 'SGITEDP20200707065112014691', 'name' => 'Ice Cream Sticks'];

    private User $user;

    private int $known;

    private int $newProduct;

    protected function setUp(): void
    {
        parent::setUp();

        config(['grab.webhook_auth' => 'off', 'grab.store_api_spacing_ms' => 0]);
        Queue::fake();
        Http::fake();
        $this->buildGrabOperator();
        $this->known = $this->grabItem('U-49', 11, 280)->product_id;
        $this->newProduct = Product::forceCreate(['name' => 'New Magnum', 'code' => 'U-99'])->id;
        $operatorId = $this->grabMapping->operator_id;
        GrabMenuProduct::create(['operator_id' => $operatorId, 'product_id' => $this->known, 'sub_category_json' => self::SUB]);
        DB::table('selling_prices')->insert([
            ['product_id' => $this->known, 'type' => 2, 'amount' => 320, 'created_at' => now(), 'updated_at' => now()],
            ['product_id' => $this->newProduct, 'type' => 2, 'amount' => 470, 'created_at' => now(), 'updated_at' => now()],
        ]);
        app(GrabMenuService::class)->saveSetting($operatorId, ['pricing_mode' => 'rp_multiplier', 'rp_type' => 2, 'multiplier_bp' => 12500, 'round_up_cents' => 10], null);

        $vend = Vend::forceCreate(['code' => 4407, 'operator_id' => $operatorId]);
        VendChannel::forceCreate(['vend_id' => $vend->id, 'code' => 11, 'product_id' => $this->known, 'qty' => 5, 'capacity' => 10, 'is_active' => true]);
        VendChannel::forceCreate(['vend_id' => $vend->id, 'code' => 12, 'product_id' => $this->newProduct, 'qty' => 5, 'capacity' => 10, 'is_active' => true]);

        foreach (['read delivery-platform-vends', 'update delivery-platform-vends'] as $permission) {
            Permission::findOrCreate($permission, 'web');
        }
        $this->user = User::factory()->create(['operator_id' => $operatorId]);
        $this->user->givePermissionTo(['read delivery-platform-vends', 'update delivery-platform-vends']);
    }

    public function test_the_window_shows_the_machines_products_priced_and_asks_for_what_is_missing(): void
    {
        $response = $this->actingAs($this->user)->getJson('/grab-stores/machine-preview?'.http_build_query([
            'operator_id' => $this->grabMapping->operator_id, 'vend_code' => '4407',
        ]))->assertOk();

        $response->assertJsonPath('company_rule.multiplier', 1.25)
            ->assertJsonPath('counts.products', 2)
            ->assertJsonPath('counts.missing', 1)
            ->assertJsonPath('rows.0.code', 'U-99')
            ->assertJsonPath('rows.0.problem', 'no_sub_category')
            ->assertJsonPath('rows.1.price', 400);

        // A draft own rule prices the same machine differently, nothing saved.
        $this->actingAs($this->user)->getJson('/grab-stores/machine-preview?'.http_build_query([
            'operator_id' => $this->grabMapping->operator_id, 'vend_code' => '4407',
            'use_company_rule' => 0, 'pricing_mode' => 'rp_multiplier', 'rp_type' => 2, 'multiplier' => '1.1', 'round_up_cents' => 10,
        ]))->assertJsonPath('rule.scope', 'store')->assertJsonPath('rows.1.price', 360);
        $this->assertSame(0, GrabStorePriceRule::count());
    }

    public function test_save_picks_the_sub_category_sets_the_stores_rule_and_goes_live_on_the_machine_menu(): void
    {
        $this->actingAs($this->user)->post('/grab-stores', [
            'operator_id' => $this->grabMapping->operator_id, 'ref_number' => '4-C4MJKA6FRUL1RX', 'vend_code' => '4407', 'skip_grab_check' => true,
            'setup' => [
                'use_company_rule' => false, 'pricing_mode' => 'rp_multiplier', 'rp_type' => 2, 'multiplier' => '1.15', 'round_up_cents' => 10, 'note' => 'Quiet store',
                'sub_categories' => [['product_id' => $this->newProduct, 'id' => 'SGMAGNUM', 'name' => 'Magnum Selection']],
            ],
        ])->assertSessionHasNoErrors();

        $ref = DeliveryPlatformRefNumber::query()->where('ref_number', '4-C4MJKA6FRUL1RX')->sole();
        $this->assertTrue($ref->usesMachineMenu());
        $this->assertSame(['id' => 'SGMAGNUM', 'name' => 'Magnum Selection'], GrabMenuProduct::where('product_id', $this->newProduct)->sole()->sub_category_json);
        $rule = GrabStorePriceRule::sole();
        $this->assertSame([11500, 'Quiet store'], [$rule->multiplier_bp, $rule->note]);

        $this->actingAs($this->user)->getJson('/grab-stores/machine-preview?'.http_build_query([
            'operator_id' => $this->grabMapping->operator_id, 'vend_code' => '4407', 'ref_id' => $ref->id,
            'use_company_rule' => 0, 'pricing_mode' => 'rp_multiplier', 'rp_type' => 2, 'multiplier' => '1.15', 'round_up_cents' => 10,
        ]))->assertJsonPath('counts.missing', 0)->assertJsonPath('own_rule.note', 'Quiet store');
    }

    public function test_a_typed_price_rule_takes_the_prices_typed_in_the_window(): void
    {
        app(GrabMenuService::class)->saveSetting($this->grabMapping->operator_id, ['pricing_mode' => 'custom', 'rp_type' => 2, 'multiplier_bp' => 10000, 'round_up_cents' => 10], null);

        $this->actingAs($this->user)->post('/grab-stores', [
            'operator_id' => $this->grabMapping->operator_id, 'ref_number' => '4-C4MJKA6FRUL1RX', 'vend_code' => '4407', 'skip_grab_check' => true,
            'setup' => [
                'use_company_rule' => true,
                'custom_prices' => [['product_id' => $this->known, 'price' => '3.90']],
            ],
        ])->assertSessionHasNoErrors();

        $this->assertSame(390, GrabMenuProduct::where('product_id', $this->known)->value('custom_amount'), 'the company\'s typed price');
    }
}
