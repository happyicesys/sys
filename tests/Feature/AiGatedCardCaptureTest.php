<?php

namespace Tests\Feature;

use App\Models\CardPaymentIntent;
use App\Models\RemoteCardTerminal;
use App\Models\SmartFreezerRecognition;
use App\Models\Vend;
use App\Models\VendTransaction;
use App\Services\CardTerminal\CardPaymentService;
use App\Services\CardTerminal\CardTerminalException;
use App\Services\CardTerminal\Payrallel\PayrallelGateway;
use App\Services\CardTerminal\TerminalTransaction as T;
use Carbon\Carbon;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\FakeRemoteCardTerminalGateway;
use Tests\TestCase;

/**
 * A T05 hold whose door closed with a session ref (freezer app 26+) is charged by the AI
 * verdict, never above the hold (Brian, 2026-10-03). NETS and QR never come here.
 */
class AiGatedCardCaptureTest extends TestCase
{
    use RefreshDatabase;

    private const SESSION = 'SF-50001-1791100000-3';

    private FakeRemoteCardTerminalGateway $gateway;

    private CardPaymentService $service;

    private Vend $vend;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-04 12:00:00');
        config(['payrallel.min_query_interval_ms' => 0, 'payrallel.mode' => 'preauth', 'payrallel.ai_capture' => true]);

        $this->gateway = new FakeRemoteCardTerminalGateway;
        $this->app->instance(PayrallelGateway::class, $this->gateway);
        $this->service = $this->app->make(CardPaymentService::class);

