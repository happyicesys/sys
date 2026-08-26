<?php

namespace Tests\Feature;

use App\Jobs\SyncOpsJobTransactionCMS;
use App\Models\Customer;
use App\Models\Operator;
use App\Models\OpsJob;
use App\Models\OpsJobItem;
use App\Models\OpsJobItemChannel;
use App\Models\Product;
use App\Models\User;
use App\Models\Vend;
use App\Models\VendChannel;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OpsJobMeltedStockActionTest extends TestCase
{
    use RefreshDatabase;

    private function makeJobItem(array $itemAttrs = [], array $channelAttrs = []): array
    {
        $operator = Operator::create(['code' => 'OP1', 'name' => 'Test Operator']);
        \Spatie\Permission\Models\Role::create(['name' => 'operator', 'guard_name' => 'web']);

        $user = User::factory()->create([
            'operator_id' => $operator->id,
            'username' => 'testuser',
        ]);
        $user->assignRole('operator');

        $customer = Customer::create([
            'name' => 'Test Site',
            'operator_id' => $operator->id,
            'person_id' => 777,
        ]);

        $opsJob = OpsJob::create([
            'code' => 'OJ-001',
            'date' => Carbon::today(),
            'operator_id' => $operator->id,
            'created_by' => $user->id,
            'delivered_by' => $user->id,
        ]);

        $product = Product::create(['code' => 'U-15', 'name' => 'Vanilla Ice Cream']);
        $vend = Vend::create(['code' => 'V001', 'operator_id' => $operator->id]);
        $vendChannel = VendChannel::create([
            'vend_id' => $vend->id,
            'product_id' => $product->id,
            'amount' => 200,
            'code' => 'A1',
            'capacity' => 10,
            'qty' => 8,
            'is_active' => 1,
        ]);

        $item = OpsJobItem::create(array_merge([
            'ops_job_id' => $opsJob->id,
            'vend_id' => $vend->id,
            'customer_id' => $customer->id,
            'status' => OpsJob::STATUS_PENDING,
        ], $itemAttrs));

        $channel = OpsJobItemChannel::create(array_merge([
            'ops_job_item_id' => $item->id,
            'vend_channel_id' => $vendChannel->id,
            'vend_channel_code' => 'A1',
        ], $channelAttrs));

        return [$user, $item, $channel];
    }

    public function test_melted_stock_action_auto_picks_with_zeroed_pick_qty()
    {
        [$user, $item, $channel] = $this->makeJobItem();

        $response = $this->actingAs($user)->post(
            '/ops-jobs/items/'.$item->id.'/update/stock-action',
            ['stock_action_type' => 'melted_stock']
        );

        $response->assertRedirect();

        $item->refresh();
        $channel->refresh();
        $this->assertSame('melted_stock', $item->stock_action_type);
        $this->assertEquals(OpsJob::STATUS_PICKED, $item->status);
        $this->assertEquals(0, $channel->picked_qty);
        $this->assertEquals(0, $channel->saved_picked_qty);
    }

    public function test_melted_stock_action_can_be_undone_back_to_pending()
    {
        [$user, $item] = $this->makeJobItem([
            'status' => OpsJob::STATUS_PICKED,
            'stock_action_type' => 'melted_stock',
        ]);

        $response = $this->actingAs($user)->post('/ops-jobs/items/'.$item->id.'/undo-stock-action');

        $response->assertRedirect();
        $item->refresh();
        $this->assertNull($item->stock_action_type);
        $this->assertEquals(OpsJob::STATUS_PENDING, $item->status);
    }

    public function test_cms_sync_sends_melted_qty_positive_and_flagged_as_discard()
    {
        config(['app.cms_url' => 'https://cms.test']);
        Http::fake(['cms.test/*' => Http::response([])]);

        [$user, $item] = $this->makeJobItem(
            [
                'status' => OpsJob::STATUS_DELIVERED,
                'stock_action_type' => 'melted_stock',
            ],
            // Machine-side stock-in: 8 melted units removed from the machine.
            ['actual_qty' => -8]
        );

        (new SyncOpsJobTransactionCMS($item, [
            'date' => Carbon::today()->format('Y-m-d'),
            'driver' => $user->username,
            'created_by' => $user->username,
            'status' => 'Delivered',
            'customers' => [],
        ], $user->id))->handle();

        Http::assertSent(function ($request) {
            $customer = $request['customers'][777] ?? null;
            if (! $customer || empty($customer['is_discard'])) {
                return false;
            }
            $line = collect($customer['channels'])->first();

            // Thrown-away goods reach CMS as a POSITIVE discard record,
            // never as the negative "give back to coldroom" of return_stock.
            return $line['needed'] === 8 && $line['amount'] === 8 * 200;
        });
    }

    public function test_cms_sync_keeps_return_stock_qty_negative_and_unflagged()
    {
        config(['app.cms_url' => 'https://cms.test']);
        Http::fake(['cms.test/*' => Http::response([])]);

        [$user, $item] = $this->makeJobItem(
            [
                'status' => OpsJob::STATUS_DELIVERED,
                'stock_action_type' => 'return_stock',
            ],
            ['actual_qty' => -8]
        );

        (new SyncOpsJobTransactionCMS($item, [
            'date' => Carbon::today()->format('Y-m-d'),
            'driver' => $user->username,
            'created_by' => $user->username,
            'status' => 'Delivered',
            'customers' => [],
        ], $user->id))->handle();

        Http::assertSent(function ($request) {
            $customer = $request['customers'][777] ?? null;
            if (! $customer || ! empty($customer['is_discard'])) {
                return false;
            }
            $line = collect($customer['channels'])->first();

            return $line['needed'] === -8;
        });
    }
}
