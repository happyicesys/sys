<?php

namespace Tests\Feature;

use Database\Seeders\RolePermissionSyncSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * AI Recognition's money actions (Brian, 2026-11-07: LiangBao, a supervisor, manages Adjust Qty and Refund
 * like Admin). Adjust Qty + Key in charge = 'charge ai-recognition'; the T05 Refund = 'verify refunds'.
 */
class AiRecognitionSupervisorPermissionTest extends TestCase
{
    use RefreshDatabase;

    private const MONEY = ['read ai-recognition', 'charge ai-recognition', 'verify refunds'];

    public function test_supervisor_holds_every_ai_recognition_money_action_like_admin(): void
    {
        $this->seed(RolePermissionSyncSeeder::class);

        foreach (['superadmin', 'admin', 'supervisor'] as $role) {
            $held = Role::findByName($role, 'web')->permissions->pluck('name')->all();
            foreach (self::MONEY as $permission) {
                $this->assertContains($permission, $held, "{$role} lacks {$permission}");
            }
        }
    }

    public function test_no_other_role_can_charge_or_adjust(): void
    {
        $this->seed(RolePermissionSyncSeeder::class);

        $others = Role::query()->whereNotIn('name', ['superadmin', 'admin', 'supervisor'])->get();
        $this->assertNotEmpty($others);
        foreach ($others as $role) {
            $this->assertNotContains('charge ai-recognition', $role->permissions->pluck('name')->all(), "{$role->name} should not charge");
        }
    }
}
