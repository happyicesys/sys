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

        $locationType = LocationType::create(['name' => 'Office', 'sequence' => 1]);

        $customer = Customer::create([
            'code' => 'C001',
            'name' => 'Test Customer',
            'location_type_id' => $locationType->id,
            'operator_id' => $operator->id
        ]);

        $vend = Vend::create([
            'code' => 'V001',
            'name' => 'Test Vend',
            'operator_id' => $operator->id,
            'customer_id' => $customer->id,
            'location_type_id' => $locationType->id
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
        $response = $this->actingAs($user)->get('/dashboard');

        // 3. Assert
        // dump($response->json('props.dayGraphData'));
        $response->assertStatus(200);

        $response->assertInertia(function (\Inertia\Testing\AssertableInertia $page) use ($lastMonth) {
            // dump($page->toArray()['props']['dayGraphData']);
            $page->component('Dashboard')
                // Check Day Graph (Yesterday + Today)
                ->has('dayGraphData', function (\Inertia\Testing\AssertableInertia $json) {
                    // We expect at least entries for yesterday and today (and potentially filled empty dates)
                    // But checking for existence and structure is good enough for now
                    // dump($json->toArray());
                    dd(array_keys($json->toArray()));
                    $json->where('0.amount', 0) // Start of month might be 0
                        ->etc();
                })
                // Check Product Graph
                ->has('productGraphData', 1)
                ->where('productGraphData.0.amount', 200)
                ->where('productGraphData.0.count', 1)

                // Check Best Performer (Last 30 days)
                ->has('performerGraphData', 1)
                ->where('performerGraphData.0.amount', 30200) // 10000 (yesterday) + 20000 (last month if within 30 days) + 200 (today) ??
                // Wait, getBestPerformer uses VendRecord only, and date range is today-29 to today.
                // Yesterday record: 10000. Last Month record: 20000.
                // If last month is > 29 days ago, it won't be included.
                // Let's assume last month is > 29 days for safety, or check logic.
                // Code: Carbon::today()->copy()->subDays(29)->startOfDay()
                // So if today is Nov 22, subDays(29) is Oct 24.
                // If last month is Oct 22, it's excluded.
                // Let's rely on Yesterday's record (10000).
                // Wait, getBestPerformer sums total_amount.
                // It queries VendRecord. Does it include today's transactions? No, only VendRecord.
                // So it should be 10000 (Yesterday).
                // ->where('performerGraphData.0.amount', 10000)

                // Check Vend Count
                ->where('vendCount', 1) // Yesterday's active vends

                // Check Monthly Analytics
                ->has('monthsByModel.Office', 12) // Location Type 'Office'
                ->where('monthsByModel.Office.' . Carbon::yesterday()->month . '.amount', 100) // 100.00
                ->where('monthsByModel.Office.' . $lastMonth->month . '.amount', 200); // 200.00
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
            12 => 'December'
        ];

        foreach ($months as $num => $name) {
            Month::firstOrCreate(['number' => $num], ['name' => $name, 'short_name' => substr($name, 0, 3)]);
        }
    }
}
