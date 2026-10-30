<?php

namespace Tests\Feature;

use App\Jobs\RefundOmiseJob;
use App\Models\Operator;
use App\Models\PaymentGatewayLog;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * refund:payment-gateway-every-ten-minutes refunds an approved Omise payment the machine never
 * confirmed. On lsh `settings.payment_gateway_log_refund_scanned_at` was NULL, which made the query
 * throw every run (2026-10-25 → 10-30), so lsh never auto-refunded.
 */
class RefundPaymentGatewayCronTest extends TestCase
{
    use RefreshDatabase;

    private int $operatorGatewayId;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        Carbon::setTestNow('2026-10-30 10:00:00');
        $operator = Operator::withoutGlobalScopes()->create(['code' => 'TSTOP', 'name' => 'Test Operator', 'is_active' => true]);
        $gatewayId = DB::table('payment_gateways')->insertGetId(['name' => 'omise', 'country_id' => 1, 'created_at' => now(), 'updated_at' => now()]);
        $this->operatorGatewayId = DB::table('operator_payment_gateways')->insertGetId([
            'operator_id' => $operator->id, 'payment_gateway_id' => $gatewayId, 'type' => 'live', 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function payment(string $orderId, string $approvedAt, bool $dispensed = false): PaymentGatewayLog
    {
        return PaymentGatewayLog::create([
            'order_id' => $orderId, 'vend_code' => '2031', 'amount' => 1.50,
            'status' => PaymentGatewayLog::STATUS_APPROVE, 'approved_at' => $approvedAt,
            'is_dispensed' => $dispensed, 'ref_id' => 'chrg_'.$orderId,
            'operator_payment_gateway_id' => $this->operatorGatewayId, 'payment_gateway_id' => DB::table('payment_gateways')->value('id'),
        ]);
    }

    public function test_never_scanned_starts_a_day_back_instead_of_throwing(): void
    {
        Setting::create([]);
        $this->payment('recent', '2026-10-30 09:30:00');
        $this->payment('too_old', '2026-10-28 09:30:00');
        $this->payment('too_new', '2026-10-30 09:55:00');
        $this->payment('dispensed', '2026-10-30 09:30:00', dispensed: true);

        $this->artisan('refund:payment-gateway-every-ten-minutes')->assertSuccessful();

        Queue::assertPushed(RefundOmiseJob::class, 1);
        $this->assertSame('2026-10-30 09:50:00', Setting::first()->payment_gateway_log_refund_scanned_at->toDateTimeString());
    }

    public function test_windows_follow_on_without_gap_or_overlap(): void
    {
        Setting::create(['payment_gateway_log_refund_scanned_at' => '2026-10-30 09:40:00']);
        $this->payment('in_window', '2026-10-30 09:45:00');
        $this->payment('before_window', '2026-10-30 09:35:00');

        $this->artisan('refund:payment-gateway-every-ten-minutes')->assertSuccessful();
        Queue::assertPushed(RefundOmiseJob::class, 1);

        Carbon::setTestNow('2026-10-30 10:10:00');
        $this->payment('next', '2026-10-30 09:55:00');
        $this->artisan('refund:payment-gateway-every-ten-minutes')->assertSuccessful();

        Queue::assertPushed(RefundOmiseJob::class, 2);
        $this->assertSame('2026-10-30 10:00:00', Setting::first()->payment_gateway_log_refund_scanned_at->toDateTimeString());
    }
}
