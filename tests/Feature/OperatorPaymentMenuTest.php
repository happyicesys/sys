<?php

namespace Tests\Feature;

use App\Models\Operator;
use App\Models\PaymentMethod;
use App\Models\User;
use App\Services\Payment\OperatorPaymentMenu;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Operator → Edit → Machine Payment Menu (Brian, 2026-10-29): each operator chooses which QR payment options its
 * machines show. A normal option shows unless switched off, an opt-in one (Ace & Apex) only where switched on, and
 * an operator nobody touched keeps the normal menu byte for byte.
 */
class OperatorPaymentMenuTest extends TestCase
{
    use RefreshDatabase;

    private const MENU = '/api/v1/payment-merchants/SG/omise';

    private int $gatewayId;

    private int $myGatewayId;

    private Operator $himd;

    private Operator $hipl;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();

        $sg = DB::table('countries')->insertGetId(['name' => 'Singapore', 'code' => 'SG', 'currency_name' => 'SGD', 'currency_symbol' => '$', 'phone_code' => 65]);
        $my = DB::table('countries')->insertGetId(['name' => 'Malaysia', 'code' => 'MY', 'currency_name' => 'MYR', 'currency_symbol' => 'RM', 'phone_code' => 60]);
        $this->gatewayId = DB::table('payment_gateways')->insertGetId(['name' => 'omise', 'country_id' => $sg, 'created_at' => now(), 'updated_at' => now()]);
        $this->myGatewayId = DB::table('payment_gateways')->insertGetId(['name' => 'omise', 'country_id' => $my, 'created_at' => now(), 'updated_at' => now()]);

        $payNow = $this->method(201, 'Paynow', 1);
        $this->method(801, 'LifeSG', 2);
        $this->method(211, 'Dana', 3);
        $this->method(802, 'Ace Credits & Apex Credits', 0, 'mindef');
        $this->method(301, 'DuitNow', 1, null, $this->myGatewayId);
        DB::table('payment_gateways')->where('id', $this->gatewayId)->update(['default_payment_method_id' => $payNow]);

