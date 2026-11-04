<?php

namespace Tests\Feature;

use App\Http\Middleware\VerifyDeviceSignature;
use App\Models\FreezerFailedSession;
use App\Models\Operator;
use App\Models\Vend;
use App\Models\VendTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * A freezer reports a paid session that ended in an error, with its cart (Brian, 2026-11-04). A record
 * only: the customer was never charged, so it must never look like a sale.
 */
class FreezerFailedSessionTest extends TestCase
{
    use RefreshDatabase;

    private const KEY = 'TESTKEY000000001';

    private const REF = 'SF-50001-1793784418-6';

    private Vend $vend;

    protected function setUp(): void
    {
        parent::setUp();
        $operator = Operator::withoutGlobalScopes()->create(['code' => 'TSTOP', 'name' => 'Test Operator', 'is_active' => true]);
        $this->vend = $this->machine(50001, $operator->id);
    }

    private function machine(int $code, int $operatorId, string $type = Vend::MACHINE_TYPE_SMART_FREEZER): Vend
    {
        $vend = new Vend;
        $vend->forceFill([
            'code' => $code, 'name' => "Machine {$code}", 'machine_type' => $type, 'is_active' => 1,
            'operator_id' => $operatorId, 'vend_model_id' => 1, 'private_key' => self::KEY, 'is_testing' => 0,
        ])->save();

        return $vend->refresh();
    }

    private function report(array $body = [], string $ref = self::REF, int $code = 50001, string $key = self::KEY): TestResponse
    {
        $path = "/api/v1/vends/{$code}/sessions/{$ref}/failed";
        $json = json_encode($body + [
            'payment_method' => 'card', 'payment_ref' => 'PAY123', 'order_id' => '26110417270700001', 'amount_cents' => 430,
            'reason' => 'door_open_failed', 'detail' => 'door refused: LOCK 1号门锁开锁超时', 'started_at' => 1793784418, 'ended_at' => 1793784433,
            'items' => [['sku' => '111', 'name' => 'Aye', 'unit_cents' => 215, 'qty' => 2, 'channel' => '11', 'product_id' => 7]],
        ]);
        $ts = (string) time();
        $headers = [
            'X-Device-Timestamp' => $ts,
            'X-Device-Signature' => VerifyDeviceSignature::sign($key, 'POST', $path, $ts, $json),
            'Content-Type' => 'application/json',
        ];

        return $this->call('POST', $path, [], [], [], $this->transformHeadersToServerVars($headers), $json);
    }

    public function test_a_report_is_kept_and_books_no_sale(): void
    {
        $this->report()->assertStatus(201)->assertJson(['status' => 'recorded']);

        $session = FreezerFailedSession::sole();
        $this->assertSame($this->vend->id, $session->vend_id);
        $this->assertSame(self::REF, $session->session_ref);
        $this->assertSame(430, $session->amount_cents);
        $this->assertSame('door_open_failed', $session->reason);
        $this->assertSame(2, $session->items[0]['qty']);
        $this->assertSame(7, $session->items[0]['product_id']);
        $this->assertSame('2026-11-04 17:27:13', $session->ended_at->timezone(config('app.timezone'))->format('Y-m-d H:i:s'));
        $this->assertSame(0, VendTransaction::withoutGlobalScopes()->count());
    }

    public function test_a_repeat_answers_ok_and_the_first_report_stands(): void
    {
        $this->report()->assertStatus(201);
        $this->report(['amount_cents' => 999])->assertStatus(200);

        $this->assertSame(430, FreezerFailedSession::sole()->amount_cents);
    }

    public function test_a_report_from_before_the_app_sent_details_is_kept(): void
    {
        $this->report(['detail' => null, 'payment_ref' => null, 'order_id' => null])->assertStatus(201);

        $this->assertNull(FreezerFailedSession::sole()->detail);
    }

    public function test_refused_for_good_with_a_plain_422(): void
    {
        $this->report(ref: 'SF-50002-1793784418-6')->assertStatus(422)->assertJson(['status' => 'refused']);
        $this->report(['reason' => null])->assertStatus(422);
        $this->report(['items' => [['unit_cents' => 100, 'qty' => 0]]])->assertStatus(422);

        $this->machine(2031, 1, Vend::MACHINE_TYPE_VENDING_MACHINE);
        $this->report(ref: 'SF-2031-1793784418-6', code: 2031)->assertStatus(422);

        $this->assertSame(0, FreezerFailedSession::count());
    }

    public function test_it_must_be_signed_by_the_machine(): void
    {
        $this->report(key: 'WRONGKEY00000001')->assertStatus(401);

        $this->assertSame(0, FreezerFailedSession::count());
    }
}
