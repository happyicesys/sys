<?php

namespace Tests\Feature;

use App\Jobs\SyncP;
use App\Models\Vend;
use App\Services\OpsDashboard\CriticalAttention;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * "Needs attention" states the real cause of a restart (Brian, 2026-10-18: 2525 showed "APK keeps
 * restarting (crash / reboot)" 21 times a day, every one the board's own no-internet reboot, and
 * "reboot means connection issue"). Pinned: a heartbeat books the rise of the board's offline-reboot
 * counter as `offline_reboot`, the "APK reboot (connection issue)" card lists only those — a restart
 * with no offline reboot behind it (crash, power cut, reload) is not shown until crashes can be
 * told apart — and the one-off backfill estimates today and yesterday from the running total.
 */
class OpsDashboardRestartCauseTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-18 22:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function vend(int $code, int $offlineCount = 0, ?string $since = '2026-10-17 21:32:00'): Vend
    {
        $customerId = DB::table('customers')->insertGetId([
            'name' => "Site {$code}", 'profile_id' => 1, 'status_id' => 1, 'is_active' => 1,
            'operator_id' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);

        return Vend::forceCreate([
            'code' => $code, 'name' => "Machine {$code}", 'machine_type' => Vend::MACHINE_TYPE_VENDING_MACHINE,
            'operator_id' => 1, 'customer_id' => $customerId, 'is_active' => 1, 'is_testing' => 0,
            'is_online' => 1, 'mqtt_last_updated_at' => now(), 'last_vend_transaction_at' => now()->subHour(),
            'offline_restart_count' => $offlineCount, 'offline_restart_count_datetime' => $since,
        ]);
    }

    private function stat(Vend $vend, string $metric, int $count, string $date = '2026-10-18'): void
    {
        DB::table('vend_daily_stats')->insert([
            'vend_id' => $vend->id, 'vend_code' => (string) $vend->code, 'date' => $date, 'metric' => $metric,
            'count' => $count, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function statCount(Vend $vend, string $date = '2026-10-18'): ?int
    {
        $count = DB::table('vend_daily_stats')->where('vend_id', $vend->id)->where('date', $date)
            ->where('metric', SyncP::METRIC_OFFLINE_REBOOT)->value('count');

        return $count === null ? null : (int) $count;
    }

    /** @return array<string, array<string, string>> group key => (machine => detail) */
    private function attention(array $vendIds): array
    {
        return collect(app(CriticalAttention::class)->forVendIds($vendIds)['groups'])
            ->mapWithKeys(fn ($g) => [$g['key'] => collect($g['machines'])->pluck('detail', 'code_label')->all()])
            ->all();
    }

    public function test_a_heartbeat_books_the_rise_of_the_offline_reboot_counter(): void
    {
        $vend = $this->vend(2525, 20);

        (new SyncP(['OfflineRestartCount' => 23, 'OfflineRestartCountDatetime' => '2026-10-17 21:32'], $vend))->handle();
        (new SyncP(['OfflineRestartCount' => 23, 'OfflineRestartCountDatetime' => '2026-10-17 21:32'], $vend))->handle();
        (new SyncP(['OfflineRestartCount' => 26, 'OfflineRestartCountDatetime' => '2026-10-17 21:32'], $vend))->handle();

        $this->assertSame(6, $this->statCount($vend), '3 + 0 + 3: a repeated heartbeat adds nothing');
        $this->assertSame(26, (int) $vend->fresh()->offline_restart_count);
    }

    public function test_a_new_install_counts_its_reboots_from_zero(): void
    {
        $vend = $this->vend(2526, 26);

        (new SyncP(['OfflineRestartCount' => 2, 'OfflineRestartCountDatetime' => '2026-10-18 09:00'], $vend))->handle();

        $this->assertSame(2, $this->statCount($vend));
    }

    public function test_a_heartbeat_without_the_counter_books_nothing(): void
    {
        $vend = $this->vend(2527, 5);

        (new SyncP(['Type' => 'P'], $vend))->handle();

        $this->assertNull($this->statCount($vend));
        $this->assertSame(5, (int) $vend->fresh()->offline_restart_count);
    }

    public function test_the_card_lists_connection_reboots_only(): void
    {
        $weakInternet = $this->vend(2525);
        $crashing = $this->vend(2320);
        $few = $this->vend(2031);

        $this->stat($weakInternet, 'apk_restart', 21);
        $this->stat($weakInternet, SyncP::METRIC_OFFLINE_REBOOT, 21);
        $this->stat($crashing, 'apk_restart', 9);
        $this->stat($few, SyncP::METRIC_OFFLINE_REBOOT, 4);

        $groups = $this->attention([$weakInternet->id, $crashing->id, $few->id]);

        $this->assertSame(['2525' => '21 reboots in a day (no internet for 6 min)'], $groups[CriticalAttention::GROUP_APK_RESTART],
            'restarts with no offline reboot behind them are not a connection issue and are not listed');
    }

    public function test_the_worst_of_today_and_yesterday_decides(): void
    {
        $vend = $this->vend(2525);
        $this->stat($vend, SyncP::METRIC_OFFLINE_REBOOT, 8, '2026-10-17');
        $this->stat($vend, SyncP::METRIC_OFFLINE_REBOOT, 3);
        $old = $this->vend(2526);
        $this->stat($old, SyncP::METRIC_OFFLINE_REBOOT, 9, '2026-10-16');

        $groups = $this->attention([$vend->id, $old->id]);

        $this->assertSame(['2525' => '8 reboots in a day (no internet for 6 min)'], $groups[CriticalAttention::GROUP_APK_RESTART]);
    }

    public function test_the_backfill_estimates_today_and_yesterday_from_the_running_total(): void
    {
        $sinceYesterday = $this->vend(2525, 26, '2026-10-17 21:32:00');
        $this->stat($sinceYesterday, 'apk_restart', 3, '2026-10-17');
        $this->stat($sinceYesterday, 'apk_restart', 21);
        $this->stat($sinceYesterday, SyncP::METRIC_OFFLINE_REBOOT, 1); // SyncP, after the deploy

        $sinceToday = $this->vend(2296, 4, '2026-10-18 17:47:00');
        $this->stat($sinceToday, 'apk_restart', 5, '2026-10-17');
        $this->stat($sinceToday, 'apk_restart', 5);

        $noCounter = $this->vend(2320, 0, null);
        $this->stat($noCounter, 'apk_restart', 9);

        $this->artisan('ops-attention:backfill-offline-reboots')->assertSuccessful();
        $this->assertSame(1, $this->statCount($sinceYesterday), 'a dry run writes nothing');

        $this->artisan('ops-attention:backfill-offline-reboots', ['--apply' => true])->assertSuccessful();

        $this->assertSame(21, $this->statCount($sinceYesterday));
        $this->assertSame(3, $this->statCount($sinceYesterday, '2026-10-17'));
        $this->assertSame(4, $this->statCount($sinceToday), 'never more than the running total');
        $this->assertNull($this->statCount($sinceToday, '2026-10-17'), 'the total only covers today');
        $this->assertNull($this->statCount($noCounter));
    }
}
