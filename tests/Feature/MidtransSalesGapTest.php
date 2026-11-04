<?php

namespace Tests\Feature;

use App\Jobs\Vend\CreateGatewayVendTransaction;
use App\Jobs\Vend\LogNofoundTxnIfStillMissing;
use App\Jobs\VerifyPaymentWebhook;
use App\Models\OperatorPaymentGateway;
use App\Models\PaymentGateway;
use App\Models\PaymentGatewayLog;
use App\Models\PaymentGateways\Midtrans;
use App\Models\Scopes\OperatorVendFilterScope;
use App\Models\User;
use App\Models\Vend;
use App\Services\OpsDashboard\CriticalAttention;
use App\Services\Payment\WebhookVerdict;
use App\Services\Payment\WebhookVerifier;
use App\Services\VendDispenseService;
use App\Support\OperatorScope;
use Carbon\Carbon;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * IDN's paid QRIS sales that went missing (IDN_MIDTRANS_MISSING_SALES_PLAN_2026-11-04.md). Pinned: a QR
 * never outlives the cutoff; a Midtrans notification is signed by the log's own operator, and enforcing
 * that has its own switch; the sweep records what Midtrans says and never dispenses; the Paid, not served
 * list holds all three failures, inside the viewer's boundary, and shows nothing on a server with none.
 */
class MidtransSalesGapTest extends TestCase
{
    use RefreshDatabase;

    private const KEY = 'SB-Mid-server-one';

    private const OTHER_KEY = 'SB-Mid-server-two';

    private const OTHER_OPERATOR_ID = 2;

    private int $gatewayId;

    private int $operatorGatewayId;

    private int $otherOperatorGatewayId;

    private int $vendId;

    private int $sequence = 0;

    /** Any dispense not expected by the test fails it. */
    private $dispense;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-04 12:00:00');
        OperatorScope::flush();
        Bus::fake();
        $this->dispense = $this->mock(VendDispenseService::class);

