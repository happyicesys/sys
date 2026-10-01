<?php

namespace Tests\Feature;

use App\Http\Middleware\VerifyDeviceSignature;
use App\Models\CardPaymentIntent;
use App\Models\RemoteCardTerminal;
use App\Models\Vend;
use App\Services\CardTerminal\Payrallel\PayrallelGateway;
use App\Services\CardTerminal\TerminalTransaction as T;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\Support\FakeRemoteCardTerminalGateway;
use Tests\TestCase;

/**
 * The device-facing card API the freezer's PayrallelCardRail calls: machine
 * signatures, the happy path, door-failure void, and error translation.
 */
class FreezerCardApiTest extends TestCase
{
    use RefreshDatabase;

    private const KEY = 'TESTKEY000000001';

    private FakeRemoteCardTerminalGateway $gateway;

    protected function setUp(): void
    {
        parent::setUp();
        config(['payrallel.min_query_interval_ms' => 0, 'payrallel.mode' => 'sale']);
        $this->gateway = new FakeRemoteCardTerminalGateway;
        $this->app->instance(PayrallelGateway::class, $this->gateway);

        $vend = new Vend;
        $vend->forceFill([
            'code' => 50001, 'machine_type' => Vend::MACHINE_TYPE_SMART_FREEZER,
            'is_active' => 1, 'operator_id' => 1, 'vend_model_id' => 1, 'private_key' => self::KEY,
        ])->save();
        RemoteCardTerminal::create([
            'vend_id' => $vend->id, 'provider' => 'payrallel', 'access_token' => 'tok', 'is_active' => true,
        ]);
    }

    private function signed(string $method, string $path, array $body = [], ?string $key = self::KEY, ?int $ts = null): TestResponse
    {
        $raw = $body === [] ? '' : json_encode($body);
        $ts = (string) ($ts ?? time());
        $headers = [
            'X-Device-Timestamp' => $ts,
            'X-Device-Signature' => VerifyDeviceSignature::sign($key, $method, $path, $ts, $raw),
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ];

        return $this->call($method, $path, [], [], [], $this->transformHeadersToServerVars($headers), $raw);
    }

    public function test_unsigned_wrongly_signed_or_stale_requests_are_refused(): void
    {
        $this->getJson('/api/v1/vends/50001/card/terminal')->assertStatus(401);
        $this->signed('GET', '/api/v1/vends/50001/card/terminal', [], 'WRONGKEY')->assertStatus(401);
        $this->signed('GET', '/api/v1/vends/50001/card/terminal', [], self::KEY, time() - 600)->assertStatus(401);
        $this->signed('GET', '/api/v1/vends/99999/card/terminal')->assertStatus(404);
        $this->assertSame([], $this->gateway->calls);
    }

    public function test_a_body_changed_after_signing_is_refused(): void
    {
        $ts = (string) time();
        $sig = VerifyDeviceSignature::sign(self::KEY, 'POST', '/api/v1/vends/50001/card/authorize', $ts,
            json_encode(['reference' => 'SF1', 'amount_cents' => 430]));
        $this->call('POST', '/api/v1/vends/50001/card/authorize', [], [], [], $this->transformHeadersToServerVars([
            'X-Device-Timestamp' => $ts, 'X-Device-Signature' => $sig, 'Content-Type' => 'application/json', 'Accept' => 'application/json',
        ]), json_encode(['reference' => 'SF1', 'amount_cents' => 1]))->assertStatus(401);
    }

    public function test_terminal_readiness(): void
    {
        $this->signed('GET', '/api/v1/vends/50001/card/terminal')
            ->assertOk()->assertJson(['configured' => true, 'online' => true, 'state' => 'ready', 'ready' => true]);
    }

    public function test_full_purchase_authorize_poll_capture(): void
    {
        $this->signed('POST', '/api/v1/vends/50001/card/authorize', ['reference' => 'SF1', 'amount_cents' => 430])
            ->assertStatus(202)->assertJson(['reference' => 'SF1', 'state' => 'processing', 'final' => false]);

        $this->signed('GET', '/api/v1/vends/50001/card/SF1')->assertOk()->assertJson(['state' => 'processing']);

        $this->gateway->answer('50001-SF1', T::APPROVED);
        $this->signed('GET', '/api/v1/vends/50001/card/SF1')
            ->assertOk()->assertJson(['state' => 'approved', 'payment_method' => 'visa']);

        $this->signed('POST', '/api/v1/vends/50001/card/SF1/capture', ['amount_cents' => 430])
            ->assertOk()->assertJson(['state' => 'captured', 'final' => true, 'captured_cents' => 430]);

        // The door already opened: a void now would refund released goods.
        $this->signed('POST', '/api/v1/vends/50001/card/SF1/void')->assertStatus(409);
    }

    public function test_door_failure_voids(): void
    {
        $this->signed('POST', '/api/v1/vends/50001/card/authorize', ['reference' => 'SF2', 'amount_cents' => 500]);
        $this->gateway->answer('50001-SF2', T::APPROVED);
        $this->signed('GET', '/api/v1/vends/50001/card/SF2');

        $this->signed('POST', '/api/v1/vends/50001/card/SF2/void')->assertOk()->assertJson(['state' => 'voided']);
        $this->assertCount(1, $this->gateway->callsOf('void'));
    }

    public function test_cancel_and_unknown_reference(): void
    {
        $this->signed('POST', '/api/v1/vends/50001/card/authorize', ['reference' => 'SF3', 'amount_cents' => 500]);
        $this->signed('POST', '/api/v1/vends/50001/card/SF3/cancel')->assertOk()->assertJson(['state' => 'cancelling']);
        $this->signed('GET', '/api/v1/vends/50001/card/NOPE')->assertStatus(404);
    }

    public function test_validation_and_unconfigured_machine(): void
    {
        $this->signed('POST', '/api/v1/vends/50001/card/authorize', ['reference' => 'bad ref!', 'amount_cents' => 0])
            ->assertStatus(422);

        RemoteCardTerminal::query()->update(['is_active' => false]);
        $this->signed('POST', '/api/v1/vends/50001/card/authorize', ['reference' => 'SF4', 'amount_cents' => 500])
            ->assertStatus(503);
        $this->signed('GET', '/api/v1/vends/50001/card/terminal')->assertOk()->assertJson(['configured' => false, 'ready' => false]);
        $this->assertSame(0, CardPaymentIntent::where('reference', 'SF4')->count());
    }
}
