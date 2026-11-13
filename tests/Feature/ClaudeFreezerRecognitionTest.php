<?php

namespace Tests\Feature;

use App\Jobs\RunClaudeFreezerRecognition;
use App\Models\Product;
use App\Models\SmartFreezerClaudeRecognition as ClaudeRow;
use App\Models\SmartFreezerRecognition;
use App\Models\SmartFreezerVideo;
use App\Models\User;
use App\Models\Vend;
use App\Models\VendChannel;
use App\Models\VendTransaction;
use App\Services\SmartFreezer\Claude\CallUsage;
use App\Services\SmartFreezer\Claude\CatalogProduct;
use App\Services\SmartFreezer\Claude\ClaudeAccuracyEvaluation;
use App\Services\SmartFreezer\Claude\ClaudeCostMoney;
use App\Services\SmartFreezer\Claude\ClaudeFreezerCounter;
use App\Services\SmartFreezer\Claude\ClaudeModelChoice;
use App\Services\SmartFreezer\Claude\ClaudeShadowRecognitionService;
use App\Services\SmartFreezer\Claude\ClaudeZijiaComparison;
use App\Services\SmartFreezer\Claude\CountFailed;
use App\Services\SmartFreezer\Claude\DoorFrame;
use App\Services\SmartFreezer\Claude\DoorFrameSource;
use App\Services\SmartFreezer\Claude\FfmpegDoorFrames;
use App\Services\SmartFreezer\Claude\FreezerCount;
use App\Services\SmartFreezer\Claude\FreezerCounter;
use App\Services\SmartFreezer\ZijiaVideoCopier;
use Carbon\Carbon;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia;
use Spatie\Permission\Models\Permission;
use Symfony\Component\Process\Process;
use Tests\TestCase;

/**
 * Claude counts each freezer door session beside Zijia (Brian, 2026-11-13), shadow only: one billed call per
 * session, its cost kept, its count judged with Zijia's own rule and compared on AI Recognition — and nothing
 * it says charges, refunds or moves stock.
 */
class ClaudeFreezerRecognitionTest extends TestCase
{
    use RefreshDatabase;

    private const REF = 'SF-50001-1794482528-4';

    private Vend $vend;

    private Product $basque;

    private Product $magnum;

    private FakeFreezerCounter $counter;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-13 12:00:00');
        config([
            'services.anthropic.api_key' => 'test-key',
            'smart_freezer.claude_recognition.enabled' => true,
            'smart_freezer.claude_recognition.vends' => [],
            'smart_freezer.zijia.algorithm.auto_submit' => false,
            'smart_freezer.zijia.video_copy.enabled' => false,
        ]);
        $this->vend = Vend::create(['code' => 50001, 'machine_type' => Vend::MACHINE_TYPE_SMART_FREEZER, 'is_active' => 1, 'operator_id' => 1]);
        $this->basque = Product::forceCreate(['code' => 'CC-01', 'name' => 'Basque Cheesecake', 'operator_id' => 1, 'barcode' => '9726436016148']);
        $this->magnum = Product::forceCreate(['code' => 'MG-01', 'name' => 'Magnum Classic', 'operator_id' => 1, 'barcode' => '8888']);
        foreach ([[11, $this->basque], [21, $this->magnum]] as [$code, $product]) {
            VendChannel::create(['vend_id' => $this->vend->id, 'code' => $code, 'qty' => 4, 'capacity' => 5, 'amount' => 240,
                'product_id' => $product->id, 'is_active' => 1, 'error_rate_json' => []]);
        }
        $this->counter = new FakeFreezerCounter;
        $this->app->instance(FreezerCounter::class, $this->counter);
        $this->app->instance(DoorFrameSource::class, new FakeDoorFrames);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function zijia(array $attributes = []): SmartFreezerRecognition
    {
        $recognition = SmartFreezerRecognition::create($attributes + [
            'vend_id' => $this->vend->id, 'trade_id' => self::REF, 'session_ref' => self::REF,
            'status' => SmartFreezerRecognition::STATUS_PENDING,
        ]);
        SmartFreezerVideo::create([
            'supplier' => 'zijia', 'vend_id' => $this->vend->id, 'smart_freezer_recognition_id' => $recognition->id,
            'order_no' => $recognition->trade_id, 'payload' => ['orderNo' => $recognition->trade_id],
            'video_urls' => ["https://oss.example.cn/{$recognition->trade_id}-d1c3-25f-1280x720.mp4"],
        ]);

        return $recognition;
    }

