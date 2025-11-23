<?php

namespace Tests\Feature;

use App\Models\OpsJob;
use App\Models\OpsJobItem;
use App\Models\OpsJobItemChannel;
use App\Models\User;
use App\Models\Operator;
use App\Models\Vend;
use App\Models\VendChannel;
use App\Models\Product;
use App\Models\UnitCost;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OpsJobControllerOptimizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_ops_jobs_calculations()
    {
        // 1. Setup Data
        $operator = Operator::create(['code' => 'OP1', 'name' => 'Test Operator']);

        // Create Role
        \Spatie\Permission\Models\Role::create(['name' => 'operator', 'guard_name' => 'web']);

        $user = User::factory()->create([
            'operator_id' => $operator->id,
            'username' => 'testuser',
        ]);
        $user->assignRole('operator');

        $opsJob = OpsJob::create([
            'code' => 'OJ-001',
            'date' => Carbon::today(),
            'operator_id' => $operator->id,
            'created_by' => $user->id,
            'delivered_by' => $user->id,
        ]);

        // Create Product and Unit Cost
        $product = Product::create(['code' => 'P001', 'name' => 'Coke']);
        UnitCost::create([
            'product_id' => $product->id,
            'cost' => 100, // $1.00
            'date_from' => Carbon::today()->subMonth(),
            'is_current' => true,
        ]);

        // Create Vend and Channel
        $vend = Vend::create(['code' => 'V001', 'operator_id' => $operator->id]);
        $vendChannel = VendChannel::create([
            'vend_id' => $vend->id,
            'product_id' => $product->id,
            'amount' => 200, // $2.00 price
            'code' => 'A1',
        ]);

        // Create OpsJobItems with different statuses
        // Item 1: Delivered (Stock In)
        $item1 = OpsJobItem::create([
            'ops_job_id' => $opsJob->id,
            'vend_id' => $vend->id,
            'status' => OpsJob::STATUS_DELIVERED,
            'cash_amount' => 50, // $50.00 (Mutator converts to 5000 cents)
            'acc_total_amount' => 10000,
        ]);
        OpsJobItemChannel::create([
            'ops_job_item_id' => $item1->id,
            'vend_channel_id' => $vendChannel->id,
            'picked_qty' => 10,
            'actual_qty' => 8, // Stock In Count
        ]);

        // Item 2: Picked only
        $item2 = OpsJobItem::create([
            'ops_job_id' => $opsJob->id,
            'vend_id' => $vend->id,
            'status' => OpsJob::STATUS_PICKED,
        ]);
        OpsJobItemChannel::create([
            'ops_job_item_id' => $item2->id,
            'vend_channel_id' => $vendChannel->id,
            'picked_qty' => 5,
            'actual_qty' => 0,
        ]);

        // Item 3: Pending
        $item3 = OpsJobItem::create([
            'ops_job_id' => $opsJob->id,
            'vend_id' => $vend->id,
            'status' => OpsJob::STATUS_PENDING,
        ]);

        // 2. Act
        $response = $this->actingAs($user)->get('/ops-jobs?date_from=' . Carbon::today()->subDay()->toDateString());

        // 3. Assert
        $response->assertStatus(200);

        $response->assertInertia(function (\Inertia\Testing\AssertableInertia $page) use ($opsJob) {
            $page->component('OpsJob/Index')
                ->has('opsJobs.data', 1)
                ->has('opsJobs.data.0', function (\Inertia\Testing\AssertableInertia $json) use ($opsJob) {
                    $json->where('id', $opsJob->id)
                        ->where('ops_job_items_count', 3)
                        ->where('ops_job_items_delivered_count', 1)
                        ->where('picked_count', 15)
                        ->where('picked_amount', 30)
                        ->where('stock_in_count', 8)
                        ->where('stock_in_amount', 16)
                        ->where('total_cash_amount', 50)
                        ->etc();
                });
        });
    }
}
