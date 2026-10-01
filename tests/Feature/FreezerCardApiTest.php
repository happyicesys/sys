<?php

namespace Tests\Feature;

use App\Http\Middleware\VerifyDeviceSignature;
use App\Models\CardPaymentEvent;
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

    public function test_the_trial_timeline_records_device_actions_state_changes_and_rejections(): void
    {
        $this->signed('GET', '/api/v1/vends/50001/card/terminal');
        $this->signed('POST', '/api/v1/vends/50001/card/authorize', ['reference' => 'SF9', 'amount_cents' => 430]);
        $this->gateway->answer('50001-SF9', T::APPROVED);
        $this->signed('GET', '/api/v1/vends/50001/card/SF9');
        $this->signed('POST', '/api/v1/vends/50001/card/SF9/capture', ['amount_cents' => 430]);
        $this->signed('GET', '/api/v1/vends/50001/card/terminal', [], 'WRONGKEY');

        $events = CardPaymentEvent::orderBy('id')->get();
        $this->assertSame([
            'terminal.status',
            'intent.created',
            'intent.state',      // pending → processing
            'device.request',    // authorize
            'intent.state',      // processing → approved
            'intent.state',      // approved → captured
            'device.request',    // capture
            'device.rejected',
        ], $events->pluck('event')->all());

        $states = $events->where('event', 'intent.state')->map(fn ($e) => $e->detail['from'].'>'.$e->detail['to'])->values()->all();
        $this->assertSame(['pending>processing', 'processing>approved', 'approved>captured'], $states);
        $this->assertSame('authorize', $events[3]->detail['action']);
        $this->assertSame(202, $events[3]->detail['http_status']);
        $this->assertNotNull($events[3]->duration_ms);
        $this->assertSame('bad signature', $events->last()->detail['why']);
        $this->assertTrue($events->every(fn ($e) => $e->vend_id !== null));
    }

    public function test_terminal_status_is_logged_only_when_it_changes(): void
    {
        $this->signed('GET', '/api/v1/vends/50001/card/terminal');
        \Illuminate\Support\Facades\Cache::flush();
        $this->signed('GET', '/api/v1/vends/50001/card/terminal');
        $this->assertSame(1, CardPaymentEvent::where('event', 'terminal.status')->count());

        \Illuminate\Support\Facades\Cache::flush();
        $this->gateway->status = new \App\Services\CardTerminal\TerminalStatus(false, null);
        $this->signed('GET', '/api/v1/vends/50001/card/terminal');
        $change = CardPaymentEvent::where('event', 'terminal.status')->orderByDesc('id')->first();
        $this->assertSame(2, CardPaymentEvent::where('event', 'terminal.status')->count());
        $this->assertFalse($change->detail['online']);
        $this->assertTrue($change->detail['was_online']);
    }

    public function test_device_events_land_on_the_timeline(): void
    {
        $this->signed('POST', '/api/v1/vends/50001/card/events', ['events' => [
            ['event' => 'rail.selected', 'detail' => ['rail' => 'remote', 'reason' => 'terminal bound in mark1']],
            ['event' => 'call.failed', 'level' => 'warning', 'reference' => 'SF5', 'ms' => 8000, 'detail' => ['call' => 'status', 'error' => 'timeout']],
        ]])->assertOk()->assertJson(['recorded' => 2]);

        $rows = CardPaymentEvent::orderBy('id')->get();
        $this->assertSame(['device.rail.selected', 'device.call.failed'], $rows->pluck('event')->all());
        $this->assertSame('remote', $rows[0]->detail['rail']);
        $this->assertSame('50001-SF5', $rows[1]->custom_order_id);
        $this->assertSame(8000, $rows[1]->duration_ms);
        $this->assertSame('warning', $rows[1]->level);

        $this->signed('POST', '/api/v1/vends/50001/card/events', ['events' => [['event' => 'Bad Name!']]])->assertStatus(422);
    }

    public function test_timeline_command_prints_the_attempt(): void
    {
        $this->signed('POST', '/api/v1/vends/50001/card/authorize', ['reference' => 'SF7', 'amount_cents' => 430]);

        $this->artisan('payrallel:timeline', ['vend' => '50001', '--ref' => 'SF7'])
            ->expectsOutputToContain('intent.created')
            ->expectsOutputToContain('from=pending to=processing')
            ->expectsOutputToContain('action=authorize')
            ->assertSuccessful();
    }
}
