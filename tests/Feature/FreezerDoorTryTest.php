<?php

namespace Tests\Feature;

use App\Models\FreezerFailedSession;
use App\Models\Product;
use App\Models\SmartFreezerRecognition;
use App\Models\User;
use App\Models\Vend;
use App\Models\VendChannel;
use App\Models\VendTransaction;
use App\Services\SmartFreezer\FreezerRecognitionService;
use App\Services\SmartFreezer\RecognitionVerdict;
use App\Services\SmartFreezer\Zijia\ZijiaSigner;
use App\Services\SmartFreezer\Zijia\ZijiaVideoPush;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Freezer app 54+ tries a lock that will not open up to five times, each try a new host order
 * "<SFREF>R<n>" (Brian, 2026-11-08). Every try's video is pushed, but only the try whose door opened
 * may reach the AI or judge the sale: a shut-lid try answers "nothing taken", which would release
 * the sale's card hold.
 */
class FreezerDoorTryTest extends TestCase
{
    use RefreshDatabase;

    private const VIDEOS = '/api/smart-freezer/zijia/videos';

    private const NOTIFY = '/api/smart-freezer/zijia/algorithm/notify';

    private const SECRET = 'app-secret-under-test';

    private const IMEI = '861232069528880';

    private const BARCODE = '9726436016148';

    private Vend $vend;

    private Product $product;

