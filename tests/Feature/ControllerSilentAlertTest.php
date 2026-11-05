<?php

namespace Tests\Feature;

use App\Jobs\SyncOnlineStatus;
use App\Mail\VendControllerSilentNotificationMail;
use App\Mail\VendOfflineNotificationMail;
use App\Models\MachineHealthHistory;
use App\Models\Scopes\OperatorVendFilterScope;
use App\Models\Vend;
use App\Models\VendLog;
use App\Services\AlertEmailService;
use App\Services\MachineHealthDashboardService;
use App\Services\MqttService;
use App\Services\OpsDashboard\CriticalAttention;
use App\Support\ControllerLink;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * (1B) Controller not responding (Brian, 2026-11-05). Machine 2351's controller went silent for 41 h
 * while its Android board stayed connected: the offline alert saw a live machine and the stock
 * melted. Pinned: that shape alerts at 30 minutes, an MQTT-only machine is not blamed on the
 * controller, a fully offline machine stays the offline alert's, buckets rise like the offline
 * alert's, and a controller that keeps dropping out does not email once per drop.
 */
class ControllerSilentAlertTest extends TestCase
{
    use RefreshDatabase;

    private const OPERATOR_ID = OperatorVendFilterScope::UNRESTRICTED_OPERATOR_ID;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-02 16:00:00');
        Mail::fake();

