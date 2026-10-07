<?php

namespace Tests\Feature;

use App\Jobs\Vend\PushMappingMenuSync;
use App\Models\Product;
use App\Models\ProductMapping;
use App\Models\ProductMappingItem;
use App\Models\SellingPrice;
use App\Models\User;
use App\Models\Vend;
use App\Services\VendJobService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * A vending machine re-reads its names list only when told to. Before this,
 * only binding a machine to a mapping told it; a product added to a channel of
 * a mapping the machine was already on reached the screen with a photo and a
 * price but no name (4730, 2026-10-07). Now every planogram write and every
 * menu-visible product edit schedules one debounced push per mapping.
 */
class MappingMenuAutoPushTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Model::reguard();
        Cache::flush();
        foreach (['read product-mappings', 'read products', 'update products'] as $p) {
            Permission::findOrCreate($p, 'web');
        }
        DB::table('operators')->insertOrIgnore([
            'id' => 1, 'code' => 'HIPL', 'name' => 'HIPL', 'country_id' => 1,
            'timezone' => 'Asia/Singapore', 'is_active' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function admin(): User
    {
        $user = User::factory()->create(['operator_id' => 1]);
        $user->givePermissionTo(['read product-mappings', 'read products', 'update products']);

        return $user;
    }

    private function mapping(string $name = 'Eatz 1'): ProductMapping
    {
        return ProductMapping::create([
            'name' => $name,
            'operator_id' => 1,
            'machine_type' => Vend::MACHINE_TYPE_VENDING_MACHINE,
        ]);
    }

    private function product(string $code, string $name): Product
    {
        return Product::create(['code' => $code, 'name' => $name, 'operator_id' => 1, 'is_active' => true]);
    }

    private function item(ProductMapping $mapping, Product $product, string $channel): ProductMappingItem
    {
        $item = new ProductMappingItem;
        $item->product_mapping_id = $mapping->id;
        $item->product_id = $product->id;
        $item->channel_code = $channel;
        $item->save();

        return $item;
    }

    private function vend(int $code, ?ProductMapping $mapping, array $attrs = []): Vend
    {
        return Vend::forceCreate(array_merge([
            'code' => $code,
            'operator_id' => 1,
            'machine_type' => Vend::MACHINE_TYPE_VENDING_MACHINE,
            'is_active' => 1,
            'product_mapping_id' => $mapping?->id,
        ], $attrs));
    }

    /** @return int[] codes the job sent the menu nudge to */
    private function runJob(int $mappingId, bool $serverPriceOnly = false): array
    {
        $pushed = [];
        $service = $this->createMock(VendJobService::class);
        $service->method('syncChannelSlotListToVend')
            ->willReturnCallback(function ($vend) use (&$pushed) {
                $pushed[] = (int) $vend->code;

                return true;
            });

        (new PushMappingMenuSync($mappingId, $serverPriceOnly))->handle($service);
        sort($pushed);

        return $pushed;
    }

    // ------------------------------------------------------------- who is told

    public function test_job_tells_active_and_testing_vending_machines_on_the_mapping_only(): void
    {
        $mapping = $this->mapping();
        $other = $this->mapping('Other');
        $this->vend(4730, $mapping);
        $this->vend(4731, $mapping, ['is_active' => 0, 'is_testing' => 1]);
        $this->vend(4732, $mapping, ['is_active' => 0, 'is_testing' => 0]);
        $this->vend(50001, $mapping, ['machine_type' => Vend::MACHINE_TYPE_SMART_FREEZER]);
        $this->vend(6003, $mapping, ['machine_type' => Vend::MACHINE_TYPE_SMART_CHILLER]);
        $this->vend(4733, $other);

        $this->assertSame([4730, 4731], $this->runJob($mapping->id));
    }

    public function test_a_price_push_reaches_only_machines_on_server_price(): void
    {
        $mapping = $this->mapping();
        $this->vend(4730, $mapping, ['is_using_server_price' => 1]);
        $this->vend(4731, $mapping, ['is_using_server_price' => 0]);

        $this->assertSame([4730], $this->runJob($mapping->id, serverPriceOnly: true));
        $this->assertSame([4730, 4731], $this->runJob($mapping->id));
    }

    // ---------------------------------------------------------- mapping writes

    public function test_adding_a_product_to_a_channel_schedules_one_push_for_a_burst(): void
    {
        Queue::fake([PushMappingMenuSync::class]);
        $mapping = $this->mapping();
        $user = $this->admin();
        $a = $this->product('003', 'Golden Turmeric Rice');
        $b = $this->product('004', 'Japanese Chicken Yakiniku');

        $this->actingAs($user)->post("/product-mappings/{$mapping->id}/items/create", ['channel_code' => '13', 'product_id' => $a->id])
            ->assertSessionHasNoErrors();
        $this->actingAs($user)->post("/product-mappings/{$mapping->id}/items/create", ['channel_code' => '14', 'product_id' => $b->id])
            ->assertSessionHasNoErrors();

        Queue::assertPushed(PushMappingMenuSync::class, 1);
    }

    public function test_deleting_a_channel_schedules_a_push(): void
    {
        Queue::fake([PushMappingMenuSync::class]);
        $mapping = $this->mapping();
        $item = $this->item($mapping, $this->product('001', 'Kam Heong'), '11');

        $this->actingAs($this->admin())->delete("/product-mappings/items/{$item->id}");

        Queue::assertPushed(PushMappingMenuSync::class, 1);
    }

    // ---------------------------------------------------------- product writes

    private function productPayload(Product $product, array $overrides = []): array
    {
        return array_merge([
            'code' => $product->code,
            'name' => $product->name,
            'operator_id' => $product->operator_id,
        ], $overrides);
    }

    public function test_renaming_a_product_pushes_every_mapping_it_sits_on(): void
    {
        $product = $this->product('001', 'Kam Heong');
        $m1 = $this->mapping('M1');
        $m2 = $this->mapping('M2');
        $this->item($m1, $product, '11');
        $this->item($m2, $product, '12');
        $this->mapping('Unrelated');
        Queue::fake([PushMappingMenuSync::class]);

        $this->actingAs($this->admin())
            ->post("/products/{$product->id}/update", $this->productPayload($product, ['name' => 'Malaysian Kam Heong Chicken']))
            ->assertSessionHasNoErrors();

        Queue::assertPushed(PushMappingMenuSync::class, 2);
        Queue::assertPushed(PushMappingMenuSync::class, fn ($job) => ! $this->serverPriceOnly($job));
    }

    public function test_saving_a_product_with_nothing_visible_changed_pushes_nothing(): void
    {
        $product = $this->product('001', 'Kam Heong');
        $this->item($this->mapping(), $product, '11');
        Queue::fake([PushMappingMenuSync::class]);

        $this->actingAs($this->admin())
            ->post("/products/{$product->id}/update", $this->productPayload($product, ['remarks' => 'internal note']))
            ->assertSessionHasNoErrors();

        Queue::assertNotPushed(PushMappingMenuSync::class);
    }

    public function test_a_new_selling_price_pushes_server_price_machines_only(): void
    {
        $product = $this->product('001', 'Kam Heong');
        $this->item($this->mapping(), $product, '11');
        Queue::fake([PushMappingMenuSync::class]);

        $this->actingAs($this->admin())
            ->post("/products/{$product->id}/update", $this->productPayload($product, [
                'sellingPrices' => [['amount' => 7.90, 'type' => SellingPrice::TYPE_2]],
            ]))
            ->assertSessionHasNoErrors();

        Queue::assertPushed(PushMappingMenuSync::class, 1);
        Queue::assertPushed(PushMappingMenuSync::class, fn ($job) => $this->serverPriceOnly($job));
    }

    public function test_deleting_a_selling_price_pushes_server_price_machines(): void
    {
        $product = $this->product('001', 'Kam Heong');
        $this->item($this->mapping(), $product, '11');
        $price = SellingPrice::create(['product_id' => $product->id, 'type' => SellingPrice::TYPE_2, 'amount' => 7.90]);
        Queue::fake([PushMappingMenuSync::class]);

        $this->actingAs($this->admin())->delete("/products/selling-prices/{$price->id}");

        Queue::assertPushed(PushMappingMenuSync::class, fn ($job) => $this->serverPriceOnly($job));
    }

    private function serverPriceOnly(PushMappingMenuSync $job): bool
    {
        $prop = new \ReflectionProperty($job, 'serverPriceOnly');

        return (bool) $prop->getValue($job);
    }
}
