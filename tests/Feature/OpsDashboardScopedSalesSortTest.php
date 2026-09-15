<?php

namespace Tests\Feature;

use App\Models\Scopes\OperatorVendFilterScope;
use App\Models\User;
use App\Support\OperatorScope;
use App\Support\ProductAccess;
use App\Support\ProductScopedSales;
use App\Support\TransactionAccess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Operation Dashboard (Lite) for a product-restricted viewer (prod_owner):
 * sorting by Sales(qty) used to do nothing, because the sort guard swapped every
 * money key for balance_percent. The column shows the viewer's OWN product
 * sales (ProductScopedSales), so that is what the rows must be ranked by -
 * never the whole-machine rollup, which would leak other suppliers' ranking.
 */
class OpsDashboardScopedSalesSortTest extends TestCase
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
            'code' => 'HIPL',
            'name' => 'HIPL',
            'is_active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('products')->insert([
            ['id' => self::OWN_PRODUCT, 'name' => 'Own SKU'],
            ['id' => self::OTHER_PRODUCT, 'name' => 'Other supplier SKU'],
        ]);
    }

    protected function tearDown(): void
    {
        OperatorScope::flush();
        ProductAccess::flush();
        ProductScopedSales::flush();
        parent::tearDown();
    }

    private function restrictedUser(): User
    {
        $user = User::factory()->create([
            'operator_id' => OperatorVendFilterScope::UNRESTRICTED_OPERATOR_ID,
            'product_access_mode' => ProductAccess::MODE_LIST,
        ]);
        DB::table('product_user')->insert(['user_id' => $user->id, 'product_id' => self::OWN_PRODUCT]);
        $user->givePermissionTo(Permission::findOrCreate('read vend-customers-lite', 'web'));
        OperatorScope::flush();

        return $user;
    }

    /** A bound machine carrying the viewer's product, with yesterday's sales. */
    private function machine(int $code, int $ownCents, int $otherCents): void
    {
        $customerId = DB::table('customers')->insertGetId([
            'name' => "Site {$code}",
            'profile_id' => 1,
            'status_id' => 2,
            'is_active' => 1,
            'operator_id' => OperatorVendFilterScope::UNRESTRICTED_OPERATOR_ID,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $vendId = DB::table('vends')->insertGetId([
            'code' => $code,
            'operator_id' => OperatorVendFilterScope::UNRESTRICTED_OPERATOR_ID,
            'customer_id' => $customerId,
            'is_active' => 1,
            'is_testing' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('vend_channels')->insert([
            ['vend_id' => $vendId, 'code' => 11, 'product_id' => self::OWN_PRODUCT, 'is_active' => 1, 'capacity' => 10, 'qty' => 5],
            ['vend_id' => $vendId, 'code' => 12, 'product_id' => self::OTHER_PRODUCT, 'is_active' => 1, 'capacity' => 10, 'qty' => 5],
        ]);

        $yesterday = now()->subDay()->toDateString();
        foreach ([self::OWN_PRODUCT => $ownCents, self::OTHER_PRODUCT => $otherCents] as $productId => $cents) {
            DB::table('vend_product_records')->insert([
                'vend_id' => $vendId,
                'product_id' => $productId,
                'date' => $yesterday,
                'total_amount' => $cents,
                'total_count' => intdiv($cents, 100),
            ]);
        }
    }

    /** @return int[] machine codes in the order the grid rendered them */
    private function codesSortedBy(User $user, string $sortKey, bool $sortBy, int $perPage = 50, int $page = 1): array
    {
        $codes = [];

        $this->actingAs($user)
            ->get('/vends/customers-lite?'.http_build_query([
                'autoload' => 1,
                'sortKey' => $sortKey,
                'sortBy' => $sortBy ? 'true' : 'false',
                'numberPerPage' => $perPage,
                'page' => $page,
            ]))
            ->assertOk()
            ->assertInertia(function (Assert $page) use (&$codes) {
                $codes = collect($page->toArray()['props']['vends']['data'])
                    ->pluck('code')
                    ->map(fn ($c) => (int) $c)
                    ->all();
            });

        return $codes;
    }

    public function test_sales_sort_ranks_by_the_viewers_own_product_sales(): void
    {
        // Whole-machine order would be 1003, 1001, 1002 - the other supplier
        // dominates 1003. Own-product order is 1002, 1001, 1003.
        $this->machine(1001, ownCents: 2000, otherCents: 1000);
        $this->machine(1002, ownCents: 5000, otherCents: 0);
        $this->machine(1003, ownCents: 100, otherCents: 90000);
        $user = $this->restrictedUser();

        $this->assertSame([1002, 1001, 1003], $this->codesSortedBy($user, 'totals_json->yesterday_amount', false));

        ProductScopedSales::flush();
        $this->assertSame([1003, 1001, 1002], $this->codesSortedBy($user, 'totals_json->yesterday_amount', true));
    }

    public function test_the_order_holds_across_pages(): void
    {
        $this->machine(1001, ownCents: 2000, otherCents: 0);
        $this->machine(1002, ownCents: 5000, otherCents: 0);
        $this->machine(1003, ownCents: 100, otherCents: 0);
        $user = $this->restrictedUser();

        $this->assertSame([1002, 1001], $this->codesSortedBy($user, 'totals_json->yesterday_amount', false, perPage: 2));
        ProductScopedSales::flush();
        $this->assertSame([1003], $this->codesSortedBy($user, 'totals_json->yesterday_amount', false, perPage: 2, page: 2));
    }

    public function test_tied_rows_are_neither_repeated_nor_dropped_across_pages(): void
    {
        foreach ([1001, 1002, 1003, 1004, 1005] as $code) {
            $this->machine($code, ownCents: 0, otherCents: 700);
        }
        $user = $this->restrictedUser();

        $seen = [];
        foreach ([1, 2, 3] as $page) {
            ProductScopedSales::flush();
            $seen = array_merge($seen, $this->codesSortedBy($user, 'totals_json->yesterday_amount', false, perPage: 2, page: $page));
        }

        sort($seen);
        $this->assertSame([1001, 1002, 1003, 1004, 1005], $seen);
    }

    /**
     * prod_owner "supreme", 2026-09-14: Transaction Access From moved to today,
     * Dashboard and Transactions dropped yesterday, Lite's Sales(qty) still
     * showed it - the scoped figures are raw reads the scope never reaches.
     */
    public function test_sales_figures_respect_transaction_access_from(): void
    {
        $this->machine(1001, ownCents: 2030, otherCents: 0);
        $user = $this->restrictedUser();

        $this->assertSame(2030, $this->salesFor($user, 1001)['yesterday_amount']);

        $user->forceFill(['transaction_access_from' => now()->toDateString()])->save();
        TransactionAccess::flush();
        ProductScopedSales::flush();

        $sales = $this->salesFor($user->fresh(), 1001);
        $this->assertSame(0, $sales['yesterday_amount']);
        $this->assertSame(0, $sales['seven_days_amount']);
        $this->assertSame(0, $sales['thirty_days_amount']);
    }

    /** @return array<string, mixed> the Sales(qty) blob the grid row carries */
    private function salesFor(User $user, int $code): array
    {
        $sales = [];

        $this->actingAs($user)
            ->get('/vends/customers-lite?autoload=1')
            ->assertOk()
            ->assertInertia(function (Assert $page) use (&$sales, $code) {
                $row = collect($page->toArray()['props']['vends']['data'])->firstWhere('code', $code);
                $sales = (array) ($row['vendTransactionTotalsJson'] ?? []);
            });

        return $sales;
    }

    public function test_sortable_key_parsing(): void
    {
        $this->assertSame('today_amount', ProductScopedSales::sortableKeyOf('totals_json->today_amount'));
        $this->assertSame('seven_days_count', ProductScopedSales::sortableKeyOf('vend_transaction_totals_json->seven_days_count'));
        $this->assertNull(ProductScopedSales::sortableKeyOf('totals_json->thirty_days_gross_profit'));
        $this->assertNull(ProductScopedSales::sortableKeyOf('totals_json->two_days_error_rate'));
        $this->assertNull(ProductScopedSales::sortableKeyOf('balance_percent'));
        $this->assertNull(ProductScopedSales::sortableKeyOf(null));
    }
}
