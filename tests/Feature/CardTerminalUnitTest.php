<?php

namespace Tests\Feature;

use App\Models\CardSettlementReport;
use App\Models\CardSettlementRow;
use App\Models\CardTerminal;
use App\Models\CardTerminalBinding;
use App\Models\CardTerminalUnit;
use App\Models\Customer;
use App\Models\PaymentMethod;
use App\Models\ProductMapping;
use App\Models\User;
use App\Models\Vend;
use App\Models\VendTransaction;
use App\Services\CardSettlement\CardSettlementMatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Data Management → Card Terminal (2026-09-05), which replaced the standalone
 * Card Terminal Bindings page.
 *
 * Two rules are load-bearing and pinned here:
 *  1. The Data Management tab maintains terminals only — it can never bind a
 *     machine. Binding happens on the machine's Setting/Edit page.
 *  2. None of it may disturb card settlement. CardSettlementMatcher resolves a
 *     report line by (provider, terminal_id) effective on the line's date, so
 *     moving a terminal must CLOSE the old binding rather than rewrite it, and
 *     a Nets-Auresys terminal must keep provider 'nets' or its machines drop
 *     off the NETS report entirely.
 */
class CardTerminalUnitTest extends TestCase
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

    /** The Setting/Edit payload, with the terminal fields caller-supplied. */
    private function savePayload(Vend $vend, array $overrides = []): array
    {
        return array_merge([
            'name' => 'Machine '.$vend->code,
            'lcd_monitor_id' => 1,
            'menu_frame_id' => 1,
            'operator_id' => 1,
            'product_mapping_id' => $vend->product_mapping_id,
            'vend_model_id' => 1,
            'vend_prefix_id' => 1,
        ], $overrides);
    }

    // ---------------------------------------------------------------- CRUD --

    public function test_data_management_lists_terminals_with_company_and_current_machine(): void
    {
        $vend = $this->makeVend(7001);
        $unit = CardTerminalUnit::create([
            'terminal_id' => '23005589', 'card_terminal_id' => $this->nets->id,
        ]);
        CardTerminalBinding::create([
            'provider' => 'nets', 'terminal_id' => '23005589',
            'vend_id' => $vend->id, 'bound_from' => now()->subDay()->toDateString(),
        ]);
        // An unbound terminal must still be listed — it is stock on the shelf.
        CardTerminalUnit::create(['terminal_id' => '99999999', 'card_terminal_id' => $this->auresys->id]);

        $this->actingAs($this->staff(['read card-terminals']))
            ->get('/card-terminal-units')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('CardTerminalUnit/Index')
                ->where('cardTerminalUnits.data.0.terminal_id', '23005589')
                ->where('cardTerminalUnits.data.0.card_terminal_name', 'Nets')
                ->where('cardTerminalUnits.data.0.current_vend_code', $vend->code)
                ->where('cardTerminalUnits.data.1.terminal_id', '99999999')
                ->where('cardTerminalUnits.data.1.current_vend_code', null)
            );

        $this->assertSame($unit->id, CardTerminalUnit::first()->id);
    }

    public function test_machine_id_filter_matches_the_terminal_on_that_machine_today(): void
    {
        $onMachine = $this->makeVend(7020);
        $other = $this->makeVend(7021);
        $user = $this->staff(['read card-terminals']);

        CardTerminalUnit::create(['terminal_id' => 'AAA11111', 'card_terminal_id' => $this->nets->id]);
        CardTerminalUnit::create(['terminal_id' => 'BBB22222', 'card_terminal_id' => $this->nets->id]);
        CardTerminalUnit::create(['terminal_id' => 'CCC33333', 'card_terminal_id' => $this->nets->id]);

        CardTerminalBinding::create([
            'provider' => 'nets', 'terminal_id' => 'AAA11111',
            'vend_id' => $onMachine->id, 'bound_from' => '2026-01-01',
        ]);
        CardTerminalBinding::create([
            'provider' => 'nets', 'terminal_id' => 'BBB22222',
            'vend_id' => $other->id, 'bound_from' => '2026-01-01',
        ]);
        // Was on 7020 but has since been taken off: the filter asks "what is on
        // that machine NOW", so this must not come back.
        CardTerminalBinding::create([
            'provider' => 'nets', 'terminal_id' => 'CCC33333',
            'vend_id' => $onMachine->id, 'bound_from' => '2025-01-01', 'bound_until' => '2025-06-01',
        ]);

        $this->actingAs($user)
            ->get('/card-terminal-units?vend_code='.$onMachine->code)
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('cardTerminalUnits.meta.total', 1)
                ->where('cardTerminalUnits.data.0.terminal_id', 'AAA11111')
                ->where('cardTerminalUnits.data.0.current_vend_id', $onMachine->id)
                ->where('filters.vend_code', (string) $onMachine->code)
            );
    }

    /**
     * Download the export and read it back. FastExcel streams the file, so the
     * response has to be spooled to disk before it can be reopened.
     *
     * @return array<int, array<string, mixed>>
     */
    private function exportSheet(array $query = []): array
    {
        $response = $this->get('/card-terminal-units/excel?'.http_build_query($query));
        $response->assertOk();
        $this->assertMatchesRegularExpression(
            '/filename="?CardTerminals_\d{8}_\d{6}\.xlsx"?/',
            (string) $response->headers->get('content-disposition')
        );

        $path = tempnam(sys_get_temp_dir(), 'card_terminal_export').'.xlsx';
        file_put_contents($path, $response->streamedContent());

        try {
            return (new \Rap2hpoutre\FastExcel\FastExcel)->importSheets($path)[0] ?? [];
        } finally {
            @unlink($path);
        }
    }

    public function test_export_columns_carry_the_terminal_its_company_and_its_machine(): void
    {
        $vend = $this->makeVend(7022);
        $unbound = CardTerminalUnit::create(['terminal_id' => 'EXP22222', 'card_terminal_id' => $this->auresys->id]);
        CardTerminalUnit::create(['terminal_id' => 'EXP11111', 'card_terminal_id' => $this->nets->id, 'remarks' => 'spare']);
        CardTerminalBinding::create([
            'provider' => 'nets', 'terminal_id' => 'EXP11111',
            'vend_id' => $vend->id, 'bound_from' => '2026-03-04',
        ]);

        $this->actingAs($this->staff(['read card-terminals', 'export card-terminals']));
        $rows = $this->exportSheet();

        $this->assertSame(
            ['Terminal ID', 'Card Terminal Company', 'Machine ID', 'Site', 'Bound From', 'Remarks'],
            array_keys($rows[0])
        );

        $bound = collect($rows)->firstWhere('Terminal ID', 'EXP11111');
        $this->assertSame('Nets', $bound['Card Terminal Company']);
        $this->assertSame((string) $vend->code, (string) $bound['Machine ID']);
        $this->assertSame('Site 7022', $bound['Site']);
        $this->assertSame('2026-03-04', $bound['Bound From']);
        $this->assertSame('spare', $bound['Remarks']);

        // A terminal on no machine still exports, with the machine cells empty.
        $free = collect($rows)->firstWhere('Terminal ID', $unbound->terminal_id);
        $this->assertNotNull($free);
        $this->assertEmpty($free['Machine ID']);
    }

    public function test_export_honours_the_page_filters(): void
    {
        $vend = $this->makeVend(7023);
        CardTerminalUnit::create(['terminal_id' => 'FIL11111', 'card_terminal_id' => $this->nets->id]);
        CardTerminalUnit::create(['terminal_id' => 'FIL22222', 'card_terminal_id' => $this->auresys->id]);
        CardTerminalBinding::create([
            'provider' => 'nets', 'terminal_id' => 'FIL11111',
            'vend_id' => $vend->id, 'bound_from' => '2026-01-01',
        ]);

        $this->actingAs($this->staff(['read card-terminals', 'export card-terminals']));

        $this->assertSame(
            ['FIL22222'],
            collect($this->exportSheet(['card_terminal_id' => $this->auresys->id]))->pluck('Terminal ID')->all()
        );

        // The Machine ID filter must reach the export too, or the file would
        // not match the grid the user is looking at.
        $this->assertSame(
            ['FIL11111'],
            collect($this->exportSheet(['vend_code' => $vend->code]))->pluck('Terminal ID')->all()
        );
    }

    public function test_export_is_refused_without_the_export_permission(): void
    {
        $this->actingAs($this->staff(['read card-terminals']))
            ->get('/card-terminal-units/excel')
            ->assertForbidden();
    }

    public function test_terminal_can_be_created_updated_and_deleted_but_never_bound_here(): void
    {
        $vend = $this->makeVend(7002);
        $user = $this->staff([
            'read card-terminals', 'create card-terminals',
            'update card-terminals', 'delete card-terminals',
        ]);

        $this->actingAs($user)->post('/card-terminal-units/create', [
            'terminal_id' => ' 23005590 ',
            'card_terminal_id' => $this->nets->id,
            'remarks' => 'spare',
            // A caller trying to bind from this page is simply ignored: the
            // controller never reads vend_id, and no binding row appears.
            'vend_id' => $vend->id,
        ])->assertRedirect();

        $unit = CardTerminalUnit::firstOrFail();
        $this->assertSame('23005590', $unit->terminal_id, 'terminal_id is trimmed');
        $this->assertSame($this->nets->id, $unit->card_terminal_id);
        $this->assertSame(0, CardTerminalBinding::count(), 'Data Management must not create a binding');

        $this->actingAs($user)->post('/card-terminal-units/'.$unit->id.'/update', [
            'terminal_id' => '23005590',
            'card_terminal_id' => $this->auresys->id,
        ])->assertRedirect();
        $this->assertSame($this->auresys->id, $unit->fresh()->card_terminal_id);

        $this->actingAs($user)->delete('/card-terminal-units/'.$unit->id)->assertRedirect();
        $this->assertSame(0, CardTerminalUnit::count());
    }

    public function test_duplicate_terminal_id_is_refused(): void
    {
        $user = $this->staff(['read card-terminals', 'create card-terminals']);
        CardTerminalUnit::create(['terminal_id' => '23005591', 'card_terminal_id' => $this->nets->id]);

        // Same TID under a DIFFERENT company is still a duplicate: settlement
        // resolves a report line by TID alone.
        $this->actingAs($user)->post('/card-terminal-units/create', [
            'terminal_id' => '23005591',
            'card_terminal_id' => $this->auresys->id,
        ])->assertSessionHasErrors('terminal_id');

        $this->assertSame(1, CardTerminalUnit::count());
    }

    public function test_reader_without_write_permission_cannot_change_terminals(): void
    {
        $unit = CardTerminalUnit::create(['terminal_id' => '23005592', 'card_terminal_id' => $this->nets->id]);
        $reader = $this->staff(['read card-terminals']);

        $this->actingAs($reader)->post('/card-terminal-units/create', [
            'terminal_id' => '23005593',
        ])->assertForbidden();

        $this->actingAs($reader)->delete('/card-terminal-units/'.$unit->id)->assertForbidden();

        $this->assertSame(1, CardTerminalUnit::count());
    }

    // ------------------------------------------------- binding on Settings --

    public function test_settings_save_binds_the_terminal_from_today_when_no_date_given(): void
    {
        $vend = $this->makeVend(7003);
        $unit = CardTerminalUnit::create(['terminal_id' => '23005594', 'card_terminal_id' => $this->nets->id]);
        $user = $this->staff(['read machine-settings', 'update machine-settings']);

        $this->actingAs($user)
            ->post('/vends/'.$vend->id.'/update', $this->savePayload($vend, [
                'card_terminal_unit_id' => $unit->id,
                'card_terminal_bound_from' => '',
            ]))
            ->assertSessionHasNoErrors();

        $binding = CardTerminalBinding::firstOrFail();
        $this->assertSame('23005594', $binding->terminal_id);
        $this->assertSame($vend->id, $binding->vend_id);
        $this->assertSame('nets', $binding->provider);
        $this->assertSame(now()->toDateString(), $binding->bound_from->toDateString());
        $this->assertNull($binding->bound_until);
    }

    public function test_bound_from_is_honoured_when_supplied(): void
    {
        $vend = $this->makeVend(7004);
        $unit = CardTerminalUnit::create(['terminal_id' => '23005595', 'card_terminal_id' => $this->nets->id]);
        $user = $this->staff(['read machine-settings', 'update machine-settings']);

        $this->actingAs($user)
            ->post('/vends/'.$vend->id.'/update', $this->savePayload($vend, [
                'card_terminal_unit_id' => $unit->id,
                'card_terminal_bound_from' => '2026-07-01',
            ]))
            ->assertSessionHasNoErrors();

        $this->assertSame('2026-07-01', CardTerminalBinding::firstOrFail()->bound_from->toDateString());
    }

    public function test_a_nets_auresys_terminal_still_binds_under_the_nets_provider(): void
    {
        $vend = $this->makeVend(7005);
        $unit = CardTerminalUnit::create(['terminal_id' => '23005596', 'card_terminal_id' => $this->auresys->id]);
        $user = $this->staff(['read machine-settings', 'update machine-settings']);

        $this->actingAs($user)
            ->post('/vends/'.$vend->id.'/update', $this->savePayload($vend, [
                'card_terminal_unit_id' => $unit->id,
            ]))
            ->assertSessionHasNoErrors();

        $this->assertSame('nets', CardTerminalBinding::firstOrFail()->provider);
    }

    public function test_a_company_with_no_settlement_parser_does_not_masquerade_as_nets(): void
    {
        $nayax = CardTerminal::create(['name' => 'Nayax']);
        $vend = $this->makeVend(7006);
        $unit = CardTerminalUnit::create(['terminal_id' => '23005597', 'card_terminal_id' => $nayax->id]);
        $user = $this->staff(['read machine-settings', 'update machine-settings']);

        $this->actingAs($user)
            ->post('/vends/'.$vend->id.'/update', $this->savePayload($vend, [
                'card_terminal_unit_id' => $unit->id,
            ]))
            ->assertSessionHasNoErrors();

        $this->assertSame('nayax', CardTerminalBinding::firstOrFail()->provider);
    }

    public function test_a_long_company_name_cannot_overflow_the_provider_column(): void
    {
        // card_terminal_bindings.provider is varchar(20). An unmapped company
        // falls back to a slug of its own name, so a long name would fail the
        // INSERT and make the machine unsavable from Setting/Edit.
        $long = CardTerminal::create(['name' => 'Some Very Long Card Terminal Company Name Ltd']);
        $vend = $this->makeVend(7016);
        $unit = CardTerminalUnit::create(['terminal_id' => '23005599', 'card_terminal_id' => $long->id]);
        $user = $this->staff(['read machine-settings', 'update machine-settings']);

        $this->actingAs($user)
            ->post('/vends/'.$vend->id.'/update', $this->savePayload($vend, [
                'card_terminal_unit_id' => $unit->id,
            ]))
            ->assertSessionHasNoErrors();

        $provider = CardTerminalBinding::firstOrFail()->provider;
        $this->assertLessThanOrEqual(20, strlen($provider));
        $this->assertNotSame('nets', $provider, 'an unknown company must not be reconciled as NETS');
    }

    public function test_changing_the_terminal_closes_the_old_binding_and_opens_a_new_one(): void
    {
        $vend = $this->makeVend(7007);
        $old = CardTerminalUnit::create(['terminal_id' => '11111111', 'card_terminal_id' => $this->nets->id]);
        $new = CardTerminalUnit::create(['terminal_id' => '22222222', 'card_terminal_id' => $this->nets->id]);
        $user = $this->staff(['read machine-settings', 'update machine-settings']);

        CardTerminalBinding::create([
            'provider' => 'nets', 'terminal_id' => '11111111',
            'vend_id' => $vend->id, 'bound_from' => '2026-01-01',
        ]);

        $this->actingAs($user)
            ->post('/vends/'.$vend->id.'/update', $this->savePayload($vend, [
                'card_terminal_unit_id' => $new->id,
                'card_terminal_bound_from' => '2026-09-01',
            ]))
            ->assertSessionHasNoErrors();

        $closed = CardTerminalBinding::where('terminal_id', '11111111')->firstOrFail();
        $this->assertSame('2026-01-01', $closed->bound_from->toDateString(), 'history is never rewritten');
        $this->assertSame('2026-09-01', $closed->bound_until->toDateString());

        $opened = CardTerminalBinding::where('terminal_id', '22222222')->firstOrFail();
        $this->assertSame('2026-09-01', $opened->bound_from->toDateString());
        $this->assertNull($opened->bound_until);

        // The old terminal still resolves to this machine for a date inside its
        // window — a settlement report from August must keep matching.
        $this->assertSame(
            $vend->id,
            CardTerminalBinding::where('terminal_id', '11111111')->effectiveOn('2026-08-15')->value('vend_id')
        );
        $this->assertSame($old->id, $old->fresh()->id);
    }

    public function test_saving_the_same_terminal_again_leaves_the_binding_untouched(): void
    {
        $vend = $this->makeVend(7008);
        $unit = CardTerminalUnit::create(['terminal_id' => '33333333', 'card_terminal_id' => $this->nets->id]);
        $user = $this->staff(['read machine-settings', 'update machine-settings']);

        $binding = CardTerminalBinding::create([
            'provider' => 'nets', 'terminal_id' => '33333333',
            'vend_id' => $vend->id, 'bound_from' => '2026-01-01',
        ]);

        $this->actingAs($user)
            ->post('/vends/'.$vend->id.'/update', $this->savePayload($vend, [
                'card_terminal_unit_id' => $unit->id,
                'card_terminal_bound_from' => '2026-09-01',
            ]))
            ->assertSessionHasNoErrors();

        $this->assertSame(1, CardTerminalBinding::count());
        $this->assertSame('2026-01-01', $binding->fresh()->bound_from->toDateString());
        $this->assertNull($binding->fresh()->bound_until);
    }

    public function test_moving_a_terminal_to_another_machine_leaves_only_one_open_binding(): void
    {
        $from = $this->makeVend(7009);
        $to = $this->makeVend(7010);
        $unit = CardTerminalUnit::create(['terminal_id' => '44444444', 'card_terminal_id' => $this->nets->id]);
        $user = $this->staff(['read machine-settings', 'update machine-settings']);

        CardTerminalBinding::create([
            'provider' => 'nets', 'terminal_id' => '44444444',
            'vend_id' => $from->id, 'bound_from' => '2026-01-01',
        ]);

        $this->actingAs($user)
            ->post('/vends/'.$to->id.'/update', $this->savePayload($to, [
                'card_terminal_unit_id' => $unit->id,
                'card_terminal_bound_from' => '2026-09-02',
            ]))
            ->assertSessionHasNoErrors();

        $open = CardTerminalBinding::where('terminal_id', '44444444')->whereNull('bound_until')->get();
        $this->assertCount(1, $open, 'two open bindings would make matching pick a machine arbitrarily');
        $this->assertSame($to->id, $open->first()->vend_id);

        // The old machine keeps the closed row, so July's report still matches it.
        $this->assertSame(
            $from->id,
            CardTerminalBinding::where('terminal_id', '44444444')->effectiveOn('2026-07-15')->value('vend_id')
        );
    }

    public function test_clearing_the_terminal_closes_the_binding_without_deleting_history(): void
    {
        $vend = $this->makeVend(7011);
        CardTerminalUnit::create(['terminal_id' => '55555555', 'card_terminal_id' => $this->nets->id]);
        $user = $this->staff(['read machine-settings', 'update machine-settings']);

        CardTerminalBinding::create([
            'provider' => 'nets', 'terminal_id' => '55555555',
            'vend_id' => $vend->id, 'bound_from' => '2026-01-01',
        ]);

        $this->actingAs($user)
            ->post('/vends/'.$vend->id.'/update', $this->savePayload($vend, [
                'card_terminal_unit_id' => null,
            ]))
            ->assertSessionHasNoErrors();

        $binding = CardTerminalBinding::firstOrFail();
        $this->assertSame(now()->toDateString(), $binding->bound_until->toDateString());
        $this->assertSame(1, CardTerminalBinding::count());
    }

    public function test_a_save_that_does_not_mention_the_terminal_never_closes_a_binding(): void
    {
        $vend = $this->makeVend(7012);
        $user = $this->staff(['read machine-settings', 'update machine-settings']);

        CardTerminalBinding::create([
            'provider' => 'nets', 'terminal_id' => '66666666',
            'vend_id' => $vend->id, 'bound_from' => '2026-01-01',
        ]);

        // The APK/API callers of VendController::update know nothing about
        // terminals; their saves must leave the binding alone.
        $this->actingAs($user)
            ->post('/vends/'.$vend->id.'/update', $this->savePayload($vend))
            ->assertSessionHasNoErrors();

        $this->assertNull(CardTerminalBinding::firstOrFail()->bound_until);
    }

    public function test_bound_from_before_the_current_bindings_start_is_refused(): void
    {
        $vend = $this->makeVend(7013);
        $new = CardTerminalUnit::create(['terminal_id' => '77777777', 'card_terminal_id' => $this->nets->id]);
        $user = $this->staff(['read machine-settings', 'update machine-settings']);

        CardTerminalBinding::create([
            'provider' => 'nets', 'terminal_id' => '88888888',
            'vend_id' => $vend->id, 'bound_from' => '2026-06-01',
        ]);

        // An inverted range (bound_until < bound_from) can never satisfy
        // effectiveOn(), so every report line in the gap would go unmatched.
        $this->actingAs($user)
            ->post('/vends/'.$vend->id.'/update', $this->savePayload($vend, [
                'card_terminal_unit_id' => $new->id,
                'card_terminal_bound_from' => '2026-05-01',
            ]))
            ->assertSessionHasErrors('card_terminal_bound_from');

        $this->assertNull(CardTerminalBinding::where('terminal_id', '88888888')->value('bound_until'));
        $this->assertSame(0, CardTerminalBinding::where('terminal_id', '77777777')->count());
    }

    public function test_settings_page_opens_on_the_currently_fitted_terminal(): void
    {
        $vend = $this->makeVend(7014);
        $unit = CardTerminalUnit::create(['terminal_id' => '23005598', 'card_terminal_id' => $this->nets->id]);
        CardTerminalBinding::create([
            'provider' => 'nets', 'terminal_id' => '23005598',
            'vend_id' => $vend->id, 'bound_from' => '2026-08-01',
        ]);

        $this->actingAs($this->staff(['read machine-settings', 'update machine-settings']))
            ->get('/settings/vend/'.$vend->id.'/update')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Setting/Edit')
                ->where('cardTerminalBinding.card_terminal_unit_id', $unit->id)
                ->where('cardTerminalBinding.bound_from', '2026-08-01')
            );
    }

    // ---------------------------------------------------- settlement proof --

    /**
     * End-to-end: a terminal bound through the new Settings flow is matched by
     * CardSettlementMatcher exactly as one bound by the old page was.
     */
    public function test_a_terminal_bound_from_settings_still_matches_a_settlement_report(): void
    {
        $vend = $this->makeVend(7015);
        $unit = CardTerminalUnit::create(['terminal_id' => '23082824', 'card_terminal_id' => $this->nets->id]);
        $user = $this->staff(['read machine-settings', 'update machine-settings']);

        $this->actingAs($user)
            ->post('/vends/'.$vend->id.'/update', $this->savePayload($vend, [
                'card_terminal_unit_id' => $unit->id,
                'card_terminal_bound_from' => '2026-08-01',
            ]))
            ->assertSessionHasNoErrors();

        $card = PaymentMethod::create(['code' => 1, 'name' => 'Card Terminal', 'is_active' => true]);
        $transaction = VendTransaction::create([
            'order_id' => 'ORD-CTU-1',
            'vend_id' => $vend->id,
            'transaction_datetime' => '2026-08-29 22:31:07',
            'amount' => 240,
            'qty' => 1,
            'success_qty' => 1,
            'dispensed_qty' => 1,
            'vend_channel_id' => 0,
            'gst_vat_rate' => 0,
            'payment_method_id' => $card->id,
            'cashless_mfg' => 'Nets',
        ]);

        $report = CardSettlementReport::create([
            'provider' => 'nets',
            'original_filename' => 'test.csv',
            'status' => CardSettlementReport::STATUS_UPLOADED,
        ]);
        $row = CardSettlementRow::create([
            'card_settlement_report_id' => $report->id,
            'row_no' => 1,
            'txn_type' => 'Purchase',
            'terminal_id' => '23082824',
            'transaction_date' => '2026-08-29',
            'transaction_time' => '22:30:58',
            'time_is_partial' => false,
            'amount_cents' => 240,
            'fingerprint' => sha1('ctu-row-1'),
            'status' => CardSettlementRow::STATUS_PENDING,
        ]);

        app(CardSettlementMatcher::class)->match($report->fresh());

        $row->refresh();
        $this->assertSame(CardSettlementRow::STATUS_MATCHED, $row->status);
        $this->assertSame(
            $transaction->id,
            $row->matched_vend_transaction_id,
            'the report line must resolve to the sale through the new binding'
        );
    }
}