    /** @param  array<int, int>  $units  product id => units paid */
    private function sale(array $units, array $meta = []): VendTransaction
    {
        $transf = [];
        foreach ($units as $productId => $n) {
            for ($i = 0; $i < $n; $i++) {
                $transf[] = ['goods_id' => $productId, 'Price' => 240];
            }
        }

        return VendTransaction::forceCreate([
            'order_id' => 'O-'.uniqid(), 'vend_id' => $this->vend->id, 'vend_channel_id' => 0, 'amount' => 240 * array_sum($units),
            'transaction_datetime' => Carbon::now(), 'gst_vat_rate' => 9, 'operator_id' => 1, 'is_multiple' => false,
            'vend_transaction_json' => ['SFREF' => self::REF, 'transf_info' => $transf],
            'meta_json' => $meta ?: null,
        ]);
    }

    private function service(): ClaudeShadowRecognitionService
    {
        return app(ClaudeShadowRecognitionService::class);
    }

    private function counted(array $items, int $input = 25800, int $output = 1800): FreezerCount
    {
        return new FreezerCount($items, [], 1, 90, false, [], 'took one Basque', ['items' => $items, 'unknown_items' => [], 'notes' => 'took one Basque'],
            new CallUsage('claude-opus-5-5', 'msg_1', 'end_turn', $input, $output, 0, 0));
    }

    public function test_a_session_opened_by_zijia_gets_one_claude_row_and_one_job(): void
    {
        Queue::fake();
        $recognition = $this->zijia();
        $service = app(\App\Services\SmartFreezer\FreezerRecognitionService::class);
        $video = $recognition->videos()->first();
        $push = \App\Services\SmartFreezer\Zijia\ZijiaVideoPush::fromPayload(['orderNo' => self::REF, 'deviceNo' => '861232069528880']);

        $service->open($video, $push, $this->vend);
        $service->open($video, $push, $this->vend);

        $this->assertSame(1, ClaudeRow::count());
        $this->assertSame(ClaudeRow::STATUS_PENDING, ClaudeRow::first()->status);
        Queue::assertPushed(RunClaudeFreezerRecognition::class, 1);
    }

    public function test_nothing_is_opened_while_it_is_switched_off_without_a_key_or_on_an_unlisted_machine(): void
    {
        Queue::fake();
        $recognition = $this->zijia();

        foreach ([
            ['smart_freezer.claude_recognition.enabled' => false],
            ['services.anthropic.api_key' => ''],
            ['smart_freezer.claude_recognition.vends' => ['50002']],
        ] as $off) {
            $this->counter->configured = ($off['services.anthropic.api_key'] ?? 'x') !== '';
            config(array_merge(['smart_freezer.claude_recognition.enabled' => true, 'smart_freezer.claude_recognition.vends' => []], $off));
            $this->service()->schedule($recognition->fresh('vend'));
        }

        $this->assertSame(0, ClaudeRow::count());
        Queue::assertNothingPushed();
    }

    public function test_a_count_is_stored_with_its_cost_and_judged_with_zijias_rule(): void
    {
        $recognition = $this->zijia();
        $this->sale([$this->basque->id => 1]);
        $row = ClaudeRow::create(['smart_freezer_recognition_id' => $recognition->id, 'status' => ClaudeRow::STATUS_PENDING]);
        $this->counter->answers[] = $this->counted([['product_id' => $this->basque->id, 'taken' => 2, 'returned' => 1]]);

        $row = $this->service()->run($row);

        $this->assertSame(ClaudeRow::STATUS_DONE, $row->status);
        $this->assertEquals([$this->basque->id => 1], $row->items, 'taken 2, put 1 back: one left the cabinet');
        $this->assertSame('match', $row->verdict);
        // 25,800 in × $4 + 1,800 out × $20 per million = $0.1392.
        $this->assertSame(139_200, $row->cost_usd_micros);
        $this->assertSame('S$0.18', $row->costText(), 'US$0.1392 at 1.29');
        $this->assertSame([3, 3, 4, 4], array_column($row->frames['sent'], 'camera'));
        $this->assertSame([$this->basque->id, $this->magnum->id], $row->frames['catalog']);
        $this->assertSame(['Basque Cheesecake', 'Magnum Classic'], array_map(fn (CatalogProduct $p) => $p->name, $this->counter->catalogs[0]));
        $this->assertSame([['11'], ['21']], array_map(fn (CatalogProduct $p) => $p->sections, $this->counter->catalogs[0]));
    }

