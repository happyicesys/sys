<?php

namespace Tests\Feature;

use App\Models\CityboxWebhookEvent;
use App\Models\Vend;
use App\Services\Citybox\OpenapiSigner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CityboxWebhookTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'openapi-test-secret';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'citybox.openapi.enabled' => true,
            'citybox.openapi.secret' => self::SECRET,
            'citybox.openapi.app_id' => '20221009',
        ]);
    }

    private function makeChiller(string $equipmentId): Vend
    {
        return Vend::create([
            'code' => 9001,
            'machine_type' => Vend::MACHINE_TYPE_SMART_CHILLER,
            'citybox_equipment_id' => $equipmentId,
            'is_active' => 1,
        ]);
    }

    /** Build signed form params the way Citybox pushes them (data = raw JSON string). */
    private function signedPush(array $data, array $extra = []): array
    {
        $params = array_merge([
            'app_id' => '20221009',
            'data' => json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ], $extra);
        $params['sign'] = md5(OpenapiSigner::baseString($params, true).self::SECRET);

        return $params;
    }

    /** Trimmed version of the doc's 有订单 order-push example. */
    private function orderPushData(string $boxNo = 'aaa'): array
    {
        return [
            'open_id' => 'dfuo577e8fa5202a4b5cac3f',
            'open_log_id' => '75664466',
            'out_trade_no' => '',
            'open_time' => '2024-04-26 17:06:23',
            'order' => [
                'id' => '65512147',
                'order_name' => 'WV2404',
                'order_time' => '2024-04-26 17:09:59',
                'box_no' => $boxNo,
                'money' => '4.05',
                'good_money' => '4.50',
                'discounted_money' => '0.45',
                'qty' => '1',
            ],
            'order_goods' => [[
                'product_id' => '1590',
                'qty' => '1',
                'price' => '4.50',
                'product_name' => '爱夸饮用天然矿泉水',
                'really_money' => '4.05',
            ]],
        ];
    }

    // ── order push ─────────────────────────────────────────────────────────

    public function test_order_push_is_stored_and_matched_to_the_chiller_vend(): void
    {
        $vend = $this->makeChiller('aaa');

        $response = $this->post('/api/citybox/order-push', $this->signedPush($this->orderPushData()));

        $response->assertOk()->assertJson(['status' => 200, 'success' => true]);

        $event = CityboxWebhookEvent::sole();
        $this->assertSame(CityboxWebhookEvent::TYPE_ORDER, $event->type);
        $this->assertSame('75664466', $event->event_key);
        $this->assertSame($vend->id, $event->vend_id);
        $this->assertSame('4.05', $event->payload['order']['money']);
        $this->assertNotNull($event->signature_variant);
        $this->assertNull($event->processed_at);
    }

    public function test_duplicate_order_push_acks_success_without_second_row(): void
    {
        $this->makeChiller('aaa');
        $params = $this->signedPush($this->orderPushData());

        $this->post('/api/citybox/order-push', $params)->assertJson(['success' => true]);
        $this->post('/api/citybox/order-push', $params)->assertJson(['success' => true]);

        $this->assertSame(1, CityboxWebhookEvent::count());
    }

    public function test_empty_order_push_door_opened_no_purchase_is_stored(): void
    {
        $this->makeChiller('aaa');
        $data = [
            'open_id' => '01f834035',
            'open_log_id' => '7563',
            'out_trade_no' => '',
            'open_time' => '2024-04-26 12:08:00',
            'order' => [],
            'order_goods' => [],
            'foot_img' => [],
        ];

        $this->post('/api/citybox/order-push', $this->signedPush($data))
            ->assertJson(['success' => true]);

        $event = CityboxWebhookEvent::sole();
        $this->assertSame('7563', $event->event_key);
        // Empty order carries no box_no — stored unmatched by design.
        $this->assertNull($event->vend_id);
    }

    public function test_order_push_for_unknown_device_is_stored_without_vend(): void
    {
        $this->post('/api/citybox/order-push', $this->signedPush($this->orderPushData('UNKNOWN1')))
            ->assertJson(['success' => true]);

        $this->assertNull(CityboxWebhookEvent::sole()->vend_id);
        $this->assertSame(0, Vend::count()); // never auto-created
    }

    public function test_vending_machine_with_same_serial_is_not_matched(): void
    {
        // The surface is Smart Chiller-only: a non-chiller vend must not match.
        Vend::create([
            'code' => 9002,
            'machine_type' => Vend::MACHINE_TYPE_VENDING_MACHINE,
            'citybox_equipment_id' => 'aaa',
            'is_active' => 1,
        ]);

        $this->post('/api/citybox/order-push', $this->signedPush($this->orderPushData()))
            ->assertJson(['success' => true]);

        $this->assertNull(CityboxWebhookEvent::sole()->vend_id);
    }

    // ── signature and config gates ─────────────────────────────────────────

    public function test_bad_signature_is_refused_but_kept_for_forensics(): void
    {
        $params = $this->signedPush($this->orderPushData());
        $params['sign'] = md5('forged');

        $this->post('/api/citybox/order-push', $params)
            ->assertOk()->assertJson(['success' => false]);

        $event = CityboxWebhookEvent::sole();
        $this->assertNull($event->signature_variant);
        $this->assertNull($event->vend_id);
    }

    public function test_no_trailing_ampersand_signature_variant_is_also_accepted(): void
    {
        $this->makeChiller('aaa');
        $params = [
            'app_id' => '20221009',
            'data' => json_encode($this->orderPushData(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ];
        $params['sign'] = md5(OpenapiSigner::baseString($params, false).self::SECRET);

        $this->post('/api/citybox/order-push', $params)->assertJson(['success' => true]);

        $this->assertSame(OpenapiSigner::VARIANT_NO_TRAILING, CityboxWebhookEvent::sole()->signature_variant);
    }

    public function test_disabled_integration_refuses_ack_so_citybox_retries(): void
    {
        config(['citybox.openapi.enabled' => false]);

        $this->post('/api/citybox/order-push', $this->signedPush($this->orderPushData()))
            ->assertOk()->assertJson(['success' => false]);

        $this->assertSame(0, CityboxWebhookEvent::count());
    }

    public function test_duplicate_key_with_different_content_keeps_v1_and_warns(): void
    {
        \Illuminate\Support\Facades\Log::spy();
        $this->makeChiller('aaa');

        $v1 = $this->orderPushData();
        $v2 = $this->orderPushData();
        $v2['order']['money'] = '9.99'; // same open_log_id, revised content

        $this->post('/api/citybox/order-push', $this->signedPush($v1))->assertJson(['success' => true]);
        $this->post('/api/citybox/order-push', $this->signedPush($v2))->assertJson(['success' => true]);

        $event = CityboxWebhookEvent::sole();
        $this->assertSame('4.05', $event->payload['order']['money']); // v1 kept

        \Illuminate\Support\Facades\Log::shouldHaveReceived('warning')
            ->withArgs(fn ($msg) => str_contains($msg, 'DIFFERENT CONTENT'))
            ->once();
    }

    public function test_malformed_json_pushes_do_not_collapse_into_one_event(): void
    {
        // Two different broken payloads must store two rows, not dedupe into
        // md5-of-empty and silently drop the second.
        foreach (['not-json-a', 'not-json-b'] as $broken) {
            $params = ['app_id' => '20221009', 'data' => $broken];
            $params['sign'] = md5(OpenapiSigner::baseString($params, true).self::SECRET);
            $this->post('/api/citybox/order-push', $params)->assertJson(['success' => true]);
        }

        $this->assertSame(2, CityboxWebhookEvent::count());
    }

    public function test_scalar_json_data_is_stored_not_crashed(): void
    {
        // `"123"` and `'"abc"'` are VALID JSON but decode to scalars, not
        // arrays. That must degrade exactly like invalid JSON (store the raw
        // bytes, ack success) — not throw on the array-typed helpers, refuse
        // the ack, and leave Citybox retrying a push we can never ingest.
        foreach (['123', '"abc"', 'true'] as $scalar) {
            $params = ['app_id' => '20221009', 'data' => $scalar];
            $params['sign'] = md5(OpenapiSigner::baseString($params, true).self::SECRET);
            $this->post('/api/citybox/order-push', $params)->assertJson(['success' => true]);
        }

        $this->assertSame(3, CityboxWebhookEvent::count());
        $this->assertSame(
            ['123', '"abc"', 'true'],
            CityboxWebhookEvent::orderBy('id')->pluck('raw_data')->all()
        );
    }

    // ── refund push ────────────────────────────────────────────────────────

    public function test_refund_push_is_stored_keyed_by_order_and_status(): void
    {
        // Doc example: refund push carries order_name OUTSIDE data.
        $data = [
            'reason' => '1',
            'reason_detail' => '希望退款退货后用积分支付',
            'refund_money' => '9.70',
            'really_money' => '9.70',
            'refund_status' => '3',
            'create_time' => '2023-11-28 15:17:32',
        ];

        $this->post('/api/citybox/refund-push', $this->signedPush($data, ['order_name' => 'WV0140421415']))
            ->assertJson(['success' => true]);

        $event = CityboxWebhookEvent::sole();
        $this->assertSame(CityboxWebhookEvent::TYPE_REFUND, $event->type);
        $this->assertSame('WV0140421415:3', $event->event_key);
        $this->assertSame('WV0140421415', $event->payload['order_name']);
    }

    public function test_refund_status_progression_stores_a_row_per_status(): void
    {
        $base = ['refund_money' => '9.70', 'create_time' => '2023-11-28 15:17:32'];

        $this->post('/api/citybox/refund-push', $this->signedPush($base + ['refund_status' => '3'], ['order_name' => 'WV1']));
        $this->post('/api/citybox/refund-push', $this->signedPush($base + ['refund_status' => '4'], ['order_name' => 'WV1']));

        $this->assertSame(2, CityboxWebhookEvent::count());
    }

    // ── close push ─────────────────────────────────────────────────────────

    public function test_close_push_is_stored_and_matched_by_top_level_box_no(): void
    {
        $vend = $this->makeChiller('IC3EFK87GSB');
        $data = [
            'close_time' => '2024-07-01 15:34:22',
            'box_no' => 'IC3EFK87GSB',
            'open_log_id' => '837sjci2344di3',
            'open_id' => '948821',
        ];

        $this->post('/api/citybox/close-push', $this->signedPush($data))
            ->assertJson(['success' => true]);

        $event = CityboxWebhookEvent::sole();
        $this->assertSame(CityboxWebhookEvent::TYPE_CLOSE, $event->type);
        $this->assertSame($vend->id, $event->vend_id);
    }
}
