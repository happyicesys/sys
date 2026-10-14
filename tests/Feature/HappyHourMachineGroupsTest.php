<?php

namespace Tests\Feature;

use App\Models\HappyHourCampaign;
use App\Models\HappyHourSlot;
use App\Models\User;
use App\Models\Vend;
use App\Services\HappyHour\HappyHourMachineGroups;
use App\Services\HappyHour\HappyHourPlanner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Live machine groups (Brian, 2026-10-14): "All VM - Big Board" / "All Smart Freezers" bind every
 * machine of that kind of the campaign OWNER's operator that can run Happy Hour, and follow it — a big
 * board joins when it reaches app 315, a deactivated one leaves with its slots. Hand-picked machines
 * are untouched, and a machine another campaign holds is never taken.
 */
class HappyHourMachineGroupsTest extends TestCase
{
    use RefreshDatabase;

    private const OTHER = 27;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        Carbon::setTestNow('2026-10-14 09:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function freezer(int $code, int $apk, int $operatorId = 1): int
    {
        return DB::table('vends')->insertGetId([
            'code' => $code, 'operator_id' => $operatorId, 'machine_type' => Vend::MACHINE_TYPE_SMART_FREEZER,
            'is_active' => 1, 'apk_version_code' => $apk, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function vending(int $code, int $apk, string $deviceType = 'ZC-328', int $operatorId = 1, bool $active = true): int
    {
        return DB::table('vends')->insertGetId([
            'code' => $code, 'operator_id' => $operatorId, 'machine_type' => Vend::MACHINE_TYPE_VENDING_MACHINE,
            'is_active' => $active ? 1 : 0, 'apk_version_code' => $apk,
            'apk_ver_json' => json_encode(['apkver' => $apk, 'buildtime' => '2026-10-12', 'deviceType' => $deviceType]),
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function admin(int $operatorId = 1): User
    {
        $user = User::factory()->create(['operator_id' => $operatorId]);
        foreach (['read', 'create', 'update', 'delete'] as $action) {
            $user->givePermissionTo(Permission::findOrCreate("{$action} happy-hour-campaigns", 'web'));
        }

        return $user;
    }

    /** @param  array<string, mixed>  $overrides */
    private function form(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Everywhere', 'is_active' => true, 'starts_on' => null, 'ends_on' => null,
            'days_mask' => HappyHourCampaign::EVERY_DAY, 'window_start' => '14:00', 'window_end' => '18:00',
            'slot_minutes' => 60, 'sku_count' => 2, 'skus_per_slot' => 1, 'selection_rule' => HappyHourCampaign::RULE_BALANCE_PCT,
            'lookback_days' => 14, 'discount_pct' => 30, 'min_balance_pct' => 15, 'min_qty' => 2,
            'price_step_cents' => 10, 'allow_below_cost' => false, 'excluded_product_ids' => [], 'vend_ids' => [],
            'machine_groups' => [],
        ], $overrides);
    }

    /** @return array<int, ?string> vend id => via_group */
    private function members(HappyHourCampaign $campaign): array
    {
        return DB::table('happy_hour_campaign_vend')->where('happy_hour_campaign_id', $campaign->id)->orderBy('vend_id')
            ->pluck('via_group', 'vend_id')->mapWithKeys(fn ($g, $id) => [(int) $id => $g])->all();
    }

    public function test_a_group_binds_the_owners_eligible_machines_and_follows_them(): void
    {
        $freezer = $this->freezer(50001, 30);
        $oldFreezer = $this->freezer(50002, 20);              // app too old: not yet
        $bigReady = $this->vending(2031, 315);
        $bigOld = $this->vending(2844, 220);                  // joins when it reaches 315
        $this->vending(2638, 315, 'ZC-83A');                  // small board: never
        $this->vending(2900, 315, 'ZC-328', 1, false);        // inactive: never
        $this->vending(4101, 315, 'ZC-328', self::OTHER);     // another operator: never (Brian)
        $hand = $this->vending(2003, 315);

        $this->actingAs($this->admin())->post('/happy-hour-campaigns', $this->form([
            'machine_groups' => [HappyHourMachineGroups::SMART_FREEZER, HappyHourMachineGroups::VENDING_BIG_BOARD],
            'vend_ids' => [$hand],
        ]))->assertSessionHasNoErrors();
        $campaign = HappyHourCampaign::firstOrFail();

        $this->assertEquals([
            $freezer => HappyHourMachineGroups::SMART_FREEZER,
            $bigReady => HappyHourMachineGroups::VENDING_BIG_BOARD,
            $hand => null, // hand-picked stays hand-picked even though the group would cover it
        ], $this->members($campaign));

        // 2844 updates to 315, 50002 to app 30; 2031 is retired with a live slot on it.
        DB::table('vends')->where('id', $bigOld)->update(['apk_version_code' => 315, 'apk_ver_json' => json_encode(['apkver' => 315, 'deviceType' => 'ZC-328'])]);
        DB::table('vends')->where('id', $oldFreezer)->update(['apk_version_code' => 30]);
        DB::table('vends')->where('id', $bigReady)->update(['is_active' => 0]);
        DB::table('vend_channels')->insert(['vend_id' => $bigReady, 'code' => 11, 'product_id' => 1, 'amount' => 200, 'qty' => 5, 'capacity' => 10, 'is_active' => 1]);
        $slot = HappyHourSlot::create([
            'happy_hour_campaign_id' => $campaign->id, 'vend_id' => $bigReady, 'product_id' => 1, 'slot_date' => '2026-10-14',
            'position' => 0, 'starts_at' => '2026-10-14 08:30:00', 'ends_at' => '2026-10-14 09:30:00',
            'original_price' => 200, 'promo_price' => 140, 'discount_pct' => 30, 'rank' => 1,
        ]);

        app(HappyHourPlanner::class)->run(now());

        $members = $this->members($campaign);
        $this->assertSame(HappyHourMachineGroups::VENDING_BIG_BOARD, $members[$bigOld] ?? 'missing', 'joined on reaching 315');
        $this->assertSame(HappyHourMachineGroups::SMART_FREEZER, $members[$oldFreezer] ?? 'missing');
        $this->assertArrayNotHasKey($bigReady, $members, 'left when deactivated');
        $this->assertSame(HappyHourSlot::STATUS_CANCELLED, $slot->fresh()->status, 'and its live slot ended');
        $this->assertNull($members[$hand]);

        // Unticking the groups keeps only the hand-picked machine.
        $this->actingAs($this->admin())->post("/happy-hour-campaigns/{$campaign->id}", $this->form(['vend_ids' => [$hand]]))
            ->assertSessionHasNoErrors();
        $this->assertSame([$hand => null], $this->members($campaign));
    }

    public function test_the_page_shows_groups_and_keeps_group_members_out_of_the_hand_picker(): void
    {
        $big = $this->vending(2031, 315);
        $admin = $this->admin();
        $this->actingAs($admin)->post('/happy-hour-campaigns', $this->form(['machine_groups' => [HappyHourMachineGroups::VENDING_BIG_BOARD]]))
            ->assertSessionHasNoErrors();

        $this->actingAs($admin)->get('/happy-hour-campaigns')->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('campaigns.0.machine_groups', [HappyHourMachineGroups::VENDING_BIG_BOARD])
                ->where('campaigns.0.vends.0.id', $big)
                ->where('campaigns.0.vends.0.via_group', HappyHourMachineGroups::VENDING_BIG_BOARD)
                ->where('machineGroups.0.name', 'All VM - Big Board'));

        // Saving the form again (no hand-picked machines) leaves the group member in place.
        $campaign = HappyHourCampaign::firstOrFail();
        $this->actingAs($admin)->post("/happy-hour-campaigns/{$campaign->id}", $this->form(['machine_groups' => [HappyHourMachineGroups::VENDING_BIG_BOARD], 'name' => 'Renamed']))
            ->assertSessionHasNoErrors();
        $this->assertSame([$big => HappyHourMachineGroups::VENDING_BIG_BOARD], $this->members($campaign));
    }

    public function test_a_machine_picked_by_name_leaves_the_group_campaign_while_that_campaign_is_in_force(): void
    {
        $picked = $this->vending(2031, 315);
        $other = $this->vending(2003, 315);
        $admin = $this->admin();
        $this->actingAs($admin)->post('/happy-hour-campaigns', $this->form(['name' => 'All VM', 'window_start' => '00:00', 'window_end' => '23:59',
            'machine_groups' => [HappyHourMachineGroups::VENDING_BIG_BOARD]]))->assertSessionHasNoErrors();
        $all = HappyHourCampaign::where('name', 'All VM')->firstOrFail();
        $this->assertSame([$picked, $other], array_keys($this->members($all)));
        DB::table('vend_channels')->insert(['vend_id' => $picked, 'code' => 11, 'product_id' => 1, 'amount' => 200, 'qty' => 5, 'capacity' => 10, 'is_active' => 1]);
        $live = HappyHourSlot::create([
            'happy_hour_campaign_id' => $all->id, 'vend_id' => $picked, 'product_id' => 1, 'slot_date' => '2026-10-14',
            'position' => 0, 'starts_at' => '2026-10-14 08:30:00', 'ends_at' => '2026-10-14 09:30:00',
            'original_price' => 200, 'promo_price' => 140, 'discount_pct' => 30, 'rank' => 1,
        ]);

        // A new entry names 2031 at overlapping hours: no clash — the name wins, for the whole machine.
        $this->actingAs($admin)->post('/happy-hour-campaigns', $this->form(['name' => 'Bench 2031', 'vend_ids' => [$picked], 'discount_pct' => 50]))
            ->assertSessionHasNoErrors();
        $bench = HappyHourCampaign::where('name', 'Bench 2031')->firstOrFail();
        $this->assertSame([$other], array_keys($this->members($all)), 'left the group campaign at once');
        $this->assertSame(HappyHourSlot::STATUS_CANCELLED, $live->fresh()->status, "and the group campaign's live slot there ended");
        $this->assertSame([$picked => null], $this->members($bench));
        $this->actingAs($admin)->get('/happy-hour-campaigns')->assertOk()
            ->assertInertia(fn ($page) => $page->where('campaigns', fn ($campaigns) => collect($campaigns)->firstWhere('name', 'All VM')['overridden']
                === [['code' => '2031', 'by' => 'Bench 2031']]));

        // Two campaigns naming the same machine at overlapping hours have no winner: still refused.
        $this->actingAs($admin)->post('/happy-hour-campaigns', $this->form(['name' => 'Twin pick', 'vend_ids' => [$picked]]))
            ->assertSessionHasErrors('vend_ids');

        // Paused: 2031 goes back to the group campaign.
        $this->actingAs($admin)->post("/happy-hour-campaigns/{$bench->id}", $this->form(['name' => 'Bench 2031', 'vend_ids' => [$picked], 'is_active' => false]))
            ->assertSessionHasNoErrors();
        $this->assertSame([$picked, $other], array_keys($this->members($all)));

        // Switched on again but starting tomorrow: the group keeps it until then, then the minute run hands it over.
        $this->actingAs($admin)->post("/happy-hour-campaigns/{$bench->id}", $this->form(['name' => 'Bench 2031', 'vend_ids' => [$picked], 'starts_on' => '2026-10-15']))
            ->assertSessionHasNoErrors();
        $this->assertSame([$picked, $other], array_keys($this->members($all)), 'not in force yet');
        Carbon::setTestNow('2026-10-15 00:01:00');
        app(HappyHourPlanner::class)->run(now());
        $this->assertSame([$other], array_keys($this->members($all)));

        // The same group on two campaigns at overlapping hours is still refused, even with no members.
        $this->actingAs($admin)->post('/happy-hour-campaigns', $this->form(['name' => 'Twin group',
            'machine_groups' => [HappyHourMachineGroups::VENDING_BIG_BOARD]]))->assertSessionHasErrors('machine_groups');
    }

    public function test_an_operator_restricted_admin_s_group_covers_only_its_own_machines(): void
    {
        $this->vending(2031, 315);
        $theirs = $this->vending(4101, 315, 'ZC-328', self::OTHER);

        $this->actingAs($this->admin(self::OTHER))->post('/happy-hour-campaigns', $this->form(['machine_groups' => [HappyHourMachineGroups::VENDING_BIG_BOARD]]))
            ->assertSessionHasNoErrors();

        $this->assertSame([$theirs => HappyHourMachineGroups::VENDING_BIG_BOARD], $this->members(HappyHourCampaign::withoutGlobalScopes()->firstOrFail()));
    }
}
