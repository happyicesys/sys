<?php

namespace Tests\Feature;

use App\Models\CardSettlementReport;
use App\Models\CardSettlementRow;
use App\Models\CardTerminalBinding;
use App\Models\PaymentMethod;
use App\Models\RefundTicket;
use App\Models\VendTransaction;
use App\Services\CardSettlement\CardSettlementRefundReconciler;
use App\Support\AutoRefundSource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * The NETS settlement report is the ONLY source of truth for the auto-refund
 * tick on card sales (2026-09-08). These pin the four rules, the "day is
 * final only when files D and D+1 are synced" gate, the ticket cross/release
 * mirror, and the dry run.
 */
class CardSettlementRefundReconcilerTest extends TestCase
{
    use RefreshDatabase;

    private const VEND = 1320;

    private const TID = '23082824';

    private const DAY = '2026-08-29';

    private function sale(string $at = '2026-08-29 14:31:07', array $overrides = []): VendTransaction
    {
        $card = PaymentMethod::firstOrCreate(['code' => 1], ['name' => 'Card Terminal', 'is_active' => true]);

        $txn = VendTransaction::create(array_merge([
            'order_id' => 'ORD-REC-'.uniqid(),
            'vend_id' => self::VEND,
            'transaction_datetime' => $at,
            'amount' => 240,
            'qty' => 1,
            'success_qty' => 0,
            'dispensed_qty' => 0,
            'vend_channel_id' => 0,
            'gst_vat_rate' => 0,
            'payment_method_id' => $card->id,
            'cashless_mfg' => 'Nets',
        ], $overrides));

        return $txn;
    }

    private function ticked(VendTransaction $txn, string $source): VendTransaction
    {
        $txn->forceFill(['is_refunded' => true, 'auto_refund_source' => $source])->save();

        return $txn;
    }

    private function report(string $cutover, string $status = CardSettlementReport::STATUS_SYNCED): CardSettlementReport
    {
        return CardSettlementReport::create([
            'provider' => 'nets',
            'original_filename' => "MCONNECT_{$cutover}.csv",
            'cutover_date' => $cutover,
            'status' => $status,
        ]);
    }

    private function bind(): void
    {
        CardTerminalBinding::create([
            'provider' => 'nets',
            'terminal_id' => self::TID,
            'vend_id' => self::VEND,
            'bound_from' => '2026-08-01',
        ]);
    }

    private function line(CardSettlementReport $report, VendTransaction $txn, bool $reversed = false): CardSettlementRow
    {
        static $n = 0;
        $n++;
        $purchase = CardSettlementRow::create([
            'card_settlement_report_id' => $report->id,
            'row_no' => $n,
            'txn_type' => 'Purchase',
            'terminal_id' => self::TID,
            'transaction_date' => self::DAY,
            'amount_cents' => 240,
            'fingerprint' => sha1('rec-'.$n),
            'status' => CardSettlementRow::STATUS_MATCHED,
            'matched_vend_transaction_id' => $txn->id,
        ]);
        if ($reversed) {
            $n++;
            $rev = CardSettlementRow::create([
                'card_settlement_report_id' => $report->id,
                'row_no' => $n,
                'txn_type' => 'Purchase',
                'terminal_id' => self::TID,
                'transaction_date' => self::DAY,
                'amount_cents' => -240,
                'is_reversal' => true,
                'fingerprint' => sha1('rec-'.$n),
                'status' => CardSettlementRow::STATUS_MATCHED,
                'reverses_row_id' => $purchase->id,
            ]);
            $purchase->update(['reversed_by_row_id' => $rev->id]);
        }

        return $purchase;
    }

    private function ticket(VendTransaction $txn, array $overrides = []): RefundTicket
    {
        return RefundTicket::create(array_merge([
            'reference' => 'RF-'.uniqid(),
            'vend_code' => '2542',
            'vend_id' => self::VEND,
            'vend_transaction_id' => $txn->id,
            'order_id' => $txn->order_id,
            'claimed_amount_cents' => 240,
            'status' => RefundTicket::STATUS_SUBMITTED,
        ], $overrides));
    }

