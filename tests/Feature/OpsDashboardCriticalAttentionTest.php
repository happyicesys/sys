<?php

namespace Tests\Feature;

use App\Jobs\CreateVendData;
use App\Jobs\Vend\UpdateApkVersion;
use App\Models\Customer;
use App\Models\Scopes\OperatorVendFilterScope;
use App\Models\User;
use App\Models\Vend;
use App\Models\VendSmartAlert;
use App\Services\OpsDashboard\CriticalAttention;
use App\Services\VendDataService;
use App\Support\OperatorScope;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Operation Dashboard "Needs attention" (Brian, 2026-10-17): before Search, the machines that need
 * someone now — Machine Health's (1) offline and 2A–2E alerts, an APK that keeps restarting, a
 * frozen touch screen, and no sales for 48 h. Pinned: each rule lists exactly its machines, the
 * hardware rules skip freezers and chillers like Machine Health does, the endpoint answers only for
 * the machines the viewer's own dashboard would show, and the restart counter ignores reconnects
 * and installs.
 */
class OpsDashboardCriticalAttentionTest extends TestCase
{
    use RefreshDatabase;

    private const OTHER_OPERATOR_ID = 2;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-17 12:00:00');
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

    private function vend(int $code, array $attributes = [], int $operatorId = OperatorVendFilterScope::UNRESTRICTED_OPERATOR_ID, int $siteStatus = Customer::STATUS_ACTIVE): int
    {
        $customerId = DB::table('customers')->insertGetId([
            'name' => "Site {$code}", 'profile_id' => 1, 'status_id' => $siteStatus, 'is_active' => 1,
            'operator_id' => $operatorId, 'created_at' => now(), 'updated_at' => now(),
        ]);

        return DB::table('vends')->insertGetId(array_merge([
            'code' => $code, 'name' => "Machine {$code}", 'machine_type' => Vend::MACHINE_TYPE_VENDING_MACHINE,
            'operator_id' => $operatorId, 'customer_id' => $customerId, 'is_active' => 1, 'is_testing' => 0,
            'is_online' => 1, 'mqtt_last_updated_at' => now(), 'last_vend_transaction_at' => now()->subHour(),
            'created_at' => now(), 'updated_at' => now(),
        ], $attributes));
    }

