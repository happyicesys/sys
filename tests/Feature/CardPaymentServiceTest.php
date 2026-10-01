<?php

namespace Tests\Feature;

use App\Models\CardPaymentIntent;
use App\Models\RemoteCardTerminal;
use App\Models\Vend;
use App\Services\CardTerminal\CardPaymentService;
use App\Services\CardTerminal\CardTerminalException;
use App\Services\CardTerminal\Payrallel\PayrallelGateway;
use App\Services\CardTerminal\TerminalTransaction as T;
use Carbon\Carbon;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\FakeRemoteCardTerminalGateway;
use Tests\TestCase;

/**
 * The remote-terminal card lifecycle (smart-freezer Payrallel rail): every
 * path that moves money, and above all the ones that must give it back.
 */
class CardPaymentServiceTest extends TestCase
{
    use RefreshDatabase;

    private FakeRemoteCardTerminalGateway $gateway;

    private CardPaymentService $service;

    private Vend $vend;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-09-30 12:00:00');
        config(['payrallel.min_query_interval_ms' => 0, 'payrallel.mode' => 'sale']);

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

    private function start(string $ref = 'SF1', int $cents = 430): CardPaymentIntent
    {
        return $this->service->authorize($this->vend, $ref, $cents);
    }

    private function order(string $ref = 'SF1'): string
    {
        return '50001-'.$ref;
    }

    public function test_authorize_sends_one_sale_and_is_idempotent_per_reference(): void
    {
        $intent = $this->start();

        $this->assertSame(CardPaymentIntent::STATE_PROCESSING, $intent->state);
        $this->assertSame('50001-SF1', $intent->custom_order_id);
        $this->assertSame([['sale', '50001-SF1', 430]], $this->gateway->callsOf('sale'));

        $again = $this->start();
        $this->assertSame($intent->id, $again->id);
        $this->assertCount(1, $this->gateway->callsOf('sale'), 'a retried request must not charge twice');
    }

    public function test_a_reference_cannot_be_reused_for_another_amount(): void
    {
        $this->start('SF1', 430);
        $this->expectException(DomainException::class);
        $this->start('SF1', 999);
    }

    public function test_a_machine_without_an_active_terminal_is_refused(): void
    {
        RemoteCardTerminal::query()->update(['is_active' => false]);
        $this->expectException(CardTerminalException::class);
        $this->start();
    }

    public function test_only_an_exact_approval_approves(): void
    {
        $intent = $this->start();
        $this->gateway->answer($this->order(), T::NOT_FOUND, T::PROCESSING, T::APPROVED);

        $this->assertSame(CardPaymentIntent::STATE_PROCESSING, $this->service->refresh($intent)->state);
        $this->assertSame(CardPaymentIntent::STATE_PROCESSING, $this->service->refresh($intent)->state);
        $approved = $this->service->refresh($intent);

        $this->assertSame(CardPaymentIntent::STATE_APPROVED, $approved->state);
        $this->assertNotNull($approved->approved_at);
        $this->assertSame('visa', $approved->payment_method);
    }

    public function test_a_decline_is_final_and_moves_no_money(): void
    {
        $intent = $this->start();
        $this->gateway->answer($this->order(), T::DECLINED);

        $declined = $this->service->refresh($intent);

        $this->assertSame(CardPaymentIntent::STATE_DECLINED, $declined->state);
        $this->assertTrue($declined->isFinal());
        $this->assertSame([], $this->gateway->callsOf('void'));
    }

    public function test_queries_are_throttled_per_intent(): void
    {
        config(['payrallel.min_query_interval_ms' => 1000]);
        $intent = $this->start();

        $this->service->refresh($intent);
        $this->service->refresh($intent);
        $this->assertCount(1, $this->gateway->callsOf('query'));

        Carbon::setTestNow(Carbon::now()->addSeconds(2));
        $this->service->refresh($intent);
        $this->assertCount(2, $this->gateway->callsOf('query'));
    }

    public function test_cancel_clears_the_screen_and_a_late_approval_is_voided(): void
    {
        $intent = $this->start();

        $cancelling = $this->service->cancel($intent);
        $this->assertSame(CardPaymentIntent::STATE_CANCELLING, $cancelling->state);
        $this->assertCount(1, $this->gateway->callsOf('cancel'));

        // The tap raced the cancel: the provider reports an approval afterwards.
        $this->gateway->answer($this->order(), T::APPROVED);
        $voided = $this->service->refresh($cancelling);

        $this->assertSame(CardPaymentIntent::STATE_VOIDED, $voided->state);
        $this->assertSame([['void', '50001-SF1', null]], $this->gateway->callsOf('void'));
    }

    public function test_cancel_then_decline_ends_cancelled(): void
    {
        $intent = $this->start();
        $this->gateway->answer($this->order(), T::DECLINED);

        $this->assertSame(CardPaymentIntent::STATE_CANCELLED, $this->service->cancel($intent)->state);
    }

    public function test_cancel_after_an_unseen_approval_voids_it(): void
    {
        $intent = $this->start();
        $this->gateway->answer($this->order(), T::APPROVED);
        $approved = $this->service->refresh($intent);

        $this->assertSame(CardPaymentIntent::STATE_VOIDED, $this->service->cancel($approved)->state);
        $this->assertCount(1, $this->gateway->callsOf('void'));
    }

