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
 * CardSettlementMatcher — a settlement line has no acquirer reference on our
 * side, so it must find its sale via terminal binding + amount (cents) + a
 * time window (terminal approval time runs 10–25 s AHEAD of our TRADE time).
 */
class CardSettlementMatchTest extends TestCase
{
    use RefreshDatabase;

    private const VEND_ID = 1320;

    private PaymentMethod $card;

    protected function setUp(): void
    {
        parent::setUp();
        $this->card = PaymentMethod::create(['code' => 1, 'name' => 'Card Terminal', 'is_active' => true]);
        CardTerminalBinding::create([
            'provider' => 'nets',
            'terminal_id' => '23082824',
            'vend_id' => self::VEND_ID,
        ]);
    }

    private function report(): CardSettlementReport
    {
        return CardSettlementReport::create([
            'provider' => 'nets',
            'original_filename' => 'test.csv',
            'status' => CardSettlementReport::STATUS_UPLOADED,
        ]);
    }

    private function row(CardSettlementReport $report, array $overrides = []): CardSettlementRow
    {
        static $n = 0;
        $n++;

        return CardSettlementRow::create(array_merge([
            'card_settlement_report_id' => $report->id,
            'row_no' => $n,
            'txn_type' => 'Purchase',
            'terminal_id' => '23082824',
            'transaction_date' => '2026-08-29',
            'transaction_time' => '22:30:58',
            'time_is_partial' => false,
            'amount_cents' => 240,
            'fingerprint' => sha1('row-'.$n.uniqid()),
            'status' => CardSettlementRow::STATUS_PENDING,
        ], $overrides));
    }

    private function txn(string $datetime, int $amount, array $overrides = []): VendTransaction
    {
        static $t = 0;
        $t++;

        return VendTransaction::create(array_merge([
            'order_id' => 'ORD-'.$t,
            'vend_id' => self::VEND_ID,
            'transaction_datetime' => $datetime,
            'amount' => $amount,
            'qty' => 1,
            'success_qty' => 1,
            'dispensed_qty' => 1,
            'vend_channel_id' => 0,
            'gst_vat_rate' => 0,
            'payment_method_id' => $this->card->id,
            'cashless_mfg' => 'Nets',
        ], $overrides));
    }

    public function test_full_time_row_matches_the_sale_reported_seconds_later()
    {
        $txn = $this->txn('2026-08-29 22:31:07', 240); // TRADE 9s after terminal approval
        $report = $this->report();
        $row = $this->row($report);

        app(CardSettlementMatcher::class)->match($report);

        $row->refresh();
        $this->assertSame(CardSettlementRow::STATUS_MATCHED, $row->status);
        $this->assertSame($txn->id, $row->matched_vend_transaction_id);
        $this->assertSame(9, $row->match_time_delta);
        $this->assertSame(1, $report->fresh()->matched_count);
        $this->assertSame(CardSettlementReport::STATUS_REVIEW, $report->fresh()->status);
    }

    public function test_amount_and_window_are_both_required()
    {
        $this->txn('2026-08-29 22:31:07', 250);      // wrong amount
        $this->txn('2026-08-29 23:30:58', 240);      // right amount, an hour late
        $report = $this->report();
        $row = $this->row($report);

        app(CardSettlementMatcher::class)->match($report);

        $this->assertSame(CardSettlementRow::STATUS_UNMATCHED, $row->fresh()->status);
    }

    public function test_two_rows_competing_for_two_sales_each_get_their_nearest()
    {
        $early = $this->txn('2026-08-29 22:31:07', 240);
        $late = $this->txn('2026-08-29 22:34:20', 240);
        $report = $this->report();
        $rowEarly = $this->row($report, ['transaction_time' => '22:30:58']);
        $rowLate = $this->row($report, ['transaction_time' => '22:34:05']);

        app(CardSettlementMatcher::class)->match($report);

        $this->assertSame($early->id, $rowEarly->fresh()->matched_vend_transaction_id);
        $this->assertSame($late->id, $rowLate->fresh()->matched_vend_transaction_id);
    }

