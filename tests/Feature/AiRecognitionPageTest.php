<?php

namespace Tests\Feature;

use App\Models\CardPaymentIntent;
use App\Models\Product;
use App\Models\RemoteCardTerminal;
use App\Models\SmartFreezerRecognition;
use App\Models\SmartFreezerVideo;
use App\Models\User;
use App\Models\Vend;
use App\Models\VendTransaction;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/** Transactions → AI Recognition (Smart Freezer): /ai-recognition. */
class AiRecognitionPageTest extends TestCase
{
    use RefreshDatabase;

    private Vend $vend;

    protected function setUp(): void
    {
        parent::setUp();

        Permission::findOrCreate('read ai-recognition', 'web');
        $this->vend = Vend::create(['code' => 50001, 'machine_type' => Vend::MACHINE_TYPE_SMART_FREEZER, 'is_active' => 1, 'operator_id' => 1]);
    }

    private function viewer(int $operatorId = 1): User
    {
        $user = User::factory()->create(['operator_id' => $operatorId]);
        $user->givePermissionTo('read ai-recognition');

        return $user;
    }

    private function recognition(array $attributes = []): SmartFreezerRecognition
    {
        $recognition = SmartFreezerRecognition::create($attributes + [
            'vend_id' => $this->vend->id,
            'trade_id' => 'SDK'.uniqid(),
            'device_id' => '861232069528880',
            'status' => SmartFreezerRecognition::STATUS_PENDING,
        ]);
        SmartFreezerVideo::create([
            'supplier' => 'zijia', 'vend_id' => $recognition->vend_id, 'smart_freezer_recognition_id' => $recognition->id,
            'order_no' => $recognition->trade_id, 'payload' => ['orderNo' => $recognition->trade_id],
            'video_urls' => ["https://oss.example.cn/{$recognition->trade_id}-d1c3-25f-1280x720.mp4"],
        ]);

        return $recognition;
    }

    public function test_it_needs_its_permission(): void
    {
        $this->actingAs(User::factory()->create(['operator_id' => 1]))->get('/ai-recognition')->assertForbidden();
    }

