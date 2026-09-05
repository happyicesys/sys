<?php

namespace Tests\Feature;

use App\Jobs\MatchCardSettlementReport;
use App\Models\CardSettlementReport;
use App\Models\CardSettlementRow;
use App\Models\CardTerminal;
use App\Models\CardTerminalBinding;
use App\Models\CardTerminalUnit;
use App\Models\Customer;
use App\Models\ProductMapping;
use App\Models\User;
use App\Models\Vend;
use App\Models\VendTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * "Move N terminals & rematch" on the Card Settlement report page — the
 * one-click form of the manual "rebind it from the right machine's Settings
 * page" instruction.
 *
 * A binding is dated and global, so a wrong move mis-resolves settled reports
 * for other months. Everything pinned here is a guard on that: the new binding
 * starts no earlier than the evidence, the old one is closed rather than
 * rewritten, and anything ambiguous is skipped with a reason instead of forced.
 */
class CardSettlementFixBindingsTest extends TestCase
{
    use RefreshDatabase;

    private const TID = '23100719';

    private CardTerminal $nets;

    protected function setUp(): void
    {
        parent::setUp();
        $this->nets = CardTerminal::create(['name' => 'Nets']);
        Queue::fake();
    }

    private function staff(): User
    {
        foreach (['read card-settlements', 'update card-settlements'] as $permission) {
            Permission::findOrCreate($permission, 'web');
        }
        $user = User::factory()->create();
        $user->givePermissionTo(['read card-settlements', 'update card-settlements']);

        return $user;
    }

    private function makeVend(int $code): Vend
    {
        $customer = Customer::create([
            'name' => "Site {$code}", 'code' => $code, 'operator_id' => 1,
            'status_id' => Customer::STATUS_ACTIVE,
        ]);
        $mapping = ProductMapping::forceCreate(['name' => "Mapping {$code}"]);

        return Vend::forceCreate([
            'code' => $code, 'customer_id' => $customer->id,
            'product_mapping_id' => $mapping->id, 'operator_id' => 1,
        ]);
    }

    private function report(): CardSettlementReport
    {
        return CardSettlementReport::create([
            'provider' => 'nets',
            'original_filename' => 'test.csv',
            'status' => CardSettlementReport::STATUS_REVIEW,
        ]);
    }

    /**
     * A line the matcher parked as "the sale exists, but on another machine" —
     * the only shape suspectBindings() reads.
     */
    private function suspectRow(CardSettlementReport $report, Vend $boundTo, int $suggestedCode, string $date, string $terminalId = self::TID): CardSettlementRow
    {
        static $n = 0;
        $n++;

        return CardSettlementRow::create([
            'card_settlement_report_id' => $report->id,
            'row_no' => $n,
            'txn_type' => 'Purchase',
            'terminal_id' => $terminalId,
            'transaction_date' => $date,
            'transaction_time' => '22:30:58',
            'time_is_partial' => false,
            'amount_cents' => 240,
            'fingerprint' => sha1('fix-'.$n.uniqid()),
            'status' => CardSettlementRow::STATUS_UNMATCHED,
            'vend_id' => $boundTo->id,
            'candidates_json' => [[
                'vend_transaction_id' => 900 + $n,
                'vend_code' => $suggestedCode,
                'other_vend' => true,
            ]],
            'resolution_note' => 'No matching sale on bound machine — found on machine '.$suggestedCode,
        ]);
    }

    /** A line already matched to a sale on `$onVend`, optionally Sync-stamped. */
    private function matchedRow(CardSettlementReport $report, Vend $onVend, string $date, bool $synced = false): CardSettlementRow
    {
        static $n = 0;
        $n++;

        $txn = VendTransaction::create([
            'order_id' => 'FIX-'.$n.'-'.uniqid(),
            'vend_id' => $onVend->id,
            'transaction_datetime' => $date.' 22:31:07',
            'amount' => 240,
            'qty' => 1,
            'success_qty' => 1,
            'dispensed_qty' => 1,
            'vend_channel_id' => 0,
            'gst_vat_rate' => 0,
        ]);

        // Not fillable — CardSettlementSyncService stamps it with a query
        // update, so the test has to write it the same way.
        if ($synced) {
            VendTransaction::withoutGlobalScopes()->whereKey($txn->id)
                ->update(['card_settlement_synced_at' => now()]);
        }

        return CardSettlementRow::create([
            'card_settlement_report_id' => $report->id,
            'row_no' => 5000 + $n,
            'txn_type' => 'Purchase',
            'terminal_id' => self::TID,
            'transaction_date' => $date,
            'transaction_time' => '22:30:58',
            'time_is_partial' => false,
            'amount_cents' => 240,
            'fingerprint' => sha1('matched-'.$n.uniqid()),
            'status' => CardSettlementRow::STATUS_MATCHED,
            'vend_id' => $onVend->id,
            'matched_vend_transaction_id' => $txn->id,
        ]);
    }

