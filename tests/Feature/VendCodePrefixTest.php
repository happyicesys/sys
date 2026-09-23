<?php

namespace Tests\Feature;

use App\Contracts\Citybox\ChillerGateway;
use App\Models\Operator;
use App\Models\User;
use App\Models\Vend;
use App\Services\Citybox\DeviceSyncService;
use App\Support\VendCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Spatie\Permission\Models\Permission;
use Tests\Support\Citybox\FakeChillerGateway;
use Tests\TestCase;

/**
 * vends.code_prefix (2026-09-19): OPS Pro owns a CityBox chiller's machine ID
 * ("C6003"); every other machine keeps a bare mark1 number. A prefixed code may
 * share its number with an old vending machine, so terminal lookups resolve by
 * bare number only, and a new unprefixed machine can never take a chiller's number.
 */
class VendCodePrefixTest extends TestCase
{
    use RefreshDatabase;

    private FakeChillerGateway $gw;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        config(['citybox.openapi.enabled' => true, 'citybox.openapi.app_id' => 'APP', 'citybox.openapi.secret' => 'S']);
        $this->gw = new FakeChillerGateway;
        $this->app->instance(ChillerGateway::class, $this->gw);
    }

    private function chiller(string $eq, int $code, ?string $prefix = 'C', ?string $lastName = null): Vend
    {
        return Vend::create([
            'code' => $code, 'code_prefix' => $prefix, 'machine_type' => Vend::MACHINE_TYPE_SMART_CHILLER,
            'citybox_equipment_id' => $eq, 'is_active' => 1,
            'citybox_status_json' => $lastName ? ['name' => $lastName] : null,
        ]);
    }

    // ── the minute poll follows OPS Pro ────────────────────────────────────

    public function test_a_legacy_chiller_adopts_its_ops_pro_machine_id_on_the_next_poll(): void
    {
        // The 7 live chillers carry CB running numbers 10001… until the first poll after deploy.
        $vend = $this->chiller('ICB26F93C1HE', 10009, prefix: null, lastName: 'C6003');
        $this->gw->seedDevice('ICB26F93C1HE', 'C6003');

        app(DeviceSyncService::class)->syncFleet();

        $this->assertSame('C6003', $vend->fresh()->codeLabel());
    }

    public function test_a_rename_in_ops_pro_renumbers_the_chiller(): void
    {
        $vend = $this->chiller('E1', 6001, lastName: 'C6001');
        $this->gw->seedDevice('E1', 'C6004 HI Office');

        app(DeviceSyncService::class)->syncFleet();

        $this->assertSame('C6004', $vend->fresh()->codeLabel());
    }

    public function test_a_duplicate_ops_pro_name_keeps_the_current_id(): void
    {
        $a = $this->chiller('E1', 6001, lastName: 'C6001');
        $b = $this->chiller('E2', 6002, lastName: 'C6002');
        $this->gw->seedDevice('E1', 'C6001')->seedDevice('E2', 'C6001');

        app(DeviceSyncService::class)->syncFleet();

        $this->assertSame('C6001', $a->fresh()->codeLabel());
        $this->assertSame('C6002', $b->fresh()->codeLabel());
    }

    public function test_a_name_without_a_machine_id_keeps_the_current_id(): void
    {
        $vend = $this->chiller('E1', 6001, lastName: 'C6001');
        $this->gw->seedDevice('E1', 'Singapore8');

        app(DeviceSyncService::class)->syncFleet();

        $this->assertSame('C6001', $vend->fresh()->codeLabel());
        $this->assertSame('Singapore8', $vend->fresh()->citybox_status_json['name']);
    }

    // ── terminal lookups never land on a chiller ───────────────────────────

    public function test_bare_code_lookup_ignores_a_prefixed_vend_with_the_same_number(): void
    {
        // Worst order for an unqualified where('code')->first(): the chiller has the lower id.
        $chiller = $this->chiller('E1', 6003);
        $vending = Vend::create(['code' => 6003, 'is_active' => 1]);

        $this->assertSame($vending->id, Vend::bareCode(6003)->first()->id);
        $this->assertSame($vending->id, Vend::bareCode('6003')->first()->id);
        $this->assertNotSame($chiller->id, Vend::bareCode(6003)->value('id'));
        $this->assertNull(Vend::bareCode(6004)->first());
    }

    // ── search boxes ───────────────────────────────────────────────────────

    public function test_search_understands_prefixed_machine_ids(): void
    {
        $chiller = $this->chiller('E1', 6001);
        $vending = Vend::create(['code' => 6001, 'is_active' => 1]);
        $other = Vend::create(['code' => 2031, 'is_active' => 1]);

        $ids = fn (string $search, bool $contains = false) => Vend::query()
            ->tap(fn ($q) => VendCode::whereSearch($q, $search, $contains))
            ->orderBy('id')->pluck('id')->all();

        $this->assertSame([$chiller->id], $ids('C6001'));
        $this->assertSame([$chiller->id], $ids('c60'));
        $this->assertSame([$chiller->id], $ids('C'));
        $this->assertSame([$chiller->id, $vending->id], $ids('6001'));   // a bare number finds both
        $this->assertSame([$chiller->id, $other->id], $ids('C6001, 2031'));
        $this->assertSame([$other->id], $ids('2031'));
    }

    // ── exports print the label ────────────────────────────────────────────

    public function test_the_sql_label_reads_c6001_for_a_chiller_and_the_bare_number_otherwise(): void
    {
        $chiller = $this->chiller('E1', 6001);
        $vending = Vend::create(['code' => 2031, 'is_active' => 1]);

        $labels = Vend::query()->whereIn('id', [$chiller->id, $vending->id])->orderBy('id')
            ->selectRaw(VendCode::sqlLabel().' AS label')->pluck('label')->all();

        $this->assertSame(['C6001', '2031'], array_map('strval', $labels));
    }

    // ── the machine ID people READ is the label, everywhere ───────────────

    public function test_a_dropdown_label_names_the_chiller_c6001_not_6001(): void
    {
        // VendResource::full_name / cust_full_name is the canonical machine-picker
        // label, feeding eight controllers' vendOptions (Brian, 2026-09-23).
        $customer = \App\Models\Customer::create([
            'name' => 'Raffles L1', 'code' => 'RL1', 'operator_id' => 1,
            'status_id' => \App\Models\Customer::STATUS_ACTIVE,
        ]);
        $chiller = $this->chiller('E1', 6001);
        $chiller->forceFill(['customer_id' => $customer->id])->save();
        $vending = Vend::create(['code' => 2031, 'is_active' => 1, 'customer_id' => $customer->id]);

        $label = fn (Vend $v) => \App\Http\Resources\VendResource::make(
            Vend::withoutGlobalScopes()->with('customer')->find($v->id)
        )->resolve()['cust_full_name'];

        $this->assertStringContainsString('(C6001)', $label($chiller));
        $this->assertStringNotContainsString('(6001)', $label($chiller));
        $this->assertStringContainsString('(2031)', $label($vending), 'an unprefixed machine is unchanged');
    }

    public function test_the_card_terminal_list_shows_the_prefixed_machine_id(): void
    {
        $chiller = $this->chiller('E1', 6001);
        $unit = \App\Models\CardTerminalUnit::create(['terminal_id' => 'T-1', 'card_terminal_id' => 1]);
        \App\Models\CardTerminalBinding::create([
            'card_terminal_unit_id' => $unit->id, 'terminal_id' => 'T-1', 'provider' => 'nets',
            'vend_id' => $chiller->id, 'bound_from' => now()->subDay()->toDateString(),
        ]);

        $user = User::factory()->create();
        $user->givePermissionTo(\Spatie\Permission\Models\Permission::findOrCreate('read card-terminals', 'web'));

        $this->actingAs($user)->get('/card-terminal-units')->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('cardTerminalUnits.data.0.current_vend_code', 'C6001')
                ->where('cardTerminalUnits.data.0.binding_history.0.vend_code', 'C6001')
            );
    }

    public function test_the_channel_export_prints_the_machine_id_ops_pro_uses(): void
    {
        // Finance and ops compare these files against OPS Pro by machine name.
        $operator = Operator::create(['code' => 'HIPL', 'name' => 'HI SG', 'country_id' => 1]);
        $user = User::factory()->create(['operator_id' => $operator->id]);
        $chiller = $this->chiller('E1', 6001);
        // The export lists bound machines under the viewer's operator.
        $site = \App\Models\Customer::create(['name' => 'Bosch 30F', 'code' => 10001, 'operator_id' => $operator->id, 'status_id' => \App\Models\Customer::STATUS_ACTIVE]);
        $chiller->forceFill(['customer_id' => $site->id, 'operator_id' => $operator->id])->save();
        \App\Models\VendChannel::create(['vend_id' => $chiller->id, 'code' => 101, 'qty' => 1, 'capacity' => 5, 'is_active' => true]);

        $response = $this->actingAs($user)->get('/vends/channels/excel');
        $response->assertOk();
        $path = tempnam(sys_get_temp_dir(), 'channels').'.xlsx';
        file_put_contents($path, $response->streamedContent());
        try {
            $rows = (new \Rap2hpoutre\FastExcel\FastExcel)->import($path);
        } finally {
            @unlink($path);
        }

        $this->assertSame('C6001', (string) $rows->first()['Machine ID']);
    }

    // ── a new mark1 number never takes a chiller's ─────────────────────────

    public function test_machine_create_refuses_a_number_a_chiller_holds(): void
    {
        $mine = Operator::create(['code' => 'MINE', 'name' => 'Mine', 'country_id' => 1]);
        foreach (['read machine-settings', 'create machine-settings'] as $perm) {
            Permission::findOrCreate($perm, 'web');
        }
        $user = User::factory()->create(['operator_id' => $mine->id]);
        $user->givePermissionTo(['read machine-settings', 'create machine-settings']);
        $this->chiller('E1', 6003);

        $this->actingAs($user)
            ->post('/settings/vend/store', ['code' => 6003, 'machine_type' => 'vending_machine', 'begin_date' => '2026-09-19'])
            ->assertSessionHasErrors(['code' => 'Machine ID 6003 is taken by C6003.']);

        $this->assertSame(1, Vend::withoutGlobalScopes()->where('code', 6003)->count());
    }
}
