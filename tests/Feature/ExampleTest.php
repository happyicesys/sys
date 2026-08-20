<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * A basic test example.
     *
     * @return void
     */
    public function test_the_application_returns_a_successful_response()
    {
        $response = $this->get('/');

        // '/' deliberately redirects guests to the login screen (routes/web.php)
        // — the Breeze welcome page this test was born asserting is long gone.
        $response->assertRedirect('/login');
    }
}
