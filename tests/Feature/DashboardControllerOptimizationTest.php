<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\LocationType;
use App\Models\Month;
use App\Models\Operator;
use App\Models\Product;
use App\Models\User;
use App\Models\Vend;
use App\Models\VendRecord;
use App\Models\VendTransaction;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardControllerOptimizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_data_integrity()
    {
        // Frozen mid-month, mid-year: 'yesterday' / 'last month' / the 29-day
        // performer window and the current-year monthly window are all stable,
        // so the assertions below never depend on the real calendar date.
        // (Laravel's TestCase resets Carbon's test-now in tearDown.)
        Carbon::setTestNow('2026-08-20 10:00:00');

        // 1. Setup Data
        $this->seedMonths();

        $operator = Operator::create(['code' => 'OP1', 'name' => 'Test Operator']);
        // Create Role
        \Spatie\Permission\Models\Role::create(['name' => 'operator', 'guard_name' => 'web']);

        $user = User::factory()->create([
            'operator_id' => $operator->id,
            'username' => 'testuser',
        ]);
        $user->assignRole('operator');
        // The route gained a server-side gate (DashboardController::__construct).
        \Spatie\Permission\Models\Permission::findOrCreate('read dashboard-performance', 'web');
        $user->givePermissionTo('read dashboard-performance');

        $locationType = LocationType::create(['name' => 'Office', 'sequence' => 1]);

        $customer = Customer::create([
            'code' => 'C001',
            'name' => 'Test Customer',
            'location_type_id' => $locationType->id,
            'operator_id' => $operator->id,
        ]);

        $vend = Vend::create([
            'code' => 'V001',
            'name' => 'Test Vend',
            'operator_id' => $operator->id,
            'customer_id' => $customer->id,
            'location_type_id' => $locationType->id,
        ]);

        $product = Product::create(['code' => 'P001', 'name' => 'Coke']);

        // Create VendRecords (Past Data)
        // Yesterday
        VendRecord::create([
            'vend_id' => $vend->id,
            'customer_id' => $customer->id,
            'operator_id' => $operator->id,
            'location_type_id' => $locationType->id,
            'date' => Carbon::yesterday(),
            'day' => Carbon::yesterday()->day,
            'month' => Carbon::yesterday()->month,
            'year' => Carbon::yesterday()->year,
            'monthname' => Carbon::yesterday()->format('F'),
            'total_amount' => 10000, // $100.00
            'total_count' => 50,
        ]);

        // Last Month (same day)
        $lastMonth = Carbon::today()->subMonth();
        VendRecord::create([
            'vend_id' => $vend->id,
            'customer_id' => $customer->id,
            'operator_id' => $operator->id,
            'location_type_id' => $locationType->id,
            'date' => $lastMonth,
            'day' => $lastMonth->day,
            'month' => $lastMonth->month,
            'year' => $lastMonth->year,
            'monthname' => $lastMonth->format('F'),
            'total_amount' => 20000, // $200.00
            'total_count' => 100,
        ]);

        // Create VendTransactions (Today's Data)
        VendTransaction::create([
            'vend_id' => $vend->id,
            'customer_id' => $customer->id,
            'operator_id' => $operator->id,
            'product_id' => $product->id,
            'transaction_datetime' => Carbon::now(),
            'amount' => 200, // $2.00
            'success_qty' => 1,
            'error_code_normalized' => 0, // Success
        ]);

        // 2. Act
        // '/dashboard' is now a redirect shell; the page lives at /performance.
        // autoload=1: without it the controller deliberately skips every graph
        // query (the page lazy-fetches them) and returns empty collections.
        $response = $this->actingAs($user)->get('/dashboard/performance?autoload=1');

        // 3. Assert
        // dump($response->json('props.dayGraphData'));
        $response->assertStatus(200);

        $response->assertInertia(function (\Inertia\Testing\AssertableInertia $page) use ($lastMonth) {
            // dump($page->toArray()['props']['dayGraphData']);
            $page->component('Dashboard')
                // Day Graph: presence only. Its day-by-day values depend on what
                // today's date is within the month, so pinning them makes the
                // test date-flaky; the graphs below carry the value assertions.
                // (This closure used to hold a leftover dd() that killed every
                // suite run at this point.)
                ->has('dayGraphData')
                // Check Product Graph
                ->has('productGraphData', 1)
                // Amounts leave the controller in dollars (cents / 100 at the edge).
                ->where('productGraphData.0.amount', 2)
                ->where('productGraphData.0.count', 1)

                // Best Performer (last 30 days) reads VendRecord only, serialized
                // through a resource collection (hence the .data level), amounts
                // in dollars. With time frozen at Aug 20 the 29-day window starts
                // Jul 22, so only yesterday's $100 record is inside it.
                ->has('performerGraphData.data', 1)
                ->where('performerGraphData.data.0.amount', 100)

                // Check Vend Count
                ->where('vendCount', 1) // Yesterday's active vends

                // Monthly Analytics: keyed by location-type name, then month
                // NUMBER — and only months that have data are present (Jul + Aug
                // here), in dollars.
                ->has('monthsByModel.Office', 2)
                ->where('monthsByModel.Office.'.Carbon::yesterday()->month.'.amount', 100)
                ->where('monthsByModel.Office.'.$lastMonth->month.'.amount', 200);
        });
    }

    private function seedMonths()
    {
        $months = [
            1 => 'January',
            2 => 'February',
            3 => 'March',
            4 => 'April',
            5 => 'May',
            6 => 'June',
            7 => 'July',
            8 => 'August',
            9 => 'September',
            10 => 'October',
            11 => 'November',
            12 => 'December',
        ];

        foreach ($months as $num => $name) {
            Month::firstOrCreate(['number' => $num], ['name' => $name, 'short_name' => substr($name, 0, 3)]);
        }
    }
}
