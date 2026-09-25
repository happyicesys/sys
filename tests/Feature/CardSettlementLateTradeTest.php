<?php

namespace Tests\Feature;

use App\Jobs\MatchCardSettlementReport;
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
use App\Services\CardSettlement\LateTradePairer;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The leftover pass (LateTradePairer): a NETS line whose TRADE no time window
 * can reach — the board clock reset to 2001 (2300, 2026-09-23), or the frame
 * uploaded long after the tap — is paired on the machine's learned clock, or
 * in order of arrival. Same logic on upload, Rematch and the orphan repair.
 */
class CardSettlementLateTradeTest extends TestCase
{
    use RefreshDatabase;

    private const TID = '23082826';

    private Vend $vend;

    private PaymentMethod $card;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-09-24 16:00:00');
        Bus::fake();

        $operator = Operator::create(['code' => 'OP1', 'name' => 'Op', 'gst_vat_rate' => 9, 'timezone' => 'Asia/Singapore']);
        $this->vend = Vend::create(['code' => '2300', 'operator_id' => $operator->id, 'is_active' => 1]);
        $this->card = PaymentMethod::firstOrCreate(['code' => PaymentMethod::CODE_CARD_TERMINAL], ['name' => 'Card Terminal', 'is_active' => true]);
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

    private function report(string $status = CardSettlementReport::STATUS_UPLOADED): CardSettlementReport
    {
        return CardSettlementReport::create(['provider' => 'nets', 'original_filename' => 'late.csv', 'status' => $status]);
    }

    private function line(CardSettlementReport $report, string $date, string $time, int $amount = 460, array $extra = []): CardSettlementRow
    {
        static $n = 0;
        $n++;

        return CardSettlementRow::create($extra + [
            'card_settlement_report_id' => $report->id, 'row_no' => $n, 'txn_type' => 'Purchase', 'terminal_id' => self::TID,
            'transaction_date' => $date, 'transaction_time' => $time, 'time_is_partial' => false, 'amount_cents' => $amount,
            'fingerprint' => sha1('late-'.$n.uniqid()), 'status' => CardSettlementRow::STATUS_PENDING,
        ]);
    }

    /** A machine sale: booked at $bookedAt, received at $receivedAt, the board's raw TIME in the frame JSON. */
    private function sale(string $bookedAt, string $receivedAt, string $boardTime, int $amount = 460, ?int $vendId = null): VendTransaction
    {
        static $t = 0;
        $t++;
        $sale = VendTransaction::create([
            'order_id' => 'ORD-L-'.$t, 'vend_id' => $vendId ?? $this->vend->id, 'transaction_datetime' => $bookedAt, 'received_at' => $receivedAt,
            'amount' => $amount, 'qty' => 1, 'success_qty' => 1, 'dispensed_qty' => 1, 'vend_channel_id' => 0, 'gst_vat_rate' => 0,
            'payment_method_id' => $this->card->id, 'cashless_mfg' => 'Nets', 'is_found_in_transaction' => true,
        ]);
        DB::table('vend_transactions')->where('id', $sale->id)->update(['vend_transaction_json' => json_encode(['Type' => 'TRADE', 'TIME' => $boardTime])]);

        return $sale;
    }

    /** 2300 on 2026-09-22: three sales matched normally while the board read 2001-01-07. */
    private function seed2001References(): void
    {
        $ref = $this->report(CardSettlementReport::STATUS_SYNCED);
        foreach ([
            ['12:38:49', '2026-09-22 12:39:01', '2001-01-07 08:04:00'],
            ['13:31:24', '2026-09-22 13:32:01', '2001-01-07 08:56:36'],
            ['18:37:09', '2026-09-22 18:37:22', '2001-01-07 14:02:18'],
        ] as [$lineTime, $received, $board]) {
            $sale = $this->sale($received, $received, $board, 240);
            $this->line($ref, '2026-09-22', $lineTime, 240, [
                'status' => CardSettlementRow::STATUS_MATCHED, 'vend_id' => $this->vend->id,
                'matched_vend_transaction_id' => $sale->id, 'resolution_note' => CardSettlementRow::NOTE_MATCHED_RECEIVED,
            ]);
        }
    }