    private function reconcile(bool $apply = true): array
    {
        return app(CardSettlementRefundReconciler::class)->reconcileDay(Carbon::parse(self::DAY), $apply);
    }

    public function test_reversal_line_sets_the_tick_and_crosses_the_ticket()
    {
        $this->bind();
        $rep = $this->report(self::DAY);
        $sale = $this->sale();
        $this->line($rep, $sale, reversed: true);
        $ticket = $this->ticket($sale, ['status' => RefundTicket::STATUS_APPROVED]);

        $stats = $this->reconcile();

        $sale->refresh();
        $this->assertTrue((bool) $sale->is_refunded);
        $this->assertSame(AutoRefundSource::SETTLEMENT_REPORT_REVERSAL, $sale->auto_refund_source);
        $this->assertSame(1, $stats['confirmed']);
        $this->assertSame(1, $stats['tickets_crossed']);
        $ticket->refresh();
        $this->assertTrue((bool) $ticket->auto_refund_detected);
        $this->assertSame(RefundTicket::STATUS_REJECTED, $ticket->status, 'approved ticket pulled out of payout');
    }

    public function test_inference_tick_the_report_confirms_is_relabelled()
    {
        $this->bind();
        $rep = $this->report(self::DAY);
        $sale = $this->ticked($this->sale(), AutoRefundSource::CARD_TERMINAL_REVERSAL);
        $this->line($rep, $sale, reversed: true);

        $stats = $this->reconcile();

        $this->assertSame(AutoRefundSource::SETTLEMENT_REPORT_REVERSAL, $sale->fresh()->auto_refund_source);
        $this->assertTrue((bool) $sale->fresh()->is_refunded);
        $this->assertSame(1, $stats['relabelled']);
        $this->assertSame(0, $stats['confirmed']);
    }

    public function test_captured_not_reversed_clears_the_tick_and_releases_the_ticket_once_the_day_is_final()
    {
        $this->bind();
        $rep = $this->report(self::DAY);
        $this->report('2026-08-30');
        $sale = $this->ticked($this->sale(), AutoRefundSource::CARD_TERMINAL_REVERSAL);
        $this->line($rep, $sale);
        $ticket = $this->ticket($sale, [
            'auto_refund_detected' => true,
            'system_recommendation' => RefundTicket::REC_REJECT,
            'system_validation_json' => ['already_refunded' => true, 'txn_already_refunded' => true, 'is_auto_refund_channel' => false],
        ]);

        $stats = $this->reconcile();

        $sale->refresh();
        $this->assertFalse((bool) $sale->is_refunded);
        $this->assertNull($sale->auto_refund_source);
        $this->assertSame(1, $stats['cleared_captured']);
        $this->assertSame(1, $stats['tickets_released']);

        $ticket->refresh();
        $this->assertFalse((bool) $ticket->auto_refund_detected);
        $this->assertFalse((bool) $ticket->system_validation_json['already_refunded']);
        $this->assertFalse((bool) $ticket->system_validation_json['txn_already_refunded']);
        $this->assertSame(RefundTicket::REC_REVIEW, $ticket->system_recommendation);
        $this->assertSame(RefundTicket::STATUS_SUBMITTED, $ticket->status);
        $this->assertFalse($ticket->isAlreadyRefunded(), 'Approve guard must be lifted');
        $this->assertDatabaseHas('refund_ticket_logs', ['refund_ticket_id' => $ticket->id, 'action' => 'auto_refund_cleared']);
    }

