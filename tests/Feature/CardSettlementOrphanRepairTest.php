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
use App\Services\CardSettlement\CardSettlementMatcher;
use App\Services\CardSettlement\CardSettlementOrphanRepair;
use App\Services\CardSettlement\CardSettlementOrphanSales;
use App\Services\Sales\DirtyDayRegistry;
use App\Services\VendTransactionService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * received_at is stamped at TRADE ingest, orphan adoption honours both
 * anchors, and card-settlement:repair-orphans replaces an orphan with the
 * real sale that fits its line on either anchor.
 */
class CardSettlementOrphanRepairTest extends TestCase
{
    use RefreshDatabase;

    private const TID = '23104091';

    private Vend $vend;

    private PaymentMethod $card;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-09-12 14:00:00');
        Bus::fake();

        $operator = Operator::create(['code' => 'OP1', 'name' => 'Op', 'gst_vat_rate' => 9, 'timezone' => 'Asia/Singapore']);
        $this->vend = Vend::create(['code' => '2760', 'operator_id' => $operator->id, 'is_active' => 1]);
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

    private function report(): CardSettlementReport
    {
        return CardSettlementReport::create([
            'provider' => 'nets', 'original_filename' => 'MCONNECT_2026-09-12.csv', 'cutover_date' => '2026-09-12',
            'status' => CardSettlementReport::STATUS_SYNCED,
        ]);
    }

    /** An unmatched line of the shape Sync turns into an orphan. */
    private function line(CardSettlementReport $report, string $time, int $amount = 200): CardSettlementRow
    {
        static $n = 0;
        $n++;

        return CardSettlementRow::create([
            'card_settlement_report_id' => $report->id, 'row_no' => $n, 'txn_type' => 'Purchase', 'terminal_id' => self::TID,
            'transaction_date' => '2026-09-12', 'transaction_time' => $time, 'time_is_partial' => false, 'amount_cents' => $amount,
            'fingerprint' => sha1('repair-'.$n), 'status' => CardSettlementRow::STATUS_UNMATCHED, 'vend_id' => $this->vend->id,
            'resolution_note' => CardSettlementRow::NOTE_NO_SALE_IN_WINDOW,
        ]);
    }

    /** A real machine sale: frame time from the board, received_at from us. */
    private function sale(string $frameTime, string $receivedAt, int $amount = 200, ?string $orderId = null): VendTransaction
    {
        static $t = 0;
        $t++;

        return VendTransaction::create([
            'order_id' => $orderId ?? 'ORD-R-'.$t, 'vend_id' => $this->vend->id, 'transaction_datetime' => $frameTime, 'received_at' => $receivedAt,
            'amount' => $amount, 'qty' => 1, 'success_qty' => 1, 'dispensed_qty' => 1, 'vend_channel_id' => 0, 'gst_vat_rate' => 0,
            'payment_method_id' => $this->card->id, 'cashless_mfg' => 'Nets', 'is_found_in_transaction' => true,
        ]);
    }

    private function orphanFor(CardSettlementRow $line): VendTransaction
    {
        app(CardSettlementOrphanSales::class)->createForReport($line->report);
        $line->refresh();
        $this->assertSame(CardSettlementRow::STATUS_MATCHED, $line->status);

        return VendTransaction::withoutGlobalScopes()->findOrFail($line->matched_vend_transaction_id);
    }

    public function test_a_live_trade_is_stamped_with_the_moment_we_received_it(): void
    {
        app(VendTransactionService::class)->create($this->vend->fresh(), [
            'ORDRID' => '20260912121021002', 'PAY_TYPE' => 1, 'TIME' => '2026-09-12 12:10:21', 'SErr' => 0, 'SId' => 11, 'Price' => 200, 'TXN_SRC' => 0,
        ]);

        $sale = VendTransaction::withoutGlobalScopes()->where('order_id', '20260912121021002')->firstOrFail();
        $this->assertSame('2026-09-12 12:10:21', $sale->transaction_datetime->toDateTimeString(), 'frame time inside the 30-day window is kept');
        $this->assertSame('2026-09-12 14:00:00', $sale->received_at->toDateTimeString());
    }

    public function test_a_trade_from_a_slow_board_adopts_the_orphan_on_receive_time(): void
    {
        $orphan = $this->orphanFor($this->line($this->report(), '12:15:24'));

        // The frame says 12:10:21 (board 303 s slow) — outside the frame
        // window of the 12:15:24 orphan — but it reaches us at 12:15:36.
        Carbon::setTestNow('2026-09-12 12:15:36');
        app(VendTransactionService::class)->create($this->vend->fresh(), [
            'ORDRID' => '20260912121021003', 'PAY_TYPE' => 1, 'TIME' => '2026-09-12 12:10:21', 'SErr' => 0, 'SId' => 11, 'Price' => 200, 'TXN_SRC' => 0,
        ]);

        $orphan->refresh();
        $this->assertTrue($orphan->is_found_in_transaction, 'adopted, not duplicated');
        $this->assertSame('20260912121021003', $orphan->order_id);
        $this->assertSame('2026-09-12 12:15:36', $orphan->received_at->toDateTimeString());
        $this->assertSame(1, VendTransaction::withoutGlobalScopes()->where('vend_id', $this->vend->id)->count());
    }

