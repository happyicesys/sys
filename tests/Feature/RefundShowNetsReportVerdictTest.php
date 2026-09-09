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
     * Both refund surfaces read the same two signals as Sales Transactions: WHY
     * a refund is recorded (auto_refund_source) and what the report itself shows
     * (na_in_nets). The second stands alone on a terminal that is not flagged
     * "Will refund" — a missing line is evidence, not proof (Brian, 2026-09-09).
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

        // Same report fact, terminal NOT flagged: the badge shows, the tick does not.
        $unflagged = $this->sale(['vend_channel_error_id' => $fault->id]);
        $unflagged->forceFill(['is_found_in_transaction' => true, 'card_settlement_state' => $notCaptured])->save();

        // The report captured this one: neither signal.
        $captured = $this->sale(['vend_channel_error_id' => $fault->id]);
        $captured->forceFill(['is_found_in_transaction' => true, 'card_settlement_state' => 'captured'])->save();

        $voidedDetail = $this->detailFor($this->ticket($voided));
        $this->assertTrue($voidedDetail['na_in_nets']);
        $this->assertTrue($voidedDetail['auto_refunded']);
        $this->assertSame(\App\Support\AutoRefundSource::SETTLEMENT_REPORT_NOT_CAPTURED, $voidedDetail['auto_refund_source']);
        $this->assertNotNull($voidedDetail['auto_refund_source_label']);

        $unflaggedDetail = $this->detailFor($this->ticket($unflagged));
        $this->assertTrue($unflaggedDetail['na_in_nets'], 'the report fact is stated');
        $this->assertFalse($unflaggedDetail['auto_refunded'], 'no refund is deduced from a missing line');
        $this->assertNull($unflaggedDetail['auto_refund_source']);

        $capturedDetail = $this->detailFor($this->ticket($captured));
        $this->assertFalse($capturedDetail['na_in_nets']);
    }

    /**
     * The Pay Method cell on the Refund Request list carries the flag of the
     * terminal fitted ON THE SALE'S OWN DATE, so a swapped terminal never
     * relabels an old claim.
     */
    public function test_the_list_row_carries_the_terminal_flag_effective_on_the_sale_date()
    {
        $nets = \App\Models\CardTerminal::create(['name' => 'Nets']);
        foreach ([['TID-YES', 1], ['TID-NO', 0]] as [$tid, $flag]) {
            \App\Models\CardTerminalUnit::create([
                'terminal_id' => $tid, 'card_terminal_id' => $nets->id, 'batch' => 'Nets #3 (50x)', 'is_will_auto_refund' => $flag,
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
        $this->assertFalse($rows[$onNo->id]['card_terminal_will_auto_refund'], 'the terminal fitted that day');
        $this->assertSame('TID-NO', $rows[$onNo->id]['card_terminal_unit_id']);
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
