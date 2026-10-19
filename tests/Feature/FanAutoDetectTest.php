<?php

namespace Tests\Feature;

use App\Jobs\DetectTempTrends;
use App\Jobs\Vend\SyncVendParameter;
use App\Models\User;
use App\Models\Vend;
use App\Models\VendFan;
use App\Models\VendSmartAlert;
use App\Models\VendTemp;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Fan detection (Brian, 2026-10-19): the system decides whether a machine has a fan — "no" until its
 * board reports one running (above 1000), then "yes" for good; only a person resets it. Machine
 * Health's 2A judges only reported readings. Pinned: the latch and its stamp, noise never counting as
 * a fan, a dead fan staying alarmed, a reporting gap never alarming, the reset, the backfill.
 */
class FanAutoDetectTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-19 15:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function vend(int $code, array $attributes = []): Vend
    {
        return Vend::forceCreate(array_merge([
            'code' => $code, 'name' => "Machine {$code}", 'operator_id' => 1,
            'machine_type' => Vend::MACHINE_TYPE_VENDING_MACHINE,
            'is_active' => 1, 'is_testing' => 0, 'is_online' => 1,
        ], $attributes));
    }

    /** One fan reading every 5 minutes, newest $minutesAgo ago. */
    private function readings(Vend $vend, array $values, int $minutesAgo = 0): void
    {
        $at = now()->subMinutes($minutesAgo + 5 * (count($values) - 1));
        foreach ($values as $value) {
            DB::table('vend_fans')->insert([
                'vend_id' => $vend->id, 'value' => $value, 'type' => VendFan::TYPE_MAIN,
                'created_at' => $at, 'updated_at' => $at,
            ]);
            $at = $at->copy()->addMinutes(5);
        }
    }

    /** Runs Machine Health's hourly check for the machine and returns whether 2A is on. */
    private function fanAlarm(Vend $vend): bool
    {
        foreach ([VendTemp::TYPE_CHAMBER => -200, VendTemp::TYPE_EVAPORATOR => -250] as $type => $value) {
            DB::table('vend_temps')->insert([
                'vend_id' => $vend->id, 'type' => $type, 'value' => $value,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
        (new DetectTempTrends($vend->id, false))->handle();

        return (bool) VendSmartAlert::where('vend_id', $vend->id)
            ->where('alert_type', VendSmartAlert::TYPE_COMP_FAN_OFF)
            ->value('is_active');
    }

    public function test_a_new_machine_starts_with_no_fan(): void
    {
        $vend = $this->vend(7001);

        $this->assertFalse($vend->fresh()->is_fan_enabled);
        $this->assertNull($vend->fresh()->fan_detected_at);
    }

    public function test_the_ingest_detects_a_running_fan_once_and_never_undoes_it(): void
    {
        $vend = $this->vend(7002);

        (new SyncVendParameter(['fan' => '600'], $vend))->handle();
        $this->assertFalse($vend->fresh()->is_fan_enabled, 'electrical noise below 1000 is not a fan');

        (new SyncVendParameter(['fan' => '4347'], $vend->fresh()))->handle();
        $this->assertTrue($vend->fresh()->is_fan_enabled);
        $this->assertSame('2026-10-19 15:00:00', $vend->fresh()->fan_detected_at->toDateTimeString());

        Carbon::setTestNow('2026-10-20 09:00:00');
        (new SyncVendParameter(['fan' => '0'], $vend->fresh()))->handle();
        (new SyncVendParameter(['fan' => '4400'], $vend->fresh()))->handle();
        $this->assertTrue($vend->fresh()->is_fan_enabled, 'a fan reading 0 is a broken fan, never "no fan"');
        $this->assertSame('2026-10-19 15:00:00', $vend->fresh()->fan_detected_at->toDateTimeString(), 'stamped once');
    }

    public function test_2a_judges_only_reported_readings(): void
    {
        $dead = $this->vend(7101, ['is_fan_enabled' => true]);
        $this->readings($dead, [4300, 4300, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0]);
        $this->assertTrue($this->fanAlarm($dead), 'reported 0 for over 40 min after running: fan off');

        $noisy = $this->vend(7102, ['is_fan_enabled' => true]);
        $this->readings($noisy, [4300, 400, 650, 380, 500, 420, 610, 390, 450, 520, 480]);
        $this->assertTrue($this->fanAlarm($noisy), 'readings below 1000 are not a running fan');

        $gap = $this->vend(7103, ['is_fan_enabled' => true]);
        $this->readings($gap, [4300, 4300, 4300], 90);
        $this->assertFalse($this->fanAlarm($gap), 'no readings for 90 min is a connection gap, not fan off');

        $running = $this->vend(7104, ['is_fan_enabled' => true]);
        $this->readings($running, [0, 0, 0, 0, 0, 0, 0, 0, 0, 4300]);
        $this->assertFalse($this->fanAlarm($running));

        $fanless = $this->vend(7105);
        $this->readings($fanless, array_fill(0, 20, 0));
        $this->assertFalse($this->fanAlarm($fanless), 'a board that never had a fan raises nothing');
    }

    public function test_a_person_can_only_reset_detection_not_set_it(): void
    {
        $vend = $this->vend(7201, ['is_fan_enabled' => true, 'fan_detected_at' => now()->subDays(3)]);
        $editor = User::factory()->create(['operator_id' => 1]);
        $editor->givePermissionTo(Permission::findOrCreate('update machine-settings', 'web'));

        $this->actingAs(User::factory()->create(['operator_id' => 1]))
            ->postJson("/vends/{$vend->id}/fan-detection/reset")->assertForbidden();
        $this->assertTrue($vend->fresh()->is_fan_enabled);

        $this->actingAs($editor)->postJson("/vends/{$vend->id}/fan-detection/reset")->assertOk();
        $this->assertFalse($vend->fresh()->is_fan_enabled);
        $this->assertNull($vend->fresh()->fan_detected_at);
    }

    public function test_the_backfill_follows_what_each_board_reported(): void
    {
        $withFan = $this->vend(7301, ['is_fan_enabled' => false]);
        $this->readings($withFan, [0, 4300, 4300], 600);
        $fanless = $this->vend(7302, ['is_fan_enabled' => true]);
        $this->readings($fanless, [0, 0, 0]);
        $paramsOnly = $this->vend(7303, ['is_fan_enabled' => true, 'parameter_json' => ['fan' => '4100']]);
        $noisy = $this->vend(7304, ['is_fan_enabled' => true]);
        $this->readings($noisy, [300, 700, 0]);

        $this->artisan('fan:backfill-detection')->assertSuccessful();
        $this->assertFalse($withFan->fresh()->is_fan_enabled, 'a dry run writes nothing');

        $this->artisan('fan:backfill-detection --apply')->assertSuccessful();
        $this->assertTrue($withFan->fresh()->is_fan_enabled);
        $this->assertSame(now()->subMinutes(605)->toDateTimeString(), $withFan->fresh()->fan_detected_at->toDateTimeString(), 'stamped with the first running reading');
        $this->assertFalse($fanless->fresh()->is_fan_enabled);
        $this->assertTrue($paramsOnly->fresh()->is_fan_enabled);
        $this->assertNotNull($paramsOnly->fresh()->fan_detected_at);
        $this->assertFalse($noisy->fresh()->is_fan_enabled);
    }
}
