<?php

namespace Tests\Feature;

use App\Jobs\DetectTempTrends;
use App\Models\Vend;
use App\Models\VendTemp;
use App\Models\VendSmartAlert;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Carbon\Carbon;

class DetectTempTrendsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Model::unguard();
    }

    public function test_it_detects_rising_temperature_t1()
    {
        // Setup
        $vend = Vend::create([
            'code' => 'V001',
            'name' => 'Test Vend 1',
        ]);
        $now = now();

        // Previous 24h min: 10.0C (stored as 100)
        VendTemp::create([
            'vend_id' => $vend->id,
            'value' => 100,
            'type' => VendTemp::TYPE_CHAMBER,
            'created_at' => $now->copy()->subHours(30),
        ]);

        // Current 24h min: 13.5C (stored as 135) - Delta 3.5C => Severity 3
        VendTemp::create([
            'vend_id' => $vend->id,
            'value' => 135,
            'type' => VendTemp::TYPE_CHAMBER,
            'created_at' => $now->copy()->subHours(1),
        ]);

        // Run Job
        (new DetectTempTrends)->handle();

        // Assert
        $this->assertDatabaseHas('vend_smart_alerts', [
            'vend_id' => $vend->id,
            'alert_type' => VendSmartAlert::TYPE_RISING_T1,
            'severity' => 3,
            'is_active' => true,
        ]);

        $alert = VendSmartAlert::where('vend_id', $vend->id)->first();
        $this->assertEquals(3.5, $alert->meta_data['delta']);
        $this->assertEquals(10.0, $alert->meta_data['prev_min']);
        $this->assertEquals(13.5, $alert->meta_data['current_min']);
    }

    public function test_it_detects_rising_temperature_t2_severity_1()
    {
        // Setup
        $vend = Vend::create([
            'code' => 'V002',
            'name' => 'Test Vend 2',
        ]);
        $now = now();

        // Previous 24h min: 5.0C
        VendTemp::create([
            'vend_id' => $vend->id,
            'value' => 50,
            'type' => VendTemp::TYPE_EVAPORATOR,
            'created_at' => $now->copy()->subHours(40),
        ]);

        // Current 24h min: 6.5C - Delta 1.5C => Severity 1
        VendTemp::create([
            'vend_id' => $vend->id,
            'value' => 65,
            'type' => VendTemp::TYPE_EVAPORATOR,
            'created_at' => $now->copy()->subHours(2),
        ]);

        // Run Job
        (new DetectTempTrends)->handle();

        // Assert
        $this->assertDatabaseHas('vend_smart_alerts', [
            'vend_id' => $vend->id,
            'alert_type' => VendSmartAlert::TYPE_RISING_T2,
            'severity' => 1,
            'is_active' => true,
        ]);
    }

    public function test_it_clears_alert_when_trend_improves()
    {
        // Setup
        $vend = Vend::create([
            'code' => 'V003',
            'name' => 'Test Vend 3',
        ]);
        $now = now();

        // Existing Alert
        VendSmartAlert::create([
            'vend_id' => $vend->id,
            'alert_type' => VendSmartAlert::TYPE_RISING_T1,
            'severity' => 3,
            'is_active' => true,
            'meta_data' => [],
        ]);

        // Previous 24h min: 10.0C
        VendTemp::create([
            'vend_id' => $vend->id,
            'value' => 100,
            'type' => VendTemp::TYPE_CHAMBER,
            'created_at' => $now->copy()->subHours(30),
        ]);

        // Current 24h min: 10.5C - Delta 0.5C => No Alert
        VendTemp::create([
            'vend_id' => $vend->id,
            'value' => 105,
            'type' => VendTemp::TYPE_CHAMBER,
            'created_at' => $now->copy()->subHours(1),
        ]);

        // Run Job
        (new DetectTempTrends)->handle();

        // Assert
        $this->assertDatabaseHas('vend_smart_alerts', [
            'vend_id' => $vend->id,
            'alert_type' => VendSmartAlert::TYPE_RISING_T1,
            'is_active' => false,
        ]);
    }
}
