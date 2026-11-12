<?php

namespace Tests\Feature;

use App\Models\CardPaymentIntent;
use App\Models\CardRefund;
use App\Models\Customer;
use App\Models\FreezerControlCommand;
use App\Models\FreezerFailedSession;
use App\Models\HappyHourCampaign;
use App\Models\HappyHourSlot;
use App\Models\Product;
use App\Models\RemoteCardTerminal;
use App\Models\SmartFreezerRecognition;
use App\Models\SmartFreezerVideo;
use App\Models\User;
use App\Models\Vend;
use App\Models\VendTransaction;
use App\Models\VendTransactionItem;
use App\Services\CardTerminal\CardPaymentService;
use App\Services\CardTerminal\Payrallel\PayrallelGateway;
use App\Services\CardTerminal\TerminalTransaction;
use App\Services\SmartFreezer\DoorFault;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia;
use Spatie\Permission\Models\Permission;
use Tests\Support\FakeRemoteCardTerminalGateway;
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
        $this->actingAs(User::factory()->create(['operator_id' => 1]))->get('/ai-recognition?date_from=')->assertForbidden();
    }

    /** Brian, 2026-11-04: the page opens on today, not on every session there has ever been. */
    public function test_it_opens_on_today_until_a_date_is_cleared_or_one_session_is_searched(): void
    {
        $this->recognition(['trade_id' => 'SDK-today']);
        $this->recognition(['trade_id' => 'SDK-old'])->forceFill(['created_at' => Carbon::parse('2026-10-01 12:00:00')])->save();
        $today = Carbon::today()->toDateString();

        $this->actingAs($this->viewer())->get('/ai-recognition')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('filters.date_from', $today)
                ->where('filters.date_to', $today)
                ->has('recognitions.data', 1)
                ->where('recognitions.data.0.trade_id', 'SDK-today')
                ->where('dashboard.total', 1));

        $this->actingAs($this->viewer())->get('/ai-recognition?date_from=&date_to=')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('filters.date_from', '')
                ->where('filters.date_to', '')
                ->has('recognitions.data', 2));

        $this->actingAs($this->viewer())->get('/ai-recognition?search=SDK-old')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('filters.date_from', '')
                ->has('recognitions.data', 1)
                ->where('recognitions.data.0.trade_id', 'SDK-old'));
    }

    private function dashboardSale(int $cents, array $attributes = []): VendTransaction
    {
        return VendTransaction::forceCreate($attributes + [
            'order_id' => 'O-'.uniqid(), 'vend_id' => $this->vend->id, 'vend_channel_id' => 0, 'amount' => $cents,
            'transaction_datetime' => Carbon::now(), 'gst_vat_rate' => 9, 'operator_id' => 1,
            'settlement_status' => VendTransaction::SETTLEMENT_SETTLED,
        ]);
    }

    private function dashboardHold(string $sessionRef, string $state, int $capturedCents = 0, int $refundedCents = 0): void
    {
        $terminal = RemoteCardTerminal::firstOrCreate(['vend_id' => $this->vend->id], ['provider' => 'payrallel', 'access_token' => 't', 'is_active' => true]);
        CardPaymentIntent::forceCreate([
            'vend_id' => $this->vend->id, 'remote_card_terminal_id' => $terminal->id, 'provider' => 'payrallel',
            'reference' => 'SF'.uniqid(), 'custom_order_id' => '50001-SF'.uniqid(), 'session_ref' => $sessionRef, 'mode' => 'preauth',
            'amount_cents' => 500, 'captured_cents' => $capturedCents ?: null, 'refunded_cents' => $refundedCents, 'state' => $state,
        ]);
    }

    private function invoiceScan(string $sessionRef, array $attributes = []): void
    {
        DB::table('freezer_receipts')->insert($attributes + [
            'vend_id' => $this->vend->id, 'session_ref' => $sessionRef, 'signature' => 'sig', 'open_count' => 1,
            'first_opened_at' => '2026-11-05 12:00:00', 'device' => 'Android',
        ]);
    }

    /** Brian, 2026-11-05: who followed the invoice QR, per session and as a rate over real paid sales. */
    public function test_it_shows_invoice_qr_scans_and_their_rate_over_real_paid_sales(): void
    {
        // Paid and followed to the final amount.
        $this->recognition(['session_ref' => 'SF-50001-1793780000-1', 'vend_transaction_id' => $this->dashboardSale(350)->id]);
        $this->invoiceScan('SF-50001-1793780000-1', ['final_seen_at' => '2026-11-05 12:01:00']);
        // Paid, scanned, gone before the amount was decided.
        $this->recognition(['session_ref' => 'SF-50001-1793780100-2', 'vend_transaction_id' => $this->dashboardSale(200)->id]);
        $this->invoiceScan('SF-50001-1793780100-2');
        // Paid, not scanned.
        $this->recognition(['session_ref' => 'SF-50001-1793780200-3', 'vend_transaction_id' => $this->dashboardSale(150)->id]);
        // Only pinned a signature (a refresh call): no scan.
        $this->recognition(['session_ref' => 'SF-50001-1793780300-4', 'vend_transaction_id' => $this->dashboardSale(150)->id]);
        $this->invoiceScan('SF-50001-1793780300-4', ['first_opened_at' => null, 'open_count' => 0, 'device' => null]);
        // Testing Mode: scanned, but out of the rate.
        $this->recognition(['session_ref' => 'SF-50001-1793780400-5', 'vend_transaction_id' => $this->dashboardSale(20, ['vend_transaction_json' => ['TEST' => 1]])->id]);
        $this->invoiceScan('SF-50001-1793780400-5');
        // Before app 41: no QR was ever shown, though a person opened the link by hand.
        $this->recognition(['session_ref' => 'SF-50001-1791539003-2', 'vend_transaction_id' => $this->dashboardSale(22)->id]);
        $this->invoiceScan('SF-50001-1791539003-2', ['device' => null]);

        $this->actingAs($this->viewer())->get('/ai-recognition?date_from=&date_to=')
            ->assertInertia(function (AssertableInertia $page) {
                $page->where('dashboard.invoice_sales', 4)
                    ->where('dashboard.invoice_scanned', 2)
                    ->where('dashboard.invoice_scanned_pct', 50)
                    ->where('dashboard.invoice_final_seen', 1);

                $invoice = collect($page->toArray()['props']['recognitions']['data'])->pluck('invoice', 'session_ref');
                $this->assertSame('Android', $invoice['SF-50001-1793780000-1']['device']);
                $this->assertSame('2026-11-05 12:01:00', $invoice['SF-50001-1793780000-1']['final_seen_at']);
                $this->assertSame('2026-11-05 12:00:00', $invoice['SF-50001-1793780100-2']['opened_at']);
                $this->assertNull($invoice['SF-50001-1793780100-2']['final_seen_at']);
                $this->assertNull($invoice['SF-50001-1793780200-3']['opened_at']);
                $this->assertTrue($invoice['SF-50001-1793780200-3']['offered']);
                $this->assertNull($invoice['SF-50001-1793780300-4']['opened_at']);
                $this->assertFalse($invoice['SF-50001-1791539003-2']['offered']);
            });
    }

    /** Each session lands in one outcome, and the money is what the rail took and gave back. */
    public function test_the_dashboard_counts_money_and_outcomes_over_everything_listed(): void
    {
        // A T05 charge with part refunded: the charge stays 4.50, the sale's own amount has already come down.
        $this->dashboardHold('SF-50001-1793780000-1', CardPaymentIntent::STATE_CAPTURED, 450, 100);
        $this->recognition(['session_ref' => 'SF-50001-1793780000-1', 'vend_transaction_id' => $this->dashboardSale(350)->id]);
        // Paid at the machine, no hold; then one refunded in full on the gateway.
        $this->recognition(['vend_transaction_id' => $this->dashboardSale(200)->id]);
        $this->recognition(['vend_transaction_id' => $this->dashboardSale(120, ['is_refunded' => true])->id]);
        // A released hold, its sale of 0 refunded.
        $this->dashboardHold('SF-50001-1793780100-2', CardPaymentIntent::STATE_VOIDED);
        $this->recognition(['session_ref' => 'SF-50001-1793780100-2', 'vend_transaction_id' => $this->dashboardSale(0, ['settlement_status' => VendTransaction::SETTLEMENT_REFUNDED])->id]);
        // The door refused a paid session: a machine error, not a void, though its hold was released too.
        $ref = 'SF-50001-1793784418-6';
        $this->sessionWithNoSale($ref);
        $this->holdWithoutSession($this->afterStart($ref, 3));
        // A door fault with no payment.
        $faulty = 'SF-50001-1793784500-7';
        $this->sessionWithNoSale($faulty);
        FreezerControlCommand::forceCreate([
            'vend_id' => $this->vend->id, 'cmd_id' => '01KA0000000000000000000009', 'op' => 'error:BoxVmc', 'status' => 'ok', 'source' => 'event',
            'response_msg' => "Door refused for {$faulty}: CAMERA (摄像头异常)", 'created_at' => $this->afterStart($faulty, 10),
        ]);
        // Still waiting for the AI: nothing collected yet.
        $this->dashboardHold('SF-50001-1793780200-3', CardPaymentIntent::STATE_AWAITING_AI);
        $this->recognition(['session_ref' => 'SF-50001-1793780200-3']);

        $this->actingAs($this->viewer())->get('/ai-recognition?date_from=&numberPerPage=1')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('dashboard.charged_cents', 450 + 200 + 120)
                ->where('dashboard.refunded_cents', 100 + 120)
                ->where('dashboard.successful', 3)
                ->where('dashboard.void', 1)
                ->where('dashboard.machine_error', 2)
                ->where('dashboard.other', 1)
                ->where('dashboard.total', 7)
                ->where('dashboard.successful_pct', 42.86));
    }

    /** A person's count is the truth; one product swapped for another is one unit wrong, and an open difference is not correct. */
    public function test_the_dashboard_counts_the_units_the_ai_got_right(): void
    {
        $counted = fn (array $basket) => $this->dashboardSale(100, ['meta_json' => ['correction' => ['by' => ['name' => 'Daniel'], 'basket' => $basket]]]);
        $line = fn (int $id, int $paid, int $taken) => ['product_id' => $id, 'code' => (string) $id, 'paid' => $paid, 'taken' => $taken, 'delta' => $taken - $paid];

        // A match: 3 right.
        $this->recognition(['vend_transaction_id' => $this->dashboardSale(100)->id, 'verdict' => 'match', 'verdict_lines' => [$line(1, 2, 2), $line(2, 1, 1)]]);
        // Swapped: the AI saw product 3, a person says it was product 4. 1 unit wrong, not 2.
        $this->recognition(['vend_transaction_id' => $counted([4 => 1])->id, 'verdict' => 'mixed', 'verdict_lines' => [$line(3, 0, 1), $line(4, 1, 0)]]);
        // The AI saw 3 of product 5, a person says 2 and 1 of product 6 it missed: 2 right, 1 swapped.
        $this->recognition(['vend_transaction_id' => $counted([5 => 2, 6 => 1])->id, 'verdict' => 'took_more', 'verdict_lines' => [$line(5, 2, 3)]]);
        // A person confirmed the AI's count: nothing changed.
        $this->recognition(['vend_transaction_id' => $counted([7 => 1])->id, 'verdict' => 'match', 'verdict_lines' => [$line(7, 1, 1)]]);
        // A difference nobody has dealt with: 1 agrees, 1 extra not checked.
        $this->recognition(['vend_transaction_id' => $this->dashboardSale(100)->id, 'verdict' => 'took_more', 'verdict_lines' => [$line(8, 1, 2)]]);
        // Released with nothing seen: no units either way.
        $this->dashboardHold('SF-50001-1793780300-4', CardPaymentIntent::STATE_VOIDED);
        $this->recognition(['session_ref' => 'SF-50001-1793780300-4', 'verdict' => 'took_less', 'verdict_lines' => [$line(9, 1, 0)]]);
        // No verdict and no count: nothing to judge.
        $this->recognition(['status' => SmartFreezerRecognition::STATUS_PENDING]);

        $this->actingAs($this->viewer())->get('/ai-recognition?date_from=')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('dashboard.correct_qty', 3 + 0 + 2 + 1 + 1)
                ->where('dashboard.adjusted_qty', 1 + 1)
                ->where('dashboard.unchecked_qty', 1)
                ->where('dashboard.total_qty', 10)
                ->where('dashboard.correct_pct', 70));
    }

    public function test_the_dashboard_follows_the_filters_and_the_viewers_operator(): void
    {
        $this->recognition(['vend_transaction_id' => $this->dashboardSale(100)->id, 'verdict' => 'match', 'verdict_lines' => [['product_id' => 1, 'code' => '1', 'paid' => 1, 'taken' => 1]]]);
        $this->recognition(['vend_transaction_id' => $this->dashboardSale(200)->id, 'verdict' => 'took_more']);
        $other = Vend::create(['code' => 50009, 'machine_type' => Vend::MACHINE_TYPE_SMART_FREEZER, 'is_active' => 1, 'operator_id' => 7]);
        $this->recognition(['vend_id' => $other->id, 'vend_transaction_id' => $this->dashboardSale(900, ['vend_id' => $other->id, 'operator_id' => 7])->id, 'verdict' => 'match']);

        $this->actingAs($this->viewer())->get('/ai-recognition?date_from=&verdict=match')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('dashboard.total', 2)
                ->where('dashboard.charged_cents', 1000));

        $this->actingAs($this->viewer(7))->get('/ai-recognition?date_from=')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('dashboard.total', 1)
                ->where('dashboard.charged_cents', 900));
    }

    public function test_the_dashboard_is_empty_when_nothing_is_listed(): void
    {
        $this->actingAs($this->viewer())->get('/ai-recognition')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('dashboard.total', 0)
                ->where('dashboard.successful_pct', null)
                ->where('dashboard.total_qty', 0)
                ->where('dashboard.correct_pct', null));
    }

    public function test_a_sale_waiting_for_its_video_shows_at_once_and_leaves_when_the_video_arrives(): void
    {
        Carbon::setTestNow('2026-11-05 10:30:00');
        $product = Product::withoutGlobalScopes()->forceCreate(['code' => 'W1', 'name' => 'Waiting Bar', 'operator_id' => 1]);
        $frame = ['SFREF' => 'SF-50001-1-1', 'transf_info' => [['goods_id' => $product->id, 'goods_name' => 'Waiting Bar', 'Price' => 170]]];
        $this->dashboardSale(170, ['order_id' => 'O-WAIT', 'vend_transaction_json' => $frame, 'transaction_datetime' => Carbon::now()->subMinutes(3)]);
        $this->dashboardSale(170, ['order_id' => 'O-OLD', 'vend_transaction_json' => ['SFREF' => 'SF-50001-1-2'], 'transaction_datetime' => Carbon::now()->subMinutes(61)]);
        $this->dashboardSale(170, ['order_id' => 'O-NOREF', 'transaction_datetime' => Carbon::now()->subMinutes(2)]);

        $this->actingAs($this->viewer())->get('/ai-recognition')->assertInertia(fn (AssertableInertia $page) => $page
            ->has('waitingForVideo', 1)
            ->where('waitingForVideo.0.order_id', 'O-WAIT')
            ->where('waitingForVideo.0.session_ref', 'SF-50001-1-1')
            ->where('waitingForVideo.0.amount_cents', 170)
            ->where('waitingForVideo.0.waited_minutes', 3)
            ->where('waitingForVideo.0.items.0.name', 'Waiting Bar'));

        $this->actingAs($this->viewer(2))->get('/ai-recognition')
            ->assertInertia(fn (AssertableInertia $page) => $page->has('waitingForVideo', 0));
        $this->actingAs($this->viewer())->get('/ai-recognition?verdict=match')
            ->assertInertia(fn (AssertableInertia $page) => $page->has('waitingForVideo', 0));

        $this->recognition(['session_ref' => 'SF-50001-1-1']);
        $this->actingAs($this->viewer())->get('/ai-recognition')
            ->assertInertia(fn (AssertableInertia $page) => $page->has('waitingForVideo', 0));
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

        $this->actingAs($this->viewer())->get('/ai-recognition?date_from=')
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
                ->where('recognitions.data.0.sale', ['id' => $sale->id, 'order_id' => 'O-123', 'amount' => 350, 'date' => '2026-09-30', 'time' => '10:00:00', 'is_test_sale' => false])
                ->where('recognitions.data.0.algorithm_status', 'normal')
                ->has('recognitions.meta')
            );
    }

    public function test_a_session_paid_on_a_t05_shows_what_the_verdict_charged(): void
    {
        $terminal = RemoteCardTerminal::create([
            'vend_id' => $this->vend->id, 'provider' => RemoteCardTerminal::PROVIDER_PAYRALLEL, 'access_token' => 't', 'is_active' => true,
        ]);
        $intent = CardPaymentIntent::forceCreate([
            'vend_id' => $this->vend->id, 'remote_card_terminal_id' => $terminal->id, 'provider' => 'payrallel',
            'reference' => 'SF1', 'custom_order_id' => '50001-SF1', 'session_ref' => 'SF-50001-1790732333-1',
            'mode' => 'preauth', 'amount_cents' => 760, 'captured_cents' => 760, 'owed_cents' => 200,
            'state' => CardPaymentIntent::STATE_CAPTURED, 'last_error' => 'further charge refused: no',
            'ai_decision' => ['reason' => 'AI judged took_more above the hold: one capture with auth increment', 'judged_cents' => 960,
                'capture_cents' => 760, 'fallback_cents' => 760, 'increment_refused' => 'HTTP 422'],
        ]);
        $this->recognition(['session_ref' => 'SF-50001-1790732333-1', 'status' => SmartFreezerRecognition::STATUS_COMPLETED, 'verdict' => 'took_more']);
        $this->recognition(['trade_id' => 'SDK-other', 'session_ref' => 'SF-50001-1790732999-2']);

        $this->actingAs($this->viewer())->get('/ai-recognition?date_from=&sortKey=created_at')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('recognitions.data.1.card', [
                    'id' => $intent->id, 'state' => 'captured', 'hold_cents' => 760, 'captured_cents' => 760, 'owed_cents' => 200, 'refunded_cents' => 0, 'refunds' => [],
                    'judged_cents' => 960, 'increment_refused' => true, 'uncertain_cents' => null,
                    'reason' => 'AI judged took_more above the hold: one capture with auth increment', 'error' => 'further charge refused: no',
                    'person_reason' => null, 'awaits_person' => false, 'ai_suggested_cents' => null, 'by_person' => null,
                ])
                ->where('recognitions.data.0.card', null));
    }

    /** Brian, 2026-11-03: Order ID is the reference shared with Sales Transactions, and its time leads. */
    public function test_rows_follow_the_transaction_time_and_are_found_by_order_id(): void
    {
        $sale = fn (string $order, string $at) => VendTransaction::forceCreate([
            'order_id' => $order, 'vend_id' => $this->vend->id, 'vend_channel_id' => 0, 'amount' => 100,
            'transaction_datetime' => Carbon::parse($at), 'gst_vat_rate' => 9, 'operator_id' => 1,
        ]);
        // The older sale's videos arrived last, so the door session alone would list it first.
        $this->recognition(['trade_id' => 'SDK-new', 'vend_transaction_id' => $sale('O-NEW', '2026-11-03 18:32:19')->id])
            ->forceFill(['created_at' => Carbon::parse('2026-11-03 18:38:14')])->save();
        $this->recognition(['trade_id' => 'SDK-old', 'vend_transaction_id' => $sale('O-OLD', '2026-11-03 18:30:00')->id])
            ->forceFill(['created_at' => Carbon::parse('2026-11-03 18:40:00')])->save();
        // No sale yet: its door session stands in.
        $this->recognition(['trade_id' => 'SDK-none'])->forceFill(['created_at' => Carbon::parse('2026-11-03 18:31:00')])->save();

        $this->actingAs($this->viewer())->get('/ai-recognition?date_from=')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('filters.sortKey', 'transaction_time')
                ->where('recognitions.data.0.sale.order_id', 'O-NEW')
                ->where('recognitions.data.0.sale.time', '18:32:19')
                ->where('recognitions.data.1.trade_id', 'SDK-none')
                ->where('recognitions.data.2.sale.order_id', 'O-OLD'));

        $this->actingAs($this->viewer())->get('/ai-recognition?search=O-OLD')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('recognitions.data', 1)
                ->where('recognitions.data.0.sale.order_id', 'O-OLD'));

        // The date filter reads the same time: 18:40 is the door session, the sale was 18:30.
        $this->recognition(['trade_id' => 'SDK-next-day'])->forceFill(['created_at' => Carbon::parse('2026-11-04 00:10:00')])->save();
        $this->actingAs($this->viewer())->get('/ai-recognition?date_from=2026-11-03&date_to=2026-11-03')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('recognitions.data', 3)
                ->where('recognitions.data.0.sale.order_id', 'O-NEW'));
    }

    /** One line per product: paid against what the AI saw, the difference, and the price, problems first. */
    public function test_the_ai_check_lines_show_paid_against_saw(): void
    {
        $paidAndTaken = Product::forceCreate(['code' => 'A', 'name' => 'Aye', 'operator_id' => 1, 'barcode' => '111']);
        $tookMore = Product::forceCreate(['code' => 'B', 'name' => 'Bee', 'operator_id' => 1, 'barcode' => '222']);
        $notTaken = Product::forceCreate(['code' => 'C', 'name' => 'Cee', 'operator_id' => 1, 'barcode' => '333']);
        $notPaid = Product::forceCreate(['code' => 'D', 'name' => 'Dee', 'operator_id' => 1, 'barcode' => '444']);
        $notAsked = Product::forceCreate(['code' => 'E', 'name' => 'Eee', 'operator_id' => 1]);
        \App\Models\VendChannel::create(['vend_id' => $this->vend->id, 'code' => 11, 'product_id' => $notPaid->id, 'qty' => 5, 'capacity' => 5, 'amount' => 250, 'is_active' => 1]);
        $sale = VendTransaction::forceCreate(['order_id' => 'O-1', 'vend_id' => $this->vend->id, 'vend_channel_id' => 0, 'amount' => 900, 'transaction_datetime' => Carbon::now(), 'gst_vat_rate' => 9, 'operator_id' => 1,
            'vend_transaction_json' => ['Type' => 'TRADE', 'transf_info' => [
                ['goods_id' => $paidAndTaken->id, 'Price' => 100], ['goods_id' => $tookMore->id, 'Price' => 200],
                ['goods_id' => $notTaken->id, 'Price' => 300], ['goods_id' => $notAsked->id, 'Price' => 400],
            ]]]);
        $this->recognition(['vend_transaction_id' => $sale->id, 'status' => SmartFreezerRecognition::STATUS_COMPLETED, 'order_status' => 0, 'verdict' => 'incomplete', 'verdict_lines' => [
            ['product_id' => $paidAndTaken->id, 'code' => '111', 'paid' => 1, 'taken' => 1, 'delta' => 0],
            ['product_id' => $tookMore->id, 'code' => '222', 'paid' => 1, 'taken' => 3, 'delta' => 2],
            ['product_id' => $notTaken->id, 'code' => '333', 'paid' => 1, 'taken' => 0, 'delta' => -1],
            ['product_id' => $notPaid->id, 'code' => '444', 'paid' => 0, 'taken' => 1, 'delta' => 1],
            ['product_id' => $notAsked->id, 'code' => null, 'paid' => 1, 'taken' => 0, 'delta' => -1],
        ]]);

        $this->actingAs($this->viewer())->get('/ai-recognition?date_from=')->assertInertia(function (AssertableInertia $page) {
            $lines = collect($page->toArray()['props']['recognitions']['data'][0]['ai_check']);
            $this->assertSame(['extra', 'extra', 'missing', 'unknown', 'ok'], $lines->pluck('state')->all());
            $byCode = $lines->keyBy('code');
            $this->assertSame([1, 3, 2, 200], [$byCode['B']['paid'], $byCode['B']['taken'], $byCode['B']['delta'], $byCode['B']['unit_cents']]);
            $this->assertSame([0, 1, 1, 250], [$byCode['D']['paid'], $byCode['D']['taken'], $byCode['D']['delta'], $byCode['D']['unit_cents']], 'not paid: the machine\'s price');
            $this->assertSame([1, 0, -1], [$byCode['C']['paid'], $byCode['C']['taken'], $byCode['C']['delta']]);
            $this->assertSame('Eee', $byCode['E']['name']);
        });
    }

    /** Released holds on 50001 (2026-11-03): the sale of 0 was swept, and the line read 0 paid, 0 taken, ticked. */
    public function test_a_session_whose_sale_is_gone_keeps_what_the_verdict_says_was_paid(): void
    {
        $product = Product::forceCreate(['code' => 'A', 'name' => 'Aye', 'operator_id' => 1, 'barcode' => '111']);
        \App\Models\VendChannel::create(['vend_id' => $this->vend->id, 'code' => 11, 'product_id' => $product->id, 'qty' => 5, 'capacity' => 5, 'amount' => 170, 'is_active' => 1]);
        $this->recognition(['vend_transaction_id' => 999999, 'status' => SmartFreezerRecognition::STATUS_COMPLETED, 'order_status' => 0, 'verdict' => 'took_less', 'verdict_lines' => [
            ['product_id' => $product->id, 'code' => '111', 'paid' => 1, 'taken' => 0, 'delta' => -1],
        ]]);

        $this->actingAs($this->viewer())->get('/ai-recognition?date_from=')->assertInertia(fn (AssertableInertia $page) => $page
            ->where('recognitions.data.0.ai_check.0.paid', 1)
            ->where('recognitions.data.0.ai_check.0.taken', 0)
            ->where('recognitions.data.0.ai_check.0.state', 'missing')
            ->where('recognitions.data.0.ai_check.0.unit_cents', 170));
    }

    /** A voided hold of an app that names no session: the state 50001's refused doors left on 2026-11-04. */
    private function holdWithoutSession(string $createdAt, array $attributes = []): CardPaymentIntent
    {
        $terminal = RemoteCardTerminal::firstOrCreate(['vend_id' => $attributes['vend_id'] ?? $this->vend->id], ['provider' => 'payrallel', 'access_token' => 't', 'is_active' => true]);
        $hold = CardPaymentIntent::create($attributes + [
            'vend_id' => $this->vend->id, 'remote_card_terminal_id' => $terminal->id, 'provider' => 'payrallel',
            'reference' => 'SF'.uniqid(), 'custom_order_id' => '50001-SF'.uniqid(), 'mode' => 'preauth', 'amount_cents' => 430,
            'state' => CardPaymentIntent::STATE_VOIDED, 'payment_method' => 'master', 'approved_at' => $createdAt, 'voided_at' => $createdAt,
        ]);
        $hold->forceFill(['created_at' => $createdAt])->save();

        return $hold;
    }

    private function sessionWithNoSale(string $sessionRef): SmartFreezerRecognition
    {
        return $this->recognition([
            'trade_id' => $sessionRef, 'session_ref' => $sessionRef, 'status' => SmartFreezerRecognition::STATUS_COMPLETED,
            'order_status' => 0, 'items' => [], 'status_reason' => "sale not found yet for {$sessionRef}",
        ]);
    }

    /** Local time of a session ref's start second, plus some seconds. */
    private function afterStart(string $sessionRef, int $seconds): string
    {
        return Carbon::createFromTimestamp((int) explode('-', $sessionRef)[2] + $seconds, config('app.timezone'))->format('Y-m-d H:i:s');
    }

    public function test_a_paid_session_that_never_became_a_sale_shows_the_payment(): void
    {
        $ref = 'SF-50001-1793784418-6';
        $this->sessionWithNoSale($ref);
        $this->holdWithoutSession($this->afterStart($ref, 3));
        FreezerControlCommand::forceCreate([
            'vend_id' => $this->vend->id, 'cmd_id' => '01KA0000000000000000000000', 'op' => 'error:BoxVmc', 'status' => 'ok', 'source' => 'event',
            'response_msg' => "Door refused for {$ref}: LOCK (1号门锁开锁超时(损坏,锁舌卡住))", 'created_at' => $this->afterStart($ref, 15),
        ]);

        $this->actingAs($this->viewer())->get('/ai-recognition?date_from=')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('recognitions.data.0.card.state', 'voided')
                ->where('recognitions.data.0.card.hold_cents', 430)
                ->where('recognitions.data.0.paid_without_sale.method', 'Mastercard')
                ->where('recognitions.data.0.door_error.code', 'LOCK')
                ->where('recognitions.data.0.door_error.text', '1号门锁开锁超时(损坏,锁舌卡住)')
                ->where('recognitions.data.0.door_error.plain', DoorFault::plain('LOCK'))
                ->where('recognitions.data.0.door_error.at', Carbon::parse($this->afterStart($ref, 15))->format('H:i:s'))
                ->where('doorFaultLegend.0.code', 'LOCK')
                ->where('recognitions.data.0.status_reason', 'No sale was recorded for this door session.')
                ->where('recognitions.data.0.card.awaits_person', false)
                ->where('recognitions.data.0.can_correct', false)
            );
    }

    /** Not only the lock, and not only a T05 hold: PayNow leaves no hold, the door error still shows. */
    public function test_any_door_error_shows_on_its_session_without_a_hold(): void
    {
        $camera = 'SF-50001-1793784418-6';
        $unreached = 'SF-50001-1793784450-7';
        $other = 'SF-50001-1793784551-8';
        $this->sessionWithNoSale($camera);
        $this->sessionWithNoSale($unreached);
        $this->sessionWithNoSale($other);
        foreach ([
            ['01KA0000000000000000000001', "Door refused for {$camera}: CAMERA (摄像头异常)", $camera],
            ['01KA0000000000000000000002', "Door call never reached host for {$unreached}: THREW", $unreached],
        ] as [$cmdId, $line, $ref]) {
            FreezerControlCommand::forceCreate([
                'vend_id' => $this->vend->id, 'cmd_id' => $cmdId, 'op' => 'error:BoxVmc', 'status' => 'ok', 'source' => 'event',
                'response_msg' => $line, 'created_at' => $this->afterStart($ref, 10),
            ]);
        }

        $this->actingAs($this->viewer())->get('/ai-recognition?date_from=')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('recognitions.data.0.session_ref', $other)
                ->where('recognitions.data.0.door_error', null)
                ->where('recognitions.data.1.door_error.code', DoorFault::NOT_DELIVERED)
                ->where('recognitions.data.1.door_error.text', 'THREW')
                ->where('recognitions.data.2.door_error.code', 'CAMERA')
                ->where('recognitions.data.2.door_error.text', '摄像头异常')
                ->where('recognitions.data.2.paid_without_sale', null)
            );
    }

    /** App 48+: the cart reaches mark1, so Paid is the quantity and the rest cannot apply. */
    public function test_a_reported_session_lists_what_the_customer_tried_to_buy(): void
    {
        $product = Product::forceCreate(['code' => 'A', 'name' => 'Aye', 'operator_id' => 1, 'barcode' => '111']);
        $ref = 'SF-50001-1793784450-7';
        $this->sessionWithNoSale($ref);
        // The time would give this hold to the session; the other one is what the machine named.
        $this->holdWithoutSession($this->afterStart($ref, 1), ['amount_cents' => 111, 'provider_txn_id' => 'OTHER']);
        $this->holdWithoutSession($this->afterStart($ref, 2), ['amount_cents' => 430, 'provider_txn_id' => 'PAY123']);
        $this->holdWithoutSession($this->afterStart($ref, 3), ['amount_cents' => 222, 'provider_txn_id' => 'LATER']);
        FreezerFailedSession::create([
            'vend_id' => $this->vend->id, 'session_ref' => $ref, 'payment_method' => 'card', 'payment_ref' => 'PAY123', 'amount_cents' => 430,
            'reason' => 'door_open_failed', 'detail' => 'door refused: LOCK 1号门锁开锁超时', 'ended_at' => $this->afterStart($ref, 10),
            'items' => [
                ['sku' => 'A', 'name' => 'Aye', 'unit_cents' => 215, 'qty' => 2, 'channel' => '11', 'product_id' => $product->id],
                ['sku' => 'Z', 'name' => 'Zed', 'unit_cents' => 100, 'qty' => 1, 'channel' => '12', 'product_id' => null],
            ],
        ]);

        $this->actingAs($this->viewer())->get('/ai-recognition?date_from=')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('recognitions.data.0.ai_check.0.product_id', $product->id)
                ->where('recognitions.data.0.ai_check.0.name', 'Aye')
                ->where('recognitions.data.0.ai_check.0.unit_cents', 215)
                ->where('recognitions.data.0.ai_check.0.paid', 2)
                ->where('recognitions.data.0.ai_check.0.taken', null)
                ->where('recognitions.data.0.ai_check.0.state', 'na')
                ->where('recognitions.data.0.ai_check.1.name', 'Zed')
                ->where('recognitions.data.0.ai_check.1.paid', 1)
                ->where('recognitions.data.0.ai_check.1.state', 'na')
                ->where('recognitions.data.0.door_error.code', 'LOCK')
                ->where('recognitions.data.0.door_error.text', '1号门锁开锁超时')
                ->where('recognitions.data.0.card.hold_cents', 430)
                ->where('recognitions.data.0.failed_session.amount_cents', 430)
                ->where('recognitions.data.0.can_correct', false)
            );
    }

    /** Brian, 2026-11-12 (50001, 19:22): app 57 reports each try, the camera reads and the sorry page, timed on the machine. */
    public function test_a_failed_door_lists_its_tries_the_wait_and_the_sorry_page(): void
    {
        $ref = 'SF-50001-1793784418-6';
        $this->sessionWithNoSale($ref);
        $hold = $this->holdWithoutSession($this->afterStart($ref, 3), ['provider_txn_id' => 'PAY123', 'approved_at' => $this->afterStart($ref, 4), 'voided_at' => $this->afterStart($ref, 12)]);
        $firstOrderMs = ((int) explode('-', $ref)[2] + 7) * 1000 + 560;
        FreezerFailedSession::create([
            'vend_id' => $this->vend->id, 'session_ref' => $ref, 'payment_method' => 'card', 'payment_ref' => 'PAY123', 'amount_cents' => 430,
            'reason' => 'hw:lock', 'detail' => '1号门锁开锁超时(损坏,锁舌卡住)', 'ended_at' => $this->afterStart($ref, 14), 'items' => [],
            'door_log' => [
                'first_order_at_ms' => $firstOrderMs,
                'error_shown_at_ms' => $firstOrderMs + 6_880,
                'steps' => [
                    ['t_ms' => 0, 'kind' => 'order', 'try' => 1, 'text' => $ref],
                    ['t_ms' => 5_020, 'kind' => 'check', 'try' => 1, 'text' => 'no door yet; AI cameras recording [3] → wait'],
                    ['t_ms' => 6_671, 'kind' => 'refused', 'try' => 1, 'text' => 'LOCK: 1号门锁开锁超时(损坏,锁舌卡住)'],
                ],
            ],
        ]);

        $this->actingAs($this->viewer())->get('/ai-recognition?date_from=')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('recognitions.data.0.door_timeline.reported', true)
                ->where('recognitions.data.0.door_timeline.tries', 1)
                ->where('recognitions.data.0.door_timeline.waited', '6.9 s')
                ->where('recognitions.data.0.door_timeline.sorry_at', Carbon::createFromTimestampMs($firstOrderMs + 6_880, config('app.timezone'))->format('H:i:s'))
                ->where('recognitions.data.0.door_timeline.steps.0', ['offset' => '+0.0 s', 'kind' => 'order', 'text' => 'Try 1: door order sent to the host'])
                ->where('recognitions.data.0.door_timeline.steps.1.text', 'Still shut, cameras checked: no door yet; AI cameras recording [3] → wait')
                ->where('recognitions.data.0.door_timeline.steps.2', ['offset' => '+6.7 s', 'kind' => 'refused', 'text' => 'Try 1 refused: LOCK: 1号门锁开锁超时(损坏,锁舌卡住)'])
                ->where('recognitions.data.0.door_timeline.steps.3', ['offset' => '+6.9 s', 'kind' => 'sorry', 'text' => 'Sorry page shown'])
                ->where('recognitions.data.0.door_timeline.card_approved_at', Carbon::parse($this->afterStart($ref, 4))->format('H:i:s'))
                ->where('recognitions.data.0.door_timeline.card_released_at', Carbon::parse($this->afterStart($ref, 12))->format('H:i:s'))
            );
        $this->assertSame(CardPaymentIntent::STATE_VOIDED, $hold->state);
    }

    /** Before app 57: the tries come from the app's own line ("after N tries", none = one), the sorry page is the report's end. */
    public function test_an_older_report_still_says_the_tries_and_the_sorry_page(): void
    {
        $once = 'SF-50001-1793784418-6';
        $thrice = 'SF-50001-1793784450-7';
        $silent = 'SF-50001-1793784551-8';
        foreach ([$once, $thrice, $silent] as $ref) {
            $this->sessionWithNoSale($ref);
            FreezerFailedSession::create([
                'vend_id' => $this->vend->id, 'session_ref' => $ref, 'payment_method' => 'card', 'amount_cents' => 430,
                'reason' => 'hw:lock', 'detail' => '1号门锁开锁超时(损坏,锁舌卡住)', 'ended_at' => $this->afterStart($ref, 20), 'items' => [],
            ]);
        }
        foreach ([
            ['01KA0000000000000000000011', "Door refused for {$once}: LOCK (1号门锁开锁超时(损坏,锁舌卡住))", $once],
            ['01KA0000000000000000000012', "Door refused for {$thrice} after 3 tries: LOCK (1号门锁开锁超时(损坏,锁舌卡住))", $thrice],
        ] as [$cmdId, $line, $ref]) {
            FreezerControlCommand::forceCreate([
                'vend_id' => $this->vend->id, 'cmd_id' => $cmdId, 'op' => 'error:BoxVmc', 'status' => 'ok', 'source' => 'event',
                'response_msg' => $line, 'created_at' => $this->afterStart($ref, 19),
            ]);
        }

        $this->actingAs($this->viewer())->get('/ai-recognition?date_from=')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('recognitions.data.0.session_ref', $silent)
                ->where('recognitions.data.0.door_timeline.reported', false)
                ->where('recognitions.data.0.door_timeline.tries', null)
                ->where('recognitions.data.0.door_timeline.waited', null)
                ->where('recognitions.data.0.door_timeline.steps', [])
                ->where('recognitions.data.0.door_timeline.sorry_at', Carbon::parse($this->afterStart($silent, 20))->format('H:i:s'))
                ->where('recognitions.data.1.door_timeline.tries', 3)
                // "after 3 tries" used to hide the whole line: the error is read now too.
                ->where('recognitions.data.1.door_error.code', 'LOCK')
                ->where('recognitions.data.1.door_error.text', '1号门锁开锁超时(损坏,锁舌卡住)')
                ->where('recognitions.data.2.door_timeline.tries', 1)
            );
    }

    /** Only a session the machine reported as failed has a door timeline. */
    public function test_a_session_not_reported_as_failed_has_no_door_timeline(): void
    {
        $this->recognition();

        $this->actingAs($this->viewer())->get('/ai-recognition?date_from=')
            ->assertInertia(fn (AssertableInertia $page) => $page->where('recognitions.data.0.door_timeline', null));
    }

    /** Session 7, 2026-11-04: the app dropped the log line, its saved record says only door_open_failed. */
    public function test_a_report_without_the_machines_words_shows_the_apps_fault_code(): void
    {
        $ref = 'SF-50001-1793784450-7';
        $this->sessionWithNoSale($ref);
        FreezerFailedSession::create([
            'vend_id' => $this->vend->id, 'session_ref' => $ref, 'payment_method' => 'paynow_qr', 'amount_cents' => 430,
            'reason' => 'door_open_failed', 'ended_at' => $this->afterStart($ref, 10), 'items' => [],
        ]);

        $this->actingAs($this->viewer())->get('/ai-recognition?date_from=')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('recognitions.data.0.door_error.code', 'DOOR_OPEN_FAILED')
                ->where('recognitions.data.0.door_error.plain', DoorFault::plain('DOOR_OPEN_FAILED'))
                ->where('recognitions.data.0.failed_session.method', 'PayNow')
                ->where('recognitions.data.0.paid_without_sale', null)
            );
    }

    /** What would catch goods leaving through a door that reported a failure. */
    public function test_goods_the_ai_saw_on_a_failed_session_stay_visible(): void
    {
        $product = Product::forceCreate(['code' => 'A', 'name' => 'Aye', 'operator_id' => 1, 'barcode' => '111']);
        $ref = 'SF-50001-1793784450-7';
        $this->recognition(['trade_id' => $ref, 'session_ref' => $ref, 'status' => SmartFreezerRecognition::STATUS_COMPLETED, 'order_status' => 0, 'items' => ['111' => 1]]);
        FreezerFailedSession::create([
            'vend_id' => $this->vend->id, 'session_ref' => $ref, 'payment_method' => 'card', 'amount_cents' => 215, 'reason' => 'door_open_failed',
            'items' => [['sku' => 'A', 'name' => 'Aye', 'unit_cents' => 215, 'qty' => 1, 'product_id' => $product->id]],
        ]);

        $this->actingAs($this->viewer())->get('/ai-recognition?date_from=')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('recognitions.data.0.ai_check.0.paid', 1)
                ->where('recognitions.data.0.ai_check.0.taken', 1)
                ->where('recognitions.data.0.ai_check.0.state', 'unknown')
            );
    }

    public function test_each_hold_goes_to_the_latest_session_before_it(): void
    {
        $first = 'SF-50001-1793784418-6';
        $second = 'SF-50001-1793784450-7';
        $this->sessionWithNoSale($first);
        $this->sessionWithNoSale($second);
        $this->holdWithoutSession($this->afterStart($first, 3), ['amount_cents' => 430]);
        $this->holdWithoutSession($this->afterStart($second, 2), ['amount_cents' => 400, 'payment_method' => 'visa']);

        $this->actingAs($this->viewer())->get('/ai-recognition?date_from=')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('recognitions.data.0.session_ref', $second)
                ->where('recognitions.data.0.card.hold_cents', 400)
                ->where('recognitions.data.0.paid_without_sale.method', 'Visa')
                ->where('recognitions.data.0.door_error', null)
                ->where('recognitions.data.1.session_ref', $first)
                ->where('recognitions.data.1.card.hold_cents', 430)
            );
    }

    public function test_a_session_with_no_approved_hold_claims_no_payment(): void
    {
        $ref = 'SF-50001-1793784418-6';
        $this->sessionWithNoSale($ref);
        // Never reached a card, far too late for this session, and on another machine.
        $this->holdWithoutSession($this->afterStart($ref, 5), ['state' => CardPaymentIntent::STATE_CANCELLED, 'approved_at' => null, 'voided_at' => null]);
        $this->holdWithoutSession($this->afterStart($ref, 601));
        $other = Vend::create(['code' => 50002, 'machine_type' => Vend::MACHINE_TYPE_SMART_FREEZER, 'is_active' => 1, 'operator_id' => 1]);
        $this->holdWithoutSession($this->afterStart($ref, 5), ['vend_id' => $other->id]);

        $this->actingAs($this->viewer())->get('/ai-recognition?date_from=')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('recognitions.data.0.card', null)
                ->where('recognitions.data.0.paid_without_sale', null)
                ->where('recognitions.data.0.status_reason', "sale not found yet for {$ref}")
            );
    }

    public function test_a_remote_camera_check_claims_no_payment(): void
    {
        $ref = 'SF-50001-1793784418-0';
        $this->sessionWithNoSale($ref);
        $this->holdWithoutSession($this->afterStart($ref, 5));

        $this->actingAs($this->viewer())->get('/ai-recognition?date_from=')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('recognitions.data.0.card', null)
                ->where('recognitions.data.0.paid_without_sale', null)
            );
    }

    public function test_a_session_with_a_sale_never_takes_a_hold_without_a_session(): void
    {
        $ref = 'SF-50001-1793784418-6';
        $sale = VendTransaction::forceCreate([
            'order_id' => 'O-200', 'vend_id' => $this->vend->id, 'vend_channel_id' => 0, 'amount' => 300,
            'transaction_datetime' => Carbon::parse($this->afterStart($ref, 40)), 'gst_vat_rate' => 9, 'operator_id' => 1,
        ]);
        $this->recognition(['trade_id' => $ref, 'session_ref' => $ref, 'vend_transaction_id' => $sale->id, 'status' => SmartFreezerRecognition::STATUS_COMPLETED, 'order_status' => 0]);
        $this->holdWithoutSession($this->afterStart($ref, 3));

        $this->actingAs($this->viewer())->get('/ai-recognition?date_from=')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('recognitions.data.0.card', null)
                ->where('recognitions.data.0.paid_without_sale', null)
            );
    }

    public function test_a_row_says_how_long_the_result_took_from_the_door_session(): void
    {
        $answered = $this->recognition(['trade_id' => 'ANSWERED', 'completed_at' => '2026-11-03 22:27:42']);
        $answered->forceFill(['created_at' => '2026-11-03 22:25:30'])->save();
        $this->recognition(['trade_id' => 'WAITING']);

        $this->actingAs($this->viewer())->get('/ai-recognition?date_from=')->assertInertia(function (AssertableInertia $page) {
            $rows = collect($page->toArray()['props']['recognitions']['data'])->keyBy('trade_id');
            $this->assertSame(132, $rows['ANSWERED']['result_seconds']);
            $this->assertNull($rows['WAITING']['result_seconds']);
        });
    }

    public function test_a_row_names_the_site_the_sale_was_booked_to_with_its_rp(): void
    {
        $booked = Customer::withoutGlobalScopes()->forceCreate(['name' => 'Hillview', 'selling_price_type' => 2, 'operator_id' => 1]);
        $now = Customer::withoutGlobalScopes()->forceCreate(['name' => 'Moved to', 'selling_price_type' => 3, 'operator_id' => 1]);
        $this->vend->forceFill(['customer_id' => $now->id])->save();
        $sale = VendTransaction::forceCreate(['order_id' => 'O-1', 'vend_id' => $this->vend->id, 'vend_channel_id' => 0, 'customer_id' => $booked->id, 'amount' => 100, 'transaction_datetime' => Carbon::now(), 'gst_vat_rate' => 9, 'operator_id' => 1]);
        $this->recognition(['vend_transaction_id' => $sale->id, 'trade_id' => 'WITH-SALE']);
        $this->recognition(['trade_id' => 'NO-SALE']);

        $this->actingAs($this->viewer())->get('/ai-recognition?date_from=')->assertInertia(function (AssertableInertia $page) {
            $rows = collect($page->toArray()['props']['recognitions']['data'])->keyBy('trade_id');
            $this->assertSame(['Hillview', 'RP2'], [$rows['WITH-SALE']['site']['name'], $rows['WITH-SALE']['site']['rp']]);
            $this->assertSame(['Moved to', 'RP3'], [$rows['NO-SALE']['site']['name'], $rows['NO-SALE']['site']['rp']], 'no sale yet: the machine\'s Site');
        });
    }

    public function test_a_line_priced_by_happy_hour_says_so(): void
    {
        $single = Product::forceCreate(['code' => 'A', 'name' => 'Aye', 'operator_id' => 1, 'barcode' => '111']);
        $promoLine = Product::forceCreate(['code' => 'B', 'name' => 'Bee', 'operator_id' => 1, 'barcode' => '222']);
        $listLine = Product::forceCreate(['code' => 'C', 'name' => 'Cee', 'operator_id' => 1, 'barcode' => '333']);
        $extra = Product::forceCreate(['code' => 'D', 'name' => 'Dee', 'operator_id' => 1, 'barcode' => '444']);
        $campaign = HappyHourCampaign::forceCreate(['name' => 'Afternoon', 'window_start' => '00:00:00', 'window_end' => '23:59:00', 'discount_pct' => 30, 'price_step_cents' => 10, 'operator_id' => 1]);
        $slot = fn (Product $product, int $pct) => HappyHourSlot::create([
            'happy_hour_campaign_id' => $campaign->id, 'vend_id' => $this->vend->id, 'product_id' => $product->id,
            'slot_date' => Carbon::now()->toDateString(), 'position' => 0, 'starts_at' => Carbon::now()->subMinutes(30), 'ends_at' => Carbon::now()->addMinutes(30),
            'original_price' => 300, 'promo_price' => 210, 'discount_pct' => $pct, 'rank' => 1,
        ]);

        $singleSale = VendTransaction::forceCreate(['order_id' => 'O-1', 'vend_id' => $this->vend->id, 'vend_channel_id' => 0, 'amount' => 210, 'transaction_datetime' => Carbon::now(), 'gst_vat_rate' => 9, 'operator_id' => 1,
            'happy_hour_slot_id' => $slot($single, 30)->id,
            'vend_transaction_json' => ['transf_info' => [['goods_id' => $single->id, 'Price' => 210]]]]);
        $this->recognition(['vend_transaction_id' => $singleSale->id, 'status' => SmartFreezerRecognition::STATUS_COMPLETED, 'order_status' => 0, 'verdict' => 'match', 'created_at' => Carbon::now(), 'verdict_lines' => [
            ['product_id' => $single->id, 'code' => '111', 'paid' => 1, 'taken' => 1, 'delta' => 0],
        ]]);

        $promoSlot = $slot($promoLine, 20);
        $slot($extra, 40);
        $basket = VendTransaction::forceCreate(['order_id' => 'O-2', 'vend_id' => $this->vend->id, 'vend_channel_id' => 0, 'amount' => 510, 'is_multiple' => true, 'transaction_datetime' => Carbon::now()->subMinute(), 'gst_vat_rate' => 9, 'operator_id' => 1,
            'happy_hour_slot_id' => $promoSlot->id,
            'vend_transaction_json' => ['transf_info' => [['goods_id' => $promoLine->id, 'Price' => 210], ['goods_id' => $listLine->id, 'Price' => 300]]]]);
        VendTransactionItem::forceCreate(['vend_transaction_id' => $basket->id, 'product_id' => $promoLine->id, 'unit_price_amount' => 210, 'vend_channel_id' => 0, 'happy_hour_slot_id' => $promoSlot->id]);
        VendTransactionItem::forceCreate(['vend_transaction_id' => $basket->id, 'product_id' => $listLine->id, 'unit_price_amount' => 300, 'vend_channel_id' => 0]);
        $this->recognition(['vend_transaction_id' => $basket->id, 'status' => SmartFreezerRecognition::STATUS_COMPLETED, 'order_status' => 0, 'verdict' => 'took_more', 'created_at' => Carbon::now()->subMinute(), 'verdict_lines' => [
            ['product_id' => $promoLine->id, 'code' => '222', 'paid' => 1, 'taken' => 1, 'delta' => 0],
            ['product_id' => $listLine->id, 'code' => '333', 'paid' => 1, 'taken' => 1, 'delta' => 0],
            ['product_id' => $extra->id, 'code' => '444', 'paid' => 0, 'taken' => 1, 'delta' => 1],
        ]]);

        $this->actingAs($this->viewer())->get('/ai-recognition?date_from=')->assertInertia(function (AssertableInertia $page) {
            $rows = $page->toArray()['props']['recognitions']['data'];
            $this->assertSame('HH 30%', $rows[0]['ai_check'][0]['happy_hour']);
            $lines = collect($rows[1]['ai_check'])->keyBy('code');
            $this->assertSame('HH 20%', $lines['B']['happy_hour']);
            $this->assertNull($lines['C']['happy_hour'], 'a list-price line of the same basket');
            $this->assertSame('HH 40%', $lines['D']['happy_hour'], 'an extra unit: the slot at that moment');
        });
    }

    public function test_a_persons_count_sits_on_the_lines_with_who_and_when(): void
    {
        $paid = Product::forceCreate(['code' => 'A', 'name' => 'Aye', 'operator_id' => 1, 'barcode' => '111']);
        $removed = Product::forceCreate(['code' => 'B', 'name' => 'Bee', 'operator_id' => 1, 'barcode' => '222']);
        $added = Product::forceCreate(['code' => 'C', 'name' => 'Cee', 'operator_id' => 1, 'barcode' => '333']);
        $sale = VendTransaction::forceCreate(['order_id' => 'O-1', 'vend_id' => $this->vend->id, 'vend_channel_id' => 0, 'amount' => 500, 'transaction_datetime' => Carbon::now(), 'gst_vat_rate' => 9, 'operator_id' => 1,
            'vend_transaction_json' => ['transf_info' => [['goods_id' => $paid->id, 'Price' => 100], ['goods_id' => $removed->id, 'Price' => 200]]],
            'meta_json' => ['correction' => [
                'at' => '2026-11-04T08:40:50+08:00', 'by' => ['name' => 'Daniel', 'user_id' => 2], 'value_cents' => 500,
                'basket' => [$paid->id => 2, $added->id => 1],
                'lines' => [['product_id' => $paid->id, 'name' => 'Aye', 'qty' => 2, 'unit_cents' => 100], ['product_id' => $added->id, 'name' => 'Cee', 'qty' => 1, 'unit_cents' => 300]],
            ]]]);
        $this->recognition(['vend_transaction_id' => $sale->id, 'status' => SmartFreezerRecognition::STATUS_COMPLETED, 'order_status' => 0, 'verdict' => 'took_more', 'verdict_lines' => [
            ['product_id' => $paid->id, 'code' => '111', 'paid' => 1, 'taken' => 3, 'delta' => 2],
            ['product_id' => $removed->id, 'code' => '222', 'paid' => 1, 'taken' => 1, 'delta' => 0],
        ]]);

        $this->actingAs($this->viewer())->get('/ai-recognition?date_from=')->assertInertia(function (AssertableInertia $page) {
            $row = $page->toArray()['props']['recognitions']['data'][0];
            $this->assertSame(['Daniel', '261104 08:40 am'], [$row['correction']['by'], $row['correction']['at']]);
            $lines = collect($row['ai_check'])->keyBy('code');
            $this->assertSame(2, $lines['A']['adjusted']);
            $this->assertSame(0, $lines['B']['adjusted'], 'left out of the count = none taken');
            $this->assertSame([1, 0, 0, 300, 'none'], [$lines['C']['adjusted'], $lines['C']['paid'], $lines['C']['taken'], $lines['C']['unit_cents'], $lines['C']['state']], 'only the person counted it');
            $this->assertFalse($row['correction']['confirms_ai'], 'the count differs from what the AI saw');
        });
    }

    /**
     * Brian, 2026-11-12 (his own test on 50002): paid for B, took A; the AI saw A, and Daniel's count said the
     * same. The person confirmed the AI, so the row must not read as a correction, and the dashboard counts
     * every unit as the AI's.
     */
    public function test_a_count_equal_to_what_the_ai_saw_confirms_it(): void
    {
        $took = Product::forceCreate(['code' => 'A', 'name' => 'Aye', 'operator_id' => 1, 'barcode' => '111']);
        $paid = Product::forceCreate(['code' => 'B', 'name' => 'Bee', 'operator_id' => 1, 'barcode' => '222']);
        $sale = VendTransaction::forceCreate(['order_id' => 'O-1', 'vend_id' => $this->vend->id, 'vend_channel_id' => 0, 'amount' => 170, 'transaction_datetime' => Carbon::now(), 'gst_vat_rate' => 9, 'operator_id' => 1,
            'vend_transaction_json' => ['transf_info' => [['goods_id' => $paid->id, 'Price' => 120]]],
            'meta_json' => ['correction' => [
                'at' => '2026-11-12T16:54:00+08:00', 'by' => ['name' => 'Daniel', 'user_id' => 2], 'value_cents' => 170,
                'basket' => [$took->id => 1],
                'lines' => [['product_id' => $took->id, 'name' => 'Aye', 'qty' => 1, 'unit_cents' => 170]],
            ]]]);
        $this->recognition(['vend_transaction_id' => $sale->id, 'status' => SmartFreezerRecognition::STATUS_COMPLETED, 'order_status' => 0, 'verdict' => 'mixed', 'verdict_lines' => [
            ['product_id' => $took->id, 'code' => '111', 'paid' => 0, 'taken' => 1, 'delta' => 1],
            ['product_id' => $paid->id, 'code' => '222', 'paid' => 1, 'taken' => 0, 'delta' => -1],
        ]]);

        $this->actingAs($this->viewer())->get('/ai-recognition?date_from=')->assertInertia(function (AssertableInertia $page) {
            $props = $page->toArray()['props'];
            $row = $props['recognitions']['data'][0];
            $this->assertTrue($row['correction']['confirms_ai']);
            $this->assertSame('Daniel', $row['correction']['by']);
            $lines = collect($row['ai_check'])->keyBy('code');
            $this->assertSame([1, 1], [$lines['A']['adjusted'], $lines['A']['taken']], 'same as the AI: drawn white, not blue');
            $this->assertSame([0, 0], [$lines['B']['adjusted'], $lines['B']['taken']]);
            $this->assertSame([1, 0], [$props['dashboard']['correct_qty'], $props['dashboard']['adjusted_qty']]);
        });
    }

    public function test_the_ai_check_lines_have_no_adjusted_count_until_a_person_gives_one(): void
    {
        $product = Product::forceCreate(['code' => 'A', 'name' => 'Aye', 'operator_id' => 1, 'barcode' => '111']);
        $sale = VendTransaction::forceCreate(['order_id' => 'O-1', 'vend_id' => $this->vend->id, 'vend_channel_id' => 0, 'amount' => 100, 'transaction_datetime' => Carbon::now(), 'gst_vat_rate' => 9, 'operator_id' => 1,
            'vend_transaction_json' => ['transf_info' => [['goods_id' => $product->id, 'Price' => 100]]]]);
        $this->recognition(['vend_transaction_id' => $sale->id, 'status' => SmartFreezerRecognition::STATUS_COMPLETED, 'order_status' => 0, 'verdict' => 'match', 'verdict_lines' => [
            ['product_id' => $product->id, 'code' => '111', 'paid' => 1, 'taken' => 1, 'delta' => 0],
        ]]);

        $this->actingAs($this->viewer())->get('/ai-recognition?date_from=')->assertInertia(fn (AssertableInertia $page) => $page
            ->where('recognitions.data.0.correction', null)
            ->where('recognitions.data.0.ai_check.0.adjusted', null));
    }

    public function test_each_refund_that_went_back_says_when_and_who(): void
    {
        $terminal = RemoteCardTerminal::create(['vend_id' => $this->vend->id, 'provider' => 'payrallel', 'access_token' => 'tok', 'is_active' => true]);
        $intent = CardPaymentIntent::create([
            'vend_id' => $this->vend->id, 'remote_card_terminal_id' => $terminal->id, 'provider' => 'payrallel', 'reference' => 'SF1', 'custom_order_id' => '50001-SF1',
            'session_ref' => 'SF-50001-1-1', 'mode' => CardPaymentIntent::MODE_PREAUTH, 'amount_cents' => 1000, 'captured_cents' => 1140, 'refunded_cents' => 140, 'state' => CardPaymentIntent::STATE_CAPTURED,
        ]);
        foreach ([[CardRefund::STATE_REFUNDED, 140], [CardRefund::STATE_REFUSED, 50]] as [$state, $cents]) {
            $refund = CardRefund::create(['card_payment_intent_id' => $intent->id, 'kind' => CardRefund::KIND_CORRECTION, 'amount_cents' => $cents, 'state' => $state, 'requested_by_name' => 'Daniel']);
            $refund->forceFill(['created_at' => '2026-11-04 08:42:21'])->save();
        }
        $this->recognition(['session_ref' => 'SF-50001-1-1']);

        $this->actingAs($this->viewer())->get('/ai-recognition?date_from=')->assertInertia(fn (AssertableInertia $page) => $page
            ->has('recognitions.data.0.card.refunds', 1) // a refused refund is not money back
            ->where('recognitions.data.0.card.refunds.0.amount_cents', 140)
            ->where('recognitions.data.0.card.refunds.0.at', '2026-11-04 08:42')
            ->where('recognitions.data.0.card.refunds.0.by', 'Daniel'));
    }

    public function test_the_ai_check_waits_for_an_answer_and_names_a_barcode_we_do_not_know(): void
    {
        $product = Product::forceCreate(['code' => 'A', 'name' => 'Aye', 'operator_id' => 1, 'barcode' => '111']);
        $sale = VendTransaction::forceCreate(['order_id' => 'O-1', 'vend_id' => $this->vend->id, 'vend_channel_id' => 0, 'amount' => 100, 'transaction_datetime' => Carbon::now(), 'gst_vat_rate' => 9, 'operator_id' => 1,
            'vend_transaction_json' => ['Type' => 'TRADE', 'transf_info' => [['goods_id' => $product->id, 'Price' => 100]]]]);
        $this->recognition(['vend_transaction_id' => $sale->id, 'status' => SmartFreezerRecognition::STATUS_SUBMITTED]);

        $this->actingAs($this->viewer())->get('/ai-recognition?date_from=')->assertInertia(fn (AssertableInertia $page) => $page
            ->where('recognitions.data.0.ai_check.0.state', 'pending')
            ->where('recognitions.data.0.ai_check.0.taken', null));

        SmartFreezerRecognition::query()->update(['order_status' => 0, 'verdict' => 'unrecognised', 'verdict_lines' => [
            ['product_id' => $product->id, 'code' => '111', 'paid' => 1, 'taken' => 1, 'delta' => 0],
            ['product_id' => null, 'code' => '999', 'paid' => 0, 'taken' => 1, 'delta' => 1],
        ]]);
        $this->actingAs($this->viewer())->get('/ai-recognition?date_from=')->assertInertia(fn (AssertableInertia $page) => $page
            ->where('recognitions.data.0.ai_check.0.state', 'unknown')
            ->where('recognitions.data.0.ai_check.0.name', '999')
            ->where('recognitions.data.0.ai_check.1.state', 'ok'));
    }

    public function test_a_hold_the_ai_cannot_judge_offers_key_in_charge(): void
    {
        $terminal = RemoteCardTerminal::create([
            'vend_id' => $this->vend->id, 'provider' => RemoteCardTerminal::PROVIDER_PAYRALLEL, 'access_token' => 't', 'is_active' => true,
        ]);
        CardPaymentIntent::forceCreate([
            'vend_id' => $this->vend->id, 'remote_card_terminal_id' => $terminal->id, 'provider' => 'payrallel',
            'reference' => 'SF1', 'custom_order_id' => '50001-SF1', 'session_ref' => 'SF-50001-1790732333-1',
            'mode' => 'preauth', 'amount_cents' => 880, 'state' => CardPaymentIntent::STATE_AWAITING_AI,
        ]);
        $this->recognition(['session_ref' => 'SF-50001-1790732333-1', 'status' => SmartFreezerRecognition::STATUS_COMPLETED, 'verdict' => 'incomplete']);

        $this->actingAs($this->viewer())->get('/ai-recognition?date_from=')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('recognitions.data.0.card.person_reason', CardPaymentService::PERSON_AI_UNSURE)
                ->where('recognitions.data.0.card.awaits_person', true)
                ->where('recognitions.data.0.card.ai_suggested_cents', null));
    }

    public function test_a_mismatch_offers_key_in_charge_with_the_ai_count(): void
    {
        $terminal = RemoteCardTerminal::create([
            'vend_id' => $this->vend->id, 'provider' => RemoteCardTerminal::PROVIDER_PAYRALLEL, 'access_token' => 't', 'is_active' => true,
        ]);
        CardPaymentIntent::forceCreate([
            'vend_id' => $this->vend->id, 'remote_card_terminal_id' => $terminal->id, 'provider' => 'payrallel',
            'reference' => 'SF1', 'custom_order_id' => '50001-SF1', 'session_ref' => 'SF-50001-1790732333-1',
            'mode' => 'preauth', 'amount_cents' => 300, 'state' => CardPaymentIntent::STATE_AWAITING_AI,
        ]);
        $sale = VendTransaction::forceCreate([
            'order_id' => 'O-1', 'vend_id' => $this->vend->id, 'vend_channel_id' => 0, 'amount' => 300,
            'transaction_datetime' => Carbon::now(), 'gst_vat_rate' => 9, 'operator_id' => 1,
            'vend_transaction_json' => ['Type' => 'TRADE', 'TXN_SRC' => 1, 'transf_info' => [['goods_id' => 1, 'goods_name' => 'x', 'Price' => 300]]],
        ]);
        $this->recognition([
            'session_ref' => 'SF-50001-1790732333-1', 'status' => SmartFreezerRecognition::STATUS_COMPLETED, 'callback_verified' => true,
            'vend_transaction_id' => $sale->id, 'verdict' => 'took_more',
            'verdict_lines' => [['product_id' => 1, 'code' => '111', 'paid' => 1, 'taken' => 2, 'delta' => 1]],
        ]);

        $this->actingAs($this->viewer())->get('/ai-recognition?date_from=')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('recognitions.data.0.card.person_reason', CardPaymentService::PERSON_AI_MISMATCH)
                ->where('recognitions.data.0.card.awaits_person', true)
                ->where('recognitions.data.0.card.ai_suggested_cents', 600));
    }

    public function test_a_row_lists_what_the_trade_paid_for_and_flags_products_the_ai_was_not_asked_about(): void
    {
        $original = Product::forceCreate(['code' => 'CC-01', 'name' => 'Basque Original', 'operator_id' => 1, 'barcode' => '9726436016148']);
        $chocolate = Product::forceCreate(['code' => 'CC-02', 'name' => 'Basque Chocolate', 'operator_id' => 1]);
        $sale = VendTransaction::forceCreate([
            'order_id' => 'O-9', 'vend_id' => $this->vend->id, 'vend_channel_id' => 0, 'amount' => 33,
            'transaction_datetime' => Carbon::parse('2026-10-05 13:59:25'), 'gst_vat_rate' => 9, 'operator_id' => 1,
            'vend_transaction_json' => ['Type' => 'TRADE', 'transf_info' => [
                ['goods_id' => $original->id, 'goods_name' => 'x', 'Price' => 11],
                ['goods_id' => $chocolate->id, 'goods_name' => 'y', 'Price' => 22],
                ['goods_id' => $chocolate->id, 'goods_name' => 'y', 'Price' => 22],
            ]],
        ]);
        $this->recognition(['vend_transaction_id' => $sale->id, 'status' => SmartFreezerRecognition::STATUS_COMPLETED, 'verdict' => 'incomplete']);

        $this->actingAs($this->viewer())->get('/ai-recognition?date_from=')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('recognitions.data.0.purchased', [
                    ['product_id' => $original->id, 'name' => 'Basque Original', 'code' => 'CC-01', 'qty' => 1, 'unit_cents' => 11, 'has_barcode' => true, 'barcode' => '9726436016148', 'in_submitted' => null],
                    ['product_id' => $chocolate->id, 'name' => 'Basque Chocolate', 'code' => 'CC-02', 'qty' => 2, 'unit_cents' => 22, 'has_barcode' => false, 'barcode' => null, 'in_submitted' => null],
                ])
                ->where('recognitions.data.0.submitted', null));
    }

    public function test_a_row_shows_the_product_list_sent_to_the_ai_and_flags_a_paid_product_missing_from_it(): void
    {
        $original = Product::forceCreate(['code' => 'CC-01', 'name' => 'Basque Original', 'operator_id' => 1, 'barcode' => '9726436016148']);
        // Barcode set after the session was sent: on the sale, not on the list the AI had.
        $matcha = Product::forceCreate(['code' => 'CC-03', 'name' => 'Basque Matcha', 'operator_id' => 1, 'barcode' => '0726436016209']);
        $sale = VendTransaction::forceCreate([
            'order_id' => 'O-10', 'vend_id' => $this->vend->id, 'vend_channel_id' => 0, 'amount' => 33,
            'transaction_datetime' => Carbon::parse('2026-11-03 10:37:45'), 'gst_vat_rate' => 9, 'operator_id' => 1,
            'vend_transaction_json' => ['Type' => 'TRADE', 'transf_info' => [
                ['goods_id' => $original->id, 'goods_name' => 'x', 'Price' => 11],
                ['goods_id' => $matcha->id, 'goods_name' => 'z', 'Price' => 22],
            ]],
        ]);
        $this->recognition([
            'vend_transaction_id' => $sale->id, 'status' => SmartFreezerRecognition::STATUS_SUBMITTED,
            'request_payload' => ['goodsList' => [
                ['positions' => [], 'sn' => '9726436016148'],
                ['positions' => [], 'sn' => '8801104170116'],
            ]],
        ]);

        $this->actingAs($this->viewer())->get('/ai-recognition?date_from=')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('recognitions.data.0.submitted', [
                    ['code' => '9726436016148', 'name' => 'Basque Original'],
                    ['code' => '8801104170116', 'name' => null],
                ])
                ->where('recognitions.data.0.purchased.0.in_submitted', true)
                ->where('recognitions.data.0.purchased.1.in_submitted', false));
    }

    public function test_the_finer_ai_reason_is_shown(): void
    {
        $this->recognition([
            'status' => SmartFreezerRecognition::STATUS_FAILED, 'order_status' => 501,
            'callback_payload' => ['bizContent' => json_encode(['tradeId' => 'X', 'orderStatus' => 501, 'jsOrderStatus' => 503, 'jsOrderStatusName' => '商品未上架'])],
        ]);

        $this->actingAs($this->viewer())->get('/ai-recognition?date_from=')
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
        $count = fn (array $q) => $this->actingAs($viewer)->get('/ai-recognition?date_from=&'.http_build_query($q))->viewData('page')['props']['recognitions']['meta']['total'];

        $this->assertSame(3, $count([]));
        $this->assertSame(2, $count(['status' => 'pending']));
        $this->assertSame(1, $count(['verdict' => 'match']));
        $this->assertSame(2, $count(['verdict' => 'none']));
        $this->assertSame(1, $count(['search' => '2009']));
    }

    public function test_order_status_0_is_processed_by_ai_and_any_other_code_by_a_human(): void
    {
        $this->recognition(['status' => SmartFreezerRecognition::STATUS_COMPLETED, 'order_status' => 0])->forceFill(['created_at' => Carbon::parse('2026-10-09 10:00:00')])->save();
        $this->recognition(['status' => SmartFreezerRecognition::STATUS_FAILED, 'order_status' => 501])->forceFill(['created_at' => Carbon::parse('2026-10-09 09:00:00')])->save();
        $this->recognition(['status' => SmartFreezerRecognition::STATUS_COMPLETED, 'order_status' => 401])->forceFill(['created_at' => Carbon::parse('2026-10-09 08:00:00')])->save();
        $this->recognition(['status' => SmartFreezerRecognition::STATUS_SUBMITTED])->forceFill(['created_at' => Carbon::parse('2026-10-09 07:00:00')])->save();

        $viewer = $this->viewer();
        $this->actingAs($viewer)->get('/ai-recognition?date_from=')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('recognitions.data.0.processed_by', 'ai')
                ->where('recognitions.data.1.processed_by', 'human')
                ->where('recognitions.data.2.processed_by', 'human')
                ->where('recognitions.data.3.processed_by', null)
                ->where('filters.processed_by', 'all'));

        $this->actingAs($viewer)->get('/ai-recognition?date_from=')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('orderStatusLegend', 7)
                ->where('orderStatusLegend.0', ['code' => 0, 'label' => 'normal', 'name_zh' => '正常', 'processed_by' => 'ai', 'plain' => 'the AI answered on its own'])
                ->where('orderStatusLegend.3', ['code' => 502, 'label' => 'unfriendly behaviour', 'name_zh' => '非友好行为', 'processed_by' => 'human', 'plain' => 'the items were handled unusually']));

        $count = fn (array $q) => $this->actingAs($viewer)->get('/ai-recognition?date_from=&'.http_build_query($q))->viewData('page')['props']['recognitions']['meta']['total'];
        $this->assertSame(4, $count(['processed_by' => 'all']));
        $this->assertSame(1, $count(['processed_by' => 'ai']));
        $this->assertSame(2, $count(['processed_by' => 'human']));
        $this->assertSame(1, $count(['processed_by' => 'none']));
        $this->actingAs($viewer)->get('/ai-recognition?date_from=&processed_by=robot')->assertSessionHasErrors('processed_by');
    }

    public function test_machine_id_and_date_filters_narrow_the_list(): void
    {
        $this->recognition()->forceFill(['created_at' => Carbon::parse('2026-09-30 09:15:00')])->save();
        $this->recognition()->forceFill(['created_at' => Carbon::parse('2026-09-30 13:28:00')])->save();
        $this->recognition()->forceFill(['created_at' => Carbon::parse('2026-09-29 13:40:00')])->save();
        $chiller = Vend::create(['code' => 6003, 'code_prefix' => 'C', 'machine_type' => Vend::MACHINE_TYPE_SMART_FREEZER, 'is_active' => 1, 'operator_id' => 1]);
        $this->recognition(['vend_id' => $chiller->id])->forceFill(['created_at' => Carbon::parse('2026-09-30 13:00:00')])->save();

        $viewer = $this->viewer();
        $count = fn (array $q) => $this->actingAs($viewer)->get('/ai-recognition?date_from=&'.http_build_query($q))->viewData('page')['props']['recognitions']['meta']['total'];

        $this->assertSame(3, $count(['codes' => '50001']));
        $this->assertSame(1, $count(['codes' => 'C6003']));
        $this->assertSame(4, $count(['codes' => '50001, C6003']));
        // Dates only: a whole day, end to end.
        $this->assertSame(3, $count(['date_from' => '2026-09-30', 'date_to' => '2026-09-30']));
        $this->assertSame(2, $count(['codes' => '50001', 'date_from' => '2026-09-30', 'date_to' => '2026-09-30']));
        $this->assertSame(4, $count(['date_from' => '2026-09-29']));
    }

    /** A T05 hold awaiting the AI on 50001, whose session the AI answered with 501 and no verdict. */
    private function holdTheAiCouldNotDecide(): array
    {
        config(['payrallel.min_query_interval_ms' => 0, 'payrallel.mode' => 'preauth', 'payrallel.ai_capture' => true]);
        $gateway = new FakeRemoteCardTerminalGateway;
        $this->app->instance(PayrallelGateway::class, $gateway);
        RemoteCardTerminal::create([
            'vend_id' => $this->vend->id, 'provider' => RemoteCardTerminal::PROVIDER_PAYRALLEL, 'access_token' => 't', 'is_active' => true,
        ]);
        $payments = $this->app->make(CardPaymentService::class);
        $intent = $payments->authorize($this->vend, 'SF1', 760);
        $gateway->answer('50001-SF1', TerminalTransaction::APPROVED);
        $payments->capture($payments->refresh($intent), 760, 'SF-50001-1791100000-3');
        $recognition = $this->recognition([
            'session_ref' => 'SF-50001-1791100000-3', 'status' => SmartFreezerRecognition::STATUS_FAILED, 'order_status' => 501,
        ]);

        return [$gateway, $recognition];
    }

    private function chargeUrl(): string
    {
        return '/ai-recognition/card-holds/'.CardPaymentIntent::sole()->id.'/charge';
    }

    public function test_a_hold_the_device_never_confirmed_is_listed_for_a_person_to_key_in(): void
    {
        config(['payrallel.min_query_interval_ms' => 0, 'payrallel.mode' => 'preauth', 'payrallel.ai_capture' => true, 'payrallel.ai_person_after_minutes' => 30]);
        $gateway = new FakeRemoteCardTerminalGateway;
        $this->app->instance(PayrallelGateway::class, $gateway);
        RemoteCardTerminal::create([
            'vend_id' => $this->vend->id, 'provider' => RemoteCardTerminal::PROVIDER_PAYRALLEL, 'access_token' => 't', 'is_active' => true,
        ]);
        $payments = $this->app->make(CardPaymentService::class);
        $intent = $payments->authorize($this->vend, 'SF9', 500);
        $gateway->answer('50001-SF9', TerminalTransaction::APPROVED);
        $payments->refresh($intent);

        Carbon::setTestNow(Carbon::now()->addMinutes(16));
        $payments->reconcile();
        $this->assertSame([], $gateway->callsOf('capture'));

        Carbon::setTestNow(Carbon::now()->addHour());
        $this->actingAs($this->charger())->get('/ai-recognition?date_from=')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('sessionlessHolds', 1)
                ->where('sessionlessHolds.0.id', $intent->id)
                ->where('sessionlessHolds.0.session_ref', $intent->reference));
    }

    public function test_a_hold_the_ai_never_received_is_listed_for_a_person_to_key_in(): void
    {
        config(['payrallel.min_query_interval_ms' => 0, 'payrallel.mode' => 'preauth', 'payrallel.ai_capture' => true, 'payrallel.ai_person_after_minutes' => 30]);
        $gateway = new FakeRemoteCardTerminalGateway;
        $this->app->instance(PayrallelGateway::class, $gateway);
        RemoteCardTerminal::create([
            'vend_id' => $this->vend->id, 'provider' => RemoteCardTerminal::PROVIDER_PAYRALLEL, 'access_token' => 't', 'is_active' => true,
        ]);
        $payments = $this->app->make(CardPaymentService::class);
        $intent = $payments->authorize($this->vend, 'SF9', 500);
        $gateway->answer('50001-SF9', TerminalTransaction::APPROVED);
        $payments->capture($payments->refresh($intent), 500, 'SF-50001-1791200000-9'); // no video push ever came

        $this->actingAs($this->charger())->get('/ai-recognition?date_from=')
            ->assertInertia(fn (AssertableInertia $page) => $page->has('sessionlessHolds', 0)); // the AI may still answer

        Carbon::setTestNow(Carbon::now()->addHour());
        $this->actingAs($this->charger())->get('/ai-recognition?date_from=')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('sessionlessHolds', 1)
                ->where('sessionlessHolds.0.id', $intent->id)
                ->where('sessionlessHolds.0.hold_cents', 500)
                ->where('sessionlessHolds.0.person_reason', CardPaymentService::PERSON_AI_NO_ANSWER));
        $this->actingAs($this->charger(2))->get('/ai-recognition?date_from=')
            ->assertInertia(fn (AssertableInertia $page) => $page->has('sessionlessHolds', 0)); // another operator's freezer

        $this->actingAs($this->charger())->post($this->chargeUrl(), ['amount_cents' => 500])->assertSessionHasNoErrors();
        $this->assertSame([['capture', '50001-SF9', 500]], $gateway->callsOf('capture'));
        Carbon::setTestNow();
    }

    private function charger(int $operatorId = 1): User
    {
        Permission::findOrCreate('charge ai-recognition', 'web');
        $user = $this->viewer($operatorId);
        $user->givePermissionTo('charge ai-recognition');

        return $user;
    }

    public function test_a_hold_the_ai_could_not_decide_offers_key_in_charge(): void
    {
        $this->holdTheAiCouldNotDecide();

        $this->actingAs($this->charger())->get('/ai-recognition?date_from=')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('canCharge', true)
                ->where('recognitions.data.0.card.state', CardPaymentIntent::STATE_AWAITING_AI)
                ->where('recognitions.data.0.card.awaits_person', true));
        $this->actingAs($this->viewer())->get('/ai-recognition?date_from=')
            ->assertInertia(fn (AssertableInertia $page) => $page->where('canCharge', false));
    }

    public function test_a_person_charges_the_keyed_in_amount_on_the_t05(): void
    {
        [$gateway, $recognition] = $this->holdTheAiCouldNotDecide();

        $this->actingAs($this->viewer())->post($this->chargeUrl(), ['amount_cents' => 460])->assertForbidden();
        $this->actingAs($this->charger())->post($this->chargeUrl(), ['amount_cents' => -1])->assertSessionHasErrors('amount_cents');
        $this->assertSame([], $gateway->callsOf('capture'));

        $this->actingAs($this->charger())->post($this->chargeUrl(), ['amount_cents' => 460])
            ->assertSessionHasNoErrors()->assertSessionHas('success');

        $intent = CardPaymentIntent::sole();
        $this->assertSame(CardPaymentIntent::STATE_CAPTURED, $intent->state);
        $this->assertSame(460, $intent->captured_cents);
        $this->assertSame([['capture', '50001-SF1', 460]], $gateway->callsOf('capture'));

        // A second press finds nothing waiting.
        $this->actingAs($this->charger())->post($this->chargeUrl(), ['amount_cents' => 460])
            ->assertSessionHasErrors('amount_cents');
        $this->assertCount(1, $gateway->callsOf('capture'));
    }

    public function test_a_refused_keyed_in_charge_is_kept_and_retried_not_offered_again(): void
    {
        [$gateway, $recognition] = $this->holdTheAiCouldNotDecide();
        $gateway->refuseAbove = 0;

        $this->actingAs($this->charger())->post($this->chargeUrl(), ['amount_cents' => 460])
            ->assertSessionHasErrors(['amount_cents' => 'Payrallel refused the charge of 4.60 (ai capture: Payrallel capture refused (HTTP 422): increment declined). mark1 keeps the amount and retries every 10 minutes.']);

        $intent = CardPaymentIntent::sole();
        $this->assertSame(CardPaymentIntent::STATE_AWAITING_AI, $intent->state);
        $this->assertSame(460, $intent->ai_decision['capture_cents']);
        $this->actingAs($this->charger())->get('/ai-recognition?date_from=')
            ->assertInertia(fn (AssertableInertia $page) => $page->where('recognitions.data.0.card.awaits_person', false));
    }

    public function test_another_operators_charger_cannot_reach_the_hold(): void
    {
        [$gateway, $recognition] = $this->holdTheAiCouldNotDecide();

        $this->actingAs($this->charger(2))->post($this->chargeUrl(), ['amount_cents' => 460])->assertNotFound();
        $this->assertSame([], $gateway->callsOf('capture'));
    }

    public function test_an_operator_viewer_sees_only_its_own_freezers_and_no_unmatched_sessions(): void
    {
        $theirs = Vend::create(['code' => 2013, 'machine_type' => Vend::MACHINE_TYPE_SMART_FREEZER, 'is_active' => 1, 'operator_id' => 7]);
        $this->recognition();                                // operator 1's freezer
        $this->recognition(['vend_id' => $theirs->id]);      // operator 7's freezer
        $this->recognition(['vend_id' => null, 'device_id' => 'JS093519']); // supplier test cabinet

        $total = fn (User $u) => $this->actingAs($u)->get('/ai-recognition?date_from=')->viewData('page')['props']['recognitions']['meta']['total'];

        $this->assertSame(3, $total($this->viewer(1)));
        $this->assertSame(1, $total($this->viewer(7)));
    }
}
