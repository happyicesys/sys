<?php

namespace Tests\Feature;

use App\Models\Operator;
use App\Models\PaymentGatewayLog;
use App\Models\Scopes\OperatorVendFilterScope;
use App\Models\User;
use App\Services\MachineHealthDashboardService;
use App\Support\OperatorScope;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Cross-operator leaks on the Sales Transactions card and the Machine Health
 * dashboard, reported 2026-08-14 by a leasing customer (operator XO, one
 * machine, zero sales) who was shown S$1,532 of Total Sales against an empty
 * grid, plus channel errors for machines that were not theirs.
 *
 * Both had the same shape: a table with NO global scope of its own
 * (payment_gateway_logs, vend_channel_error_logs) reaching machine/transaction
 * data by a route the global scopes never covered. Each test below fails on the
 * pre-fix code.
 */
class OperatorScopeLeakTest extends TestCase
{
    use RefreshDatabase;

    private Operator $hipl;

    private Operator $opA;

    private Operator $opB;

    protected function setUp(): void
    {
        parent::setUp();

        config(['cache.default' => 'array']);
        Cache::flush();
        OperatorScope::flush();

        // Pinned, not just created first: OperatorVendFilterScope keys the
        // "sees the whole fleet" exemption on the id, and RefreshDatabase rolls
        // back without resetting AUTO_INCREMENT, so insertion order does not
        // give a stable id 1.
        $this->hipl = $this->hiplOperator();
        $this->opA = $this->operator('OPA');
        $this->opB = $this->operator('OPB');
    }

    protected function tearDown(): void
    {
        OperatorScope::flush();
        parent::tearDown();
    }

    // ---------------------------------------------------------------- fixtures

