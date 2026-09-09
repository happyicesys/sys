<?php

namespace Tests\Feature;

use App\Models\CardSettlementReport;
use App\Models\CardSettlementRow;
use App\Models\CardTerminal;
use App\Models\CardTerminalBinding;
use App\Models\CardTerminalUnit;
use App\Models\PaymentMethod;
use App\Models\RefundTicket;
use App\Models\VendChannelError;
use App\Models\VendTransaction;
use App\Services\CardSettlement\CardSettlementRefundReconciler;
use App\Support\AutoRefundSource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Part 2, direction 2 — TRADE, no NETS line. Once the day is final every card
 * sale gets a persisted card_settlement_state; a FAILED single-item sale on a
 * terminal flagged "will auto refund" with no line is ticked "NA in NETS";
 * everything else (dispensed, multiple, unflagged terminal, partial-coverage
 * company, unbound machine) is never ticked from the absence of a line.
 */
class CardSettlementStateAndVoidTickTest extends TestCase
{
    use RefreshDatabase;

    private const VEND = 1320;

    private const TID = '23100701';

    private const DAY = '2026-08-29';

    private PaymentMethod $card;

    private CardTerminal $nets;

    private CardTerminal $auresys;

    private VendChannelError $ok;

    private VendChannelError $fault;

    protected function setUp(): void
    {
        parent::setUp();
        $this->card = PaymentMethod::firstOrCreate(['code' => PaymentMethod::CODE_CARD_TERMINAL], ['name' => 'Card Terminal', 'is_active' => true]);
        $this->nets = CardTerminal::create(['name' => 'Nets']);
        $this->auresys = CardTerminal::create(['name' => 'Nets-Auresys']);
        $this->ok = VendChannelError::firstOrCreate(['code' => 0], ['desc' => 'No Malfunction (0)']);
        $this->fault = VendChannelError::firstOrCreate(['code' => 4], ['desc' => 'Open circuit, motor not detected (4)']);
        CardTerminalBinding::create(['provider' => 'nets', 'terminal_id' => self::TID, 'vend_id' => self::VEND, 'bound_from' => '2026-08-01']);
    }

    private function unit(?bool $flag, ?CardTerminal $company = null): CardTerminalUnit
    {
        return CardTerminalUnit::create([
            'terminal_id' => self::TID, 'card_terminal_id' => ($company ?? $this->nets)->id,
            'is_will_auto_refund' => $flag === null ? null : (int) $flag,
            'auto_refund_flag_source' => $flag === null ? null : CardTerminalUnit::FLAG_SOURCE_SEED,
            'batch' => 'Nets #3 (50x)',
        ]);
    }

    private function finalDay(): void
    {
        foreach ([self::DAY, '2026-08-30'] as $cutover) {
            CardSettlementReport::create(['provider' => 'nets', 'original_filename' => "MCONNECT_{$cutover}.csv", 'cutover_date' => $cutover, 'status' => CardSettlementReport::STATUS_SYNCED]);
        }
    }

    private function sale(array $overrides = []): VendTransaction
    {
        return VendTransaction::create(array_merge([
            'order_id' => 'ORD-'.uniqid(), 'vend_id' => self::VEND, 'transaction_datetime' => self::DAY.' 14:31:07', 'amount' => 240,
            'qty' => 1, 'success_qty' => 0, 'dispensed_qty' => 0, 'vend_channel_id' => 0, 'gst_vat_rate' => 0,
            'payment_method_id' => $this->card->id, 'cashless_mfg' => 'Nets', 'vend_channel_error_id' => $this->fault->id,
            'is_multiple' => false, 'is_found_in_transaction' => true,
        ], $overrides));
    }

    private function line(VendTransaction $sale, bool $reversed = false): CardSettlementRow
    {
        static $n = 0;
        $n++;
        $report = CardSettlementReport::where('cutover_date', self::DAY)->first();
        $purchase = CardSettlementRow::create([
            'card_settlement_report_id' => $report->id, 'row_no' => $n, 'txn_type' => 'Purchase', 'terminal_id' => self::TID,
            'transaction_date' => self::DAY, 'amount_cents' => 240, 'fingerprint' => sha1('st-'.$n),
            'status' => CardSettlementRow::STATUS_MATCHED, 'matched_vend_transaction_id' => $sale->id,
        ]);
        if ($reversed) {
            $n++;
            $rev = CardSettlementRow::create([
                'card_settlement_report_id' => $report->id, 'row_no' => $n, 'txn_type' => 'Purchase', 'terminal_id' => self::TID,
                'transaction_date' => self::DAY, 'amount_cents' => -240, 'is_reversal' => true, 'fingerprint' => sha1('st-'.$n),
                'status' => CardSettlementRow::STATUS_MATCHED, 'reverses_row_id' => $purchase->id,
            ]);
            $purchase->update(['reversed_by_row_id' => $rev->id]);
        }

        return $purchase;
    }

