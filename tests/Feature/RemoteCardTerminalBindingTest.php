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
 * Setting/Edit "Remote card terminal (T05)" and the Card Terminal Company flag
 * "One terminal can serve several machines" (default off).
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
        foreach (['read machine-settings', 'update machine-settings', 'update card-terminals'] as $p) {
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

    private function payrallelCompany(bool $multi = false): CardTerminal
    {
        $company = CardTerminal::query()->firstOrCreate(['name' => CardTerminal::NAME_PAYRALLEL]);
        $company->update(['can_bind_multiple_vends' => $multi]);

        return $company;
    }

    public function test_the_migration_adds_payrallel_as_a_single_machine_company(): void
    {
        $company = CardTerminal::query()->where('name', CardTerminal::NAME_PAYRALLEL)->first();
        $this->assertNotNull($company);
        $this->assertFalse($company->can_bind_multiple_vends);
    }

    public function test_binding_from_setting_edit_stores_the_token_encrypted_and_never_returns_it(): void
    {
        $vend = $this->vend(50001);
        $company = $this->payrallelCompany();

        $res = $this->putJson("/vends/{$vend->id}/remote-card-terminal", [
            'label' => 'T05 50001', 'access_token' => 'tok-secret-1', 'is_active' => true,
        ])->assertOk();

        $res->assertJsonPath('terminal.is_active', true)
            ->assertJsonPath('terminal.has_token', true)
            ->assertJsonPath('terminal.label', 'T05 50001')
            ->assertJsonMissingPath('terminal.access_token');
        $this->assertStringNotContainsString('tok-secret-1', $res->getContent());

        $row = RemoteCardTerminal::query()->where('vend_id', $vend->id)->firstOrFail();
        $this->assertSame('tok-secret-1', $row->access_token);
        $this->assertNotSame('tok-secret-1', $row->getRawOriginal('access_token'), 'encrypted at rest');
        $this->assertSame($company->id, (int) $vend->fresh()->card_terminal_id, 'company filled when empty');
        $this->assertTrue(CardPaymentEvent::query()->where('event', 'terminal.bound')->exists());
    }

    public function test_a_first_bind_needs_a_token_and_a_later_save_keeps_it(): void
    {
        $vend = $this->vend(50001);
        $this->putJson("/vends/{$vend->id}/remote-card-terminal", ['is_active' => true])
            ->assertStatus(422)->assertJsonValidationErrors('access_token');

        $this->putJson("/vends/{$vend->id}/remote-card-terminal", ['access_token' => 'tok-1', 'is_active' => true])->assertOk();
        $this->putJson("/vends/{$vend->id}/remote-card-terminal", ['label' => 'renamed', 'is_active' => true])->assertOk();

        $row = RemoteCardTerminal::query()->where('vend_id', $vend->id)->firstOrFail();
        $this->assertSame('tok-1', $row->access_token);
        $this->assertSame('renamed', $row->label);
    }

    public function test_deactivate_keeps_the_row_and_the_freezer_reads_not_configured(): void
    {
        $vend = $this->vend(50001);
        $this->putJson("/vends/{$vend->id}/remote-card-terminal", ['access_token' => 'tok-1', 'is_active' => true])->assertOk();
        $this->putJson("/vends/{$vend->id}/remote-card-terminal", ['is_active' => false])
            ->assertOk()->assertJsonPath('terminal.is_active', false)->assertJsonPath('terminal.has_token', true);

        $this->assertNull(RemoteCardTerminal::activeForVend($vend));
        $this->assertTrue(CardPaymentEvent::query()->where('event', 'terminal.deactivated')->exists());
    }

    public function test_one_terminal_one_machine_by_default_binding_moves_it(): void
    {
        $this->payrallelCompany(multi: false);
        $a = $this->vend(50001);
        $b = $this->vend(50002);
        $this->putJson("/vends/{$a->id}/remote-card-terminal", ['access_token' => 'same-tok', 'is_active' => true])->assertOk();

        $this->putJson("/vends/{$b->id}/remote-card-terminal", ['access_token' => 'same-tok', 'is_active' => true])
            ->assertOk()->assertJsonPath('released_from', ['50001']);

        $this->assertNull(RemoteCardTerminal::activeForVend($a), 'taken off the first freezer');
        $this->assertNotNull(RemoteCardTerminal::activeForVend($b));
    }

    public function test_a_company_flagged_multi_machine_keeps_the_terminal_on_both(): void
    {
        $this->payrallelCompany(multi: true);
        $a = $this->vend(50001);
        $b = $this->vend(50002);
        $this->putJson("/vends/{$a->id}/remote-card-terminal", ['access_token' => 'same-tok', 'is_active' => true])->assertOk();
        $this->putJson("/vends/{$b->id}/remote-card-terminal", ['access_token' => 'same-tok', 'is_active' => true])
            ->assertOk()->assertJsonPath('released_from', []);

        $this->assertNotNull(RemoteCardTerminal::activeForVend($a));
        $this->assertNotNull(RemoteCardTerminal::activeForVend($b));
    }

    public function test_a_different_token_on_another_freezer_is_left_alone(): void
    {
        $a = $this->vend(50001);
        $b = $this->vend(50002);
        $this->putJson("/vends/{$a->id}/remote-card-terminal", ['access_token' => 'tok-a', 'is_active' => true])->assertOk();
        $this->putJson("/vends/{$b->id}/remote-card-terminal", ['access_token' => 'tok-b', 'is_active' => true])->assertOk();

        $this->assertNotNull(RemoteCardTerminal::activeForVend($a));
    }

    public function test_only_smart_freezers_can_be_bound_and_binding_needs_update_card_terminals(): void
    {
        $vending = $this->vend(2031, 'vending');
        $this->putJson("/vends/{$vending->id}/remote-card-terminal", ['access_token' => 'tok', 'is_active' => true])
            ->assertStatus(422)->assertJsonValidationErrors('vend');

        $freezer = $this->vend(50001);
        $this->user->revokePermissionTo('update card-terminals');
        app()['cache']->forget('spatie.permission.cache');
        $this->putJson("/vends/{$freezer->id}/remote-card-terminal", ['access_token' => 'tok', 'is_active' => true])->assertForbidden();
        $this->getJson("/vends/{$freezer->id}/remote-card-terminal")->assertOk()->assertJsonPath('terminal', null);
    }

    public function test_the_artisan_command_follows_the_same_one_machine_rule(): void
    {
        $this->payrallelCompany(multi: false);
        $a = $this->vend(50001);
        $b = $this->vend(50002);
        $this->putJson("/vends/{$a->id}/remote-card-terminal", ['access_token' => 'same-tok', 'is_active' => true])->assertOk();

        $this->artisan('payrallel:bind-terminal', ['vend' => '50002', '--label' => 'T05'])
            ->expectsQuestion('Payrallel access token for this terminal', 'same-tok')
            ->expectsOutputToContain('Taken off: 50001')
            ->assertSuccessful();

        $this->assertNull(RemoteCardTerminal::activeForVend($a));
        $this->assertNotNull(RemoteCardTerminal::activeForVend($b));
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
