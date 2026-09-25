<?php

namespace Tests\Feature;

use App\Jobs\MatchCardSettlementReport;
use App\Mail\CardSettlementHealthMail;
use App\Models\AlertEmailItem;
use App\Models\CardSettlementReport;
use App\Models\CardSettlementRow;
use App\Models\CardTerminal;
use App\Models\CardTerminalBinding;
use App\Models\CardTerminalUnit;
use App\Models\Operator;
use App\Models\PaymentMethod;
use App\Models\RefundTicket;
use App\Models\Vend;
use App\Models\VendChannelError;
use App\Models\VendTransaction;
use App\Services\CardSettlement\CardSettlementHealthCheck;
use App\Services\CardSettlement\CardSettlementMatcher;
use App\Services\CardSettlement\CardSettlementOrphanRepair;
use App\Services\CardSettlement\CardSettlementOrphanSales;
use App\Services\CardSettlement\CardTerminalBindingService;
use App\Services\CardSettlement\RetainedCreditLinker;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Hands-off card settlement (2026-09-26): NETS lines only ever meet NETS
 * machines; retained-credit re-vends and top-ups are recognised from the
 * report; strong terminal moves apply themselves; reports sync themselves;
 * the nightly check emails only what needs a person.
 */
class CardSettlementHandsOffTest extends TestCase
{
    use RefreshDatabase;

    private const TID = '23100732';

    private Operator $operator;

    private Vend $vend;

    private CardTerminal $nets;

    private CardTerminal $mls;

    private PaymentMethod $card;

    private VendChannelError $ok;