    private function reconcile(bool $apply = true): array
    {
        return app(CardSettlementRefundReconciler::class)->reconcileDay(Carbon::parse(self::DAY), $apply);
    }

    public function test_failed_single_sale_with_no_line_on_a_flagged_terminal_is_ticked_na_in_nets_and_crosses_the_ticket(): void
    {
        $this->unit(true);
        $this->finalDay();
        $sale = $this->sale();
        $ticket = RefundTicket::create([
            'reference' => 'RF-'.uniqid(), 'vend_code' => '2542', 'vend_id' => self::VEND, 'vend_transaction_id' => $sale->id,
            'order_id' => $sale->order_id, 'claimed_amount_cents' => 240, 'status' => RefundTicket::STATUS_SUBMITTED,
        ]);

        $stats = $this->reconcile();

        $sale->refresh();
        $this->assertTrue((bool) $sale->is_refunded);
        $this->assertSame(AutoRefundSource::SETTLEMENT_REPORT_NOT_CAPTURED, $sale->auto_refund_source);
        $this->assertSame(CardSettlementRefundReconciler::STATE_NOT_CAPTURED, $sale->card_settlement_state);
        $this->assertSame(1, $stats['ticked_not_captured']);
        $this->assertSame(1, $stats['tickets_crossed']);
        $this->assertTrue((bool) $ticket->fresh()->auto_refund_detected);

        // Idempotent.
        $again = $this->reconcile();
        $this->assertSame(0, $again['ticked_not_captured']);
        $this->assertSame(0, $again['states_written']);
    }

    public function test_the_tick_is_gated_on_the_terminal_flag_and_the_sale_shape(): void
    {
        $this->unit(false); // batch #1/#2: charges the customer
        $this->finalDay();
        $unflagged = $this->sale();

        $stats = $this->reconcile();
        $this->assertFalse((bool) $unflagged->fresh()->is_refunded, 'a "No" terminal never ticks from a missing line');
        $this->assertSame(CardSettlementRefundReconciler::STATE_NOT_CAPTURED, $unflagged->fresh()->card_settlement_state, 'but the state is recorded for the verify list');
        $this->assertSame(0, $stats['ticked_not_captured']);

        CardTerminalUnit::query()->update(['is_will_auto_refund' => null, 'auto_refund_flag_source' => null]);
        $this->reconcile();
        $this->assertFalse((bool) $unflagged->fresh()->is_refunded, 'unknown is not yes');

        CardTerminalUnit::query()->update(['is_will_auto_refund' => 1]);
        $dispensed = $this->sale(['vend_channel_error_id' => $this->ok->id, 'success_qty' => 1, 'dispensed_qty' => 1]);
        $multiple = $this->sale(['is_multiple' => true, 'qty' => 2]);
        $noTrade = $this->sale(['is_found_in_transaction' => false, 'vend_channel_error_id' => null]);

        $stats = $this->reconcile();
        $this->assertSame(1, $stats['ticked_not_captured'], 'only the failed single sale');
        $this->assertTrue((bool) $unflagged->fresh()->is_refunded);
        $this->assertFalse((bool) $dispensed->fresh()->is_refunded, 'goods went out: a loss to list, not a refund');
        $this->assertFalse((bool) $multiple->fresh()->is_refunded, 'terminals never void a multiple');
        $this->assertFalse((bool) $noTrade->fresh()->is_refunded, 'no verdict, no void');
        foreach ([$dispensed, $multiple, $noTrade] as $s) {
            $this->assertSame(CardSettlementRefundReconciler::STATE_NOT_CAPTURED, $s->fresh()->card_settlement_state);
        }
    }

    public function test_partial_coverage_company_is_uncovered_never_not_captured(): void
    {
        $this->unit(true, $this->auresys);
        $this->finalDay();
        $sale = $this->sale();

        $stats = $this->reconcile();

        $this->assertFalse((bool) $sale->fresh()->is_refunded);
        $this->assertSame(CardSettlementRefundReconciler::STATE_UNCOVERED, $sale->fresh()->card_settlement_state);
        $this->assertSame(0, $stats['ticked_not_captured']);
        $this->assertSame('Not in report (partial coverage)', app(CardSettlementRefundReconciler::class)->verdictFor($sale)['label']);
    }

