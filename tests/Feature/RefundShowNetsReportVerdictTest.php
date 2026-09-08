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