    private function unit(string $terminalId = self::TID): CardTerminalUnit
    {
        return CardTerminalUnit::create([
            'terminal_id' => $terminalId, 'card_terminal_id' => $this->nets->id,
        ]);
    }

    public function test_it_closes_the_wrong_binding_and_opens_the_right_one_from_the_first_proven_date(): void
    {
        $wrong = $this->makeVend(2443);
        $right = $this->makeVend(2518);
        $this->unit();
        CardTerminalBinding::create([
            'provider' => 'nets', 'terminal_id' => self::TID,
            'vend_id' => $wrong->id, 'bound_from' => '2025-09-26',
        ]);

        $report = $this->report();
        $this->suspectRow($report, $wrong, 2518, '2026-08-20');
        $this->suspectRow($report, $wrong, 2518, '2026-08-15');

        $this->actingAs($this->staff())
            ->post('/card-settlements/'.$report->id.'/fix-bindings')
            ->assertRedirect();

        // Old row CLOSED, never rewritten: a July report must still resolve to
        // the machine the terminal was actually on then.
        $old = CardTerminalBinding::where('vend_id', $wrong->id)->firstOrFail();
        $this->assertSame('2025-09-26', $old->bound_from->toDateString());
        $this->assertSame('2026-08-15', $old->bound_until->toDateString());

        $new = CardTerminalBinding::where('vend_id', $right->id)->firstOrFail();
        $this->assertSame(self::TID, $new->terminal_id);
        $this->assertSame('nets', $new->provider);
        $this->assertSame('2026-08-15', $new->bound_from->toDateString());
        $this->assertNull($new->bound_until);

        Queue::assertPushed(MatchCardSettlementReport::class);
    }

    public function test_the_date_comes_from_the_lines_that_fit_the_suggested_machine_only(): void
    {
        $wrong = $this->makeVend(2443);
        $right = $this->makeVend(2518);
        $this->makeVend(2900);
        $this->unit();
        CardTerminalBinding::create([
            'provider' => 'nets', 'terminal_id' => self::TID,
            'vend_id' => $wrong->id, 'bound_from' => '2025-09-26',
        ]);

        $report = $this->report();
        $this->suspectRow($report, $wrong, 2518, '2026-08-20');
        $this->suspectRow($report, $wrong, 2518, '2026-08-22');
        // An older line pointing at a THIRD machine must not drag the new
        // binding back over a period this move cannot account for.
        $this->suspectRow($report, $wrong, 2900, '2026-07-01');

        $this->actingAs($this->staff())->post('/card-settlements/'.$report->id.'/fix-bindings');

        $new = CardTerminalBinding::where('vend_id', $right->id)->firstOrFail();
        $this->assertSame('2026-08-20', $new->bound_from->toDateString());
    }

