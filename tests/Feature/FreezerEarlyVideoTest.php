<?php

namespace Tests\Feature;

use App\Jobs\FetchEarlyFreezerVideo;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\SmartFreezerRecognition;
use App\Models\SmartFreezerVideo;
use App\Models\Vend;
use App\Models\VendChannel;
use App\Models\VendChannelError;
use App\Models\VendTransaction;
use App\Services\SmartFreezer\FreezerRecognitionService;
use App\Services\SmartFreezer\Zijia\EarlyOrderVideo;
use App\Services\VendTransactionService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * The order video is on Zijia's OSS ~1 s after the door closes, but their push reaches us ~5 min 50 s
 * later (Brian, 2026-11-09). mark1 looks for the file itself when the TRADE lands and opens the AI
 * check as soon as it is there; the push stays the fallback and lands on the same recognition.
 */
class FreezerEarlyVideoTest extends TestCase
{
    use RefreshDatabase;

    private const VIDEOS = '/api/smart-freezer/zijia/videos';

    private const IMEI = '861232069528880';

    private const OSS = 'https://jishi1.oss-cn-hangzhou.aliyuncs.com';

    private const OLD = 'SF-50001-1794194827-1';

    private const REF = 'SF-50001-1794196595-2';

    private Vend $vend;

    private Product $product;

    /** @var array<string, bool> object path => present on OSS */
    private array $oss = [];

    /** @var list<array<string, mixed>> what the algorithm was asked */
    private array $algoCalls = [];

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-09 12:00:00');
        config([
            'smart_freezer.zijia.video_webhook_token' => 'push-token',
            'smart_freezer.zijia.algorithm' => [
                'base_url' => 'https://algo.test', 'app_id' => '1789379222883159', 'app_secret' => 'app-secret-under-test',
                'model_ids' => [], 'notify_url' => null, 'auto_submit' => true, 'submit_delay_seconds' => 5,
                'timeout' => 5, 'callback_verification' => 'log', 'timezone' => 'Asia/Shanghai',
            ],
            'smart_freezer.zijia.early_video.vends' => ['50001'],
            // Most tests put only one camera on OSS; the grace for the other has its own tests.
            'smart_freezer.zijia.early_video.camera_grace_seconds' => 0,
        ]);
        Http::fake(function (Request $request) {
            if (str_starts_with($request->url(), 'https://algo.test')) {
                $this->algoCalls[] = json_decode((string) ($request->data()['bizContent'] ?? '{}'), true);

                return Http::response('{"code":0,"msg":"success","requestId":1849307985052983297}', 200);
            }
            if (str_starts_with($request->url(), self::OSS)) {
                $this->assertSame('HEAD', $request->method());

                return Http::response('', ($this->oss[parse_url($request->url(), PHP_URL_PATH)] ?? false) ? 200 : 404);
            }

            return Http::response('unexpected', 500);
        });