    public function test_a_sale_already_claimed_by_an_earlier_report_is_not_claimed_again()
    {
        $txn = $this->txn('2026-08-29 22:31:07', 240);

        $first = $this->report();
        $this->row($first);
        app(CardSettlementMatcher::class)->match($first);

        $second = $this->report();
        $row = $this->row($second);
        app(CardSettlementMatcher::class)->match($second);

        $row->refresh();
        $this->assertSame(CardSettlementRow::STATUS_UNMATCHED, $row->status);
        $this->assertNull($row->matched_vend_transaction_id);
        $this->assertSame('No matching sale in window', $row->resolution_note);
        $this->assertSame($txn->id, $first->rows()->first()->matched_vend_transaction_id);
    }

    public function test_retained_credit_settlements_are_never_candidates()
    {
        // Not mass-assignable (only RetainedCreditSettlementRecorder writes it).
        $this->txn('2026-08-29 22:31:07', 240)
            ->forceFill(['is_retained_credit_settlement' => true])->save();
        $report = $this->report();
        $row = $this->row($report);

        app(CardSettlementMatcher::class)->match($report);

        $this->assertSame(CardSettlementRow::STATUS_UNMATCHED, $row->fresh()->status);
    }

    public function test_binding_effective_dates_are_respected()
    {
        CardTerminalBinding::query()->update(['bound_from' => '2026-08-30']); // starts after the row's date
        $this->txn('2026-08-29 22:31:07', 240);
        $report = $this->report();
        $row = $this->row($report);

        app(CardSettlementMatcher::class)->match($report);

        $row->refresh();
        $this->assertSame(CardSettlementRow::STATUS_UNMATCHED, $row->status);
        $this->assertSame('No terminal binding', $row->resolution_note);
    }

    public function test_partial_time_row_matches_within_the_hour()
    {
        // Excel-damaged file: the row only knows mm:ss = 30:58.
        $txn = $this->txn('2026-08-29 14:31:07', 240);
        $report = $this->report();
        $row = $this->row($report, ['transaction_time' => '00:30:58', 'time_is_partial' => true]);

        app(CardSettlementMatcher::class)->match($report);

        $row->refresh();
        $this->assertSame(CardSettlementRow::STATUS_MATCHED, $row->status);
        $this->assertSame($txn->id, $row->matched_vend_transaction_id);
    }

    public function test_partial_time_row_with_candidates_in_two_hours_is_ambiguous()
    {
        $this->txn('2026-08-29 14:31:07', 240);
        $this->txn('2026-08-29 19:31:12', 240);
        $report = $this->report();
        $row = $this->row($report, ['transaction_time' => '00:30:58', 'time_is_partial' => true]);

        app(CardSettlementMatcher::class)->match($report);

        $row->refresh();
        $this->assertSame(CardSettlementRow::STATUS_AMBIGUOUS, $row->status);
        $this->assertNull($row->matched_vend_transaction_id);
        $this->assertCount(2, $row->candidates_json);
    }

    public function test_reversal_line_pairs_with_the_purchase_it_undoes()
    {
        $txn = $this->txn('2026-08-29 22:31:07', 240);
        $report = $this->report();
        $purchase = $this->row($report, ['transaction_time' => '22:30:58']);
        $reversal = $this->row($report, [
            'transaction_time' => '22:31:40',
            'amount_cents' => -240,
            'is_reversal' => true,
        ]);

        app(CardSettlementMatcher::class)->match($report);

        $purchase->refresh();
        $reversal->refresh();
        $this->assertSame($txn->id, $purchase->matched_vend_transaction_id);
        $this->assertSame($reversal->id, $purchase->reversed_by_row_id);
        $this->assertSame(CardSettlementRow::STATUS_MATCHED, $reversal->status);
        $this->assertSame($purchase->id, $reversal->reverses_row_id);
        $this->assertNull($reversal->matched_vend_transaction_id); // the sale stays claimed by the purchase line only
        $this->assertSame(42, $reversal->match_time_delta);
        // "Sync N matched" counts purchases, not the paired reversal line.
        $this->assertSame(1, $report->fresh()->matched_count);
    }