    public function test_a_report_uploaded_out_of_order_pulls_an_existing_binding_back_instead_of_doing_nothing(): void
    {
        $wrong = $this->makeVend(2443);
        $right = $this->makeVend(2518);
        $this->unit();
        // The newest report was processed first, so the terminal is already on
        // the right machine — but only from 2026-08-20.
        CardTerminalBinding::create([
            'provider' => 'nets', 'terminal_id' => self::TID,
            'vend_id' => $wrong->id, 'bound_from' => '2025-09-26', 'bound_until' => '2026-08-20',
        ]);
        $current = CardTerminalBinding::create([
            'provider' => 'nets', 'terminal_id' => self::TID,
            'vend_id' => $right->id, 'bound_from' => '2026-08-20',
        ]);

        $report = $this->report();
        $this->suspectRow($report, $wrong, 2518, '2026-08-25');

        $this->actingAs($this->staff())->post('/card-settlements/'.$report->id.'/fix-bindings');

        // 2026-08-25 is already inside the binding — nothing to widen, and the
        // start date must not be pushed forward.
        $this->assertSame('2026-08-20', $current->fresh()->bound_from->toDateString());

        // Now an OLDER report proves it was there earlier, into a window the
        // closed binding does not claim.
        CardTerminalBinding::where('vend_id', $wrong->id)->update(['bound_until' => '2026-08-01']);
        $older = $this->report();
        $this->suspectRow($older, $wrong, 2518, '2026-08-10');

        $this->actingAs($this->staff())->post('/card-settlements/'.$older->id.'/fix-bindings');

        $this->assertSame('2026-08-10', $current->fresh()->bound_from->toDateString());
    }

    public function test_it_refuses_to_widen_over_a_period_another_binding_already_claims(): void
    {
        $wrong = $this->makeVend(2443);
        $right = $this->makeVend(2518);
        $this->unit();
        CardTerminalBinding::create([
            'provider' => 'nets', 'terminal_id' => self::TID,
            'vend_id' => $wrong->id, 'bound_from' => '2025-09-26', 'bound_until' => '2026-08-20',
        ]);
        $current = CardTerminalBinding::create([
            'provider' => 'nets', 'terminal_id' => self::TID,
            'vend_id' => $right->id, 'bound_from' => '2026-08-20',
        ]);

        $report = $this->report();
        $this->suspectRow($report, $wrong, 2518, '2026-08-10'); // inside the closed binding

        $this->actingAs($this->staff())
            ->post('/card-settlements/'.$report->id.'/fix-bindings')
            ->assertSessionHas('message', fn ($m) => str_contains($m, 'an earlier binding covers 2026-08-10'));

        $this->assertSame('2026-08-20', $current->fresh()->bound_from->toDateString());
        Queue::assertNotPushed(MatchCardSettlementReport::class);
    }

    public function test_a_terminal_with_no_card_terminal_record_is_skipped_with_its_reason(): void
    {
        $wrong = $this->makeVend(2443);
        $this->makeVend(2518);
        CardTerminalBinding::create([
            'provider' => 'nets', 'terminal_id' => self::TID,
            'vend_id' => $wrong->id, 'bound_from' => '2025-09-26',
        ]);

        $report = $this->report();
        $this->suspectRow($report, $wrong, 2518, '2026-08-15');

        $this->actingAs($this->staff())
            ->post('/card-settlements/'.$report->id.'/fix-bindings')
            ->assertSessionHas('message', fn ($m) => str_contains($m, 'no terminal record'));

        $this->assertSame(1, CardTerminalBinding::count());
        Queue::assertNotPushed(MatchCardSettlementReport::class);
    }

    public function test_an_unknown_suggested_machine_is_skipped_rather_than_guessed(): void
    {
        $wrong = $this->makeVend(2443);
        $this->unit();
        CardTerminalBinding::create([
            'provider' => 'nets', 'terminal_id' => self::TID,
            'vend_id' => $wrong->id, 'bound_from' => '2025-09-26',
        ]);

        $report = $this->report();
        $this->suspectRow($report, $wrong, 9999, '2026-08-15');

        $this->actingAs($this->staff())
            ->post('/card-settlements/'.$report->id.'/fix-bindings')
            ->assertSessionHas('message', fn ($m) => str_contains($m, 'machine 9999 not found'));

        $this->assertNull(CardTerminalBinding::where('terminal_id', self::TID)->first()->bound_until);
    }

