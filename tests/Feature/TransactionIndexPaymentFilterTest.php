<?php

namespace Tests\Feature;

use App\Models\PaymentMethod;
use App\Models\VendTransaction;
use App\Support\AutoRefundSource;
use App\Support\SaleFacts;
use App\Support\SaleStatus;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

/**
 * Sales Transactions "Payment Status" filter (request key `payment_status`).
 *
 * The filter is SaleStatus::payment() expressed in SQL, so every option must
 * list exactly the rows whose Payment Status cell shows that label — pinned
 * here by cross-checking each listed row against SaleStatus itself.
 */
class TransactionIndexPaymentFilterTest extends TestCase
{
    use RefreshDatabase;

    private PaymentMethod $cash;

    private PaymentMethod $card;

    private PaymentMethod $paynow;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cash = PaymentMethod::create(['code' => 0, 'name' => 'Cash', 'is_active' => true]);
        $this->card = PaymentMethod::create(['code' => 1, 'name' => 'Card Terminal', 'is_active' => true]);
        $this->paynow = PaymentMethod::create(['code' => 201, 'name' => 'Omise (Paynow)', 'payment_gateway_id' => 2, 'is_active' => true]);
    }

    private function txn(PaymentMethod $method, array $attrs = [], array $force = []): VendTransaction
    {
        static $n = 0;

        $txn = VendTransaction::create(array_merge([
            'order_id' => 'PAY'.(++$n),
            'vend_id' => 1320,
            'transaction_datetime' => Carbon::parse('2026-09-02 11:49:46'),
            'amount' => 200,
            'qty' => 1,
            'vend_channel_id' => 0,
            'gst_vat_rate' => 0,
            'payment_method_id' => $method->id,
            'settlement_status' => VendTransaction::SETTLEMENT_SETTLED,
        ], $attrs));

        if ($force) {
            $txn->forceFill($force)->save();
        }

        return $txn;
    }

    private function listed(string $filter): array
    {
        return VendTransaction::withoutGlobalScopes()
            ->filterTransactionIndex(new Request(['payment_status' => $filter]), true)
            ->pluck('vend_transactions.id')
            ->sort()
            ->values()
            ->all();
    }

    /** What the grid cell would show for a row (same inputs the grid feeds SaleStatus). */
    private function label(VendTransaction $txn): string
    {
        $row = $txn->fresh();
        $row->payment_method_gateway_id = PaymentMethod::find($row->payment_method_id)?->payment_gateway_id;

        return SaleStatus::payment(SaleFacts::fromRow($row));
    }

    public function test_each_option_lists_exactly_the_rows_the_column_labels_that_way(): void
    {
        $cashUnconfirmed = $this->txn($this->cash);
        $cardUnconfirmed = $this->txn($this->card);
        $cardSettled = $this->txn($this->card, [], ['card_settlement_synced_at' => now()]);
        $paynowPaid = $this->txn($this->paynow);
        $paynowPending = $this->txn($this->paynow, ['settlement_status' => VendTransaction::SETTLEMENT_PENDING]);
        $cardReversed = $this->txn($this->card, [], [
            'is_refunded' => true,
            'auto_refund_source' => AutoRefundSource::SETTLEMENT_REPORT_REVERSAL,
            'card_settlement_synced_at' => now(),
        ]);
        $paynowVoided = $this->txn($this->paynow, ['settlement_status' => VendTransaction::SETTLEMENT_REFUNDED]);
        $reVended = $this->txn($this->card, [], [
            'is_refunded' => true,
            'auto_refund_source' => AutoRefundSource::RETAINED_CREDIT_REVEND,
        ]);
        $retained = $this->txn($this->card, [], ['is_retained_credit_settlement' => true]);

        $expected = [
            'unconfirmed' => [$cashUnconfirmed, $cardUnconfirmed],
            'settled' => [$cardSettled],
            'paid' => [$paynowPaid, $paynowPending],
            'refunded' => [$cardReversed, $paynowVoided],
            're_vended' => [$reVended],
            'retained_credit' => [$retained],
        ];
        $labels = [
            'unconfirmed' => SaleStatus::UNCONFIRMED,
            'settled' => SaleStatus::SETTLED,
            'paid' => SaleStatus::PAID,
            'refunded' => SaleStatus::REFUNDED,
            're_vended' => SaleStatus::RE_VENDED,
            'retained_credit' => SaleStatus::RETAINED_CREDIT,
        ];

        foreach ($expected as $filter => $rows) {
            $ids = collect($rows)->pluck('id')->sort()->values()->all();
            $this->assertSame($ids, $this->listed($filter), "filter {$filter}");
            foreach ($rows as $row) {
                $this->assertSame($labels[$filter], $this->label($row), "row {$row->order_id} under {$filter}");
            }
        }

        // Every row lands in exactly one bucket.
        $all = collect($expected)->flatten()->pluck('id')->sort()->values()->all();
        $this->assertSame($all, $this->listed('all'));
        $this->assertCount(9, $all);
    }

    public function test_dispense_filter_exposes_the_no_verdict_states(): void
    {
        $pending = $this->txn($this->paynow, ['settlement_status' => VendTransaction::SETTLEMENT_PENDING]);
        $noReport = $this->txn($this->paynow, [], ['is_found_in_transaction' => false]);
        $this->txn($this->paynow); // settled, TRADE received

        $dispense = fn (string $f) => VendTransaction::withoutGlobalScopes()
            ->filterTransactionIndex(new Request(['is_payment_received' => $f]), true)
            ->pluck('vend_transactions.id')->sort()->values()->all();

        $this->assertSame([$pending->id], $dispense('pending'));
        $this->assertSame([$noReport->id], $dispense('no_report'));
    }
}
