<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Scopes\OperatorVendFilterScope;
use App\Models\User;
use App\Models\Vend;
use App\Services\OpsDashboard\MachineAlerts;
use App\Support\OperatorScope;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Operation Dashboard "Show Alert/Error" (Brian, 2026-10-19): one click lists the machines with at
 * least one red badge in the Error / Machine Status / Payment Device columns, or a Needs attention
 * reason. Pinned: each badge rule flags exactly its machine, the door flag and inactive machines are
 * left out, and the page lists only those machines, among the viewer's own, with a count per reason.
 */
class OpsDashboardMachineAlertsTest extends TestCase
{
    use RefreshDatabase;

    private const OTHER_OPERATOR_ID = 2;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-19 12:00:00');
        config(['cache.default' => 'array']);
        Cache::flush();
        OperatorScope::flush();

        foreach ([[OperatorVendFilterScope::UNRESTRICTED_OPERATOR_ID, 'HIPL'], [self::OTHER_OPERATOR_ID, 'OTH']] as [$id, $code]) {
            DB::table('operators')->insert([
                'id' => $id, 'code' => $code, 'name' => $code, 'is_active' => 1,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        OperatorScope::flush();
        parent::tearDown();
    }

    private function vend(int $code, array $attributes = [], int $operatorId = OperatorVendFilterScope::UNRESTRICTED_OPERATOR_ID): int
    {
        $customerId = DB::table('customers')->insertGetId([
            'name' => "Site {$code}", 'profile_id' => 1, 'status_id' => Customer::STATUS_ACTIVE, 'is_active' => 1,
            'operator_id' => $operatorId, 'created_at' => now(), 'updated_at' => now(),
        ]);

        return DB::table('vends')->insertGetId(array_merge([
            'code' => $code, 'name' => "Machine {$code}", 'machine_type' => Vend::MACHINE_TYPE_VENDING_MACHINE,
            'operator_id' => $operatorId, 'customer_id' => $customerId, 'is_active' => 1, 'is_testing' => 0,
            'is_online' => 1, 'mqtt_last_updated_at' => now(), 'last_vend_transaction_at' => now()->subHour(),
            'created_at' => now(), 'updated_at' => now(),
        ], $attributes));
    }

    private function channelError(int $vendId, bool $cleared = false, bool $channelActive = true, int $code = 11): void
    {
        $channelId = DB::table('vend_channels')->insertGetId([
            'vend_id' => $vendId, 'code' => $code, 'is_active' => $channelActive, 'error_rate_json' => '{}',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('vend_channel_error_logs')->insert([
            'vend_channel_id' => $channelId, 'vend_channel_error_id' => 1, 'is_error_cleared' => $cleared,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function modem(bool $online, bool $reported = true): int
    {
        return DB::table('modem_units')->insertGetId([
            'is_online' => $online, 'last_updated_at' => $reported ? now() : null,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    /** @return array<int, string[]> vend id => reason keys */
    private function keys(array $result): array
    {
        return collect($result['by_vend'])->map(fn ($tags) => array_column($tags, 'key'))->all();
    }

    private function viewer(int $operatorId = OperatorVendFilterScope::UNRESTRICTED_OPERATOR_ID): User
    {
        $user = User::factory()->create(['operator_id' => $operatorId]);
        $user->givePermissionTo(Permission::findOrCreate('read vend-customers', 'web'));
        $user->givePermissionTo(Permission::findOrCreate('admin-access vend-customers', 'web'));
        OperatorScope::flush();

        return $user;
    }

    public function test_each_badge_rule_flags_exactly_its_machine(): void
    {
        $clean = $this->vend(1001, ['parameter_json' => json_encode(['Sensor' => '1', 'BILLStat' => 3, 'CHGEStat' => 3, 'CoinCnt' => 9000]), 'last_cash_vend_transaction_at' => now()]);
        $offline = $this->vend(1002, ['is_online' => 0, 'mqtt_last_updated_at' => now()->subDays(10), 'last_vend_transaction_at' => now()->subDays(10)]);
        $error = $this->vend(1003);
        $this->channelError($error);
        $cleared = $this->vend(1004);
        $this->channelError($cleared, cleared: true);
        $this->channelError($cleared, channelActive: false, code: 12);
        $rate = $this->vend(1005, ['vend_transaction_totals_json' => json_encode(['one_day_error_rate' => 0, 'two_days_error_rate' => 1.2, 'seven_days_error_rate' => 3.5])]);
        $sensor = $this->vend(1006, ['parameter_json' => json_encode(['Sensor' => '2'])]);
        $modem = $this->vend(1007, ['modem_unit_id' => $this->modem(online: false)]);
        $modemNa = $this->vend(1008, ['modem_unit_id' => $this->modem(online: false, reported: false)]);
        $bill = $this->vend(1009, ['parameter_json' => json_encode(['BILLStat' => 1])]);
        $coin = $this->vend(1010, ['parameter_json' => json_encode(['CHGEStat' => '1'])]);
        $float = $this->vend(1011, ['parameter_json' => json_encode(['CoinCnt' => 2500]), 'last_cash_vend_transaction_at' => now()]);
        $card = $this->vend(1012, ['card_terminal_id' => 1, 'last_card_vend_transaction_at' => now()->subDays(3)]);
        $door = $this->vend(1013, ['parameter_json' => json_encode(['door' => 'open'])]);
        $inactive = $this->vend(1014, ['is_active' => 0, 'is_online' => 0, 'parameter_json' => json_encode(['Sensor' => '0'])]);
        $recent = $this->vend(1015, ['is_online' => 0, 'mqtt_last_updated_at' => now()->subHours(3)]);

        $result = app(MachineAlerts::class)->forVendIds([$clean, $offline, $error, $cleared, $rate, $sensor, $modem, $modemNa, $bill, $coin, $float, $card, $door, $inactive, $recent]);
        $keys = $this->keys($result);

        $this->assertSame(['no_sales', 'http_offline'], $keys[$offline], 'offline past the Machine Health window: the HTTP badge, not (1)');
        $this->assertSame(['channel_error'], $keys[$error]);
        $this->assertSame(['error_rate'], $keys[$rate]);
        $this->assertSame('3.5%', $result['by_vend'][$rate][0]['detail']);
        $this->assertSame(['sensor_off'], $keys[$sensor]);
        $this->assertSame(['modem_offline'], $keys[$modem]);
        $this->assertSame(['bill_inactive'], $keys[$bill]);
        $this->assertSame(['coin_inactive'], $keys[$coin]);
        $this->assertSame(['coin_float_low'], $keys[$float]);
        $this->assertSame(['rail_card'], $keys[$card]);
        $this->assertSame(['offline'], $keys[$recent], 'a Needs attention offline machine is not tagged twice');
        foreach ([$clean, $cleared, $modemNa, $door, $inactive] as $quiet) {
            $this->assertArrayNotHasKey($quiet, $keys, "vend {$quiet} has no red badge");
        }
        $this->assertSame(10, $result['total']);
        $this->assertSame(1, collect($result['reasons'])->firstWhere('key', 'sensor_off')['count']);
    }

    public function test_the_button_lists_only_the_viewers_alert_machines_with_their_reasons(): void
    {
        $this->vend(2001);
        $this->vend(2002, ['parameter_json' => json_encode(['Sensor' => '2'])]);
        $this->vend(2003, ['parameter_json' => json_encode(['CoinCnt' => 100, 'Sensor' => '4']), 'last_cash_vend_transaction_at' => now()]);
        $this->vend(3001, ['parameter_json' => json_encode(['Sensor' => '2'])], self::OTHER_OPERATOR_ID);

        $page = fn (string $query) => $this->actingAs($this->viewer())
            ->get('/vends/customers?autoload=true&group_siblings=false&numberPerPage=All&'.$query)->assertOk();

        $props = null;
        $page('alerts_only=true')->assertInertia(function (Assert $p) use (&$props) {
            $props = $p->toArray()['props'];
        });
        $codes = collect($props['vends']['data'])->pluck('code')->map(fn ($c) => (int) $c)->sort()->values()->all();
        $this->assertSame([2002, 2003], $codes);
        $this->assertSame(2, $props['machineAlerts']['total']);
        $this->assertSame(3, $props['machineAlerts']['machine_count']);
        $this->assertSame(['sensor_off' => 2, 'coin_float_low' => 1], collect($props['machineAlerts']['reasons'])->pluck('count', 'key')->all());

        $page('alerts_only=true&alert_reason=coin_float_low')->assertInertia(function (Assert $p) use (&$props) {
            $props = $p->toArray()['props'];
        });
        $this->assertSame([2003], collect($props['vends']['data'])->pluck('code')->map(fn ($c) => (int) $c)->all(), 'a reason narrows the list');
        $this->assertSame(2, $props['machineAlerts']['total'], 'the counts stay over the whole list');

        $page('')->assertInertia(fn (Assert $p) => $p->where('machineAlerts', null)->has('vends.data', 3));
    }

    public function test_the_button_badge_counts_what_the_button_would_list(): void
    {
        $this->vend(4001);
        $this->vend(4002, ['parameter_json' => json_encode(['Sensor' => '2'])]);
        $this->vend(4003, ['parameter_json' => json_encode(['BILLStat' => 1])]);
        $this->vend(5001, ['parameter_json' => json_encode(['Sensor' => '2'])], self::OTHER_OPERATOR_ID);

        $this->actingAs($this->viewer())->postJson('/vends/customers/alert-count')
            ->assertOk()->assertExactJson(['total' => 2, 'machine_count' => 3]);
        $this->actingAs($this->viewer(self::OTHER_OPERATOR_ID))->postJson('/vends/customers/alert-count')
            ->assertOk()->assertExactJson(['total' => 1, 'machine_count' => 1]);
        $this->actingAs(User::factory()->create(['operator_id' => OperatorVendFilterScope::UNRESTRICTED_OPERATOR_ID]))
            ->postJson('/vends/customers/alert-count')->assertForbidden();
    }
}