    public function test_a_reset_board_clock_is_matched_on_the_machines_learned_offset(): void
    {
        $this->seed2001References();
        // 2026-09-23: two $4.60 taps, both TRADEs flushed together at 12:50:16,
        // board stamps from the 2001 clock (rejected → booked at arrival).
        $first = $this->sale('2026-09-23 12:50:16', '2026-09-23 12:50:16', '2001-01-08 08:02:49');
        $second = $this->sale('2026-09-23 12:50:16', '2026-09-23 12:50:16', '2001-01-08 08:09:42');
        $report = $this->report();
        $a = $this->line($report, '2026-09-23', '12:37:33');
        $b = $this->line($report, '2026-09-23', '12:44:25');

        app(CardSettlementMatcher::class)->match($report);

        $a->refresh();
        $b->refresh();
        $this->assertSame($first->id, $a->matched_vend_transaction_id);
        $this->assertSame($second->id, $b->matched_vend_transaction_id);
        $this->assertSame(CardSettlementRow::NOTE_MATCHED_CLOCK_OFFSET, $a->resolution_note);
        $this->assertLessThanOrEqual(10, abs($a->match_time_delta), 'within seconds of the learned offset');
    }

    public function test_without_history_late_trades_pair_in_order_of_arrival(): void
    {
        // No matched sales to learn from; both TRADEs reach us 1.5 h after the taps.
        $first = $this->sale('2026-09-23 11:30:00', '2026-09-23 11:30:00', '2001-01-08 06:00:00');
        $second = $this->sale('2026-09-23 11:30:02', '2026-09-23 11:30:02', '2001-01-08 06:05:00');
        $report = $this->report();
        $a = $this->line($report, '2026-09-23', '10:00:00');
        $b = $this->line($report, '2026-09-23', '10:05:00');

        app(CardSettlementMatcher::class)->match($report);

        $this->assertSame($first->id, $a->fresh()->matched_vend_transaction_id);
        $this->assertSame($second->id, $b->fresh()->matched_vend_transaction_id);
        $this->assertSame(CardSettlementRow::NOTE_MATCHED_LATE_SEQUENCE, $a->fresh()->resolution_note);
        $this->assertSame(5400, $a->fresh()->match_time_delta, 'delta = how late the TRADE reached us');
    }

    public function test_sequence_never_guesses_when_counts_disagree_or_the_trade_is_too_late(): void
    {
        // Two taps, one TRADE: which line it belongs to is unknowable → both stay.
        $this->sale('2026-09-23 11:30:00', '2026-09-23 11:30:00', '2001-01-08 06:00:00');
        $report = $this->report();
        $a = $this->line($report, '2026-09-23', '10:00:00');
        $b = $this->line($report, '2026-09-23', '10:05:00');
        // A TRADE 4 h after its tap is outside match_late_max_lag_seconds (3 h).
        $this->sale('2026-09-23 18:00:00', '2026-09-23 18:00:00', '2001-01-08 12:00:00', 300);
        $c = $this->line($report, '2026-09-23', '14:00:00', 300);

        app(CardSettlementMatcher::class)->match($report);

        foreach ([$a, $b, $c] as $row) {
            $this->assertSame(CardSettlementRow::STATUS_UNMATCHED, $row->fresh()->status);
            $this->assertSame(CardSettlementRow::NOTE_NO_SALE_IN_WINDOW, $row->fresh()->resolution_note);
        }
    }

