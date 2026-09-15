<?php

namespace Tests\Feature;

use App\Jobs\Vend\CreateGatewayVendTransaction;
use App\Jobs\Vend\LogNofoundTxnIfStillMissing;
use App\Jobs\VerifyPaymentWebhook;
use App\Models\OperatorPaymentGateway;
use App\Models\PaymentGatewayLog;
use App\Models\VendTransaction;
use App\Services\Payment\WebhookVerdict;
use App\Services\Payment\WebhookVerifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

/**
 * Audit M3-01: POST /api/v1/payment-gateway-status/omise is unauthenticated and
 * unsigned. A forged "successful" charge used to dispense product; a forged
 * "refund" marked a sale refunded. The webhook is now checked against Omise's
 * own record of the charge (GET /charges/{id} with the merchant's secret key).
 *
 * `log` mode (the default): the webhook is processed exactly as before and the
 * verdict is only logged from a queued job - proves the check on real traffic.
 * `enforce` mode: a MISMATCH is refused before any state changes; UNVERIFIABLE
 * (Omise API down) is allowed through so an Omise outage cannot stop QR sales.
 */
class PaymentWebhookVerificationTest extends TestCase
{
    use RefreshDatabase;

    private const ORDER = '26091516000002031';

    private const CHARGE = 'chrg_test_verify_1';

