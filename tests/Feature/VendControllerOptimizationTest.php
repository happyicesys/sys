<?php

namespace Tests\Feature;

use App\Models\User;
use Tests\TestCase;

class VendControllerOptimizationTest extends TestCase
{
    public function test_index_customer_query_runs_without_error()
    {
        // Create a user to authenticate
        $user = User::factory()->create();

        // Call the endpoint
        // We use withoutMiddleware to bypass permission checks for this smoke test
        $response = $this->actingAs($user)
            ->withoutMiddleware()
            ->get('/customers');

        // Assert success
        $response->assertStatus(200);
    }
}
