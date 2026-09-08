<?php

namespace Tests\Feature;

use App\Models\Operator;
use App\Models\User;
use Database\Seeders\RolePermissionSyncSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * picker (added 2026-09-08, Brian): a role whose whole job is Daily Jobs > Jobs.
 * It holds the section gate (`read operations`) plus every operation-jobs
 * action, and NOTHING else — asserted as set equality so any later widening
 * has to be deliberate. It lands on /ops-jobs after login and is not swept
 * into the driver-only behaviour (own-jobs filter, self-only assignee).
 */
class PickerRoleTest extends TestCase
{
    use RefreshDatabase;

    private const EXPECTED = [
        'admin-access operation-jobs',
        'create operation-jobs',
        'delete operation-jobs',
        'export operation-jobs',
        'read operation-jobs',
        'read operations',
        'update operation-jobs',
    ];

    private function permissionNames(string $roleName): array
    {
        $names = Role::findByName($roleName, 'web')
            ->permissions
            ->pluck('name')
            ->all();

        sort($names);

        return $names;
    }

    public function test_picker_holds_exactly_the_ops_jobs_grants(): void
    {
        $this->seed(RolePermissionSyncSeeder::class);

        $this->assertSame(self::EXPECTED, $this->permissionNames('picker'));
    }

    public function test_picker_cannot_see_the_summary_page_or_renumber(): void
    {
        $this->seed(RolePermissionSyncSeeder::class);

        $picker = $this->permissionNames('picker');

        // Daily Jobs > Summary is gated on this in Authenticated.vue.
        $this->assertNotContains('read operation-job-summaries', $picker);
        // OpsJob > Edit "Renumber" button.
        $this->assertNotContains('admin-access operations', $picker);
    }

    public function test_splitting_the_operations_tuple_did_not_change_driver(): void
    {
        $this->seed(RolePermissionSyncSeeder::class);

        $driver = $this->permissionNames('driver');

        foreach (['read', 'export', 'create', 'update', 'delete', 'admin-access'] as $action) {
            $this->assertContains($action.' operations', $driver, $action);
        }
    }

    public function test_picker_lands_on_ops_jobs_after_login(): void
    {
        $this->seed(RolePermissionSyncSeeder::class);

        $user = User::factory()->create();
        $user->assignRole('picker');

        $this->assertSame('/ops-jobs', $user->getRedirectRoute());
    }

    public function test_picker_can_open_ops_jobs_and_is_bounced_from_the_dashboard(): void
    {
        $this->seed(RolePermissionSyncSeeder::class);

        // OpsJobController::index reads auth()->user()->operator->code.
        $operator = Operator::create(['code' => 'OP1', 'name' => 'Test Operator']);
        $user = User::factory()->create(['operator_id' => $operator->id]);
        $user->assignRole('picker');

        $this->actingAs($user)->get('/ops-jobs')->assertOk();
        $this->actingAs($user)->get('/vends/customers')->assertForbidden();
    }

    public function test_picker_is_not_a_driver(): void
    {
        $this->seed(RolePermissionSyncSeeder::class);

        $user = User::factory()->create();
        $user->assignRole('picker');

        $this->assertFalse($user->isDriver());
    }
}
