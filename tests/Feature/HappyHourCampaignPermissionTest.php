<?php

namespace Tests\Feature;

use Database\Seeders\RolePermissionSyncSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Machine Management → Happy Hour Campaign: superadmin, admin, supervisor and technician (Brian's
 * Roles and Permissions sheet, 2026-10-15) — the same four as the Machine Management rows around it.
 */
class HappyHourCampaignPermissionTest extends TestCase
{
    use RefreshDatabase;

    private const ACTIONS = ['read', 'create', 'update', 'delete'];

    public function test_the_sheet_roles_hold_every_happy_hour_action_and_no_one_else_does(): void
    {
        $this->seed(RolePermissionSyncSeeder::class);

        foreach (['superadmin', 'admin', 'supervisor', 'technician'] as $role) {
            $held = Role::findByName($role, 'web')->permissions->pluck('name')->all();
            foreach (self::ACTIONS as $action) {
                $this->assertContains("{$action} happy-hour-campaigns", $held, "{$role} lacks {$action}");
            }
        }

        $others = Role::query()->whereNotIn('name', ['superadmin', 'admin', 'supervisor', 'technician'])->get();
        foreach ($others as $role) {
            foreach (self::ACTIONS as $action) {
                $this->assertNotContains("{$action} happy-hour-campaigns", $role->permissions->pluck('name')->all(), "{$role->name} should not have {$action}");
            }
        }
    }
}