    /**
     * The panel must show what a move COSTS, not just what it fixes — counted
     * across every report from that date, because a binding is global.
     */
    public function test_the_panel_reports_what_the_move_would_fix_and_break(): void
    {
        $wrong = $this->makeVend(2443);
        $right = $this->makeVend(2518);
        $this->unit();
        CardTerminalBinding::create([
            'provider' => 'nets', 'terminal_id' => self::TID,
            'vend_id' => $wrong->id, 'bound_from' => '2025-09-26',
        ]);

        $report = $this->report();
        $this->suspectRow($report, $wrong, 2518, '2026-08-15');
        $this->suspectRow($report, $wrong, 2518, '2026-08-16');

        // Matched on the OLD machine, inside the window: the move takes its
        // binding away, so it falls back to a query.
        $this->matchedRow($report, $wrong, '2026-08-17');
        // Matched already on the machine we are moving TO: survives untouched.
        $this->matchedRow($report, $right, '2026-08-18');
        // Before the move date: outside the blast radius entirely.
        $this->matchedRow($report, $wrong, '2026-07-01');

        $this->actingAs($this->staff())
            ->get('/card-settlements/'.$report->id)
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('suspectBindings.0.would_fix', 2)
                ->where('suspectBindings.0.would_break', 1)
                ->where('suspectBindings.0.would_break_synced', 0)
            );
    }

    public function test_it_will_not_break_an_already_synced_line_unattended(): void
    {
        $wrong = $this->makeVend(2443);
        $right = $this->makeVend(2518);
        $this->unit();
        CardTerminalBinding::create([
            'provider' => 'nets', 'terminal_id' => self::TID,
            'vend_id' => $wrong->id, 'bound_from' => '2025-09-26',
        ]);

        $report = $this->report();
        $this->suspectRow($report, $wrong, 2518, '2026-08-15');
        $this->matchedRow($report, $wrong, '2026-08-17', synced: true);

        $this->actingAs($this->staff())
            ->post('/card-settlements/'.$report->id.'/fix-bindings')
            ->assertSessionHas('message', fn ($m) => str_contains($m, 'would break 1 already-synced line'));

        // Binding untouched, and no rematch that would strand the settled line.
        $this->assertNull(CardTerminalBinding::where('terminal_id', self::TID)->first()->bound_until);
        $this->assertSame(0, CardTerminalBinding::where('vend_id', $right->id)->count());
        Queue::assertNotPushed(MatchCardSettlementReport::class);
    }

    /**
     * The panel's ticks decide which suggestions run. An unticked terminal —
     * typically one the impact badge says would break more than it fixes —
     * must be left exactly as it was.
     */
    public function test_only_the_ticked_terminals_are_moved(): void
    {
        $wrong = $this->makeVend(2443);
        $right = $this->makeVend(2518);
        $otherWrong = $this->makeVend(2871);
        $otherRight = $this->makeVend(2337);
        $this->unit();
        $this->unit('23107352');

        CardTerminalBinding::create([
            'provider' => 'nets', 'terminal_id' => self::TID,
            'vend_id' => $wrong->id, 'bound_from' => '2025-09-26',
        ]);
        $untouched = CardTerminalBinding::create([
            'provider' => 'nets', 'terminal_id' => '23107352',
            'vend_id' => $otherWrong->id, 'bound_from' => '2025-09-26',
        ]);

        $report = $this->report();
        $this->suspectRow($report, $wrong, 2518, '2026-09-01');
        $this->suspectRow($report, $otherWrong, 2337, '2026-09-01', terminalId: '23107352');

        $this->actingAs($this->staff())
            ->post('/card-settlements/'.$report->id.'/fix-bindings', ['terminal_ids' => [self::TID]])
            ->assertRedirect();

        // Ticked one moved...
        $this->assertSame(1, CardTerminalBinding::where('vend_id', $right->id)->count());
        // ...unticked one is untouched: still open, still on its old machine,
        // and never even reported as skipped.
        $this->assertNull($untouched->fresh()->bound_until);
        $this->assertSame($otherWrong->id, $untouched->fresh()->vend_id);
        $this->assertSame(0, CardTerminalBinding::where('vend_id', $otherRight->id)->count());
    }

    public function test_it_needs_the_update_permission(): void
    {
        Permission::findOrCreate('read card-settlements', 'web');
        Permission::findOrCreate('update card-settlements', 'web');
        $reader = User::factory()->create();
        $reader->givePermissionTo('read card-settlements');

        $report = $this->report();

        $this->actingAs($reader)
            ->post('/card-settlements/'.$report->id.'/fix-bindings')
            ->assertForbidden();
    }
}
