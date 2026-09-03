<?php

namespace Tests\Feature;

use App\Models\Operator;
use App\Models\Scopes\OperatorActiveScope;
use App\Models\Scopes\OperatorVendFilterScope;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Operators are never deleted (2026-09-03): the Delete button on
 * /operators became Deactivate / Activate. Deactivation must be reversible,
 * and the old DELETE route must be gone.
 */
class OperatorToggleActiveTest extends TestCase
{
    use RefreshDatabase;

    /**
     * OperatorFilterScope pins every non-HappyIce user to their own operator
     * row, and HappyIce is keyed on id 1. RefreshDatabase does not reset
     * AUTO_INCREMENT, so insert the id explicitly rather than relying on
     * creation order.
     */
    private function hipl(): Operator
    {
        $id = OperatorVendFilterScope::UNRESTRICTED_OPERATOR_ID;

        DB::table('operators')->insert([
            'id' => $id,
            'code' => 'HIPL',
            'name' => 'HIPL',
            'is_active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return Operator::withoutGlobalScopes()->findOrFail($id);
    }

    private function admin(): User
    {
        $user = User::factory()->create(['operator_id' => $this->hipl()->id]);
        $user->givePermissionTo(Permission::findOrCreate('read operators', 'web'));
        $user->givePermissionTo(Permission::findOrCreate('admin-access operators', 'web'));

        return $user;
    }

    public function test_deactivate_flips_is_active_and_stamps_deactivated_at(): void
    {
        $admin = $this->admin();
        $target = Operator::withoutGlobalScopes()->create(['code' => 'TGT', 'name' => 'Target', 'is_active' => true]);

        $this->actingAs($admin)
            ->from('/operators?status=active')
            ->post("/operators/{$target->id}/toggle-activate-deactivate")
            ->assertRedirect('/operators?status=active');

        $fresh = Operator::withoutGlobalScope(OperatorActiveScope::class)->find($target->id);
        $this->assertFalse((bool) $fresh->is_active);
        $this->assertNotNull($fresh->deactivated_at);
        // Still on disk - never deleted.
        $this->assertDatabaseHas('operators', ['id' => $target->id]);
    }

    public function test_reactivate_finds_the_row_behind_the_active_scope(): void
    {
        $admin = $this->admin();
        $target = Operator::withoutGlobalScopes()->create([
            'code' => 'TGT', 'name' => 'Target', 'is_active' => false, 'deactivated_at' => now()->subDay(),
        ]);

        $this->actingAs($admin)
            ->post("/operators/{$target->id}/toggle-activate-deactivate")
            ->assertRedirect();

        $fresh = Operator::withoutGlobalScope(OperatorActiveScope::class)->find($target->id);
        $this->assertTrue((bool) $fresh->is_active);
        $this->assertNull($fresh->deactivated_at);
    }

    public function test_toggle_requires_admin_access_operators(): void
    {
        $user = User::factory()->create(['operator_id' => $this->hipl()->id]);
        $user->givePermissionTo(Permission::findOrCreate('read operators', 'web'));
        $target = Operator::withoutGlobalScopes()->create(['code' => 'TGT', 'name' => 'Target', 'is_active' => true]);

        $this->actingAs($user)
            ->post("/operators/{$target->id}/toggle-activate-deactivate")
            ->assertForbidden();

        $this->assertTrue((bool) Operator::withoutGlobalScopes()->find($target->id)->is_active);
    }

    public function test_delete_route_is_gone(): void
    {
        $admin = $this->admin();
        $target = Operator::withoutGlobalScopes()->create(['code' => 'TGT', 'name' => 'Target', 'is_active' => true]);

        $this->actingAs($admin)
            ->delete("/operators/{$target->id}")
            ->assertNotFound();

        $this->assertDatabaseHas('operators', ['id' => $target->id]);
    }
}
