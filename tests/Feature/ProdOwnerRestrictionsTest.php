<?php

namespace Tests\Feature;

use App\Models\Operator;
use App\Models\User;
use Database\Seeders\RolePermissionSyncSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * What prod_owner (an outside product owner) is kept off, Brian 2026-09-13:
 *
 *   - Dashboard > Performance. It reads vend_records, which has no product
 *     dimension, so it shows whole-machine revenue. Performance (Lite) only.
 *   - The Sales Transactions "Show All Filters" toggle.
 *   - The Operator and "Is Available?" filters on Warehouse Qty.
 */
class ProdOwnerRestrictionsTest extends TestCase
{
    use RefreshDatabase;

    private function permissionNames(string $role): array
    {
        return Role::findByName($role, 'web')->permissions->pluck('name')->all();
    }

    public function test_prod_owner_holds_lite_but_not_full_performance(): void
    {
        $this->seed(RolePermissionSyncSeeder::class);

        $perms = $this->permissionNames('prod_owner');

        $this->assertContains('read dashboard-performance-lite', $perms);
        $this->assertNotContains('read dashboard-performance', $perms);
        $this->assertNotContains('export dashboard-performance', $perms);
    }

    public function test_prod_owner_is_refused_the_full_performance_route(): void
    {
        $this->seed(RolePermissionSyncSeeder::class);

        $operator = Operator::firstOrCreate(['code' => 'OP1'], ['name' => 'Test Operator']);
        $user = User::factory()->create(['operator_id' => $operator->id]);
        $user->assignRole('prod_owner');

        $this->actingAs($user)->get('/dashboard/performance')->assertForbidden();
    }

    public function test_prod_owner_is_off_the_extra_filters(): void
    {
        $this->seed(RolePermissionSyncSeeder::class);

        $perms = $this->permissionNames('prod_owner');

        // Still on both pages ...
        $this->assertContains('read transactions', $perms);
        $this->assertContains('read product-availability', $perms);

        // ... without the extra filters.
        $this->assertNotContains('read transactions-all-filters', $perms);
        $this->assertNotContains('read product-availability-filters', $perms);
    }

    /**
     * Each new permission is "everyone who reaches the page, minus prod_owner" -
     * a role missing here would silently lose a filter it has today.
     */
    public function test_every_other_role_on_those_pages_keeps_its_filters(): void
    {
        $this->seed(RolePermissionSyncSeeder::class);

        $pairs = [
            'read transactions' => 'read transactions-all-filters',
            'read product-availability' => 'read product-availability-filters',
            'read dashboard-performance-lite' => 'read dashboard-performance',
        ];

        foreach (Role::where('guard_name', 'web')->where('name', '!=', 'prod_owner')->get() as $role) {
            foreach ($pairs as $page => $extra) {
                if ($role->hasPermissionTo($page)) {
                    $this->assertTrue($role->hasPermissionTo($extra), "{$role->name} lost {$extra}");
                }
            }
        }
    }
}