    public function test_it_never_touches_the_sale_the_zijia_row_or_the_stock(): void
    {
        $recognition = $this->zijia(['status' => SmartFreezerRecognition::STATUS_COMPLETED, 'verdict' => 'match', 'items' => ['9726436016148' => 1]]);
        $sale = $this->sale([$this->basque->id => 1]);
        $before = [$sale->fresh()->toArray(), $recognition->fresh()->toArray(), VendChannel::orderBy('id')->pluck('qty')->all()];
        $row = ClaudeRow::create(['smart_freezer_recognition_id' => $recognition->id, 'status' => ClaudeRow::STATUS_PENDING]);
        $this->counter->answers[] = $this->counted([['product_id' => $this->magnum->id, 'taken' => 3, 'returned' => 0]]);

        $this->assertSame('mixed', $this->service()->run($row)->verdict);

        $this->assertEquals($before, [$sale->fresh()->toArray(), $recognition->fresh()->toArray(), VendChannel::orderBy('id')->pluck('qty')->all()]);
    }

    public function test_a_row_is_sent_once_even_when_run_twice(): void
    {
        $recognition = $this->zijia();
        $row = ClaudeRow::create(['smart_freezer_recognition_id' => $recognition->id, 'status' => ClaudeRow::STATUS_PENDING]);
        $this->counter->answers[] = $this->counted([]);

        $this->service()->run($row);
        $this->service()->run(ClaudeRow::find($row->id));
        (new RunClaudeFreezerRecognition($row->id))->handle($this->service());

        $this->assertCount(1, $this->counter->catalogs);
    }

    public function test_the_daily_limit_stops_automatic_calls_but_not_a_person(): void
    {
        config(['smart_freezer.claude_recognition.daily_limit' => 1]);
        $first = $this->zijia();
        ClaudeRow::create(['smart_freezer_recognition_id' => $first->id, 'status' => ClaudeRow::STATUS_DONE, 'started_at' => Carbon::now()]);
        $second = $this->zijia(['trade_id' => 'SF-50001-1794482580-5', 'session_ref' => 'SF-50001-1794482580-5']);
        $row = ClaudeRow::create(['smart_freezer_recognition_id' => $second->id, 'status' => ClaudeRow::STATUS_PENDING]);

        $row = $this->service()->run($row);
        $this->assertSame(ClaudeRow::STATUS_SKIPPED, $row->status);
        $this->assertSame('daily limit of Claude calls reached', $row->status_reason);
        $this->assertSame([], $this->counter->catalogs);

        $row->update(['status' => ClaudeRow::STATUS_PENDING]);
        $this->counter->answers[] = $this->counted([]);
        $this->assertSame(ClaudeRow::STATUS_DONE, $this->service()->run($row, byPerson: true)->status);
    }

    public function test_the_monthly_budget_stops_every_call_even_a_persons(): void
    {
        config(['smart_freezer.claude_recognition.monthly_budget_usd' => 0.2]);
        $first = $this->zijia();
        ClaudeRow::create(['smart_freezer_recognition_id' => $first->id, 'status' => ClaudeRow::STATUS_DONE,
            'started_at' => Carbon::now(), 'cost_usd_micros' => 200_000]);
        ClaudeRow::create(['smart_freezer_recognition_id' => $this->zijia(['trade_id' => 'T-old', 'session_ref' => null])->id,
            'status' => ClaudeRow::STATUS_DONE, 'started_at' => Carbon::now()->subMonth(), 'cost_usd_micros' => 9_000_000]);
        $row = ClaudeRow::create(['smart_freezer_recognition_id' => $this->zijia(['trade_id' => 'SF-50001-1794482580-5', 'session_ref' => 'SF-50001-1794482580-5'])->id,
            'status' => ClaudeRow::STATUS_PENDING]);

        $row = $this->service()->run($row, byPerson: true);

        $this->assertSame(ClaudeRow::STATUS_SKIPPED, $row->status);
        $this->assertSame('monthly Claude budget of US$0.2 reached', $row->status_reason);
        $this->assertSame([], $this->counter->catalogs);
        $this->assertSame(200_000, $this->service()->spentThisMonthUsdMicros(), 'last month does not count');
    }

    public function test_a_door_try_that_did_not_open_is_not_sent(): void
    {
        $recognition = $this->zijia(['status' => SmartFreezerRecognition::STATUS_SKIPPED, 'status_reason' => 'another try opened']);
        $row = ClaudeRow::create(['smart_freezer_recognition_id' => $recognition->id, 'status' => ClaudeRow::STATUS_PENDING]);

        $this->assertSame(ClaudeRow::STATUS_SKIPPED, $this->service()->run($row)->status);
        $this->assertSame([], $this->counter->catalogs);
    }

