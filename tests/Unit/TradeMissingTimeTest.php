<?php

namespace Tests\Unit;

use App\Models\Operator;
use App\Models\PaymentMethod;
use App\Models\Vend;
use App\Models\VendChannelError;
use App\Models\VendTransaction;
use App\Services\VendTransactionService;
use App\Support\TradeTimestampResolver;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

/**
 * Audit M3-16 (2026-09-16). A TRADE with no TIME used to get a server "now"
 * substituted in the app zone, which the resolver then read as the
 * operator's zone — and the `missing` stamp was unreachable. Now the null
 * reaches TradeTimestampResolver: booked at arrival, stamped `missing`, and
 * both consumers (createVendTransaction, findSettlementOrphan) take the null.
 */
class TradeMissingTimeTest extends TestCase
{
    use RefreshDatabase;

    private Vend $vend;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-09-16 10:00:00');
        Bus::fake();

        // An operator in a zone that is NOT the app zone: the old substitution shifted the sale by the offset.
        $operator = Operator::create(['code' => 'OP1', 'name' => 'Op', 'timezone' => 'Asia/Jakarta']);
        $this->vend = Vend::create(['code' => '9103', 'operator_id' => $operator->id, 'is_active' => 1]);
        PaymentMethod::forceCreate(['code' => 0, 'name' => 'Cash']);
        PaymentMethod::forceCreate(['code' => 1, 'name' => 'Card Terminal']);
        VendChannelError::firstOrCreate(['code' => 0], ['desc' => 'No Malfunction (0)']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_a_frame_without_time_books_at_arrival_and_is_stamped_missing(): void
    {
        app(VendTransactionService::class)->create($this->vend->fresh(), [
            'ORDRID' => 'M1', 'PAY_TYPE' => 0, 'SErr' => 0, 'SId' => 11, 'Price' => 200, 'TXN_SRC' => 0,
        ]);

        $row = VendTransaction::withoutGlobalScopes()->where('vend_id', $this->vend->id)->first();
        $this->assertNotNull($row);
        $this->assertSame('2026-09-16 10:00:00', $row->transaction_datetime->toDateTimeString(), 'arrival time in the app zone, no operator-zone shift');
        $this->assertSame('2026-09-16 10:00:00', $row->received_at->toDateTimeString());
        $this->assertEquals(
            ['raw' => null, 'rejected' => true, 'reason' => TradeTimestampResolver::REASON_MISSING],
            $row->meta_json['frame_time']
        );
        $this->assertArrayHasKey('vend_code', $row->meta_json, 'merged into the existing meta bag');
    }

    public function test_a_card_frame_without_time_still_adopts_its_nets_orphan_on_the_receive_anchor(): void
    {
        $orphan = VendTransaction::forceCreate([
            'order_id' => 'CS-7', 'vend_id' => $this->vend->id, 'vend_channel_id' => 0, 'amount' => 300, 'gst_vat_rate' => 9,
            'transaction_datetime' => '2026-09-16 09:59:45', 'operator_id' => $this->vend->operator_id,
            'card_settlement_row_id' => 7, 'is_found_in_transaction' => false, 'settlement_status' => VendTransaction::SETTLEMENT_SETTLED,
        ]);

        app(VendTransactionService::class)->create($this->vend->fresh(), [
            'ORDRID' => 'M2', 'PAY_TYPE' => 1, 'SErr' => 0, 'SId' => 11, 'Price' => 300, 'TXN_SRC' => 0,
        ]);

        $rows = VendTransaction::withoutGlobalScopes()->where('vend_id', $this->vend->id)->get();
        $this->assertCount(1, $rows);
        $this->assertSame($orphan->id, $rows[0]->id);
        $this->assertSame('M2', $rows[0]->order_id);
        $this->assertTrue((bool) $rows[0]->is_found_in_transaction);
    }
}
