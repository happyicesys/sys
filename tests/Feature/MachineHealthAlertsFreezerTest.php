<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Vend;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * No machine-health rule is written for a Smart Freezer yet (the rules read vending T1/T2 probes,
 * channel error codes and cash patterns), so the Operation Dashboard must not badge one.
 */
class MachineHealthAlertsFreezerTest extends TestCase
{
    use RefreshDatabase;

    public function test_freezers_are_left_out_of_the_active_alerts_payload(): void
    {
        $user = User::factory()->create(['operator_id' => 1]);
        $user->givePermissionTo(Permission::findOrCreate('read reports', 'web'));
        $this->actingAs($user);

        $freezer = Vend::create([
            'code' => 50001, 'machine_type' => Vend::MACHINE_TYPE_SMART_FREEZER,
            'is_active' => 1, 'operator_id' => 1,
        ]);
        $vending = Vend::create([
            'code' => 2052, 'machine_type' => Vend::MACHINE_TYPE_VENDING_MACHINE,
            'is_active' => 1, 'operator_id' => 1,
        ]);

        $response = $this->postJson('/reports/machine-health/active-alerts', [
            'vend_ids' => [$freezer->id, $vending->id],
        ])->assertOk();

        $payload = $response->json();
        $this->assertArrayNotHasKey((string) $freezer->id, $payload);
        // ...while the vending machine beside it still reports: both are offline, so the guard
        // must silence the freezer only, not the whole call.
        $this->assertArrayHasKey((string) $vending->id, $payload);
        $this->assertSame('connectivity', $payload[(string) $vending->id][0]['group']);
    }
}
