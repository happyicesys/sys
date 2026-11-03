<?php

namespace Tests\Feature;

use App\Models\CardTerminal;
use App\Models\CardTerminalUnit;
use App\Models\Customer;
use App\Models\ProductMapping;
use App\Models\Simcard;
use App\Models\Telco;
use App\Models\User;
use App\Models\Vend;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * A card terminal carries a SIM from the same pool machines use, and a SIM sits
 * in one place only. Checked from all three doors that can place a SIM: the Card
 * Terminal form, Setting/Edit and the SIM Card page.
 */
class CardTerminalSimcardTest extends TestCase
{
    use RefreshDatabase;

    private CardTerminal $company;

    private Telco $telco;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = CardTerminal::create(['name' => 'Nets']);
        $this->telco = Telco::create(['name' => 'VP-Starhub 6GB/y']);
        $this->actingAs($this->staff());
    }

    private function staff(array $attributes = []): User
    {
        $permissions = ['read card-terminals', 'export card-terminals', 'update card-terminals', 'create card-terminals', 'read simcards', 'update simcards', 'read machine-settings', 'update machine-settings'];
        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission, 'web');
        }
        $user = User::factory()->create($attributes);
        $user->givePermissionTo($permissions);

        return $user;
    }

    private function sim(string $code): Simcard
    {
        return Simcard::create(['code' => $code, 'telco_id' => $this->telco->id]);
    }

    private function unit(string $terminalId, ?Simcard $sim = null): CardTerminalUnit
    {
        return CardTerminalUnit::create([
            'terminal_id' => $terminalId,
            'card_terminal_id' => $this->company->id,
            'simcard_id' => $sim?->id,
        ]);
    }

    private function machine(int $code, ?Simcard $sim = null, int $operatorId = 1): Vend
    {
        $customer = Customer::create([
            'name' => "Site {$code}", 'code' => $code, 'operator_id' => $operatorId,
            'status_id' => Customer::STATUS_ACTIVE,
        ]);

        return Vend::forceCreate([
            'code' => $code, 'customer_id' => $customer->id, 'operator_id' => $operatorId,
            'product_mapping_id' => ProductMapping::forceCreate(['name' => "Mapping {$code}"])->id,
            'simcard_id' => $sim?->id,
        ]);
    }

    private function saveTerminal(CardTerminalUnit $unit, array $overrides = [])
    {
        return $this->post('/card-terminal-units/'.$unit->id.'/update', array_merge([
            'terminal_id' => $unit->terminal_id,
            'card_terminal_id' => $this->company->id,
        ], $overrides));
    }

    public function test_index_shows_the_bound_sim_and_offers_only_sims_not_in_a_machine(): void
    {
        $inMachine = $this->sim('111');
        $this->machine(7001, $inMachine);
        $free = $this->sim('222');
        $inTerminal = $this->sim('333');
        $unit = $this->unit('N620W1', $inTerminal);

        $this->get('/card-terminal-units')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('cardTerminalUnits.data.0.simcard_id', $inTerminal->id)
                ->where('cardTerminalUnits.data.0.simcard_code', '333')
                ->where('cardTerminalUnits.data.0.simcard_package', 'VP-Starhub 6GB/y')
                ->where('simcardOptions', [
                    ['id' => $free->id, 'name' => 'VP-Starhub 6GB/y - 222', 'card_terminal_unit_id' => null],
                    ['id' => $inTerminal->id, 'name' => 'VP-Starhub 6GB/y - 333', 'card_terminal_unit_id' => $unit->id],
                ])
            );
    }

    /** whereDoesntHave('vends') would apply the Vend scope and offer this SIM to them. */
    public function test_a_restricted_viewer_is_not_offered_a_sim_in_another_operators_machine(): void
    {
        $sim = $this->sim('111');
        $this->machine(7001, $sim, 1);

        $this->actingAs($this->staff(['operator_id' => 99]));
        $this->assertSame(0, Vend::where('simcard_id', $sim->id)->count(), 'the viewer must not see that machine');

        $this->get('/card-terminal-units')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('simcardOptions', []));

        $this->saveTerminal($this->unit('N620W1'), ['simcard_id' => $sim->id])
            ->assertSessionHasErrors('simcard_id');
    }

    public function test_the_edit_form_binds_changes_and_clears_a_sim(): void
    {
        $first = $this->sim('111');
        $second = $this->sim('222');
        $unit = $this->unit('N620W1');

        $this->saveTerminal($unit, ['simcard_id' => $first->id])->assertSessionHasNoErrors();
        $this->assertSame($first->id, $unit->fresh()->simcard_id);

        $this->saveTerminal($unit, ['simcard_id' => $second->id])->assertSessionHasNoErrors();
        $this->assertSame($second->id, $unit->fresh()->simcard_id);

        $this->saveTerminal($unit, ['simcard_id' => null])->assertSessionHasNoErrors();
        $this->assertNull($unit->fresh()->simcard_id);
    }

    public function test_a_t05_takes_a_sim_and_keeps_its_token(): void
    {
        $t05 = CardTerminal::create(['name' => CardTerminal::NAME_PAYRALLEL]);
        $sim = $this->sim('111');
        $unit = CardTerminalUnit::create([
            'terminal_id' => 'N620W342856', 'card_terminal_id' => $t05->id, 'access_token' => 'stored-token',
        ]);

        $this->saveTerminal($unit, ['card_terminal_id' => $t05->id, 'simcard_id' => $sim->id])
            ->assertSessionHasNoErrors();

        $this->assertSame($sim->id, $unit->fresh()->simcard_id);
        $this->assertSame('stored-token', $unit->fresh()->access_token);
    }

    public function test_a_tab_opened_before_the_field_existed_keeps_the_sim(): void
    {
        $sim = $this->sim('111');
        $unit = $this->unit('N620W1', $sim);

        $this->saveTerminal($unit, ['remarks' => 'edited'])->assertSessionHasNoErrors();

        $this->assertSame($sim->id, $unit->fresh()->simcard_id);
        $this->assertSame('edited', $unit->fresh()->remarks);
    }

    public function test_a_terminal_cannot_take_a_sim_held_elsewhere(): void
    {
        $inMachine = $this->sim('111');
        $vend = $this->machine(7001, $inMachine);
        $inTerminal = $this->sim('222');
        $this->unit('N620W1', $inTerminal);
        $unit = $this->unit('N620W2');

        $this->saveTerminal($unit, ['simcard_id' => $inMachine->id])
            ->assertSessionHasErrors(['simcard_id' => __('This SIM card is in machine :machine. Take it off that machine first.', ['machine' => $vend->codeLabel()])]);

        $this->saveTerminal($unit, ['simcard_id' => $inTerminal->id])
            ->assertSessionHasErrors(['simcard_id' => __('This SIM card is in terminal :terminal. Take it off that terminal first.', ['terminal' => 'N620W1'])]);

        $this->assertNull($unit->fresh()->simcard_id);
    }

    public function test_a_terminal_keeps_saving_when_its_own_sim_is_also_in_a_machine(): void
    {
        $sim = $this->sim('111');
        $unit = $this->unit('N620W1', $sim);
        $this->machine(7001, $sim);

        $this->saveTerminal($unit, ['simcard_id' => $sim->id, 'remarks' => 'edited'])->assertSessionHasNoErrors();

        $this->assertSame('edited', $unit->fresh()->remarks);
    }

    public function test_the_machine_page_refuses_a_sim_in_a_terminal_and_stops_offering_it(): void
    {
        $sim = $this->sim('111');
        $free = $this->sim('222');
        $this->unit('N620W1', $sim);
        $vend = $this->machine(7001);

        $payload = [
            'name' => 'Machine', 'lcd_monitor_id' => 1, 'menu_frame_id' => 1, 'operator_id' => 1,
            'product_mapping_id' => $vend->product_mapping_id, 'vend_model_id' => 1, 'vend_prefix_id' => 1,
        ];

        $this->post('/vends/'.$vend->id.'/update', $payload + ['simcard_id' => $sim->id])
            ->assertSessionHasErrors('simcard_id');
        $this->assertNull($vend->fresh()->simcard_id);

        $this->post('/vends/'.$vend->id.'/update', $payload + ['simcard_id' => $free->id])
            ->assertSessionHasNoErrors();
        $this->assertSame($free->id, $vend->fresh()->simcard_id);

        $this->get('/settings/vend/'.$vend->id.'/update')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('simcardOptions.data', 1)
                ->where('simcardOptions.data.0.id', $free->id));
    }

    public function test_the_sim_page_shows_the_terminal_and_refuses_a_machine_for_its_sim(): void
    {
        $sim = $this->sim('111');
        $this->unit('N620W1', $sim);
        $vend = $this->machine(7001);

        $this->get('/simcards')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('simcards.data.0.card_terminal_id', 'N620W1'));

        $this->post('/simcards/'.$sim->id.'/update', [
            'code' => '111', 'telco_id' => $this->telco->id, 'vend_id' => $vend->id,
        ])->assertSessionHasErrors('vend_id');

        $this->assertNull($vend->fresh()->simcard_id);
    }

    public function test_deleting_a_sim_leaves_the_terminal_with_none(): void
    {
        $sim = $this->sim('111');
        $unit = $this->unit('N620W1', $sim);

        $sim->delete();

        $this->assertNull($unit->fresh()->simcard_id);
    }
}
