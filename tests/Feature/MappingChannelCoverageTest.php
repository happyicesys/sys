<?php

namespace Tests\Feature;

use App\Models\Operator;
use App\Models\Product;
use App\Models\ProductMapping;
use App\Models\ProductMappingItem;
use App\Models\Scopes\OperatorVendFilterScope;
use App\Models\User;
use App\Models\Vend;
use App\Models\VendChannel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * The coverage payload behind Setting/Edit's "covers 0 of N slots" warning.
 *
 * Origin: machine 2487 ran 2026-09-17 → 09-23 on UEE-UEI_2609, a mapping for a
 * different machine family with ZERO overlap with its real slots. Nothing
 * refused it and nothing warned, and every sale was booked with no product and
 * no COGS. See UNATTRIBUTED_SALES_AUDIT_2026-09-23.md.
 *
 * Run: php artisan test --filter=MappingChannelCoverageTest
 */
class MappingChannelCoverageTest extends TestCase
{
    use RefreshDatabase;

    private Operator $operator;

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('operators')->insert([
            'id' => OperatorVendFilterScope::UNRESTRICTED_OPERATOR_ID,
            'code' => 'HIPL', 'name' => 'HIPL', 'is_active' => 1, 'gst_vat_rate' => 9,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->operator = Operator::withoutGlobalScopes()->findOrFail(OperatorVendFilterScope::UNRESTRICTED_OPERATOR_ID);
    }

    private function user(): User
    {
        $user = User::factory()->create(['operator_id' => $this->operator->id]);
        // the edit route gates on both
        $user->givePermissionTo(Permission::findOrCreate('read machine-settings', 'web'));
        $user->givePermissionTo(Permission::findOrCreate('update machine-settings', 'web'));

        return $user;
    }

    private function vend(string $machineType = Vend::MACHINE_TYPE_VENDING_MACHINE, array $channelCodes = ['11', '12', '13']): Vend
    {
        $vend = Vend::withoutGlobalScopes()->create([
            'code' => 2487,
            'operator_id' => $this->operator->id,
            'is_active' => true,
            'machine_type' => $machineType,
        ]);

        foreach ($channelCodes as $code) {
            VendChannel::withoutGlobalScopes()->create([
                'vend_id' => $vend->id, 'code' => $code, 'is_active' => true,
            ]);
        }

        return $vend;
    }

    private function mapping(string $name, array $channelCodes, string $machineType = Vend::MACHINE_TYPE_VENDING_MACHINE): ProductMapping
    {
        $mapping = ProductMapping::withoutGlobalScopes()->create([
            'name' => $name,
            'operator_id' => $this->operator->id,
            'is_active' => true,
            'machine_type' => $machineType,
        ]);

        foreach ($channelCodes as $code) {
            $product = Product::withoutGlobalScopes()->create([
                'code' => $name.'-'.$code, 'name' => $name.'-'.$code,
                'operator_id' => $this->operator->id, 'is_active' => true,
            ]);
            ProductMappingItem::create([
                'product_mapping_id' => $mapping->id,
                'channel_code' => $code,
                'product_id' => $product->id,
            ]);
        }

        return $mapping;
    }

    private function coverage(Vend $vend): array
    {
        return $this->actingAs($this->user())
            ->get(route('settings.edit', $vend->id))
            ->viewData('page')['props']['mappingChannelCoverage'];
    }

    public function test_it_counts_how_many_live_slots_each_mapping_covers(): void
    {
        $vend = $this->vend();
        $fits = $this->mapping('USD_2608a', ['11', '12', '13']);
        $partial = $this->mapping('USD_partial', ['11', '99']);
        $wrongFamily = $this->mapping('UEE_2609', ['31', '32', '61']);

        $coverage = $this->coverage($vend);

        $this->assertSame(3, $coverage['active_channels']);
        $this->assertSame(3, $coverage['covered'][$fits->id]);
        $this->assertSame(1, $coverage['covered'][$partial->id], 'only channel 11 overlaps');
        $this->assertSame(0, $coverage['covered'][$wrongFamily->id], 'the 2487 case');
    }

    public function test_a_mapping_with_no_overlap_is_reported_as_zero_not_omitted(): void
    {
        $vend = $this->vend();
        $wrongFamily = $this->mapping('UEE_2609', ['31', '32']);

        $coverage = $this->coverage($vend);

        // The Vue reads this key directly; a missing key would read as "cannot
        // judge" and silently suppress the warning.
        $this->assertArrayHasKey($wrongFamily->id, $coverage['covered']);
        $this->assertSame(0, $coverage['covered'][$wrongFamily->id]);
    }

    public function test_an_inactive_slot_is_not_counted(): void
    {
        $vend = $this->vend(Vend::MACHINE_TYPE_VENDING_MACHINE, ['11', '12']);
        VendChannel::withoutGlobalScopes()->create([
            'vend_id' => $vend->id, 'code' => '77', 'is_active' => false,
        ]);
        $mapping = $this->mapping('only_77', ['77']);

        $coverage = $this->coverage($vend);

        $this->assertSame(2, $coverage['active_channels']);
        $this->assertSame(0, $coverage['covered'][$mapping->id], 'slot 77 is inactive, so covering it counts for nothing');
    }

    public function test_a_machine_with_no_reported_slots_cannot_be_judged(): void
    {
        $vend = $this->vend(Vend::MACHINE_TYPE_VENDING_MACHINE, []);
        $this->mapping('USD_2608a', ['11']);

        $coverage = $this->coverage($vend);

        // No slots reported yet (a new machine) — an empty map means the Vue
        // shows nothing rather than warning about everything.
        $this->assertSame(0, $coverage['active_channels']);
        $this->assertSame([], $coverage['covered']);
    }

    /**
     * A freezer's and a chiller's channels are CREATED from the mapping
     * (FreezerChannelSync / ChillerChannelMap), so coverage is tautological and
     * warning would be noise.
     */
    public function test_a_smart_freezer_is_not_judged(): void
    {
        $vend = $this->vend(Vend::MACHINE_TYPE_SMART_FREEZER, ['11', '12']);
        $this->mapping('freezer_map', ['31'], Vend::MACHINE_TYPE_SMART_FREEZER);

        $this->assertSame(['active_channels' => 0, 'covered' => []], $this->coverage($vend));
    }

    public function test_a_smart_chiller_is_not_judged(): void
    {
        $vend = $this->vend(Vend::MACHINE_TYPE_SMART_CHILLER, ['101', '102']);
        $this->mapping('chiller_map', ['501'], Vend::MACHINE_TYPE_SMART_CHILLER);

        $this->assertSame(['active_channels' => 0, 'covered' => []], $this->coverage($vend));
    }
}