    public function test_a_declined_call_is_failed_and_its_cost_still_kept(): void
    {
        $recognition = $this->zijia();
        $row = ClaudeRow::create(['smart_freezer_recognition_id' => $recognition->id, 'status' => ClaudeRow::STATUS_PENDING]);
        $this->counter->answers[] = new CountFailed('Claude declined this session', new CallUsage('claude-opus-5-5', 'msg_2', 'refusal', 20000, 100, 0, 0));

        $row = $this->service()->run($row);

        $this->assertSame(ClaudeRow::STATUS_FAILED, $row->status);
        $this->assertSame('Claude declined this session', $row->status_reason);
        $this->assertSame(82_000, $row->cost_usd_micros);
    }

    public function test_a_sale_landing_later_is_judged_by_the_sweep(): void
    {
        $recognition = $this->zijia();
        $row = ClaudeRow::create(['smart_freezer_recognition_id' => $recognition->id, 'status' => ClaudeRow::STATUS_PENDING]);
        $this->counter->answers[] = $this->counted([]);
        $row = $this->service()->run($row);
        $this->assertNull($row->verdict);
        $this->assertSame('sale not found yet for '.self::REF, $row->status_reason);

        $this->sale([$this->basque->id => 1]);
        $this->artisan('smart-freezer:claude-recognition', ['--evaluate' => true])->assertSuccessful();

        $this->assertSame('took_less', $row->fresh()->verdict);
    }

    public function test_the_counter_sends_frames_and_photos_but_never_what_was_paid_and_reads_the_bill(): void
    {
        $history = new \ArrayObject;
        $stack = HandlerStack::create(new MockHandler([new Response(200, ['Content-Type' => 'application/json'], json_encode([
            'id' => 'msg_9', 'type' => 'message', 'role' => 'assistant', 'model' => 'claude-opus-5-5',
            'content' => [
                ['type' => 'thinking', 'thinking' => '', 'signature' => 'sig'],
                ['type' => 'text', 'text' => json_encode([
                    'items' => [['product_id' => 7, 'taken' => 1, 'returned' => 0], ['product_id' => 999, 'taken' => 1, 'returned' => 0]],
                    'unknown_items' => [['description' => 'a blue cup', 'taken' => 1]],
                    'people_seen' => 2, 'confidence' => 140, 'needs_review' => true, 'reasons' => ['two_people', 'made_up'], 'notes' => 'Two hands.',
                ])],
            ],
            'stop_reason' => 'end_turn', 'stop_sequence' => null,
            'usage' => ['input_tokens' => 25800, 'output_tokens' => 1800, 'cache_creation_input_tokens' => 0, 'cache_read_input_tokens' => 0],
        ]))]));
        $stack->push(Middleware::history($history));
        $counter = new ClaudeFreezerCounter(new GuzzleClient(['handler' => $stack]));

        $count = $counter->count(
            [new DoorFrame(3, 0, 11000, 'jpeg-a'), new DoorFrame(4, 5400, 11000, 'jpeg-b')],
            [new CatalogProduct(7, 'Basque Cheesecake', 'CC-01', ['11'], 'photo'), new CatalogProduct(8, 'Magnum Classic', null, ['21'], null)],
            self::REF.' on machine 50001',
        );

        $body = json_decode((string) $history[0]['request']->getBody(), true);
        $this->assertSame('claude-opus-5-5', $body['model']);
        $this->assertSame('default', $body['fallbacks']);
        $this->assertSame('high', $body['output_config']['effort']);
        $this->assertSame('json_schema', $body['output_config']['format']['type']);
        $images = array_values(array_filter($body['messages'][0]['content'], fn ($b) => $b['type'] === 'image'));
        $this->assertCount(3, $images, 'one product photo and two frames');
        $text = implode("\n", array_column(array_filter($body['messages'][0]['content'], fn ($b) => $b['type'] === 'text'), 'text'));
        $this->assertStringContainsString('Product id 7: Basque Cheesecake (item code CC-01), in section 11.', $text);
        $this->assertStringContainsString('Camera 4 at 5.4 s:', $text);
        $this->assertStringNotContainsStringIgnoringCase('paid', $text, 'Claude counts blind');

        $this->assertSame([['product_id' => 7, 'taken' => 1, 'returned' => 0]], $count->items);
        $this->assertSame(['product id 999 (not on this freezer)', 'a blue cup'], array_column($count->unknownItems, 'description'));
        $this->assertSame(100, $count->confidence);
        $this->assertSame(['two_people'], $count->reasons);
        $this->assertSame(139_200, $count->usage->costUsdMicros());
    }

