<?php

namespace Tests\Feature;

use App\Jobs\PublishMqtt;
use App\Jobs\Vend\PushPaymentMenu;
use App\Models\Operator;
use App\Models\PaymentMethod;
use App\Models\User;
use App\Models\Vend;
use App\Services\Payment\OperatorPaymentMenu;
use App\Services\VendJobService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * A Machine Payment Menu change reaches the machines at once and the page shows which have it (Brian, 2026-10-30).
 * A save queues PAYMENTMENU (debounced) to the operator's big boards on app 321+; each machine's own fetch of its
 * menu is recorded, and a machine is "up to date" when what it last fetched is what the menu answers now.
 */
class PaymentMenuPushTest extends TestCase
{
    use RefreshDatabase;

    private const MENU = '/api/v1/payment-merchants/SG/omise';

    private Operator $himd;

    private Operator $hipl;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        // On by default in config; set here so a local .env that switches it off cannot fail the suite.
        config(['payment.menu_push' => true]);

        $sg = DB::table('countries')->insertGetId(['name' => 'Singapore', 'code' => 'SG', 'currency_name' => 'SGD', 'currency_symbol' => '$', 'phone_code' => 65]);
        $gatewayId = DB::table('payment_gateways')->insertGetId(['name' => 'omise', 'country_id' => $sg, 'created_at' => now(), 'updated_at' => now()]);
        $payNow = $this->method($gatewayId, 201, 'Paynow', 1);
        $this->method($gatewayId, 801, 'LifeSG', 2);
        $this->method($gatewayId, 211, 'Dana', 3);
        DB::table('payment_gateways')->where('id', $gatewayId)->update(['default_payment_method_id' => $payNow]);

        $this->himd = $this->operator('HIMD', $gatewayId);
        $this->hipl = $this->operator('HIPL', $gatewayId);