        $this->vend = Vend::create(['code' => 50001, 'machine_type' => Vend::MACHINE_TYPE_SMART_FREEZER, 'is_active' => 1, 'operator_id' => 1]);
        $this->product = Product::forceCreate(['code' => 'CC-01', 'name' => 'Basque Cheesecake', 'operator_id' => 1, 'barcode' => '9726436016148']);
        VendChannel::create(['vend_id' => $this->vend->id, 'code' => 11, 'qty' => 4, 'capacity' => 5, 'amount' => 350,
            'product_id' => $this->product->id, 'is_active' => 1, 'error_rate_json' => []]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private static function path(string $order, int $camera): string
    {
        return '/boxapp/'.self::IMEI."/order/{$order}/{$order}-d1c{$camera}-25f-1280x720.mp4";
    }

    /** Zijia's live push body, byte for byte in shape (2026-11-09, video row 109). */
    private function push(string $order, array $cameras = [3], string $query = ''): void
    {
        $body = ['sign' => 'ABC', 'doorId' => 1, 'method' => 'video', 'orderNo' => $order, 'deviceNo' => self::IMEI, 'pullDoor' => 1];
        foreach (array_values($cameras) as $i => $camera) {
            $body['video'.($i + 1)] = ['videoUrl' => self::OSS.self::path($order, $camera).$query, 'videoSize' => 0, 'videoFrames' => 0, 'videoDuration' => 0];
        }
        $this->postJson(self::VIDEOS.'?token=push-token', $body)->assertOk();
    }

    private function fetch(string $order = self::REF, int $giveUpIn = 60): string
    {
        return app(EarlyOrderVideo::class)->fetch($this->vend->id, $order, Carbon::now()->timestamp + $giveUpIn);
    }

    private function sale(array $frame): VendTransaction
    {
        return VendTransaction::forceCreate([
            'order_id' => 'O-'.uniqid(), 'vend_id' => $this->vend->id, 'vend_channel_id' => 0, 'amount' => 350,
            'transaction_datetime' => Carbon::now(), 'gst_vat_rate' => 9, 'is_multiple' => false,
            'operator_id' => 1, 'vend_transaction_json' => $frame,
        ]);
    }

    private function algoCallsFor(string $tradeId): array
    {
        return array_values(array_filter($this->algoCalls, fn ($c) => ($c['tradeId'] ?? null) === $tradeId));
    }

    public function test_the_video_found_on_oss_opens_the_ai_check_and_the_later_push_only_confirms_it(): void
    {
        $this->push(self::OLD);                       // the machine's last push = the link pattern
        $this->oss[self::path(self::REF, 3)] = true;  // the file is there a second after the door closed

        $this->assertSame('found', $this->fetch());

        $early = SmartFreezerVideo::where('order_no', self::REF)->sole();
        $this->assertTrue($early->isEarlyFetch());
        $this->assertSame([self::OSS.self::path(self::REF, 3)], $early->video_urls);
        $recognition = SmartFreezerRecognition::where('trade_id', self::REF)->sole();
        $this->assertSame(self::REF, $recognition->session_ref);
        $this->assertSame($this->vend->id, (int) $recognition->vend_id);
        $this->assertSame(SmartFreezerRecognition::STATUS_SUBMITTED, $recognition->status);

        // The algorithm is asked exactly what the push would have asked it.
        [$call] = $this->algoCallsFor(self::REF);
        $this->assertSame(self::IMEI, $call['deviceId']);
        $this->assertSame([self::OSS.self::path(self::REF, 3)], $call['videoList']);
        $this->assertSame(1, $call['doorId']);
        $this->assertSame([['positions' => [], 'sn' => '9726436016148']], $call['goodsList']);

        // ~6 min later Zijia's push lands on the same session: no second call, no second row.
        Log::spy();
        $this->push(self::REF);
        $this->assertSame(1, SmartFreezerRecognition::where('trade_id', self::REF)->count());
        $this->assertCount(1, $this->algoCallsFor(self::REF));
        $this->assertSame(2, $recognition->videos()->count());
        Log::shouldHaveReceived('info')->withArgs(fn ($message) => $message === 'zijia push confirmed an early-fetched session')->once();
    }

    public function test_a_push_naming_a_video_the_early_fetch_did_not_send_is_logged_as_a_warning(): void
    {
        $this->push(self::OLD);
        $this->oss[self::path(self::REF, 3)] = true;
        $this->assertSame('found', $this->fetch());

        Log::spy();
        $this->push(self::REF, [3, 5]);
        Log::shouldHaveReceived('warning')->withArgs(fn ($message, $context = []) => $message === 'zijia push names videos the early fetch did not send'
            && $context['missed'] === [self::OSS.self::path(self::REF, 5)])->once();
    }

    public function test_not_there_yet_waits_and_gives_up_at_the_deadline_leaving_the_push_to_work_as_before(): void
    {
        $this->push(self::OLD);

        $this->assertSame('waiting', $this->fetch());
        $this->assertSame('gave_up', $this->fetch(giveUpIn: 0));
        $this->assertFalse(SmartFreezerRecognition::where('trade_id', self::REF)->exists());
        $this->assertFalse(SmartFreezerVideo::where('order_no', self::REF)->exists());

        $this->push(self::REF);
        $this->assertSame(SmartFreezerRecognition::STATUS_SUBMITTED, SmartFreezerRecognition::where('trade_id', self::REF)->sole()->status);
        $this->assertCount(1, $this->algoCallsFor(self::REF));
    }

    public function test_a_session_the_push_already_opened_is_left_alone_without_asking_oss(): void
    {
        $this->push(self::OLD);
        $this->push(self::REF);
        Http::assertSentCount(2); // the two submits; no HEAD yet

        $this->assertSame('done', $this->fetch());
        $this->assertSame(1, SmartFreezerVideo::where('order_no', self::REF)->count());
        Http::assertNotSent(fn (Request $r) => $r->method() === 'HEAD');
    }

    public function test_both_cameras_there_are_sent_together_at_once(): void
    {
        $this->push(self::OLD, [3, 4]);
        $this->oss[self::path(self::REF, 3)] = true;
        $this->oss[self::path(self::REF, 4)] = true;

        $this->assertSame('found', $this->fetch());
        $this->assertSame([self::OSS.self::path(self::REF, 3), self::OSS.self::path(self::REF, 4)],
            SmartFreezerVideo::where('order_no', self::REF)->sole()->video_urls);
        [$call] = $this->algoCallsFor(self::REF);
        $this->assertSame([self::OSS.self::path(self::REF, 3), self::OSS.self::path(self::REF, 4)], $call['videoList']);
    }

    public function test_a_camera_that_lands_within_the_grace_is_waited_for(): void
    {
        config(['smart_freezer.zijia.early_video.camera_grace_seconds' => 10]);
        $this->push(self::OLD, [3]);
        $this->oss[self::path(self::REF, 3)] = true;
        $this->assertSame('waiting', $this->fetch());

        Carbon::setTestNow(Carbon::now()->addSeconds(5));
        $this->oss[self::path(self::REF, 4)] = true;
        $this->assertSame('found', $this->fetch());
        $this->assertSame([self::OSS.self::path(self::REF, 3), self::OSS.self::path(self::REF, 4)],
            SmartFreezerVideo::where('order_no', self::REF)->sole()->video_urls);
    }

    /**
     * 2026-11-10, 50001: the push before carried cameras 3 + 4 (camera 4 briefly back), so the next
     * sale waited 90 s for a camera-4 file that never came, gave up, and waited ~6 min for Zijia.
     */
    public function test_a_camera_the_last_push_had_but_that_is_offline_now_does_not_hold_the_sale(): void
    {
        config(['smart_freezer.zijia.early_video.camera_grace_seconds' => 10]);
        $this->push(self::OLD, [3, 4], '?door=unopened');
        $this->oss[self::path(self::REF, 3)] = true;

        $this->assertSame('waiting', $this->fetch());
        Carbon::setTestNow(Carbon::now()->addSeconds(5));
        $this->assertSame('waiting', $this->fetch());
        $this->assertSame([], $this->algoCallsFor(self::REF));

        Carbon::setTestNow(Carbon::now()->addSeconds(5));
        $this->assertSame('found', $this->fetch());
        $this->assertSame([self::OSS.self::path(self::REF, 3)], SmartFreezerVideo::where('order_no', self::REF)->sole()->video_urls);
        [$call] = $this->algoCallsFor(self::REF);
        $this->assertSame([self::OSS.self::path(self::REF, 3)], $call['videoList']);

        // Zijia's push names camera 3 only, as on 11-10: it just confirms.
        Log::spy();
        $this->push(self::REF, [3]);
        $this->assertCount(1, $this->algoCallsFor(self::REF));
        Log::shouldHaveReceived('info')->withArgs(fn ($message) => $message === 'zijia push confirmed an early-fetched session')->once();
    }

    public function test_the_give_up_time_cuts_the_grace_short_and_sends_what_is_there(): void
    {
        config(['smart_freezer.zijia.early_video.camera_grace_seconds' => 30]);
        $this->push(self::OLD, [3, 4]);
        $this->oss[self::path(self::REF, 4)] = true;

        $this->assertSame('found', $this->fetch(giveUpIn: 0));
        $this->assertSame([self::OSS.self::path(self::REF, 4)], SmartFreezerVideo::where('order_no', self::REF)->sole()->video_urls);
    }

    public function test_a_camera_back_online_is_sent_too_in_camera_order(): void
    {
        $this->push(self::OLD, [4]);
        $this->oss[self::path(self::REF, 3)] = true;
        $this->oss[self::path(self::REF, 4)] = true;

        $this->assertSame('found', $this->fetch());
        $this->assertSame([self::OSS.self::path(self::REF, 3), self::OSS.self::path(self::REF, 4)],
            SmartFreezerVideo::where('order_no', self::REF)->sole()->video_urls);
    }

    public function test_the_unopened_marker_of_the_template_is_dropped_and_its_last_real_push_is_the_pattern(): void
    {
        $this->push(self::OLD, [3], '?door=unopened');
        $this->oss[self::path(self::REF, 3)] = true;
        $this->assertSame('found', $this->fetch());
        $this->assertSame([self::OSS.self::path(self::REF, 3)], SmartFreezerVideo::where('order_no', self::REF)->sole()->video_urls);

        // The next order: the latest row is an early one, never a pattern — the push before it is.
        $next = 'SF-50001-1794199999-3';
        $this->oss[self::path($next, 3)] = true;
        $this->assertSame('found', $this->fetch($next));
        $this->assertSame([self::OSS.self::path($next, 3)], SmartFreezerVideo::where('order_no', $next)->sole()->video_urls);
    }

    public function test_no_link_is_built_from_a_foreign_host_an_old_push_or_no_push(): void
    {
        $this->assertSame('no_template', $this->fetch());

        $this->postJson(self::VIDEOS.'?token=push-token', ['orderNo' => self::OLD, 'deviceNo' => self::IMEI,
            'video1' => ['videoUrl' => 'https://evil.example.com'.self::path(self::OLD, 3)]])->assertOk();
        $this->assertSame('no_template', $this->fetch());

        SmartFreezerVideo::query()->delete();
        $this->push(self::OLD);
        Carbon::setTestNow(Carbon::now()->addDays(15));
        $this->assertSame('no_template', $this->fetch());

        Http::assertNotSent(fn (Request $r) => $r->method() === 'HEAD');
        $this->assertFalse(SmartFreezerVideo::where('order_no', self::REF)->exists());
    }

    public function test_a_push_in_before_the_send_is_what_gets_sent(): void
    {
        config(['smart_freezer.zijia.algorithm.auto_submit' => false]);
        $this->push(self::OLD);
        $this->oss[self::path(self::REF, 3)] = true;
        $this->assertSame('found', $this->fetch());
        $this->push(self::REF, [3], '?door=unopened');

        $recognition = SmartFreezerRecognition::where('trade_id', self::REF)->sole();
        $this->assertSame([self::OSS.self::path(self::REF, 3).'?door=unopened'], app(FreezerRecognitionService::class)->videoUrls($recognition));
    }

    public function test_the_trade_starts_the_search_for_the_door_try_that_opened_on_listed_freezers_only(): void
    {
        Queue::fake([FetchEarlyFreezerVideo::class]);

        EarlyOrderVideo::afterTrade($this->vend, $this->sale(['SFREF' => self::REF, 'SFORD' => self::REF.'R2']));
        Queue::assertPushed(FetchEarlyFreezerVideo::class, fn ($job) => $job->orderRef === self::REF.'R2' && $job->vendId === $this->vend->id
            && $job->giveUpAt === Carbon::now()->timestamp + 90);

        // Another machine's ref, no ref, an old replayed TRADE, an unlisted machine, auto-submit off: nothing.
        EarlyOrderVideo::afterTrade($this->vend, $this->sale(['SFREF' => 'SF-50002-1794196595-2']));
        EarlyOrderVideo::afterTrade($this->vend, $this->sale(['Type' => 'TRADE']));
        $old = $this->sale(['SFREF' => self::REF]);
        $old->transaction_datetime = Carbon::now()->subHour();
        EarlyOrderVideo::afterTrade($this->vend, $old);
        config(['smart_freezer.zijia.early_video.vends' => ['50002']]);
        EarlyOrderVideo::afterTrade($this->vend, $this->sale(['SFREF' => self::REF]));
        config(['smart_freezer.zijia.early_video.vends' => ['*'], 'smart_freezer.zijia.algorithm.auto_submit' => false]);
        EarlyOrderVideo::afterTrade($this->vend, $this->sale(['SFREF' => self::REF]));
        Queue::assertPushed(FetchEarlyFreezerVideo::class, 1);

        // A vending machine is never a candidate, even under "*".
        config(['smart_freezer.zijia.algorithm.auto_submit' => true]);
        $vending = Vend::create(['code' => 2031, 'is_active' => 1, 'operator_id' => 1]);
        $this->assertFalse(EarlyOrderVideo::enabledFor($vending));
        $this->assertTrue(EarlyOrderVideo::enabledFor($this->vend));
    }

    public function test_the_real_trade_ingest_starts_the_search(): void
    {
        Queue::fake([FetchEarlyFreezerVideo::class]);
        PaymentMethod::firstOrCreate(['code' => 0], ['name' => 'Cash', 'is_active' => true]);
        PaymentMethod::firstOrCreate(['code' => 2], ['name' => 'Credit Card', 'is_active' => true]);
        VendChannelError::firstOrCreate(['code' => 0], ['desc' => 'No Malfunction (0)']);

        app(VendTransactionService::class)->create($this->vend->fresh(), [
            'ORDRID' => 'SFA'.uniqid(), 'PAY_TYPE' => 2, 'TIME' => Carbon::now()->format('Y-m-d H:i:s'),
            'SErr' => 0, 'SId' => 11, 'Price' => '3.50', 'TXN_SRC' => 1, 'SFREF' => self::REF,
            'transf_info' => [['SId' => 11, 'SErr' => 0, 'Price' => 350, 'goods_id' => $this->product->id]],
        ]);

        $this->assertTrue(VendTransaction::where('vend_id', $this->vend->id)->exists());
        Queue::assertPushed(FetchEarlyFreezerVideo::class, fn ($job) => $job->orderRef === self::REF);
    }

    public function test_the_job_looks_again_until_found(): void
    {
        $this->push(self::OLD);
        Queue::fake([FetchEarlyFreezerVideo::class]);

        // On a real queue a miss queues the next look; the sync driver cannot wait and does not.
        $job = new FetchEarlyFreezerVideo($this->vend->id, self::REF, Carbon::now()->timestamp + 60);
        $job->withFakeQueueInteractions();
        $job->handle(app(EarlyOrderVideo::class));
        Queue::assertPushed(FetchEarlyFreezerVideo::class, 1);

        $this->oss[self::path(self::REF, 3)] = true;
        $job->handle(app(EarlyOrderVideo::class));
        Queue::assertPushed(FetchEarlyFreezerVideo::class, 1);
        $this->assertTrue(SmartFreezerRecognition::where('trade_id', self::REF)->exists());
    }
}
