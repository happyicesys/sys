<?php

namespace Tests\Feature;

use App\Models\Telco;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Data Management (Telco) — the `desc` textarea added 2026-08-24.
 * Covers storability via create/update and exposure through TelcoResource.
 */
class TelcoDescFieldTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_stores_desc(): void
    {
        $this->actingAs(User::factory()->create())
            ->post('/telcos/create', [
                'name' => 'Singtel (IMSI)',
                'desc' => "CAT-M plan\n200MB monthly",
            ])
            ->assertRedirect(route('telcos'));

        $this->assertDatabaseHas('telcos', [
            'name' => 'Singtel (IMSI)',
            'desc' => "CAT-M plan\n200MB monthly",
        ]);
    }

    public function test_create_allows_empty_desc(): void
    {
        $this->actingAs(User::factory()->create())
            ->post('/telcos/create', ['name' => 'M1', 'desc' => null])
            ->assertRedirect(route('telcos'));

        $this->assertDatabaseHas('telcos', ['name' => 'M1', 'desc' => null]);
    }

    public function test_update_stores_desc(): void
    {
        $telco = Telco::create(['name' => 'Starhub (ICCID)']);

        $this->actingAs(User::factory()->create())
            ->post("/telcos/{$telco->id}/update", [
                'name' => 'Starhub (ICCID)',
                'desc' => 'Legacy fleet SIMs',
            ])
            ->assertRedirect(route('telcos'));

        $this->assertSame('Legacy fleet SIMs', $telco->fresh()->desc);
    }

    public function test_index_exposes_desc_in_resource(): void
    {
        Telco::create(['name' => 'Redone', 'desc' => 'MY machines']);

        $this->actingAs(User::factory()->create())
            ->get('/telcos')
            ->assertInertia(fn ($page) => $page
                ->component('Telco/Index')
                ->where('telcos.data.0.name', 'Redone')
                ->where('telcos.data.0.desc', 'MY machines')
            );
    }
}