        // HIMD: a big board on 321, one on 320, a small board, a smart freezer and an inactive 321.
        $this->vend(2003, $this->himd, 321, 'ZC-328');
        $this->vend(2004, $this->himd, 320, 'ZC-328');
        $this->vend(2005, $this->himd, 16, 'ZC-83A');
        $this->vend(50001, $this->himd, 321, null, ['machine_type' => Vend::MACHINE_TYPE_SMART_FREEZER]);
        $this->vend(2006, $this->himd, 321, 'ZC-328', ['is_active' => 0]);
        // HIPL: a big board on 321 that must not be told about HIMD's change.
        $this->vend(1001, $this->hipl, 321, 'ZC-328');
    }

    private function method(int $gatewayId, int $code, string $merchant, int $sequence): int
    {
        $merchantId = DB::table('payment_merchants')->insertGetId([
            'name' => $merchant, 'image_url' => "https://example.test/{$code}.png", 'created_at' => now(), 'updated_at' => now(),
        ]);

        return DB::table('payment_methods')->insertGetId([
            'code' => $code, 'name' => $merchant, 'type_name' => 'paynow', 'sequence' => $sequence, 'is_active' => true,
            'payment_gateway_id' => $gatewayId, 'payment_merchant_id' => $merchantId, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function operator(string $code, int $gatewayId): Operator
    {
        $operator = Operator::withoutGlobalScopes()->create(['code' => $code, 'name' => $code, 'is_active' => true]);
        DB::table('operator_payment_gateways')->insert([
            'operator_id' => $operator->id, 'payment_gateway_id' => $gatewayId, 'key1' => 'pk', 'key2' => 'sk',
            // Outside production QR uses the sandbox keys, and only an operator with keys is served wallets
            // (OperatorPaymentMenu::operatorTakesQr).
            'type' => 'sandbox', 'created_at' => now(), 'updated_at' => now(),
        ]);

        return $operator;
    }

    private function vend(int $code, Operator $operator, int $apk, ?string $deviceType, array $attributes = []): void
    {
        DB::table('vends')->insert(array_merge([
            'code' => $code, 'name' => "Machine {$code}", 'operator_id' => $operator->id, 'is_active' => 1, 'is_testing' => 0,
            'machine_type' => Vend::MACHINE_TYPE_VENDING_MACHINE, 'private_key' => 'TESTKEY000000001',
            'apk_ver_json' => $deviceType ? json_encode(['apkver' => (string) $apk, 'deviceType' => $deviceType]) : null,
            'apk_version_code' => $deviceType ? null : $apk,
            'created_at' => now(), 'updated_at' => now(),
        ], $attributes));
    }

    private function admin(): User
    {
        $user = User::factory()->create(['operator_id' => 1]);
        $user->givePermissionTo(Permission::findOrCreate('read operators', 'web'), Permission::findOrCreate('update operators', 'web'));

        return $user;
    }

    private function hide(Operator $operator, int $code)
    {
        return $this->actingAs($this->admin())->postJson("/operators/{$operator->id}/payment-menu", [
            'payment_method_id' => PaymentMethod::where('code', $code)->value('id'), 'is_shown' => false,
        ]);
    }

    /** @return array<int, string> machine code => frame Type, for every frame published */
    private function framesSent(): array
    {
        $frames = [];
        foreach (Queue::pushed(PublishMqtt::class) as $job) {
            [$topic, $wire] = (fn () => [$this->topic, $this->message])->call($job);
            [, , $b64] = explode(',', $wire);
            $frames[(int) substr($topic, 2)] = json_decode(base64_decode($b64), true)['Type'];
        }
        ksort($frames);

        return $frames;
    }

    private function state(int $code): array
    {
        return collect(app(OperatorPaymentMenu::class)->machinesFor($this->himd))->firstWhere('code', $code);
    }

    public function test_a_save_queues_one_debounced_push_for_that_operator(): void
    {
        Queue::fake();

        $this->hide($this->himd, 211)->assertOk();
        $this->hide($this->himd, 801)->assertOk();

        Queue::assertPushed(PushPaymentMenu::class, 1);
        Queue::assertPushed(PushPaymentMenu::class, fn ($job) => $job->delay !== null);
    }

    public function test_the_push_reaches_only_that_operators_big_boards_on_321_and_stamps_them(): void
    {
        Queue::fake();

        (new PushPaymentMenu($this->himd->id))->handle(app(VendJobService::class));

        $this->assertSame([2003 => 'PAYMENTMENU'], $this->framesSent());
        $this->assertNotNull(DB::table('vends')->where('code', 2003)->value('payment_menu_pushed_at'));
        $this->assertNull(DB::table('vends')->where('code', 2004)->value('payment_menu_pushed_at'));
    }

    public function test_a_payment_method_edit_pushes_to_every_operators_machines(): void
    {
        Queue::fake();

        PaymentMethod::where('code', 801)->first()->update(['sequence' => 9]);

        $jobs = Queue::pushed(PushPaymentMenu::class);
        $this->assertCount(1, $jobs);
        $this->assertNull((fn () => $this->operatorId)->call($jobs->first()));

        $jobs->first()->handle(app(VendJobService::class));
        $this->assertSame([1001 => 'PAYMENTMENU', 2003 => 'PAYMENTMENU'], $this->framesSent());
    }

    public function test_the_switch_off_sends_nothing(): void
    {
        config(['payment.menu_push' => false]);
        Queue::fake();

        $this->hide($this->himd, 211)->assertOk();

        Queue::assertNotPushed(PushPaymentMenu::class);
    }

    public function test_a_machines_fetch_is_recorded_and_judged_against_the_menu_now(): void
    {
        Queue::fake();
        $this->assertSame('unknown', $this->state(2003)['state']);

        $this->getJson(self::MENU.'?machineID=2003')->assertOk();
        $this->assertSame('current', $this->state(2003)['state']);
        $this->assertSame('SG/omise', DB::table('vends')->where('code', 2003)->value('payment_menu_path'));

        // The operator hides Dana: the machine still holds the old menu until it fetches again.
        $this->hide($this->himd, 211)->assertOk()->assertJsonPath('paymentMenuMachines.0.state', 'stale');
        $this->assertSame('stale', $this->state(2003)['state']);

        $codes = collect($this->getJson(self::MENU.'?machineID=2003')->assertOk()->json())->pluck('code')->all();
        $this->assertSame([201, 801], $codes);
        $this->assertSame('current', $this->state(2003)['state']);
    }

    public function test_a_fetch_never_touches_the_machines_updated_at(): void
    {
        $before = DB::table('vends')->where('code', 2003)->value('updated_at');
        $this->travel(5)->minutes();

        $this->getJson(self::MENU.'?machineID=2003')->assertOk();

        $this->assertSame($before, DB::table('vends')->where('code', 2003)->value('updated_at'));
    }

    public function test_the_machine_list_says_which_update_at_once(): void
    {
        $machines = collect(app(OperatorPaymentMenu::class)->machinesFor($this->himd))->keyBy('code');

        // Vending machines only (no freezer), active or testing only (no 2006).
        $this->assertSame([2003, 2004, 2005], $machines->keys()->all());
        $this->assertTrue($machines[2003]['instant']);
        $this->assertFalse($machines[2004]['instant']);
        $this->assertFalse($machines[2005]['instant']);
    }

    public function test_the_page_endpoints_poll_and_resend_within_the_viewers_operator(): void
    {
        Queue::fake();
        $admin = $this->admin();

        $this->actingAs($admin)->getJson("/operators/{$this->himd->id}/payment-menu/machines")
            ->assertOk()->assertJsonCount(3, 'paymentMenuMachines');
        $this->actingAs($admin)->postJson("/operators/{$this->himd->id}/payment-menu/push")->assertOk();
        Queue::assertPushed(PushPaymentMenu::class, 1);

        $other = User::factory()->create(['operator_id' => $this->hipl->id]);
        $other->givePermissionTo(Permission::findOrCreate('read operators', 'web'), Permission::findOrCreate('update operators', 'web'));
        $this->actingAs($other)->getJson("/operators/{$this->himd->id}/payment-menu/machines")->assertForbidden();
        $this->actingAs($other)->postJson("/operators/{$this->himd->id}/payment-menu/push")->assertForbidden();
    }
}