    public function test_a_cut_off_answer_fails_with_its_bill(): void
    {
        $stack = HandlerStack::create(new MockHandler([new Response(200, ['Content-Type' => 'application/json'], json_encode([
            'id' => 'msg_3', 'type' => 'message', 'role' => 'assistant', 'model' => 'claude-sonnet-5-5',
            'content' => [['type' => 'text', 'text' => '{"items":[']],
            'stop_reason' => 'max_tokens', 'stop_sequence' => null,
            'usage' => ['input_tokens' => 1000, 'output_tokens' => 16000],
        ]))]));

        try {
            (new ClaudeFreezerCounter(new GuzzleClient(['handler' => $stack])))->count([new DoorFrame(3, 0, 1000, 'x')], [], 'S');
            $this->fail('expected a failed count');
        } catch (CountFailed $e) {
            $this->assertSame('claude-sonnet-5-5', $e->usage->model, 'the model that answered is the one priced');
            $this->assertSame(1000 * 2 + 16000 * 10, $e->usage->costUsdMicros());
        }
    }

    public function test_the_camera_is_read_from_the_file_name_and_frames_spread_over_the_video(): void
    {
        $this->assertSame(3, FfmpegDoorFrames::cameraOf('https://x/boxapp/1/order/SF-1/SF-1-d1c3-25f-1280x720.mp4?a=1'));
        $this->assertSame(5, FfmpegDoorFrames::cameraOf('https://x/SF-1-d1c5-25f-1280x720.mp4'));
        $this->assertNull(FfmpegDoorFrames::cameraOf('https://x/clip.mp4'));
        $this->assertSame([0, 3600, 7200, 10800], FfmpegDoorFrames::moments(11000, 4));
        // First and last kept for the baskets before and after; the rest go where the hand moves.
        $this->assertSame([0, 2000, 3000, 4000, 5000, 6000, 8680], FfmpegDoorFrames::moments(8880, 7, [2000, 6000]));
        $this->assertSame([0, 4440, 8680], FfmpegDoorFrames::moments(8880, 3, [4440, 4440]));
    }

    public function test_real_frames_come_from_cameras_3_and_4_only(): void
    {
        $ffmpeg = trim((string) shell_exec('command -v ffmpeg'));
        $ffprobe = trim((string) shell_exec('command -v ffprobe'));
        if ($ffmpeg === '' || $ffprobe === '') {
            $this->markTestSkipped('ffmpeg is not installed here');
        }
        config(['smart_freezer.claude_recognition.ffmpeg_bin' => $ffmpeg, 'smart_freezer.clips.ffprobe_bin' => $ffprobe,
            'smart_freezer.claude_recognition.frames_per_camera' => 3, 'smart_freezer.zijia.video_copy.hosts' => ['oss.example.cn']]);
        $video = tempnam(sys_get_temp_dir(), 'sfvid').'.mp4';
        (new Process([$ffmpeg, '-v', 'error', '-f', 'lavfi', '-i', 'testsrc=duration=2:size=1280x720:rate=25', '-pix_fmt', 'yuv420p', '-y', $video]))->mustRun();
        $bytes = file_get_contents($video);
        @unlink($video);
        \Illuminate\Support\Facades\Http::fake(['https://oss.example.cn/*' => \Illuminate\Support\Facades\Http::response($bytes, 200, ['Content-Type' => 'video/mp4'])]);

        $recognition = SmartFreezerRecognition::create(['vend_id' => $this->vend->id, 'trade_id' => self::REF, 'session_ref' => self::REF, 'status' => 'pending']);
        SmartFreezerVideo::create(['supplier' => 'zijia', 'vend_id' => $this->vend->id, 'smart_freezer_recognition_id' => $recognition->id,
            'order_no' => self::REF, 'payload' => ['orderNo' => self::REF], 'raw_body' => '{}',
            'video_urls' => ['https://oss.example.cn/'.self::REF.'-d1c3-25f-1280x720.mp4', 'https://oss.example.cn/'.self::REF.'-d1c5-25f-1280x720.mp4']]);

        $got = (new FfmpegDoorFrames(app(ZijiaVideoCopier::class)))->framesFor($recognition->fresh('videos'));

        $this->assertSame([3, 3, 3], array_map(fn (DoorFrame $f) => $f->camera, $got['frames']));
        $this->assertSame([820, 576], array_slice(getimagesizefromstring($got['frames'][0]->jpeg), 0, 2), 'camera 3 loses its fisheye corners');
        $this->assertStringContainsString('camera 5', implode(' ', $got['notes']));
    }

