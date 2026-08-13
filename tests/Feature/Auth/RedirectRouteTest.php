<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RedirectRouteTest extends TestCase
{
    use RefreshDatabase;

    private function userWithRole(string $roleName): User
    {
        Role::findOrCreate($roleName, 'web');

        return tap(User::factory()->create())->assignRole($roleName);
    }

    /**
     * Driver roles hold no `read vend-customers`, so the default
     * /vends/customers landing gave them a bare 403 right after login.
     */
    public function test_driver_roles_land_on_ops_jobs(): void
    {
        foreach (['driver', 'operator_driver'] as $roleName) {
            $this->assertSame(
                '/ops-jobs',
                $this->userWithRole($roleName)->getRedirectRoute(),
                "role {$roleName}"
            );
        }
    }

    public function test_a_future_driver_role_also_lands_on_ops_jobs(): void
    {
        $this->assertSame(
            '/ops-jobs',
            $this->userWithRole('operator_3pl_driver')->getRedirectRoute()
        );
    }

    public function test_non_driver_roles_are_unaffected(): void
    {
        $this->assertSame(
            '/vends/customers-lite',
            $this->userWithRole('prod_owner')->getRedirectRoute()
        );

        $this->assertSame(
            '/vends/customers',
            $this->userWithRole('operator_3pl')->getRedirectRoute()
        );
    }
}
