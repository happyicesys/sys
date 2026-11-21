<?php

namespace Tests\Feature;

use App\Jobs\PublishMqtt;
use App\Jobs\Vend\SaveVendChannelsJson;
use App\Jobs\Vend\SyncVendChannelErrorLog;
use App\Models\Customer;
use App\Models\Operator;
use App\Models\Product;
use App\Models\ProductMapping;
use App\Models\ProductMappingItem;
use App\Models\SellingPrice;
use App\Models\User;
use App\Models\Vend;
use App\Models\VendChannel;
use App\Services\Freezer\FreezerChannelSync;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * A new Pieces per freezer slot on Product → Edit reached the machine only after someone saved the
 * mapping: U-32 on 50002 read 26 / 18 although the product said 26 (Brian, 2026-11-04). Every slot
 * that follows the default now moves when the product is saved; a Real Capacity keeps its number.
 */
class ProductCapacityFollowsToMachineTest extends TestCase
{
    use RefreshDatabase;

    private int $codes = 50000;

    protected function setUp(): void
    {
        parent::setUp();
        \Illuminate\Database\Eloquent\Model::reguard(); // other suite files unguard and never reguard
        Cache::flush();
        // SyncVendChannels runs inline (queue=sync); its Redis-unique children are faked.
        Queue::fake([SyncVendChannelErrorLog::class, SaveVendChannelsJson::class, PublishMqtt::class]);
    }

    private function product(string $code, ?int $slotQty): Product
    {
        $product = Product::create(['code' => $code, 'name' => $code, 'freezer_slot_qty' => $slotQty, 'operator_id' => 1, 'is_active' => true]);
        SellingPrice::create(['product_id' => $product->id, 'type' => SellingPrice::TYPE_2, 'amount' => 3.00]);

        return $product;
    }

    /** @param  array<string,array{0:Product,1?:int}>  $layout  code => [product, Real Capacity] */
    private function freezer(array $layout, bool $synced = true, int $operatorId = 1): Vend
    {
        $mapping = ProductMapping::create(['name' => 'Layout '.$this->codes, 'machine_type' => Vend::MACHINE_TYPE_SMART_FREEZER, 'is_smart' => true, 'is_active' => true, 'operator_id' => $operatorId]);
        foreach ($layout as $code => $item) {
            ProductMappingItem::create(['product_mapping_id' => $mapping->id, 'channel_code' => (string) $code, 'product_id' => $item[0]->id, 'capacity_override' => $item[1] ?? null]);
        }
        $customer = Customer::create(['name' => 'Site '.$this->codes, 'code' => 'FS'.$this->codes, 'operator_id' => $operatorId, 'status_id' => Customer::STATUS_ACTIVE, 'selling_price_type' => SellingPrice::TYPE_2]);
        $vend = Vend::create([
            'code' => ++$this->codes, 'machine_type' => Vend::MACHINE_TYPE_SMART_FREEZER, 'is_active' => 1, 'operator_id' => $operatorId,
            'vend_model_id' => 1, 'customer_id' => $customer->id, 'product_mapping_id' => $mapping->id, 'is_using_server_price' => true,
        ]);
        if ($synced) {
            app(FreezerChannelSync::class)->sync($vend);
        }

        return $vend;
    }

    private function saveProduct(Product $product, ?int $slotQty, int $operatorId = 1)
    {
        $user = User::factory()->create(['operator_id' => $operatorId]);
        foreach (['read products', 'update products'] as $permission) {
            $user->givePermissionTo(Permission::findOrCreate($permission, 'web'));
        }

        return $this->actingAs($user)->from("/products/{$product->id}/edit")
            ->post("/products/{$product->id}/update", ['code' => $product->code, 'name' => $product->name, 'operator_id' => $product->operator_id, 'freezer_slot_qty' => $slotQty])
            ->assertSessionHasNoErrors();
    }

    private function capacity(Vend $vend, Product $product): ?int
    {
        return VendChannel::where('vend_id', $vend->id)->where('product_id', $product->id)->value('capacity');
    }

    public function test_every_freezer_following_the_default_takes_the_new_capacity_and_keeps_its_stock(): void
    {
        $product = $this->product('U-32', 18);
        $first = $this->freezer(['51' => [$product]]);
        $second = $this->freezer(['21' => [$product]]);
        VendChannel::where('vend_id', $first->id)->update(['qty' => 7]);

        $this->saveProduct($product, 26);

        $this->assertSame(26, $this->capacity($first, $product));
        $this->assertSame(26, $this->capacity($second, $product));
        $this->assertSame(7, (int) VendChannel::where('vend_id', $first->id)->value('qty'), 'stock is the ledger, not the planogram');
    }

