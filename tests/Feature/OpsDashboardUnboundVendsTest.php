<?php

namespace Tests\Feature;

use App\Models\Operator;
use App\Models\Scopes\OperatorVendFilterScope;
use App\Models\User;
use App\Support\OperatorScope;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Operation Dashboard "Include unbound?" toggle (2026-09-06). The page is
 * taking over from Vend/Index, whose access is being withdrawn, so it must be
 * able to list machines that have no site binding (vends.customer_id NULL).
 *
 * Those rows come from a RIGHT JOIN and carry NULL customer columns — exactly
 * the shape that slips past Customer's operator global scope, which is why
 * the ceiling cases below exist (VendController::customerIndexBaseQuery).
 */
class OpsDashboardUnboundVendsTest extends TestCase
{
    use RefreshDatabase;

    private Operator $hipl;

    private Operator $opA;

    private Operator $opB;

    protected function setUp(): void
    {
        parent::setUp();

        config(['cache.default' => 'array']);
        Cache::flush();
        OperatorScope::flush();

        // Pinned id: the "sees the whole fleet" exemption keys on it, and
        // RefreshDatabase does not reset AUTO_INCREMENT between tests.
        DB::table('operators')->insert([
            'id' => OperatorVendFilterScope::UNRESTRICTED_OPERATOR_ID,
            'code' => 'HIPL',
            'name' => 'HIPL',
            'is_active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->hipl = Operator::withoutGlobalScopes()->findOrFail(OperatorVendFilterScope::UNRESTRICTED_OPERATOR_ID);
        $this->opA = Operator::withoutGlobalScopes()->create(['code' => 'OPA', 'name' => 'OPA', 'is_active' => true]);
        $this->opB = Operator::withoutGlobalScopes()->create(['code' => 'OPB', 'name' => 'OPB', 'is_active' => true]);
    }

    protected function tearDown(): void
    {
        OperatorScope::flush();
        parent::tearDown();
    }

    // ---------------------------------------------------------------- fixtures

    private function userFor(Operator $operator, bool $adminFilters = true): User
    {
        $user = User::factory()->create(['operator_id' => $operator->id]);
        $user->givePermissionTo(Permission::findOrCreate('read vend-customers', 'web'));
        if ($adminFilters) {
            $user->givePermissionTo(Permission::findOrCreate('admin-access vend-customers', 'web'));
        }
        OperatorScope::flush();

        return $user;
    }

    private function makeVend(Operator $operator, int $code, ?int $customerId): int
    {
        return DB::table('vends')->insertGetId([
            'code' => $code,
            'name' => "Machine {$code}",
            'operator_id' => $operator->id,
            'customer_id' => $customerId,
            'is_active' => 1,
            'is_testing' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function makeBoundVend(Operator $operator, int $code): int
    {
        $customerId = DB::table('customers')->insertGetId([
            'name' => "Site {$code}",
            'profile_id' => 1,
            'status_id' => 1,
            'is_active' => 1,
            'operator_id' => $operator->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $this->makeVend($operator, $code, $customerId);
    }

    private function makeUnboundVend(Operator $operator, int $code): int
    {
        return $this->makeVend($operator, $code, null);
    }

    /** @return int[] machine codes on the rendered grid, ascending */
    private function codesFor(User $user, array $query = []): array
    {
        $codes = [];

        $this->actingAs($user)
            ->get('/vends/customers?'.http_build_query(['autoload' => 1] + $query))
            ->assertOk()
            ->assertInertia(function (Assert $page) use (&$codes) {
                $codes = collect($page->toArray()['props']['vends']['data'])
                    ->pluck('code')
                    ->map(fn ($c) => (int) $c)
                    ->sort()
                    ->values()
                    ->all();
            });

        return $codes;
    }

    // ------------------------------------------------------------------ cases

    public function test_unbound_machines_are_hidden_by_default_and_listed_when_toggled(): void
    {
        $this->makeBoundVend($this->hipl, 1001);
        $this->makeUnboundVend($this->hipl, 1002);
        $user = $this->userFor($this->hipl);

        $this->assertSame([1001], $this->codesFor($user));
        $this->assertSame([1001, 1002], $this->codesFor($user, ['include_unbound_vends' => 1]));
    }

    /**
     * The page sends is_active=true by default with no visible control (it is
     * a site flag). A site-less row can never satisfy that, so the machine's
     * own flag stands in — otherwise the toggle silently adds nothing (found
     * on the local DB 2026-09-06: 387 rows off, 386 on, zero unbound rows).
     */
    public function test_hidden_site_active_default_judges_unbound_rows_by_the_machine(): void
    {
        $this->makeBoundVend($this->hipl, 1001);
        $this->makeUnboundVend($this->hipl, 1002);
        $inactive = $this->makeUnboundVend($this->hipl, 1003);
        DB::table('vends')->where('id', $inactive)->update(['is_active' => 0]);
        $user = $this->userFor($this->hipl);

        $this->assertSame([1001, 1002], $this->codesFor($user, ['include_unbound_vends' => 1, 'is_active' => 'true', 'group_siblings' => 1]));
        $this->assertSame([1001, 1002, 1003], $this->codesFor($user, ['include_unbound_vends' => 1, 'is_active' => 'all']));
    }

    public function test_grouped_mode_keeps_unbound_machines(): void
    {
        $this->makeBoundVend($this->hipl, 1001);
        $this->makeUnboundVend($this->hipl, 1002);
        $user = $this->userFor($this->hipl);

        $this->assertSame([1001, 1002], $this->codesFor($user, ['include_unbound_vends' => 1, 'group_siblings' => 1]));
    }

    public function test_unbound_row_reads_the_machine_where_it_would_read_the_site(): void
    {
        $this->makeUnboundVend($this->hipl, 1002);
        $user = $this->userFor($this->hipl);

        $this->actingAs($user)
            ->get('/vends/customers?'.http_build_query(['autoload' => 1, 'include_unbound_vends' => 1]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('vends.data.0.code', 1002)
                ->where('vends.data.0.customer_id', null)
                ->where('vends.data.0.is_active', true)      // vends.is_active stands in for the missing site
                ->where('vends.data.0.customer_is_active', false)
                ->where('vends.data.0.operator_code', 'HIPL') // operators join follows the machine
            );
    }

    /**
     * The Remote Modem / Modem badges used to read `vend.modemType` and
     * `vend.modemUnit`, both eager-loaded through Customer::vend() — so an
     * unbound machine (no customer row to hang the relation on) rendered a
     * false "N/A" while Vend/Index showed the same modem as Online. They now
     * read flat per-vend columns; this pins that.
     */
    public function test_unbound_row_carries_the_modem_badge_fields(): void
    {
        $modemTypeId = DB::table('modem_types')->insertGetId([
            'name' => 'Air724UGB4: 4G',
            'alias' => 'Square Module',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $modemUnitId = DB::table('modem_units')->insertGetId([
            'imei' => '869701076005621',
            'modem_type_id' => $modemTypeId,
            'is_online' => 1,
            'last_updated_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $vendId = $this->makeUnboundVend($this->hipl, 1002);
        DB::table('vends')->where('id', $vendId)->update([
            'modem_type_id' => $modemTypeId,
            'modem_unit_id' => $modemUnitId,
        ]);

        $user = $this->userFor($this->hipl);

        $this->actingAs($user)
            ->get('/vends/customers?'.http_build_query(['autoload' => 1, 'include_unbound_vends' => 1]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('vends.data.0.code', 1002)
                ->where('vends.data.0.customer_id', null)
                ->where('vends.data.0.modem_type_alias', 'Square Module')
                ->where('vends.data.0.modem_unit_is_online', 1)
                // Formatted by VendResource ("7s ago"), so only presence matters.
                ->whereNot('vends.data.0.modem_unit_last_updated_at', null)
            );
    }

    /**
     * "Will refund?" next to the Card Terminal badge: the flag of the acquirer
     * TID fitted TODAY (card_terminal_units.is_will_auto_refund, seeded from
     * the partner workbook). Resolved by correlated subqueries, never a join —
     * this page's plan is join-sensitive.
     */
    public function test_card_terminal_badge_carries_the_will_auto_refund_flag_of_the_terminal_fitted_today(): void
    {
        $nets = DB::table('card_terminals')->insertGetId(['name' => 'Nets', 'created_at' => now(), 'updated_at' => now()]);
        foreach ([['23100701', 1], ['23005589', 0], ['23077326', null]] as [$tid, $flag]) {
            DB::table('card_terminal_units')->insert([
                'terminal_id' => $tid, 'card_terminal_id' => $nets, 'is_will_auto_refund' => $flag,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        $yes = $this->makeBoundVend($this->hipl, 3001);
        $no = $this->makeBoundVend($this->hipl, 3002);
        $unknownFlag = $this->makeBoundVend($this->hipl, 3003);
        $noTerminal = $this->makeBoundVend($this->hipl, 3004);
        $expired = $this->makeBoundVend($this->hipl, 3005);
        DB::table('vends')->whereIn('id', [$yes, $no, $unknownFlag, $noTerminal, $expired])->update(['card_terminal_id' => $nets]);

        $bind = fn (int $vendId, string $tid, ?string $until = null) => DB::table('card_terminal_bindings')->insert([
            'provider' => 'nets', 'terminal_id' => $tid, 'vend_id' => $vendId,
            'bound_from' => now()->subMonth()->toDateString(), 'bound_until' => $until,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $bind($yes, '23100701');
        $bind($no, '23005589');
        $bind($unknownFlag, '23077326');
        // Terminal taken off the machine yesterday: today's row shows no flag.
        $bind($expired, '23100701', now()->subDay()->toDateString());

        $rows = [];
        $this->actingAs($this->userFor($this->hipl))
            ->get('/vends/customers?'.http_build_query(['autoload' => 1]))
            ->assertOk()
            ->assertInertia(function (Assert $page) use (&$rows) {
                foreach ($page->toArray()['props']['vends']['data'] as $row) {
                    $rows[(int) $row['code']] = $row;
                }
            });

        $this->assertSame('23100701', $rows[3001]['card_terminal_unit_id']);
        $this->assertTrue($rows[3001]['card_terminal_will_auto_refund']);
        $this->assertFalse($rows[3002]['card_terminal_will_auto_refund']);
        // Bound, but the workbook says nothing about that terminal.
        $this->assertSame('23077326', $rows[3003]['card_terminal_unit_id']);
        $this->assertNull($rows[3003]['card_terminal_will_auto_refund']);
        // No terminal at all, and a binding that ended: no badge either way.
        $this->assertNull($rows[3004]['card_terminal_unit_id']);
        $this->assertNull($rows[3004]['card_terminal_will_auto_refund']);
        $this->assertNull($rows[3005]['card_terminal_unit_id']);
        $this->assertNull($rows[3005]['card_terminal_will_auto_refund']);
    }

    /**
     * The global scope on Customer is lifted for this mode (it would drop
     * every NULL-customer row), so the ceiling has to be re-applied by hand.
     */
    public function test_operator_user_sees_only_their_own_unbound_machines(): void
    {
        $this->makeBoundVend($this->opA, 1001);
        $this->makeUnboundVend($this->opA, 1002);
        $this->makeUnboundVend($this->opB, 1003);
        $this->makeBoundVend($this->opB, 1004);

        $this->assertSame([1001, 1002], $this->codesFor($this->userFor($this->opA), ['include_unbound_vends' => 1]));
    }

    public function test_requested_operator_filter_cannot_widen_the_ceiling_for_unbound_machines(): void
    {
        $this->makeUnboundVend($this->opA, 1002);
        $this->makeUnboundVend($this->opB, 1003);

        $this->assertSame([], $this->codesFor($this->userFor($this->opA), [
            'include_unbound_vends' => 1,
            'operators' => [$this->opB->id],
        ]));
    }

    public function test_toggle_is_ignored_without_the_admin_filter_permission(): void
    {
        $this->makeBoundVend($this->hipl, 1001);
        $this->makeUnboundVend($this->hipl, 1002);

        $this->assertSame([1001], $this->codesFor($this->userFor($this->hipl, adminFilters: false), ['include_unbound_vends' => 1]));
    }
}
