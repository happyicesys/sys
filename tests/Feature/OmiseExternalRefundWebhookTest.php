<?php

namespace Tests\Feature;

use App\Models\PaymentGatewayLog;
use App\Models\VendTransaction;
use App\Support\AutoRefundSource;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regression cover for chrg_68n67o8khxc8go7inac (2026-08-12): Omise accepted a
 * WeChat dispute as a refund and emitted `refund.create`, but the refund object
 * carried NO metadata.order_id (only refunds WE make via the API are stamped),
 * so the webhook handler could not map it and mark1 kept the charge as paid —
 * Sales Transactions / Refund Request both said "not refunded".
 *
 * The handler must resolve such refunds through data.charge (= our ref_id),
 * flip the log to REFUND, and bring the linked vend_transaction in line
 * (is_refunded, auto_refund_source = omise_external, settlement REFUNDED).
 * Dispute events must be acknowledged, not 500'd.
 */
class OmiseExternalRefundWebhookTest extends TestCase
{
    use RefreshDatabase;

    private const ORDER = '26081115390302129';

    private const CHARGE = 'chrg_68n67o8khxc8go7inac';

    protected function setUp(): void
    {
        parent::setUp();
        // Unified transactions on, every machine (prod state since 2026-05-26).
        config(['app.gateway_unified_txn_enabled' => true, 'app.gateway_unified_txn_vend_codes' => '']);
    }

    private function seedPaidCharge(): array
    {
        $log = PaymentGatewayLog::create([
            'order_id' => self::ORDER,
            'ref_id' => self::CHARGE,
            'vend_id' => 166,
            'vend_code' => '2129',
            'operator_payment_gateway_id' => 1,
            'payment_gateway_id' => 1,
            'amount' => 3.5,
            'method' => 'wechat_pay_mpm',
            'status' => PaymentGatewayLog::STATUS_APPROVE,
            'is_dispensed' => true,
            'approved_at' => Carbon::parse('2026-08-11 15:39:25'),
            'txn_src' => 1,
        ]);

        $txn = VendTransaction::create([
            'order_id' => self::ORDER,
            'vend_id' => 166,
            'transaction_datetime' => Carbon::parse('2026-08-11 15:39:25'),
            'amount' => 350,
            'qty' => 1,
            'success_qty' => 0,
            'dispensed_qty' => 0,
            'is_multiple' => false,
            'vend_channel_id' => 0,
            'gst_vat_rate' => 0,
            'payment_gateway_log_id' => $log->id,
            'is_found_in_transaction' => false,
            'settlement_status' => VendTransaction::SETTLEMENT_SETTLED,
        ]);

        return [$log, $txn];
    }

    /** The exact shape Omise sends for a dashboard / dispute refund: empty metadata, charge id in data.charge. */
    private function externalRefundEvent(): array
    {
        return [
            'object' => 'event',
            'id' => 'evnt_test_68n67o8',
            'key' => 'refund.create',
            'data' => [
                'object' => 'refund',
                'id' => 'rfnd_test_68n67o8',
                'amount' => 350,
                'currency' => 'SGD',
                'charge' => self::CHARGE,
                'status' => 'closed',
                'metadata' => [],
                'created_at' => '2026-08-12T04:53:15Z',
            ],
        ];
    }

    public function test_external_refund_without_order_metadata_is_mapped_by_charge_and_recorded(): void
    {
        [$log, $txn] = $this->seedPaidCharge();

        $this->postJson('/api/v1/payment-gateway-status/omise', $this->externalRefundEvent())->assertOk();

        $log->refresh();
        $txn->refresh();

        $this->assertSame(PaymentGatewayLog::STATUS_REFUND, (int) $log->status, 'log must flip to REFUND');
        $this->assertSame(self::CHARGE, $log->ref_id, 'ref_id must stay the charge id (not the rfnd_ id)');
        $this->assertSame('refund.create', $log->response['key'] ?? null, 'the webhook payload is stored');

        $this->assertTrue((bool) $txn->is_refunded, 'vend_transaction must read as refunded');
        $this->assertSame(AutoRefundSource::OMISE_EXTERNAL, $txn->auto_refund_source);
        $this->assertSame(VendTransaction::SETTLEMENT_REFUNDED, (int) $txn->settlement_status);
    }

    public function test_external_refund_is_idempotent_on_replay(): void
    {
        [$log, $txn] = $this->seedPaidCharge();

        $this->postJson('/api/v1/payment-gateway-status/omise', $this->externalRefundEvent())->assertOk();
        $this->postJson('/api/v1/payment-gateway-status/omise', $this->externalRefundEvent())->assertOk();

        $this->assertSame(PaymentGatewayLog::STATUS_REFUND, (int) $log->refresh()->status);
        $this->assertTrue((bool) $txn->refresh()->is_refunded);
        $this->assertSame(1, PaymentGatewayLog::where('order_id', self::ORDER)->count(), 'no second log row is created');
    }

    public function test_refund_for_unknown_charge_is_ignored_without_error(): void
    {
        $event = $this->externalRefundEvent();
        $event['data']['charge'] = 'chrg_not_ours';

        $this->postJson('/api/v1/payment-gateway-status/omise', $event)->assertOk();
        $this->assertSame(0, PaymentGatewayLog::count());
    }

    public function test_dispute_events_are_acknowledged_not_500(): void
    {
        $this->seedPaidCharge();

        $this->postJson('/api/v1/payment-gateway-status/omise', [
            'object' => 'event',
            'key' => 'dispute.create',
            'data' => ['object' => 'dispute', 'id' => 'dspt_test', 'charge' => self::CHARGE, 'status' => 'open'],
        ])->assertOk();

        $this->assertSame(PaymentGatewayLog::STATUS_APPROVE, (int) PaymentGatewayLog::first()->status, 'a dispute alone moves no money');
    }
}
