<?php

namespace Tests\Feature;

use App\Models\CardSettlementReport;
use App\Models\CardSettlementRow;
use App\Models\CardTerminal;
use App\Models\CardTerminalBinding;
use App\Models\CardTerminalUnit;
use App\Models\Operator;
use App\Models\PaymentMethod;
use App\Models\Vend;
use App\Models\VendChannelError;
use App\Models\VendTransaction;
use App\Services\CardSettlement\CardSettlementOrphanSales;
use App\Services\CardSettlement\CardSettlementSyncService;
use App\Services\Sales\DirtyDayRegistry;
use App\Services\VendTransactionService;
use App\Support\AutoRefundSource;
use App\Support\DispenseVerdict;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

/**
 * Part 2, direction 1 — NETS line, no TRADE. Sync turns each qualifying
 * unmatched line into a code-99 sale and the line claims it; a later card
 * TRADE on that machine adopts the row instead of inserting a second sale;
 * Assign / Ignore on the line deletes an orphan still awaiting its TRADE.
 */
class CardSettlementOrphanSalesTest extends TestCase
{
    use RefreshDatabase;

    private const TID = '23100701';

    private Vend $vend;

    private PaymentMethod $card;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-09-09 14:00:00');
        Bus::fake();

        $operator = Operator::create(['code' => 'OP1', 'name' => 'Op', 'gst_vat_rate' => 9, 'timezone' => 'Asia/Singapore']);
        $this->vend = Vend::create(['code' => '9001', 'operator_id' => $operator->id, 'is_active' => 1]);
        $this->card = PaymentMethod::firstOrCreate(['code' => PaymentMethod::CODE_CARD_TERMINAL], ['name' => 'Card Terminal', 'is_active' => true]);
        PaymentMethod::firstOrCreate(['code' => 0], ['name' => 'Cash', 'is_active' => true]);
        VendChannelError::firstOrCreate(['code' => 0], ['desc' => 'No Malfunction (0)']);
        $nets = CardTerminal::create(['name' => 'Nets']);
        CardTerminalUnit::create(['terminal_id' => self::TID, 'card_terminal_id' => $nets->id]);
        CardTerminalBinding::create(['provider' => 'nets', 'terminal_id' => self::TID, 'vend_id' => $this->vend->id, 'bound_from' => '2026-08-01']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function report(string $cutover = '2026-09-05'): CardSettlementReport
    {
        return CardSettlementReport::create([
            'provider' => 'nets', 'original_filename' => "MCONNECT_{$cutover}.csv", 'cutover_date' => $cutover,
            'status' => CardSettlementReport::STATUS_REVIEW,
        ]);
    }

    private function line(CardSettlementReport $report, array $overrides = []): CardSettlementRow
    {
        static $n = 0;
        $n++;

        return CardSettlementRow::create(array_merge([
            'card_settlement_report_id' => $report->id, 'row_no' => $n, 'txn_type' => 'Purchase', 'terminal_id' => self::TID,
            'transaction_date' => '2026-09-05', 'transaction_time' => '10:15:20', 'time_is_partial' => false, 'amount_cents' => 250,
            'fingerprint' => sha1('orphan-'.$n), 'status' => CardSettlementRow::STATUS_UNMATCHED, 'vend_id' => $this->vend->id,
            'resolution_note' => CardSettlementRow::NOTE_NO_SALE_IN_WINDOW,
        ], $overrides));
    }

    public function test_sync_creates_a_code_99_sale_from_each_qualifying_line_and_nothing_else(): void
    {
        $rep = $this->report();
        $settled = $this->line($rep);
        $reversedLine = $this->line($rep, ['transaction_time' => '11:00:00']);
        $reversal = $this->line($rep, ['transaction_time' => '11:00:05', 'amount_cents' => -250, 'is_reversal' => true, 'reverses_row_id' => $reversedLine->id]);
        $reversedLine->update(['reversed_by_row_id' => $reversal->id]);
        $partial = $this->line($rep, ['time_is_partial' => true, 'transaction_time' => '00:15:20']);
        $unbound = $this->line($rep, ['vend_id' => null, 'resolution_note' => 'No terminal binding']);
        $doubleTap = $this->line($rep, ['resolution_note' => 'All matching sales already claimed']);
        $wrongMachine = $this->line($rep, ['resolution_note' => 'No matching sale on bound machine — found on machine 2379']);

        $sync = app(CardSettlementSyncService::class);
        $count = $sync->sync($rep, null);

        $this->assertSame(2, $sync->lastOrphansCreated());
        $this->assertSame(2, $count, 'the new rows are synced like any matched line');

        $sale = $settled->fresh()->orphanSale;
        $this->assertNotNull($sale);
        $this->assertSame(CardSettlementRow::STATUS_MATCHED, $settled->fresh()->status);
        $this->assertSame($sale->id, $settled->fresh()->matched_vend_transaction_id);
        $this->assertSame(CardSettlementRow::NOTE_CREATED_FROM_REPORT, $settled->fresh()->resolution_note);

        $this->assertSame('2026-09-05 10:15:20', $sale->transaction_datetime->toDateTimeString());
        $this->assertSame(250, (int) $sale->amount);
        $this->assertSame('CS-'.$settled->id, $sale->order_id);
        $this->assertSame($this->vend->id, (int) $sale->vend_id);
        $this->assertSame($this->card->id, (int) $sale->payment_method_id);
        $this->assertSame('Nets', $sale->cashless_mfg);
        $this->assertSame(DispenseVerdict::NOT_FOUND_CODE, $sale->vendChannelError->code);
        $this->assertFalse((bool) $sale->is_found_in_transaction);
        $this->assertSame(VendTransaction::SETTLEMENT_SETTLED, (int) $sale->settlement_status);
        $this->assertFalse((bool) $sale->is_refunded);
        $this->assertNotNull($sale->card_settlement_synced_at);
        $this->assertSame($settled->id, (int) $sale->card_settlement_row_id);
        $this->assertSame('card_settlement', $sale->meta_json['source']);
        $this->assertSame('2026-09-09 14:00:00', $sale->meta_json['missing_trade']['marked_at']);
        $this->assertNull($sale->product_id);
        $this->assertEqualsWithDelta(250 / 1.09, (float) $sale->revenue, 1.0, "the operator's GST rate, not 0 (column rounds to whole cents)");
        $this->assertSame(9.0, (float) $sale->gst_vat_rate);
        $this->assertTrue($sale->isSettlementOrphan());

        $refunded = $reversedLine->fresh()->orphanSale;
        $this->assertSame(VendTransaction::SETTLEMENT_REFUNDED, (int) $refunded->settlement_status);
        $this->assertTrue((bool) $refunded->is_refunded);
        $this->assertSame(AutoRefundSource::SETTLEMENT_REPORT_REVERSAL, $refunded->auto_refund_source);

        foreach ([$partial, $unbound, $doubleTap, $wrongMachine, $reversal] as $row) {
            $this->assertNull($row->fresh()->orphanSale, "row {$row->row_no} must not spawn a sale");
        }
        $this->assertSame(2, VendTransaction::withoutGlobalScopes()->count());
        $this->assertSame(['2026-09-05'], app(DirtyDayRegistry::class)->days());

        // A second Sync finds nothing left to create.
        $sync->sync($rep->fresh(), null);
        $this->assertSame(0, $sync->lastOrphansCreated());
        $this->assertSame(2, VendTransaction::withoutGlobalScopes()->count());
    }

    public function test_a_late_card_trade_adopts_the_orphan_instead_of_inserting_a_second_sale(): void
    {
        $rep = $this->report();
        $row = $this->line($rep);
        app(CardSettlementSyncService::class)->sync($rep, null);
        $orphan = $row->fresh()->orphanSale;

        // TRADE 12 s after the terminal's approval time, same cents, ORDRID from the machine.
        app(VendTransactionService::class)->create($this->vend->fresh(), [
            'ORDRID' => '20260905101532001', 'PAY_TYPE' => 1, 'TIME' => '2026-09-05 10:15:32', 'SErr' => 0, 'SId' => 11, 'Price' => 250, 'TXN_SRC' => 0,
        ]);

        $this->assertSame(1, VendTransaction::withoutGlobalScopes()->count(), 'adopted, not duplicated');
        $sale = $orphan->fresh();
        $this->assertTrue((bool) $sale->is_found_in_transaction);
        $this->assertSame('20260905101532001', $sale->order_id, 'the machine\'s id replaces the synthetic one');
        $this->assertSame(0, $sale->vendChannelError->code);
        $this->assertSame(1, (int) $sale->success_qty);
        $this->assertSame($row->id, (int) $sale->card_settlement_row_id, 'provenance kept');
        $this->assertSame('2026-09-05 10:15:20', $sale->transaction_datetime->toDateTimeString(), 'the report time stays the transaction moment');
        $this->assertSame(VendTransaction::SETTLEMENT_SETTLED, (int) $sale->settlement_status);
        $this->assertSame('2026-09-09 14:00:00', $sale->meta_json['missing_trade']['cleared_at']);
        $this->assertFalse($sale->isSettlementOrphan());
        $this->assertSame($sale->id, $row->fresh()->matched_vend_transaction_id, 'the line still claims it');
        $this->assertSame(['2026-09-05'], app(DirtyDayRegistry::class)->days());

        // The same frame again is the ordinary duplicate short-circuit.
        app(VendTransactionService::class)->create($this->vend->fresh(), [
            'ORDRID' => '20260905101532001', 'PAY_TYPE' => 1, 'TIME' => '2026-09-05 10:15:32', 'SErr' => 0, 'SId' => 11, 'Price' => 250, 'TXN_SRC' => 0,
        ]);
        $this->assertSame(1, VendTransaction::withoutGlobalScopes()->count());
    }

    public function test_a_trade_outside_the_window_or_for_other_cents_or_cash_is_a_fresh_sale(): void
    {
        $rep = $this->report();
        $row = $this->line($rep);
        app(CardSettlementSyncService::class)->sync($rep, null);
        $svc = app(VendTransactionService::class);

        $svc->create($this->vend->fresh(), ['ORDRID' => 'A1', 'PAY_TYPE' => 1, 'TIME' => '2026-09-05 10:30:00', 'SErr' => 0, 'SId' => 11, 'Price' => 250, 'TXN_SRC' => 0]); // 14 min late
        $svc->create($this->vend->fresh(), ['ORDRID' => 'A2', 'PAY_TYPE' => 1, 'TIME' => '2026-09-05 10:15:30', 'SErr' => 0, 'SId' => 11, 'Price' => 300, 'TXN_SRC' => 0]); // other cents
        $svc->create($this->vend->fresh(), ['ORDRID' => 'A3', 'PAY_TYPE' => 0, 'TIME' => '2026-09-05 10:15:30', 'SErr' => 0, 'SId' => 11, 'Price' => 250, 'TXN_SRC' => 0]); // cash

        $this->assertSame(4, VendTransaction::withoutGlobalScopes()->count());
        $this->assertTrue($row->fresh()->orphanSale->isSettlementOrphan(), 'the orphan is untouched');
    }

    public function test_release_deletes_an_orphan_still_awaiting_its_trade_but_never_an_adopted_one(): void
    {
        $rep = $this->report();
        $row = $this->line($rep);
        $adoptedRow = $this->line($rep, ['transaction_time' => '12:00:00']);
        app(CardSettlementSyncService::class)->sync($rep, null);
        app(VendTransactionService::class)->create($this->vend->fresh(), [
            'ORDRID' => 'B1', 'PAY_TYPE' => 1, 'TIME' => '2026-09-05 12:00:10', 'SErr' => 0, 'SId' => 11, 'Price' => 250, 'TXN_SRC' => 0,
        ]);
        $orphanId = $row->fresh()->matched_vend_transaction_id;
        $adoptedId = $adoptedRow->fresh()->matched_vend_transaction_id;

        $svc = app(CardSettlementOrphanSales::class);
        $this->assertTrue($svc->release($row->fresh()));
        $this->assertNull(VendTransaction::withoutGlobalScopes()->find($orphanId));
        $this->assertNull($row->fresh()->matched_vend_transaction_id);

        $this->assertFalse($svc->release($adoptedRow->fresh()), 'a real sale is never deleted');
        $this->assertNotNull(VendTransaction::withoutGlobalScopes()->find($adoptedId));
        $this->assertSame($adoptedId, $adoptedRow->fresh()->matched_vend_transaction_id);
    }

    public function test_orphans_audit_lists_a_possible_double_count(): void
    {
        $rep = $this->report();
        $row = $this->line($rep);
        app(CardSettlementSyncService::class)->sync($rep, null);
        // A card TRADE 2 days later that could not be adopted (outside the window) and that no line claims.
        VendTransaction::create([
            'order_id' => 'LATE-1', 'vend_id' => $this->vend->id, 'transaction_datetime' => '2026-09-07 09:00:00', 'amount' => 250,
            'qty' => 1, 'success_qty' => 1, 'dispensed_qty' => 1, 'vend_channel_id' => 0, 'gst_vat_rate' => 0,
            'payment_method_id' => $this->card->id, 'cashless_mfg' => 'Nets', 'is_found_in_transaction' => true,
        ]);

        $this->artisan('card-settlement:orphans-audit --days=35 --slack-days=3')
            ->expectsOutputToContain('1 still without a TRADE')
            ->expectsOutputToContain('1 possible double count(s)')
            ->assertSuccessful();

        $this->artisan('card-settlement:orphans-audit --days=35 --slack-days=1')
            ->expectsOutputToContain('No orphan has an unclaimed same-amount card TRADE nearby.')
            ->assertSuccessful();
        unset($row);
    }

    public function test_seed_command_reports_then_creates_for_already_synced_reports_only(): void
    {
        $synced = $this->report('2026-09-05');
        $this->line($synced);
        $synced->forceFill(['status' => CardSettlementReport::STATUS_SYNCED])->save();
        $inReview = $this->report('2026-09-06');
        $this->line($inReview, ['transaction_date' => '2026-09-06']);

        $this->artisan('card-settlement:create-orphan-sales')
            ->expectsOutputToContain('Would create 1 orphan sale(s) across 1 report(s).')
            ->assertSuccessful();
        $this->assertSame(0, VendTransaction::withoutGlobalScopes()->count());

        $this->artisan('card-settlement:create-orphan-sales --apply')
            ->expectsOutputToContain('Created 1 orphan sale(s) across 1 report(s).')
            ->assertSuccessful();
        $this->assertSame(1, VendTransaction::withoutGlobalScopes()->count());
        $this->assertSame(1, VendTransaction::withoutGlobalScopes()->where('card_settlement_row_id', '!=', null)->count());
        $this->assertNull($inReview->rows()->first()->fresh()->orphanSale, 'reports in review are Sync\'s job');
    }
}