    private function hiplOperator(): Operator
    {
        $id = OperatorVendFilterScope::UNRESTRICTED_OPERATOR_ID;

        DB::table('operators')->insert([
            'id' => $id,
            'code' => 'HIPL',
            'name' => 'HIPL',
            'is_active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return Operator::withoutGlobalScopes()->findOrFail($id);
    }

    private function operator(string $code): Operator
    {
        return Operator::withoutGlobalScopes()->firstOrCreate(['code' => $code], [
            'name' => $code,
            'is_active' => true,
        ]);
    }

    private function userFor(Operator $operator): User
    {
        $user = User::factory()->create(['operator_id' => $operator->id]);

        OperatorScope::flush();

        return $user;
    }

    /** @return int vend id */
    private function makeVend(Operator $operator, int $code): int
    {
        $customerId = DB::table('customers')->insertGetId([
            'name' => "Site {$code}",
            'profile_id' => 1,
            'status_id' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return DB::table('vends')->insertGetId([
            'code' => $code,
            'name' => "Machine {$code}",
            'operator_id' => $operator->id,
            'customer_id' => $customerId,
            'is_testing' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function makeUnclearedChannelError(int $vendId, int $errorCode): void
    {
        $channelId = DB::table('vend_channels')->insertGetId([
            'vend_id' => $vendId,
            'code' => 11,
            'error_rate_json' => '{}',
            'is_active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $errorId = DB::table('vend_channel_errors')->where('code', $errorCode)->value('id')
            ?: DB::table('vend_channel_errors')->insertGetId([
                'code' => $errorCode,
                'desc' => "Error {$errorCode}",
                'created_at' => now(),
                'updated_at' => now(),
            ]);

        DB::table('vend_channel_error_logs')->insert([
            'vend_channel_id' => $channelId,
            'vend_channel_error_id' => $errorId,
            // Only has to be non-null: the dashboard counts DISTINCT transaction ids.
            'vend_transaction_id' => 900000 + $vendId,
            'is_error_cleared' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * An approved + dispensed gateway payment. $reported controls whether the
     * machine reported it back as a vend_transaction - i.e. whether it is
     * genuinely "dispensed but unreported" revenue.
     */
    private function makeGatewayPayment(Operator $operator, int $vendId, float $amount, bool $reported): void
    {
        $gatewayAccountId = DB::table('operator_payment_gateways')->insertGetId([
            'operator_id' => $operator->id,
            'payment_gateway_id' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $logId = DB::table('payment_gateway_logs')->insertGetId([
            'operator_payment_gateway_id' => $gatewayAccountId,
            'payment_gateway_id' => 1,
            'vend_id' => $vendId,
            'amount' => $amount,
            'status' => PaymentGatewayLog::STATUS_APPROVE,
            'is_dispensed' => 1,
            'approved_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        if (! $reported) {
            return;
        }

        DB::table('vend_transactions')->insert([
            'payment_gateway_log_id' => $logId,
            'order_id' => "ORDER-{$logId}",
            'transaction_datetime' => now(),
            'vend_channel_id' => 1,
            'vend_id' => $vendId,
            'operator_id' => $operator->id,
            'amount' => (int) round($amount * 100),
            'gst_vat_rate' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function unreportedTotalFor(User $user): float
    {
        $this->actingAs($user);

        return (float) PaymentGatewayLog::query()
            ->unreportedDispensed(new Request)
            ->sum('payment_gateway_logs.amount');
    }

    // ------------------------------------------------- Total Sales card leaks

    /**
     * The reported bug. Every gateway payment in the fleet HAD been reported, so
     * "dispensed but unreported" revenue was 0.00 for everyone - yet the card
     * showed the whole fleet's QR total.
     *
     * whereDoesntHave('vendTransaction') ran VendTransaction's global scopes
     * inside the existence subquery, so for an operator-scoped viewer the test
     * degraded to "no transaction I can SEE", which is true of every other
     * operator's transaction.
     */
    public function test_reported_payments_are_never_counted_as_unreported_for_another_operator(): void
    {
        $vendB = $this->makeVend($this->opB, 1002);
        $this->makeGatewayPayment($this->opB, $vendB, 5.50, reported: true);

        $this->makeVend($this->opA, 1001);

        $this->assertSame(0.0, $this->unreportedTotalFor($this->userFor($this->opA)));
    }

    /**
     * Genuinely unreported revenue still belongs to exactly one operator. The
     * "All" operator chip is the filter's default and drops the operator
     * predicate, so nothing but the viewer ceiling stands between it and the
     * whole fleet.
     */
    public function test_unreported_revenue_is_visible_only_to_its_own_operator(): void
    {
        $vendB = $this->makeVend($this->opB, 1002);
        $this->makeGatewayPayment($this->opB, $vendB, 7.25, reported: false);

        $this->makeVend($this->opA, 1001);

        $this->assertSame(0.0, $this->unreportedTotalFor($this->userFor($this->opA)));
        $this->assertSame(7.25, $this->unreportedTotalFor($this->userFor($this->opB)));
    }

    /**
     * The other half of the fix: it must not quietly restrict HappyIce staff,
     * whose Total Sales headline is fleet-wide by design.
     */
    public function test_happyice_staff_still_see_fleet_wide_unreported_revenue(): void
    {
        $vendA = $this->makeVend($this->opA, 1001);
        $vendB = $this->makeVend($this->opB, 1002);

        $this->makeGatewayPayment($this->opA, $vendA, 2.00, reported: false);
        $this->makeGatewayPayment($this->opB, $vendB, 7.25, reported: false);

        $this->assertSame(9.25, $this->unreportedTotalFor($this->userFor($this->hipl)));
    }

    // ------------------------------------------------- Machine Health leaks

    /** @return int[] vend codes in the dispense-stability table */
    private function channelErrorVendCodesFor(User $user): array
    {
        $this->actingAs($user);

        $data = app(MachineHealthDashboardService::class)->getDashboardData(new Request);

        return collect($data['error_codes']['dispense_stability']['rows'])
            ->pluck('vend_code')
            ->map(fn ($code) => (int) $code)
            ->sort()
            ->values()
            ->all();
    }

    /**
     * Section (5) Channel Errors drives from vend_channel_error_logs and reaches
     * vends by a RAW JOIN, so OperatorVendFilterScope never fired and every
     * operator saw the whole fleet's errors.
     *
     * Asserting both directions also covers the cache: the dashboard is cached
     * for 5 minutes and the key was built from the request filters alone, with
     * no viewer identity, so on a default page load whoever warmed it decided
     * what every other operator saw.
     */
    public function test_channel_errors_are_isolated_per_operator(): void
    {
        $vendA = $this->makeVend($this->opA, 1001);
        $vendB = $this->makeVend($this->opB, 1002);

        $this->makeUnclearedChannelError($vendA, 7);
        $this->makeUnclearedChannelError($vendB, 7);

        $this->assertSame([1001], $this->channelErrorVendCodesFor($this->userFor($this->opA)));
        $this->assertSame([1002], $this->channelErrorVendCodesFor($this->userFor($this->opB)));
    }

    /** HappyIce staff keep the fleet-wide view of channel errors. */
    public function test_happyice_staff_still_see_fleet_wide_channel_errors(): void
    {
        $vendA = $this->makeVend($this->opA, 1001);
        $vendB = $this->makeVend($this->opB, 1002);

        $this->makeUnclearedChannelError($vendA, 7);
        $this->makeUnclearedChannelError($vendB, 7);

        $this->assertSame([1001, 1002], $this->channelErrorVendCodesFor($this->userFor($this->hipl)));
    }

    /**
     * A request-supplied operator filter is a preference, not an entitlement:
     * asking for someone else's operator must return nothing, not their data.
     */
    public function test_requested_operator_filter_cannot_widen_the_viewer_ceiling(): void
    {
        $vendA = $this->makeVend($this->opA, 1001);
        $vendB = $this->makeVend($this->opB, 1002);

        $this->makeUnclearedChannelError($vendA, 7);
        $this->makeUnclearedChannelError($vendB, 7);

        $this->actingAs($this->userFor($this->opA));

        $data = app(MachineHealthDashboardService::class)->getDashboardData(
            new Request(['operator_ids' => [$this->opB->id]])
        );

        $this->assertSame([], $data['error_codes']['dispense_stability']['rows']);
    }
}
