<?php

namespace Tests\Feature;

use App\Http\Controllers\RefundController;
use App\Models\CardSettlementReport;
use App\Models\CardSettlementRow;
use App\Models\CardTerminalBinding;
use App\Models\PaymentMethod;
use App\Models\RefundTicket;
use App\Models\VendTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The refund ticket page carries the NETS settlement report's verdict on the
 * matched card sale (`nets_report`), because the report — not the tick — is
 * what ops must decide a card claim on (2026-09-08).
 */
class RefundShowNetsReportVerdictTest extends TestCase
{
    use RefreshDatabase;

    private const VEND_ID = 1320;

    private function detailFor(RefundTicket $ticket): array
    {
        $controller = app(RefundController::class);
        $method = (new \ReflectionClass($controller))->getMethod('toDetail');
        $method->setAccessible(true);

        return $method->invoke($controller, $ticket->fresh()->load(['items', 'logs', 'attachments']));
    }

    private function sale(array $overrides = []): VendTransaction
    {
        $card = PaymentMethod::firstOrCreate(['code' => 1], ['name' => 'Card Terminal', 'is_active' => true]);

        return VendTransaction::create(array_merge([
            'order_id' => 'ORD-SHOW-'.uniqid(),
            'vend_id' => self::VEND_ID,
            'transaction_datetime' => '2026-08-29 14:31:07',
            'amount' => 240,
            'qty' => 1,
            'success_qty' => 0,
            'dispensed_qty' => 0,
            'vend_channel_id' => 0,
            'gst_vat_rate' => 0,
            'payment_method_id' => $card->id,
            'cashless_mfg' => 'Nets',
        ], $overrides));
    }

    private function ticket(VendTransaction $txn): RefundTicket
    {
        return RefundTicket::create([
            'reference' => 'RF-'.uniqid(),
            'vend_code' => '2542',
            'vend_id' => self::VEND_ID,
            'vend_transaction_id' => $txn->id,
            'order_id' => $txn->order_id,
            'claimed_amount_cents' => 240,
            'status' => RefundTicket::STATUS_SUBMITTED,
        ]);
    }

    /**
     * Both refund surfaces read the same signals as Sales Transactions: WHY a
     * refund is recorded (auto_refund_source) and what the report itself shows
     * — na_in_nets (no line: nothing was charged, already refunded) and
     * matched_in_nets (a line and no reversal: the customer WAS charged, so a
     * valid claim still has to be paid). Brian, 2026-09-09.
     */
    public function test_the_refund_pages_carry_the_refund_source_and_the_report_fact()
    {
        $fault = \App\Models\VendChannelError::firstOrCreate(['code' => 7], ['desc' => 'Sensor error (7)']);
        $notCaptured = \App\Services\CardSettlement\CardSettlementRefundReconciler::STATE_NOT_CAPTURED;

        // Ticked by the report: no line, terminal voids by itself.
        $voided = $this->sale(['vend_channel_error_id' => $fault->id]);
        $voided->forceFill([
            'is_found_in_transaction' => true, 'card_settlement_state' => $notCaptured,
            'is_refunded' => true, 'auto_refund_source' => \App\Support\AutoRefundSource::SETTLEMENT_REPORT_NOT_CAPTURED,
        ])->save();

        // Same report fact, not reconciled yet: the badge is derived from the row.
        $unflagged = $this->sale(['vend_channel_error_id' => $fault->id]);
        $unflagged->forceFill(['is_found_in_transaction' => true, 'card_settlement_state' => $notCaptured])->save();

        // The report captured this one: "Matched in NETS", never "NA in NETS".
        $captured = $this->sale(['vend_channel_error_id' => $fault->id]);
        $captured->forceFill(['is_found_in_transaction' => true, 'card_settlement_state' => 'captured'])->save();

        $voidedDetail = $this->detailFor($this->ticket($voided));
        $this->assertTrue($voidedDetail['na_in_nets']);
        $this->assertFalse($voidedDetail['matched_in_nets']);
        $this->assertTrue($voidedDetail['auto_refunded']);
        $this->assertSame(\App\Support\AutoRefundSource::SETTLEMENT_REPORT_NOT_CAPTURED, $voidedDetail['auto_refund_source']);
        $this->assertNotNull($voidedDetail['auto_refund_source_label']);

        $unflaggedDetail = $this->detailFor($this->ticket($unflagged));
        $this->assertTrue($unflaggedDetail['na_in_nets'], 'the report fact is stated');
        $this->assertFalse($unflaggedDetail['auto_refunded'], 'the tick is the reconciler\'s to write');
        $this->assertNull($unflaggedDetail['auto_refund_source']);

        $capturedDetail = $this->detailFor($this->ticket($captured));
        $this->assertFalse($capturedDetail['na_in_nets']);
        $this->assertTrue($capturedDetail['matched_in_nets'], 'the report carries this sale: the customer was charged');
        $this->assertFalse($capturedDetail['auto_refunded']);
    }