    public function test_a_sale_whose_sane_clock_puts_it_well_after_the_tap_is_not_that_taps_sale(): void
    {
        // 2760, 2026-09-12 (prod dry run): the 12:50:41 line is a second
        // charge; the only unclaimed $2.50 sale's own clock reads 13:13:27.
        // Arrival order must not pair them — late delivery, not a late sale.
        $report = $this->report(CardSettlementReport::STATUS_SYNCED);
        $this->line($report, '2026-09-12', '12:50:41', 250, ['status' => CardSettlementRow::STATUS_UNMATCHED, 'vend_id' => $this->vend->id, 'resolution_note' => CardSettlementRow::NOTE_NO_SALE_IN_WINDOW]);
        app(CardSettlementOrphanSales::class)->createForReport($report);
        $this->sale('2026-09-12 13:13:27', '2026-09-12 13:18:39', '2026-09-12 13:13:27', 250);

        $plan = app(CardSettlementOrphanRepair::class)->plan(Carbon::parse('2026-09-01'), Carbon::parse('2026-09-30'));

        $this->assertCount(1, $plan);
        $this->assertNull($plan[0]['sale'], 'the orphan stays: a genuine second charge');

        // A garbage clock ("14:06:112", 2624 on 09-03) arriving in a burst 6½
        // min after the tap is still paired by arrival.
        // Through the repair (no wide pass there), as on prod.
        $report2 = $this->report(CardSettlementReport::STATUS_SYNCED);
        $this->line($report2, '2026-09-03', '16:21:44', 300, ['status' => CardSettlementRow::STATUS_UNMATCHED, 'vend_id' => $this->vend->id, 'resolution_note' => CardSettlementRow::NOTE_NO_SALE_IN_WINDOW]);
        app(CardSettlementOrphanSales::class)->createForReport($report2);
        $burst = $this->sale('2026-09-03 16:28:21', '2026-09-03 16:28:21', '2026-08-03 14:06:112', 300);

        $plan = app(CardSettlementOrphanRepair::class)->plan(Carbon::parse('2026-09-03'), Carbon::parse('2026-09-03 23:59:59'));

        $this->assertSame($burst->id, $plan[0]['sale']?->id);
        $this->assertSame(LateTradePairer::TIER_SEQUENCE, $plan[0]['anchor']);
    }

    public function test_a_failed_trade_pairs_too(): void
    {
        $failed = $this->sale('2026-09-23 11:30:00', '2026-09-23 11:30:00', '2001-01-08 06:00:00');
        DB::table('vend_transactions')->where('id', $failed->id)->update(['success_qty' => 0, 'dispensed_qty' => 0]);
        $report = $this->report();
        $a = $this->line($report, '2026-09-23', '10:00:00');

        app(CardSettlementMatcher::class)->match($report);

        $this->assertSame($failed->id, $a->fresh()->matched_vend_transaction_id);
    }

    public function test_a_single_graze_on_another_machine_does_not_outvote_the_binding(): void
    {
        $other = Vend::create(['code' => '2283', 'operator_id' => $this->vend->operator_id, 'is_active' => 1]);
        // The terminal matched two sales on its own machine that day…
        $own = $this->report(CardSettlementReport::STATUS_SYNCED);
        foreach (['09:00:00', '11:00:00'] as $time) {
            $s = $this->sale('2026-09-23 '.$time, '2026-09-23 '.$time, '2026-09-23 '.$time, 200);
            $this->line($own, '2026-09-23', $time, 200, ['status' => CardSettlementRow::STATUS_MATCHED, 'vend_id' => $this->vend->id, 'matched_vend_transaction_id' => $s->id]);
        }
        // …and one line whose only fit is a $1.60 sale on 2283 twenty seconds later.
        $this->sale('2026-09-23 18:08:19', '2026-09-23 18:08:19', '2026-09-23 18:08:19', 160, $other->id);
        $report = $this->report();
        $graze = $this->line($report, '2026-09-23', '18:07:59', 160);

        app(CardSettlementMatcher::class)->match($report);

        $this->assertSame(CardSettlementRow::NOTE_NO_SALE_IN_WINDOW, $graze->fresh()->resolution_note, 'an NA orphan at Sync, not a binding query');
        $this->assertNull($graze->fresh()->candidates_json);
    }

