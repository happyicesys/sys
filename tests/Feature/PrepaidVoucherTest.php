<?php

namespace Tests\Feature;

use App\Http\Middleware\VerifyDeviceSignature;
use App\Jobs\PublishMqtt;
use App\Models\Customer;
use App\Models\Operator;
use App\Models\PaymentGatewayLog;
use App\Models\PaymentMethod;
use App\Models\PrepaidVoucher;
use App\Models\PrepaidVoucherCampaign;
use App\Models\Product;
use App\Models\ProductMapping;
use App\Models\ProductMappingItem;
use App\Models\SellingPrice;
use App\Models\User;
use App\Models\Vend;
use App\Models\VendChannelError;
use App\Models\VendTransaction;
use App\Services\PrepaidVoucher\PrepaidVoucherExpiry;
use App\Services\PrepaidVoucher\PrepaidVoucherPhone;
use App\Services\PrepaidVoucher\PrepaidVoucherProgram;
use App\Services\PrepaidVoucher\PrepaidVoucherSales;
use App\Services\VendTransactionService;
use Database\Seeders\RolePermissionSyncSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Next-visit vouchers (Brian, 2026-11-20; apk/mark1-apk/UNRELEASED_V329.md): the price and expiry rules, a
 * voucher bought by card (its TRADE) and by QR (the approval, before any TRADE), lookup / reserve / release
 * over the signed machine API, settling from the redemption TRADE, the Pause, the settings page, and the
 * number erased after the retention period.
 */
class PrepaidVoucherTest extends TestCase
{
    use RefreshDatabase;

    private ?ProductMapping $mapping = null;

