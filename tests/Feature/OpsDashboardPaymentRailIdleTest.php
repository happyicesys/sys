<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Scopes\OperatorVendFilterScope;
use App\Models\User;
use App\Support\OperatorScope;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * The Operation Dashboard rows carry `payment_idle` (App\Support\PaymentRailIdle) from the grid's
 * own columns, so the Card Terminal / LCD Monitor / Coin Float badges can draw their red frame.
 */
class OpsDashboardPaymentRailIdleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-17 12:00:00');
        config(['cache.default' => 'array']);
        Cache::flush();
        OperatorScope::flush();
        DB::table('operators')->insert([
            'id' => OperatorVendFilterScope::UNRESTRICTED_OPERATOR_ID, 'code' => 'HIPL', 'name' => 'HIPL',
            'is_active' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        OperatorScope::flush();
        parent::tearDown();
    }

    private function vend(int $code, array $attributes): void
    {
        $customerId = DB::table('customers')->insertGetId([
            'name' => "Site {$code}", 'profile_id' => 1, 'status_id' => Customer::STATUS_ACTIVE, 'is_active' => 1,
            'operator_id' => OperatorVendFilterScope::UNRESTRICTED_OPERATOR_ID, 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('vends')->insert(array_merge([
            'code' => $code, 'name' => "Machine {$code}", 'machine_type' => 'vending_machine',
            'operator_id' => OperatorVendFilterScope::UNRESTRICTED_OPERATOR_ID, 'customer_id' => $customerId,
            'is_active' => 1, 'is_testing' => 0, 'created_at' => now(), 'updated_at' => now(),
        ], $attributes));
    }

    public function test_rows_carry_the_idle_rails(): void
    {
        $nets = DB::table('card_terminals')->insertGetId(['name' => 'Nets', 'created_at' => now(), 'updated_at' => now()]);
        $this->vend(2031, [
            'card_terminal_id' => $nets, 'lcd_monitor_id' => 1, 'parameter_json' => json_encode(['CoinCnt' => 5860]),
            'last_vend_transaction_at' => now()->subHours(2),
            'last_card_vend_transaction_at' => now()->subDays(4),
            'last_cashless_vend_transaction_at' => now()->subHours(2),
            'last_cash_vend_transaction_at' => now()->subHours(5),
        ]);
        $this->vend(2032, [
            'card_terminal_id' => $nets,
            'last_vend_transaction_at' => now()->subHour(),
            'last_card_vend_transaction_at' => now()->subHour(),
        ]);

        $user = User::factory()->create(['operator_id' => OperatorVendFilterScope::UNRESTRICTED_OPERATOR_ID]);
        $user->givePermissionTo(Permission::findOrCreate('read vend-customers', 'web'));
        $user->givePermissionTo(Permission::findOrCreate('admin-access vend-customers', 'web'));
        OperatorScope::flush();

        $rows = [];
        $this->actingAs($user)->get('/vends/customers?autoload=1')->assertOk()
            ->assertInertia(function (Assert $page) use (&$rows) {
                foreach ($page->toArray()['props']['vends']['data'] as $vend) {
                    $rows[(int) $vend['code']] = (array) $vend['payment_idle'];
                }
            });

        $this->assertSame(['card'], array_keys($rows[2031]));
        $this->assertEquals(96, $rows[2031]['card']['hours_since']);
        $this->assertFalse($rows[2031]['card']['machine_idle']);
        $this->assertSame([], $rows[2032]);
    }
}