    private VendChannelError $sensor;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-09-26 09:00:00');
        Bus::fake();
        $this->operator = Operator::create(['code' => 'HIPL', 'name' => 'Happy Ice', 'gst_vat_rate' => 9, 'timezone' => 'Asia/Singapore']);
        $this->nets = CardTerminal::create(['name' => 'Nets']);
        $this->mls = CardTerminal::create(['name' => 'MLS']);
        $this->vend = $this->machine('4177', $this->nets);
        $this->card = PaymentMethod::firstOrCreate(['code' => PaymentMethod::CODE_CARD_TERMINAL], ['name' => 'Card Terminal', 'is_active' => true]);
        $this->ok = VendChannelError::firstOrCreate(['code' => 0], ['desc' => 'No Malfunction (0)']);
        $this->sensor = VendChannelError::firstOrCreate(['code' => 7], ['desc' => 'Sensor error (7)']);
        CardTerminalUnit::create(['terminal_id' => self::TID, 'card_terminal_id' => $this->nets->id]);
        CardTerminalBinding::create(['provider' => 'nets', 'terminal_id' => self::TID, 'vend_id' => $this->vend->id, 'from_at' => '2026-08-01 00:00:00']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function machine(string $code, ?CardTerminal $company): Vend
    {
        return Vend::forceCreate(['code' => $code, 'operator_id' => $this->operator->id, 'is_active' => 1, 'card_terminal_id' => $company?->id]);
    }

    private function report(string $status = CardSettlementReport::STATUS_UPLOADED, ?string $cutover = null): CardSettlementReport
    {
        return CardSettlementReport::create(['provider' => 'nets', 'original_filename' => 'x.csv', 'status' => $status, 'cutover_date' => $cutover]);
    }

    private function line(CardSettlementReport $r, string $time, int $amount, array $extra = [], string $tid = self::TID, string $date = '2026-09-21'): CardSettlementRow
    {
        static $n = 0;
        $n++;

        return CardSettlementRow::create($extra + [
            'card_settlement_report_id' => $r->id, 'row_no' => $n, 'txn_type' => 'Purchase', 'terminal_id' => $tid,
            'transaction_date' => $date, 'transaction_time' => $time, 'time_is_partial' => false, 'amount_cents' => $amount,
            'fingerprint' => sha1('h-'.$n.uniqid()), 'status' => CardSettlementRow::STATUS_PENDING,
        ]);
    }

    private function sale(Vend $vend, string $at, int $amount, bool $failed = false): VendTransaction
    {
        static $n = 0;
        $n++;

        return VendTransaction::create([
            'order_id' => 'H-'.$n, 'vend_id' => $vend->id, 'transaction_datetime' => $at, 'received_at' => $at,
            'amount' => $amount, 'qty' => 1, 'success_qty' => $failed ? 0 : 1, 'dispensed_qty' => $failed ? 0 : 1,
            'vend_channel_id' => 0, 'vend_channel_error_id' => $failed ? $this->sensor->id : $this->ok->id, 'gst_vat_rate' => 0,
            'payment_method_id' => $this->card->id, 'cashless_mfg' => 'Nets', 'is_found_in_transaction' => true,
        ]);
    }

    /** A failed $amount vend at $at that NETS charged (a matched line, no reversal). */
    private function chargedFailure(string $at, int $amount): VendTransaction
    {
        $failed = $this->sale($this->vend, $at, $amount, true);
        $this->line($this->report(CardSettlementReport::STATUS_SYNCED), Carbon::parse($at)->subSeconds(20)->format('H:i:s'), $amount, [
            'status' => CardSettlementRow::STATUS_MATCHED, 'vend_id' => $this->vend->id, 'matched_vend_transaction_id' => $failed->id,
        ]);

        return $failed;
    }

    public function test_a_nets_line_never_matches_a_sale_on_a_machine_whose_reader_is_not_nets(): void
    {
        // 2700 (MLS): a NETS TID bound there by a coincidence must match nothing.
        $mlsMachine = $this->machine('2700', $this->mls);
        CardTerminalUnit::create(['terminal_id' => '23082802', 'card_terminal_id' => $this->nets->id]);
        CardTerminalBinding::create(['provider' => 'nets', 'terminal_id' => '23082802', 'vend_id' => $mlsMachine->id, 'from_at' => '2026-09-05 00:00:00']);
        $mlsSale = $this->sale($mlsMachine, '2026-09-21 13:26:10', 300);
        $report = $this->report();
        $line = $this->line($report, '13:26:00', 300, [], '23082802');

        app(CardSettlementMatcher::class)->match($report);

        $this->assertNotSame($mlsSale->id, $line->fresh()->matched_vend_transaction_id);
        $this->assertSame(CardSettlementRow::STATUS_UNMATCHED, $line->fresh()->status);
    }

    public function test_a_graze_on_a_non_nets_machine_is_never_a_move_suggestion(): void
    {
        $pax = CardTerminal::create(['name' => 'PAX']);
        $paxMachine = $this->machine('2504', $pax);
        $this->sale($paxMachine, '2026-09-21 18:08:10', 160);
        $report = $this->report();
        $line = $this->line($report, '18:07:59', 160);

        app(CardSettlementMatcher::class)->match($report);

        $this->assertSame(CardSettlementRow::NOTE_NO_SALE_IN_WINDOW, $line->fresh()->resolution_note);
    }

    public function test_the_report_fix_refuses_to_bind_a_nets_terminal_to_a_non_nets_machine(): void
    {
        $mlsMachine = $this->machine('2700', $this->mls);

        $result = app(CardTerminalBindingService::class)->moveToVend(CardTerminalUnit::where('terminal_id', self::TID)->first(), $mlsMachine, '2026-09-21 10:00:00');

        $this->assertFalse($result['moved']);
        $this->assertStringContainsString('card reader is MLS', $result['note']);
    }

    public function test_a_same_amount_retry_after_a_charged_failure_is_linked_as_a_retained_credit_revend(): void
    {
        // 4177, 2026-09-21: $4.60 failed + charged 22:00, $4.60 dispensed 22:01, one line.
        $this->report(CardSettlementReport::STATUS_SYNCED, '2026-09-21');
        $this->report(CardSettlementReport::STATUS_SYNCED, '2026-09-22');
        $failed = $this->chargedFailure('2026-09-21 22:00:02', 460);
        $retry = $this->sale($this->vend, '2026-09-21 22:01:22', 460);
        $retry->forceFill(['card_settlement_state' => 'not_captured'])->save();

        $linked = app(RetainedCreditLinker::class)->linkRevends(Carbon::parse('2026-09-21'), Carbon::parse('2026-09-21 23:59:59'),
            fn ($d) => app(\App\Services\CardSettlement\CardSettlementRefundReconciler::class)->isDayFinal($d));

        $this->assertSame([$retry->id => $failed->id], $linked);
        $retry->refresh();
        $this->assertTrue($retry->is_retained_credit_settlement);
        $this->assertSame($failed->id, (int) $retry->retained_credit_settles_txn_id);
        $this->assertNull($retry->card_settlement_state, 'no longer "No line in NETS"');
    }

    public function test_a_retry_is_not_linked_before_nets_is_final_or_outside_fifteen_minutes(): void
    {
        $this->chargedFailure('2026-09-21 22:00:02', 460);
        $this->sale($this->vend, '2026-09-21 22:01:22', 460);
        $this->sale($this->vend, '2026-09-21 23:30:00', 460);
        $linker = app(RetainedCreditLinker::class);

        $this->assertSame([], $linker->linkRevends(Carbon::parse('2026-09-21'), Carbon::parse('2026-09-21 23:59:59'), fn () => false), 'the next file may still carry its line');
        $linked = $linker->linkRevends(Carbon::parse('2026-09-21'), Carbon::parse('2026-09-21 23:59:59'), fn () => true);
        $this->assertCount(1, $linked, 'one failure funds one retry; 23:30 is 90 min later');
    }

    public function test_a_top_up_line_is_matched_to_the_sale_it_completes(): void
    {
        // 5073, 2026-09-21: $1.70 failed + charged 22:02, $2.40 dispensed 22:06:35, $0.70 line 22:06:39.
        $failed = $this->chargedFailure('2026-09-21 22:02:01', 170);
        $sale = $this->sale($this->vend, '2026-09-21 22:06:35', 240);
        $report = $this->report();
        $topUp = $this->line($report, '22:06:39', 70);

        app(CardSettlementMatcher::class)->match($report);

        $topUp->refresh();
        $this->assertSame($sale->id, $topUp->matched_vend_transaction_id);
        $this->assertSame(CardSettlementRow::NOTE_MATCHED_TOP_UP, $topUp->resolution_note);
        $this->assertTrue($sale->fresh()->is_retained_credit_settlement);
        $this->assertSame($failed->id, (int) $sale->fresh()->retained_credit_settles_txn_id);
    }

    public function test_an_existing_top_up_na_row_is_repaired_onto_its_sale(): void
    {
        $failed = $this->chargedFailure('2026-09-21 22:02:01', 170);
        $report = $this->report(CardSettlementReport::STATUS_SYNCED);
        $this->line($report, '22:06:39', 70, ['status' => CardSettlementRow::STATUS_UNMATCHED, 'vend_id' => $this->vend->id, 'resolution_note' => CardSettlementRow::NOTE_NO_SALE_IN_WINDOW]);
        app(CardSettlementOrphanSales::class)->createForReport($report);
        $sale = $this->sale($this->vend, '2026-09-21 22:06:35', 240);

        $repair = app(CardSettlementOrphanRepair::class);
        $plan = $repair->plan(Carbon::parse('2026-09-21'), Carbon::parse('2026-09-21 23:59:59'));
        $this->assertSame($sale->id, $plan[0]['sale']?->id);
        $repair->apply($plan[0]);

        $this->assertSame(0, VendTransaction::withoutGlobalScopes()->whereNotNull('card_settlement_row_id')->where('is_found_in_transaction', false)->count(), 'no $0.70 NA sale left');
        $this->assertSame($failed->id, (int) $sale->fresh()->retained_credit_settles_txn_id);
    }

    public function test_a_refund_claim_on_a_failure_whose_customer_got_the_item_on_retry_is_flagged(): void
    {
        $failed = $this->chargedFailure('2026-09-21 22:00:02', 460);
        $retry = $this->sale($this->vend, '2026-09-21 22:01:22', 460);
        app(RetainedCreditLinker::class)->link($retry->id, $failed->id, 're-vend');
        $ticket = RefundTicket::create([
            'reference' => 'RF-TEST', 'vend_code' => '4177', 'vend_id' => $this->vend->id, 'vend_transaction_id' => $failed->id,
            'order_id' => $failed->order_id, 'claimed_amount_cents' => 460, 'status' => RefundTicket::STATUS_SUBMITTED,
        ]);

        $sections = collect(app(CardSettlementHealthCheck::class)->run())->keyBy('key');

        $this->assertStringContainsString('RF-TEST', $sections['refunds_on_retries']['items'][0]['text']);
        $this->assertStringEndsWith('/refunds/'.$ticket->id, $sections['refunds_on_retries']['items'][0]['url']);
    }

    public function test_strong_evidence_moves_the_terminal_and_the_report_syncs_itself(): void
    {
        // TID bound to 4177 but its three lines fit sales on 2616 (a NETS machine).
        $target = $this->machine('2616', $this->nets);
        foreach (['10:00:12', '12:00:12', '14:00:12'] as $t) {
            $this->sale($target, '2026-09-21 '.$t, 160);
        }
        $report = $this->report();
        foreach (['10:00:00', '12:00:00', '14:00:00'] as $t) {
            $this->line($report, $t, 160);
        }

        app()->call([new MatchCardSettlementReport($report->id), 'handle']);

        $this->assertSame(self::TID, CardTerminalBinding::terminalIdAt($target->id, Carbon::parse('2026-09-21 13:00')));
        $this->assertSame(3, $report->rows()->where('status', CardSettlementRow::STATUS_MATCHED)->count(), 'rematched onto 2616');
        $this->assertSame(CardSettlementReport::STATUS_SYNCED, $report->fresh()->status);
    }

    public function test_weak_evidence_does_not_move_anything(): void
    {
        $target = $this->machine('2616', $this->nets);
        foreach (['10:00:12', '12:00:12'] as $t) {
            $this->sale($target, '2026-09-21 '.$t, 160);
        }
        $report = $this->report();
        foreach (['10:00:00', '12:00:00'] as $t) {
            $this->line($report, $t, 160);
        }

        app()->call([new MatchCardSettlementReport($report->id), 'handle']);

        $this->assertSame(0, CardTerminalBinding::where('vend_id', $target->id)->count(), 'two lines: a human decides');
        $this->assertSame(CardSettlementReport::STATUS_SYNCED, $report->fresh()->status, 'the queries do not block the Sync');
    }

    public function test_the_nightly_check_is_silent_when_nothing_needs_a_person(): void
    {
        Mail::fake();
        foreach (range(2, 14) as $ago) {
            $this->report(CardSettlementReport::STATUS_SYNCED, now()->subDays($ago)->toDateString());
        }

        $this->artisan('card-settlement:health')->expectsOutputToContain('Nothing needs a person')->assertSuccessful();

        Mail::assertNothingQueued();
    }

    public function test_the_nightly_check_emails_the_hipl_alert_list_when_something_does(): void
    {
        Mail::fake();
        AlertEmailItem::create(['email' => 'ops@example.com', 'is_active' => true, 'operator_id' => $this->operator->id]);
        AlertEmailItem::create(['email' => 'other@example.com', 'is_active' => true, 'operator_id' => null]);
        // Only 2026-09-20 missing among the last two weeks of files.
        foreach (range(2, 14) as $ago) {
            if (now()->subDays($ago)->toDateString() !== '2026-09-20') {
                $this->report(CardSettlementReport::STATUS_SYNCED, now()->subDays($ago)->toDateString());
            }
        }

        $this->artisan('card-settlement:health')->expectsOutputToContain('No NETS file uploaded for 2026-09-20')->assertSuccessful();

        Mail::assertQueued(CardSettlementHealthMail::class, fn ($m) => $m->hasTo('ops@example.com'));
        Mail::assertNotQueued(CardSettlementHealthMail::class, fn ($m) => $m->hasTo('other@example.com'));
    }

    public function test_the_nightly_check_ignores_test_amounts_and_one_day_legacy_overlaps(): void
    {
        // A $0.20 unbound-terminal line and a same-day swap overlap: noise, not work.
        $this->line($this->report(CardSettlementReport::STATUS_SYNCED), '10:00:00', 20, ['status' => CardSettlementRow::STATUS_UNMATCHED, 'resolution_note' => 'No terminal binding'], '23104113', now()->subDays(3)->toDateString());
        $other = $this->machine('2847', $this->nets);
        CardTerminalBinding::create(['provider' => 'nets', 'terminal_id' => '23104097', 'vend_id' => $this->vend->id, 'bound_from' => '2026-05-17', 'bound_until' => '2026-09-23']);
        CardTerminalBinding::create(['provider' => 'nets', 'terminal_id' => '23104097', 'vend_id' => $other->id, 'bound_from' => '2026-09-23']);

        $keys = collect(app(CardSettlementHealthCheck::class)->run())->pluck('key');

        $this->assertNotContains('pending_moves', $keys);
        $this->assertNotContains('double_bound', $keys);

        // A real overlap (weeks) is reported.
        CardTerminalBinding::create(['provider' => 'nets', 'terminal_id' => '90602207', 'vend_id' => $this->vend->id, 'from_at' => '2026-03-19 00:00:00', 'until_at' => '2026-09-16 15:25:30']);
        CardTerminalBinding::create(['provider' => 'nets', 'terminal_id' => '90602207', 'vend_id' => $other->id, 'from_at' => '2026-08-31 00:00:00', 'until_at' => '2026-09-22 00:00:00']);
        $this->assertContains('double_bound', collect(app(CardSettlementHealthCheck::class)->run())->pluck('key'));
    }

    public function test_the_nightly_repair_releases_lines_whose_swept_test_sale_is_gone(): void
    {
        $report = $this->report(CardSettlementReport::STATUS_SYNCED);
        $line = $this->line($report, '10:00:00', 10, ['status' => CardSettlementRow::STATUS_MATCHED, 'vend_id' => $this->vend->id, 'matched_vend_transaction_id' => 999999]);

        $this->artisan('card-settlement:repair-orphans --apply --from=2026-09-01')->assertSuccessful();

        $this->assertSame(CardSettlementRow::STATUS_IGNORED, $line->fresh()->status);
        $this->assertSame(CardSettlementRow::NOTE_TEST_AMOUNT, $line->fresh()->resolution_note);
    }
}