        foreach ([[OperatorVendFilterScope::UNRESTRICTED_OPERATOR_ID, 'HIPL'], [self::OTHER_OPERATOR_ID, 'OTH']] as [$id, $code]) {
            DB::table('operators')->insert(['id' => $id, 'code' => $code, 'name' => $code, 'is_active' => 1, 'created_at' => now(), 'updated_at' => now()]);
        }
        $this->gatewayId = PaymentGateway::create(['name' => 'midtrans'])->id;
        $this->operatorGatewayId = OperatorPaymentGateway::create(['operator_id' => 1, 'payment_gateway_id' => $this->gatewayId, 'key1' => self::KEY, 'type' => 'production'])->id;
        $this->otherOperatorGatewayId = OperatorPaymentGateway::create(['operator_id' => self::OTHER_OPERATOR_ID, 'payment_gateway_id' => $this->gatewayId, 'key1' => self::OTHER_KEY, 'type' => 'production'])->id;
        $this->vendId = $this->vend(10017);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        OperatorScope::flush();
        parent::tearDown();
    }

    private function vend(int $code, int $operatorId = OperatorVendFilterScope::UNRESTRICTED_OPERATOR_ID): int
    {
        $customerId = DB::table('customers')->insertGetId([
            'name' => "Site {$code}", 'profile_id' => 1, 'status_id' => 1, 'is_active' => 1,
            'operator_id' => $operatorId, 'created_at' => now(), 'updated_at' => now(),
        ]);

        return DB::table('vends')->insertGetId([
            'code' => $code, 'name' => "Machine {$code}", 'machine_type' => Vend::MACHINE_TYPE_VENDING_MACHINE,
            'operator_id' => $operatorId, 'customer_id' => $customerId, 'is_active' => 1, 'is_testing' => 0,
            'is_online' => 1, 'mqtt_last_updated_at' => now(), 'last_vend_transaction_at' => now()->subHour(),
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function log(array $attributes = [], ?Carbon $createdAt = null): PaymentGatewayLog
    {
        $log = PaymentGatewayLog::create(array_merge([
            'order_id' => '2611041200000'.(++$this->sequence),
            'vend_id' => $this->vendId,
            'vend_code' => '10017',
            'operator_payment_gateway_id' => $this->operatorGatewayId,
            'payment_gateway_id' => $this->gatewayId,
            'amount' => 20000,
            'status' => PaymentGatewayLog::STATUS_PENDING,
            'txn_src' => 1,
        ], $attributes));
        $log->created_at = $createdAt ?? now()->subHours(2);
        $log->save();

        return $log->fresh();
    }

    /** A QR that expired 10 minutes ago, so the sweep asks about it. */
    private function expiredLog(array $attributes = []): PaymentGatewayLog
    {
        return $this->log($attributes + ['response' => ['expiry_time' => now('Asia/Jakarta')->subMinutes(10)->format('Y-m-d H:i:s')]]);
    }

    /** A notification as Midtrans sends it, gross_amount with its decimals. */
    private function notification(PaymentGatewayLog $log, string $state = 'settlement', string $key = self::KEY, string $gross = '20000.00'): array
    {
        $code = $state === 'settlement' ? '200' : '202';

        return [
            'order_id' => $log->order_id, 'status_code' => $code, 'gross_amount' => $gross, 'transaction_status' => $state,
            'transaction_id' => 'tx-'.$log->order_id, 'acquirer' => 'gopay',
            'signature_key' => hash('sha512', $log->order_id.$code.$gross.$key),
        ];
    }

    private function fakeMidtrans(array|int $answer): void
    {
        Http::fake(['*/status' => is_int($answer) ? Http::response(['message' => 'no'], $answer) : Http::response($answer, 200)]);
    }

    /** What GET /v2/{order_id}/status says about a log. */
    private function midtransSays(PaymentGatewayLog $log, string $state): array
    {
        return [
            'order_id' => $log->order_id, 'transaction_status' => $state, 'status_code' => '200', 'gross_amount' => '20000.00',
            'transaction_id' => 'tx-'.$log->order_id, 'acquirer' => 'gopay', 'settlement_time' => '2026-11-04 10:00:30',
        ];
    }

    private function viewer(int $operatorId = OperatorVendFilterScope::UNRESTRICTED_OPERATOR_ID, array $permissions = []): User
    {
        $user = User::factory()->create(['operator_id' => $operatorId]);
        foreach ($permissions as $permission) {
            $user->givePermissionTo(Permission::findOrCreate($permission, 'web'));
        }
        OperatorScope::flush();

        return $user;
    }

    private function sale(PaymentGatewayLog $log, int $settlement): void
    {
        DB::table('vend_transactions')->insert([
            'vend_id' => $log->vend_id, 'order_id' => $log->order_id, 'payment_gateway_log_id' => $log->id,
            'settlement_status' => $settlement, 'transaction_datetime' => $log->created_at, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    // ---------------------------------------------------------------- QR life

    public function test_a_qr_never_outlives_the_cutoff_whatever_the_board_sends(): void
    {
        Http::fake(['*/v2/charge' => Http::response(['order_id' => 'x'], 201)]);

        foreach ([120000 => 180, 120 => 120, 180 => 180, 0 => 180] as $sent => $expected) {
            (new Midtrans(self::KEY))->createCharge([
                'amount' => 20000, 'metadata' => ['order_id' => 'x'], 'type' => 'gopay', 'timezone' => 'Asia/Jakarta', 'expiry_seconds' => $sent,
            ]);
            Http::assertSent(fn ($request) => $request['custom_expiry']['expiry_duration'] === $expected && $request['custom_expiry']['unit'] === 'second');
        }

        $this->assertLessThan(210, Midtrans::MAX_QR_SECONDS, 'PaymentController never serves a payment after 210 s');
    }

    // ---------------------------------------------------------------- signature

    public function test_the_signature_is_checked_with_the_logs_own_operator_key(): void
    {
        $verifier = app(WebhookVerifier::class);
        $mine = $this->log();
        $theirs = $this->log(['operator_payment_gateway_id' => $this->otherOperatorGatewayId]);
        $approve = PaymentGatewayLog::STATUS_APPROVE;

        $this->assertSame(WebhookVerdict::VERIFIED, $verifier->verify('midtrans', $this->notification($mine), $mine, $approve)->outcome);
        $this->assertSame(WebhookVerdict::VERIFIED, $verifier->verify('midtrans', $this->notification($theirs, key: self::OTHER_KEY), $theirs, $approve)->outcome);

        $this->assertTrue($verifier->verify('midtrans', $this->notification($mine, key: self::OTHER_KEY), $mine, $approve)->isMismatch(), 'another operator\'s key');
        $this->assertTrue($verifier->verify('midtrans', ['signature_key' => null] + $this->notification($mine), $mine, $approve)->isMismatch(), 'no signature');
        $noSignature = $this->notification($mine);
        unset($noSignature['signature_key']);
        $this->assertTrue($verifier->verify('midtrans', $noSignature, $mine, $approve)->isMismatch());
        $this->assertTrue($verifier->verify('midtrans', ['gross_amount' => ['20000.00']] + $this->notification($mine), $mine, $approve)->isMismatch(), 'junk fields');
        $this->assertTrue($verifier->verify('midtrans', $this->notification($mine, gross: '1.00'), $mine, $approve)->isMismatch(), 'signed, but not our amount');
        $this->assertTrue($verifier->verify('midtrans', $this->notification($theirs, key: self::KEY), $theirs, $approve)->isMismatch());

        $noKey = $this->log(['operator_payment_gateway_id' => OperatorPaymentGateway::create(['operator_id' => 1, 'payment_gateway_id' => $this->gatewayId, 'key1' => '', 'type' => 'production'])->id]);
        $this->assertSame(WebhookVerdict::UNVERIFIABLE, $verifier->verify('midtrans', $this->notification($noKey), $noKey, $approve)->outcome);
    }

    public function test_enforce_refuses_a_forged_paid_and_a_forged_expire_and_lets_the_real_one_through(): void
    {
        config(['payment.midtrans_webhook_verification' => 'enforce']);
        $this->dispense->shouldReceive('getSingleParam')->once()->andReturn([]);
        $this->dispense->shouldReceive('dispense')->once();
        $log = $this->log(['request' => ['PRICE' => 20000]], now()->subMinute());

        $this->postJson('/api/v1/payment-gateway-status/midtrans', $this->notification($log, key: 'guess'))->assertOk();
        $this->assertSame(PaymentGatewayLog::STATUS_PENDING, $log->fresh()->status);
        Bus::assertNotDispatched(CreateGatewayVendTransaction::class);

        // A forged expire would close the order, and the real payment that follows would be lost.
        $this->postJson('/api/v1/payment-gateway-status/midtrans', $this->notification($log, 'expire', 'guess'))->assertOk();
        $this->assertSame(PaymentGatewayLog::STATUS_PENDING, $log->fresh()->status);

        $this->postJson('/api/v1/payment-gateway-status/midtrans', $this->notification($log))->assertOk();
        $this->assertSame(PaymentGatewayLog::STATUS_APPROVE, $log->fresh()->status);
        $this->assertNotNull($log->fresh()->approved_at);
    }

    public function test_midtrans_has_its_own_switch_so_sys_enforcing_omise_does_not_apply_to_it(): void
    {
        config(['payment.webhook_verification' => 'enforce', 'payment.midtrans_webhook_verification' => 'log']);
        $this->dispense->shouldReceive('getSingleParam')->once()->andReturn([]);
        $this->dispense->shouldReceive('dispense')->once();
        $log = $this->log(['request' => ['PRICE' => 20000]], now()->subMinute());

        $this->postJson('/api/v1/payment-gateway-status/midtrans', $this->notification($log, key: 'guess'))->assertOk();

        $this->assertSame(PaymentGatewayLog::STATUS_APPROVE, $log->fresh()->status, 'log mode only reports');
        Bus::assertDispatched(VerifyPaymentWebhook::class, fn ($job) => $job->company === 'midtrans');
    }

    public function test_a_state_with_no_mapping_changes_nothing(): void
    {
        $log = $this->log();

        $this->postJson('/api/v1/payment-gateway-status/midtrans', $this->notification($log, 'failure'))->assertOk();

        $this->assertSame(PaymentGatewayLog::STATUS_PENDING, $log->fresh()->status);
    }

    // ---------------------------------------------------------------- sweep

    public function test_a_payment_found_late_is_recorded_and_never_dispensed(): void
    {
        $log = $this->expiredLog();
        $this->fakeMidtrans($this->midtransSays($log, 'settlement'));

        $this->artisan('payment:reconcile-midtrans --apply')->assertSuccessful();

        $log->refresh();
        $this->assertSame(PaymentGatewayLog::STATUS_APPROVE, $log->status);
        $this->assertNull($log->approved_at, 'no approved_at: it was never served');
        $this->assertFalse($log->is_dispensed);
        $this->assertSame('tx-'.$log->order_id, $log->ref_id);
        $this->assertSame('gopay', $log->method);
        $this->assertSame('payment:reconcile-midtrans', $log->response['reconciled_by']);
        $this->assertNoSaleStarted();
        Http::assertSent(fn ($request) => str_ends_with($request->url(), "/v2/{$log->order_id}/status")
            && $request->header('Authorization')[0] === 'Basic '.base64_encode(self::KEY.':'));

        // The retried notification finds no open row: nothing can dispense after the sweep.
        $this->postJson('/api/v1/payment-gateway-status/midtrans', $this->notification($log))->assertOk();
        $this->assertNoSaleStarted();
    }

    public function test_each_other_answer(): void
    {
        foreach (['expire' => PaymentGatewayLog::STATUS_DECLINE, 'cancel' => PaymentGatewayLog::STATUS_DECLINE, 'deny' => PaymentGatewayLog::STATUS_DECLINE, 'refund' => PaymentGatewayLog::STATUS_REFUND, 'partial_refund' => PaymentGatewayLog::STATUS_REFUND, 'pending' => PaymentGatewayLog::STATUS_PENDING, 'failure' => PaymentGatewayLog::STATUS_PENDING] as $state => $expected) {
            $log = $this->expiredLog();
            $this->freshHttp();
            $this->fakeMidtrans($this->midtransSays($log, $state));

            $this->artisan('payment:reconcile-midtrans --apply --order='.$log->order_id)->assertSuccessful();

            $this->assertSame($expected, $log->fresh()->status, $state);
        }
    }

    public function test_an_unclear_answer_leaves_the_row_alone(): void
    {
        $log = $this->expiredLog();
        $answers = [
            500,
            ['status_code' => '404', 'status_message' => "Transaction doesn't exist."],
            404,
            ['transaction_status' => 'settlement', 'order_id' => 'someone-else', 'gross_amount' => '20000.00'],
            ['gross_amount' => '1.00'] + $this->midtransSays($log, 'settlement'),
        ];
        foreach ($answers as $answer) {
            $this->freshHttp();
            $this->fakeMidtrans($answer);
            $this->artisan('payment:reconcile-midtrans --apply')->assertSuccessful();
            $this->assertSame(PaymentGatewayLog::STATUS_PENDING, $log->fresh()->status, json_encode($answer));
        }
    }

    public function test_it_only_asks_about_qrs_that_expired_a_while_ago(): void
    {
        $this->fakeMidtrans(['status_code' => '404']);
        $qrStillAlive = $this->log(['response' => ['expiry_time' => '2026-11-05 22:55:04']]);
        $inGrace = $this->log(['response' => ['expiry_time' => now('Asia/Jakarta')->subMinutes(3)->format('Y-m-d H:i:s')]]);
        $expired = $this->log(['response' => ['expiry_time' => now('Asia/Jakarta')->subMinutes(10)->format('Y-m-d H:i:s')]]);
        // No expiry on record: the longest QR ever made is 33.3 h.
        $noExpiryYoung = $this->log([], now()->subHours(20));
        $tooOld = $this->log([], now()->subDays(100));
        $notMidtrans = $this->log(['payment_gateway_id' => PaymentGateway::create(['name' => 'omise'])->id], now()->subDays(3));
        $settled = $this->log(['status' => PaymentGatewayLog::STATUS_APPROVE], now()->subDays(3));

        $this->artisan('payment:reconcile-midtrans --apply')->assertSuccessful();

        Http::assertSentCount(1);
        Http::assertSent(fn ($request) => str_contains($request->url(), "/{$expired->order_id}/"));
    }

    public function test_a_dry_run_asks_but_writes_nothing(): void
    {
        $log = $this->expiredLog();
        $this->fakeMidtrans($this->midtransSays($log, 'settlement'));

        $this->artisan('payment:reconcile-midtrans')->expectsOutputToContain('Dry run: asked 1, paid 1')->assertSuccessful();

        $this->assertSame(PaymentGatewayLog::STATUS_PENDING, $log->fresh()->status);
    }

    public function test_a_notification_racing_the_sweep_wins_and_applies_once(): void
    {
        $log = $this->expiredLog();
        Http::fake(function () use ($log) {
            PaymentGatewayLog::whereKey($log->id)->update(['status' => PaymentGatewayLog::STATUS_DECLINE]);

            return Http::response($this->midtransSays($log, 'settlement'), 200);
        });

        $this->artisan('payment:reconcile-midtrans --apply')->expectsOutputToContain('raced 1')->assertSuccessful();

        $this->assertSame(PaymentGatewayLog::STATUS_DECLINE, $log->fresh()->status);
    }

    public function test_an_outage_ends_the_run_early(): void
    {
        foreach (range(1, 5) as $i) {
            $this->expiredLog();
        }
        Http::fake(fn () => throw new ConnectionException('down'));

        $this->artisan('payment:reconcile-midtrans --apply')->expectsOutputToContain('asked 3')->assertSuccessful();
    }

    public function test_a_server_with_no_midtrans_asks_nothing_and_the_schedule_is_off_until_switched_on(): void
    {
        PaymentGateway::where('name', 'midtrans')->update(['name' => 'omise']);
        Http::fake();
        $this->log();
        $this->artisan('payment:reconcile-midtrans --apply')->assertSuccessful();
        Http::assertNothingSent();

        $event = collect(app(Schedule::class)->events())->first(fn ($e) => str_contains((string) $e->command, 'payment:reconcile-midtrans'));
        $this->assertNotNull($event);
        $this->assertFalse($event->filtersPass($this->app));
        config(['payment.midtrans_reconcile.enabled' => true]);
        $this->assertTrue($event->filtersPass($this->app));
    }

    // ---------------------------------------------------------------- list

    /** @return array<string, PaymentGatewayLog> */
    private function seedListRows(): array
    {
        $paid = ['status' => PaymentGatewayLog::STATUS_APPROVE];

        $listed = [
            'late' => $this->log($paid),
            'stuck' => $this->log($paid + ['approved_at' => now()->subHours(2)]),
        ];
        $this->sale($listed['stuck'], 0);

        $notListed = [
            'settled' => $this->log($paid + ['approved_at' => now()->subHours(2)]),
            'dispensed' => $this->log($paid + ['approved_at' => now()->subHours(2), 'is_dispensed' => true]),
            'refunded' => $this->log(['status' => PaymentGatewayLog::STATUS_REFUND]),
            'open' => $this->log(),
            'young' => $this->log($paid, now()->subMinutes(10)),
            'test amount' => $this->log($paid + ['amount' => 200]),
            'not midtrans' => $this->log($paid + ['payment_gateway_id' => PaymentGateway::create(['name' => 'omise'])->id]),
        ];
        $this->sale($notListed['settled'], 2);

        return ['listed' => $listed, 'not_listed' => $notListed];
    }

    public function test_the_list_holds_what_was_paid_and_not_confirmed_and_nothing_else(): void
    {
        $rows = $this->seedListRows();

        $found = PaymentGatewayLog::query()->paidNotServed()->pluck('id')->sort()->values()->all();

        $this->assertSame(collect($rows['listed'])->pluck('id')->sort()->values()->all(), $found);
    }

    public function test_a_late_trade_takes_a_row_off_the_list(): void
    {
        $rows = $this->seedListRows();

        DB::table('vend_transactions')->where('payment_gateway_log_id', $rows['listed']['stuck']->id)->update(['settlement_status' => 2]);

        $this->assertSame([$rows['listed']['late']->id], PaymentGatewayLog::query()->paidNotServed()->pluck('id')->all());
    }

    public function test_the_page_filter_finds_rows_with_no_paid_time_and_keeps_to_the_viewers_machines(): void
    {
        config(['app.gateway_unified_txn_enabled' => true]);
        $rows = $this->seedListRows();
        $url = '/vends/payment-gateway-transactions?paid_not_served=true&date_from=2026-11-01&date_to=2026-11-04&operators[]=all';

        $this->actingAs($this->viewer())->get($url)->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('paidNotServed.count', 2)
            ->where('paymentGatewayLogs.meta.total', 2)
            ->where('paymentGatewayLogs.data', fn ($data) => collect($data)->firstWhere('order_id', $rows['listed']['late']->order_id)['paid_at_formatted'] !== null));

        // Without the filter a payment with no approved_at stays out, as it always has.
        $this->actingAs($this->viewer())->get('/vends/payment-gateway-transactions?date_from=2026-11-01&date_to=2026-11-04&operators[]=all')
            ->assertInertia(fn (Assert $page) => $page->where('paymentGatewayLogs.meta.total', 3)->where('paidNotServed.count', 2));

        // Asking for "all" never reaches past the viewer's own machines.
        $this->actingAs($this->viewer(self::OTHER_OPERATOR_ID))->get($url)->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('paidNotServed.count', 0)
            ->where('paymentGatewayLogs.meta.total', 0));
    }

    public function test_the_count_looks_back_the_refund_window_not_the_pages_dates(): void
    {
        $this->log(['status' => PaymentGatewayLog::STATUS_APPROVE], now()->subDays(44));
        $this->log(['status' => PaymentGatewayLog::STATUS_APPROVE], now()->subDays(46));

        $this->actingAs($this->viewer())->get('/vends/payment-gateway-transactions')->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('paidNotServed.count', 1));
    }

    // ---------------------------------------------------------------- Needs attention

    public function test_needs_attention_names_the_machine_and_stays_hidden_with_none(): void
    {
        $group = fn (array $result) => collect($result['groups'])->firstWhere('key', CriticalAttention::GROUP_PAID_NOT_SERVED);

        $this->assertNull($group(app(CriticalAttention::class)->forVendIds([$this->vendId])), 'a server with none looks as it did');

        $this->log(['status' => PaymentGatewayLog::STATUS_APPROVE]);
        $this->log(['status' => PaymentGatewayLog::STATUS_APPROVE]);
        $otherVend = $this->vend(10099);
        $this->log(['status' => PaymentGatewayLog::STATUS_APPROVE, 'vend_id' => $otherVend]);
        $this->log(['status' => PaymentGatewayLog::STATUS_APPROVE], now()->subDays(46));

        $found = $group(app(CriticalAttention::class)->forVendIds([$this->vendId]));
        $this->assertSame(['10017'], array_column($found['machines'], 'code_label'), 'only the machines asked about');
        $this->assertSame('2 payments', $found['machines'][0]['detail']);
        $this->assertNull($found['link'], 'no link for a person who cannot open the page');

        $this->actingAs($this->viewer(permissions: ['read transactions-payment-gateway']));
        $this->assertStringContainsString('paid_not_served=true', $group(app(CriticalAttention::class)->forVendIds([$this->vendId]))['link']);
    }

    private function assertNoSaleStarted(): void
    {
        Bus::assertNotDispatched(CreateGatewayVendTransaction::class);
        Bus::assertNotDispatched(LogNofoundTxnIfStillMissing::class);
        $this->assertSame(0, DB::table('vend_transactions')->count());
    }

    private function freshHttp(): void
    {
        \Illuminate\Support\Facades\Facade::clearResolvedInstance(\Illuminate\Http\Client\Factory::class);
        $this->app->forgetInstance(\Illuminate\Http\Client\Factory::class);
    }
}
