<?php

namespace Tests\Feature;

use App\Models\DeliveryPlatformRefNumber;
use App\Models\Scopes\OperatorVendFilterScope;
use App\Models\User;
use App\Support\OperatorScope;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Operation Dashboard Grab badge + "Grab Store" filter (Brian, 2026-11-19): each row carries its machine's open Grab
 * stores, green while selling, red when paused / switched off / the machine left the store's Site; the filter picks
 * out the red ones. Both read GrabStoreHealth.
 */
class OpsDashboardGrabBadgeTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        config(['cache.default' => 'array']);
        Cache::flush();
        OperatorScope::flush();
        DB::table('operators')->insert([
            'id' => OperatorVendFilterScope::UNRESTRICTED_OPERATOR_ID, 'code' => 'HIPL', 'name' => 'HIPL', 'is_active' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->user = User::factory()->create(['operator_id' => OperatorVendFilterScope::UNRESTRICTED_OPERATOR_ID]);
        $this->user->givePermissionTo(Permission::findOrCreate('read vend-customers', 'web'));
        $this->user->givePermissionTo(Permission::findOrCreate('admin-access vend-customers', 'web'));
        OperatorScope::flush();
    }

    protected function tearDown(): void
    {
        OperatorScope::flush();
        parent::tearDown();
    }

    private function site(string $name): int
    {
        return DB::table('customers')->insertGetId([
            'name' => $name, 'profile_id' => 1, 'status_id' => 1, 'is_active' => 1,
            'operator_id' => OperatorVendFilterScope::UNRESTRICTED_OPERATOR_ID, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function machine(int $code, ?int $siteId): int
    {
        return DB::table('vends')->insertGetId([
            'code' => $code, 'name' => "Machine {$code}", 'operator_id' => OperatorVendFilterScope::UNRESTRICTED_OPERATOR_ID,
            'customer_id' => $siteId, 'is_active' => 1, 'is_testing' => 0, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function store(int $vendId, int $code, string $merchantId, ?int $siteId, bool $active = true): void
    {
        $refId = DB::table('delivery_platform_ref_numbers')->insertGetId([
            'ref_number' => $merchantId, 'status' => DeliveryPlatformRefNumber::STATUS_ACTIVE,
            'operator_id' => OperatorVendFilterScope::UNRESTRICTED_OPERATOR_ID, 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('delivery_product_mapping_vend')->insert([
            'vend_id' => $vendId, 'vend_code' => $code, 'platform_ref_id' => $merchantId, 'delivery_platform_ref_number_id' => $refId,
            'customer_id' => $siteId, 'is_active' => $active, 'start_date' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function rows(array $query = []): array
    {
        $rows = [];
        $this->actingAs($this->user)
            ->get('/vends/customers?'.http_build_query(['autoload' => 1, 'include_unbound_vends' => 1] + $query))
            ->assertOk()
            ->assertInertia(function (Assert $page) use (&$rows) {
                $rows = collect($page->toArray()['props']['vends']['data'])->keyBy(fn ($row) => (int) $row['code'])->all();
            });
        ksort($rows);

        return $rows;
    }

    public function test_rows_carry_green_and_red_grab_stores_and_the_filter_picks_the_red_ones(): void
    {
        $siteA = $this->site('Site A');
        $siteB = $this->site('Site B');
        $selling = $this->machine(1001, $siteA);
        $this->store($selling, 1001, '4-SELLING', $siteA);
        $paused = $this->machine(1002, $siteA);
        $this->store($paused, 1002, '4-PAUSED', $siteA, active: false);
        $moved = $this->machine(1003, $siteB);
        $this->store($moved, 1003, '4-MOVED', $siteA);
        $unbound = $this->machine(1004, null);
        $this->store($unbound, 1004, '4-UNBOUND', $siteA);
        $this->machine(1005, $siteA);

        $rows = $this->rows();
        $this->assertSame('selling', $rows[1001]['grab_stores'][0]['state']);
        $this->assertSame('4-SELLING', $rows[1001]['grab_stores'][0]['merchant_id']);
        $this->assertSame('problem', $rows[1002]['grab_stores'][0]['state']);
        $this->assertSame('problem', $rows[1003]['grab_stores'][0]['state']);
        $this->assertStringContainsString('Site A', $rows[1003]['grab_stores'][0]['reasons'][0]);
        $this->assertSame('problem', $rows[1004]['grab_stores'][0]['state'], 'an unbound machine shows its store too');
        $this->assertSame([], $rows[1005]['grab_stores']);

        $this->assertSame([1002, 1003, 1004], array_keys($this->rows(['grab_store_state' => 'problem'])));
        $this->assertSame([1001], array_keys($this->rows(['grab_store_state' => 'selling'])));
        $this->assertSame([1005], array_keys($this->rows(['grab_store_state' => 'none'])));
        $this->assertCount(5, $this->rows(['grab_store_state' => 'all']));
    }
}