    public function test_a_terminal_with_no_sale_on_its_bound_machine_is_still_flagged_as_moved(): void
    {
        $other = Vend::create(['code' => '2696', 'operator_id' => $this->vend->operator_id, 'is_active' => 1]);
        $this->sale('2026-09-23 18:08:19', '2026-09-23 18:08:19', '2026-09-23 18:08:19', 170, $other->id);
        $report = $this->report();
        $line = $this->line($report, '2026-09-23', '18:07:59', 170);

        app(CardSettlementMatcher::class)->match($report);

        $this->assertSame('No matching sale on bound machine — found on machine 2696', $line->fresh()->resolution_note);
    }

    public function test_rematch_replaces_na_orphans_whose_trade_arrived_after_sync(): void
    {
        $this->seed2001References();
        $report = $this->report(CardSettlementReport::STATUS_SYNCED);
        $a = $this->line($report, '2026-09-23', '12:37:33', 460, ['status' => CardSettlementRow::STATUS_UNMATCHED, 'vend_id' => $this->vend->id, 'resolution_note' => CardSettlementRow::NOTE_NO_SALE_IN_WINDOW]);
        $b = $this->line($report, '2026-09-23', '12:44:25', 460, ['status' => CardSettlementRow::STATUS_UNMATCHED, 'vend_id' => $this->vend->id, 'resolution_note' => CardSettlementRow::NOTE_NO_SALE_IN_WINDOW]);
        app(CardSettlementOrphanSales::class)->createForReport($report);
        $orphanIds = [$a->fresh()->matched_vend_transaction_id, $b->fresh()->matched_vend_transaction_id];
        $this->assertNotContains(null, $orphanIds);

        $first = $this->sale('2026-09-23 12:50:16', '2026-09-23 12:50:16', '2001-01-08 08:02:49');
        $second = $this->sale('2026-09-23 12:50:16', '2026-09-23 12:50:16', '2001-01-08 08:09:42');

        // The Rematch button.
        app()->call([new MatchCardSettlementReport($report->id), 'handle']);

        $this->assertSame($first->id, $a->fresh()->matched_vend_transaction_id);
        $this->assertSame($second->id, $b->fresh()->matched_vend_transaction_id);
        $this->assertSame(CardSettlementRow::NOTE_REPAIRED_FROM_ORPHAN, $a->fresh()->resolution_note);
        $this->assertSame(0, VendTransaction::withoutGlobalScopes()->whereIn('id', $orphanIds)->count(), 'orphans gone');
        $this->assertSame(CardSettlementReport::STATUS_REVIEW, $report->fresh()->status, 'never stuck in matching');
    }

    /** A synced report cut on $date — two consecutive ones make the first day final. */
    private function syncedCutover(string $date): CardSettlementReport
    {
        return CardSettlementReport::create(['provider' => 'nets', 'original_filename' => "MCONNECT_{$date}.csv", 'cutover_date' => $date, 'status' => CardSettlementReport::STATUS_SYNCED]);
    }

    private function orphanLine(CardSettlementReport $report, string $date, string $time, int $amount): CardSettlementRow
    {
        $line = $this->line($report, $date, $time, $amount, ['status' => CardSettlementRow::STATUS_UNMATCHED, 'vend_id' => $this->vend->id, 'resolution_note' => CardSettlementRow::NOTE_NO_SALE_IN_WINDOW]);
        app(CardSettlementOrphanSales::class)->createForReport($report);

        return $line->fresh();
    }

