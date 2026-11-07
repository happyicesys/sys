<?php

namespace Tests\Feature;

use App\Jobs\SyncAcbStatus;
use App\Jobs\SyncOnlineStatus;
use App\Jobs\Vend\SyncVendParameter;
use App\Mail\VendControllerSilentNotificationMail;
use App\Mail\VendTempProbeErrorNotificationMail;
use App\Models\MachineHealthHistory;
use App\Models\Scopes\OperatorVendFilterScope;
use App\Models\Vend;
use App\Models\VendLog;
use App\Models\VendTemp;
use App\Services\AlertEmailService;
use App\Services\MachineHealthDashboardService;
use App\Services\MqttService;
use App\Services\OpsDashboard\CriticalAttention;
use App\Support\TempProbe;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * (1C) Temperature probe error (Brian, 2026-11-07). Machine 2573's T1 probe sent 32767 from 3 Nov
 * 08:08 for almost three days, with a good reading now and then; the machine stayed online, its T2
 * read normally, nothing was stored and no alert fired. Pinned: a probe that read normally and then
 * errors alerts after 30 minutes, a probe never fitted does not, a single good reading does not end
 * the fault, an hour of good readings does, T2 is kept while T1 is in error, and the alert stays out
 * of the way of 1B and the offline alert.
 */
class TempProbeErrorAlertTest extends TestCase
{
    use RefreshDatabase;

    private const OPERATOR_ID = OperatorVendFilterScope::UNRESTRICTED_OPERATOR_ID;

