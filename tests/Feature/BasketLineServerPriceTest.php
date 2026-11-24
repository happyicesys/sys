<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\PaymentGatewayLog;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\ProductMapping;
use App\Models\ProductMappingItem;
use App\Models\Vend;
use App\Models\VendChannelError;
use App\Models\VendTransaction;
use App\Services\GatewayVendTransactionService;
use App\Services\VendTransactionService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * A basket line is priced at what the machine's screen charged (Brian, 2026-11-24, IDN 10027 order
 * 26112112553410027: 60,000 paid for Tropical Sunrise 32,000 + Watermelon Berry 28,000, both lines stored at
 * 32,000 — the board price — on a machine that sells at the Site's RP2). Pinned: the TRADE ingest and the QR
 * pre-create both price a line by ChannelChargedPrice, a Machine Price machine keeps its board prices, and the
 * backfill re-prices old baskets only where the lines then add up to what was paid.
 */
class BasketLineServerPriceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        Carbon::setTestNow('2026-11-24 12:00:00');
        PaymentMethod::firstOrCreate(['code' => 1], ['name' => 'Card Terminal', 'is_active' => true]);
        VendChannelError::firstOrCreate(['code' => 0], ['desc' => 'No Malfunction (0)']);
        VendChannelError::firstOrCreate(['code' => 7], ['desc' => 'Sensor error (7)']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /**
     * @param  array<int, array{0: string, 1: int, 2: int|null}>  $channels  code => [product code, board ¢, Site RP2 ¢]
     */
    private function machine(int $code, array $channels, bool $serverPrice = true, ?array $loaded = null): Vend
    {
        $customer = Customer::create(['name' => "Site {$code}", 'code' => "S{$code}", 'operator_id' => 1, 'status_id' => Customer::STATUS_ACTIVE]);
        DB::table('customers')->where('id', $customer->id)->update(['selling_price_type' => 2]);
        $mapping = ProductMapping::create(['name' => "Mapping {$code}", 'machine_type' => Vend::MACHINE_TYPE_VENDING_MACHINE, 'is_active' => true, 'operator_id' => 1]);
        $vend = new Vend;
        $vend->forceFill([
            'code' => $code, 'machine_type' => Vend::MACHINE_TYPE_VENDING_MACHINE, 'is_active' => 1, 'operator_id' => 1,
            'vend_model_id' => 1, 'product_mapping_id' => $mapping->id, 'customer_id' => $customer->id,
            'is_using_server_price' => $serverPrice,
        ])->save();
        foreach ($channels as $channel => [$productCode, $board, $rp2]) {
            $product = Product::forceCreate(['code' => "{$productCode}-{$code}", 'name' => "Juice {$productCode}", 'operator_id' => 1]);
            ProductMappingItem::create(['product_mapping_id' => $mapping->id, 'channel_code' => (string) $channel, 'product_id' => $product->id, 'sequence' => $channel]);
            DB::table('vend_channels')->insert([
                'vend_id' => $vend->id, 'code' => $channel, 'product_id' => $product->id, 'amount' => $board,
                'qty' => 5, 'capacity' => 10, 'is_active' => 1, 'error_rate_json' => '{}',
            ]);
            if ($rp2 !== null) {
                DB::table('selling_prices')->insert(['product_id' => $product->id, 'type' => 2, 'amount' => $rp2, 'created_at' => now(), 'updated_at' => now()]);
            }
        }
        if ($loaded !== null) {
            DB::table('vends')->where('id', $vend->id)->update(['price_menu_prices_json' => json_encode((object) $loaded)]);
        }

        return $vend->fresh();
    }

    /** @return int[] line prices in line order */
    private function prices(VendTransaction $sale): array
    {
        return DB::table('vend_transaction_items')->where('vend_transaction_id', $sale->id)
            ->orderBy('id')->pluck('unit_price_amount')->map(fn ($v) => (int) $v)->all();
    }

    private function trade(Vend $vend, string $orderId, string $price, array $lines): VendTransaction
    {
        app(VendTransactionService::class)->create($vend, [
            'ORDRID' => $orderId, 'PAY_TYPE' => 1, 'TIME' => now()->format('Y-m-d H:i:s'), 'TXN_SRC' => 1,
            'Price' => $price, 'transf_info' => $lines,
        ]);

        return VendTransaction::withoutGlobalScopes()->where('order_id', $orderId)->firstOrFail();
    }

    public function test_a_server_price_basket_line_is_stored_at_the_price_the_machine_charged(): void
    {
        // The 10027 case: board 32,000 on both; RP2 has Watermelon Berry at 28,000 and the machine loaded it.
        $vend = $this->machine(10027, [16 => ['CP22', 3200000, 3200000], 15 => ['CP19', 3200000, 2800000]],
            loaded: ['16' => 3200000, '15' => 2800000]);

        $sale = $this->trade($vend, '26112112553410027', '60000.00', [['SId' => 16, 'SErr' => 0], ['SId' => 15, 'SErr' => 7]]);

        $this->assertSame(6000000, (int) $sale->amount);
        $this->assertSame([3200000, 2800000], $this->prices($sale), 'the lines add up to what was paid');
    }

    public function test_the_loaded_price_wins_and_an_unloaded_channel_uses_the_site_price_or_the_board(): void
    {
        // 11: the machine loaded no server price (null) → board price. 12: loaded 450 while RP2 now says 480 → it
        // charged 450. 13: not in the loaded record → today's RP2 (400).
        $vend = $this->machine(4801, [11 => ['A', 300, null], 12 => ['B', 500, 480], 13 => ['C', 500, 400]],
            loaded: ['11' => null, '12' => 450]);

        $sale = $this->trade($vend, 'ORD-4801', '11.50', [['SId' => 11, 'SErr' => 0], ['SId' => 12, 'SErr' => 0], ['SId' => 13, 'SErr' => 0]]);

        $this->assertSame([300, 450, 400], $this->prices($sale));
    }

    public function test_a_machine_price_machine_keeps_its_board_prices(): void
    {
        $vend = $this->machine(4802, [11 => ['A', 300, 250], 12 => ['B', 500, 400]], serverPrice: false, loaded: ['11' => null, '12' => null]);

        $sale = $this->trade($vend, 'ORD-4802', '8.00', [['SId' => 11, 'SErr' => 0], ['SId' => 12, 'SErr' => 0]]);

        $this->assertSame([300, 500], $this->prices($sale));
    }

    public function test_a_single_sale_still_takes_the_trade_price(): void
    {
        $vend = $this->machine(4803, [15 => ['CP19', 3200000, 2800000]], loaded: ['15' => 2800000]);

        $sale = $this->trade($vend, 'ORD-4803', '27000.00', [['SId' => 15, 'SErr' => 0]]);

        $this->assertSame(2700000, (int) $sale->amount, 'the machine\'s own figure is never second-guessed');
    }

    public function test_the_qr_pre_create_prices_basket_lines_the_same_way(): void
    {
        $vend = $this->machine(10028, [16 => ['CP22', 3200000, 3200000], 15 => ['CP19', 3200000, 2800000]],
            loaded: ['16' => 3200000, '15' => 2800000]);
        $log = PaymentGatewayLog::create([
            'order_id' => '26112400000010028', 'vend_id' => $vend->id, 'vend_code' => (string) $vend->code,
            'amount' => 60000, 'status' => PaymentGatewayLog::STATUS_APPROVE, 'txn_src' => 1,
            'vend_channels_json' => [['id' => null, 'code' => 16], ['id' => null, 'code' => 15]],
        ]);

        $sale = app(GatewayVendTransactionService::class)->createFromPaymentGatewayLog($log->fresh());

        $this->assertNotNull($sale);
        $this->assertSame([3200000, 2800000], $this->prices($sale));
    }

    public function test_the_backfill_reprices_only_baskets_whose_lines_then_add_up(): void
    {
        $vend = $this->machine(10029, [16 => ['CP22', 3200000, 3200000], 15 => ['CP19', 3200000, 2800000]],
            loaded: ['16' => 3200000, '15' => 2800000]);
        $channels = DB::table('vend_channels')->where('vend_id', $vend->id)->pluck('id', 'code');
        $basket = function (string $orderId, int $amount, string $at) use ($vend, $channels): int {
            $id = DB::table('vend_transactions')->insertGetId([
                'vend_id' => $vend->id, 'order_id' => $orderId, 'amount' => $amount, 'is_multiple' => 1, 'qty' => 2,
                'transaction_datetime' => $at, 'created_at' => $at, 'updated_at' => $at, 'operator_id' => 1,
                'gst_vat_rate' => 0,
            ]);
            foreach ([16, 15] as $code) {
                DB::table('vend_transaction_items')->insert([
                    'vend_transaction_id' => $id, 'vend_channel_id' => $channels[$code], 'vend_channel_code' => $code,
                    'vend_channel_error_code' => 0, 'unit_price_amount' => 3200000, 'is_refunded' => 0,
                    'created_at' => $at, 'updated_at' => $at,
                ]);
            }

            return $id;
        };
        $wrong = $basket('B-1', 6000000, '2026-11-21 12:55:00');      // paid 60,000: re-pricing proves it
        $priceMoved = $basket('B-2', 6400000, '2026-11-05 10:00:00'); // paid 64,000: RP2 was 32,000 then — leave it
        $tooOld = $basket('B-3', 6000000, '2026-09-01 10:00:00');     // outside the window
        $lines = fn (int $id) => DB::table('vend_transaction_items')->where('vend_transaction_id', $id)->orderBy('id')
            ->pluck('unit_price_amount')->map(fn ($v) => (int) $v)->all();

        $this->artisan('sales:backfill-server-price-basket-lines')
            ->expectsOutputToContain('Would re-price 1 basket(s), 1 line(s)')->assertSuccessful();
        $this->assertSame([3200000, 3200000], $lines($wrong), 'a dry run writes nothing');

        $this->artisan('sales:backfill-server-price-basket-lines --apply')->assertSuccessful();
        $this->assertSame([3200000, 2800000], $lines($wrong));
        $this->assertSame([3200000, 3200000], $lines($priceMoved), 'already adds up: what was charged then');
        $this->assertSame([3200000, 3200000], $lines($tooOld));

        $this->artisan('sales:backfill-server-price-basket-lines --apply')
            ->expectsOutputToContain('Re-priced 0 basket(s)')->assertSuccessful();
    }
}