    public function test_states_are_persisted_for_lined_sales_immediately_and_for_the_rest_only_once_final(): void
    {
        $this->unit(true);
        CardSettlementReport::create(['provider' => 'nets', 'original_filename' => 'd.csv', 'cutover_date' => self::DAY, 'status' => CardSettlementReport::STATUS_SYNCED]);
        $captured = $this->sale();
        $reversed = $this->sale();
        $noLine = $this->sale();
        $unbound = $this->sale(['vend_id' => 999]);
        $this->line($captured);
        $this->line($reversed, reversed: true);

        $stats = $this->reconcile(); // D+1 not synced → not final

        $this->assertSame(CardSettlementRefundReconciler::STATE_REVERSED, $reversed->fresh()->card_settlement_state, 'positive evidence lands at once');
        $this->assertNull($captured->fresh()->card_settlement_state, 'captured waits for the next file (a reversal may sit there)');
        $this->assertNull($noLine->fresh()->card_settlement_state);
        $this->assertNull($unbound->fresh()->card_settlement_state);
        $this->assertFalse((bool) $noLine->fresh()->is_refunded, 'no tick before the day is final');
        $this->assertSame(1, $stats['states_written']);

        CardSettlementReport::create(['provider' => 'nets', 'original_filename' => 'd1.csv', 'cutover_date' => '2026-08-30', 'status' => CardSettlementReport::STATUS_SYNCED]);
        $stats = $this->reconcile();

        $this->assertSame(CardSettlementRefundReconciler::STATE_CAPTURED, $captured->fresh()->card_settlement_state);
        $this->assertSame(CardSettlementRefundReconciler::STATE_NOT_CAPTURED, $noLine->fresh()->card_settlement_state);
        $this->assertSame(CardSettlementRefundReconciler::STATE_UNBOUND, $unbound->fresh()->card_settlement_state);
        $this->assertTrue((bool) $noLine->fresh()->is_refunded);
        $this->assertSame(3, $stats['states_written']);
        $this->assertFalse((bool) $captured->fresh()->is_refunded);
    }

    public function test_a_reversed_orphan_leaves_revenue(): void
    {
        $this->unit(true);
        $this->finalDay();
        $orphan = $this->sale(['is_found_in_transaction' => false, 'vend_channel_error_id' => null, 'card_settlement_row_id' => 1, 'settlement_status' => VendTransaction::SETTLEMENT_SETTLED]);
        $row = $this->line($orphan, reversed: true);
        $orphan->forceFill(['card_settlement_row_id' => $row->id])->save();

        $stats = $this->reconcile();

        $orphan->refresh();
        $this->assertSame(VendTransaction::SETTLEMENT_REFUNDED, (int) $orphan->settlement_status);
        $this->assertTrue((bool) $orphan->is_refunded);
        $this->assertSame(AutoRefundSource::SETTLEMENT_REPORT_REVERSAL, $orphan->auto_refund_source);
        $this->assertSame(CardSettlementRefundReconciler::STATE_REVERSED, $orphan->card_settlement_state);
        $this->assertSame(1, $stats['orphans_refunded']);
    }

    public function test_a_later_line_for_a_sale_ticked_na_in_nets_clears_it(): void
    {
        $this->unit(true);
        $this->finalDay();
        $sale = $this->sale();
        $this->reconcile();
        $this->assertTrue((bool) $sale->fresh()->is_refunded);

        // A re-uploaded / corrected report now carries a captured line for it.
        $this->line($sale);
        $stats = $this->reconcile();

        $this->assertFalse((bool) $sale->fresh()->is_refunded);
        $this->assertNull($sale->fresh()->auto_refund_source);
        $this->assertSame(CardSettlementRefundReconciler::STATE_CAPTURED, $sale->fresh()->card_settlement_state);
        $this->assertSame(1, $stats['cleared_captured']);
    }

    public function test_dry_run_counts_but_writes_nothing(): void
    {
        $this->unit(true);
        $this->finalDay();
        $sale = $this->sale();

        $stats = $this->reconcile(false);

        $this->assertSame(1, $stats['ticked_not_captured']);
        $this->assertSame(1, $stats['states_written']);
        $this->assertFalse((bool) $sale->fresh()->is_refunded);
        $this->assertNull($sale->fresh()->card_settlement_state);
    }

    public function test_verdict_for_the_ticket_page_carries_the_terminal_flag(): void
    {
        $this->unit(false);
        $sale = $this->sale();

        $verdict = app(CardSettlementRefundReconciler::class)->verdictFor($sale);

        $this->assertSame(CardSettlementRefundReconciler::STATE_NO_REPORT, $verdict['state']);
        $this->assertSame(['terminal_id' => self::TID, 'batch' => 'Nets #3 (50x)', 'will_auto_refund' => false], $verdict['terminal']);
    }
}
