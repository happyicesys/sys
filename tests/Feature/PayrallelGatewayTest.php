<?php

namespace Tests\Feature;

use App\Models\CardPaymentEvent;
use App\Models\RemoteCardTerminal;
use App\Services\CardTerminal\CardTerminalException;
use App\Services\CardTerminal\Payrallel\PayrallelGateway;
use App\Services\CardTerminal\TerminalTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * The Payrallel wire format, pinned to their public Remote Terminal guide:
 * paths, integer cents, per-terminal auth, and a defensive reading of answers.
 */
class PayrallelGatewayTest extends TestCase
{
    use RefreshDatabase;

    private PayrallelGateway $gateway;

    private RemoteCardTerminal $terminal;

    protected function setUp(): void
    {
        parent::setUp();
        config(['payrallel.base_url' => 'https://pay.example/api', 'payrallel.authorization_format' => 'Bearer {token}']);
        $this->gateway = $this->app->make(PayrallelGateway::class);
        $this->terminal = new RemoteCardTerminal(['provider' => 'payrallel', 'access_token' => 'tok-1']);
        $this->terminal->id = 7;
    }

    public function test_sale_posts_cents_and_order_id_with_the_terminal_token(): void
    {
        Http::fake(['pay.example/*' => Http::response(['success' => true])]);

        $this->gateway->requestSale($this->terminal, '50001-SF1', 430);

        Http::assertSent(function (Request $r) {
            return $r->url() === 'https://pay.example/api/terminal/payment-request/sale'
                && $r->method() === 'POST'
                && $r->header('Authorization')[0] === 'Bearer tok-1'
                && $r->data() === ['amountInCents' => 430, 'customOrderId' => '50001-SF1'];
        });
    }

    public function test_every_call_is_on_the_timeline_with_its_raw_answer(): void
    {
        Http::fake(['pay.example/*' => Http::response(['success' => false, 'message' => 'Terminal offline'], 503)]);

        try {
            $this->gateway->requestSale($this->terminal, '50001-SF1', 430);
        } catch (CardTerminalException) {
        }

        $e = CardPaymentEvent::sole();
        $this->assertSame('provider.http', $e->event);
        $this->assertSame('warning', $e->level);
        $this->assertSame('50001-SF1', $e->custom_order_id);
        $this->assertSame('terminal/payment-request/sale', $e->detail['path']);
        $this->assertSame(503, $e->detail['http_status']);
        $this->assertStringContainsString('Terminal offline', $e->detail['response']);
        $this->assertSame(['amountInCents' => 430, 'customOrderId' => '50001-SF1'], $e->detail['request']);
        $this->assertNotNull($e->duration_ms);
        $this->assertStringNotContainsString('tok-1', json_encode($e->detail), 'the token never reaches the timeline');
    }

    public function test_exactly_one_content_type_and_the_raw_token_format(): void
    {
        config(['payrallel.authorization_format' => '{token}']);
        Http::fake(['pay.example/*' => Http::response(['success' => true, 'status' => 'online', 'state' => 'ready'])]);

        $this->gateway->status($this->terminal);

        Http::assertSent(function (Request $r) {
            return $r->header('Content-Type') === ['application/json; charset=UTF-8']
                && $r->header('Authorization') === ['tok-1'];
        });
    }

    public function test_cancel_names_the_attempt_when_known(): void
    {
        Http::fake(['pay.example/*' => Http::response(['success' => true])]);

        $this->gateway->cancelActiveRequest($this->terminal, '50001-SF1', 430);
        $this->gateway->cancelActiveRequest($this->terminal);

        $bodies = Http::recorded()->map(fn ($pair) => $pair[0]->body())->all();
        $this->assertSame(['{"amountInCents":430,"customOrderId":"50001-SF1"}', '{}'], $bodies);
    }

