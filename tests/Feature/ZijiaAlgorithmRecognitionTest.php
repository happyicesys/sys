<?php

namespace Tests\Feature;

use App\Jobs\SubmitFreezerRecognition;
use App\Models\Product;
use App\Models\SmartFreezerRecognition;
use App\Models\Vend;
use App\Models\VendChannel;
use App\Models\VendTransaction;
use App\Services\SmartFreezer\FreezerRecognitionService;
use App\Services\SmartFreezer\RecognitionVerdict;
use App\Services\SmartFreezer\Zijia\ZijiaSigner;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * A freezer door session's AI check, end to end: Zijia pushes the videos (with the IMEI), mark1
 * asks their algorithm with a signed envelope, the algorithm answers on the notify URL, and the
 * answer is held against the paid sale.
 */
class ZijiaAlgorithmRecognitionTest extends TestCase
{
    use RefreshDatabase;

    private const VIDEOS = '/api/smart-freezer/zijia/videos';

    private const NOTIFY = '/api/smart-freezer/zijia/algorithm/notify';

    private const SECRET = 'app-secret-under-test';

    private const IMEI = '861232069528880';

    private const BARCODE = '6925303751401';

    private Vend $vend;

    private Product $product;

    private int $epoch;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'smart_freezer.zijia.video_webhook_token' => 'push-token',
            'smart_freezer.zijia.algorithm' => [
                'base_url' => 'https://algo.test',
                'app_id' => '1789379222883159',
                'app_secret' => self::SECRET,
                'model_ids' => ['model-a'],
                'notify_url' => null,
                'auto_submit' => true,
                'timeout' => 5,
                'callback_verification' => 'log',
                'timezone' => 'Asia/Shanghai',
            ],
        ]);
        Http::fake(['https://algo.test/*' => Http::response(
            '{"code":0,"msg":"success","requestId":1849307985052983297,"remainIdentifyTime":12}', 200, ['Content-Type' => 'text/plain;charset=UTF-8'],
        )]);

        $this->vend = Vend::create(['code' => 50001, 'machine_type' => Vend::MACHINE_TYPE_SMART_FREEZER, 'is_active' => 1, 'operator_id' => 1]);
        $this->vend->forceFill(['freezer_control_status_json' => json_encode(['identity' => ['imei' => self::IMEI, 'deviceNo' => self::IMEI]])])->save();
        $this->product = Product::forceCreate(['code' => 'U-01', 'name' => 'Magnum', 'operator_id' => 1, 'barcode' => self::BARCODE]);
        VendChannel::create(['vend_id' => $this->vend->id, 'code' => 11, 'qty' => 4, 'capacity' => 5, 'amount' => 350,
            'product_id' => $this->product->id, 'is_active' => 1, 'error_rate_json' => []]);
        $this->epoch = Carbon::now()->timestamp;
    }

    private function push(array $body = []): void
    {
        $this->postJson(self::VIDEOS.'?token=push-token', array_merge([
            'imei' => self::IMEI,
            'tradeId' => 'ZSA769242319-251218135320244',
            'orderNo' => "SF-50001-{$this->epoch}-7",
            'videos' => [['camera' => 1, 'url' => 'https://oss.example.cn/cam1.mp4'], ['camera' => 2, 'url' => 'https://oss.example.cn/cam2.mp4']],
            'videoDuration' => 28,
        ], $body))->assertOk();
    }

    private function notify(array $biz, ?string $secret = self::SECRET): \Illuminate\Testing\TestResponse
    {
        $envelope = [
            'appId' => '1789379222883159', 'version' => 'v1', 'signType' => 'md5', 'timestamp' => '2026-09-27 12:00:00',
            'method' => 'cabinet.algorithm.order.result', 'bizContent' => json_encode($biz),
        ];
        $envelope['sign'] = (new ZijiaSigner($secret))->sign($envelope);

        return $this->postJson(self::NOTIFY, $envelope);
    }

    private function sale(int $units): VendTransaction
    {
        return VendTransaction::forceCreate([
            'order_id' => 'O-'.uniqid(), 'vend_id' => $this->vend->id, 'vend_channel_id' => 0, 'amount' => 350 * $units,
            'transaction_datetime' => Carbon::createFromTimestamp($this->epoch, config('app.timezone'))->addMinutes(2), 'gst_vat_rate' => 9,
            'is_multiple' => $units > 1, 'operator_id' => 1,
            'vend_transaction_json' => [
                'Type' => 'TRADE', 'SFREF' => "SF-50001-{$this->epoch}-7",
                'transf_info' => array_fill(0, $units, ['SId' => 11, 'SErr' => 0, 'goods_id' => $this->product->id]),
            ],
        ]);
    }

    public function test_a_push_is_matched_by_imei_and_submitted_with_a_signed_envelope(): void
    {
        $this->push(['orderNo' => null]);

        $recognition = SmartFreezerRecognition::sole();
        $this->assertSame($this->vend->id, $recognition->vend_id);
        $this->assertSame(SmartFreezerRecognition::STATUS_SUBMITTED, $recognition->status);
        $this->assertSame('1849307985052983297', $recognition->request_id);

        Http::assertSent(function (Request $request) {
            $envelope = json_decode($request->body(), true);
            $biz = json_decode($envelope['bizContent'], true);

            return $request->url() === 'https://algo.test/api/algorithm/api'
                && (new ZijiaSigner(self::SECRET))->verify($envelope)
                && $envelope['method'] === 'dynamic.cabinet.add.queue'
                && $envelope['appId'] === '1789379222883159'
                && $biz['goodsList'] === [['positions' => [], 'sn' => self::BARCODE]]
                && $biz['videoList'] === ['https://oss.example.cn/cam1.mp4', 'https://oss.example.cn/cam2.mp4']
                && $biz['tradeId'] === 'ZSA769242319-251218135320244'
                && $biz['deviceId'] === self::IMEI
                && $biz['modelIdList'] === ['model-a']
                && $biz['videoDuration'] === 28
                && $biz['notifyUrl'] === route('smart-freezer.zijia.algorithm.notify');
        });
    }

    public function test_the_result_is_held_against_the_paid_sale(): void
    {
        $this->push();
        $sale = $this->sale(1);

        $this->notify(['tradeId' => 'ZSA769242319-251218135320244', 'orderStatus' => 0, 'items' => [['code' => self::BARCODE, 'number' => 2]]])
            ->assertOk()->assertExactJson(['status' => 200, 'body' => 'SUCCESS']);

        $recognition = SmartFreezerRecognition::sole();
        $this->assertSame(SmartFreezerRecognition::STATUS_COMPLETED, $recognition->status);
        $this->assertTrue($recognition->callback_verified);
        $this->assertSame($sale->id, $recognition->vend_transaction_id);
        $this->assertSame(RecognitionVerdict::TOOK_MORE, $recognition->verdict);
        // assertEquals: MySQL's JSON type reorders object keys; the values are what matter.
        $this->assertEquals([['product_id' => $this->product->id, 'code' => self::BARCODE, 'paid' => 1, 'taken' => 2, 'delta' => 1]], $recognition->verdict_lines);
    }

    public function test_a_sale_that_lands_after_the_result_is_linked_when_re_evaluated(): void
    {
        $this->push();
        $this->notify(['tradeId' => 'ZSA769242319-251218135320244', 'orderStatus' => 0, 'items' => [['code' => self::BARCODE, 'number' => 1]]]);

        $recognition = SmartFreezerRecognition::sole();
        $this->assertNull($recognition->verdict);
        $this->assertStringStartsWith('sale not found yet', $recognition->status_reason);

        $sale = $this->sale(1);
        app(FreezerRecognitionService::class)->evaluate($recognition->fresh());

        $this->assertSame(RecognitionVerdict::MATCH, $recognition->fresh()->verdict);
        $this->assertSame($sale->id, $recognition->fresh()->vend_transaction_id);
    }

    public function test_an_algorithm_failure_status_is_recorded_without_a_verdict(): void
    {
        $this->push();
        $this->notify(['tradeId' => 'ZSA769242319-251218135320244', 'orderStatus' => 401, 'errorMessage' => 'video lost', 'items' => []]);

        $recognition = SmartFreezerRecognition::sole();
        $this->assertSame(SmartFreezerRecognition::STATUS_FAILED, $recognition->status);
        $this->assertSame('algorithm: video error / frames lost', $recognition->status_reason);
        $this->assertNull($recognition->verdict);
    }

    public function test_nothing_is_submitted_while_something_is_missing_and_the_row_says_what(): void
    {
        $this->product->forceFill(['barcode' => null])->save();
        $this->push();

        $recognition = SmartFreezerRecognition::sole();
        $this->assertSame(SmartFreezerRecognition::STATUS_PENDING, $recognition->status);
        $this->assertSame("none of this freezer's products has a barcode", $recognition->status_reason);
        Http::assertNothingSent();

        config(['smart_freezer.zijia.algorithm.model_ids' => []]);
        $this->assertSame('no algorithm model id configured (ZIJIA_ALGO_MODEL_IDS)', app(FreezerRecognitionService::class)->blocker($recognition));
    }

    public function test_auto_submit_off_leaves_it_pending_and_a_recognition_is_never_spent_twice(): void
    {
        config(['smart_freezer.zijia.algorithm.auto_submit' => false]);
        $this->push();
        $this->push(); // the same door session pushed again: still one row
        Http::assertNothingSent();

        $recognition = SmartFreezerRecognition::sole();
        $this->assertSame(SmartFreezerRecognition::STATUS_PENDING, $recognition->status);
        $this->assertNull($recognition->status_reason);

        $service = app(FreezerRecognitionService::class);
        $service->submit($recognition);
        $service->submit($recognition->fresh());
        Http::assertSentCount(1);
        $this->assertSame(SmartFreezerRecognition::STATUS_SUBMITTED, $recognition->fresh()->status);
    }

    public function test_a_session_pushed_once_per_camera_is_submitted_with_every_camera(): void
    {
        config(['smart_freezer.zijia.algorithm.auto_submit' => false]);
        $this->push(['videos' => [['camera' => 1, 'url' => 'https://oss.example.cn/cam1.mp4']], 'videoDuration' => 20]);
        $this->push(['videos' => [['camera' => 2, 'url' => 'https://oss.example.cn/cam2.mp4']], 'videoDuration' => 28]);

        $recognition = SmartFreezerRecognition::sole();
        $this->assertCount(2, $recognition->videos);
        app(FreezerRecognitionService::class)->submit($recognition);

        $this->assertSame(['https://oss.example.cn/cam1.mp4', 'https://oss.example.cn/cam2.mp4'], $recognition->fresh()->request_payload['videoList']);
        $this->assertSame(28, $recognition->fresh()->request_payload['videoDuration']);
    }

    public function test_auto_submit_waits_the_settle_window_for_the_other_cameras(): void
    {
        config(['smart_freezer.zijia.algorithm.submit_delay_seconds' => 90]);
        Queue::fake();
        $this->push();

        Queue::assertPushed(SubmitFreezerRecognition::class, function (SubmitFreezerRecognition $job) {
            return $job->recognitionId === SmartFreezerRecognition::sole()->id
                && $job->delay !== null
                && abs(Carbon::now()->addSeconds(90)->diffInSeconds($job->delay)) <= 2;
        });
    }

    public function test_a_badly_signed_callback_is_refused_in_enforce_and_flagged_in_log(): void
    {
        $this->push();
        $biz = ['tradeId' => 'ZSA769242319-251218135320244', 'orderStatus' => 0, 'items' => []];

        config(['smart_freezer.zijia.algorithm.callback_verification' => 'enforce']);
        $this->notify($biz, 'wrong-key')->assertExactJson(['status' => 500, 'body' => 'sign verification failed']);
        $this->assertSame(SmartFreezerRecognition::STATUS_SUBMITTED, SmartFreezerRecognition::sole()->status);

        config(['smart_freezer.zijia.algorithm.callback_verification' => 'log']);
        $this->notify($biz, 'wrong-key')->assertExactJson(['status' => 200, 'body' => 'SUCCESS']);
        $this->assertFalse(SmartFreezerRecognition::sole()->callback_verified);
    }

    public function test_a_result_for_an_unknown_trade_is_kept_not_lost(): void
    {
        $this->notify(['tradeId' => 'NOT-OURS-1', 'orderStatus' => 0, 'items' => [['code' => '1', 'number' => 1]]])
            ->assertExactJson(['status' => 200, 'body' => 'SUCCESS']);

        $orphan = SmartFreezerRecognition::sole();
        $this->assertSame('NOT-OURS-1', $orphan->trade_id);
        $this->assertNull($orphan->vend_id);
        $this->assertSame(['1' => 1], $orphan->items);
    }

    // ------------------------------------------------------------ review fixes (2026-09-28)

    public function test_pushes_of_one_session_share_one_row_even_when_only_one_names_the_freezer(): void
    {
        config(['smart_freezer.zijia.algorithm.auto_submit' => false]);
        // Camera 2 first, carrying nothing that names the freezer; camera 1 then carries the IMEI.
        $this->push(['imei' => null, 'orderNo' => null, 'videos' => [['camera' => 2, 'url' => 'https://oss.example.cn/cam2.mp4']]]);
        $this->assertNull(SmartFreezerRecognition::sole()->vend_id);
        $this->push(['orderNo' => null, 'videos' => [['camera' => 1, 'url' => 'https://oss.example.cn/cam1.mp4']]]);

        $recognition = SmartFreezerRecognition::sole();
        $this->assertSame($this->vend->id, $recognition->vend_id);
        $this->assertCount(2, $recognition->videos);
    }

    public function test_a_paid_product_without_a_barcode_is_incomplete_not_a_refund(): void
    {
        $plain = Product::forceCreate(['code' => 'U-99', 'name' => 'No barcode yet', 'operator_id' => 1]);
        $this->push();
        $sale = $this->sale(1);
        $frame = $sale->vend_transaction_json;
        $frame['transf_info'][] = ['SId' => 12, 'SErr' => 0, 'goods_id' => $plain->id];
        $sale->forceFill(['vend_transaction_json' => $frame])->save();

        $this->notify(['tradeId' => 'ZSA769242319-251218135320244', 'orderStatus' => 0, 'items' => [['code' => self::BARCODE, 'number' => 1]]]);

        $recognition = SmartFreezerRecognition::sole();
        $this->assertSame(RecognitionVerdict::INCOMPLETE, $recognition->verdict);
        $this->assertStringContainsString((string) $plain->id, $recognition->status_reason);
    }

    public function test_a_sale_from_a_board_with_a_broken_clock_is_found_on_our_clock(): void
    {
        $this->push();
        // The board's clock was years off, so mark1 booked the TRADE at arrival — nowhere near the
        // epoch inside the session ref.
        $this->epoch = Carbon::parse('2001-01-01 10:00:00')->timestamp;
        $sale = $this->sale(1);
        $sale->forceFill(['vend_transaction_json' => array_merge($sale->vend_transaction_json, ['SFREF' => 'SF-50001-'.Carbon::now()->timestamp.'-7'])])->save();
        $sale->forceFill(['transaction_datetime' => Carbon::now()->addYears(3)])->save();

        $this->notify(['tradeId' => 'ZSA769242319-251218135320244', 'orderStatus' => 0, 'items' => [['code' => self::BARCODE, 'number' => 1]]]);

        $this->assertSame($sale->id, SmartFreezerRecognition::sole()->vend_transaction_id);
    }

    public function test_the_sweep_judges_a_result_once_its_late_sale_arrives(): void
    {
        $this->push();
        $this->notify(['tradeId' => 'ZSA769242319-251218135320244', 'orderStatus' => 0, 'items' => [['code' => self::BARCODE, 'number' => 1]]]);
        $this->assertNull(SmartFreezerRecognition::sole()->verdict);

        $this->sale(1);
        $this->artisan('smart-freezer:zijia-evaluate-pending')->assertSuccessful();

        $this->assertSame(RecognitionVerdict::MATCH, SmartFreezerRecognition::sole()->verdict);
    }

    public function test_an_unverified_result_cannot_overwrite_a_verified_one_or_invent_a_trade(): void
    {
        $this->push();
        $good = ['tradeId' => 'ZSA769242319-251218135320244', 'orderStatus' => 0, 'items' => [['code' => self::BARCODE, 'number' => 1]]];
        $this->notify($good);
        $this->assertTrue(SmartFreezerRecognition::sole()->callback_verified);

        // Forged (log mode lets it through the door): it must not replace the verified answer.
        $this->notify(['tradeId' => $good['tradeId'], 'orderStatus' => 0, 'items' => [['code' => self::BARCODE, 'number' => 9]]], 'forged')
            ->assertExactJson(['status' => 200, 'body' => 'SUCCESS']);
        $this->assertSame([self::BARCODE => 1], SmartFreezerRecognition::sole()->items);

        // Nor may it create a row for a trade mark1 never sent.
        $this->notify(['tradeId' => 'INVENTED-1', 'orderStatus' => 0, 'items' => []], 'forged');
        $this->assertSame(1, SmartFreezerRecognition::count());
    }

    public function test_a_failed_submission_is_resent_only_on_request(): void
    {
        config(['smart_freezer.zijia.algorithm.auto_submit' => false]);
        // Fake stubs match in registration order: drop setUp's always-succeeds stub first.
        Http::swap(new \Illuminate\Http\Client\Factory);
        Http::fake(['https://algo.test/*' => Http::sequence()
            ->push('{"code":500,"msg":"系统繁忙"}', 200)
            ->push('{"code":0,"msg":"success","requestId":42}', 200)]);
        $this->push();
        $recognition = SmartFreezerRecognition::sole();
        $service = app(FreezerRecognitionService::class);

        $service->submit($recognition);
        $this->assertSame(SmartFreezerRecognition::STATUS_FAILED, $recognition->fresh()->status);
        $this->assertSame('submit refused: 系统繁忙', $recognition->fresh()->status_reason);

        // Submitting a failed row does nothing, and leaves its reason alone.
        $service->submit($recognition->fresh());
        Http::assertSentCount(1);
        $this->assertSame('submit refused: 系统繁忙', $recognition->fresh()->status_reason);

        $this->artisan('smart-freezer:zijia-recognition', ['id' => $recognition->id, '--retry' => true, '--submit' => true])->assertSuccessful();
        Http::assertSentCount(2);
        $this->assertSame(SmartFreezerRecognition::STATUS_SUBMITTED, $recognition->fresh()->status);
        $this->assertSame('42', $recognition->fresh()->request_id);
    }

    public function test_a_sync_queue_submits_at_once_instead_of_waiting_forever(): void
    {
        // A settle window on a queue that cannot delay must not re-queue itself in a loop.
        config(['smart_freezer.zijia.algorithm.submit_delay_seconds' => 60]);
        $this->push();

        Http::assertSentCount(1);
        $this->assertSame(SmartFreezerRecognition::STATUS_SUBMITTED, SmartFreezerRecognition::sole()->status);
    }
}