    public function test_repair_replaces_a_slow_clock_orphan_with_the_real_sale(): void
    {
        Carbon::setTestNow('2026-09-14 18:40:13'); // repairs run after the day is over (the registry ignores today)
        $line = $this->line($this->report(), '12:15:24');
        $orphan = $this->orphanFor($line);
        DB::table('vend_transactions')->where('id', $orphan->id)->update(['card_settlement_synced_at' => '2026-09-14 18:40:13']);
        $real = $this->sale('2026-09-12 12:10:21', '2026-09-12 12:15:36');

        $repair = app(CardSettlementOrphanRepair::class);
        $plan = $repair->plan(Carbon::parse('2026-09-01'), Carbon::parse('2026-09-30'));

        $this->assertCount(1, $plan);
        $this->assertSame($real->id, $plan[0]['sale']->id);
        $this->assertSame(CardSettlementMatcher::ANCHOR_RECEIVED, $plan[0]['anchor']);
        $this->assertSame(12, $plan[0]['delta']);

        $days = $repair->apply($plan[0]);

        $this->assertNull(VendTransaction::withoutGlobalScopes()->find($orphan->id), 'orphan deleted');
        $line->refresh();
        $this->assertSame(CardSettlementRow::STATUS_MATCHED, $line->status);
        $this->assertSame($real->id, $line->matched_vend_transaction_id);
        $this->assertSame(12, $line->match_time_delta);
        $this->assertSame(CardSettlementRow::NOTE_REPAIRED_FROM_ORPHAN, $line->resolution_note);
        $this->assertSame('2026-09-14 18:40:13', (string) $real->fresh()->card_settlement_synced_at, 'Sync stamp carried over');
        $this->assertSame(['2026-09-12'], $days);
        $this->assertContains('2026-09-12', app(DirtyDayRegistry::class)->days());

        // Second run: nothing left to repair.
        $this->assertCount(0, $repair->plan(Carbon::parse('2026-09-01'), Carbon::parse('2026-09-30')));
    }

    public function test_repair_pairs_burst_flushed_sales_with_their_own_lines(): void
    {
        // 2502 shape: two lines, two real sales received together 6 min later.
        $rep = $this->report();
        $a = $this->line($rep, '20:53:07', 460);
        $b = $this->line($rep, '20:54:09', 460);
        app(CardSettlementOrphanSales::class)->createForReport($rep);
        $first = $this->sale('2026-09-12 20:53:35', '2026-09-12 20:59:46', 460);
        $second = $this->sale('2026-09-12 20:54:38', '2026-09-12 20:59:46', 460);

        $repair = app(CardSettlementOrphanRepair::class);
        $plan = $repair->plan(Carbon::parse('2026-09-01'), Carbon::parse('2026-09-30'));
        $this->assertCount(2, $plan->filter(fn ($e) => $e['sale'] !== null));
        foreach ($plan as $entry) {
            $repair->apply($entry);
        }

        $this->assertSame($first->id, $a->fresh()->matched_vend_transaction_id);
        $this->assertSame($second->id, $b->fresh()->matched_vend_transaction_id);
        $this->assertSame(CardSettlementMatcher::ANCHOR_FRAME, $plan[0]['anchor']);
        $this->assertSame(2, VendTransaction::withoutGlobalScopes()->where('vend_id', $this->vend->id)->count(), 'both orphans gone');
    }

    public function test_repair_leaves_genuine_orphans_and_claimed_sales_alone(): void
    {
        $rep = $this->report();
        $genuine = $this->orphanFor($this->line($rep, '09:00:00', 300));          // no machine sale at all
        $contested = $this->line($rep, '10:00:00', 250);
        app(CardSettlementOrphanSales::class)->createForReport($rep);
        $contested->refresh();
        $claimedElsewhere = $this->sale('2026-09-12 10:00:10', '2026-09-12 10:00:22', 250);
        $otherLine = $this->line($this->report(), '10:00:01', 250);
        $otherLine->update(['status' => CardSettlementRow::STATUS_MATCHED, 'matched_vend_transaction_id' => $claimedElsewhere->id, 'resolution_note' => null]);

        $plan = app(CardSettlementOrphanRepair::class)->plan(Carbon::parse('2026-09-01'), Carbon::parse('2026-09-30'));

        $this->assertCount(2, $plan);
        $this->assertTrue($plan->every(fn ($e) => $e['sale'] === null));
        $this->assertNotNull(VendTransaction::withoutGlobalScopes()->find($genuine->id));
        $this->assertSame($claimedElsewhere->id, $otherLine->fresh()->matched_vend_transaction_id);
    }

    public function test_the_command_is_a_dry_run_unless_told_to_apply(): void
    {
        $line = $this->line($this->report(), '12:15:24');
        $orphan = $this->orphanFor($line);
        $real = $this->sale('2026-09-12 12:10:21', '2026-09-12 12:15:36');

        $this->artisan('card-settlement:repair-orphans', ['--from' => '2026-09-01', '--vend' => '2760'])
            ->expectsOutputToContain('1 repairable')
            ->expectsOutputToContain('Dry run')
            ->assertSuccessful();
        $this->assertNotNull(VendTransaction::withoutGlobalScopes()->find($orphan->id));

        $this->artisan('card-settlement:repair-orphans', ['--from' => '2026-09-01', '--vend' => '2760', '--apply' => true])
            ->expectsOutputToContain('Repaired 1 orphan(s)')
            ->assertSuccessful();
        $this->assertNull(VendTransaction::withoutGlobalScopes()->find($orphan->id));
        $this->assertSame($real->id, $line->fresh()->matched_vend_transaction_id);
    }
}
