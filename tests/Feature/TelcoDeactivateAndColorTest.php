<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Simcard;
use App\Models\Telco;
use App\Models\User;
use App\Models\Vend;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * SimCard Package retirement + badge colour (Brian, 2026-09-09).
 *
 * A package is deactivated, never deleted — simcards.telco_id and every report
 * reached through it keep their label — and only while no SIM on the package
 * is still bound to a machine. The Index carries that number ("Active /
 * Total") so the block is visible before the button is pressed.
 *
 * `color` tints the package's badge on the Operation Dashboard; only the five
 * Telco::COLORS are accepted, because green / grey / red already mean machine
 * status on that page.
 */
class TelcoDeactivateAndColorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->be(User::factory()->create());
    }

    /** A SIM on the package, optionally sitting in a machine. */
    private function simcard(Telco $telco, ?int $vendCode = null): Simcard
    {
        $simcard = Simcard::create([
            'code' => '89852202511210'.str_pad((string) random_int(1, 999999), 6, '0', STR_PAD_LEFT),
            'telco_id' => $telco->id,
            'created_by' => auth()->id(),
        ]);

        if ($vendCode !== null) {
            $customer = Customer::create([
                'name' => 'Site '.$vendCode,
                'code' => $vendCode,
                'operator_id' => auth()->user()->operator_id,
                'status_id' => Customer::STATUS_ACTIVE,
            ]);

            Vend::create([
                'code' => $vendCode,
                'machine_type' => 'vending_machine',
                'is_active' => 1,
                'customer_id' => $customer->id,
                'simcard_id' => $simcard->id,
            ]);
        }

        return $simcard;
    }

    public function test_index_counts_sims_on_machines_over_the_package_total(): void
    {
        $telco = Telco::create(['name' => 'Starhub (ICCID)']);
        $this->simcard($telco, 20001);
        $this->simcard($telco, 20002);
        $this->simcard($telco); // spare, not in a machine

        $this->get('/telcos')
            ->assertInertia(fn ($page) => $page
                ->component('Telco/Index')
                ->where('telcos.data.0.simcards_on_machine_count', 2)
                ->where('telcos.data.0.simcards_count', 3)
                ->where('telcos.data.0.is_active', true)
                ->where('colorOptions', Telco::COLORS)
                ->where('status', 'active')
            );
    }

    public function test_deactivate_is_refused_while_a_sim_is_in_a_machine(): void
    {
        $telco = Telco::create(['name' => 'Starhub (ICCID)']);
        $this->simcard($telco, 20003);

        $this->from('/telcos')
            ->post("/telcos/{$telco->id}/toggle-activate-deactivate")
            ->assertRedirect('/telcos')
            ->assertSessionHasErrors('is_active');

        $this->assertTrue($telco->fresh()->is_active);
    }

    public function test_a_package_with_only_spare_sims_can_be_retired_and_brought_back(): void
    {
        $telco = Telco::create(['name' => 'M1']);
        $this->simcard($telco); // in stock, no machine

        $this->from('/telcos')
            ->post("/telcos/{$telco->id}/toggle-activate-deactivate")
            ->assertRedirect('/telcos')
            ->assertSessionHasNoErrors();

        $this->assertFalse($telco->fresh()->is_active);

        // Reactivating is never blocked.
        $this->from('/telcos')->post("/telcos/{$telco->id}/toggle-activate-deactivate");

        $this->assertTrue($telco->fresh()->is_active);
    }

    public function test_status_filter_defaults_to_active_and_can_list_the_retired_ones(): void
    {
        Telco::create(['name' => 'Live package']);
        Telco::create(['name' => 'Retired package', 'is_active' => false]);

        $this->get('/telcos')
            ->assertInertia(fn ($page) => $page
                ->component('Telco/Index')
                ->has('telcos.data', 1)
                ->where('telcos.data.0.name', 'Live package')
            );

        $this->get('/telcos?status=inactive')
            ->assertInertia(fn ($page) => $page
                ->has('telcos.data', 1)
                ->where('telcos.data.0.name', 'Retired package')
            );

        $this->get('/telcos?status=all')
            ->assertInertia(fn ($page) => $page->has('telcos.data', 2));
    }

    public function test_color_is_stored_from_the_palette_and_blank_clears_it(): void
    {
        $this->post('/telcos/create', ['name' => 'VP-Starhub 6GB/y', 'color' => 'purple'])
            ->assertRedirect(route('telcos'));

        $telco = Telco::where('name', 'VP-Starhub 6GB/y')->firstOrFail();
        $this->assertSame('purple', $telco->color);

        $this->post("/telcos/{$telco->id}/update", ['name' => 'VP-Starhub 6GB/y', 'color' => ''])
            ->assertRedirect(route('telcos'));

        $this->assertNull($telco->fresh()->color);
    }

    public function test_the_reserved_status_colors_are_rejected(): void
    {
        foreach (['green', 'grey', 'red', 'pink'] as $reserved) {
            $this->from('/telcos')
                ->post('/telcos/create', ['name' => 'Bogus '.$reserved, 'color' => $reserved])
                ->assertRedirect('/telcos')
                ->assertSessionHasErrors('color');
        }

        $this->assertSame(0, Telco::count());
    }
}
