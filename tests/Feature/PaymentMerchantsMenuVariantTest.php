<?php

namespace Tests\Feature;

use App\Models\Operator;
use App\Models\PaymentMethod;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * /api/v1/payment-merchants/{country}/{gateway}/{variant?} (Brian, 2026-10-29): the plain URL — what
 * the fleet's mqtt-config rows point at — must not change; a variant tail adds that variant's methods.
 * `?machineID=` (appended by mqtt-config) picks the variant from the machine's operator instead.
 */
class PaymentMerchantsMenuVariantTest extends TestCase
{
    use RefreshDatabase;

    private int $gatewayId;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();

        $countryId = DB::table('countries')->insertGetId([
            'name' => 'Singapore', 'code' => 'SG', 'currency_name' => 'SGD', 'currency_symbol' => '$', 'phone_code' => 65,
        ]);
        $this->gatewayId = DB::table('payment_gateways')->insertGetId([
            'name' => 'omise', 'country_id' => $countryId, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->method(201, 'PayNow', 'paynow', 1);
        $this->method(801, 'LifeSG', 'paynow', 2);
        $this->method(802, 'Ace Credits & Apex Credits', 'paynow', 0, 'mindef');
    }

    private function method(int $code, string $merchant, string $slug, int $sequence, ?string $variant = null): void
    {
        $merchantId = DB::table('payment_merchants')->insertGetId([
            'name' => $merchant, 'image_url' => "https://example.test/{$code}.png", 'created_at' => now(), 'updated_at' => now(),
        ]);
        PaymentMethod::create([
            'code' => $code, 'name' => $merchant, 'type_name' => $slug, 'menu_variant' => $variant, 'sequence' => $sequence,
            'is_active' => true, 'payment_gateway_id' => $this->gatewayId, 'payment_merchant_id' => $merchantId,
        ]);
    }

    public function test_the_plain_url_never_lists_a_variant_method(): void
    {
        $this->getJson('/api/v1/payment-merchants/SG/omise')
            ->assertOk()
            ->assertJsonCount(2)
            ->assertJsonPath('0.code', 201)
            ->assertJsonPath('1.code', 801);
    }

    public function test_the_mindef_tail_adds_its_method_to_the_normal_menu(): void
    {
        $this->getJson('/api/v1/payment-merchants/SG/omise/mindef')
            ->assertOk()
            ->assertJsonCount(3)
            ->assertJsonPath('0.name', 'Ace Credits & Apex Credits')
            ->assertJsonPath('0.slug', 'paynow')
            ->assertJsonPath('0.code', 802)
            ->assertJsonPath('1.code', 201)
            ->assertJsonPath('2.code', 801);

        // Case-insensitive, and an unknown variant is simply the normal menu.
        $this->getJson('/api/v1/payment-merchants/SG/omise/MINDEF')->assertJsonCount(3);
        $this->getJson('/api/v1/payment-merchants/SG/omise/army')->assertOk()->assertJsonCount(2);
    }

    public function test_the_cached_variant_menu_is_dropped_when_a_method_changes(): void
    {
        $this->getJson('/api/v1/payment-merchants/SG/omise/mindef')->assertJsonCount(3);
        $this->getJson('/api/v1/payment-merchants/SG/omise')->assertJsonCount(2);

        PaymentMethod::where('code', 802)->firstOrFail()->update(['is_active' => false]);

        $this->getJson('/api/v1/payment-merchants/SG/omise/mindef')->assertJsonCount(2);
        $this->getJson('/api/v1/payment-merchants/SG/omise')->assertJsonCount(2);
    }

    /**
     * An operator, and one machine of it (bare number, as boards report). A variant switches that variant's methods
     * on for the operator, as the 2026_10_29_220000 migration carried `operators.payment_menu_variant` over.
     */
    private function machine(int $code, ?string $variant, ?string $codePrefix = null, string $operatorCode = 'OPX'): int
    {
        $operator = Operator::withoutGlobalScopes()->firstOrCreate(['code' => $operatorCode], ['name' => $operatorCode, 'is_active' => true]);
        $this->switchOnVariant($operator->id, $variant);

        return DB::table('vends')->insertGetId([
            'code' => $code, 'code_prefix' => $codePrefix, 'name' => "Machine {$code}", 'operator_id' => $operator->id,
            'is_testing' => 0, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function switchOnVariant(int $operatorId, ?string $variant): void
    {
        if ($variant === null) {
            return;
        }
        foreach (PaymentMethod::where('menu_variant', $variant)->pluck('id') as $methodId) {
            DB::table('operator_payment_methods')->insertOrIgnore([
                'operator_id' => $operatorId, 'payment_method_id' => $methodId, 'is_shown' => true,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    public function test_the_machines_operator_picks_the_variant(): void
    {
        $this->machine(2788, 'mindef', operatorCode: 'HIMD');

        $this->getJson('/api/v1/payment-merchants/SG/omise?machineID=2788')
            ->assertOk()
            ->assertJsonCount(3)
            ->assertJsonPath('0.code', 802)
            ->assertJsonPath('1.code', 201)
            ->assertJsonPath('2.code', 801);
    }

    public function test_a_machine_of_an_operator_without_a_variant_gets_the_normal_menu(): void
    {
        $this->machine(1001, null, operatorCode: 'HIPL');
        $this->machine(1002, 'army', operatorCode: 'OTHER'); // a variant no method carries

        $this->getJson('/api/v1/payment-merchants/SG/omise?machineID=1001')->assertOk()->assertJsonCount(2);
        $this->getJson('/api/v1/payment-merchants/SG/omise?machineID=1002')->assertOk()->assertJsonCount(2);
    }

    public function test_a_board_never_loses_its_menu_over_a_bad_machine_id(): void
    {
        $this->machine(2788, 'mindef', operatorCode: 'HIMD');

        foreach (['', '9999', 'abc', '2788abc', '2788 OR 1=1', '-2788', '27.88', '1234567890123', 'C2788'] as $machineId) {
            $this->getJson('/api/v1/payment-merchants/SG/omise?machineID='.rawurlencode($machineId))
                ->assertOk()
                ->assertJsonCount(2);
        }
        // An array instead of a string.
        $this->getJson('/api/v1/payment-merchants/SG/omise?machineID[]=2788')->assertOk()->assertJsonCount(2);
    }

    public function test_only_the_bare_number_resolves_never_a_prefixed_chiller_with_the_same_number(): void
    {
        $this->machine(6002, 'mindef', codePrefix: 'C', operatorCode: 'HIMD');
        $this->getJson('/api/v1/payment-merchants/SG/omise?machineID=6002')->assertOk()->assertJsonCount(2);

        $this->machine(6002, null, operatorCode: 'HIPL');
        $this->getJson('/api/v1/payment-merchants/SG/omise?machineID=6002')->assertOk()->assertJsonCount(2);
    }

    public function test_the_url_tail_wins_over_the_operator(): void
    {
        $this->machine(1001, null, operatorCode: 'HIPL');
        $this->machine(2788, 'mindef', operatorCode: 'HIMD');

        // The rows already pointed at /mindef keep it, whatever the operator says.
        $this->getJson('/api/v1/payment-merchants/SG/omise/mindef?machineID=1001')->assertOk()->assertJsonCount(3);
        // An unknown tail is the normal menu, even for a MINDEF machine.
        $this->getJson('/api/v1/payment-merchants/SG/omise/army?machineID=2788')->assertOk()->assertJsonCount(2);
    }

    public function test_a_machine_moved_to_another_operator_follows_at_once(): void
    {
        $vendId = $this->machine(2788, null, operatorCode: 'HIPL');
        $this->getJson('/api/v1/payment-merchants/SG/omise?machineID=2788')->assertJsonCount(2);

        $himd = Operator::withoutGlobalScopes()->create(['code' => 'HIMD', 'name' => 'HI Mindef', 'is_active' => true]);
        $this->switchOnVariant($himd->id, 'mindef');
        DB::table('vends')->where('id', $vendId)->update(['operator_id' => $himd->id]);

        $this->getJson('/api/v1/payment-merchants/SG/omise?machineID=2788')->assertJsonCount(3)->assertJsonPath('0.code', 802);

        DB::table('vends')->where('id', $vendId)->update(['operator_id' => Operator::withoutGlobalScopes()->where('code', 'HIPL')->value('id')]);
        $this->getJson('/api/v1/payment-merchants/SG/omise?machineID=2788')->assertJsonCount(2);
    }

    public function test_an_inactive_operator_still_resolves(): void
    {
        $this->machine(2788, 'mindef', operatorCode: 'HIMD');
        DB::table('operators')->where('code', 'HIMD')->update(['is_active' => false]);

        $this->getJson('/api/v1/payment-merchants/SG/omise?machineID=2788')->assertJsonCount(3);
    }

    public function test_the_menu_payload_is_unchanged_by_a_machine_id(): void
    {
        $this->machine(1001, null, operatorCode: 'HIPL');

        $plain = $this->getJson('/api/v1/payment-merchants/SG/omise')->assertOk()->getContent();
        Cache::flush();
        $this->assertSame($plain, $this->getJson('/api/v1/payment-merchants/SG/omise?machineID=1001')->getContent());
        $this->assertSame($plain, $this->getJson('/api/v1/payment-merchants/SG/omise?machineID=garbage')->getContent());
    }
}