    /**
     * Overlapping-cutover re-ingest: the purchase's DUPLICATE copy has the same
     * time and amount as the original but never a matched sale. The reversal
     * must pair with the original (MATCHED) line, whichever row the DB returns
     * first, or the sale is never marked refunded.
     */
    public function test_reversal_ignores_a_duplicate_copy_of_the_purchase()
    {
        $txn = $this->txn('2026-08-29 22:31:07', 240);
        $earlier = $this->report();
        $original = $this->row($earlier, ['transaction_time' => '22:30:58', 'status' => CardSettlementRow::STATUS_MATCHED, 'matched_vend_transaction_id' => $txn->id]);
        $earlier->update(['status' => CardSettlementReport::STATUS_SYNCED]);

        $report = $this->report();
        $copy = $this->row($report, ['transaction_time' => '22:30:58', 'status' => CardSettlementRow::STATUS_DUPLICATE]);
        $reversal = $this->row($report, ['transaction_time' => '22:31:40', 'amount_cents' => -240, 'is_reversal' => true]);

        app(CardSettlementMatcher::class)->match($report);

        $this->assertSame($original->id, $reversal->fresh()->reverses_row_id);
        $this->assertSame($reversal->id, $original->fresh()->reversed_by_row_id);
        $this->assertNull($copy->fresh()->reversed_by_row_id);
        $this->assertSame(CardSettlementRow::STATUS_DUPLICATE, $copy->fresh()->status);
    }

    /**
     * Deleting the report that holds one half of a cross-report pair must
     * release the other half: the purchase line forgets its reversal (else it
     * is skipped on re-upload and still marked refunded on Sync), and a
     * reversal line whose purchase vanished goes back to a query.
     */
    public function test_deleting_a_report_releases_cross_report_reversal_links()
    {
        $a = $this->report();
        $b = $this->report();
        $purchase = $this->row($a, ['status' => CardSettlementRow::STATUS_MATCHED]);
        $reversal = $this->row($b, ['transaction_time' => '22:31:40', 'amount_cents' => -240, 'is_reversal' => true, 'status' => CardSettlementRow::STATUS_MATCHED, 'reverses_row_id' => $purchase->id]);
        $purchase->update(['reversed_by_row_id' => $reversal->id]);

        $b->delete();
        $this->assertNull($purchase->fresh()->reversed_by_row_id);

        $c = $this->report();
        $reversal2 = $this->row($c, ['transaction_time' => '22:31:40', 'amount_cents' => -240, 'is_reversal' => true, 'status' => CardSettlementRow::STATUS_MATCHED, 'reverses_row_id' => $purchase->id]);
        $purchase->update(['reversed_by_row_id' => $reversal2->id]);

        $a->delete();
        $reversal2->refresh();
        $this->assertNull($reversal2->reverses_row_id);
        $this->assertSame(CardSettlementRow::STATUS_UNMATCHED, $reversal2->status);
    }

    public function test_reversal_with_no_prior_purchase_is_a_query()
    {
        $report = $this->report();
        $reversal = $this->row($report, [
            'transaction_time' => '22:31:40',
            'amount_cents' => -240,
            'is_reversal' => true,
        ]);

        app(CardSettlementMatcher::class)->match($report);

        $reversal->refresh();
        $this->assertSame(CardSettlementRow::STATUS_UNMATCHED, $reversal->status);
        $this->assertSame('No original purchase found for reversal', $reversal->resolution_note);
    }

    public function test_partial_time_reversal_pairs_within_the_hour()
    {
        $this->txn('2026-08-29 14:31:07', 240);
        $report = $this->report();
        $purchase = $this->row($report, ['transaction_time' => '00:30:58', 'time_is_partial' => true]);
        $reversal = $this->row($report, [
            'transaction_time' => '00:31:33',
            'time_is_partial' => true,
            'amount_cents' => -240,
            'is_reversal' => true,
        ]);

        app(CardSettlementMatcher::class)->match($report);

        $this->assertSame($reversal->id, $purchase->fresh()->reversed_by_row_id);
        $this->assertSame(35, $reversal->fresh()->match_time_delta);
    }

    public function test_non_purchase_rows_are_ignored()
    {
        $report = $this->report();
        $row = $this->row($report, ['txn_type' => 'Logon', 'amount_cents' => 0]);

        app(CardSettlementMatcher::class)->match($report);

        $this->assertSame(CardSettlementRow::STATUS_IGNORED, $row->fresh()->status);
    }
}
