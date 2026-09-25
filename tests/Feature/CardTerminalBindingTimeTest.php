<?php

namespace Tests\Feature;

use App\Models\CardSettlementReport;
use App\Models\CardSettlementRow;
use App\Models\CardTerminal;
use App\Models\CardTerminalBinding;
use App\Models\CardTerminalUnit;
use App\Models\Operator;
use App\Models\PaymentMethod;
use App\Models\User;
use App\Models\Vend;
use App\Models\VendTransaction;
use App\Services\CardSettlement\CardSettlementMatcher;
use App\Services\CardSettlement\CardTerminalBindingService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Bindings to the second (2026-09-25): a 14:30 swap splits the day, and NETS
 * evidence never overrides a change a person recorded.
 */
class CardTerminalBindingTimeTest extends TestCase
{
    use RefreshDatabase;

    private Vend $v2831;

    private Vend $v2857;

    private PaymentMethod $card;

    protected function setUp(): void
    {
        parent::setUp();
        $op = Operator::create(['code' => 'OP1', 'name' => 'Op', 'gst_vat_rate' => 9, 'timezone' => 'Asia/Singapore']);
        $this->v2831 = Vend::create(['code' => '2831', 'operator_id' => $op->id, 'is_active' => 1]);
        $this->v2857 = Vend::create(['code' => '2857', 'operator_id' => $op->id, 'is_active' => 1]);
        $this->card = PaymentMethod::firstOrCreate(['code' => PaymentMethod::CODE_CARD_TERMINAL], ['name' => 'Card Terminal', 'is_active' => true]);
        $nets = CardTerminal::create(['name' => 'Nets']);
        foreach (['23082817', '90602210', '23000009'] as $tid) {
            CardTerminalUnit::create(['terminal_id' => $tid, 'card_terminal_id' => $nets->id]);
        }
    }

    private function unit(string $tid): CardTerminalUnit
    {
        return CardTerminalUnit::where('terminal_id', $tid)->firstOrFail();
    }

    private function sale(Vend $vend, string $at, int $amount = 200): VendTransaction
    {
        static $n = 0;
        $n++;

        return VendTransaction::create([
            'order_id' => 'T-'.$n, 'vend_id' => $vend->id, 'transaction_datetime' => $at, 'received_at' => $at,
            'amount' => $amount, 'qty' => 1, 'success_qty' => 1, 'dispensed_qty' => 1, 'vend_channel_id' => 0, 'gst_vat_rate' => 0,
            'payment_method_id' => $this->card->id, 'cashless_mfg' => 'Nets', 'is_found_in_transaction' => true,
        ]);
    }

    private function line(CardSettlementReport $r, string $tid, string $date, string $time, int $amount = 200): CardSettlementRow
    {
        static $n = 0;
        $n++;

        return CardSettlementRow::create([
            'card_settlement_report_id' => $r->id, 'row_no' => $n, 'txn_type' => 'Purchase', 'terminal_id' => $tid,
            'transaction_date' => $date, 'transaction_time' => $time, 'time_is_partial' => false, 'amount_cents' => $amount,
            'fingerprint' => sha1('t-'.$n.uniqid()), 'status' => CardSettlementRow::STATUS_PENDING,
        ]);
    }

    public function test_a_mid_day_swap_sends_the_mornings_lines_to_the_old_machine(): void
    {
        // 23082817 sat on 2857 until 14:30, then moved to 2831.
        CardTerminalBinding::create(['provider' => 'nets', 'terminal_id' => '23082817', 'vend_id' => $this->v2857->id,
            'from_at' => '2026-09-01 00:00:00', 'until_at' => '2026-09-20 14:30:00']);
        CardTerminalBinding::create(['provider' => 'nets', 'terminal_id' => '23082817', 'vend_id' => $this->v2831->id,
            'from_at' => '2026-09-20 14:30:00']);
        $morning = $this->sale($this->v2857, '2026-09-20 10:00:12');
        $afternoon = $this->sale($this->v2831, '2026-09-20 16:00:12');
        $report = CardSettlementReport::create(['provider' => 'nets', 'original_filename' => 'x.csv', 'status' => CardSettlementReport::STATUS_UPLOADED]);
        $a = $this->line($report, '23082817', '2026-09-20', '10:00:00');
        $b = $this->line($report, '23082817', '2026-09-20', '16:00:00');

        app(CardSettlementMatcher::class)->match($report);

        $this->assertSame($morning->id, $a->fresh()->matched_vend_transaction_id);
        $this->assertSame($afternoon->id, $b->fresh()->matched_vend_transaction_id);
    }

