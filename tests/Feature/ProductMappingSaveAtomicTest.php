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
 * Mapping Save deletes every item and recreates it from the page. Half of that is a mapping with
 * missing rows, which `syncChannels()` then turns into channels with no product on every bound
 * machine — unattributed sales (audit CORE-06, 2026-10-17). It is one transaction, and the
 * products are read once, not once per row.
 */
class ProductMappingSaveAtomicTest extends TestCase
{
    use RefreshDatabase;

    private ProductMapping $mapping;

    /** @var Product[] */
    private array $products = [];

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        DB::table('operators')->insert([
            'id' => OperatorVendFilterScope::UNRESTRICTED_OPERATOR_ID, 'code' => 'HIPL', 'name' => 'HIPL', 'is_active' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        foreach (range(1, 8) as $i) {
            $this->products[] = Product::create(['code' => "P-{$i}", 'name' => "Product {$i}", 'freezer_slot_qty' => 6]);
        }
        $this->mapping = ProductMapping::create([
            'name' => 'Freezer planogram', 'machine_type' => Vend::MACHINE_TYPE_SMART_FREEZER,
            'is_smart' => true, 'is_active' => true, 'operator_id' => OperatorVendFilterScope::UNRESTRICTED_OPERATOR_ID,
        ]);
        foreach ([11, 12] as $i => $code) {
            ProductMappingItem::create(['product_mapping_id' => $this->mapping->id, 'channel_code' => (string) $code, 'product_id' => $this->products[$i]->id, 'sequence' => $i]);
        }
    }

    private function user(): User
    {
        $user = User::factory()->create(['operator_id' => OperatorVendFilterScope::UNRESTRICTED_OPERATOR_ID]);
        $user->givePermissionTo(Permission::findOrCreate('read product-mappings', 'web'));
        $user->givePermissionTo(Permission::findOrCreate('update product-mappings', 'web'));

        return $user;
    }

    private function rows(int $count): array
    {
        return array_map(fn ($i) => [
            'channel_code' => (string) (11 + 10 * intdiv($i, 2) + $i % 2),
            'product' => ['id' => $this->products[$i]->id],
            'sequence' => $i,
            'capacity_override' => $i % 2 ? 4 : 6,
        ], range(0, $count - 1));
    }

    public function test_a_save_that_fails_part_way_leaves_the_items_as_they_were(): void
    {
        $rows = $this->rows(3);
        unset($rows[1]['sequence']); // the second row breaks after the first was recreated

        $this->withoutExceptionHandling();
        try {
            $this->actingAs($this->user())->post("/product-mappings/{$this->mapping->id}/update", [
                'name' => 'Freezer planogram', 'productMappingItems' => $rows,
            ]);
            $this->fail('the broken row should have stopped the save');
        } catch (\Throwable $e) {
            // expected
        }

        $this->assertSame(
            [[11, $this->products[0]->id], [12, $this->products[1]->id]],
            ProductMappingItem::where('product_mapping_id', $this->mapping->id)->orderBy('channel_code')->get()
                ->map(fn ($i) => [(int) $i->channel_code, (int) $i->product_id])->all(),
            'both original items are still there'
        );
    }

    public function test_the_product_lookups_do_not_grow_with_the_items(): void
    {
        $queries = 0;
        DB::listen(function ($q) use (&$queries) {
            if (preg_match('/from `products`/i', $q->sql)) {
                $queries++;
            }
        });
        $user = $this->user();
        $save = function (int $items) use ($user, &$queries): int {
            $queries = 0;
            $this->actingAs($user)->post("/product-mappings/{$this->mapping->id}/update", [
                'name' => 'Freezer planogram', 'productMappingItems' => $this->rows($items),
            ])->assertSessionHasNoErrors();

            return $queries;
        };

        $small = $save(2);
        $large = $save(8);

        $this->assertSame($small, $large, "products queries: {$small} for 2 items, {$large} for 8");
        $items = ProductMappingItem::where('product_mapping_id', $this->mapping->id)->get()->keyBy('channel_code');
        $this->assertCount(8, $items);
        $this->assertNull($items['11']->capacity_override, 'an override equal to the default is stored as NULL');
        $this->assertSame(4, (int) $items['12']->capacity_override);
    }
}
