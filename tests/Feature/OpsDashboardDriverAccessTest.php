<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolePermissionSyncSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * The driver role reaches the Operation Dashboard (Full Filter) and Machine Settings (Brian,
 * 2026-10-26 sheet: "upskill driver to solve basic technical issues").
 *
 * History: driver lost the Dashboard in two passes (`read vends` 2026-07-23, `read vend-customers` +
 * `admin-access vend-customers` 2026-08-09) and held Dashboard Lite in its place from 2026-08-18. The
 * 2026-10-26 sheet gives the full Dashboard back and strikes Lite. Machine Settings: read + update
 * (Setting/Edit and its machine actions) + admin-access (the list's filters), never `create` — making
 * a machine is not a driver's job. Drivers still land on /ops-jobs — see Auth\RedirectRouteTest.
 */
class OpsDashboardDriverAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_driver_reaches_the_operation_dashboard_with_the_full_filter(): void
    {
        $this->seed(RolePermissionSyncSeeder::class);

        $driver = Role::findByName('driver', 'web');

        // Nav link (Authenticated.vue), the route gate, and the full filter set in CustomerIndex.vue.
        foreach (['read vends', 'read vend-customers', 'admin-access vend-customers'] as $permission) {
            $this->assertTrue($driver->hasPermissionTo($permission), $permission);
        }
        // Lite was the stand-in for the full Dashboard; struck on the same sheet.
        $this->assertFalse($driver->hasPermissionTo('read vend-customers-lite'), 'read vend-customers-lite');
    }

    public function test_driver_can_open_and_save_machine_settings_but_not_create_a_machine(): void
    {
        $this->seed(RolePermissionSyncSeeder::class);

        $driver = Role::findByName('driver', 'web');

        foreach (['read machine-settings', 'update machine-settings', 'admin-access machine-settings'] as $permission) {
            $this->assertTrue($driver->hasPermissionTo($permission), $permission);
        }
        $this->assertFalse($driver->hasPermissionTo('create machine-settings'), 'create machine-settings');

        $user = User::factory()->create();
        $user->assignRole('driver');
        $this->actingAs($user)->get('/settings/vend/create')->assertForbidden();
    }

    /** Drivers still work out of ops jobs and hold the permissions that render that page's nav. */
    public function test_driver_still_holds_its_ops_jobs_permissions(): void
    {
        $this->seed(RolePermissionSyncSeeder::class);

        $driver = Role::findByName('driver', 'web');

        $this->assertTrue($driver->hasPermissionTo('read operations'), 'read operations');
        $this->assertTrue($driver->hasPermissionTo('read operation-jobs'), 'read operation-jobs');
    }
}
