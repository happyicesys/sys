<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\HappyHourCampaign;
use App\Models\Operator;
use App\Models\Scopes\OperatorVendFilterScope;
use App\Models\User;
use App\Support\OperatorScope;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * The "★ HH" badge in the Machine ID column of the Operation Dashboard (2026-10-15): every Happy
 * Hour campaign a machine is bound to, active and not past its last day, with why a bound machine
 * is not running it (bound to a UI Setting, which beats Happy Hour, or not an eligible board/app).
 */
class OpsDashboardHappyHourBadgeTest extends TestCase
{
    use RefreshDatabase;

    private Operator $hipl;

    protected function setUp(): void
    {
        parent::setUp();

        config(['cache.default' => 'array']);
        Cache::flush();
        OperatorScope::flush();
        Carbon::setTestNow(Carbon::parse('2026-10-15 13:00:00'));

        DB::table('operators')->insert([
            'id' => OperatorVendFilterScope::UNRESTRICTED_OPERATOR_ID,
            'code' => 'HIPL',
            'name' => 'HIPL',
            'is_active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->hipl = Operator::withoutGlobalScopes()->findOrFail(OperatorVendFilterScope::UNRESTRICTED_OPERATOR_ID);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        OperatorScope::flush();
        parent::tearDown();
    }

    private function makeVend(int $code, int $apk): int
    {
        $customerId = DB::table('customers')->insertGetId([
            'name' => "Site {$code}",
            'profile_id' => 1,
            'status_id' => 1,
            'is_active' => 1,
            'operator_id' => $this->hipl->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return DB::table('vends')->insertGetId([
            'code' => $code,
            'name' => "Machine {$code}",
            'machine_type' => 'vending_machine',
            'apk_ver_json' => json_encode(['apkver' => $apk, 'buildtime' => '2026-10-14', 'deviceType' => 'ZC-328']),
            'operator_id' => $this->hipl->id,
            'customer_id' => $customerId,
            'is_active' => 1,
            'is_testing' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function campaign(array $vendIds, array $overrides = []): HappyHourCampaign
    {
        $campaign = HappyHourCampaign::create(array_merge([
            'name' => 'Afternoon HH', 'operator_id' => $this->hipl->id, 'is_active' => true,
            'days_mask' => HappyHourCampaign::WEEKDAYS, 'window_start' => '14:00:00', 'window_end' => '18:00:00',
            'slot_minutes' => 60, 'sku_count' => 1, 'selection_rule' => HappyHourCampaign::RULE_BALANCE_PCT,
            'lookback_days' => 14, 'discount_pct' => 30, 'min_balance_pct' => 15, 'min_qty' => 2,
            'price_step_cents' => 10, 'allow_below_cost' => false,
        ], $overrides));
        $campaign->vends()->attach($vendIds);

        return $campaign;
    }

    private function uiSettingCampaign(int $vendId, array $campaign = []): void
    {
        $settingId = DB::table('apk_settings')->insertGetId([
            'name' => 'Label promo setting', 'settings_parameter_json' => json_encode([]),
            'operator_id' => $this->hipl->id, 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('apk_setting_vend')->insert(['apk_setting_id' => $settingId, 'vend_id' => $vendId, 'created_at' => now(), 'updated_at' => now()]);
        $row = Campaign::create(array_merge([
            'name' => 'Buy 2 for $5', 'operator_id' => $this->hipl->id, 'is_active' => true, 'promo_type' => 'Amount',
            'start_at' => '2026-10-01 00:00:00', 'end_at' => '2026-12-31 00:00:00',
        ], $campaign));
        DB::table('apk_setting_campaign')->insert(['apk_setting_id' => $settingId, 'campaign_id' => $row->id, 'created_at' => now(), 'updated_at' => now()]);
    }

    /** @return array<int, array<int, array<string, mixed>>> happy_hour_campaigns keyed by machine code */
    private function badges(): array
    {
        $user = User::factory()->create(['operator_id' => $this->hipl->id]);
        $user->givePermissionTo(Permission::findOrCreate('read vend-customers', 'web'));
        $user->givePermissionTo(Permission::findOrCreate('admin-access vend-customers', 'web'));
        OperatorScope::flush();

        $rows = [];
        $this->actingAs($user)
            ->get('/vends/customers?autoload=1')
            ->assertOk()
            ->assertInertia(function (Assert $page) use (&$rows) {
                foreach ($page->toArray()['props']['vends']['data'] as $row) {
                    $rows[(int) $row['code']] = $row['vend']['happy_hour_campaigns'] ?? null;
                }
            });

        return $rows;
    }

    public function test_a_running_campaign_shows_its_terms(): void
    {
        $vendId = $this->makeVend(2003, 316);
        $this->campaign([$vendId], ['ends_on' => '2026-10-31']);
        $this->campaign([$vendId], ['name' => 'Switched off', 'is_active' => false]);
        $this->campaign([$vendId], ['name' => 'Ended', 'ends_on' => '2026-10-14']);
        $this->campaign([$vendId], ['name' => 'Deleted'])->delete();
        $this->makeVend(2004, 316);

        $badges = $this->badges();

        $this->assertCount(1, $badges[2003], 'inactive, ended and deleted campaigns are not badged');
        $this->assertSame('Afternoon HH', $badges[2003][0]['name']);
        $this->assertSame(30, $badges[2003][0]['discount_pct']);
        $this->assertSame('Weekdays', $badges[2003][0]['days']);
        $this->assertSame('14:00–18:00', $badges[2003][0]['window']);
        $this->assertSame('2026-10-31', $badges[2003][0]['ends_on']);
        $this->assertNull($badges[2003][0]['starts_on']);
        $this->assertNull($badges[2003][0]['ignored_by']);
        $this->assertNull($badges[2003][0]['ineligible']);
        $this->assertSame([], $badges[2004], 'a machine in no campaign gets no badge');
    }

    public function test_a_campaign_ending_today_and_one_not_started_yet_are_both_badged(): void
    {
        $vendId = $this->makeVend(2003, 316);
        $this->campaign([$vendId], ['name' => 'Last day', 'ends_on' => '2026-10-15']);
        $this->campaign([$vendId], ['name' => 'Next week', 'starts_on' => '2026-10-20']);

        $badges = collect($this->badges()[2003])->keyBy('name');

        $this->assertSame('2026-10-15', $badges['Last day']['ends_on']);
        $this->assertSame('2026-10-20', $badges['Next week']['starts_on']);
    }

    public function test_any_bound_ui_setting_makes_it_ignored(): void
    {
        $vendId = $this->makeVend(2003, 316);
        $this->campaign([$vendId]);
        // The UI Setting's own campaign has not started: Happy Hour is ignored all the same.
        $this->uiSettingCampaign($vendId, ['start_at' => '2026-11-01 00:00:00']);

        $this->assertSame('Label promo setting', $this->badges()[2003][0]['ignored_by']);
    }

    public function test_an_old_app_is_flagged_as_not_running(): void
    {
        $vendId = $this->makeVend(2844, 220);
        $this->campaign([$vendId]);

        $this->assertStringContainsString('needs 315', $this->badges()[2844][0]['ineligible']);
    }
}
