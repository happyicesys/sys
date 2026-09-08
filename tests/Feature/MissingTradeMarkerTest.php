<?php

namespace Tests\Feature;

use App\Models\Operator;
use App\Models\PaymentGatewayLog;
use App\Models\Setting;
use App\Models\Vend;
use App\Models\VendChannelError;
use App\Models\VendTransaction;
use App\Models\VendTransactionItem;
use App\Services\Sales\MissingTradeMarker;
use App\Support\DispenseVerdict;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Nightly marker: gateway rows whose day is over and whose TRADE never came
 * get code 99 on the header AND on their item rows; today's rows, non-gateway
 * rows and rows that got a TRADE are untouched; a second run finds nothing;
 * the watermark only moves on --apply.
 */
class MissingTradeMarkerTest extends TestCase
{
    use RefreshDatabase;

    private Vend $vend;

    private PaymentGatewayLog $log;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-09-09 00:01:00');

        $operator = Operator::create(['code' => 'OP1', 'name' => 'Op']);
        $this->vend = Vend::create(['code' => '9001', 'operator_id' => $operator->id]);
        $this->log = PaymentGatewayLog::forceCreate([
            'vend_code' => '9001', 'vend_id' => $this->vend->id, 'order_id' => 'PG-1', 'operator_payment_gateway_id' => 1,
            'amount' => 2.5, 'status' => 2, 'created_at' => now(), 'is_dispensed' => 1,
        ]);
        VendChannelError::firstOrCreate(['code' => 0], ['desc' => 'No Malfunction (0)']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function txn(array $attrs = []): VendTransaction
    {
        return VendTransaction::forceCreate(array_merge([
            'order_id' => 'O-'.uniqid(), 'vend_id' => $this->vend->id, 'vend_channel_id' => 0, 'amount' => 250,
            'transaction_datetime' => '2026-09-08 12:00:00', 'gst_vat_rate' => 9, 'is_multiple' => 0,
            'payment_gateway_log_id' => $this->log->id, 'is_found_in_transaction' => false, 'vend_channel_error_id' => null,
            'settlement_status' => VendTransaction::SETTLEMENT_SETTLED, 'operator_id' => $this->vend->operator_id,
        ], $attrs));
    }

    public function test_marks_yesterdays_gateway_rows_without_a_trade_on_header_and_items(): void
    {
        $single = $this->txn();
        $multi = $this->txn(['is_multiple' => 1, 'qty' => 2]);
        VendTransactionItem::forceCreate(['vend_transaction_id' => $multi->id, 'vend_channel_id' => 0, 'vend_channel_code' => 11, 'vend_channel_error_id' => null, 'unit_price_amount' => 100]);
        VendTransactionItem::forceCreate(['vend_transaction_id' => $multi->id, 'vend_channel_id' => 0, 'vend_channel_code' => 12, 'vend_channel_error_id' => null, 'unit_price_amount' => 150]);

        $today = $this->txn(['transaction_datetime' => '2026-09-09 00:00:30']);            // day not over
        $cash = $this->txn(['payment_gateway_log_id' => null]);                              // not a gateway row
        $refunded = $this->txn(['settlement_status' => VendTransaction::SETTLEMENT_REFUNDED, 'is_refunded' => 1]); // marked too (Brian)
        $withTrade = $this->txn(['is_found_in_transaction' => true, 'vend_channel_error_id' => VendChannelError::where('code', 0)->value('id')]);

        $naId = VendChannelError::where('code', DispenseVerdict::NOT_FOUND_CODE)->value('id');
        $marker = new MissingTradeMarker(Setting::create([]));
        [$from, $until] = $marker->nightlyWindow();
        $this->assertSame('2026-08-01 00:00:00', $from->toDateTimeString());
        $this->assertSame('2026-09-09 00:00:00', $until->toDateTimeString());

        // Report mode: counts, writes nothing.
        $report = $marker->mark($from, $until, false);
        $this->assertSame(3, $report->headers);
        $this->assertSame(2, $report->items);
        $this->assertSame(['2026-09-08' => 3], $report->perDay);
        $this->assertNull($single->fresh()->vend_channel_error_id);
        $this->assertNull(Setting::first()->missing_trade_marked_until);

        // Apply.
        $result = $marker->mark($from, $until, true);
        $this->assertSame(3, $result->headers);

        foreach ([$single, $multi, $refunded] as $row) {
            $row->refresh();
            $this->assertSame($naId, $row->vend_channel_error_id, $row->order_id);
            $this->assertSame('2026-09-09 00:01:00', $row->meta_json['missing_trade']['marked_at']);
            $this->assertFalse((bool) $row->is_found_in_transaction, 'the TRADE flag is not the marker\'s to touch');
        }
        $this->assertSame(VendTransaction::SETTLEMENT_REFUNDED, (int) $refunded->fresh()->settlement_status);
        foreach ($multi->vendTransactionItems as $item) {
            $this->assertSame($naId, $item->vend_channel_error_id);
            $this->assertSame(99, (int) $item->vend_channel_error_code);
        }

        $this->assertNull($today->fresh()->vend_channel_error_id);
        $this->assertNull($cash->fresh()->vend_channel_error_id);
        $this->assertSame(0, $withTrade->fresh()->vendChannelError->code);

        $this->assertSame('2026-09-09 00:00:00', Setting::first()->missing_trade_marked_until->toDateTimeString());

        // Idempotent: the next night starts at the watermark and finds nothing.
        [$from2, $until2] = (new MissingTradeMarker(Setting::first()))->nightlyWindow();
        $this->assertSame('2026-09-09 00:00:00', $from2->toDateTimeString());
        $this->assertSame(0, (new MissingTradeMarker(Setting::first()))->mark($from, $until, true)->headers);
    }

    public function test_command_reports_and_applies(): void
    {
        $this->txn();
        Setting::create([]);

        $this->artisan('sales:mark-missing-trade')
            ->expectsOutputToContain('Would mark 1 header row(s)')
            ->assertSuccessful();
        $this->assertNull(VendTransaction::first()->vend_channel_error_id);

        $this->artisan('sales:mark-missing-trade --from=2026-09-01 --to=2026-09-09 --apply')
            ->expectsOutputToContain('Marked 1 header row(s)')
            ->assertSuccessful();
        $this->assertSame(99, VendTransaction::first()->vendChannelError->code);
    }
}
