<?php

namespace Tests\Feature;

use App\Jobs\PublishFreezerAiJob;
use App\Models\SmartFreezerOwnAiRecognition;
use App\Models\SmartFreezerRecognition;
use App\Models\Vend;
use App\Services\Mqtt\MqttPublisher;
use App\Services\SmartFreezer\OwnAi\OwnAiLink;
use App\Services\SubscribeMqttService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * mark1 ↔ our own freezer AI on the office Macs over MQTT (Brian, 2026-11-19): mark1 announces each door session on
 * `freezer-ai/jobs`, each Mac (worker) answers on `freezer-ai/results/<worker>`, one answer kept per worker, so the
 * MacBook M1 and the Mac mini can both be connected. The fleet listener never treats this traffic as machine data.
 */
class FreezerAiLinkTest extends TestCase
{
    use RefreshDatabase;

    private Vend $vend;

    /** @var list<array{0: string, 1: string, 2: int}> */
    private array $published = [];

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-19 12:00:00');
        config([
            'smart_freezer.own_ai.enabled' => true,
            'smart_freezer.own_ai.vends' => [],
            'smart_freezer.own_ai.topic_prefix' => 'freezer-ai',
            'smart_freezer.claude_recognition.enabled' => false,
        ]);
        $this->vend = Vend::create(['code' => 50001, 'machine_type' => Vend::MACHINE_TYPE_SMART_FREEZER, 'is_active' => 1, 'operator_id' => 1]);
        $this->app->instance(MqttPublisher::class, new MqttPublisher(
            ['persistent_codes' => []], [], 'mqtt_vends', null,
            function (string $topic, string $message, int $qos) {
                $this->published[] = [$topic, $message, $qos];
            },
        ));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function recognition(string $ref = 'SF-50001-1795000000-1'): SmartFreezerRecognition
    {
        return SmartFreezerRecognition::create([
            'vend_id' => $this->vend->id, 'trade_id' => $ref, 'session_ref' => $ref, 'status' => SmartFreezerRecognition::STATUS_PENDING,
        ]);
    }

    private function link(): OwnAiLink
    {
        return $this->app->make(OwnAiLink::class);
    }

    public function test_the_fleet_listener_leaves_freezer_ai_topics_alone(): void
    {
        $this->assertTrue(SubscribeMqttService::isFreezerAiTopic('freezer-ai/results/m1'));
        $this->assertTrue(SubscribeMqttService::isFreezerAiTopic('freezer-ai/jobs'));
        $this->assertFalse(SubscribeMqttService::isFreezerAiTopic('CM50001'));
        $this->assertFalse(SubscribeMqttService::isFreezerAiTopic('freezer-aix/jobs'));
    }

    public function test_a_session_is_announced_once_after_the_delay_and_only_when_on(): void
    {
        Queue::fake();
        $r = $this->recognition();
        config(['smart_freezer.own_ai.enabled' => false]);
        $this->link()->schedule($r);
        Queue::assertNothingPushed();

        config(['smart_freezer.own_ai.enabled' => true, 'smart_freezer.own_ai.vends' => ['2009']]);
        $this->link()->schedule($r);
        Queue::assertNothingPushed();

        config(['smart_freezer.own_ai.vends' => ['50001']]);
        $this->link()->schedule($r);
        $this->link()->schedule($r);   // a second camera's push for the same session
        Queue::assertPushed(PublishFreezerAiJob::class, 1);
    }

    public function test_the_job_carries_ids_only(): void
    {
        $r = $this->recognition();
        (new PublishFreezerAiJob($r->id))->handle($this->link());

        $this->assertCount(1, $this->published);
        [$topic, $message, $qos] = $this->published[0];
        $this->assertSame('freezer-ai/jobs', $topic);
        $this->assertSame(1, $qos);
        $job = json_decode($message, true);
        $this->assertSame(['v', 'session', 'vend', 'session_ref', 'opened_at', 'sent_at'], array_keys($job));
        $this->assertSame($r->id, $job['session']);
        $this->assertSame('50001', $job['vend']);
        $this->assertStringNotContainsString('http', $message);
    }

