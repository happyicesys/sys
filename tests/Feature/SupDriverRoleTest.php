<?php

namespace Tests\Feature;

use App\Http\Controllers\DashboardController;
use App\Models\User;
use Database\Seeders\RolePermissionSyncSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * sup_driver ("Sup Driver", added to the sheet 2026-08-13) is driver plus the
 * Operation Dashboard, and nothing else. Asserted as SET EQUALITY so that a
 * later change to driver's grants fails here unless sup_driver moves with it.
 */
class SupDriverRoleTest extends TestCase
{
    use RefreshDatabase;

    /** The Ops Dashboard (Full Filter) grants, and the only intended difference. */
    private const DASHBOARD_ONLY = [
        'read vends',
        'export vends',
        'read vend-customers',
        'export vend-customers',
        'admin-access vend-customers',
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

    public function test_sup_driver_is_driver_plus_the_operation_dashboard(): void
    {
        $this->seed(RolePermissionSyncSeeder::class);

        // driver holds vend-customers-lite as a REPLACEMENT for the full
        // Dashboard it lost in the 2026-08-09 sync (seeder note, 2026-08-18).
        // sup_driver kept the full Dashboard (that is its whole point), so it
        // deliberately does not carry the Lite fallback.
        $expected = array_merge(
            array_values(array_filter(
                $this->permissionNames('driver'),
                fn ($p) => ! str_ends_with($p, 'vend-customers-lite')
            )),
            self::DASHBOARD_ONLY
        );
        sort($expected);

        $this->assertSame($expected, $this->permissionNames('sup_driver'));
    }

    public function test_the_new_role_did_not_change_driver(): void
    {
        $this->seed(RolePermissionSyncSeeder::class);

        $driver = $this->permissionNames('driver');

        // driver keeps its ops-jobs grants...
        $this->assertContains('read operations', $driver);
        $this->assertContains('read operation-jobs', $driver);
        $this->assertContains('read operation-job-summaries', $driver);

        // ...and still cannot reach the dashboard. See OpsDashboardDriverAccessTest.
        foreach (self::DASHBOARD_ONLY as $permission) {
            $this->assertNotContains($permission, $driver);
        }
    }

    public function test_sup_driver_lands_on_ops_jobs_after_login(): void
    {
        $this->seed(RolePermissionSyncSeeder::class);

        $user = User::factory()->create();
        $user->assignRole('sup_driver');

        $this->assertSame('/ops-jobs', $user->getRedirectRoute());
    }

    /**
     * The role-NAME-keyed behaviour (own-jobs-only Summary, self-only assignee
     * dropdown, adminish default filters, monthly sales popup) all routes
     * through isDriver(), so sup_driver must answer it exactly like driver.
     */
    public function test_sup_driver_counts_as_a_driver_for_role_keyed_behaviour(): void
    {
        $this->seed(RolePermissionSyncSeeder::class);

        foreach (['driver', 'sup_driver'] as $roleName) {
            $user = User::factory()->create();
            $user->assignRole($roleName);

            $this->assertTrue($user->isDriver(), $roleName);
            $this->assertContains($roleName, DashboardController::MONTHLY_SALES_POPUP_ROLES, $roleName);
        }
    }

    public function test_non_driver_roles_are_not_swept_into_isdriver(): void
    {
        $this->seed(RolePermissionSyncSeeder::class);

        // operator_driver is an operator-side role and has never been in these
        // checks, despite the name.
        foreach (['supervisor', 'technician', 'operator_driver'] as $roleName) {
            $user = User::factory()->create();
            $user->assignRole($roleName);

            $this->assertFalse($user->isDriver(), $roleName);
        }
    }
}