    public function test_clearing_waits_until_the_next_days_file_is_synced()
    {
        $this->bind();
        $rep = $this->report(self::DAY); // D synced, D+1 missing → not final
        $sale = $this->ticked($this->sale(), AutoRefundSource::CARD_TERMINAL_REVERSAL);
        $this->line($rep, $sale);

        $stats = $this->reconcile();

        $this->assertTrue((bool) $sale->fresh()->is_refunded, 'the reversal may still be in the D+1 file');
        $this->assertFalse($stats['final']);
        $this->assertSame(1, $stats['skipped_not_final']);
        $this->assertSame(0, $stats['cleared_captured']);

        // D+1 arrives in review only — still not final.
        $next = $this->report('2026-08-30', CardSettlementReport::STATUS_REVIEW);
        $this->assertSame(1, $this->reconcile()['skipped_not_final']);

        // D+1 synced → final → cleared.
        $next->update(['status' => CardSettlementReport::STATUS_SYNCED]);
        $stats = $this->reconcile();
        $this->assertSame(1, $stats['cleared_captured']);
        $this->assertFalse((bool) $sale->fresh()->is_refunded);
    }

    public function test_no_capture_on_a_bound_terminal_clears_the_tick_once_final()
    {
        $this->bind();
        $this->report(self::DAY);
        $this->report('2026-08-30');
        $sale = $this->ticked($this->sale(), AutoRefundSource::CARD_TERMINAL_REVERSAL);

        $stats = $this->reconcile();

        $this->assertFalse((bool) $sale->fresh()->is_refunded);
        $this->assertSame(1, $stats['cleared_not_captured']);
    }

    public function test_no_capture_on_an_unbound_machine_is_left_alone()
    {
        // No binding: the report cannot say anything about this machine.
        $this->report(self::DAY);
        $this->report('2026-08-30');
        $sale = $this->ticked($this->sale(), AutoRefundSource::CARD_TERMINAL_REVERSAL);

        $stats = $this->reconcile();

        $this->assertTrue((bool) $sale->fresh()->is_refunded);
        $this->assertSame(1, $stats['skipped_unbound']);
    }

    public function test_a_line_in_a_report_still_in_review_is_not_acted_on()
    {
        $this->bind();
        $rep = $this->report(self::DAY, CardSettlementReport::STATUS_REVIEW);
        $this->report('2026-08-30');
        $sale = $this->sale();
        $this->line($rep, $sale, reversed: true);
        $ticked = $this->ticked($this->sale('2026-08-29 15:00:00'), AutoRefundSource::CARD_TERMINAL_REVERSAL);
        $this->line($rep, $ticked);

        $stats = $this->reconcile();

        $this->assertFalse((bool) $sale->fresh()->is_refunded, 'a reversal in an unsynced report sets nothing');
        $this->assertTrue((bool) $ticked->fresh()->is_refunded, 'an unsynced line clears nothing');
        $this->assertSame(0, $stats['confirmed'] + $stats['cleared_captured'] + $stats['cleared_not_captured']);
    }

    public function test_sources_the_report_does_not_own_are_never_cleared()
    {
        $this->bind();
        $rep = $this->report(self::DAY);
        $this->report('2026-08-30');
        $revend = $this->ticked($this->sale(), AutoRefundSource::RETAINED_CREDIT_REVEND);
        $this->line($rep, $revend);
        $omise = $this->ticked($this->sale('2026-08-29 16:00:00', ['cashless_mfg' => null]), AutoRefundSource::OMISE_TRADE_FAIL);

        $stats = $this->reconcile();

        $this->assertTrue((bool) $revend->fresh()->is_refunded);
        $this->assertSame(AutoRefundSource::RETAINED_CREDIT_REVEND, $revend->fresh()->auto_refund_source);
        $this->assertTrue((bool) $omise->fresh()->is_refunded);
        // Every card sale of the day is classified now (state persisted), but nothing owned was touched.
        $this->assertSame(2, $stats['candidates']);
        $this->assertSame(0, $stats['cleared_captured'] + $stats['cleared_not_captured'] + $stats['confirmed'] + $stats['relabelled']);
        $this->assertSame(AutoRefundSource::OMISE_TRADE_FAIL, $omise->fresh()->auto_refund_source);
    }

