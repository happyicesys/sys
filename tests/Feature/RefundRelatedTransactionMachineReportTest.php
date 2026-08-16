<?php

namespace Tests\Feature;

use App\Http\Controllers\RefundController;
use App\Models\PaymentGatewayLog;
use App\Models\RefundTicket;
use App\Models\VendTransaction;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Refund "Related transactions": a gateway row's dispensed_qty is a PLACEHOLDER
 * until the machine's TRADE arrives.
 *
 * QR / PayNow sales are pre-created at paid-time by GatewayVendTransactionService
 * with dispensed_qty = 0 and no channel error, and only filled with machine ground
 * truth if a TRADE later lands (VendTransactionService::applyTradeToPreCreatedRow).
 * Measured on production 2026-08-09..16, ~80% of gateway sales never receive that
 * TRADE — so the refund screen was showing Ops "Dispensed 0/3, Channel error:
 * none" for sales the machine simply never reported, which reads as "the customer
 * got nothing from a healthy machine" and drives wrong refund decisions.
 *
 * The payload must therefore carry machine_reported so the UI can render "unknown"
 * instead of a zero. Cash/card rows only exist because a TRADE created them
 * (is_found_in_transaction defaults to 1), so they must stay unaffected.
 */
class RefundRelatedTransactionMachineReportTest extends TestCase
{
    use RefreshDatabase;

    // The payload builder resolves the transaction by order_id and reads only
    // optional relations off it, so the test needs no vend/customer/operator
    // rows — vend_transactions carries no foreign keys. Keeping it schema-light
    // also keeps the test independent of the vends table's shape.
    private const VEND_ID = 1320;

    private const VEND_CODE = '2870';

    /** Call the controller's protected payload builder for a ticket on this order. */
    private function relatedFor(string $orderId): array
    {
        $ticket = new RefundTicket(['order_id' => $orderId, 'vend_code' => self::VEND_CODE]);

        $controller = app(RefundController::class);
        $method = (new \ReflectionClass($controller))->getMethod('relatedTransactions');
        $method->setAccessible(true);

        return $method->invoke($controller, $ticket);
    }

    /** A QR sale the machine never reported back: dispense count is UNKNOWN, not 0. */
    public function test_gateway_row_without_a_trade_is_flagged_as_not_machine_reported()
    {
        $log = PaymentGatewayLog::create([
            'order_id' => '26081615460702870',
            'vend_id' => self::VEND_ID,
            'vend_code' => self::VEND_CODE,
            'operator_payment_gateway_id' => 1,
            'amount' => 7.8,
            'method' => 'paynow',
            'status' => PaymentGatewayLog::STATUS_APPROVE,
            // The machine asked to dispense and we confirmed the command
            // (GetPurchaseConfirm) — an ack, not proof the product dropped.
            'is_dispensed' => true,
            'approved_at' => Carbon::parse('2026-08-16 15:46:37'),
            'txn_src' => 1,
        ]);

        VendTransaction::create([
            'order_id' => '26081615460702870',
            'vend_id' => self::VEND_ID,
            'transaction_datetime' => Carbon::parse('2026-08-16 15:46:37'),
            'amount' => 780,
            'qty' => 3,
            'success_qty' => 0,
            'dispensed_qty' => 0,      // placeholder — no TRADE ever arrived
            'is_multiple' => true,
            'vend_channel_id' => 0,
            'gst_vat_rate' => 0,
            'payment_gateway_log_id' => $log->id,
            'is_found_in_transaction' => false,
            'settlement_status' => VendTransaction::SETTLEMENT_SETTLED,
        ]);

        $rows = $this->relatedFor('26081615460702870');

        $this->assertCount(1, $rows);
        $this->assertFalse($rows[0]['machine_reported'], 'A pre-created gateway row with no TRADE must be flagged as not machine-reported.');
        $this->assertTrue($rows[0]['gateway_dispense_ack'], 'The gateway dispense ack must be surfaced as its own, weaker signal.');
        // The raw placeholder stays in the payload; it is the UI that must stop
        // rendering it as a count. Pinning it keeps the two facts side by side.
        $this->assertSame(0, $rows[0]['dispensed_qty']);
        $this->assertSame(3, $rows[0]['qty']);
    }

    /** The same gateway sale once the TRADE lands: a real, machine-reported count. */
    public function test_gateway_row_with_a_trade_is_machine_reported()
    {
        $log = PaymentGatewayLog::create([
            'order_id' => '26081615460702871',
            'vend_id' => self::VEND_ID,
            'vend_code' => self::VEND_CODE,
            'operator_payment_gateway_id' => 1,
            'amount' => 3.0,
            'method' => 'paynow',
            'status' => PaymentGatewayLog::STATUS_APPROVE,
            'is_dispensed' => true,
            'approved_at' => Carbon::parse('2026-08-16 15:50:00'),
            'txn_src' => 1,
        ]);

        VendTransaction::create([
            'order_id' => '26081615460702871',
            'vend_id' => self::VEND_ID,
            'transaction_datetime' => Carbon::parse('2026-08-16 15:50:00'),
            'amount' => 300,
            'qty' => 1,
            'success_qty' => 1,
            'dispensed_qty' => 1,
            'vend_channel_id' => 0,
            'gst_vat_rate' => 0,
            'payment_gateway_log_id' => $log->id,
            'is_found_in_transaction' => true,
            'settlement_status' => VendTransaction::SETTLEMENT_SETTLED,
        ]);

        $rows = $this->relatedFor('26081615460702871');

        $this->assertCount(1, $rows);
        $this->assertTrue($rows[0]['machine_reported']);
        $this->assertSame(1, $rows[0]['dispensed_qty']);
    }

    /** Card-terminal / cash rows exist only because a TRADE created them. */
    public function test_card_terminal_row_is_machine_reported_with_no_gateway_ack()
    {
        VendTransaction::create([
            'order_id' => '2026081616064518617',
            'vend_id' => self::VEND_ID,
            'transaction_datetime' => Carbon::parse('2026-08-16 16:07:59'),
            'amount' => 1640,
            'qty' => 5,
            'success_qty' => 4,
            'dispensed_qty' => 5,
            'is_multiple' => true,
            'vend_channel_id' => 0,
            'gst_vat_rate' => 0,
            'settlement_status' => VendTransaction::SETTLEMENT_SETTLED,
            // is_found_in_transaction intentionally omitted: the column defaults
            // to 1, which is exactly how the TRADE path writes these rows.
        ]);

        $rows = $this->relatedFor('2026081616064518617');

        $this->assertCount(1, $rows);
        $this->assertTrue($rows[0]['machine_reported'], 'A TRADE-created row must never be flagged as unreported.');
        $this->assertNull($rows[0]['gateway_dispense_ack'], 'Non-gateway rows have no dispense ack to show.');
        $this->assertSame(5, $rows[0]['dispensed_qty']);
    }
}
