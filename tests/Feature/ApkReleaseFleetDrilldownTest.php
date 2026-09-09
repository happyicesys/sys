<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\User;
use App\Models\Vend;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * APK OTA Updates → "Fleet version spread": clicking a bar lists the machines
 * behind it.
 *
 * The contract that matters is that the list and the count above it are drawn
 * from the SAME scope. If they ever diverge, the popup becomes a quiet lie about
 * which machines a publish is going to reach.
 */
class ApkReleaseFleetDrilldownTest extends TestCase
{
    use RefreshDatabase;

    private function staff(array $permissions): User
    {
        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission, 'web');
        }
        $user = User::factory()->create();
        $user->givePermissionTo($permissions);

        return $user;
    }

    /**
     * `apk_version_code` / `apk_checked_in_at` are guarded — production only ever
     * writes them through OtaController::recordCheckIn's forceFill — so the test
     * sets them the same way rather than through mass assignment.
     */
    private function vend(array $attrs = []): Vend
    {
        $guarded = array_intersect_key($attrs, array_flip(['apk_version_code', 'apk_checked_in_at', 'is_disposed']));
        $fillable = array_diff_key($attrs, $guarded);

        $vend = Vend::create(array_merge([
            'machine_type' => Vend::MACHINE_TYPE_VENDING_MACHINE,
            'is_active' => 1,
            'operator_id' => 1,
        ], $fillable));

        if ($guarded !== []) {
            $vend->forceFill($guarded)->save();
        }

        return $vend->refresh();
    }

    public function test_it_lists_the_machines_on_one_version_code(): void
    {
        $site = Customer::create([
            'name' => 'Carissa Park Condo', 'code' => '15653 (BEC2)',
            'operator_id' => 1, 'status_id' => Customer::STATUS_ACTIVE,
        ]);
        $on305 = $this->vend(['code' => 2046, 'apk_version_code' => 305, 'customer_id' => $site->id]);
        $this->vend(['code' => 2117, 'apk_version_code' => 303]);

        $this->actingAs($this->staff(['read apk-releases']));

        $res = $this->getJson('/apk-releases/fleet-machines?channel=vending&version_code=305')
            ->assertOk()
            ->assertJsonPath('version_code', 305)
            ->assertJsonPath('total', 1)
            ->assertJsonPath('truncated', false)
            ->assertJsonCount(1, 'machines');

        $res->assertJsonPath('machines.0.id', $on305->id)
            ->assertJsonPath('machines.0.code', 2046)
            ->assertJsonPath('machines.0.site_ref', '15653 (BEC2)')
            ->assertJsonPath('machines.0.site_name', 'Carissa Park Condo')
            ->assertJsonPath('machines.0.is_active', true);
    }

    public function test_unknown_bucket_returns_machines_that_never_checked_in(): void
    {
        $never = $this->vend(['code' => 3001, 'apk_version_code' => null]);
        $this->vend(['code' => 3002, 'apk_version_code' => 305]);

        $this->actingAs($this->staff(['read apk-releases']));

        $this->getJson('/apk-releases/fleet-machines?channel=vending&version_code=unknown')
            ->assertOk()
            ->assertJsonPath('version_code', null)
            ->assertJsonPath('total', 1)
            ->assertJsonPath('machines.0.id', $never->id);

        // Omitting version_code entirely means the same bucket, since that is how
        // the panel labels a machine with no reported version.
        $this->getJson('/apk-releases/fleet-machines?channel=vending')
            ->assertOk()
            ->assertJsonPath('total', 1);
    }

    public function test_a_machine_with_no_site_is_listed_with_null_site_fields(): void
    {
        $this->vend(['code' => 2031, 'apk_version_code' => 305, 'customer_id' => null]);

        $this->actingAs($this->staff(['read apk-releases']));

        $this->getJson('/apk-releases/fleet-machines?channel=vending&version_code=305')
            ->assertOk()
            ->assertJsonPath('machines.0.code', 2031)
            ->assertJsonPath('machines.0.site_ref', null)
            ->assertJsonPath('machines.0.site_name', null);
    }

    public function test_the_drilldown_total_matches_the_panel_count_for_the_same_bucket(): void
    {
        // Inactive and disposed machines are inside the spread panel's scope, so
        // they must be inside the drill-down's too — that equality is the point.
        $this->vend(['code' => 4001, 'apk_version_code' => null, 'is_active' => 1]);
        $this->vend(['code' => 4002, 'apk_version_code' => null, 'is_active' => 0]);
        $this->vend(['code' => 4003, 'apk_version_code' => null, 'is_active' => 1, 'is_disposed' => 1]);

        $this->actingAs($this->staff(['read apk-releases']));

        $page = $this->get('/apk-releases?channel=vending')->assertOk();
        $spread = collect($page->viewData('page')['props']['fleetVersions'] ?? []);
        $unknownBucket = $spread->firstWhere('version_code', null);

        $this->getJson('/apk-releases/fleet-machines?channel=vending&version_code=unknown')
            ->assertOk()
            ->assertJsonPath('total', $unknownBucket['total']);
    }

    public function test_a_non_integer_version_code_is_rejected(): void
    {
        $this->actingAs($this->staff(['read apk-releases']));

        $this->getJson('/apk-releases/fleet-machines?channel=vending&version_code=305;DROP')
            ->assertStatus(422);
    }

    public function test_it_requires_the_apk_releases_read_permission(): void
    {
        $this->actingAs($this->staff(['read machine-settings']));

        $this->getJson('/apk-releases/fleet-machines?channel=vending&version_code=305')
            ->assertForbidden();
    }
}
