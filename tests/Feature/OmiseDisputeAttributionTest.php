<?php

namespace Tests\Feature;

use App\Models\PaymentGatewayLog;
use App\Models\VendTransaction;
use App\Support\AutoRefundSource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A chargeback the CUSTOMER raised and a refund an admin made on the Omise
 * dashboard both arrive as "a refund we did not initiate", and until
 * 2026-09-09 both were recorded as omise_external — so the customer's own
 * dispute read as our decision.
 *
 * Omise announces the chargeback first (dispute.create / .accept / .close),
 * which mark1 acknowledged and threw away. It now stamps the charge, and the
 * refund that follows is attributed to the customer.
 */
class OmiseDisputeAttributionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // The recorder only writes the sale's refund columns for machines on the
        // unified-transactions rail (GatewayUnifiedTransaction), which is what
        // every live Omise machine is on.
        config(['app.gateway_unified_txn_enabled' => true, 'app.gateway_unified_txn_vend_codes' => '']);
    }

    private function log(string $orderId, string $chargeId): PaymentGatewayLog
    {
        return PaymentGatewayLog::forceCreate([
            'order_id' => $orderId, 'ref_id' => $chargeId, 'vend_code' => '2129', 'vend_id' => 1,
            'operator_payment_gateway_id' => 1, 'amount' => 9.0, 'status' => PaymentGatewayLog::STATUS_APPROVE,
            'approved_at' => now()->subHour(), 'created_at' => now()->subHour(),
        ]);
    }

    private function webhook(array $payload): \Illuminate\Testing\TestResponse
    {
        return $this->postJson('/api/v1/payment-gateway-status/omise', $payload);
    }

    public function test_a_dispute_event_stamps_the_charge_and_is_still_acknowledged(): void
    {
        $log = $this->log('ORD-DISPUTE-1', 'chrg_dispute_1');

        $response = $this->webhook([
            'key' => 'dispute.create',
            'data' => ['object' => 'dispute', 'id' => 'dspt_1', 'charge' => 'chrg_dispute_1', 'status' => 'open'],
        ]);

        $response->assertSuccessful();
        $this->assertNotNull($log->fresh()->disputed_at, 'the chargeback must be recorded');
        $this->assertSame(PaymentGatewayLog::STATUS_APPROVE, (int) $log->fresh()->status, 'no money moved on the dispute event itself');

        // The first stamp wins: dispute.accept / .close follow for the same dispute.
        $first = $log->fresh()->disputed_at;
        $this->travel(2)->minutes();
        $this->webhook(['key' => 'dispute.accept', 'data' => ['object' => 'dispute', 'id' => 'dspt_1', 'charge' => 'chrg_dispute_1']])->assertSuccessful();
        $this->assertEquals($first, $log->fresh()->disputed_at);
    }

    public function test_a_dispute_event_for_an_unknown_charge_is_acknowledged_without_error(): void
    {
        // Another instance / sandbox / pre-mark1: a 500 would make Omise retry for days.
        $this->webhook(['key' => 'dispute.create', 'data' => ['object' => 'dispute', 'id' => 'dspt_x', 'charge' => 'chrg_not_ours']])
            ->assertSuccessful();
    }

    public function test_a_refund_on_a_disputed_charge_is_attributed_to_the_customer(): void
    {
        $log = $this->log('ORD-DISPUTE-2', 'chrg_dispute_2');
        $txn = VendTransaction::create([
            'order_id' => 'ORD-DISPUTE-2', 'vend_id' => 1, 'transaction_datetime' => now()->subHour(), 'amount' => 900,
            'qty' => 1, 'vend_channel_id' => 0, 'gst_vat_rate' => 0, 'payment_gateway_log_id' => $log->id,
        ]);

        $this->webhook(['key' => 'dispute.create', 'data' => ['object' => 'dispute', 'id' => 'dspt_2', 'charge' => 'chrg_dispute_2']])->assertSuccessful();
        $this->webhook(['key' => 'refund.create', 'data' => ['object' => 'refund', 'id' => 'rfnd_2', 'charge' => 'chrg_dispute_2', 'amount' => 900]])->assertSuccessful();

        $this->assertSame(PaymentGatewayLog::STATUS_REFUND, (int) $log->fresh()->status);
        $this->assertSame(AutoRefundSource::OMISE_DISPUTE, $txn->fresh()->auto_refund_source);
        $this->assertSame(AutoRefundSource::TRIGGER_CUSTOMER, AutoRefundSource::trigger($txn->fresh()->auto_refund_source));
    }

    public function test_a_refund_with_no_dispute_stays_an_admin_refund(): void
    {
        $log = $this->log('ORD-DASHBOARD', 'chrg_dashboard');
        $txn = VendTransaction::create([
            'order_id' => 'ORD-DASHBOARD', 'vend_id' => 1, 'transaction_datetime' => now()->subHour(), 'amount' => 900,
            'qty' => 1, 'vend_channel_id' => 0, 'gst_vat_rate' => 0, 'payment_gateway_log_id' => $log->id,
        ]);

        $this->webhook(['key' => 'refund.create', 'data' => ['object' => 'refund', 'id' => 'rfnd_3', 'charge' => 'chrg_dashboard', 'amount' => 900]])->assertSuccessful();

        $this->assertSame(AutoRefundSource::OMISE_EXTERNAL, $txn->fresh()->auto_refund_source);
        $this->assertSame(AutoRefundSource::TRIGGER_ADMIN, AutoRefundSource::trigger($txn->fresh()->auto_refund_source));
    }
}
