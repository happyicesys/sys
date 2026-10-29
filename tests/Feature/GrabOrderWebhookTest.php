<?php

namespace Tests\Feature;

use App\Jobs\DispenseDeliveryPlatformOrder;
use App\Jobs\ProcessDeliveryPlatformOrder;
use App\Models\DeliveryPlatformOrder;
use App\Models\DeliveryProductMappingVendChannel;
use App\Services\DeliveryPlatformService;
use App\Services\DeliveryProductMappingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\Support\Grab\BuildsGrabStore;
use Tests\TestCase;

/**
 * The Grab order webhook end to end, driven by a real prod GrabMart submit
 * (tests/Support/Grab/fixtures, customer details removed). GRAB_REVAMP_PLAN_2026-10-29.md
 * A1 (a repeated submit got a 500 forever), A2 (Grab calls inside the webhook),
 * A4 (store moved to another machine), A5 (held stock).
 */
class GrabOrderWebhookTest extends TestCase
{
    use BuildsGrabStore;
    use RefreshDatabase;

    private const MERCHANT = '4-C6VGNELVVPWKAX';

    protected function setUp(): void
    {
        parent::setUp();

        config(['grab.webhook_auth' => 'off']);
        $this->buildGrabOperator();
        $this->grabItem('U-49', 11, 280);
        $this->grabItem('U-26', 12, 500);
    }

    private function itemIds(): array
    {
        return [$this->grabItems['U-49']->id, $this->grabItems['U-26']->id];
    }

    private function submit(array $payload)
    {
        return $this->postJson('/api/delivery/grab/order/create', $payload);
    }

    public function test_an_order_is_stored_with_its_channels_and_marked_ready_from_the_queue(): void
    {
        Queue::fake();
        $this->bindGrabStore(4416, self::MERCHANT, [11 => ['qty' => 5, 'capacity' => 10], 12 => ['qty' => 3, 'capacity' => 10]]);

        $this->submit($this->grabOrderPayload(self::MERCHANT, 4416, $this->itemIds()))->assertOk();

        $order = DeliveryPlatformOrder::firstOrFail();
        $this->assertSame('GM-604', $order->short_order_id);
        $this->assertSame(1060, (int) $order->getRawOriginal('subtotal_amount'));
        $this->assertSame([11 => 2, 12 => 1], $order->orderItemVendChannels()->pluck('qty', 'vend_channel_code')->mapWithKeys(fn ($q, $c) => [(int) $c => $q])->all());

        // No Grab call inside the webhook: the follow-up is queued.
        Queue::assertPushed(ProcessDeliveryPlatformOrder::class, 1);
    }

    public function test_the_queued_follow_up_marks_the_order_ready_on_grab(): void
    {
        Http::fake(['*/partner/v1/orders/mark' => Http::response(null, 204)]);
        $this->bindGrabStore(4416, self::MERCHANT, [11 => ['qty' => 5, 'capacity' => 10], 12 => ['qty' => 3, 'capacity' => 10]]);

        $this->submit($this->grabOrderPayload(self::MERCHANT, 4416, $this->itemIds()))->assertOk();

        Http::assertSent(fn ($request) => str_ends_with($request->url(), '/partner/v1/orders/mark')
            && $request['orderID'] === '001569933881-TEST' && $request['markStatus'] === 1);
        $this->assertSame(DeliveryPlatformOrder::STATUS_ACCEPTED, DeliveryPlatformOrder::firstOrFail()->status);
    }

    public function test_a_repeated_submit_returns_ok_and_stores_nothing_twice(): void
    {
        Queue::fake();
        $this->bindGrabStore(4416, self::MERCHANT, [11 => ['qty' => 5, 'capacity' => 10], 12 => ['qty' => 3, 'capacity' => 10]]);
        $payload = $this->grabOrderPayload(self::MERCHANT, 4416, $this->itemIds());

        $this->submit($payload)->assertOk();
        $this->submit($payload)->assertOk(); // was: 500 "Replicated Order ID on Creation"

        $this->assertSame(1, DeliveryPlatformOrder::count());
        $this->assertSame(2, DeliveryPlatformOrder::firstOrFail()->orderItemVendChannels()->count());
        Queue::assertPushed(ProcessDeliveryPlatformOrder::class, 1);
    }

    public function test_a_grab_edit_of_a_stored_order_is_kept_for_a_person(): void
    {
        Queue::fake();
        $this->bindGrabStore(4416, self::MERCHANT, [11 => ['qty' => 5, 'capacity' => 10], 12 => ['qty' => 3, 'capacity' => 10]]);
        $payload = $this->grabOrderPayload(self::MERCHANT, 4416, $this->itemIds());
        $this->submit($payload)->assertOk();

        $edit = $payload;
        $edit['featureFlags']['isMexEditOrder'] = true;
        $edit['items'] = [$payload['items'][0]];
        $this->submit($edit)->assertOk();

        $order = DeliveryPlatformOrder::firstOrFail();
        $this->assertTrue((bool) $order->is_edited);
        $this->assertSame('grab_edit', $order->request_history_json[0]['type']);
        $this->assertSame(2, $order->orderItemVendChannels()->count(), 'the allocation is left for a person');
    }

    public function test_a_store_moved_to_another_machine_is_found_by_its_merchant(): void
    {
        // 4-C7CCRTEJJYUJR6: Grab still sends the machine it was activated on (2114).
        Queue::fake();
        $this->bindGrabStore(2401, self::MERCHANT, [11 => ['qty' => 5, 'capacity' => 10], 12 => ['qty' => 3, 'capacity' => 10]]);

        $this->submit($this->grabOrderPayload(self::MERCHANT, 2114, $this->itemIds()))->assertOk();

        $this->assertSame(2401, (int) DeliveryPlatformOrder::firstOrFail()->deliveryProductMappingVend->vend_code);
    }

