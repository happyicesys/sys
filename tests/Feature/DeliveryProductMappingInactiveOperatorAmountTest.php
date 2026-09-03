<?php

namespace Tests\Feature;

use App\Models\DeliveryProductMapping;
use App\Models\DeliveryProductMappingVendChannel;
use App\Models\Operator;
use App\Support\OperatorScope;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Deactivating an operator must not break its own delivery mapping rows.
 *
 * Operator carries OperatorActiveScope (`where is_active = true`), so a
 * belongsTo(Operator::class) resolves to NULL the moment the operator is
 * deactivated - the row is still there, the scope just hides it. The
 * DeliveryProductMappingVendChannel `amount` mutator walks
 * ...->operator->country->currency_exponent to convert to integer cents, so a
 * null operator turned every write into a fatal:
 *
 *   ErrorException: Attempt to read property "country" on null
 *   in app/Models/DeliveryProductMappingVendChannel.php:51
 *
 * Seen in production 2026-09-04 on the `high` queue
 * (App\Jobs\SyncDeliveryProductMappingVendChannels, DeliveryProductMappingVend
 * 168) after operators 26/28/30/34 were deactivated on 2026-09-03 17:49.
 */
class DeliveryProductMappingInactiveOperatorAmountTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        OperatorScope::flush();
    }

    protected function tearDown(): void
    {
        OperatorScope::flush();
        parent::tearDown();
    }

    // ---------------------------------------------------------------- fixtures

    private function makeCountry(int $exponent = 2): int
    {
        return DB::table('countries')->insertGetId([
            'name' => 'Testland',
            'code' => 'TL',
            'currency_name' => 'Test Dollar',
            'currency_exponent' => $exponent,
            'currency_symbol' => '$',
            'phone_code' => 65,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function makeOperator(int $countryId, bool $isActive): int
    {
        return DB::table('operators')->insertGetId([
            'code' => $isActive ? 'ACTIVEOP' : 'DEADOP',
            'name' => $isActive ? 'Active Op' : 'Deactivated Op',
            'country_id' => $countryId,
            'is_active' => $isActive,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function makeMapping(int $operatorId): int
    {
        return DB::table('delivery_product_mappings')->insertGetId([
            'name' => 'DEA_TEST_V1.0',
            'operator_id' => $operatorId,
            'delivery_platform_operator_id' => 1,
            'product_mapping_id' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function makeMappingVend(int $mappingId): int
    {
        return DB::table('delivery_product_mapping_vend')->insertGetId([
            'delivery_product_mapping_id' => $mappingId,
            'vend_id' => 4242,
            'end_date' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function makeChannel(int $mappingVendId, int $mappingId, int $amountInCents = 0): int
    {
        return DB::table('delivery_product_mapping_vend_channels')->insertGetId([
            'delivery_product_mapping_vend_id' => $mappingVendId,
            'delivery_product_mapping_id' => $mappingId,
            'vend_channel_id' => 777,
            'vend_channel_code' => '11',
            'vend_id' => 4242,
            'vend_code' => '4242',
            'amount' => $amountInCents,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /** @return array{0:int,1:int} [mapping id, channel id] */
    private function scenario(bool $operatorActive, int $exponent = 2): array
    {
        $operatorId = $this->makeOperator($this->makeCountry($exponent), $operatorActive);
        $mappingId = $this->makeMapping($operatorId);
        $channelId = $this->makeChannel($this->makeMappingVend($mappingId), $mappingId);

        return [$mappingId, $channelId];
    }

    // -------------------------------------------------------------------- tests

    /** The scope is a listing filter; it must not sever a mapping from its owner. */
    public function test_mapping_still_resolves_its_operator_when_that_operator_is_deactivated(): void
    {
        [$mappingId] = $this->scenario(operatorActive: false);

        $operator = DeliveryProductMapping::findOrFail($mappingId)->operator;

        $this->assertNotNull($operator, 'operator was hidden by OperatorActiveScope');
        $this->assertSame('DEADOP', $operator->code);
        $this->assertNotNull($operator->country, 'country chain broken');
    }

    /** OperatorActiveScope must still hide a deactivated operator from a plain query. */
    public function test_operator_active_scope_still_applies_to_direct_queries(): void
    {
        $this->scenario(operatorActive: false);

        $this->assertSame(0, Operator::query()->count());
        $this->assertSame(1, Operator::withoutGlobalScopes()->count());
    }

    /** The regression itself: the write that fataled on the `high` queue. */
    public function test_amount_can_be_written_when_the_operator_is_deactivated(): void
    {
        [, $channelId] = $this->scenario(operatorActive: false);

        DeliveryProductMappingVendChannel::findOrFail($channelId)->update(['amount' => 1.50]);

        $this->assertSame(150, (int) DB::table('delivery_product_mapping_vend_channels')
            ->where('id', $channelId)->value('amount'));
    }

    public function test_amount_round_trips_for_an_active_operator(): void
    {
        [, $channelId] = $this->scenario(operatorActive: true);

        $channel = DeliveryProductMappingVendChannel::findOrFail($channelId);
        $channel->update(['amount' => 2.35]);

        $this->assertSame(235, (int) DB::table('delivery_product_mapping_vend_channels')
            ->where('id', $channelId)->value('amount'));
        $this->assertEquals(2.35, DeliveryProductMappingVendChannel::findOrFail($channelId)->amount);
    }

    /** A non-2 exponent must follow the country, not the hardcoded 100 fallback. */
    public function test_amount_honours_a_non_two_currency_exponent(): void
    {
        [, $channelId] = $this->scenario(operatorActive: false, exponent: 3);

        DeliveryProductMappingVendChannel::findOrFail($channelId)->update(['amount' => 1.50]);

        $this->assertSame(1500, (int) DB::table('delivery_product_mapping_vend_channels')
            ->where('id', $channelId)->value('amount'));
    }

    /** An orphan row (no mapping vend yet) falls back to 100 instead of fataling. */
    public function test_amount_falls_back_to_cents_when_the_chain_is_missing(): void
    {
        $channel = new DeliveryProductMappingVendChannel;
        $channel->amount = 1.50;

        $this->assertSame(150, (int) $channel->getAttributes()['amount']);
    }
}
