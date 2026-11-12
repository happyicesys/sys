<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Site → "Grab driver instruction" (Brian, 2026-11-12): how a Grab rider finds the machine, kept with the delivery
 * address and offered when the store's address is sent to Grab.
 */
class SiteGrabDriverInstructionTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_site_page_saves_the_grab_driver_instruction(): void
    {
        Permission::findOrCreate('update customers', 'web');
        $user = User::factory()->create(['operator_id' => 1]);
        $user->givePermissionTo('update customers');
        $site = Customer::create(['name' => 'Amber Park Condo', 'code' => '16825', 'operator_id' => 1, 'status_id' => Customer::STATUS_ACTIVE]);

        $this->actingAs($user)->post("/customers/{$site->id}/update", [
            'customer' => ['status_id' => Customer::STATUS_ACTIVE, 'grab_driver_instruction' => "Clubhouse, level 1.\nAsk the guardhouse to let you in."],
        ])->assertSessionHasNoErrors();

        $this->assertSame("Clubhouse, level 1.\nAsk the guardhouse to let you in.", $site->fresh()->grab_driver_instruction);

        $this->actingAs($user)->post("/customers/{$site->id}/update", [
            'customer' => ['status_id' => Customer::STATUS_ACTIVE, 'grab_driver_instruction' => str_repeat('x', 1001)],
        ])->assertSessionHasErrors('customer.grab_driver_instruction');
    }
}
