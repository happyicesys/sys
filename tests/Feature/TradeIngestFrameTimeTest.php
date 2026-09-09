<?php

namespace Tests\Feature;

use App\Models\Operator;
use App\Models\PaymentGatewayLog;
use App\Models\PaymentMethod;
use App\Models\Vend;
use App\Models\VendChannelError;
use App\Models\VendTransaction;
use App\Services\Sales\DirtyDayRegistry;
use App\Services\VendTransactionService;
use App\Support\DispenseVerdict;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

/**
 * End to end through VendTransactionService::create(): a live frame keeps its
 * TIME inside the window and is booked at arrival outside it (with the audit
 * stamp); a past-day frame dirties its day; a TRADE that fills a 99-marked
 * gateway row clears the mark in the same write; a TXN_SRC-50 TRADE replayed
 * across a month-digit boundary still finds the row its gateway pre-created.
 */
class TradeIngestFrameTimeTest extends TestCase
{
    use RefreshDatabase;

    private Vend $vend;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-09-09 14:00:00');
        Bus::fake();

        $operator = Operator::create(['code' => 'OP1', 'name' => 'Op', 'timezone' => 'Asia/Singapore']);
        $this->vend = Vend::create(['code' => '9001', 'operator_id' => $operator->id, 'is_active' => 1]);
        PaymentMethod::forceCreate(['code' => 0, 'name' => 'Cash']);
        VendChannelError::firstOrCreate(['code' => 0], ['desc' => 'No Malfunction (0)']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function trade(array $frame): ?VendTransaction
    {
        app(VendTransactionService::class)->create($this->vend->fresh(), array_merge([
            'ORDRID' => 'T-'.uniqid(), 'PAY_TYPE' => 0, 'TIME' => '2026-09-09 13:59:50', 'SErr' => 0, 'SId' => 11, 'Price' => 200, 'TXN_SRC' => 0,
        ], $frame));

        return VendTransaction::withoutGlobalScopes()->where('vend_id', $this->vend->id)->orderByDesc('id')->first();
    }

    public function test_a_replayed_frame_keeps_its_day_and_dirties_it(): void
    {
        $row = $this->trade(['ORDRID' => 'T1', 'TIME' => '2026-09-07 10:00:00']);

        $this->assertNotNull($row);
        $this->assertSame('2026-09-07 10:00:00', $row->transaction_datetime->toDateTimeString());
        $this->assertArrayNotHasKey('frame_time', $row->meta_json ?? []);
        $this->assertSame(0, $row->vendChannelError->code);
        $this->assertSame(['2026-09-07'], app(DirtyDayRegistry::class)->days());
    }

    public function test_a_live_frame_dirties_nothing_and_a_future_clock_is_booked_now_with_a_stamp(): void
    {
        $live = $this->trade(['ORDRID' => 'T2']);
        $this->assertSame('2026-09-09 13:59:50', $live->transaction_datetime->toDateTimeString());
        $this->assertSame([], app(DirtyDayRegistry::class)->days());

        $future = $this->trade(['ORDRID' => 'T3', 'TIME' => '2070-01-09 01:19:08']);
        $this->assertSame('2026-09-09 14:00:00', $future->transaction_datetime->toDateTimeString());
        $this->assertEquals( // MySQL stores JSON keys in its own order
            ['raw' => '2070-01-09 01:19:08', 'rejected' => true, 'reason' => 'future'],
            $future->meta_json['frame_time']
        );
        $this->assertArrayHasKey('vend_code', $future->meta_json, 'the stamp is merged into the existing meta bag, not a second key');
    }

    public function test_a_late_trade_fills_the_marked_gateway_row_and_clears_the_99_in_one_write(): void
    {
        $log = PaymentGatewayLog::forceCreate([
            'vend_code' => '9001', 'vend_id' => $this->vend->id, 'order_id' => 'T4', 'operator_payment_gateway_id' => 1,
            'amount' => 2.0, 'status' => 2, 'created_at' => '2026-09-05 09:00:00', 'is_dispensed' => 1,
        ]);
        $naId = VendChannelError::where('code', DispenseVerdict::NOT_FOUND_CODE)->value('id');
        $pre = VendTransaction::forceCreate([
            'order_id' => 'T4', 'vend_id' => $this->vend->id, 'vend_channel_id' => 0, 'amount' => 200, 'gst_vat_rate' => 9,
            'transaction_datetime' => '2026-09-05 09:00:00', 'operator_id' => $this->vend->operator_id,
            'payment_gateway_log_id' => $log->id, 'is_found_in_transaction' => false,
            'vend_channel_error_id' => $naId, 'settlement_status' => VendTransaction::SETTLEMENT_SETTLED,
            'meta_json' => ['missing_trade' => ['marked_at' => '2026-09-06 00:01:00'], 'vend_code' => '9001'],
        ]);

        $row = $this->trade(['ORDRID' => 'T4', 'TIME' => '2026-09-05 09:00:04']);

        $this->assertSame($pre->id, $row->id, 'filled, not duplicated');
        $this->assertSame(1, VendTransaction::withoutGlobalScopes()->where('vend_id', $this->vend->id)->count());
        $this->assertTrue((bool) $row->is_found_in_transaction);
        $this->assertSame(0, $row->vendChannelError->code);
        $this->assertSame('2026-09-05 09:00:00', $row->transaction_datetime->toDateTimeString(), 'paid time stays the transaction moment');
        $this->assertSame('2026-09-06 00:01:00', $row->meta_json['missing_trade']['marked_at']);
        $this->assertSame('2026-09-09 14:00:00', $row->meta_json['missing_trade']['cleared_at']);
        $this->assertSame(['2026-09-05'], app(DirtyDayRegistry::class)->days());
    }

    public function test_a_txn_src_50_trade_replayed_across_a_month_boundary_finds_its_pre_created_row(): void
    {
        Carbon::setTestNow('2026-10-01 00:10:00'); // frame paid 30 Sep, TRADE arrives 1 Oct
        $log = PaymentGatewayLog::forceCreate([
            'vend_code' => '9001', 'vend_id' => $this->vend->id, 'order_id' => '260ABC', 'operator_payment_gateway_id' => 1,
            'amount' => 2.0, 'status' => 2, 'created_at' => '2026-09-30 23:59:00', 'is_dispensed' => 1,
        ]);
        $pre = VendTransaction::forceCreate([
            'order_id' => '260ABC', 'vend_id' => $this->vend->id, 'vend_channel_id' => 0, 'amount' => 200, 'gst_vat_rate' => 9,
            'transaction_datetime' => '2026-09-30 23:59:00', 'operator_id' => $this->vend->operator_id,
            'payment_gateway_log_id' => $log->id, 'is_found_in_transaction' => false, 'settlement_status' => VendTransaction::SETTLEMENT_SETTLED,
        ]);

        $row = $this->trade(['ORDRID' => 'ABC', 'TXN_SRC' => 50, 'TIME' => '2026-09-30 23:59:05']);

        $this->assertSame($pre->id, $row->id);
        $this->assertSame(1, VendTransaction::withoutGlobalScopes()->where('vend_id', $this->vend->id)->count());
        $this->assertTrue((bool) $row->is_found_in_transaction);
        $this->assertSame('260ABC', $row->order_id, 'the paid-time id is kept');
    }
}
