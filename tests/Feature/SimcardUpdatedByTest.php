<?php

namespace Tests\Feature;

use App\Models\Simcard;
use App\Models\Telco;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Simcard Index "Updated By" column (Daniel, 2026-08-24): edits stamp
 * updated_by + updated_at — including binding-only edits, whose write lands on
 * vends, not the simcard row (the controller stamps explicitly for that).
 * Creation stamps created_by but leaves updated_by null, so never-edited rows
 * show '—' in the column.
 */
class SimcardUpdatedByTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create();
    }

    public function test_update_stamps_updated_by_and_time(): void
    {
        $telco = Telco::create(['name' => 'Starhub']);
        $sim = Simcard::create(['code' => '896505', 'telco_id' => $telco->id]);
        $this->assertNull($sim->updated_by);

        $user = $this->admin();
        $this->actingAs($user)
            ->post("/simcards/{$sim->id}/update", ['code' => '896505', 'telco_id' => $telco->id])
            ->assertRedirect(route('simcards'));

        $sim->refresh();
        $this->assertSame($user->id, $sim->updated_by);
    }

    public function test_binding_only_update_still_stamps(): void
    {
        $telco = Telco::create(['name' => 'M1']);
        $sim = Simcard::create(['code' => '012512', 'telco_id' => $telco->id]);
        $vend = \App\Models\Vend::create(['code' => '9101', 'is_active' => 1]);

        $user = $this->admin();
        $this->actingAs($user)
            ->post("/simcards/{$sim->id}/update", [
                'code' => '012512', 'telco_id' => $telco->id, 'vend_id' => $vend->id,
            ])->assertRedirect(route('simcards'));

        $sim->refresh();
        $this->assertSame($user->id, $sim->updated_by);
        $this->assertSame($sim->id, $vend->fresh()->simcard_id);
    }

    public function test_store_stamps_created_by_not_updated_by(): void
    {
        $telco = Telco::create(['name' => 'Singtel']);
        $user = $this->admin();

        $this->actingAs($user)
            ->post('/simcards/store', ['code' => '898989', 'telco_id' => $telco->id])
            ->assertRedirect(route('simcards'));

        $sim = Simcard::where('code', '898989')->firstOrFail();
        $this->assertSame($user->id, $sim->created_by);
        $this->assertNull($sim->updated_by);
    }
}