    public function test_a_paused_store_refuses_with_a_4xx_json(): void
    {
        $this->bindGrabStore(4754, self::MERCHANT, [11 => ['qty' => 5, 'capacity' => 10]], isActive: false);

        $this->submit($this->grabOrderPayload(self::MERCHANT, 4754, $this->itemIds()))
            ->assertStatus(404)
            ->assertJson(['error_message' => 'Vending Machine is paused for this Platform Ref ID.']);

        $this->assertSame(0, DeliveryPlatformOrder::count());
    }

    public function test_an_order_for_items_the_store_does_not_list_is_a_4xx_and_rolls_back(): void
    {
        $this->bindGrabStore(4416, self::MERCHANT, [11 => ['qty' => 5, 'capacity' => 10], 12 => ['qty' => 3, 'capacity' => 10]]);

        $this->submit($this->grabOrderPayload(self::MERCHANT, 4416, [999999]))->assertStatus(422);

        $this->assertSame(0, DeliveryPlatformOrder::count());
    }

    public function test_an_open_order_holds_its_units_until_dispensed_or_cancelled(): void
    {
        Queue::fake();
        $mappingVend = $this->bindGrabStore(4416, self::MERCHANT, [11 => ['qty' => 5, 'capacity' => 10], 12 => ['qty' => 3, 'capacity' => 10]]);
        $this->submit($this->grabOrderPayload(self::MERCHANT, 4416, $this->itemIds()))->assertOk();

        $service = app(DeliveryProductMappingService::class);
        $channel11 = DeliveryProductMappingVendChannel::where('delivery_product_mapping_vend_id', $mappingVend->id)->where('vend_channel_code', 11)->firstOrFail();

        $this->assertSame([$channel11->id => 2], $service->heldQtyByChannel([$channel11->id]));
        $this->assertSame(3, $service->getDeliveryVendChannelStatus($channel11->vendChannel, $channel11)['available_qty']);

        // The legacy counter no longer decides: a stuck value changes nothing.
        $channel11->forceFill(['order_qty' => 40])->save();
        $this->assertSame(3, $service->getDeliveryVendChannelStatus($channel11->vendChannel, $channel11->fresh())['available_qty']);

        DeliveryPlatformOrder::firstOrFail()->forceFill(['dispensed_at' => now(), 'status' => DeliveryPlatformOrder::STATUS_DISPENSED])->save();
        $this->assertSame([], $service->heldQtyByChannel([$channel11->id]));
    }

    public function test_grab_cancelling_an_order_releases_its_stock_through_the_state_webhook(): void
    {
        Queue::fake();
        $mappingVend = $this->bindGrabStore(4416, self::MERCHANT, [11 => ['qty' => 5, 'capacity' => 10], 12 => ['qty' => 3, 'capacity' => 10]]);
        $this->submit($this->grabOrderPayload(self::MERCHANT, 4416, $this->itemIds()))->assertOk();

        $this->putJson('/api/delivery/grab/order/update', [
            'merchantID' => self::MERCHANT, 'orderID' => '001569933881-TEST', 'state' => 'CANCELLED',
        ])->assertOk();

        $order = DeliveryPlatformOrder::firstOrFail();
        $this->assertSame(DeliveryPlatformOrder::STATUS_CANCELLED, $order->status);
        $this->assertNotNull($order->final_status_at);
        $ids = DeliveryProductMappingVendChannel::where('delivery_product_mapping_vend_id', $mappingVend->id)->pluck('id');
        $this->assertSame([], app(DeliveryProductMappingService::class)->heldQtyByChannel($ids));
    }

    public function test_the_driver_keying_the_order_in_queues_the_dispense(): void
    {
        Queue::fake();
        $this->bindGrabStore(4416, self::MERCHANT, [11 => ['qty' => 5, 'capacity' => 10], 12 => ['qty' => 3, 'capacity' => 10]]);
        $this->submit($this->grabOrderPayload(self::MERCHANT, 4416, $this->itemIds()))->assertOk();

        $this->postJson('/api/delivery/order/search/1', ['Vid' => 4416, 'short_order_id' => 'GM604', 'driver_phone_number' => '+6511111111'])
            ->assertOk();

        Queue::assertPushed(DispenseDeliveryPlatformOrder::class, 1);
        $this->assertSame(DeliveryPlatformOrder::STATUS_REQUESTED, DeliveryPlatformOrder::firstOrFail()->status);
    }

    public function test_dispensing_builds_one_trade_line_per_unit_for_the_machine(): void
    {
        Queue::fake();
        $this->bindGrabStore(4416, self::MERCHANT, [11 => ['qty' => 5, 'capacity' => 10], 12 => ['qty' => 3, 'capacity' => 10]]);
        $this->submit($this->grabOrderPayload(self::MERCHANT, 4416, $this->itemIds()))->assertOk();

        app(DeliveryPlatformService::class)->dispenseOrder(DeliveryPlatformOrder::firstOrFail());

        $frame = DeliveryPlatformOrder::firstOrFail()->response_history_json;
        $this->assertSame('TRADE', $frame['Type']);
        $this->assertSame(4416, $frame['mid']);
        $this->assertEquals(10.6, $frame['price'], 'unchanged wire format: dollars');
        $this->assertSame([11, 11, 12], array_column($frame['shipment_info'], 'goodroadid'));
    }
}