    /**
     * Every verdict the report can reach reaches the screen. A claim it CANNOT
     * rule on used to look exactly like one nobody had checked — Brian, on an
     * `uncovered` row (Nets-Auresys, only part of its sales in the file):
     * "single purchase, error 7, and no badge?".
     */
    public function test_every_report_state_reaches_the_row_and_a_gateway_sale_carries_none()
    {
        $fault = \App\Models\VendChannelError::firstOrCreate(['code' => 7], ['desc' => 'Sensor error (7)']);
        $states = [
            \App\Services\CardSettlement\CardSettlementRefundReconciler::STATE_UNCOVERED,
            \App\Services\CardSettlement\CardSettlementRefundReconciler::STATE_UNBOUND,
            \App\Services\CardSettlement\CardSettlementRefundReconciler::STATE_CAPTURED,
            \App\Services\CardSettlement\CardSettlementRefundReconciler::STATE_REVERSED,
        ];

        foreach ($states as $state) {
            $sale = $this->sale(['vend_channel_error_id' => $fault->id]);
            $sale->forceFill(['is_found_in_transaction' => true, 'card_settlement_state' => $state])->save();
            $row = $this->detailFor($this->ticket($sale));
            $this->assertSame($state, $row['nets_report_state'], "card sale in state {$state}");
        }

        // A card sale whose day is not final yet: 'pending', never a bare null —
        // "the report has not ruled" is a different message from "no verdict".
        $noReport = $this->sale(['vend_channel_error_id' => $fault->id]);
        $noReport->forceFill(['is_found_in_transaction' => true, 'card_settlement_state' => null])->save();
        $this->assertSame('pending', $this->detailFor($this->ticket($noReport))['nets_report_state']);

        // A gateway (QR) sale has no NETS opinion at all, so it carries no badge.
        $gatewayMethod = \App\Models\PaymentMethod::create([
            'code' => 99, 'name' => 'Omise QR', 'is_active' => true, 'payment_gateway_id' => 1,
        ]);
        $qr = $this->sale(['vend_channel_error_id' => $fault->id, 'payment_method_id' => $gatewayMethod->id]);
        $qr->forceFill(['is_found_in_transaction' => true])->save();
        $this->assertNull($this->detailFor($this->ticket($qr))['nets_report_state']);
    }