    public function test_dry_run_counts_but_writes_nothing()
    {
        $this->bind();
        $rep = $this->report(self::DAY);
        $this->report('2026-08-30');
        $cleared = $this->ticked($this->sale(), AutoRefundSource::CARD_TERMINAL_REVERSAL);
        $this->line($rep, $cleared);
        $set = $this->sale('2026-08-29 15:00:00');
        $this->line($rep, $set, reversed: true);
        $ticket = $this->ticket($cleared, ['auto_refund_detected' => true]);

        $stats = $this->reconcile(apply: false);

        $this->assertSame(1, $stats['cleared_captured']);
        $this->assertSame(1, $stats['confirmed']);
        $this->assertTrue((bool) $cleared->fresh()->is_refunded);
        $this->assertFalse((bool) $set->fresh()->is_refunded);
        $this->assertTrue((bool) $ticket->fresh()->auto_refund_detected);
    }

    public function test_reconcile_is_idempotent()
    {
        $this->bind();
        $rep = $this->report(self::DAY);
        $this->report('2026-08-30');
        $a = $this->ticked($this->sale(), AutoRefundSource::CARD_TERMINAL_REVERSAL);
        $this->line($rep, $a);
        $b = $this->sale('2026-08-29 15:00:00');
        $this->line($rep, $b, reversed: true);

        $this->reconcile();
        $second = $this->reconcile();

        $this->assertSame(0, $second['confirmed'] + $second['relabelled'] + $second['cleared_captured'] + $second['cleared_not_captured']);
        $this->assertFalse((bool) $a->fresh()->is_refunded);
        $this->assertTrue((bool) $b->fresh()->is_refunded);
    }

    public function test_verdict_for_the_ticket_page_follows_the_same_rules()
    {
        $this->bind();
        $rep = $this->report(self::DAY);
        $reversed = $this->sale();
        $this->line($rep, $reversed, reversed: true);
        $captured = $this->sale('2026-08-29 15:00:00');
        $this->line($rep, $captured);
        $missing = $this->sale('2026-08-29 16:00:00');
        $unbound = $this->sale('2026-08-29 17:00:00', ['vend_id' => 999]);

        $r = app(CardSettlementRefundReconciler::class);
        $this->assertSame('reversed', $r->verdictFor($reversed)['state']);
        $this->assertSame('captured', $r->verdictFor($captured)['state']);
        $this->assertSame('no_report', $r->verdictFor($missing)['state'], 'D+1 not synced yet');
        $this->assertSame('unbound', $r->verdictFor($unbound)['state']);

        $this->report('2026-08-30');
        $this->assertSame('not_captured', $r->verdictFor($missing)['state']);
        $this->assertSame($rep->id, $r->verdictFor($reversed)['report_id']);
    }

    public function test_command_dry_runs_by_default_and_applies_with_the_flag()
    {
        $this->bind();
        $rep = $this->report(self::DAY);
        $this->report('2026-08-30');
        $sale = $this->ticked($this->sale(), AutoRefundSource::CARD_TERMINAL_REVERSAL);
        $this->line($rep, $sale);

        $this->artisan('card-settlement:reconcile-refunds', ['--from' => self::DAY, '--to' => self::DAY])
            ->expectsOutputToContain('DRY RUN')
            ->assertSuccessful();
        $this->assertTrue((bool) $sale->fresh()->is_refunded);

        $this->artisan('card-settlement:reconcile-refunds', ['--from' => self::DAY, '--to' => self::DAY, '--apply' => true])
            ->expectsOutputToContain('APPLYING')
            ->assertSuccessful();
        $this->assertFalse((bool) $sale->fresh()->is_refunded);
    }
}
