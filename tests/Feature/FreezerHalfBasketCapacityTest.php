<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductMapping;
use App\Models\ProductMappingItem;
use App\Models\Scopes\OperatorVendFilterScope;
use App\Models\User;
use App\Models\Vend;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Freezer baskets 1 and 4 hold half a SKU's listed capacity by default (Brian, 2026-11-04). A number
 * typed on the mapping still wins.
 */
class FreezerHalfBasketCapacityTest extends TestCase
{
    use RefreshDatabase;

    private function product(?int $freezer, ?int $chiller = null): Product
    {
        return Product::create(['code' => 'P-'.uniqid(), 'name' => 'Product', 'freezer_slot_qty' => $freezer, 'chiller_slot_qty' => $chiller]);
    }

    private function capacity(string $code, ?int $freezer, ?int $override = null): int
    {
        $item = new ProductMappingItem(['channel_code' => $code, 'capacity_override' => $override]);
        $item->setRelation('product', $this->product($freezer));

        return $item->effectiveCapacity(Vend::MACHINE_TYPE_SMART_FREEZER);
    }

    public function test_baskets_1_and_4_default_to_half_and_the_others_to_all(): void
    {
        foreach (['11', '12', '13', '41', '42', '43'] as $code) {
            $this->assertSame(10, $this->capacity($code, 20), "basket of code {$code}");
        }
        foreach (['21', '31', '51', '61'] as $code) {
            $this->assertSame(20, $this->capacity($code, 20), "basket of code {$code}");
        }
    }

    public function test_half_rounds_down_to_whole_pieces_and_an_unmeasured_product_stays_unmeasured(): void
    {
        $this->assertSame(12, $this->capacity('11', 25));
        $this->assertSame(1, $this->capacity('41', 1), 'a measured product never reads as unmeasured');
        $this->assertSame(0, $this->capacity('11', null));
    }

    public function test_a_typed_real_capacity_wins(): void
    {
        $this->assertSame(16, $this->capacity('11', 20, 16));
        $this->assertSame(20, $this->capacity('41', 20, 20));
    }

    public function test_a_chiller_and_a_vending_machine_never_halve(): void
    {
        $product = $this->product(20, 20);

        $this->assertSame(20, ProductMappingItem::productDefaultCapacity($product, Vend::MACHINE_TYPE_SMART_CHILLER, '101'));
        $this->assertSame(20, ProductMappingItem::productDefaultCapacity($product, Vend::MACHINE_TYPE_SMART_CHILLER, '11'));
        $this->assertSame(0, ProductMappingItem::productDefaultCapacity($product, Vend::MACHINE_TYPE_VENDING_MACHINE, '11'));
    }

    public function test_the_item_endpoint_stores_only_a_number_that_differs_from_the_baskets_default(): void
    {
        Queue::fake();
        DB::table('operators')->insert([
            'id' => OperatorVendFilterScope::UNRESTRICTED_OPERATOR_ID, 'code' => 'HIPL', 'name' => 'HIPL', 'is_active' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $mapping = ProductMapping::create([
            'name' => 'Freezer planogram', 'machine_type' => Vend::MACHINE_TYPE_SMART_FREEZER,
            'is_smart' => true, 'is_active' => true, 'operator_id' => OperatorVendFilterScope::UNRESTRICTED_OPERATOR_ID,
        ]);
        $item = ProductMappingItem::create(['product_mapping_id' => $mapping->id, 'channel_code' => '11', 'product_id' => $this->product(20)->id]);
        $user = User::factory()->create(['operator_id' => OperatorVendFilterScope::UNRESTRICTED_OPERATOR_ID]);
        $user->givePermissionTo(Permission::findOrCreate('read product-mappings', 'web'));
        $user->givePermissionTo(Permission::findOrCreate('update product-mappings', 'web'));

        $this->actingAs($user)->post("/product-mappings/items/{$item->id}/update", ['capacity_override' => 20])->assertSessionHasNoErrors();
        $this->assertSame(20, (int) $item->fresh()->capacity_override, 'the full listed number is an override in basket 1');

        $this->actingAs($user)->post("/product-mappings/items/{$item->id}/update", ['capacity_override' => 10])->assertSessionHasNoErrors();
        $this->assertNull($item->fresh()->capacity_override, "half is basket 1's default, so nothing is stored");
    }
}