    public function test_claude_zijia_and_a_person_are_compared_session_by_session(): void
    {
        $agree = $this->zijia(['status' => 'completed', 'verdict' => 'match', 'verdict_lines' => [['product_id' => $this->basque->id, 'paid' => 1, 'taken' => 1]]]);
        ClaudeRow::create(['smart_freezer_recognition_id' => $agree->id, 'status' => 'done', 'items' => [$this->basque->id => 1], 'verdict' => 'match', 'cost_usd_micros' => 140_000]);

        $ref = 'SF-50001-1794482580-5';
        $sale = VendTransaction::forceCreate(['order_id' => 'O-2', 'vend_id' => $this->vend->id, 'vend_channel_id' => 0, 'amount' => 240,
            'transaction_datetime' => Carbon::now(), 'gst_vat_rate' => 9, 'operator_id' => 1,
            'vend_transaction_json' => ['SFREF' => $ref], 'meta_json' => ['correction' => ['basket' => [$this->magnum->id => 2]]]]);
        $differ = $this->zijia(['trade_id' => $ref, 'session_ref' => $ref, 'status' => 'completed', 'verdict' => 'took_less', 'vend_transaction_id' => $sale->id,
            'verdict_lines' => [['product_id' => $this->magnum->id, 'paid' => 2, 'taken' => 1]]]);
        ClaudeRow::create(['smart_freezer_recognition_id' => $differ->id, 'status' => 'done', 'items' => [$this->magnum->id => 2], 'verdict' => 'match',
            'vend_transaction_id' => $sale->id, 'cost_usd_micros' => 160_000]);

        $s = ClaudeZijiaComparison::summarise(ClaudeRow::with('recognition')->get());

        $this->assertSame(2, $s['claude_match_paid']);
        $this->assertSame(1, $s['zijia_match_paid']);
        $this->assertSame([1, 2], [$s['agree'], $s['both_answered']]);
        $this->assertSame([1, 1, 0, 1], [$s['person_counted'], $s['claude_right'], $s['zijia_right'], $s['zijia_person_counted']]);
        $this->assertSame('S$0.39', $s['cost_text'], 'US$0.30 at 1.29');
        $this->assertSame('S$0.19', $s['cost_per_session_text']);
    }