    private const ERR = VendTemp::TEMPERATURE_ERROR;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-03 08:03:00');
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
            'name' => 'Outside Colugo Camp - Zoo', 'profile_id' => 1, 'status_id' => 1, 'is_active' => 1,
            'operator_id' => self::OPERATOR_ID, 'created_at' => now(), 'updated_at' => now(),
        ]);

        return DB::table('vends')->insertGetId(array_merge([
            'code' => 2573, 'name' => 'Machine', 'machine_type' => Vend::MACHINE_TYPE_VENDING_MACHINE,
            'operator_id' => self::OPERATOR_ID, 'customer_id' => $customerId, 'is_active' => 1, 'is_testing' => 0,
            'is_online' => 1, 'is_mqtt' => 1, 'temp' => -313,
            'mqtt_last_updated_at' => now(), 'last_updated_at' => now(), 'temp_updated_at' => now(),
            'created_at' => now(), 'updated_at' => now(),
        ], $attributes));
    }

    /** One VENDER frame, five minutes after the last (the controller is heard each time). */
    private function frame(int $id, mixed $t1, mixed $t2 = -124, int $minutes = 5): void
    {
        Carbon::setTestNow(now()->addMinutes($minutes));
        DB::table('vends')->where('id', $id)->update(['last_updated_at' => now(), 'mqtt_last_updated_at' => now()]);
        $input = ['Type' => 'VENDER', 'TEMP' => $t1, 'fan' => 4411, 't3' => self::ERR, 't4' => self::ERR];
        if ($t2 !== null) {
            $input['t2'] = $t2;
        }
        (new SyncVendParameter($input, Vend::withoutGlobalScopes()->find($id)))->handle();
    }

    private function run_job(): void
    {
        (new SyncOnlineStatus)->handle($this->mock(MqttService::class), app(AlertEmailService::class));
    }

    private function row(int $id): object
    {
        return DB::table('vends')->where('id', $id)->first();
    }

    /** A normal frame, then $count error frames 5 minutes apart. */
    private function breakT1(int $id, int $count): void
    {
        $this->frame($id, -313, -124, 0);
        for ($i = 0; $i < $count; $i++) {
            $this->frame($id, self::ERR, -123 - $i);
        }
    }

    public function test_the_2573_shape_alerts_at_thirty_minutes(): void
    {
        $id = $this->vend();
        $this->breakT1($id, 6); // errors from 08:08 to 08:33

        $this->assertSame('2026-11-03 08:08:00', $this->row($id)->temp_probe_error_since);
        $this->assertSame('T1', $this->row($id)->temp_probe_error_probes);
        $this->run_job();
        Mail::assertNothingQueued(); // 25 minutes

        $this->frame($id, self::ERR, -130); // 08:38
        $this->run_job();

        Mail::assertQueued(VendTempProbeErrorNotificationMail::class, fn ($mail) => $mail->hasTo('ops@example.test') && $mail->label === '< 1hr');
        Mail::assertNotQueued(VendControllerSilentNotificationMail::class);
        $this->assertSame(1, (int) $this->row($id)->temp_probe_error_level);
        $this->assertSame('Temperature probe error: T1 (< 1hr)', VendLog::where('vend_id', $id)->where('context->alert_type', TempProbe::ALERT_TYPE)->value('subject'));
        $this->assertSame(1, MachineHealthHistory::where('vend_id', $id)->where('alert_type', TempProbe::ALERT_TYPE)->count());
    }

    public function test_t2_is_kept_while_t1_is_in_error(): void
    {
        $id = $this->vend();
        $this->breakT1($id, 2);

        $t2 = DB::table('vend_temps')->where('vend_id', $id)->where('type', VendTemp::TYPE_EVAPORATOR)->orderBy('id')->pluck('value')->all();
        $this->assertSame([-124, -123, -124], array_map('intval', $t2));
        $this->assertSame(1, DB::table('vend_temps')->where('vend_id', $id)->where('type', VendTemp::TYPE_CHAMBER)->count());
        $this->assertSame(-313, (int) $this->row($id)->temp); // the last good T1 is kept
        $this->assertSame(1, (int) $this->row($id)->is_temp_error);
    }

    public function test_a_probe_that_was_never_fitted_never_alerts(): void
    {
        $id = $this->vend();
        // t3 / t4 read 32767 in every frame; T2 absent from the board.
        for ($i = 0; $i < 12; $i++) {
            $this->frame($id, -313, null);
        }
        $this->run_job();

        $this->assertNull($this->row($id)->temp_probe_error_since);
        Mail::assertNothingQueued();
    }

    public function test_a_probe_in_error_since_before_tracking_does_not_start_one(): void
    {
        $id = $this->vend(['parameter_json' => json_encode(['TEMP' => self::ERR, 't2' => -124])]);
        for ($i = 0; $i < 10; $i++) {
            $this->frame($id, self::ERR);
        }

        $this->assertNull($this->row($id)->temp_probe_error_since);
    }

    public function test_one_good_reading_does_not_end_the_fault(): void
    {
        $id = $this->vend();
        $this->breakT1($id, 4); // 08:08-08:23
        $this->frame($id, -268); // 08:28, one good reading (2573 on 7 Nov 00:45)
        $this->assertNotNull($this->row($id)->temp_probe_ok_since);
        $this->run_job();
        Mail::assertNothingQueued(); // not in error right now

        $this->frame($id, self::ERR); // 08:33
        $this->frame($id, self::ERR); // 08:38
        $this->run_job();

        $this->assertSame('2026-11-03 08:08:00', $this->row($id)->temp_probe_error_since);
        Mail::assertQueued(VendTempProbeErrorNotificationMail::class, 1);
    }

    public function test_a_single_glitch_never_alerts_and_ends_after_an_hour(): void
    {
        $id = $this->vend();
        $this->breakT1($id, 1); // 08:08
        for ($i = 0; $i < 13; $i++) {
            $this->frame($id, -313); // 08:13 ... 09:13, an hour after the first good one
            $this->run_job();
        }

        Mail::assertNothingQueued();
        $this->assertNull($this->row($id)->temp_probe_error_since);
        $this->assertNull($this->row($id)->temp_probe_error_probes);
        $this->assertSame(0, VendLog::where('vend_id', $id)->count());
    }

    public function test_buckets_rise_and_it_clears_after_an_hour_of_good_readings(): void
    {
        $id = $this->vend();
        $this->breakT1($id, 7);
        $this->run_job();
        for ($i = 0; $i < 12; $i++) {
            $this->frame($id, self::ERR); // to 09:38
        }
        $this->run_job();

        $this->assertSame(2, (int) $this->row($id)->temp_probe_error_level);
        $this->assertSame(['< 2hr'], MachineHealthHistory::where('vend_id', $id)->where('event', 'machine_health_alert')->pluck('bucket')->all());
        Mail::assertQueued(VendTempProbeErrorNotificationMail::class, 2);

        for ($i = 0; $i < 13; $i++) {
            $this->frame($id, -220); // an hour of good T1
        }
        $this->run_job();

        $this->assertSame(0, (int) $this->row($id)->temp_probe_error_level);
        $this->assertSame('Temperature probe reading again (was < 2hr)', VendLog::where('vend_id', $id)->where('event', 'machine_health_alert_dismissed')->value('subject'));
    }

    public function test_a_t2_probe_failing_alerts_too(): void
    {
        $id = $this->vend();
        $this->frame($id, -213, -240, 0);
        for ($i = 0; $i < 7; $i++) {
            $this->frame($id, -213, self::ERR);
        }
        $this->run_job();

        $this->assertSame('T2', $this->row($id)->temp_probe_error_probes);
        Mail::assertQueued(VendTempProbeErrorNotificationMail::class);
        $this->assertSame(0, (int) $this->row($id)->is_temp_error); // T1 fine
    }

    public function test_a_silent_controller_is_1b_not_1c(): void
    {
        $id = $this->vend();
        $this->breakT1($id, 3);
        // Then the controller stops: no frames for 40 minutes, HTTP still arriving.
        Carbon::setTestNow(now()->addMinutes(40));
        DB::table('vends')->where('id', $id)->update(['last_updated_at' => now(), 'mqtt_last_updated_at' => now(), 'temp_updated_at' => now()->subMinutes(40)]);
        $this->run_job();

        Mail::assertQueued(VendControllerSilentNotificationMail::class);
        Mail::assertNotQueued(VendTempProbeErrorNotificationMail::class);
    }

    public function test_smart_freezers_are_not_tracked(): void
    {
        $id = $this->vend(['code' => 50002, 'machine_type' => Vend::MACHINE_TYPE_SMART_FREEZER]);
        $this->breakT1($id, 8);
        $this->run_job();

        $this->assertNull($this->row($id)->temp_probe_error_since);
        Mail::assertNotQueued(VendTempProbeErrorNotificationMail::class);
    }

    public function test_acb_frames_are_tracked_and_keep_t2(): void
    {
        $id = $this->vend();
        $acb = function (mixed $t1) use ($id) {
            Carbon::setTestNow(now()->addMinutes(3));
            (new SyncAcbStatus(['t1' => $t1, 't2' => -150], Vend::withoutGlobalScopes()->find($id)))->handle();
        };
        $acb(-200);
        $acb(self::ERR);

        $this->assertNotNull($this->row($id)->temp_probe_error_since);
        $this->assertSame(2, DB::table('vend_temps')->where('vend_id', $id)->where('type', VendTemp::TYPE_EVAPORATOR)->count());
    }

    public function test_the_switch_stops_the_email_but_not_the_report(): void
    {
        config(['vend.attention.temp_probe_error_email' => false]);
        $id = $this->vend();
        $this->breakT1($id, 7);
        $this->run_job();

        Mail::assertNotQueued(VendTempProbeErrorNotificationMail::class);
        $this->assertSame(1, VendLog::where('vend_id', $id)->where('context->alert_type', TempProbe::ALERT_TYPE)->count());
    }

    public function test_the_email_renders(): void
    {
        $id = $this->vend();
        $this->breakT1($id, 7);

        $html = (new VendTempProbeErrorNotificationMail($id, '< 1hr'))->render();

        $this->assertStringContainsString('Temperature Probe Error (T1)', $html);
        $this->assertStringContainsString('T2 now: -12.9°C', $html);
        $this->assertStringContainsString('2026-11-03 08:08:00', $html);
    }

    public function test_needs_attention_and_machine_health_list_it(): void
    {
        $id = $this->vend(['last_vend_transaction_at' => now()]);
        $this->breakT1($id, 18); // 90 minutes
        $other = $this->vend(['code' => 2574]);

        $groups = collect(app(CriticalAttention::class)->forVendIds([$id, $other])['groups'])->keyBy('key');
        $machines = $groups[CriticalAttention::GROUP_TEMP_PROBE]['machines'];
        $this->assertSame([$id], array_column($machines, 'vend_id'));
        $this->assertSame('T1 probe error 1.4h', $machines[0]['detail']);

        $data = app(MachineHealthDashboardService::class)->getDashboardData(Request::create('/'));
        $buckets = collect($data['temp_probe_error']['buckets'])->keyBy('label');
        $this->assertSame([$id], array_column($buckets['< 2hr']['rows'], 'vend_id'));
        $this->assertSame('T1', $buckets['< 2hr']['rows'][0]['probes']);
        $this->assertSame(-14.0, $buckets['< 2hr']['rows'][0]['t2']);
    }
}
