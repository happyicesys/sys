<?php

namespace Tests\Feature\Auth;

use App\Http\Middleware\Authenticate;
use App\Models\Scopes\OperatorVendFilterScope;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Deactivate must throw a user out at once, not only refuse their next login
 * (Brian, 2026-11-16: a deactivated user still logged in kept working).
 */
class DeactivatedUserLoggedOutTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware(['web', 'auth'])->get('/__test/deactivated-probe', fn () => 'inside');
    }

    public function test_active_user_keeps_working(): void
    {
        $user = User::factory()->create(['is_active' => true]);

        $this->actingAs($user)->get('/__test/deactivated-probe')
            ->assertOk()
            ->assertSee('inside');

        $this->assertAuthenticatedAs($user);
    }

    public function test_logged_in_user_is_thrown_out_once_deactivated(): void
    {
        $user = User::factory()->create(['is_active' => true]);

        $this->actingAs($user)->get('/__test/deactivated-probe')->assertOk();

        $user->forceFill(['is_active' => false])->save();

        $this->get('/__test/deactivated-probe')
            ->assertRedirect(route('login'))
            ->assertSessionHas('status', 'Your account is inactive.');

        $this->assertGuest();
    }

    public function test_inertia_request_from_deactivated_user_is_sent_to_login(): void
    {
        $user = User::factory()->create(['is_active' => false]);

        $this->actingAs($user)
            ->get('/__test/deactivated-probe', ['X-Inertia' => 'true', 'X-Requested-With' => 'XMLHttpRequest'])
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }

    public function test_json_request_from_deactivated_user_gets_401(): void
    {
        $user = User::factory()->create(['is_active' => false]);

        $this->actingAs($user)->getJson('/__test/deactivated-probe')->assertUnauthorized();

        $this->assertGuest();
    }

    public function test_middleware_alias_is_ours(): void
    {
        $this->assertSame(Authenticate::class, app('router')->getMiddleware()['auth']);
    }

    public function test_deactivate_rotates_remember_token_and_revokes_api_tokens(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        // Pinned id: the "sees everything" exemption is keyed on operator id 1.
        DB::table('operators')->insert([
            'id' => OperatorVendFilterScope::UNRESTRICTED_OPERATOR_ID,
            'code' => 'HIPL', 'name' => 'HIPL', 'is_active' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        Role::findOrCreate('superadmin', 'web')->givePermissionTo(Permission::findOrCreate('read users', 'web'));

        $admin = User::factory()->create(['operator_id' => OperatorVendFilterScope::UNRESTRICTED_OPERATOR_ID]);
        $admin->assignRole('superadmin');

        $target = User::factory()->create([
            'operator_id' => OperatorVendFilterScope::UNRESTRICTED_OPERATOR_ID,
            'is_active' => true,
            'remember_token' => 'old-remember-token',
        ]);

        $tokenId = str_repeat('a', 80);
        DB::table('oauth_access_tokens')->insert([
            'id' => $tokenId,
            'user_id' => $target->id,
            'client_id' => 1,
            'name' => 'test',
            'scopes' => '[]',
            'revoked' => false,
            'created_at' => now(),
            'updated_at' => now(),
            'expires_at' => now()->addYear(),
        ]);

        $this->actingAs($admin)->post("/users/{$target->id}/toggle-activate-deactivate")->assertRedirect('/users');

        $target->refresh();
        $this->assertFalse((bool) $target->is_active);
        $this->assertNotSame('old-remember-token', $target->remember_token);
        $this->assertTrue((bool) DB::table('oauth_access_tokens')->where('id', $tokenId)->value('revoked'));

        // Activating again does not touch tokens.
        $this->actingAs($admin)->post("/users/{$target->id}/toggle-activate-deactivate")->assertRedirect('/users');
        $this->assertTrue((bool) $target->fresh()->is_active);
    }

    private function seedHiplAndRoles(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        DB::table('operators')->insert([
            'id' => OperatorVendFilterScope::UNRESTRICTED_OPERATOR_ID,
            'code' => 'HIPL', 'name' => 'HIPL', 'is_active' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $readUsers = Permission::findOrCreate('read users', 'web');
        foreach (['superadmin', 'admin'] as $name) {
            Role::findOrCreate($name, 'web')->givePermissionTo($readUsers);
        }
    }

    private function hiplUser(string $role): User
    {
        $user = User::factory()->create([
            'operator_id' => OperatorVendFilterScope::UNRESTRICTED_OPERATOR_ID,
            'is_active' => true,
        ]);
        $user->assignRole($role);

        return $user;
    }

    public function test_nobody_else_can_deactivate_a_superadmin(): void
    {
        $this->seedHiplAndRoles();
        $boss = $this->hiplUser('superadmin');

        foreach ([$this->hiplUser('admin'), $this->hiplUser('superadmin')] as $other) {
            $this->actingAs($other)->post("/users/{$boss->id}/toggle-activate-deactivate")->assertForbidden();
            $this->assertTrue((bool) $boss->fresh()->is_active);
        }
    }

    public function test_nobody_else_can_delete_or_demote_a_superadmin(): void
    {
        $this->seedHiplAndRoles();
        $boss = $this->hiplUser('superadmin');
        $admin = $this->hiplUser('admin');

        $this->actingAs($admin)->delete("/users/{$boss->id}")->assertForbidden();
        $this->assertDatabaseHas('users', ['id' => $boss->id]);

        $this->actingAs($admin)->post("/users/{$boss->id}/update", [
            'name' => $boss->name,
            'email' => $boss->email,
            'role_id' => Role::findByName('admin', 'web')->id,
        ])->assertForbidden();
        $this->assertTrue($boss->fresh()->hasRole('superadmin'));
    }

    /** Audit 2026-11-22: an admin must not take a superadmin's account over by resetting its sign-in. */
    public function test_an_admin_cannot_change_a_superadmins_password_email_or_username(): void
    {
        $this->seedHiplAndRoles();
        $boss = $this->hiplUser('superadmin');
        $admin = $this->hiplUser('admin');
        $before = $boss->fresh()->password;

        foreach ([['password' => 'taken-over'], ['email' => 'other@example.com'], ['username' => 'someone-else']] as $change) {
            $this->actingAs($admin)->post("/users/{$boss->id}/update", array_merge([
                'name' => $boss->name,
                'email' => $boss->email,
                'username' => $boss->username,
            ], $change))->assertForbidden();
        }
        $after = $boss->fresh();
        $this->assertSame($before, $after->password);
        $this->assertSame($boss->email, $after->email);
        $this->assertSame($boss->username, $after->username);

        // Saving the same values back (the form posts the whole user) is not a sign-in change.
        $this->actingAs($admin)->post("/users/{$boss->id}/update", [
            'name' => 'Renamed', 'email' => $boss->email, 'username' => $boss->username,
        ])->assertRedirect();
        $this->assertSame('Renamed', $boss->fresh()->name);

        // Another superadmin may still reset it.
        $this->actingAs($this->hiplUser('superadmin'))->post("/users/{$boss->id}/update", [
            'name' => 'Renamed', 'email' => $boss->email, 'username' => $boss->username, 'password' => 'new-secret',
        ])->assertRedirect();
        $this->assertNotSame($before, $boss->fresh()->password);
    }

    public function test_a_superadmin_may_still_deactivate_themselves_and_admins_still_work(): void
    {
        $this->seedHiplAndRoles();
        $boss = $this->hiplUser('superadmin');
        $admin = $this->hiplUser('admin');

        // Admins and other non-superadmins are unaffected.
        $this->actingAs($boss)->post("/users/{$admin->id}/toggle-activate-deactivate")->assertRedirect('/users');
        $this->assertFalse((bool) $admin->fresh()->is_active);

        // Activating a deactivated superadmin is never blocked; only deactivating is.
        $other = $this->hiplUser('superadmin');
        $other->forceFill(['is_active' => false])->save();
        $this->actingAs($boss)->post("/users/{$other->id}/toggle-activate-deactivate")->assertRedirect('/users');
        $this->assertTrue((bool) $other->fresh()->is_active);

        $this->actingAs($boss)->post("/users/{$boss->id}/toggle-activate-deactivate")->assertRedirect('/users');
        $this->assertFalse((bool) $boss->fresh()->is_active);
    }
}
