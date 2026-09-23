<?php

namespace Tests\Feature;

use App\Models\Operator;
use App\Models\ProductMapping;
use App\Models\User;
use App\Models\Vend;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * The Setting/Edit mapping dropdowns must offer the same mappings the Product
 * Mapping index lists (Brian, 2026-09-23).
 *
 * Both builders call withoutGlobalScopes() so the vend's own assigned mapping
 * survives, then hand-rolled "own operator or global" — stricter than
 * OperatorProductMappingScope, which drops the operator filter entirely for
 * HIPL. An HIPL user editing a CityBox chiller therefore saw only the machine's
 * own mapping plus the one global sample, while the index listed every chiller
 * planogram, because those belong to the MACHINE's operator (CB), not the
 * viewer's.
 */
class SettingMappingOptionsOperatorTest extends TestCase
{
    use RefreshDatabase;

    private function chillerOperator(): Operator
    {
        return Operator::firstOrCreate(['code' => 'CB'], ['name' => 'Citybox']);
    }

    private function mapping(string $name, ?int $operatorId, bool $active = true): ProductMapping
    {
        return ProductMapping::create([
            'name' => $name,
            'machine_type' => Vend::MACHINE_TYPE_SMART_CHILLER,
            'is_smart' => false,
            'is_active' => $active,
            'operator_id' => $operatorId,
        ]);
    }

    private function viewer(int $operatorId): User
    {
        $user = User::factory()->create(['operator_id' => $operatorId]);
        $user->givePermissionTo(Permission::findOrCreate('read machine-settings', 'web'));
        $user->givePermissionTo(Permission::findOrCreate('update machine-settings', 'web'));

        return $user;
    }

    /** @return array<int,string> the names offered in both dropdowns */
    private function optionNames(User $user, Vend $vend, string $prop): array
    {
        $names = [];
        $this->actingAs($user)->get("/settings/vend/{$vend->id}/update")->assertOk()
            ->assertInertia(function ($page) use (&$names, $prop) {
                $names = collect($page->toArray()['props'][$prop]['data'] ?? [])->pluck('name')->all();
            });
        sort($names);

        return $names;
    }

    public function test_hipl_sees_every_chiller_planogram_not_just_the_global_sample(): void
    {
        $cb = $this->chillerOperator();
        $ownMapping = $this->mapping('C6001 planogram', $cb->id);
        $this->mapping('C6002 planogram', $cb->id);      // another machine's, same operator
        $this->mapping('citybox-sample', 1);             // HIPL's own
        $this->mapping('C6002 planogram-replicated', 1, active: false); // inactive, must stay out

        $vend = Vend::create([
            'code' => 6001, 'code_prefix' => 'C', 'machine_type' => Vend::MACHINE_TYPE_SMART_CHILLER,
            'citybox_equipment_id' => 'E1', 'is_active' => 1,
            'operator_id' => $cb->id, 'product_mapping_id' => $ownMapping->id,
        ]);

        $expected = ['C6001 planogram', 'C6002 planogram', 'citybox-sample'];
        $this->assertSame($expected, $this->optionNames($this->viewer(1), $vend, 'productMappingOptions'));
        $this->assertSame($expected, $this->optionNames($this->viewer(1), $vend, 'upcomingProductMappingOptions'));
    }

    public function test_another_operator_gets_its_own_plus_the_machines_own_operator(): void
    {
        $cb = $this->chillerOperator();
        $other = Operator::firstOrCreate(['code' => 'XO'], ['name' => 'XO']);
        $ownMapping = $this->mapping('C6001 planogram', $cb->id);
        $this->mapping('XO layout', $other->id);
        $this->mapping('global layout', null);
        $this->mapping('someone else layout', 999);

        $vend = Vend::create([
            'code' => 6001, 'code_prefix' => 'C', 'machine_type' => Vend::MACHINE_TYPE_SMART_CHILLER,
            'citybox_equipment_id' => 'E1', 'is_active' => 1,
            'operator_id' => $cb->id, 'product_mapping_id' => $ownMapping->id,
        ]);

        // Editing a CB machine: CB's mappings apply to it, plus the viewer's own and global.
        // A fourth operator's layout stays out.
        $this->assertSame(
            ['C6001 planogram', 'XO layout', 'global layout'],
            $this->optionNames($this->viewer($other->id), $vend, 'productMappingOptions')
        );
    }
}
