<?php

namespace Tests\Feature;

use Database\Seeders\RolePermissionSyncSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * The driver role must NOT reach the Operation Dashboard (/vends/customers).
 *
 * Struck from the sheet in two passes - `read vends` on 2026-07-23, then
 * `read vend-customers` and `admin-access vend-customers` on 2026-08-09. A
 * temporary re-grant was applied on 2026-08-13 and reverted the same day; this
 * test exists so the next re-grant has to be deliberate rather than accidental.
 *
 * Drivers land on /ops-jobs instead - see Auth\RedirectRouteTest.
 */
class OpsDashboardDriverAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_driver_cannot_reach_the_operation_dashboard(): void
    {
        $this->seed(RolePermissionSyncSeeder::class);

        $driver = Role::findByName('driver', 'web');

        // Nav link (Authenticated.vue) + Operations section gate.
        $this->assertFalse($driver->hasPermissionTo('read vends'), 'read vends');

        // Route gate - holding this is what made /vends/customers reachable.
        $this->assertFalse($driver->hasPermissionTo('read vend-customers'), 'read vend-customers');

        // Full filter set in CustomerIndex.vue.
        $this->assertFalse($driver->hasPermissionTo('admin-access vend-customers'), 'admin-access vend-customers');
    }

    /**
     * The driver role is not stranded by the above: /ops-jobs is where it
     * belongs, and it holds the permissions that render that page's nav.
     */
    public function test_driver_still_holds_its_ops_jobs_permissions(): void
    {
        $this->seed(RolePermissionSyncSeeder::class);

        $driver = Role::findByName('driver', 'web');

        $this->assertTrue($driver->hasPermissionTo('read operations'), 'read operations');
        $this->assertTrue($driver->hasPermissionTo('read operation-jobs'), 'read operation-jobs');
    }
}