    public function test_every_endpoint_path(): void
    {
        Http::fake(['pay.example/*' => Http::response(['success' => true, 'status' => 'online', 'state' => 'ready'])]);

        $this->gateway->requestPreauth($this->terminal, 'o', 100);
        $this->gateway->capture($this->terminal, 'o', 100);
        $this->gateway->cancelActiveRequest($this->terminal);
        $this->gateway->void($this->terminal, 'o');
        $status = $this->gateway->status($this->terminal);

        $paths = Http::recorded()->map(fn ($pair) => parse_url($pair[0]->url(), PHP_URL_PATH))->all();
        $this->assertSame([
            '/api/terminal/payment-request/preauth',
            '/api/transactions/card/capture',
            '/api/terminal/payment-request/cancel',
            '/api/transactions/actions/void',
            '/api/terminal/status',
        ], $paths);
        $this->assertTrue($status->isReady());
    }

    public function test_query_maps_statuses_and_only_exact_approved_approves(): void
    {
        Http::fakeSequence('pay.example/*')
            ->push(['success' => true, 'transaction' => ['transactionId' => 'T1', 'status' => 'approved', 'paymentMethod' => 'visa']])
            ->push(['success' => true, 'transaction' => ['status' => 'APPROVED_PENDING']])
            ->push(['success' => true, 'transaction' => ['status' => 'declined']])
            ->push(['success' => false, 'message' => 'Transaction Not Found'], 200)
            ->push([], 404);

        $approved = $this->gateway->query($this->terminal, 'o');
        $this->assertSame(TerminalTransaction::APPROVED, $approved->status);
        $this->assertSame('T1', $approved->providerTxnId);
        $this->assertSame('visa', $approved->paymentMethod);

        $this->assertSame(TerminalTransaction::PROCESSING, $this->gateway->query($this->terminal, 'o')->status);
        $this->assertSame(TerminalTransaction::DECLINED, $this->gateway->query($this->terminal, 'o')->status);
        $this->assertSame(TerminalTransaction::NOT_FOUND, $this->gateway->query($this->terminal, 'o')->status);
        $this->assertSame(TerminalTransaction::NOT_FOUND, $this->gateway->query($this->terminal, 'o')->status);
    }

    public function test_a_4xx_is_a_refusal_that_never_reached_the_terminal(): void
    {
        Http::fake(['pay.example/*' => Http::response(['success' => false, 'message' => 'invalid amount'], 422)]);

        try {
            $this->gateway->requestSale($this->terminal, 'o', 430);
            $this->fail('expected a refusal');
        } catch (CardTerminalException $e) {
            $this->assertFalse($e->mayHaveReachedTerminal);
            $this->assertStringContainsString('invalid amount', $e->getMessage());
        }
    }

    public function test_a_5xx_or_success_false_2xx_may_have_reached_the_terminal(): void
    {
        Http::fakeSequence('pay.example/*')
            ->push(['success' => false], 502)
            ->push(['success' => false, 'message' => 'busy'], 200);

        foreach ([1, 2] as $_) {
            try {
                $this->gateway->requestSale($this->terminal, 'o', 430);
                $this->fail('expected an exception');
            } catch (CardTerminalException $e) {
                $this->assertTrue($e->mayHaveReachedTerminal);
            }
        }
    }

    public function test_unconfigured_base_url_never_calls_out_and_never_leaks_the_token(): void
    {
        config(['payrallel.base_url' => null]);
        Http::fake();

        try {
            $this->gateway->requestSale($this->terminal, 'o', 430);
            $this->fail('expected not-configured');
        } catch (CardTerminalException $e) {
            $this->assertFalse($e->mayHaveReachedTerminal);
            $this->assertStringNotContainsString('tok-1', $e->getMessage());
        }
        Http::assertNothingSent();
    }

    public function test_non_positive_amounts_are_refused_before_sending(): void
    {
        Http::fake();
        $this->expectException(CardTerminalException::class);
        try {
            $this->gateway->requestSale($this->terminal, 'o', 0);
        } finally {
            Http::assertNothingSent();
        }
    }
}
