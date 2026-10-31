<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Product;
use App\Models\ProductMapping;
use App\Models\ProductMappingItem;
use App\Models\SellingPrice;
use App\Models\User;
use App\Models\Vend;
use App\Models\VendChannel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The Operation Dashboard's Smart Freezer popup shows each SKU's balance / capacity and its price, and the
 * machine's RP like every other machine's popup (Brian, 2026-10-31) - VendController::smartPlanogram.
 */
class SmartFreezerPlanogramPopupTest extends TestCase
{
    use RefreshDatabase;

    public function test_each_slot_carries_balance_capacity_and_the_sites_rp_price(): void
    {
        $cheesecake = Product::create(['code' => 'CC-01', 'name' => 'Basque Cheesecake', 'freezer_slot_qty' => 10]);
        $eclair = Product::create(['code' => 'E-01', 'name' => 'Eclair', 'freezer_slot_qty' => 8]);
        $cone = Product::create(['code' => 'U-05', 'name' => 'Cornetto']);
        SellingPrice::create(['product_id' => $cheesecake->id, 'type' => SellingPrice::TYPE_1, 'amount' => 4.30]);
        SellingPrice::create(['product_id' => $cheesecake->id, 'type' => SellingPrice::TYPE_2, 'amount' => 4.50]);

        $mapping = ProductMapping::create([
            'name' => 'Freezer planogram', 'machine_type' => Vend::MACHINE_TYPE_SMART_FREEZER,
            'is_smart' => true, 'is_active' => true, 'operator_id' => 1,
        ]);
        ProductMappingItem::create(['product_mapping_id' => $mapping->id, 'channel_code' => '11', 'product_id' => $cheesecake->id]);
        ProductMappingItem::create(['product_mapping_id' => $mapping->id, 'channel_code' => '13', 'product_id' => $eclair->id, 'capacity_override' => 6]);
        ProductMappingItem::create(['product_mapping_id' => $mapping->id, 'channel_code' => '31', 'product_id' => $cone->id]);

        $customer = Customer::create([
            'name' => 'Freezer Site', 'code' => 'FS1', 'operator_id' => 1,
            'status_id' => Customer::STATUS_ACTIVE, 'selling_price_type' => SellingPrice::TYPE_2,
        ]);
        $vend = Vend::create([
            'code' => 50001, 'machine_type' => Vend::MACHINE_TYPE_SMART_FREEZER, 'is_active' => 1,
            'operator_id' => 1, 'vend_model_id' => 1, 'customer_id' => $customer->id,
            'product_mapping_id' => $mapping->id, 'is_using_server_price' => true,
        ]);
        VendChannel::create(['vend_id' => $vend->id, 'code' => 11, 'product_id' => $cheesecake->id, 'qty' => 1, 'capacity' => 0]);

        $response = $this->actingAs(User::factory()->create(['operator_id' => 1]))
            ->getJson("/vends/{$vend->id}/smart-planogram")
            ->assertOk()
            ->assertJsonPath('price_type', 2);

        $items = collect($response->json('items'))->keyBy('channel_code');

        $this->assertSame(1, $items['11']['qty']);
        $this->assertSame(10, $items['11']['capacity'], "the product's pieces per freezer slot");
        $this->assertSame(450, $items['11']['price_cents'], 'the Site tier (RP2), in cents');

        $this->assertSame(6, $items['13']['capacity'], "the mapping's Real Capacity beats the product's");
        $this->assertNull($items['31']['capacity'], 'unmeasured');
        $this->assertNull($items['31']['price_cents'], 'no RP2 price set');
    }
}
