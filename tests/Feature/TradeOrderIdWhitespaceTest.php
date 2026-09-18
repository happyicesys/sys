<?php

namespace Tests\Feature;

use App\Jobs\Vend\CreateVendTransaction;
use App\Models\Operator;
use App\Models\PaymentGatewayLog;
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
 * IDN claw 18004 (since 2026-09-03) sends ORDRID with a leading space. MySQL
 * compares leading spaces, so the TRADE missed the row its Midtrans payment had
 * pre-created and inserted a second sale — every QR sale was booked twice.
 */
class TradeOrderIdWhitespaceTest extends TestCase
{
    use RefreshDatabase;

    private Vend $vend;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-09-15 15:16:00');
        Bus::fake();

        $operator = Operator::create(['code' => 'OP1', 'name' => 'Op', 'timezone' => 'Asia/Singapore']);
        $this->vend = Vend::create(['code' => '18004', 'operator_id' => $operator->id, 'is_active' => 1]);
        PaymentMethod::forceCreate(['code' => 0, 'name' => 'Cash']);
        VendChannelError::firstOrCreate(['code' => 0], ['desc' => 'No Malfunction (0)']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function frame(string $orderId): array
    {
        return [
            'ORDRID' => $orderId, 'PAY_TYPE' => 0, 'TIME' => '2026-09-15 15:15:50', 'SErr' => 0, 'SId' => 52, 'Price' => 1000000, 'TXN_SRC' => 0,
        ];
    }

    public function test_a_padded_trade_fills_the_gateway_pre_created_row_instead_of_adding_a_second(): void
    {
        $log = PaymentGatewayLog::create([
            'order_id' => '26091515150318004',
            'vend_id' => $this->vend->id,
            'vend_code' => $this->vend->code,
            'operator_payment_gateway_id' => 1,
            'amount' => 10000,
            'method' => 'qris',
            'status' => PaymentGatewayLog::STATUS_APPROVE,
            'approved_at' => Carbon::parse('2026-09-15 15:15:04'),
            'txn_src' => 0,
        ]);
        $preCreated = VendTransaction::create([
            'order_id' => '26091515150318004',
            'vend_id' => $this->vend->id,
            'transaction_datetime' => Carbon::parse('2026-09-15 15:15:40'),
            'amount' => 1000000,
            'qty' => 1,
            'vend_channel_id' => 0,
            'gst_vat_rate' => 0,
            'payment_gateway_log_id' => $log->id,
            'is_found_in_transaction' => false,
            'settlement_status' => VendTransaction::SETTLEMENT_SETTLED,
        ]);

        app(VendTransactionService::class)->create($this->vend->fresh(), $this->frame(' 26091515150318004'));

        $rows = VendTransaction::withoutGlobalScopes()->where('vend_id', $this->vend->id)->get();
        $this->assertCount(1, $rows, 'The padded TRADE must land on the pre-created row, not beside it.');
        $this->assertSame($preCreated->id, $rows[0]->id);
        $this->assertSame('26091515150318004', $rows[0]->order_id);
        $this->assertTrue((bool) $rows[0]->is_found_in_transaction);
    }

    public function test_a_padded_trade_is_stored_trimmed_and_its_re_delivery_is_skipped(): void
    {
        (new CreateVendTransaction($this->frame(' 26091515150318004'), $this->vend->fresh()))
            ->handle(app(VendTransactionService::class));
        (new CreateVendTransaction($this->frame('26091515150318004 '), $this->vend->fresh()))
            ->handle(app(VendTransactionService::class));

        $this->assertSame(
            ['26091515150318004'],
            VendTransaction::withoutGlobalScopes()->where('vend_id', $this->vend->id)->pluck('order_id')->all()
        );
    }
}
