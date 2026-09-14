<?php

namespace Tests\Feature;

use App\Models\ProductMapping;
use App\Models\User;
use App\Models\Vend;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Setting/Edit hides the VMC-board pickers for a Smart Freezer (setting chart,
 * machine prefix, menu frame, LCD monitor, LED panel, fan signal), so the save
 * must not require them — the same relaxation a Smart Chiller already gets.
 */
class SmartFreezerSettingsSaveTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        $this->actingAs(User::factory()->create());
    }

    private function freezer(array $attrs = []): Vend
    {
        return Vend::create(array_merge([
            'code' => 50009, 'machine_type' => Vend::MACHINE_TYPE_SMART_FREEZER,
            'is_active' => 1, 'operator_id' => 1, 'vend_model_id' => 1,
            'lcd_monitor_id' => null, 'menu_frame_id' => null, 'vend_config_id' => null, 'vend_prefix_id' => null,
        ], $attrs));
    }

    public function test_freezer_saves_without_vending_hardware_or_prefix(): void
    {
        $mapping = ProductMapping::create([
            'name' => 'Freezer planogram', 'machine_type' => Vend::MACHINE_TYPE_SMART_FREEZER,
            'is_active' => true, 'operator_id' => 1,
        ]);
        $vend = $this->freezer(['product_mapping_id' => $mapping->id]);

        $this->post('/vends/'.$vend->id.'/update', [
            'machine_type' => 'smart_freezer',
            'operator_id' => 1,
            'vend_model_id' => 1,
            'label_name' => 'Unit 1',
            'product_mapping_id' => $mapping->id,
            'vend_prefix_id' => null,
            'vend_config_id' => null,
            'lcd_monitor_id' => null,
            'menu_frame_id' => null,
        ])->assertSessionHasNoErrors();

        $this->assertSame('Unit 1', $vend->fresh()->label_name);
    }

    public function test_freezer_saves_before_a_mapping_is_bound(): void
    {
        $vend = $this->freezer(['product_mapping_id' => null]);

        $this->post('/vends/'.$vend->id.'/update', [
            'machine_type' => 'smart_freezer', 'operator_id' => 1, 'vend_model_id' => 1,
            'product_mapping_id' => null,
        ])->assertSessionHasNoErrors();
    }

    public function test_freezer_still_requires_operator_and_model(): void
    {
        $vend = $this->freezer();

        $this->post('/vends/'.$vend->id.'/update', ['machine_type' => 'smart_freezer'])
            ->assertSessionHasErrors(['operator_id', 'vend_model_id']);
    }
}