    public function test_a_row_carries_order_numbers_videos_the_ai_result_and_the_sale(): void
    {
        $magnum = Product::forceCreate(['code' => 'CC-01', 'name' => 'Magnum', 'operator_id' => 1, 'barcode' => '9726436016148']);
        $sale = VendTransaction::forceCreate([
            'order_id' => 'O-123', 'vend_id' => $this->vend->id, 'vend_channel_id' => 0, 'amount' => 350,
            'transaction_datetime' => Carbon::parse('2026-09-30 10:00:00'), 'gst_vat_rate' => 9, 'operator_id' => 1,
        ]);
        $this->recognition([
            'trade_id' => 'SDK1790732333409', 'session_ref' => 'SF-50001-1790732333-1', 'request_id' => '2105129738980528129',
            'vend_transaction_id' => $sale->id, 'status' => SmartFreezerRecognition::STATUS_COMPLETED,
            'order_status' => 0, 'items' => ['9726436016148' => 2], 'callback_verified' => true,
            'callback_payload' => ['bizContent' => json_encode(['tradeId' => 'SDK1790732333409', 'orderStatus' => 0, 'items' => []])],
            'verdict' => 'took_more',
            'verdict_lines' => [['product_id' => $magnum->id, 'code' => '9726436016148', 'paid' => 1, 'taken' => 2, 'delta' => 1]],
        ]);

        $this->actingAs($this->viewer())->get('/ai-recognition')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('AiRecognition/Index')
                ->has('recognitions.data', 1)
                ->where('recognitions.data.0.vend_label', '50001')
                ->where('recognitions.data.0.trade_id', 'SDK1790732333409')
                ->where('recognitions.data.0.session_ref', 'SF-50001-1790732333-1')
                ->where('recognitions.data.0.videos.0', 'https://oss.example.cn/SDK1790732333409-d1c3-25f-1280x720.mp4')
                ->where('recognitions.data.0.items.0', ['code' => '9726436016148', 'name' => 'Magnum', 'number' => 2])
                ->where('recognitions.data.0.verdict', 'took_more')
                ->where('recognitions.data.0.verdict_lines.0.name', 'Magnum')
                ->where('recognitions.data.0.sale', ['id' => $sale->id, 'order_id' => 'O-123', 'amount' => 350, 'date' => '2026-09-30', 'time' => '10:00:00'])
                ->where('recognitions.data.0.algorithm_status', 'normal')
                ->has('recognitions.meta')
            );
    }

    public function test_a_session_paid_on_a_t05_shows_what_the_verdict_charged(): void
    {
        $terminal = RemoteCardTerminal::create([
            'vend_id' => $this->vend->id, 'provider' => RemoteCardTerminal::PROVIDER_PAYRALLEL, 'access_token' => 't', 'is_active' => true,
        ]);
        CardPaymentIntent::forceCreate([
            'vend_id' => $this->vend->id, 'remote_card_terminal_id' => $terminal->id, 'provider' => 'payrallel',
            'reference' => 'SF1', 'custom_order_id' => '50001-SF1', 'session_ref' => 'SF-50001-1790732333-1',
            'mode' => 'preauth', 'amount_cents' => 760, 'captured_cents' => 760, 'owed_cents' => 200,
            'state' => CardPaymentIntent::STATE_CAPTURED, 'last_error' => 'further charge refused: no',
            'ai_decision' => ['reason' => 'AI judged took_more above the hold: 2 charges', 'judged_cents' => 960,
                'charges' => [760, 200], 'paid' => [760]],
        ]);
        $this->recognition(['session_ref' => 'SF-50001-1790732333-1', 'status' => SmartFreezerRecognition::STATUS_COMPLETED, 'verdict' => 'took_more']);
        $this->recognition(['trade_id' => 'SDK-other', 'session_ref' => 'SF-50001-1790732999-2']);

        $this->actingAs($this->viewer())->get('/ai-recognition?sortKey=created_at')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('recognitions.data.1.card', [
                    'state' => 'captured', 'hold_cents' => 760, 'captured_cents' => 760, 'owed_cents' => 200,
                    'judged_cents' => 960, 'charges' => 2, 'paid' => 1, 'uncertain_cents' => null,
                    'reason' => 'AI judged took_more above the hold: 2 charges', 'error' => 'further charge refused: no',
                ])
                ->where('recognitions.data.0.card', null));
    }

    public function test_the_finer_ai_reason_is_shown(): void
    {
        $this->recognition([
            'status' => SmartFreezerRecognition::STATUS_FAILED, 'order_status' => 501,
            'callback_payload' => ['bizContent' => json_encode(['tradeId' => 'X', 'orderStatus' => 501, 'jsOrderStatus' => 503, 'jsOrderStatusName' => '商品未上架'])],
        ]);

        $this->actingAs($this->viewer())->get('/ai-recognition')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('recognitions.data.0.algorithm_status', 'recognition error — 503 goods not listed in the model (商品未上架)'));
    }

    public function test_filters_narrow_by_status_verdict_and_machine_search(): void
    {
        $this->recognition(['status' => SmartFreezerRecognition::STATUS_COMPLETED, 'verdict' => 'match']);
        $this->recognition(['status' => SmartFreezerRecognition::STATUS_PENDING]);
        $other = Vend::create(['code' => 2009, 'machine_type' => Vend::MACHINE_TYPE_SMART_FREEZER, 'is_active' => 1, 'operator_id' => 1]);
        $this->recognition(['vend_id' => $other->id, 'status' => SmartFreezerRecognition::STATUS_PENDING]);

        $viewer = $this->viewer();
        $count = fn (array $q) => $this->actingAs($viewer)->get('/ai-recognition?'.http_build_query($q))->viewData('page')['props']['recognitions']['meta']['total'];

        $this->assertSame(3, $count([]));
        $this->assertSame(2, $count(['status' => 'pending']));
        $this->assertSame(1, $count(['verdict' => 'match']));
        $this->assertSame(2, $count(['verdict' => 'none']));
        $this->assertSame(1, $count(['search' => '2009']));
    }

    public function test_machine_id_and_time_filters_narrow_the_list(): void
    {
        $this->recognition()->forceFill(['created_at' => Carbon::parse('2026-09-30 09:15:00')])->save();
        $this->recognition()->forceFill(['created_at' => Carbon::parse('2026-09-30 13:28:00')])->save();
        $this->recognition()->forceFill(['created_at' => Carbon::parse('2026-09-29 13:40:00')])->save();
        $chiller = Vend::create(['code' => 6003, 'code_prefix' => 'C', 'machine_type' => Vend::MACHINE_TYPE_SMART_FREEZER, 'is_active' => 1, 'operator_id' => 1]);
        $this->recognition(['vend_id' => $chiller->id])->forceFill(['created_at' => Carbon::parse('2026-09-30 13:00:00')])->save();

        $viewer = $this->viewer();
        $count = fn (array $q) => $this->actingAs($viewer)->get('/ai-recognition?'.http_build_query($q))->viewData('page')['props']['recognitions']['meta']['total'];

        $this->assertSame(3, $count(['codes' => '50001']));
        $this->assertSame(1, $count(['codes' => 'C6003']));
        $this->assertSame(4, $count(['codes' => '50001, C6003']));
        // Date + time: a bounded window on one day.
        $this->assertSame(2, $count(['date_from' => '2026-09-30', 'time_from' => '12:00', 'date_to' => '2026-09-30', 'time_to' => '14:00']));
        $this->assertSame(1, $count(['codes' => '50001', 'date_from' => '2026-09-30', 'time_from' => '12:00', 'date_to' => '2026-09-30']));
        // Time alone: that time of day on every day.
        $this->assertSame(3, $count(['time_from' => '13:00', 'time_to' => '14:00']));
        $this->actingAs($viewer)->get('/ai-recognition?time_from=25:99')->assertSessionHasErrors('time_from');
    }

    public function test_an_operator_viewer_sees_only_its_own_freezers_and_no_unmatched_sessions(): void
    {
        $theirs = Vend::create(['code' => 2013, 'machine_type' => Vend::MACHINE_TYPE_SMART_FREEZER, 'is_active' => 1, 'operator_id' => 7]);
        $this->recognition();                                // operator 1's freezer
        $this->recognition(['vend_id' => $theirs->id]);      // operator 7's freezer
        $this->recognition(['vend_id' => null, 'device_id' => 'JS093519']); // supplier test cabinet

        $total = fn (User $u) => $this->actingAs($u)->get('/ai-recognition')->viewData('page')['props']['recognitions']['meta']['total'];

        $this->assertSame(3, $total($this->viewer(1)));
        $this->assertSame(1, $total($this->viewer(7)));
    }
}
