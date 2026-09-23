<?php

namespace Tests\Feature;

use App\Console\Commands\RepairOpsJobItemVmcQty;
use App\Jobs\Vend\SaveVendChannelsJson;
use App\Jobs\Vend\SyncVendChannelErrorLog;
use App\Jobs\Vend\SyncVendChannels;
use App\Models\Customer;
use App\Models\OpsJob;
use App\Models\OpsJobItem;
use App\Models\OpsJobItemChannel;
use App\Models\Product;
use App\Models\SellingPrice;
use App\Models\Vend;
use App\Models\VendChannelRecord;
use App\Services\DeliveryProductMappingService;
use App\Services\ProductMappingService;
use App\Support\OpsJobFrameQty;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * "After Refill" on an ops-job item: the A frame's qty landing on
 * ops_job_item_channels.vmc_after_qty.
 *
 * Regression origin (prod, 2026-09-23): a vending board's B/A frame carries a
 * product_id, but it is the VMC's own slot index (1, 2, 21 …), never a
 * products.id. SyncVendChannels::frameEntryMatchesOpsRow keyed on "the entry
 * has a product_id" and so compared 1 against 506 — no match, After Refill blank
 * for every item whose A frame arrived after the driver pressed Complete (the
 * completion path in OpsJobController matches on channel_code and fills both
 * columns for drivers who wait, which is why only one driver's items were hit).
 */
class OpsJobVmcRefillQtyTest extends TestCase
{
    use RefreshDatabase;

    /** What a vending board puts in a frame entry's product_id: its own slot index. */
    private const FRAME_SLOT_INDEX = 1;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake([SyncVendChannelErrorLog::class, SaveVendChannelsJson::class]);
    }

    /** A vending machine with one ops-job item already linked to a B-only record. */
    private function scene(): array
    {
        // Filler, so the SKU's products.id cannot coincide with the slot index
        // the frame carries — that coincidence is what the bug hid behind.
        foreach (['F-1', 'F-2', 'F-3'] as $code) {
            Product::create(['code' => $code, 'name' => $code, 'is_active' => true]);
        }
        $product = Product::create(['code' => 'U-70', 'name' => "Wall's Cup Chocolate", 'is_active' => true]);
        $this->assertNotSame(self::FRAME_SLOT_INDEX, (int) $product->id);
        SellingPrice::create(['product_id' => $product->id, 'type' => SellingPrice::TYPE_2, 'amount' => 1.60]);
        $customer = Customer::create([
            'name' => 'Site', 'code' => 'VS1', 'operator_id' => 1,
            'status_id' => Customer::STATUS_ACTIVE, 'selling_price_type' => SellingPrice::TYPE_2,
        ]);
        $vend = Vend::create([
            'code' => 9601, 'is_active' => 1, 'operator_id' => 1,
            'vend_model_id' => 1, 'customer_id' => $customer->id,
        ]);

        $job = OpsJob::create([
            'code' => 900300, 'date' => now()->toDateString(), 'status' => 1,
            'delivered_by' => 1, 'operator_id' => 1,
        ]);
        $item = OpsJobItem::create([
            'ops_job_id' => $job->id, 'vend_id' => $vend->id, 'customer_id' => $customer->id,
            'status' => OpsJob::STATUS_DELIVERED, 'completed_at' => now(),
        ]);
        $channel = OpsJobItemChannel::create([
            'ops_job_id' => $job->id, 'ops_job_item_id' => $item->id,
            'vend_channel_code' => 11, 'vend_code' => $vend->code,
            'product_id' => $product->id, 'qty' => 2, 'capacity' => 10, 'picked_qty' => 8,
        ]);

        // The driver pressed Complete while only the B frame had landed, so the
        // completion path filled before-qty and left after-qty for the A frame.
        $record = VendChannelRecord::create([
            'vend_id' => $vend->id, 'customer_id' => $customer->id, 'operator_id' => 1,
            'before_data_json' => ['label' => 'B', 'channels' => [$this->frameEntry(2)]],
            'before_data_created_at' => now()->subMinute(),
            'before_label' => 'B',
        ]);
        $item->update(['vend_channel_record_id' => $record->id]);
        $channel->update(['vmc_before_qty' => 2]);

        return [$vend, $item, $channel, $record];
    }

    /** One entry of a vending board's frame: product_id is the VMC's slot index. */
    private function frameEntry(int $qty): array
    {
        return [
            'channel_code' => 11, 'qty' => $qty, 'capacity' => 10,
            'amount' => 160, 'amount2' => 160, 'lock_qty' => 0,
            'error_code' => 0, 'product_id' => self::FRAME_SLOT_INDEX, 'discount_group' => 0,
        ];
    }

    private function sendFrame(Vend $vend, array $input): void
    {
        (new SyncVendChannels($input, $vend))->handle(
            app(DeliveryProductMappingService::class),
            app(ProductMappingService::class),
        );
    }

    public function test_an_a_frame_arriving_after_completion_fills_after_refill_on_a_vending_machine(): void
    {
        [$vend, , $channel] = $this->scene();

        $this->sendFrame($vend, ['label' => 'A', 'channels' => [$this->frameEntry(10)]]);

        $this->assertSame(10, (int) $channel->fresh()->vmc_after_qty, "the board's product_id is a slot index, not a products.id — match on the slot code");
    }

    public function test_the_repair_command_refills_after_qty_from_a_stored_frame(): void
    {
        [, $item, $channel, $record] = $this->scene();

        // What the broken build left behind: the A frame is on the record, the
        // ops row never got its qty.
        $record->update([
            'after_data_json' => ['label' => 'A', 'channels' => [$this->frameEntry(10)]],
            'after_data_created_at' => now(),
            'after_label' => 'A',
        ]);

        $this->artisan(RepairOpsJobItemVmcQty::class, ['--item' => $item->id])->assertSuccessful();
        $this->assertNull($channel->fresh()->vmc_after_qty, 'dry run writes nothing');

        $this->artisan(RepairOpsJobItemVmcQty::class, ['--item' => $item->id, '--apply' => true])->assertSuccessful();
        $this->assertSame(10, (int) $channel->fresh()->vmc_after_qty);
        $this->assertSame(2, (int) $channel->fresh()->vmc_before_qty, 'an already-filled column is left alone');
    }

    // ── the rule is shared, so the two paths cannot disagree ───────────────

    public function test_a_sku_stocked_machine_matches_by_product_even_when_the_position_moved(): void
    {
        // A freezer's code is a position LABEL: the mapping moved these two SKUs
        // onto each other's baskets. Keying on the code would hand each row the
        // other SKU's qty — the completion path's old rule.
        $cornetto = Product::create(['code' => 'F-A', 'name' => 'Cornetto', 'is_active' => true]);
        $magnum = Product::create(['code' => 'F-B', 'name' => 'Magnum', 'is_active' => true]);
        $customer = Customer::create([
            'name' => 'Freezer Site', 'code' => 'FS9', 'operator_id' => 1,
            'status_id' => Customer::STATUS_ACTIVE, 'selling_price_type' => SellingPrice::TYPE_2,
        ]);
        $vend = Vend::create([
            'code' => 50901, 'machine_type' => Vend::MACHINE_TYPE_SMART_FREEZER, 'is_active' => 1,
            'operator_id' => 1, 'vend_model_id' => 1, 'customer_id' => $customer->id,
            'is_using_server_price' => true,
        ]);
        $job = OpsJob::create([
            'code' => 900301, 'date' => now()->toDateString(), 'status' => 1,
            'delivered_by' => 1, 'operator_id' => 1,
        ]);
        $item = OpsJobItem::create([
            'ops_job_id' => $job->id, 'vend_id' => $vend->id, 'customer_id' => $customer->id,
            'status' => OpsJob::STATUS_DELIVERED, 'completed_at' => now(),
        ]);
        $rowA = OpsJobItemChannel::create([
            'ops_job_id' => $job->id, 'ops_job_item_id' => $item->id, 'vend_channel_code' => 11,
            'vend_code' => $vend->code, 'product_id' => $cornetto->id, 'qty' => 0, 'capacity' => 24, 'picked_qty' => 0,
        ]);
        $rowB = OpsJobItemChannel::create([
            'ops_job_id' => $job->id, 'ops_job_item_id' => $item->id, 'vend_channel_code' => 21,
            'vend_code' => $vend->code, 'product_id' => $magnum->id, 'qty' => 0, 'capacity' => 12, 'picked_qty' => 0,
        ]);

        // The frame reports each SKU at the OTHER row's code.
        $frame = ['label' => 'A', 'channels' => [
            ['channel_code' => 21, 'product_id' => $cornetto->id, 'qty' => 24, 'capacity' => 24],
            ['channel_code' => 11, 'product_id' => $magnum->id, 'qty' => 12, 'capacity' => 12],
        ]];

        $this->assertSame(2, OpsJobFrameQty::apply($item->fresh()->load('opsJobItemChannels'), $frame, 'vmc_after_qty'));
        $this->assertSame(24, (int) $rowA->fresh()->vmc_after_qty, 'Cornetto keeps its own qty, wherever the label put it');
        $this->assertSame(12, (int) $rowB->fresh()->vmc_after_qty);
    }

    public function test_the_completion_path_and_the_a_frame_path_fill_the_same_values(): void
    {
        // Same machine, same frames, both routes — the columns must agree.
        [$vendA, $itemA, $channelA, $recordA] = $this->scene();
        $after = ['label' => 'A', 'channels' => [$this->frameEntry(10)]];

        // Route 1: the A frame arrives after completion (SyncVendChannels).
        $this->sendFrame($vendA, $after);

        // Route 2: the frame was already on the record at completion time
        // (OpsJobController's block, which calls the same helper).
        [, $itemB, $channelB, $recordB] = $this->scene2();
        $recordB->update(['after_data_json' => $after, 'after_data_created_at' => now(), 'after_label' => 'A']);
        OpsJobFrameQty::apply($itemB->fresh()->load('opsJobItemChannels'), $recordB->after_data_json, 'vmc_after_qty');

        $this->assertSame(
            (int) $channelA->fresh()->vmc_after_qty,
            (int) $channelB->fresh()->vmc_after_qty,
            'both paths resolve the same frame entry'
        );
        $this->assertSame(10, (int) $channelA->fresh()->vmc_after_qty);
    }

    /** A second, independent copy of scene() (different vend/site codes). */
    private function scene2(): array
    {
        $product = Product::where('code', 'U-70')->firstOrFail();
        $customer = Customer::create([
            'name' => 'Site 2', 'code' => 'VS2', 'operator_id' => 1,
            'status_id' => Customer::STATUS_ACTIVE, 'selling_price_type' => SellingPrice::TYPE_2,
        ]);
        $vend = Vend::create([
            'code' => 9602, 'is_active' => 1, 'operator_id' => 1,
            'vend_model_id' => 1, 'customer_id' => $customer->id,
        ]);
        $job = OpsJob::create([
            'code' => 900302, 'date' => now()->toDateString(), 'status' => 1,
            'delivered_by' => 1, 'operator_id' => 1,
        ]);
        $item = OpsJobItem::create([
            'ops_job_id' => $job->id, 'vend_id' => $vend->id, 'customer_id' => $customer->id,
            'status' => OpsJob::STATUS_DELIVERED, 'completed_at' => now(),
        ]);
        $channel = OpsJobItemChannel::create([
            'ops_job_id' => $job->id, 'ops_job_item_id' => $item->id,
            'vend_channel_code' => 11, 'vend_code' => $vend->code,
            'product_id' => $product->id, 'qty' => 2, 'capacity' => 10, 'picked_qty' => 8,
        ]);
        $record = VendChannelRecord::create([
            'vend_id' => $vend->id, 'customer_id' => $customer->id, 'operator_id' => 1,
            'before_data_json' => ['label' => 'B', 'channels' => [$this->frameEntry(2)]],
            'before_data_created_at' => now()->subMinute(),
            'before_label' => 'B',
        ]);
        $item->update(['vend_channel_record_id' => $record->id]);
        $channel->update(['vmc_before_qty' => 2]);

        return [$vend, $item, $channel, $record];
    }
}