    /** @var array<string, Product> */
    private array $products = [];

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        Carbon::setTestNow(Carbon::parse('2026-11-20 14:00:00'));
        PaymentMethod::firstOrCreate(['code' => 1], ['name' => 'Card Terminal', 'is_active' => true]);
        PaymentMethod::firstOrCreate(['code' => 10], ['name' => 'Free Vend', 'is_active' => true]);
        VendChannelError::firstOrCreate(['code' => 0], ['desc' => 'No Malfunction (0)']);
        VendChannelError::firstOrCreate(['code' => 7], ['desc' => 'Drop sensor (7)']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function operator(int $id, string $code, string $tz = 'Asia/Singapore'): Operator
    {
        $country = DB::table('countries')->where('phone_code', '65')->value('id')
            ?? DB::table('countries')->insertGetId(['name' => 'Singapore', 'code' => 'SG', 'phone_code' => '65', 'currency_name' => 'SGD', 'currency_symbol' => '$', 'currency_exponent' => 2]);
        $op = Operator::withoutGlobalScopes()->find($id) ?? new Operator;
        $op->forceFill(['id' => $id, 'code' => $code, 'name' => $code, 'timezone' => $tz, 'country_id' => $country, 'is_active' => 1])->save();

        return $op;
    }

    private function vending(int $code, int $operatorId = 1, int $apk = 329, string $deviceType = 'ZC-328'): Vend
    {
        $customer = Customer::create([
            'name' => "Site {$code}", 'code' => "S{$code}", 'operator_id' => $operatorId,
            'status_id' => Customer::STATUS_ACTIVE, 'selling_price_type' => SellingPrice::TYPE_2,
        ]);
        $this->mapping ??= ProductMapping::create([
            'name' => 'Vending', 'machine_type' => Vend::MACHINE_TYPE_VENDING_MACHINE, 'is_active' => true, 'operator_id' => 1,
        ]);
        $vend = new Vend;
        $vend->forceFill([
            'code' => $code, 'machine_type' => Vend::MACHINE_TYPE_VENDING_MACHINE, 'is_active' => 1, 'operator_id' => $operatorId,
            'vend_model_id' => 1, 'product_mapping_id' => $this->mapping->id, 'customer_id' => $customer->id,
            'apk_ver_json' => ['apkver' => $apk, 'buildtime' => '2026-11-20', 'deviceType' => $deviceType],
            'private_key' => '123456789110138',
        ])->save();
        foreach ([11 => ['A', 180], 21 => ['B', 250], 31 => ['C', 120]] as $channel => [$productCode, $cents]) {
            $product = $this->products[$productCode] ??= Product::forceCreate(['code' => $productCode, 'name' => "Treat {$productCode}", 'operator_id' => 1]);
            if (! ProductMappingItem::where('product_mapping_id', $this->mapping->id)->where('channel_code', (string) $channel)->exists()) {
                ProductMappingItem::create(['product_mapping_id' => $this->mapping->id, 'channel_code' => (string) $channel, 'product_id' => $product->id, 'sequence' => $channel]);
            }
            DB::table('vend_channels')->insert([
                'vend_id' => $vend->id, 'code' => $channel, 'product_id' => $product->id, 'amount' => $cents,
                'qty' => 5, 'capacity' => 10, 'is_active' => 1,
            ]);
        }

        return $vend->fresh();
    }

    private function campaign(int $operatorId = 1, array $overrides = []): PrepaidVoucherCampaign
    {
        return PrepaidVoucherCampaign::create(array_merge([
            'name' => 'Next visit', 'operator_id' => $operatorId, 'is_active' => true, 'is_redeem_enabled' => true,
            'discount_pct' => 10, 'price_step_cents' => 10, 'expiry_days' => 1, 'offer_seconds' => 20,
        ], $overrides));
    }

    private function trade(Vend $vend, string $orderId, array $extra): ?VendTransaction
    {
        app(VendTransactionService::class)->create($vend->fresh(), array_merge([
            'ORDRID' => $orderId, 'PAY_TYPE' => 1, 'TIME' => now()->format('Y-m-d H:i:s'), 'SErr' => 0, 'SId' => 11,
            'Price' => '1.70', 'TXN_SRC' => 1,
            'transf_info' => [['SId' => 11, 'SErr' => 0, 'Price' => 170]],
        ], $extra));

        return VendTransaction::withoutGlobalScopes()->where('order_id', $orderId)->first();
    }

    private function signed(string $method, Vend $vend, string $path, array $body = [])
    {
        $raw = $body === [] ? '' : json_encode($body);
        $ts = (string) time();
        // The board signs with mqtt-config's client id + "A"; mark1 stores the id without it.
        $sig = VerifyDeviceSignature::sign('123456789110138A', $method, $path, $ts, $raw);

        return $this->call($method, $path, [], [], [], [
            'HTTP_X_DEVICE_TIMESTAMP' => $ts,
            'HTTP_X_DEVICE_SIGNATURE' => $sig,
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
        ], $raw);
    }

    private function voucher(Vend $vend, array $overrides = []): PrepaidVoucher
    {
        return PrepaidVoucher::create(array_merge([
            'operator_id' => 1, 'phone' => '+6591234567', 'product_id' => $this->products['A']->id,
            'list_price_cents' => 180, 'price_paid_cents' => 170, 'discount_pct' => 10, 'pay_rail' => 'card',
            'bought_vend_id' => $vend->id, 'bought_order_id' => 'X'.uniqid(), 'issued_at' => now(),
            'expires_at' => now()->addDays(3), 'status' => 'active',
        ], $overrides));
    }

    public function test_price_rounds_up_to_the_step_and_expiry_is_the_end_of_a_local_day(): void
    {
        $this->assertSame(170, PrepaidVoucherProgram::voucherPrice(180, 10, 10)); // 1.62 -> 1.70
        $this->assertSame(250, PrepaidVoucherProgram::voucherPrice(250, 0, 10));
        $this->assertSame(200, PrepaidVoucherProgram::voucherPrice(250, 20, 50));
        $this->assertSame(120, PrepaidVoucherProgram::voucherPrice(120, 1, 100)); // never above the list price

        // Bought 23:30 Singapore (15:30 UTC app time on a UTC app): 1 day = the end of TOMORROW, Singapore.
        config(['app.timezone' => 'Asia/Singapore']);
        $issued = Carbon::parse('2026-11-20 23:30:00', 'Asia/Singapore');
        $this->assertSame('2026-11-21 23:59:59', PrepaidVoucherExpiry::expiresAt($issued, 1, 'Asia/Singapore')->format('Y-m-d H:i:s'));
        // A Bangkok machine's day ends an hour later in Singapore time.
        $this->assertSame('2026-11-22 00:59:59', PrepaidVoucherExpiry::expiresAt($issued, 1, 'Asia/Bangkok')->format('Y-m-d H:i:s'));
    }

    public function test_phone_numbers_follow_the_country_rule(): void
    {
        $this->assertSame('+6591234567', PrepaidVoucherPhone::normalise('9123 4567', '65'));
        $this->assertSame('+6591234567', PrepaidVoucherPhone::normalise('+65 91234567', '65'));
        $this->assertNull(PrepaidVoucherPhone::normalise('61234567', '65'));
        $this->assertNull(PrepaidVoucherPhone::normalise('912345', '65'));
        $this->assertSame('+60123456789', PrepaidVoucherPhone::normalise('0123456789', '60'));
        $this->assertSame(['country_code' => '65', 'min_digits' => 8, 'max_digits' => 8, 'first_digits' => ['8', '9']], PrepaidVoucherPhone::rule('65'));
    }

    public function test_a_card_purchase_trade_issues_the_voucher_once_and_carries_no_cost(): void
    {
        $this->operator(1, 'HIPL');
        $vend = $this->vending(2245);
        $this->campaign();

        $pv = ['role' => 'buy', 'phone' => '91234567', 'pid' => $this->products['A']->id, 'list' => 180, 'first' => '26112014000012245'];
        $sale = $this->trade($vend, '26112014000112245', ['PV' => $pv]);
        // A replay of the same frame (TradeOutbox) changes nothing.
        $this->trade($vend, '26112014000112245', ['PV' => $pv]);

        $this->assertSame(1, PrepaidVoucher::count());
        $v = PrepaidVoucher::firstOrFail();
        $this->assertSame('+6591234567', $v->phone);
        $this->assertSame(170, $v->price_paid_cents);
        $this->assertSame(180, $v->list_price_cents);
        $this->assertSame('card', $v->pay_rail);
        $this->assertSame($sale->id, (int) $v->bought_vend_transaction_id);
        $this->assertSame('26112014000012245', $v->first_order_id);
        // Bought 2026-11-20 (app tz) with 1 day: the end of 21 Nov, Singapore.
        $this->assertSame('2026-11-21 23:59:59', $v->expires_at->copy()->setTimezone('Asia/Singapore')->format('Y-m-d H:i:s'));

        $sale->refresh();
        $this->assertSame('purchase', $sale->meta_json['prepaid_voucher']['role']);
        $this->assertSame($v->id, $sale->meta_json['prepaid_voucher']['id']);
        $this->assertSame(0, (int) $sale->unit_cost);
        $this->assertSame(170, (int) $sale->amount); // the purchase IS the sale (Brian, 2026-11-20)
    }

    public function test_a_qr_purchase_is_issued_at_approval_and_its_dispense_frame_drops_nothing(): void
    {
        $this->operator(1, 'HIPL');
        $vend = $this->vending(2245);
        $this->campaign();
        $log = PaymentGatewayLog::forceCreate([
            'order_id' => '20261120140000002245', 'vend_id' => $vend->id, 'vend_code' => (string) $vend->code,
            'amount' => 1.70, 'status' => PaymentGatewayLog::STATUS_APPROVE,
            'request' => ['PRICE' => '1.70', 'SId' => 11, 'PV' => ['role' => 'buy', 'phone' => '91234567', 'pid' => $this->products['A']->id, 'list' => 180, 'first' => 'HELD1']],
        ]);

        $this->assertTrue(PrepaidVoucherSales::isPurchaseRequest($log));
        $v = app(PrepaidVoucherSales::class)->fromGatewayApproval($log);
        $this->assertNotNull($v);
        $this->assertSame(170, $v->price_paid_cents);
        $this->assertSame('qr', $v->pay_rail);
        $this->assertSame($log->id, (int) $v->payment_gateway_log_id);

        // The machine's purchase TRADE later adds the sale row to the same voucher, never a second one.
        $sale = $this->trade($vend, '20261120140000002245', ['PAY_TYPE' => 0, 'PV' => ['role' => 'buy', 'phone' => '91234567', 'pid' => $this->products['A']->id, 'list' => 180]]);
        $this->assertSame(1, PrepaidVoucher::count());
        $this->assertSame($sale->id, (int) $v->fresh()->bought_vend_transaction_id);

        // Delivered: the 10-minute "paid, never dispensed" sweep must not refund it.
        $this->assertTrue((bool) $log->fresh()->is_dispensed);
        // Refunded later (dispute, dashboard): the unused voucher is void.
        app(\App\Services\Refund\OmiseRefundRecorder::class)->record($log->fresh(), null, \App\Support\AutoRefundSource::OMISE_EXTERNAL);
        $this->assertSame('voided', $v->fresh()->status);

        $plain = PaymentGatewayLog::forceCreate(['order_id' => 'P1', 'vend_id' => $vend->id, 'vend_code' => '2245', 'amount' => 1.8, 'status' => 2, 'request' => ['PRICE' => '1.80', 'SId' => 11]]);
        $this->assertFalse(PrepaidVoucherSales::isPurchaseRequest($plain));
        $this->assertNull(app(PrepaidVoucherSales::class)->fromGatewayApproval($plain));
    }

    public function test_the_qr_dispense_frame_of_a_voucher_purchase_is_marked_pv(): void
    {
        $this->operator(1, 'HIPL');
        $vend = $this->vending(2245);
        $gatewayId = DB::table('payment_gateways')->insertGetId(['name' => 'omise', 'created_at' => now(), 'updated_at' => now()]);
        $make = fn (string $orderId, array $request) => PaymentGatewayLog::forceCreate([
            'order_id' => $orderId, 'vend_id' => $vend->id, 'vend_code' => '2245', 'amount' => 1.70, 'method' => 'paynow',
            'status' => PaymentGatewayLog::STATUS_APPROVE, 'payment_gateway_id' => $gatewayId, 'request' => $request,
            'vend_channels_json' => [['code' => 11, 'product' => ['id' => $this->products['A']->id, 'name' => 'Treat A']]],
        ]);
        $controller = app(\App\Http\Controllers\PaymentController::class);
        $process = new \ReflectionMethod($controller, 'processPayment');
        $process->invoke($controller, $make('Q1', ['PRICE' => '1.70', 'SId' => 11, 'PV' => ['role' => 'buy', 'phone' => '91234567', 'pid' => 1, 'list' => 180]]));
        $process->invoke($controller, $make('Q2', ['PRICE' => '1.80', 'SId' => 11]));

        $results = [];
        Queue::assertPushed(\App\Jobs\PublishDispenseMqttLoop::class, function ($job) use (&$results) {
            $data = (new \ReflectionProperty($job, 'dataArr'))->getValue($job);
            $results[$data['result']['orderid']] = $data['result']['pv'] ?? null;

            return true;
        });
        $this->assertSame(['Q1' => 1, 'Q2' => null], $results);
    }

    public function test_machine_config_follows_the_pause_the_board_and_the_group(): void
    {
        $this->operator(1, 'HIPL');
        $lea = $this->operator(7, 'LEA');
        $other = $this->operator(9, 'XYZ');
        $hipl = $this->vending(2245);
        $leaVend = $this->vending(2300, $lea->id);
        $old = $this->vending(2301, 1, 328);
        $small = $this->vending(2302, 1, 329, 'ZC-83A');
        $outsider = $this->vending(2303, $other->id);
        $campaign = $this->campaign(1, ['excluded_product_ids' => [$this->products['C']->id]]);

        $cfg = $this->signed('GET', $hipl, '/api/v1/vends/2245/prepaid-voucher')->assertOk()->json();
        $this->assertTrue($cfg['offer']);
        $this->assertTrue($cfg['redeem']);
        $this->assertSame(10, $cfg['discount_pct']);
        $this->assertTrue($cfg['card']);
        $this->assertSame([$this->products['C']->id], $cfg['excluded_product_ids']);
        $this->assertSame('65', $cfg['phone']['country_code']);

        // An HIPL-group machine takes HIPL's settings; an outsider has none.
        $this->assertTrue($this->signed('GET', $leaVend, '/api/v1/vends/2300/prepaid-voucher')->json('offer'));
        $this->assertFalse($this->signed('GET', $outsider, '/api/v1/vends/2303/prepaid-voucher')->json('redeem'));
        $this->assertFalse($this->signed('GET', $old, '/api/v1/vends/2301/prepaid-voucher')->json('offer'));
        $this->assertFalse($this->signed('GET', $small, '/api/v1/vends/2302/prepaid-voucher')->json('redeem'));

        // Pause: no new offers, bought vouchers still redeem. Excluding a machine stops its offer only.
        $campaign->update(['is_active' => false]);
        $cfg = $this->signed('GET', $hipl, '/api/v1/vends/2245/prepaid-voucher')->json();
        $this->assertFalse($cfg['offer']);
        $this->assertTrue($cfg['redeem']);
        $campaign->update(['is_active' => true, 'excluded_vend_ids' => [$hipl->id]]);
        $cfg = $this->signed('GET', $hipl, '/api/v1/vends/2245/prepaid-voucher')->json();
        $this->assertFalse($cfg['offer']);
        $this->assertTrue($cfg['redeem']);

        // Unsigned calls are refused.
        $this->getJson('/api/v1/vends/2245/prepaid-voucher')->assertStatus(401);
    }

    public function test_lookup_reserve_and_release_hold_one_voucher_for_one_machine(): void
    {
        $this->operator(1, 'HIPL');
        $lea = $this->operator(7, 'LEA');
        $other = $this->operator(9, 'XYZ');
        $a = $this->vending(2245);
        $b = $this->vending(2300, $lea->id);
        $outsider = $this->vending(2303, $other->id);
        $this->campaign();
        $this->campaign($other->id);
        $mine = $this->voucher($a);
        $this->voucher($a, ['expires_at' => now()->subMinute()]); // expired
        $this->voucher($a, ['phone' => '+6598765432']); // someone else
        $this->voucher($a, ['status' => 'redeemed']);

        $list = $this->signed('POST', $b, '/api/v1/vends/2300/prepaid-vouchers/lookup', ['phone' => '91234567'])->assertOk()->json('vouchers');
        $this->assertSame([$mine->id], array_column($list, 'id'));
        $this->assertSame(170, $list[0]['value_cents']);
        $this->assertArrayNotHasKey('phone', $list[0]);
        // Another operator's machine cannot see HIPL's vouchers.
        $this->assertSame([], $this->signed('POST', $outsider, '/api/v1/vends/2303/prepaid-vouchers/lookup', ['phone' => '91234567'])->json('vouchers'));
        $this->signed('POST', $b, '/api/v1/vends/2300/prepaid-vouchers/lookup', ['phone' => '1234'])->assertStatus(422)->assertJson(['error' => 'invalid_phone']);

        $token = $this->signed('POST', $b, "/api/v1/vends/2300/prepaid-vouchers/{$mine->id}/reserve", ['phone' => '91234567'])->assertOk()->json('token');
        $this->assertNotEmpty($token);
        // A second machine (or a second tap) cannot take it while it is held.
        $this->signed('POST', $a, "/api/v1/vends/2245/prepaid-vouchers/{$mine->id}/reserve", ['phone' => '91234567'])->assertStatus(409);
        $this->assertSame([], $this->signed('POST', $a, '/api/v1/vends/2245/prepaid-vouchers/lookup', ['phone' => '91234567'])->json('vouchers'));
        // The wrong number cannot reserve it either.
        $this->signed('POST', $a, "/api/v1/vends/2245/prepaid-vouchers/{$mine->id}/reserve", ['phone' => '98765432'])->assertStatus(409);

        $this->signed('POST', $b, "/api/v1/vends/2300/prepaid-vouchers/{$mine->id}/release", ['token' => $token])->assertOk()->assertJson(['ok' => true]);
        $this->assertSame('active', $mine->fresh()->status);

        // A hold that lapses gives the voucher back without anyone releasing it.
        $this->signed('POST', $b, "/api/v1/vends/2300/prepaid-vouchers/{$mine->id}/reserve", ['phone' => '91234567'])->assertOk();
        Carbon::setTestNow(now()->addMinutes(16));
        $this->signed('POST', $a, "/api/v1/vends/2245/prepaid-vouchers/{$mine->id}/reserve", ['phone' => '91234567'])->assertOk();
    }

    public function test_a_redemption_trade_spends_the_voucher_or_gives_it_back_when_nothing_dropped(): void
    {
        $this->operator(1, 'HIPL');
        $vend = $this->vending(2245);
        $this->campaign();
        $same = $this->voucher($vend, ['status' => 'reserved', 'reserved_vend_id' => $vend->id, 'reserved_token' => 'T1', 'reserved_until' => now()->addMinutes(10)]);
        $value = $this->voucher($vend);
        $failed = $this->voucher($vend, ['status' => 'reserved', 'reserved_vend_id' => $vend->id, 'reserved_token' => 'T3', 'reserved_until' => now()->addMinutes(10)]);

        // Same SKU: $0, Free Vend.
        $sale = $this->trade($vend, 'R1', ['PAY_TYPE' => 10, 'Price' => '0.00', 'transf_info' => [['SId' => 11, 'SErr' => 0, 'Price' => 0]],
            'PV' => ['role' => 'redeem', 'id' => $same->id, 'token' => 'T1', 'mode' => 'sku', 'used' => 170, 'lost' => 0, 'balance' => 0]]);
        $same->refresh();
        $this->assertSame('redeemed', $same->status);
        $this->assertSame('sku', $same->redeemed_mode);
        $this->assertSame($sale->id, (int) $same->redeemed_vend_transaction_id);
        $this->assertSame(0, (int) $sale->amount);
        $this->assertSame('redemption', $sale->fresh()->meta_json['prepaid_voucher']['role']);

        // Value towards a dearer item: the balance is the sale's amount. A lapsed or missing hold still settles.
        $this->trade($vend, 'R2', ['SId' => 21, 'Price' => '0.80', 'transf_info' => [['SId' => 21, 'SErr' => 0, 'Price' => 80]],
            'PV' => ['role' => 'redeem', 'id' => $value->id, 'mode' => 'value', 'used' => 170, 'lost' => 0, 'balance' => 80]]);
        $value->refresh();
        $this->assertSame('redeemed', $value->status);
        $this->assertSame('value', $value->redeemed_mode);
        $this->assertSame(170, $value->value_used_cents);
        $this->assertSame(80, $value->balance_paid_cents);
        $this->assertSame($this->products['B']->id, (int) $value->redeemed_product_id);

        // The item did not drop: the customer keeps the voucher.
        $this->trade($vend, 'R3', ['PAY_TYPE' => 10, 'Price' => '0.00', 'SErr' => 7, 'transf_info' => [['SId' => 11, 'SErr' => 7, 'Price' => 0]],
            'PV' => ['role' => 'redeem', 'id' => $failed->id, 'token' => 'T3', 'mode' => 'sku']]);
        $this->assertSame('active', $failed->fresh()->status);

        // A second machine claiming an already-spent voucher is recorded as a conflict, the voucher untouched.
        $dup = $this->trade($vend, 'R4', ['PAY_TYPE' => 10, 'Price' => '0.00', 'transf_info' => [['SId' => 11, 'SErr' => 0, 'Price' => 0]],
            'PV' => ['role' => 'redeem', 'id' => $same->id, 'mode' => 'sku']]);
        $this->assertSame('conflict', $dup->fresh()->meta_json['prepaid_voucher']['outcome']);
        $this->assertSame('R1', $same->fresh()->redeemed_order_id);
    }

    public function test_the_settings_tab_saves_pauses_and_nudges_ready_machines(): void
    {
        $this->operator(1, 'HIPL');
        $this->seed(RolePermissionSyncSeeder::class);
        $ready = $this->vending(2245);
        $this->vending(2301, 1, 328);
        $user = User::factory()->create(['operator_id' => 1]);
        $user->assignRole('admin');
        $this->actingAs($user);

        $this->get('/prepaid-vouchers')->assertOk()
            ->assertInertia(fn ($page) => $page->component('PrepaidVoucher/Index')->where('campaign', null)->where('minVendingApkVersion', 329));

        $this->post('/prepaid-vouchers/settings', [
            'name' => 'Next visit', 'is_active' => true, 'is_redeem_enabled' => true, 'is_card_enabled' => true, 'discount_pct' => 15,
            'price_step_cents' => 10, 'expiry_days' => 7, 'offer_seconds' => 20,
            'excluded_product_ids' => [], 'excluded_vend_ids' => [],
        ])->assertRedirect();
        $this->assertSame(15, PrepaidVoucherCampaign::where('operator_id', 1)->value('discount_pct'));

        $frames = [];
        Queue::assertPushed(PublishMqtt::class, function (PublishMqtt $job) use (&$frames) {
            $frames[] = $job;

            return true;
        });
        $this->assertCount(1, $frames); // the 329 board only

        // A role without the permission is refused.
        $tech = User::factory()->create(['operator_id' => 1]);
        $tech->assignRole('technician');
        $this->actingAs($tech)->get('/prepaid-vouchers')->assertForbidden();
    }

    public function test_void_extend_and_the_number_is_erased_after_the_retention_period(): void
    {
        $this->operator(1, 'HIPL');
        $this->seed(RolePermissionSyncSeeder::class);
        $vend = $this->vending(2245);
        $user = User::factory()->create(['operator_id' => 1]);
        $user->assignRole('admin');
        $this->actingAs($user);

        $v = $this->voucher($vend);
        $this->post("/prepaid-vouchers/{$v->id}/extend", ['days' => 7])->assertRedirect();
        $this->assertTrue($v->fresh()->expires_at->gt(now()->addDays(9)));
        $this->post("/prepaid-vouchers/{$v->id}/void", ['reason' => 'Customer complaint'])->assertRedirect();
        $this->assertSame('voided', $v->fresh()->status);

        // Search by the full number only; the list shows it masked.
        $this->get('/prepaid-vouchers?phone=91234567')->assertInertia(fn ($page) => $page
            ->where('vouchers.data.0.id', $v->id)
            ->where('vouchers.data.0.phone', '+65 9123 ••67'));

        $old = $this->voucher($vend, ['status' => 'redeemed', 'redeemed_at' => now()->subDays(91)]);
        $fresh = $this->voucher($vend, ['status' => 'redeemed', 'redeemed_at' => now()->subDays(10)]);
        $this->artisan('prepaid-vouchers:prune-phones')->assertSuccessful();
        $this->assertNull($old->fresh()->phone);
        $this->assertNotNull($old->fresh()->phone_purged_at);
        $this->assertSame('+6591234567', $fresh->fresh()->phone);
    }
}