    /**
     * The Pay Method cell on the Refund Request list carries the flag of the
     * terminal fitted ON THE SALE'S OWN DATE, so a swapped terminal never
     * relabels an old claim.
     */
    public function test_the_list_row_carries_the_terminal_flag_effective_on_the_sale_date()
    {
        $nets = \App\Models\CardTerminal::create(['name' => 'Nets']);
        // The older terminal is an AURESYS unit. The sale's own cashless_mfg says
        // "Nets" either way (the board cannot tell them apart), so the row has to
        // carry the supplier separately or the screen mislabels it.
        $auresys = \App\Models\CardTerminal::create(['name' => 'Nets-Auresys']);
        foreach ([['TID-YES', 1, $nets->id], ['TID-NO', 0, $auresys->id]] as [$tid, $flag, $companyId]) {
            \App\Models\CardTerminalUnit::create([
                'terminal_id' => $tid, 'card_terminal_id' => $companyId, 'batch' => 'Nets #3 (50x)', 'is_will_auto_refund' => $flag,
            ]);
        }
        CardTerminalBinding::create(['provider' => 'nets', 'terminal_id' => 'TID-NO', 'vend_id' => self::VEND_ID,
            'bound_from' => '2026-08-01', 'bound_until' => '2026-08-28']);
        CardTerminalBinding::create(['provider' => 'nets', 'terminal_id' => 'TID-YES', 'vend_id' => self::VEND_ID,
            'bound_from' => '2026-08-29']);

        $onYes = $this->ticket($this->sale(['transaction_datetime' => '2026-08-29 14:31:07']));
        $onNo = $this->ticket($this->sale(['transaction_datetime' => '2026-08-20 09:00:00']));

        $controller = app(RefundController::class);
        $method = (new \ReflectionClass($controller))->getMethod('buildRows');
        $method->setAccessible(true);
        $rows = $method->invoke($controller, RefundTicket::whereIn('id', [$onYes->id, $onNo->id])->get());

        $this->assertTrue($rows[$onYes->id]['card_terminal_will_auto_refund']);
        $this->assertSame('TID-YES', $rows[$onYes->id]['card_terminal_unit_id']);
        $this->assertSame('Nets #3 (50x)', $rows[$onYes->id]['card_terminal_batch']);
        $this->assertSame('Nets', $rows[$onYes->id]['card_terminal_company']);
        $this->assertFalse($rows[$onNo->id]['card_terminal_will_auto_refund'], 'the terminal fitted that day');
        $this->assertSame('TID-NO', $rows[$onNo->id]['card_terminal_unit_id']);
        $this->assertSame('Nets-Auresys', $rows[$onNo->id]['card_terminal_company'], 'the supplier, not the board\'s "Nets"');
    }

    public function test_card_sale_with_a_reversal_line_reads_reversed_with_a_report_link()
    {
        CardTerminalBinding::create(['provider' => 'nets', 'terminal_id' => '23082824', 'vend_id' => self::VEND_ID, 'bound_from' => '2026-08-01']);
        $report = CardSettlementReport::create(['provider' => 'nets', 'original_filename' => 'a.csv', 'cutover_date' => '2026-08-29', 'status' => CardSettlementReport::STATUS_SYNCED]);
        $txn = $this->sale();
        $purchase = CardSettlementRow::create([
            'card_settlement_report_id' => $report->id, 'row_no' => 1, 'txn_type' => 'Purchase', 'terminal_id' => '23082824',
            'transaction_date' => '2026-08-29', 'amount_cents' => 240, 'fingerprint' => sha1('show-1'),
            'status' => CardSettlementRow::STATUS_MATCHED, 'matched_vend_transaction_id' => $txn->id,
        ]);
        $rev = CardSettlementRow::create([
            'card_settlement_report_id' => $report->id, 'row_no' => 2, 'txn_type' => 'Purchase', 'terminal_id' => '23082824',
            'transaction_date' => '2026-08-29', 'amount_cents' => -240, 'is_reversal' => true, 'fingerprint' => sha1('show-2'),
            'status' => CardSettlementRow::STATUS_MATCHED, 'reverses_row_id' => $purchase->id,
        ]);
        $purchase->update(['reversed_by_row_id' => $rev->id]);

        $detail = $this->detailFor($this->ticket($txn));

        $this->assertSame('reversed', $detail['nets_report']['state']);
        $this->assertSame('Reversed', $detail['nets_report']['label']);
        $this->assertSame($report->id, $detail['nets_report']['report_id']);
    }

    public function test_card_sale_with_no_report_yet_says_so()
    {
        CardTerminalBinding::create(['provider' => 'nets', 'terminal_id' => '23082824', 'vend_id' => self::VEND_ID, 'bound_from' => '2026-08-01']);
        $detail = $this->detailFor($this->ticket($this->sale()));

        $this->assertSame('no_report', $detail['nets_report']['state']);
        $this->assertNull($detail['nets_report']['report_id']);
    }

    public function test_gateway_sale_carries_no_nets_verdict()
    {
        $detail = $this->detailFor($this->ticket($this->sale(['cashless_mfg' => null])));

        $this->assertNull($detail['nets_report']);
    }
}
