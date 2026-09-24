<?php

namespace Tests\Feature;

use App\Models\CardSettlementReport;
use App\Models\CardSettlementRow;
use App\Models\CardTerminalBinding;
use App\Models\PaymentMethod;
use App\Models\VendTransaction;
use App\Services\CardSettlement\CardSettlementMatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Part 2 matcher hygiene: only Card Terminal sales are candidates (Grab Mart /
 * Free Vend rows used to steal same-amount lines), and a line nothing fits
 * inside the 60/300 s window is paired by a second, wider pass ONLY when the
 * pairing is unique both ways.
 */
class CardSettlementWideMatchTest extends TestCase
{
    use RefreshDatabase;

    private const VEND_ID = 1320;

    private PaymentMethod $card;

    private PaymentMethod $grab;

    protected function setUp(): void
    {
        parent::setUp();
        $this->card = PaymentMethod::create(['code' => PaymentMethod::CODE_CARD_TERMINAL, 'name' => 'Card Terminal', 'is_active' => true]);
        $this->grab = PaymentMethod::create(['code' => 209, 'name' => 'Grab Mart', 'is_active' => true]);
        CardTerminalBinding::create(['provider' => 'nets', 'terminal_id' => '23082824', 'vend_id' => self::VEND_ID]);
    }

    private function report(): CardSettlementReport
    {
        return CardSettlementReport::create(['provider' => 'nets', 'original_filename' => 'test.csv', 'status' => CardSettlementReport::STATUS_UPLOADED]);
    }

    private function row(CardSettlementReport $report, string $time = '22:30:58', int $amount = 240): CardSettlementRow
    {
        static $n = 0;
        $n++;

        return CardSettlementRow::create([
            'card_settlement_report_id' => $report->id, 'row_no' => $n, 'txn_type' => 'Purchase', 'terminal_id' => '23082824',
            'transaction_date' => '2026-08-29', 'transaction_time' => $time, 'time_is_partial' => false, 'amount_cents' => $amount,
            'fingerprint' => sha1('wide-'.$n.uniqid()), 'status' => CardSettlementRow::STATUS_PENDING,
        ]);
    }

    private function txn(string $datetime, int $amount = 240, ?PaymentMethod $method = null): VendTransaction
    {
        static $t = 0;
        $t++;

        return VendTransaction::create([
            'order_id' => 'ORD-W-'.$t, 'vend_id' => self::VEND_ID, 'transaction_datetime' => $datetime, 'amount' => $amount,
            'qty' => 1, 'success_qty' => 1, 'dispensed_qty' => 1, 'vend_channel_id' => 0, 'gst_vat_rate' => 0,
            'payment_method_id' => ($method ?? $this->card)->id, 'cashless_mfg' => 'Nets',
        ]);
    }

    public function test_a_grab_mart_sale_is_never_a_candidate_for_a_nets_line(): void
    {
        $grab = $this->txn('2026-08-29 22:31:07', 240, $this->grab);
        $report = $this->report();
        $row = $this->row($report);

        app(CardSettlementMatcher::class)->match($report);

        $row->refresh();
        $this->assertSame(CardSettlementRow::STATUS_UNMATCHED, $row->status);
        $this->assertSame(CardSettlementRow::NOTE_NO_SALE_IN_WINDOW, $row->resolution_note);
        $this->assertNull($row->matched_vend_transaction_id);
        $this->assertNull(CardSettlementRow::where('matched_vend_transaction_id', $grab->id)->first());
    }

    public function test_a_sale_just_outside_the_window_is_paired_by_the_wide_pass_when_unique(): void
    {
        $sale = $this->txn('2026-08-29 22:42:00'); // 11 min after the line: clock drift
        $report = $this->report();
        $row = $this->row($report);

        app(CardSettlementMatcher::class)->match($report);

        $row->refresh();
        $this->assertSame(CardSettlementRow::STATUS_MATCHED, $row->status);
        $this->assertSame($sale->id, $row->matched_vend_transaction_id);
        $this->assertSame(CardSettlementRow::NOTE_MATCHED_WIDE, $row->resolution_note);
        $this->assertSame(662, $row->match_time_delta);
    }

    public function test_the_wide_pass_never_guesses_between_two_lines_or_two_sales(): void
    {
        // Two lines, two same-amount sales, all outside the normal window: ambiguous → both stay queries.
        $this->txn('2026-08-29 22:42:00');
        $this->txn('2026-08-29 22:50:00');
        $report = $this->report();
        $a = $this->row($report, '22:30:58');
        $b = $this->row($report, '22:31:30');

        app(CardSettlementMatcher::class)->match($report);

        $this->assertSame(CardSettlementRow::STATUS_UNMATCHED, $a->fresh()->status);
        $this->assertSame(CardSettlementRow::STATUS_UNMATCHED, $b->fresh()->status);

        // One line, two sales that both fit the wide window: still a query.
        $report2 = $this->report();
        $c = $this->row($report2, '23:10:00', 300);
        $this->txn('2026-08-29 23:20:00', 300);
        $this->txn('2026-08-29 23:25:00', 300);

        app(CardSettlementMatcher::class)->match($report2);

        $this->assertSame(CardSettlementRow::STATUS_UNMATCHED, $c->fresh()->status);
        $this->assertSame(CardSettlementRow::NOTE_NO_SALE_IN_WINDOW, $c->fresh()->resolution_note);
    }

    public function test_the_wide_pass_yields_to_the_normal_window_first(): void
    {
        $near = $this->txn('2026-08-29 22:31:10');  // inside the window
        $far = $this->txn('2026-08-29 22:45:00');   // wide only
        $report = $this->report();
        $row = $this->row($report);

        app(CardSettlementMatcher::class)->match($report);

        $this->assertSame($near->id, $row->fresh()->matched_vend_transaction_id);
        $this->assertNull($row->fresh()->resolution_note);
        $this->assertNull(CardSettlementRow::where('matched_vend_transaction_id', $far->id)->first());
    }
}