        $this->himd = $this->operator('HIMD', 2788);
        $this->hipl = $this->operator('HIPL', 1001);
    }

    private function method(int $code, string $merchant, int $sequence, ?string $variant = null, ?int $gatewayId = null): int
    {
        $merchantId = DB::table('payment_merchants')->insertGetId([
            'name' => $merchant, 'image_url' => "https://example.test/{$code}.png", 'created_at' => now(), 'updated_at' => now(),
        ]);

        return PaymentMethod::create([
            'code' => $code, 'name' => $merchant, 'type_name' => 'paynow', 'menu_variant' => $variant, 'sequence' => $sequence,
            'is_active' => true, 'payment_gateway_id' => $gatewayId ?? $this->gatewayId, 'payment_merchant_id' => $merchantId,
        ])->id;
    }

    /** An operator holding SG Omise keys, with one machine. */
    private function operator(string $code, int $machine): Operator
    {
        $operator = Operator::withoutGlobalScopes()->create(['code' => $code, 'name' => $code, 'is_active' => true]);
        DB::table('operator_payment_gateways')->insert([
            'operator_id' => $operator->id, 'payment_gateway_id' => $this->gatewayId, 'key1' => 'pk', 'key2' => 'sk',
            'type' => 'production', 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('vends')->insert([
            'code' => $machine, 'name' => "Machine {$machine}", 'operator_id' => $operator->id, 'is_testing' => 0,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return $operator;
    }

    private function admin(): User
    {
        return $this->userWithOperatorAccess(1);
    }

    /** Admin → Operators needs both: the controller reads, the switch updates. */
    private function userWithOperatorAccess(int $operatorId): User
    {
        $user = User::factory()->create(['operator_id' => $operatorId]);
        $user->givePermissionTo(Permission::findOrCreate('read operators', 'web'), Permission::findOrCreate('update operators', 'web'));

        return $user;
    }

    private function codes(string $machine): array
    {
        return collect($this->getJson(self::MENU.'?machineID='.$machine)->assertOk()->json())->pluck('code')->all();
    }

    private function id(int $code): int
    {
        return PaymentMethod::where('code', $code)->value('id');
    }

    private function set(Operator $operator, int $code, bool $shown)
    {
        return $this->actingAs($this->admin())->postJson("/operators/{$operator->id}/payment-menu", [
            'payment_method_id' => $this->id($code), 'is_shown' => $shown,
        ]);
    }

    public function test_an_operator_nobody_touched_gets_the_normal_menu_byte_for_byte(): void
    {
        $plain = $this->getJson(self::MENU)->assertOk()->getContent();
        Cache::flush();

        $this->assertSame($plain, $this->getJson(self::MENU.'?machineID=1001')->getContent());
        $this->assertSame([201, 801, 211], $this->codes('2788'));
    }

    public function test_switching_options_changes_only_that_operators_machines_at_once(): void
    {
        // Warm every cache first, so the change must clear it.
        $this->assertSame([201, 801, 211], $this->codes('2788'));
        $this->getJson(self::MENU)->assertOk();

        $this->set($this->himd, 802, true)->assertOk();
        $this->set($this->himd, 211, false)->assertOk();

        $this->assertSame([802, 201, 801], $this->codes('2788'));
        $this->assertSame([201, 801, 211], $this->codes('1001'));
        $this->assertSame([201, 801, 211], collect($this->getJson(self::MENU)->json())->pluck('code')->all());
        $this->assertSame(2, DB::table('operator_payment_methods')->where('operator_id', $this->himd->id)->count());
    }

    public function test_switching_back_to_the_default_removes_the_row(): void
    {
        $this->set($this->himd, 211, false)->assertOk();
        $this->assertSame([201, 801], $this->codes('2788'));

        $this->set($this->himd, 211, true)->assertOk()
            ->assertJsonPath('paymentMenus.0.shown_count', 3);

        $this->assertSame(0, DB::table('operator_payment_methods')->count());
        $this->assertSame([201, 801, 211], $this->codes('2788'));
    }

    public function test_everything_switched_off_falls_back_to_the_normal_menu(): void
    {
        foreach ([201, 801, 211] as $code) {
            $this->set($this->himd, $code, false)->assertOk();
        }

        $this->assertSame([201, 801, 211], $this->codes('2788'));
        $table = app(OperatorPaymentMenu::class)->tableFor($this->himd);
        $this->assertTrue($table[0]['falls_back']);
        $this->assertSame(3, $table[0]['shown_count']);
    }

    public function test_the_first_option_shown_becomes_the_default_when_paynow_is_switched_off(): void
    {
        $this->set($this->himd, 201, false)->assertOk();

        $menu = $this->getJson(self::MENU.'?machineID=2788')->assertOk()->json();
        $this->assertSame([801, 211], array_column($menu, 'code'));
        $this->assertSame([true, false], array_column($menu, 'is_default'));

        // An operator that keeps PayNow keeps PayNow as its default.
        $this->set($this->hipl, 211, false)->assertOk();
        $menu = $this->getJson(self::MENU.'?machineID=1001')->json();
        $this->assertSame([true, false], array_column($menu, 'is_default'));
        $this->assertSame(201, $menu[0]['code']);
    }

    public function test_the_url_tail_still_wins_over_the_operators_choices(): void
    {
        $this->set($this->hipl, 211, false)->assertOk();

        $codes = collect($this->getJson(self::MENU.'/mindef?machineID=1001')->assertOk()->json())->pluck('code')->all();
        $this->assertSame([802, 201, 801, 211], $codes);
    }

    public function test_an_option_of_a_gateway_the_operator_does_not_use_is_refused(): void
    {
        $this->set($this->himd, 301, true)->assertStatus(422)->assertJsonValidationErrors('payment_method_id');

        $this->assertSame(0, DB::table('operator_payment_methods')->count());
    }

    public function test_only_users_who_may_update_operators_can_switch(): void
    {
        $viewer = User::factory()->create(['operator_id' => 1]);
        $viewer->givePermissionTo(Permission::findOrCreate('read operators', 'web'));
        $this->actingAs($viewer)->postJson("/operators/{$this->himd->id}/payment-menu", [
            'payment_method_id' => $this->id(211), 'is_shown' => false,
        ])->assertForbidden();

        // Another operator's admin cannot change HIMD's menu.
        $this->actingAs($this->userWithOperatorAccess($this->hipl->id))->postJson("/operators/{$this->himd->id}/payment-menu", [
            'payment_method_id' => $this->id(211), 'is_shown' => false,
        ])->assertForbidden();

        $this->assertSame(0, DB::table('operator_payment_methods')->count());
    }

    public function test_the_table_lists_the_operators_gateway_with_each_choice(): void
    {
        $this->set($this->himd, 802, true)->assertOk();

        $table = app(OperatorPaymentMenu::class)->tableFor($this->himd->fresh());
        $this->assertCount(1, $table);
        $this->assertSame('SG', $table[0]['country']);
        $this->assertSame([802, 201, 801, 211], array_column($table[0]['methods'], 'code'));
        $ace = $table[0]['methods'][0];
        $this->assertFalse($ace['shown_by_default']);
        $this->assertTrue($ace['is_shown']);
        $this->assertNotNull($ace['changed_by']);
        $this->assertTrue($table[0]['methods'][1]['is_gateway_default']);
    }

    public function test_the_migration_carries_the_operator_variant_over(): void
    {
        Schema::drop('operator_payment_methods');
        DB::table('operators')->where('id', $this->himd->id)->update(['payment_menu_variant' => ' MINDEF ']);

        (require database_path('migrations/2026_10_29_220000_create_operator_payment_methods_table.php'))->up();

        $rows = DB::table('operator_payment_methods')->get();
        $this->assertCount(1, $rows);
        $this->assertSame($this->himd->id, (int) $rows[0]->operator_id);
        $this->assertSame($this->id(802), (int) $rows[0]->payment_method_id);
        $this->assertSame(1, (int) $rows[0]->is_shown);
        $this->assertSame([802, 201, 801, 211], $this->codes('2788'));
    }
}
