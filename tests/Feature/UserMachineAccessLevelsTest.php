<?php

namespace Tests\Feature;

use App\Models\Scopes\OperatorVendFilterScope;
use App\Models\User;
use App\Models\VendTransaction;
use App\Support\OperatorScope;
use App\Support\ProductAccess;
use App\Support\ProductScopedSales;
use App\Support\VendAccess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Three access levels (Brian, 2026-10-27): operator → products → machines. A
 * product-limited user's machines are the ones carrying their products, each
 * Show (default) / No Show; a No Show machine disappears from everything that
 * limits by machine (App\Support\VendAccess). Everyone else keeps the
 * bind-a-machine allow-list (`user_vend`, empty = all).
 */
class UserMachineAccessLevelsTest extends TestCase
{
    use RefreshDatabase;

    private const OWN_PRODUCT = 500;

    private const OTHER_PRODUCT = 900;

    protected function setUp(): void
    {
        parent::setUp();

        config(['cache.default' => 'array']);
        Cache::flush();
        OperatorScope::flush();
        ProductAccess::flush();
        ProductScopedSales::flush();

        DB::table('operators')->insert([
            'id' => OperatorVendFilterScope::UNRESTRICTED_OPERATOR_ID,
            'code' => 'HIPL', 'name' => 'HIPL', 'is_active' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('products')->insert([
            ['id' => self::OWN_PRODUCT, 'code' => 'OK-01', 'name' => 'Own SKU', 'operator_id' => 1],
            ['id' => self::OTHER_PRODUCT, 'code' => 'ZZ-99', 'name' => 'Other supplier SKU', 'operator_id' => 1],
        ]);
    }

    protected function tearDown(): void
    {
        OperatorScope::flush();
        ProductAccess::flush();
        ProductScopedSales::flush();
        parent::tearDown();
    }

    public function test_access_mode_follows_the_product_level(): void
    {
        [$a] = $this->machine(7001);
        [$b] = $this->machine(7002);

        $plain = User::factory()->create(['operator_id' => 1]);
        $this->assertSame(VendAccess::ALL, VendAccess::forUser($plain)->mode);

        DB::table('user_vend')->insert(['user_id' => $plain->id, 'vend_id' => $a]);
        VendAccess::flush($plain->id);
        $this->assertSame([VendAccess::ONLY, [$a]], [VendAccess::forUser($plain)->mode, VendAccess::forUser($plain)->vendIds]);

        // A product-limited user ignores user_vend: every machine but the No Show ones.
        $owner = $this->productOwner();
        DB::table('user_vend')->insert(['user_id' => $owner->id, 'vend_id' => $a]);
        $this->assertSame(VendAccess::ALL, VendAccess::forUser($owner)->mode);

        DB::table('user_hidden_vends')->insert(['user_id' => $owner->id, 'vend_id' => $b]);
        VendAccess::flush($owner->id);
        $access = VendAccess::forUser($owner);
        $this->assertSame([VendAccess::EXCEPT, [$b]], [$access->mode, $access->vendIds]);
        $this->assertTrue($access->allows($a));
        $this->assertFalse($access->allows($b));
    }

    public function test_a_no_show_machine_leaves_the_owners_sales_and_dashboard(): void
    {
        [$shown, $shownSite] = $this->machine(7001);
        [$hidden, $hiddenSite] = $this->machine(7002);
        $this->sale($shown, $shownSite);
        $this->sale($hidden, $hiddenSite);

        $owner = $this->productOwner();
        $owner->givePermissionTo(Permission::findOrCreate('read vend-customers-lite', 'web'));
        $this->actingAs($owner);

        $this->assertEqualsCanonicalizing([$shown, $hidden], VendTransaction::query()->pluck('vend_id')->map(fn ($id) => (int) $id)->all());
        $this->assertEqualsCanonicalizing([7001, 7002], $this->dashboardCodes($owner));

        DB::table('user_hidden_vends')->insert(['user_id' => $owner->id, 'vend_id' => $hidden]);
        VendAccess::flush($owner->id);

        $this->assertSame([$shown], VendTransaction::query()->pluck('vend_id')->map(fn ($id) => (int) $id)->all());
        $this->assertSame([7001], $this->dashboardCodes($owner));
    }

    public function test_user_edit_lists_the_machines_carrying_the_products_and_saves_a_switch(): void
    {
        [$carrying] = $this->machine(7001);
        [$otherOnly] = $this->machine(7002, products: [self::OTHER_PRODUCT]);
        $owner = $this->productOwner();
        $admin = $this->admin(['read users', 'update users']);

        $this->actingAs($admin)->get("/users/{$owner->id}/edit")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('machineVisibility', 1)
                ->where('machineVisibility.0.id', $carrying)
                ->where('machineVisibility.0.products', ['OK-01'])
                ->where('machineVisibility.0.is_shown', true));

        $this->actingAs($admin)->postJson("/users/{$owner->id}/machine-visibility", ['vend_id' => $carrying, 'show' => false])
            ->assertOk()
            ->assertJsonPath('machines.0.is_shown', false);
        $this->assertDatabaseHas('user_hidden_vends', ['user_id' => $owner->id, 'vend_id' => $carrying, 'created_by' => $admin->id]);

        // A machine that does not carry their products is not theirs to switch.
        $this->actingAs($admin)->postJson("/users/{$owner->id}/machine-visibility", ['vend_id' => $otherOnly, 'show' => false])
            ->assertStatus(422);

        // Show again.
        $this->actingAs($admin)->postJson("/users/{$owner->id}/machine-visibility", ['vend_id' => $carrying, 'show' => true])
            ->assertOk()
            ->assertJsonPath('machines.0.is_shown', true);
        $this->assertDatabaseMissing('user_hidden_vends', ['user_id' => $owner->id]);
    }

    public function test_a_hidden_machine_that_stopped_carrying_stays_listed_to_switch_back(): void
    {
        [$vend] = $this->machine(7001);
        $owner = $this->productOwner();
        DB::table('user_hidden_vends')->insert(['user_id' => $owner->id, 'vend_id' => $vend]);
        DB::table('vend_channels')->where('vend_id', $vend)->update(['is_active' => 0]);

        $this->actingAs($this->admin(['read users']))->get("/users/{$owner->id}/edit")
            ->assertInertia(fn (Assert $page) => $page
                ->where('machineVisibility.0.id', $vend)
                ->where('machineVisibility.0.carries', false)
                ->where('machineVisibility.0.is_shown', false));
    }

    public function test_switching_needs_update_users_and_a_user_on_all_products_has_no_list(): void
    {
        [$vend] = $this->machine(7001);
        $owner = $this->productOwner();

        $this->actingAs($this->admin(['read users']))->postJson("/users/{$owner->id}/machine-visibility", ['vend_id' => $vend, 'show' => false])
            ->assertForbidden();

        $plain = User::factory()->create(['operator_id' => 1]);
        $this->actingAs($this->admin(['read users']))->get("/users/{$plain->id}/edit")
            ->assertInertia(fn (Assert $page) => $page->where('machineVisibility', null));
    }

    public function test_the_carry_over_keeps_what_a_bound_product_owner_saw(): void
    {
        [$bound] = $this->machine(7001);
        [$unbound] = $this->machine(7002);
        $owner = $this->productOwner();
        DB::table('user_vend')->insert(['user_id' => $owner->id, 'vend_id' => $bound]);

        (require base_path('database/migrations/2026_10_27_120100_carry_bound_machines_into_hidden_vends.php'))->up();

        $this->assertSame([$unbound], DB::table('user_hidden_vends')->where('user_id', $owner->id)->pluck('vend_id')->map(fn ($id) => (int) $id)->all());
        $this->assertSame(0, DB::table('user_vend')->where('user_id', $owner->id)->count());
        $access = VendAccess::forUser($owner->fresh());
        $this->assertTrue($access->allows($bound));
        $this->assertFalse($access->allows($unbound));
    }

    private function productOwner(): User
    {
        $user = User::factory()->create(['operator_id' => 1, 'product_access_mode' => ProductAccess::MODE_LIST]);
        DB::table('product_user')->insert(['user_id' => $user->id, 'product_id' => self::OWN_PRODUCT]);

        return $user;
    }

    private function admin(array $permissions): User
    {
        $admin = User::factory()->create(['operator_id' => 1]);
        foreach ($permissions as $permission) {
            $admin->givePermissionTo(Permission::findOrCreate($permission, 'web'));
        }

        return $admin;
    }

    /** @return array{0: int, 1: int} vend id, site id */
    private function machine(int $code, array $products = [self::OWN_PRODUCT, self::OTHER_PRODUCT]): array
    {
        $siteId = DB::table('customers')->insertGetId([
            'name' => "Site {$code}", 'profile_id' => 1, 'status_id' => 2, 'is_active' => 1,
            'operator_id' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $vendId = DB::table('vends')->insertGetId([
            'code' => $code, 'operator_id' => 1, 'customer_id' => $siteId, 'is_active' => 1, 'is_testing' => 0,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        foreach (array_values($products) as $i => $productId) {
            DB::table('vend_channels')->insert([
                'vend_id' => $vendId, 'code' => 11 + $i, 'product_id' => $productId, 'is_active' => 1, 'capacity' => 10, 'qty' => 5,
            ]);
        }
        DB::table('vend_product_records')->insert([
            'vend_id' => $vendId, 'product_id' => self::OWN_PRODUCT, 'date' => now()->subDay()->toDateString(),
            'total_amount' => 300, 'total_count' => 2,
        ]);

        return [$vendId, $siteId];
    }

    private function sale(int $vendId, int $siteId): void
    {
        DB::table('vend_transactions')->insert([
            'vend_id' => $vendId, 'customer_id' => $siteId, 'operator_id' => 1, 'product_id' => self::OWN_PRODUCT,
            'amount' => 150, 'transaction_datetime' => now()->subHour(), 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    /** @return int[] */
    private function dashboardCodes(User $user): array
    {
        $codes = [];
        $this->actingAs($user)->get('/vends/customers-lite?autoload=1&numberPerPage=50')
            ->assertOk()
            ->assertInertia(function (Assert $page) use (&$codes) {
                $codes = collect($page->toArray()['props']['vends']['data'])->pluck('code')->map(fn ($c) => (int) $c)->all();
            });

        return $codes;
    }
}
