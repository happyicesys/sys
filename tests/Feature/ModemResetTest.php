<?php

namespace Tests\Feature;

use App\Jobs\PublishMqtt;
use App\Jobs\SyncOnlineStatus;
use App\Models\Scopes\OperatorVendFilterScope;
use App\Models\Vend;
use App\Models\VendLog;
use App\Services\AlertEmailService;
use App\Services\MqttService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use PhpMqtt\Client\MqttClient;
use Tests\TestCase;

/**
 * External modem reset (Brian, 2026-11-07; App\Support\ModemReset). The old rule reset an Air724 modem every
 * minute once HTTP was 5 minutes old, which turned one late packet into a 20-30 minute outage on 52 of 55
 * machines daily. Pinned: no reset while MQTT is up or HTTP is under 10 minutes old, none when the modem is
 * itself unreachable, at most one per 30 minutes, QoS 0, and each one logged.
 */
class ModemResetTest extends TestCase
{
    use RefreshDatabase;

    private const OPERATOR_ID = OperatorVendFilterScope::UNRESTRICTED_OPERATOR_ID;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-06 01:11:00');
        Mail::fake();
        Queue::fake([PublishMqtt::class]);
        DB::table('operators')->insert([
            'id' => self::OPERATOR_ID, 'code' => 'HIPL', 'name' => 'HIPL', 'is_active' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function vend(array $attributes = [], array $modem = [], bool $resettable = true): int
    {
        $typeId = DB::table('modem_types')->insertGetId([
            'name' => 'Air724UGB4', 'is_resetable' => $resettable, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $unitId = DB::table('modem_units')->insertGetId(array_merge([
            'modem_type_id' => $typeId, 'imei' => '861234560388', 'is_online' => 1, 'is_active' => 1, 'last_updated_at' => now()->subMinute(),
            'created_at' => now(), 'updated_at' => now(),
        ], $modem));
        $customerId = DB::table('customers')->insertGetId([
            'name' => 'Jurong East St 31 Blk 321', 'profile_id' => 1, 'status_id' => 1, 'is_active' => 1,
            'operator_id' => self::OPERATOR_ID, 'created_at' => now(), 'updated_at' => now(),
        ]);

        return DB::table('vends')->insertGetId(array_merge([
            'code' => 2388, 'name' => 'Machine', 'machine_type' => Vend::MACHINE_TYPE_VENDING_MACHINE,
            'operator_id' => self::OPERATOR_ID, 'customer_id' => $customerId, 'is_active' => 1, 'is_testing' => 0,
            'is_online' => 1, 'is_mqtt' => 1, 'modem_type_id' => $typeId, 'modem_unit_id' => $unitId,
            'last_updated_at' => now()->subMinutes(11), 'mqtt_last_updated_at' => now()->subMinutes(11),
            'temp_updated_at' => now()->subMinutes(11),
            'created_at' => now(), 'updated_at' => now(),
        ], $attributes));
    }

    private function run_job(): void
    {
        (new SyncOnlineStatus)->handle(app(MqttService::class), app(AlertEmailService::class));
    }

    public function test_a_board_unreachable_on_both_links_with_a_live_modem_is_reset_once_at_qos_0(): void
    {
        $id = $this->vend();

        $this->run_job();

        Queue::assertPushed(PublishMqtt::class, 1);
        Queue::assertPushed(PublishMqtt::class, function ($job) {
            $props = (fn () => [$this->topic, $this->qos])->call($job);

            return $props === ['CX560388', MqttClient::QOS_AT_MOST_ONCE];
        });
        $log = VendLog::where('vend_id', $id)->where('event', VendLog::EVENT_MODEM_RESET)->first();
        $this->assertSame(11, $log->context['http_quiet_minutes']);
    }

    public function test_the_2388_loop_no_longer_starts_five_minutes_after_one_late_packet(): void
    {
        $this->vend(['last_updated_at' => now()->subMinutes(5), 'mqtt_last_updated_at' => now()->subMinutes(5)]);

        $this->run_job();

        Queue::assertNotPushed(PublishMqtt::class);
    }

    public function test_no_reset_while_the_board_mqtt_is_up(): void
    {
        $this->vend(['mqtt_last_updated_at' => now()->subMinute()]);

        $this->run_job();

        Queue::assertNotPushed(PublishMqtt::class);
    }

    public function test_no_reset_when_the_modem_itself_is_unreachable(): void
    {
        $this->vend([], ['last_updated_at' => now()->subMinutes(40)]);

        $this->run_job();

        Queue::assertNotPushed(PublishMqtt::class);
    }

    public function test_at_most_one_reset_per_cooldown(): void
    {
        $this->vend();
        $this->run_job();
        Carbon::setTestNow(now()->addMinutes(29));
        DB::table('modem_units')->update(['last_updated_at' => now()]);
        $this->run_job();
        Queue::assertPushed(PublishMqtt::class, 1);

        Carbon::setTestNow(now()->addMinutes(2));
        DB::table('modem_units')->update(['last_updated_at' => now()]);
        $this->run_job();
        Queue::assertPushed(PublishMqtt::class, 2);
    }

    public function test_other_modems_unit_65_and_the_switch_are_never_reset(): void
    {
        $this->vend([], [], false);
        $this->vend(['code' => 2389, 'modem_unit_id' => 65]);
        config(['vend.modem_reset.enabled' => false]);
        $this->vend(['code' => 2390]);

        $this->run_job();

        Queue::assertNotPushed(PublishMqtt::class);
    }

    public function test_a_board_without_mqtt_is_judged_on_http_alone(): void
    {
        $this->vend(['is_mqtt' => 0, 'mqtt_last_updated_at' => null]);

        $this->run_job();

        Queue::assertPushed(PublishMqtt::class, 1);
    }
}
