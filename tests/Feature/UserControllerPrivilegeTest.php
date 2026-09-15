<?php

namespace Tests\Feature;

use App\Models\Operator;
use App\Models\Scopes\OperatorVendFilterScope;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * AUDIT_2026-09-15 M2-01 / M2-12.
 *
 * `users` has no global scope, and UserController was gated on nothing but
 * `permission:read users` - which operator_admin holds. So an operator_admin
 * could create an operator_id=1 superadmin, re-parent users into HappyIce,
 * edit / delete any operator's users, and POST /self/<superadmin>/update
 * with a new password. Each test below fails on the pre-fix controller.
 *
 * Rules asserted:
 *  - operator ceiling = OperatorVendFilterScope::viewerOperatorId(): a
 *    non-HappyIce viewer may only touch users of their OWN operator, and may
 *    only create into it;
 *  - role allow-list = User::assignableRoleNames(): anyone who is not
 *    superadmin/admin may only hand out User::OPERATOR_ASSIGNABLE_ROLES;
 *  - /self ignores the route id and edits the caller only, and needs no
 *    `read users` (M2-12).
 */
class UserControllerPrivilegeTest extends TestCase
{
    use RefreshDatabase;

    private Operator $hipl;

    private Operator $opA;

    private Operator $opB;

    private User $superadmin;

    private User $opAdminA;

    private User $staffA;

    private User $staffB;

