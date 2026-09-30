<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductMapping;
use App\Models\ProductMappingItem;
use App\Models\User;
use App\Models\Vend;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * ProductMapping → Edit must carry each SKU's default capacity. The page eager-
 * loaded products with a narrow column list that left out chiller_slot_qty /
 * freezer_slot_qty, so the Default column and every un-overridden Reality box
 * read "-" — a Reality equal to the default is stored as null, so ops typed 7,
 * saved, reopened and saw "-" (C6001, 2026-09-30) although the save had landed.
 */
class ProductMappingEditCapacityTest extends TestCase
{
    use RefreshDatabase;

    public function test_edit_page_sends_the_products_default_capacity_and_the_override(): void
    {
        Queue::fake();
        Permission::findOrCreate('read product-mappings', 'web');
        $user = User::factory()->create();
        $user->givePermissionTo('read product-mappings');

        $mapping = ProductMapping::create([
            'name' => 'C9999 planogram',
            'machine_type' => Vend::MACHINE_TYPE_SMART_CHILLER,
            'is_smart' => false,
            'is_active' => true,
            'operator_id' => 1,
        ]);
        $a = Product::create(['code' => 'C-A', 'name' => 'Milo can', 'chiller_slot_qty' => 7, 'freezer_slot_qty' => 12, 'is_active' => true]);
        $b = Product::create(['code' => 'C-B', 'name' => 'Red Bull can', 'chiller_slot_qty' => 7, 'is_active' => true]);
        ProductMappingItem::create(['product_mapping_id' => $mapping->id, 'channel_code' => '101', 'product_id' => $a->id]);
        ProductMappingItem::create(['product_mapping_id' => $mapping->id, 'channel_code' => '102', 'product_id' => $b->id, 'capacity_override' => 6]);

        $this->actingAs($user)
            ->get("/product-mappings/{$mapping->id}/edit")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('productMapping.data.productMappingItems.0.product.chiller_slot_qty', 7)
                ->where('productMapping.data.productMappingItems.0.product.freezer_slot_qty', 12)
                ->where('productMapping.data.productMappingItems.0.capacity_override', null)
                ->where('productMapping.data.productMappingItems.1.product.chiller_slot_qty', 7)
                ->where('productMapping.data.productMappingItems.1.capacity_override', 6)
                ->etc()
            );
    }
}