        DB::table('operators')->insert([
            'id' => self::OPERATOR_ID, 'code' => 'HIPL', 'name' => 'HIPL', 'is_active' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('alert_email_items')->insert([
            'email' => 'ops@example.test', 'operator_id' => self::OPERATOR_ID, 'is_active' => 1,
            'is_send_offline_notification' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function vend(array $attributes = []): int
    {
        $customerId = DB::table('customers')->insertGetId([
            'name' => 'Orchard Scotts Condo', 'profile_id' => 1, 'status_id' => 1, 'is_active' => 1,
            'operator_id' => self::OPERATOR_ID, 'created_at' => now(), 'updated_at' => now(),
        ]);

        return DB::table('vends')->insertGetId(array_merge([
            'code' => 2351, 'name' => 'Machine', 'machine_type' => Vend::MACHINE_TYPE_VENDING_MACHINE,
            'operator_id' => self::OPERATOR_ID, 'customer_id' => $customerId, 'is_active' => 1, 'is_testing' => 0,
            'is_online' => 1, 'is_mqtt' => 1, 'temp' => -213,
            'mqtt_last_updated_at' => now(), 'last_updated_at' => now(), 'temp_updated_at' => now(),
            'created_at' => now(), 'updated_at' => now(),
        ], $attributes));
    }

    private function run_job(): void
    {
        (new SyncOnlineStatus)->handle($this->mock(MqttService::class), app(AlertEmailService::class));
    }

    public function test_the_2351_shape_alerts_at_thirty_minutes(): void
    {
        $id = $this->vend(['temp_updated_at' => now()->subMinutes(31)]);

        $this->run_job();

        Mail::assertQueued(VendControllerSilentNotificationMail::class, fn ($mail) => $mail->hasTo('ops@example.test')
            && $mail->state === ControllerLink::STATE_SILENT && $mail->label === '< 1hr');
        Mail::assertNotQueued(VendOfflineNotificationMail::class);
        $this->assertSame(1, (int) DB::table('vends')->where('id', $id)->value('controller_silent_level'));
        $this->assertSame('Controller not responding (< 1hr)', VendLog::where('vend_id', $id)->value('subject'));
        $this->assertSame(1, MachineHealthHistory::where('vend_id', $id)->where('alert_type', 'controller_silent')->count());
    }

    public function test_a_silence_shorter_than_the_limit_is_ignored(): void
    {
        $id = $this->vend(['temp_updated_at' => now()->subMinutes(29)]);

        $this->run_job();

        Mail::assertNothingQueued();
        $this->assertSame(0, VendLog::where('vend_id', $id)->count());
    }

    public function test_the_limit_comes_from_config(): void
    {
        config(['vend.attention.controller_silent_minutes' => 60]);
        $this->vend(['temp_updated_at' => now()->subMinutes(45)]);

        $this->run_job();

        Mail::assertNotQueued(VendControllerSilentNotificationMail::class);
    }

    public function test_mqtt_only_is_not_blamed_on_the_controller(): void
    {
        $id = $this->vend(['temp_updated_at' => now()->subMinutes(40), 'last_updated_at' => now()->subMinutes(40)]);

        $this->run_job();

        Mail::assertQueued(VendControllerSilentNotificationMail::class, fn ($mail) => $mail->state === ControllerLink::STATE_NO_DATA);
        $this->assertStringContainsString('Machine data not arriving', VendLog::where('vend_id', $id)->value('subject'));
    }

    public function test_a_fully_offline_machine_stays_the_offline_alerts(): void
    {
        $id = $this->vend([
            'temp_updated_at' => now()->subHours(2), 'last_updated_at' => now()->subHours(2), 'mqtt_last_updated_at' => now()->subHours(2),
        ]);

        $this->run_job();

        Mail::assertQueued(VendOfflineNotificationMail::class);
        Mail::assertNotQueued(VendControllerSilentNotificationMail::class);
        $this->assertSame(0, (int) DB::table('vends')->where('id', $id)->value('controller_silent_level'));
    }

    public function test_only_vending_machines_in_service_are_checked(): void
    {
        $stale = ['temp_updated_at' => now()->subHours(3)];
        $this->vend($stale + ['code' => 50001, 'machine_type' => Vend::MACHINE_TYPE_SMART_FREEZER]);
        $this->vend($stale + ['code' => 2031, 'is_testing' => 1]);
        $this->vend($stale + ['code' => 2032, 'is_active' => 0]);

        $this->run_job();

        Mail::assertNotQueued(VendControllerSilentNotificationMail::class);
    }

    public function test_a_machine_that_never_reported_is_not_silent(): void
    {
        $this->vend(['temp_updated_at' => null]);

        $this->run_job();

        Mail::assertNotQueued(VendControllerSilentNotificationMail::class);
    }

    public function test_buckets_rise_and_only_the_highest_stays(): void
    {
        $id = $this->vend(['temp_updated_at' => now()->subMinutes(31)]);
        $this->run_job();

        Carbon::setTestNow(now()->addMinutes(40));
        DB::table('vends')->where('id', $id)->update(['last_updated_at' => now(), 'mqtt_last_updated_at' => now()]);
        $this->run_job();
        $this->run_job();

        Mail::assertQueued(VendControllerSilentNotificationMail::class, 2);
        $this->assertSame(2, (int) DB::table('vends')->where('id', $id)->value('controller_silent_level'));
        $this->assertSame(['< 2hr'], MachineHealthHistory::where('vend_id', $id)->pluck('bucket')->all());
    }

    public function test_it_clears_only_when_the_controller_is_heard_again(): void
    {
        $id = $this->vend(['temp_updated_at' => now()->subMinutes(31)]);
        $this->run_job();

        // Now fully offline as well: the alert stays.
        Carbon::setTestNow(now()->addMinutes(20));
        DB::table('vends')->where('id', $id)->update(['last_updated_at' => now()->subMinutes(20), 'mqtt_last_updated_at' => now()->subMinutes(20)]);
        $this->run_job();
        $this->assertSame(1, (int) DB::table('vends')->where('id', $id)->value('controller_silent_level'));

        DB::table('vends')->where('id', $id)->update(['temp_updated_at' => now(), 'last_updated_at' => now(), 'mqtt_last_updated_at' => now()]);
        $this->run_job();

        $this->assertSame(0, (int) DB::table('vends')->where('id', $id)->value('controller_silent_level'));
        $this->assertSame('Controller responding (was < 1hr)', VendLog::where('vend_id', $id)->where('event', 'machine_health_alert_dismissed')->value('subject'));
    }

    public function test_a_controller_that_keeps_dropping_out_does_not_email_per_drop(): void
    {
        $id = $this->vend(['temp_updated_at' => now()->subMinutes(31)]);
        $this->run_job();

        Carbon::setTestNow(now()->addMinutes(2));
        DB::table('vends')->where('id', $id)->update(['temp_updated_at' => now(), 'last_updated_at' => now(), 'mqtt_last_updated_at' => now()]);
        $this->run_job();

        Carbon::setTestNow(now()->addMinutes(31));
        DB::table('vends')->where('id', $id)->update(['last_updated_at' => now(), 'mqtt_last_updated_at' => now()]);
        $this->run_job();

        Mail::assertQueued(VendControllerSilentNotificationMail::class, 1);
        $this->assertSame(1, (int) DB::table('vends')->where('id', $id)->value('controller_silent_level'));

        // Past the cooldown a new silence emails again.
        Carbon::setTestNow(now()->addMinutes(2));
        DB::table('vends')->where('id', $id)->update(['temp_updated_at' => now(), 'last_updated_at' => now(), 'mqtt_last_updated_at' => now()]);
        $this->run_job();
        Carbon::setTestNow(now()->addMinutes(60));
        DB::table('vends')->where('id', $id)->update(['temp_updated_at' => now()->subMinutes(31), 'last_updated_at' => now(), 'mqtt_last_updated_at' => now()]);
        $this->run_job();

        Mail::assertQueued(VendControllerSilentNotificationMail::class, 2);
    }

    public function test_the_switch_stops_the_email_but_not_the_report(): void
    {
        config(['vend.attention.controller_silent_email' => false]);
        $id = $this->vend(['temp_updated_at' => now()->subMinutes(31)]);

        $this->run_job();

        Mail::assertNotQueued(VendControllerSilentNotificationMail::class);
        $this->assertSame(1, VendLog::where('vend_id', $id)->count());
    }

    public function test_a_failed_send_does_not_log_again_every_minute(): void
    {
        $id = $this->vend(['temp_updated_at' => now()->subMinutes(31)]);
        $emails = $this->mock(AlertEmailService::class);
        $emails->shouldReceive('sendVendControllerSilentNotificationMail')->once()->andThrow(new \RuntimeException('mail down'));

        (new SyncOnlineStatus)->handle($this->mock(MqttService::class), $emails);
        (new SyncOnlineStatus)->handle($this->mock(MqttService::class), $emails);

        $this->assertSame(1, (int) DB::table('vends')->where('id', $id)->value('controller_silent_level'));
        $this->assertSame(1, VendLog::where('vend_id', $id)->count());
    }

    public function test_a_machine_with_no_site_is_not_in_the_report(): void
    {
        $this->vend(['temp_updated_at' => now()->subHours(3), 'customer_id' => null]);

        $data = app(MachineHealthDashboardService::class)->getDashboardData(Request::create('/'));

        $this->assertSame([], collect($data['controller_silent']['buckets'])->flatMap(fn ($b) => $b['rows'])->all());
    }

    public function test_the_email_renders(): void
    {
        $id = $this->vend(['temp_updated_at' => now()->subMinutes(31)]);

        $html = (new VendControllerSilentNotificationMail($id, '< 1hr', ControllerLink::STATE_SILENT))->render();

        $this->assertStringContainsString('Controller Not Responding', $html);
        $this->assertStringContainsString('-21.3°C', $html);
    }

    public function test_needs_attention_lists_it(): void
    {
        $id = $this->vend(['temp_updated_at' => now()->subMinutes(90), 'last_vend_transaction_at' => now()]);
        $other = $this->vend(['code' => 2352]);

        $groups = collect(app(CriticalAttention::class)->forVendIds([$id, $other])['groups'])->keyBy('key');

        $machines = $groups[CriticalAttention::GROUP_CONTROLLER_SILENT]['machines'];
        $this->assertSame([$id], array_column($machines, 'vend_id'));
        $this->assertSame('silent 1.5h', $machines[0]['detail']);
        $this->assertSame(0, $groups[CriticalAttention::GROUP_OFFLINE]['count']);
    }

    public function test_machine_health_has_its_own_section(): void
    {
        $id = $this->vend(['temp_updated_at' => now()->subHours(3)]);
        $this->vend(['code' => 2352]);
        $this->vend(['code' => 2353, 'temp_updated_at' => now()->subHours(3), 'is_active' => 0]);

        $data = app(MachineHealthDashboardService::class)->getDashboardData(Request::create('/'));

        $buckets = collect($data['controller_silent']['buckets'])->keyBy('label');
        $this->assertSame(['< 1hr', '< 2hr', '< 4hr', '< 8hr', '< 12hr', '> 12hr'], $buckets->keys()->all());
        $this->assertSame([$id], array_column($buckets['< 4hr']['rows'], 'vend_id'));
        $this->assertSame(-21.3, $buckets['< 4hr']['rows'][0]['temp']);
    }

    public function test_bucket_edges(): void
    {
        $this->assertSame([1, '< 1hr'], ControllerLink::bucket(0.5));
        $this->assertSame([2, '< 2hr'], ControllerLink::bucket(1));
        $this->assertSame([3, '< 4hr'], ControllerLink::bucket(2));
        $this->assertSame([4, '< 8hr'], ControllerLink::bucket(4));
        $this->assertSame([5, '< 12hr'], ControllerLink::bucket(8));
        $this->assertSame([6, '> 12hr'], ControllerLink::bucket(41));
    }
}