    private const SECRET = 'skey_test_verify';

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.gateway_unified_txn_enabled' => true, 'app.gateway_unified_txn_vend_codes' => '']);
        // Auto-increment ids are not reset between transactional tests, so the
        // real id is captured rather than assumed to be 1.
        $this->gatewayId = OperatorPaymentGateway::create(['operator_id' => 1, 'payment_gateway_id' => 1, 'key1' => 'pkey_test', 'key2' => self::SECRET, 'type' => 'production'])->id;
    }

    private int $gatewayId;

    private function seedLog(int $status = PaymentGatewayLog::STATUS_PENDING, float $amount = 3.5): PaymentGatewayLog
    {
        return PaymentGatewayLog::create([
            'order_id' => self::ORDER,
            'ref_id' => $status === PaymentGatewayLog::STATUS_APPROVE ? self::CHARGE : null,
            'vend_id' => 166,
            'vend_code' => '2031',
            'operator_payment_gateway_id' => $this->gatewayId,
            // No payment_gateways row exists in the test DB, so processPayment()
            // (the MQTT dispense) is skipped and the approval is observable via
            // the two jobs it dispatches first.
            'payment_gateway_id' => 1,
            'amount' => $amount,
            'status' => $status,
            'txn_src' => 1,
        ]);
    }

    /** The shape Omise sends for charge.complete on a PayNow QR. */
    private function approveEvent(string $chargeId = self::CHARGE, int $amount = 350): array
    {
        return [
            'object' => 'event', 'id' => 'evnt_test_1', 'key' => 'charge.complete',
            'data' => [
                'object' => 'charge', 'id' => $chargeId, 'status' => 'successful', 'amount' => $amount, 'currency' => 'SGD',
                'metadata' => ['order_id' => self::ORDER],
                'source' => ['type' => 'paynow', 'provider_references' => ['reference_number_1' => 'PN123']],
            ],
        ];
    }

    private function refundEvent(): array
    {
        return [
            'object' => 'event', 'id' => 'evnt_test_2', 'key' => 'refund.create',
            'data' => ['object' => 'refund', 'id' => 'rfnd_test_1', 'amount' => 350, 'charge' => self::CHARGE, 'status' => 'closed', 'metadata' => []],
        ];
    }

    /** What Omise's API says about the charge. */
    private function omiseCharge(array $overrides = []): array
    {
        return array_replace_recursive([
            'object' => 'charge', 'id' => self::CHARGE, 'status' => 'successful', 'amount' => 350, 'currency' => 'SGD',
            'refunded_amount' => 0, 'refunds' => ['object' => 'list', 'data' => []],
            'metadata' => ['order_id' => self::ORDER],
        ], $overrides);
    }

    /**
     * Http::fake() calls ACCUMULATE on one factory instance and the first
     * matching stub wins, so a test that fakes several answers in sequence
     * must start from a fresh factory each time or every call sees the first.
     */
    private function freshHttp(): void
    {
        \Illuminate\Support\Facades\Facade::clearResolvedInstance(\Illuminate\Http\Client\Factory::class);
        $this->app->forgetInstance(\Illuminate\Http\Client\Factory::class);
    }

    private function fakeOmise(array|int $chargeResponse): void
    {
        $this->freshHttp();
        Http::fake([
            'api.omise.co/charges/*' => is_int($chargeResponse)
                ? Http::response(['object' => 'error', 'code' => 'not_found'], $chargeResponse)
                : Http::response($chargeResponse, 200),
        ]);
    }

    // ---------------------------------------------------------------- verifier

    public function test_verifier_confirms_a_genuine_approve_and_uses_the_merchant_secret(): void
    {
        $log = $this->seedLog();
        $this->fakeOmise($this->omiseCharge());

        $verdict = app(WebhookVerifier::class)->verify('omise', $this->approveEvent(), $log, PaymentGatewayLog::STATUS_APPROVE);

        $this->assertSame(WebhookVerdict::VERIFIED, $verdict->outcome, $verdict->reason);
        Http::assertSent(fn ($req) => str_contains($req->url(), '/charges/'.self::CHARGE)
            && $req->header('Authorization')[0] === 'Basic '.base64_encode(self::SECRET.':'));
    }

    public function test_verifier_rejects_forged_status_wrong_order_wrong_amount_and_unknown_charge(): void
    {
        $log = $this->seedLog();
        $verifier = app(WebhookVerifier::class);
        $approve = fn () => $verifier->verify('omise', $this->approveEvent(), $log, PaymentGatewayLog::STATUS_APPROVE);

        $this->fakeOmise($this->omiseCharge(['status' => 'pending']));
        $this->assertSame([WebhookVerdict::MISMATCH, 'omise does not report the charge as successful'], [$approve()->outcome, $approve()->reason]);

        $this->fakeOmise($this->omiseCharge(['metadata' => ['order_id' => '26091516000002999']]));
        $this->assertSame([WebhookVerdict::MISMATCH, 'charge belongs to a different order'], [$approve()->outcome, $approve()->reason]);

        $this->fakeOmise($this->omiseCharge(['amount' => 100]));
        $this->assertSame([WebhookVerdict::MISMATCH, 'charge amount differs from our order'], [$approve()->outcome, $approve()->reason]);

        $this->fakeOmise(404);
        $this->assertSame([WebhookVerdict::MISMATCH, 'omise has no such charge'], [$approve()->outcome, $approve()->reason]);
    }

    public function test_verifier_is_unverifiable_not_mismatch_when_omise_is_down_and_skips_pending(): void
    {
        $log = $this->seedLog();
        $verifier = app(WebhookVerifier::class);

        $this->freshHttp();
        Http::fake(['api.omise.co/*' => Http::response('', 503)]);
        $this->assertSame(WebhookVerdict::UNVERIFIABLE, $verifier->verify('omise', $this->approveEvent(), $log, PaymentGatewayLog::STATUS_APPROVE)->outcome);

        $this->freshHttp();
        Http::fake(['api.omise.co/*' => fn () => throw new \Illuminate\Http\Client\ConnectionException('timeout')]);
        $this->assertSame(WebhookVerdict::UNVERIFIABLE, $verifier->verify('omise', $this->approveEvent(), $log, PaymentGatewayLog::STATUS_APPROVE)->outcome);

        $this->freshHttp();
        Http::fake();
        $this->assertSame(WebhookVerdict::SKIPPED, $verifier->verify('omise', $this->approveEvent(), $log, PaymentGatewayLog::STATUS_PENDING)->outcome);
        $this->assertSame(WebhookVerdict::SKIPPED, $verifier->verify('fiuu', [], $log, PaymentGatewayLog::STATUS_APPROVE)->outcome);
        Http::assertNothingSent();
    }

    public function test_verifier_confirms_a_refund_only_when_omise_shows_money_returned(): void
    {
        $log = $this->seedLog(PaymentGatewayLog::STATUS_APPROVE);
        $verifier = app(WebhookVerifier::class);
        $refund = fn () => $verifier->verify('omise', $this->refundEvent(), $log, PaymentGatewayLog::STATUS_REFUND);

        $this->fakeOmise($this->omiseCharge());
        $this->assertSame(WebhookVerdict::MISMATCH, $refund()->outcome, 'nothing refunded at Omise');

        $this->fakeOmise($this->omiseCharge(['refunded_amount' => 350, 'refunds' => ['data' => [['object' => 'refund', 'id' => 'rfnd_test_1']]]]));
        $verdict = $refund();
        $this->assertSame(WebhookVerdict::VERIFIED, $verdict->outcome, $verdict->reason.' '.json_encode($verdict->detail));

        $this->fakeOmise($this->omiseCharge(['refunded_amount' => 350, 'refunds' => ['data' => [['object' => 'refund', 'id' => 'rfnd_other']]]]));
        $this->assertSame(WebhookVerdict::MISMATCH, $refund()->outcome, 'the named refund is not on the charge');
    }

    // ---------------------------------------------------------------- log mode

    public function test_log_mode_processes_the_webhook_as_before_and_queues_the_check(): void
    {
        config(['payment.webhook_verification' => 'log']);
        Bus::fake([VerifyPaymentWebhook::class, LogNofoundTxnIfStillMissing::class, CreateGatewayVendTransaction::class]);
        Http::fake();
        $log = $this->seedLog();

        $this->postJson('/api/v1/payment-gateway-status/omise', $this->approveEvent())->assertOk();

        $this->assertSame(PaymentGatewayLog::STATUS_APPROVE, (int) $log->refresh()->status);
        $this->assertNotNull($log->approved_at);
        Bus::assertDispatched(LogNofoundTxnIfStillMissing::class);
        Bus::assertDispatched(CreateGatewayVendTransaction::class);
        Bus::assertDispatched(VerifyPaymentWebhook::class, fn ($job) => $job->paymentGatewayLogId === $log->id
            && $job->company === 'omise' && $job->status === PaymentGatewayLog::STATUS_APPROVE && $job->queue === 'low');
        Http::assertNothingSent(); // nothing inline - the approval path is untouched
    }

    public function test_log_mode_does_not_queue_a_check_for_pending_events(): void
    {
        config(['payment.webhook_verification' => 'log']);
        Bus::fake([VerifyPaymentWebhook::class]);
        $this->seedLog();
        $event = $this->approveEvent();
        $event['data']['status'] = 'pending';

        $this->postJson('/api/v1/payment-gateway-status/omise', $event)->assertOk();

        Bus::assertNotDispatched(VerifyPaymentWebhook::class);
    }

    public function test_log_mode_job_logs_a_mismatch_but_changes_nothing(): void
    {
        $log = $this->seedLog(PaymentGatewayLog::STATUS_APPROVE);
        $this->fakeOmise($this->omiseCharge(['status' => 'failed']));
        Log::shouldReceive('warning')->once()->withArgs(fn ($msg, $ctx) => str_contains($msg, 'mismatch (not enforced)')
            && $ctx['outcome'] === WebhookVerdict::MISMATCH && $ctx['log_id'] === $log->id && isset($ctx['duration_ms']));
        Log::shouldReceive('info')->zeroOrMoreTimes();

        (new VerifyPaymentWebhook($log->id, 'omise', $this->approveEvent(), PaymentGatewayLog::STATUS_APPROVE))->handle(app(WebhookVerifier::class));

        $this->assertSame(PaymentGatewayLog::STATUS_APPROVE, (int) $log->refresh()->status);
    }

    // ------------------------------------------------------------ enforce mode

    public function test_enforce_mode_refuses_a_forged_approve_before_any_state_changes(): void
    {
        config(['payment.webhook_verification' => 'enforce']);
        Bus::fake();
        $log = $this->seedLog();
        $this->fakeOmise($this->omiseCharge(['status' => 'pending'])); // Omise: not paid

        $this->postJson('/api/v1/payment-gateway-status/omise', $this->approveEvent())->assertOk();

        $log->refresh();
        $this->assertSame(PaymentGatewayLog::STATUS_PENDING, (int) $log->status, 'forged approve must not flip the log');
        $this->assertNull($log->approved_at);
        $this->assertNull($log->ref_id);
        Bus::assertNotDispatched(LogNofoundTxnIfStillMissing::class);
        Bus::assertNotDispatched(CreateGatewayVendTransaction::class);
        Bus::assertNotDispatched(VerifyPaymentWebhook::class);
    }

    public function test_enforce_mode_approves_a_genuine_charge(): void
    {
        config(['payment.webhook_verification' => 'enforce']);
        Bus::fake([LogNofoundTxnIfStillMissing::class, CreateGatewayVendTransaction::class, VerifyPaymentWebhook::class]);
        $log = $this->seedLog();
        $this->fakeOmise($this->omiseCharge());

        $this->postJson('/api/v1/payment-gateway-status/omise', $this->approveEvent())->assertOk();

        $this->assertSame(PaymentGatewayLog::STATUS_APPROVE, (int) $log->refresh()->status);
        Bus::assertDispatched(CreateGatewayVendTransaction::class);
        Bus::assertNotDispatched(VerifyPaymentWebhook::class, 'inline check replaces the queued one');
    }

    public function test_enforce_mode_lets_an_unverifiable_approve_through(): void
    {
        config(['payment.webhook_verification' => 'enforce']);
        Bus::fake([LogNofoundTxnIfStillMissing::class, CreateGatewayVendTransaction::class]);
        $log = $this->seedLog();
        Http::fake(['api.omise.co/*' => Http::response('', 503)]);

        $this->postJson('/api/v1/payment-gateway-status/omise', $this->approveEvent())->assertOk();

        $this->assertSame(PaymentGatewayLog::STATUS_APPROVE, (int) $log->refresh()->status, 'an Omise outage must not stop QR sales');
        Bus::assertDispatched(CreateGatewayVendTransaction::class);
    }

    public function test_enforce_mode_refuses_a_forged_refund(): void
    {
        config(['payment.webhook_verification' => 'enforce']);
        $log = $this->seedLog(PaymentGatewayLog::STATUS_APPROVE);
        $txn = VendTransaction::create([
            'order_id' => self::ORDER, 'vend_id' => 166, 'transaction_datetime' => now(), 'amount' => 350, 'qty' => 1,
            'success_qty' => 1, 'dispensed_qty' => 1, 'is_multiple' => false, 'vend_channel_id' => 0, 'gst_vat_rate' => 0,
            'payment_gateway_log_id' => $log->id, 'is_found_in_transaction' => true, 'settlement_status' => VendTransaction::SETTLEMENT_SETTLED,
        ]);
        $this->fakeOmise($this->omiseCharge()); // Omise: refunded_amount 0

        $this->postJson('/api/v1/payment-gateway-status/omise', $this->refundEvent())->assertOk();

        $this->assertSame(PaymentGatewayLog::STATUS_APPROVE, (int) $log->refresh()->status, 'log must stay APPROVE');
        $this->assertFalse((bool) $txn->refresh()->is_refunded, 'no money moved, so is_refunded must stay false');
    }

    public function test_off_mode_neither_checks_nor_queues(): void
    {
        config(['payment.webhook_verification' => 'off']);
        Bus::fake([VerifyPaymentWebhook::class, LogNofoundTxnIfStillMissing::class, CreateGatewayVendTransaction::class]);
        Http::fake();
        $log = $this->seedLog();

        $this->postJson('/api/v1/payment-gateway-status/omise', $this->approveEvent())->assertOk();

        $this->assertSame(PaymentGatewayLog::STATUS_APPROVE, (int) $log->refresh()->status);
        Bus::assertNotDispatched(VerifyPaymentWebhook::class);
        Http::assertNothingSent();
    }
}
