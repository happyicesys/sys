<?php

namespace Tests\Feature;

use App\Http\Controllers\DashboardController;
use App\Models\User;
use Database\Seeders\RolePermissionSyncSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * sup_driver ("Sup Driver", added to the sheet 2026-08-13) was driver plus the Operation Dashboard.
 * Since the 2026-10-26 sheet driver holds that Dashboard too, plus Machine Settings, which the sheet
 * does NOT give sup_driver. Asserted as SET EQUALITY so a later change to either role's grants fails
 * here unless it is deliberate.
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

    /** What driver holds that sup_driver does not (2026-10-26 sheet: Machine Settings for Driver only). */
    private const DRIVER_ONLY = [
        'read machine-settings',
        'update machine-settings',
        'admin-access machine-settings',
    ];

    public function test_sup_driver_is_driver_without_machine_settings(): void
    {
        $this->seed(RolePermissionSyncSeeder::class);

        $expected = array_values(array_diff($this->permissionNames('driver'), self::DRIVER_ONLY));
        sort($expected);

        $this->assertSame($expected, $this->permissionNames('sup_driver'));
        foreach (self::DASHBOARD_ONLY as $permission) {
            $this->assertContains($permission, $this->permissionNames('driver'));
        }
    }

    public function test_driver_keeps_its_ops_jobs_grants(): void
    {
        $this->seed(RolePermissionSyncSeeder::class);

        $driver = $this->permissionNames('driver');

        $this->assertContains('read operations', $driver);
        $this->assertContains('read operation-jobs', $driver);
        $this->assertContains('read operation-job-summaries', $driver);
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