    private function stat(int $vendId, string $metric, int $count, string $date = '2026-10-17'): void
    {
        DB::table('vend_daily_stats')->insert([
            'vend_id' => $vendId, 'vend_code' => (string) DB::table('vends')->where('id', $vendId)->value('code'),
            'date' => $date, 'metric' => $metric, 'count' => $count, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function alert(int $vendId, string $type, bool $active = true): void
    {
        VendSmartAlert::create([
            'vend_id' => $vendId, 'alert_type' => $type, 'severity' => 'high', 'is_active' => $active,
        ]);
    }

    /** @return array<string, string[]> group key => machine labels */
    private function labels(array $result): array
    {
        return collect($result['groups'])->mapWithKeys(fn ($g) => [$g['key'] => array_column($g['machines'], 'code_label')])->all();
    }

    private function viewer(int $operatorId = OperatorVendFilterScope::UNRESTRICTED_OPERATOR_ID): User
    {
        $user = User::factory()->create(['operator_id' => $operatorId]);
        $user->givePermissionTo(Permission::findOrCreate('read vend-customers', 'web'));
        $user->givePermissionTo(Permission::findOrCreate('admin-access vend-customers', 'web'));
        OperatorScope::flush();

        return $user;
    }

    public function test_each_rule_lists_exactly_its_machines(): void
    {
        $ids = [
            $this->vend(1001, ['is_online' => 0, 'mqtt_last_updated_at' => now()->subHours(3)]),
            $this->vend(1002, ['is_online' => 0, 'mqtt_last_updated_at' => now()->subHours(80), 'last_vend_transaction_at' => now()->subHours(80)]),
            $fan = $this->vend(1003),
            $restarts = $this->vend(1004),
            $fewRestarts = $this->vend(1005),
            $frozen = $this->vend(1006),
            $probes = $this->vend(1007),
            $probesOk = $this->vend(1008),
            $this->vend(1009, ['last_vend_transaction_at' => now()->subHours(50)]),
            $this->vend(1010, ['last_vend_transaction_at' => now()->subHours(10)]),
            $this->vend(1011, ['last_vend_transaction_at' => null]),
        ];
        $this->alert($fan, VendSmartAlert::TYPE_COMP_FAN_OFF);
        $this->alert($fan, VendSmartAlert::TYPE_TEMPS_ABOVE_0, active: false);
        $this->stat($restarts, 'offline_reboot', 6);
        $this->stat($fewRestarts, 'offline_reboot', 2);
        $this->stat($frozen, 'ui_looper_stall', 1, '2026-10-16');
        $this->stat($probes, 'ui_probe_fail', 3);
        $this->stat($probesOk, 'ui_probe_fail', 1);
        $this->stat($probesOk, 'ui_probe_pass', 5);

        $result = app(CriticalAttention::class)->forVendIds($ids);
        $labels = $this->labels($result);

        $this->assertSame(['1001'], $labels['offline'], 'offline under the Machine Health window only');
        $this->assertSame(['1003'], $labels[VendSmartAlert::TYPE_COMP_FAN_OFF]);
        $this->assertSame([], $labels[VendSmartAlert::TYPE_TEMPS_ABOVE_0], 'a cleared alert is not listed');
        $this->assertSame(['1004'], $labels['apk_restart']);
        $this->assertSame(['1006', '1007'], $labels['screen']);
        $this->assertSame(['1002', '1009'], $labels['no_sales'], 'never sold = not flagged');
        $this->assertSame(11, $result['machine_count']);
        $this->assertSame(7, $result['flagged_count']);
        $this->assertSame('Site 1001', $result['groups'][0]['machines'][0]['site']);
        $this->assertSame('2A Fan OFF', $result['groups'][1]['short'], 'the tag each full grid row carries');
    }

    /**
     * Brian, 2026-10-25 (machine 2809): a stall the APK saw clear by itself 15 s later is not a
     * frozen screen — only a stall with no recovery after it is (still stuck, or it ended in a
     * reboot). Netted over today and yesterday, so a recovery just after midnight still counts.
     */
    public function test_a_screen_stall_that_recovered_by_itself_is_not_flagged(): void
    {
        $ids = [
            $recovered = $this->vend(1101),
            $stuck = $this->vend(1102),
            $acrossMidnight = $this->vend(1103),
            $oneOfTwo = $this->vend(1104),
            $noFrames = $this->vend(1105),
        ];
        $this->stat($recovered, 'ui_looper_stall', 1);
        $this->stat($recovered, 'ui_looper_recovered', 1);
        $this->stat($stuck, 'ui_looper_stall', 1, '2026-10-16');
        $this->stat($acrossMidnight, 'ui_looper_stall', 1, '2026-10-16');
        $this->stat($acrossMidnight, 'ui_looper_recovered', 1);
        $this->stat($oneOfTwo, 'ui_looper_stall', 2);
        $this->stat($oneOfTwo, 'ui_looper_recovered', 1);
        $this->stat($noFrames, 'ui_no_frames', 1);

        $result = app(CriticalAttention::class)->forVendIds($ids);
        $screen = collect($result['groups'])->firstWhere('key', 'screen');

        $this->assertSame(['1102', '1104', '1105'], array_column($screen['machines'], 'code_label'));
        $this->assertSame('1 freeze (not recovered)', $screen['machines'][0]['detail']);
    }

    public function test_hardware_rules_skip_freezers_and_chillers_but_no_sales_does_not(): void
    {
        $freezer = $this->vend(50001, [
            'machine_type' => Vend::MACHINE_TYPE_SMART_FREEZER,
            'is_online' => 0, 'mqtt_last_updated_at' => now()->subHours(2), 'last_vend_transaction_at' => now()->subHours(60),
        ]);
        $this->alert($freezer, VendSmartAlert::TYPE_TEMPS_ABOVE_0);
        $this->stat($freezer, 'offline_reboot', 9);

        $labels = $this->labels(app(CriticalAttention::class)->forVendIds([$freezer]));

        $this->assertSame([], $labels['offline']);
        $this->assertSame([], $labels[VendSmartAlert::TYPE_TEMPS_ABOVE_0]);
        $this->assertSame([], $labels['apk_restart']);
        $this->assertSame(['50001'], $labels['no_sales']);
    }

    public function test_the_endpoint_answers_only_for_the_viewers_own_machines(): void
    {
        $this->vend(2001, ['is_online' => 0, 'mqtt_last_updated_at' => now()->subHours(2)]);
        $this->vend(3001, ['is_online' => 0, 'mqtt_last_updated_at' => now()->subHours(2)], self::OTHER_OPERATOR_ID);

        $other = $this->actingAs($this->viewer(self::OTHER_OPERATOR_ID))
            ->postJson('/vends/customers/critical-attention')
            ->assertOk()->json();
        $this->assertSame(['3001'], $this->labels($other)['offline']);

        $this->actingAs(User::factory()->create(['operator_id' => OperatorVendFilterScope::UNRESTRICTED_OPERATOR_ID]))
            ->postJson('/vends/customers/critical-attention')
            ->assertForbidden();
    }

    public function test_an_inactive_machine_is_left_out_like_the_cards_leave_it_out(): void
    {
        $this->vend(4001, ['is_online' => 0, 'mqtt_last_updated_at' => now()->subHours(2)]);
        $this->vend(4002, ['is_online' => 0, 'mqtt_last_updated_at' => now()->subHours(2), 'is_active' => 0]);

        $result = $this->actingAs($this->viewer())->postJson('/vends/customers/critical-attention')->assertOk()->json();

        $this->assertSame(['4001'], $this->labels($result)['offline']);
    }

    public function test_the_section_counts_the_same_machines_as_the_cards(): void
    {
        $this->vend(5001);
        $this->vend(5002, ['is_online' => 0, 'mqtt_last_updated_at' => now()->subHours(2)]);
        $this->vend(5003, siteStatus: Customer::STATUS_INACTIVE);
        $this->vend(5004, ['is_active' => 0]);
        $viewer = $this->viewer();

        $cards = null;
        $this->actingAs($viewer)->get('/vends/customers')->assertOk()
            ->assertInertia(function (Assert $page) use (&$cards) {
                $cards = $page->toArray()['props']['initialStats']['vmCount'] ?? null;
            });
        $section = $this->actingAs($viewer)->postJson('/vends/customers/critical-attention')->assertOk()->json('machine_count');

        $this->assertNotNull($cards);
        $this->assertSame($cards, $section);
    }

    public function test_the_full_rows_load_as_a_partial_reload_of_just_those_machines(): void
    {
        foreach ([6001, 6002, 6003] as $code) {
            $this->vend($code);
        }

        $response = $this->actingAs($this->viewer())->withHeaders([
            'X-Inertia' => 'true',
            'X-Inertia-Version' => (string) app(\App\Http\Middleware\HandleInertiaRequests::class)->version(request()),
            'X-Inertia-Partial-Component' => 'Vend/CustomerIndex',
            'X-Inertia-Partial-Data' => 'vends',
        ])->get('/vends/customers?autoload=true&codes=6001,6003&numberPerPage=All&defer_aggregates=0');

        $response->assertOk();
        $props = $response->json('props');
        $this->assertArrayHasKey('vends', $props);
        $this->assertArrayNotHasKey('initialStats', $props, 'only the rows travel, the cards stay as they were');
        $codes = collect($props['vends']['data'])->pluck('code')->map(fn ($c) => (int) $c)->sort()->values()->all();
        $this->assertSame([6001, 6003], $codes);
    }

    private function pwron(int $code, int $frameId, string $apkVersion): void
    {
        $service = new VendDataService;
        $input = [
            'f' => (string) $frameId, 't' => '5', 'm' => (string) $code, 'g' => '20',
            'p' => base64_encode(json_encode(['Type' => 'PWRON', 'apkver' => $apkVersion])),
        ];
        $std = $service->standardizedVendData($input, 'mqtt');
        $service->processVendData($std, $service->decodeVendData($std), '10.0.0.1', 'mqtt');
    }

    public function test_a_restart_is_counted_but_a_reconnect_or_an_install_is_not(): void
    {
        Queue::fake([UpdateApkVersion::class, CreateVendData::class]);
        $vendId = Vend::forceCreate(['code' => 2031])->id;
        $restarts = fn () => (int) DB::table('vend_daily_stats')->where('vend_id', $vendId)->where('metric', 'apk_restart')->sum('count');

        $this->pwron(2031, 40, '317');   // first sighting: nothing to compare with
        $this->pwron(2031, 120, '317');  // counter climbed: a reconnect of the same process
        $this->assertSame(0, $restarts());

        $this->pwron(2031, 6, '317');    // counter fell back: the process restarted
        $this->assertSame(1, $restarts());

        $this->pwron(2031, 6, '318');    // fell back on a new build: an install, not a crash
        $this->assertSame(1, $restarts());

        $this->pwron(2031, 8, '318');
        $this->pwron(2031, 4, '318');
        $this->assertSame(2, $restarts());
        $this->assertSame(6, (int) DB::table('vend_daily_stats')->where('vend_id', $vendId)->where('metric', 'pwron')->sum('count'));
    }
}