    public function test_baskets_one_and_four_take_half_of_the_new_number(): void
    {
        $product = $this->product('CC-01', 20);
        $vend = $this->freezer(['11' => [$product]]);
        $this->assertSame(10, $this->capacity($vend, $product));

        $this->saveProduct($product, 30);

        $this->assertSame(15, $this->capacity($vend, $product));
    }

    public function test_a_real_capacity_keeps_its_number_while_other_machines_follow(): void
    {
        $product = $this->product('U-12', 20);
        $adjusted = $this->freezer(['22' => [$product, 16]]);
        $following = $this->freezer(['22' => [$product]]);

        $this->saveProduct($product, 48);

        $this->assertSame(16, $this->capacity($adjusted, $product));
        $this->assertSame(48, $this->capacity($following, $product));
    }

    public function test_a_sku_on_two_slots_moves_only_the_slot_that_follows(): void
    {
        $product = $this->product('U-16', 20);
        $vend = $this->freezer(['21' => [$product, 16], '22' => [$product]]);
        $this->assertSame(36, $this->capacity($vend, $product));

        $this->saveProduct($product, 40);

        $this->assertSame(56, $this->capacity($vend, $product));
    }

    public function test_clearing_the_number_leaves_the_slot_unmeasured(): void
    {
        $product = $this->product('U-70', 28);
        $vend = $this->freezer(['53' => [$product]]);

        $this->saveProduct($product, null);

        $this->assertSame(0, $this->capacity($vend, $product));
    }

    public function test_an_editor_limited_to_one_operator_reaches_every_freezer_and_leaves_other_skus_alone(): void
    {
        $owner = Operator::create(['code' => 'OTH', 'name' => 'Other', 'country_id' => 1, 'timezone' => 'Asia/Singapore', 'is_active' => true]);
        $editor = Operator::create(['code' => 'EDT', 'name' => 'Editor', 'country_id' => 1, 'timezone' => 'Asia/Singapore', 'is_active' => true]);
        $product = $this->product('U-89', 26);
        $hidden = $this->product('U-70', 28);
        $product->update(['operator_id' => $editor->id]);
        $hidden->update(['operator_id' => $owner->id]);
        $vend = $this->freezer(['52' => [$product], '53' => [$hidden]], operatorId: $owner->id);

        $this->saveProduct($product, 30, $editor->id);

        $this->assertSame(30, $this->capacity($vend, $product), "the editor's product reaches a machine the editor cannot see");
        $this->assertSame(28, $this->capacity($vend, $hidden), 'a SKU the editor cannot see keeps its capacity, not 0');
    }

    public function test_a_freezer_with_no_rows_yet_is_not_given_any(): void
    {
        $product = $this->product('U-79', 26);
        $vend = $this->freezer(['13' => [$product]], synced: false);

        $this->saveProduct($product, 30);

        $this->assertSame(0, VendChannel::where('vend_id', $vend->id)->count());
    }

    public function test_a_save_that_leaves_the_number_alone_syncs_nothing(): void
    {
        $product = $this->product('U-05', 28);
        $vend = $this->freezer(['31' => [$product]]);
        VendChannel::where('vend_id', $vend->id)->update(['capacity' => 99]);

        $this->saveProduct($product, 28);

        $this->assertSame(99, $this->capacity($vend, $product), 'nothing re-wrote the row');
    }

    public function test_a_vending_machine_carrying_the_product_is_left_alone(): void
    {
        $product = $this->product('U-11', 50);
        $mapping = ProductMapping::create(['name' => 'Vending', 'machine_type' => Vend::MACHINE_TYPE_VENDING_MACHINE, 'is_smart' => false, 'is_active' => true, 'operator_id' => 1]);
        ProductMappingItem::create(['product_mapping_id' => $mapping->id, 'channel_code' => '11', 'product_id' => $product->id]);
        $vend = Vend::create(['code' => 2999, 'machine_type' => Vend::MACHINE_TYPE_VENDING_MACHINE, 'is_active' => 1, 'operator_id' => 1, 'vend_model_id' => 1, 'product_mapping_id' => $mapping->id]);
        $row = VendChannel::create(['vend_id' => $vend->id, 'code' => 11, 'product_id' => $product->id, 'qty' => 3, 'capacity' => 8, 'amount' => 170, 'is_active' => true]);

        $this->saveProduct($product, 60);

        $this->assertSame(8, (int) $row->fresh()->capacity, "a vending slot's capacity is the board's");
        $this->assertSame($product->id, (int) $row->fresh()->product_id);
    }
}
