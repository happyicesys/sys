<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Nobody signs themselves up (security check 2026-11-08): a self-made account landed in operator 1,
 * which sees the whole fleet. Staff accounts are made by an admin under Users.
 */
class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_there_is_no_registration_screen()
    {
        $this->get('/register')->assertNotFound();
    }

    public function test_nobody_can_sign_themselves_up()
    {
        $before = User::count();

        $this->post('/register', [
            'name' => 'Stranger',
            'email' => 'stranger@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertStatus(404);

        $this->assertGuest();
        $this->assertSame($before, User::count());
    }
}
