<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Product;
use App\Models\ProductMapping;
use App\Models\ProductMappingItem;
use App\Models\SellingPrice;
use App\Models\User;
use App\Models\Vend;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Setting/Edit hides the VMC-board pickers for a Smart Freezer (setting chart,
 * machine prefix, menu frame, LCD monitor, LED panel, fan signal), so the save
 * must not require them — the same relaxation a Smart Chiller already gets.
 */
class SmartFreezerSettingsSaveTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        $user = User::factory()->create();
        foreach (['read machine-settings', 'update machine-settings'] as $p) {
            $user->givePermissionTo(Permission::findOrCreate($p, 'web'));
        }
        $this->actingAs($user);
    }

    private function freezer(array $attrs = []): Vend
    {
        return Vend::create(array_merge([
            'code' => 50009, 'machine_type' => Vend::MACHINE_TYPE_SMART_FREEZER,
            'is_active' => 1, 'operator_id' => 1, 'vend_model_id' => 1,
            'lcd_monitor_id' => null, 'menu_frame_id' => null, 'vend_config_id' => null, 'vend_prefix_id' => null,
        ], $attrs));
    }

    public function test_freezer_saves_without_vending_hardware_or_prefix(): void
    {
        $mapping = ProductMapping::create([
            'name' => 'Freezer planogram', 'machine_type' => Vend::MACHINE_TYPE_SMART_FREEZER,
            'is_active' => true, 'operator_id' => 1,
        ]);
        $vend = $this->freezer(['product_mapping_id' => $mapping->id]);

        $this->post('/vends/'.$vend->id.'/update', [
            'machine_type' => 'smart_freezer',
            'operator_id' => 1,
            'vend_model_id' => 1,
            'label_name' => 'Unit 1',
            'product_mapping_id' => $mapping->id,
            'vend_prefix_id' => null,
            'vend_config_id' => null,
            'lcd_monitor_id' => null,
            'menu_frame_id' => null,
        ])->assertSessionHasNoErrors();

        $this->assertSame('Unit 1', $vend->fresh()->label_name);
    }

    public function test_freezer_saves_before_a_mapping_is_bound(): void
    {
        $vend = $this->freezer(['product_mapping_id' => null]);

        $this->post('/vends/'.$vend->id.'/update', [
            'machine_type' => 'smart_freezer', 'operator_id' => 1, 'vend_model_id' => 1,
            'product_mapping_id' => null,
        ])->assertSessionHasNoErrors();
    }

    public function test_freezer_still_requires_operator_and_model(): void
    {
        $vend = $this->freezer();

        $this->post('/vends/'.$vend->id.'/update', ['machine_type' => 'smart_freezer'])
            ->assertSessionHasErrors(['operator_id', 'vend_model_id']);
    }

    public function test_mapping_options_carry_the_sites_tier_price_for_the_planogram(): void
    {
        // A freezer has no live vend_channels, so Setting/Edit previews its menu (table and
        // planogram) from the mapping option object. Without prices it read blank (1372).
        $product = Product::create(['code' => 'U-01', 'name' => 'Cornetto']);
        SellingPrice::create(['product_id' => $product->id, 'type' => SellingPrice::TYPE_1, 'amount' => 2.70]);
        SellingPrice::create(['product_id' => $product->id, 'type' => SellingPrice::TYPE_2, 'amount' => 3.00]);
        $mapping = ProductMapping::create([
            'name' => 'Freezer planogram', 'machine_type' => Vend::MACHINE_TYPE_SMART_FREEZER,
            'is_smart' => true, 'is_active' => true, 'operator_id' => 1,
        ]);
        ProductMappingItem::create(['product_mapping_id' => $mapping->id, 'channel_code' => '11', 'product_id' => $product->id]);
        $customer = Customer::create([
            'name' => 'Freezer Site', 'code' => 'FS1', 'operator_id' => 1,
            'status_id' => Customer::STATUS_ACTIVE, 'selling_price_type' => SellingPrice::TYPE_2,
        ]);
        $vend = $this->freezer(['product_mapping_id' => $mapping->id, 'customer_id' => $customer->id]);
        $rp2Cents = SellingPrice::where('product_id', $product->id)->where('type', SellingPrice::TYPE_2)->first()->getRawOriginal('amount');

        $this->get('/settings/vend/'.$vend->id.'/update')
            ->assertOk()
            ->assertInertia(function ($page) use ($mapping, $rp2Cents) {
                $options = collect($page->toArray()['props']['productMappingOptions']['data']);
                $item = collect($options->firstWhere('id', $mapping->id)['productMappingItems'])->first();
                $prices = $item['product']['sellingPrices'];

                $this->assertCount(1, $prices, 'only the Site tier is shipped');
                $this->assertSame(SellingPrice::TYPE_2, (int) $prices[0]['type']);
                $this->assertEquals($rp2Cents, $prices[0]['amount']);
            });
    }
}
