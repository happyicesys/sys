<?php

namespace Tests\Unit;

use App\Models\Operator;
use App\Models\PaymentMethod;
use App\Models\Vend;
use App\Models\VendChannelError;
use App\Models\VendTransaction;
use App\Services\VendTransactionService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

/**
 * Audit M3-04 (2026-09-16). The header Price of a transf_info frame is a
 * dollar string ("4.6") and used to go through `Price * 100` with no
 * rounding: 4.6 * 100 is 459.999… in binary, so the `(int)` casts in the
 * orphan lookup and the `> 0` gate saw 459 while MySQL stored 460 — a $4.60
 * / $8.20 / $5.10 card sale could never adopt its NETS-report orphan.
 */
class TradeAmountNormalisationTest extends TestCase
{
    use RefreshDatabase;

    private Vend $vend;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-09-16 10:00:00');
        Bus::fake();

        $operator = Operator::create(['code' => 'OP1', 'name' => 'Op', 'timezone' => 'Asia/Singapore']);
        $this->vend = Vend::create(['code' => '9102', 'operator_id' => $operator->id, 'is_active' => 1]);
        PaymentMethod::forceCreate(['code' => 0, 'name' => 'Cash']);
        PaymentMethod::forceCreate(['code' => 1, 'name' => 'Card Terminal']);
        VendChannelError::firstOrCreate(['code' => 0], ['desc' => 'No Malfunction (0)']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /** processInput() is private; drive it in the service's scope with the lookups create() would have loaded. */
    private function processInput(array $frame): array
    {
        $vend = $this->vend->fresh()->load('operator', 'productMapping');

        return (function () use ($vend, $frame) {
            $this->paymentMethods = PaymentMethod::all()->keyBy('code');
            $this->vendChannelErrors = VendChannelError::all()->keyBy('code');
            $this->vendChannels = $vend->vendChannels->keyBy('code');

            return $this->processInput($vend, $frame);
        })->call(app(VendTransactionService::class));
    }

    private static function items(string ...$prices): array
    {
        return array_map(fn ($price, $i) => ['SId' => 11 + $i, 'SErr' => 0, 'Price' => $price, 'goods_id' => 1], $prices, array_keys($prices));
    }

    public function test_transf_info_dollar_prices_round_to_exact_cents(): void
    {
        foreach (['4.6' => 460, '8.2' => 820, '5.1' => 510, '2' => 200, '0.7' => 70] as $price => $cents) {
            $data = $this->processInput(['Price' => (string) $price, 'transf_info' => self::items('2.3', '2.3')]);

            $this->assertSame($cents, $data['amount'], "Price {$price}");
        }

        $this->assertSame(460, $this->processInput(['Price' => 4.6, 'transf_info' => self::items('4.6')])['amount'], 'a float Price rounds too');
    }

    public function test_a_frame_without_transf_info_carries_cents_already(): void
    {
        $this->assertSame(350, $this->processInput(['Price' => '350'])['amount']);
        $this->assertSame(350, $this->processInput(['Price' => 350])['amount']);
        $this->assertSame(0, $this->processInput([])['amount']);
    }

    public function test_a_multi_item_card_trade_at_four_sixty_adopts_its_nets_orphan(): void
    {
        // The report created this sale before the machine reported (Part 2
        // orphan): same machine, 460 cents, awaiting its TRADE. With the
        // truncated 459 the lookup missed it and a second row was inserted.
        $orphan = VendTransaction::forceCreate([
            'order_id' => 'CS-1', 'vend_id' => $this->vend->id, 'vend_channel_id' => 0, 'amount' => 460, 'gst_vat_rate' => 9,
            'transaction_datetime' => '2026-09-16 09:59:40', 'operator_id' => $this->vend->operator_id,
            'card_settlement_row_id' => 1, 'is_found_in_transaction' => false, 'settlement_status' => VendTransaction::SETTLEMENT_SETTLED,
        ]);

        app(VendTransactionService::class)->create($this->vend->fresh(), [
            'ORDRID' => 'C460', 'PAY_TYPE' => 1, 'TIME' => '2026-09-16 09:59:55', 'TXN_SRC' => 0, 'Price' => '4.6',
            'transf_info' => self::items('2.3', '2.3'),
        ]);

        $rows = VendTransaction::withoutGlobalScopes()->where('vend_id', $this->vend->id)->get();
        $this->assertCount(1, $rows, 'adopted, not duplicated');
        $this->assertSame($orphan->id, $rows[0]->id);
        $this->assertSame('C460', $rows[0]->order_id, 'the machine order id replaces the synthetic one');
        $this->assertTrue((bool) $rows[0]->is_found_in_transaction);
        $this->assertSame(460, (int) $rows[0]->amount);
    }
}
