<?php

namespace Tests\Feature;

use App\Models\CardSettlementReport;
use App\Models\CardSettlementRow;
use App\Models\CardTerminalBinding;
use App\Models\PaymentMethod;
use App\Models\VendTransaction;
use App\Services\CardSettlement\CardSettlementMatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The matcher tests a sale on two anchors — the frame's own TIME
 * (transaction_datetime, the board's clock) and the moment mark1 received
 * it (received_at, our clock) — and takes whichever fits closer to the
 * expected lag. Live shapes from 2026-09-15: 2760's board runs 314 s slow
 * (only receive time fits); 2502 flushed three sales in one burst 6 min
 * after the taps (only frame time fits).
 */
class CardSettlementDualAnchorMatchTest extends TestCase
{
    use RefreshDatabase;

    private const VEND_ID = 1320;

    private PaymentMethod $card;

    protected function setUp(): void
    {
        parent::setUp();
        $this->card = PaymentMethod::create(['code' => PaymentMethod::CODE_CARD_TERMINAL, 'name' => 'Card Terminal', 'is_active' => true]);
        CardTerminalBinding::create(['provider' => 'nets', 'terminal_id' => '23104091', 'vend_id' => self::VEND_ID]);
    }

    private function report(): CardSettlementReport
    {
        return CardSettlementReport::create(['provider' => 'nets', 'original_filename' => 'test.csv', 'status' => CardSettlementReport::STATUS_UPLOADED]);
    }

    private function row(CardSettlementReport $report, string $time, int $amount = 200, string $date = '2026-09-12'): CardSettlementRow
    {
        static $n = 0;
        $n++;

        return CardSettlementRow::create([
            'card_settlement_report_id' => $report->id, 'row_no' => $n, 'txn_type' => 'Purchase', 'terminal_id' => '23104091',
            'transaction_date' => $date, 'transaction_time' => $time, 'time_is_partial' => false, 'amount_cents' => $amount,
            'fingerprint' => sha1('dual-'.$n.uniqid()), 'status' => CardSettlementRow::STATUS_PENDING,
        ]);
    }

    private function sale(string $frameTime, ?string $receivedAt, int $amount = 200): VendTransaction
    {
        static $t = 0;
        $t++;

        return VendTransaction::create([
            'order_id' => 'ORD-D-'.$t, 'vend_id' => self::VEND_ID, 'transaction_datetime' => $frameTime, 'received_at' => $receivedAt,
            'amount' => $amount, 'qty' => 1, 'success_qty' => 1, 'dispensed_qty' => 1, 'vend_channel_id' => 0, 'gst_vat_rate' => 0,
            'payment_method_id' => $this->card->id, 'cashless_mfg' => 'Nets', 'is_found_in_transaction' => true,
        ]);
    }

    public function test_a_slow_board_clock_is_matched_on_receive_time(): void
    {
        // 2760 shape: line 12:15:24, board stamps 12:10:21 (−303 s), we received it at 12:15:36.
        $sale = $this->sale('2026-09-12 12:10:21', '2026-09-12 12:15:36');
        $report = $this->report();
        $row = $this->row($report, '12:15:24');

        app(CardSettlementMatcher::class)->match($report);

        $row->refresh();
        $this->assertSame(CardSettlementRow::STATUS_MATCHED, $row->status);
        $this->assertSame($sale->id, $row->matched_vend_transaction_id);
        $this->assertSame(12, $row->match_time_delta);
        $this->assertSame(CardSettlementRow::NOTE_MATCHED_RECEIVED, $row->resolution_note);
    }

    public function test_a_burst_flushed_outbox_is_matched_on_frame_time(): void
    {
        // 2502 shape: tap 20:53:07, frame 20:53:35, but received with two others at 20:59:46.
        $sale = $this->sale('2026-09-12 20:53:35', '2026-09-12 20:59:46');
        $report = $this->report();
        $row = $this->row($report, '20:53:07');

        app(CardSettlementMatcher::class)->match($report);

        $row->refresh();
        $this->assertSame(CardSettlementRow::STATUS_MATCHED, $row->status);
        $this->assertSame($sale->id, $row->matched_vend_transaction_id);
        $this->assertSame(28, $row->match_time_delta);
        $this->assertNull($row->resolution_note, 'frame anchor is the ordinary match — no note');
    }

    public function test_burst_sales_pair_with_their_own_lines_not_first_come(): void
    {
        $first = $this->sale('2026-09-12 20:53:35', '2026-09-12 20:59:46');
        $second = $this->sale('2026-09-12 20:54:38', '2026-09-12 20:59:46');
        $report = $this->report();
        $a = $this->row($report, '20:53:07');
        $b = $this->row($report, '20:54:09');

        app(CardSettlementMatcher::class)->match($report);

        $this->assertSame($first->id, $a->fresh()->matched_vend_transaction_id);
        $this->assertSame($second->id, $b->fresh()->matched_vend_transaction_id);
    }

    public function test_when_both_anchors_fit_the_one_closer_to_the_expected_lag_wins(): void
    {
        // Frame +15 s (textbook), receive +42 s: frame wins, no note.
        $this->sale('2026-09-12 12:15:39', '2026-09-12 12:16:06');
        $report = $this->report();
        $row = $this->row($report, '12:15:24');

        app(CardSettlementMatcher::class)->match($report);

        $this->assertSame(15, $row->fresh()->match_time_delta);
        $this->assertNull($row->fresh()->resolution_note);

        // Frame −50 s (clock a little behind), receive +14 s: receive wins.
        $report2 = $this->report();
        $row2 = $this->row($report2, '13:00:00', 300);
        $this->sale('2026-09-12 12:59:10', '2026-09-12 13:00:14', 300);

        app(CardSettlementMatcher::class)->match($report2);

        $this->assertSame(14, $row2->fresh()->match_time_delta);
        $this->assertSame(CardSettlementRow::NOTE_MATCHED_RECEIVED, $row2->fresh()->resolution_note);
    }

    public function test_the_receive_anchor_is_ignored_when_it_trails_the_frame_by_more_than_the_cap(): void
    {
        // A sale replayed 36 h after it happened arrives at 12:15:36 — it must
        // not claim the line NETS stamped at 12:15:24 for someone else's sale.
        $replayed = $this->sale('2026-09-11 00:15:00', '2026-09-12 12:15:36');
        $report = $this->report();
        $row = $this->row($report, '12:15:24');

        app(CardSettlementMatcher::class)->match($report);

        $row->refresh();
        $this->assertSame(CardSettlementRow::STATUS_UNMATCHED, $row->status);
        $this->assertNull(CardSettlementRow::where('matched_vend_transaction_id', $replayed->id)->first());

        // Two hours behind is inside the cap: the receive anchor still counts.
        $report2 = $this->report();
        $row2 = $this->row($report2, '15:00:00', 300);
        $late = $this->sale('2026-09-12 13:00:00', '2026-09-12 15:00:12', 300);

        app(CardSettlementMatcher::class)->match($report2);

        $this->assertSame($late->id, $row2->fresh()->matched_vend_transaction_id);
    }

    public function test_a_legacy_server_time_row_still_offers_the_boards_frame_time_from_the_raw_json(): void
    {
        // Before 09-09 transaction_datetime was the server time. 2502 on
        // 2026-09-03: three sales received together at 20:59:46, the board's
        // TIME (20:53:35) only in the raw frame. Only that anchor fits.
        $legacy = $this->sale('2026-09-12 20:59:46', null, 460);
        DB::table('vend_transactions')->where('id', $legacy->id)->update([
            'created_at' => '2026-09-12 20:59:46',
            'vend_transaction_json' => json_encode(['Type' => 'TRADE', 'TIME' => '2026-09-12 20:53:35', 'ORDRID' => '2026091220533500245']),
        ]);
        $report = $this->report();
        $row = $this->row($report, '20:53:07', 460);

        app(CardSettlementMatcher::class)->match($report);

        $row->refresh();
        $this->assertSame($legacy->id, $row->matched_vend_transaction_id);
        $this->assertSame(28, $row->match_time_delta);
        $this->assertNull($row->resolution_note, 'the board stamp is the frame anchor');

        // A dead-RTC frame (years off) is ignored; a row WITH received_at never reads the JSON.
        $dead = $this->sale('2026-09-12 21:30:00', null, 300);
        DB::table('vend_transactions')->where('id', $dead->id)->update([
            'created_at' => '2026-09-12 21:30:00',
            'vend_transaction_json' => json_encode(['TIME' => '2008-11-08 16:50:11']),
        ]);
        $report2 = $this->report();
        $row2 = $this->row($report2, '21:20:00', 300); // 10 min before: no anchor fits the normal window

        app(CardSettlementMatcher::class)->match($report2);

        $this->assertSame(CardSettlementRow::NOTE_MATCHED_WIDE, $row2->fresh()->resolution_note, 'only the wide pass on server time, not a bogus 2008 anchor');
    }

    public function test_rows_written_before_received_at_existed_fall_back_to_created_at_only_when_the_trade_created_them(): void
    {
        // Legacy live row: no received_at, created_at is the receive moment → matches.
        $legacy = $this->sale('2026-09-12 12:10:21', null);
        DB::table('vend_transactions')->where('id', $legacy->id)->update(['created_at' => '2026-09-12 12:15:36']);
        $report = $this->report();
        $row = $this->row($report, '12:15:24');

        app(CardSettlementMatcher::class)->match($report);

        $this->assertSame($legacy->id, $row->fresh()->matched_vend_transaction_id);
        $this->assertSame(CardSettlementRow::NOTE_MATCHED_RECEIVED, $row->fresh()->resolution_note);

        // An adopted orphan's created_at is Sync time, not a receipt → no
        // fallback: the receive anchor (delta 12) is NOT used. The sale is
        // still paired — by the wide pass on its frame time (−360 s), which
        // is what the note and delta must say.
        $other = $this->report();
        $orphanLine = $this->row($other, '16:00:00', 300);
        $adopted = $this->sale('2026-09-12 15:54:00', null, 300);
        DB::table('vend_transactions')->where('id', $adopted->id)->update([
            'created_at' => '2026-09-12 16:00:12', 'card_settlement_row_id' => $orphanLine->id,
        ]);
        $orphanLine->delete(); // leaves the sale unclaimed, so it IS a candidate
        $report3 = $this->report();
        $row3 = $this->row($report3, '16:00:00', 300);

        app(CardSettlementMatcher::class)->match($report3);

        $row3->refresh();
        $this->assertSame(CardSettlementRow::NOTE_MATCHED_WIDE, $row3->resolution_note);
        $this->assertSame(-360, $row3->match_time_delta);
    }
}