    private string $ref;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'smart_freezer.zijia.video_webhook_token' => 'push-token',
            'smart_freezer.zijia.algorithm' => [
                'base_url' => 'https://algo.test',
                'app_id' => '1789379222883159',
                'app_secret' => self::SECRET,
                'model_ids' => [],
                'notify_url' => null,
                'auto_submit' => true,
                'submit_delay_seconds' => 5,
                'timeout' => 5,
                'callback_verification' => 'log',
                'timezone' => 'Asia/Shanghai',
            ],
        ]);
        Http::fake(['https://algo.test/*' => Http::response('{"code":0,"msg":"success","requestId":1849307985052983297}', 200)]);

        $this->vend = Vend::create(['code' => 50001, 'machine_type' => Vend::MACHINE_TYPE_SMART_FREEZER, 'is_active' => 1, 'operator_id' => 1]);
        $this->vend->forceFill(['freezer_control_status_json' => json_encode(['identity' => ['imei' => self::IMEI]])])->save();
        $this->product = Product::forceCreate(['code' => 'CC-01', 'name' => 'Basque Cheesecake', 'operator_id' => 1, 'barcode' => self::BARCODE]);
        VendChannel::create(['vend_id' => $this->vend->id, 'code' => 11, 'qty' => 4, 'capacity' => 5, 'amount' => 350,
            'product_id' => $this->product->id, 'is_active' => 1, 'error_rate_json' => []]);
        $this->ref = 'SF-50001-'.Carbon::now()->timestamp.'-8';
    }

    /** A live push: their body names our order ref as `orderNo` and nothing else. */
    private function push(string $orderNo): void
    {
        $this->postJson(self::VIDEOS.'?token=push-token', [
            'imei' => self::IMEI,
            'orderNo' => $orderNo,
            'videos' => [['camera' => 3, 'url' => "https://oss.example.cn/{$orderNo}-d1c3.mp4"]],
            'videoDuration' => 12,
        ])->assertOk();
    }

    private function sale(?string $opened): VendTransaction
    {
        $frame = ['Type' => 'TRADE', 'SFREF' => $this->ref, 'transf_info' => [['SId' => 11, 'SErr' => 0, 'goods_id' => $this->product->id]]];
        if ($opened !== null) {
            $frame['SFORD'] = $opened;
        }

        return VendTransaction::forceCreate([
            'order_id' => 'O-'.uniqid(), 'vend_id' => $this->vend->id, 'vend_channel_id' => 0, 'amount' => 350,
            'transaction_datetime' => Carbon::now()->addMinute(), 'gst_vat_rate' => 9, 'is_multiple' => false,
            'operator_id' => 1, 'vend_transaction_json' => $frame,
        ]);
    }

    private function notify(string $tradeId, int $units): void
    {
        $envelope = [
            'appId' => '1789379222883159', 'version' => 'v1', 'signType' => 'md5', 'timestamp' => '2026-11-08 21:00:00',
            'method' => 'cabinet.algorithm.order.result',
            'bizContent' => json_encode(['tradeId' => $tradeId, 'orderStatus' => 0,
                'items' => $units > 0 ? [['code' => self::BARCODE, 'number' => $units]] : []]),
        ];
        $envelope['sign'] = (new ZijiaSigner(self::SECRET))->sign($envelope);
        $this->postJson(self::NOTIFY, $envelope)->assertOk();
    }

    private function row(string $tradeId): SmartFreezerRecognition
    {
        return SmartFreezerRecognition::where('trade_id', $tradeId)->sole();
    }

    public function test_the_push_names_the_sale_and_the_try_apart(): void
    {
        $push = ZijiaVideoPush::fromPayload(['orderNo' => 'SF-50001-1794142471-8R3', 'imei' => self::IMEI]);
        $this->assertSame('SF-50001-1794142471-8', $push->sessionRef);
        $this->assertSame('SF-50001-1794142471-8R3', $push->orderRef);
        $this->assertSame('SF-50001-1794142471-8R3', $push->tradeId);
        $this->assertSame(3, $push->doorTry);
        $this->assertSame('50001', $push->sessionVendCode());

        $plain = ZijiaVideoPush::fromPayload(['orderNo' => 'SF-50001-1794142471-8']);
        $this->assertSame('SF-50001-1794142471-8', $plain->sessionRef);
        $this->assertSame('SF-50001-1794142471-8', $plain->tradeId);
        $this->assertSame(1, $plain->doorTry);

        $this->assertSame(3, FreezerRecognitionService::doorTryOf('SF-50001-1794142471-8R3'));
        $this->assertSame(1, FreezerRecognitionService::doorTryOf('SF-50001-1794142471-8'));
        $this->assertNull(FreezerRecognitionService::doorTryOf('SDK1790732333409'));
    }

    public function test_only_the_try_that_opened_is_sent_to_the_ai_and_judges_the_sale(): void
    {
        // The TRADE arrives at door close, minutes before Zijia's pushes.
        $sale = $this->sale("{$this->ref}R3");

        $this->push($this->ref);
        $this->push("{$this->ref}R2");
        $this->push("{$this->ref}R3");

        $this->assertSame(SmartFreezerRecognition::STATUS_SKIPPED, $this->row($this->ref)->status);
        $this->assertStringContainsString('opened on try 3', $this->row($this->ref)->status_reason);
        $this->assertSame(SmartFreezerRecognition::STATUS_SKIPPED, $this->row("{$this->ref}R2")->status);
        $opened = $this->row("{$this->ref}R3");
        $this->assertSame(SmartFreezerRecognition::STATUS_SUBMITTED, $opened->status);
        $this->assertSame($this->ref, $opened->session_ref);
        Http::assertSentCount(1);
        Http::assertSent(fn ($request) => json_decode(json_decode($request->body(), true)['bizContent'], true)['tradeId'] === "{$this->ref}R3");

        $this->notify("{$this->ref}R3", 1);
        $this->assertSame(RecognitionVerdict::MATCH, $opened->fresh()->verdict);
        $this->assertSame($sale->id, $opened->fresh()->vend_transaction_id);
        $this->assertNull($this->row($this->ref)->verdict);
    }

    public function test_a_try_sent_before_the_sale_arrived_never_judges_it(): void
    {
        // Try 1's push lands while the customer is still at the open door: nothing says it failed yet.
        $this->push($this->ref);
        $first = $this->row($this->ref);
        $this->assertSame(SmartFreezerRecognition::STATUS_SUBMITTED, $first->status);
        $this->notify($this->ref, 0);
        $this->assertStringStartsWith('sale not found yet', $first->fresh()->status_reason);

        // The TRADE names try 2 as the one that opened; the sweep must not hold try 1's empty answer against it.
        $sale = $this->sale("{$this->ref}R2");
        app(FreezerRecognitionService::class)->evaluateAwaitingSale();

        $first->refresh();
        $this->assertSame(SmartFreezerRecognition::STATUS_SKIPPED, $first->status);
        $this->assertNull($first->verdict);
        $this->assertNull($first->vend_transaction_id);

        $this->push("{$this->ref}R2");
        $this->notify("{$this->ref}R2", 1);
        $this->assertSame(RecognitionVerdict::MATCH, $this->row("{$this->ref}R2")->verdict);
        $this->assertSame($sale->id, $this->row("{$this->ref}R2")->vend_transaction_id);
    }

    public function test_a_later_try_marks_the_earlier_one_even_before_the_trade(): void
    {
        $this->push("{$this->ref}R2");
        Http::assertSentCount(1);

        // A late push of try 1: try 2 exists, so try 1's door did not open.
        $this->push($this->ref);
        $this->assertSame(SmartFreezerRecognition::STATUS_SKIPPED, $this->row($this->ref)->status);
        Http::assertSentCount(1);
    }

    public function test_when_every_try_failed_nothing_is_sent_and_the_page_lists_the_session_once(): void
    {
        FreezerFailedSession::create([
            'vend_id' => $this->vend->id, 'session_ref' => $this->ref, 'payment_method' => 'card', 'amount_cents' => 170,
            'reason' => 'hw:lock', 'detail' => '1号门锁开锁超时(损坏,锁舌卡住)', 'items' => [],
        ]);
        foreach (['', 'R2', 'R3', 'R4', 'R5'] as $suffix) {
            $this->push($this->ref.$suffix);
        }

        $this->assertSame(5, SmartFreezerRecognition::where('status', SmartFreezerRecognition::STATUS_SKIPPED)->count());
        Http::assertNothingSent();

        Permission::findOrCreate('read ai-recognition', 'web');
        $viewer = User::factory()->create(['operator_id' => 1]);
        $viewer->givePermissionTo('read ai-recognition');
        $this->actingAs($viewer)->get('/ai-recognition?date_from=&date_to=')->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('recognitions.data', 1)
                ->where('recognitions.data.0.trade_id', $this->ref)
                ->where('recognitions.data.0.status', SmartFreezerRecognition::STATUS_SKIPPED));
    }

    public function test_the_page_shows_the_opened_try_alone_with_its_try_number(): void
    {
        $this->sale("{$this->ref}R3");
        foreach (['', 'R2', 'R3'] as $suffix) {
            $this->push($this->ref.$suffix);
        }

        Permission::findOrCreate('read ai-recognition', 'web');
        $viewer = User::factory()->create(['operator_id' => 1]);
        $viewer->givePermissionTo('read ai-recognition');
        $this->actingAs($viewer)->get('/ai-recognition?date_from=&date_to=')->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('recognitions.data', 1)
                ->where('recognitions.data.0.trade_id', "{$this->ref}R3")
                ->where('recognitions.data.0.door_try', 3));
    }

    public function test_a_sale_from_an_older_app_is_judged_as_before(): void
    {
        $sale = $this->sale(null);
        $this->push($this->ref);
        $this->notify($this->ref, 1);

        $row = $this->row($this->ref);
        $this->assertSame(RecognitionVerdict::MATCH, $row->verdict);
        $this->assertSame($sale->id, $row->vend_transaction_id);
    }
}