    public function test_same_day_pairs_an_na_orphan_with_the_machines_unmatched_trade_once_nets_is_final(): void
    {
        // 2760, 2026-09-12: NETS took $2.50 at 12:50:41, no TRADE fits it;
        // a $2.50 TRADE at 13:13 has no NETS line. NETS is the money truth.
        $report = $this->syncedCutover('2026-09-12');
        $line = $this->orphanLine($report, '2026-09-12', '12:50:41', 250);
        $sale = $this->sale('2026-09-12 13:13:27', '2026-09-12 13:18:39', '2026-09-12 13:13:27', 250);

        $repair = app(CardSettlementOrphanRepair::class);
        $this->assertNull($repair->plan(Carbon::parse('2026-09-12'), Carbon::parse('2026-09-12 23:59:59'))[0]['sale'], 'D+1 file not in yet — the TRADE may still have its own line');

        $this->syncedCutover('2026-09-13');
        $plan = app(CardSettlementOrphanRepair::class)->plan(Carbon::parse('2026-09-12'), Carbon::parse('2026-09-12 23:59:59'));
        $this->assertSame($sale->id, $plan[0]['sale']?->id);
        $this->assertSame(LateTradePairer::TIER_SAME_DAY, $plan[0]['anchor']);

        $repair->apply($plan[0]);
        $this->assertSame($sale->id, $line->fresh()->matched_vend_transaction_id);
        $this->assertSame(CardSettlementRow::NOTE_MATCHED_SAME_DAY, $line->fresh()->resolution_note, 'kept, so it is never a clock reference');
    }

    public function test_same_day_reaches_across_midnight(): void
    {
        // Tap 23:58 on the 12th; the TRADE (board clock 2001) lands 00:03 on the 13th.
        $report = $this->syncedCutover('2026-09-12');
        $this->syncedCutover('2026-09-13');
        $this->syncedCutover('2026-09-14');
        $line = $this->orphanLine($report, '2026-09-12', '23:58:00', 300);
        $sale = $this->sale('2026-09-13 00:03:00', '2026-09-13 00:03:00', '2001-01-08 19:00:00', 300);

        $plan = app(CardSettlementOrphanRepair::class)->plan(Carbon::parse('2026-09-12'), Carbon::parse('2026-09-12 23:59:59'));

        $this->assertSame($sale->id, $plan[0]['sale']?->id);
        $this->assertSame($line->id, $plan[0]['row']->id);
    }

    public function test_same_day_pairs_never_cross(): void
    {
        $report = $this->syncedCutover('2026-09-12');
        $this->syncedCutover('2026-09-13');
        $morning = $this->orphanLine($report, '2026-09-12', '09:00:00', 200);
        $evening = $this->orphanLine($report, '2026-09-12', '18:00:00', 200);
        $noon = $this->sale('2026-09-12 12:00:00', '2026-09-12 12:00:10', '2026-09-12 12:00:00', 200);
        $night = $this->sale('2026-09-12 20:00:00', '2026-09-12 20:00:10', '2026-09-12 20:00:00', 200);

        $plan = app(CardSettlementOrphanRepair::class)->plan(Carbon::parse('2026-09-12'), Carbon::parse('2026-09-12 23:59:59'))
            ->keyBy(fn ($e) => $e['row']->id);

        $this->assertSame($noon->id, $plan[$morning->id]['sale']->id);
        $this->assertSame($night->id, $plan[$evening->id]['sale']->id);
    }

    /** @return array<int, int|null> line id → planned sale id */
    private function sameDayPlan(): array
    {
        return app(CardSettlementOrphanRepair::class)->plan(Carbon::parse('2026-09-12'), Carbon::parse('2026-09-12 23:59:59'))
            ->mapWithKeys(fn ($e) => [$e['row']->id => $e['sale']?->id])->all();
    }

    public function test_several_same_amount_nas_take_the_trades_in_order_at_least_total_gap(): void
    {
        // Nearest-first takes 10:05 → 10:20 (15 min) and then cannot give
        // 10:00 the 10:50 TRADE without crossing — one pair, one NA left. In
        // order, both pair: 10:00 → 10:20, 10:05 → 10:50.
        $report = $this->syncedCutover('2026-09-12');
        $this->syncedCutover('2026-09-13');
        $a = $this->orphanLine($report, '2026-09-12', '10:00:00', 460);
        $b = $this->orphanLine($report, '2026-09-12', '10:05:00', 460);
        $s1 = $this->sale('2026-09-12 10:20:00', '2026-09-12 10:20:10', '2026-09-12 10:20:00', 460);
        $s2 = $this->sale('2026-09-12 10:50:00', '2026-09-12 10:50:10', '2026-09-12 10:50:00', 460);

        $plan = $this->sameDayPlan();

        $this->assertSame($s1->id, $plan[$a->id]);
        $this->assertSame($s2->id, $plan[$b->id]);
    }

