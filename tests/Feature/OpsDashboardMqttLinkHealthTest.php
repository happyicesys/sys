<?php

namespace Tests\Feature;

use App\Models\Operator;
use App\Models\Scopes\OperatorVendFilterScope;
use App\Models\User;
use App\Support\OperatorScope;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * "MQTT Offline (min)" on the Operation Dashboard (2026-09-25).
 *
 * Per-day seconds with no subscribed MQTT session, reported by big 306+ /
 * small v14+ and stored in vend_daily_stats (metric=mqtt_offline_s). The
 * contract pinned here is the one that keeps the column honest: a machine
 * that sends nothing reads as NULL ("–"), never as 0 minutes, on the inline
 * path, on the deferred-aggregates path and in the sort (no data sorts last
 * in both directions).
 */
class OpsDashboardMqttLinkHealthTest extends TestCase
{
    use RefreshDatabase;

    private Operator $hipl;

    /** @var array<int,int> code => vend id */
    private array $vendIds = [];

    /** @var array<int,int> code => customer id */
    private array $customerIds = [];

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-09-25 11:00:00');
        config(['cache.default' => 'array']);
        Cache::flush();
        OperatorScope::flush();

        DB::table('operators')->insert([
            'id' => OperatorVendFilterScope::UNRESTRICTED_OPERATOR_ID,
            'code' => 'HIPL', 'name' => 'HIPL', 'is_active' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->hipl = Operator::withoutGlobalScopes()->findOrFail(OperatorVendFilterScope::UNRESTRICTED_OPERATOR_ID);

        $this->makeVend(1001); // reports: 425 s today, nothing yesterday, 4000 s two days ago
        $this->makeVend(1002); // older build: reports nothing
        $this->makeVend(1003); // reports a real zero today

        $this->stat(1001, '2026-09-25', 'mqtt_offline_s', 425);
        $this->stat(1001, '2026-09-23', 'mqtt_offline_s', 4000);
        $this->stat(1001, '2026-09-25', 'mqtt_drops', 3);
        $this->stat(1001, '2026-09-25', 'mqtt_recycles', 1);
        $this->stat(1001, '2026-09-25', 'mqtt_conn_fails', 8);
        $this->stat(1003, '2026-09-25', 'mqtt_offline_s', 0);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        OperatorScope::flush();
        parent::tearDown();
    }

    private function makeVend(int $code): void
    {
        $customerId = DB::table('customers')->insertGetId([
            'name' => "Site {$code}", 'profile_id' => 1, 'status_id' => 1, 'is_active' => 1,
            'operator_id' => $this->hipl->id, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->customerIds[$code] = $customerId;
        $this->vendIds[$code] = DB::table('vends')->insertGetId([
            'code' => $code, 'name' => "Machine {$code}", 'operator_id' => $this->hipl->id,
            'customer_id' => $customerId, 'is_active' => 1, 'is_testing' => 0,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function stat(int $code, string $date, string $metric, int $count): void
    {
        DB::table('vend_daily_stats')->insert([
            'vend_id' => $this->vendIds[$code], 'vend_code' => (string) $code, 'date' => $date,
            'metric' => $metric, 'count' => $count, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function user(): User
    {
        $user = User::factory()->create(['operator_id' => $this->hipl->id]);
        $user->givePermissionTo(Permission::findOrCreate('read vend-customers', 'web'));
        $user->givePermissionTo(Permission::findOrCreate('admin-access vend-customers', 'web'));
        OperatorScope::flush();

        return $user;
    }

    /** @return array<int, array<string,mixed>> rows keyed by machine code, in page order */
    private function page(array $query = []): array
    {
        $rows = [];
        $this->actingAs($this->user())
            ->get('/vends/customers?'.http_build_query(['autoload' => 1] + $query))
            ->assertOk()
            ->assertInertia(function (Assert $page) use (&$rows) {
                foreach ($page->toArray()['props']['vends']['data'] as $vend) {
                    $rows[(int) $vend['code']] = $vend;
                }
            });

        return $rows;
    }

    public function test_inline_rows_carry_minutes_and_keep_no_data_as_null(): void
    {
        $rows = $this->page();

        $this->assertSame(425, $rows[1001]['mqtt_offline_1d_s']);
        $this->assertNull($rows[1001]['mqtt_offline_2d_s'], 'a silent day is no data, not 0');
        $this->assertSame(4000, $rows[1001]['mqtt_offline_3d_s']);
        $this->assertSame(3, $rows[1001]['mqtt_drops_1d']);
        $this->assertSame(1, $rows[1001]['mqtt_recycles_1d']);
        $this->assertSame(8, $rows[1001]['mqtt_conn_fails_1d']);

        $this->assertNull($rows[1002]['mqtt_offline_1d_s'], 'an older build must not read as 0 min offline');
        $this->assertNull($rows[1002]['mqtt_drops_1d']);

        $this->assertSame(0, $rows[1003]['mqtt_offline_1d_s'], 'a real zero stays zero');
    }

    public function test_sorting_puts_machines_without_data_last_in_both_directions(): void
    {
        $desc = array_keys($this->page(['sortKey' => 'mqtt_offline_1d_s', 'sortBy' => 'false']));
        $this->assertSame([1001, 1003, 1002], $desc);

        $asc = array_keys($this->page(['sortKey' => 'mqtt_offline_1d_s', 'sortBy' => 'true']));
        $this->assertSame([1003, 1001, 1002], $asc);
    }

    public function test_the_deferred_aggregates_path_returns_the_same_fields(): void
    {
        $rows = collect([1001, 1002])->map(fn ($code) => [
            'vend_id' => $this->vendIds[$code], 'customer_id' => $this->customerIds[$code],
        ])->all();

        $map = $this->actingAs($this->user())
            ->postJson('/vends/customers/aggregates', ['rows' => $rows])
            ->assertOk()
            ->json('rows');

        $with = $map[(string) $this->vendIds[1001]];
        $this->assertSame(425, $with['mqtt_offline_1d_s']);
        $this->assertNull($with['mqtt_offline_2d_s']);
        $this->assertSame(3, $with['mqtt_drops_1d']);

        $without = $map[(string) $this->vendIds[1002]];
        $this->assertArrayHasKey('mqtt_offline_1d_s', $without, 'the key must be present so the merge overwrites');
        $this->assertNull($without['mqtt_offline_1d_s']);
    }
}
