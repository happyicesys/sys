<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductMovement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Batch numbers are free text. "Benelux #001" (prod, 2026-10-08) 404'd on View
 * because Ziggy left the "#" raw and the browser cut the URL there.
 */
class IncomingBatchDetailTest extends TestCase
{
    use RefreshDatabase;

    private function viewer(): User
    {
        Permission::findOrCreate('read products', 'web');

        return tap(User::factory()->create())->givePermissionTo('read products');
    }

    private function seedBatch(string $batch): void
    {
        $a = Product::create(['code' => 'IB1', 'name' => 'Tea']);
        $b = Product::create(['code' => 'IB2', 'name' => 'Crackers']);
        DB::table('product_movements')->insert([
            ['product_id' => $a->id, 'type' => ProductMovement::TYPE_INCOMING, 'qty' => 24, 'batch_number' => $batch, 'created_at' => now(), 'updated_at' => now()],
            ['product_id' => $b->id, 'type' => ProductMovement::TYPE_INCOMING, 'qty' => 12, 'batch_number' => $batch, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public static function batchNumbers(): array
    {
        return [
            'hash' => ['Benelux #001'],
            'slash' => ['PO 12/3'],
            'question mark' => ['Shopee?1'],
            'plain' => ['Scarlett 261006'],
        ];
    }

    #[DataProvider('batchNumbers')]
    public function test_encoded_batch_number_opens_its_detail(string $batch): void
    {
        $this->seedBatch($batch);

        $this->actingAs($this->viewer())
            ->get('/products/movements/incoming-history/'.rawurlencode($batch))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('ProductMovement/IncomingBatchDetail')
                ->where('metadata.batch_number', $batch)
                ->has('movements', 2));
    }

    public function test_export_route_is_not_swallowed_by_the_catch_all_batch_segment(): void
    {
        $this->assertSame(
            'product-movements.incoming-history-export',
            app('router')->getRoutes()->match(\Illuminate\Http\Request::create('/products/movements/incoming-history/export'))->getName()
        );
    }
}
