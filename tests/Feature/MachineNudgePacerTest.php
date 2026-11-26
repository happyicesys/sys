<?php

namespace Tests\Feature;

use App\Jobs\PublishMqtt;
use App\Jobs\Vend\SendMachineNudge;
use App\Models\Vend;
use App\Services\Vend\MachineNudgePacer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * 2026-11-26 outage: a fan-out told ~224 boards to re-read their menu in the same second. Fleet-wide frames
 * now queue on one shared clock and are de-duplicated per machine.
 */
class MachineNudgePacerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-26 11:00:00');
        config(['vend.nudge_pace.units_per_second' => 10, 'vend.nudge_pace.min_delay_seconds' => 1]);
    }

    /** @return array<int, int> vend id => seconds from now */
    private function delays(): array
    {
        $out = [];
        Queue::assertPushed(SendMachineNudge::class, function (SendMachineNudge $job) use (&$out) {
            $out[$job->vendId] = (int) now()->diffInSeconds($job->delay);

            return true;
        });

        return $out;
    }

    public function test_heavy_frames_go_two_a_second_not_all_at_once(): void
    {
        Queue::fake([SendMachineNudge::class]);

        $queued = app(MachineNudgePacer::class)->schedule(range(1, 6), MachineNudgePacer::MENU);

        $this->assertSame(6, $queued);
        $this->assertSame([1 => 1, 2 => 1, 3 => 2, 4 => 2, 5 => 3, 6 => 3], $this->delays());
    }

    public function test_light_frames_go_ten_a_second(): void
    {
        Queue::fake([SendMachineNudge::class]);

        app(MachineNudgePacer::class)->schedule(range(1, 12), MachineNudgePacer::MENU_LIGHT);

        $delays = $this->delays();
        $this->assertSame(10, count(array_filter($delays, fn ($d) => $d === 1)));
        $this->assertSame(2, count(array_filter($delays, fn ($d) => $d === 2)));
    }

    public function test_two_senders_share_one_clock(): void
    {
        Queue::fake([SendMachineNudge::class]);
        $pacer = app(MachineNudgePacer::class);

        $pacer->schedule([1, 2], MachineNudgePacer::MENU);      // fills second +1
        $pacer->schedule([3], MachineNudgePacer::SETTINGS);     // must wait for +2

        $this->assertSame([1 => 1, 2 => 1, 3 => 2], $this->delays());
    }

    public function test_a_waiting_frame_swallows_the_same_request_and_a_full_menu_covers_a_light_one(): void
    {
        Queue::fake([SendMachineNudge::class]);
        $pacer = app(MachineNudgePacer::class);

        $this->assertSame(1, $pacer->schedule([7], MachineNudgePacer::MENU));
        $this->assertSame(0, $pacer->schedule([7], MachineNudgePacer::MENU));
        $this->assertSame(0, $pacer->schedule([7], MachineNudgePacer::MENU_LIGHT));
        $this->assertSame(1, $pacer->schedule([7], MachineNudgePacer::SETTINGS));

        Queue::assertPushed(SendMachineNudge::class, 2);
    }

    public function test_the_job_sends_the_frame_and_frees_the_machine_for_the_next_change(): void
    {
        Queue::fake([SendMachineNudge::class, PublishMqtt::class]);
        $vend = Vend::forceCreate(['code' => 9821, 'machine_type' => Vend::MACHINE_TYPE_VENDING_MACHINE]);
        $pacer = app(MachineNudgePacer::class);
        $pacer->schedule([$vend->id], MachineNudgePacer::MENU);

        (new SendMachineNudge($vend->id, MachineNudgePacer::MENU))->handle(app(\App\Services\VendJobService::class));

        Queue::assertPushed(PublishMqtt::class, 1);
        $this->assertFalse(Cache::has(MachineNudgePacer::pendingKey($vend->id, MachineNudgePacer::MENU)));
        $this->assertSame(1, $pacer->schedule([$vend->id], MachineNudgePacer::MENU));
    }

    public function test_pacing_off_sends_after_the_minimum_delay(): void
    {
        config(['vend.nudge_pace.units_per_second' => 0]);
        Queue::fake([SendMachineNudge::class]);

        app(MachineNudgePacer::class)->schedule(range(1, 5), MachineNudgePacer::MENU);

        $this->assertSame([1, 1, 1, 1, 1], array_values($this->delays()));
    }
}