        $vend = new Vend;
        $vend->forceFill([
            'code' => 50001, 'machine_type' => Vend::MACHINE_TYPE_SMART_FREEZER,
            'is_active' => 1, 'operator_id' => 1, 'vend_model_id' => 1,
        ])->save();
        $this->vend = $vend->refresh();
        RemoteCardTerminal::create([
            'vend_id' => $this->vend->id, 'provider' => RemoteCardTerminal::PROVIDER_PAYRALLEL,
            'access_token' => 'tok-50001', 'is_active' => true,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /** A 760c hold (2 × product 1 at 300c + 1 × product 2 at 160c), door closed. */
    private function doorClosed(?string $session = self::SESSION): CardPaymentIntent
    {
        $intent = $this->service->authorize($this->vend, 'SF1', 760);
        $this->gateway->answer('50001-SF1', T::APPROVED);

        return $this->service->capture($this->service->refresh($intent), 760, $session);
    }

    private function verdict(string $verdict, array $taken): SmartFreezerRecognition
    {
        $sale = VendTransaction::forceCreate([
            'order_id' => 'O-1', 'vend_id' => $this->vend->id, 'vend_channel_id' => 0, 'amount' => 760,
            'transaction_datetime' => Carbon::now(), 'gst_vat_rate' => 9, 'is_multiple' => true, 'operator_id' => 1,
            'vend_transaction_json' => ['Type' => 'TRADE', 'SFREF' => self::SESSION, 'transf_info' => [
                ['SId' => 11, 'goods_id' => 1, 'Price' => 300],
                ['SId' => 11, 'goods_id' => 1, 'Price' => 300],
                ['SId' => 12, 'goods_id' => 2, 'Price' => 160],
            ]],
        ]);
        $paid = [1 => 2, 2 => 1];
        $lines = [];
        foreach ($paid + $taken as $productId => $_) {
            $lines[] = ['product_id' => $productId, 'code' => null, 'paid' => $paid[$productId] ?? 0,
                'taken' => $taken[$productId] ?? 0, 'delta' => ($taken[$productId] ?? 0) - ($paid[$productId] ?? 0)];
        }

        return SmartFreezerRecognition::query()->create([
            'vend_id' => $this->vend->id, 'trade_id' => 'SDK-'.uniqid(), 'session_ref' => self::SESSION,
            'status' => SmartFreezerRecognition::STATUS_COMPLETED, 'vend_transaction_id' => $sale->id,
            'verdict' => $verdict, 'verdict_lines' => $lines, 'completed_at' => Carbon::now(),
        ]);
    }

    public function test_door_close_with_a_session_ref_charges_nothing_and_waits_for_the_ai(): void
    {
        $intent = $this->doorClosed();

        $this->assertSame(CardPaymentIntent::STATE_AWAITING_AI, $intent->state);
        $this->assertSame(self::SESSION, $intent->session_ref);
        $this->assertSame([], $this->gateway->callsOf('capture'));

        $this->service->reconcile();
        $this->assertSame(CardPaymentIntent::STATE_AWAITING_AI, $intent->fresh()->state, 'no verdict, no charge');
        $this->assertSame(CardPaymentIntent::STATE_AWAITING_AI, $this->service->capture($intent->fresh(), 760, self::SESSION)->state, 'a retried door-close is a no-op');
    }

    public function test_an_old_app_without_a_session_ref_still_charges_at_once(): void
    {
        $intent = $this->doorClosed(null);

        $this->assertSame(CardPaymentIntent::STATE_CAPTURED, $intent->state);
        $this->assertSame([['capture', '50001-SF1', 760]], $this->gateway->callsOf('capture'));
    }

    public function test_match_charges_the_full_hold(): void
    {
        $this->doorClosed();
        $this->verdict('match', [1 => 2, 2 => 1]);
        $this->service->reconcile();

        $intent = CardPaymentIntent::sole();
        $this->assertSame(CardPaymentIntent::STATE_CAPTURED, $intent->state);
        $this->assertSame(760, $intent->captured_cents);
        $this->assertSame([['capture', '50001-SF1', 760]], $this->gateway->callsOf('capture'));
    }

    public function test_took_less_charges_less(): void
    {
        $this->doorClosed();
        $this->verdict('took_less', [1 => 1, 2 => 1]);
        $this->service->reconcile();

        $this->assertSame(460, CardPaymentIntent::sole()->captured_cents);
        $this->assertSame([['capture', '50001-SF1', 460]], $this->gateway->callsOf('capture'));
    }

    public function test_nothing_taken_releases_the_hold(): void
    {
        $this->doorClosed();
        $this->verdict('took_less', []);
        $this->service->reconcile();

        $this->assertSame(CardPaymentIntent::STATE_VOIDED, CardPaymentIntent::sole()->state);
        $this->assertSame([], $this->gateway->callsOf('capture'));
        $this->assertCount(1, $this->gateway->callsOf('void'));
    }

    public function test_took_more_charges_the_hold_and_records_what_is_owed(): void
    {
        DB::table('vend_channels')->insert(['vend_id' => $this->vend->id, 'code' => 31, 'product_id' => 3, 'amount' => 200]);
        $this->doorClosed();
        $recognition = $this->verdict('took_more', [1 => 2, 2 => 1, 3 => 1]);
        $this->service->reconcile();

        $intent = CardPaymentIntent::sole();
        $this->assertSame(760, $intent->captured_cents);
        $this->assertSame(200, $intent->owed_cents);
        $this->assertSame($recognition->id, $intent->ai_decision['recognition_id']);
        $this->assertSame(960, $intent->ai_decision['judged_cents']);
    }

    public function test_cannot_identify_charges_the_cart_total(): void
    {
        $this->doorClosed();
        $this->verdict('incomplete', []);
        $this->service->reconcile();

        $this->assertSame(760, CardPaymentIntent::sole()->captured_cents);
    }

    public function test_no_verdict_is_charged_in_full_at_the_backstop_only(): void
    {
        config(['payrallel.ai_capture_backstop_hours' => 72]);
        $this->doorClosed();

        Carbon::setTestNow(Carbon::now()->addHours(71));
        $this->service->reconcile();
        $this->assertSame(CardPaymentIntent::STATE_AWAITING_AI, CardPaymentIntent::sole()->state);

        Carbon::setTestNow(Carbon::now()->addHours(2));
        $this->service->reconcile();
        $intent = CardPaymentIntent::sole();
        $this->assertSame(CardPaymentIntent::STATE_CAPTURED, $intent->state);
        $this->assertSame(760, $intent->captured_cents);
        $this->assertStringContainsString('no AI verdict', $intent->ai_decision['reason']);
    }

    public function test_a_refused_charge_keeps_its_decision_and_retries_later(): void
    {
        $this->doorClosed();
        $this->verdict('took_less', [1 => 1]);
        $this->gateway->failCapture = new CardTerminalException('provider down');
        $this->service->reconcile();

        $intent = CardPaymentIntent::sole();
        $this->assertSame(CardPaymentIntent::STATE_AWAITING_AI, $intent->state);
        $this->assertSame(300, $intent->ai_decision['capture_cents']);

        // The recognition changing now does not change the decision: the first verdict is final.
        SmartFreezerRecognition::query()->update(['verdict' => 'match']);
        $this->gateway->failCapture = null;
        $this->service->reconcile();
        $this->assertSame(CardPaymentIntent::STATE_AWAITING_AI, $intent->fresh()->state, 'retry waits its window');

        Carbon::setTestNow(Carbon::now()->addMinutes(11));
        $this->service->reconcile();
        $this->assertSame(300, $intent->fresh()->captured_cents);
    }

    public function test_the_device_cannot_void_once_the_door_closed(): void
    {
        $intent = $this->doorClosed();

        $this->expectException(DomainException::class);
        $this->service->void($intent);
    }

    public function test_switched_off_it_charges_at_door_close_as_before(): void
    {
        config(['payrallel.ai_capture' => false]);

        $this->assertSame(CardPaymentIntent::STATE_CAPTURED, $this->doorClosed()->state);
    }
}
