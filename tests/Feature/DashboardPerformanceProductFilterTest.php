<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\LocationType;
use App\Models\Month;
use App\Models\Operator;
use App\Models\Product;
use App\Models\User;
use App\Models\Vend;
use App\Models\VendProductRecord;
use App\Models\VendRecord;
use App\Models\VendTransaction;
use App\Models\VendTransactionItem;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Dashboard > Performance product filter (Daniel, 2026-10-29).
 *
 * With no product picked the page must read vend_records exactly as before.
 * With one picked, EVERY figure is per product (vend_product_records): sales of
 * the picked products, and machines / sites counted only where they sold.
 *
 * Two machines, two products, yesterday = 2026-08-19:
 *   machine A sold P1 $60 (3) + P2 $40 (2)  — machine total $100
 *   machine B sold P2 $50 (5)               — machine total $50
 * Today machine A sells P1 alone ($2) and a basket of P1 $2 + P2 $3 ($5).
 */
class DashboardPerformanceProductFilterTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Product $p1;

    private Product $p2;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-08-20 10:00:00');

        foreach (range(1, 12) as $number) {
            $name = Carbon::create(2026, $number, 1)->format('F');
            Month::firstOrCreate(['number' => $number], ['name' => $name, 'short_name' => substr($name, 0, 3)]);
        }

        $operator = Operator::create(['code' => 'OP1', 'name' => 'Test Operator']);
        Role::create(['name' => 'operator', 'guard_name' => 'web']);
        Permission::findOrCreate('read dashboard-performance', 'web');
        $this->user = User::factory()->create(['operator_id' => $operator->id, 'username' => 'tester']);
        $this->user->assignRole('operator');
        $this->user->givePermissionTo('read dashboard-performance');

        $office = LocationType::create(['name' => 'Office', 'sequence' => 1]);
        $machines = [];
        foreach (['A', 'B'] as $letter) {
            $customer = Customer::create(['code' => 'C'.$letter, 'name' => 'Site '.$letter, 'location_type_id' => $office->id, 'operator_id' => $operator->id]);
            $machines[$letter] = Vend::create(['code' => 'V'.$letter, 'name' => 'Machine '.$letter, 'operator_id' => $operator->id, 'customer_id' => $customer->id, 'location_type_id' => $office->id]);
        }

        Product::unguarded(function () use ($operator) {
            $this->p1 = Product::create(['code' => 'P1', 'name' => 'Cone', 'category_id' => 301, 'category_group_id' => 55, 'operator_id' => $operator->id]);
            $this->p2 = Product::create(['code' => 'P2', 'name' => 'Bar', 'category_id' => 302, 'category_group_id' => 55, 'operator_id' => $operator->id]);
        });

        $yesterday = Carbon::yesterday();
        $day = fn (Vend $vend) => [
            'vend_id' => $vend->id, 'customer_id' => $vend->customer_id, 'operator_id' => $operator->id,
            'location_type_id' => $office->id, 'date' => $yesterday, 'day' => $yesterday->day,
            'month' => $yesterday->month, 'year' => $yesterday->year, 'monthname' => $yesterday->format('F'),
        ];
        VendRecord::create($day($machines['A']) + ['total_amount' => 10000, 'total_count' => 5]);
        VendRecord::create($day($machines['B']) + ['total_amount' => 5000, 'total_count' => 5]);

        $line = fn (Vend $vend, Product $product, int $cents, int $count) => $day($vend) + [
            'product_id' => $product->id, 'product_code' => $product->code, 'product_name' => $product->name,
            'category_id' => $product->category_id, 'category_group_id' => $product->category_group_id,
            'total_amount' => $cents, 'total_count' => $count,
        ];
        VendProductRecord::create($line($machines['A'], $this->p1, 6000, 3));
        VendProductRecord::create($line($machines['A'], $this->p2, 4000, 2));
        VendProductRecord::create($line($machines['B'], $this->p2, 5000, 5));

        VendTransaction::unguarded(function () use ($machines, $operator) {
            $base = ['vend_id' => $machines['A']->id, 'customer_id' => $machines['A']->customer_id, 'operator_id' => $operator->id,
                'transaction_datetime' => Carbon::now()];
            VendTransaction::create($base + ['order_id' => 'T1', 'product_id' => $this->p1->id, 'amount' => 200, 'success_qty' => 1, 'qty' => 1]);
            $basket = VendTransaction::create($base + ['order_id' => 'T2', 'is_multiple' => true, 'amount' => 500, 'success_qty' => 2, 'qty' => 2]);
            VendTransactionItem::create(['vend_transaction_id' => $basket->id, 'product_id' => $this->p1->id, 'unit_price_amount' => 200]);
            VendTransactionItem::create(['vend_transaction_id' => $basket->id, 'product_id' => $this->p2->id, 'unit_price_amount' => 300]);
        });
    }

    private function page(array $query = [])
    {
        return $this->actingAs($this->user)->get('/dashboard/performance?'.http_build_query(['autoload' => 1] + $query));
    }

    private function today(AssertableInertia $page): array
    {
        return collect($page->toArray()['props']['dayGraphData']['data'])
            ->first(fn ($day) => (int) $day['month'] === 8 && (int) $day['day'] === 20);
    }

    public function test_without_a_product_filter_the_page_reads_whole_machines(): void
    {
        $this->page()->assertInertia(function (AssertableInertia $page) {
            $page->component('Dashboard')
                ->where('productGrain', false)
                ->where('vendCount', 2)
                ->where('monthGraphData.2026.8.amount', 150)
                ->where('activeMachineGraphData.2026.8.count', 2)
                ->where('monthsByModel.Office.8.amount', 150)
                // Machine Count: machines selling on an average day. It read a
                // column the query no longer returned and showed 0 for months.
                ->where('monthsByModel.Office.8.vend_count', 2)
                ->where('performerGraphData.data.0.amount', 100)
                ->has('productGraphData', 2)
                ->has('productOptions', 2);
            // Today = every sale's whole amount: $2 + $5.
            $this->assertEquals(7, $this->today($page)['amount']);
        });
    }

    public function test_a_picked_product_narrows_every_figure_to_that_product(): void
    {
        $this->page(['product_id' => [$this->p1->id]])->assertInertia(function (AssertableInertia $page) {
            $page->component('Dashboard')
                ->where('productGrain', true)
                // Only machine A sold P1 yesterday.
                ->where('vendCount', 1)
                ->where('monthGraphData.2026.8.amount', 60)
                ->where('activeMachineGraphData.2026.8.count', 1)
                ->where('monthsByModel.Office.8.amount', 60)
                ->where('monthsByModel.Office.8.vend_count', 1)
                ->has('performerGraphData.data', 1)
                ->where('performerGraphData.data.0.amount', 60)
                ->has('productGraphData', 1)
                ->where('productGraphData.0.product.id', $this->p1->id)
                // P1 alone + P1's basket line: 2 units, $4.
                ->where('productGraphData.0.count', 2)
                ->where('productGraphData.0.amount', 4)
                // No site-earnings split per product.
                ->where('earningsGraphData', []);
            // Today counts P1's lines only, not the basket's $5 header.
            $this->assertEquals(4, $this->today($page)['amount']);
        });
    }

    public function test_category_and_group_filters_narrow_the_same_way(): void
    {
        $this->page(['product_category_id' => [302]])->assertInertia(fn (AssertableInertia $page) => $page
            ->where('vendCount', 2)
            ->where('monthGraphData.2026.8.amount', 90)
            ->where('activeMachineGraphData.2026.8.count', 2));

        // Group 55 holds both products: per-product figures for everything.
        $this->page(['product_category_group_id' => [55]])->assertInertia(fn (AssertableInertia $page) => $page
            ->where('productGrain', true)
            ->where('monthGraphData.2026.8.amount', 150));
    }

    public function test_the_all_sentinel_is_no_product_filter(): void
    {
        $this->page(['product_id' => ['all']])->assertInertia(fn (AssertableInertia $page) => $page
            ->where('productGrain', false)
            ->where('vendCount', 2)
            ->where('monthGraphData.2026.8.amount', 150));
    }

    public function test_switching_products_never_serves_the_previous_products_figures(): void
    {
        // Same viewer, same other filters, back to back: the 5-minute chart
        // cache used to ignore the product filter, and the controller instance
        // is reused by the router, so neither may carry the first request over.
        $this->page(['product_id' => [$this->p1->id]])->assertInertia(fn (AssertableInertia $page) => $page
            ->where('activeMachineGraphData.2026.8.count', 1)
            ->where('monthsByModel.Office.8.amount', 60));

        $this->page(['product_id' => [$this->p2->id]])->assertInertia(fn (AssertableInertia $page) => $page
            ->where('vendCount', 2)
            ->where('monthGraphData.2026.8.amount', 90)
            ->where('activeMachineGraphData.2026.8.count', 2)
            ->where('monthsByModel.Office.8.amount', 90));

        $this->page()->assertInertia(fn (AssertableInertia $page) => $page
            ->where('productGrain', false)
            ->where('monthGraphData.2026.8.amount', 150));
    }

    public function test_has_product_filter_ignores_empty_values(): void
    {
        $request = fn (array $query) => new \Illuminate\Http\Request($query);

        $this->assertFalse(VendProductRecord::hasProductFilter($request([])));
        $this->assertFalse(VendProductRecord::hasProductFilter($request(['product_id' => [], 'product_name' => ''])));
        $this->assertFalse(VendProductRecord::hasProductFilter($request(['product_category_id' => ['all']])));
        $this->assertTrue(VendProductRecord::hasProductFilter($request(['product_id' => [5]])));
        $this->assertTrue(VendProductRecord::hasProductFilter($request(['product_name' => 'cone'])));
    }
}