    public function test_two_macs_each_keep_their_own_answer(): void
    {
        $r = $this->recognition();
        $answer = fn (array $items, int $conf) => json_encode(['v' => 1, 'session' => $r->id, 'items' => $items, 'confidence' => $conf,
            'auto' => true, 'model' => 'v6-2026-11-19']);

        $ack = $this->link()->receive('freezer-ai/results/m1', $answer(['445' => 1, '740' => 2], 81));
        $this->assertSame('freezer-ai/ack/m1', $ack[0]);
        $this->assertSame(['session' => $r->id, 'ok' => true], json_decode($ack[1], true));
        $this->link()->receive('freezer-ai/results/mini', $answer(['445' => 1], 64));
        $this->link()->receive('freezer-ai/results/m1', $answer(['445' => 1, '740' => 1], 90));   // m1 answers again

        $rows = SmartFreezerOwnAiRecognition::orderBy('worker')->get();
        $this->assertSame(['m1', 'mini'], $rows->pluck('worker')->all());
        $this->assertSame(['445' => 1, '740' => 1], $rows[0]->items);
        $this->assertSame(90, $rows[0]->confidence);
        $this->assertSame(2, $rows[0]->answers);
        $this->assertSame(['445' => 1], $rows[1]->items);
        $this->assertTrue($rows[1]->auto_accept);
        $this->assertSame(['m1', 'mini'], array_keys($this->link()->workers()));
    }

    public function test_nothing_taken_is_a_real_answer(): void
    {
        $r = $this->recognition();
        $this->link()->receive('freezer-ai/results/m1', json_encode(['session' => $r->id, 'items' => []]));
        $this->assertSame([], SmartFreezerOwnAiRecognition::sole()->items);
    }

    public function test_bad_messages_are_refused_and_never_stored(): void
    {
        $r = $this->recognition();
        $this->assertNull($this->link()->receive('freezer-ai/results/Bad Name', json_encode(['session' => $r->id, 'items' => []])));
        $this->assertNull($this->link()->receive('CM50001', '{}'));
        $unknown = $this->link()->receive('freezer-ai/results/m1', json_encode(['session' => 999999, 'items' => []]));
        $this->assertSame(['session' => 999999, 'ok' => false, 'error' => 'unknown session'], json_decode($unknown[1], true));
        $bad = $this->link()->receive('freezer-ai/results/m1', json_encode(['session' => $r->id, 'items' => ['445' => -1]]));
        $this->assertSame('bad items', json_decode($bad[1], true)['error']);
        $notJson = $this->link()->receive('freezer-ai/results/m1', 'nope');
        $this->assertSame('not JSON', json_decode($notJson[1], true)['error']);
        $this->assertSame(0, SmartFreezerOwnAiRecognition::count());
    }

    public function test_a_mac_that_goes_quiet_reads_silent_and_its_last_will_reads_offline(): void
    {
        $this->link()->receive('freezer-ai/status/m1', json_encode(['state' => 'online', 'model' => 'v6']));
        $this->link()->receive('freezer-ai/status/mini', json_encode(['state' => 'online']));
        $this->link()->receive('freezer-ai/status/mini', json_encode(['state' => 'offline']));
        $this->assertSame('online', $this->link()->workers()['m1']['state']);
        $this->assertSame('offline', $this->link()->workers()['mini']['state']);

        Carbon::setTestNow(now()->addMinutes(5));
        $this->assertSame('silent', $this->link()->workers()['m1']['state']);
    }

    public function test_the_mini_answer_is_read_first_and_the_m1_is_the_fallback(): void
    {
        config(['smart_freezer.own_ai.worker_priority' => ['mini', 'm1']]);
        $r = $this->recognition();
        $send = fn (string $w, array $items) => $this->link()->receive("freezer-ai/results/{$w}", json_encode(['session' => $r->id, 'items' => $items]));

        $send('m1', ['445' => 1]);                       // the mini was offline: the M1 took the job
        $this->assertSame('m1', $this->link()->preferredAnswer($r->id)->worker);

        Carbon::setTestNow(now()->addMinute());
        $send('mini', ['445' => 2]);                     // the mini came back and answered too
        $this->assertSame('mini', $this->link()->preferredAnswer($r->id)->worker);
        $this->assertSame(['445' => 2], $this->link()->preferredAnswer($r->id)->items);
        $this->assertNull($this->link()->preferredAnswer(999999));
    }

    public function test_status_carries_each_macs_role(): void
    {
        $this->link()->receive('freezer-ai/status/mini', json_encode(['state' => 'online', 'role' => 'active']));
        $this->link()->receive('freezer-ai/status/m1', json_encode(['state' => 'online', 'role' => 'standby']));
        $this->assertSame('active', $this->link()->workers()['mini']['role']);
        $this->assertSame('standby', $this->link()->workers()['m1']['role']);
    }
}
