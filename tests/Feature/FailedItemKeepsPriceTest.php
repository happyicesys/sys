<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\ProductMapping;
use App\Models\ProductMappingItem;
use App\Models\Vend;
use App\Models\VendChannelError;
use App\Models\VendTransaction;
use App\Services\VendTransactionService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * A line of a multiple purchase that failed to dispense keeps its price (Brian, 2026-10-21: "we already
 * receive the payment, so payment will not reduced because of error, since we can not do partial auto
 * refund"). Pinned: the ingest prices it like any line, the sold figures still leave it out by its error
 * code, and the backfill prices lines stored at 0 before the fix inside its window only.
 */
class FailedItemKeepsPriceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        Carbon::setTestNow('2026-10-21 12:00:00');
        PaymentMethod::firstOrCreate(['code' => 1], ['name' => 'Card Terminal', 'is_active' => true]);
        VendChannelError::firstOrCreate(['code' => 0], ['desc' => 'No Malfunction (0)']);
        VendChannelError::firstOrCreate(['code' => 7], ['desc' => 'Sensor error (7)']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /** @param  array<int, array{0: string, 1: int}>  $channels  code => [product code, board price ¢] */
    private function vending(int $code, array $channels): Vend
    {
        $customer = Customer::create(['name' => "Site {$code}", 'code' => "S{$code}", 'operator_id' => 1, 'status_id' => Customer::STATUS_ACTIVE]);
        $mapping = ProductMapping::create(['name' => "Mapping {$code}", 'machine_type' => Vend::MACHINE_TYPE_VENDING_MACHINE, 'is_active' => true, 'operator_id' => 1]);
        $vend = new Vend;
        $vend->forceFill([
            'code' => $code, 'machine_type' => Vend::MACHINE_TYPE_VENDING_MACHINE, 'is_active' => 1, 'operator_id' => 1,
            'vend_model_id' => 1, 'product_mapping_id' => $mapping->id, 'customer_id' => $customer->id,
        ])->save();
        foreach ($channels as $channel => [$productCode, $cents]) {
            $product = Product::forceCreate(['code' => "{$productCode}-{$code}", 'name' => "Treat {$productCode}", 'operator_id' => 1]);
            ProductMappingItem::create(['product_mapping_id' => $mapping->id, 'channel_code' => (string) $channel, 'product_id' => $product->id, 'sequence' => $channel]);
            DB::table('vend_channels')->insert([
                'vend_id' => $vend->id, 'code' => $channel, 'product_id' => $product->id, 'amount' => $cents,
                'qty' => 5, 'capacity' => 10, 'is_active' => 1, 'error_rate_json' => '{}',
            ]);
        }

        return $vend->fresh();
    }

    /** @return int[] the sale's line prices, in line order */
    private function prices(VendTransaction $sale): array
    {
        return DB::table('vend_transaction_items')->where('vend_transaction_id', $sale->id)
            ->orderBy('id')->pluck('unit_price_amount')->map(fn ($v) => (int) $v)->all();
    }

    public function test_a_failed_line_keeps_its_price_and_still_is_not_sold(): void
    {
        // 4713, 2026-10-21: three lines dispensed, Walls's Ice Ball Lemon failed (Sensor error 7), the card
        // charged the whole basket.
        $vend = $this->vending(4713, [63 => ['U-07', 320], 43 => ['U-87', 250]]);

        app(VendTransactionService::class)->create($vend, [
            'ORDRID' => '2026102112003787916', 'PAY_TYPE' => 1, 'TIME' => now()->format('Y-m-d H:i:s'), 'TXN_SRC' => 1,
            'Price' => '8.90',
            'transf_info' => [['SId' => 63, 'SErr' => 0], ['SId' => 63, 'SErr' => 0], ['SId' => 43, 'SErr' => 7]],
        ]);
        $sale = VendTransaction::withoutGlobalScopes()->where('order_id', '2026102112003787916')->firstOrFail();

        $this->assertSame(890, (int) $sale->amount);
        $this->assertSame([320, 320, 250], $this->prices($sale), 'the failed line shows what was paid for it, not 0');

        // What counts as sold still leaves the failed line out — by its error code, not its price.
        $sold = DB::table('vend_transaction_items')->where('vend_transaction_id', $sale->id)
            ->whereRaw(\App\Support\DispenseVerdict::sqlSale('vend_channel_error_code'))->sum('unit_price_amount');
        $this->assertSame(640, (int) $sold);
    }

    public function test_the_backfill_prices_old_failed_lines_inside_its_window_only(): void
    {
        $vend = $this->vending(4714, [63 => ['U-07', 320], 43 => ['U-87', 250]]);
        $channels = DB::table('vend_channels')->where('vend_id', $vend->id)->pluck('id', 'code');
        $basket = function (string $at) use ($vend, $channels): int {
            $id = DB::table('vend_transactions')->insertGetId([
                'vend_id' => $vend->id, 'order_id' => 'OLD-'.$at, 'amount' => 570, 'is_multiple' => 1, 'qty' => 2,
                'transaction_datetime' => $at, 'created_at' => $at, 'updated_at' => $at, 'operator_id' => 1,
            ]);
            foreach ([[63, 0, 320], [43, 7, 0]] as [$code, $error, $price]) {
                DB::table('vend_transaction_items')->insert([
                    'vend_transaction_id' => $id, 'vend_channel_id' => $channels[$code], 'vend_channel_code' => $code,
                    'vend_channel_error_code' => $error, 'unit_price_amount' => $price, 'is_refunded' => 0,
                    'created_at' => $at, 'updated_at' => $at,
                ]);
            }

            return $id;
        };
        $recent = $basket('2026-10-15 10:00:00');
        $old = $basket('2026-08-01 10:00:00');
        $lines = fn (int $id) => DB::table('vend_transaction_items')->where('vend_transaction_id', $id)->orderBy('id')
            ->pluck('unit_price_amount')->map(fn ($v) => (int) $v)->all();

        $this->artisan('sales:backfill-failed-item-prices')
            ->expectsOutputToContain('Would price 1 failed line(s)')->assertSuccessful();
        $this->assertSame([320, 0], $lines($recent), 'a dry run writes nothing');

        $this->artisan('sales:backfill-failed-item-prices --apply')->assertSuccessful();
        $this->assertSame([320, 250], $lines($recent));
        $this->assertSame([320, 0], $lines($old), 'outside the window today\'s channel price may not be what was charged');

        $this->artisan('sales:backfill-failed-item-prices --apply')
            ->expectsOutputToContain('Priced 0 failed line(s)')->assertSuccessful();
    }
}
