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

    /** Runs the minute sweep $times times, a minute apart (one AI charge per intent per sweep). */
    private function sweep(int $times = 1): void
    {
        for ($i = 0; $i < $times; $i++) {
            $this->service->reconcile();
            Carbon::setTestNow(Carbon::now()->addMinute());
        }
    }

    /** A 760c hold (2 × product 1 at 300c + 1 × product 2 at 160c), door closed. */
    private function doorClosed(?string $session = self::SESSION): CardPaymentIntent
    {
        $intent = $this->service->authorize($this->vend, 'SF1', 760);
        $this->gateway->answer('50001-SF1', T::APPROVED);

        return $this->service->capture($this->service->refresh($intent), 760, $session);
    }

    private function verdict(string $verdict, array $taken, array $sale = [], array $recognition = []): SmartFreezerRecognition
    {
        $sale = VendTransaction::forceCreate($sale + [
            'order_id' => 'O-'.uniqid(), 'vend_id' => $this->vend->id, 'vend_channel_id' => 0, 'amount' => 760,
            'transaction_datetime' => Carbon::now(), 'gst_vat_rate' => 9, 'is_multiple' => true, 'operator_id' => 1,
            'vend_transaction_json' => ['Type' => 'TRADE', 'SFREF' => self::SESSION, 'TXN_SRC' => 1, 'transf_info' => [
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

        return SmartFreezerRecognition::query()->create($recognition + [
            'vend_id' => $this->vend->id, 'trade_id' => 'SDK-'.uniqid(), 'session_ref' => self::SESSION,
            'status' => SmartFreezerRecognition::STATUS_COMPLETED, 'vend_transaction_id' => $sale->id,
            'verdict' => $verdict, 'verdict_lines' => $lines, 'completed_at' => Carbon::now(), 'callback_verified' => true,
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

    public function test_took_more_charges_the_hold_then_the_rest(): void
    {
        DB::table('vend_channels')->insert(['vend_id' => $this->vend->id, 'code' => 31, 'product_id' => 3, 'amount' => 200]);
        $this->doorClosed();
        $recognition = $this->verdict('took_more', [1 => 2, 2 => 1, 3 => 1]);
        $this->sweep();
        $this->assertSame(CardPaymentIntent::STATE_AWAITING_AI, CardPaymentIntent::sole()->state, 'one charge per sweep');
        $this->sweep();

        $intent = CardPaymentIntent::sole();
        $this->assertSame(CardPaymentIntent::STATE_CAPTURED, $intent->state);
        $this->assertSame([['capture', '50001-SF1', 760], ['capture', '50001-SF1', 200]], $this->gateway->callsOf('capture'));
        $this->assertSame(960, $intent->captured_cents);
        $this->assertNull($intent->owed_cents);
        $this->assertSame($recognition->id, $intent->ai_decision['recognition_id']);
    }

    public function test_a_refused_further_charge_resumes_and_is_given_up_after_its_attempts(): void
    {
        config(['payrallel.ai_extra_charge_attempts' => 2]);
        $this->doorClosed();
        $this->verdict('took_more', [1 => 5, 2 => 1]); // 1660c = 760 + 760 + 140
        $this->gateway->failCaptureAfter = 1;
        $this->gateway->failCapture = CardTerminalException::notSent('second capture refused');
        $this->sweep(2);

        $intent = CardPaymentIntent::sole();
        $this->assertSame(CardPaymentIntent::STATE_AWAITING_AI, $intent->state, 'charges remain');
        $this->assertSame(760, $intent->captured_cents);
        $this->assertSame(900, $intent->owed_cents);

        Carbon::setTestNow(Carbon::now()->addMinutes(11));
        $this->service->reconcile();
        $intent->refresh();
        $this->assertSame(CardPaymentIntent::STATE_CAPTURED, $intent->state, 'given up after 2 refusals');
        $this->assertSame(760, $intent->captured_cents);
        $this->assertSame(900, $intent->owed_cents);
        $this->assertStringContainsString('further charge refused', $intent->last_error);
    }

    public function test_a_refused_further_charge_that_then_succeeds_finishes_the_rest(): void
    {
        $this->doorClosed();
        $this->verdict('took_more', [1 => 5, 2 => 1]); // 760 + 760 + 140
        $this->gateway->failCaptureAfter = 1;
        $this->gateway->failCapture = CardTerminalException::notSent('busy');
        $this->sweep(2);

        $this->gateway->failCapture = null;
        Carbon::setTestNow(Carbon::now()->addMinutes(11));
        $this->sweep(2);

        $intent = CardPaymentIntent::sole();
        $this->assertSame(CardPaymentIntent::STATE_CAPTURED, $intent->state);
        $this->assertSame(1660, $intent->captured_cents);
        $this->assertSame([760, 760, 140], array_map(fn ($c) => $c[2], array_slice($this->gateway->callsOf('capture'), -3)));
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
        $this->gateway->failCapture = CardTerminalException::notSent('provider refused');
        $this->service->reconcile();

        $intent = CardPaymentIntent::sole();
        $this->assertSame(CardPaymentIntent::STATE_AWAITING_AI, $intent->state);
        $this->assertSame([300], $intent->ai_decision['charges']);

        // The recognition changing now does not change the decision: the first verdict is final.
        SmartFreezerRecognition::query()->update(['verdict' => 'match']);
        $this->gateway->failCapture = null;
        $this->service->reconcile();
        $this->assertSame(CardPaymentIntent::STATE_AWAITING_AI, $intent->fresh()->state, 'retry waits its window');

        Carbon::setTestNow(Carbon::now()->addMinutes(11));
        $this->service->reconcile();
        $this->assertSame(300, $intent->fresh()->captured_cents);
    }

    public function test_a_charge_that_may_have_gone_through_is_never_resent_until_a_person_says(): void
    {
        $this->doorClosed();
        $this->verdict('took_more', [1 => 5, 2 => 1]); // 760 + 760 + 140
        $this->gateway->failCaptureAfter = 1;
        $this->gateway->failCapture = new CardTerminalException('timeout'); // may have reached Payrallel
        $this->sweep(2);

        $intent = CardPaymentIntent::sole();
        $this->assertSame(760, $intent->ai_decision['uncertain']);
        $this->assertStringContainsString('not resent', $intent->last_error);

        $this->gateway->failCapture = null;
        Carbon::setTestNow(Carbon::now()->addHours(2));
        $this->service->reconcile();
        $this->assertCount(2, $this->gateway->callsOf('capture'), 'flagged: nothing more is sent');

        $this->artisan('card-payments:resolve-ai-charge', ['reference' => 'SF1', '--charged' => true])->assertSuccessful();
        $this->sweep();
        $intent->refresh();
        $this->assertSame(CardPaymentIntent::STATE_CAPTURED, $intent->state);
        $this->assertSame(1660, $intent->captured_cents);
        $this->assertSame([760, 140], array_map(fn ($c) => $c[2], array_slice($this->gateway->callsOf('capture'), -2)), 'only the 140 was sent after');
    }

    public function test_a_flagged_charge_the_person_says_did_not_go_through_is_sent_again(): void
    {
        $this->doorClosed();
        $this->verdict('match', [1 => 2, 2 => 1]);
        $this->gateway->failCapture = new CardTerminalException('HTTP 502');
        $this->service->reconcile();
        $this->assertSame(760, CardPaymentIntent::sole()->ai_decision['uncertain']);

        $this->gateway->failCapture = null;
        $this->artisan('card-payments:resolve-ai-charge', ['reference' => 'SF1', '--not-charged' => true])->assertSuccessful();

        $this->assertSame(CardPaymentIntent::STATE_CAPTURED, CardPaymentIntent::sole()->state);
        $this->assertCount(2, $this->gateway->callsOf('capture'));
    }

    public function test_a_run_that_died_mid_charge_is_flagged_not_resent(): void
    {
        $intent = $this->doorClosed();
        $intent->update(['ai_decision' => ['cart_cents' => 760, 'action' => 'capture', 'charges' => [760], 'paid' => [], 'inflight' => 760]]);

        $this->service->reconcile();

        $this->assertSame(760, $intent->fresh()->ai_decision['uncertain']);
        $this->assertSame([], $this->gateway->callsOf('capture'));
    }

    /** @return array<string, array{0: array, 1: array, 2: int, 3: string}> */
    public static function untrustedResults(): array
    {
        $qr = ['vend_transaction_json' => ['Type' => 'TRADE', 'SFREF' => self::SESSION, 'TXN_SRC' => 0, 'transf_info' => [['goods_id' => 1, 'Price' => 300]]]];

        return [
            'unverified callback' => [[], ['callback_verified' => false], 1, 'not signature-verified'],
            'a QR sale' => [$qr, [], 1, 'not paid by card'],
            'another amount' => [['amount' => 900], [], 1, 'is not this hold'],
            'two results' => [[], [], 2, 'several AI results'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('untrustedResults')]
    public function test_a_result_that_is_not_clearly_about_this_card_sale_charges_the_cart(array $sale, array $recognition, int $copies, string $reason): void
    {
        $this->doorClosed();
        for ($i = 0; $i < $copies; $i++) {
            $this->verdict('took_less', [], $sale, $recognition); // "nothing taken" would release the hold
        }
        $this->service->reconcile();

        $intent = CardPaymentIntent::sole();
        $this->assertSame(760, $intent->captured_cents);
        $this->assertStringContainsString($reason, $intent->ai_decision['reason']);
    }

    public function test_a_zero_priced_line_never_releases_a_hold_on_goods_taken(): void
    {
        $this->doorClosed();
        $this->verdict('took_less', [1 => 1], ['vend_transaction_json' => ['Type' => 'TRADE', 'SFREF' => self::SESSION, 'TXN_SRC' => 1,
            'transf_info' => [['goods_id' => 1, 'Price' => 0], ['goods_id' => 1, 'Price' => 0], ['goods_id' => 2, 'Price' => 160]]]]);
        $this->service->reconcile();

        $intent = CardPaymentIntent::sole();
        $this->assertSame(CardPaymentIntent::STATE_CAPTURED, $intent->state, 'no shelf price either: the cart');
        $this->assertSame(760, $intent->captured_cents);
    }

    public function test_a_hold_is_charged_on_the_token_it_was_made_with_after_a_t05_swap(): void
    {
        $this->doorClosed();
        RemoteCardTerminal::sole()->update(['access_token' => 'tok-NEW-T05']);
        $this->verdict('match', [1 => 2, 2 => 1]);
        $this->service->reconcile();

        $this->assertSame(['tok-50001'], $this->gateway->tokens);
        $this->assertSame('tok-NEW-T05', RemoteCardTerminal::sole()->access_token, 'the swap itself is untouched');
    }

    public function test_one_broken_intent_never_stops_the_sweep_for_the_others(): void
    {
        $broken = $this->doorClosed();
        $broken->forceFill(['ai_decision' => ['cart_cents' => 760, 'action' => 'capture', 'charges' => [760], 'paid' => []]])->save();
        $this->gateway->explodeFor = ['50001-SF1'];

        $other = $this->service->authorize($this->vend, 'SF2', 760);
        $this->gateway->answer('50001-SF2', T::APPROVED);
        $other = $this->service->capture($this->service->refresh($other), 760, 'SF-50001-1791100000-9');
        SmartFreezerRecognition::query()->create([
            'vend_id' => $this->vend->id, 'trade_id' => 'SDK-other', 'session_ref' => 'SF-50001-1791100000-9',
            'status' => SmartFreezerRecognition::STATUS_COMPLETED, 'verdict' => 'incomplete', 'verdict_lines' => [],
            'callback_verified' => true, 'vend_transaction_id' => $this->verdict('match', [])->vend_transaction_id,
        ]);

        $this->service->reconcile();

        $this->assertSame(CardPaymentIntent::STATE_AWAITING_AI, $broken->fresh()->state);
        $this->assertSame(CardPaymentIntent::STATE_CAPTURED, $other->fresh()->state);
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