    public function test_ai_recognition_shows_claude_beside_zijia(): void
    {
        Permission::findOrCreate('read ai-recognition', 'web');
        $user = User::factory()->create(['operator_id' => 1]);
        $user->givePermissionTo('read ai-recognition');
        $recognition = $this->zijia(['status' => 'completed', 'verdict' => 'match', 'verdict_lines' => [['product_id' => $this->basque->id, 'paid' => 1, 'taken' => 1]]]);
        ClaudeRow::create(['smart_freezer_recognition_id' => $recognition->id, 'status' => 'done', 'items' => [$this->basque->id => 1],
            'verdict' => 'match', 'confidence' => 88, 'needs_review' => false, 'cost_usd_micros' => 139_200, 'answer' => ['notes' => 'One Basque.', 'unknown_items' => []]]);

        $this->actingAs($user)->get('/ai-recognition')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('recognitions.data.0.claude.status', 'done')
                ->where('recognitions.data.0.claude.items.0.name', 'Basque Cheesecake')
                ->where('recognitions.data.0.claude.same_as_zijia', true)
                ->where('recognitions.data.0.claude.cost_text', 'S$0.18')
                ->where('dashboard.claude.agree', 1)
                ->where('dashboard.claude.claude_match_paid', 1));
    }

    public function test_an_admin_picks_the_model_others_only_see_it_and_the_pick_is_what_is_sent(): void
    {
        Permission::findOrCreate('read ai-recognition', 'web');
        Permission::findOrCreate('update ai-recognition', 'web');
        $admin = User::factory()->create(['operator_id' => 1, 'name' => 'Brian']);
        $admin->givePermissionTo('read ai-recognition', 'update ai-recognition');
        $viewer = User::factory()->create(['operator_id' => 1]);
        $viewer->givePermissionTo('read ai-recognition');

        $this->actingAs($viewer)->post('/ai-recognition/claude-model', ['model' => 'claude-haiku-5-5'])->assertForbidden();
        $this->actingAs($admin)->post('/ai-recognition/claude-model', ['model' => 'gpt-5'])->assertSessionHasErrors('model');
        $this->assertSame('claude-opus-5-5', ClaudeModelChoice::current(), 'nothing picked = the config default');

        $this->actingAs($admin)->post('/ai-recognition/claude-model', ['model' => 'claude-haiku-5-5'])->assertSessionHasNoErrors();

        $this->assertSame('claude-haiku-5-5', ClaudeModelChoice::current());
        $this->assertSame('claude-haiku-5-5', (new ClaudeFreezerCounter)->model());
        $this->actingAs($viewer)->get('/ai-recognition')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('claudeModel.current', 'claude-haiku-5-5')
                ->where('claudeModel.can_change', false)
                ->where('claudeModel.enabled', true)
                ->where('claudeModel.set_by', 'Brian')
                ->where('claudeModel.options.1.id', 'claude-opus-5-5')
                ->where('claudeModel.options.1.estimate', 'S$0.18'));
    }

    public function test_haiku_is_sent_without_the_fallback_it_does_not_take(): void
    {
        ClaudeModelChoice::pick('claude-haiku-5-5', User::factory()->create(['operator_id' => 1]));
        $history = new \ArrayObject;
        $stack = HandlerStack::create(new MockHandler([new Response(200, ['Content-Type' => 'application/json'], json_encode([
            'id' => 'msg_h', 'type' => 'message', 'role' => 'assistant', 'model' => 'claude-haiku-5-5',
            'content' => [['type' => 'text', 'text' => '{"items":[],"unknown_items":[],"people_seen":1,"confidence":80,"needs_review":false,"reasons":[],"notes":""}']],
            'stop_reason' => 'end_turn', 'stop_sequence' => null, 'usage' => ['input_tokens' => 25000, 'output_tokens' => 2000],
        ]))]));
        $stack->push(Middleware::history($history));

        $count = (new ClaudeFreezerCounter(new GuzzleClient(['handler' => $stack])))->count([new DoorFrame(3, 0, 1000, 'x')], [], 'S');

        $body = json_decode((string) $history[0]['request']->getBody(), true);
        $this->assertSame('claude-haiku-5-5', $body['model']);
        $this->assertArrayNotHasKey('fallbacks', $body);
        $this->assertStringNotContainsString('server-side-fallback', $history[0]['request']->getHeaderLine('anthropic-beta'));
        $this->assertSame(3_500, $count->usage->costUsdMicros(), '25,000 in × $0.10 + 2,000 out × $0.50 per million');
    }

    public function test_costs_show_in_the_sites_own_money(): void
    {
        \Illuminate\Support\Facades\Cache::forget('claude-freezer-ai-currency');
        $idr = \Illuminate\Support\Facades\DB::table('countries')->insertGetId(['name' => 'Indonesia', 'code' => 'ID', 'currency_name' => 'IDR',
            'currency_symbol' => 'Rp', 'is_currency_exponent_hidden' => 1, 'phone_code' => 62]);
        \Illuminate\Support\Facades\DB::table('operators')->insert(['code' => 'IDN', 'name' => 'IDN', 'is_active' => 1, 'country_id' => $idr,
            'created_at' => now(), 'updated_at' => now()]);

        $this->assertSame('IDR', ClaudeCostMoney::currency(), 'every operator here is Indonesian');
        $this->assertSame('Rp 2,533', ClaudeCostMoney::text(139_200), 'US$0.1392 at 18,200');

        config(['smart_freezer.claude_recognition.currency' => 'SGD']);
        $this->assertSame('S$0.18', ClaudeCostMoney::text(139_200));
        $this->assertSame('S$0.005', ClaudeCostMoney::text(3_500), 'a tiny cost keeps a third decimal');

        config(['smart_freezer.claude_recognition.currency' => 'MYR']);
        $this->assertSame('RM0.57', ClaudeCostMoney::text(139_200));

        config(['smart_freezer.claude_recognition.currency' => 'THB']);
        $this->assertSame('US$0.14', ClaudeCostMoney::text(139_200), 'no rate on file: US dollars');
        $this->assertNull(ClaudeCostMoney::text(null));
    }

    /** @param  array<int, int>|null  $correction  a person's Adjust Qty basket */
    private function judgedSession(string $ref, ?string $zijiaVerdict, array $zijiaTaken, ?array $correction, ?array $claude, int $micros, string $status = 'done'): void
    {
        $sale = VendTransaction::forceCreate(['order_id' => 'O-'.$ref, 'vend_id' => $this->vend->id, 'vend_channel_id' => 0, 'amount' => 240,
            'transaction_datetime' => Carbon::now(), 'gst_vat_rate' => 9, 'operator_id' => 1, 'vend_transaction_json' => ['SFREF' => $ref],
            'meta_json' => $correction === null ? null : ['correction' => ['basket' => $correction]]]);
        $zijia = $this->zijia(['trade_id' => $ref, 'session_ref' => $ref, 'status' => 'completed', 'verdict' => $zijiaVerdict, 'vend_transaction_id' => $sale->id,
            'verdict_lines' => collect($zijiaTaken)->map(fn ($n, $id) => ['product_id' => $id, 'paid' => 1, 'taken' => $n])->values()->all()]);
        ClaudeRow::create(['smart_freezer_recognition_id' => $zijia->id, 'vend_id' => $this->vend->id, 'session_ref' => $ref, 'status' => $status,
            'items' => $claude, 'vend_transaction_id' => $sale->id, 'model' => 'claude-opus-5-5', 'cost_usd_micros' => $micros]);
    }

    public function test_a_persons_amended_count_is_the_final_and_a_correct_result_is_priced(): void
    {
        $b = $this->basque->id;
        $m = $this->magnum->id;
        // Amended by a person: Claude saw it right, Zijia did not.
        $this->judgedSession('SF-50001-1794000001-1', 'took_less', [$b => 0], [$b => 1], [$b => 1], 140_000);
        // Zijia matched paid, nobody amended: Claude agrees once, disagrees once.
        $this->judgedSession('SF-50001-1794000002-2', 'match', [$m => 1], null, [$m => 1], 140_000);
        $this->judgedSession('SF-50001-1794000003-3', 'match', [$m => 2], null, [$m => 1], 140_000);
        // No final result: Zijia unsure, nobody amended.
        $this->judgedSession('SF-50001-1794000004-4', 'mixed', [$b => 1], null, [$b => 2], 140_000);
        // A billed call that failed still costs.
        $this->judgedSession('SF-50001-1794000005-5', 'match', [$b => 1], null, null, 40_000, 'failed');

        $s = ClaudeAccuracyEvaluation::evaluate(ClaudeRow::with('recognition')->orderBy('id')->get())['summary'];

        $this->assertSame([1, 1, 100.0], [$s['claude_right_person'], $s['person_sessions'], $s['claude_right_person_pct']]);
        $this->assertSame([0, 1, 0.0], [$s['zijia_right_person'], $s['zijia_person_sessions'], $s['zijia_right_person_pct']]);
        $this->assertSame([1, 2], [$s['claude_right_accepted'], $s['accepted_sessions']]);
        $this->assertSame([2, 3, 66.7], [$s['claude_right'], $s['judged_sessions'], $s['claude_right_pct']]);
        $this->assertSame(1, $s['unknown_sessions']);
        $this->assertSame([600_000, 5, 120_000], [$s['cost_usd_micros'], $s['calls'], $s['cost_per_call_usd_micros']]);
        $this->assertSame(300_000, $s['cost_per_correct_usd_micros'], 'US$0.60 spent for two right answers');
        $lines = ClaudeAccuracyEvaluation::reportLines(ClaudeAccuracyEvaluation::evaluate(ClaudeRow::with('recognition')->get()));
        $this->assertStringContainsString('US$0.3000 (S$0.39) per correct result', implode("\n", $lines));
    }

    public function test_the_evaluation_seeder_sends_nothing_until_switched_on_and_never_counts_a_session_twice(): void
    {
        $this->zijia();
        config(['smart_freezer.claude_recognition.enabled' => false]);
        $this->seed(\Database\Seeders\ClaudeFreezerEvaluationSeeder::class);
        $this->assertSame(0, ClaudeRow::count());

        config(['smart_freezer.claude_recognition.enabled' => true]);
        $this->counter->answers[] = $this->counted([['product_id' => $this->basque->id, 'taken' => 1, 'returned' => 0]]);
        $this->seed(\Database\Seeders\ClaudeFreezerEvaluationSeeder::class);
        $this->seed(\Database\Seeders\ClaudeFreezerEvaluationSeeder::class);

        $this->assertSame(1, ClaudeRow::count());
        $this->assertSame(ClaudeRow::STATUS_DONE, ClaudeRow::first()->status);
        $this->assertCount(1, $this->counter->catalogs, 'one billed call for one session');
        $this->assertNotEmpty(glob(storage_path('app/claude-eval/eval-*.csv')));
    }
}

class FakeFreezerCounter implements FreezerCounter
{
    public bool $configured = true;

    /** @var list<FreezerCount|CountFailed> */
    public array $answers = [];

    /** @var list<list<CatalogProduct>> */
    public array $catalogs = [];

    public function isConfigured(): bool
    {
        return $this->configured;
    }

    public function model(): string
    {
        return 'claude-opus-5-5';
    }

    public function count(array $frames, array $catalog, string $sessionLabel): FreezerCount
    {
        $this->catalogs[] = $catalog;
        $answer = array_shift($this->answers) ?? throw new \LogicException('no answer queued');
        if ($answer instanceof CountFailed) {
            throw $answer;
        }

        return $answer;
    }
}

class FakeDoorFrames implements DoorFrameSource
{
    public function framesFor(SmartFreezerRecognition $recognition): array
    {
        return ['frames' => [new DoorFrame(3, 0, 9000, 'a'), new DoorFrame(3, 8800, 9000, 'b'), new DoorFrame(4, 0, 9000, 'c'), new DoorFrame(4, 8800, 9000, 'd')], 'notes' => []];
    }
}