    public function test_an_uncertain_send_is_watched_and_a_tap_that_lands_anyway_is_voided(): void
    {
        $this->gateway->failSend = new CardTerminalException('Payrallel unreachable: timeout');
        $intent = $this->start();

        $this->assertSame(CardPaymentIntent::STATE_CANCELLING, $intent->state);
        $this->assertCount(1, $this->gateway->callsOf('cancel'));

        $this->gateway->answer($this->order(), T::APPROVED);
        $this->service->reconcile();

        $this->assertSame(CardPaymentIntent::STATE_VOIDED, $intent->fresh()->state);
    }

    public function test_an_explicit_refusal_is_an_error_with_nothing_to_watch(): void
    {
        $this->gateway->failSend = CardTerminalException::notSent('Payrallel sale refused (HTTP 400)');
        $intent = $this->start();

        $this->assertSame(CardPaymentIntent::STATE_ERROR, $intent->state);
        $this->assertSame([], $this->gateway->callsOf('cancel'));
    }

    public function test_capture_in_sale_mode_closes_the_void_window_without_a_provider_call(): void
    {
        $intent = $this->start();
        $this->gateway->answer($this->order(), T::APPROVED);
        $captured = $this->service->capture($this->service->refresh($intent), 430);

        $this->assertSame(CardPaymentIntent::STATE_CAPTURED, $captured->state);
        $this->assertSame(430, $captured->captured_cents);
        $this->assertSame([], $this->gateway->callsOf('capture'));
        $this->assertSame($captured->id, $this->service->capture($captured, 430)->id, 'capture is idempotent');

        $this->expectException(DomainException::class);
        $this->service->void($captured);
    }

    public function test_sale_mode_cannot_capture_less_than_it_charged(): void
    {
        $intent = $this->start();
        $this->gateway->answer($this->order(), T::APPROVED);
        $approved = $this->service->refresh($intent);

        $this->expectException(DomainException::class);
        $this->service->capture($approved, 200);
    }

    public function test_preauth_mode_charges_at_capture(): void
    {
        config(['payrallel.mode' => 'preauth']);
        $intent = $this->start();
        $this->assertSame([['preauth', '50001-SF1', 430]], $this->gateway->callsOf('preauth'));

        $this->gateway->answer($this->order(), T::APPROVED);
        $captured = $this->service->capture($this->service->refresh($intent), 300);

        $this->assertSame([['capture', '50001-SF1', 300]], $this->gateway->callsOf('capture'));
        $this->assertSame(300, $captured->captured_cents);
    }

    public function test_device_void_inside_the_window_returns_the_money(): void
    {
        $intent = $this->start();
        $this->gateway->answer($this->order(), T::APPROVED);
        $approved = $this->service->refresh($intent);

        $this->assertSame(CardPaymentIntent::STATE_VOIDED, $this->service->void($approved)->state);
        $this->assertSame(CardPaymentIntent::STATE_VOIDED, $this->service->void($approved->fresh())->state, 'void is idempotent');
        $this->assertCount(1, $this->gateway->callsOf('void'));
    }

    public function test_device_void_past_the_window_is_refused(): void
    {
        $intent = $this->start();
        $this->gateway->answer($this->order(), T::APPROVED);
        $approved = $this->service->refresh($intent);

        Carbon::setTestNow(Carbon::now()->addMinutes(16));
        $this->expectException(DomainException::class);
        $this->service->void($approved);
    }

    public function test_a_failed_void_is_retried_by_the_reconciler(): void
    {
        $intent = $this->start();
        $this->gateway->answer($this->order(), T::APPROVED);
        $approved = $this->service->refresh($intent);

        $this->gateway->failVoid = new CardTerminalException('Payrallel void refused (HTTP 502)');
        $this->assertSame(CardPaymentIntent::STATE_VOID_FAILED, $this->service->void($approved)->state);

        $this->gateway->failVoid = null;
        $this->service->reconcile();

        $this->assertSame(CardPaymentIntent::STATE_VOIDED, $approved->fresh()->state);
        $this->assertCount(2, $this->gateway->callsOf('void'));
    }

    public function test_a_stale_attempt_is_cancelled_on_the_terminal_and_then_given_up(): void
    {
        $intent = $this->start();

        Carbon::setTestNow(Carbon::now()->addSeconds(121));
        $this->service->reconcile();
        $this->assertSame(CardPaymentIntent::STATE_CANCELLING, $intent->fresh()->state);
        $this->assertCount(1, $this->gateway->callsOf('cancel'));

        Carbon::setTestNow(Carbon::now()->addMinutes(11));
        $this->service->reconcile();
        $this->assertSame(CardPaymentIntent::STATE_CANCELLED, $intent->fresh()->state);
        $this->assertSame([], $this->gateway->callsOf('void'));
    }

    public function test_an_unconfirmed_sale_is_closed_as_captured_after_the_void_window(): void
    {
        $intent = $this->start();
        $this->gateway->answer($this->order(), T::APPROVED);
        $this->service->refresh($intent);

        Carbon::setTestNow(Carbon::now()->addMinutes(16));
        $this->service->reconcile();

        $closed = $intent->fresh();
        $this->assertSame(CardPaymentIntent::STATE_CAPTURED, $closed->state);
        $this->assertSame([], $this->gateway->callsOf('void'), 'the goods may have gone — never void here');
    }

    public function test_terminal_status_is_cached_and_recorded(): void
    {
        $status = $this->service->terminalStatus($this->vend);
        $this->service->terminalStatus($this->vend);

        $this->assertTrue($status->isReady());
        $this->assertCount(1, $this->gateway->callsOf('status'));
        $terminal = RemoteCardTerminal::first();
        $this->assertTrue($terminal->last_online);
        $this->assertSame('ready', $terminal->last_state);
    }
}