    public function test_report_evidence_never_overrides_a_change_a_person_recorded_later(): void
    {
        // 2831 on 2026-09-24: a person fitted 90602210 at 11:43 — the old form
        // back-dated it to 2025-10-23. The 09-21 report then shows 23082817
        // selling on 2831 at 22:34.
        $person = User::factory()->create();
        Carbon::setTestNow('2026-09-24 11:43:27');
        $manual = CardTerminalBinding::create(['provider' => 'nets', 'terminal_id' => '90602210', 'vend_id' => $this->v2831->id,
            'from_at' => '2025-10-23 00:00:00', 'created_by' => $person->id]);
        Carbon::setTestNow('2026-09-24 15:43:00');

        $result = app(CardTerminalBindingService::class)->moveToVend($this->unit('23082817'), $this->v2831, '2026-09-21 22:34:58');

        $this->assertTrue($result['moved'], $result['note']);
        $segment = CardTerminalBinding::where('terminal_id', '23082817')->firstOrFail();
        $this->assertSame('2026-09-21 22:34:58', $segment->from_at->format('Y-m-d H:i:s'));
        $this->assertSame('2026-09-24 11:43:27', $segment->until_at->format('Y-m-d H:i:s'), 'ends where the person recorded their change');
        $manual->refresh();
        $this->assertSame('2026-09-24 11:43:27', $manual->from_at->format('Y-m-d H:i:s'), 'theirs from the moment they recorded it');
        $this->assertNull($manual->until_at, 'the person\'s binding stays open');
        $this->assertSame('90602210', CardTerminalBinding::terminalIdAt($this->v2831->id, Carbon::parse('2026-09-25 09:00')));
        Carbon::setTestNow();
    }

    public function test_evidence_contradicting_a_change_recorded_before_it_is_left_for_a_human(): void
    {
        $person = User::factory()->create();
        Carbon::setTestNow('2026-09-20 10:00:00');
        CardTerminalBinding::create(['provider' => 'nets', 'terminal_id' => '90602210', 'vend_id' => $this->v2831->id,
            'from_at' => '2026-09-20 10:00:00', 'created_by' => $person->id]);
        Carbon::setTestNow('2026-09-22 09:00:00');

        $result = app(CardTerminalBindingService::class)->moveToVend($this->unit('23082817'), $this->v2831, '2026-09-20 15:10:00');

        $this->assertFalse($result['moved']);
        $this->assertStringContainsString('check which is right', $result['note']);
        $this->assertSame(0, CardTerminalBinding::where('terminal_id', '23082817')->count());
        Carbon::setTestNow();
    }

    public function test_the_repair_puts_a_back_dated_change_where_the_evidence_says_it_happened(): void
    {
        // Imported: 23082817 on 2831 since 2025-10-23. Saved 2026-09-24 11:43 by a
        // person: 90602210 "from 2025-10-23", which closed the import row that day.
        $person = User::factory()->create();
        $import = CardTerminalBinding::create(['provider' => 'nets', 'terminal_id' => '23082817', 'vend_id' => $this->v2831->id,
            'bound_from' => '2025-10-23', 'source' => CardTerminalBinding::SOURCE_IMPORT]);
        Carbon::setTestNow('2026-09-24 11:43:27');
        $import->update(['bound_until' => '2025-10-23']);
        $manual = CardTerminalBinding::create(['provider' => 'nets', 'terminal_id' => '90602210', 'vend_id' => $this->v2831->id,
            'bound_from' => '2025-10-23', 'created_by' => $person->id]);
        // Later, a report fix put 23082817 back from 09-21, closing the person's row.
        Carbon::setTestNow('2026-09-24 15:43:24');
        $manual->update(['until_at' => '2026-09-21 00:00:00']);
        $reportRow = CardTerminalBinding::create(['provider' => 'nets', 'terminal_id' => '23082817', 'vend_id' => $this->v2831->id,
            'from_at' => '2026-09-21 00:00:00', 'source' => CardTerminalBinding::SOURCE_REPORT]);
        // Evidence: 23082817 sold on 2831 up to 09-22 23:12; 90602210 has no line there.
        $r = CardSettlementReport::create(['provider' => 'nets', 'original_filename' => 'x.csv', 'status' => CardSettlementReport::STATUS_SYNCED]);
        $s = $this->sale($this->v2831, '2026-09-22 23:12:40');
        $this->line($r, '23082817', '2026-09-22', '23:12:36')->update(['status' => CardSettlementRow::STATUS_MATCHED, 'vend_id' => $this->v2831->id, 'matched_vend_transaction_id' => $s->id]);
        Carbon::setTestNow('2026-09-25 12:00:00');

        $this->artisan('card-settlement:repair-backdated-bindings --apply')->assertSuccessful();

        $this->assertSame('2026-09-24 11:43:27', $import->fresh()->until_at->format('Y-m-d H:i:s'), 'the old terminal stays until the recorded change');
        $this->assertSame('2026-09-24 11:43:27', $manual->fresh()->from_at->format('Y-m-d H:i:s'));
        $this->assertNull($manual->fresh()->until_at, 'reopened: nothing shows 23082817 selling after the change');
        $this->assertSame('2026-09-24 11:43:27', $reportRow->fresh()->until_at->format('Y-m-d H:i:s'), 'the report row no longer runs past the person');
        $this->assertSame('23082817', CardTerminalBinding::terminalIdAt($this->v2831->id, Carbon::parse('2026-08-15 12:00')), 'August resolves to the terminal that was really there');
        $this->assertSame('90602210', CardTerminalBinding::terminalIdAt($this->v2831->id, Carbon::parse('2026-09-24 12:00')));
        Carbon::setTestNow();
    }

