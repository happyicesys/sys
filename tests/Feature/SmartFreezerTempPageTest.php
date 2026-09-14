<?php

namespace Tests\Feature;

use App\Http\Resources\VendDBResource;
use Tests\TestCase;

/**
 * Temp.vue hides the T2-T4 probe toggles and the alert markers for a Smart
 * Freezer (one probe, no alert rules yet), keyed on vendObj.data.machine_type —
 * VendController::temp selects vends.machine_type and VendDBResource must emit it.
 */
class SmartFreezerTempPageTest extends TestCase
{
    public function test_vend_db_resource_emits_machine_type_when_selected(): void
    {
        $withType = VendDBResource::make($this->row(['id' => 1, 'machine_type' => 'smart_freezer']))->resolve(request());
        $without = VendDBResource::make($this->row(['id' => 2]))->resolve(request());

        $this->assertSame('smart_freezer', $withType['machine_type']);
        $this->assertNull($without['machine_type']);
    }

    /** A DB::table row stand-in: selected columns are set, the rest read as null (the resource reads many unguarded). */
    private function row(array $columns): object
    {
        return new class($columns)
        {
            public function __construct(private array $columns) {}

            public function __get($key)
            {
                return $this->columns[$key] ?? null;
            }

            public function __isset($key)
            {
                return isset($this->columns[$key]);
            }
        };
    }

    public function test_temp_controller_selects_machine_type(): void
    {
        $source = file_get_contents(app_path('Http/Controllers/VendController.php'));
        $temp = substr($source, strpos($source, 'public function temp('), 2500);

        $this->assertStringContainsString("'vends.machine_type'", $temp);
    }
}
