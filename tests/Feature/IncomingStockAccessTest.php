<?php

namespace Tests\Feature;

use App\Exports\IncomingStockHistoryExport;
use App\Exports\ProductMovementTrackingExport;
use App\Models\Product;
use App\Models\ProductMovement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia;
use Maatwebsite\Excel\Facades\Excel;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Follow-up to audit UI-02 (2026-10-17): the Incoming Stock pages trusted `read products` for the
 * ledger writes, and ProductMovement has no operator scope, so every operator's batches showed to
 * every viewer — and the tracking page took the operator list from the request. A restricted
 * viewer now sees only its own operator's movements; HIPL (operator 1) still sees all.
 */
class IncomingStockAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        foreach ([1 => 'HIPL', 2 => 'OPB'] as $id => $code) {
            DB::table('operators')->insert(['id' => $id, 'code' => $code, 'name' => $code, 'is_active' => 1, 'created_at' => now(), 'updated_at' => now()]);
        }
    }

    private function staff(int $operatorId, array $verbs = ['read', 'update']): User
    {
        $user = User::factory()->create(['operator_id' => $operatorId]);
        foreach ($verbs as $verb) {
            $user->givePermissionTo(Permission::findOrCreate("{$verb} products", 'web'));
        }

        return $user;
    }

    private function incoming(string $batch, int $operatorId, int $qty = 10): Product
    {
        $product = Product::forceCreate(['code' => "P-{$batch}", 'name' => "Product {$batch}", 'operator_id' => $operatorId, 'is_inventory' => true, 'is_active' => true]);
        DB::table('product_movements')->insert([
            'product_id' => $product->id, 'type' => ProductMovement::TYPE_INCOMING, 'qty' => $qty,
            'batch_number' => $batch, 'operator_id' => $operatorId, 'created_at' => now(), 'updated_at' => now(),
        ]);

        return $product;
    }

    public function test_read_alone_cannot_key_incoming_stock(): void
    {
        $product = $this->incoming('HIPL-1', 1);
        $reader = $this->staff(1, ['read']);

        $this->actingAs($reader)->get('/products/movements/batch-incoming')->assertForbidden();
        $this->actingAs($reader)->post('/products/movements/batch-incoming', [
            'batch_number' => 'X', 'created_at' => '2026-10-17', 'products' => [['id' => $product->id, 'qty' => 5]],
        ])->assertForbidden();
        $this->actingAs($reader)->post('/products/movements', [
            'product_id' => $product->id, 'qty' => 5, 'type' => ProductMovement::TYPE_ADJUSTMENT,
        ])->assertForbidden();

        $this->assertSame(1, ProductMovement::count());
    }

    public function test_a_role_with_update_still_keys_incoming_stock(): void
    {
        $product = $this->incoming('HIPL-1', 1);

        $this->actingAs($this->staff(1))->post('/products/movements', [
            'product_id' => $product->id, 'qty' => 5, 'type' => ProductMovement::TYPE_ADJUSTMENT,
        ])->assertRedirect();

        $this->assertSame(2, ProductMovement::count());
    }

    public function test_another_operator_does_not_see_hipl_batches(): void
    {
        $this->incoming('HIPL-1', 1);
        $this->incoming('OPB-1', 2);
        $outsider = $this->staff(2);

        $this->actingAs($outsider)->get('/products/movements/incoming-history')
            ->assertInertia(fn (AssertableInertia $page) => $this->assertSame(['OPB-1'],
                array_column($page->toArray()['props']['history']['data'], 'batch_number')));
        $this->actingAs($outsider)->get('/products/movements/incoming-history/HIPL-1')->assertNotFound();
        $this->actingAs($outsider)->get('/products/movements/incoming-history/OPB-1')->assertOk();

        $this->actingAs($this->staff(1))->get('/products/movements/incoming-history')
            ->assertInertia(fn (AssertableInertia $page) => $this->assertEqualsCanonicalizing(['HIPL-1', 'OPB-1'],
                array_column($page->toArray()['props']['history']['data'], 'batch_number')));
    }

    public function test_the_history_export_keeps_to_the_viewers_operator(): void
    {
        $this->incoming('HIPL-1', 1);
        $this->incoming('OPB-1', 2);
        $this->actingAs($this->staff(2));

        $batches = (new IncomingStockHistoryExport(request()))->query()->pluck('batch_number')->all();

        $this->assertSame(['OPB-1'], $batches);
    }

    public function test_tracking_ignores_another_operator_named_in_the_request(): void
    {
        $this->incoming('HIPL-1', 1);
        $this->incoming('OPB-1', 2);
        $outsider = $this->staff(2);
        $today = now()->toDateString();

        $this->actingAs($outsider)->get('/products/movements/tracking?'.http_build_query(['operators' => [1, 2], 'date_from' => $today, 'date_to' => $today]))
            ->assertOk()
            ->assertInertia(function (AssertableInertia $page) {
                $remarks = collect($page->toArray()['props']['movements']['data'])->pluck('remarks')->unique()->values()->all();
                $this->assertSame(['OPB-1'], $remarks);
            });

        Excel::fake();
        $this->actingAs($outsider)->get('/products/movements/tracking-export-excel?'.http_build_query(['operators' => '1,2', 'date_from' => $today, 'date_to' => $today]));
        Excel::assertDownloaded('Product_Movement_Tracking_'.now()->format('ymdHis').'.xlsx', function (ProductMovementTrackingExport $export) {
            $remarks = collect($export->collection())->pluck('remarks')->filter()->unique()->values()->all();
            $this->assertSame(['OPB-1'], $remarks);

            return true;
        });
    }
}
