<?php

namespace Tests\Feature;

use App\Models\CardPaymentEvent;
use App\Models\CardTerminal;
use App\Models\CardTerminalBinding;
use App\Models\CardTerminalUnit;
use App\Models\RemoteCardTerminal;
use App\Models\User;
use App\Models\Vend;
use App\Services\CardSettlement\CardTerminalBindingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * A Payrallel (T05) terminal is a Data Management > Card Terminal unit (SN = terminal_id,
 * access token on the unit). Binding it to a smart freezer with the ordinary Card Terminal
 * binding switches the freezer's card rail (remote_card_terminals) to it. Card Terminal
 * Company "One terminal can serve several machines" (default off) decides whether binding
 * it elsewhere takes it off the first machine.
 */
class RemoteCardTerminalBindingTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        Http::fake(); // terminalStatus may call Payrallel; nothing real leaves the test
        $this->user = User::factory()->create(['name' => 'Ops One', 'operator_id' => 1]);
        foreach (['read machine-settings', 'update machine-settings', 'read card-terminals', 'update card-terminals', 'create card-terminals', 'delete card-terminals'] as $p) {
            $this->user->givePermissionTo(Permission::findOrCreate($p, 'web'));
        }
        $this->actingAs($this->user);
    }

    private function vend(int $code, string $type = Vend::MACHINE_TYPE_SMART_FREEZER): Vend
    {
        $vend = new Vend;
        $vend->forceFill([
            'code' => $code, 'machine_type' => $type, 'is_active' => 1, 'operator_id' => 1,
            'vend_model_id' => 1, 'private_key' => 'TESTKEY000000001',
        ])->save();

        return $vend->refresh();
    }

    private function payrallel(bool $multi = false): CardTerminal
    {
        $company = CardTerminal::query()->firstOrCreate(['name' => CardTerminal::NAME_PAYRALLEL]);
        $company->update(['can_bind_multiple_vends' => $multi]);

        return $company;
    }

    private function t05(string $sn = 'T05SN0001', ?string $token = 'tok-1'): CardTerminalUnit
    {
        return CardTerminalUnit::query()->create([
            'card_terminal_id' => $this->payrallel()->id, 'terminal_id' => $sn, 'access_token' => $token,
        ])->load('company');
    }

    private function bind(Vend $vend, ?CardTerminalUnit $unit): void
    {
        app(CardTerminalBindingService::class)->assignToVend($vend, $unit, null, $this->user->id);
    }

    public function test_the_migration_adds_payrallel_as_a_single_machine_company(): void
    {
        $company = CardTerminal::query()->where('name', CardTerminal::NAME_PAYRALLEL)->first();
        $this->assertNotNull($company);
        $this->assertFalse($company->can_bind_multiple_vends);
    }

    public function test_a_t05_unit_needs_its_token_which_is_stored_encrypted_and_never_returned(): void
    {
        $company = $this->payrallel();
        $this->post('/card-terminal-units/create', ['terminal_id' => 'T05SN0001', 'card_terminal_id' => $company->id])
            ->assertSessionHasErrors('access_token');

        $this->post('/card-terminal-units/create', ['terminal_id' => 'T05SN0001', 'card_terminal_id' => $company->id, 'access_token' => 'tok-secret'])
            ->assertSessionHasNoErrors();
        $unit = CardTerminalUnit::query()->where('terminal_id', 'T05SN0001')->firstOrFail();
        $this->assertSame('tok-secret', $unit->access_token);
        $this->assertNotSame('tok-secret', $unit->getRawOriginal('access_token'), 'encrypted at rest');
        $this->assertArrayNotHasKey('access_token', $unit->toArray());

        // Editing with a blank token keeps it; a NETS unit never carries one.
        $this->post("/card-terminal-units/{$unit->id}/update", ['terminal_id' => 'T05SN0001', 'card_terminal_id' => $company->id, 'remarks' => 'x'])
            ->assertSessionHasNoErrors();
        $this->assertSame('tok-secret', $unit->fresh()->access_token);
        $nets = CardTerminal::query()->create(['name' => 'Nets']);
        $this->post('/card-terminal-units/create', ['terminal_id' => '23005588', 'card_terminal_id' => $nets->id, 'access_token' => 'ignored'])
            ->assertSessionHasNoErrors();
        $this->assertNull(CardTerminalUnit::query()->where('terminal_id', '23005588')->first()->getRawOriginal('access_token'));
    }

    public function test_binding_a_t05_unit_to_a_freezer_switches_its_card_rail_and_keeps_history(): void
    {
        $vend = $this->vend(50001);
        $unit = $this->t05();

        $this->bind($vend, $unit);

        $remote = RemoteCardTerminal::activeForVend($vend);
        $this->assertNotNull($remote);
        $this->assertSame($unit->id, (int) $remote->card_terminal_unit_id);
        $this->assertSame('tok-1', $remote->access_token);
        $this->assertSame('T05SN0001', $remote->label);
        $this->assertTrue(CardTerminalBinding::query()->where('vend_id', $vend->id)->where('terminal_id', 'T05SN0001')->whereNull('until_at')->exists());
        $this->assertSame($unit->card_terminal_id, (int) $vend->fresh()->card_terminal_id, 'company filled when empty');
        $this->assertTrue(CardPaymentEvent::query()->where('event', 'terminal.bound')->exists());

        $this->getJson("/vends/{$vend->id}/remote-card-terminal")->assertOk()
            ->assertJsonPath('terminal.sn', 'T05SN0001')
            ->assertJsonPath('terminal.from_command', false)
            ->assertJsonMissingPath('terminal.access_token');
    }

    public function test_clearing_or_choosing_another_terminal_switches_the_rail_back(): void
    {
        $vend = $this->vend(50001);
        $this->bind($vend, $this->t05());
        $this->bind($vend, null);

        $this->assertNull(RemoteCardTerminal::activeForVend($vend));
        $this->assertTrue(CardPaymentEvent::query()->where('event', 'terminal.deactivated')->exists());
    }

    public function test_a_save_never_switches_off_a_t05_bound_by_the_old_command(): void
    {
        $vend = $this->vend(50001);
        RemoteCardTerminal::query()->create([
            'vend_id' => $vend->id, 'provider' => 'payrallel', 'label' => 'T05 50001', 'access_token' => 'legacy', 'is_active' => true,
        ]);

        // Setting/Edit posts the (empty) Card Terminal on every save.
        $this->bind($vend, null);

        $this->assertNotNull(RemoteCardTerminal::activeForVend($vend), 'a command-line binding survives an unrelated save');
    }

    public function test_one_t05_one_machine_by_default_binding_moves_it(): void
    {
        $unit = $this->t05();
        $a = $this->vend(50001);
        $b = $this->vend(50002);
        $this->bind($a, $unit);
        $this->bind($b, $unit);

        $this->assertNull(RemoteCardTerminal::activeForVend($a), 'taken off the first freezer');
        $this->assertNotNull(RemoteCardTerminal::activeForVend($b));
        $this->assertSame(1, CardTerminalBinding::query()->where('terminal_id', 'T05SN0001')->whereNull('until_at')->count());
    }

    public function test_a_company_flagged_multi_machine_keeps_the_t05_on_both(): void
    {
        $unit = $this->t05();
        $this->payrallel(multi: true);
        $unit->load('company');
        $a = $this->vend(50001);
        $b = $this->vend(50002);
        $this->bind($a, $unit);
        $this->bind($b, $unit->fresh('company'));

        $this->assertNotNull(RemoteCardTerminal::activeForVend($a));
        $this->assertNotNull(RemoteCardTerminal::activeForVend($b));
        $this->assertSame(2, CardTerminalBinding::query()->where('terminal_id', 'T05SN0001')->whereNull('until_at')->count());
    }

    public function test_a_token_changed_on_the_unit_reaches_the_freezer_and_deleting_it_switches_the_rail_off(): void
    {
        $vend = $this->vend(50001);
        $unit = $this->t05();
        $this->bind($vend, $unit);

        $this->post("/card-terminal-units/{$unit->id}/update", [
            'terminal_id' => 'T05SN0001', 'card_terminal_id' => $unit->card_terminal_id, 'access_token' => 'tok-2',
        ])->assertSessionHasNoErrors();
        $this->assertSame('tok-2', RemoteCardTerminal::activeForVend($vend)->access_token);

        $this->delete("/card-terminal-units/{$unit->id}")->assertRedirect();
        $this->assertNull(RemoteCardTerminal::activeForVend($vend));
    }

    public function test_a_t05_on_a_vending_machine_does_nothing_to_card_payments(): void
    {
        $vending = $this->vend(2031, 'vending');
        $this->bind($vending, $this->t05());

        $this->assertNull(RemoteCardTerminal::query()->where('vend_id', $vending->id)->first());
    }

    public function test_the_command_binds_by_sn_and_deactivates_either_kind(): void
    {
        $vend = $this->vend(50001);
        $this->t05('T05SN0009', 'tok-9');

        $this->artisan('payrallel:bind-terminal', ['vend' => '50001', '--sn' => 'T05SN0009'])
            ->expectsOutputToContain('T05 T05SN0009 bound to 50001')
            ->assertSuccessful();
        $this->assertSame('tok-9', RemoteCardTerminal::activeForVend($vend)->access_token);

        $this->artisan('payrallel:bind-terminal', ['vend' => '50001', '--deactivate' => true])->assertSuccessful();
        $this->assertNull(RemoteCardTerminal::activeForVend($vend));
        $this->assertFalse(CardTerminalBinding::query()->where('vend_id', $vend->id)->whereNull('until_at')->exists());

        $this->artisan('payrallel:bind-terminal', ['vend' => '50001', '--sn' => 'NOPE'])->assertFailed();
    }

    public function test_company_form_saves_the_multi_machine_flag_default_off(): void
    {
        $this->post('/card-terminals/create', ['name' => 'NewCo'])->assertRedirect();
        $this->assertFalse(CardTerminal::query()->where('name', 'NewCo')->firstOrFail()->can_bind_multiple_vends);

        $co = CardTerminal::query()->where('name', 'NewCo')->firstOrFail();
        $this->post("/card-terminals/{$co->id}/update", ['name' => 'NewCo', 'can_bind_multiple_vends' => true])->assertRedirect();
        $this->assertTrue($co->fresh()->can_bind_multiple_vends);
    }

    public function test_a_unit_of_a_multi_machine_company_stays_bound_to_both_machines(): void
    {
        $multi = CardTerminal::query()->create(['name' => 'SharedCo', 'can_bind_multiple_vends' => true]);
        $single = CardTerminal::query()->create(['name' => 'SoloCo']);
        $shared = CardTerminalUnit::query()->create(['card_terminal_id' => $multi->id, 'terminal_id' => 'SHARED01']);
        $solo = CardTerminalUnit::query()->create(['card_terminal_id' => $single->id, 'terminal_id' => 'SOLO0001']);
        $a = $this->vend(3001, 'vending');
        $b = $this->vend(3002, 'vending');
        $svc = app(CardTerminalBindingService::class);

        $svc->assignToVend($a, $shared);
        $svc->assignToVend($b, $shared);
        $this->assertSame(2, CardTerminalBinding::query()->where('terminal_id', 'SHARED01')->whereNull('until_at')->count());

        $svc->assignToVend($a, $solo);
        $svc->assignToVend($b, $solo);
        $open = CardTerminalBinding::query()->where('terminal_id', 'SOLO0001')->whereNull('until_at')->get();
        $this->assertCount(1, $open, 'default: one terminal, one machine');
        $this->assertSame($b->id, (int) $open->first()->vend_id);
    }
}
