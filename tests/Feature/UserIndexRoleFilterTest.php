<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Users index "Role" filter (Brian, 2026-09-11): a multi-select, request key
 * `roles[]` of role ids. Empty means every role; a user matches when ANY of
 * their roles is selected.
 */
class UserIndexRoleFilterTest extends TestCase
{
    use RefreshDatabase;

    private function userWithRole(string $name, string $role, ?int $operatorId): User
    {
        $user = User::factory()->create(['name' => $name, 'operator_id' => $operatorId]);
        $user->assignRole(Role::findOrCreate($role, 'web'));

        return $user;
    }

    public function test_filters_users_by_one_or_more_roles(): void
    {
        $viewer = User::factory()->create(['name' => 'Viewer']);
        $viewer->givePermissionTo(Permission::findOrCreate('read users', 'web'));
        $this->be($viewer);

        $this->userWithRole('Alice', 'admin', $viewer->operator_id);
        $this->userWithRole('Bob', 'driver', $viewer->operator_id);
        $this->userWithRole('Carol', 'technician', $viewer->operator_id);

        $admin = Role::findByName('admin', 'web');
        $driver = Role::findByName('driver', 'web');

        $names = fn (array $query) => collect(
            $this->get('/users?'.http_build_query($query))->viewData('page')['props']['users']['data']
        )->pluck('name')->sort()->values()->all();

        $this->assertSame(['Alice', 'Bob', 'Carol', 'Viewer'], $names([]));
        $this->assertSame(['Alice'], $names(['roles' => [$admin->id]]));
        $this->assertSame(['Alice', 'Bob'], $names(['roles' => [$admin->id, $driver->id]]));
    }
}
