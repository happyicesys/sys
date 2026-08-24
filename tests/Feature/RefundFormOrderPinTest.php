<?php

namespace Tests\Feature;

use App\Models\PaymentGatewayLog;
use App\Models\Vend;
use App\Models\VendTransaction;
use App\Services\Refund\RefundMatchingService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 2026-08-23: the APK shows a refund QR on the dispensing dialog when an item
 * fails — /refund?machineID=<vend_code>&order_id=<ORDRID>. The form must open
 * on that purchase without the day / amount search, and must still resolve the
 * order from the gateway log when the TRADE upload has not landed yet.
 */
class RefundFormOrderPinTest extends TestCase
{
    use RefreshDatabase;

    private const VEND_CODE = '2031';

    private function makeVend(): Vend
    {
        // Bound to an active Site — the realistic field state (and ready for the
        // shelved machine-eligibility gate, see scratch patch 2026-08-24).
        $customer = \App\Models\Customer::create(['code' => 'C'.self::VEND_CODE, 'name' => 'Bench Site', 'is_active' => 1]);

        return Vend::create(['code' => self::VEND_CODE, 'name' => 'Bench 2031', 'is_active' => 1, 'customer_id' => $customer->id]);
    }

    public function test_order_id_resolves_to_the_vend_transaction()
    {
        $vend = $this->makeVend();
        VendTransaction::create([
            'order_id' => '2026082319531051679',
            'vend_id' => $vend->id,
            'transaction_datetime' => Carbon::parse('2026-08-23 19:53:10'),
            'amount' => 20,
            'qty' => 1,
            'success_qty' => 0,
            'dispensed_qty' => 0,
            'vend_channel_id' => 0,
            'gst_vat_rate' => 0,
            'settlement_status' => VendTransaction::SETTLEMENT_SETTLED,
        ]);

        $c = app(RefundMatchingService::class)->candidateByOrderId(self::VEND_CODE, '2026082319531051679');

        $this->assertNotNull($c);
        $this->assertSame('transaction', $c['source']);
        $this->assertSame(20, $c['amount_cents']);

        // unknown order / unknown machine -> null, the form falls back to the normal search
        $this->assertNull(app(RefundMatchingService::class)->candidateByOrderId(self::VEND_CODE, 'nope'));
        $this->assertNull(app(RefundMatchingService::class)->candidateByOrderId('0000', '2026082319531051679'));
    }

    /** Scan happens seconds after the failure: the TRADE may not be uploaded yet, the gateway log is. */
    public function test_order_id_falls_back_to_the_gateway_log_before_the_trade_lands()
    {
        $vend = $this->makeVend();
        PaymentGatewayLog::create([
            'order_id' => '2026082319531051680',
            'vend_id' => $vend->id,
            'vend_code' => self::VEND_CODE,
            'operator_payment_gateway_id' => 1,
            'amount' => 0.2,
            'method' => 'paynow',
            'status' => PaymentGatewayLog::STATUS_APPROVE,
            'is_dispensed' => false,
            'approved_at' => Carbon::parse('2026-08-23 19:53:10'),
            'txn_src' => 1,
        ]);

        $c = app(RefundMatchingService::class)->candidateByOrderId(self::VEND_CODE, '2026082319531051680');

        $this->assertNotNull($c);
        $this->assertSame('gateway', $c['source']);
        $this->assertTrue($c['items'][0]['had_channel_error']);
    }

    public function test_form_page_receives_the_prefilled_candidate_and_by_order_endpoint_answers()
    {
        $vend = $this->makeVend();
        VendTransaction::create([
            'order_id' => '2026082319531051681',
            'vend_id' => $vend->id,
            'transaction_datetime' => Carbon::parse('2026-08-23 19:55:00'),
            'amount' => 150,
            'qty' => 1,
            'success_qty' => 0,
            'dispensed_qty' => 0,
            'vend_channel_id' => 0,
            'gst_vat_rate' => 0,
            'settlement_status' => VendTransaction::SETTLEMENT_SETTLED,
        ]);

        $this->get('/refund?machineID='.self::VEND_CODE.'&order_id=2026082319531051681')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Refund/Form')
                ->where('machineFound', true)
                ->where('orderId', '2026082319531051681')
                ->where('prefilledCandidate.source', 'transaction')
                ->where('prefilledCandidate.amount_cents', 150));

        // junk order ids are ignored, not rejected
        $this->get('/refund?machineID='.self::VEND_CODE.'&order_id=<script>')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('orderId', '')->where('prefilledCandidate', null));

        $this->postJson('/refund/by-order', ['machineID' => self::VEND_CODE, 'order_id' => '2026082319531051681'])
            ->assertOk()
            ->assertJsonPath('found', true)
            ->assertJsonPath('candidate.amount_cents', 150);

        $this->postJson('/refund/by-order', ['machineID' => self::VEND_CODE, 'order_id' => '1111111111'])
            ->assertOk()
            ->assertJsonPath('found', false);
    }
}