    private User $driver;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        // Pinned id: the "sees everything" exemption is keyed on operator id 1
        // and RefreshDatabase does not reset AUTO_INCREMENT.
        DB::table('operators')->insert([
            'id' => OperatorVendFilterScope::UNRESTRICTED_OPERATOR_ID,
            'code' => 'HIPL', 'name' => 'HIPL', 'is_active' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->hipl = Operator::withoutGlobalScopes()->findOrFail(OperatorVendFilterScope::UNRESTRICTED_OPERATOR_ID);
        $this->opA = Operator::withoutGlobalScopes()->create(['code' => 'OPA', 'name' => 'Operator A', 'is_active' => 1]);
        $this->opB = Operator::withoutGlobalScopes()->create(['code' => 'OPB', 'name' => 'Operator B', 'is_active' => 1]);

        $readUsers = Permission::findOrCreate('read users', 'web');
        foreach (['superadmin', 'admin', 'operator_admin'] as $name) {
            Role::findOrCreate($name, 'web')->givePermissionTo($readUsers);
        }
        foreach (['operator_supervisor', 'operator_driver', 'operator_3pl', 'driver', 'supervisor'] as $name) {
            Role::findOrCreate($name, 'web');
        }

        $this->superadmin = $this->user('Super', $this->hipl, 'superadmin');
        $this->opAdminA = $this->user('Op Admin A', $this->opA, 'operator_admin');
        $this->staffA = $this->user('Staff A', $this->opA, 'operator_driver');
        $this->staffB = $this->user('Staff B', $this->opB, 'operator_driver');
        $this->driver = $this->user('Driver', $this->hipl, 'driver');
    }

    private function user(string $name, Operator $operator, string $role): User
    {
        $user = User::factory()->create(['name' => $name, 'operator_id' => $operator->id, 'password' => 'original']);
        $user->assignRole($role);

        return $user;
    }

    private function createPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'New User',
            'email' => 'new.user@example.test',
            'password' => 'secret123',
            'operator_id' => $this->opA->id,
            'role_id' => Role::findByName('operator_driver', 'web')->id,
        ], $overrides);
    }

    private function updatePayload(User $user, array $overrides = []): array
    {
        return array_merge([
            'name' => $user->name.' (edited)',
            'email' => $user->email,
            'operator_id' => $user->operator_id,
        ], $overrides);
    }

    // ------------------------------------------------------------ create()

    public function test_operator_admin_cannot_create_a_user_under_another_operator(): void
    {
        $this->actingAs($this->opAdminA)
            ->post('/users/create', $this->createPayload(['operator_id' => $this->hipl->id]))
            ->assertForbidden();

        $this->assertDatabaseMissing('users', ['email' => 'new.user@example.test']);
    }

    public function test_operator_admin_cannot_mint_a_superadmin_or_any_happyice_role(): void
    {
        foreach (['superadmin', 'admin', 'supervisor', 'driver'] as $role) {
            $this->actingAs($this->opAdminA)
                ->post('/users/create', $this->createPayload(['role_id' => Role::findByName($role, 'web')->id]))
                ->assertForbidden();
        }

        $this->assertDatabaseMissing('users', ['email' => 'new.user@example.test']);
    }

    public function test_operator_admin_creates_an_operator_tier_user_under_their_own_operator(): void
    {
        $this->actingAs($this->opAdminA)
            ->post('/users/create', $this->createPayload())
            ->assertSessionHasNoErrors()
            ->assertRedirect('/users');

        $created = User::where('email', 'new.user@example.test')->sole();
        $this->assertSame($this->opA->id, (int) $created->operator_id);
        $this->assertTrue($created->hasRole('operator_driver'));
    }

    public function test_create_ignores_privileged_fields_posted_alongside(): void
    {
        $this->actingAs($this->superadmin)
            ->post('/users/create', $this->createPayload([
                'is_active' => 0,
                'access_token' => 'stolen',
                'product_access_mode' => 'list',
                'transaction_access_from' => '2020-01-01',
            ]))
            ->assertSessionHasNoErrors();

        $created = User::where('email', 'new.user@example.test')->sole();
        $this->assertNull($created->access_token);
        $this->assertSame('all', $created->product_access_mode);
        $this->assertNull($created->transaction_access_from);
    }

    public function test_superadmin_can_create_a_superadmin_under_any_operator(): void
    {
        $this->actingAs($this->superadmin)
            ->post('/users/create', $this->createPayload([
                'operator_id' => $this->opB->id,
                'role_id' => Role::findByName('superadmin', 'web')->id,
            ]))
            ->assertSessionHasNoErrors()
            ->assertRedirect('/users');

        $created = User::where('email', 'new.user@example.test')->sole();
        $this->assertSame($this->opB->id, (int) $created->operator_id);
        $this->assertTrue($created->hasRole('superadmin'));
    }

    // --------------------------------------------- edit / update / delete / toggle

    public function test_operator_admin_cannot_edit_update_delete_or_toggle_another_operators_user(): void
    {
        $target = $this->staffB;

        $this->actingAs($this->opAdminA)->get("/users/{$target->id}/edit")->assertForbidden();
        $this->actingAs($this->opAdminA)->post("/users/{$target->id}/update", $this->updatePayload($target))->assertForbidden();
        $this->actingAs($this->opAdminA)->post("/users/{$target->id}/toggle-activate-deactivate")->assertForbidden();
        $this->actingAs($this->opAdminA)->delete("/users/{$target->id}")->assertForbidden();
        $this->actingAs($this->opAdminA)->post('/users/bind-vend', ['operator_id' => $target->id, 'vend_id' => 1])->assertForbidden();

        $fresh = $target->fresh();
        $this->assertSame('Staff B', $fresh->name);
        $this->assertTrue((bool) $fresh->is_active);
    }

    public function test_operator_admin_cannot_reparent_their_own_user_into_another_operator(): void
    {
        $this->actingAs($this->opAdminA)
            ->post("/users/{$this->staffA->id}/update", $this->updatePayload($this->staffA, ['operator_id' => $this->hipl->id]))
            ->assertForbidden();

        $this->assertSame($this->opA->id, (int) $this->staffA->fresh()->operator_id);
    }

    public function test_operator_admin_cannot_promote_a_user_to_superadmin(): void
    {
        $this->actingAs($this->opAdminA)
            ->post("/users/{$this->staffA->id}/update", $this->updatePayload($this->staffA, [
                'role_id' => Role::findByName('superadmin', 'web')->id,
            ]))
            ->assertForbidden();

        $fresh = $this->staffA->fresh();
        $this->assertTrue($fresh->hasRole('operator_driver'));
        $this->assertFalse($fresh->hasRole('superadmin'));
        $this->assertSame('Staff A', $fresh->name);
    }

    public function test_operator_admin_updates_their_own_operators_user_within_the_allow_list(): void
    {
        $this->actingAs($this->opAdminA)
            ->post("/users/{$this->staffA->id}/update", $this->updatePayload($this->staffA, [
                'role_id' => Role::findByName('operator_supervisor', 'web')->id,
            ]))
            ->assertSessionHasNoErrors()
            ->assertRedirect("/users/{$this->staffA->id}/edit");

        $fresh = $this->staffA->fresh();
        $this->assertSame('Staff A (edited)', $fresh->name);
        $this->assertTrue($fresh->hasRole('operator_supervisor'));
        $this->assertFalse($fresh->hasRole('operator_driver'));
    }

    public function test_superadmin_may_edit_move_promote_toggle_and_delete_any_user(): void
    {
        $target = $this->staffB;

        $this->actingAs($this->superadmin)->get("/users/{$target->id}/edit")->assertOk();

        $this->actingAs($this->superadmin)
            ->post("/users/{$target->id}/update", $this->updatePayload($target, [
                'operator_id' => $this->hipl->id,
                'role_id' => Role::findByName('admin', 'web')->id,
            ]))
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $fresh = $target->fresh();
        $this->assertSame($this->hipl->id, (int) $fresh->operator_id);
        $this->assertTrue($fresh->hasRole('admin'));

        $this->actingAs($this->superadmin)->post("/users/{$target->id}/toggle-activate-deactivate")->assertRedirect('/users');
        $this->assertFalse((bool) $target->fresh()->is_active);

        $this->actingAs($this->superadmin)->delete("/users/{$target->id}")->assertRedirect('/users');
        $this->assertDatabaseMissing('users', ['id' => $target->id]);
    }

    // ------------------------------------------------------------- index()

    public function test_operator_admin_cannot_enumerate_other_operators_users_through_the_filter(): void
    {
        $names = fn (array $query) => collect(
            $this->actingAs($this->opAdminA)
                ->get('/users?'.http_build_query($query))
                ->assertOk()
                ->viewData('page')['props']['users']['data']
        )->pluck('name')->sort()->values()->all();

        $own = ['Op Admin A', 'Staff A'];

        $this->assertSame($own, $names([]));
        $this->assertSame($own, $names(['operator_id' => $this->hipl->id]));
        $this->assertSame($own, $names(['operator_id' => $this->opB->id]));
        $this->assertSame($own, $names(['operator_id' => 'all']));
    }

    public function test_superadmin_keeps_the_free_operator_filter(): void
    {
        $names = collect(
            $this->actingAs($this->superadmin)
                ->get('/users?operator_id='.$this->opB->id)
                ->viewData('page')['props']['users']['data']
        )->pluck('name')->all();

        $this->assertSame(['Staff B'], $names);
    }

    public function test_role_dropdown_matches_the_allow_list(): void
    {
        $roleNames = fn (User $viewer, string $url) => collect(
            $this->actingAs($viewer)->get($url)->assertOk()->viewData('page')['props']['roles']['data']
        )->pluck('name')->sort()->values()->all();

        $operatorTier = User::OPERATOR_ASSIGNABLE_ROLES;
        sort($operatorTier);

        $this->assertSame($operatorTier, $roleNames($this->opAdminA, '/users'));
        $this->assertSame($operatorTier, $roleNames($this->opAdminA, "/users/{$this->staffA->id}/edit"));

        $this->assertContains('superadmin', $roleNames($this->superadmin, '/users'));
        $this->assertContains('superadmin', $roleNames($this->superadmin, "/users/{$this->staffA->id}/edit"));
    }

    // ------------------------------------------------------- /self (M2-12 + M2-01)

    public function test_self_update_ignores_the_route_id_and_only_edits_the_caller(): void
    {
        $superHashBefore = $this->superadmin->password;

        $this->actingAs($this->opAdminA)
            ->post("/self/{$this->superadmin->id}/update", [
                'name' => 'Op Admin A',
                'email' => $this->opAdminA->email,
                'password' => 'pwned-pass',
                'password_confirmation' => 'pwned-pass',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect('/self');

        $this->assertSame($superHashBefore, $this->superadmin->fresh()->password);
        $this->assertTrue(Hash::check('original', $this->superadmin->fresh()->password));
        $this->assertTrue(Hash::check('pwned-pass', $this->opAdminA->fresh()->password));
    }

    public function test_every_role_can_open_and_save_account_settings(): void
    {
        $this->actingAs($this->driver)->get('/self')->assertOk();

        $this->actingAs($this->driver)
            ->post("/self/{$this->driver->id}/update", [
                'name' => 'Driver Renamed',
                'email' => $this->driver->email,
                'password' => 'new-driver-pass',
                'password_confirmation' => 'new-driver-pass',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect('/self');

        $fresh = $this->driver->fresh();
        $this->assertSame('Driver Renamed', $fresh->name);
        $this->assertTrue(Hash::check('new-driver-pass', $fresh->password));
    }

    public function test_driver_still_cannot_open_the_users_list(): void
    {
        $this->actingAs($this->driver)->get('/users')->assertForbidden();
    }
}
