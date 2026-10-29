<?php

namespace Tests\Feature;

use App\Models\PaymentMethod;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * /api/v1/payment-merchants/{country}/{gateway}/{variant?} (Brian, 2026-10-29): the plain URL — what
 * the fleet's mqtt-config rows point at — must not change; a variant tail adds that variant's methods.
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
}