    public function test_more_nas_than_trades_leaves_the_one_that_fits_worst(): void
    {
        $report = $this->syncedCutover('2026-09-12');
        $this->syncedCutover('2026-09-13');
        $early = $this->orphanLine($report, '2026-09-12', '09:00:00', 200);
        $noon = $this->orphanLine($report, '2026-09-12', '12:00:00', 200);
        $evening = $this->orphanLine($report, '2026-09-12', '18:00:00', 200);
        $s1 = $this->sale('2026-09-12 12:10:00', '2026-09-12 12:10:05', '2026-09-12 12:10:00', 200);
        $s2 = $this->sale('2026-09-12 18:20:00', '2026-09-12 18:20:05', '2026-09-12 18:20:00', 200);

        $plan = $this->sameDayPlan();

        $this->assertSame($s1->id, $plan[$noon->id]);
        $this->assertSame($s2->id, $plan[$evening->id]);
        $this->assertNull($plan[$early->id], 'stays NA — NETS took money no TRADE explains');
    }

    public function test_a_trade_after_the_tap_is_preferred_to_one_before_it(): void
    {
        // 10 min before vs 12 min after: a TRADE follows its tap, so the later one.
        $report = $this->syncedCutover('2026-09-12');
        $this->syncedCutover('2026-09-13');
        $line = $this->orphanLine($report, '2026-09-12', '12:00:00', 300);
        $this->sale('2026-09-12 11:50:00', '2026-09-12 11:50:05', '2026-09-12 11:50:00', 300);
        $after = $this->sale('2026-09-12 12:12:00', '2026-09-12 12:12:05', '2026-09-12 12:12:00', 300);

        $this->assertSame($after->id, $this->sameDayPlan()[$line->id]);
    }

    public function test_syncing_the_next_days_report_pairs_the_previous_days_orphan_at_once(): void
    {
        $day = $this->syncedCutover('2026-09-12');
        $line = $this->orphanLine($day, '2026-09-12', '12:50:41', 250);
        $sale = $this->sale('2026-09-12 13:13:27', '2026-09-12 13:18:39', '2026-09-12 13:13:27', 250);
        $next = CardSettlementReport::create(['provider' => 'nets', 'original_filename' => 'MCONNECT_2026-09-13.csv', 'cutover_date' => '2026-09-13', 'status' => CardSettlementReport::STATUS_REVIEW]);

        app(\App\Services\CardSettlement\CardSettlementSyncService::class)->sync($next, null);

        $this->assertSame($sale->id, $line->fresh()->matched_vend_transaction_id);
        $this->assertSame(0, VendTransaction::withoutGlobalScopes()->whereNotNull('card_settlement_row_id')->where('is_found_in_transaction', false)->count(), 'orphan gone');
    }

    public function test_the_repair_command_plans_with_the_same_tiers(): void
    {
        $report = $this->report(CardSettlementReport::STATUS_SYNCED);
        $a = $this->line($report, '2026-09-23', '10:00:00', 460, ['status' => CardSettlementRow::STATUS_UNMATCHED, 'vend_id' => $this->vend->id, 'resolution_note' => CardSettlementRow::NOTE_NO_SALE_IN_WINDOW]);
        app(CardSettlementOrphanSales::class)->createForReport($report);
        $late = $this->sale('2026-09-23 11:30:00', '2026-09-23 11:30:00', '2001-01-08 06:00:00');

        $plan = app(CardSettlementOrphanRepair::class)->plan(Carbon::parse('2026-09-01'), Carbon::parse('2026-09-30'));

        $this->assertCount(1, $plan);
        $this->assertSame($late->id, $plan[0]['sale']->id);
        $this->assertSame(LateTradePairer::TIER_SEQUENCE, $plan[0]['anchor']);
        $this->assertSame($a->id, $plan[0]['row']->id);
    }
}
