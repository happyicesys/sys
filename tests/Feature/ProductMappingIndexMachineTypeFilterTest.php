<?php

namespace Tests\Feature;

use App\Models\Operator;
use App\Models\ProductMapping;
use App\Models\Scopes\OperatorVendFilterScope;
use App\Models\User;
use App\Models\Vend;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Machine Type filter on the Product Mapping index (2026-09-21).
 *
 * The list now carries three taxonomies side by side — ordinary vending
 * planograms, Smart Freezer mappings (ours) and Smart Chiller / CityBox
 * mirrors — and ops needed to see one kind at a time.
 *
 * Pinned here: 'all' (and a bare request, which merges to 'all') lists every
 * kind, each explicit type lists only its own, and the dropdown's options are
 * served from Vend::MACHINE_TYPE_MAPPINGS so the filter can never offer a
 * value the taxonomy does not have.
 *
 * Run: php artisan test --filter=ProductMappingIndexMachineTypeFilterTest
 */
class ProductMappingIndexMachineTypeFilterTest extends TestCase
{
    use RefreshDatabase;

    private Operator $hipl;

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('operators')->insert([
            'id' => OperatorVendFilterScope::UNRESTRICTED_OPERATOR_ID,
            'code' => 'HIPL',
            'name' => 'HIPL',
            'is_active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->hipl = Operator::withoutGlobalScopes()->findOrFail(OperatorVendFilterScope::UNRESTRICTED_OPERATOR_ID);
    }

    private function user(): User
    {
        $user = User::factory()->create(['operator_id' => $this->hipl->id]);
        $user->givePermissionTo(Permission::findOrCreate('read product-mappings', 'web'));

        return $user;
    }

    /**
     * Each mapping gets one ACTIVE bound machine: the index defaults to
     * vendStatus=active, which is a whereHas('vends') — a mapping with no bound
     * machine is invisible on the default view regardless of this filter.
     */
    private function mapping(string $name, string $machineType): ProductMapping
    {
        $mapping = ProductMapping::withoutGlobalScopes()->create([
            'name' => $name,
            'operator_id' => $this->hipl->id,
            'is_active' => true,
            'machine_type' => $machineType,
            'is_smart' => $machineType === Vend::MACHINE_TYPE_SMART_FREEZER,
        ]);

        Vend::withoutGlobalScopes()->create([
            'code' => '9'.str_pad((string) $mapping->id, 4, '0', STR_PAD_LEFT),
            'operator_id' => $this->hipl->id,
            'product_mapping_id' => $mapping->id,
            'is_active' => true,
            'machine_type' => $machineType,
        ]);

        return $mapping;
    }

    /** @return array<int, string> the mapping names the page rendered */
    private function names($response): array
    {
        $rows = $response->viewData('page')['props']['productMappings']['data'];

        return array_map(fn ($row) => $row['name'], $rows);
    }

    private function seedOneOfEach(): void
    {
        $this->mapping('VM planogram', Vend::MACHINE_TYPE_VENDING_MACHINE);
        $this->mapping('Freezer planogram', Vend::MACHINE_TYPE_SMART_FREEZER);
        $this->mapping('Chiller mirror', Vend::MACHINE_TYPE_SMART_CHILLER);
    }

    public function test_no_filter_lists_every_machine_type(): void
    {
        $this->seedOneOfEach();

        $names = $this->names($this->actingAs($this->user())->get('/product-mappings'));

        $this->assertEqualsCanonicalizing(
            ['VM planogram', 'Freezer planogram', 'Chiller mirror'],
            $names
        );
    }

    public function test_all_sentinel_lists_every_machine_type(): void
    {
        $this->seedOneOfEach();

        $names = $this->names(
            $this->actingAs($this->user())->get('/product-mappings?machineType=all')
        );

        $this->assertCount(3, $names);
    }

    public function test_each_machine_type_lists_only_its_own(): void
    {
        $this->seedOneOfEach();
        $user = $this->user();

        $expected = [
            Vend::MACHINE_TYPE_VENDING_MACHINE => 'VM planogram',
            Vend::MACHINE_TYPE_SMART_FREEZER => 'Freezer planogram',
            Vend::MACHINE_TYPE_SMART_CHILLER => 'Chiller mirror',
        ];

        foreach ($expected as $machineType => $name) {
            $names = $this->names(
                $this->actingAs($user)->get('/product-mappings?machineType='.$machineType)
            );

            $this->assertSame([$name], $names, "machineType={$machineType}");
        }
    }

    public function test_binded_vends_total_follows_the_machine_type_filter(): void
    {
        $this->mapping('VM planogram', Vend::MACHINE_TYPE_VENDING_MACHINE);
        $this->mapping('Freezer planogram', Vend::MACHINE_TYPE_SMART_FREEZER);

        $response = $this->actingAs($this->user())
            ->get('/product-mappings?machineType='.Vend::MACHINE_TYPE_SMART_FREEZER);

        $this->assertSame(1, $response->viewData('page')['props']['totalBindedVends']);
    }

    public function test_dropdown_options_come_from_the_vend_taxonomy(): void
    {
        $response = $this->actingAs($this->user())->get('/product-mappings');

        $this->assertSame(
            Vend::MACHINE_TYPE_MAPPINGS,
            $response->viewData('page')['props']['machineTypeOptions']
        );
    }
}
