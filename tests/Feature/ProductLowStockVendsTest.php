<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Operator;
use App\Models\OpsJob;
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

        // The drill-down belongs to the Warehouse Qty page, so it is gated on
        // that page's own permission (split off 'read products' 2026-09-10).
        Permission::findOrCreate('read product-availability', 'web');

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
        DB::table('vends')->where('id', $low)->update(['vend_prefix_id' => DB::table('vend_prefixes')->insertGetId([
            'name' => 'UUD-11B-11B', 'created_at' => now(), 'updated_at' => now(),
        ])]);
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
        $user->givePermissionTo('read product-availability');

        $response = $this->actingAs($user)
            ->getJson("/products/availability/low-stock-vends/{$product->id}")
            ->assertOk();

        $vends = collect($response->json('vends'));
        $this->assertSame(['9001', '9002'], $vends->pluck('vend_code')->sort()->values()->all());  // vend_code is the label ("C6001" for a chiller), so a string

        $alpha = $vends->firstWhere('vend_code', '9001');
        $this->assertSame(2, $alpha['qty']);
        $this->assertSame('UUD-11B-11B', $alpha['vend_prefix_name'], 'the Machine Prefix column');
        $this->assertSame('Alpha Condo', $alpha['site_name']);
        $this->assertSame('HC A', $alpha['zone_name']);
        $this->assertSame(
            DB::table('vends')->where('id', $low)->value('customer_id') + Customer::RUNNING_NUMBER_INIT,
            $alpha['site_ref_id']
        );

        $bravo = $vends->firstWhere('vend_code', '9002');
        $this->assertSame(0, $bravo['qty']);
        $this->assertNull($bravo['vend_prefix_name'], 'a machine with no prefix');
        $this->assertNull($bravo['zone_name']);
    }

    public function test_scope_all_and_with_stock_mirror_the_page_counts(): void
    {
        $product = Product::create(['code' => 'LS4', 'name' => 'Scoped Counts Bar']);

        // 5+0 -> carrying, has stock (any channel > 0), not low
        $full = $this->makeVend($this->hipl, 9301, 'Full Site', null);
        $this->makeChannel($full, 11, $product->id, 5);
        $this->makeChannel($full, 12, $product->id, 0);
        // 1 -> carrying, has stock, low
        $low = $this->makeVend($this->hipl, 9302, 'Low Site', null);
        $this->makeChannel($low, 11, $product->id, 1);
        // 0 -> carrying, no stock, low
        $empty = $this->makeVend($this->hipl, 9303, 'Empty Site', null);
        $this->makeChannel($empty, 11, $product->id, 0);
        // unbound / inactive channel -> never counted in any scope
        $unbound = $this->makeVend($this->hipl, 9304, 'Parked', null, bind: false);
        $this->makeChannel($unbound, 11, $product->id, 5);
        $inactive = $this->makeVend($this->hipl, 9305, 'Inactive Channel', null);
        $this->makeChannel($inactive, 11, $product->id, 5, isActiveChannel: false);

        $user = User::factory()->create(['operator_id' => $this->hipl->id]);
        $user->givePermissionTo('read product-availability');
        $this->actingAs($user);

        $codes = fn (?string $scope) => collect($this->getJson(
            "/products/availability/low-stock-vends/{$product->id}".($scope ? "?scope={$scope}" : '')
        )->assertOk()->json('vends'))->pluck('vend_code')->sort()->values()->all();

        $this->assertSame(['9301', '9302', '9303'], $codes('all'));
        $this->assertSame(['9301', '9302'], $codes('with_stock'));
        $this->assertSame(['9302', '9303'], $codes('low'));
        // No scope keeps the original "stock <= 2" meaning.
        $this->assertSame(['9302', '9303'], $codes(null));

        $this->getJson("/products/availability/low-stock-vends/{$product->id}?scope=bogus")
            ->assertStatus(422);
    }

    public function test_reports_the_last_completed_job_date_and_its_age(): void
    {
        $product = Product::create(['code' => 'LS3', 'name' => 'Aged Bar']);

        $refilled = $this->makeVend($this->hipl, 9201, 'Refilled Site', null);
        $this->makeChannel($refilled, 11, $product->id, 1);
        // Delivered 5 days ago is the answer; the older delivered job loses,
        // the cancelled one and the job dated tomorrow do not count at all.
        $this->makeJobItem($refilled, now()->subDays(5), OpsJob::STATUS_DELIVERED);
        $this->makeJobItem($refilled, now()->subDays(20), OpsJob::STATUS_VERIFIED);
        $this->makeJobItem($refilled, now()->subDay(), OpsJob::STATUS_CANCELLED);
        $this->makeJobItem($refilled, now()->addDay(), OpsJob::STATUS_DELIVERED);
        // Only ever had a job that never got delivered -> no last job.
        $pending = $this->makeVend($this->hipl, 9202, 'Pending Site', null);
        $this->makeChannel($pending, 11, $product->id, 0);
        $this->makeJobItem($pending, now()->subDays(3), OpsJob::STATUS_PICKED);

        $user = User::factory()->create(['operator_id' => $this->hipl->id]);
        $user->givePermissionTo('read product-availability');

        $vends = collect($this->actingAs($user)
            ->getJson("/products/availability/low-stock-vends/{$product->id}")
            ->assertOk()
            ->json('vends'));

        $refilledRow = $vends->firstWhere('vend_code', '9201');
        $this->assertSame(now()->subDays(5)->toDateString(), $refilledRow['last_job_date']);
        $this->assertSame(5, $refilledRow['last_job_days_ago']);

        $pendingRow = $vends->firstWhere('vend_code', '9202');
        $this->assertNull($pendingRow['last_job_date']);
        $this->assertNull($pendingRow['last_job_days_ago']);
    }

    public function test_reports_machine_l30d_and_sku_l7d_sales(): void
    {
        $product = Product::create(['code' => 'LS5', 'name' => 'Sales Bar']);
        $other = Product::create(['code' => 'LS6', 'name' => 'Other Bar']);

        $sold = $this->makeVend($this->hipl, 9401, 'Selling Site', null);
        $this->makeChannel($sold, 11, $product->id, 1);
        // VM L30d is the machine's own rollup, the Operation Dashboard's figure.
        DB::table('vends')->where('id', $sold)->update([
            'vend_transaction_totals_json' => json_encode(['thirty_days_amount' => 123450]),
        ]);
        DB::table('vend_product_records')->insert([
            // inside L7d (7 completed days + today)
            ['vend_id' => $sold, 'product_id' => $product->id, 'date' => now()->subDays(7)->toDateString(), 'total_amount' => 400, 'total_count' => 2],
            ['vend_id' => $sold, 'product_id' => $product->id, 'date' => now()->subDay()->toDateString(), 'total_amount' => 250, 'total_count' => 1],
            // older than L7d
            ['vend_id' => $sold, 'product_id' => $product->id, 'date' => now()->subDays(8)->toDateString(), 'total_amount' => 999, 'total_count' => 9],
            // another SKU at the same machine
            ['vend_id' => $sold, 'product_id' => $other->id, 'date' => now()->subDay()->toDateString(), 'total_amount' => 777, 'total_count' => 7],
        ]);
        $quiet = $this->makeVend($this->hipl, 9402, 'Quiet Site', null);
        $this->makeChannel($quiet, 11, $product->id, 1);

        $user = User::factory()->create(['operator_id' => $this->hipl->id]);
        $user->givePermissionTo('read product-availability');

        $vends = collect($this->actingAs($user)
            ->getJson("/products/availability/low-stock-vends/{$product->id}")
            ->assertOk()
            ->json('vends'));

        $row = $vends->firstWhere('vend_code', '9401');
        $this->assertSame(123450, $row['vend_thirty_days_amount']);
        $this->assertSame(650, $row['sku_seven_days_amount']);
        $this->assertSame(3, $row['sku_seven_days_count']);

        $quietRow = $vends->firstWhere('vend_code', '9402');
        $this->assertNull($quietRow['vend_thirty_days_amount'], 'no rollup yet = blank, not 0');
        $this->assertSame(0, $quietRow['sku_seven_days_amount']);
        $this->assertSame(0, $quietRow['sku_seven_days_count']);
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
        $viewer->givePermissionTo('read product-availability');

        $vends = $this->actingAs($viewer)
            ->getJson("/products/availability/low-stock-vends/{$product->id}")
            ->assertOk()
            ->json('vends');

        $this->assertSame(['9101'], collect($vends)->pluck('vend_code')->all());
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

    private function makeJobItem(int $vendId, \Illuminate\Support\Carbon $date, string $status): void
    {
        $opsJobId = DB::table('ops_jobs')->insertGetId([
            'code' => random_int(1, 999999999),
            'created_by' => 1,
            'date' => $date,
            'operator_id' => $this->hipl->id,
            'status' => $status,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('ops_job_items')->insert([
            'customer_id' => DB::table('vends')->where('id', $vendId)->value('customer_id'),
            'ops_job_id' => $opsJobId,
            'status' => $status,
            'updated_by' => 1,
            'vend_id' => $vendId,
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
