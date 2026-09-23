<?php

namespace Tests\Feature;

use App\Models\ProductMapping;
use App\Models\User;
use App\Models\Vend;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * basket_layout_json is the Smart Freezer's basket grid and nothing else's.
 *
 * The retired CityBox mirror wrote a [{layer, positions}] shape onto chiller
 * mappings, Replicate copied it, and ProductMapping/Edit echoes the column back
 * on Save — so the freezer rules ("basket_layout_json.*.basket required")
 * rejected every Save on ten chiller mappings (prod, 2026-09-23). The rules now
 * apply only to a freezer, a non-freezer's column is nulled on Save, and a
 * command clears the rows nobody has saved since.
 */
class ProductMappingBasketLayoutScopeTest extends TestCase
{
    use RefreshDatabase;

    private const MIRROR_LAYOUT = [
        ['layer' => 1, 'positions' => 13], ['layer' => 2, 'positions' => 12],
        ['layer' => 3, 'positions' => 12], ['layer' => 4, 'positions' => 7], ['layer' => 5, 'positions' => 17],
    ];

    private const BASKET_LAYOUT = [['basket' => 1, 'divisions' => 2], ['basket' => 2, 'divisions' => 1]];

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        foreach (['read product-mappings', 'update machine-settings'] as $p) {
            Permission::findOrCreate($p, 'web');
        }
        $user = User::factory()->create();
        $user->givePermissionTo(['read product-mappings', 'update machine-settings']);
        $this->actingAs($user);
    }

    private function mapping(string $machineType, ?array $layout): ProductMapping
    {
        return ProductMapping::create([
            'name' => ucfirst($machineType).' '.uniqid(),
            'machine_type' => $machineType,
            'is_smart' => $machineType === Vend::MACHINE_TYPE_SMART_FREEZER,
            'is_active' => true,
            'operator_id' => 1,
            'basket_layout_json' => $layout,
        ]);
    }

    public function test_a_chiller_mapping_saves_although_the_form_echoes_the_mirror_layout(): void
    {
        $mapping = $this->mapping(Vend::MACHINE_TYPE_SMART_CHILLER, self::MIRROR_LAYOUT);

        // Exactly what Edit.vue posts: the whole resource back, layout included.
        $this->from('/product-mappings/'.$mapping->id.'/edit')
            ->post('/product-mappings/'.$mapping->id.'/update', [
                'name' => 'C6002 planogram',
                'machine_type' => Vend::MACHINE_TYPE_SMART_CHILLER,
                'is_smart' => false,
                'basket_layout_json' => self::MIRROR_LAYOUT,
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect('/product-mappings/'.$mapping->id.'/edit')
            // Every save confirms itself; a silent redirect-back read as "nothing happened".
            ->assertSessionHas('success', 'Product mapping saved.');

        $mapping->refresh();
        $this->assertSame('C6002 planogram', $mapping->name);
        $this->assertNull($mapping->basket_layout_json, 'A chiller keeps no basket grid');
    }

    public function test_a_vending_mapping_never_keeps_a_layout_either(): void
    {
        $mapping = $this->mapping(Vend::MACHINE_TYPE_VENDING_MACHINE, null);

        $this->post('/product-mappings/'.$mapping->id.'/update', [
            'name' => 'VM menu', 'basket_layout_json' => [['basket' => 1, 'divisions' => 2]],
        ])->assertSessionHasNoErrors();

        $this->assertNull($mapping->fresh()->basket_layout_json);
    }

    public function test_a_freezer_layout_is_still_validated_and_kept(): void
    {
        $mapping = $this->mapping(Vend::MACHINE_TYPE_SMART_FREEZER, self::BASKET_LAYOUT);

        $this->from('/product-mappings/'.$mapping->id.'/edit')
            ->post('/product-mappings/'.$mapping->id.'/update', [
                'name' => 'Freezer A', 'is_smart' => true, 'basket_layout_json' => [['positions' => 3]],
            ])
            ->assertSessionHasErrors(['basket_layout_json.0.basket', 'basket_layout_json.0.divisions']);

        $this->post('/product-mappings/'.$mapping->id.'/update', [
            'name' => 'Freezer A', 'is_smart' => true, 'basket_layout_json' => [['basket' => 3, 'divisions' => 4]],
        ])->assertSessionHasNoErrors();

        $this->assertSame([['basket' => 3, 'divisions' => 4]], $mapping->fresh()->basket_layout_json);
    }

    public function test_the_clear_command_nulls_only_non_freezer_layouts(): void
    {
        $chiller = $this->mapping(Vend::MACHINE_TYPE_SMART_CHILLER, self::MIRROR_LAYOUT);
        $vending = $this->mapping(Vend::MACHINE_TYPE_VENDING_MACHINE, self::BASKET_LAYOUT);
        $freezer = $this->mapping(Vend::MACHINE_TYPE_SMART_FREEZER, self::BASKET_LAYOUT);
        $clean = $this->mapping(Vend::MACHINE_TYPE_SMART_CHILLER, null);

        $this->artisan('product-mappings:clear-basket-layout')
            ->expectsOutputToContain('Nothing written')
            ->assertSuccessful();
        $this->assertNotNull($chiller->fresh()->basket_layout_json, 'A dry run writes nothing');

        $this->artisan('product-mappings:clear-basket-layout', ['--apply' => true])
            ->expectsOutputToContain('Cleared basket_layout_json on 2 mapping(s)')
            ->assertSuccessful();

        $this->assertNull($chiller->fresh()->basket_layout_json);
        $this->assertNull($vending->fresh()->basket_layout_json);
        $this->assertNull($clean->fresh()->basket_layout_json);
        $this->assertSame(self::BASKET_LAYOUT, $freezer->fresh()->basket_layout_json, 'A freezer keeps its grid');
    }
}
