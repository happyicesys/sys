<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductMapping;
use App\Models\Vend;
use App\Models\VendChannel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\Citybox\ChillerMapping;
use Tests\TestCase;

/**
 * `citybox:adopt-planogram` — the one-off that lands the 2026-09-21 hand-over on
 * a fleet that has been running the mirror: capacities learnt from the par the
 * channels carry today, and the "(mirror)" mappings renamed to the machine's ID.
 */
class CityboxAdoptPlanogramTest extends TestCase
{
    use RefreshDatabase;

    private function chiller(int $code, string $prefix = 'C'): Vend
    {
        return Vend::create([
            'code' => $code, 'code_prefix' => $prefix, 'machine_type' => Vend::MACHINE_TYPE_SMART_CHILLER,
            'citybox_equipment_id' => 'E'.$code, 'is_active' => 1, 'operator_id' => 1,
        ]);
    }

    public function test_it_seeds_capacity_from_the_par_the_channels_carry_and_renames_the_mirrors(): void
    {
        $vend = $this->chiller(5001);
        ChillerMapping::bind($vend, [101 => [90338, 0], 102 => [90339, 0]], 'CityBox E5001 (mirror)');
        $vend->refresh();
        ProductMapping::whereKey($vend->product_mapping_id)->update(['remarks' => 'Read-only mirror of CityBox Pre-Stock Setup — edit in the CityBox portal, then Re-sync.']);
        $suntory = Product::where('code', '90338')->firstOrFail();
        $lemon = Product::where('code', '90339')->firstOrFail();
        // Capacity is blank on both; their channels carry the old par (7 and 5).
        Product::whereIn('id', [$suntory->id, $lemon->id])->update(['chiller_slot_qty' => null]);
        VendChannel::create(['vend_id' => $vend->id, 'code' => 101, 'product_id' => $suntory->id, 'qty' => 0, 'capacity' => 7, 'is_active' => true]);
        VendChannel::create(['vend_id' => $vend->id, 'code' => 102, 'product_id' => $lemon->id, 'qty' => 0, 'capacity' => 5, 'is_active' => true]);

        $this->artisan('citybox:adopt-planogram')->assertSuccessful();
        $this->assertNull($suntory->fresh()->chiller_slot_qty, 'dry run writes nothing');
        $this->assertStringContainsString('(mirror)', ProductMapping::find($vend->product_mapping_id)->name);

        $this->artisan('citybox:adopt-planogram --apply')->assertSuccessful();

        $this->assertSame(7, (int) $suntory->fresh()->chiller_slot_qty);
        $this->assertSame(5, (int) $lemon->fresh()->chiller_slot_qty);
        $mapping = ProductMapping::find($vend->product_mapping_id);
        $this->assertSame('C5001 planogram', $mapping->name);
        $this->assertStringContainsString('OPS Pro', $mapping->remarks);
    }

    public function test_it_never_overwrites_a_capacity_someone_has_set(): void
    {
        $vend = $this->chiller(5002);
        ChillerMapping::bind($vend, [101 => [90338, 3]], 'CityBox E5002 (mirror)');
        $vend->refresh();
        $product = Product::where('code', '90338')->firstOrFail();
        VendChannel::create(['vend_id' => $vend->id, 'code' => 101, 'product_id' => $product->id, 'qty' => 0, 'capacity' => 7, 'is_active' => true]);

        $this->artisan('citybox:adopt-planogram --apply')->assertSuccessful();

        $this->assertSame(3, (int) $product->fresh()->chiller_slot_qty, 'ops measured 3 — their par must not override it');
    }

    public function test_a_second_run_changes_nothing(): void
    {
        $vend = $this->chiller(5003);
        ChillerMapping::bind($vend, [101 => [90338, 0]], 'CityBox E5003 (mirror)');
        $vend->refresh();
        Product::where('code', '90338')->update(['chiller_slot_qty' => null]);
        VendChannel::create(['vend_id' => $vend->id, 'code' => 101, 'product_id' => Product::where('code', '90338')->value('id'), 'qty' => 0, 'capacity' => 6, 'is_active' => true]);

        $this->artisan('citybox:adopt-planogram --apply')->assertSuccessful();
        $this->artisan('citybox:adopt-planogram --apply')->assertSuccessful();

        $this->assertSame(6, (int) Product::where('code', '90338')->value('chiller_slot_qty'));
        $this->assertSame('C5003 planogram', ProductMapping::find($vend->fresh()->product_mapping_id)->name);
    }
}
