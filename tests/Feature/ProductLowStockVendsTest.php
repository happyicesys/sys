<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Operator;
use App\Models\Product;
use App\Models\Scopes\OperatorVendFilterScope;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * "stock <= 2" drill-down on the Warehouse Qty page
 * (GET /products/availability/low-stock-vends/{product_id}): the machines
 * whose TOTAL qty of the SKU across active channels is <= 2, with the Site
 * and its refilling route (zone). Deployed machines only, and the viewer's
 * operator boundary is applied by hand (raw join to vends — see CLAUDE.md).
 */
class ProductLowStockVendsTest extends TestCase
{
    use RefreshDatabase;

    private Operator $hipl;

    private Operator $opA;

    protected function setUp(): void
    {
        parent::setUp();

        Permission::findOrCreate('read products', 'web');

        // HIPL must sit on the unrestricted id (RefreshDatabase does not
        // reset AUTO_INCREMENT, so insertion order gives no stable id).
        DB::table('operators')->insert([
            'id' => OperatorVendFilterScope::UNRESTRICTED_OPERATOR_ID,
            'code' => 'HIPL', 'name' => 'HIPL', 'is_active' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->hipl = Operator::withoutGlobalScopes()->findOrFail(OperatorVendFilterScope::UNRESTRICTED_OPERATOR_ID);
        $this->opA = Operator::withoutGlobalScopes()->firstOrCreate(['code' => 'OPA'], ['name' => 'OPA', 'is_active' => true]);
    }

    public function test_lists_machines_whose_total_qty_is_two_or_less_with_site_and_route(): void
    {
        $product = Product::create(['code' => 'LS1', 'name' => 'Low Stock Bar']);
        $zoneId = DB::table('zones')->insertGetId(['name' => 'HC A', 'created_at' => now(), 'updated_at' => now()]);

        // 1+1 across two channels = 2 -> listed
        $low = $this->makeVend($this->hipl, 9001, 'Alpha Condo', $zoneId);
        $this->makeChannel($low, 11, $product->id, 1);
        $this->makeChannel($low, 12, $product->id, 1);
        // empty machine -> listed (qty 0)
        $empty = $this->makeVend($this->hipl, 9002, 'Bravo Camp', null);
        $this->makeChannel($empty, 11, $product->id, 0);
        // 2+2 = 4 -> NOT listed (per-machine total, not per-channel)
        $healthy = $this->makeVend($this->hipl, 9003, 'Charlie Mall', $zoneId);
        $this->makeChannel($healthy, 11, $product->id, 2);
        $this->makeChannel($healthy, 12, $product->id, 2);
        // low qty but the channel is inactive -> machine is not carrying the SKU
        $inactive = $this->makeVend($this->hipl, 9004, 'Delta Block', $zoneId);
        $this->makeChannel($inactive, 11, $product->id, 1, isActiveChannel: false);
        // low qty but parked in the warehouse (unbound) -> excluded
        $unbound = $this->makeVend($this->hipl, 9005, 'Echo Site', $zoneId, bind: false);
        $this->makeChannel($unbound, 11, $product->id, 1);

        $user = User::factory()->create(['operator_id' => $this->hipl->id]);
        $user->givePermissionTo('read products');

        $response = $this->actingAs($user)
            ->getJson("/products/availability/low-stock-vends/{$product->id}")
            ->assertOk();

        $vends = collect($response->json('vends'));
        $this->assertSame([9001, 9002], $vends->pluck('vend_code')->sort()->values()->all());

        $alpha = $vends->firstWhere('vend_code', 9001);
        $this->assertSame(2, $alpha['qty']);
        $this->assertSame('Alpha Condo', $alpha['site_name']);
        $this->assertSame('HC A', $alpha['zone_name']);
        $this->assertSame(
            DB::table('vends')->where('id', $low)->value('customer_id') + Customer::RUNNING_NUMBER_INIT,
            $alpha['site_ref_id']
        );

        $bravo = $vends->firstWhere('vend_code', 9002);
        $this->assertSame(0, $bravo['qty']);
        $this->assertNull($bravo['zone_name']);
    }

    public function test_viewer_operator_boundary_is_applied(): void
    {
        // Owned by the restricted operator, so the product global scope lets
        // the viewer reach it — the boundary under test is on the VENDS join.
        $product = Product::create(['code' => 'LS2', 'name' => 'Scoped Bar', 'operator_id' => $this->opA->id]);

        $own = $this->makeVend($this->opA, 9101, 'Own Site', null);
        $this->makeChannel($own, 11, $product->id, 1);
        $foreign = $this->makeVend($this->hipl, 9102, 'Foreign Site', null);
        $this->makeChannel($foreign, 11, $product->id, 1);

        $viewer = User::factory()->create(['operator_id' => $this->opA->id]);
        $viewer->givePermissionTo('read products');

        $vends = $this->actingAs($viewer)
            ->getJson("/products/availability/low-stock-vends/{$product->id}")
            ->assertOk()
            ->json('vends');

        $this->assertSame([9101], collect($vends)->pluck('vend_code')->all());
    }

    // ---------------------------------------------------------------- fixtures

    /** @return int vend id */
    private function makeVend(Operator $operator, int $code, string $siteName, ?int $zoneId, bool $bind = true): int
    {
        $customerId = DB::table('customers')->insertGetId([
            'name' => $siteName,
            'profile_id' => 1,
            'status_id' => 1,
            'zone_id' => $zoneId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return DB::table('vends')->insertGetId([
            'code' => $code,
            'name' => "Machine {$code}",
            'operator_id' => $operator->id,
            'customer_id' => $bind ? $customerId : null,
            'is_active' => 1,
            'is_disposed' => 0,
            'is_testing' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function makeChannel(int $vendId, int $code, int $productId, int $qty, bool $isActiveChannel = true): void
    {
        DB::table('vend_channels')->insert([
            'vend_id' => $vendId,
            'code' => $code,
            'product_id' => $productId,
            'qty' => $qty,
            'capacity' => 10,
            'amount' => 200,
            'is_active' => $isActiveChannel ? 1 : 0,
            'error_rate_json' => '{}',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
