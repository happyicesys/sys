<?php

namespace Tests\Feature;

use App\Models\CardTerminal;
use App\Models\CardTerminalUnit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Data Management → Card Terminal: batch + "Will auto refund?" (seeded from
 * the partner workbook, manual override sticks), the filter, and the seed
 * import command.
 */
class CardTerminalAutoRefundFlagTest extends TestCase
{
    use RefreshDatabase;

    private CardTerminal $nets;

    private CardTerminal $auresys;

    protected function setUp(): void
    {
        parent::setUp();
        $this->nets = CardTerminal::create(['name' => 'Nets']);
        $this->auresys = CardTerminal::create(['name' => 'Nets-Auresys']);
    }

    private function staff(array $permissions): User
    {
        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission, 'web');
        }
        $user = User::factory()->create();
        $user->givePermissionTo($permissions);

        return $user;
    }

    private function csv(): string
    {
        $path = tempnam(sys_get_temp_dir(), 'seed').'.csv';
        file_put_contents($path, implode("\n", [
            'terminal_id,batch,is_will_auto_refund,flag_basis,auto_refund_events,unrefunded_events,batch_rate,window_from,window_to,sheet_status_v3',
            '23100701,Nets #3 (50x),1,batch_pooled,28,6,0.90,2026-08-15,2026-09-06,Supports auto-refund',
            '23005589,Nets #1 (25x),0,batch_pooled,3,0,0.16,2026-08-15,2026-09-06,Supports auto-refund',
            '23077326,Auresys #1,,unknown,0,0,,2026-08-15,2026-09-06,',
            '23999999,Nets #5 (80x),1,batch_pooled,10,1,0.90,2026-08-15,2026-09-06,',
        ]));

        return $path;
    }

    public function test_import_is_a_dry_run_by_default_then_seeds_flags_batches_and_stats(): void
    {
        CardTerminalUnit::create(['terminal_id' => '23100701', 'card_terminal_id' => $this->nets->id]);
        CardTerminalUnit::create(['terminal_id' => '23005589', 'card_terminal_id' => $this->nets->id]);
        $manual = CardTerminalUnit::create(['terminal_id' => '23077326', 'card_terminal_id' => $this->auresys->id, 'is_will_auto_refund' => 1, 'auto_refund_flag_source' => CardTerminalUnit::FLAG_SOURCE_MANUAL]);
        $csv = $this->csv();

        $this->artisan("card-settlement:import-terminal-flags {$csv}")->assertSuccessful();
        $this->assertNull(CardTerminalUnit::where('terminal_id', '23100701')->first()->is_will_auto_refund, 'dry run writes nothing');

        $this->artisan("card-settlement:import-terminal-flags {$csv} --apply")->assertSuccessful();

        $yes = CardTerminalUnit::where('terminal_id', '23100701')->first();
        $this->assertTrue($yes->willAutoRefund());
        $this->assertSame(CardTerminalUnit::FLAG_SOURCE_SEED, $yes->auto_refund_flag_source);
        $this->assertSame('Nets #3 (50x)', $yes->batch);
        $this->assertSame(28, $yes->auto_refund_stats_json['seed']['auto_refund_events']);
        $this->assertSame(1, $yes->auto_refund_stats_json['seed_flag']);

        $this->assertFalse(CardTerminalUnit::where('terminal_id', '23005589')->first()->willAutoRefund());

        $manual->refresh();
        $this->assertTrue($manual->willAutoRefund(), 'a manual flag survives the import');
        $this->assertSame(CardTerminalUnit::FLAG_SOURCE_MANUAL, $manual->auto_refund_flag_source);
        $this->assertSame('Auresys #1', $manual->batch, 'but the batch is still refreshed');

        $this->assertNull(CardTerminalUnit::where('terminal_id', '23999999')->first(), 'unknown TIDs are reported, not created, unless asked');

        $this->artisan("card-settlement:import-terminal-flags {$csv} --apply --create-missing")->assertSuccessful();
        $created = CardTerminalUnit::where('terminal_id', '23999999')->first();
        $this->assertNotNull($created);
        $this->assertSame($this->nets->id, (int) $created->card_terminal_id);
        $this->assertTrue($created->willAutoRefund());
        unlink($csv);
    }

    public function test_index_shows_the_flag_and_filters_on_it(): void
    {
        CardTerminalUnit::create(['terminal_id' => '23100701', 'card_terminal_id' => $this->nets->id, 'batch' => 'Nets #3 (50x)', 'is_will_auto_refund' => 1, 'auto_refund_flag_source' => 'seed']);
        CardTerminalUnit::create(['terminal_id' => '23005589', 'card_terminal_id' => $this->nets->id, 'batch' => 'Nets #1 (25x)', 'is_will_auto_refund' => 0, 'auto_refund_flag_source' => 'seed']);
        CardTerminalUnit::create(['terminal_id' => '23077326', 'card_terminal_id' => $this->auresys->id]);
        $staff = $this->staff(['read card-terminals']);

        $this->actingAs($staff)->get('/card-terminal-units')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('cardTerminalUnits.data.0.terminal_id', '23005589')
                ->where('cardTerminalUnits.data.0.is_will_auto_refund', false)
                ->where('cardTerminalUnits.data.0.batch', 'Nets #1 (25x)')
                ->where('cardTerminalUnits.data.1.is_will_auto_refund', null)
                ->where('cardTerminalUnits.data.2.is_will_auto_refund', true)
                ->where('filters.will_auto_refund', 'all'));

        $this->actingAs($staff)->get('/card-terminal-units?will_auto_refund=yes')
            ->assertInertia(fn ($page) => $page->has('cardTerminalUnits.data', 1)->where('cardTerminalUnits.data.0.terminal_id', '23100701'));
        $this->actingAs($staff)->get('/card-terminal-units?will_auto_refund=unknown')
            ->assertInertia(fn ($page) => $page->has('cardTerminalUnits.data', 1)->where('cardTerminalUnits.data.0.terminal_id', '23077326'));
        $this->actingAs($staff)->get('/card-terminal-units?sortKey=is_will_auto_refund&sortBy=false')
            ->assertInertia(fn ($page) => $page->where('cardTerminalUnits.data.0.terminal_id', '23100701'));
    }

    public function test_edit_sets_a_manual_override_and_auto_lifts_it_back_to_the_seed(): void
    {
        $unit = CardTerminalUnit::create([
            'terminal_id' => '23100701', 'card_terminal_id' => $this->nets->id, 'is_will_auto_refund' => 1,
            'auto_refund_flag_source' => 'seed', 'auto_refund_stats_json' => ['seed_flag' => 1],
        ]);
        $staff = $this->staff(['update card-terminals']);
        $payload = ['terminal_id' => '23100701', 'card_terminal_id' => $this->nets->id, 'remarks' => '', 'batch' => 'Nets #3 (50x)'];

        $this->actingAs($staff)->post("/card-terminal-units/{$unit->id}/update", $payload + ['will_auto_refund' => 'no'])->assertRedirect();
        $unit->refresh();
        $this->assertFalse($unit->willAutoRefund());
        $this->assertSame(CardTerminalUnit::FLAG_SOURCE_MANUAL, $unit->auto_refund_flag_source);
        $this->assertSame('Nets #3 (50x)', $unit->batch);

        $this->actingAs($staff)->post("/card-terminal-units/{$unit->id}/update", $payload + ['will_auto_refund' => 'auto'])->assertRedirect();
        $unit->refresh();
        $this->assertTrue($unit->willAutoRefund(), 'back to the workbook\'s answer');
        $this->assertSame(CardTerminalUnit::FLAG_SOURCE_SEED, $unit->auto_refund_flag_source);

        $this->actingAs($staff)->post("/card-terminal-units/{$unit->id}/update", $payload + ['will_auto_refund' => 'maybe'])->assertSessionHasErrors('will_auto_refund');
    }
}