    public function test_a_report_row_for_the_same_terminal_from_midnight_is_collapsed(): void
    {
        // 2401 shape: the report opened the NEW terminal from 09-14 00:00; the old
        // one sold until 07:53; the person saved the change at 10:33.
        $person = User::factory()->create();
        $import = CardTerminalBinding::create(['provider' => 'nets', 'terminal_id' => '23082817', 'vend_id' => $this->v2831->id,
            'bound_from' => '2025-12-03', 'source' => CardTerminalBinding::SOURCE_IMPORT]);
        Carbon::setTestNow('2026-09-14 10:33:14');
        $import->update(['bound_until' => '2025-12-03']);
        $manual = CardTerminalBinding::create(['provider' => 'nets', 'terminal_id' => '90602210', 'vend_id' => $this->v2831->id,
            'bound_from' => '2025-12-03', 'created_by' => $person->id]);
        Carbon::setTestNow('2026-09-22 12:41:15');
        $manual->update(['until_at' => '2026-09-14 00:00:00']);
        $dup = CardTerminalBinding::create(['provider' => 'nets', 'terminal_id' => '90602210', 'vend_id' => $this->v2831->id,
            'from_at' => '2026-09-14 00:00:00', 'source' => CardTerminalBinding::SOURCE_REPORT]);
        Carbon::setTestNow('2026-09-25 12:00:00');

        $this->artisan('card-settlement:repair-backdated-bindings --apply')->assertSuccessful();

        $this->assertSame(1, CardTerminalBinding::where('terminal_id', '90602210')->whereNull('until_at')->count(), 'one open binding per terminal');
        $this->assertSame('23082817', CardTerminalBinding::terminalIdAt($this->v2831->id, Carbon::parse('2026-09-14 08:00')), 'the morning is the old terminal\'s');
        $this->assertSame('90602210', CardTerminalBinding::terminalIdAt($this->v2831->id, Carbon::parse('2026-09-14 10:34')));
        $this->assertTrue($dup->fresh()->until_at->equalTo($dup->fresh()->from_at));
        Carbon::setTestNow();
    }

    public function test_a_disputed_change_still_gets_its_history_before_the_save_repaired(): void
    {
        // 2321: imported 23082817 since March; a person saved 90602210 on 09-13
        // 17:09 "from March"; report fixes then put 23082817 back from 09-10 and
        // a third terminal selling there on 09-16 — the later period is disputed.
        $person = User::factory()->create();
        $import = CardTerminalBinding::create(['provider' => 'nets', 'terminal_id' => '23082817', 'vend_id' => $this->v2831->id,
            'bound_from' => '2026-03-28', 'source' => CardTerminalBinding::SOURCE_IMPORT]);
        Carbon::setTestNow('2026-09-13 17:09:52');
        $import->update(['bound_until' => '2026-03-28']);
        $manual = CardTerminalBinding::create(['provider' => 'nets', 'terminal_id' => '90602210', 'vend_id' => $this->v2831->id,
            'bound_from' => '2026-03-28', 'created_by' => $person->id]);
        Carbon::setTestNow('2026-09-14 18:01:30');
        $manual->update(['until_at' => '2026-09-10 00:00:00']);
        CardTerminalBinding::create(['provider' => 'nets', 'terminal_id' => '23082817', 'vend_id' => $this->v2831->id,
            'from_at' => '2026-09-10 00:00:00', 'until_at' => '2026-09-16 00:00:00', 'source' => CardTerminalBinding::SOURCE_REPORT]);
        CardTerminalBinding::create(['provider' => 'nets', 'terminal_id' => '23000009', 'vend_id' => $this->v2831->id,
            'from_at' => '2026-09-16 00:00:00', 'source' => CardTerminalBinding::SOURCE_REPORT]);
        $r = CardSettlementReport::create(['provider' => 'nets', 'original_filename' => 'x.csv', 'status' => CardSettlementReport::STATUS_SYNCED]);
        $s = $this->sale($this->v2831, '2026-09-16 11:24:10');
        $this->line($r, '23000009', '2026-09-16', '11:24:00')->update(['status' => CardSettlementRow::STATUS_MATCHED, 'vend_id' => $this->v2831->id, 'matched_vend_transaction_id' => $s->id]);
        Carbon::setTestNow('2026-09-25 12:00:00');

        $this->artisan('card-settlement:repair-backdated-bindings --apply')->assertSuccessful();

        $this->assertSame('23082817', CardTerminalBinding::terminalIdAt($this->v2831->id, Carbon::parse('2026-08-15 12:00')), 'August no longer resolves to the back-dated terminal');
        $this->assertSame('23000009', CardTerminalBinding::terminalIdAt($this->v2831->id, Carbon::parse('2026-09-17 12:00')), 'the disputed period is left as it was');
        Carbon::setTestNow();
    }
}
