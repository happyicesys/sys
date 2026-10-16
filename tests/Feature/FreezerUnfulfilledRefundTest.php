<?php

namespace Tests\Feature;

use App\Http\Middleware\VerifyDeviceSignature;
use App\Jobs\RefundOmiseJob;
use App\Models\Operator;
use App\Models\PaymentGatewayLog;
use App\Models\SmartFreezerVideo;
use App\Models\Vend;
use App\Models\VendTransaction;
use App\Support\AutoRefundSource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * A freezer that took a PayNow payment but never opened its door (an AI camera down after payment, or
 * the unlock never authorised) reports it, and mark1 refunds through Omise — only when nothing says
 * the door session happened (Brian, 2026-10-16). Also: the camera list the freezer must check.
 */
class FreezerUnfulfilledRefundTest extends TestCase
{
    use RefreshDatabase;

    private const KEY = 'TESTKEY000000001';

    private const ORDER = '26101611354950001';

    private const REF = 'SF-50001-1792121724-1';

    private Vend $vend;

    private int $operatorGatewayId;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        $operator = Operator::withoutGlobalScopes()->create(['code' => 'TSTOP', 'name' => 'Test Operator', 'is_active' => true]);
        $this->vend = $this->freezer(50001, $operator->id);
        $gatewayId = DB::table('payment_gateways')->insertGetId(['name' => 'omise', 'country_id' => 1, 'created_at' => now(), 'updated_at' => now()]);
        $this->operatorGatewayId = DB::table('operator_payment_gateways')->insertGetId([
            'operator_id' => $operator->id, 'payment_gateway_id' => $gatewayId, 'type' => 'live', 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function freezer(int $code, int $operatorId, string $type = Vend::MACHINE_TYPE_SMART_FREEZER): Vend
    {
        $vend = new Vend;
        $vend->forceFill([
            'code' => $code, 'name' => "Freezer {$code}", 'machine_type' => $type, 'is_active' => 1,
            'operator_id' => $operatorId, 'vend_model_id' => 1, 'private_key' => self::KEY, 'is_testing' => 0,
        ])->save();

        return $vend->refresh();
    }

    private function payment(array $overrides = []): PaymentGatewayLog
    {
        return PaymentGatewayLog::create($overrides + [
            'order_id' => self::ORDER, 'vend_id' => $this->vend->id, 'vend_code' => '50001',
            'amount' => 1.10, 'status' => PaymentGatewayLog::STATUS_APPROVE, 'approved_at' => now()->subMinute(),
            'is_dispensed' => true, 'ref_id' => 'chrg_test_1', 'operator_payment_gateway_id' => $this->operatorGatewayId,
        ]);
    }

    private function report(array $body = [], string $order = self::ORDER, int $code = 50001, ?string $key = self::KEY): TestResponse
    {
        $path = "/api/v1/vends/{$code}/orders/{$order}/unfulfilled";
        $json = json_encode($body + ['session_ref' => self::REF, 'amount_cents' => 110, 'reason' => 'cameras_down']);
        $ts = (string) time();
        $headers = [
            'X-Device-Timestamp' => $ts,
            'X-Device-Signature' => VerifyDeviceSignature::sign($key ?? 'nope', 'POST', $path, $ts, $json),
            'Content-Type' => 'application/json',
        ];

        return $this->call('POST', $path, [], [], [], $this->transformHeadersToServerVars($headers), $json);
    }

    private function assertRefundQueued(): void
    {
        Queue::assertPushed(RefundOmiseJob::class, function (RefundOmiseJob $job) {
            $read = fn (string $p) => (fn () => $this->{$p})->call($job);

            return $read('orderId') === self::ORDER && $read('source') === AutoRefundSource::OMISE_DOOR_NOT_OPENED;
        });
    }

    public function test_a_paid_order_whose_door_never_opened_is_refunded_once(): void
    {
        $this->payment();

        $this->report()->assertStatus(202)->assertJson(['status' => 'refund_queued']);
        $this->report()->assertStatus(202);

        $this->assertRefundQueued();
        Queue::assertPushed(RefundOmiseJob::class, 1);
    }

    public function test_an_unlock_that_was_never_authorised_is_refunded_too(): void
    {
        $this->payment();

        $this->report(['reason' => 'unlock_authorization_failed'])->assertStatus(202);

        $this->assertRefundQueued();
    }

    public function test_an_already_refunded_order_answers_done_without_a_second_refund(): void
    {
        $this->payment(['status' => PaymentGatewayLog::STATUS_REFUND]);

        $this->report()->assertOk()->assertJson(['status' => 'already_refunded']);
        Queue::assertNothingPushed();
    }

    public function test_any_sign_that_the_door_session_happened_refuses(): void
    {
        $log = $this->payment();
        $txn = new VendTransaction;
        $txn->forceFill([
            'vend_id' => $this->vend->id, 'order_id' => self::ORDER, 'payment_gateway_log_id' => $log->id,
            'is_found_in_transaction' => true, 'amount' => 110, 'transaction_datetime' => now(),
        ])->saveQuietly();

        $this->report()->assertStatus(409)->assertJson(['error' => 'the machine reported a sale for this order']);

        $txn->forceFill(['is_found_in_transaction' => false])->saveQuietly();
        SmartFreezerVideo::create(['vend_id' => $this->vend->id, 'order_no' => self::REF, 'payload' => [], 'video_urls' => []]);

        $this->report()->assertStatus(409)->assertJson(['error' => 'a door session was recorded']);
        Queue::assertNothingPushed();
    }

    public function test_a_claim_that_does_not_fit_the_payment_is_refused(): void
    {
        $this->payment();

        $this->report(['amount_cents' => 220])->assertStatus(409);
        $this->report(['reason' => 'door_open_failed'])->assertStatus(422);
        $this->report([], '99999999999999999')->assertStatus(404);
        $this->report(['amount_cents' => 0])->assertStatus(422);
        Queue::assertNothingPushed();
    }

    public function test_an_old_or_unapproved_payment_is_left_for_a_person(): void
    {
        $log = $this->payment(['approved_at' => now()->subHours(7)]);
        $this->report()->assertStatus(409)->assertJson(['error' => 'payment too old to refund automatically']);

        $log->update(['status' => PaymentGatewayLog::STATUS_PENDING, 'approved_at' => null]);
        $this->report()->assertStatus(409)->assertJson(['error' => 'payment not approved']);
        Queue::assertNothingPushed();
    }

    public function test_another_machines_order_or_an_unsigned_call_is_refused(): void
    {
        $other = $this->freezer(50002, $this->vend->operator_id);
        $this->payment(['vend_id' => $other->id, 'vend_code' => '50002']);

        $this->report()->assertStatus(404);
        $this->report([], self::ORDER, 50001, 'WRONGKEY00000000')->assertStatus(401);
        Queue::assertNothingPushed();
    }

    public function test_the_freezer_is_told_which_cameras_to_check(): void
    {
        $settings = function (int $code): array {
            $path = "/api/v1/vends/{$code}/clips/settings";
            $ts = (string) time();

            return $this->call('GET', $path, [], [], [], $this->transformHeadersToServerVars([
                'X-Device-Timestamp' => $ts, 'Accept' => 'application/json',
                'X-Device-Signature' => VerifyDeviceSignature::sign(self::KEY, 'GET', $path, $ts, ''),
            ]))->assertOk()->json('guard_cameras');
        };
        $this->freezer(50002, $this->vend->operator_id);
        $this->freezer(50003, $this->vend->operator_id);

        config(['smart_freezer.camera_guard.vends' => []]);
        $this->assertSame([], $settings(50001), 'empty list = off everywhere');

        config(['smart_freezer.camera_guard.cameras' => [4, 3], 'smart_freezer.camera_guard.vends' => ['50001', '50002:3', '50003:9+4']]);
        $this->assertSame([3, 4], $settings(50001));
        $this->assertSame([3], $settings(50002));
        $this->assertSame([4], $settings(50003), 'out-of-range ids are dropped');
    }
}
