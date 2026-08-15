<?php

namespace Tests\Feature;

use App\Jobs\SyncP;
use App\Models\Vend;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The Type "P" heartbeat must never erase `vends.offline_restart_count`.
 *
 * The bug: `OfflineRestartCount` was only sent by the APK when debug mode was
 * on, so 462 of 464 machines omitted it. VendDataService coalesced the missing
 * key to 0, compared that against the real stored value, saw a difference and
 * dispatched SyncP — which wrote the 0. Every reporting machine wiped its own
 * counter within one 5-minute heartbeat, leaving the column permanently ~0 and
 * useless for judging whether the offline auto-reboot helps.
 *
 * An absent key means "this heartbeat is not reporting the counter", never
 * "the counter is zero".
 */
class SyncPOfflineRestartCountTest extends TestCase
{
    use RefreshDatabase;

    private function vendWithCount(int $code, int $count): Vend
    {
        return Vend::forceCreate([
            'code' => $code,
            'offline_restart_count' => $count,
            'offline_restart_count_datetime' => '2026-08-01 10:00:00',
        ]);
    }

    public function test_heartbeat_without_the_key_does_not_zero_a_real_count(): void
    {
        $vend = $this->vendWithCount(9601, 7);

        // A debug-mode-off heartbeat: Type P and nothing else.
        (new SyncP(['Type' => 'P'], $vend))->handle();

        $this->assertSame(7, (int) $vend->fresh()->offline_restart_count);
        $this->assertNotNull($vend->fresh()->offline_restart_count_datetime);
    }

    public function test_heartbeat_with_the_key_still_updates(): void
    {
        $vend = $this->vendWithCount(9602, 7);

        (new SyncP([
            'Type' => 'P',
            'OfflineRestartCount' => 9,
            'OfflineRestartCountDatetime' => '2026-08-14 12:00:00',
        ], $vend))->handle();

        $this->assertSame(9, (int) $vend->fresh()->offline_restart_count);
    }

    public function test_a_genuine_zero_is_still_honoured(): void
    {
        $vend = $this->vendWithCount(9603, 7);

        // A machine that really did reset its counter reports 0 explicitly.
        (new SyncP(['Type' => 'P', 'OfflineRestartCount' => 0], $vend))->handle();

        $this->assertSame(0, (int) $vend->fresh()->offline_restart_count);
    }
}
